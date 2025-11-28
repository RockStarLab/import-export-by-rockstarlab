<?php
namespace WP_AIE\helper;

use WP_AIE\model\log;

class logger {

public static function info( $job_id, $message, $data = [] ) {
return log::info( $job_id, $message, $data );
}

public static function warning( $job_id, $message, $data = [] ) {
return log::warning( $job_id, $message, $data );
}

public static function error( $job_id, $message, $data = [] ) {
return log::error( $job_id, $message, $data );
}

public static function get_logs( $job_id, $level = null ) {
return log::get_by_job( $job_id, $level );
}

public static function clear_logs( $job_id ) {
return log::delete_by_job( $job_id );
}
}
