<?php
namespace WP_AIE\helper;

class fs {
public static function get_upload_dir() {
$upload = wp_upload_dir();
$aie_dir = $upload['basedir'] . '/aie-uploads';
$aie_url = $upload['baseurl'] . '/aie-uploads';
if ( ! file_exists( $aie_dir ) ) {
wp_mkdir_p( $aie_dir );
}
return ['path' => $aie_dir, 'url' => $aie_url];
}

public static function handle_upload( $file ) {
if ( ! isset( $file['error'] ) || is_array( $file['error'] ) ) {
return new \WP_Error( 'invalid_upload', 'Invalid file upload.' );
}
$upload_dir = self::get_upload_dir();
$filename = wp_unique_filename( $upload_dir['path'], $file['name'] );
$file_path = $upload_dir['path'] . '/' . $filename;
if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
return new \WP_Error( 'upload_failed', 'Failed to move uploaded file.' );
}
return ['file' => $filename, 'path' => $file_path];
}
}
