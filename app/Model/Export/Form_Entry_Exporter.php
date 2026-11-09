<?php
/**
 * Form entries exporter (Gravity Forms, Contact Form 7 via Flamingo).
 *
 * @package RockStarLab\ImportExport\Model\Export
 */

namespace RockStarLab\ImportExport\Model\Export;

use RockStarLab\ImportExport\Service\Form_Entries_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Options: form_source (gravityforms|flamingo), form_id, filters, fields.
 */
class Form_Entry_Exporter extends Abstract_Exporter {

	/**
	 * Service.
	 *
	 * @var Form_Entries_Service
	 */
	private $service;

	/**
	 * Constructor.
	 *
	 * @param int $job_id Job.
	 */
	public function __construct( $job_id = 0 ) {
		parent::__construct( $job_id );
		$this->service = new Form_Entries_Service();
	}

	/** @return string */
	public function get_name() {
		return 'form_entries';
	}

	/** @return string */
	public function get_description() {
		return __( 'Export entries of a Gravity Forms or Contact Form 7 (Flamingo) form', 'import-export-by-rockstarlab' );
	}

	/** @return array */
	public function get_supported_filters() {
		return [
			'form_source'  => __( 'gravityforms or flamingo (required)', 'import-export-by-rockstarlab' ),
			'form_id'      => __( 'Gravity Forms form ID, or the Flamingo channel ID of a Contact Form 7 form (required; see form-entry-forms)', 'import-export-by-rockstarlab' ),
			'status'       => __( 'Filter field "status" equals active (default), spam or trash', 'import-export-by-rockstarlab' ),
			'date_created' => __( 'Filter field "date_created" with greater, less, equals_or_greater, equals_or_less, equals or between ("2026-10-01,2026-10-31"), in site time', 'import-export-by-rockstarlab' ),
			'entry_id'     => __( 'Filter field "entry_id" with in (comma-separated IDs)', 'import-export-by-rockstarlab' ),
			'form fields'  => __( 'Filter a form field by its column name with equals, not_equals, contains or not_contains', 'import-export-by-rockstarlab' ),
		];
	}

	/** @return array */
	public function get_available_fields() {
		$fields = $this->form_fields();
		return is_wp_error( $fields ) ? array_merge( Form_Entries_Service::BASE_START, Form_Entries_Service::BASE_END ) : array_keys( $fields );
	}

	/** @return array */
	public function get_default_fields() {
		if ( empty( $this->options['form_source'] ) ) {
			return [ '*' ];
		}
		$fields = $this->service->default_fields( (string) $this->options['form_source'], (int) ( $this->options['form_id'] ?? 0 ) );
		return is_wp_error( $fields ) ? [ '*' ] : $fields;
	}

	/**
	 * Validate options.
	 *
	 * @param array $options Options.
	 * @return true|\WP_Error
	 */
	public function validate_options( $options ) {
		$valid = parent::validate_options( $options );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		return $this->service->check_form( (string) ( $options['form_source'] ?? '' ), (int) ( $options['form_id'] ?? 0 ) );
	}

	/**
	 * Count.
	 *
	 * @param array $options Options.
	 * @return int|\WP_Error
	 */
	public function get_count( $options = [] ) {
		return $this->service->count( (string) ( $options['form_source'] ?? '' ), (int) ( $options['form_id'] ?? 0 ), (array) ( $options['filters'] ?? [] ) );
	}

	/**
	 * Rows.
	 *
	 * @param array $options Options.
	 * @return array|\WP_Error
	 */
	public function get_data( $options = [] ) {
		$this->options = $options + $this->options;
		return $this->service->rows( (string) ( $options['form_source'] ?? '' ), (int) ( $options['form_id'] ?? 0 ), (array) ( $options['filters'] ?? [] ), (int) ( $options['offset'] ?? 0 ), (int) ( $options['limit'] ?? -1 ) );
	}

	/**
	 * Default columns once the form is known, then the shared processing.
	 * Values typed by visitors that start with = + - @ are prefixed with ' in CSV
	 * files so a spreadsheet does not run them as formulas.
	 *
	 * @param mixed $item  Row.
	 * @param int   $index Index.
	 * @return mixed
	 */
	protected function process_item( $item, $index ) {
		if ( empty( $this->options['fields'] ) || [ '*' ] === $this->options['fields'] ) {
			$this->options['fields'] = $this->get_default_fields();
		}
		if ( is_array( $item ) && ! empty( $this->options['csv_safe'] ) ) {
			foreach ( $item as $key => $value ) {
				if ( is_string( $value ) && '' !== $value && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
					$item[ $key ] = "'" . $value;
				}
			}
		}
		return parent::process_item( $item, $index );
	}

	/**
	 * Columns of the selected form.
	 *
	 * @return array|\WP_Error
	 */
	private function form_fields() {
		if ( empty( $this->options['form_source'] ) ) {
			return new \WP_Error( 'rsl_ie_form_source_unavailable', '' );
		}
		return $this->service->fields( (string) $this->options['form_source'], (int) ( $this->options['form_id'] ?? 0 ) );
	}
}
