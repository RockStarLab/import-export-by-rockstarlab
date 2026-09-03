<?php
/**
 * Media Hash Controller
 *
 * AJAX endpoints for managing media file hashes
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\Media_Hash;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX endpoints for the shared Media Library hash index.
 */
class Media_Hash_Controller extends Base_Controller {

	/**
	 * Get AJAX actions.
	 *
	 * @return array<string,array<string,string>>
	 */
	protected function get_ajax_actions() {
			return [
				'get_hash_statistics'  => [ 'callback' => 'get_hash_statistics' ],
				'bulk_add_hashes'      => [ 'callback' => 'bulk_add_hashes' ],
				'check_duplicate_hash' => [ 'callback' => 'check_duplicate_hash' ],
				'get_debug_site_info'  => [ 'callback' => 'get_debug_site_info' ],
			];
	}

	/**
	 * Get statistics about hashed media files
	 */
	public function get_hash_statistics() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$stats = Media_Hash::get_statistics();
		$this->send_success( $stats );
	}

	/**
	 * Bulk add hashes to existing media files
	 */
	public function bulk_add_hashes() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$batch_size = absint( $this->get_request_param( 'batch_size', 50 ) );
		$offset     = absint( $this->get_request_param( 'offset', 0 ) );

		// Limit batch size for performance.
		$batch_size = min( $batch_size, 100 );
		$batch_size = max( $batch_size, 1 );

		$result = Media_Hash::bulk_add_hashes( $batch_size, $offset );

		$this->send_success( $result );
	}

	/**
	 * Check if file is a duplicate based on hash
	 */
	public function check_duplicate_hash() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'file_path' ] );
		$file_path = $this->get_request_param( 'file_path' );

		// Security: validate file path is within uploads directory.
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'];
		$real_path  = realpath( $file_path );
		$real_base  = realpath( $base_dir );

		if ( false === $real_path || false === $real_base || 0 !== strpos( $real_path, trailingslashit( $real_base ) ) ) {
			$this->send_error(
				new \WP_Error(
					'invalid_path',
					__( 'Invalid file path. Path must be within uploads directory.', 'import-export-by-rockstarlab' )
				)
			);
		}

		$duplicate_id = Media_Hash::find_duplicate( $real_path );

		if ( $duplicate_id ) {
			$attachment_url = wp_get_attachment_url( $duplicate_id );
			$this->send_success(
				[
					'is_duplicate'   => true,
					'attachment_id'  => $duplicate_id,
					'attachment_url' => $attachment_url,
				]
			);
		} else {
			$this->send_success(
				[
					'is_duplicate' => false,
				]
			);
		}
	}

	/**
	 * Get a safe site information snapshot for support/debugging.
	 */
	public function get_debug_site_info() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->send_success(
			[
				'info' => $this->build_debug_site_info(),
			]
		);
	}

	/**
	 * Build a plain-text debug report.
	 *
	 * @return string
	 */
	private function build_debug_site_info() {
		global $wpdb, $wp_version;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$lines           = [];
		$active_theme    = wp_get_theme();
		$upload_dir      = wp_upload_dir();
		$server_software = filter_input( INPUT_SERVER, 'SERVER_SOFTWARE', FILTER_UNSAFE_RAW );

		$lines[] = 'Import Export by RockStarLab - Site Debug Info';
		$lines[] = 'Generated: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		$lines[] = '';

		$this->append_debug_section(
			$lines,
			'WordPress',
			[
				'Site URL'            => site_url(),
				'Home URL'            => home_url(),
				'Current Request URL' => \RockStarLab\ImportExport\Helper\Site_URL::current_request_site_url(),
				'WordPress Version'   => $wp_version,
				'Multisite'           => is_multisite() ? 'Yes' : 'No',
				'Environment Type'    => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'unknown',
				'Language'            => get_locale(),
				'Timezone'            => wp_timezone_string(),
				'Permalink Structure' => get_option( 'permalink_structure' ) ?: 'Plain',
				'Debug Mode'          => defined( 'WP_DEBUG' ) && WP_DEBUG ? 'Enabled' : 'Disabled',
				'Script Debug'        => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? 'Enabled' : 'Disabled',
				'Memory Limit'        => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : 'Not set',
				'Max Memory Limit'    => defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : 'Not set',
				'Cron Disabled'       => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? 'Yes' : 'No',
				'Alternate Cron'      => defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ? 'Yes' : 'No',
			]
		);

		$this->append_debug_section(
			$lines,
			'Import Export by RockStarLab',
			[
				'Free Version'      => defined( 'RSL_IE_VERSION' ) ? RSL_IE_VERSION : 'Unknown',
				'Free Plugin Path'  => defined( 'RSL_IE_PATH' ) ? RSL_IE_PATH : 'Unknown',
				'PRO Addon Active'  => class_exists( '\RockStarLab\ImportExport\Helper\Pro_Addon' ) && \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active() ? 'Yes' : 'No',
				'PRO Addon Enabled' => class_exists( '\RockStarLab\ImportExport\Helper\Pro_Addon' ) && \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_enabled() ? 'Yes' : 'No',
				'Uploads Writable'  => wp_is_writable( $upload_dir['basedir'] ) ? 'Yes' : 'No',
				'Upload Base Dir'   => $upload_dir['basedir'],
				'Upload Base URL'   => $upload_dir['baseurl'],
				'Upload Error'      => $upload_dir['error'] ?: 'None',
			]
		);

		$this->append_debug_section(
			$lines,
			'Active Theme',
			[
				'Name'       => $active_theme->get( 'Name' ),
				'Version'    => $active_theme->get( 'Version' ),
				'Author'     => wp_strip_all_tags( $active_theme->get( 'Author' ) ),
				'Template'   => $active_theme->get_template(),
				'Stylesheet' => $active_theme->get_stylesheet(),
			]
		);

		$this->append_debug_section(
			$lines,
			'Server',
			[
				'PHP Version'         => PHP_VERSION,
				'PHP SAPI'            => PHP_SAPI,
				'Server Software'     => $server_software ? sanitize_text_field( wp_unslash( $server_software ) ) : 'Unknown',
				'Operating System'    => PHP_OS_FAMILY . ' (' . PHP_OS . ')',
				'MySQL Version'       => $wpdb->db_version(),
				'Database Charset'    => $wpdb->charset,
				'Database Collate'    => $wpdb->collate ?: 'Not set',
				'Table Prefix Length' => strlen( $wpdb->prefix ),
				'ABSPATH Writable'    => wp_is_writable( ABSPATH ) ? 'Yes' : 'No',
				'Filesystem Method'   => get_filesystem_method(),
			]
		);

		$this->append_debug_section(
			$lines,
			'PHP Limits',
			[
				'memory_limit'        => ini_get( 'memory_limit' ),
				'max_execution_time'  => ini_get( 'max_execution_time' ),
				'max_input_time'      => ini_get( 'max_input_time' ),
				'max_input_vars'      => ini_get( 'max_input_vars' ),
				'post_max_size'       => ini_get( 'post_max_size' ),
				'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
				'max_file_uploads'    => ini_get( 'max_file_uploads' ),
			]
		);

		$this->append_debug_section(
			$lines,
			'PHP Extensions',
			[
				'curl'      => extension_loaded( 'curl' ) ? 'Loaded' : 'Missing',
				'dom'       => extension_loaded( 'dom' ) ? 'Loaded' : 'Missing',
				'fileinfo'  => extension_loaded( 'fileinfo' ) ? 'Loaded' : 'Missing',
				'gd'        => extension_loaded( 'gd' ) ? 'Loaded' : 'Missing',
				'imagick'   => extension_loaded( 'imagick' ) ? 'Loaded' : 'Missing',
				'json'      => extension_loaded( 'json' ) ? 'Loaded' : 'Missing',
				'mbstring'  => extension_loaded( 'mbstring' ) ? 'Loaded' : 'Missing',
				'mysqli'    => extension_loaded( 'mysqli' ) ? 'Loaded' : 'Missing',
				'openssl'   => extension_loaded( 'openssl' ) ? 'Loaded' : 'Missing',
				'simplexml' => extension_loaded( 'simplexml' ) ? 'Loaded' : 'Missing',
				'xml'       => extension_loaded( 'xml' ) ? 'Loaded' : 'Missing',
				'xmlreader' => extension_loaded( 'xmlreader' ) ? 'Loaded' : 'Missing',
				'xmlwriter' => extension_loaded( 'xmlwriter' ) ? 'Loaded' : 'Missing',
				'zip'       => extension_loaded( 'zip' ) ? 'Loaded' : 'Missing',
			]
		);

		$this->append_plugins_debug_info( $lines );
		$this->append_mu_plugins_debug_info( $lines );
		$this->append_dropins_debug_info( $lines );

		$lines[] = '';

		return implode( "\n", $lines );
	}

	/**
	 * Append a formatted key/value section.
	 *
	 * @param array  $lines   Output lines.
	 * @param string $heading Section heading.
	 * @param array  $items   Key/value items.
	 * @return void
	 */
	private function append_debug_section( &$lines, $heading, $items ) {
		$lines[] = '### ' . $heading;
		foreach ( $items as $label => $value ) {
			$lines[] = $label . ': ' . $this->debug_value_to_string( $value );
		}
		$lines[] = '';
	}

	/**
	 * Append installed plugins.
	 *
	 * @param array $lines Output lines.
	 * @return void
	 */
	private function append_plugins_debug_info( &$lines ) {
		$plugins        = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', [] );

		$lines[] = '### Plugins';
		$lines[] = 'Active Plugins: ' . count( $active_plugins );
		$lines[] = 'Installed Plugins: ' . count( $plugins );

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			$status  = in_array( $plugin_file, $active_plugins, true ) ? 'active' : 'inactive';
			$network = is_plugin_active_for_network( $plugin_file ) ? ', network-active' : '';
			$lines[] = sprintf(
				'- [%1$s%2$s] %3$s %4$s (%5$s)',
				$status,
				$network,
				wp_strip_all_tags( $plugin_data['Name'] ?? $plugin_file ),
				$plugin_data['Version'] ?? 'unknown',
				$plugin_file
			);
		}
		$lines[] = '';
	}

	/**
	 * Append must-use plugins.
	 *
	 * @param array $lines Output lines.
	 * @return void
	 */
	private function append_mu_plugins_debug_info( &$lines ) {
		$mu_plugins = get_mu_plugins();

		$lines[] = '### Must-Use Plugins';
		if ( empty( $mu_plugins ) ) {
			$lines[] = 'None';
			$lines[] = '';
			return;
		}

		foreach ( $mu_plugins as $plugin_file => $plugin_data ) {
			$lines[] = sprintf(
				'- %1$s %2$s (%3$s)',
				wp_strip_all_tags( $plugin_data['Name'] ?? $plugin_file ),
				$plugin_data['Version'] ?? 'unknown',
				$plugin_file
			);
		}
		$lines[] = '';
	}

	/**
	 * Append drop-ins.
	 *
	 * @param array $lines Output lines.
	 * @return void
	 */
	private function append_dropins_debug_info( &$lines ) {
		$dropins = get_dropins();

		$lines[] = '### Drop-ins';
		if ( empty( $dropins ) ) {
			$lines[] = 'None';
			$lines[] = '';
			return;
		}

		foreach ( $dropins as $dropin_file => $dropin_data ) {
			$lines[] = sprintf(
				'- %1$s: %2$s',
				$dropin_file,
				wp_strip_all_tags( $dropin_data['Name'] ?? 'Loaded' )
			);
		}
		$lines[] = '';
	}

	/**
	 * Convert debug values to safe strings.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function debug_value_to_string( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'Yes' : 'No';
		}

		if ( null === $value || '' === $value ) {
			return 'Not set';
		}

		if ( is_scalar( $value ) ) {
			return wp_strip_all_tags( (string) $value );
		}

		return wp_json_encode( $value );
	}
}
