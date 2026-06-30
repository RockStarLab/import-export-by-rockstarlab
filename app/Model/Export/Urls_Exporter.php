<?php
/**
 * URLs Exporter
 *
 * Exports frontend URLs for public WordPress post types.
 *
 * @package RockStarLab\ImportExport\Model\Export
 */

namespace RockStarLab\ImportExport\Model\Export;

defined( 'ABSPATH' ) || exit;

class Urls_Exporter extends Abstract_Exporter {

	/**
	 * Get exportable public post types that have frontend URLs.
	 *
	 * @return array<string,\WP_Post_Type>
	 */
	public static function get_exportable_post_types() {
		$post_types = get_post_types(
			[
				'public' => true,
			],
			'objects'
		);

		$result = [];
		foreach ( $post_types as $post_type ) {
			if ( ! self::is_exportable_post_type( $post_type ) ) {
				continue;
			}

			$result[ $post_type->name ] = $post_type;
		}

		uasort(
			$result,
			static function ( $a, $b ) {
				return strcasecmp( $a->label, $b->label );
			}
		);

		return $result;
	}

	/**
	 * Count published/frontend-visible URLs for one post type.
	 *
	 * @param string $post_type Post type.
	 * @return int
	 */
	public static function count_for_post_type( $post_type ) {
		$post_type = sanitize_key( $post_type );
		if ( ! self::is_post_type_name_exportable( $post_type ) ) {
			return 0;
		}

		$query = new \WP_Query(
			[
				'post_type'              => $post_type,
				'post_status'            => self::get_public_statuses_for_type( $post_type ),
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		return (int) $query->found_posts;
	}

	/**
	 * Get exportable public taxonomies that have frontend term archive URLs.
	 *
	 * @return array<string,\WP_Taxonomy>
	 */
	public static function get_exportable_taxonomies() {
		$taxonomies = get_taxonomies(
			[
				'public' => true,
			],
			'objects'
		);

		$result = [];
		foreach ( $taxonomies as $taxonomy ) {
			if ( ! self::is_exportable_taxonomy( $taxonomy ) ) {
				continue;
			}

			$result[ $taxonomy->name ] = $taxonomy;
		}

		uasort(
			$result,
			static function ( $a, $b ) {
				return strcasecmp( $a->label, $b->label );
			}
		);

		return $result;
	}

	/**
	 * Count terms with frontend-visible URLs for one taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return int
	 */
	public static function count_for_taxonomy( $taxonomy ) {
		$taxonomy = sanitize_key( $taxonomy );
		if ( ! self::is_taxonomy_name_exportable( $taxonomy ) ) {
			return 0;
		}

		$count = wp_count_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $count ) ) {
			return 0;
		}

		return (int) $count;
	}

	/**
	 * Count URLs for a generated source.
	 *
	 * @param string $kind Source kind.
	 * @param string $name Source name.
	 * @return int
	 */
	public static function count_for_generated_source( $kind, $name ) {
		$kind = sanitize_key( $kind );
		$name = sanitize_key( $name );

		if ( ! self::is_supported_generated_source( $kind, $name ) ) {
			return 0;
		}

		return count( self::get_generated_urls_for_source( $kind, $name ) );
	}

	/**
	 * Get exporter name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'urls';
	}

	/**
	 * Get exporter description.
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export frontend URLs for public content types', 'import-export-by-rockstarlab' );
	}

	/**
	 * Get supported export filters.
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'content_types' => __( 'Public post types and taxonomies to include', 'import-export-by-rockstarlab' ),
		];
	}

	/**
	 * Get available fields.
	 *
	 * @return array
	 */
	public function get_available_fields() {
		return [
			'url',
		];
	}

	/**
	 * Get default fields.
	 *
	 * @return array
	 */
	public function get_default_fields() {
		return $this->get_available_fields();
	}

	/**
	 * Validate options.
	 *
	 * @param array $options Options.
	 * @return true|\WP_Error
	 */
	public function validate_options( $options ) {
		$validation = parent::validate_options( $options );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( isset( $options['content_types'] ) && ! is_array( $options['content_types'] ) ) {
			return new \WP_Error( 'invalid_content_types', __( 'Content types must be an array.', 'import-export-by-rockstarlab' ) );
		}

		return true;
	}

