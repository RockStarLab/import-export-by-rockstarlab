<?php
/**
 * Form entries from Gravity Forms, Contact Form 7 (Flamingo) and Fluent Forms as an export source.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;

defined( 'ABSPATH' ) || exit;

/**
 * Reads entries of one form. Columns: entry_id, date_created (site time), status
 * (active, spam or trash), the form fields under their labels, then source_url,
 * ip, user_agent and user. Only entries are read; nothing is written.
 */
class Form_Entries_Service {
	const SOURCES        = [ 'gravityforms', 'flamingo', 'fluentform' ];
	const SKIP_FF_TYPES  = [ 'custom_html', 'section_break', 'recaptcha', 'hcaptcha', 'turnstile', 'shortcode', 'action_hook', 'form_step', 'custom_submit_button', 'cpt_selection' ];
	/** Fluent Forms submission status => export status (read and unread entries are both active). */
	const FF_STATE       = [
		'active' => [ 'unread', 'read' ],
		'spam'   => [ 'spam' ],
		'trash'  => [ 'trashed' ],
	];
	const STATUSES       = [ 'active', 'spam', 'trash' ];
	const BASE_START     = [ 'entry_id', 'date_created', 'status' ];
	const BASE_END       = [ 'source_url', 'ip', 'user_agent', 'user' ];
	const SKIP_GF_TYPES  = [ 'html', 'section', 'page', 'captcha', 'turnstile' ];
	const FLAMINGO_TYPE  = 'flamingo_inbound';
	const FLAMINGO_TAX   = 'flamingo_inbound_channel';
	const FLAMINGO_STATE = [
		'active' => 'publish',
		'spam'   => 'flamingo-spam',
		'trash'  => 'trash',
	];

	/**
	 * Dispatch an ability.
	 *
	 * @param string $name  Ability suffix.
	 * @param array  $input Input.
	 * @return array|\WP_Error
	 */
	public function execute( $name, array $input = [] ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		if ( 'form-entry-forms' === $name ) {
			return [
				'sources' => array_keys( self::available_sources() ),
				'forms'   => $this->forms(),
			];
		}
		if ( 'form-entry-fields' === $name ) {
			$source = (string) ( $input['form_source'] ?? '' );
			$id     = (int) ( $input['form_id'] ?? 0 );
			$fields = $this->fields( $source, $id );
			if ( is_wp_error( $fields ) ) {
				return $fields;
			}
			$out = [];
			foreach ( $fields as $name_key => $label ) {
				$out[] = [
					'name'  => (string) $name_key,
					'label' => (string) $label,
				];
			}
			return [
				'fields'         => $out,
				'default_fields' => $this->default_fields( $source, $id ),
			];
		}
		return $this->error( 'operation', __( 'Unknown operation.', 'import-export-by-rockstarlab' ) );
	}

	/**
	 * Installed form plugins this site can export from.
	 *
	 * @return array source => label
	 */
	public static function available_sources() {
		$out = [];
		if ( class_exists( 'GFAPI' ) ) {
			$out['gravityforms'] = 'Gravity Forms';
		}
		if ( class_exists( 'Flamingo_Inbound_Message' ) && taxonomy_exists( self::FLAMINGO_TAX ) ) {
			$out['flamingo'] = 'Contact Form 7 (Flamingo)';
		}
		if ( defined( 'FLUENTFORM_VERSION' ) && self::ff_table_exists() ) {
			$out['fluentform'] = 'Fluent Forms';
		}
		return $out;
	}

