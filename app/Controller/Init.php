<?php

namespace WP_AIE\Controller;

defined( 'ABSPATH' ) or exit;

/**
 * Init Controller Class
 *
 * Handles plugin initialization, hooks, and admin pages.
 *
 * @package WP_AIE\Controller
 */
class Init {

	/**
	 * Import Controller
	 *
	 * @var Import_Controller
	 */
	private $import_controller;

	/**
	 * Export Controller
	 *
	 * @var Export_Controller
	 */
	private $export_controller;

	/**
	 * Job Controller
	 *
	 * @var Job_Controller
	 */
	private $job_controller;

	/**
	 * Functions Controller
	 *
	 * @var Functions_Controller
	 */
	private $functions_controller;

	/**
	 * Media Sync Controller
	 *
	 * @var Media_Sync_Controller
	 */
	private $media_sync_controller;

	/**
	 * Media Hash Controller
	 *
	 * @var Media_Hash_Controller
	 */
	private $media_hash_controller;

	/**
	 * Content Updater Controller
	 *
	 * @var Content_Updater_Controller
	 */
	private $content_updater_controller;

	/**
	 * Content Sync Controller
	 *
	 * @var Content_Sync_Controller
	 */
	private $content_sync_controller;

	/**
	 * Content Sync API Controller
	 *
	 * @var Content_Sync_API_Controller
	 */
	private $content_sync_api_controller;

	/**
	 * Cron Manager
	 *
	 * @var \WP_AIE\Model\Queue\Cron_Manager
	 */
	private $cron_manager;

	/**
	 * Constructor
	 **/
	function __construct() {

		// load plugin translations
		add_action( 'init', array( $this, 'load_translations' ) );

		// load admin scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );

		// add settings pages
		add_action( 'admin_menu', array( $this, 'add_settings_pages' ) );

		// Initialize AJAX controllers
		add_action( 'init', array( $this, 'init_controllers' ) );

		// Initialize cron manager
		add_action( 'init', array( $this, 'init_cron_manager' ) );
	}

