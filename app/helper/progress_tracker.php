<?php
namespace WP_AIE\helper;

use WP_AIE\model\job;
use WP_AIE\model\log;

class progress_tracker {

public static function update_progress( $job_id, $total, $processed, $success, $failed ) {
return job::update_progress( $job_id, $total, $processed, $success, $failed );
}

public static function increment( $job_id, $success = true ) {
return job::increment( $job_id, $success );
}

public static function get_progress( $job_id ) {
return job::get_progress( $job_id );
}

public static function mark_complete( $job_id, $success = true ) {
return $success ? job::mark_completed( $job_id ) : job::mark_failed( $job_id );
}

public static function mark_running( $job_id ) {
return job::mark_running( $job_id );
}

public static function mark_failed( $job_id, $error_message = '' ) {
if ( $error_message ) {
log::error( $job_id, $error_message );
}
return job::mark_failed( $job_id );
}
}
