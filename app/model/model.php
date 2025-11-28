<?php
namespace WP_AIE\model;

abstract class model {

protected static $table_name;

protected static function get_table_name() {
global $wpdb;
return $wpdb->prefix . static::$table_name;
}

public static function find( $id ) {
global $wpdb;
$table = static::get_table_name();

return $wpdb->get_row(
$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
);
}

public static function find_by( $column, $value ) {
global $wpdb;
$table = static::get_table_name();

return $wpdb->get_row(
$wpdb->prepare( "SELECT * FROM {$table} WHERE {$column} = %s", $value )
);
}

public static function all( $args = [] ) {
global $wpdb;
$table = static::get_table_name();

$limit = isset( $args['limit'] ) ? intval( $args['limit'] ) : 100;
$offset = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;
$order_by = isset( $args['order_by'] ) ? sanitize_key( $args['order_by'] ) : 'id';
$order = isset( $args['order'] ) && in_array( strtoupper( $args['order'] ), ['ASC', 'DESC'] ) 
? strtoupper( $args['order'] ) 
: 'DESC';

return $wpdb->get_results(
"SELECT * FROM {$table} ORDER BY {$order_by} {$order} LIMIT {$limit} OFFSET {$offset}"
);
}

public static function insert( $data ) {
global $wpdb;
$table = static::get_table_name();

$formats = static::get_formats( $data );

$result = $wpdb->insert( $table, $data, $formats );

if ( $result === false ) {
return new \WP_Error( 'db_insert_error', $wpdb->last_error );
}

return $wpdb->insert_id;
}

public static function update( $id, $data ) {
global $wpdb;
$table = static::get_table_name();

$formats = static::get_formats( $data );

$result = $wpdb->update(
$table,
$data,
[ 'id' => $id ],
$formats,
[ '%d' ]
);

if ( $result === false ) {
return new \WP_Error( 'db_update_error', $wpdb->last_error );
}

return $result;
}

public static function delete( $id ) {
global $wpdb;
$table = static::get_table_name();

return $wpdb->delete(
$table,
[ 'id' => $id ],
[ '%d' ]
);
}

public static function count( $where = [] ) {
global $wpdb;
$table = static::get_table_name();

if ( empty( $where ) ) {
return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
}

$conditions = [];
$values = [];

foreach ( $where as $column => $value ) {
$conditions[] = "{$column} = %s";
$values[] = $value;
}

$where_clause = implode( ' AND ', $conditions );
$query = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", $values );

return (int) $wpdb->get_var( $query );
}

protected static function get_formats( $data ) {
$formats = [];

foreach ( $data as $value ) {
if ( is_int( $value ) ) {
$formats[] = '%d';
} elseif ( is_float( $value ) ) {
$formats[] = '%f';
} else {
$formats[] = '%s';
}
}

return $formats;
}
}
