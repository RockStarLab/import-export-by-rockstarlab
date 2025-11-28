<?php
namespace WP_AIE\helper;

class progress_tracker {

public static function update_progress( $job_id, $total, $processed, $success, $failed ) {
global $wpdb;
$table = $wpdb->prefix . 'aie_jobs';

$percentage = $total > 0 ? round( ( $processed / $total ) * 100, 2 ) : 0;

return $wpdb->update(
$table,
[
'total_items' => $total,
'processed_items' => $processed,
'success_items' => $success,
'failed_items' => $failed,
'percentage' => $percentage,
],
[ 'id' => $job_id ],
[ '%d', '%d', '%d', '%d', '%f' ],
[ '%d' ]
);
}

public static function increment( $job_id, $success = true ) {
global $wpdb;
$table = $wpdb->prefix . 'aie_jobs';

$job = $wpdb->get_row(
$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $job_id )
);

if ( ! $job ) {
return false;
}

$processed = (int) $job->processed_items + 1;
$success_count = $success ? (int) $job->success_items + 1 : (int) $job->success_items;
$failed_count = ! $success ? (int) $job->failed_items + 1 : (int) $job->failed_items;

return self::update_progress(
$job_id,
(int) $job->total_items,
$processed,
$success_count,
$failed_count
);
}

public static function get_progress( $job_id ) {
global $wpdb;
$table = $wpdb->prefix . 'aie_jobs';

$job = $wpdb->get_row(
$wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $job_id )
);

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

public static function mark_complete( $job_id, $success = true ) {
global $wpdb;
$table = $wpdb->prefix . 'aie_jobs';

return $wpdb->update(
$table,
[
'status' => $success ? 'completed' : 'failed',
'completed_at' => current_time( 'mysql' ),
],
[ 'id' => $job_id ],
[ '%s', '%s' ],
[ '%d' ]
);
}

public static function mark_running( $job_id ) {
global $wpdb;
$table = $wpdb->prefix . 'aie_jobs';

return $wpdb->update(
$table,
[ 'status' => 'running' ],
[ 'id' => $job_id ],
[ '%s' ],
[ '%d' ]
);
}

public static function mark_failed( $job_id, $error_message = '' ) {
global $wpdb;
$table = $wpdb->prefix . 'aie_jobs';

$data = [
'status' => 'failed',
'completed_at' => current_time( 'mysql' ),
];

if ( $error_message ) {
logger::error( $job_id, $error_message );
}

return $wpdb->update(
$table,
$data,
[ 'id' => $job_id ],
[ '%s', '%s' ],
[ '%d' ]
);
}
}
