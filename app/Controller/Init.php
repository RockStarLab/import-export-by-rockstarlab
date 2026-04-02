<?php
/**
 * Init Controller Class
 *
 * Handles plugin initialization, hooks, and admin pages.
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

defined( 'ABSPATH' ) or exit;

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
	 * AI URL Importer Controller
	 *
	 * @var AI_URL_Importer_Controller
	 */
	private $ai_url_importer_controller;

	/**
	 * Cron Manager
	 *
	 * @var \WP_AIE\Model\Queue\Cron_Manager
	 */
	private $cron_manager;

	/**
	 * Settings Controller
	 *
	 * @var Settings_Controller
	 */
	private $settings_controller;

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

		// Handle welcome page redirect
		add_action( 'admin_init', array( $this, 'welcome_redirect' ) );

		// Show 5-star review request notice (plugin pages only, after 1 week)
		\WP_AIE\Helper\Review_Notice::init();

		// Fix attachment URLs for "keep in current directory" mode files outside uploads.
		add_filter( 'wp_get_attachment_url', array( $this, 'fix_keep_mode_attachment_url' ), 10, 2 );
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
			}
		}

		$this->ai_url_importer_controller = new AI_URL_Importer_Controller();
		$this->ai_url_importer_controller->init();

		$this->settings_controller = new Settings_Controller();
		$this->settings_controller->init();
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
	 *
	 * Note: load_plugin_textdomain() is no longer needed since WordPress 4.6.
	 * WordPress automatically loads translations from the languages directory.
	 */
	function load_translations() {
		// Translations are loaded automatically by WordPress since version 4.6.
	}

	/**
	 * Load admin scripts
	 */
	function load_admin_assets( $admin_page ) {

	if ( ! in_array(
		$admin_page,
		array(
			'toplevel_page_advanced-import-export',
			'advanced-import-export_page_wp-aie-import',
			'advanced-import-export_page_wp-aie-export',
			'advanced-import-export_page_wp-aie-content-sync',
			'advanced-import-export_page_wp-aie-content-updater',
			'advanced-import-export_page_wp-aie-jobs-log',
			'advanced-import-export_page_wp-aie-media-sync',
			'advanced-import-export_page_wp-aie-ai-url-importer',
			'advanced-import-export_page_wp-aie-functions',
			'advanced-import-export_page_wp-aie-plugin-options',
		)
	) ) {
			return;
		}

		// Enqueue CodeMirror for code editor
		if ( 'advanced-import-export_page_wp-aie-functions' === $admin_page ) {
			wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );
		}

		wp_enqueue_script(
			'advanced-import-export-scripts',
			plugins_url( 'assets/js/app.js', WP_AIE_FILE ),
			array( 'jquery', 'jquery-ui-sortable' ),
			filemtime( plugin_dir_path( WP_AIE_FILE ) . 'assets/js/app.js' ),
			array(
				'in_footer' => true,
			)
		);

		// Localize script with AJAX data
		wp_localize_script(
			'advanced-import-export-scripts',
			'aieData',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'aie_nonce' ),
				'pluginUrl'      => plugins_url( '', WP_AIE_FILE ),
				'functionsUrl'   => admin_url( 'admin.php?page=wp-aie-functions' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified via verify_request(). -- Input is sanitized and validated in context.
				'optionsUrl'     => admin_url( 'admin.php?page=wp-aie-plugin-options' ),
				'currentPage'    => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading admin page slug, no nonce needed.
				'hasOpenAIApiKey' => \WP_AIE\Helper\AI_Function_Generator::has_api_key(),
				'isPremium'      => function_exists( 'aie_fs' ) && aie_fs()->can_use_premium_code(),
				'premiumDataTypes' => [
					'custom_post_types', 'custom_post_type',
					'media',
					'menu', 'menus', 'nav_menu',
					'user', 'users',
					'comment', 'comments',
					'taxonomy', 'taxonomy_term', 'taxonomy_terms', 'term', 'terms',
					'category', 'categories', 'tag', 'tags',
					'woo_product', 'product', 'products',
					'woo_order', 'woo_orders',
					'woo_coupon',
					'woo_attribute',
					'database_table',
				],
				'i18n'           => array(
					// General
					'skip'                       => __( 'Skip', 'advanced-import-export' ),
					'uploading'                  => __( 'Uploading...', 'advanced-import-export' ),
					'processing'                 => __( 'Processing...', 'advanced-import-export' ),
					'completed'                  => __( 'Completed', 'advanced-import-export' ),
					'failed'                     => __( 'Failed', 'advanced-import-export' ),
					'confirmCancel'              => __( 'Are you sure you want to cancel?', 'advanced-import-export' ),
					'errorOccurred'              => __( 'An error occurred', 'advanced-import-export' ),
					'fileTooLarge'               => __( 'File size exceeds maximum allowed', 'advanced-import-export' ),
					'invalidFileType'            => __( 'Invalid file type', 'advanced-import-export' ),
					'invalidFileTypeCsv'         => __( 'Invalid file type. Please upload CSV files only.', 'advanced-import-export' ),
					'fileUploadedSuccessfully'   => __( 'File uploaded successfully', 'advanced-import-export' ),
					'uploadFailed'               => __( 'Upload failed', 'advanced-import-export' ),
					'noFileDataAvailable'        => __( 'No file data available', 'advanced-import-export' ),
					'noPreviewDataAvailable'     => __( 'No preview data available', 'advanced-import-export' ),
					'selectFile'                 => __( 'Please select a file', 'advanced-import-export' ),
					'selectFields'               => __( 'Please select at least one field', 'advanced-import-export' ),
					'mapFields'                  => __( 'Please map at least one field', 'advanced-import-export' ),
					'name_required'              => __( 'Please enter a function name.', 'advanced-import-export' ),
					'code_required'              => __( 'Please enter the PHP code for your function.', 'advanced-import-export' ),
					'category_required'          => __( 'Please select a category.', 'advanced-import-export' ),

					// Export Step 3
					'fieldAlreadyAdded'          => __( 'This field is already added', 'advanced-import-export' ),
					'confirmRemoveAllFields'     => __( 'Are you sure you want to remove all fields?', 'advanced-import-export' ),
					'functionsSavedSuccess'      => __( 'Functions saved successfully', 'advanced-import-export' ),
					'enterTestValue'             => __( 'Please enter a test value', 'advanced-import-export' ),
					'noFunctionsToTest'          => __( 'No functions to test', 'advanced-import-export' ),
					'pleaseAddAtLeastOneFunction' => __( 'Please add at least one function to test', 'advanced-import-export' ),
					'createFunctionsInLibrary'   => __( 'Creating custom functions will be available in the Functions Library section', 'advanced-import-export' ),
					'errorLoadingFunctions'      => __( 'Error loading functions', 'advanced-import-export' ),
					'importStartedSuccessfully'  => __( 'Import started successfully', 'advanced-import-export' ),
					'importCompletedSuccessfully' => __( 'Import completed successfully!', 'advanced-import-export' ),
					'importCancelled'            => __( 'Import cancelled', 'advanced-import-export' ),
					'importFailed'               => __( 'Import failed', 'advanced-import-export' ),
					'configErrorAieData'         => __( 'Configuration error: aieData not found', 'advanced-import-export' ),
					'testFailed'                 => __( 'Test failed', 'advanced-import-export' ),
					'errorTestingPipeline'       => __( 'Error testing pipeline', 'advanced-import-export' ),
					
					// Custom Field Modal
					'addTaxonomyField'           => __( 'Add Taxonomy Field', 'advanced-import-export' ),
					'addCustomField'             => __( 'Add Custom Field', 'advanced-import-export' ),
					'enterTaxonomySlug'          => __( 'Enter taxonomy slug (e.g., category, post_tag, product_cat)', 'advanced-import-export' ),
					'enterFieldKey'              => __( 'Enter field key (e.g., _custom_price)', 'advanced-import-export' ),
					'dataFormat'                 => __( 'Data Format', 'advanced-import-export' ),
					'termIdFormat'               => __( 'Term ID (e.g., 5, 12, 23)', 'advanced-import-export' ),
					'termSlugFormat'             => __( 'Term Slug (e.g., technology, news)', 'advanced-import-export' ),
					'termNameFormat'             => __( 'Term Name (e.g., Technology, News)', 'advanced-import-export' ),
					'selectTaxonomyDataFormat'   => __( 'Select the format of taxonomy data in your CSV file.', 'advanced-import-export' ),				'taxonomySlugLabel'          => __( 'Taxonomy Slug', 'advanced-import-export' ),
				'taxonomySlugDescription'    => __( 'The slug of the taxonomy (category, post_tag, or custom taxonomy).', 'advanced-import-export' ),
				'metaKeyLabel'               => __( 'Meta Key', 'advanced-import-export' ),
				'metaKeyDescription'         => __( 'The meta key for the custom field (e.g., _custom_price, my_custom_field).', 'advanced-import-export' ),
				'cancel'                     => __( 'Cancel', 'advanced-import-export' ),
					'addField'                   => __( 'Add Field', 'advanced-import-export' ),
					'addTransformationFunction'  => __( 'Add transformation function', 'advanced-import-export' ),
					'removeMapping'              => __( 'Remove mapping', 'advanced-import-export' ),
					
					// Field Functions Modal
					'fieldTransformationFunctions' => __( 'Field Transformation Functions', 'advanced-import-export' ),
					'field'                      => __( 'Field', 'advanced-import-export' ),
					'type'                       => __( 'Type', 'advanced-import-export' ),
					'appliedFunctions'           => __( 'Applied Functions', 'advanced-import-export' ),
					'noFunctionsApplied'         => __( 'No functions applied yet. Add functions from the list below.', 'advanced-import-export' ),
					'functionsAppliedInOrder'    => __( 'Functions are applied in order from top to bottom. Drag to reorder.', 'advanced-import-export' ),
					'availableFunctions'         => __( 'Available Functions', 'advanced-import-export' ),
					'searchFunctions'            => __( 'Search functions...', 'advanced-import-export' ),
					'all'                        => __( 'All', 'advanced-import-export' ),
					'library'                    => __( 'Library', 'advanced-import-export' ),
					'custom'                     => __( 'Custom', 'advanced-import-export' ),
					'loadingFunctions'           => __( 'Loading functions...', 'advanced-import-export' ),
					'createNewFunction'          => __( 'Create New Function', 'advanced-import-export' ),
					'previewTransformation'      => __( 'Preview Transformation', 'advanced-import-export' ),
					'testValue'                  => __( 'Test Value', 'advanced-import-export' ),
					'enterTestValue'             => __( 'Enter test value...', 'advanced-import-export' ),
					'testPipeline'               => __( 'Test Pipeline', 'advanced-import-export' ),
					'applyFunctions'             => __( 'Apply Functions', 'advanced-import-export' ),
					// translators: %s = content placeholder.
					'initialValue'               => __( 'Initial Value', 'advanced-import-export' ),
					'finalResult'                => __( 'Final Result', 'advanced-import-export' ),
					// translators: %d is a dynamic value.
					'autoMappedFields'           => __( 'Auto-mapped %d fields', 'advanced-import-export' ),
					'dragSourceColumns'          => __( 'Drag source columns to WordPress fields to create mappings', 'advanced-import-export' ),

					// Jobs Log
					'viewDetails'                => __( 'View Details', 'advanced-import-export' ),
					'resume'                     => __( 'Resume', 'advanced-import-export' ),
					'retry'                      => __( 'Retry (Create new job with same parameters)', 'advanced-import-export' ),
					'download'                   => __( 'Download', 'advanced-import-export' ),
					'delete'                     => __( 'Delete', 'advanced-import-export' ),
					'errorLoadingJobs'           => __( 'Error loading jobs: ', 'advanced-import-export' ),
					'confirmResumeJob'           => __( 'Resume this job?', 'advanced-import-export' ),
					'jobResumedSuccess'          => __( 'Job resumed successfully', 'advanced-import-export' ),
					'errorResumingJob'           => __( 'Error resuming job: ', 'advanced-import-export' ),
					'confirmRestartJob'          => __( 'Restart this job with the same settings?', 'advanced-import-export' ),
					'jobRestartedSuccess'        => __( 'Job restarted successfully', 'advanced-import-export' ),
					'errorRestartingJob'         => __( 'Error restarting job: ', 'advanced-import-export' ),
					'confirmRetryJob'            => __( 'Retry this job with the same settings?', 'advanced-import-export' ),
					'jobCreatedStarting'         => __( 'Job created, starting process...', 'advanced-import-export' ),
					'errorRetryingJob'           => __( 'Error retrying job: ', 'advanced-import-export' ),
					'jobDeletedSuccess'          => __( 'Job deleted successfully', 'advanced-import-export' ),
					'errorDeletingJob'           => __( 'Error deleting job: ', 'advanced-import-export' ),
					'downloadFailed'             => __( 'Download failed', 'advanced-import-export' ),
					'failedGenerateDownloadUrl'  => __( 'Failed to generate download URL', 'advanced-import-export' ),
					'errorLoadingJobDetails'     => __( 'Error loading job details: ', 'advanced-import-export' ),
					'jobId'                      => __( 'ID', 'advanced-import-export' ),
					'jobType'                    => __( 'Type', 'advanced-import-export' ),
					'jobDataType'                => __( 'Data Type', 'advanced-import-export' ),
					'jobFileFormat'              => __( 'File Format', 'advanced-import-export' ),
					'jobStatus'                  => __( 'Status', 'advanced-import-export' ),
					'jobProgress'                => __( 'Progress', 'advanced-import-export' ),
					'jobItems'                   => __( 'Items', 'advanced-import-export' ),
					'jobSuccess'                 => __( 'Success', 'advanced-import-export' ),
					'jobCreated'                 => __( 'Created', 'advanced-import-export' ),
					'jobStarted'                 => __( 'Started', 'advanced-import-export' ),
					'jobCompleted'               => __( 'Completed', 'advanced-import-export' ),
					'jobFile'                    => __( 'File', 'advanced-import-export' ),
					'jobFileSize'                => __( 'File Size', 'advanced-import-export' ),
					'jobParameters'              => __( 'Parameters', 'advanced-import-export' ),
					'typeImport'                 => __( 'Import', 'advanced-import-export' ),
					'typeExport'                 => __( 'Export', 'advanced-import-export' ),
					'typeMediaSync'              => __( 'Media Sync', 'advanced-import-export' ),
					'statusPending'              => __( 'Pending', 'advanced-import-export' ),
					'statusProcessing'           => __( 'Processing', 'advanced-import-export' ),
					'statusCompleted'            => __( 'Completed', 'advanced-import-export' ),
					'statusFailed'               => __( 'Failed', 'advanced-import-export' ),
					'statusPaused'               => __( 'Paused', 'advanced-import-export' ),
					'statusCancelled'            => __( 'Cancelled', 'advanced-import-export' ),

					// Content Sync
					'failedLoadSites'            => __( 'Failed to load sites', 'advanced-import-export' ),
					'confirmDeleteSiteConnection' => __( 'Are you sure you want to delete this site connection?', 'advanced-import-export' ),
					'failedDeleteSite'           => __( 'Failed to delete site', 'advanced-import-export' ),
					'connectionTestFailed'       => __( 'Connection test failed', 'advanced-import-export' ),
					'confirmRegenerateSiteKey'   => __( 'Are you sure you want to regenerate this site\'s API key?\n\nThis will break the connection with the remote site until you update the key there.', 'advanced-import-export' ),
					'newApiKey'                  => __( 'New API Key: ', 'advanced-import-export' ),
					'failedRegenerateKey'        => __( 'Failed to regenerate key', 'advanced-import-export' ),
					'apiKeyCopied'               => __( 'API key copied to clipboard', 'advanced-import-export' ),
					'confirmRegenerateMyKey'     => __( 'Are you sure you want to regenerate your API key?\n\nThis will invalidate the current key and all remote sites will need to update their connection settings with the new key.', 'advanced-import-export' ),
					// translators: %s = content placeholder.
					'failedRegenerateApiKey'     => __( 'Failed to regenerate API key', 'advanced-import-export' ),

					// Media Sync
					'noFilesFoundCriteria'       => __( 'No files found matching the criteria', 'advanced-import-export' ),
					// translators: %d is a dynamic value.
					'foundFilesReadyToSync'      => __( 'Found %d files ready to sync', 'advanced-import-export' ),
					'noFilesFoundTitle'          => __( 'No Files Found', 'advanced-import-export' ),
					'noFilesFoundDesc'           => __( 'No files matching your criteria were found in the selected folder.', 'advanced-import-export' ),
					// translators: %s = content placeholder.
					'suggestions'                => __( 'Suggestions', 'advanced-import-export' ),
					'checkFolderPath'            => __( 'Check if the folder path is correct', 'advanced-import-export' ),
					'enableScanRecursive'        => __( 'Try enabling "Scan Recursive" to search in subfolders', 'advanced-import-export' ),
					'changeFileTypeFilter'       => __( 'Change the file type filter', 'advanced-import-export' ),
					'makeSureFolderContains'     => __( 'Make sure the folder contains supported media files', 'advanced-import-export' ),
					'scanComplete'               => __( 'Scan Complete', 'advanced-import-export' ),
					// translators: %1$s is a dynamic value, %2$s is a dynamic value.
					'foundFilesReadySync'        => __( 'Found %1$s files ready for synchronization (Total: %2$s)', 'advanced-import-export' ),
					'fileTypes'                  => __( 'File Types', 'advanced-import-export' ),
					'filesProcessedBatches'      => __( 'All files will be processed in batches. Click "Start Sync" below to begin.', 'advanced-import-export' ),
					'enterFolderPath'            => __( 'Please enter a folder path', 'advanced-import-export' ),
					'requestFailed'              => __( 'Request failed', 'advanced-import-export' ),
					'noFilesToSync'              => __( 'No files to sync. Please scan a folder first.', 'advanced-import-export' ),
					'invalidFolderPath'          => __( 'Invalid folder path', 'advanced-import-export' ),
					'syncStarted'                => __( 'Synchronization started', 'advanced-import-export' ),
					'syncPaused'                 => __( 'Sync paused', 'advanced-import-export' ),
					// translators: %s = content placeholder.
					'syncResumed'                => __( 'Sync resumed', 'advanced-import-export' ),
					'confirmCancelSync'          => __( 'Are you sure you want to cancel the synchronization?\n\nThis will stop the process and you\'ll need to start over.', 'advanced-import-export' ),
					'syncCancelled'              => __( 'Sync cancelled', 'advanced-import-export' ),			// Post Sync
			'selectAtLeastOnePost'       => __( 'Please select at least one post', 'advanced-import-export' ),
			'selectSite'                 => __( 'Please select a site', 'advanced-import-export' ),
			// translators: %s = content placeholder.
			'noPostsSelected'            => __( 'No posts selected', 'advanced-import-export' ),
			'pushTo'                     => __( 'push to', 'advanced-import-export' ),
			'pullFrom'                   => __( 'pull from', 'advanced-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value, %3$s is a dynamic value.
			'confirmSyncAction'          => __( 'Are you sure you want to %1$s %2$s?\n\nThis will affect %3$s post(s).', 'advanced-import-export' ),
			'preparingToPush'            => __( 'Preparing to push content...', 'advanced-import-export' ),
			'preparingToPull'            => __( 'Preparing to pull content...', 'advanced-import-export' ),
			// translators: %s = content placeholder.
			'uploadingContent'           => __( 'Uploading content...', 'advanced-import-export' ),
			// translators: %s = content placeholder.
			'downloadingContent'         => __( 'Downloading content...', 'advanced-import-export' ),
			'syncCompletedSuccessfully'  => __( 'Sync completed successfully', 'advanced-import-export' ),
			// translators: 1: number of created posts, 2: number of updated posts.
			'createdPosts'               => __( '✓ Created %1$d post(s), Updated %2$d post(s)', 'advanced-import-export' ),
			// translators: %d is a dynamic value.
			'syncedImages'               => __( '✓ Synced %d image(s)', 'advanced-import-export' ),
			'syncFailed'                 => __( 'Sync failed', 'advanced-import-export' ),
			'errorOccurredDuringSync'    => __( 'An error occurred during sync', 'advanced-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'postsProgress'              => __( 'Posts: %1$s/%2$s', 'advanced-import-export' ),
			// translators: %d is a dynamic value.
			'imagesSyncedProgress'       => __( 'Images synced: %d', 'advanced-import-export' ),

			// Time formats
			// translators: %d is a dynamic value.
			'timeFormatSeconds'          => __( '%ds', 'advanced-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'timeFormatMinutesSeconds'   => __( '%1$sm %2$ss', 'advanced-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'timeFormatHoursMinutes'     => __( '%1$sh %2$sm', 'advanced-import-export' ),

			// File validation
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'fileSizeExceeds'            => __( 'File size (%1$s) exceeds maximum allowed size (%2$s)', 'advanced-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'fileTypeNotAllowed'         => __( 'File type .%1$s is not allowed. Allowed types: %2$s', 'advanced-import-export' ),

			// Export
					'exportStartedSuccess'       => __( 'Export started successfully', 'advanced-import-export' ),
					'exportCompletedSuccess'     => __( 'Export completed successfully!', 'advanced-import-export' ),
					'confirmCancelExport'        => __( 'Are you sure you want to cancel this export?', 'advanced-import-export' ),
					'exportCancelled'            => __( 'Export cancelled', 'advanced-import-export' ),

					// AI URL Importer
					'testing'                    => __( 'Testing...', 'advanced-import-export' ),
					'testConnection'             => __( 'Test Connection', 'advanced-import-export' ),
					'generatingPreview'          => __( 'Generating Preview...', 'advanced-import-export' ),
					'generatePreview'            => __( 'Generate Preview', 'advanced-import-export' ),
					'failedLoadAcfFields'        => __( 'Failed to load ACF fields. Please try again.', 'advanced-import-export' ),
					'noAcfFields'                => __( 'No ACF fields found for this post type.', 'advanced-import-export' ),
					'noImagesFound'              => __( 'No images found', 'advanced-import-export' ),
					'noFeaturedImage'            => __( 'No featured image selected', 'advanced-import-export' ),
				'confirmCancelImport'        => __( 'Are you sure you want to cancel this import?', 'advanced-import-export' ),
				'failedCancelImport'         => __( 'Failed to cancel the import. Please try again.', 'advanced-import-export' ),
				'error'                      => __( 'Error', 'advanced-import-export' ),
				'rateLimitReached'           => __( 'Rate Limit Reached', 'advanced-import-export' ),
				// translators: %s is a dynamic value.
				'importCompleted'            => __( 'Import completed! %s URLs imported successfully.', 'advanced-import-export' ),
				// translators: %s is a dynamic value.
				'importFailed'               => __( 'Import failed: %s', 'advanced-import-export' ),					// Import
					'showingFirstRows'           => __( 'Showing first 5 rows', 'advanced-import-export' ),
					'pleaseSelectTable'          => __( 'Please select a database table above to see available columns', 'advanced-import-export' ),
					'selectTable'                => __( 'Select a table...', 'advanced-import-export' ),
					'noTablesFound'              => __( 'No tables found', 'advanced-import-export' ),
					'errorLoadingTables'         => __( 'Error loading tables', 'advanced-import-export' ),
				'loading'                    => __( 'Loading...', 'advanced-import-export' ),
				'errorLoadingColumns'        => __( 'Error loading columns', 'advanced-import-export' ),
				'loadingTableColumns'        => __( 'Loading table columns...', 'advanced-import-export' ),
				'pleaseEnterFieldName'       => __( 'Please enter a field name', 'advanced-import-export' ),
				'failedTestPipeline'         => __( 'Failed to test pipeline', 'advanced-import-export' ),
				'confirmCancelImportStep'    => __( 'Are you sure you want to cancel this import?', 'advanced-import-export' ),

				// Export (additional strings)
				// translators: %s = content placeholder.
				'exportComplete'             => __( 'Export Complete!', 'advanced-import-export' ),
				'selectPostType'             => __( 'Select Post Type', 'advanced-import-export' ),
				'selectPostTypePlaceholder'  => __( 'Select Post Type...', 'advanced-import-export' ),
				'selectTaxonomy'             => __( 'Select Taxonomy', 'advanced-import-export' ),
				'selectTaxonomyPlaceholder'  => __( 'Select Taxonomy...', 'advanced-import-export' ),
				'selectTablePlaceholder'     => __( 'Select Table...', 'advanced-import-export' ),
				'selectField'                => __( 'Select Field...', 'advanced-import-export' ),
				'value'                      => __( 'Value', 'advanced-import-export' ),
				'errorLoadingPostTypes'      => __( 'Error loading post types', 'advanced-import-export' ),
				'errorLoadingTaxonomies'     => __( 'Error loading taxonomies', 'advanced-import-export' ),
				
				// Export Step 3
				'assignFunctionsTitle'       => __( 'Assign functions', 'advanced-import-export' ),
				'remove'                     => __( 'Remove', 'advanced-import-export' ),
				'functions'                  => __( 'function(s)', 'advanced-import-export' ),
				'enterColumnName'            => __( 'Enter column name:', 'advanced-import-export' ),
				'noFieldsSelected'           => __( 'No Fields Selected', 'advanced-import-export' ),
				'pleaseSelectFieldMessage'   => __( 'Please select at least one field to continue with the export.', 'advanced-import-export' ),
				'addAll'                     => __( 'Add all', 'advanced-import-export' ),
				'addAllFieldsTitle'          => __( 'Add all fields from this category', 'advanced-import-export' ),
				'loadingAcfFields'           => __( 'Loading ACF fields...', 'advanced-import-export' ),
				'loadingYoastFields'         => __( 'Loading Yoast SEO fields...', 'advanced-import-export' ),
				'noFunctionsAvailableYet'    => __( 'No functions available yet.', 'advanced-import-export' ),
				'createFirstFunction'        => __( 'Create your first custom function to get started.', 'advanced-import-export' ),
				// translators: %s is a dynamic value.
				'noFunctionsFound'           => __( 'No %s functions found.', 'advanced-import-export' ),
				// translators: %s is a dynamic value.
				'errorLabel'                 => __( 'Error: %s', 'advanced-import-export' ),

				// Export (UI strings used in export.js)
				'noDataAvailable'            => __( 'No Data Available', 'advanced-import-export' ),
				'adjustFiltersMessage'       => __( 'Adjust your filters or select a different content type to continue with the export.', 'advanced-import-export' ),
				'postTypeRequired'           => __( 'Post Type Required', 'advanced-import-export' ),
				'pleaseSelectPostType'       => __( 'Please select a specific post type from the dropdown to continue.', 'advanced-import-export' ),
				'taxonomyRequired'           => __( 'Taxonomy Required', 'advanced-import-export' ),
				'pleaseSelectTaxonomy'       => __( 'Please select a specific taxonomy from the dropdown to continue.', 'advanced-import-export' ),
				'tableRequired'              => __( 'Table Required', 'advanced-import-export' ),
				'pleaseSelectTable'          => __( 'Please select a database table from the dropdown to continue.', 'advanced-import-export' ),
				'enterNumberPlaceholder'     => __( 'Enter number...', 'advanced-import-export' ),
				'enterFilterValue'           => __( 'Enter value...', 'advanced-import-export' ),
				'enterCustomFieldName'       => __( 'Enter custom field name...', 'advanced-import-export' ),
				'taxonomyPlaceholderExamples'=> __( 'e.g., category, post_tag, product_cat...', 'advanced-import-export' ),
				'enterTermSlugs'             => __( 'Enter term slugs (comma-separated)...', 'advanced-import-export' ),
				'inFilter'                   => __( 'In', 'advanced-import-export' ),
				'notInFilter'                => __( 'Not In', 'advanced-import-export' ),
				'inComma'                    => __( 'In (comma-separated)', 'advanced-import-export' ),
				'notInComma'                 => __( 'Not In (comma-separated)', 'advanced-import-export' ),
				'hasTermsIn'                 => __( 'Has Term(s) - IN', 'advanced-import-export' ),
				'doesNotHaveTermsNotIn'      => __( 'Does Not Have Term(s) - NOT IN', 'advanced-import-export' ),
				'hasAllTermsAnd'             => __( 'Has All Terms - AND', 'advanced-import-export' ),
				'pleaseSelectFieldToExport'  => __( 'Please select at least one field to export', 'advanced-import-export' ),
				'pleaseUploadFile'           => __( 'Please upload a file', 'advanced-import-export' ),
				'pleaseEnterCustomDelimiter' => __( 'Please enter a custom delimiter', 'advanced-import-export' ),
				'pleaseSelectPostType'       => __( 'Please select a post type', 'advanced-import-export' ),
				'exportFailed'               => __( 'Export failed', 'advanced-import-export' ),
				'unknownError'               => __( 'Unknown error', 'advanced-import-export' ),
				'enterValuesCommaSeparated'  => __( 'Enter values separated by comma (e.g., 1,5,8 or test1,test2)', 'advanced-import-export' ),
				'enterTwoNumbersCommaSeparated' => __( 'Enter two numbers separated by comma (e.g., 10,100)', 'advanced-import-export' ),

				// Function Categories
				'categoryStringOperations'   => __( 'String Operations', 'advanced-import-export' ),
				'categoryDateTime'           => __( 'Date & Time', 'advanced-import-export' ),
				'categoryNumericOperations'  => __( 'Numeric Operations', 'advanced-import-export' ),
				'categoryHtmlOperations'     => __( 'HTML Operations', 'advanced-import-export' ),
				'categoryWordPress'          => __( 'WordPress', 'advanced-import-export' ),
				'categoryValidation'         => __( 'Validation', 'advanced-import-export' ),
				'categoryAdvanced'           => __( 'Advanced', 'advanced-import-export' ),
				'categoryCustom'             => __( 'Custom', 'advanced-import-export' ),

				// Export Field Groups
				'fieldGroupStandard'          => __( 'Standard', 'advanced-import-export' ),
				'fieldGroupBasic'             => __( 'Basic', 'advanced-import-export' ),
				'fieldGroupOther'             => __( 'Other', 'advanced-import-export' ),
				'fieldGroupCustomFilters'     => __( 'Custom Filters', 'advanced-import-export' ),
				'fieldGroupFileInformation'   => __( 'File Information', 'advanced-import-export' ),
				'fieldGroupImageDimensions'   => __( 'Image Dimensions', 'advanced-import-export' ),
				'fieldGroupDates'             => __( 'Dates', 'advanced-import-export' ),
				'fieldGroupAuthor'            => __( 'Author', 'advanced-import-export' ),
				'fieldGroupAttachment'        => __( 'Attachment', 'advanced-import-export' ),
				'fieldGroupDetails'           => __( 'Details', 'advanced-import-export' ),
				'fieldGroupProfile'           => __( 'Profile', 'advanced-import-export' ),
				'fieldGroupRolePermissions'   => __( 'Role & Permissions', 'advanced-import-export' ),
				'fieldGroupPreferences'       => __( 'Preferences', 'advanced-import-export' ),
				'fieldGroupStats'             => __( 'Stats', 'advanced-import-export' ),
				'fieldGroupRelatedPost'       => __( 'Related Post', 'advanced-import-export' ),
				'fieldGroupHierarchy'         => __( 'Hierarchy', 'advanced-import-export' ),
				'fieldGroupBlockThemeComponents' => __( 'Block Theme Components', 'advanced-import-export' ),
				'fieldGroupPostTypeSelection' => __( 'Post Type Selection', 'advanced-import-export' ),
				'fieldGroupTaxonomySelection' => __( 'Taxonomy Selection', 'advanced-import-export' ),
				'fieldGroupTaxonomy'          => __( 'Taxonomy', 'advanced-import-export' ),
				'fieldGroupContent'           => __( 'Content', 'advanced-import-export' ),
				'fieldGroupPricing'           => __( 'Pricing', 'advanced-import-export' ),
				'fieldGroupInventory'         => __( 'Inventory', 'advanced-import-export' ),
				'fieldGroupProductType'       => __( 'Product Type', 'advanced-import-export' ),
				'fieldGroupShipping'          => __( 'Shipping', 'advanced-import-export' ),
				'fieldGroupMedia'             => __( 'Media', 'advanced-import-export' ),
				'fieldGroupFeaturedImage'     => __( 'Featured Image', 'advanced-import-export' ),
				'fieldGroupFile'              => __( 'File', 'advanced-import-export' ),
				'fieldGroupImage'             => __( 'Image', 'advanced-import-export' ),
				'fieldGroupRole'              => __( 'Role', 'advanced-import-export' ),
				'fieldGroupLinkedProducts'    => __( 'Linked Products', 'advanced-import-export' ),
				'fieldGroupAttributes'        => __( 'Attributes', 'advanced-import-export' ),
				'fieldGroupTotals'            => __( 'Totals', 'advanced-import-export' ),
				'fieldGroupStructure'         => __( 'Structure', 'advanced-import-export' ),
				'fieldGroupStatus'            => __( 'Status', 'advanced-import-export' ),
				'fieldGroupPost'              => __( 'Post', 'advanced-import-export' ),
				'fieldGroupCustomFieldsMeta'  => __( 'Custom Fields (Meta)', 'advanced-import-export' ),
				'fieldGroupCustomFieldsUserMeta' => __( 'Custom Fields (User Meta)', 'advanced-import-export' ),
				'fieldGroupCustomFieldsCommentMeta' => __( 'Custom Fields (Comment Meta)', 'advanced-import-export' ),
				'fieldGroupCustomFieldsTermMeta' => __( 'Custom Fields (Term Meta)', 'advanced-import-export' ),
				'fieldGroupCustomFields'      => __( 'Custom Fields', 'advanced-import-export' ),
				'fieldGroupTaxonomies'        => __( 'Taxonomies', 'advanced-import-export' ),
				'fieldGroupMenuItem'          => __( 'Menu Item', 'advanced-import-export' ),
				'fieldGroupAttribute'         => __( 'Attribute', 'advanced-import-export' ),
				'fieldGroupCommentData'       => __( 'Comment Data', 'advanced-import-export' ),
				'fieldGroupTermData'          => __( 'Term Data', 'advanced-import-export' ),
				'fieldGroupReviews'           => __( 'Reviews', 'advanced-import-export' ),
				'fieldGroupVisibility'        => __( 'Visibility', 'advanced-import-export' ),
				'fieldGroupAmounts'           => __( 'Amounts', 'advanced-import-export' ),
				'fieldGroupCustomer'          => __( 'Customer', 'advanced-import-export' ),
				'fieldGroupBillingAddress'    => __( 'Billing Address', 'advanced-import-export' ),
				'fieldGroupShippingAddress'   => __( 'Shipping Address', 'advanced-import-export' ),
				'fieldGroupOrderItems'        => __( 'Order Items', 'advanced-import-export' ),
				'fieldGroupPayment'           => __( 'Payment', 'advanced-import-export' ),
				'fieldGroupNotes'             => __( 'Notes', 'advanced-import-export' ),
				'fieldGroupDiscount'          => __( 'Discount', 'advanced-import-export' ),
				'fieldGroupUsageRestrictions' => __( 'Usage Restrictions', 'advanced-import-export' ),
				'fieldGroupProductRestrictions' => __( 'Product Restrictions', 'advanced-import-export' ),
				'fieldGroupEmailRestrictions' => __( 'Email Restrictions', 'advanced-import-export' ),
				'fieldGroupUsageLimits'       => __( 'Usage Limits', 'advanced-import-export' ),
				'fieldGroupSettings'          => __( 'Settings', 'advanced-import-export' ),
				'fieldGroupTerms'             => __( 'Terms', 'advanced-import-export' ),
				'fieldGroupTableColumns'      => __( 'Table Columns', 'advanced-import-export' ),
				'fieldGroupTableSelection'    => __( 'Table Selection', 'advanced-import-export' ),

				// Export Field Labels (Common)
				'fieldTitle'                  => __( 'Title', 'advanced-import-export' ),
				'fieldContent'                => __( 'Content', 'advanced-import-export' ),
				'fieldExcerpt'                => __( 'Excerpt', 'advanced-import-export' ),
				'fieldDate'                   => __( 'Date', 'advanced-import-export' ),
				'fieldStatus'                 => __( 'Status', 'advanced-import-export' ),
				'fieldCommentStatus'          => __( 'Comment Status', 'advanced-import-export' ),
				'fieldModifiedDate'           => __( 'Modified Date', 'advanced-import-export' ),
				'fieldTemplate'               => __( 'Template', 'advanced-import-export' ),
				'fieldCustomFieldMeta'        => __( '🔧 Custom Field (Meta)', 'advanced-import-export' ),
				'fieldTaxonomyFilter'         => __( '🏷️ Taxonomy Filter', 'advanced-import-export' ),
				'fieldDescription'            => __( 'Description', 'advanced-import-export' ),
				'fieldCaption'                => __( 'Caption', 'advanced-import-export' ),
				'fieldAltText'                => __( 'Alt Text', 'advanced-import-export' ),
				'fieldFileUrlGuid'            => __( 'File URL (GUID)', 'advanced-import-export' ),
				'fieldFileUrl'                => __( 'File URL', 'advanced-import-export' ),
				'fieldFilePathRelative'       => __( 'File Path (Relative)', 'advanced-import-export' ),
				'fieldFileName'               => __( 'File Name', 'advanced-import-export' ),
				'fieldFileExtension'          => __( 'File Extension', 'advanced-import-export' ),
				'fieldMimeType'               => __( 'MIME Type', 'advanced-import-export' ),
				'fieldFileSizeBytes'          => __( 'File Size (bytes)', 'advanced-import-export' ),
				'fieldWidthPx'                => __( 'Width (px)', 'advanced-import-export' ),
				'fieldHeightPx'               => __( 'Height (px)', 'advanced-import-export' ),
				'fieldUploadDate'             => __( 'Upload Date', 'advanced-import-export' ),
				'fieldAuthorId'               => __( 'Author ID', 'advanced-import-export' ),
				'fieldAuthorName'             => __( 'Author Name', 'advanced-import-export' ),
				'fieldAuthorEmail'            => __( 'Author Email', 'advanced-import-export' ),
				'fieldAttachedToPostId'       => __( 'Attached To (Post ID)', 'advanced-import-export' ),
				'fieldAttachedPostTitle'      => __( 'Attached Post Title', 'advanced-import-export' ),
				'fieldMenuName'               => __( 'Menu Name', 'advanced-import-export' ),
				'fieldMenuItemsArray'         => __( 'Menu Items (Array)', 'advanced-import-export' ),
				'fieldItemsCount'             => __( 'Items Count', 'advanced-import-export' ),
				'fieldThemeLocations'         => __( 'Theme Locations', 'advanced-import-export' ),
				'fieldUsername'               => __( 'Username', 'advanced-import-export' ),
				'fieldEmail'                  => __( 'Email', 'advanced-import-export' ),
				'fieldDisplayName'            => __( 'Display Name', 'advanced-import-export' ),
				'fieldNiceName'               => __( 'Nice Name', 'advanced-import-export' ),
				'fieldFirstName'              => __( 'First Name', 'advanced-import-export' ),
				'fieldLastName'               => __( 'Last Name', 'advanced-import-export' ),
				'fieldNickname'               => __( 'Nickname', 'advanced-import-export' ),
				'fieldBio'                    => __( 'Bio', 'advanced-import-export' ),
				'fieldWebsite'                => __( 'Website', 'advanced-import-export' ),
				'fieldAvatarUrl'              => __( 'Avatar URL', 'advanced-import-export' ),
				'fieldRole'                   => __( 'Role', 'advanced-import-export' ),
				'fieldCapabilitiesArray'      => __( 'Capabilities (Array)', 'advanced-import-export' ),
				'fieldLanguage'               => __( 'Language', 'advanced-import-export' ),
				'fieldAdminColorScheme'       => __( 'Admin Color Scheme', 'advanced-import-export' ),
				'fieldVisualEditor'           => __( 'Visual Editor', 'advanced-import-export' ),
				'fieldPostsCount'             => __( 'Posts Count', 'advanced-import-export' ),
				'fieldRegistrationDate'       => __( 'Registration Date', 'advanced-import-export' ),
				'fieldUserStatus'             => __( 'User Status', 'advanced-import-export' ),
				'fieldCommentId'              => __( 'Comment ID', 'advanced-import-export' ),
				'fieldPostId'                 => __( 'Post ID', 'advanced-import-export' ),
				'fieldCommentContent'         => __( 'Comment Content', 'advanced-import-export' ),
				'fieldCommentType'            => __( 'Comment Type', 'advanced-import-export' ),
				'fieldAuthorUrl'              => __( 'Author URL', 'advanced-import-export' ),
				'fieldAuthorIp'               => __( 'Author IP', 'advanced-import-export' ),
				'fieldUserId'                 => __( 'User ID', 'advanced-import-export' ),
				'fieldUserAgent'              => __( 'User Agent', 'advanced-import-export' ),
				'fieldPostTitle'              => __( 'Post Title', 'advanced-import-export' ),
				'fieldPostAuthorId'           => __( 'Post Author ID', 'advanced-import-export' ),
				'fieldCommentDate'            => __( 'Comment Date', 'advanced-import-export' ),
				'fieldCommentDateGmt'         => __( 'Comment Date (GMT)', 'advanced-import-export' ),
				'fieldParentCommentId'        => __( 'Parent Comment ID', 'advanced-import-export' ),
				'fieldKarma'                  => __( 'Karma', 'advanced-import-export' ),
				'fieldGlobalStylesThemeJson'  => __( 'Global Styles (theme.json)', 'advanced-import-export' ),
				'fieldCustomTemplates'        => __( 'Custom Templates', 'advanced-import-export' ),
				'fieldTemplateParts'          => __( 'Template Parts', 'advanced-import-export' ),
				'fieldThemeModifications'     => __( 'Theme Modifications', 'advanced-import-export' ),
				'fieldCustomCss'              => __( 'Custom CSS', 'advanced-import-export' ),
				'fieldPostTypeSelectSpecific' => __( 'Post Type (select specific)', 'advanced-import-export' ),
				'fieldId'                     => __( 'ID', 'advanced-import-export' ),
				'fieldSlug'                   => __( 'Slug', 'advanced-import-export' ),
				'fieldParentId'               => __( 'Parent ID', 'advanced-import-export' ),
				'fieldTermMetaField'          => __( '🔧 Term Meta Field', 'advanced-import-export' ),
				'fieldTaxonomySelectSpecific' => __( 'Taxonomy (select specific)', 'advanced-import-export' ),
				'fieldTermId'                 => __( 'Term ID', 'advanced-import-export' ),
				'fieldTermName'               => __( 'Term Name', 'advanced-import-export' ),
				'fieldTermSlug'               => __( 'Term Slug', 'advanced-import-export' ),
				'fieldTaxonomyType'           => __( 'Taxonomy Type', 'advanced-import-export' ),
				'fieldTaxonomyId'             => __( 'Taxonomy ID', 'advanced-import-export' ),
				'fieldParentTermId'           => __( 'Parent Term ID', 'advanced-import-export' ),
				'fieldProductId'              => __( 'Product ID', 'advanced-import-export' ),
				'fieldProductName'            => __( 'Product Name', 'advanced-import-export' ),
				'fieldSku'                    => __( 'SKU', 'advanced-import-export' ),
				'fieldShortDescription'       => __( 'Short Description', 'advanced-import-export' ),
				'fieldRegularPrice'           => __( 'Regular Price', 'advanced-import-export' ),
				'fieldSalePrice'              => __( 'Sale Price', 'advanced-import-export' ),
				'fieldTaxStatus'              => __( 'Tax Status', 'advanced-import-export' ),
				'fieldTaxClass'               => __( 'Tax Class', 'advanced-import-export' ),
				'fieldStockQuantity'          => __( 'Stock Quantity', 'advanced-import-export' ),
				'fieldStockStatus'            => __( 'Stock Status', 'advanced-import-export' ),
				'fieldManageStock'            => __( 'Manage Stock', 'advanced-import-export' ),
				'fieldBackorders'             => __( 'Backorders', 'advanced-import-export' ),
				'fieldProductType'            => __( 'Product Type', 'advanced-import-export' ),
				'fieldDownloadable'           => __( 'Downloadable', 'advanced-import-export' ),
				'fieldVirtual'                => __( 'Virtual', 'advanced-import-export' ),
				'fieldWeight'                 => __( 'Weight', 'advanced-import-export' ),
				'fieldLength'                 => __( 'Length', 'advanced-import-export' ),
				'fieldWidth'                  => __( 'Width', 'advanced-import-export' ),
				'fieldHeight'                 => __( 'Height', 'advanced-import-export' ),
				'fieldShippingClass'          => __( 'Shipping Class', 'advanced-import-export' ),
				'fieldFeaturedImage'          => __( 'Featured Image', 'advanced-import-export' ),
				'fieldFeaturedImageId'        => __( 'Featured Image ID', 'advanced-import-export' ),
				'fieldFeaturedImageUrl'       => __( 'Featured Image URL', 'advanced-import-export' ),
				'fieldFeaturedImageTitle'     => __( 'Featured Image Title', 'advanced-import-export' ),
				'fieldFeaturedImageCaption'   => __( 'Featured Image Caption', 'advanced-import-export' ),
				'fieldGalleryImages'          => __( 'Gallery Images', 'advanced-import-export' ),
				'fieldCategories'             => __( 'Categories', 'advanced-import-export' ),
				'fieldTags'                   => __( 'Tags', 'advanced-import-export' ),
				'fieldAverageRating'          => __( 'Average Rating', 'advanced-import-export' ),
				'fieldReviewCount'            => __( 'Review Count', 'advanced-import-export' ),
				'fieldReviewsEnabled'         => __( 'Reviews Enabled', 'advanced-import-export' ),
				'fieldFeatured'               => __( 'Featured', 'advanced-import-export' ),
				'fieldCatalogVisibility'      => __( 'Catalog Visibility', 'advanced-import-export' ),
				'fieldTotalSales'             => __( 'Total Sales', 'advanced-import-export' ),
				'fieldCreatedDate'            => __( 'Created Date', 'advanced-import-export' ),
				'fieldOrderId'                => __( 'Order ID', 'advanced-import-export' ),
				'fieldOrderNumber'            => __( 'Order Number', 'advanced-import-export' ),
				'fieldOrderKey'               => __( 'Order Key', 'advanced-import-export' ),
				'fieldCurrency'               => __( 'Currency', 'advanced-import-export' ),
				'fieldOrderTotal'             => __( 'Order Total', 'advanced-import-export' ),
				'fieldSubtotal'               => __( 'Subtotal', 'advanced-import-export' ),
				'fieldTax'                    => __( 'Tax', 'advanced-import-export' ),
				'fieldShipping'               => __( 'Shipping', 'advanced-import-export' ),
				'fieldDiscount'               => __( 'Discount', 'advanced-import-export' ),
				'fieldCustomerId'             => __( 'Customer ID', 'advanced-import-export' ),
				'fieldCustomerNote'           => __( 'Customer Note', 'advanced-import-export' ),
				'fieldCompany'                => __( 'Company', 'advanced-import-export' ),
				'fieldAddress1'               => __( 'Address 1', 'advanced-import-export' ),
				'fieldAddress2'               => __( 'Address 2', 'advanced-import-export' ),
				'fieldCity'                   => __( 'City', 'advanced-import-export' ),
				'fieldState'                  => __( 'State', 'advanced-import-export' ),
				'fieldPostcode'               => __( 'Postcode', 'advanced-import-export' ),
				'fieldCountry'                => __( 'Country', 'advanced-import-export' ),
				'fieldPhone'                  => __( 'Phone', 'advanced-import-export' ),
				'fieldOrderItemsArray'        => __( 'Order Items (Array)', 'advanced-import-export' ),
				'fieldItemCount'              => __( 'Item Count', 'advanced-import-export' ),
				'fieldPaymentMethod'          => __( 'Payment Method', 'advanced-import-export' ),
				'fieldPaymentMethodTitle'     => __( 'Payment Method Title', 'advanced-import-export' ),
				'fieldTransactionId'          => __( 'Transaction ID', 'advanced-import-export' ),
				'fieldShippingMethod'         => __( 'Shipping Method', 'advanced-import-export' ),
				'fieldOrderDate'              => __( 'Order Date', 'advanced-import-export' ),
				'fieldCompletedDate'          => __( 'Completed Date', 'advanced-import-export' ),
				'fieldPaidDate'               => __( 'Paid Date', 'advanced-import-export' ),
				'fieldOrderNotesArray'        => __( 'Order Notes (Array)', 'advanced-import-export' ),
				'fieldCouponId'               => __( 'Coupon ID', 'advanced-import-export' ),
				'fieldCouponCode'             => __( 'Coupon Code', 'advanced-import-export' ),
				'fieldDiscountType'           => __( 'Discount Type', 'advanced-import-export' ),
				'fieldCouponAmount'           => __( 'Coupon Amount', 'advanced-import-export' ),
				'fieldFreeShipping'           => __( 'Free Shipping', 'advanced-import-export' ),
				'fieldMinimumSpend'           => __( 'Minimum Spend', 'advanced-import-export' ),
				// translators: %s = content placeholder.
				'fieldMaximumSpend'           => __( 'Maximum Spend', 'advanced-import-export' ),
				'fieldIndividualUseOnly'      => __( 'Individual Use Only', 'advanced-import-export' ),
				'fieldExcludeSaleItems'       => __( 'Exclude Sale Items', 'advanced-import-export' ),
				'fieldAllowedProducts'        => __( 'Allowed Products', 'advanced-import-export' ),
				'fieldExcludedProducts'       => __( 'Excluded Products', 'advanced-import-export' ),
				'fieldAllowedCategories'      => __( 'Allowed Categories', 'advanced-import-export' ),
				// translators: %s = content placeholder.
				'fieldExcludedCategories'     => __( 'Excluded Categories', 'advanced-import-export' ),
				'fieldAllowedEmails'          => __( 'Allowed Emails', 'advanced-import-export' ),
				'fieldUsageCount'             => __( 'Usage Count', 'advanced-import-export' ),
				'fieldUsageLimitTotal'        => __( 'Usage Limit Total', 'advanced-import-export' ),
				'fieldUsageLimitPerUser'      => __( 'Usage Limit Per User', 'advanced-import-export' ),
				'fieldExpiryDate'             => __( 'Expiry Date', 'advanced-import-export' ),
				'fieldAttributeId'            => __( 'Attribute ID', 'advanced-import-export' ),
				'fieldAttributeName'          => __( 'Attribute Name', 'advanced-import-export' ),
				'fieldAttributeLabel'         => __( 'Attribute Label', 'advanced-import-export' ),
				'fieldAttributeType'          => __( 'Attribute Type', 'advanced-import-export' ),
				'fieldDefaultSortOrder'       => __( 'Default Sort Order', 'advanced-import-export' ),
				'fieldEnableArchives'         => __( 'Enable Archives', 'advanced-import-export' ),
				'fieldTermsCount'             => __( 'Terms Count', 'advanced-import-export' ),
				'fieldAllTermsArray'          => __( 'All Terms (Array)', 'advanced-import-export' ),
				'fieldSelectTableFirst'       => __( '⚠️ Please select a database table first', 'advanced-import-export' ),
				'fieldPleaseSelectTable'      => __( '⚠️ Please select a database table first', 'advanced-import-export' ),

			// Content Updater
			'confirmClearFields'         => __( 'Are you sure you want to clear all selected fields?', 'advanced-import-export' ),
			'confirmClearFunctions'      => __( 'Are you sure you want to clear all function assignments?', 'advanced-import-export' ),
			'confirmCancelUpdate'        => __( 'Are you sure you want to cancel the update?', 'advanced-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'processingItems'            => __( 'Processing items... (%1$s / %2$s)', 'advanced-import-export' ),
			'premiumOnlyFeature'         => __( 'This content type is only available in the Premium version. Upgrade to unlock this feature.', 'advanced-import-export' ),
			'fileValidationFailed'       => __( 'File Validation Failed', 'advanced-import-export' ),
			'goBackUploadValidFile'      => __( 'Go Back and Upload a Valid File', 'advanced-import-export' ),
			'pleaseSelectContentType'    => __( 'Please select a content type', 'advanced-import-export' ),
			'pleaseSelectAtLeastOneField' => __( 'Please select at least one field to update', 'advanced-import-export' ),
			'pleaseAssignFunction'       => __( 'Please assign at least one function to a field', 'advanced-import-export' ),
			// translators: %s = field name.
			'fieldAlreadySelected'       => __( 'Field "%s" is already selected', 'advanced-import-export' ),
			'noFieldsSelected'           => __( 'No fields selected. Please go back and select fields first.', 'advanced-import-export' ),
			'assignFunctions'            => __( 'Assign Functions', 'advanced-import-export' ),
			'noFunctionsAvailable'       => __( 'No functions available. Create a custom function first.', 'advanced-import-export' ),
			'add'                        => __( 'Add', 'advanced-import-export' ),
			'enterTestValue'             => __( 'Enter a test value:', 'advanced-import-export' ),
			'noFunctionAssigned'         => __( 'No function assigned to this field', 'advanced-import-export' ),
			'functionTestFailed'         => __( 'Function test failed', 'advanced-import-export' ),
			'updateStarted'              => __( 'Update started successfully', 'advanced-import-export' ),
			'failedStartUpdate'          => __( 'Failed to start update', 'advanced-import-export' ),
			'noFieldsSelectedError'      => __( 'No fields selected. Please go back and select fields to update.', 'advanced-import-export' ),
			'noFunctionsAssigned'        => __( 'No functions assigned. Please go back and assign functions to fields.', 'advanced-import-export' ),
			'updateCancelled'            => __( 'Update cancelled', 'advanced-import-export' ),
			'functionAssignmentsCleared' => __( 'All function assignments cleared', 'advanced-import-export' ),
			'enterFunctionId'            => __( 'Enter function ID to apply to all fields (or leave empty for none):', 'advanced-import-export' ),
			'functionAppliedToAll'       => __( 'Function applied to all fields', 'advanced-import-export' ),
			'pleaseEnterTestValue'       => __( 'Please enter a test value', 'advanced-import-export' ),
			'noFunctionsToTest'          => __( 'No functions to test', 'advanced-import-export' ),
			'testFailed'                 => __( 'Test failed', 'advanced-import-export' ),
			'configurationError'         => __( 'Configuration error: aieData not found', 'advanced-import-export' ),
			'errorTestingPipeline'       => __( 'Error testing pipeline', 'advanced-import-export' ),
			'input'                      => __( 'Input', 'advanced-import-export' ),
			// Filter conditions
			'equals'                     => __( 'Equals', 'advanced-import-export' ),
			'notEquals'                  => __( 'Not Equals', 'advanced-import-export' ),
			'contains'                   => __( 'Contains', 'advanced-import-export' ),
			'notContains'                => __( 'Not Contains', 'advanced-import-export' ),
			'startsWith'                 => __( 'Starts With', 'advanced-import-export' ),
			'endsWith'                   => __( 'Ends With', 'advanced-import-export' ),
			'isEmpty'                    => __( 'Is Empty', 'advanced-import-export' ),
			'isNotEmpty'                 => __( 'Is Not Empty', 'advanced-import-export' ),
			'greaterThan'                => __( 'Greater Than', 'advanced-import-export' ),
			'lessThan'                   => __( 'Less Than', 'advanced-import-export' ),
			'greaterOrEqual'             => __( 'Greater or Equal', 'advanced-import-export' ),
			'lessOrEqual'                => __( 'Less or Equal', 'advanced-import-export' ),
			'between'                    => __( 'Between', 'advanced-import-export' ),
			'onDate'                     => __( 'On Date', 'advanced-import-export' ),
			'before'                     => __( 'Before', 'advanced-import-export' ),
			'after'                      => __( 'After', 'advanced-import-export' ),				// Content Sync
				'addNewSite'                 => __( 'Add New Site', 'advanced-import-export' ),
				'editSite'                   => __( 'Edit Site', 'advanced-import-export' ),
				'saveConnection'             => __( 'Save Connection', 'advanced-import-export' ),
				'hideDetails'                => __( 'Hide Details', 'advanced-import-export' ),
				'showDetails'                => __( 'Show Details', 'advanced-import-export' ),
				'copied'                     => __( 'Copied!', 'advanced-import-export' ),
				// translators: %s = content placeholder.
				'regenerating'               => __( 'Regenerating...', 'advanced-import-export' ),
				'regenerated'                => __( 'Regenerated!', 'advanced-import-export' ),
				'updating'                   => __( 'Updating...', 'advanced-import-export' ),
				'validatingSaving'           => __( 'Validating & Saving...', 'advanced-import-export' ),
				'validatingApiKey'           => __( 'Validating API key...', 'advanced-import-export' ),
				'pleaseWaitVerifying'        => __( 'Please wait while we verify the connection to the remote site.', 'advanced-import-export' ),
				'operationCompleted'         => __( 'Operation completed successfully', 'advanced-import-export' ),
				'noChanges'                  => __( 'No Changes', 'advanced-import-export' ),
				'success'                    => __( 'Success!', 'advanced-import-export' ),
				'validationFailed'           => __( 'Validation Failed', 'advanced-import-export' ),
				'failedSaveSiteConnection'   => __( 'Failed to save site connection', 'advanced-import-export' ),
				'connectionError'            => __( 'Connection Error', 'advanced-import-export' ),
				'unexpectedError'            => __( 'An unexpected error occurred while trying to save the site connection.', 'advanced-import-export' ),
				'connectionFailed'           => __( 'Connection Failed', 'advanced-import-export' ),
				'possibleReasons'            => __( 'Possible reasons:', 'advanced-import-export' ),
				'urlIncorrect'               => __( '- The URL is incorrect or not accessible', 'advanced-import-export' ),
				'remoteSiteOffline'          => __( '- The remote site is offline', 'advanced-import-export' ),
				'networkFirewall'            => __( '- Network or firewall issues are blocking the connection', 'advanced-import-export' ),
				'invalidApiKey'              => __( 'Invalid API Key', 'advanced-import-export' ),
				'toResolveIssue'             => __( 'To resolve this issue:', 'advanced-import-export' ),
				'goToContentSync'            => __( '- Go to Content Sync page on the remote site', 'advanced-import-export' ),
				'clickShowDetails'           => __( '- Click "Show Details" to reveal the API key', 'advanced-import-export' ),
				// translators: %s = content placeholder.
				'copyEntireKey'              => __( '- Copy the entire key and paste it here', 'advanced-import-export' ),
				'pluginNotFound'             => __( 'Plugin Not Found', 'advanced-import-export' ),
				'duplicateConnection'        => __( 'Duplicate Connection', 'advanced-import-export' ),
				'siteAlreadyConnected'       => __( 'This site URL is already in your connected sites list.', 'advanced-import-export' ),
				'validationError'            => __( 'Validation Error', 'advanced-import-export' ),
				'networkError'               => __( 'Network Error', 'advanced-import-export' ),
				'unableConnectServer'        => __( 'Unable to connect to the server. Please check your internet connection.', 'advanced-import-export' ),
				'serverError'                => __( 'Server Error', 'advanced-import-export' ),
				// translators: %s is a dynamic value.
				'serverReturnedError'        => __( 'The server returned an error (%s). Please try again later.', 'advanced-import-export' ),
				'notFound'                   => __( 'Not Found', 'advanced-import-export' ),
				'endpointNotFound'           => __( 'The requested endpoint was not found. Please check if the plugin is properly installed.', 'advanced-import-export' ),

				// translators: %s = content placeholder.
				// Functions Management
				'serverErrorPhpSyntax'       => __( 'Server error: The function code contains errors that prevent it from being saved. Please check your PHP syntax.', 'advanced-import-export' ),
				'serverErrorUnableToSave'    => __( 'Server error: Unable to save function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.', 'advanced-import-export' ),
				// translators: %s = content placeholder.
				'failedToLoadFunctions'      => __( 'Failed to load functions', 'advanced-import-export' ),
				'failedToLoadFunction'       => __( 'Failed to load function', 'advanced-import-export' ),
				'failedToSaveFunction'       => __( 'Failed to save function', 'advanced-import-export' ),
				'failedToDeleteFunction'     => __( 'Failed to delete function', 'advanced-import-export' ),
				'pleaseEnterFunctionCode'    => __( 'Please enter function code first', 'advanced-import-export' ),
				'serverErrorFunctionErrors'  => __( 'Server error: The function code contains errors. Please check your PHP syntax.', 'advanced-import-export' ),
				// translators: %s = content placeholder.
				'serverErrorUnableToTest'    => __( 'Server error: Unable to test function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.', 'advanced-import-export' ),
				'testFailed'                 => __( 'Test failed', 'advanced-import-export' ),
				'apiKeyNotConfigured'        => __( 'OpenAI API key is not configured. Please configure it in Plugin Options to use AI generation.\n\nDo you want to go to Plugin Options now?', 'advanced-import-export' ),
				'badgeLibrary'               => __( 'Library', 'advanced-import-export' ),
				'badgeCustom'                => __( 'Custom', 'advanced-import-export' ),
				'badgeActive'                => __( 'Active', 'advanced-import-export' ),
				'badgeInactive'              => __( 'Inactive', 'advanced-import-export' ),
				'noDescription'              => __( 'No description', 'advanced-import-export' ),
				'editButton'                 => __( 'Edit', 'advanced-import-export' ),
				'deleteButton'               => __( 'Delete', 'advanced-import-export' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value, %3$s is a dynamic value.
				'showingFunctions'           => __( 'Showing %1$s-%2$s of %3$s functions', 'advanced-import-export' ),
				'customizeFunction'          => __( 'Customize Function', 'advanced-import-export' ),

				// Media Sync
				'scanning'                   => __( 'Scanning...', 'advanced-import-export' ),
				'starting'                   => __( 'Starting...', 'advanced-import-export' ),
				'processing'                 => __( 'Processing...', 'advanced-import-export' ),
				'syncPaused'                 => __( 'Synchronization Paused', 'advanced-import-export' ),
				'paused'                     => __( 'Paused', 'advanced-import-export' ),
				'reresume'                     => __( 'Resume', 'advanced-import-export' ),
				'syncInProgress'             => __( 'Synchronization in Progress', 'advanced-import-export' ),
				'pause'                      => __( 'Pause', 'advanced-import-export' ),
				'startSync'                  => __( 'Start Sync', 'advanced-import-export' ),
				'scanFolder'                 => __( 'Scan Folder', 'advanced-import-export' ),
				// translators: %d is a dynamic value.
				'andMoreErrors'              => __( '... and %d more errors', 'advanced-import-export' ),
				
				// Completion messages
				'syncCompleteTitle'          => __( 'Synchronization Complete!', 'advanced-import-export' ),
				// translators: %s is a dynamic value.
				'successfullyProcessed'      => __( 'Successfully processed %s file', 'advanced-import-export' ),
				// translators: %s is a dynamic value.
				'successfullyProcessedPlural' => __( 'Successfully processed %s files', 'advanced-import-export' ),
				'imported'                   => __( 'Imported', 'advanced-import-export' ),
				'skipped'                    => __( 'Skipped', 'advanced-import-export' ),
				'syncFailedTitle'            => __( 'Synchronization Failed', 'advanced-import-export' ),
				'syncFailedDesc'             => __( 'The synchronization process encountered an error and could not complete.', 'advanced-import-export' ),
				'syncCancelledTitle'         => __( 'Synchronization Cancelled', 'advanced-import-export' ),
				// translators: %s is a dynamic value.
				'processedBeforeCancellation' => __( 'Processed %s file before cancellation.', 'advanced-import-export' ),
				// translators: %s is a dynamic value.
				'processedBeforeCancellationPlural' => __( 'Processed %s files before cancellation.', 'advanced-import-export' ),
				
				// Folder browser
				'goUp'                       => __( 'Go Up', 'advanced-import-export' ),
				'useThisFolder'              => __( '. (Use this folder)', 'advanced-import-export' ),
				
				// Notifications
				'dismissNotice'              => __( 'Dismiss this notice.', 'advanced-import-export' ),

				// Jobs Log
				'noJobsFound'                => __( 'No jobs found.', 'advanced-import-export' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value, %3$s is a dynamic value.
				'showingJobs'                => __( 'Showing %1$s-%2$s of %3$s jobs', 'advanced-import-export' ),
				'retryRequiresPremium'       => __( 'A valid premium license is required to retry this job. Please activate or renew your license.', 'advanced-import-export' ),
			),
		)
	);		// Localize script for Content Sync page
		if ( 'advanced-import-export_page_wp-aie-content-sync' === $admin_page ) {
			wp_localize_script(
				'advanced-import-export-scripts',
				'aieContentSync',
				array(
					'nonce'     => wp_create_nonce( 'aie_nonce' ),
					'isPremium' => function_exists( 'aie_fs' ) && aie_fs()->can_use_premium_code(),
				)
			);
		}

		wp_enqueue_style(
			'advanced-import-export-styles',
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
		__( 'Advanced Import Export', 'advanced-import-export' ),
		__( 'Advanced Import Export', 'advanced-import-export' ),
		'manage_options',
		'advanced-import-export',
		array( $this, 'display_welcome_page' ),
		'dashicons-update-alt',
		99,
	);

	add_submenu_page(
		'advanced-import-export',
		__( 'Welcome', 'advanced-import-export' ),
		__( 'Welcome', 'advanced-import-export' ) . ' 🎉',
		'manage_options',
		'advanced-import-export',
		array( $this, 'display_welcome_page' )
	);

	add_submenu_page(
		'advanced-import-export',
		__( 'Import', 'advanced-import-export' ),
		__( 'Import', 'advanced-import-export' ),
		'manage_options',
		'wp-aie-import',
		array( $this, 'display_settings_import_page' )
	);

	add_submenu_page(
			'advanced-import-export',
			__( 'Export', 'advanced-import-export' ),
			__( 'Export', 'advanced-import-export' ),
			'manage_options',
			'wp-aie-export',
			array( $this, 'display_settings_export_page' )
		);

		add_submenu_page(
			'advanced-import-export',
			__( 'Content Sync', 'advanced-import-export' ),
			__( 'Content Sync', 'advanced-import-export' ),
			'manage_options',
			'wp-aie-content-sync',
			array( $this, 'display_content_sync_page' )
		);

		add_submenu_page(
			'advanced-import-export',
			__( 'Content Updater', 'advanced-import-export' ),
			__( 'Content Updater', 'advanced-import-export' ),
			'manage_options',
			'wp-aie-content-updater',
			array( $this, 'display_content_updater_page' )
		);

		add_submenu_page(
			'advanced-import-export',
			__( 'Media Sync', 'advanced-import-export' ),
			__( 'Media Sync', 'advanced-import-export' ),
			'manage_options',
			'wp-aie-media-sync',
			array( $this, 'display_media_sync_page' )
		);

		add_submenu_page(
			'advanced-import-export',
			__( 'AI URL Importer', 'advanced-import-export' ),
			__( 'AI URL Importer', 'advanced-import-export' ) . ' 🤖',
			'manage_options',
			'wp-aie-ai-url-importer',
			array( $this, 'display_ai_url_importer_page' )
		);

		add_submenu_page(
			'advanced-import-export',
			__( 'Functions', 'advanced-import-export' ),
			__( 'Functions', 'advanced-import-export' ),
			'manage_options',
			'wp-aie-functions',
			array( $this, 'display_settings_functions_page' )
		);

		add_submenu_page(
			'advanced-import-export',
			__( 'Jobs Log', 'advanced-import-export' ),
			__( 'Jobs Log', 'advanced-import-export' ),
			'manage_options',
			'wp-aie-jobs-log',
			array( $this, 'display_jobs_log_page' )
		);

		add_submenu_page(
			'advanced-import-export',
			__( 'Plugin Options', 'advanced-import-export' ),
			__( 'Plugin Options', 'advanced-import-export' ),
			'manage_options',
			'wp-aie-plugin-options',
			array( $this, 'display_plugin_options_page' )
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
	 * Display AI URL Importer Page
	 */
	function display_ai_url_importer_page() {
		WP_AIE()->View->load( 'settings/ai_url_importer' );
	}

	/**
	 * Display Jobs Log Page
	 */
	function display_jobs_log_page() {
		WP_AIE()->View->load( 'settings/jobs-log' );
	}

	/**
	 * Display "Functions" admin page
	 */ // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request().
	function display_settings_functions_page() {
		WP_AIE()->View->load( 'settings/functions' );
	}

	/**
	 * Display Plugin Options Page
	 */
	function display_plugin_options_page() {
		WP_AIE()->View->load( 'settings/plugin_options' );
	}

	/**
	 * Display Welcome Page
	 */
	function display_welcome_page() {
		include WP_AIE_PATH . 'app/View/settings/welcome.php';
	}

	/**
	 * Handle redirect to welcome page after activation
	 */
	function welcome_redirect() {
		// Check if we should redirect
		if ( get_transient( 'aie_activation_redirect' ) ) {
			delete_transient( 'aie_activation_redirect' );
			
			// Don't redirect if activating multiple plugins at once
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking for bulk activation, no nonce needed.
			if ( ! isset( $_GET['activate-multi'] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=advanced-import-export' ) );
				exit;
			}
		}
	}

	/**
	 * Filter wp_get_attachment_url to return the correct URL for attachments
	 * imported with "keep in current directory" mode where the file lives outside
	 * the uploads directory. WordPress would otherwise prepend the uploads base URL
	 * to the stored absolute path, producing double-slashes and a wrong URL.
	 *
	 * @param string $url     Current attachment URL.
	 * @param int    $post_id Attachment post ID.
	 * @return string Correct URL.
	 */
	public function fix_keep_mode_attachment_url( $url, $post_id ) {
		$custom_url = get_post_meta( $post_id, 'aie_file_url', true );
		if ( ! empty( $custom_url ) ) {
			return $custom_url;
		}
		return $url;
	}
}