	/**
	 * Forms with their number of entries (all statuses but trash).
	 *
	 * @return array
	 */
	public function forms() {
		$out = [];
		if ( class_exists( 'GFAPI' ) ) {
			foreach ( (array) \GFAPI::get_forms( null, false, 'title' ) as $form ) {
				$out[] = [
					'source'  => 'gravityforms',
					'form_id' => (int) $form['id'],
					'title'   => (string) $form['title'],
					'active'  => ! empty( $form['is_active'] ),
					'entries' => (int) \GFAPI::count_entries( (int) $form['id'], [ 'status' => 'active' ] ),
				];
			}
		}
		if ( isset( self::available_sources()['flamingo'] ) ) {
			$parent = get_term_by( 'slug', 'contact-form-7', self::FLAMINGO_TAX );
			$terms  = get_terms(
				[
					'taxonomy'   => self::FLAMINGO_TAX,
					'hide_empty' => false,
				]
			);
			foreach ( is_wp_error( $terms ) ? [] : $terms as $term ) {
				if ( $parent && (int) $term->term_id === (int) $parent->term_id ) {
					continue; // The "Contact Form 7" group itself is not a form.
				}
				$out[] = [
					'source'  => 'flamingo',
					'form_id' => (int) $term->term_id,
					'title'   => (string) $term->name,
					'active'  => true,
					'entries' => $this->flamingo_count( (int) $term->term_id, [ 'status' => 'active' ] ),
				];
			}
		}
		if ( isset( self::available_sources()['fluentform'] ) ) {
			global $wpdb;
			$forms = $wpdb->get_results( "SELECT id, title, status FROM {$wpdb->prefix}fluentform_forms ORDER BY title ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fluent Forms table, no input.
			foreach ( (array) $forms as $form ) {
				$out[] = [
					'source'  => 'fluentform',
					'form_id' => (int) $form['id'],
					'title'   => (string) $form['title'],
					'active'  => 'published' === (string) $form['status'],
					'entries' => $this->ff_count( (int) $form['id'], [ 'status' => 'active' ] ),
				];
			}
		}
		return $out;
	}

	/**
	 * Columns of a form: key => label. Form field keys are their labels, so the
	 * file reads like the form.
	 *
	 * @param string $source  gravityforms|flamingo|fluentform.
	 * @param int    $form_id Form ID or Flamingo channel term ID.
	 * @return array|\WP_Error
	 */
	public function fields( $source, $form_id ) {
		$map = $this->field_map( $source, $form_id );
		if ( is_wp_error( $map ) ) {
			return $map;
		}
		$labels = self::base_labels( $source );
		$out    = [];
		foreach ( $labels as $key => $label ) {
			if ( in_array( $key, self::BASE_END, true ) ) {
				foreach ( $map as $column => $unused ) {
					$out[ $column ] = $column;
				}
				$map = [];
			}
			$out[ $key ] = $label;
		}
		return $out;
	}

	/**
	 * Headers of the entry columns that are not form fields.
	 *
	 * @param string $source Source.
	 * @return array key => label
	 */
	private static function base_labels( $source ) {
		$labels = [
			'entry_id'     => __( 'Entry ID', 'import-export-by-rockstarlab' ),
			'date_created' => __( 'Date', 'import-export-by-rockstarlab' ),
			'status'       => __( 'Status', 'import-export-by-rockstarlab' ),
			'source_url'   => __( 'Page URL', 'import-export-by-rockstarlab' ),
			'ip'           => __( 'IP address', 'import-export-by-rockstarlab' ),
			'user_agent'   => __( 'Browser (user agent)', 'import-export-by-rockstarlab' ),
			'user'         => __( 'WordPress user', 'import-export-by-rockstarlab' ),
		];
		if ( 'flamingo' === $source ) {
			$labels = array_slice( $labels, 0, 3, true ) + [
				'subject'    => __( 'Subject', 'import-export-by-rockstarlab' ),
				'from_name'  => __( 'From name', 'import-export-by-rockstarlab' ),
				'from_email' => __( 'From email', 'import-export-by-rockstarlab' ),
			] + array_slice( $labels, 3, null, true );
		}
		return $labels;
	}

	/**
	 * Columns exported when none are chosen: everything except IP, browser and user.
	 *
	 * @param string $source  Source.
	 * @param int    $form_id Form.
	 * @return array|\WP_Error
	 */
	public function default_fields( $source, $form_id ) {
		$fields = $this->fields( $source, $form_id );
		return is_wp_error( $fields ) ? $fields : array_values( array_diff( array_keys( $fields ), [ 'ip', 'user_agent', 'user' ] ) );
	}

	/**
	 * Number of entries matching the filters.
	 *
	 * @param string $source  Source.
	 * @param int    $form_id Form.
	 * @param array  $filters Export filters.
	 * @return int|\WP_Error
	 */
	public function count( $source, $form_id, array $filters = [] ) {
		$query = $this->query( $source, $form_id, $filters );
		if ( is_wp_error( $query ) ) {
			return $query;
		}
		if ( 'gravityforms' === $source ) {
			return (int) \GFAPI::count_entries( (int) $form_id, $query );
		}
		if ( 'fluentform' === $source ) {
			return $this->ff_count( (int) $form_id, $query );
		}
		return $this->flamingo_count( (int) $form_id, $query );
	}

	/**
	 * Entries as rows, oldest first.
	 *
	 * @param string $source  Source.
	 * @param int    $form_id Form.
	 * @param array  $filters Filters.
	 * @param int    $offset  Offset.
	 * @param int    $limit   Limit (-1 = all, capped at 5000).
	 * @return array|\WP_Error
	 */
	public function rows( $source, $form_id, array $filters, $offset, $limit ) {
		$query = $this->query( $source, $form_id, $filters );
		if ( is_wp_error( $query ) ) {
			return $query;
		}
		$limit = $limit > 0 ? min( 5000, (int) $limit ) : 5000;
		$map   = $this->field_map( $source, $form_id );
		if ( is_wp_error( $map ) ) {
			return $map;
		}
		if ( 'fluentform' === $source ) {
			return $this->ff_rows( (int) $form_id, $query, $map, max( 0, (int) $offset ), $limit );
		}
		return 'gravityforms' === $source ? $this->gf_rows( (int) $form_id, $query, $map, max( 0, (int) $offset ), $limit ) : $this->flamingo_rows( (int) $form_id, $query, $map, max( 0, (int) $offset ), $limit );
	}

	/**
	 * Check a source and form before use.
	 *
	 * @param string $source  Source.
	 * @param int    $form_id Form.
	 * @return true|\WP_Error
	 */
	public function check_form( $source, $form_id ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		if ( ! isset( self::available_sources()[ $source ] ) ) {
			return $this->error( 'form_source_unavailable', __( 'Choose Gravity Forms, Contact Form 7 (Flamingo) or Fluent Forms; the plugin must be active.', 'import-export-by-rockstarlab' ) );
		}
		if ( 'gravityforms' === $source ) {
			$form = \GFAPI::get_form( (int) $form_id );
			return $form ? true : $this->error( 'form_not_found', __( 'Form not found.', 'import-export-by-rockstarlab' ), 404 );
		}
		if ( 'fluentform' === $source ) {
			return null !== self::ff_form( (int) $form_id ) ? true : $this->error( 'form_not_found', __( 'Form not found.', 'import-export-by-rockstarlab' ), 404 );
		}
		$term = get_term( (int) $form_id, self::FLAMINGO_TAX );
		return $term && ! is_wp_error( $term ) ? true : $this->error( 'form_not_found', __( 'Form not found.', 'import-export-by-rockstarlab' ), 404 );
	}

	/**
	 * Form field columns: column => gravity field ID or Flamingo field name.
	 *
	 * @param string $source  Source.
	 * @param int    $form_id Form.
	 * @return array|\WP_Error
	 */
	private function field_map( $source, $form_id ) {
		$check = $this->check_form( $source, $form_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		// Column headers of the entry columns are taken too: a form field labelled "Date" must not
		// share the header of the entry date.
		$lower = static function ( $s ) { return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s ); };
		// Subject and From are entry columns of Flamingo only; a Fluent Forms field named Subject keeps its name.
		$taken = array_map( $lower, array_merge( self::BASE_START, self::BASE_END, 'fluentform' === $source ? [] : [ 'subject', 'from_name', 'from_email' ], array_values( self::base_labels( $source ) ) ) );
		$out   = [];
		$add   = static function ( $label, $ref ) use ( &$out, $taken, $lower ) {
			$label = trim( wp_strip_all_tags( (string) $label ) );
			$label = '' === $label ? 'Field ' . $ref : $label;
			$label = function_exists( 'mb_substr' ) ? mb_substr( $label, 0, 80 ) : substr( $label, 0, 80 );
			$key   = $label;
			for ( $n = 2; isset( $out[ $key ] ) || in_array( $lower( $key ), $taken, true ); $n++ ) {
				$key = $label . ' (' . $n . ')';
			}
			$out[ $key ] = $ref;
		};
		if ( 'gravityforms' === $source ) {
			$form = \GFAPI::get_form( (int) $form_id );
			foreach ( (array) ( $form['fields'] ?? [] ) as $field ) {
				if ( ! is_object( $field ) || in_array( $field->type, self::SKIP_GF_TYPES, true ) ) {
					continue;
				}
				$add( ! empty( $field->adminLabel ) ? $field->adminLabel : $field->label, (string) $field->id ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Gravity Forms property.
			}
			return $out;
		}
		if ( 'fluentform' === $source ) {
			$form   = self::ff_form( (int) $form_id );
			$fields = json_decode( (string) ( $form['form_fields'] ?? '' ), true );
			foreach ( self::ff_inputs( is_array( $fields ) ? (array) ( $fields['fields'] ?? [] ) : [] ) as $name => $label ) {
				$add( $label, $name );
			}
			return $out;
		}
		// Flamingo keeps no form definition: collect field names from recent messages.
		$ids = get_posts(
			[
				'post_type'      => self::FLAMINGO_TYPE,
				'post_status'    => array_values( self::FLAMINGO_STATE ),
				'tax_query'      => [ $this->channel_query( $form_id ) ], // phpcs:ignore WordPress.DB.SlowDBQuery -- One channel.
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
			]
		);
		$names = [];
		foreach ( array_reverse( $ids ) as $id ) {
			$fields = get_post_meta( $id, '_fields', true );
			foreach ( is_array( $fields ) ? array_keys( $fields ) : [] as $name ) {
				$names[ (string) $name ] = true;
			}
		}
		foreach ( array_keys( $names ) as $name ) {
			$add( $name, $name );
		}
		return $out;
	}

	/**
	 * Turn export filters into a Gravity Forms search or Flamingo query description.
	 *
	 * @param string $source  Source.
	 * @param int    $form_id Form.
	 * @param array  $filters Filters (field, condition, value).
	 * @return array|\WP_Error ['status' => ..., 'from' => ..., 'to' => ..., 'ids' => [...], 'fields' => [...]]
	 */
	private function query( $source, $form_id, array $filters ) {
		$map = $this->field_map( $source, $form_id );
		if ( is_wp_error( $map ) ) {
			return $map;
		}
		$q = [
			'status' => 'active',
			'from'   => '',
			'to'     => '',
			'ids'    => [],
			'fields' => [],
		];
		foreach ( $filters as $filter ) {
			if ( ! is_array( $filter ) || ! isset( $filter['field'], $filter['condition'] ) ) {
				continue;
			}
			$field = (string) $filter['field'];
			$cond  = (string) $filter['condition'];
			$value = $filter['value'] ?? '';
			$value = is_array( $value ) ? implode( ',', array_map( 'strval', $value ) ) : (string) $value;
			if ( 'status' === $field ) {
				$values = array_values( array_filter( array_map( 'trim', explode( ',', strtolower( $value ) ) ) ) );
				if ( ! in_array( $cond, [ 'equals', 'in' ], true ) || 1 !== count( $values ) || ! in_array( $values[0], self::STATUSES, true ) ) {
					return $this->error( 'form_status_filter', __( 'Filter Status by one value: active, spam or trash.', 'import-export-by-rockstarlab' ) );
				}
				$q['status'] = $values[0];
			} elseif ( 'date_created' === $field ) {
				$range = $this->date_range( $cond, $value );
				if ( is_wp_error( $range ) ) {
					return $range;
				}
				$q['from'] = max( $q['from'], $range[0] );
				$q['to']   = '' === $q['to'] ? $range[1] : ( '' === $range[1] ? $q['to'] : min( $q['to'], $range[1] ) );
			} elseif ( 'entry_id' === $field ) {
				if ( ! in_array( $cond, [ 'equals', 'in' ], true ) ) {
					return $this->error( 'form_id_filter', __( 'Filter Entry ID with "equals" or "in".', 'import-export-by-rockstarlab' ) );
				}
				$q['ids'] = array_values( array_filter( array_map( 'absint', explode( ',', $value ) ) ) );
				if ( ! $q['ids'] ) {
					$q['ids'] = [ 0 ];
				}
			} elseif ( isset( $map[ $field ] ) || ( 'flamingo' === $source && in_array( $field, [ 'subject', 'from_name', 'from_email' ], true ) ) ) {
				if ( ! in_array( $cond, [ 'equals', 'not_equals', 'contains', 'not_contains' ], true ) ) {
					return $this->error( 'form_field_filter', __( 'Filter form fields with equals, not equals, contains or does not contain.', 'import-export-by-rockstarlab' ) );
				}
				$q['fields'][] = [
					'ref'       => $map[ $field ] ?? $field,
					'condition' => $cond,
					'value'     => $value,
				];
			} else {
				/* translators: %s: column name. */
				return $this->error( 'form_filter_field', sprintf( __( 'This form cannot be filtered by "%s".', 'import-export-by-rockstarlab' ), $field ) );
			}
		}
		if ( 'flamingo' === $source || 'fluentform' === $source ) {
			return $q;
		}
		$search = [ 'status' => $q['status'] ];
		if ( '' !== $q['from'] ) {
			$search['start_date'] = $q['from'];
		}
		if ( '' !== $q['to'] ) {
			$search['end_date'] = $q['to'];
		}
		$ops = [
			'equals'       => 'is',
			'not_equals'   => 'isnot',
			'contains'     => 'contains',
			'not_contains' => 'not contains',
		];
		$field_filters = [];
		if ( $q['ids'] ) {
			$field_filters[] = [
				'key'      => 'id',
				'operator' => 'in',
				'value'    => $q['ids'],
			];
		}
		foreach ( $q['fields'] as $f ) {
			$field_filters[] = [
				'key'      => $f['ref'],
				'operator' => $ops[ $f['condition'] ],
				'value'    => $f['value'],
			];
		}
		if ( $field_filters ) {
			$field_filters['mode']   = 'all';
			$search['field_filters'] = $field_filters;
		}
		return $search;
	}

	/**
	 * Date filter to a local "from"/"to" pair (Y-m-d H:i:s, inclusive).
	 *
	 * @param string $cond  Condition.
	 * @param string $value Value: a date, or "from,to" for between.
	 * @return array|\WP_Error
	 */
	private function date_range( $cond, $value ) {
		$parse = static function ( $v, $end ) {
			$v = trim( (string) $v );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
				return $v . ( $end ? ' 23:59:59' : ' 00:00:00' );
			}
			return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $v ) ? ( 16 === strlen( $v ) ? $v . ':00' : $v ) : null;
		};
		$parts = array_map( 'trim', explode( ',', $value ) );
		switch ( $cond ) {
			case 'equals':
				$from = $parse( $parts[0], false );
				$to   = $parse( $parts[0], true );
				break;
			case 'greater':
				$end  = $parse( $parts[0], true );
				// One second after, so "greater than 2026-10-05" starts on 2026-10-06.
				$from = null === $end ? null : gmdate( 'Y-m-d H:i:s', strtotime( $end . ' UTC' ) + 1 );
				$to   = '';
				break;
			case 'equals_or_greater':
				$from = $parse( $parts[0], false );
				$to   = '';
				break;
			case 'less':
				$from  = '';
				$start = $parse( $parts[0], false );
				// One second before, so "less than 2026-10-01" ends at 2026-09-30 23:59:59.
				$to = null === $start ? null : gmdate( 'Y-m-d H:i:s', strtotime( $start . ' UTC' ) - 1 );
				break;
			case 'equals_or_less':
				$from = '';
				$to   = $parse( $parts[0], true );
				break;
			case 'between':
				$from = $parse( $parts[0], false );
				$to   = $parse( $parts[1] ?? '', true );
				break;
			default:
				$from = null;
				$to   = null;
		}
		if ( null === $from || null === $to ) {
			return $this->error( 'form_date_filter', __( 'Filter Date with a date like 2026-10-01 (between takes two dates separated by a comma).', 'import-export-by-rockstarlab' ) );
		}
		return [ $from, $to ];
	}