	/**
	 * Initialize AJAX controllers
	 */
	function init_controllers() {
		$this->import_controller = new Import_Controller();
		$this->import_controller->init();

		$this->export_controller = new Export_Controller();
		$this->export_controller->init();

		$this->job_controller = new Job_Controller();
		$this->job_controller->init();

		$this->functions_controller = new Functions_Controller();
		$this->functions_controller->init();

		$this->media_sync_controller = new Media_Sync_Controller();
		$this->media_sync_controller->init();

		$this->media_hash_controller = new Media_Hash_Controller();
		$this->media_hash_controller->init();

		$this->content_updater_controller = new Content_Updater_Controller();
		$this->content_updater_controller->init();

		$this->content_sync_controller = new Content_Sync_Controller();
		$this->content_sync_controller->init();
		$this->content_sync_controller->register_post_list_hooks();

		// Initialize REST API controller (safe initialization)
		try {
			$this->content_sync_api_controller = new Content_Sync_API_Controller();
		} catch ( \Exception $e ) {
			// Log error but don't break plugin activation
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WP_AIE: Failed to initialize Content_Sync_API_Controller: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Initialize Cron Manager
	 */
	function init_cron_manager() {
		$this->cron_manager = new \WP_AIE\Model\Queue\Cron_Manager();
		$this->cron_manager->init();
	}

	/**
	 * Load plugin translations
	 */
	function load_translations() {

		load_plugin_textdomain( 'wp-advanced-import-export', false, dirname( plugin_basename( WP_AIE_FILE ) ) . '/languages' );
	}

	/**
	 * Load admin scripts
	 */
	function load_admin_assets( $admin_page ) {

		if ( ! in_array(
			$admin_page,
			array(
				'toplevel_page_wp-advanced-import-export',
				'advanced-import-export_page_wp-aie-export',
				'advanced-import-export_page_wp-aie-content-sync',
				'advanced-import-export_page_wp-aie-content-updater',
				'advanced-import-export_page_wp-aie-jobs-log',
				'advanced-import-export_page_wp-aie-media-sync',
				'advanced-import-export_page_wp-aie-functions',
			)
		) ) {
			return;
		}

		// Enqueue CodeMirror for code editor
		if ( 'advanced-import-export_page_wp-aie-functions' === $admin_page ) {
			wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );
		}

		wp_enqueue_script(
			'wp-advanced-import-export-scripts',
			plugins_url( 'assets/js/app.js', WP_AIE_FILE ),
			array( 'jquery' ),
			filemtime( plugin_dir_path( WP_AIE_FILE ) . 'assets/js/app.js' ),
			array(
				'in_footer' => true,
			)
		);

		// Localize script with AJAX data
		wp_localize_script(
			'wp-advanced-import-export-scripts',
			'aieData',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'aie_nonce' ),
				'pluginUrl'    => plugins_url( '', WP_AIE_FILE ),
				'functionsUrl' => admin_url( 'admin.php?page=wp-aie-functions' ),
				'currentPage'  => isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '',
				'i18n'         => array(
					// General
					'skip'                       => __( 'Skip', 'wp-advanced-import-export' ),
					'uploading'                  => __( 'Uploading...', 'wp-advanced-import-export' ),
					'processing'                 => __( 'Processing...', 'wp-advanced-import-export' ),
					'completed'                  => __( 'Completed', 'wp-advanced-import-export' ),
					'failed'                     => __( 'Failed', 'wp-advanced-import-export' ),
					'confirmCancel'              => __( 'Are you sure you want to cancel?', 'wp-advanced-import-export' ),
					'errorOccurred'              => __( 'An error occurred', 'wp-advanced-import-export' ),
					'fileTooLarge'               => __( 'File size exceeds maximum allowed', 'wp-advanced-import-export' ),
					'invalidFileType'            => __( 'Invalid file type', 'wp-advanced-import-export' ),
					'selectFile'                 => __( 'Please select a file', 'wp-advanced-import-export' ),
					'selectFields'               => __( 'Please select at least one field', 'wp-advanced-import-export' ),
					'mapFields'                  => __( 'Please map at least one field', 'wp-advanced-import-export' ),
					'name_required'              => __( 'Please enter a function name.', 'wp-advanced-import-export' ),
					'code_required'              => __( 'Please enter the PHP code for your function.', 'wp-advanced-import-export' ),
					'category_required'          => __( 'Please select a category.', 'wp-advanced-import-export' ),

					// Export Step 3
					'fieldAlreadyAdded'          => __( 'This field is already added', 'wp-advanced-import-export' ),
					'confirmRemoveAllFields'     => __( 'Are you sure you want to remove all fields?', 'wp-advanced-import-export' ),
					'functionsSavedSuccess'      => __( 'Functions saved successfully', 'wp-advanced-import-export' ),
					'enterTestValue'             => __( 'Please enter a test value', 'wp-advanced-import-export' ),
					'noFunctionsToTest'          => __( 'No functions to test', 'wp-advanced-import-export' ),
					'configErrorAieData'         => __( 'Configuration error: aieData not found', 'wp-advanced-import-export' ),
					'testFailed'                 => __( 'Test failed', 'wp-advanced-import-export' ),
					'errorTestingPipeline'       => __( 'Error testing pipeline', 'wp-advanced-import-export' ),

					// Jobs Log
					'errorLoadingJobs'           => __( 'Error loading jobs: ', 'wp-advanced-import-export' ),
					'confirmResumeJob'           => __( 'Resume this job?', 'wp-advanced-import-export' ),
					'jobResumedSuccess'          => __( 'Job resumed successfully', 'wp-advanced-import-export' ),
					'errorResumingJob'           => __( 'Error resuming job: ', 'wp-advanced-import-export' ),
					'confirmRestartJob'          => __( 'Restart this job with the same settings?', 'wp-advanced-import-export' ),
					'jobRestartedSuccess'        => __( 'Job restarted successfully', 'wp-advanced-import-export' ),
					'errorRestartingJob'         => __( 'Error restarting job: ', 'wp-advanced-import-export' ),
					'confirmRetryJob'            => __( 'Retry this job with the same settings?', 'wp-advanced-import-export' ),
					'jobCreatedStarting'         => __( 'Job created, starting process...', 'wp-advanced-import-export' ),
					'errorRetryingJob'           => __( 'Error retrying job: ', 'wp-advanced-import-export' ),
					'jobDeletedSuccess'          => __( 'Job deleted successfully', 'wp-advanced-import-export' ),
					'errorDeletingJob'           => __( 'Error deleting job: ', 'wp-advanced-import-export' ),
					'downloadFailed'             => __( 'Download failed', 'wp-advanced-import-export' ),
					'failedGenerateDownloadUrl'  => __( 'Failed to generate download URL', 'wp-advanced-import-export' ),
					'errorLoadingJobDetails'     => __( 'Error loading job details: ', 'wp-advanced-import-export' ),

					// Content Sync
					'failedLoadSites'            => __( 'Failed to load sites', 'wp-advanced-import-export' ),
					'confirmDeleteSiteConnection' => __( 'Are you sure you want to delete this site connection?', 'wp-advanced-import-export' ),
					'failedDeleteSite'           => __( 'Failed to delete site', 'wp-advanced-import-export' ),
					'connectionTestFailed'       => __( 'Connection test failed', 'wp-advanced-import-export' ),
					'confirmRegenerateSiteKey'   => __( 'Are you sure you want to regenerate this site\'s API key?\n\nThis will break the connection with the remote site until you update the key there.', 'wp-advanced-import-export' ),
					'newApiKey'                  => __( 'New API Key: ', 'wp-advanced-import-export' ),
					'failedRegenerateKey'        => __( 'Failed to regenerate key', 'wp-advanced-import-export' ),
					'apiKeyCopied'               => __( 'API key copied to clipboard', 'wp-advanced-import-export' ),
					'confirmRegenerateMyKey'     => __( 'Are you sure you want to regenerate your API key?\n\nThis will invalidate the current key and all remote sites will need to update their connection settings with the new key.', 'wp-advanced-import-export' ),
					'failedRegenerateApiKey'     => __( 'Failed to regenerate API key', 'wp-advanced-import-export' ),

					// Media Sync
					'enterFolderPath'            => __( 'Please enter a folder path', 'wp-advanced-import-export' ),
					'requestFailed'              => __( 'Request failed', 'wp-advanced-import-export' ),
					'noFilesToSync'              => __( 'No files to sync. Please scan a folder first.', 'wp-advanced-import-export' ),
					'invalidFolderPath'          => __( 'Invalid folder path', 'wp-advanced-import-export' ),
					'syncStarted'                => __( 'Synchronization started', 'wp-advanced-import-export' ),
					'syncPaused'                 => __( 'Sync paused', 'wp-advanced-import-export' ),
					'syncResumed'                => __( 'Sync resumed', 'wp-advanced-import-export' ),
					'confirmCancelSync'          => __( 'Are you sure you want to cancel the synchronization?\n\nThis will stop the process and you\'ll need to start over.', 'wp-advanced-import-export' ),
					'syncCancelled'              => __( 'Sync cancelled', 'wp-advanced-import-export' ),

					// Post Sync
					'selectAtLeastOnePost'       => __( 'Please select at least one post', 'wp-advanced-import-export' ),
					'selectSite'                 => __( 'Please select a site', 'wp-advanced-import-export' ),
					'noPostsSelected'            => __( 'No posts selected', 'wp-advanced-import-export' ),

					// Export
					'exportStartedSuccess'       => __( 'Export started successfully', 'wp-advanced-import-export' ),
					'exportCompletedSuccess'     => __( 'Export completed successfully!', 'wp-advanced-import-export' ),
					'confirmCancelExport'        => __( 'Are you sure you want to cancel this export?', 'wp-advanced-import-export' ),
					'exportCancelled'            => __( 'Export cancelled', 'wp-advanced-import-export' ),
				),
			)
		);