	/**
	 * Get total count.
	 *
	 * @param array $options Options.
	 * @return int
	 */
	public function get_count( $options = [] ) {
		$total     = 0;
		$selection = $this->get_selected_url_sources( $options );

		foreach ( $selection['post_types'] as $post_type ) {
			$total += self::count_for_post_type( $post_type );
		}

		foreach ( $selection['taxonomies'] as $taxonomy ) {
			$total += self::count_for_taxonomy( $taxonomy );
		}

		foreach ( $selection['generated_sources'] as $source ) {
			$total += count( self::get_generated_urls_for_source( $source['kind'], $source['name'] ) );
		}

		return $total;
	}

	/**
	 * Get data.
	 *
	 * @param array $options Options.
	 * @return array
	 */
	public function get_data( $options = [] ) {
		$limit     = isset( $options['limit'] ) ? (int) $options['limit'] : -1;
		$offset    = isset( $options['offset'] ) ? max( 0, (int) $options['offset'] ) : 0;
		$remaining = $limit > 0 ? $limit : -1;
		$skip      = $offset;
		$items     = [];
		$selection = $this->get_selected_url_sources( $options );

		foreach ( $selection['post_types'] as $post_type ) {
			$type_count = self::count_for_post_type( $post_type );

			if ( $skip >= $type_count ) {
				$skip -= $type_count;
				continue;
			}

			$query_args = [
				'post_type'              => $post_type,
				'post_status'            => self::get_public_statuses_for_type( $post_type ),
				'posts_per_page'         => $remaining > 0 ? $remaining : -1,
				'offset'                 => $skip,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			];

			$query = new \WP_Query( $query_args );
			foreach ( $query->posts as $post ) {
				$url = get_permalink( $post );
				if ( ! $url ) {
					continue;
				}

				$items[] = [
					'url' => $url,
				];

				if ( $remaining > 0 ) {
					--$remaining;
					if ( 0 === $remaining ) {
						break 2;
					}
				}
			}

			$skip = 0;
		}

		if ( 0 === $remaining ) {
			return $items;
		}

		foreach ( $selection['taxonomies'] as $taxonomy ) {
			$type_count = self::count_for_taxonomy( $taxonomy );

			if ( $skip >= $type_count ) {
				$skip -= $type_count;
				continue;
			}

			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'orderby'    => 'term_id',
					'order'      => 'ASC',
					'offset'     => $skip,
					'number'     => $remaining > 0 ? $remaining : 0,
				]
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				$skip = 0;
				continue;
			}

			foreach ( $terms as $term ) {
				$url = get_term_link( $term );
				if ( is_wp_error( $url ) || ! $url ) {
					continue;
				}

				$items[] = [
					'url' => $url,
				];

				if ( $remaining > 0 ) {
					--$remaining;
					if ( 0 === $remaining ) {
						break 2;
					}
				}
			}

