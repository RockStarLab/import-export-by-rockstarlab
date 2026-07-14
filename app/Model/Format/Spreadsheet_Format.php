<?php
/**
 * Spreadsheet Format Handler
 *
 * Handles parsing XLSX/ODS files and generating XLSX/ODS files.
 *
 * @package RockStarLab\ImportExport\Model\Format
 */

namespace RockStarLab\ImportExport\Model\Format;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

defined( 'ABSPATH' ) || exit;

/**
 * Spreadsheet format parser and writer.
 */
class Spreadsheet_Format implements File_Format_Interface {

	/**
	 * Import-capable spreadsheet extensions.
	 *
	 * @var array
	 */
	private const IMPORT_EXTENSIONS = array( 'xlsx', 'ods' );

	/**
	 * Export-capable spreadsheet extensions.
	 *
	 * @var array
	 */
	private const EXPORT_EXTENSIONS = array( 'xlsx', 'ods' );

	/**
	 * Parse entire spreadsheet file.
	 *
	 * @param string $file_path Absolute path to spreadsheet file.
	 * @param array  $options   Parser options.
	 * @return array|\WP_Error Array of parsed rows or WP_Error on failure.
	 */
	public function parse( $file_path, $options = array() ) {
		$spreadsheet = $this->load_spreadsheet( $file_path );
		if ( is_wp_error( $spreadsheet ) ) {
			return $spreadsheet;
		}

		$sheet       = $spreadsheet->getActiveSheet();
		$highest_row = (int) $sheet->getHighestDataRow();
		$highest_col = Coordinate::columnIndexFromString( $sheet->getHighestDataColumn() );
		$has_header  = array_key_exists( 'has_header', $options ) ? (bool) $options['has_header'] : true;

		if ( $highest_row < 1 || $highest_col < 1 ) {
			$spreadsheet->disconnectWorksheets();
			return array();
		}

		$headers   = $has_header ? $this->read_row( $sheet, 1, $highest_col ) : $this->generate_column_headers( $highest_col );
		$start_row = $has_header ? 2 : 1;
		$data      = array();

		for ( $row = $start_row; $row <= $highest_row; $row++ ) {
			$values = $this->read_row( $sheet, $row, $highest_col );

			if ( $this->is_empty_row( $values ) ) {
				continue;
			}

			$combined = array_combine( $headers, $values );
			if ( false !== $combined ) {
				$data[] = $combined;
			}
		}

		$spreadsheet->disconnectWorksheets();

		return $data;
	}

	/**
	 * Parse spreadsheet file in chunks.
	 *
	 * @param string $file_path Absolute path to spreadsheet file.
	 * @param int    $offset    Starting row offset, excluding header.
	 * @param int    $limit     Number of rows to read.
	 * @param array  $options   Parser options.
	 * @return array|\WP_Error Array of parsed rows or WP_Error on failure.
	 */
	public function parse_chunk( $file_path, $offset, $limit, $options = array() ) {
		$data = $this->parse( $file_path, $options );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return array_slice( $data, max( 0, (int) $offset ), max( 1, (int) $limit ) );
	}