		// Localize script for Content Sync page
		if ( 'advanced-import-export_page_wp-aie-content-sync' === $admin_page ) {
			wp_localize_script(
				'wp-advanced-import-export-scripts',
				'aieContentSync',
				array(
					'nonce'     => wp_create_nonce( 'aie_nonce' ),
					'isPremium' => function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code(),
				)
			);
		}

		wp_enqueue_style(
			'wp-advanced-import-export-styles',
			plugins_url( 'assets/css/app.css', WP_AIE_FILE ),
			false,
			filemtime( plugin_dir_path( WP_AIE_FILE ) . 'assets/css/app.css' )
		);
	}

	/**
	 * Add plugin's settings pages
	 */
	function add_settings_pages() {

		add_menu_page(
			__( 'Advanced Import Export', 'wp-advanced-import-export' ),
			__( 'Advanced Import Export', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-advanced-import-export',
			array( $this, 'display_settings_import_page' ),
			'dashicons-update-alt',
			99,
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Import', 'wp-advanced-import-export' ),
			__( 'Import', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-advanced-import-export',
			array( $this, 'display_settings_import_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Export', 'wp-advanced-import-export' ),
			__( 'Export', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-export',
			array( $this, 'display_settings_export_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Content Sync', 'wp-advanced-import-export' ),
			__( 'Content Sync', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-content-sync',
			array( $this, 'display_content_sync_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Content Updater', 'wp-advanced-import-export' ),
			__( 'Content Updater', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-content-updater',
			array( $this, 'display_content_updater_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Media Sync', 'wp-advanced-import-export' ),
			__( 'Media Sync', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-media-sync',
			array( $this, 'display_media_sync_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Functions', 'wp-advanced-import-export' ),
			__( 'Functions', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-functions',
			array( $this, 'display_settings_functions_page' )
		);

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Jobs Log', 'wp-advanced-import-export' ),
			__( 'Jobs Log', 'wp-advanced-import-export' ),
			'manage_options',
			'wp-aie-jobs-log',
			array( $this, 'display_jobs_log_page' )
		);
	}

	/**
	 * Display Import Settings Page
	 */
	function display_settings_import_page() {
		WP_AIE()->View->load( 'settings/import' );
	}

	/**
	 * Display Export Settings Page
	 */
	function display_settings_export_page() {
		WP_AIE()->View->load( 'settings/export' );
	}

	/**
	 * Display Content Sync Settings Page
	 */
	function display_content_sync_page() {
		WP_AIE()->View->load( 'settings/content_sync' );
	}

	/**
	 * Display Content Updater Settings Page
	 */
	function display_content_updater_page() {
		WP_AIE()->View->load( 'settings/content_updater' );
	}

	/**
	 * Display Media Sync Settings Page
	 */
	function display_media_sync_page() {
		WP_AIE()->View->load( 'settings/media_sync' );
	}

	/**
	 * Display Jobs Log Page
	 */
	function display_jobs_log_page() {
		WP_AIE()->View->load( 'settings/jobs-log' );
	}

	/**
	 * Display "Functions" admin page
	 */
	function display_settings_functions_page() {
		WP_AIE()->View->load( 'settings/functions' );
	}
}