			$skip = 0;
		}

		if ( 0 === $remaining ) {
			return $items;
		}

		foreach ( $selection['generated_sources'] as $source ) {
			$urls       = self::get_generated_urls_for_source( $source['kind'], $source['name'] );
			$type_count = count( $urls );

			if ( $skip >= $type_count ) {
				$skip -= $type_count;
				continue;
			}

			$urls = array_slice( $urls, $skip, $remaining > 0 ? $remaining : null );
			foreach ( $urls as $url ) {
				if ( ! $url ) {
					continue;
				}

				$items[] = [
					'url' => $url,
				];

				if ( $remaining > 0 ) {
					--$remaining;
					if ( 0 === $remaining ) {
						break 2;
					}
				}
			}

			$skip = 0;
		}

		return $items;
	}

	/**
	 * URLs export always writes only the URL column, even when an older saved job
	 * still contains legacy field settings.
	 *
	 * @param mixed $item  Item data.
	 * @param int   $index Item index.
	 * @return array
	 */
	protected function process_item( $item, $index ) {
		if ( is_array( $item ) && isset( $item['url'] ) ) {
			return [
				'url' => $item['url'],
			];
		}

		return [
			'url' => '',
		];
	}

	/**
	 * Get sanitized selected URL source names.
	 *
	 * @param array $options Options.
	 * @return array{post_types:string[],taxonomies:string[],generated_sources:array<int,array{kind:string,name:string}>}
	 */
	private function get_selected_url_sources( $options ) {
		$selected = $options['content_types'] ?? [];
		if ( empty( $selected ) || ! is_array( $selected ) ) {
			return [
				'post_types'        => array_keys( self::get_exportable_post_types() ),
				'taxonomies'        => array_keys( self::get_exportable_taxonomies() ),
				'generated_sources' => self::get_default_generated_sources(),
			];
		}

		$post_types        = [];
		$taxonomies        = [];
		$generated_sources = [];

		foreach ( $selected as $source ) {
			$source = (string) $source;
			$kind   = 'post_type';
			$name   = $source;

			if ( false !== strpos( $source, ':' ) ) {
				$parts = explode( ':', $source, 2 );
				$kind  = sanitize_key( $parts[0] );
				$name  = $parts[1] ?? '';
			}

			$name = sanitize_key( $name );
			if ( '' === $name ) {
				continue;
			}

			if ( 'taxonomy' === $kind ) {
				if ( self::is_taxonomy_name_exportable( $name ) ) {
					$taxonomies[] = $name;
				}
				continue;
			}

			if ( self::is_supported_generated_source( $kind, $name ) ) {
				$generated_sources[] = [
					'kind' => $kind,
					'name' => $name,
				];
				continue;
			}

			if ( self::is_post_type_name_exportable( $name ) ) {
				$post_types[] = $name;
			}
		}

		return [
			'post_types'        => array_values( array_unique( $post_types ) ),
			'taxonomies'        => array_values( array_unique( $taxonomies ) ),
			'generated_sources' => self::unique_generated_sources( $generated_sources ),
		];
	}

	/**
	 * Get standard finite URL sources selected by default.
	 *
	 * @return array<int,array{kind:string,name:string}>
	 */
	private static function get_default_generated_sources() {
		$sources = [
			[
				'kind' => 'standard',
				'name' => 'homepage',
			],
			[
				'kind' => 'standard',
				'name' => 'authors',
			],
			[
				'kind' => 'standard',
				'name' => 'date_archives',
			],
			[
				'kind' => 'standard',
				'name' => 'search_results',
			],
		];

		foreach ( array_keys( self::get_exportable_post_types() ) as $post_type ) {
			if ( self::is_supported_generated_source( 'post_type_archive', $post_type ) ) {
				$sources[] = [
					'kind' => 'post_type_archive',
					'name' => $post_type,
				];
			}
		}

		return $sources;
	}

	/**
	 * Check whether a generated source token is supported.
	 *
	 * @param string $kind Source kind.
	 * @param string $name Source name.
	 * @return bool
	 */
	private static function is_supported_generated_source( $kind, $name ) {
		if ( 'standard' === $kind ) {
			return in_array( $name, [ 'homepage', 'authors', 'date_archives', 'search_results' ], true );
		}

		if ( 'feed' === $kind ) {
			return in_array( $name, [ 'main', 'atom', 'comments' ], true );
		}

		if ( 'rest' === $kind ) {
			return 'root' === $name;
		}

		if ( in_array( $kind, [ 'post_type_archive', 'post_type_feed', 'rest_post_type' ], true ) ) {
			return self::is_post_type_name_exportable( $name ) && ! empty( self::get_generated_urls_for_source( $kind, $name ) );
		}

		return false;
	}

	/**
	 * Remove duplicate generated sources while preserving order.
	 *
	 * @param array<int,array{kind:string,name:string}> $sources Sources.
	 * @return array<int,array{kind:string,name:string}>
	 */
	private static function unique_generated_sources( $sources ) {
		$result = [];
		$seen   = [];

		foreach ( $sources as $source ) {
			$key = $source['kind'] . ':' . $source['name'];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$result[]     = $source;
		}

		return $result;
	}

	/**
	 * Get generated URLs for finite WordPress URL sources.
	 *
	 * @param string $kind Source kind.
	 * @param string $name Source name.
	 * @return string[]
	 */
	private static function get_generated_urls_for_source( $kind, $name ) {
		if ( 'standard' === $kind ) {
			if ( 'homepage' === $name ) {
				return [ home_url( '/' ) ];
			}

			if ( 'authors' === $name ) {
				return self::get_author_archive_urls();
			}

			if ( 'date_archives' === $name ) {
				return self::get_date_archive_urls();
			}

			if ( 'search_results' === $name ) {
				return [ home_url( '/?s=' ) ];
			}
		}

		if ( 'feed' === $kind ) {
			if ( 'main' === $name ) {
				return [ get_feed_link( 'rss2' ) ];
			}

			if ( 'atom' === $name ) {
				return [ get_feed_link( 'atom' ) ];
			}

			if ( 'comments' === $name ) {
				return [ get_feed_link( 'comments_rss2' ) ];
			}
		}

		if ( 'rest' === $kind && 'root' === $name ) {
			return [ rest_url() ];
		}

		if ( 'post_type_archive' === $kind ) {
			$url = get_post_type_archive_link( $name );
			return $url ? [ $url ] : [];
		}

		if ( 'post_type_feed' === $kind ) {
			$url = self::get_post_type_feed_link( $name );
			return $url ? [ $url ] : [];
		}

		if ( 'rest_post_type' === $kind ) {
			$url = self::get_post_type_rest_url( $name );
			return $url ? [ $url ] : [];
		}

		return [];
	}

	/**
	 * Get author archive URLs for users with published content.
	 *
	 * @return string[]
	 */
	private static function get_author_archive_urls() {
		$user_ids = get_users(
			[
				'fields'              => 'ID',
				'has_published_posts' => array_keys( self::get_exportable_post_types() ),
				'orderby'             => 'ID',
				'order'               => 'ASC',
			]
		);

		return array_values(
			array_filter(
				array_map(
					static function ( $user_id ) {
						return get_author_posts_url( (int) $user_id );
					},
					$user_ids
				)
			)
		);
	}

	/**
	 * Get year and month archive URLs based on published posts.
	 *
	 * @return string[]
	 */
	private static function get_date_archive_urls() {
		$urls = [];
		foreach ( [ 'yearly', 'monthly' ] as $archive_type ) {
			$archive_links = wp_get_archives(
				[
					'type'   => $archive_type,
					'format' => 'custom',
					'echo'   => 0,
					'before' => '',
					'after'  => "\n",
				]
			);

			if ( ! is_string( $archive_links ) || '' === $archive_links ) {
				continue;
			}

			if ( preg_match_all( '/href=[\'"]([^\'"]+)[\'"]/i', $archive_links, $matches ) ) {
				foreach ( $matches[1] as $url ) {
					$urls[] = esc_url_raw( html_entity_decode( $url, ENT_QUOTES ) );
				}
			}
		}

		return array_values( array_unique( array_filter( $urls ) ) );
	}

	/**
	 * Get a post type archive feed URL.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	private static function get_post_type_feed_link( $post_type ) {
		$archive = get_post_type_archive_link( $post_type );
		if ( ! $archive ) {
			return '';
		}

		return add_query_arg( 'feed', 'rss2', $archive );
	}

	/**
	 * Get REST collection URL for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	private static function get_post_type_rest_url( $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object || ! $post_type_object->show_in_rest ) {
			return '';
		}

		$rest_base = $post_type_object->rest_base ? $post_type_object->rest_base : $post_type;

		return rest_url( 'wp/v2/' . $rest_base );
	}

	/**
	 * Check a post type object.
	 *
	 * @param \WP_Post_Type $post_type Post type object.
	 * @return bool
	 */
	private static function is_exportable_post_type( $post_type ) {
		if ( ! $post_type instanceof \WP_Post_Type ) {
			return false;
		}

		if ( in_array( $post_type->name, [ 'revision', 'nav_menu_item', 'wp_navigation' ], true ) ) {
			return false;
		}

		return is_post_type_viewable( $post_type );
	}

	/**
	 * Check a post type name.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private static function is_post_type_name_exportable( $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		return self::is_exportable_post_type( $post_type_object );
	}

	/**
	 * Check a taxonomy object.
	 *
	 * @param \WP_Taxonomy $taxonomy Taxonomy object.
	 * @return bool
	 */
	private static function is_exportable_taxonomy( $taxonomy ) {
		if ( ! $taxonomy instanceof \WP_Taxonomy ) {
			return false;
		}

		if ( 'post_format' === $taxonomy->name ) {
			return false;
		}

		return is_taxonomy_viewable( $taxonomy );
	}

	/**
	 * Check a taxonomy name.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool
	 */
	private static function is_taxonomy_name_exportable( $taxonomy ) {
		$taxonomy_object = get_taxonomy( $taxonomy );
		return self::is_exportable_taxonomy( $taxonomy_object );
	}

	/**
	 * Get statuses that represent frontend URLs for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return string[]
	 */
	private static function get_public_statuses_for_type( $post_type ) {
		if ( 'attachment' === $post_type ) {
			return [ 'inherit' ];
		}

		return [ 'publish' ];
	}
}
