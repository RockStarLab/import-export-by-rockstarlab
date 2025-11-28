<?php
/**
 * Plugin Configuration
 *
 * @package WP_AIE
 */

defined( 'ABSPATH' ) || exit;

return [
	'version'         => WP_AIE_VERSION,
	'plugin_name'     => 'WP Advanced Import Export',
	'plugin_slug'     => 'wp-advanced-import-export',
	'text_domain'     => 'wp-advanced-import-export',
	'upload_dir'      => wp_upload_dir()['basedir'] . '/wp-aie',
	'upload_url'      => wp_upload_dir()['baseurl'] . '/wp-aie',
	'max_file_size'   => 50 * 1024 * 1024, // 50MB
	'chunk_size'      => 100, // Items per chunk
	'allowed_formats' => [ 'csv', 'json', 'xml' ],
];