	/**
	 * Gravity Forms rows.
	 *
	 * @param int   $form_id Form.
	 * @param array $search  Search criteria.
	 * @param array $map     Column => field ID.
	 * @param int   $offset  Offset.
	 * @param int   $limit   Limit.
	 * @return array
	 */
	private function gf_rows( $form_id, array $search, array $map, $offset, $limit ) {
		$form    = \GFAPI::get_form( $form_id );
		$entries = \GFAPI::get_entries(
			$form_id,
			$search,
			[
				'key'       => 'id',
				'direction' => 'ASC',
			],
			[
				'offset'    => $offset,
				'page_size' => $limit,
			]
		);
		$rows    = [];
		foreach ( is_wp_error( $entries ) ? [] : $entries as $entry ) {
			$row = [
				'entry_id'     => (int) $entry['id'],
				'date_created' => get_date_from_gmt( (string) $entry['date_created'], 'Y-m-d H:i:s' ),
				'status'       => (string) $entry['status'],
			];
			foreach ( $map as $column => $field_id ) {
				$field          = \GFAPI::get_field( $form, $field_id );
				$row[ $column ] = $field ? $this->text( $field->get_value_export( $entry, (string) $field_id, false, true ) ) : '';
			}
			$user        = ! empty( $entry['created_by'] ) ? get_userdata( (int) $entry['created_by'] ) : false;
			$row        += [
				'source_url' => (string) ( $entry['source_url'] ?? '' ),
				'ip'         => (string) ( $entry['ip'] ?? '' ),
				'user_agent' => (string) ( $entry['user_agent'] ?? '' ),
				'user'       => $user ? $user->user_login : '',
			];
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Flamingo messages as rows.
	 *
	 * @param int   $term_id Channel.
	 * @param array $q       Query description.
	 * @param array $map     Column => field name.
	 * @param int   $offset  Offset.
	 * @param int   $limit   Limit.
	 * @return array
	 */
	private function flamingo_rows( $term_id, array $q, array $map, $offset, $limit ) {
		$args  = $this->flamingo_args( $term_id, $q ) + [
			'posts_per_page' => $limit,
			'offset'         => $offset,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		];
		$posts = get_posts( $args );
		$state = array_flip( self::FLAMINGO_STATE );
		$rows  = [];
		foreach ( $posts as $post ) {
			$meta = get_post_meta( $post->ID, '_meta', true );
			$meta = is_array( $meta ) ? $meta : [];
			$row  = [
				'entry_id'     => (int) $post->ID,
				'date_created' => (string) $post->post_date,
				'status'       => $state[ $post->post_status ] ?? $post->post_status,
				'subject'      => $this->text( get_post_meta( $post->ID, '_subject', true ) ),
				'from_name'    => $this->text( get_post_meta( $post->ID, '_from_name', true ) ),
				'from_email'   => $this->text( get_post_meta( $post->ID, '_from_email', true ) ),
			];
			foreach ( $map as $column => $name ) {
				$row[ $column ] = $this->text( get_post_meta( $post->ID, '_field_' . $name, true ) );
			}
			$row   += [
				'source_url' => (string) ( ! empty( $meta['post_url'] ) ? $meta['post_url'] : ( $meta['url'] ?? '' ) ),
				'ip'         => (string) ( $meta['remote_ip'] ?? '' ),
				'user_agent' => (string) ( $meta['user_agent'] ?? '' ),
				'user'       => (string) ( $meta['user_login'] ?? '' ),
			];
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Count Flamingo messages.
	 *
	 * @param int   $term_id Channel.
	 * @param array $q       Query description (status, from, to, ids, fields).
	 * @return int
	 */
	private function flamingo_count( $term_id, array $q ) {
		$q     += [
			'from'   => '',
			'to'     => '',
			'ids'    => [],
			'fields' => [],
		];
		$query  = new \WP_Query(
			$this->flamingo_args( $term_id, $q ) + [
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);
		return (int) $query->found_posts;
	}

	/**
	 * WP_Query arguments for one Flamingo channel.
	 *
	 * @param int   $term_id Channel.
	 * @param array $q       Query description.
	 * @return array
	 */
	private function flamingo_args( $term_id, array $q ) {
		$args = [
			'post_type'        => self::FLAMINGO_TYPE,
			'post_status'      => self::FLAMINGO_STATE[ $q['status'] ?? 'active' ],
			'tax_query'        => [ $this->channel_query( $term_id ) ], // phpcs:ignore WordPress.DB.SlowDBQuery -- One channel.
			'suppress_filters' => true,
		];
		if ( '' !== $q['from'] || '' !== $q['to'] ) {
			$date = [ 'inclusive' => true ];
			if ( '' !== $q['from'] ) {
				$date['after'] = $q['from'];
			}
			if ( '' !== $q['to'] ) {
				$date['before'] = $q['to'];
			}
			$args['date_query'] = [ $date ];
		}
		if ( $q['ids'] ) {
			$args['post__in'] = $q['ids'];
		}
		$meta = [];
		foreach ( $q['fields'] as $f ) {
			$key    = in_array( $f['ref'], [ 'subject', 'from_name', 'from_email' ], true ) ? '_' . $f['ref'] : '_field_' . $f['ref'];
			$meta[] = [
				'key'     => $key,
				'value'   => $f['value'],
				'compare' => [
					'equals'       => '=',
					'not_equals'   => '!=',
					'contains'     => 'LIKE',
					'not_contains' => 'NOT LIKE',
				][ $f['condition'] ],
			];
		}
		if ( $meta ) {
			$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery -- Optional filter.
		}
		return $args;
	}

	/** Whether the Fluent Forms submissions table exists. */
	private static function ff_table_exists() {
		global $wpdb;
		$table = $wpdb->prefix . 'fluentform_submissions';
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/** One Fluent Forms form row, or null. */
	private static function ff_form( $form_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, title, form_fields FROM {$wpdb->prefix}fluentform_forms WHERE id = %d", $form_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * Input fields of a Fluent Forms form in form order: name => label (columns and steps opened up).
	 *
	 * @param array $fields Form elements.
	 * @return array
	 */
	private static function ff_inputs( array $fields ) {
		$out = [];
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			foreach ( (array) ( $field['columns'] ?? [] ) as $column ) {
				$out += self::ff_inputs( (array) ( $column['fields'] ?? [] ) );
			}
			$name = (string) ( $field['attributes']['name'] ?? '' );
			if ( '' === $name || in_array( (string) ( $field['element'] ?? '' ), self::SKIP_FF_TYPES, true ) ) {
				continue;
			}
			$settings     = (array) ( $field['settings'] ?? [] );
			$label        = (string) ( ! empty( $settings['admin_field_label'] ) ? $settings['admin_field_label'] : ( $settings['label'] ?? '' ) );
			$out[ $name ] = '' !== trim( wp_strip_all_tags( $label ) ) ? $label : $name;
		}
		return $out;
	}

	/**
	 * SQL WHERE (with values) for Fluent Forms submissions of one form.
	 *
	 * @param int   $form_id Form.
	 * @param array $q       Query description (status, from, to, ids, fields).
	 * @return array [ sql, values ]
	 */
	private function ff_where( $form_id, array $q ) {
		global $wpdb;
		$q     += [ 'status' => 'active', 'from' => '', 'to' => '', 'ids' => [], 'fields' => [] ];
		$states = self::FF_STATE[ $q['status'] ] ?? self::FF_STATE['active'];
		$sql    = 's.form_id = %d AND s.status IN (' . implode( ',', array_fill( 0, count( $states ), '%s' ) ) . ')';
		$vals   = array_merge( [ (int) $form_id ], $states );
		// created_at is stored in site time.
		if ( '' !== $q['from'] ) {
			$sql   .= ' AND s.created_at >= %s';
			$vals[] = $q['from'];
		}
		if ( '' !== $q['to'] ) {
			$sql   .= ' AND s.created_at <= %s';
			$vals[] = $q['to'];
		}
		if ( $q['ids'] ) {
			$sql .= ' AND s.id IN (' . implode( ',', array_map( 'absint', $q['ids'] ) ) . ')';
		}
		$ops = [ 'equals' => '= %s', 'not_equals' => '= %s', 'contains' => 'LIKE %s', 'not_contains' => 'LIKE %s' ];
		foreach ( $q['fields'] as $f ) {
			$value  = in_array( $f['condition'], [ 'contains', 'not_contains' ], true ) ? '%' . $wpdb->esc_like( $f['value'] ) . '%' : $f['value'];
			$not    = in_array( $f['condition'], [ 'not_equals', 'not_contains' ], true ) ? 'NOT ' : '';
			$sql   .= " AND {$not}EXISTS (SELECT 1 FROM {$wpdb->prefix}fluentform_entry_details d WHERE d.submission_id = s.id AND d.field_name = %s AND d.field_value " . $ops[ $f['condition'] ] . ')';
			$vals[] = (string) $f['ref'];
			$vals[] = $value;
		}
		return [ $sql, $vals ];
	}

	/** Number of Fluent Forms entries matching a query description. */
	private function ff_count( $form_id, array $q ) {
		global $wpdb;
		list( $where, $vals ) = $this->ff_where( $form_id, $q );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}fluentform_submissions s WHERE $where", $vals ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Fluent Forms entries as rows.
	 *
	 * @param int   $form_id Form.
	 * @param array $q       Query description.
	 * @param array $map     Column => field name.
	 * @param int   $offset  Offset.
	 * @param int   $limit   Limit.
	 * @return array
	 */
	private function ff_rows( $form_id, array $q, array $map, $offset, $limit ) {
		global $wpdb;
		list( $where, $vals ) = $this->ff_where( $form_id, $q );
		$vals[]  = (int) $limit;
		$vals[]  = (int) $offset;
		$entries = $wpdb->get_results( $wpdb->prepare( "SELECT s.* FROM {$wpdb->prefix}fluentform_submissions s WHERE $where ORDER BY s.id ASC LIMIT %d OFFSET %d", $vals ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$state   = [];
		foreach ( self::FF_STATE as $export => $list ) {
			foreach ( $list as $one ) {
				$state[ $one ] = $export;
			}
		}
		$rows = [];
		foreach ( (array) $entries as $entry ) {
			$response = json_decode( (string) $entry['response'], true );
			$response = is_array( $response ) ? $response : [];
			$row      = [
				'entry_id'     => (int) $entry['id'],
				'date_created' => (string) $entry['created_at'],
				'status'       => $state[ (string) $entry['status'] ] ?? (string) $entry['status'],
			];
			foreach ( $map as $column => $name ) {
				$value = $response[ $name ] ?? '';
				// A name field keeps first, middle and last name apart: one cell reads "Nino Beridze".
				if ( is_array( $value ) && array_intersect( array_keys( $value ), [ 'first_name', 'middle_name', 'last_name' ] ) ) {
					$value = implode( ' ', array_filter( array_map( 'strval', array_filter( $value, 'is_scalar' ) ), 'strlen' ) );
				}
				$row[ $column ] = $this->text( $value );
			}
			$user   = ! empty( $entry['user_id'] ) ? get_userdata( (int) $entry['user_id'] ) : false;
			$row   += [
				'source_url' => (string) ( $entry['source_url'] ?? '' ),
				'ip'         => (string) ( $entry['ip'] ?? '' ),
				// Fluent Forms keeps the browser and device names, not the full user agent.
				'user_agent' => implode( ', ', array_diff( array_filter( [ (string) ( $entry['browser'] ?? '' ), (string) ( $entry['device'] ?? '' ) ], 'strlen' ), [ 'unknown' ] ) ),
				'user'       => $user ? $user->user_login : '',
			];
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Tax query for one channel (not its children).
	 *
	 * @param int $term_id Channel.
	 * @return array
	 */
	private function channel_query( $term_id ) {
		return [
			'taxonomy'         => self::FLAMINGO_TAX,
			'field'            => 'term_id',
			'terms'            => [ (int) $term_id ],
			'include_children' => false,
		];
	}

	/**
	 * Flatten a stored value to text.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function text( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_map( [ $this, 'text' ], $value ) );
		}
		return is_scalar( $value ) ? wp_specialchars_decode( (string) $value, ENT_QUOTES ) : '';
	}

	/**
	 * Error with status.
	 *
	 * @param string $code    Code suffix.
	 * @param string $message Message.
	 * @param int    $status  Status.
	 * @return \WP_Error
	 */
	private function error( $code, $message, $status = 400 ) {
		return new \WP_Error( 'rsl_ie_' . $code, $message, [ 'status' => $status ] );
	}
}
