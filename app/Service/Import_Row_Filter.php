<?php
/**
 * Import row filter: import only the source rows whose columns meet conditions.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

class Import_Row_Filter {
	const OPERATORS = [ 'equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'is_empty', 'is_not_empty', 'greater_than', 'less_than', 'in', 'not_in' ];

	/**
	 * Validate and normalize the row_filters option.
	 *
	 * @param mixed $filters Raw filters.
	 * @return array|\WP_Error List of {field, operator, value}.
	 */
	public static function normalize( $filters ) {
		if ( null === $filters || '' === $filters || [] === $filters ) {
			return [];
		}
		if ( ! is_array( $filters ) || count( $filters ) > 20 ) {
			return self::invalid();
		}
		$out = [];
		foreach ( $filters as $filter ) {
			if ( ! is_array( $filter ) || ! isset( $filter['field'], $filter['operator'] ) || ! is_string( $filter['field'] ) || '' === trim( $filter['field'] ) || ! in_array( $filter['operator'], self::OPERATORS, true ) ) {
				return self::invalid();
			}
			$value = $filter['value'] ?? '';
			if ( ! is_scalar( $value ) ) {
				return self::invalid();
			}
			$out[] = [
				'field'    => trim( $filter['field'] ),
				'operator' => $filter['operator'],
				'value'    => (string) $value,
			];
		}
		return $out;
	}

	/**
	 * Keep rows that match the filters.
	 *
	 * @param array  $rows    Raw parsed rows (column => value).
	 * @param array  $filters Normalized filters.
	 * @param string $match   'all' or 'any'.
	 * @return array Re-indexed matching rows.
	 */
	public static function apply( array $rows, array $filters, $match = 'all' ) {
		if ( ! $filters ) {
			return $rows;
		}
		$any = 'any' === $match;
		$out = [];
		foreach ( $rows as $row ) {
			$hit = ! $any;
			foreach ( $filters as $filter ) {
				$ok = self::matches( is_array( $row ) ? $row : [], $filter );
				if ( $any && $ok ) {
					$hit = true;
					break;
				}
				if ( ! $any && ! $ok ) {
					$hit = false;
					break;
				}
			}
			if ( $hit ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/** One condition against one row. A missing column is treated as empty. */
	public static function matches( array $row, array $filter ) {
		$actual = self::column( $row, $filter['field'] );
		$want   = (string) $filter['value'];
		$a      = mb_strtolower( trim( $actual ) );
		$w      = mb_strtolower( trim( $want ) );
		switch ( $filter['operator'] ) {
			case 'equals':
				return $a === $w;
			case 'not_equals':
				return $a !== $w;
			case 'contains':
				return '' !== $w && false !== mb_strpos( $a, $w );
			case 'not_contains':
				return '' === $w || false === mb_strpos( $a, $w );
			case 'starts_with':
				return '' !== $w && 0 === mb_strpos( $a, $w );
			case 'is_empty':
				return '' === $a;
			case 'is_not_empty':
				return '' !== $a;
			case 'in':
			case 'not_in':
				$list = array_filter( array_map( static function ( $v ) {
					return mb_strtolower( trim( $v ) );
				}, explode( ',', $want ) ), 'strlen' );
				$in   = in_array( $a, $list, true );
				return 'in' === $filter['operator'] ? $in : ! $in;
			case 'greater_than':
			case 'less_than':
				if ( '' === $a || '' === $w ) {
					return false;
				}
				$cmp = self::compare( trim( $actual ), trim( $want ) );
				return 'greater_than' === $filter['operator'] ? $cmp > 0 : $cmp < 0;
		}
		return false;
	}

	/** Numbers compare as numbers, dates as dates, everything else as text. */
	private static function compare( $a, $b ) {
		if ( is_numeric( $a ) && is_numeric( $b ) ) {
			return (float) $a <=> (float) $b;
		}
		$date = '/^\d{4}-\d{2}-\d{2}/';
		if ( preg_match( $date, $a ) && preg_match( $date, $b ) ) {
			$ta = strtotime( $a );
			$tb = strtotime( $b );
			if ( false !== $ta && false !== $tb ) {
				return $ta <=> $tb;
			}
		}
		return strcmp( mb_strtolower( $a ), mb_strtolower( $b ) );
	}

	/** Column value; case-insensitive header match; arrays are joined by commas. */
	private static function column( array $row, $field ) {
		$value = null;
		if ( array_key_exists( $field, $row ) ) {
			$value = $row[ $field ];
		} else {
			foreach ( $row as $key => $candidate ) {
				if ( 0 === strcasecmp( trim( (string) $key ), $field ) ) {
					$value = $candidate;
					break;
				}
			}
		}
		if ( is_array( $value ) ) {
			$flat = [];
			array_walk_recursive( $value, static function ( $v ) use ( &$flat ) {
				if ( is_scalar( $v ) ) {
					$flat[] = (string) $v;
				}
			} );
			return implode( ', ', $flat );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	private static function invalid() {
		return new \WP_Error( 'rsl_ie_invalid_row_filters', __( 'Each row filter needs a column, a supported condition and a text value.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
	}
}