	/**
	 * Generate spreadsheet file from data.
	 *
	 * @param array  $data      Data to write.
	 * @param string $file_path Target path.
	 * @param array  $options   Generation options.
	 * @return bool|\WP_Error True on success or WP_Error on failure.
	 */
	public function generate( $data, $file_path, $options = array() ) {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, self::EXPORT_EXTENSIONS, true ) ) {
			return new \WP_Error( 'invalid_extension', __( 'Spreadsheet export supports XLSX and ODS files only.', 'import-export-by-rockstarlab' ) );
		}

		$headers = $options['headers'] ?? null;
		if ( false === $headers ) {
			$headers = array();
		} elseif ( null === $headers ) {
			$headers = $this->detect_headers( $data );
		}

		$spreadsheet = new Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();
		$row_index   = 1;

		if ( ! empty( $headers ) ) {
			$this->write_row( $sheet, $row_index, $headers );
			++$row_index;
		}

		foreach ( $data as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$values = empty( $headers ) ? array_values( $row ) : $this->order_row_by_headers( $row, $headers );
			$this->write_row( $sheet, $row_index, $values );
			++$row_index;
		}

		foreach ( range( 1, max( 1, count( $headers ) ) ) as $column_index ) {
			$sheet->getColumnDimension( Coordinate::stringFromColumnIndex( $column_index ) )->setAutoSize( true );
		}

		$writer_type = 'ods' === $extension ? 'Ods' : 'Xlsx';
		$writer      = IOFactory::createWriter( $spreadsheet, $writer_type );

		try {
			$writer->save( $file_path );
		} catch ( \Throwable $e ) {
			$spreadsheet->disconnectWorksheets();
			return new \WP_Error( 'file_write_error', esc_html( $e->getMessage() ) );
		}

		$spreadsheet->disconnectWorksheets();

		return true;
	}

	/**
	 * Validate spreadsheet file.
	 *
	 * @param string $file_path Absolute path to file.
	 * @return bool|\WP_Error True if valid or WP_Error on failure.
	 */
	public function validate( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'Spreadsheet file not found', 'import-export-by-rockstarlab' ) );
		}

		if ( ! is_readable( $file_path ) ) {
			return new \WP_Error( 'file_not_readable', __( 'Spreadsheet file is not readable', 'import-export-by-rockstarlab' ) );
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, self::IMPORT_EXTENSIONS, true ) ) {
			return new \WP_Error( 'invalid_extension', __( 'Invalid spreadsheet file extension', 'import-export-by-rockstarlab' ) );
		}

		$spreadsheet = $this->load_spreadsheet( $file_path );
		if ( is_wp_error( $spreadsheet ) ) {
			return $spreadsheet;
		}

		$spreadsheet->disconnectWorksheets();

		return true;
	}

	/**
	 * Get spreadsheet headers.
	 *
	 * @param string $file_path Absolute path to file.
	 * @param array  $options   Parser options.
	 * @return array|\WP_Error Array of headers or WP_Error on failure.
	 */
	public function get_headers( $file_path, $options = array() ) {
		$spreadsheet = $this->load_spreadsheet( $file_path );
		if ( is_wp_error( $spreadsheet ) ) {
			return $spreadsheet;
		}

		$sheet       = $spreadsheet->getActiveSheet();
		$highest_col = Coordinate::columnIndexFromString( $sheet->getHighestDataColumn() );
		$headers     = $this->read_row( $sheet, 1, $highest_col );

		$spreadsheet->disconnectWorksheets();

		return $headers;
	}

	/**
	 * Count spreadsheet rows, excluding the header row.
	 *
	 * @param string $file_path Absolute path to file.
	 * @return int|\WP_Error Row count or WP_Error on failure.
	 */
	public function count_rows( $file_path ) {
		$spreadsheet = $this->load_spreadsheet( $file_path );
		if ( is_wp_error( $spreadsheet ) ) {
			return $spreadsheet;
		}

		$count = max( 0, (int) $spreadsheet->getActiveSheet()->getHighestDataRow() - 1 );
		$spreadsheet->disconnectWorksheets();

		return $count;
	}

	/**
	 * Get supported file extensions.
	 *
	 * @return array
	 */
	public function get_extensions() {
		return self::IMPORT_EXTENSIONS;
	}

	/**
	 * Get supported MIME types.
	 *
	 * @return array
	 */
	public function get_mime_types() {
		return array(
			'application/vnd.oasis.opendocument.spreadsheet',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		);
	}

	/**
	 * Load a spreadsheet safely.
	 *
	 * @param string $file_path Absolute path to file.
	 * @return Spreadsheet|\WP_Error Spreadsheet or WP_Error.
	 */
	private function load_spreadsheet( $file_path ) {
		$validation = $this->basic_file_validation( $file_path );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		try {
			$reader = IOFactory::createReaderForFile( $file_path );
			$reader->setReadDataOnly( true );
			return $reader->load( $file_path );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'spreadsheet_parse_error', esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Basic readable file validation.
	 *
	 * @param string $file_path Absolute path to file.
	 * @return true|\WP_Error True or WP_Error.
	 */
	private function basic_file_validation( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'Spreadsheet file not found', 'import-export-by-rockstarlab' ) );
		}

		if ( ! is_readable( $file_path ) ) {
			return new \WP_Error( 'file_not_readable', __( 'Spreadsheet file is not readable', 'import-export-by-rockstarlab' ) );
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, self::IMPORT_EXTENSIONS, true ) ) {
			return new \WP_Error( 'invalid_extension', __( 'Invalid spreadsheet file extension', 'import-export-by-rockstarlab' ) );
		}

		return true;
	}

	/**
	 * Read one row as scalar strings.
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet       Worksheet.
	 * @param int                                           $row_index   Row number.
	 * @param int                                           $highest_col Highest column index.
	 * @return array
	 */
	private function read_row( $sheet, $row_index, $highest_col ) {
		$row = array();

		for ( $column = 1; $column <= $highest_col; $column++ ) {
			$coordinate = Coordinate::stringFromColumnIndex( $column ) . $row_index;
			$row[]      = $this->normalize_cell_value( $sheet->getCell( $coordinate )->getValue() );
		}

		return $row;
	}

	/**
	 * Normalize a cell value for import/export.
	 *
	 * @param mixed $value Cell value.
	 * @return string Scalar string.
	 */
	private function normalize_cell_value( $value ) {
		if ( null === $value ) {
			return '';
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$encoded = wp_json_encode( $value );
			return $encoded ? $encoded : '';
		}

		return (string) $value;
	}

	/**
	 * Write one row using explicit strings to avoid formula execution.
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet     Worksheet.
	 * @param int                                           $row_index Row number.
	 * @param array                                         $values    Row values.
	 */
	private function write_row( $sheet, $row_index, $values ) {
		$column_index = 1;

		foreach ( $values as $value ) {
			$coordinate = Coordinate::stringFromColumnIndex( $column_index ) . $row_index;
			$sheet->setCellValueExplicit(
				$coordinate,
				$this->normalize_cell_value( $value ),
				DataType::TYPE_STRING
			);
			++$column_index;
		}
	}

	/**
	 * Detect headers from first associative data row.
	 *
	 * @param array $data Export data.
	 * @return array
	 */
	private function detect_headers( $data ) {
		if ( empty( $data ) || ! isset( $data[0] ) || ! is_array( $data[0] ) ) {
			return array();
		}

		return isset( $data[0][0] ) ? array() : array_keys( $data[0] );
	}

	/**
	 * Order row values by headers.
	 *
	 * @param array $row     Data row.
	 * @param array $headers Header names.
	 * @return array
	 */
	private function order_row_by_headers( $row, $headers ) {
		$ordered = array();

		foreach ( $headers as $header ) {
			$ordered[] = $row[ $header ] ?? '';
		}

		return $ordered;
	}

	/**
	 * Generate Column 1, Column 2 style headers.
	 *
	 * @param int $highest_col Highest column index.
	 * @return array
	 */
	private function generate_column_headers( $highest_col ) {
		$headers = array();

		for ( $i = 1; $i <= $highest_col; $i++ ) {
			$headers[] = 'Column ' . $i;
		}

		return $headers;
	}

	/**
	 * Check whether row has only empty values.
	 *
	 * @param array $row Row values.
	 * @return bool
	 */
	private function is_empty_row( $row ) {
		foreach ( $row as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}
}
