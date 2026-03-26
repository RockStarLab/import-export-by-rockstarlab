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
			'toplevel_page_wp-advanced-import-export',
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
			'wp-advanced-import-export-scripts',
			plugins_url( 'assets/js/app.js', WP_AIE_FILE ),
			array( 'jquery', 'jquery-ui-sortable' ),
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
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'aie_nonce' ),
				'pluginUrl'      => plugins_url( '', WP_AIE_FILE ),
				'functionsUrl'   => admin_url( 'admin.php?page=wp-aie-functions' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified via verify_request(). -- Input is sanitized and validated in context.
				'optionsUrl'     => admin_url( 'admin.php?page=wp-aie-plugin-options' ),
				'currentPage'    => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading admin page slug, no nonce needed.
				'hasOpenAIApiKey' => \WP_AIE\Helper\AI_Function_Generator::has_api_key(),
				'isPremium'      => function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code(),
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
					'skip'                       => __( 'Skip', 'wp-advanced-import-export' ),
					'uploading'                  => __( 'Uploading...', 'wp-advanced-import-export' ),
					'processing'                 => __( 'Processing...', 'wp-advanced-import-export' ),
					'completed'                  => __( 'Completed', 'wp-advanced-import-export' ),
					'failed'                     => __( 'Failed', 'wp-advanced-import-export' ),
					'confirmCancel'              => __( 'Are you sure you want to cancel?', 'wp-advanced-import-export' ),
					'errorOccurred'              => __( 'An error occurred', 'wp-advanced-import-export' ),
					'fileTooLarge'               => __( 'File size exceeds maximum allowed', 'wp-advanced-import-export' ),
					'invalidFileType'            => __( 'Invalid file type', 'wp-advanced-import-export' ),
					'invalidFileTypeCsv'         => __( 'Invalid file type. Please upload CSV files only.', 'wp-advanced-import-export' ),
					'fileUploadedSuccessfully'   => __( 'File uploaded successfully', 'wp-advanced-import-export' ),
					'uploadFailed'               => __( 'Upload failed', 'wp-advanced-import-export' ),
					'noFileDataAvailable'        => __( 'No file data available', 'wp-advanced-import-export' ),
					'noPreviewDataAvailable'     => __( 'No preview data available', 'wp-advanced-import-export' ),
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
					'pleaseAddAtLeastOneFunction' => __( 'Please add at least one function to test', 'wp-advanced-import-export' ),
					'createFunctionsInLibrary'   => __( 'Creating custom functions will be available in the Functions Library section', 'wp-advanced-import-export' ),
					'errorLoadingFunctions'      => __( 'Error loading functions', 'wp-advanced-import-export' ),
					'importStartedSuccessfully'  => __( 'Import started successfully', 'wp-advanced-import-export' ),
					'importCompletedSuccessfully' => __( 'Import completed successfully!', 'wp-advanced-import-export' ),
					'importCancelled'            => __( 'Import cancelled', 'wp-advanced-import-export' ),
					'importFailed'               => __( 'Import failed', 'wp-advanced-import-export' ),
					'configErrorAieData'         => __( 'Configuration error: aieData not found', 'wp-advanced-import-export' ),
					'testFailed'                 => __( 'Test failed', 'wp-advanced-import-export' ),
					'errorTestingPipeline'       => __( 'Error testing pipeline', 'wp-advanced-import-export' ),
					
					// Custom Field Modal
					'addTaxonomyField'           => __( 'Add Taxonomy Field', 'wp-advanced-import-export' ),
					'addCustomField'             => __( 'Add Custom Field', 'wp-advanced-import-export' ),
					'enterTaxonomySlug'          => __( 'Enter taxonomy slug (e.g., category, post_tag, product_cat)', 'wp-advanced-import-export' ),
					'enterFieldKey'              => __( 'Enter field key (e.g., _custom_price)', 'wp-advanced-import-export' ),
					'dataFormat'                 => __( 'Data Format', 'wp-advanced-import-export' ),
					'termIdFormat'               => __( 'Term ID (e.g., 5, 12, 23)', 'wp-advanced-import-export' ),
					'termSlugFormat'             => __( 'Term Slug (e.g., technology, news)', 'wp-advanced-import-export' ),
					'termNameFormat'             => __( 'Term Name (e.g., Technology, News)', 'wp-advanced-import-export' ),
					'selectTaxonomyDataFormat'   => __( 'Select the format of taxonomy data in your CSV file.', 'wp-advanced-import-export' ),				'taxonomySlugLabel'          => __( 'Taxonomy Slug', 'wp-advanced-import-export' ),
				'taxonomySlugDescription'    => __( 'The slug of the taxonomy (category, post_tag, or custom taxonomy).', 'wp-advanced-import-export' ),
				'metaKeyLabel'               => __( 'Meta Key', 'wp-advanced-import-export' ),
				'metaKeyDescription'         => __( 'The meta key for the custom field (e.g., _custom_price, my_custom_field).', 'wp-advanced-import-export' ),
				'cancel'                     => __( 'Cancel', 'wp-advanced-import-export' ),
					'addField'                   => __( 'Add Field', 'wp-advanced-import-export' ),
					'addTransformationFunction'  => __( 'Add transformation function', 'wp-advanced-import-export' ),
					'removeMapping'              => __( 'Remove mapping', 'wp-advanced-import-export' ),
					
					// Field Functions Modal
					'fieldTransformationFunctions' => __( 'Field Transformation Functions', 'wp-advanced-import-export' ),
					'field'                      => __( 'Field', 'wp-advanced-import-export' ),
					'type'                       => __( 'Type', 'wp-advanced-import-export' ),
					'appliedFunctions'           => __( 'Applied Functions', 'wp-advanced-import-export' ),
					'noFunctionsApplied'         => __( 'No functions applied yet. Add functions from the list below.', 'wp-advanced-import-export' ),
					'functionsAppliedInOrder'    => __( 'Functions are applied in order from top to bottom. Drag to reorder.', 'wp-advanced-import-export' ),
					'availableFunctions'         => __( 'Available Functions', 'wp-advanced-import-export' ),
					'searchFunctions'            => __( 'Search functions...', 'wp-advanced-import-export' ),
					'all'                        => __( 'All', 'wp-advanced-import-export' ),
					'library'                    => __( 'Library', 'wp-advanced-import-export' ),
					'custom'                     => __( 'Custom', 'wp-advanced-import-export' ),
					'loadingFunctions'           => __( 'Loading functions...', 'wp-advanced-import-export' ),
					'createNewFunction'          => __( 'Create New Function', 'wp-advanced-import-export' ),
					'previewTransformation'      => __( 'Preview Transformation', 'wp-advanced-import-export' ),
					'testValue'                  => __( 'Test Value', 'wp-advanced-import-export' ),
					'enterTestValue'             => __( 'Enter test value...', 'wp-advanced-import-export' ),
					'testPipeline'               => __( 'Test Pipeline', 'wp-advanced-import-export' ),
					'applyFunctions'             => __( 'Apply Functions', 'wp-advanced-import-export' ),
					// translators: %s = content placeholder.
					'initialValue'               => __( 'Initial Value', 'wp-advanced-import-export' ),
					'finalResult'                => __( 'Final Result', 'wp-advanced-import-export' ),
					// translators: %d is a dynamic value.
					'autoMappedFields'           => __( 'Auto-mapped %d fields', 'wp-advanced-import-export' ),
					'dragSourceColumns'          => __( 'Drag source columns to WordPress fields to create mappings', 'wp-advanced-import-export' ),

					// Jobs Log
					'viewDetails'                => __( 'View Details', 'wp-advanced-import-export' ),
					'resume'                     => __( 'Resume', 'wp-advanced-import-export' ),
					'retry'                      => __( 'Retry (Create new job with same parameters)', 'wp-advanced-import-export' ),
					'download'                   => __( 'Download', 'wp-advanced-import-export' ),
					'delete'                     => __( 'Delete', 'wp-advanced-import-export' ),
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
					'jobId'                      => __( 'ID', 'wp-advanced-import-export' ),
					'jobType'                    => __( 'Type', 'wp-advanced-import-export' ),
					'jobDataType'                => __( 'Data Type', 'wp-advanced-import-export' ),
					'jobFileFormat'              => __( 'File Format', 'wp-advanced-import-export' ),
					'jobStatus'                  => __( 'Status', 'wp-advanced-import-export' ),
					'jobProgress'                => __( 'Progress', 'wp-advanced-import-export' ),
					'jobItems'                   => __( 'Items', 'wp-advanced-import-export' ),
					'jobSuccess'                 => __( 'Success', 'wp-advanced-import-export' ),
					'jobCreated'                 => __( 'Created', 'wp-advanced-import-export' ),
					'jobStarted'                 => __( 'Started', 'wp-advanced-import-export' ),
					'jobCompleted'               => __( 'Completed', 'wp-advanced-import-export' ),
					'jobFile'                    => __( 'File', 'wp-advanced-import-export' ),
					'jobFileSize'                => __( 'File Size', 'wp-advanced-import-export' ),
					'jobParameters'              => __( 'Parameters', 'wp-advanced-import-export' ),
					'typeImport'                 => __( 'Import', 'wp-advanced-import-export' ),
					'typeExport'                 => __( 'Export', 'wp-advanced-import-export' ),
					'typeMediaSync'              => __( 'Media Sync', 'wp-advanced-import-export' ),
					'statusPending'              => __( 'Pending', 'wp-advanced-import-export' ),
					'statusProcessing'           => __( 'Processing', 'wp-advanced-import-export' ),
					'statusCompleted'            => __( 'Completed', 'wp-advanced-import-export' ),
					'statusFailed'               => __( 'Failed', 'wp-advanced-import-export' ),
					'statusPaused'               => __( 'Paused', 'wp-advanced-import-export' ),
					'statusCancelled'            => __( 'Cancelled', 'wp-advanced-import-export' ),

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
					// translators: %s = content placeholder.
					'failedRegenerateApiKey'     => __( 'Failed to regenerate API key', 'wp-advanced-import-export' ),

					// Media Sync
					'noFilesFoundCriteria'       => __( 'No files found matching the criteria', 'wp-advanced-import-export' ),
					// translators: %d is a dynamic value.
					'foundFilesReadyToSync'      => __( 'Found %d files ready to sync', 'wp-advanced-import-export' ),
					'noFilesFoundTitle'          => __( 'No Files Found', 'wp-advanced-import-export' ),
					'noFilesFoundDesc'           => __( 'No files matching your criteria were found in the selected folder.', 'wp-advanced-import-export' ),
					// translators: %s = content placeholder.
					'suggestions'                => __( 'Suggestions', 'wp-advanced-import-export' ),
					'checkFolderPath'            => __( 'Check if the folder path is correct', 'wp-advanced-import-export' ),
					'enableScanRecursive'        => __( 'Try enabling "Scan Recursive" to search in subfolders', 'wp-advanced-import-export' ),
					'changeFileTypeFilter'       => __( 'Change the file type filter', 'wp-advanced-import-export' ),
					'makeSureFolderContains'     => __( 'Make sure the folder contains supported media files', 'wp-advanced-import-export' ),
					'scanComplete'               => __( 'Scan Complete', 'wp-advanced-import-export' ),
					// translators: %1$s is a dynamic value, %2$s is a dynamic value.
					'foundFilesReadySync'        => __( 'Found %1$s files ready for synchronization (Total: %2$s)', 'wp-advanced-import-export' ),
					'fileTypes'                  => __( 'File Types', 'wp-advanced-import-export' ),
					'filesProcessedBatches'      => __( 'All files will be processed in batches. Click "Start Sync" below to begin.', 'wp-advanced-import-export' ),
					'enterFolderPath'            => __( 'Please enter a folder path', 'wp-advanced-import-export' ),
					'requestFailed'              => __( 'Request failed', 'wp-advanced-import-export' ),
					'noFilesToSync'              => __( 'No files to sync. Please scan a folder first.', 'wp-advanced-import-export' ),
					'invalidFolderPath'          => __( 'Invalid folder path', 'wp-advanced-import-export' ),
					'syncStarted'                => __( 'Synchronization started', 'wp-advanced-import-export' ),
					'syncPaused'                 => __( 'Sync paused', 'wp-advanced-import-export' ),
					// translators: %s = content placeholder.
					'syncResumed'                => __( 'Sync resumed', 'wp-advanced-import-export' ),
					'confirmCancelSync'          => __( 'Are you sure you want to cancel the synchronization?\n\nThis will stop the process and you\'ll need to start over.', 'wp-advanced-import-export' ),
					'syncCancelled'              => __( 'Sync cancelled', 'wp-advanced-import-export' ),			// Post Sync
			'selectAtLeastOnePost'       => __( 'Please select at least one post', 'wp-advanced-import-export' ),
			'selectSite'                 => __( 'Please select a site', 'wp-advanced-import-export' ),
			// translators: %s = content placeholder.
			'noPostsSelected'            => __( 'No posts selected', 'wp-advanced-import-export' ),
			'pushTo'                     => __( 'push to', 'wp-advanced-import-export' ),
			'pullFrom'                   => __( 'pull from', 'wp-advanced-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value, %3$s is a dynamic value.
			'confirmSyncAction'          => __( 'Are you sure you want to %1$s %2$s?\n\nThis will affect %3$s post(s).', 'wp-advanced-import-export' ),
			'preparingToPush'            => __( 'Preparing to push content...', 'wp-advanced-import-export' ),
			'preparingToPull'            => __( 'Preparing to pull content...', 'wp-advanced-import-export' ),
			// translators: %s = content placeholder.
			'uploadingContent'           => __( 'Uploading content...', 'wp-advanced-import-export' ),
			// translators: %s = content placeholder.
			'downloadingContent'         => __( 'Downloading content...', 'wp-advanced-import-export' ),
			'syncCompletedSuccessfully'  => __( 'Sync completed successfully', 'wp-advanced-import-export' ),
			// translators: 1: number of created posts, 2: number of updated posts.
			'createdPosts'               => __( '✓ Created %1$d post(s), Updated %2$d post(s)', 'wp-advanced-import-export' ),
			// translators: %d is a dynamic value.
			'syncedImages'               => __( '✓ Synced %d image(s)', 'wp-advanced-import-export' ),
			'syncFailed'                 => __( 'Sync failed', 'wp-advanced-import-export' ),
			'errorOccurredDuringSync'    => __( 'An error occurred during sync', 'wp-advanced-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'postsProgress'              => __( 'Posts: %1$s/%2$s', 'wp-advanced-import-export' ),
			// translators: %d is a dynamic value.
			'imagesSyncedProgress'       => __( 'Images synced: %d', 'wp-advanced-import-export' ),

			// Time formats
			// translators: %d is a dynamic value.
			'timeFormatSeconds'          => __( '%ds', 'wp-advanced-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'timeFormatMinutesSeconds'   => __( '%1$sm %2$ss', 'wp-advanced-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'timeFormatHoursMinutes'     => __( '%1$sh %2$sm', 'wp-advanced-import-export' ),

			// File validation
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'fileSizeExceeds'            => __( 'File size (%1$s) exceeds maximum allowed size (%2$s)', 'wp-advanced-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'fileTypeNotAllowed'         => __( 'File type .%1$s is not allowed. Allowed types: %2$s', 'wp-advanced-import-export' ),

			// Export
					'exportStartedSuccess'       => __( 'Export started successfully', 'wp-advanced-import-export' ),
					'exportCompletedSuccess'     => __( 'Export completed successfully!', 'wp-advanced-import-export' ),
					'confirmCancelExport'        => __( 'Are you sure you want to cancel this export?', 'wp-advanced-import-export' ),
					'exportCancelled'            => __( 'Export cancelled', 'wp-advanced-import-export' ),

					// AI URL Importer
					'testing'                    => __( 'Testing...', 'wp-advanced-import-export' ),
					'testConnection'             => __( 'Test Connection', 'wp-advanced-import-export' ),
					'generatingPreview'          => __( 'Generating Preview...', 'wp-advanced-import-export' ),
					'generatePreview'            => __( 'Generate Preview', 'wp-advanced-import-export' ),
					'failedLoadAcfFields'        => __( 'Failed to load ACF fields. Please try again.', 'wp-advanced-import-export' ),
					'noAcfFields'                => __( 'No ACF fields found for this post type.', 'wp-advanced-import-export' ),
					'noImagesFound'              => __( 'No images found', 'wp-advanced-import-export' ),
					'noFeaturedImage'            => __( 'No featured image selected', 'wp-advanced-import-export' ),
				'confirmCancelImport'        => __( 'Are you sure you want to cancel this import?', 'wp-advanced-import-export' ),
				'failedCancelImport'         => __( 'Failed to cancel the import. Please try again.', 'wp-advanced-import-export' ),
				'error'                      => __( 'Error', 'wp-advanced-import-export' ),
				'rateLimitReached'           => __( 'Rate Limit Reached', 'wp-advanced-import-export' ),
				// translators: %s is a dynamic value.
				'importCompleted'            => __( 'Import completed! %s URLs imported successfully.', 'wp-advanced-import-export' ),
				// translators: %s is a dynamic value.
				'importFailed'               => __( 'Import failed: %s', 'wp-advanced-import-export' ),					// Import
					'showingFirstRows'           => __( 'Showing first 5 rows', 'wp-advanced-import-export' ),
					'pleaseSelectTable'          => __( 'Please select a database table above to see available columns', 'wp-advanced-import-export' ),
					'selectTable'                => __( 'Select a table...', 'wp-advanced-import-export' ),
					'noTablesFound'              => __( 'No tables found', 'wp-advanced-import-export' ),
					'errorLoadingTables'         => __( 'Error loading tables', 'wp-advanced-import-export' ),
				'loading'                    => __( 'Loading...', 'wp-advanced-import-export' ),
				'errorLoadingColumns'        => __( 'Error loading columns', 'wp-advanced-import-export' ),
				'loadingTableColumns'        => __( 'Loading table columns...', 'wp-advanced-import-export' ),
				'pleaseEnterFieldName'       => __( 'Please enter a field name', 'wp-advanced-import-export' ),
				'failedTestPipeline'         => __( 'Failed to test pipeline', 'wp-advanced-import-export' ),
				'confirmCancelImportStep'    => __( 'Are you sure you want to cancel this import?', 'wp-advanced-import-export' ),

				// Export (additional strings)
				// translators: %s = content placeholder.
				'exportComplete'             => __( 'Export Complete!', 'wp-advanced-import-export' ),
				'selectPostType'             => __( 'Select Post Type', 'wp-advanced-import-export' ),
				'selectPostTypePlaceholder'  => __( 'Select Post Type...', 'wp-advanced-import-export' ),
				'selectTaxonomy'             => __( 'Select Taxonomy', 'wp-advanced-import-export' ),
				'selectTaxonomyPlaceholder'  => __( 'Select Taxonomy...', 'wp-advanced-import-export' ),
				'selectTablePlaceholder'     => __( 'Select Table...', 'wp-advanced-import-export' ),
				'selectField'                => __( 'Select Field...', 'wp-advanced-import-export' ),
				'value'                      => __( 'Value', 'wp-advanced-import-export' ),
				'errorLoadingPostTypes'      => __( 'Error loading post types', 'wp-advanced-import-export' ),
				'errorLoadingTaxonomies'     => __( 'Error loading taxonomies', 'wp-advanced-import-export' ),
				
				// Export Step 3
				'assignFunctionsTitle'       => __( 'Assign functions', 'wp-advanced-import-export' ),
				'remove'                     => __( 'Remove', 'wp-advanced-import-export' ),
				'functions'                  => __( 'function(s)', 'wp-advanced-import-export' ),
				'enterColumnName'            => __( 'Enter column name:', 'wp-advanced-import-export' ),
				'noFieldsSelected'           => __( 'No Fields Selected', 'wp-advanced-import-export' ),
				'pleaseSelectFieldMessage'   => __( 'Please select at least one field to continue with the export.', 'wp-advanced-import-export' ),
				'addAll'                     => __( 'Add all', 'wp-advanced-import-export' ),
				'addAllFieldsTitle'          => __( 'Add all fields from this category', 'wp-advanced-import-export' ),
				'loadingAcfFields'           => __( 'Loading ACF fields...', 'wp-advanced-import-export' ),
				'loadingYoastFields'         => __( 'Loading Yoast SEO fields...', 'wp-advanced-import-export' ),
				'noFunctionsAvailableYet'    => __( 'No functions available yet.', 'wp-advanced-import-export' ),
				'createFirstFunction'        => __( 'Create your first custom function to get started.', 'wp-advanced-import-export' ),
				// translators: %s is a dynamic value.
				'noFunctionsFound'           => __( 'No %s functions found.', 'wp-advanced-import-export' ),
				// translators: %s is a dynamic value.
				'errorLabel'                 => __( 'Error: %s', 'wp-advanced-import-export' ),

				// Export (UI strings used in export.js)
				'noDataAvailable'            => __( 'No Data Available', 'wp-advanced-import-export' ),
				'adjustFiltersMessage'       => __( 'Adjust your filters or select a different content type to continue with the export.', 'wp-advanced-import-export' ),
				'postTypeRequired'           => __( 'Post Type Required', 'wp-advanced-import-export' ),
				'pleaseSelectPostType'       => __( 'Please select a specific post type from the dropdown to continue.', 'wp-advanced-import-export' ),
				'taxonomyRequired'           => __( 'Taxonomy Required', 'wp-advanced-import-export' ),
				'pleaseSelectTaxonomy'       => __( 'Please select a specific taxonomy from the dropdown to continue.', 'wp-advanced-import-export' ),
				'tableRequired'              => __( 'Table Required', 'wp-advanced-import-export' ),
				'pleaseSelectTable'          => __( 'Please select a database table from the dropdown to continue.', 'wp-advanced-import-export' ),
				'enterNumberPlaceholder'     => __( 'Enter number...', 'wp-advanced-import-export' ),
				'enterFilterValue'           => __( 'Enter value...', 'wp-advanced-import-export' ),
				'enterCustomFieldName'       => __( 'Enter custom field name...', 'wp-advanced-import-export' ),
				'taxonomyPlaceholderExamples'=> __( 'e.g., category, post_tag, product_cat...', 'wp-advanced-import-export' ),
				'enterTermSlugs'             => __( 'Enter term slugs (comma-separated)...', 'wp-advanced-import-export' ),
				'inFilter'                   => __( 'In', 'wp-advanced-import-export' ),
				'notInFilter'                => __( 'Not In', 'wp-advanced-import-export' ),
				'inComma'                    => __( 'In (comma-separated)', 'wp-advanced-import-export' ),
				'notInComma'                 => __( 'Not In (comma-separated)', 'wp-advanced-import-export' ),
				'hasTermsIn'                 => __( 'Has Term(s) - IN', 'wp-advanced-import-export' ),
				'doesNotHaveTermsNotIn'      => __( 'Does Not Have Term(s) - NOT IN', 'wp-advanced-import-export' ),
				'hasAllTermsAnd'             => __( 'Has All Terms - AND', 'wp-advanced-import-export' ),
				'pleaseSelectFieldToExport'  => __( 'Please select at least one field to export', 'wp-advanced-import-export' ),
				'pleaseUploadFile'           => __( 'Please upload a file', 'wp-advanced-import-export' ),
				'pleaseEnterCustomDelimiter' => __( 'Please enter a custom delimiter', 'wp-advanced-import-export' ),
				'pleaseSelectPostType'       => __( 'Please select a post type', 'wp-advanced-import-export' ),
				'exportFailed'               => __( 'Export failed', 'wp-advanced-import-export' ),
				'unknownError'               => __( 'Unknown error', 'wp-advanced-import-export' ),
				'enterValuesCommaSeparated'  => __( 'Enter values separated by comma (e.g., 1,5,8 or test1,test2)', 'wp-advanced-import-export' ),
				'enterTwoNumbersCommaSeparated' => __( 'Enter two numbers separated by comma (e.g., 10,100)', 'wp-advanced-import-export' ),

				// Function Categories
				'categoryStringOperations'   => __( 'String Operations', 'wp-advanced-import-export' ),
				'categoryDateTime'           => __( 'Date & Time', 'wp-advanced-import-export' ),
				'categoryNumericOperations'  => __( 'Numeric Operations', 'wp-advanced-import-export' ),
				'categoryHtmlOperations'     => __( 'HTML Operations', 'wp-advanced-import-export' ),
				'categoryWordPress'          => __( 'WordPress', 'wp-advanced-import-export' ),
				'categoryValidation'         => __( 'Validation', 'wp-advanced-import-export' ),
				'categoryAdvanced'           => __( 'Advanced', 'wp-advanced-import-export' ),
				'categoryCustom'             => __( 'Custom', 'wp-advanced-import-export' ),

				// Export Field Groups
				'fieldGroupStandard'          => __( 'Standard', 'wp-advanced-import-export' ),
				'fieldGroupBasic'             => __( 'Basic', 'wp-advanced-import-export' ),
				'fieldGroupOther'             => __( 'Other', 'wp-advanced-import-export' ),
				'fieldGroupCustomFilters'     => __( 'Custom Filters', 'wp-advanced-import-export' ),
				'fieldGroupFileInformation'   => __( 'File Information', 'wp-advanced-import-export' ),
				'fieldGroupImageDimensions'   => __( 'Image Dimensions', 'wp-advanced-import-export' ),
				'fieldGroupDates'             => __( 'Dates', 'wp-advanced-import-export' ),
				'fieldGroupAuthor'            => __( 'Author', 'wp-advanced-import-export' ),
				'fieldGroupAttachment'        => __( 'Attachment', 'wp-advanced-import-export' ),
				'fieldGroupDetails'           => __( 'Details', 'wp-advanced-import-export' ),
				'fieldGroupProfile'           => __( 'Profile', 'wp-advanced-import-export' ),
				'fieldGroupRolePermissions'   => __( 'Role & Permissions', 'wp-advanced-import-export' ),
				'fieldGroupPreferences'       => __( 'Preferences', 'wp-advanced-import-export' ),
				'fieldGroupStats'             => __( 'Stats', 'wp-advanced-import-export' ),
				'fieldGroupRelatedPost'       => __( 'Related Post', 'wp-advanced-import-export' ),
				'fieldGroupHierarchy'         => __( 'Hierarchy', 'wp-advanced-import-export' ),
				'fieldGroupBlockThemeComponents' => __( 'Block Theme Components', 'wp-advanced-import-export' ),
				'fieldGroupPostTypeSelection' => __( 'Post Type Selection', 'wp-advanced-import-export' ),
				'fieldGroupTaxonomySelection' => __( 'Taxonomy Selection', 'wp-advanced-import-export' ),
				'fieldGroupTaxonomy'          => __( 'Taxonomy', 'wp-advanced-import-export' ),
				'fieldGroupContent'           => __( 'Content', 'wp-advanced-import-export' ),
				'fieldGroupPricing'           => __( 'Pricing', 'wp-advanced-import-export' ),
				'fieldGroupInventory'         => __( 'Inventory', 'wp-advanced-import-export' ),
				'fieldGroupProductType'       => __( 'Product Type', 'wp-advanced-import-export' ),
				'fieldGroupShipping'          => __( 'Shipping', 'wp-advanced-import-export' ),
				'fieldGroupMedia'             => __( 'Media', 'wp-advanced-import-export' ),
				'fieldGroupFeaturedImage'     => __( 'Featured Image', 'wp-advanced-import-export' ),
				'fieldGroupFile'              => __( 'File', 'wp-advanced-import-export' ),
				'fieldGroupImage'             => __( 'Image', 'wp-advanced-import-export' ),
				'fieldGroupRole'              => __( 'Role', 'wp-advanced-import-export' ),
				'fieldGroupLinkedProducts'    => __( 'Linked Products', 'wp-advanced-import-export' ),
				'fieldGroupAttributes'        => __( 'Attributes', 'wp-advanced-import-export' ),
				'fieldGroupTotals'            => __( 'Totals', 'wp-advanced-import-export' ),
				'fieldGroupStructure'         => __( 'Structure', 'wp-advanced-import-export' ),
				'fieldGroupStatus'            => __( 'Status', 'wp-advanced-import-export' ),
				'fieldGroupPost'              => __( 'Post', 'wp-advanced-import-export' ),
				'fieldGroupCustomFieldsMeta'  => __( 'Custom Fields (Meta)', 'wp-advanced-import-export' ),
				'fieldGroupCustomFieldsUserMeta' => __( 'Custom Fields (User Meta)', 'wp-advanced-import-export' ),
				'fieldGroupCustomFieldsCommentMeta' => __( 'Custom Fields (Comment Meta)', 'wp-advanced-import-export' ),
				'fieldGroupCustomFieldsTermMeta' => __( 'Custom Fields (Term Meta)', 'wp-advanced-import-export' ),
				'fieldGroupCustomFields'      => __( 'Custom Fields', 'wp-advanced-import-export' ),
				'fieldGroupTaxonomies'        => __( 'Taxonomies', 'wp-advanced-import-export' ),
				'fieldGroupMenuItem'          => __( 'Menu Item', 'wp-advanced-import-export' ),
				'fieldGroupAttribute'         => __( 'Attribute', 'wp-advanced-import-export' ),
				'fieldGroupCommentData'       => __( 'Comment Data', 'wp-advanced-import-export' ),
				'fieldGroupTermData'          => __( 'Term Data', 'wp-advanced-import-export' ),
				'fieldGroupReviews'           => __( 'Reviews', 'wp-advanced-import-export' ),
				'fieldGroupVisibility'        => __( 'Visibility', 'wp-advanced-import-export' ),
				'fieldGroupAmounts'           => __( 'Amounts', 'wp-advanced-import-export' ),
				'fieldGroupCustomer'          => __( 'Customer', 'wp-advanced-import-export' ),
				'fieldGroupBillingAddress'    => __( 'Billing Address', 'wp-advanced-import-export' ),
				'fieldGroupShippingAddress'   => __( 'Shipping Address', 'wp-advanced-import-export' ),
				'fieldGroupOrderItems'        => __( 'Order Items', 'wp-advanced-import-export' ),
				'fieldGroupPayment'           => __( 'Payment', 'wp-advanced-import-export' ),
				'fieldGroupNotes'             => __( 'Notes', 'wp-advanced-import-export' ),
				'fieldGroupDiscount'          => __( 'Discount', 'wp-advanced-import-export' ),
				'fieldGroupUsageRestrictions' => __( 'Usage Restrictions', 'wp-advanced-import-export' ),
				'fieldGroupProductRestrictions' => __( 'Product Restrictions', 'wp-advanced-import-export' ),
				'fieldGroupEmailRestrictions' => __( 'Email Restrictions', 'wp-advanced-import-export' ),
				'fieldGroupUsageLimits'       => __( 'Usage Limits', 'wp-advanced-import-export' ),
				'fieldGroupSettings'          => __( 'Settings', 'wp-advanced-import-export' ),
				'fieldGroupTerms'             => __( 'Terms', 'wp-advanced-import-export' ),
				'fieldGroupTableColumns'      => __( 'Table Columns', 'wp-advanced-import-export' ),
				'fieldGroupTableSelection'    => __( 'Table Selection', 'wp-advanced-import-export' ),

				// Export Field Labels (Common)
				'fieldTitle'                  => __( 'Title', 'wp-advanced-import-export' ),
				'fieldContent'                => __( 'Content', 'wp-advanced-import-export' ),
				'fieldExcerpt'                => __( 'Excerpt', 'wp-advanced-import-export' ),
				'fieldDate'                   => __( 'Date', 'wp-advanced-import-export' ),
				'fieldStatus'                 => __( 'Status', 'wp-advanced-import-export' ),
				'fieldCommentStatus'          => __( 'Comment Status', 'wp-advanced-import-export' ),
				'fieldModifiedDate'           => __( 'Modified Date', 'wp-advanced-import-export' ),
				'fieldTemplate'               => __( 'Template', 'wp-advanced-import-export' ),
				'fieldCustomFieldMeta'        => __( '🔧 Custom Field (Meta)', 'wp-advanced-import-export' ),
				'fieldTaxonomyFilter'         => __( '🏷️ Taxonomy Filter', 'wp-advanced-import-export' ),
				'fieldDescription'            => __( 'Description', 'wp-advanced-import-export' ),
				'fieldCaption'                => __( 'Caption', 'wp-advanced-import-export' ),
				'fieldAltText'                => __( 'Alt Text', 'wp-advanced-import-export' ),
				'fieldFileUrlGuid'            => __( 'File URL (GUID)', 'wp-advanced-import-export' ),
				'fieldFileUrl'                => __( 'File URL', 'wp-advanced-import-export' ),
				'fieldFilePathRelative'       => __( 'File Path (Relative)', 'wp-advanced-import-export' ),
				'fieldFileName'               => __( 'File Name', 'wp-advanced-import-export' ),
				'fieldFileExtension'          => __( 'File Extension', 'wp-advanced-import-export' ),
				'fieldMimeType'               => __( 'MIME Type', 'wp-advanced-import-export' ),
				'fieldFileSizeBytes'          => __( 'File Size (bytes)', 'wp-advanced-import-export' ),
				'fieldWidthPx'                => __( 'Width (px)', 'wp-advanced-import-export' ),
				'fieldHeightPx'               => __( 'Height (px)', 'wp-advanced-import-export' ),
				'fieldUploadDate'             => __( 'Upload Date', 'wp-advanced-import-export' ),
				'fieldAuthorId'               => __( 'Author ID', 'wp-advanced-import-export' ),
				'fieldAuthorName'             => __( 'Author Name', 'wp-advanced-import-export' ),
				'fieldAuthorEmail'            => __( 'Author Email', 'wp-advanced-import-export' ),
				'fieldAttachedToPostId'       => __( 'Attached To (Post ID)', 'wp-advanced-import-export' ),
				'fieldAttachedPostTitle'      => __( 'Attached Post Title', 'wp-advanced-import-export' ),
				'fieldMenuName'               => __( 'Menu Name', 'wp-advanced-import-export' ),
				'fieldMenuItemsArray'         => __( 'Menu Items (Array)', 'wp-advanced-import-export' ),
				'fieldItemsCount'             => __( 'Items Count', 'wp-advanced-import-export' ),
				'fieldThemeLocations'         => __( 'Theme Locations', 'wp-advanced-import-export' ),
				'fieldUsername'               => __( 'Username', 'wp-advanced-import-export' ),
				'fieldEmail'                  => __( 'Email', 'wp-advanced-import-export' ),
				'fieldDisplayName'            => __( 'Display Name', 'wp-advanced-import-export' ),
				'fieldNiceName'               => __( 'Nice Name', 'wp-advanced-import-export' ),
				'fieldFirstName'              => __( 'First Name', 'wp-advanced-import-export' ),
				'fieldLastName'               => __( 'Last Name', 'wp-advanced-import-export' ),
				'fieldNickname'               => __( 'Nickname', 'wp-advanced-import-export' ),
				'fieldBio'                    => __( 'Bio', 'wp-advanced-import-export' ),
				'fieldWebsite'                => __( 'Website', 'wp-advanced-import-export' ),
				'fieldAvatarUrl'              => __( 'Avatar URL', 'wp-advanced-import-export' ),
				'fieldRole'                   => __( 'Role', 'wp-advanced-import-export' ),
				'fieldCapabilitiesArray'      => __( 'Capabilities (Array)', 'wp-advanced-import-export' ),
				'fieldLanguage'               => __( 'Language', 'wp-advanced-import-export' ),
				'fieldAdminColorScheme'       => __( 'Admin Color Scheme', 'wp-advanced-import-export' ),
				'fieldVisualEditor'           => __( 'Visual Editor', 'wp-advanced-import-export' ),
				'fieldPostsCount'             => __( 'Posts Count', 'wp-advanced-import-export' ),
				'fieldRegistrationDate'       => __( 'Registration Date', 'wp-advanced-import-export' ),
				'fieldUserStatus'             => __( 'User Status', 'wp-advanced-import-export' ),
				'fieldCommentId'              => __( 'Comment ID', 'wp-advanced-import-export' ),
				'fieldPostId'                 => __( 'Post ID', 'wp-advanced-import-export' ),
				'fieldCommentContent'         => __( 'Comment Content', 'wp-advanced-import-export' ),
				'fieldCommentType'            => __( 'Comment Type', 'wp-advanced-import-export' ),
				'fieldAuthorUrl'              => __( 'Author URL', 'wp-advanced-import-export' ),
				'fieldAuthorIp'               => __( 'Author IP', 'wp-advanced-import-export' ),
				'fieldUserId'                 => __( 'User ID', 'wp-advanced-import-export' ),
				'fieldUserAgent'              => __( 'User Agent', 'wp-advanced-import-export' ),
				'fieldPostTitle'              => __( 'Post Title', 'wp-advanced-import-export' ),
				'fieldPostAuthorId'           => __( 'Post Author ID', 'wp-advanced-import-export' ),
				'fieldCommentDate'            => __( 'Comment Date', 'wp-advanced-import-export' ),
				'fieldCommentDateGmt'         => __( 'Comment Date (GMT)', 'wp-advanced-import-export' ),
				'fieldParentCommentId'        => __( 'Parent Comment ID', 'wp-advanced-import-export' ),
				'fieldKarma'                  => __( 'Karma', 'wp-advanced-import-export' ),
				'fieldGlobalStylesThemeJson'  => __( 'Global Styles (theme.json)', 'wp-advanced-import-export' ),
				'fieldCustomTemplates'        => __( 'Custom Templates', 'wp-advanced-import-export' ),
				'fieldTemplateParts'          => __( 'Template Parts', 'wp-advanced-import-export' ),
				'fieldThemeModifications'     => __( 'Theme Modifications', 'wp-advanced-import-export' ),
				'fieldCustomCss'              => __( 'Custom CSS', 'wp-advanced-import-export' ),
				'fieldPostTypeSelectSpecific' => __( 'Post Type (select specific)', 'wp-advanced-import-export' ),
				'fieldId'                     => __( 'ID', 'wp-advanced-import-export' ),
				'fieldSlug'                   => __( 'Slug', 'wp-advanced-import-export' ),
				'fieldParentId'               => __( 'Parent ID', 'wp-advanced-import-export' ),
				'fieldTermMetaField'          => __( '🔧 Term Meta Field', 'wp-advanced-import-export' ),
				'fieldTaxonomySelectSpecific' => __( 'Taxonomy (select specific)', 'wp-advanced-import-export' ),
				'fieldTermId'                 => __( 'Term ID', 'wp-advanced-import-export' ),
				'fieldTermName'               => __( 'Term Name', 'wp-advanced-import-export' ),
				'fieldTermSlug'               => __( 'Term Slug', 'wp-advanced-import-export' ),
				'fieldTaxonomyType'           => __( 'Taxonomy Type', 'wp-advanced-import-export' ),
				'fieldTaxonomyId'             => __( 'Taxonomy ID', 'wp-advanced-import-export' ),
				'fieldParentTermId'           => __( 'Parent Term ID', 'wp-advanced-import-export' ),
				'fieldProductId'              => __( 'Product ID', 'wp-advanced-import-export' ),
				'fieldProductName'            => __( 'Product Name', 'wp-advanced-import-export' ),
				'fieldSku'                    => __( 'SKU', 'wp-advanced-import-export' ),
				'fieldShortDescription'       => __( 'Short Description', 'wp-advanced-import-export' ),
				'fieldRegularPrice'           => __( 'Regular Price', 'wp-advanced-import-export' ),
				'fieldSalePrice'              => __( 'Sale Price', 'wp-advanced-import-export' ),
				'fieldTaxStatus'              => __( 'Tax Status', 'wp-advanced-import-export' ),
				'fieldTaxClass'               => __( 'Tax Class', 'wp-advanced-import-export' ),
				'fieldStockQuantity'          => __( 'Stock Quantity', 'wp-advanced-import-export' ),
				'fieldStockStatus'            => __( 'Stock Status', 'wp-advanced-import-export' ),
				'fieldManageStock'            => __( 'Manage Stock', 'wp-advanced-import-export' ),
				'fieldBackorders'             => __( 'Backorders', 'wp-advanced-import-export' ),
				'fieldProductType'            => __( 'Product Type', 'wp-advanced-import-export' ),
				'fieldDownloadable'           => __( 'Downloadable', 'wp-advanced-import-export' ),
				'fieldVirtual'                => __( 'Virtual', 'wp-advanced-import-export' ),
				'fieldWeight'                 => __( 'Weight', 'wp-advanced-import-export' ),
				'fieldLength'                 => __( 'Length', 'wp-advanced-import-export' ),
				'fieldWidth'                  => __( 'Width', 'wp-advanced-import-export' ),
				'fieldHeight'                 => __( 'Height', 'wp-advanced-import-export' ),
				'fieldShippingClass'          => __( 'Shipping Class', 'wp-advanced-import-export' ),
				'fieldFeaturedImage'          => __( 'Featured Image', 'wp-advanced-import-export' ),
				'fieldFeaturedImageId'        => __( 'Featured Image ID', 'wp-advanced-import-export' ),
				'fieldFeaturedImageUrl'       => __( 'Featured Image URL', 'wp-advanced-import-export' ),
				'fieldFeaturedImageTitle'     => __( 'Featured Image Title', 'wp-advanced-import-export' ),
				'fieldFeaturedImageCaption'   => __( 'Featured Image Caption', 'wp-advanced-import-export' ),
				'fieldGalleryImages'          => __( 'Gallery Images', 'wp-advanced-import-export' ),
				'fieldCategories'             => __( 'Categories', 'wp-advanced-import-export' ),
				'fieldTags'                   => __( 'Tags', 'wp-advanced-import-export' ),
				'fieldAverageRating'          => __( 'Average Rating', 'wp-advanced-import-export' ),
				'fieldReviewCount'            => __( 'Review Count', 'wp-advanced-import-export' ),
				'fieldReviewsEnabled'         => __( 'Reviews Enabled', 'wp-advanced-import-export' ),
				'fieldFeatured'               => __( 'Featured', 'wp-advanced-import-export' ),
				'fieldCatalogVisibility'      => __( 'Catalog Visibility', 'wp-advanced-import-export' ),
				'fieldTotalSales'             => __( 'Total Sales', 'wp-advanced-import-export' ),
				'fieldCreatedDate'            => __( 'Created Date', 'wp-advanced-import-export' ),
				'fieldOrderId'                => __( 'Order ID', 'wp-advanced-import-export' ),
				'fieldOrderNumber'            => __( 'Order Number', 'wp-advanced-import-export' ),
				'fieldOrderKey'               => __( 'Order Key', 'wp-advanced-import-export' ),
				'fieldCurrency'               => __( 'Currency', 'wp-advanced-import-export' ),
				'fieldOrderTotal'             => __( 'Order Total', 'wp-advanced-import-export' ),
				'fieldSubtotal'               => __( 'Subtotal', 'wp-advanced-import-export' ),
				'fieldTax'                    => __( 'Tax', 'wp-advanced-import-export' ),
				'fieldShipping'               => __( 'Shipping', 'wp-advanced-import-export' ),
				'fieldDiscount'               => __( 'Discount', 'wp-advanced-import-export' ),
				'fieldCustomerId'             => __( 'Customer ID', 'wp-advanced-import-export' ),
				'fieldCustomerNote'           => __( 'Customer Note', 'wp-advanced-import-export' ),
				'fieldCompany'                => __( 'Company', 'wp-advanced-import-export' ),
				'fieldAddress1'               => __( 'Address 1', 'wp-advanced-import-export' ),
				'fieldAddress2'               => __( 'Address 2', 'wp-advanced-import-export' ),
				'fieldCity'                   => __( 'City', 'wp-advanced-import-export' ),
				'fieldState'                  => __( 'State', 'wp-advanced-import-export' ),
				'fieldPostcode'               => __( 'Postcode', 'wp-advanced-import-export' ),
				'fieldCountry'                => __( 'Country', 'wp-advanced-import-export' ),
				'fieldPhone'                  => __( 'Phone', 'wp-advanced-import-export' ),
				'fieldOrderItemsArray'        => __( 'Order Items (Array)', 'wp-advanced-import-export' ),
				'fieldItemCount'              => __( 'Item Count', 'wp-advanced-import-export' ),
				'fieldPaymentMethod'          => __( 'Payment Method', 'wp-advanced-import-export' ),
				'fieldPaymentMethodTitle'     => __( 'Payment Method Title', 'wp-advanced-import-export' ),
				'fieldTransactionId'          => __( 'Transaction ID', 'wp-advanced-import-export' ),
				'fieldShippingMethod'         => __( 'Shipping Method', 'wp-advanced-import-export' ),
				'fieldOrderDate'              => __( 'Order Date', 'wp-advanced-import-export' ),
				'fieldCompletedDate'          => __( 'Completed Date', 'wp-advanced-import-export' ),
				'fieldPaidDate'               => __( 'Paid Date', 'wp-advanced-import-export' ),
				'fieldOrderNotesArray'        => __( 'Order Notes (Array)', 'wp-advanced-import-export' ),
				'fieldCouponId'               => __( 'Coupon ID', 'wp-advanced-import-export' ),
				'fieldCouponCode'             => __( 'Coupon Code', 'wp-advanced-import-export' ),
				'fieldDiscountType'           => __( 'Discount Type', 'wp-advanced-import-export' ),
				'fieldCouponAmount'           => __( 'Coupon Amount', 'wp-advanced-import-export' ),
				'fieldFreeShipping'           => __( 'Free Shipping', 'wp-advanced-import-export' ),
				'fieldMinimumSpend'           => __( 'Minimum Spend', 'wp-advanced-import-export' ),
				// translators: %s = content placeholder.
				'fieldMaximumSpend'           => __( 'Maximum Spend', 'wp-advanced-import-export' ),
				'fieldIndividualUseOnly'      => __( 'Individual Use Only', 'wp-advanced-import-export' ),
				'fieldExcludeSaleItems'       => __( 'Exclude Sale Items', 'wp-advanced-import-export' ),
				'fieldAllowedProducts'        => __( 'Allowed Products', 'wp-advanced-import-export' ),
				'fieldExcludedProducts'       => __( 'Excluded Products', 'wp-advanced-import-export' ),
				'fieldAllowedCategories'      => __( 'Allowed Categories', 'wp-advanced-import-export' ),
				// translators: %s = content placeholder.
				'fieldExcludedCategories'     => __( 'Excluded Categories', 'wp-advanced-import-export' ),
				'fieldAllowedEmails'          => __( 'Allowed Emails', 'wp-advanced-import-export' ),
				'fieldUsageCount'             => __( 'Usage Count', 'wp-advanced-import-export' ),
				'fieldUsageLimitTotal'        => __( 'Usage Limit Total', 'wp-advanced-import-export' ),
				'fieldUsageLimitPerUser'      => __( 'Usage Limit Per User', 'wp-advanced-import-export' ),
				'fieldExpiryDate'             => __( 'Expiry Date', 'wp-advanced-import-export' ),
				'fieldAttributeId'            => __( 'Attribute ID', 'wp-advanced-import-export' ),
				'fieldAttributeName'          => __( 'Attribute Name', 'wp-advanced-import-export' ),
				'fieldAttributeLabel'         => __( 'Attribute Label', 'wp-advanced-import-export' ),
				'fieldAttributeType'          => __( 'Attribute Type', 'wp-advanced-import-export' ),
				'fieldDefaultSortOrder'       => __( 'Default Sort Order', 'wp-advanced-import-export' ),
				'fieldEnableArchives'         => __( 'Enable Archives', 'wp-advanced-import-export' ),
				'fieldTermsCount'             => __( 'Terms Count', 'wp-advanced-import-export' ),
				'fieldAllTermsArray'          => __( 'All Terms (Array)', 'wp-advanced-import-export' ),
				'fieldSelectTableFirst'       => __( '⚠️ Please select a database table first', 'wp-advanced-import-export' ),
				'fieldPleaseSelectTable'      => __( '⚠️ Please select a database table first', 'wp-advanced-import-export' ),

			// Content Updater
			'confirmClearFields'         => __( 'Are you sure you want to clear all selected fields?', 'wp-advanced-import-export' ),
			'confirmClearFunctions'      => __( 'Are you sure you want to clear all function assignments?', 'wp-advanced-import-export' ),
			'confirmCancelUpdate'        => __( 'Are you sure you want to cancel the update?', 'wp-advanced-import-export' ),
			// translators: %1$s is a dynamic value, %2$s is a dynamic value.
			'processingItems'            => __( 'Processing items... (%1$s / %2$s)', 'wp-advanced-import-export' ),
			'premiumOnlyFeature'         => __( 'This content type is only available in the Premium version. Upgrade to unlock this feature.', 'wp-advanced-import-export' ),
			'fileValidationFailed'       => __( 'File Validation Failed', 'wp-advanced-import-export' ),
			'goBackUploadValidFile'      => __( 'Go Back and Upload a Valid File', 'wp-advanced-import-export' ),
			'pleaseSelectContentType'    => __( 'Please select a content type', 'wp-advanced-import-export' ),
			'pleaseSelectAtLeastOneField' => __( 'Please select at least one field to update', 'wp-advanced-import-export' ),
			'pleaseAssignFunction'       => __( 'Please assign at least one function to a field', 'wp-advanced-import-export' ),
			// translators: %s = field name.
			'fieldAlreadySelected'       => __( 'Field "%s" is already selected', 'wp-advanced-import-export' ),
			'noFieldsSelected'           => __( 'No fields selected. Please go back and select fields first.', 'wp-advanced-import-export' ),
			'assignFunctions'            => __( 'Assign Functions', 'wp-advanced-import-export' ),
			'noFunctionsAvailable'       => __( 'No functions available. Create a custom function first.', 'wp-advanced-import-export' ),
			'add'                        => __( 'Add', 'wp-advanced-import-export' ),
			'enterTestValue'             => __( 'Enter a test value:', 'wp-advanced-import-export' ),
			'noFunctionAssigned'         => __( 'No function assigned to this field', 'wp-advanced-import-export' ),
			'functionTestFailed'         => __( 'Function test failed', 'wp-advanced-import-export' ),
			'updateStarted'              => __( 'Update started successfully', 'wp-advanced-import-export' ),
			'failedStartUpdate'          => __( 'Failed to start update', 'wp-advanced-import-export' ),
			'noFieldsSelectedError'      => __( 'No fields selected. Please go back and select fields to update.', 'wp-advanced-import-export' ),
			'noFunctionsAssigned'        => __( 'No functions assigned. Please go back and assign functions to fields.', 'wp-advanced-import-export' ),
			'updateCancelled'            => __( 'Update cancelled', 'wp-advanced-import-export' ),
			'functionAssignmentsCleared' => __( 'All function assignments cleared', 'wp-advanced-import-export' ),
			'enterFunctionId'            => __( 'Enter function ID to apply to all fields (or leave empty for none):', 'wp-advanced-import-export' ),
			'functionAppliedToAll'       => __( 'Function applied to all fields', 'wp-advanced-import-export' ),
			'pleaseEnterTestValue'       => __( 'Please enter a test value', 'wp-advanced-import-export' ),
			'noFunctionsToTest'          => __( 'No functions to test', 'wp-advanced-import-export' ),
			'testFailed'                 => __( 'Test failed', 'wp-advanced-import-export' ),
			'configurationError'         => __( 'Configuration error: aieData not found', 'wp-advanced-import-export' ),
			'errorTestingPipeline'       => __( 'Error testing pipeline', 'wp-advanced-import-export' ),
			'input'                      => __( 'Input', 'wp-advanced-import-export' ),
			// Filter conditions
			'equals'                     => __( 'Equals', 'wp-advanced-import-export' ),
			'notEquals'                  => __( 'Not Equals', 'wp-advanced-import-export' ),
			'contains'                   => __( 'Contains', 'wp-advanced-import-export' ),
			'notContains'                => __( 'Not Contains', 'wp-advanced-import-export' ),
			'startsWith'                 => __( 'Starts With', 'wp-advanced-import-export' ),
			'endsWith'                   => __( 'Ends With', 'wp-advanced-import-export' ),
			'isEmpty'                    => __( 'Is Empty', 'wp-advanced-import-export' ),
			'isNotEmpty'                 => __( 'Is Not Empty', 'wp-advanced-import-export' ),
			'greaterThan'                => __( 'Greater Than', 'wp-advanced-import-export' ),
			'lessThan'                   => __( 'Less Than', 'wp-advanced-import-export' ),
			'greaterOrEqual'             => __( 'Greater or Equal', 'wp-advanced-import-export' ),
			'lessOrEqual'                => __( 'Less or Equal', 'wp-advanced-import-export' ),
			'between'                    => __( 'Between', 'wp-advanced-import-export' ),
			'onDate'                     => __( 'On Date', 'wp-advanced-import-export' ),
			'before'                     => __( 'Before', 'wp-advanced-import-export' ),
			'after'                      => __( 'After', 'wp-advanced-import-export' ),				// Content Sync
				'addNewSite'                 => __( 'Add New Site', 'wp-advanced-import-export' ),
				'editSite'                   => __( 'Edit Site', 'wp-advanced-import-export' ),
				'saveConnection'             => __( 'Save Connection', 'wp-advanced-import-export' ),
				'hideDetails'                => __( 'Hide Details', 'wp-advanced-import-export' ),
				'showDetails'                => __( 'Show Details', 'wp-advanced-import-export' ),
				'copied'                     => __( 'Copied!', 'wp-advanced-import-export' ),
				// translators: %s = content placeholder.
				'regenerating'               => __( 'Regenerating...', 'wp-advanced-import-export' ),
				'regenerated'                => __( 'Regenerated!', 'wp-advanced-import-export' ),
				'updating'                   => __( 'Updating...', 'wp-advanced-import-export' ),
				'validatingSaving'           => __( 'Validating & Saving...', 'wp-advanced-import-export' ),
				'validatingApiKey'           => __( 'Validating API key...', 'wp-advanced-import-export' ),
				'pleaseWaitVerifying'        => __( 'Please wait while we verify the connection to the remote site.', 'wp-advanced-import-export' ),
				'operationCompleted'         => __( 'Operation completed successfully', 'wp-advanced-import-export' ),
				'noChanges'                  => __( 'No Changes', 'wp-advanced-import-export' ),
				'success'                    => __( 'Success!', 'wp-advanced-import-export' ),
				'validationFailed'           => __( 'Validation Failed', 'wp-advanced-import-export' ),
				'failedSaveSiteConnection'   => __( 'Failed to save site connection', 'wp-advanced-import-export' ),
				'connectionError'            => __( 'Connection Error', 'wp-advanced-import-export' ),
				'unexpectedError'            => __( 'An unexpected error occurred while trying to save the site connection.', 'wp-advanced-import-export' ),
				'connectionFailed'           => __( 'Connection Failed', 'wp-advanced-import-export' ),
				'possibleReasons'            => __( 'Possible reasons:', 'wp-advanced-import-export' ),
				'urlIncorrect'               => __( '- The URL is incorrect or not accessible', 'wp-advanced-import-export' ),
				'remoteSiteOffline'          => __( '- The remote site is offline', 'wp-advanced-import-export' ),
				'networkFirewall'            => __( '- Network or firewall issues are blocking the connection', 'wp-advanced-import-export' ),
				'invalidApiKey'              => __( 'Invalid API Key', 'wp-advanced-import-export' ),
				'toResolveIssue'             => __( 'To resolve this issue:', 'wp-advanced-import-export' ),
				'goToContentSync'            => __( '- Go to Content Sync page on the remote site', 'wp-advanced-import-export' ),
				'clickShowDetails'           => __( '- Click "Show Details" to reveal the API key', 'wp-advanced-import-export' ),
				// translators: %s = content placeholder.
				'copyEntireKey'              => __( '- Copy the entire key and paste it here', 'wp-advanced-import-export' ),
				'pluginNotFound'             => __( 'Plugin Not Found', 'wp-advanced-import-export' ),
				'duplicateConnection'        => __( 'Duplicate Connection', 'wp-advanced-import-export' ),
				'siteAlreadyConnected'       => __( 'This site URL is already in your connected sites list.', 'wp-advanced-import-export' ),
				'validationError'            => __( 'Validation Error', 'wp-advanced-import-export' ),
				'networkError'               => __( 'Network Error', 'wp-advanced-import-export' ),
				'unableConnectServer'        => __( 'Unable to connect to the server. Please check your internet connection.', 'wp-advanced-import-export' ),
				'serverError'                => __( 'Server Error', 'wp-advanced-import-export' ),
				// translators: %s is a dynamic value.
				'serverReturnedError'        => __( 'The server returned an error (%s). Please try again later.', 'wp-advanced-import-export' ),
				'notFound'                   => __( 'Not Found', 'wp-advanced-import-export' ),
				'endpointNotFound'           => __( 'The requested endpoint was not found. Please check if the plugin is properly installed.', 'wp-advanced-import-export' ),

				// translators: %s = content placeholder.
				// Functions Management
				'serverErrorPhpSyntax'       => __( 'Server error: The function code contains errors that prevent it from being saved. Please check your PHP syntax.', 'wp-advanced-import-export' ),
				'serverErrorUnableToSave'    => __( 'Server error: Unable to save function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.', 'wp-advanced-import-export' ),
				// translators: %s = content placeholder.
				'failedToLoadFunctions'      => __( 'Failed to load functions', 'wp-advanced-import-export' ),
				'failedToLoadFunction'       => __( 'Failed to load function', 'wp-advanced-import-export' ),
				'failedToSaveFunction'       => __( 'Failed to save function', 'wp-advanced-import-export' ),
				'failedToDeleteFunction'     => __( 'Failed to delete function', 'wp-advanced-import-export' ),
				'pleaseEnterFunctionCode'    => __( 'Please enter function code first', 'wp-advanced-import-export' ),
				'serverErrorFunctionErrors'  => __( 'Server error: The function code contains errors. Please check your PHP syntax.', 'wp-advanced-import-export' ),
				// translators: %s = content placeholder.
				'serverErrorUnableToTest'    => __( 'Server error: Unable to test function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.', 'wp-advanced-import-export' ),
				'testFailed'                 => __( 'Test failed', 'wp-advanced-import-export' ),
				'apiKeyNotConfigured'        => __( 'OpenAI API key is not configured. Please configure it in Plugin Options to use AI generation.\n\nDo you want to go to Plugin Options now?', 'wp-advanced-import-export' ),
				'badgeLibrary'               => __( 'Library', 'wp-advanced-import-export' ),
				'badgeCustom'                => __( 'Custom', 'wp-advanced-import-export' ),
				'badgeActive'                => __( 'Active', 'wp-advanced-import-export' ),
				'badgeInactive'              => __( 'Inactive', 'wp-advanced-import-export' ),
				'noDescription'              => __( 'No description', 'wp-advanced-import-export' ),
				'editButton'                 => __( 'Edit', 'wp-advanced-import-export' ),
				'deleteButton'               => __( 'Delete', 'wp-advanced-import-export' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value, %3$s is a dynamic value.
				'showingFunctions'           => __( 'Showing %1$s-%2$s of %3$s functions', 'wp-advanced-import-export' ),
				'customizeFunction'          => __( 'Customize Function', 'wp-advanced-import-export' ),

				// Media Sync
				'scanning'                   => __( 'Scanning...', 'wp-advanced-import-export' ),
				'starting'                   => __( 'Starting...', 'wp-advanced-import-export' ),
				'processing'                 => __( 'Processing...', 'wp-advanced-import-export' ),
				'syncPaused'                 => __( 'Synchronization Paused', 'wp-advanced-import-export' ),
				'paused'                     => __( 'Paused', 'wp-advanced-import-export' ),
				'reresume'                     => __( 'Resume', 'wp-advanced-import-export' ),
				'syncInProgress'             => __( 'Synchronization in Progress', 'wp-advanced-import-export' ),
				'pause'                      => __( 'Pause', 'wp-advanced-import-export' ),
				'startSync'                  => __( 'Start Sync', 'wp-advanced-import-export' ),
				'scanFolder'                 => __( 'Scan Folder', 'wp-advanced-import-export' ),
				// translators: %d is a dynamic value.
				'andMoreErrors'              => __( '... and %d more errors', 'wp-advanced-import-export' ),
				
				// Completion messages
				'syncCompleteTitle'          => __( 'Synchronization Complete!', 'wp-advanced-import-export' ),
				// translators: %s is a dynamic value.
				'successfullyProcessed'      => __( 'Successfully processed %s file', 'wp-advanced-import-export' ),
				// translators: %s is a dynamic value.
				'successfullyProcessedPlural' => __( 'Successfully processed %s files', 'wp-advanced-import-export' ),
				'imported'                   => __( 'Imported', 'wp-advanced-import-export' ),
				'skipped'                    => __( 'Skipped', 'wp-advanced-import-export' ),
				'syncFailedTitle'            => __( 'Synchronization Failed', 'wp-advanced-import-export' ),
				'syncFailedDesc'             => __( 'The synchronization process encountered an error and could not complete.', 'wp-advanced-import-export' ),
				'syncCancelledTitle'         => __( 'Synchronization Cancelled', 'wp-advanced-import-export' ),
				// translators: %s is a dynamic value.
				'processedBeforeCancellation' => __( 'Processed %s file before cancellation.', 'wp-advanced-import-export' ),
				// translators: %s is a dynamic value.
				'processedBeforeCancellationPlural' => __( 'Processed %s files before cancellation.', 'wp-advanced-import-export' ),
				
				// Folder browser
				'goUp'                       => __( 'Go Up', 'wp-advanced-import-export' ),
				'useThisFolder'              => __( '. (Use this folder)', 'wp-advanced-import-export' ),
				
				// Notifications
				'dismissNotice'              => __( 'Dismiss this notice.', 'wp-advanced-import-export' ),

				// Jobs Log
				'noJobsFound'                => __( 'No jobs found.', 'wp-advanced-import-export' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value, %3$s is a dynamic value.
				'showingJobs'                => __( 'Showing %1$s-%2$s of %3$s jobs', 'wp-advanced-import-export' ),
				'retryRequiresPremium'       => __( 'A valid premium license is required to retry this job. Please activate or renew your license.', 'wp-advanced-import-export' ),
			),
		)
	);		// Localize script for Content Sync page
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
		array( $this, 'display_welcome_page' ),
		'dashicons-update-alt',
		99,
	);

	add_submenu_page(
		'wp-advanced-import-export',
		__( 'Welcome', 'wp-advanced-import-export' ),
		__( 'Welcome', 'wp-advanced-import-export' ) . ' 🎉',
		'manage_options',
		'wp-advanced-import-export',
		array( $this, 'display_welcome_page' )
	);

	add_submenu_page(
		'wp-advanced-import-export',
		__( 'Import', 'wp-advanced-import-export' ),
		__( 'Import', 'wp-advanced-import-export' ),
		'manage_options',
		'wp-aie-import',
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
			__( 'AI URL Importer', 'wp-advanced-import-export' ),
			__( 'AI URL Importer', 'wp-advanced-import-export' ) . ' 🤖',
			'manage_options',
			'wp-aie-ai-url-importer',
			array( $this, 'display_ai_url_importer_page' )
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

		add_submenu_page(
			'wp-advanced-import-export',
			__( 'Plugin Options', 'wp-advanced-import-export' ),
			__( 'Plugin Options', 'wp-advanced-import-export' ),
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
				wp_safe_redirect( admin_url( 'admin.php?page=wp-advanced-import-export' ) );
				exit;
			}
		}
	}
}
