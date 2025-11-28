<?php
namespace WP_AIE\helper;

class test_format {
	public static function bad_format( $a, $b ) {
		if ( $a > $b ) {
			return $a;
		} else {
			return $b;
		}
	}
	public static function another_method( $x, $y ) {
		$result = $x + $y;
		return $result;
	}
}
