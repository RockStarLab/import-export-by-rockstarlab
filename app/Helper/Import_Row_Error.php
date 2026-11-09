<?php
/**
 * Readable import row errors: row, field, reason and how to fix it.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/** Turns importer failures into entries people can act on, and builds the error CSV. */
class Import_Row_Error {
	/** Rows whose values are kept for the error CSV. */
	const VALUE_ROWS = 100;
	/** Columns kept per row. */
	const VALUE_COLUMNS = 40;
	/** Characters kept per value. */
	const VALUE_LENGTH = 500;

	/**
	 * Describe one failed row.
	 *
	 * @param \WP_Error $error      Importer error.
	 * @param array     $item       Prepared row (target field => value).
	 * @param int       $row        1-based row number in the file.
	 * @param bool      $keep_values Store the row values for the error CSV.
	 * @return array
	 */
	public static function describe( \WP_Error $error, array $item, $row, $keep_values = true ) {
		$code    = (string) $error->get_error_code();
		$message = wp_strip_all_tags( (string) $error->get_error_message() );
		$data    = $error->get_error_data();
		$field   = is_array( $data ) && isset( $data['field'] ) && is_scalar( $data['field'] ) ? (string) $data['field'] : self::guess_field( $code, $message, $item );
		$entry   = [
			'row'     => (int) $row,
			'message' => '' !== $message ? $message : __( 'The row could not be imported.', 'import-export-by-rockstarlab' ),
			'code'    => $code,
			'field'   => $field,
			'hint'    => self::hint( $code, $field, $message ),
		];
		if ( $keep_values ) {
			$entry['values'] = self::values( $item );
		}
		return $entry;
	}

	/**
	 * Visible row values, bounded in size.
	 *
	 * @param array $item Prepared row.
	 * @return array
	 */
	public static function values( array $item ) {
		$out = [];
		foreach ( $item as $key => $value ) {
			$key = (string) $key;
			if ( '' === $key || '_' === $key[0] || in_array( $key, [ 'post_meta', 'taxonomies' ], true ) ) {
				continue;
			}
			if ( is_array( $value ) || is_object( $value ) ) {
				$value = wp_json_encode( $value );
			}
			if ( ! is_scalar( $value ) && null !== $value ) {
				continue;
			}
			$value       = (string) $value;
			$out[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::VALUE_LENGTH ) : substr( $value, 0, self::VALUE_LENGTH );
			if ( count( $out ) >= self::VALUE_COLUMNS ) {
				break;
			}
		}
		// Custom fields and terms were moved into nested arrays; show them under their column names.
		foreach ( [ 'post_meta' => 'meta_', 'taxonomies' => 'taxonomy_' ] as $group => $prefix ) {
			if ( empty( $item[ $group ] ) || ! is_array( $item[ $group ] ) ) {
				continue;
			}
			foreach ( $item[ $group ] as $key => $value ) {
				if ( count( $out ) >= self::VALUE_COLUMNS || '_' === substr( (string) $key, 0, 1 ) ) {
					break;
				}
				if ( 'taxonomies' === $group && is_array( $value ) ) {
					$value = $value['terms'] ?? '';
				}
				$value = is_scalar( $value ) ? (string) $value : (string) wp_json_encode( $value );
				$out[ $prefix . $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, self::VALUE_LENGTH ) : substr( $value, 0, self::VALUE_LENGTH );
			}
		}
		return $out;
	}

	/**
	 * Field a failure most likely refers to, when the importer does not say.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @param array  $item    Row.
	 * @return string
	 */
	private static function guess_field( $code, $message, array $item ) {
		if ( 'missing_required_fields' === $code && preg_match( '/:\s*(.+)$/', $message, $m ) ) {
			return trim( $m[1] );
		}
		$by_code = [
			'invalid_date'               => 'post_date',
			'invalid_page_template'      => '_wp_page_template',
			'empty_content'              => 'post_content',
			'missing_comment_content'    => 'comment_content',
			'missing_post_id'            => 'ID',
			'invalid_taxonomy'           => 'taxonomies',
			'rsl_ie_media_not_image'     => isset( $item['featured_image_url'] ) ? 'featured_image_url' : '',
			'rsl_ie_download_failed'     => isset( $item['featured_image_url'] ) ? 'featured_image_url' : '',
			'download_failed'            => isset( $item['file'] ) ? 'file' : '',
			'rsl_ie_media_url_not_importable' => isset( $item['file'] ) ? 'file' : '',
		];
		if ( isset( $by_code[ $code ] ) ) {
			return $by_code[ $code ];
		}
		foreach ( array_keys( $item ) as $key ) {
			$key = (string) $key;
			if ( '' !== $key && '_' !== $key[0] && strlen( $key ) > 3 && false !== stripos( $message, $key ) ) {
				return $key;
			}
		}
		return '';
	}

