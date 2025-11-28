<?php
namespace WP_AIE\model;

class log extends model {

protected static $table_name = 'aie_logs';

const INFO = 'info';
const WARNING = 'warning';
const ERROR = 'error';

public static function create( $job_id, $level, $message, $data = [] ) {
return static::insert( [
'job_id' => $job_id,
'level' => $level,
'message' => $message,
'data' => ! empty( $data ) ? json_encode( $data ) : null,
'created_at' => current_time( 'mysql' ),
] );
}

public static function info( $job_id, $message, $data = [] ) {
return static::create( $job_id, self::INFO, $message, $data );
}

public static function warning( $job_id, $message, $data = [] ) {
return static::create( $job_id, self::WARNING, $message, $data );
}

public static function error( $job_id, $message, $data = [] ) {
return static::create( $job_id, self::ERROR, $message, $data );
}

public static function get_by_job( $job_id, $level = null ) {
global $wpdb;
$table = static::get_table_name();

if ( $level ) {
$results = $wpdb->get_results(
$wpdb->prepare(
"SELECT * FROM {$table} WHERE job_id = %d AND level = %s ORDER BY created_at DESC",
$job_id,
$level
)
);
} else {
$results = $wpdb->get_results(
$wpdb->prepare(
"SELECT * FROM {$table} WHERE job_id = %d ORDER BY created_at DESC",
$job_id
)
);
}

foreach ( $results as $log ) {
if ( $log->data ) {
$log->data = json_decode( $log->data, true );
}
}

return $results;
}

public static function delete_by_job( $job_id ) {
global $wpdb;
$table = static::get_table_name();

return $wpdb->delete(
$table,
[ 'job_id' => $job_id ],
[ '%d' ]
);
}

public static function get_errors_by_job( $job_id ) {
return static::get_by_job( $job_id, self::ERROR );
}
}
