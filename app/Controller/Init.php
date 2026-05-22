<?php
/**
 * Init Controller Class
 *
 * Handles plugin initialization, hooks, and admin pages.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

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
	 * @var \RockStarLab\ImportExport\Model\Queue\Cron_Manager
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
		\RockStarLab\ImportExport\Helper\Review_Notice::init();

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
		$this->cron_manager = new \RockStarLab\ImportExport\Model\Queue\Cron_Manager();
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
				'toplevel_page_import-export-by-rockstarlab',
				'import-export-by-rockstarlab_page_rsl-ie-import',
				'import-export-by-rockstarlab_page_rsl-ie-export',
				'import-export-by-rockstarlab_page_rsl-ie-content-sync',
				'import-export-by-rockstarlab_page_rsl-ie-content-updater',
				'import-export-by-rockstarlab_page_rsl-ie-jobs-log',
				'import-export-by-rockstarlab_page_rsl-ie-media-sync',
				'import-export-by-rockstarlab_page_rsl-ie-ai-url-importer',
				'import-export-by-rockstarlab_page_rsl-ie-functions',
				'import-export-by-rockstarlab_page_rsl-ie-plugin-options',
				'import-export-by-rockstarlab_page_import-export-by-rockstarlab-addons',
			)
		) ) {
			return;
		}

		// Enqueue CodeMirror for code editor
		if ( 'import-export-by-rockstarlab_page_rsl-ie-functions' === $admin_page ) {
			wp_enqueue_code_editor( array( 'type' => 'application/x-httpd-php' ) );
		}

		wp_enqueue_script(
			'import-export-by-rockstarlab-scripts',
			plugins_url( 'assets/js/app.js', RSL_IE_FILE ),
			array( 'jquery', 'jquery-ui-sortable' ),
			filemtime( plugin_dir_path( RSL_IE_FILE ) . 'assets/js/app.js' ),
			array(
				'in_footer' => true,
			)
		);

		// Localize script with AJAX data
		wp_localize_script(
			'import-export-by-rockstarlab-scripts',
			'rslIeData',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'rsl_ie_nonce' ),
				'pluginUrl'        => plugins_url( '', RSL_IE_FILE ),
				'functionsUrl'     => admin_url( 'admin.php?page=rsl-ie-functions' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Nonce verified via verify_request(). -- Input is sanitized and validated in context.
				'optionsUrl'       => admin_url( 'admin.php?page=rsl-ie-plugin-options' ),
				'currentPage'      => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading admin page slug, no nonce needed.
				'hasOpenAIApiKey'  => \RockStarLab\ImportExport\Helper\AI_Function_Generator::has_api_key(),
				'isPremium'        => \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active(),
				'isProAddonActive' => \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active(),
				'isProEnabled'     => \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_enabled(),
				'premiumDataTypes' => array(
					'custom_post_types',
					'custom_post_type',
					'media',
					'menu',
					'menus',
					'nav_menu',
					'user',
					'users',
					'comment',
					'comments',
					'taxonomy',
					'taxonomy_term',
					'taxonomy_terms',
					'term',
					'terms',
					'category',
					'categories',
					'tag',
					'tags',
					'woo_product',
					'product',
					'products',
					'woo_order',
					'woo_orders',
					'woo_coupon',
					'woo_attribute',
					'database_table',
				),
				'i18n'             => array(
					// General
					'skip'                          => __( 'Skip', 'import-export-by-rockstarlab' ),
					'uploading'                     => __( 'Uploading...', 'import-export-by-rockstarlab' ),
					'processing'                    => __( 'Processing...', 'import-export-by-rockstarlab' ),
					'completed'                     => __( 'Completed', 'import-export-by-rockstarlab' ),
					'failed'                        => __( 'Failed', 'import-export-by-rockstarlab' ),
					'confirmCancel'                 => __( 'Are you sure you want to cancel?', 'import-export-by-rockstarlab' ),
					'errorOccurred'                 => __( 'An error occurred', 'import-export-by-rockstarlab' ),
					'saved'                         => __( 'Settings saved successfully', 'import-export-by-rockstarlab' ),
					'testingConnection'             => __( 'Testing connection...', 'import-export-by-rockstarlab' ),
					'apiKeyConfiguredTitle'         => __( 'API key is configured.', 'import-export-by-rockstarlab' ),
					'apiKeyConfiguredDesc'          => __( 'Try generating a function to test the actual connection.', 'import-export-by-rockstarlab' ),
					'noApiKeyTitle'                 => __( 'No API key configured.', 'import-export-by-rockstarlab' ),
					'noApiKeyDesc'                  => __( 'Please enter your OpenAI API key and save settings.', 'import-export-by-rockstarlab' ),
					'fileTooLarge'                  => __( 'File size exceeds maximum allowed', 'import-export-by-rockstarlab' ),
					'invalidFileType'               => __( 'Invalid file type', 'import-export-by-rockstarlab' ),
					'invalidFileTypeCsv'            => __( 'Invalid file type. Please upload CSV files only.', 'import-export-by-rockstarlab' ),
					'fileUploadedSuccessfully'      => __( 'File uploaded successfully', 'import-export-by-rockstarlab' ),
					'uploadFailed'                  => __( 'Upload failed', 'import-export-by-rockstarlab' ),
					'noFileDataAvailable'           => __( 'No file data available', 'import-export-by-rockstarlab' ),
					'noPreviewDataAvailable'        => __( 'No preview data available', 'import-export-by-rockstarlab' ),
					'selectFile'                    => __( 'Please select a file', 'import-export-by-rockstarlab' ),
					'selectFields'                  => __( 'Please select at least one field', 'import-export-by-rockstarlab' ),
					'mapFields'                     => __( 'Please map at least one field', 'import-export-by-rockstarlab' ),
					'name_required'                 => __( 'Please enter a function name.', 'import-export-by-rockstarlab' ),
					'code_required'                 => __( 'Please enter the PHP code for your function.', 'import-export-by-rockstarlab' ),
					'category_required'             => __( 'Please select a category.', 'import-export-by-rockstarlab' ),

					// Export Step 3
					'fieldAlreadyAdded'             => __( 'This field is already added', 'import-export-by-rockstarlab' ),
					'confirmRemoveAllFields'        => __( 'Are you sure you want to remove all fields?', 'import-export-by-rockstarlab' ),
					'functionsSavedSuccess'         => __( 'Functions saved successfully', 'import-export-by-rockstarlab' ),
					'enterTestValue'                => __( 'Please enter a test value', 'import-export-by-rockstarlab' ),
					'noFunctionsToTest'             => __( 'No functions to test', 'import-export-by-rockstarlab' ),
					'pleaseAddAtLeastOneFunction'   => __( 'Please add at least one function to test', 'import-export-by-rockstarlab' ),
					'createFunctionsInLibrary'      => __( 'Creating custom functions will be available in the Functions Library section', 'import-export-by-rockstarlab' ),
					'errorLoadingFunctions'         => __( 'Error loading functions', 'import-export-by-rockstarlab' ),
					'importStartedSuccessfully'     => __( 'Import started successfully', 'import-export-by-rockstarlab' ),
					'importCompletedSuccessfully'   => __( 'Import completed successfully!', 'import-export-by-rockstarlab' ),
					'importCancelled'               => __( 'Import cancelled', 'import-export-by-rockstarlab' ),
					'importFailed'                  => __( 'Import failed', 'import-export-by-rockstarlab' ),
					'configErrorRslIeData'          => __( 'Configuration error: rslIeData not found', 'import-export-by-rockstarlab' ),
					'testFailed'                    => __( 'Test failed', 'import-export-by-rockstarlab' ),
					'errorTestingPipeline'          => __( 'Error testing pipeline', 'import-export-by-rockstarlab' ),

					// Custom Field Modal
					'addTaxonomyField'              => __( 'Add Taxonomy Field', 'import-export-by-rockstarlab' ),
					'addCustomField'                => __( 'Add Custom Field', 'import-export-by-rockstarlab' ),
					'enterTaxonomySlug'             => __( 'Enter taxonomy slug (e.g., category, post_tag, product_cat)', 'import-export-by-rockstarlab' ),
					'enterFieldKey'                 => __( 'Enter field key (e.g., _custom_price)', 'import-export-by-rockstarlab' ),
					'dataFormat'                    => __( 'Data Format', 'import-export-by-rockstarlab' ),
					'termIdFormat'                  => __( 'Term ID (e.g., 5, 12, 23)', 'import-export-by-rockstarlab' ),
					'termSlugFormat'                => __( 'Term Slug (e.g., technology, news)', 'import-export-by-rockstarlab' ),
					'termNameFormat'                => __( 'Term Name (e.g., Technology, News)', 'import-export-by-rockstarlab' ),
					'selectTaxonomyDataFormat'      => __( 'Select the format of taxonomy data in your CSV file.', 'import-export-by-rockstarlab' ),
				'taxonomySlugLabel'                 => __( 'Taxonomy Slug', 'import-export-by-rockstarlab' ),
				'taxonomySlugDescription'           => __( 'The slug of the taxonomy (category, post_tag, or custom taxonomy).', 'import-export-by-rockstarlab' ),
				'metaKeyLabel'                      => __( 'Meta Key', 'import-export-by-rockstarlab' ),
				'metaKeyDescription'                => __( 'The meta key for the custom field (e.g., _custom_price, my_custom_field).', 'import-export-by-rockstarlab' ),
				'cancel'                            => __( 'Cancel', 'import-export-by-rockstarlab' ),
					'addField'                      => __( 'Add Field', 'import-export-by-rockstarlab' ),
					'addTransformationFunction'     => __( 'Add transformation function', 'import-export-by-rockstarlab' ),
					'removeMapping'                 => __( 'Remove mapping', 'import-export-by-rockstarlab' ),

					// Field Functions Modal
					'fieldTransformationFunctions'  => __( 'Field Transformation Functions', 'import-export-by-rockstarlab' ),
					'field'                         => __( 'Field', 'import-export-by-rockstarlab' ),
					'type'                          => __( 'Type', 'import-export-by-rockstarlab' ),
					'appliedFunctions'              => __( 'Applied Functions', 'import-export-by-rockstarlab' ),
					'noFunctionsApplied'            => __( 'No functions applied yet. Add functions from the list below.', 'import-export-by-rockstarlab' ),
					'functionsAppliedInOrder'       => __( 'Functions are applied in order from top to bottom. Drag to reorder.', 'import-export-by-rockstarlab' ),
					'availableFunctions'            => __( 'Available Functions', 'import-export-by-rockstarlab' ),
					'searchFunctions'               => __( 'Search functions...', 'import-export-by-rockstarlab' ),
					'all'                           => __( 'All', 'import-export-by-rockstarlab' ),
					'library'                       => __( 'Library', 'import-export-by-rockstarlab' ),
					'custom'                        => __( 'Custom', 'import-export-by-rockstarlab' ),
					'loadingFunctions'              => __( 'Loading functions...', 'import-export-by-rockstarlab' ),
					'createNewFunction'             => __( 'Create New Function', 'import-export-by-rockstarlab' ),
					'previewTransformation'         => __( 'Preview Transformation', 'import-export-by-rockstarlab' ),
					'testValue'                     => __( 'Test Value', 'import-export-by-rockstarlab' ),
					'enterTestValue'                => __( 'Enter test value...', 'import-export-by-rockstarlab' ),
					'testPipeline'                  => __( 'Test Pipeline', 'import-export-by-rockstarlab' ),
					'applyFunctions'                => __( 'Apply Functions', 'import-export-by-rockstarlab' ),
					// translators: %s = content placeholder.
					'initialValue'                  => __( 'Initial Value', 'import-export-by-rockstarlab' ),
					'finalResult'                   => __( 'Final Result', 'import-export-by-rockstarlab' ),
					// translators: %d is a dynamic value.
					'autoMappedFields'              => __( 'Auto-mapped %d fields', 'import-export-by-rockstarlab' ),
					'dragSourceColumns'             => __( 'Drag source columns to WordPress fields to create mappings', 'import-export-by-rockstarlab' ),

					// Jobs Log
					'viewDetails'                   => __( 'View Details', 'import-export-by-rockstarlab' ),
					'resume'                        => __( 'Resume', 'import-export-by-rockstarlab' ),
					'retry'                         => __( 'Retry (Create new job with same parameters)', 'import-export-by-rockstarlab' ),
					'download'                      => __( 'Download', 'import-export-by-rockstarlab' ),
					'delete'                        => __( 'Delete', 'import-export-by-rockstarlab' ),
					'errorLoadingJobs'              => __( 'Error loading jobs: ', 'import-export-by-rockstarlab' ),
					'confirmResumeJob'              => __( 'Resume this job?', 'import-export-by-rockstarlab' ),
					'jobResumedSuccess'             => __( 'Job resumed successfully', 'import-export-by-rockstarlab' ),
					'errorResumingJob'              => __( 'Error resuming job: ', 'import-export-by-rockstarlab' ),
					'confirmRestartJob'             => __( 'Restart this job with the same settings?', 'import-export-by-rockstarlab' ),
					'jobRestartedSuccess'           => __( 'Job restarted successfully', 'import-export-by-rockstarlab' ),
					'errorRestartingJob'            => __( 'Error restarting job: ', 'import-export-by-rockstarlab' ),
					'confirmRetryJob'               => __( 'Retry this job with the same settings?', 'import-export-by-rockstarlab' ),
					'jobCreatedStarting'            => __( 'Job created, starting process...', 'import-export-by-rockstarlab' ),
					'errorRetryingJob'              => __( 'Error retrying job: ', 'import-export-by-rockstarlab' ),
					'jobDeletedSuccess'             => __( 'Job deleted successfully', 'import-export-by-rockstarlab' ),
					'errorDeletingJob'              => __( 'Error deleting job: ', 'import-export-by-rockstarlab' ),
					'downloadFailed'                => __( 'Download failed', 'import-export-by-rockstarlab' ),
					'failedGenerateDownloadUrl'     => __( 'Failed to generate download URL', 'import-export-by-rockstarlab' ),
					'errorLoadingJobDetails'        => __( 'Error loading job details: ', 'import-export-by-rockstarlab' ),
					'jobId'                         => __( 'ID', 'import-export-by-rockstarlab' ),
					'jobType'                       => __( 'Type', 'import-export-by-rockstarlab' ),
					'jobDataType'                   => __( 'Data Type', 'import-export-by-rockstarlab' ),
					'jobFileFormat'                 => __( 'File Format', 'import-export-by-rockstarlab' ),
					'jobStatus'                     => __( 'Status', 'import-export-by-rockstarlab' ),
					'jobProgress'                   => __( 'Progress', 'import-export-by-rockstarlab' ),
					'jobItems'                      => __( 'Items', 'import-export-by-rockstarlab' ),
					'jobSuccess'                    => __( 'Success', 'import-export-by-rockstarlab' ),
					'jobCreated'                    => __( 'Created', 'import-export-by-rockstarlab' ),
					'jobStarted'                    => __( 'Started', 'import-export-by-rockstarlab' ),
					'jobCompleted'                  => __( 'Completed', 'import-export-by-rockstarlab' ),
					'jobFile'                       => __( 'File', 'import-export-by-rockstarlab' ),
					'jobFileSize'                   => __( 'File Size', 'import-export-by-rockstarlab' ),
					'jobParameters'                 => __( 'Parameters', 'import-export-by-rockstarlab' ),
					'typeImport'                    => __( 'Import', 'import-export-by-rockstarlab' ),
					'typeExport'                    => __( 'Export', 'import-export-by-rockstarlab' ),
					'typeMediaSync'                 => __( 'Media Sync', 'import-export-by-rockstarlab' ),
					'statusPending'                 => __( 'Pending', 'import-export-by-rockstarlab' ),
					'statusProcessing'              => __( 'Processing', 'import-export-by-rockstarlab' ),
					'statusCompleted'               => __( 'Completed', 'import-export-by-rockstarlab' ),
					'statusFailed'                  => __( 'Failed', 'import-export-by-rockstarlab' ),
					'statusPaused'                  => __( 'Paused', 'import-export-by-rockstarlab' ),
					'statusCancelled'               => __( 'Cancelled', 'import-export-by-rockstarlab' ),

					// Content Sync
					'failedLoadSites'               => __( 'Failed to load sites', 'import-export-by-rockstarlab' ),
					'confirmDeleteSiteConnection'   => __( 'Are you sure you want to delete this site connection?', 'import-export-by-rockstarlab' ),
					'failedDeleteSite'              => __( 'Failed to delete site', 'import-export-by-rockstarlab' ),
					'connectionTestFailed'          => __( 'Connection test failed', 'import-export-by-rockstarlab' ),
					'confirmRegenerateSiteKey'      => __( 'Are you sure you want to regenerate this site\'s API key?\n\nThis will break the connection with the remote site until you update the key there.', 'import-export-by-rockstarlab' ),
					'newApiKey'                     => __( 'New API Key: ', 'import-export-by-rockstarlab' ),
					'failedRegenerateKey'           => __( 'Failed to regenerate key', 'import-export-by-rockstarlab' ),
					'apiKeyCopied'                  => __( 'API key copied to clipboard', 'import-export-by-rockstarlab' ),
					'confirmRegenerateMyKey'        => __( 'Are you sure you want to regenerate your API key?\n\nThis will invalidate the current key and all remote sites will need to update their connection settings with the new key.', 'import-export-by-rockstarlab' ),
					// translators: %s = content placeholder.
					'failedRegenerateApiKey'        => __( 'Failed to regenerate API key', 'import-export-by-rockstarlab' ),

					// Media Sync
					'noFilesFoundCriteria'          => __( 'No files found matching the criteria', 'import-export-by-rockstarlab' ),
					// translators: %d is a dynamic value.
					'foundFilesReadyToSync'         => __( 'Found %d files ready to sync', 'import-export-by-rockstarlab' ),
					'noFilesFoundTitle'             => __( 'No Files Found', 'import-export-by-rockstarlab' ),
					'noFilesFoundDesc'              => __( 'No files matching your criteria were found in the selected folder.', 'import-export-by-rockstarlab' ),
					// translators: %s = content placeholder.
					'suggestions'                   => __( 'Suggestions', 'import-export-by-rockstarlab' ),
					'checkFolderPath'               => __( 'Check if the folder path is correct', 'import-export-by-rockstarlab' ),
					'enableScanRecursive'           => __( 'Try enabling "Scan Recursive" to search in subfolders', 'import-export-by-rockstarlab' ),
					'changeFileTypeFilter'          => __( 'Change the file type filter', 'import-export-by-rockstarlab' ),
					'makeSureFolderContains'        => __( 'Make sure the folder contains supported media files', 'import-export-by-rockstarlab' ),
					'scanComplete'                  => __( 'Scan Complete', 'import-export-by-rockstarlab' ),
					// translators: %1$s is a dynamic value, %2$s is a dynamic value.
					'foundFilesReadySync'           => __( 'Found %1$s files ready for synchronization (Total: %2$s)', 'import-export-by-rockstarlab' ),
					'fileTypes'                     => __( 'File Types', 'import-export-by-rockstarlab' ),
					'filesProcessedBatches'         => __( 'All files will be processed in batches. Click "Start Sync" below to begin.', 'import-export-by-rockstarlab' ),
					'enterFolderPath'               => __( 'Please enter a folder path', 'import-export-by-rockstarlab' ),
					'requestFailed'                 => __( 'Request failed', 'import-export-by-rockstarlab' ),
					'noFilesToSync'                 => __( 'No files to sync. Please scan a folder first.', 'import-export-by-rockstarlab' ),
					'invalidFolderPath'             => __( 'Invalid folder path', 'import-export-by-rockstarlab' ),
					'syncStarted'                   => __( 'Synchronization started', 'import-export-by-rockstarlab' ),
					'syncPaused'                    => __( 'Sync paused', 'import-export-by-rockstarlab' ),
					// translators: %s = content placeholder.
					'syncResumed'                   => __( 'Sync resumed', 'import-export-by-rockstarlab' ),
					'confirmCancelSync'             => __( 'Are you sure you want to cancel the synchronization?\n\nThis will stop the process and you\'ll need to start over.', 'import-export-by-rockstarlab' ),
					'syncCancelled'                 => __( 'Sync cancelled', 'import-export-by-rockstarlab' ),         // Post Sync
				'selectAtLeastOnePost'              => __( 'Please select at least one post', 'import-export-by-rockstarlab' ),
				'selectSite'                        => __( 'Please select a site', 'import-export-by-rockstarlab' ),
				// translators: %s = content placeholder.
				'noPostsSelected'                   => __( 'No posts selected', 'import-export-by-rockstarlab' ),
				'pushTo'                            => __( 'push to', 'import-export-by-rockstarlab' ),
				'pullFrom'                          => __( 'pull from', 'import-export-by-rockstarlab' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value, %3$s is a dynamic value.
				'confirmSyncAction'                 => __( 'Are you sure you want to %1$s %2$s?\n\nThis will affect %3$s post(s).', 'import-export-by-rockstarlab' ),
				'preparingToPush'                   => __( 'Preparing to push content...', 'import-export-by-rockstarlab' ),
				'preparingToPull'                   => __( 'Preparing to pull content...', 'import-export-by-rockstarlab' ),
				// translators: %s = content placeholder.
				'uploadingContent'                  => __( 'Uploading content...', 'import-export-by-rockstarlab' ),
				// translators: %s = content placeholder.
				'downloadingContent'                => __( 'Downloading content...', 'import-export-by-rockstarlab' ),
				'syncCompletedSuccessfully'         => __( 'Sync completed successfully', 'import-export-by-rockstarlab' ),
				// translators: 1: number of created posts, 2: number of updated posts.
				'createdPosts'                      => __( '✓ Created %1$d post(s), Updated %2$d post(s)', 'import-export-by-rockstarlab' ),
				// translators: %d is a dynamic value.
				'syncedImages'                      => __( '✓ Synced %d image(s)', 'import-export-by-rockstarlab' ),
				'syncFailed'                        => __( 'Sync failed', 'import-export-by-rockstarlab' ),
				'errorOccurredDuringSync'           => __( 'An error occurred during sync', 'import-export-by-rockstarlab' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value.
				'postsProgress'                     => __( 'Posts: %1$s/%2$s', 'import-export-by-rockstarlab' ),
				// translators: %d is a dynamic value.
				'imagesSyncedProgress'              => __( 'Images synced: %d', 'import-export-by-rockstarlab' ),

				// Time formats
				// translators: %d is a dynamic value.
				'timeFormatSeconds'                 => __( '%ds', 'import-export-by-rockstarlab' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value.
				'timeFormatMinutesSeconds'          => __( '%1$sm %2$ss', 'import-export-by-rockstarlab' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value.
				'timeFormatHoursMinutes'            => __( '%1$sh %2$sm', 'import-export-by-rockstarlab' ),

				// File validation
				// translators: %1$s is a dynamic value, %2$s is a dynamic value.
				'fileSizeExceeds'                   => __( 'File size (%1$s) exceeds maximum allowed size (%2$s)', 'import-export-by-rockstarlab' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value.
				'fileTypeNotAllowed'                => __( 'File type .%1$s is not allowed. Allowed types: %2$s', 'import-export-by-rockstarlab' ),

				// Export
					'exportStartedSuccess'          => __( 'Export started successfully', 'import-export-by-rockstarlab' ),
					'exportCompletedSuccess'        => __( 'Export completed successfully!', 'import-export-by-rockstarlab' ),
					'confirmCancelExport'           => __( 'Are you sure you want to cancel this export?', 'import-export-by-rockstarlab' ),
					'exportCancelled'               => __( 'Export cancelled', 'import-export-by-rockstarlab' ),

					// AI URL Importer
					'testing'                       => __( 'Testing...', 'import-export-by-rockstarlab' ),
					'testConnection'                => __( 'Test Connection', 'import-export-by-rockstarlab' ),
					'generatingPreview'             => __( 'Generating Preview...', 'import-export-by-rockstarlab' ),
					'generatePreview'               => __( 'Generate Preview', 'import-export-by-rockstarlab' ),
					'failedLoadAcfFields'           => __( 'Failed to load ACF fields. Please try again.', 'import-export-by-rockstarlab' ),
					'noAcfFields'                   => __( 'No ACF fields found for this post type.', 'import-export-by-rockstarlab' ),
					'noImagesFound'                 => __( 'No images found', 'import-export-by-rockstarlab' ),
					'noFeaturedImage'               => __( 'No featured image selected', 'import-export-by-rockstarlab' ),
				'confirmCancelImport'               => __( 'Are you sure you want to cancel this import?', 'import-export-by-rockstarlab' ),
				'failedCancelImport'                => __( 'Failed to cancel the import. Please try again.', 'import-export-by-rockstarlab' ),
				'error'                             => __( 'Error', 'import-export-by-rockstarlab' ),
				'rateLimitReached'                  => __( 'Rate Limit Reached', 'import-export-by-rockstarlab' ),
				// translators: %s is a dynamic value.
				'importCompleted'                   => __( 'Import completed! %s URLs imported successfully.', 'import-export-by-rockstarlab' ),
				// translators: %s is a dynamic value.
				'importFailed'                      => __( 'Import failed: %s', 'import-export-by-rockstarlab' ),                  // Import
					'showingFirstRows'              => __( 'Showing first 5 rows', 'import-export-by-rockstarlab' ),
					'pleaseSelectTable'             => __( 'Please select a database table above to see available columns', 'import-export-by-rockstarlab' ),
					'selectTable'                   => __( 'Select a table...', 'import-export-by-rockstarlab' ),
					'noTablesFound'                 => __( 'No tables found', 'import-export-by-rockstarlab' ),
					'errorLoadingTables'            => __( 'Error loading tables', 'import-export-by-rockstarlab' ),
				'loading'                           => __( 'Loading...', 'import-export-by-rockstarlab' ),
				'errorLoadingColumns'               => __( 'Error loading columns', 'import-export-by-rockstarlab' ),
				'loadingTableColumns'               => __( 'Loading table columns...', 'import-export-by-rockstarlab' ),
				'pleaseEnterFieldName'              => __( 'Please enter a field name', 'import-export-by-rockstarlab' ),
				'failedTestPipeline'                => __( 'Failed to test pipeline', 'import-export-by-rockstarlab' ),
				'confirmCancelImportStep'           => __( 'Are you sure you want to cancel this import?', 'import-export-by-rockstarlab' ),

				// Export (additional strings)
				// translators: %s = content placeholder.
				'exportComplete'                    => __( 'Export Complete!', 'import-export-by-rockstarlab' ),
				'selectPostType'                    => __( 'Select Post Type', 'import-export-by-rockstarlab' ),
				'selectPostTypePlaceholder'         => __( 'Select Post Type...', 'import-export-by-rockstarlab' ),
				'selectTaxonomy'                    => __( 'Select Taxonomy', 'import-export-by-rockstarlab' ),
				'selectTaxonomyPlaceholder'         => __( 'Select Taxonomy...', 'import-export-by-rockstarlab' ),
				'selectTablePlaceholder'            => __( 'Select Table...', 'import-export-by-rockstarlab' ),
				'selectField'                       => __( 'Select Field...', 'import-export-by-rockstarlab' ),
				'value'                             => __( 'Value', 'import-export-by-rockstarlab' ),
				'errorLoadingPostTypes'             => __( 'Error loading post types', 'import-export-by-rockstarlab' ),
				'errorLoadingTaxonomies'            => __( 'Error loading taxonomies', 'import-export-by-rockstarlab' ),

				// Export Step 3
				'assignFunctionsTitle'              => __( 'Assign functions', 'import-export-by-rockstarlab' ),
				'remove'                            => __( 'Remove', 'import-export-by-rockstarlab' ),
				'functions'                         => __( 'function(s)', 'import-export-by-rockstarlab' ),
				'enterColumnName'                   => __( 'Enter column name:', 'import-export-by-rockstarlab' ),
				'noFieldsSelected'                  => __( 'No Fields Selected', 'import-export-by-rockstarlab' ),
				'pleaseSelectFieldMessage'          => __( 'Please select at least one field to continue with the export.', 'import-export-by-rockstarlab' ),
				'addAll'                            => __( 'Add all', 'import-export-by-rockstarlab' ),
				'addAllFieldsTitle'                 => __( 'Add all fields from this category', 'import-export-by-rockstarlab' ),
				'loadingAcfFields'                  => __( 'Loading ACF fields...', 'import-export-by-rockstarlab' ),
				'loadingYoastFields'                => __( 'Loading Yoast SEO fields...', 'import-export-by-rockstarlab' ),
				'noFunctionsAvailableYet'           => __( 'No functions available yet.', 'import-export-by-rockstarlab' ),
				'createFirstFunction'               => __( 'Create your first custom function to get started.', 'import-export-by-rockstarlab' ),
				// translators: %s is a dynamic value.
				'noFunctionsFound'                  => __( 'No %s functions found.', 'import-export-by-rockstarlab' ),
				// translators: %s is a dynamic value.
				'errorLabel'                        => __( 'Error: %s', 'import-export-by-rockstarlab' ),

				// Export (UI strings used in export.js)
				'noDataAvailable'                   => __( 'No Data Available', 'import-export-by-rockstarlab' ),
				'adjustFiltersMessage'              => __( 'Adjust your filters or select a different content type to continue with the export.', 'import-export-by-rockstarlab' ),
				'postTypeRequired'                  => __( 'Post Type Required', 'import-export-by-rockstarlab' ),
				'pleaseSelectPostType'              => __( 'Please select a specific post type from the dropdown to continue.', 'import-export-by-rockstarlab' ),
				'taxonomyRequired'                  => __( 'Taxonomy Required', 'import-export-by-rockstarlab' ),
				'pleaseSelectTaxonomy'              => __( 'Please select a specific taxonomy from the dropdown to continue.', 'import-export-by-rockstarlab' ),
				'tableRequired'                     => __( 'Table Required', 'import-export-by-rockstarlab' ),
				'pleaseSelectTable'                 => __( 'Please select a database table from the dropdown to continue.', 'import-export-by-rockstarlab' ),
				'enterNumberPlaceholder'            => __( 'Enter number...', 'import-export-by-rockstarlab' ),
				'enterFilterValue'                  => __( 'Enter value...', 'import-export-by-rockstarlab' ),
				'enterCustomFieldName'              => __( 'Enter custom field name...', 'import-export-by-rockstarlab' ),
				'taxonomyPlaceholderExamples'       => __( 'e.g., category, post_tag, product_cat...', 'import-export-by-rockstarlab' ),
				'enterTermSlugs'                    => __( 'Enter term slugs (comma-separated)...', 'import-export-by-rockstarlab' ),
				'inFilter'                          => __( 'In', 'import-export-by-rockstarlab' ),
				'notInFilter'                       => __( 'Not In', 'import-export-by-rockstarlab' ),
				'inComma'                           => __( 'In (comma-separated)', 'import-export-by-rockstarlab' ),
				'notInComma'                        => __( 'Not In (comma-separated)', 'import-export-by-rockstarlab' ),
				'hasTermsIn'                        => __( 'Has Term(s) - IN', 'import-export-by-rockstarlab' ),
				'doesNotHaveTermsNotIn'             => __( 'Does Not Have Term(s) - NOT IN', 'import-export-by-rockstarlab' ),
				'hasAllTermsAnd'                    => __( 'Has All Terms - AND', 'import-export-by-rockstarlab' ),
				'pleaseSelectFieldToExport'         => __( 'Please select at least one field to export', 'import-export-by-rockstarlab' ),
				'pleaseUploadFile'                  => __( 'Please upload a file', 'import-export-by-rockstarlab' ),
				'pleaseEnterCustomDelimiter'        => __( 'Please enter a custom delimiter', 'import-export-by-rockstarlab' ),
				'pleaseSelectPostType'              => __( 'Please select a post type', 'import-export-by-rockstarlab' ),
				'exportFailed'                      => __( 'Export failed', 'import-export-by-rockstarlab' ),
				'unknownError'                      => __( 'Unknown error', 'import-export-by-rockstarlab' ),
				'enterValuesCommaSeparated'         => __( 'Enter values separated by comma (e.g., 1,5,8 or test1,test2)', 'import-export-by-rockstarlab' ),
				'enterTwoNumbersCommaSeparated'     => __( 'Enter two numbers separated by comma (e.g., 10,100)', 'import-export-by-rockstarlab' ),

				// Function Categories
				'categoryStringOperations'          => __( 'String Operations', 'import-export-by-rockstarlab' ),
				'categoryDateTime'                  => __( 'Date & Time', 'import-export-by-rockstarlab' ),
				'categoryNumericOperations'         => __( 'Numeric Operations', 'import-export-by-rockstarlab' ),
				'categoryHtmlOperations'            => __( 'HTML Operations', 'import-export-by-rockstarlab' ),
				'categoryWordPress'                 => __( 'WordPress', 'import-export-by-rockstarlab' ),
				'categoryValidation'                => __( 'Validation', 'import-export-by-rockstarlab' ),
				'categoryAdvanced'                  => __( 'Advanced', 'import-export-by-rockstarlab' ),
				'categoryCustom'                    => __( 'Custom', 'import-export-by-rockstarlab' ),

				// Export Field Groups
				'fieldGroupStandard'                => __( 'Standard', 'import-export-by-rockstarlab' ),
				'fieldGroupBasic'                   => __( 'Basic', 'import-export-by-rockstarlab' ),
				'fieldGroupOther'                   => __( 'Other', 'import-export-by-rockstarlab' ),
				'fieldGroupCustomFilters'           => __( 'Custom Filters', 'import-export-by-rockstarlab' ),
				'fieldGroupFileInformation'         => __( 'File Information', 'import-export-by-rockstarlab' ),
				'fieldGroupImageDimensions'         => __( 'Image Dimensions', 'import-export-by-rockstarlab' ),
				'fieldGroupDates'                   => __( 'Dates', 'import-export-by-rockstarlab' ),
				'fieldGroupAuthor'                  => __( 'Author', 'import-export-by-rockstarlab' ),
				'fieldGroupAttachment'              => __( 'Attachment', 'import-export-by-rockstarlab' ),
				'fieldGroupDetails'                 => __( 'Details', 'import-export-by-rockstarlab' ),
				'fieldGroupProfile'                 => __( 'Profile', 'import-export-by-rockstarlab' ),
				'fieldGroupRolePermissions'         => __( 'Role & Permissions', 'import-export-by-rockstarlab' ),
				'fieldGroupPreferences'             => __( 'Preferences', 'import-export-by-rockstarlab' ),
				'fieldGroupStats'                   => __( 'Stats', 'import-export-by-rockstarlab' ),
				'fieldGroupRelatedPost'             => __( 'Related Post', 'import-export-by-rockstarlab' ),
				'fieldGroupHierarchy'               => __( 'Hierarchy', 'import-export-by-rockstarlab' ),
				'fieldGroupBlockThemeComponents'    => __( 'Block Theme Components', 'import-export-by-rockstarlab' ),
				'fieldGroupPostTypeSelection'       => __( 'Post Type Selection', 'import-export-by-rockstarlab' ),
				'fieldGroupTaxonomySelection'       => __( 'Taxonomy Selection', 'import-export-by-rockstarlab' ),
				'fieldGroupTaxonomy'                => __( 'Taxonomy', 'import-export-by-rockstarlab' ),
				'fieldGroupContent'                 => __( 'Content', 'import-export-by-rockstarlab' ),
				'fieldGroupPricing'                 => __( 'Pricing', 'import-export-by-rockstarlab' ),
				'fieldGroupInventory'               => __( 'Inventory', 'import-export-by-rockstarlab' ),
				'fieldGroupProductType'             => __( 'Product Type', 'import-export-by-rockstarlab' ),
				'fieldGroupShipping'                => __( 'Shipping', 'import-export-by-rockstarlab' ),
				'fieldGroupMedia'                   => __( 'Media', 'import-export-by-rockstarlab' ),
				'fieldGroupFeaturedImage'           => __( 'Featured Image', 'import-export-by-rockstarlab' ),
				'fieldGroupFile'                    => __( 'File', 'import-export-by-rockstarlab' ),
				'fieldGroupImage'                   => __( 'Image', 'import-export-by-rockstarlab' ),
				'fieldGroupRole'                    => __( 'Role', 'import-export-by-rockstarlab' ),
				'fieldGroupLinkedProducts'          => __( 'Linked Products', 'import-export-by-rockstarlab' ),
				'fieldGroupAttributes'              => __( 'Attributes', 'import-export-by-rockstarlab' ),
				'fieldGroupTotals'                  => __( 'Totals', 'import-export-by-rockstarlab' ),
				'fieldGroupStructure'               => __( 'Structure', 'import-export-by-rockstarlab' ),
				'fieldGroupStatus'                  => __( 'Status', 'import-export-by-rockstarlab' ),
				'fieldGroupPost'                    => __( 'Post', 'import-export-by-rockstarlab' ),
				'fieldGroupCustomFieldsMeta'        => __( 'Custom Fields (Meta)', 'import-export-by-rockstarlab' ),
				'fieldGroupCustomFieldsUserMeta'    => __( 'Custom Fields (User Meta)', 'import-export-by-rockstarlab' ),
				'fieldGroupCustomFieldsCommentMeta' => __( 'Custom Fields (Comment Meta)', 'import-export-by-rockstarlab' ),
				'fieldGroupCustomFieldsTermMeta'    => __( 'Custom Fields (Term Meta)', 'import-export-by-rockstarlab' ),
				'fieldGroupCustomFields'            => __( 'Custom Fields', 'import-export-by-rockstarlab' ),
				'fieldGroupTaxonomies'              => __( 'Taxonomies', 'import-export-by-rockstarlab' ),
				'fieldGroupMenuItem'                => __( 'Menu Item', 'import-export-by-rockstarlab' ),
				'fieldGroupAttribute'               => __( 'Attribute', 'import-export-by-rockstarlab' ),
				'fieldGroupCommentData'             => __( 'Comment Data', 'import-export-by-rockstarlab' ),
				'fieldGroupTermData'                => __( 'Term Data', 'import-export-by-rockstarlab' ),
				'fieldGroupReviews'                 => __( 'Reviews', 'import-export-by-rockstarlab' ),
				'fieldGroupVisibility'              => __( 'Visibility', 'import-export-by-rockstarlab' ),
				'fieldGroupAmounts'                 => __( 'Amounts', 'import-export-by-rockstarlab' ),
				'fieldGroupCustomer'                => __( 'Customer', 'import-export-by-rockstarlab' ),
				'fieldGroupBillingAddress'          => __( 'Billing Address', 'import-export-by-rockstarlab' ),
				'fieldGroupShippingAddress'         => __( 'Shipping Address', 'import-export-by-rockstarlab' ),
				'fieldGroupOrderItems'              => __( 'Order Items', 'import-export-by-rockstarlab' ),
				'fieldGroupPayment'                 => __( 'Payment', 'import-export-by-rockstarlab' ),
				'fieldGroupNotes'                   => __( 'Notes', 'import-export-by-rockstarlab' ),
				'fieldGroupDiscount'                => __( 'Discount', 'import-export-by-rockstarlab' ),
				'fieldGroupUsageRestrictions'       => __( 'Usage Restrictions', 'import-export-by-rockstarlab' ),
				'fieldGroupProductRestrictions'     => __( 'Product Restrictions', 'import-export-by-rockstarlab' ),
				'fieldGroupEmailRestrictions'       => __( 'Email Restrictions', 'import-export-by-rockstarlab' ),
				'fieldGroupUsageLimits'             => __( 'Usage Limits', 'import-export-by-rockstarlab' ),
				'fieldGroupSettings'                => __( 'Settings', 'import-export-by-rockstarlab' ),
				'fieldGroupTerms'                   => __( 'Terms', 'import-export-by-rockstarlab' ),
				'fieldGroupTableColumns'            => __( 'Table Columns', 'import-export-by-rockstarlab' ),
				'fieldGroupTableSelection'          => __( 'Table Selection', 'import-export-by-rockstarlab' ),

				// Export Field Labels (Common)
				'fieldTitle'                        => __( 'Title', 'import-export-by-rockstarlab' ),
				'fieldContent'                      => __( 'Content', 'import-export-by-rockstarlab' ),
				'fieldExcerpt'                      => __( 'Excerpt', 'import-export-by-rockstarlab' ),
				'fieldDate'                         => __( 'Date', 'import-export-by-rockstarlab' ),
				'fieldStatus'                       => __( 'Status', 'import-export-by-rockstarlab' ),
				'fieldCommentStatus'                => __( 'Comment Status', 'import-export-by-rockstarlab' ),
				'fieldModifiedDate'                 => __( 'Modified Date', 'import-export-by-rockstarlab' ),
				'fieldTemplate'                     => __( 'Template', 'import-export-by-rockstarlab' ),
				'fieldCustomFieldMeta'              => __( '🔧 Custom Field (Meta)', 'import-export-by-rockstarlab' ),
				'fieldTaxonomyFilter'               => __( '🏷️ Taxonomy Filter', 'import-export-by-rockstarlab' ),
				'fieldDescription'                  => __( 'Description', 'import-export-by-rockstarlab' ),
				'fieldCaption'                      => __( 'Caption', 'import-export-by-rockstarlab' ),
				'fieldAltText'                      => __( 'Alt Text', 'import-export-by-rockstarlab' ),
				'fieldFileUrlGuid'                  => __( 'File URL (GUID)', 'import-export-by-rockstarlab' ),
				'fieldFileUrl'                      => __( 'File URL', 'import-export-by-rockstarlab' ),
				'fieldFilePathRelative'             => __( 'File Path (Relative)', 'import-export-by-rockstarlab' ),
				'fieldFileName'                     => __( 'File Name', 'import-export-by-rockstarlab' ),
				'fieldFileExtension'                => __( 'File Extension', 'import-export-by-rockstarlab' ),
				'fieldMimeType'                     => __( 'MIME Type', 'import-export-by-rockstarlab' ),
				'fieldFileSizeBytes'                => __( 'File Size (bytes)', 'import-export-by-rockstarlab' ),
				'fieldWidthPx'                      => __( 'Width (px)', 'import-export-by-rockstarlab' ),
				'fieldHeightPx'                     => __( 'Height (px)', 'import-export-by-rockstarlab' ),
				'fieldUploadDate'                   => __( 'Upload Date', 'import-export-by-rockstarlab' ),
				'fieldAuthorId'                     => __( 'Author ID', 'import-export-by-rockstarlab' ),
				'fieldAuthorName'                   => __( 'Author Name', 'import-export-by-rockstarlab' ),
				'fieldAuthorEmail'                  => __( 'Author Email', 'import-export-by-rockstarlab' ),
				'fieldAttachedToPostId'             => __( 'Attached To (Post ID)', 'import-export-by-rockstarlab' ),
				'fieldAttachedPostTitle'            => __( 'Attached Post Title', 'import-export-by-rockstarlab' ),
				'fieldMenuName'                     => __( 'Menu Name', 'import-export-by-rockstarlab' ),
				'fieldMenuItemsArray'               => __( 'Menu Items (Array)', 'import-export-by-rockstarlab' ),
				'fieldItemsCount'                   => __( 'Items Count', 'import-export-by-rockstarlab' ),
				'fieldThemeLocations'               => __( 'Theme Locations', 'import-export-by-rockstarlab' ),
				'fieldUsername'                     => __( 'Username', 'import-export-by-rockstarlab' ),
				'fieldEmail'                        => __( 'Email', 'import-export-by-rockstarlab' ),
				'fieldDisplayName'                  => __( 'Display Name', 'import-export-by-rockstarlab' ),
				'fieldNiceName'                     => __( 'Nice Name', 'import-export-by-rockstarlab' ),
				'fieldFirstName'                    => __( 'First Name', 'import-export-by-rockstarlab' ),
				'fieldLastName'                     => __( 'Last Name', 'import-export-by-rockstarlab' ),
				'fieldNickname'                     => __( 'Nickname', 'import-export-by-rockstarlab' ),
				'fieldBio'                          => __( 'Bio', 'import-export-by-rockstarlab' ),
				'fieldWebsite'                      => __( 'Website', 'import-export-by-rockstarlab' ),
				'fieldAvatarUrl'                    => __( 'Avatar URL', 'import-export-by-rockstarlab' ),
				'fieldRole'                         => __( 'Role', 'import-export-by-rockstarlab' ),
				'fieldCapabilitiesArray'            => __( 'Capabilities (Array)', 'import-export-by-rockstarlab' ),
				'fieldLanguage'                     => __( 'Language', 'import-export-by-rockstarlab' ),
				'fieldAdminColorScheme'             => __( 'Admin Color Scheme', 'import-export-by-rockstarlab' ),
				'fieldVisualEditor'                 => __( 'Visual Editor', 'import-export-by-rockstarlab' ),
				'fieldPostsCount'                   => __( 'Posts Count', 'import-export-by-rockstarlab' ),
				'fieldRegistrationDate'             => __( 'Registration Date', 'import-export-by-rockstarlab' ),
				'fieldUserStatus'                   => __( 'User Status', 'import-export-by-rockstarlab' ),
				'fieldCommentId'                    => __( 'Comment ID', 'import-export-by-rockstarlab' ),
				'fieldPostId'                       => __( 'Post ID', 'import-export-by-rockstarlab' ),
				'fieldCommentContent'               => __( 'Comment Content', 'import-export-by-rockstarlab' ),
				'fieldCommentType'                  => __( 'Comment Type', 'import-export-by-rockstarlab' ),
				'fieldAuthorUrl'                    => __( 'Author URL', 'import-export-by-rockstarlab' ),
				'fieldAuthorIp'                     => __( 'Author IP', 'import-export-by-rockstarlab' ),
				'fieldUserId'                       => __( 'User ID', 'import-export-by-rockstarlab' ),
				'fieldUserAgent'                    => __( 'User Agent', 'import-export-by-rockstarlab' ),
				'fieldPostTitle'                    => __( 'Post Title', 'import-export-by-rockstarlab' ),
				'fieldPostAuthorId'                 => __( 'Post Author ID', 'import-export-by-rockstarlab' ),
				'fieldCommentDate'                  => __( 'Comment Date', 'import-export-by-rockstarlab' ),
				'fieldCommentDateGmt'               => __( 'Comment Date (GMT)', 'import-export-by-rockstarlab' ),
				'fieldParentCommentId'              => __( 'Parent Comment ID', 'import-export-by-rockstarlab' ),
				'fieldKarma'                        => __( 'Karma', 'import-export-by-rockstarlab' ),
				'fieldGlobalStylesThemeJson'        => __( 'Global Styles (theme.json)', 'import-export-by-rockstarlab' ),
				'fieldCustomTemplates'              => __( 'Custom Templates', 'import-export-by-rockstarlab' ),
				'fieldTemplateParts'                => __( 'Template Parts', 'import-export-by-rockstarlab' ),
				'fieldThemeModifications'           => __( 'Theme Modifications', 'import-export-by-rockstarlab' ),
				'fieldCustomCss'                    => __( 'Custom CSS', 'import-export-by-rockstarlab' ),
				'fieldPostTypeSelectSpecific'       => __( 'Post Type (select specific)', 'import-export-by-rockstarlab' ),
				'fieldId'                           => __( 'ID', 'import-export-by-rockstarlab' ),
				'fieldSlug'                         => __( 'Slug', 'import-export-by-rockstarlab' ),
				'fieldParentId'                     => __( 'Parent ID', 'import-export-by-rockstarlab' ),
				'fieldTermMetaField'                => __( '🔧 Term Meta Field', 'import-export-by-rockstarlab' ),
				'fieldTaxonomySelectSpecific'       => __( 'Taxonomy (select specific)', 'import-export-by-rockstarlab' ),
				'fieldTermId'                       => __( 'Term ID', 'import-export-by-rockstarlab' ),
				'fieldTermName'                     => __( 'Term Name', 'import-export-by-rockstarlab' ),
				'fieldTermSlug'                     => __( 'Term Slug', 'import-export-by-rockstarlab' ),
				'fieldTaxonomyType'                 => __( 'Taxonomy Type', 'import-export-by-rockstarlab' ),
				'fieldTaxonomyId'                   => __( 'Taxonomy ID', 'import-export-by-rockstarlab' ),
				'fieldParentTermId'                 => __( 'Parent Term ID', 'import-export-by-rockstarlab' ),
				'fieldProductId'                    => __( 'Product ID', 'import-export-by-rockstarlab' ),
				'fieldProductName'                  => __( 'Product Name', 'import-export-by-rockstarlab' ),
				'fieldSku'                          => __( 'SKU', 'import-export-by-rockstarlab' ),
				'fieldShortDescription'             => __( 'Short Description', 'import-export-by-rockstarlab' ),
				'fieldRegularPrice'                 => __( 'Regular Price', 'import-export-by-rockstarlab' ),
				'fieldSalePrice'                    => __( 'Sale Price', 'import-export-by-rockstarlab' ),
				'fieldTaxStatus'                    => __( 'Tax Status', 'import-export-by-rockstarlab' ),
				'fieldTaxClass'                     => __( 'Tax Class', 'import-export-by-rockstarlab' ),
				'fieldStockQuantity'                => __( 'Stock Quantity', 'import-export-by-rockstarlab' ),
				'fieldStockStatus'                  => __( 'Stock Status', 'import-export-by-rockstarlab' ),
				'fieldManageStock'                  => __( 'Manage Stock', 'import-export-by-rockstarlab' ),
				'fieldBackorders'                   => __( 'Backorders', 'import-export-by-rockstarlab' ),
				'fieldProductType'                  => __( 'Product Type', 'import-export-by-rockstarlab' ),
				'fieldDownloadable'                 => __( 'Downloadable', 'import-export-by-rockstarlab' ),
				'fieldVirtual'                      => __( 'Virtual', 'import-export-by-rockstarlab' ),
				'fieldWeight'                       => __( 'Weight', 'import-export-by-rockstarlab' ),
				'fieldLength'                       => __( 'Length', 'import-export-by-rockstarlab' ),
				'fieldWidth'                        => __( 'Width', 'import-export-by-rockstarlab' ),
				'fieldHeight'                       => __( 'Height', 'import-export-by-rockstarlab' ),
				'fieldShippingClass'                => __( 'Shipping Class', 'import-export-by-rockstarlab' ),
				'fieldFeaturedImage'                => __( 'Featured Image', 'import-export-by-rockstarlab' ),
				'fieldFeaturedImageId'              => __( 'Featured Image ID', 'import-export-by-rockstarlab' ),
				'fieldFeaturedImageUrl'             => __( 'Featured Image URL', 'import-export-by-rockstarlab' ),
				'fieldFeaturedImageTitle'           => __( 'Featured Image Title', 'import-export-by-rockstarlab' ),
				'fieldFeaturedImageCaption'         => __( 'Featured Image Caption', 'import-export-by-rockstarlab' ),
				'fieldGalleryImages'                => __( 'Gallery Images', 'import-export-by-rockstarlab' ),
				'fieldCategories'                   => __( 'Categories', 'import-export-by-rockstarlab' ),
				'fieldTags'                         => __( 'Tags', 'import-export-by-rockstarlab' ),
				'fieldAverageRating'                => __( 'Average Rating', 'import-export-by-rockstarlab' ),
				'fieldReviewCount'                  => __( 'Review Count', 'import-export-by-rockstarlab' ),
				'fieldReviewsEnabled'               => __( 'Reviews Enabled', 'import-export-by-rockstarlab' ),
				'fieldFeatured'                     => __( 'Featured', 'import-export-by-rockstarlab' ),
				'fieldCatalogVisibility'            => __( 'Catalog Visibility', 'import-export-by-rockstarlab' ),
				'fieldTotalSales'                   => __( 'Total Sales', 'import-export-by-rockstarlab' ),
				'fieldCreatedDate'                  => __( 'Created Date', 'import-export-by-rockstarlab' ),
				'fieldOrderId'                      => __( 'Order ID', 'import-export-by-rockstarlab' ),
				'fieldOrderNumber'                  => __( 'Order Number', 'import-export-by-rockstarlab' ),
				'fieldOrderKey'                     => __( 'Order Key', 'import-export-by-rockstarlab' ),
				'fieldCurrency'                     => __( 'Currency', 'import-export-by-rockstarlab' ),
				'fieldOrderTotal'                   => __( 'Order Total', 'import-export-by-rockstarlab' ),
				'fieldSubtotal'                     => __( 'Subtotal', 'import-export-by-rockstarlab' ),
				'fieldTax'                          => __( 'Tax', 'import-export-by-rockstarlab' ),
				'fieldShipping'                     => __( 'Shipping', 'import-export-by-rockstarlab' ),
				'fieldDiscount'                     => __( 'Discount', 'import-export-by-rockstarlab' ),
				'fieldCustomerId'                   => __( 'Customer ID', 'import-export-by-rockstarlab' ),
				'fieldCustomerNote'                 => __( 'Customer Note', 'import-export-by-rockstarlab' ),
				'fieldCompany'                      => __( 'Company', 'import-export-by-rockstarlab' ),
				'fieldAddress1'                     => __( 'Address 1', 'import-export-by-rockstarlab' ),
				'fieldAddress2'                     => __( 'Address 2', 'import-export-by-rockstarlab' ),
				'fieldCity'                         => __( 'City', 'import-export-by-rockstarlab' ),
				'fieldState'                        => __( 'State', 'import-export-by-rockstarlab' ),
				'fieldPostcode'                     => __( 'Postcode', 'import-export-by-rockstarlab' ),
				'fieldCountry'                      => __( 'Country', 'import-export-by-rockstarlab' ),
				'fieldPhone'                        => __( 'Phone', 'import-export-by-rockstarlab' ),
				'fieldOrderItemsArray'              => __( 'Order Items (Array)', 'import-export-by-rockstarlab' ),
				'fieldItemCount'                    => __( 'Item Count', 'import-export-by-rockstarlab' ),
				'fieldPaymentMethod'                => __( 'Payment Method', 'import-export-by-rockstarlab' ),
				'fieldPaymentMethodTitle'           => __( 'Payment Method Title', 'import-export-by-rockstarlab' ),
				'fieldTransactionId'                => __( 'Transaction ID', 'import-export-by-rockstarlab' ),
				'fieldShippingMethod'               => __( 'Shipping Method', 'import-export-by-rockstarlab' ),
				'fieldOrderDate'                    => __( 'Order Date', 'import-export-by-rockstarlab' ),
				'fieldCompletedDate'                => __( 'Completed Date', 'import-export-by-rockstarlab' ),
				'fieldPaidDate'                     => __( 'Paid Date', 'import-export-by-rockstarlab' ),
				'fieldOrderNotesArray'              => __( 'Order Notes (Array)', 'import-export-by-rockstarlab' ),
				'fieldCouponId'                     => __( 'Coupon ID', 'import-export-by-rockstarlab' ),
				'fieldCouponCode'                   => __( 'Coupon Code', 'import-export-by-rockstarlab' ),
				'fieldDiscountType'                 => __( 'Discount Type', 'import-export-by-rockstarlab' ),
				'fieldCouponAmount'                 => __( 'Coupon Amount', 'import-export-by-rockstarlab' ),
				'fieldFreeShipping'                 => __( 'Free Shipping', 'import-export-by-rockstarlab' ),
				'fieldMinimumSpend'                 => __( 'Minimum Spend', 'import-export-by-rockstarlab' ),
				// translators: %s = content placeholder.
				'fieldMaximumSpend'                 => __( 'Maximum Spend', 'import-export-by-rockstarlab' ),
				'fieldIndividualUseOnly'            => __( 'Individual Use Only', 'import-export-by-rockstarlab' ),
				'fieldExcludeSaleItems'             => __( 'Exclude Sale Items', 'import-export-by-rockstarlab' ),
				'fieldAllowedProducts'              => __( 'Allowed Products', 'import-export-by-rockstarlab' ),
				'fieldExcludedProducts'             => __( 'Excluded Products', 'import-export-by-rockstarlab' ),
				'fieldAllowedCategories'            => __( 'Allowed Categories', 'import-export-by-rockstarlab' ),
				// translators: %s = content placeholder.
				'fieldExcludedCategories'           => __( 'Excluded Categories', 'import-export-by-rockstarlab' ),
				'fieldAllowedEmails'                => __( 'Allowed Emails', 'import-export-by-rockstarlab' ),
				'fieldUsageCount'                   => __( 'Usage Count', 'import-export-by-rockstarlab' ),
				'fieldUsageLimitTotal'              => __( 'Usage Limit Total', 'import-export-by-rockstarlab' ),
				'fieldUsageLimitPerUser'            => __( 'Usage Limit Per User', 'import-export-by-rockstarlab' ),
				'fieldExpiryDate'                   => __( 'Expiry Date', 'import-export-by-rockstarlab' ),
				'fieldAttributeId'                  => __( 'Attribute ID', 'import-export-by-rockstarlab' ),
				'fieldAttributeName'                => __( 'Attribute Name', 'import-export-by-rockstarlab' ),
				'fieldAttributeLabel'               => __( 'Attribute Label', 'import-export-by-rockstarlab' ),
				'fieldAttributeType'                => __( 'Attribute Type', 'import-export-by-rockstarlab' ),
				'fieldDefaultSortOrder'             => __( 'Default Sort Order', 'import-export-by-rockstarlab' ),
				'fieldEnableArchives'               => __( 'Enable Archives', 'import-export-by-rockstarlab' ),
				'fieldTermsCount'                   => __( 'Terms Count', 'import-export-by-rockstarlab' ),
				'fieldAllTermsArray'                => __( 'All Terms (Array)', 'import-export-by-rockstarlab' ),
				'fieldSelectTableFirst'             => __( '⚠️ Please select a database table first', 'import-export-by-rockstarlab' ),
				'fieldPleaseSelectTable'            => __( '⚠️ Please select a database table first', 'import-export-by-rockstarlab' ),

				// Content Updater
				'confirmClearFields'                => __( 'Are you sure you want to clear all selected fields?', 'import-export-by-rockstarlab' ),
				'confirmClearFunctions'             => __( 'Are you sure you want to clear all function assignments?', 'import-export-by-rockstarlab' ),
				'confirmCancelUpdate'               => __( 'Are you sure you want to cancel the update?', 'import-export-by-rockstarlab' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value.
				'processingItems'                   => __( 'Processing items... (%1$s / %2$s)', 'import-export-by-rockstarlab' ),
				'premiumOnlyFeature'                => __( 'This content type is only available in the Premium version. Upgrade to unlock this feature.', 'import-export-by-rockstarlab' ),
				'fileValidationFailed'              => __( 'File Validation Failed', 'import-export-by-rockstarlab' ),
				'goBackUploadValidFile'             => __( 'Go Back and Upload a Valid File', 'import-export-by-rockstarlab' ),
				'pleaseSelectContentType'           => __( 'Please select a content type', 'import-export-by-rockstarlab' ),
				'pleaseSelectAtLeastOneField'       => __( 'Please select at least one field to update', 'import-export-by-rockstarlab' ),
				'pleaseAssignFunction'              => __( 'Please assign at least one function to a field', 'import-export-by-rockstarlab' ),
				// translators: %s = field name.
				'fieldAlreadySelected'              => __( 'Field "%s" is already selected', 'import-export-by-rockstarlab' ),
				'noFieldsSelected'                  => __( 'No fields selected. Please go back and select fields first.', 'import-export-by-rockstarlab' ),
				'assignFunctions'                   => __( 'Assign Functions', 'import-export-by-rockstarlab' ),
				'noFunctionsAvailable'              => __( 'No functions available. Create a custom function first.', 'import-export-by-rockstarlab' ),
				'add'                               => __( 'Add', 'import-export-by-rockstarlab' ),
				'enterTestValue'                    => __( 'Enter a test value:', 'import-export-by-rockstarlab' ),
				'noFunctionAssigned'                => __( 'No function assigned to this field', 'import-export-by-rockstarlab' ),
				'functionTestFailed'                => __( 'Function test failed', 'import-export-by-rockstarlab' ),
				'updateStarted'                     => __( 'Update started successfully', 'import-export-by-rockstarlab' ),
				'failedStartUpdate'                 => __( 'Failed to start update', 'import-export-by-rockstarlab' ),
				'noFieldsSelectedError'             => __( 'No fields selected. Please go back and select fields to update.', 'import-export-by-rockstarlab' ),
				'noFunctionsAssigned'               => __( 'No functions assigned. Please go back and assign functions to fields.', 'import-export-by-rockstarlab' ),
				'updateCancelled'                   => __( 'Update cancelled', 'import-export-by-rockstarlab' ),
				'functionAssignmentsCleared'        => __( 'All function assignments cleared', 'import-export-by-rockstarlab' ),
				'enterFunctionId'                   => __( 'Enter function ID to apply to all fields (or leave empty for none):', 'import-export-by-rockstarlab' ),
				'functionAppliedToAll'              => __( 'Function applied to all fields', 'import-export-by-rockstarlab' ),
				'pleaseEnterTestValue'              => __( 'Please enter a test value', 'import-export-by-rockstarlab' ),
				'noFunctionsToTest'                 => __( 'No functions to test', 'import-export-by-rockstarlab' ),
				'testFailed'                        => __( 'Test failed', 'import-export-by-rockstarlab' ),
				'configurationError'                => __( 'Configuration error: rslIeData not found', 'import-export-by-rockstarlab' ),
				'errorTestingPipeline'              => __( 'Error testing pipeline', 'import-export-by-rockstarlab' ),
				'input'                             => __( 'Input', 'import-export-by-rockstarlab' ),
				// Filter conditions
				'equals'                            => __( 'Equals', 'import-export-by-rockstarlab' ),
				'notEquals'                         => __( 'Not Equals', 'import-export-by-rockstarlab' ),
				'contains'                          => __( 'Contains', 'import-export-by-rockstarlab' ),
				'notContains'                       => __( 'Not Contains', 'import-export-by-rockstarlab' ),
				'startsWith'                        => __( 'Starts With', 'import-export-by-rockstarlab' ),
				'endsWith'                          => __( 'Ends With', 'import-export-by-rockstarlab' ),
				'isEmpty'                           => __( 'Is Empty', 'import-export-by-rockstarlab' ),
				'isNotEmpty'                        => __( 'Is Not Empty', 'import-export-by-rockstarlab' ),
				'greaterThan'                       => __( 'Greater Than', 'import-export-by-rockstarlab' ),
				'lessThan'                          => __( 'Less Than', 'import-export-by-rockstarlab' ),
				'greaterOrEqual'                    => __( 'Greater or Equal', 'import-export-by-rockstarlab' ),
				'lessOrEqual'                       => __( 'Less or Equal', 'import-export-by-rockstarlab' ),
				'between'                           => __( 'Between', 'import-export-by-rockstarlab' ),
				'onDate'                            => __( 'On Date', 'import-export-by-rockstarlab' ),
				'before'                            => __( 'Before', 'import-export-by-rockstarlab' ),
				'after'                             => __( 'After', 'import-export-by-rockstarlab' ),              // Content Sync
				'addNewSite'                        => __( 'Add New Site', 'import-export-by-rockstarlab' ),
				'editSite'                          => __( 'Edit Site', 'import-export-by-rockstarlab' ),
				'saveConnection'                    => __( 'Save Connection', 'import-export-by-rockstarlab' ),
				'hideDetails'                       => __( 'Hide Details', 'import-export-by-rockstarlab' ),
				'showDetails'                       => __( 'Show Details', 'import-export-by-rockstarlab' ),
				'copied'                            => __( 'Copied!', 'import-export-by-rockstarlab' ),
				// translators: %s = content placeholder.
				'regenerating'                      => __( 'Regenerating...', 'import-export-by-rockstarlab' ),
				'regenerated'                       => __( 'Regenerated!', 'import-export-by-rockstarlab' ),
				'updating'                          => __( 'Updating...', 'import-export-by-rockstarlab' ),
				'validatingSaving'                  => __( 'Validating & Saving...', 'import-export-by-rockstarlab' ),
				'validatingApiKey'                  => __( 'Validating API key...', 'import-export-by-rockstarlab' ),
				'pleaseWaitVerifying'               => __( 'Please wait while we verify the connection to the remote site.', 'import-export-by-rockstarlab' ),
				'operationCompleted'                => __( 'Operation completed successfully', 'import-export-by-rockstarlab' ),
				'noChanges'                         => __( 'No Changes', 'import-export-by-rockstarlab' ),
				'success'                           => __( 'Success!', 'import-export-by-rockstarlab' ),
				'validationFailed'                  => __( 'Validation Failed', 'import-export-by-rockstarlab' ),
				'failedSaveSiteConnection'          => __( 'Failed to save site connection', 'import-export-by-rockstarlab' ),
				'connectionError'                   => __( 'Connection Error', 'import-export-by-rockstarlab' ),
				'unexpectedError'                   => __( 'An unexpected error occurred while trying to save the site connection.', 'import-export-by-rockstarlab' ),
				'connectionFailed'                  => __( 'Connection Failed', 'import-export-by-rockstarlab' ),
				'possibleReasons'                   => __( 'Possible reasons:', 'import-export-by-rockstarlab' ),
				'urlIncorrect'                      => __( '- The URL is incorrect or not accessible', 'import-export-by-rockstarlab' ),
				'remoteSiteOffline'                 => __( '- The remote site is offline', 'import-export-by-rockstarlab' ),
				'networkFirewall'                   => __( '- Network or firewall issues are blocking the connection', 'import-export-by-rockstarlab' ),
				'invalidApiKey'                     => __( 'Invalid API Key', 'import-export-by-rockstarlab' ),
				'toResolveIssue'                    => __( 'To resolve this issue:', 'import-export-by-rockstarlab' ),
				'goToContentSync'                   => __( '- Go to Content Sync page on the remote site', 'import-export-by-rockstarlab' ),
				'clickShowDetails'                  => __( '- Click "Show Details" to reveal the API key', 'import-export-by-rockstarlab' ),
				// translators: %s = content placeholder.
				'copyEntireKey'                     => __( '- Copy the entire key and paste it here', 'import-export-by-rockstarlab' ),
				'pluginNotFound'                    => __( 'Plugin Not Found', 'import-export-by-rockstarlab' ),
				'duplicateConnection'               => __( 'Duplicate Connection', 'import-export-by-rockstarlab' ),
				'siteAlreadyConnected'              => __( 'This site URL is already in your connected sites list.', 'import-export-by-rockstarlab' ),
				'validationError'                   => __( 'Validation Error', 'import-export-by-rockstarlab' ),
				'networkError'                      => __( 'Network Error', 'import-export-by-rockstarlab' ),
				'unableConnectServer'               => __( 'Unable to connect to the server. Please check your internet connection.', 'import-export-by-rockstarlab' ),
				'serverError'                       => __( 'Server Error', 'import-export-by-rockstarlab' ),
				// translators: %s is a dynamic value.
				'serverReturnedError'               => __( 'The server returned an error (%s). Please try again later.', 'import-export-by-rockstarlab' ),
				'notFound'                          => __( 'Not Found', 'import-export-by-rockstarlab' ),
				'endpointNotFound'                  => __( 'The requested endpoint was not found. Please check if the plugin is properly installed.', 'import-export-by-rockstarlab' ),

				// translators: %s = content placeholder.
				// Functions Management
				'serverErrorPhpSyntax'              => __( 'Server error: The function code contains errors that prevent it from being saved. Please check your PHP syntax.', 'import-export-by-rockstarlab' ),
				'serverErrorUnableToSave'           => __( 'Server error: Unable to save function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.', 'import-export-by-rockstarlab' ),
				// translators: %s = content placeholder.
				'failedToLoadFunctions'             => __( 'Failed to load functions', 'import-export-by-rockstarlab' ),
				'failedToLoadFunction'              => __( 'Failed to load function', 'import-export-by-rockstarlab' ),
				'failedToSaveFunction'              => __( 'Failed to save function', 'import-export-by-rockstarlab' ),
				'failedToDeleteFunction'            => __( 'Failed to delete function', 'import-export-by-rockstarlab' ),
				'pleaseEnterFunctionCode'           => __( 'Please enter function code first', 'import-export-by-rockstarlab' ),
				'serverErrorFunctionErrors'         => __( 'Server error: The function code contains errors. Please check your PHP syntax.', 'import-export-by-rockstarlab' ),
				// translators: %s = content placeholder.
				'serverErrorUnableToTest'           => __( 'Server error: Unable to test function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.', 'import-export-by-rockstarlab' ),
				'testFailed'                        => __( 'Test failed', 'import-export-by-rockstarlab' ),
				'apiKeyNotConfigured'               => __( 'OpenAI API key is not configured. Please configure it in Settings → Connectors (WordPress 7+) or in Plugin Options to use AI generation.\n\nDo you want to go to Plugin Options now?', 'import-export-by-rockstarlab' ),
				'badgeLibrary'                      => __( 'Library', 'import-export-by-rockstarlab' ),
				'badgeCustom'                       => __( 'Custom', 'import-export-by-rockstarlab' ),
				'badgeActive'                       => __( 'Active', 'import-export-by-rockstarlab' ),
				'badgeInactive'                     => __( 'Inactive', 'import-export-by-rockstarlab' ),
				'noDescription'                     => __( 'No description', 'import-export-by-rockstarlab' ),
				'editButton'                        => __( 'Edit', 'import-export-by-rockstarlab' ),
				'deleteButton'                      => __( 'Delete', 'import-export-by-rockstarlab' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value, %3$s is a dynamic value.
				'showingFunctions'                  => __( 'Showing %1$s-%2$s of %3$s functions', 'import-export-by-rockstarlab' ),
				'customizeFunction'                 => __( 'Customize Function', 'import-export-by-rockstarlab' ),

				// Media Sync
				'scanning'                          => __( 'Scanning...', 'import-export-by-rockstarlab' ),
				'starting'                          => __( 'Starting...', 'import-export-by-rockstarlab' ),
				'processing'                        => __( 'Processing...', 'import-export-by-rockstarlab' ),
				'syncPaused'                        => __( 'Synchronization Paused', 'import-export-by-rockstarlab' ),
				'paused'                            => __( 'Paused', 'import-export-by-rockstarlab' ),
				'reresume'                          => __( 'Resume', 'import-export-by-rockstarlab' ),
				'syncInProgress'                    => __( 'Synchronization in Progress', 'import-export-by-rockstarlab' ),
				'pause'                             => __( 'Pause', 'import-export-by-rockstarlab' ),
				'startSync'                         => __( 'Start Sync', 'import-export-by-rockstarlab' ),
				'scanFolder'                        => __( 'Scan Folder', 'import-export-by-rockstarlab' ),
				// translators: %d is a dynamic value.
				'andMoreErrors'                     => __( '... and %d more errors', 'import-export-by-rockstarlab' ),

				// Completion messages
				'syncCompleteTitle'                 => __( 'Synchronization Complete!', 'import-export-by-rockstarlab' ),
				// translators: %s is a dynamic value.
				'successfullyProcessed'             => __( 'Successfully processed %s file', 'import-export-by-rockstarlab' ),
				// translators: %s is a dynamic value.
				'successfullyProcessedPlural'       => __( 'Successfully processed %s files', 'import-export-by-rockstarlab' ),
				'imported'                          => __( 'Imported', 'import-export-by-rockstarlab' ),
				'skipped'                           => __( 'Skipped', 'import-export-by-rockstarlab' ),
				'syncFailedTitle'                   => __( 'Synchronization Failed', 'import-export-by-rockstarlab' ),
				'syncFailedDesc'                    => __( 'The synchronization process encountered an error and could not complete.', 'import-export-by-rockstarlab' ),
				'syncCancelledTitle'                => __( 'Synchronization Cancelled', 'import-export-by-rockstarlab' ),
				// translators: %s is a dynamic value.
				'processedBeforeCancellation'       => __( 'Processed %s file before cancellation.', 'import-export-by-rockstarlab' ),
				// translators: %s is a dynamic value.
				'processedBeforeCancellationPlural' => __( 'Processed %s files before cancellation.', 'import-export-by-rockstarlab' ),

				// Folder browser
				'goUp'                              => __( 'Go Up', 'import-export-by-rockstarlab' ),
				'useThisFolder'                     => __( '. (Use this folder)', 'import-export-by-rockstarlab' ),

				// Notifications
				'dismissNotice'                     => __( 'Dismiss this notice.', 'import-export-by-rockstarlab' ),

				// Jobs Log
				'noJobsFound'                       => __( 'No jobs found.', 'import-export-by-rockstarlab' ),
				// translators: %1$s is a dynamic value, %2$s is a dynamic value, %3$s is a dynamic value.
				'showingJobs'                       => __( 'Showing %1$s-%2$s of %3$s jobs', 'import-export-by-rockstarlab' ),
				'retryRequiresPremium'              => __( 'The PRO addon is required to retry this job.', 'import-export-by-rockstarlab' ),
			),
			)
		);

		// Localize script for Content Sync page
		if ( 'import-export-by-rockstarlab_page_rsl-ie-content-sync' === $admin_page ) {
			wp_localize_script(
				'import-export-by-rockstarlab-scripts',
				'rslIeContentSync',
				array(
					'nonce'     => wp_create_nonce( 'rsl_ie_nonce' ),
					'isPremium' => \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active(),
				)
			);
		}

		wp_enqueue_style(
			'import-export-by-rockstarlab-styles',
			plugins_url( 'assets/css/app.css', RSL_IE_FILE ),
			false,
			filemtime( plugin_dir_path( RSL_IE_FILE ) . 'assets/css/app.css' )
		);

		// WordPress 7+ admin UI tweaks.
		global $wp_version;
		if ( isset( $wp_version ) && version_compare( $wp_version, '7.0', '>=' ) ) {
			wp_enqueue_style(
				'import-export-by-rockstarlab-admin-wp7',
				plugins_url( 'assets/css/admin-wp7.css', RSL_IE_FILE ),
				array( 'import-export-by-rockstarlab-styles' ),
				filemtime( plugin_dir_path( RSL_IE_FILE ) . 'assets/css/admin-wp7.css' )
			);
		}

		// Prevent step-flash when resuming export jobs (use enqueue + inline style, not <style> tags).
		if ( 'import-export-by-rockstarlab_page_rsl-ie-export' === $admin_page ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading resume state only (no state change).
			$resume_job_id = isset( $_GET['resume_job'] ) ? absint( $_GET['resume_job'] ) : 0;
			if ( $resume_job_id ) {
				wp_add_inline_style(
					'import-export-by-rockstarlab-styles',
					'.rsl-ie-resuming-job .rsl-ie-step-1,.rsl-ie-resuming-job .rsl-ie-step-2,.rsl-ie-resuming-job .rsl-ie-step-3,.rsl-ie-resuming-job .rsl-ie-step-4{display:none!important}.rsl-ie-resuming-job .rsl-ie-step-5{display:block!important}'
				);
			}
		}
	}

	/**
	 * Add plugin's settings pages
	 */
	function add_settings_pages() {

		add_menu_page(
			__( 'Import Export by RockStarLab', 'import-export-by-rockstarlab' ),
			__( 'Import Export by RockStarLab', 'import-export-by-rockstarlab' ),
			'manage_options',
			'import-export-by-rockstarlab',
			array( $this, 'display_welcome_page' ),
			'dashicons-update-alt',
			99,
		);

		add_submenu_page(
			'import-export-by-rockstarlab',
			__( 'Welcome', 'import-export-by-rockstarlab' ),
			__( 'Welcome', 'import-export-by-rockstarlab' ) . ' 🎉',
			'manage_options',
			'import-export-by-rockstarlab',
			array( $this, 'display_welcome_page' )
		);

		add_submenu_page(
			'import-export-by-rockstarlab',
			__( 'Import', 'import-export-by-rockstarlab' ),
			__( 'Import', 'import-export-by-rockstarlab' ),
			'manage_options',
			'rsl-ie-import',
			array( $this, 'display_settings_import_page' )
		);

		add_submenu_page(
			'import-export-by-rockstarlab',
			__( 'Export', 'import-export-by-rockstarlab' ),
			__( 'Export', 'import-export-by-rockstarlab' ),
			'manage_options',
			'rsl-ie-export',
			array( $this, 'display_settings_export_page' )
		);

		add_submenu_page(
			'import-export-by-rockstarlab',
			__( 'Content Sync', 'import-export-by-rockstarlab' ),
			__( 'Content Sync', 'import-export-by-rockstarlab' ),
			'manage_options',
			'rsl-ie-content-sync',
			array( $this, 'display_content_sync_page' )
		);

		add_submenu_page(
			'import-export-by-rockstarlab',
			__( 'Content Updater', 'import-export-by-rockstarlab' ),
			__( 'Content Updater', 'import-export-by-rockstarlab' ),
			'manage_options',
			'rsl-ie-content-updater',
			array( $this, 'display_content_updater_page' )
		);

		add_submenu_page(
			'import-export-by-rockstarlab',
			__( 'Media Sync', 'import-export-by-rockstarlab' ),
			__( 'Media Sync', 'import-export-by-rockstarlab' ),
			'manage_options',
			'rsl-ie-media-sync',
			array( $this, 'display_media_sync_page' )
		);

		add_submenu_page(
			'import-export-by-rockstarlab',
			__( 'AI URL Importer', 'import-export-by-rockstarlab' ),
			__( 'AI URL Importer', 'import-export-by-rockstarlab' ) . ' 🤖',
			'manage_options',
			'rsl-ie-ai-url-importer',
			array( $this, 'display_ai_url_importer_page' )
		);

		add_submenu_page(
			'import-export-by-rockstarlab',
			__( 'Functions', 'import-export-by-rockstarlab' ),
			__( 'Functions', 'import-export-by-rockstarlab' ),
			'manage_options',
			'rsl-ie-functions',
			array( $this, 'display_settings_functions_page' )
		);

		add_submenu_page(
			'import-export-by-rockstarlab',
			__( 'Jobs Log', 'import-export-by-rockstarlab' ),
			__( 'Jobs Log', 'import-export-by-rockstarlab' ),
			'manage_options',
			'rsl-ie-jobs-log',
			array( $this, 'display_jobs_log_page' )
		);

		add_submenu_page(
			'import-export-by-rockstarlab',
			__( 'Plugin Options', 'import-export-by-rockstarlab' ),
			__( 'Plugin Options', 'import-export-by-rockstarlab' ),
			'manage_options',
			'rsl-ie-plugin-options',
			array( $this, 'display_plugin_options_page' )
		);
	}

	/**
	 * Display Import Settings Page
	 */
	function display_settings_import_page() {
		rsl_ie()->View->load( 'settings/import' );
	}

	/**
	 * Display Export Settings Page
	 */
	function display_settings_export_page() {
		rsl_ie()->View->load( 'settings/export' );
	}

	/**
	 * Display Content Sync Settings Page
	 */
	function display_content_sync_page() {
		rsl_ie()->View->load( 'settings/content_sync' );
	}

	/**
	 * Display Content Updater Settings Page
	 */
	function display_content_updater_page() {
		rsl_ie()->View->load( 'settings/content_updater' );
	}

	/**
	 * Display Media Sync Settings Page
	 */
	function display_media_sync_page() {
		rsl_ie()->View->load( 'settings/media_sync' );
	}

	/**
	 * Display AI URL Importer Page
	 */
	function display_ai_url_importer_page() {
		rsl_ie()->View->load( 'settings/ai_url_importer' );
	}

	/**
	 * Display Jobs Log Page
	 */
	function display_jobs_log_page() {
		rsl_ie()->View->load( 'settings/jobs-log' );
	}

	/**
	 * Display "Functions" admin page
	 */ // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified via verify_request().
	function display_settings_functions_page() {
		rsl_ie()->View->load( 'settings/functions' );
	}

	/**
	 * Display Plugin Options Page
	 */
	function display_plugin_options_page() {
		rsl_ie()->View->load( 'settings/plugin_options' );
	}

	/**
	 * Display Welcome Page
	 */
	function display_welcome_page() {
		include RSL_IE_PATH . 'app/View/settings/welcome.php';
	}

	/**
	 * Handle redirect to welcome page after activation
	 */
	function welcome_redirect() {
		// Check if we should redirect
		if ( get_transient( 'rsl_ie_activation_redirect' ) ) {
			delete_transient( 'rsl_ie_activation_redirect' );

			// Don't redirect if activating multiple plugins at once
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking for bulk activation, no nonce needed.
			if ( ! isset( $_GET['activate-multi'] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=import-export-by-rockstarlab' ) );
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
			$custom_url = get_post_meta( $post_id, 'rsl_ie_file_url', true );
		if ( ! empty( $custom_url ) ) {
			return $custom_url;
		}
		return $url;
	}
}