	/**
	 * How to fix a failure, in plain words.
	 *
	 * @param string $code    Error code.
	 * @param string $field   Field.
	 * @param string $message Message.
	 * @return string
	 */
	public static function hint( $code, $field = '', $message = '' ) {
		$hints = [
			'missing_required_fields'         => __( 'Map a column of your file to this field in the Field Mapping step, or fill in the empty cells.', 'import-export-by-rockstarlab' ),
			'empty_content'                   => __( 'The row has no title, content or excerpt. Fill in at least one of them or remove the row.', 'import-export-by-rockstarlab' ),
			'empty_data'                      => __( 'The row is empty. Remove it from the file.', 'import-export-by-rockstarlab' ),
			'invalid_date'                    => __( 'Use a date WordPress understands, for example 2026-11-09 14:30.', 'import-export-by-rockstarlab' ),
			'invalid_page_template'           => __( 'The page template does not exist in the active theme. Leave the cell empty or use a template file name of this theme.', 'import-export-by-rockstarlab' ),
			'post_not_found'                  => __( 'No existing item matches this row. Check the value in the match column, or choose Create in "If No Match Found".', 'import-export-by-rockstarlab' ),
			'missing_post_id'                 => __( 'The row has no ID to update. Fill in the ID column or match by another field.', 'import-export-by-rockstarlab' ),
			'missing_comment_content'         => __( 'Comments need text. Fill in the comment content cell.', 'import-export-by-rockstarlab' ),
			'download_failed'                 => __( 'The file could not be downloaded. Open the URL in a browser: it must be public and return the file itself, not a page.', 'import-export-by-rockstarlab' ),
			'rsl_ie_download_failed'          => __( 'The file could not be downloaded. Open the URL in a browser: it must be public and return the file itself, not a page.', 'import-export-by-rockstarlab' ),
			'rsl_ie_media_url_not_importable' => __( 'The URL cannot be imported. Use a direct public link to the file (http or https).', 'import-export-by-rockstarlab' ),
			'rsl_ie_media_not_image'          => __( 'The URL does not point to an image. Use a direct link to a .jpg, .png, .gif or .webp file.', 'import-export-by-rockstarlab' ),
			'invalid_taxonomy'                => __( 'The taxonomy does not exist on this site. Check the field name or register the taxonomy first.', 'import-export-by-rockstarlab' ),
			'post_creation_failed'            => __( 'WordPress did not save the row. Check the values of this row for unusual characters or very long text.', 'import-export-by-rockstarlab' ),
			'comment_creation_failed'         => __( 'WordPress did not save the comment. Check that the post it belongs to exists.', 'import-export-by-rockstarlab' ),
			'db_insert_error'                 => __( 'The database refused the row. Check the values of this row for unusual characters or very long text.', 'import-export-by-rockstarlab' ),
			'rsl_ie_import_exception'         => __( 'An unexpected error stopped this row. Check its values; if it keeps failing, send the error text to support.', 'import-export-by-rockstarlab' ),
		];
		if ( isset( $hints[ $code ] ) ) {
			return $hints[ $code ];
		}
		if ( false !== stripos( $message, 'permission' ) || false !== stripos( $message, 'not allowed' ) ) {
			return __( 'Your account cannot change this item. Run the import as an administrator.', 'import-export-by-rockstarlab' );
		}
		return __( 'Correct the value in this row and import the rows with errors again (Download error rows as CSV).', 'import-export-by-rockstarlab' );
	}

	/**
	 * Error report as CSV: row, field, error, how to fix, then the row values.
	 *
	 * The value columns use the WordPress field names of the mapping, so the file can
	 * be corrected and imported again with Auto Map.
	 *
	 * @param array $errors Stored error entries.
	 * @return string
	 */
	public static function csv( array $errors ) {
		$columns = [];
		foreach ( $errors as $error ) {
			foreach ( array_keys( (array) ( $error['values'] ?? [] ) ) as $key ) {
				$columns[ (string) $key ] = true;
			}
		}
		$columns = array_keys( $columns );
		$handle  = fopen( 'php://temp', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- In-memory CSV buffer.
		$header  = array_merge(
			[
				__( 'Row', 'import-export-by-rockstarlab' ),
				__( 'Field', 'import-export-by-rockstarlab' ),
				__( 'Error', 'import-export-by-rockstarlab' ),
				__( 'How to fix', 'import-export-by-rockstarlab' ),
			],
			$columns
		);
		fputcsv( $handle, array_map( [ __CLASS__, 'cell' ], $header ), ',', '"', '' );
		foreach ( $errors as $error ) {
			if ( ! is_array( $error ) ) {
				continue;
			}
			$line = [
				(string) ( $error['row'] ?? '' ),
				(string) ( $error['field'] ?? '' ),
				(string) ( $error['message'] ?? '' ),
				(string) ( $error['hint'] ?? self::hint( (string) ( $error['code'] ?? '' ), '', (string) ( $error['message'] ?? '' ) ) ),
			];
			foreach ( $columns as $column ) {
				$line[] = (string) ( $error['values'][ $column ] ?? '' );
			}
			fputcsv( $handle, array_map( [ __CLASS__, 'cell' ], $line ), ',', '"', '' );
		}
		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- In-memory CSV buffer.
		return "\xEF\xBB\xBF" . $csv;
	}

	/**
	 * Neutralize spreadsheet formulas in exported cells.
	 *
	 * @param string $value Cell.
	 * @return string
	 */
	private static function cell( $value ) {
		$value = (string) $value;
		return '' !== $value && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) && ! is_numeric( $value ) ? "'" . $value : $value;
	}
}
