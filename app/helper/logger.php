<?php
namespace WP_AIE\helper;

class logger {
const INFO = 'info';
const WARNING = 'warning';
const ERROR = 'error';

public static function log( $job_id, $level, $message, $data = [] ) {
global $wpdb;
$table = $wpdb->prefix . 'aie_logs';

$wpdb->insert(
$table,
[
'job_id' => $job_id,
'level' => $level,
'message' => $message,
'data' => json_encode( $data ),
'created_at' => current_time( 'mysql' ),
],
[ '%d', '%s', '%s', '%s', '%s' ]
);

return $wpdb->insert_id;
}

public static function info( $job_id, $message, $data = [] ) {
return self::log( $job_id, self::INFO, $message, $data );
}

public static function warning( $job_id, $message, $data = [] ) {
return self::log( $job_id, self::WARNING, $message, $data );
}

public static function error( $job_id, $message, $data = [] ) {
return self::log( $job_id, self::ERROR, $message, $data );
}

public static function get_logs( $job_id, $level = null ) {
global $wpdb;
$table = $wpdb->prefix . 'aie_logs';

$where = $wpdb->prepare( 'WHERE job_id = %d', $job_id );
if ( $level ) {
$where .= $wpdb->prepare( ' AND level = %s', $level );
}

$results = $wpdb->get_results(
"SELECT * FROM $table $where ORDER BY created_at DESC"
);

foreach ( $results as $log ) {
if ( $log->data ) {
$log->data = json_decode( $log->data, true );
}
}

return $results;
}

public static function clear_logs( $job_id ) {
global $wpdb;
$table = $wpdb->prefix . 'aie_logs';

return $wpdb->delete(
$table,
[ 'job_id' => $job_id ],
[ '%d' ]
);
}
}
