<?php
namespace WP_AIE\model;

class job extends model {

protected static $table_name = 'aie_jobs';

public static function create( $data ) {
$defaults = [
'status' => 'pending',
'total_items' => 0,
'processed_items' => 0,
'success_items' => 0,
'failed_items' => 0,
'created_at' => current_time( 'mysql' ),
'updated_at' => current_time( 'mysql' ),
];

$data = wp_parse_args( $data, $defaults );

return static::insert( $data );
}

public static function update_progress( $job_id, $total, $processed, $success, $failed ) {
$percentage = $total > 0 ? round( ( $processed / $total ) * 100, 2 ) : 0;

return static::update( $job_id, [
'total_items' => $total,
'processed_items' => $processed,
'success_items' => $success,
'failed_items' => $failed,
'percentage' => $percentage,
'updated_at' => current_time( 'mysql' ),
] );
}

public static function increment( $job_id, $success = true ) {
$job = static::find( $job_id );

if ( ! $job ) {
return false;
}

$processed = (int) $job->processed_items + 1;
$success_count = $success ? (int) $job->success_items + 1 : (int) $job->success_items;
$failed_count = ! $success ? (int) $job->failed_items + 1 : (int) $job->failed_items;

return static::update_progress(
$job_id,
(int) $job->total_items,
$processed,
$success_count,
$failed_count
);
}

public static function get_progress( $job_id ) {
$job = static::find( $job_id );

if ( ! $job ) {
return null;
}

return [
'total' => (int) $job->total_items,
'processed' => (int) $job->processed_items,
'success' => (int) $job->success_items,
'failed' => (int) $job->failed_items,
'percentage' => (float) $job->percentage,
'status' => $job->status,
];
}

public static function update_status( $job_id, $status ) {
$data = [
'status' => $status,
'updated_at' => current_time( 'mysql' ),
];

if ( in_array( $status, [ 'completed', 'failed', 'cancelled' ] ) ) {
$data['completed_at'] = current_time( 'mysql' );
}

return static::update( $job_id, $data );
}

public static function mark_running( $job_id ) {
return static::update_status( $job_id, 'running' );
}

public static function mark_completed( $job_id ) {
return static::update_status( $job_id, 'completed' );
}

public static function mark_failed( $job_id ) {
return static::update_status( $job_id, 'failed' );
}

public static function get_by_user( $user_id, $args = [] ) {
global $wpdb;
$table = static::get_table_name();

$limit = isset( $args['limit'] ) ? intval( $args['limit'] ) : 20;
$offset = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;

return $wpdb->get_results(
$wpdb->prepare(
"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
$user_id,
$limit,
$offset
)
);
}

public static function get_by_status( $status, $args = [] ) {
global $wpdb;
$table = static::get_table_name();

$limit = isset( $args['limit'] ) ? intval( $args['limit'] ) : 100;

return $wpdb->get_results(
$wpdb->prepare(
"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d",
$status,
$limit
)
);
}
}
