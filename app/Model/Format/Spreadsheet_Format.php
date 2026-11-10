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
	 * Fixed-decimals style indexes of the workbook being written.
	 *
	 * @var array<string,int>
	 */
	private $decimal_styles = array();

	/**
	 * Whether the workbook being written keeps fixed-decimals number formats (XLSX).
	 *
	 * @var bool
	 */
	private $fixed_decimals = false;

	/**
	 * Parse entire spreadsheet file.
	 *
	 * @param string $file_path Absolute path to spreadsheet file.
	 * @param array  $options   Parser options.
	 * @return array|\WP_Error Array of parsed rows or WP_Error on failure.
	 */
	public function parse( $file_path, $options = array() ) {
		$validation = $this->basic_file_validation( $file_path );
		if ( is_wp_error( $validation ) ) {
			return $validation; }
		try {
			return iterator_to_array( $this->rows( $file_path, $options ), false ); } catch ( \Throwable $error ) {
			return new \WP_Error( 'spreadsheet_parse_error', esc_html( $error->getMessage() ) ); }
	}

	/** Yield normalized rows without building a second complete row array. */
	public function rows( $file_path, $options = array(), &$raw_headers = null ) {
		$validation = $this->basic_file_validation( $file_path );
		if ( is_wp_error( $validation ) ) {
			throw new \RuntimeException( $validation->get_error_code() ); }
		// Identify by content, preserving the legacy reader's extension-independent selection.
		$reader = IOFactory::createReaderForFile( $file_path );
		if ( $reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx ) {
			yield from XLSX_Row_Stream::rows( $file_path, $options, $raw_headers );
			return;
		}
		if ( $reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Ods ) {
			yield from ODS_Row_Stream::rows( $file_path, $options, $raw_headers );
			return;
		}
		$spreadsheet = $this->load_spreadsheet( $file_path );
		if ( is_wp_error( $spreadsheet ) ) {
			throw new \RuntimeException( $spreadsheet->get_error_code() ); }
		try {
			yield from $this->worksheet_rows( $spreadsheet->getActiveSheet(), $options, $raw_headers );
		} finally {
			$spreadsheet->disconnectWorksheets();
		}
	}

	/** Build a bounded sample and exact count with a single worksheet load. */
	public function preview( $file_path, $options = [] ) {
		$validation = $this->basic_file_validation( $file_path );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		$spreadsheet = null;
		try {
			$reader = IOFactory::createReaderForFile( $file_path );
			if ( $reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx ) {
				return XLSX_Row_Stream::preview( $file_path, $options );
			}
			if ( $reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Ods ) {
				return ODS_Row_Stream::preview( $file_path, $options );
			}
			$spreadsheet = $this->load_spreadsheet( $file_path );
			if ( is_wp_error( $spreadsheet ) ) {
				return $spreadsheet;
			}
			$sheet  = $spreadsheet->getActiveSheet();
			$width  = Coordinate::columnIndexFromString( $sheet->getHighestDataColumn() );
			$result = [
				'headers'    => ( $options['has_header'] ?? true ) ? $this->read_row( $sheet, 1, $width ) : $this->generate_column_headers( $width ),
				'preview'    => [],
				'total_rows' => 0,
			];
			foreach ( $this->worksheet_rows( $sheet, $options ) as $row ) {
				if ( $result['total_rows'] < 5 ) {
					$result['preview'][] = $row;
				}
				++$result['total_rows'];
			}
			return $result;
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'spreadsheet_parse_error', esc_html( $error->getMessage() ) );
		} finally {
			if ( $spreadsheet instanceof Spreadsheet ) {
				$spreadsheet->disconnectWorksheets();
			}
		}
	}

	/** Iterate nonempty normalized rows of an already loaded worksheet. */
	private function worksheet_rows( $sheet, array $options, &$raw_headers = null ) {
		$raw_headers = [];
		$highest_row = (int) $sheet->getHighestDataRow();
		$highest_col = Coordinate::columnIndexFromString( $sheet->getHighestDataColumn() );
		$has_header  = array_key_exists( 'has_header', $options ) ? (bool) $options['has_header'] : true;
		if ( $highest_row < 1 || $highest_col < 1 ) {
			return; }
		$headers     = $has_header ? $this->read_row( $sheet, 1, $highest_col ) : $this->generate_column_headers( $highest_col );
		$raw_headers = $headers;
		for ( $row = $has_header ? 2 : 1; $row <= $highest_row; ++$row ) {
			$values = $this->read_row( $sheet, $row, $highest_col );
			if ( $this->is_empty_row( $values ) ) {
				continue; }
			$combined = array_combine( $headers, $values );
			if ( false !== $combined ) {
				yield $combined; }
		}
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
		$validation = $this->basic_file_validation( $file_path );
		if ( is_wp_error( $validation ) ) {
			return $validation; }
		$offset = max( 0, (int) $offset );
		$limit  = max( 1, (int) $limit );
		$data   = [];
		$index  = 0;
		try {
			foreach ( $this->rows( $file_path, $options ) as $row ) {
				if ( $index++ < $offset ) {
					continue; }
				$data[] = $row;
				if ( count( $data ) >= $limit ) {
					break; }
			}
			return $data;
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'spreadsheet_parse_error', esc_html( $error->getMessage() ) ); }
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

		$spreadsheet          = new Spreadsheet();
		$sheet                = $spreadsheet->getActiveSheet();
		$row_index            = 1;
		$this->decimal_styles = array();
		// The ODS writer drops number formats, so 13.80 would show and re-import as 13.8 there.
		$this->fixed_decimals = 'xlsx' === $extension;

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
		$validation = $this->basic_file_validation( $file_path );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		try {
			// Validate exactly the import data read by preview, including its complete tail.
			foreach ( $this->rows( $file_path ) as $row ) {
			}
			return true;
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'spreadsheet_parse_error', esc_html( $error->getMessage() ) );
		}
	}

	/**
	 * Get spreadsheet headers.
	 *
	 * @param string $file_path Absolute path to file.
	 * @param array  $options   Parser options.
	 * @return array|\WP_Error Array of headers or WP_Error on failure.
	 */
	public function get_headers( $file_path, $options = array() ) {
		$validation = $this->basic_file_validation( $file_path );
		if ( is_wp_error( $validation ) ) {
			return $validation; }
		try {
			$reader = IOFactory::createReaderForFile( $file_path );
			if ( $reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx ) {
				return XLSX_Row_Stream::headers( $file_path, $options ); }
			if ( $reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Ods ) {
				return ODS_Row_Stream::headers( $file_path, $options ); }
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'spreadsheet_parse_error', esc_html( $error->getMessage() ) ); }
		$spreadsheet = $this->load_spreadsheet( $file_path );
		if ( is_wp_error( $spreadsheet ) ) {
			return $spreadsheet;
		}

		$sheet       = $spreadsheet->getActiveSheet();
		$highest_col = Coordinate::columnIndexFromString( $sheet->getHighestDataColumn() );
		$headers     = ( $options['has_header'] ?? true ) ? $this->read_row( $sheet, 1, $highest_col ) : $this->generate_column_headers( $highest_col );

		$spreadsheet->disconnectWorksheets();

		return $headers;
	}

	/**
	 * Count spreadsheet rows, excluding the header row.
	 *
	 * @param string $file_path Absolute path to file.
	 * @param array  $options Optional parser settings.
	 * @return int|\WP_Error Row count or WP_Error on failure.
	 */
	public function count_rows( $file_path, $options = [] ) {
		$validation = $this->basic_file_validation( $file_path );
		if ( is_wp_error( $validation ) ) {
			return $validation; }
		$count = 0;
		try {
			foreach ( $this->rows( $file_path, $options ) as $row ) {
				++$count; }
			return $count;
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'spreadsheet_parse_error', esc_html( $error->getMessage() ) ); }
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
			// Reading a missing cell through getCell() creates it, densifying sparse sheets.
			$row[] = $sheet->cellExists( $coordinate ) ? $this->normalize_cell_value( $sheet->getCell( $coordinate )->getValue() ) : '';
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
	 * A plain number that reads back as exactly the same text, or null.
	 *
	 * Quantities, IDs and prices become real numbers so spreadsheets can sum and sort them.
	 * Codes such as 00123 or +4930123, numbers longer than 11 digits (barcodes, tracking
	 * numbers, which Excel would show as 1.2E+12) stay text, so a re-import reads exactly
	 * what was exported. Decimals with trailing zeros (13.80) are handled by
	 * trailing_zero_decimals(): a number with a fixed-decimals format.
	 *
	 * @param string $text Cell text.
	 * @return int|float|null
	 */
	private function exact_number( $text ) {
		if ( ! is_string( $text ) || ! preg_match( '/^-?(?:0|[1-9]\d{0,10})(?:\.\d{1,10})?$/', $text ) ) {
			return null;
		}
		$number = false === strpos( $text, '.' ) ? (int) $text : (float) $text;
		return (string) $number === $text ? $number : null;
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
			$text       = $this->normalize_cell_value( $value );
			$number     = $this->exact_number( $text );
			$decimals   = null === $number && $this->fixed_decimals ? $this->trailing_zero_decimals( $text ) : null;
			if ( null !== $decimals ) {
				$number = (float) $text;
			}
			$sheet->setCellValueExplicit(
				$coordinate,
				null === $number ? $text : $number,
				null === $number ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC
			);
			if ( null !== $decimals ) {
				$sheet->getCell( $coordinate )->setXfIndex( $this->decimals_style( $sheet, $decimals ) );
			}
			++$column_index;
		}
	}

	/**
	 * Decimals of a price-like value such as 13.80 or 40.00, or null.
	 *
	 * Such a value is written as a number with a 0.00 format: spreadsheets can sum it,
	 * it shows as 13.80, and the importer reads 13.80 back from the cell format.
	 *
	 * @param string $text Cell text.
	 * @return int|null
	 */
	private function trailing_zero_decimals( $text ) {
		if ( ! is_string( $text ) || ! preg_match( '/^-?(?:0|[1-9]\d{0,10})\.(\d{0,9}0)$/', $text, $match ) ) {
			return null;
		}
		$short = rtrim( rtrim( $text, '0' ), '.' );
		return (string) (float) $text === $short || ( '-0' === $short && 0.0 === (float) $text ) ? strlen( $match[1] ) : null;
	}

	/**
	 * Workbook style index for a fixed number of decimals, created once per workbook.
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet    Worksheet.
	 * @param int                                           $decimals Decimals.
	 * @return int
	 */
	private function decimals_style( $sheet, $decimals ) {
		$book = $sheet->getParent();
		$key  = spl_object_id( $book ) . ':' . $decimals;
		if ( ! isset( $this->decimal_styles[ $key ] ) ) {
			$style = new \PhpOffice\PhpSpreadsheet\Style\Style();
			$style->getNumberFormat()->setFormatCode( '0.' . str_repeat( '0', $decimals ) );
			$book->addCellXf( $style );
			$this->decimal_styles[ $key ] = $style->getIndex();
		}
		return $this->decimal_styles[ $key ];
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
