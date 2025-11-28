<?php
namespace WP_AIE\helper;

class data_transformer {

public static function sanitize_data( $data, $type = 'text' ) {
switch ( $type ) {
case 'email':
return sanitize_email( $data );
case 'url':
return esc_url_raw( $data );
case 'html':
return wp_kses_post( $data );
case 'int':
return intval( $data );
case 'float':
return floatval( $data );
case 'bool':
return filter_var( $data, FILTER_VALIDATE_BOOLEAN );
case 'slug':
return sanitize_title( $data );
case 'key':
return sanitize_key( $data );
case 'text':
default:
return sanitize_text_field( $data );
}
}

public static function format_date( $date, $format = 'Y-m-d H:i:s' ) {
if ( empty( $date ) ) {
return '';
}

$timestamp = is_numeric( $date ) ? $date : strtotime( $date );
if ( ! $timestamp ) {
return $date;
}

return gmdate( $format, $timestamp );
}

public static function validate_required( $data, $required_fields = [] ) {
$errors = [];

foreach ( $required_fields as $field ) {
if ( ! isset( $data[ $field ] ) || empty( $data[ $field ] ) ) {
$errors[] = sprintf( 'Field "%s" is required', $field );
}
}

return empty( $errors ) ? true : $errors;
}

public static function transform_field( $value, $transformations = [] ) {
foreach ( $transformations as $transformation ) {
$type = $transformation['type'] ?? '';
$params = $transformation['params'] ?? [];

switch ( $type ) {
case 'search_replace':
$search = $params['search'] ?? '';
$replace = $params['replace'] ?? '';
$value = str_replace( $search, $replace, $value );
break;

case 'regex_replace':
$pattern = $params['pattern'] ?? '';
$replace = $params['replace'] ?? '';
$value = preg_replace( $pattern, $replace, $value );
break;

case 'uppercase':
$value = strtoupper( $value );
break;

case 'lowercase':
$value = strtolower( $value );
break;

case 'trim':
$value = trim( $value );
break;

case 'prefix':
$prefix = $params['prefix'] ?? '';
$value = $prefix . $value;
break;

case 'suffix':
$suffix = $params['suffix'] ?? '';
$value = $value . $suffix;
break;

case 'custom_function':
$function = $params['function'] ?? '';
if ( function_exists( $function ) ) {
$value = call_user_func( $function, $value );
}
break;
}
}

return $value;
}

public static function parse_csv_line( $line, $delimiter = ',', $enclosure = '"' ) {
return str_getcsv( $line, $delimiter, $enclosure );
}

public static function array_to_csv( $data, $delimiter = ',', $enclosure = '"' ) {
$output = fopen( 'php://temp', 'r+' );
fputcsv( $output, $data, $delimiter, $enclosure );
rewind( $output );
$csv = stream_get_contents( $output );
fclose( $output );

return rtrim( $csv );
}

public static function normalize_array_keys( $array, $lowercase = true ) {
$normalized = [];

foreach ( $array as $key => $value ) {
$new_key = $lowercase ? strtolower( $key ) : $key;
$new_key = sanitize_key( $new_key );
$normalized[ $new_key ] = $value;
}

return $normalized;
}

public static function deep_clean( $value ) {
if ( is_array( $value ) ) {
return array_map( [ __CLASS__, 'deep_clean' ], $value );
}

if ( is_string( $value ) ) {
$value = stripslashes( $value );
$value = trim( $value );
}

return $value;
}
}
