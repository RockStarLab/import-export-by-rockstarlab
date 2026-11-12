import TabProgress from '../utils/tab-progress';
/**
 * Import Module
 *
 * Handles the import wizard functionality
 */

import Utils from './utils';
import FileUploader from './FileUploader';
import BackupWarningModal from './BackupWarningModal';

const ImportModule = {
	// Current wizard state
	currentStep: 1,
	totalSteps: 6,
	uploadedFile: null,
	fileData: null,
	jobId: null,
	progressInterval: null,
	batchTimeout: null,
	fileUploader: null,
	mappingFunctions: {},
	importStartTime: null,
	// Rows already processed when this page started timing (a resumed job has earlier rows).
	importStartProcessed: 0,
	isCancellingImport: false,
	isBatchProcessing: false,
	mappingTemplates: [],
	activeTemplate: null,
	pendingTemplateOptions: null,

	/**
	 * Initialize module
	 */
	areFieldTransformationsEnabled() {
		return !! window.rslIeData?.fieldTransformationsEnabled;
	},

	getFieldTransformationAction( key ) {
		return window.rslIeData?.fieldTransformationActions?.[ key ] || '';
	},

	isIntegrationActive( key ) {
		const integrations = window.rslIeData?.activeIntegrations;

		if ( ! integrations || typeof integrations[ key ] === 'undefined' ) {
			return true;
		}

		return !! integrations[ key ];
	},

	init() {
		if ( ! jQuery( '#rsl-ie-import' ).length ) {
			return;
		}

		// Check if resuming a job from Jobs Log BEFORE showing any step.
		const urlParams = new URLSearchParams( window.location.search );
		const resumeJobId = urlParams.get( 'resume_job' );

		this.bindEvents();
		this.updateReplaceLinksRemoveButtons();

		if ( resumeJobId ) {
			// Resume job – go directly to step 6 (progress) and start batch processing.
			this.jobId = parseInt( resumeJobId );
			this.importStartTime = Date.now();
			this.importStartProcessed = null;
			this.showStep( 6 );
			this.startProgressTracking();
			this.startBatchProcessing();
		} else {
			this.showStep( 1 );
		}
	},

	/**
	 * When the file was read with another delimiter than the default comma
	 * (semicolon files from Excel, tab-separated exports), show it in the
	 * Delimiter dropdown and keep it for the import, without reloading the preview.
	 *
	 * @param {Object} result Upload or preview response.
	 */
	showDetectedDelimiter( result ) {
		const detected = result && result.detected_delimiter;
		const $select = jQuery( '#csv_delimiter' );
		if ( ! detected || ! $select.length || ',' !== $select.val() ) {
			return;
		}
		const value = '\t' === detected ? 'tab' : detected;
		if (
			',' === value ||
			! $select.find( `option[value="${ value }"]` ).length
		) {
			return;
		}
		$select.val( value );
		if ( this.fileData ) {
			this.fileData.delimiter = value;
		}
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $wizard = jQuery( '#rsl-ie-import' );

		// Content type filter/search
		$wizard.on( 'input', '#rsl-ie-content-type-search', ( e ) =>
			this.filterContentTypes( e )
		);

		// Step navigation
		$wizard.on( 'click', '.rsl-ie-next-step', () => this.nextStep() );
		$wizard.on( 'click', '.rsl-ie-prev-step', () => this.prevStep() );

		// Content type selection
		$wizard.on( 'change', 'input[name="content_type"]', ( e ) =>
			this.onContentTypeChange( e )
		);

		// File upload (delegated so it keeps working after wizard resets / partial re-renders).
		$wizard.on( 'click', '#rsl-ie-fetch-url', () => this.fetchFromUrl() );
		$wizard.on( 'keydown', '#rsl-ie-file-url', ( e ) => {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				this.fetchFromUrl();
			}
		} );
		// Files on a saved FTP/SFTP server.
		$wizard.on( 'click', '#rsl-ie-remote-browse', () => {
			const path = String( jQuery( '#rsl-ie-remote-path' ).val() || '/' );
			// Browse the folder of a typed file path.
			this.browseRemote(
				/\.[a-z0-9]{2,4}$/i.test( path )
					? path.replace( /\/[^/]*$/, '' ) || '/'
					: path
			);
		} );
		$wizard.on( 'click', '.rsl-ie-remote-item', ( e ) => {
			e.preventDefault();
			const $item = jQuery( e.currentTarget );
			if ( 'dir' === $item.data( 'type' ) ) {
				this.browseRemote( String( $item.data( 'path' ) ) );
				return;
			}
			jQuery( '#rsl-ie-remote-path' ).val(
				String( $item.data( 'path' ) )
			);
			jQuery( '.rsl-ie-remote-listing' ).hide();
			this.fetchRemote();
		} );
		$wizard.on( 'change', '#rsl-ie-remote-server', () => {
			jQuery( '.rsl-ie-remote-listing' ).hide().empty();
		} );
		$wizard.on( 'click', '#rsl-ie-remote-fetch', () => this.fetchRemote() );
		$wizard.on( 'keydown', '#rsl-ie-remote-path', ( e ) => {
			if ( 'Enter' === e.key ) {
				e.preventDefault();
				this.fetchRemote();
			}
		} );
		$wizard.on( 'click', '#rsl-ie-select-file', () => {
			$wizard.find( '#rsl-ie-file-input' ).trigger( 'click' );
		} );
		$wizard.on( 'change', '#rsl-ie-file-input', ( e ) =>
			this.onFileSelect( e )
		);
		$wizard.on( 'click', '.rsl-ie-remove-file', () => this.removeFile() );

		// Drag & drop (delegated for resilience).
		$wizard.on( 'dragover', '#rsl-ie-upload-area', ( e ) => {
			e.preventDefault();
			jQuery( e.currentTarget ).addClass( 'rsl-ie-dragover' );
		} );
		$wizard.on( 'dragleave', '#rsl-ie-upload-area', ( e ) => {
			jQuery( e.currentTarget ).removeClass( 'rsl-ie-dragover' );
		} );
		$wizard.on( 'drop', '#rsl-ie-upload-area', ( e ) => {
			e.preventDefault();
			const $dropZone = jQuery( e.currentTarget );
			$dropZone.removeClass( 'rsl-ie-dragover' );
			const files = e.originalEvent.dataTransfer.files;
			if ( files.length > 0 ) {
				this.handleFile( files[ 0 ] );
			}
		} );

		// CSV delimiter options
		$wizard.on( 'change', '#csv_delimiter', ( e ) => {
			this.onDelimiterChange( e );
			// Reload preview if file is already uploaded
			if ( this.fileData && this.fileData.file_path ) {
				this.reloadFilePreview();
			}
		} );
		$wizard.on( 'input', '#csv_custom_delimiter', () => {
			this.validateCustomDelimiter();
		} );
		$wizard.on( 'blur', '#csv_custom_delimiter', () => {
			// Reload preview when custom delimiter is finalized
			if ( this.fileData && this.fileData.file_path ) {
				this.reloadFilePreview();
			}
		} );
		$wizard.on( 'change', '#csv_encoding', () => {
			// Reload preview with the chosen encoding
			if ( this.fileData && this.fileData.file_path ) {
				this.reloadFilePreview();
			}
		} );
		$wizard.on( 'change', 'input[name="csv_has_header"]', () => {
			// Reload preview when has_header changes
			if ( this.fileData && this.fileData.file_path ) {
				this.reloadFilePreview();
			}
		} );

		// Field mapping
		$wizard.on( 'click', '.rsl-ie-auto-map', ( e ) =>
			this.autoMapFields( e )
		);
		$wizard.on( 'click', '.rsl-ie-clear-map', () =>
			this.clearFieldMapping()
		);

		// Mapping templates
		$wizard.on( 'change', '#rsl-ie-template-select', () =>
			this.updateTemplateButtons()
		);
		$wizard.on( 'click', '.rsl-ie-template-apply', () =>
			this.applySelectedTemplate()
		);
		$wizard.on( 'click', '.rsl-ie-template-save', ( e ) =>
			this.saveMappingTemplate( e )
		);
		$wizard.on( 'click', '.rsl-ie-template-rename', () =>
			this.renameMappingTemplate()
		);
		$wizard.on( 'click', '.rsl-ie-template-delete', () =>
			this.deleteMappingTemplate()
		);
		$wizard.on( 'change', '#rsl-ie-custom-post-type', () => {
			if ( this.currentStep === 4 ) {
				this.loadMappingTemplates();
			}
		} );

		// Import actions
		$wizard.on( 'click', '.rsl-ie-start-import', () => this.startImport() );
		$wizard.on( 'change', '#rsl-ie-enable-replace-links', ( e ) =>
			this.toggleReplaceLinksRepeater( e )
		);
		$wizard.on( 'click', '.rsl-ie-add-replace-link', () =>
			this.addReplaceLinksRow()
		);
		$wizard.on( 'click', '.rsl-ie-remove-replace-link', ( e ) =>
			this.removeReplaceLinksRow( e )
		);
		$wizard.on( 'change', '#rsl-ie-enable-row-filter', ( e ) => {
			this.refreshRowFilterColumns();
			jQuery( '#rsl-ie-row-filter-repeater' ).toggle(
				jQuery( e.currentTarget ).is( ':checked' )
			);
		} );
		$wizard.on( 'click', '.rsl-ie-add-row-filter', () =>
			this.addRowFilterRow()
		);
		$wizard.on( 'click', '.rsl-ie-remove-row-filter', ( e ) => {
			const $rows = jQuery( '.rsl-ie-row-filter-row' );
			if ( $rows.length > 1 ) {
				jQuery( e.currentTarget )
					.closest( '.rsl-ie-row-filter-row' )
					.remove();
			} else {
				$rows.find( '.rsl-ie-row-filter-value' ).val( '' );
			}
		} );
		$wizard.on( 'change', '.rsl-ie-row-filter-operator', ( e ) => {
			const op = jQuery( e.currentTarget ).val();
			jQuery( e.currentTarget )
				.siblings( '.rsl-ie-row-filter-value' )
				.toggle( op !== 'is_empty' && op !== 'is_not_empty' );
		} );
		$wizard.on( 'click', '.rsl-ie-cancel-import', () =>
			this.cancelImport()
		);
		$wizard.on( 'click', '.rsl-ie-new-import', () => this.resetWizard() );
		$wizard.on( 'click', '.rsl-ie-toggle-logs', () => this.toggleLogs() );

		// Media import options
		$wizard.on( 'change', '#rsl-ie-auto-import-media', ( e ) => {
			this.toggleMediaDuplicateOptions( e );
		} );
	},

	/**
	 * Show specific step
	 */
	showStep( step ) {
		const $wizard = jQuery( '#rsl-ie-import' );

		// Hide all steps
		$wizard.find( '.rsl-ie-step' ).removeClass( 'active' );

		// Show current step
		$wizard.find( `.rsl-ie-step-${ step }` ).addClass( 'active' );

		// Update indicators
		$wizard
			.find( '.rsl-ie-step-indicator' )
			.removeClass( 'active completed' );
		$wizard
			.find( `.rsl-ie-step-indicator[data-step="${ step }"]` )
			.addClass( 'active' );
		$wizard
			.find( `.rsl-ie-step-indicator[data-step]` )
			.filter( function () {
				return jQuery( this ).data( 'step' ) < step;
			} )
			.addClass( 'completed' );

		this.currentStep = step;

		// Step-specific actions
		if ( step === 3 ) {
			this.loadPreview();

			// Check if there's an error from upload
			if ( this.fileData && this.fileData.hasError ) {
				// Show error message
				jQuery( '.rsl-ie-preview-table-container' ).hide();
				jQuery( '.rsl-ie-json-preview-container' ).hide();

				// Show error in preview area
				const errorHtml = `
					<div class="notice notice-error" style="padding: 20px; margin: 20px 0;">
						<h3 style="margin-top: 0;">❌ ${
							rslIeData.i18n.fileValidationFailed ||
							'File Validation Failed'
						}</h3>
						<p style="font-size: 14px;">${ Utils.escapeHtml( this.fileData.error ) }</p>
						<p style="margin-bottom: 0;">
							<button type="button" class="button rsl-ie-prev-step">
								← ${ rslIeData.i18n.goBackUploadValidFile || 'Go Back and Upload a Valid File' }
							</button>
						</p>
					</div>
				`;
				jQuery( '.rsl-ie-step-3 .rsl-ie-step-content' ).prepend(
					errorHtml
				);

				// Disable next button
				jQuery( '.rsl-ie-step-3 .rsl-ie-next-step' ).prop(
					'disabled',
					true
				);
			} else {
				// Enable next button if no error
				jQuery( '.rsl-ie-step-3 .rsl-ie-next-step' ).prop(
					'disabled',
					false
				);
			}
		} else if ( step === 4 ) {
			// Disable Next button before building mapping
			jQuery( '.rsl-ie-next-step' ).prop( 'disabled', true );
			this.buildFieldMapping();
			this.showTemplateNotice( '' );
			this.loadMappingTemplates();
		} else if ( step === 5 ) {
			this.populateUniqueFieldOptions();
			this.handleMediaImportOptions();
			this.applyPendingTemplateOptions();
		}
	},

	/**
	 * Go to next step
	 */
	nextStep() {
		if ( this.currentStep < this.totalSteps ) {
			// Show backup warning when leaving step 1 (content type selection)
			if ( this.currentStep === 1 ) {
				const mode = { media_sync: 'media-sync', ai_url: 'ai-url' }[
					jQuery( 'input[name="content_type"]:checked' ).val()
				];
				if ( mode ) {
					window.location.href =
						'admin.php?page=rsl-ie-import&import_mode=' + mode;
					return;
				}
				BackupWarningModal.show(
					() => {
						// User confirmed backup - proceed to next step
						if ( this.validateStep( this.currentStep ) ) {
							this.showStep( this.currentStep + 1 );
						}
					},
					() => {
						// User cancelled - stay on current step
						// Do nothing
					}
				);
				return;
			}

			// Validate current step
			if ( this.validateStep( this.currentStep ) ) {
				this.showStep( this.currentStep + 1 );
			}
		}
	},

	/**
	 * Go to previous step
	 */
	prevStep() {
		if ( this.currentStep > 1 ) {
			this.showStep( this.currentStep - 1 );
		}
	},

	/**
	 * Validate step
	 */
	validateStep( step ) {
		switch ( step ) {
			case 2:
				if ( ! this.uploadedFile ) {
					Utils.showNotice(
						rslIeData.i18n.pleaseUploadFile ||
							'Please upload a file',
						'error'
					);
					return false;
				}

				// Validate custom delimiter if selected
				const delimiter = jQuery( '#csv_delimiter' ).val();
				if ( delimiter === 'custom' ) {
					const customDelimiter = jQuery( '#csv_custom_delimiter' )
						.val()
						.trim();
					if ( customDelimiter === '' ) {
						Utils.showNotice(
							rslIeData.i18n.pleaseEnterCustomDelimiter ||
								'Please enter a custom delimiter',
							'error'
						);
						return false;
					}
				}
				break;
			case 4:
				// Validate post type selection for custom post types
				const contentType = jQuery(
					'input[name="content_type"]:checked'
				).val();
				if ( contentType === 'custom_post_types' ) {
					const selectedPostType = jQuery(
						'#rsl-ie-custom-post-type'
					).val();
					if ( ! selectedPostType ) {
						Utils.showNotice(
							rslIeData.i18n.pleaseSelectPostType ||
								'Please select a post type',
							'error'
						);
						return false;
					}
				}

				// Validate field mapping
				const mappedFields = this.getFieldMapping();
				if ( ! mappedFields || mappedFields.length === 0 ) {
					Utils.showNotice(
						rslIeData.i18n.mapFields ||
							'Please map at least one field',
						'error'
					);
					return false;
				}
				break;
		}
		return true;
	},

	/**
	 * Handle content type change
	 */
	onContentTypeChange( e ) {
		const contentType = jQuery( e.target ).val();
		const $indicators = jQuery( '#rsl-ie-import .rsl-ie-steps-indicator' );
		const $secondLabel = $indicators.find(
			'[data-step="2"] .rsl-ie-step-label'
		);
		if ( ! $secondLabel.data( 'original-label' ) ) {
			$secondLabel.data( 'original-label', $secondLabel.text() );
		}
		$secondLabel.text(
			[ 'media_sync', 'ai_url' ].includes( contentType )
				? $indicators.data(
						contentType === 'media_sync'
							? 'media-sync-label'
							: 'ai-url-label'
				  )
				: $secondLabel.data( 'original-label' )
		);
		$indicators
			.find( '.rsl-ie-step-indicator' )
			.slice( 2 )
			.toggle( ! [ 'media_sync', 'ai_url' ].includes( contentType ) );

		// Show/hide content-specific options
		if ( contentType === 'media' ) {
			jQuery( '.rsl-ie-post-options' ).hide();
			jQuery( '.rsl-ie-media-options' ).show();
		} else {
			jQuery( '.rsl-ie-post-options' ).show();
			jQuery( '.rsl-ie-media-options' ).hide();
		}
	},

	/**
	 * Filter content types based on search input
	 */
	filterContentTypes( e ) {
		const searchTerm = jQuery( e.target ).val().toLowerCase().trim();
		const $contentTypes = jQuery( '.rsl-ie-content-type' );
		const $filterCount = jQuery( '.rsl-ie-filter-count' );
		const $filterCountValue = jQuery( '.rsl-ie-filter-count-value' );
		const $noResults = jQuery( '.rsl-ie-no-results' );
		const $nextStepBtn = jQuery( '.rsl-ie-step-1 .rsl-ie-next-step' );
		let visibleCount = 0;

		if ( searchTerm === '' ) {
			// Show all if search is empty
			$contentTypes.show();
			$filterCount.hide();
			$noResults.hide();
			$nextStepBtn.prop( 'disabled', false );
			return;
		}

		// Filter content types
		$contentTypes.each( function () {
			const $this = jQuery( this );
			const title = $this.find( 'h3' ).text().toLowerCase();
			const description = $this.find( 'p' ).text().toLowerCase();

			// Check if search term matches title or description
			if (
				title.includes( searchTerm ) ||
				description.includes( searchTerm )
			) {
				$this.show();
				visibleCount++;
			} else {
				$this.hide();
			}
		} );

		// Update and show count
		$filterCountValue.text( visibleCount );
		$filterCount.show();

		// Show/hide no results message
		if ( visibleCount === 0 ) {
			$noResults.show();
			// Disable Next button when no results found
			$nextStepBtn.prop( 'disabled', true );
		} else {
			$noResults.hide();
			// Enable Next button when results are visible
			$nextStepBtn.prop( 'disabled', false );
		}
	},

	/**
	 * Handle delimiter dropdown change
	 */
	onDelimiterChange( e ) {
		const delimiter = jQuery( e.target ).val();

		if ( delimiter === 'custom' ) {
			jQuery( '.rsl-ie-custom-delimiter-wrapper' ).show();
			// Validate immediately
			this.validateCustomDelimiter();
		} else {
			jQuery( '.rsl-ie-custom-delimiter-wrapper' ).hide();
			// Re-enable next button if file is uploaded and processed
			if ( this.fileData && ! this.fileData.hasError ) {
				jQuery( '.rsl-ie-step-2 .rsl-ie-next-step' ).prop(
					'disabled',
					false
				);
			}
		}
	},

	/**
	 * Validate custom delimiter input
	 */
	validateCustomDelimiter() {
		const customDelimiter = jQuery( '#csv_custom_delimiter' ).val().trim();
		const delimiter = jQuery( '#csv_delimiter' ).val();

		// Only validate if delimiter is set to custom
		if ( delimiter === 'custom' ) {
			if ( customDelimiter === '' ) {
				// Disable next button if custom delimiter is empty
				jQuery( '.rsl-ie-step-2 .rsl-ie-next-step' ).prop(
					'disabled',
					true
				);
			} else {
				// Enable next button if file is uploaded and custom delimiter is provided
				if ( this.fileData && ! this.fileData.hasError ) {
					jQuery( '.rsl-ie-step-2 .rsl-ie-next-step' ).prop(
						'disabled',
						false
					);
				}
			}
		}
	},

	/**
	 * Handle file selection
	 */
	onFileSelect( e ) {
		const file = e.target.files[ 0 ];
		if ( file ) {
			this.handleFile( file );
		}
	},

	/**
	 * Handle file upload
	 */
	handleFile( file ) {
		// Validate file extension only (no size limit with chunked upload)
		const allowedExtensions = [
			'.csv',
			'.json',
			'.xml',
			'.xlsx',
			'.ods',
			'.zip',
		];
		const fileExt = '.' + file.name.split( '.' ).pop().toLowerCase();

		if ( ! allowedExtensions.includes( fileExt ) ) {
			Utils.showNotice(
				rslIeData.i18n.invalidFileTypeCsv ||
					'This type of file cannot be imported. Use a CSV, XLSX, ODS, JSON, XML or ZIP file. A tab-separated .txt file can be renamed to .csv.',
				'error'
			);
			return;
		}

		this.uploadedFile = file;

		// Show file info
		jQuery( '.rsl-ie-upload-placeholder, .rsl-ie-url-import' ).hide();
		jQuery( '.rsl-ie-file-info' ).show();
		jQuery( '.rsl-ie-file-name' ).text( file.name );
		jQuery( '.rsl-ie-file-size' ).text( Utils.formatFileSize( file.size ) );

		// Detect format
		const format = this.detectFormat( file.name );
		jQuery( '.rsl-ie-file-format' ).text( format.toUpperCase() );

		// Show format options
		if ( format === 'csv' ) {
			jQuery( '.rsl-ie-format-options' ).show();
			jQuery( '.rsl-ie-csv-options' ).show();
		} else {
			jQuery( '.rsl-ie-format-options' ).hide();
			jQuery( '.rsl-ie-csv-options' ).hide();
		}

		// Start chunked upload
		this.uploadFileInChunks( file );
	},

	/**
	 * Get actual delimiter value (convert 'tab' to \t)
	 */
	getDelimiterValue( delimiter ) {
		if ( delimiter === 'tab' ) {
			// Send as escaped sequence so server-side sanitization doesn't strip the tab.
			return '\\t';
		}
		return delimiter;
	},

	/**
	 * Upload file in chunks
	 */
	uploadFileInChunks( file ) {
		// Show upload progress
		jQuery( '.rsl-ie-upload-placeholder, .rsl-ie-url-import' ).hide();
		jQuery( '.rsl-ie-file-info' ).hide();
		jQuery( '.rsl-ie-upload-progress' ).show();

		// Collect CSV options if file is CSV
		const fileExt = '.' + file.name.split( '.' ).pop().toLowerCase();
		const csvOptions = {};

		if ( fileExt === '.csv' ) {
			const delimiter = jQuery( '#csv_delimiter' ).val();
			const actualDelimiter =
				delimiter === 'custom'
					? jQuery( '#csv_custom_delimiter' ).val().trim()
					: this.getDelimiterValue( delimiter );
			csvOptions.delimiter = actualDelimiter;
			csvOptions.has_header = jQuery( 'input[name="csv_has_header"]' ).is(
				':checked'
			);
			csvOptions.encoding = jQuery( '#csv_encoding' ).val() || 'auto';
		}

		// Create uploader instance
		this.fileUploader = new FileUploader( {
			chunkSize: 1024 * 1024, // 1MB chunks
			additionalData: csvOptions, // Pass CSV options to uploader
			onProgress: ( progress ) => {
				// Update progress bar
				jQuery(
					'.rsl-ie-upload-progress .rsl-ie-progress-bar-fill'
				).css( 'width', progress.progress + '%' );
				jQuery( '.rsl-ie-upload-percentage' ).text(
					Math.round( progress.progress ) + '%'
				);
				jQuery( '.rsl-ie-upload-speed' ).text(
					FileUploader.formatSpeed( progress.speed )
				);
			},
			onComplete: ( result ) => {
				// Check for validation errors
				if ( result.error ) {
					Utils.showNotice( result.error, 'error' );

					// Show file info but keep upload area visible
					jQuery( '.rsl-ie-upload-progress' ).hide();
					jQuery(
						'.rsl-ie-upload-placeholder, .rsl-ie-url-import'
					).show();

					// Store error in fileData to show on step 3
					this.fileData = {
						error: result.error,
						hasError: true,
					};

					// Disable next button due to validation error
					jQuery( '.rsl-ie-step-2 .rsl-ie-next-step' ).prop(
						'disabled',
						true
					);

					return;
				}

				// Upload complete
				this.fileData = result;
				// Store delimiter used so it can be passed to the import job
				this.fileData.delimiter = csvOptions.delimiter || ',';
				this.showDetectedDelimiter( result );
				this.fileData.encoding = csvOptions.encoding || 'auto';

				// Hide upload area completely, show file info
				jQuery( '.rsl-ie-upload-area' ).hide();
				jQuery( '.rsl-ie-file-info' ).show();

				// Enable next button only if custom delimiter validation passes
				const delimiter = jQuery( '#csv_delimiter' ).val();
				const shouldDisable =
					delimiter === 'custom' &&
					jQuery( '#csv_custom_delimiter' ).val().trim() === '';

				jQuery( '.rsl-ie-step-2 .rsl-ie-next-step' ).prop(
					'disabled',
					shouldDisable
				);

				// Show success message
				Utils.showNotice(
					rslIeData.i18n.fileUploadedSuccessfully ||
						'File uploaded successfully',
					'success'
				);

				// Show warning if present
				if ( result.warning ) {
					Utils.showNotice( result.warning, 'warning' );
				}

				// A known third-party export was rewritten into native columns.
				if (
					[ 'shopify', 'shopify_customers' ].includes(
						result.converted_from
					) &&
					result.conversion
				) {
					const counts = result.conversion;
					const conversionText =
						result.converted_from === 'shopify'
							? (
									rslIeData.i18n.shopifyExportConverted ||
									'Shopify product export detected: %1$s rows were combined into %2$s products with %3$s variations. Import it as WooCommerce Products and Auto Map will match every column.'
							  )
									.replace(
										'%1$s',
										parseInt( counts.source_rows, 10 ) || 0
									)
									.replace(
										'%2$s',
										parseInt( counts.products, 10 ) || 0
									)
									.replace(
										'%3$s',
										parseInt( counts.variations, 10 ) || 0
									)
							: (
									rslIeData.i18n.shopifyCustomersConverted ||
									'Shopify customer export detected: %1$s customers, %2$s without an email address. Import it as WooCommerce Customers and Auto Map will match every column.'
							  )
									.replace(
										'%1$s',
										parseInt( counts.customers, 10 ) || 0
									)
									.replace(
										'%2$s',
										parseInt( counts.no_email, 10 ) || 0
									);
					Utils.showNotice(
						Utils.escapeHtml( conversionText ),
						'info'
					);
					// Keep the explanation next to the file after the notice fades.
					jQuery( '.rsl-ie-file-conversion' )
						.text( conversionText )
						.show();
				}

				// Records found deeper in a JSON file (for example a Ghost export).
				if (
					'json_records' === result.converted_from &&
					result.conversion
				) {
					const recordsText = (
						rslIeData.i18n.jsonRecordsFound ||
						'The records in this JSON file are inside %1$s: %2$s records found. Each record becomes a row.'
					)
						.replace( '%1$s', result.records_path || '' )
						.replace(
							'%2$s',
							parseInt( result.conversion.records, 10 ) || 0
						);
					Utils.showNotice( Utils.escapeHtml( recordsText ), 'info' );
					jQuery( '.rsl-ie-file-conversion' )
						.text( recordsText )
						.show();
				}
			},
			onError: ( error ) => {
				// Upload failed
				Utils.showNotice(
					( rslIeData.i18n.uploadFailed || 'Upload failed' ) +
						': ' +
						error.message,
					'error'
				);
				this.removeFile();
			},
		} );

		// Start upload
		this.fileUploader.upload( file );
	},

	/**
	 * List a folder of the chosen FTP/SFTP server under the path field.
	 *
	 * @param {string} path Folder on the server.
	 */
	async browseRemote( path ) {
		const clientData = window.rslIeData || {};
		const i18n = clientData.i18n || {};
		const $list = jQuery( '.rsl-ie-remote-listing' );
		$list
			.show()
			.html(
				'<p class="description">' +
					Utils.escapeHtml( i18n.remoteLoading || 'Connecting...' ) +
					'</p>'
			);
		const body = new FormData();
		body.append( 'action', 'rsl_ie_remote_browse' );
		body.append(
			'nonce',
			( clientData.nonces || {} ).rsl_ie_remote_browse || ''
		);
		body.append(
			'server_id',
			String( jQuery( '#rsl-ie-remote-server' ).val() || '' )
		);
		body.append( 'path', path || '/' );
		try {
			const response = await fetch(
				clientData.ajaxUrl || window.ajaxurl || '',
				{ method: 'POST', body, credentials: 'same-origin' }
			);
			const data = await response.json();
			if ( ! data.success ) {
				// Controller errors carry the reason in data.message.
				throw new Error(
					data.message ||
						( data.data && data.data.message ) ||
						( 'string' === typeof data.data ? data.data : '' ) ||
						'Error'
				);
			}
			const result = data.data;
			const rows = [];
			if ( '/' !== result.path ) {
				const up = result.path.replace( /\/[^/]*$/, '' ) || '/';
				rows.push(
					'<li><a href="#" class="rsl-ie-remote-item" data-type="dir" data-path="' +
						Utils.escapeHtml( up ) +
						'"><span class="dashicons dashicons-arrow-up-alt2"></span> ..</a></li>'
				);
			}
			( result.items || [] ).forEach( ( item ) => {
				const isDir = 'dir' === item.type;
				rows.push(
					'<li><a href="#" class="rsl-ie-remote-item" data-type="' +
						( isDir ? 'dir' : 'file' ) +
						'" data-path="' +
						Utils.escapeHtml( item.path ) +
						'"><span class="dashicons ' +
						( isDir
							? 'dashicons-category'
							: 'dashicons-media-default' ) +
						'"></span> ' +
						Utils.escapeHtml( item.name ) +
						'</a>' +
						( isDir
							? ''
							: ' <span class="description">' +
							  Utils.escapeHtml(
									Utils.formatFileSize(
										parseInt( item.size, 10 ) || 0
									)
							  ) +
							  '</span>' ) +
						'</li>'
				);
			} );
			$list.html(
				'<p class="rsl-ie-remote-folder"><code>' +
					Utils.escapeHtml( result.path ) +
					'</code></p>' +
					( rows.length
						? '<ul>' + rows.join( '' ) + '</ul>'
						: '<p class="description">' +
						  Utils.escapeHtml(
								i18n.remoteEmpty || 'This folder is empty.'
						  ) +
						  '</p>' )
			);
		} catch ( error ) {
			$list.html(
				'<p class="rsl-ie-remote-error">' +
					Utils.escapeHtml( error.message ) +
					'</p>'
			);
		}
	},

	/**
	 * Download the import file from the chosen FTP/SFTP server.
	 */
	fetchRemote() {
		const server = String( jQuery( '#rsl-ie-remote-server' ).val() || '' );
		const path = String(
			jQuery( '#rsl-ie-remote-path' ).val() || ''
		).trim();
		if ( ! server || ! path || /\/$/.test( path ) ) {
			Utils.showNotice(
				( window.rslIeData.i18n || {} ).remotePickFile ||
					'Enter the path of the file on the server, or click Browse.',
				'error'
			);
			return;
		}
		this.fetchFromUrl(
			'rsl-remote://' + server + '/' + path.replace( /^\/+/, '' ),
			jQuery( '#rsl-ie-remote-server option:selected' ).text() +
				': ' +
				( path.charAt( 0 ) === '/' ? path : '/' + path )
		);
	},

	/**
	 * Download the import file from a URL on the server.
	 *
	 * @param {string} address Optional address (saved server file) instead of the URL field.
	 * @param {string} label   Readable source for the file info line.
	 */
	async fetchFromUrl( address = '', label = '' ) {
		const url =
			'string' === typeof address && address
				? address
				: String( jQuery( '#rsl-ie-file-url' ).val() || '' ).trim();
		const shown = label || url;
		if ( ! /^(https?|rsl-remote):\/\//i.test( url ) ) {
			Utils.showNotice(
				rslIeData.i18n.fileUrlInvalid ||
					'Enter a full http:// or https:// address of the file.',
				'error'
			);
			return;
		}
		const clientData = window.rslIeData || {};
		const $button = jQuery( '#rsl-ie-fetch-url, #rsl-ie-remote-fetch' );
		$button.prop( 'disabled', true );
		jQuery( '.rsl-ie-upload-placeholder, .rsl-ie-url-import' ).hide();
		jQuery( '.rsl-ie-upload-progress' ).show();
		jQuery( '.rsl-ie-upload-status-text' ).text(
			rslIeData.i18n.fileUrlDownloading || 'Downloading the file...'
		);
		const body = new FormData();
		body.append( 'action', 'rsl_ie_upload_from_url' );
		body.append( 'nonce', clientData.nonce || '' );
		body.append( 'url', url );
		try {
			const response = await fetch(
				clientData.ajaxUrl || window.ajaxurl || '',
				{ method: 'POST', body, credentials: 'same-origin' }
			);
			const data = await response.json();
			if ( ! data.success ) {
				throw new Error(
					'string' === typeof data.data
						? data.data
						: 'Download failed'
				);
			}
			const result = data.data;
			// The file is on the server already; keep what step validation needs.
			this.uploadedFile = {
				name: result.file_name || shown,
				size:
					parseInt( result.file_size || result.expected_bytes, 10 ) ||
					0,
				sourceUrl: url,
			};
			this.fileData = result;
			this.fileData.delimiter = ',';
			this.fileData.encoding = 'auto';
			this.showDetectedDelimiter( result );
			const format = String( result.format || '' ).toLowerCase();
			jQuery( '.rsl-ie-file-name' ).text( result.file_name || shown );
			jQuery( '.rsl-ie-file-size' ).text(
				Utils.formatFileSize(
					parseInt( result.file_size || result.expected_bytes, 10 ) ||
						0
				)
			);
			jQuery( '.rsl-ie-file-format' ).text( format.toUpperCase() );
			jQuery( '.rsl-ie-file-conversion' )
				.text(
					(
						rslIeData.i18n.fileUrlSource || 'Downloaded from %s'
					).replace( '%s', shown )
				)
				.show();
			jQuery( '.rsl-ie-upload-progress' ).hide();
			jQuery( '.rsl-ie-upload-area' ).hide();
			jQuery( '.rsl-ie-file-info' ).show();
			jQuery( '.rsl-ie-step-2 .rsl-ie-next-step' ).prop(
				'disabled',
				false
			);
			Utils.showNotice(
				rslIeData.i18n.fileUrlDownloaded || 'File downloaded',
				'success'
			);
		} catch ( error ) {
			jQuery( '.rsl-ie-upload-progress' ).hide();
			jQuery( '.rsl-ie-upload-placeholder, .rsl-ie-url-import' ).show();
			Utils.showNotice( Utils.escapeHtml( error.message ), 'error' );
		} finally {
			$button.prop( 'disabled', false );
			jQuery( '.rsl-ie-upload-status-text' ).text(
				rslIeData.i18n.uploading || 'Uploading...'
			);
		}
	},

	/**
	 * Remove uploaded file
	 */
	removeFile() {
		// Abort upload if in progress
		if ( this.fileUploader ) {
			this.fileUploader.abort();
			this.fileUploader = null;
		}

		this.uploadedFile = null;
		this.fileData = null;

		jQuery( '.rsl-ie-file-info' ).hide();
		jQuery( '.rsl-ie-file-conversion' ).hide().text( '' );
		jQuery( '.rsl-ie-upload-area' ).show();
		jQuery( '.rsl-ie-upload-placeholder, .rsl-ie-url-import' ).show();
		jQuery( '.rsl-ie-upload-progress' ).hide();
		jQuery( '.rsl-ie-format-options' ).hide();
		jQuery( '#rsl-ie-file-input' ).val( '' );
		jQuery( '.rsl-ie-step-2 .rsl-ie-next-step' ).prop( 'disabled', true );
	},

	/**
	 * Reload file preview with updated CSV options
	 */
	async reloadFilePreview() {
		if ( ! this.fileData || ! this.fileData.file_path ) {
			return;
		}

		// Only reload for CSV files
		if ( this.fileData.format !== 'csv' ) {
			return;
		}

		// Collect current CSV options
		const delimiter = jQuery( '#csv_delimiter' ).val();
		const actualDelimiter =
			delimiter === 'custom'
				? jQuery( '#csv_custom_delimiter' ).val().trim()
				: this.getDelimiterValue( delimiter );
		const csvOptions = {
			delimiter: actualDelimiter,
			has_header: jQuery( 'input[name="csv_has_header"]' ).is(
				':checked'
			),
			encoding: jQuery( '#csv_encoding' ).val() || 'auto',
		};

		try {
			// Send request to regenerate preview with new options
			const response = await jQuery.ajax( {
				url: rslIeData.ajaxUrl,
				method: 'POST',
				data: {
					action: 'rsl_ie_reload_preview',
					nonce: rslIeData.nonce,
					file_path: this.fileData.file_path,
					delimiter: csvOptions.delimiter,
					has_header: csvOptions.has_header,
					encoding: csvOptions.encoding,
				},
			} );

			if ( response.success ) {
				// Update stored file data with new preview AND delimiter
				this.fileData.preview = response.data.preview;
				this.fileData.columns = response.data.columns;
				this.fileData.total_rows = response.data.total_rows;
				this.fileData.delimiter = csvOptions.delimiter;
				this.fileData.encoding = csvOptions.encoding;
			}
		} catch ( error ) {}
	},

	/**
	 * Detect file format from filename
	 */
	detectFormat( filename ) {
		const extension = filename.split( '.' ).pop().toLowerCase();
		const supported = [ 'csv', 'json', 'xml', 'xlsx', 'ods', 'zip' ];

		return supported.includes( extension ) ? extension : 'csv';
	},

	/**
	 * Load data preview
	 */
	async loadPreview() {
		if ( ! this.fileData ) {
			Utils.showNotice(
				rslIeData.i18n.noFileDataAvailable || 'No file data available',
				'error'
			);
			return;
		}

		// Check if there's an error
		if ( this.fileData.hasError ) {
			return; // Error display is handled in showStep
		}

		if ( ! this.fileData.preview ) {
			Utils.showNotice(
				rslIeData.i18n.noPreviewDataAvailable ||
					'No preview data available',
				'error'
			);
			return;
		}

		const preview = this.fileData.preview;
		const format = this.fileData.format || 'csv';

		// Update stats
		jQuery( '.rsl-ie-total-rows' ).text( this.fileData.total_rows || 0 );
		jQuery( '.rsl-ie-total-columns' ).text(
			this.fileData.columns?.length || 0
		);

		// Always show table preview (both CSV and JSON)
		this.showTablePreview( preview );
	},

	/**
	 * Show JSON preview (expanded first object)
	 */
	showJsonPreview( firstObject ) {
		// Hide table, show JSON preview
		jQuery( '.rsl-ie-preview-table-container' ).hide();
		jQuery( '.rsl-ie-json-preview-container' ).show();
		jQuery( '.rsl-ie-preview-note' ).text(
			'Showing first object from JSON file'
		);

		// Build JSON preview HTML
		let html = '<div class="rsl-ie-json-object">';
		html += '<table class="wp-list-table widefat striped">';
		html += '<thead><tr>';
		html += '<th style="width: 30%;">Field</th>';
		html += '<th style="width: 20%;">Type</th>';
		html += '<th style="width: 50%;">Value</th>';
		html += '</tr></thead><tbody>';

		// Iterate through all fields
		for ( const [ key, value ] of Object.entries( firstObject ) ) {
			const type = this.getValueType( value );
			const displayValue = this.formatJsonValue( value );

			html += '<tr>';
			html += `<td><strong>${ Utils.escapeHtml( key ) }</strong></td>`;
			html += `<td><code>${ type }</code></td>`;
			html += `<td>${ displayValue }</td>`;
			html += '</tr>';
		}

		html += '</tbody></table></div>';

		jQuery( '.rsl-ie-json-preview' ).html( html );
	},

	/**
	 * Show table preview for CSV
	 */
	showTablePreview( preview ) {
		// Show table, hide JSON preview
		jQuery( '.rsl-ie-preview-table-container' ).show();
		jQuery( '.rsl-ie-json-preview-container' ).hide();
		jQuery( '.rsl-ie-preview-note' ).text(
			window.rslIeData.i18n.showingFirstRows
		);

		const $table = jQuery( '.rsl-ie-preview-table' ); // Build table header
		let headerHtml = '<tr>';
		if ( preview.headers ) {
			preview.headers.forEach( ( header ) => {
				headerHtml += `<th>${ Utils.escapeHtml( header ) }</th>`;
			} );
		}
		headerHtml += '</tr>';
		$table.find( 'thead' ).html( headerHtml );

		// Build table body
		let bodyHtml = '';
		if ( preview.data ) {
			preview.data.forEach( ( row, index ) => {
				bodyHtml += '<tr>';
				row.forEach( ( cell ) => {
					// Limit cell content length for preview
					let cellContent = String( cell );

					// If it looks like JSON, format it nicely
					if (
						cellContent.startsWith( '{' ) ||
						cellContent.startsWith( '[' )
					) {
						cellContent = cellContent.substring( 0, 150 );
						if ( String( cell ).length > 150 ) {
							cellContent += '...';
						}
					} else {
						cellContent = cellContent.substring( 0, 100 );
						if ( String( cell ).length > 100 ) {
							cellContent += '...';
						}
					}

					bodyHtml += `<td>${ Utils.escapeHtml( cellContent ) }</td>`;
				} );
				bodyHtml += '</tr>';
			} );
		}
		$table.find( 'tbody' ).html( bodyHtml );

		// Check if table has horizontal scroll
		this.checkTableScroll();
	},

	/**
	 * Check if table container has horizontal scroll and add indicator
	 */
	checkTableScroll() {
		const $container = jQuery( '.rsl-ie-preview-table-container' );

		if ( $container.length ) {
			// Use setTimeout to ensure DOM is fully rendered
			setTimeout( () => {
				const container = $container[ 0 ];
				const hasScroll = container.scrollWidth > container.clientWidth;

				$container.toggleClass( 'has-scroll', hasScroll );

				// Add scroll event listener to hide shadow when scrolled to end
				$container
					.off( 'scroll.preview' )
					.on( 'scroll.preview', function () {
						const scrollLeft = jQuery( this ).scrollLeft();
						const scrollWidth = this.scrollWidth;
						const clientWidth = this.clientWidth;
						const isAtEnd =
							scrollLeft + clientWidth >= scrollWidth - 5;

						jQuery( this ).toggleClass(
							'scrolled-to-end',
							isAtEnd
						);
					} );
			}, 100 );
		}
	},

	/**
	 * Get value type for display
	 */
	getValueType( value ) {
		if ( value === null ) return 'null';
		if ( Array.isArray( value ) ) return 'array';
		if ( typeof value === 'object' ) return 'object';
		if ( typeof value === 'number' ) return 'number';
		if ( typeof value === 'boolean' ) return 'boolean';
		return 'string';
	},

	/**
	 * Format JSON value for display
	 */
	formatJsonValue( value ) {
		if ( value === null ) {
			return '<em style="color: #999;">null</em>';
		}

		if ( Array.isArray( value ) ) {
			if ( value.length === 0 ) {
				return '<code>[]</code>';
			}
			const preview = value
				.slice( 0, 3 )
				.map( ( v ) => JSON.stringify( v ) )
				.join( ', ' );
			const more = value.length > 3 ? ` ... +${ value.length - 3 }` : '';
			return `<code>[ ${ Utils.escapeHtml( preview ) }${ more } ]</code>`;
		}

		if ( typeof value === 'object' ) {
			const keys = Object.keys( value );
			if ( keys.length === 0 ) {
				return '<code>{}</code>';
			}
			const preview = keys.slice( 0, 2 ).join( ', ' );
			const more = keys.length > 2 ? ` ... +${ keys.length - 2 }` : '';
			return `<code>{ ${ Utils.escapeHtml( preview ) }${ more } }</code>`;
		}

		if ( typeof value === 'boolean' ) {
			return `<code style="color: #0073aa;">${ value }</code>`;
		}

		if ( typeof value === 'number' ) {
			return `<code style="color: #d63638;">${ value }</code>`;
		}

		// String
		const strValue = String( value );
		const displayValue =
			strValue.length > 200
				? strValue.substring( 0, 200 ) + '...'
				: strValue;
		return Utils.escapeHtml( displayValue );
	},

	/**
	 * Build field mapping interface (Drag & Drop)
	 */
	buildFieldMapping() {
		if ( ! this.fileData || ! this.fileData.columns ) {
			return;
		}

		const contentType = jQuery(
			'input[name="content_type"]:checked'
		).val();

		// Show/hide post type selector for custom post types
		this.togglePostTypeSelector( contentType );

		// Show/hide database table selector
		this.toggleDatabaseTableSelector( contentType );

		// Build source fields (from file)
		this.buildSourceFields();

		// Build target fields (WordPress fields or database table columns)
		if ( contentType === 'database_table' ) {
			// Table columns will be loaded after table selection
			jQuery( '#rsl-ie-target-fields' ).html(
				`<div class="rsl-ie-info">${ window.rslIeData.i18n.pleaseSelectTable }</div>`
			);
		} else {
			this.buildTargetFields( contentType );
		} // Load dynamic ACF fields
		this.loadACFFields( contentType );

		// Load dynamic Yoast fields
		this.loadYoastFields( contentType );

		// Load dynamic Rank Math fields
		this.loadRankMathFields( contentType );

		// Load dynamic Elementor fields
		this.loadElementorFields( contentType );

		// Initialize drag & drop
		this.initializeDragDrop();

		// Initialize search
		this.initializeFieldSearch();

		// Update stats
		this.updateMappingStats();
	},

	/**
	 * Build source fields from uploaded file
	 */
	buildSourceFields() {
		const $container = jQuery( '#rsl-ie-source-fields' );
		let html = '';

		this.fileData.columns.forEach( ( column, index ) => {
			const sampleData =
				this.fileData.preview?.data?.[ 0 ]?.[ index ] || '';
			const sampleDisplay = String( sampleData ).substring( 0, 30 );

			html += `
				<div class="rsl-ie-field-card" draggable="true" data-source-field="${ Utils.escapeHtml(
					column
				) }" data-source-index="${ index }">
					<div class="rsl-ie-field-icon">
						<span class="dashicons dashicons-media-spreadsheet"></span>
					</div>
					<div class="rsl-ie-field-info">
						<div class="rsl-ie-field-name">${ Utils.escapeHtml( column ) }</div>
						${
							sampleDisplay
								? `<div class="rsl-ie-field-sample">${ Utils.escapeHtml(
										sampleDisplay
								  ) }...</div>`
								: ''
						}
					</div>
				</div>
			`;
		} );

		$container.html( html );
	},

	/**
	 * Toggle post type selector visibility
	 */
	togglePostTypeSelector( contentType ) {
		const $selector = jQuery( '.rsl-ie-post-type-selector' );

		if ( contentType === 'custom_post_types' ) {
			$selector.css( 'display', 'block' );
			this.loadCustomPostTypes();
		} else {
			$selector.css( 'display', 'none' );
		}
	},

	/**
	 * Load custom post types via AJAX
	 */
	loadCustomPostTypes() {
		const $select = jQuery( '#rsl-ie-custom-post-type' );

		// If already loaded, skip
		if ( $select.find( 'option' ).length > 1 ) {
			return;
		}

		jQuery.ajax( {
			url: window.rslIeData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'rsl_ie_get_custom_post_types',
				nonce: window.rslIeData.nonce,
			},
			success: ( response ) => {
				if ( response.success && response.data ) {
					let options =
						'<option value="">-- ' +
						( rslIeData.i18n.selectPostType ||
							'Select Post Type' ) +
						' --</option>';

					response.data.forEach( ( postType ) => {
						options += `<option value="${ postType.name }">${ postType.label }</option>`;
					} );

					$select.html( options );
					$select
						.off(
							'change.rslIeYoast change.rslIeRankMath change.rslIeElementor'
						)
						.on(
							'change.rslIeYoast change.rslIeRankMath change.rslIeElementor',
							() => {
								jQuery( '.rsl-ie-yoast-fields-group' ).remove();
								jQuery(
									'.rsl-ie-rank-math-fields-group'
								).remove();
								jQuery(
									'.rsl-ie-elementor-fields-group'
								).remove();
								jQuery( '.rsl-ie-acf-fields-group' ).remove();
								this.loadACFFields( 'custom_post_types' );
								this.loadYoastFields( 'custom_post_types' );
								this.loadRankMathFields( 'custom_post_types' );
								this.loadElementorFields( 'custom_post_types' );
							}
						);
				}
			},
			error: ( xhr, status, error ) => {},
		} );
	},

	/**
	 * Toggle database table selector on Step 4
	 */
	toggleDatabaseTableSelector( contentType ) {
		const $selector = jQuery( '.rsl-ie-table-selection-section' );

		if ( contentType === 'database_table' ) {
			$selector.show();
			this.loadDatabaseTables();
			this.initializeCreateTableMode();
		} else {
			$selector.hide();
		}
	},

	/**
	 * Initialize create-table controls for database table imports.
	 */
	initializeCreateTableMode() {
		const $toggle = jQuery( '#rsl-ie-create-table-if-missing' );
		const $newTableWrap = jQuery( '.rsl-ie-create-table-name-wrap' );
		const $newTableName = jQuery( '#rsl-ie-new-table-name' );
		const $select = jQuery( '#rsl-ie-import-table-name' );

		$toggle
			.off( 'change.rslIeCreateTable' )
			.on( 'change.rslIeCreateTable', () => {
				const createMode = $toggle.is( ':checked' );
				$newTableWrap.toggle( createMode );
				$select.prop( 'disabled', createMode );
				jQuery( '.rsl-ie-table-info' ).toggle(
					! createMode && !! this.selectedTableName
				);

				if ( createMode ) {
					this.selectedTableName = '';
					$select.val( '' );
					this.buildNewTableTargetFields();
				} else {
					if ( $select.val() ) {
						this.selectedTableName = $select.val();
						this.loadTableInfo( this.selectedTableName );
						this.loadTableColumnsForMapping();
					} else {
						jQuery( '#rsl-ie-target-fields' ).html(
							'<div class="rsl-ie-info">' +
								( window.rslIeData.i18n.pleaseSelectTable ||
									'Please select a database table above to see available columns' ) +
								'</div>'
						);
					}
				}
			} );

		$newTableName
			.off( 'input.rslIeCreateTable' )
			.on( 'input.rslIeCreateTable', () => {
				if ( $toggle.is( ':checked' ) ) {
					this.buildNewTableTargetFields();
				}
			} );

		if ( $toggle.is( ':checked' ) ) {
			$newTableWrap.show();
			$select.prop( 'disabled', true );
			this.buildNewTableTargetFields();
		}
	},

	/**
	 * Load database tables on Step 4
	 */
	loadDatabaseTables() {
		const $select = jQuery( '#rsl-ie-import-table-name' );
		const $spinner = jQuery( '.rsl-ie-table-selector .spinner' );
		const $section = jQuery( '.rsl-ie-table-selection-section' );

		// If already loaded, skip
		if ( $select.find( 'option' ).length > 1 ) {
			return;
		}

		// Show section
		$section.show();

		// Show loading
		$select.prop( 'disabled', true );
		$spinner.addClass( 'is-active' );

		jQuery.ajax( {
			url: window.rslIeData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'rsl_ie_get_database_tables',
				nonce: window.rslIeData.nonce,
			},
			success: ( response ) => {
				$spinner.removeClass( 'is-active' );

				if ( response.success && response.data ) {
					const tables = response.data.tables || response.data || [];

					$select.empty();
					$select.append(
						jQuery( '<option>' )
							.val( '' )
							.text( window.rslIeData.i18n.selectTable )
					);

					if ( ! Array.isArray( tables ) || tables.length === 0 ) {
						$select.append(
							jQuery( '<option>' )
								.val( '' )
								.text( window.rslIeData.i18n.noTablesFound )
						);
						$select.prop( 'disabled', true );
						return;
					}
					tables.forEach( ( table ) => {
						$select.append(
							jQuery( '<option>' )
								.val( table.table_name )
								.text( table.label )
						);
					} );

					$select.prop(
						'disabled',
						jQuery( '#rsl-ie-create-table-if-missing' ).is(
							':checked'
						)
					);

					// Handle table selection
					$select.off( 'change' ).on( 'change', () => {
						if (
							jQuery( '#rsl-ie-create-table-if-missing' ).is(
								':checked'
							)
						) {
							return;
						}

						const tableName = $select.val();
						if ( tableName ) {
							this.selectedTableName = tableName;
							this.loadTableInfo( tableName );
							this.loadTableColumnsForMapping();
						} else {
							this.selectedTableName = '';
							jQuery( '.rsl-ie-table-info' ).html( '' ).hide();
							jQuery( '#rsl-ie-target-fields' ).html(
								'<div class="rsl-ie-info">' +
									( window.rslIeData.i18n.pleaseSelectTable ||
										'Please select a database table above to see available columns' ) +
									'</div>'
							);
						}
					} );
				} else {
					$select.html(
						`<option value="">${ window.rslIeData.i18n.noTablesFound }</option>`
					);
				}
			},
			error: ( xhr, status, error ) => {
				$spinner.removeClass( 'is-active' );
				$select.html(
					`<option value="">${ window.rslIeData.i18n.errorLoadingTables }</option>`
				);
			},
		} );
	},

	/**
	 * Build target fields from uploaded file columns for a table that will be created.
	 */
	buildNewTableTargetFields() {
		const $container = jQuery( '#rsl-ie-target-fields' );

		if ( ! this.fileData || ! Array.isArray( this.fileData.columns ) ) {
			$container.html(
				'<div class="rsl-ie-info">Upload a file first to create table columns from its headers.</div>'
			);
			return;
		}

		let html = '<div class="rsl-ie-field-group">';
		html +=
			'<div class="rsl-ie-field-group-label">' +
			( window.rslIeData.i18n.fieldGroupTableColumns ||
				'Table Columns' ) +
			'</div>';
		html +=
			'<div class="rsl-ie-info rsl-ie-create-table-info">New table columns will be created from the mapped file columns below. All columns are created as LONGTEXT for safe import of text, HTML, JSON, and serialized values.</div>';

		this.fileData.columns.forEach( ( column ) => {
			const columnName = this.normalizeDatabaseIdentifier( column );
			if ( ! columnName ) {
				return;
			}

			html += `
				<div class="rsl-ie-target-field" data-target-field="${ Utils.escapeHtml(
					columnName
				) }" data-field-type="longtext">
					<div class="rsl-ie-field-icon">
						<span class="dashicons dashicons-database-add"></span>
					</div>
					<div class="rsl-ie-field-info">
						<div class="rsl-ie-field-label">${ Utils.escapeHtml( columnName ) }</div>
						<span class="rsl-ie-field-type-badge">LONGTEXT</span>
					</div>
				</div>
			`;
		} );

		html += '</div>';
		$container.html( html );
		this.initializeDragDrop();
	},

	/**
	 * Normalize file headers to safe MySQL identifiers.
	 *
	 * @param {string} value Raw value.
	 * @return {string} Safe identifier.
	 */
	normalizeDatabaseIdentifier( value ) {
		let identifier = String( value || '' )
			.trim()
			.replace( /[^A-Za-z0-9_]/g, '_' )
			.replace( /_+/g, '_' )
			.replace( /^_+|_+$/g, '' );

		if ( identifier && ! /^[A-Za-z_]/.test( identifier ) ) {
			identifier = `column_${ identifier }`;
		}

		return identifier.substring( 0, 64 );
	},

	/**
	 * Load table info on Step 2
	 */
	loadTableInfo( tableName ) {
		const $tableInfo = jQuery( '.rsl-ie-table-info' );
		const $columnsList = jQuery( '.rsl-ie-columns-list' );
		const $rowCount = jQuery( '.rsl-ie-table-row-count' );
		const $columnCount = jQuery( '.rsl-ie-table-column-count' );

		$tableInfo.show();
		$columnsList.html( '<p>Loading...</p>' );

		jQuery.ajax( {
			url: window.rslIeData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'rsl_ie_get_table_columns',
				nonce: window.rslIeData.nonce,
				table_name: tableName,
			},
			success: ( response ) => {
				if ( response.success && response.data ) {
					const columns = response.data.columns || [];
					const rowCount = response.data.row_count || 0;

					$rowCount.text( rowCount.toLocaleString() );
					$columnCount.text( columns.length );

					let html = '<div class="rsl-ie-column-badges">';
					columns.forEach( ( column ) => {
						html += `<span class="rsl-ie-column-badge">${ column.name }</span>`;
					} );
					html += '</div>';

					$columnsList.html( html );
				}
			},
			error: ( xhr, status, error ) => {
				$columnsList.html(
					`<p>${ window.rslIeData.i18n.errorLoadingColumns }</p>`
				);
			},
		} );
	},

	/**
	 * Load table columns for Step 4 (field mapping)
	 */
	loadTableColumnsForMapping() {
		if ( ! this.selectedTableName ) {
			return;
		}

		const $container = jQuery( '#rsl-ie-target-fields' );

		$container.html(
			`<div class="rsl-ie-loading">${ window.rslIeData.i18n.loadingTableColumns }</div>`
		);

		jQuery.ajax( {
			url: window.rslIeData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'rsl_ie_get_table_columns',
				nonce: window.rslIeData.nonce,
				table_name: this.selectedTableName,
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data &&
					response.data.columns
				) {
					const columns = response.data.columns;

					let html = '<div class="rsl-ie-field-group">';
					html +=
						'<div class="rsl-ie-field-group-label">' +
						( window.rslIeData.i18n.fieldGroupTableColumns ||
							'Table Columns' ) +
						'</div>';

					columns.forEach( ( column ) => {
						html += `
							<div class="rsl-ie-target-field" data-target-field="${
								column.name
							}" data-field-type="${ column.type || 'string' }">
								<div class="rsl-ie-field-icon">
									<span class="dashicons dashicons-database"></span>
								</div>
								<div class="rsl-ie-field-info">
									<div class="rsl-ie-field-label">${ column.name }</div>
									<span class="rsl-ie-field-type-badge">${ column.type || 'string' }</span>
								</div>
							</div>
						`;
					} );

					html += '</div>';
					$container.html( html );

					// Re-initialize drag & drop
					this.initializeDragDrop();
				} else {
					$container.html(
						`<div class="rsl-ie-error">${ window.rslIeData.i18n.errorLoadingColumns }</div>`
					);
				}
			},
			error: ( xhr, status, error ) => {
				$container.html(
					`<div class="rsl-ie-error">${ window.rslIeData.i18n.errorLoadingColumns }</div>`
				);
			},
		} );
	},

	/**
	 * Build target WordPress fields
	 */
	buildTargetFields( contentType ) {
		const $container = jQuery( '#rsl-ie-target-fields' );

		// Get fields for content type
		const fieldGroups = this.getFieldsByContentType( contentType );

		let html = '';

		fieldGroups.forEach( ( group ) => {
			html += `<div class="rsl-ie-field-group">`;
			html += `<div class="rsl-ie-field-group-label">${ group.label }</div>`;

			group.options.forEach( ( field ) => {
				// Skip special fields (except template field)
				if (
					field.value.startsWith( '_' ) &&
					field.value !== '_wp_page_template'
				) {
					return;
				}

				// Custom fields with add button
				if ( field.custom ) {
					html += `
					<div class="rsl-ie-target-field rsl-ie-custom-field-template" data-field-type="${
						field.type || 'string'
					}" data-multiple="${ field.multiple || false }">
						<div class="rsl-ie-field-icon">
							<span class="dashicons dashicons-plus"></span>
						</div>
						<div class="rsl-ie-field-info">
							<div class="rsl-ie-field-label">${ field.label }</div>
							<button type="button" class="rsl-ie-add-custom-field button button-small">+ ${
								window.rslIeData.i18n.add || 'Add'
							}</button>
						</div>
					</div>
				`;
				} else {
					const iconClass = this.getTargetFieldIcon(
						field.type,
						field.value
					);
					html += `
					<div class="rsl-ie-target-field" data-target-field="${
						field.value
					}" data-field-type="${
						field.type || 'string'
					}" data-multiple="${ field.multiple || false }">
						<div class="rsl-ie-field-icon">
							<span class="dashicons ${ iconClass }"></span>
						</div>
						<div class="rsl-ie-field-info">
							<div class="rsl-ie-field-label">${ field.label }</div>
							<span class="rsl-ie-field-type-badge">${ field.type || 'string' }</span>
						</div>
					</div>
				`;
				}
			} );

			html += `</div>`;
		} );

		$container.html( html );

		// Initialize custom field add buttons
		this.initCustomFieldButtons();
	},

	getTargetFieldIcon( type, fieldValue = '' ) {
		return 'dashicons-wordpress';
	},

	/**
	 * Initialize custom field add buttons
	 */
	initCustomFieldButtons() {
		const self = this;

		jQuery( '.rsl-ie-add-custom-field' )
			.off( 'click' )
			.on( 'click', function () {
				const $button = jQuery( this );
				const $template = $button.closest(
					'.rsl-ie-custom-field-template'
				);
				const fieldType = $template.data( 'field-type' );
				const isMultiple = $template.data( 'multiple' );

				// Show modal to add custom field
				self.showCustomFieldModal( $template, fieldType, isMultiple );
			} );
	},

	/**
	 * Show modal to add custom taxonomy or meta field
	 */
	showCustomFieldModal( $template, fieldType, isMultiple ) {
		const self = this;
		const isTaxonomy = fieldType === 'taxonomy';
		const isMeta = fieldType === 'meta';

		const title = isTaxonomy
			? window.rslIeData.i18n.addTaxonomyField || 'Add Taxonomy Field'
			: window.rslIeData.i18n.addCustomField || 'Add Custom Field';
		const placeholder = isTaxonomy
			? window.rslIeData.i18n.enterTaxonomySlug ||
			  'Enter taxonomy slug (e.g., category, post_tag, product_cat)'
			: window.rslIeData.i18n.enterFieldKey ||
			  'Enter field key (e.g., _custom_price)';
		const icon = isTaxonomy
			? 'dashicons-category'
			: 'dashicons-admin-plugins';

		// Taxonomy format options
		const taxonomyFormatField = isTaxonomy
			? `
			<label style="margin-top: 15px;">
				<strong>${ window.rslIeData.i18n.dataFormat || 'Data Format' }:</strong>
				<select class="rsl-ie-taxonomy-format regular-text">
					<option value="id">${
						window.rslIeData.i18n.termIdFormat ||
						'Term ID (e.g., 5, 12, 23)'
					}</option>
					<option value="slug">${
						window.rslIeData.i18n.termSlugFormat ||
						'Term Slug (e.g., technology, news)'
					}</option>
					<option value="name" selected>${
						window.rslIeData.i18n.termNameFormat ||
						'Term Name (e.g., Technology, News)'
					}</option>
				</select>
				<p class="description" style="margin-top: 5px;">
					${
						window.rslIeData.i18n.selectTaxonomyDataFormat ||
						'Select the format of taxonomy data in your CSV file.'
					}
				</p>
			</label>
		`
			: '';

		// Create modal HTML (same structure as function modal)
		const modalHtml = `
			<div id="rsl-ie-custom-field-modal" class="rsl-ie-modal" style="display:flex;">
				<div class="rsl-ie-modal-backdrop"></div>
				<div class="rsl-ie-modal-content rsl-ie-custom-field-modal-content">
					<div class="rsl-ie-modal-header">
						<h2 class="rsl-ie-modal-title">
							<span class="dashicons ${ icon }"></span>
							${ title }
						</h2>
						<button type="button" class="rsl-ie-modal-close">
							<span class="dashicons dashicons-no-alt"></span>
						</button>				</div>
				<div class="rsl-ie-modal-body">
					<label>
						<strong>${
							isTaxonomy
								? window.rslIeData.i18n.taxonomySlugLabel ||
								  'Taxonomy Slug'
								: window.rslIeData.i18n.metaKeyLabel ||
								  'Meta Key'
						}:</strong>
						<input type="text" class="rsl-ie-custom-field-input regular-text" placeholder="${ placeholder }" />
						${
							isTaxonomy
								? '<p class="description" style="margin-top: 5px;">' +
								  ( window.rslIeData.i18n
										.taxonomySlugDescription ||
										'The slug of the taxonomy (category, post_tag, or custom taxonomy).' ) +
								  '</p>'
								: isMeta
								? '<p class="description" style="margin-top: 5px;">' +
								  ( window.rslIeData.i18n.metaKeyDescription ||
										'The meta key for the custom field (e.g., _custom_price, my_custom_field).' ) +
								  '</p>'
								: ''
						}
					</label>
					${ taxonomyFormatField }
					</div>
					<div class="rsl-ie-modal-footer">
						<button type="button" class="button rsl-ie-modal-cancel">${
							window.rslIeData.i18n.cancel || 'Cancel'
						}</button>
						<button type="button" class="button button-primary rsl-ie-modal-add">${
							window.rslIeData.i18n.addField || 'Add Field'
						}</button>
					</div>
				</div>
			</div>
		`;

		// Add modal to body
		jQuery( 'body' ).append( modalHtml );

		const $modal = jQuery( '#rsl-ie-custom-field-modal' );
		const $backdrop = $modal.find( '.rsl-ie-modal-backdrop' );
		const $input = $modal.find( '.rsl-ie-custom-field-input' );

		// Focus input
		setTimeout( () => $input.focus(), 100 );

		// Close modal handlers
		$modal
			.find( '.rsl-ie-modal-close, .rsl-ie-modal-cancel' )
			.on( 'click', function () {
				$modal.remove();
			} );

		$backdrop.on( 'click', function () {
			$modal.remove();
		} );

		// Add field handler
		$modal.find( '.rsl-ie-modal-add' ).on( 'click', function () {
			const fieldValue = $input.val().trim();

			if ( ! fieldValue ) {
				alert( window.rslIeData.i18n.pleaseEnterFieldName );
				return;
			} // Get taxonomy format if applicable
			let taxonomyFormat = 'name';
			if ( isTaxonomy ) {
				taxonomyFormat = $modal.find( '.rsl-ie-taxonomy-format' ).val();
			}

			// Create new field card
			self.addCustomFieldToGroup(
				$template,
				fieldValue,
				fieldType,
				isMultiple,
				taxonomyFormat
			);

			$modal.remove();
		} );

		// Enter key to add
		$input.on( 'keypress', function ( e ) {
			if ( e.which === 13 ) {
				$modal.find( '.rsl-ie-modal-add' ).click();
			}
		} );
	},

	/**
	 * Add custom field to group
	 */
	addCustomFieldToGroup(
		$template,
		fieldValue,
		fieldType,
		isMultiple,
		taxonomyFormat
	) {
		const $group = $template.closest( '.rsl-ie-field-group' );
		const isTaxonomy = fieldType === 'taxonomy';

		// The importer reads taxonomy values from `taxonomy_<slug>` keys; a slug typed
		// in the "Add Taxonomy Field" dialog must get that prefix to be imported.
		if ( isTaxonomy ) {
			const slug = String( fieldValue ).trim();
			fieldValue = slug.startsWith( 'taxonomy_' )
				? slug
				: `taxonomy_${ slug }`;
		}

		// Custom fields are saved from meta_<key> or _<key> columns only; a plain key such as
		// "event_id" would be ignored by the importer, so give it the meta_ prefix.
		if ( 'meta' === fieldType ) {
			const key = String( fieldValue ).trim();
			fieldValue = /^(meta_|acf_|_)/.test( key ) ? key : `meta_${ key }`;
		}

		// Create label with format info for taxonomy
		let label, badge;
		if ( isTaxonomy ) {
			const formatLabels = {
				id: 'ID',
				slug: 'Slug',
				name: 'Name',
			};
			label = `${ String( fieldValue ).replace( /^taxonomy_/, '' ) }`;
			badge = `taxonomy (${ formatLabels[ taxonomyFormat ] || 'Name' })`;
		} else {
			label = fieldValue;
			badge = 'meta';
		}

		// Store taxonomy format in data attribute
		const taxonomyFormatAttr = isTaxonomy
			? `data-taxonomy-format="${ taxonomyFormat }"`
			: '';

		const fieldHtml = `
			<div class="rsl-ie-target-field" data-target-field="${ fieldValue }" data-field-type="${ fieldType }" data-multiple="${ isMultiple }" ${ taxonomyFormatAttr }>
				<div class="rsl-ie-field-icon">
					<span class="dashicons ${
						isTaxonomy
							? 'dashicons-category'
							: 'dashicons-admin-plugins'
					}"></span>
				</div>
				<div class="rsl-ie-field-info">
					<div class="rsl-ie-field-label">${ label }</div>
					<span class="rsl-ie-field-type-badge">${ badge }</span>
					<button type="button" class="rsl-ie-remove-custom-field" title="Remove">&times;</button>
				</div>
			</div>
		`;

		// Insert before template
		const $newEl = jQuery( fieldHtml );
		$template.before( $newEl );

		// Add remove handler
		$group
			.find( '.rsl-ie-remove-custom-field' )
			.off( 'click' )
			.on( 'click', function () {
				jQuery( this ).closest( '.rsl-ie-target-field' ).remove();
			} );

		return $newEl;
	},

	/**
	 * Get fields by content type (import-compatible fields)
	 */
	getFieldsByContentType( contentType ) {
		// Helper function to get translated field group label
		const t = ( key, fallback ) =>
			window.rslIeData?.i18n?.[
				'fieldGroup' + key.replace( /[^A-Za-z]/g, '' )
			] || fallback;

		// Helper to translate field groups
		const translateGroups = ( groups ) => {
			return groups.map( ( group ) => ( {
				...group,
				label: t( group.label, group.label ),
			} ) );
		};

		const baseFields = [
			{
				label: t( 'Standard', 'Standard' ),
				options: [
					{ value: 'ID', label: 'ID', type: 'number' },
					{ value: 'post_title', label: 'Title', type: 'string' },
					{ value: 'post_content', label: 'Content', type: 'string' },
					{ value: 'post_excerpt', label: 'Excerpt', type: 'string' },
					{ value: 'post_date', label: 'Date', type: 'date' },
					{ value: 'post_status', label: 'Status', type: 'string' },
					{ value: 'post_name', label: 'Slug', type: 'string' },
					{
						value: '_wp_page_template',
						label: 'Template',
						type: 'string',
					},
				],
			},
			{
				label: t( 'Author', 'Author' ),
				options: [
					{
						value: 'post_author',
						label: 'Author ID',
						type: 'number',
					},
					{
						value: 'author_login',
						label: 'Author Login',
						type: 'string',
					},
					{
						value: 'author_name',
						label: 'Author Name',
						type: 'string',
					},
					{
						value: 'author_email',
						label: 'Author Email',
						type: 'email',
					},
				],
			},
			{
				label: 'Media',
				options: [
					{
						value: 'featured_image',
						label: 'Featured Image (Legacy URL)',
						type: 'string',
					},
					{
						value: 'featured_image_url',
						label: 'Featured Image URL',
						type: 'string',
					},
					{
						value: 'featured_image_id',
						label: 'Featured Image ID',
						type: 'number',
					},
					{
						value: 'featured_image_title',
						label: 'Featured Image Title',
						type: 'string',
					},
					{
						value: 'featured_image_caption',
						label: 'Featured Image Caption',
						type: 'string',
					},
					{
						value: 'featured_image_alt',
						label: 'Featured Image Alt Text',
						type: 'string',
					},
				],
			},
			{
				label: 'Other',
				options: [
					{
						value: 'comment_status',
						label: 'Comment Status',
						type: 'string',
					},
					{
						value: 'post_password',
						label: 'Post Password',
						type: 'string',
					},
					{
						value: 'post_modified',
						label: 'Modified Date',
						type: 'date',
					},
					{
						value: 'menu_order',
						label: 'Menu Order',
						type: 'number',
					},
					{
						value: 'is_sticky',
						label: 'Sticky',
						type: 'boolean',
					},
					{
						value: 'post_parent',
						label: 'Parent ID',
						type: 'number',
					},
				],
			},
		];

		// Media
		if ( contentType === 'media' ) {
			return translateGroups( [
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'ID', type: 'number' },
						{ value: 'post_title', label: 'Title', type: 'string' },
						{
							value: 'post_content',
							label: 'Description',
							type: 'string',
						},
						{
							value: 'post_excerpt',
							label: 'Caption',
							type: 'string',
						},
						{
							value: 'alt_text',
							label: 'Alt Text',
							type: 'string',
						},
						{ value: 'guid', label: 'GUID', type: 'string' },
					],
				},
				{
					label: 'File',
					options: [
						{ value: 'file_url', label: 'File URL', type: 'url' },
						{
							value: 'file_path',
							label: 'File Path',
							type: 'string',
						},
						{
							value: 'file_name',
							label: 'File Name',
							type: 'string',
						},
						{
							value: 'file_extension',
							label: 'File Extension',
							type: 'string',
						},
						{
							value: 'post_mime_type',
							label: 'MIME Type',
							type: 'string',
						},
						{
							value: 'file_size',
							label: 'File Size (bytes)',
							type: 'number',
						},
					],
				},
				{
					label: 'Image',
					options: [
						{ value: 'width', label: 'Width (px)', type: 'number' },
						{
							value: 'height',
							label: 'Height (px)',
							type: 'number',
						},
					],
				},
				{
					label: 'Author',
					options: [
						{
							value: 'post_author',
							label: 'Author ID',
							type: 'number',
						},
						{
							value: 'author_login',
							label: 'Author Login',
							type: 'string',
						},
						{
							value: 'author_name',
							label: 'Author Name',
							type: 'string',
						},
						{
							value: 'author_email',
							label: 'Author Email',
							type: 'email',
						},
					],
				},
				{
					label: 'Other',
					options: [
						{
							value: 'post_date',
							label: 'Upload Date',
							type: 'date',
						},
						{
							value: 'post_modified',
							label: 'Modified Date',
							type: 'date',
						},
						{
							value: 'post_parent',
							label: 'Attached To (Post ID)',
							type: 'number',
						},
						{
							value: 'attached_post_title',
							label: 'Attached Post Title',
							type: 'string',
						},
					],
				},
			] );
		}

		// Menus (classic nav_menu / nav_menu_item export)
		if (
			contentType === 'menu' ||
			contentType === 'menus' ||
			contentType === 'nav_menu'
		) {
			return translateGroups( [
				{
					label: 'Basic',
					options: [
						{
							value: 'term_id',
							label: 'Source Menu ID',
							type: 'number',
						},
						{ value: 'name', label: 'Menu Name', type: 'string' },
						{ value: 'slug', label: 'Menu Slug', type: 'string' },
						{
							value: 'description',
							label: 'Description',
							type: 'string',
						},
						{
							value: 'locations',
							label: 'Menu Locations',
							type: 'string',
						},
						{ value: 'count', label: 'Item Count', type: 'number' },
					],
				},
				{
					label: 'Items',
					options: [
						{
							value: 'menu_items',
							label: 'Menu Items Portable JSON (includes source IDs and object hints)',
							type: 'json',
						},
					],
				},
			] );
		}

		// Pages (no taxonomies, only custom fields)
		if ( contentType === 'page' ) {
			return translateGroups( [
				...baseFields,
				{
					label: 'Custom Fields (Meta)',
					options: [
						{
							value: 'meta',
							label: 'Custom Field',
							type: 'meta',
							custom: true,
						},
					],
				},
			] );
		}

		// Users
		if ( contentType === 'user' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'User ID', type: 'number' },
						{
							value: 'user_login',
							label: 'Username',
							type: 'string',
						},
						{ value: 'user_email', label: 'Email', type: 'email' },
						{
							value: 'user_pass',
							label: 'Password',
							type: 'string',
						},
						{
							value: 'display_name',
							label: 'Display Name',
							type: 'string',
						},
						{
							value: 'user_nicename',
							label: 'Nice Name (Slug)',
							type: 'string',
						},
					],
				},
				{
					label: 'Profile',
					options: [
						{
							value: 'first_name',
							label: 'First Name',
							type: 'string',
						},
						{
							value: 'last_name',
							label: 'Last Name',
							type: 'string',
						},
						{
							value: 'nickname',
							label: 'Nickname',
							type: 'string',
						},
						{ value: 'description', label: 'Bio', type: 'string' },
						{ value: 'user_url', label: 'Website', type: 'url' },
						{
							value: 'avatar_url',
							label: 'Avatar URL',
							type: 'url',
						},
					],
				},
				{
					label: 'Social Media',
					options: [
						{
							value: 'facebook',
							label: 'Facebook',
							type: 'string',
						},
						{
							value: 'instagram',
							label: 'Instagram',
							type: 'string',
						},
						{
							value: 'linkedin',
							label: 'LinkedIn',
							type: 'string',
						},
						{ value: 'myspace', label: 'MySpace', type: 'string' },
						{
							value: 'pinterest',
							label: 'Pinterest',
							type: 'string',
						},
						{
							value: 'soundcloud',
							label: 'SoundCloud',
							type: 'string',
						},
						{ value: 'tumblr', label: 'Tumblr', type: 'string' },
						{
							value: 'wikipedia',
							label: 'Wikipedia',
							type: 'string',
						},
						{
							value: 'twitter',
							label: 'Twitter/X',
							type: 'string',
						},
						{ value: 'youtube', label: 'YouTube', type: 'string' },
					],
				},
				{
					label: 'Role & Permissions',
					options: [
						{ value: 'role', label: 'Role', type: 'string' },
						{
							value: 'roles',
							label: 'Roles (comma-separated)',
							type: 'string',
						},
						{
							value: 'capabilities',
							label: 'Capabilities (JSON)',
							type: 'string',
						},
					],
				},
				{
					label: 'Preferences',
					options: [
						{ value: 'locale', label: 'Language', type: 'string' },
						{
							value: 'admin_color',
							label: 'Admin Color Scheme',
							type: 'string',
						},
						{
							value: 'rich_editing',
							label: 'Visual Editor',
							type: 'boolean',
						},
					],
				},
				{
					label: 'Stats',
					options: [
						{
							value: 'posts_count',
							label: 'Posts Count',
							type: 'number',
						},
						{
							value: 'user_registered',
							label: 'Registration Date',
							type: 'date',
						},
					],
				},
				{
					label: 'Custom Fields (User Meta)',
					options: [
						{
							value: 'user_meta',
							label: 'User Meta (JSON)',
							type: 'json',
						},
						{
							value: 'meta',
							label: 'Custom Field',
							type: 'meta',
							custom: true,
						},
					],
				},
			];
		}

		// Comments
		if ( contentType === 'comment' || contentType === 'comments' ) {
			return [
				{
					label: 'Basic',
					options: [
						{
							value: 'comment_ID',
							label: 'Comment ID (local/source reference)',
							type: 'number',
						},
						{
							value: 'source_comment_id',
							label: 'Source Comment ID (for updates)',
							type: 'number',
						},
						{
							value: 'comment_post_ID',
							label: 'Post ID (source/local fallback)',
							type: 'number',
						},
						{
							value: 'source_post_id',
							label: 'Source Post ID (matches imported posts)',
							type: 'number',
						},
						{
							value: 'comment_content',
							label: 'Comment Content',
							type: 'string',
						},
						{
							value: 'comment_approved',
							label: 'Status (1/0/spam)',
							type: 'string',
						},
						{
							value: 'comment_type',
							label: 'Comment Type',
							type: 'string',
						},
					],
				},
				{
					label: 'Author',
					options: [
						{
							value: 'comment_author',
							label: 'Author Name',
							type: 'string',
						},
						{
							value: 'comment_author_email',
							label: 'Author Email',
							type: 'email',
						},
						{
							value: 'comment_author_url',
							label: 'Author URL',
							type: 'url',
						},
						{
							value: 'comment_author_IP',
							label: 'Author IP',
							type: 'string',
						},
						{
							value: 'user_id',
							label: 'User ID (local fallback; email is preferred)',
							type: 'number',
						},
						{
							value: 'comment_agent',
							label: 'User Agent',
							type: 'string',
						},
					],
				},
				{
					label: 'Related Post',
					options: [
						{
							value: 'post_permalink',
							label: 'Post Permalink (matching hint)',
							type: 'url',
						},
						{
							value: 'post_type',
							label: 'Post Type',
							type: 'string',
						},
						{
							value: 'post_slug',
							label: 'Post Slug (recommended matching)',
							type: 'string',
						},
						{
							value: 'post_title',
							label: 'Post Title (fallback matching)',
							type: 'string',
						},
						{
							value: 'post_author',
							label: 'Post Author ID',
							type: 'number',
						},
					],
				},
				{
					label: 'Dates',
					options: [
						{
							value: 'comment_date',
							label: 'Comment Date',
							type: 'datetime',
						},
						{
							value: 'comment_date_gmt',
							label: 'Comment Date (GMT)',
							type: 'datetime',
						},
					],
				},
				{
					label: 'Hierarchy',
					options: [
						{
							value: 'comment_parent',
							label: 'Parent Comment ID (source reference)',
							type: 'number',
						},
						{
							value: 'source_comment_parent_id',
							label: 'Source Parent Comment ID (for threaded comments)',
							type: 'number',
						},
						{
							value: 'comment_karma',
							label: 'Karma',
							type: 'number',
						},
					],
				},
				{
					label: 'Custom Fields (Meta)',
					options: [
						{
							value: 'comment_meta',
							label: 'Comment Meta (JSON)',
							type: 'json',
						},
						{
							value: 'meta',
							label: 'Custom Field',
							type: 'meta',
							custom: true,
						},
					],
				},
			];
		}

		// WooCommerce Products
		if ( contentType === 'product' || contentType === 'woo_product' ) {
			return [
				{
					label: 'Basic Info',
					options: [
						{ value: 'ID', label: 'Product ID', type: 'number' },
						{
							value: 'post_title',
							label: 'Product Title',
							type: 'string',
						},
						{ value: 'post_name', label: 'Slug', type: 'string' },
						{
							value: 'post_status',
							label: 'Status (publish, draft, pending)',
							type: 'string',
						},
						{
							value: 'post_author',
							label: 'Author ID',
							type: 'number',
						},
						{
							value: 'post_content',
							label: 'Description',
							type: 'string',
						},
						{
							value: 'post_excerpt',
							label: 'Short Description',
							type: 'string',
						},
						{
							value: 'comment_status',
							label: 'Reviews Enabled',
							type: 'string',
						},
					],
				},
				{
					label: 'Product Data',
					options: [
						{ value: 'sku', label: 'SKU', type: 'string' },
						{
							value: 'regular_price',
							label: 'Regular Price',
							type: 'number',
						},
						{
							value: 'sale_price',
							label: 'Sale Price',
							type: 'number',
						},
						{
							value: 'product_type',
							label: 'Product Type (simple, variable, grouped, external)',
							type: 'string',
						},
						{
							value: 'product_url',
							label: 'External Product URL',
							type: 'url',
						},
						{
							value: 'button_text',
							label: 'External Button Text',
							type: 'string',
						},
						{
							value: 'downloadable',
							label: 'Downloadable (yes, no)',
							type: 'string',
						},
						{
							value: 'virtual',
							label: 'Virtual (yes, no)',
							type: 'string',
						},
						{
							value: 'featured',
							label: 'Featured (yes, no)',
							type: 'string',
						},
						{
							value: 'visibility',
							label: 'Catalog Visibility',
							type: 'string',
						},
					],
				},
				{
					label: 'Inventory',
					options: [
						{
							value: 'stock_quantity',
							label: 'Stock Quantity',
							type: 'number',
						},
						{
							value: 'stock_status',
							label: 'Stock Status (instock, outofstock, onbackorder)',
							type: 'string',
						},
						{
							value: 'manage_stock',
							label: 'Manage Stock (yes, no)',
							type: 'string',
						},
						{
							value: 'backorders',
							label: 'Backorders (yes, no, notify)',
							type: 'string',
						},
					],
				},
				{
					label: 'Tax',
					options: [
						{
							value: 'tax_status',
							label: 'Tax Status (taxable, shipping, none)',
							type: 'string',
						},
						{
							value: 'tax_class',
							label: 'Tax Class',
							type: 'string',
						},
					],
				},
				{
					label: 'Shipping',
					options: [
						{ value: 'weight', label: 'Weight', type: 'number' },
						{ value: 'length', label: 'Length', type: 'number' },
						{ value: 'width', label: 'Width', type: 'number' },
						{ value: 'height', label: 'Height', type: 'number' },
						{
							value: 'shipping_class',
							label: 'Shipping Class',
							type: 'string',
						},
					],
				},
				{
					label: 'Variations & Extra Data (WooCommerce CSV)',
					options: [
						{
							value: 'parent_sku',
							label: 'Parent (SKU or id:N) - for variation rows',
							type: 'string',
						},
						{
							value: 'upsell_skus',
							label: 'Upsells (SKUs or id:N, comma-separated)',
							type: 'string',
						},
						{
							value: 'crosssell_skus',
							label: 'Cross-sells (SKUs or id:N, comma-separated)',
							type: 'string',
						},
						{
							value: 'download_limit',
							label: 'Download Limit',
							type: 'number',
						},
						{
							value: 'download_expiry',
							label: 'Download Expiry (days)',
							type: 'number',
						},
						{
							value: 'download_1_name',
							label: 'Download 1 name',
							type: 'string',
						},
						{
							value: 'download_1_url',
							label: 'Download 1 URL',
							type: 'url',
						},
						{
							value: 'download_2_name',
							label: 'Download 2 name',
							type: 'string',
						},
						{
							value: 'download_2_url',
							label: 'Download 2 URL',
							type: 'url',
						},
						{
							value: 'download_3_name',
							label: 'Download 3 name',
							type: 'string',
						},
						{
							value: 'download_3_url',
							label: 'Download 3 URL',
							type: 'url',
						},
						{
							value: 'download_4_name',
							label: 'Download 4 name',
							type: 'string',
						},
						{
							value: 'download_4_url',
							label: 'Download 4 URL',
							type: 'url',
						},
						{
							value: 'download_5_name',
							label: 'Download 5 name',
							type: 'string',
						},
						{
							value: 'download_5_url',
							label: 'Download 5 URL',
							type: 'url',
						},
						{
							value: 'sold_individually',
							label: 'Sold Individually (yes, no)',
							type: 'string',
						},
						{
							value: 'purchase_note',
							label: 'Purchase Note',
							type: 'string',
						},
						{
							value: 'low_stock_amount',
							label: 'Low Stock Amount',
							type: 'number',
						},
						{
							value: 'menu_order',
							label: 'Position (menu order)',
							type: 'number',
						},
						{
							value: 'date_on_sale_from',
							label: 'Sale Price Start Date',
							type: 'date',
						},
						{
							value: 'date_on_sale_to',
							label: 'Sale Price End Date',
							type: 'date',
						},
					],
				},
				{
					label: 'Attributes (WooCommerce CSV columns)',
					options: [
						{
							value: 'attribute_1_name',
							label: 'Attribute 1 name',
							type: 'string',
						},
						{
							value: 'attribute_1_values',
							label: 'Attribute 1 value(s)',
							type: 'string',
						},
						{
							value: 'attribute_1_visible',
							label: 'Attribute 1 visible (1/0)',
							type: 'string',
						},
						{
							value: 'attribute_1_global',
							label: 'Attribute 1 global (1/0)',
							type: 'string',
						},
						{
							value: 'attribute_2_name',
							label: 'Attribute 2 name',
							type: 'string',
						},
						{
							value: 'attribute_2_values',
							label: 'Attribute 2 value(s)',
							type: 'string',
						},
						{
							value: 'attribute_2_visible',
							label: 'Attribute 2 visible (1/0)',
							type: 'string',
						},
						{
							value: 'attribute_2_global',
							label: 'Attribute 2 global (1/0)',
							type: 'string',
						},
						{
							value: 'attribute_3_name',
							label: 'Attribute 3 name',
							type: 'string',
						},
						{
							value: 'attribute_3_values',
							label: 'Attribute 3 value(s)',
							type: 'string',
						},
						{
							value: 'attribute_3_visible',
							label: 'Attribute 3 visible (1/0)',
							type: 'string',
						},
						{
							value: 'attribute_3_global',
							label: 'Attribute 3 global (1/0)',
							type: 'string',
						},
						{
							value: 'attribute_4_name',
							label: 'Attribute 4 name',
							type: 'string',
						},
						{
							value: 'attribute_4_values',
							label: 'Attribute 4 value(s)',
							type: 'string',
						},
						{
							value: 'attribute_4_visible',
							label: 'Attribute 4 visible (1/0)',
							type: 'string',
						},
						{
							value: 'attribute_4_global',
							label: 'Attribute 4 global (1/0)',
							type: 'string',
						},
						{
							value: 'attribute_5_name',
							label: 'Attribute 5 name',
							type: 'string',
						},
						{
							value: 'attribute_5_values',
							label: 'Attribute 5 value(s)',
							type: 'string',
						},
						{
							value: 'attribute_5_visible',
							label: 'Attribute 5 visible (1/0)',
							type: 'string',
						},
						{
							value: 'attribute_5_global',
							label: 'Attribute 5 global (1/0)',
							type: 'string',
						},
					],
				},
				{
					label: 'Images',
					options: [
						{
							value: 'featured_image_id',
							label: 'Featured Image ID',
							type: 'number',
						},
						{
							value: 'featured_image_url',
							label: 'Featured Image URL',
							type: 'url',
						},
						{
							value: 'featured_image_title',
							label: 'Featured Image Title',
							type: 'string',
						},
						{
							value: 'featured_image_caption',
							label: 'Featured Image Caption',
							type: 'string',
						},
						{
							value: 'featured_image_alt',
							label: 'Featured Image Alt Text',
							type: 'string',
						},
						{
							value: 'product_images',
							label: 'Images (comma-separated URLs: first = featured)',
							type: 'string',
						},
						{
							value: 'product_gallery',
							label: 'Gallery Image IDs (comma-separated)',
							type: 'string',
						},
						{
							value: 'variations',
							label: 'Variations (JSON)',
							type: 'json',
						},
						{
							value: 'product_attributes',
							label: 'Product Attributes (JSON)',
							type: 'json',
						},
						{
							value: 'grouped_products',
							label: 'Grouped Products (JSON)',
							type: 'json',
						},
						{
							value: '_children',
							label: 'Grouped Product Children (raw _children)',
							type: 'string',
						},
					],
				},
				{
					label: 'Categories & Tags',
					options: [
						{
							value: 'product_cat',
							label: 'Categories (comma-separated)',
							type: 'string',
						},
						{
							value: 'product_tag',
							label: 'Tags (comma-separated)',
							type: 'string',
						},
						{
							value: 'product_brand',
							label: 'Brands (JSON or comma-separated)',
							type: 'json',
						},
					],
				},
				{
					label: 'Reviews',
					options: [
						{
							value: 'average_rating',
							label: 'Average Rating',
							type: 'number',
						},
						{
							value: 'review_count',
							label: 'Review Count',
							type: 'number',
						},
					],
				},
				{
					label: 'Stats',
					options: [
						{
							value: 'total_sales',
							label: 'Total Sales',
							type: 'number',
						},
					],
				},
				{
					label: 'Dates',
					options: [
						{
							value: 'post_date',
							label: 'Created Date',
							type: 'date',
						},
						{
							value: 'post_modified',
							label: 'Modified Date',
							type: 'date',
						},
					],
				},
				{
					label: 'Custom Fields (Product Meta)',
					options: [
						{
							value: 'meta',
							label: 'Custom Field',
							type: 'meta',
							custom: true,
						},
					],
				},
			];
		}

		// WooCommerce Orders
		if ( contentType === 'woo_order' || contentType === 'order' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'Order ID', type: 'number' },
						{
							value: 'order_number',
							label: 'Order Number',
							type: 'string',
						},
						{
							value: 'order_key',
							label: 'Order Key',
							type: 'string',
						},
						{
							value: 'order_status',
							label: 'Order Status (pending, processing, completed, ...)',
							type: 'string',
						},
						{
							value: 'currency',
							label: 'Currency',
							type: 'string',
						},
					],
				},
				{
					label: 'Dates',
					options: [
						{
							value: 'order_date',
							label: 'Order Date',
							type: 'datetime',
						},
						{
							value: 'date_modified',
							label: 'Modified Date',
							type: 'datetime',
						},
						{
							value: 'paid_date',
							label: 'Paid Date',
							type: 'datetime',
						},
						{
							value: 'completed_date',
							label: 'Completed Date',
							type: 'datetime',
						},
					],
				},
				{
					label: 'Customer',
					options: [
						{
							value: 'customer_id',
							label: 'Customer ID',
							type: 'number',
						},
						{
							value: 'billing_email',
							label: 'Billing Email',
							type: 'email',
						},
						{
							value: 'billing_phone',
							label: 'Billing Phone',
							type: 'string',
						},
						{
							value: 'customer_note',
							label: 'Customer Note',
							type: 'string',
						},
						{
							value: 'customer_ip_address',
							label: 'Customer IP Address',
							type: 'string',
						},
						{
							value: 'customer_user_agent',
							label: 'Customer User Agent',
							type: 'string',
						},
					],
				},
				{
					label: 'Billing Address',
					options: [
						{
							value: 'billing_first_name',
							label: 'First Name',
							type: 'string',
						},
						{
							value: 'billing_last_name',
							label: 'Last Name',
							type: 'string',
						},
						{
							value: 'billing_company',
							label: 'Company',
							type: 'string',
						},
						{
							value: 'billing_address_1',
							label: 'Address 1',
							type: 'string',
						},
						{
							value: 'billing_address_2',
							label: 'Address 2',
							type: 'string',
						},
						{
							value: 'billing_city',
							label: 'City',
							type: 'string',
						},
						{
							value: 'billing_state',
							label: 'State',
							type: 'string',
						},
						{
							value: 'billing_postcode',
							label: 'Postcode',
							type: 'string',
						},
						{
							value: 'billing_country',
							label: 'Country',
							type: 'string',
						},
					],
				},
				{
					label: 'Shipping Address',
					options: [
						{
							value: 'shipping_first_name',
							label: 'First Name',
							type: 'string',
						},
						{
							value: 'shipping_last_name',
							label: 'Last Name',
							type: 'string',
						},
						{
							value: 'shipping_company',
							label: 'Company',
							type: 'string',
						},
						{
							value: 'shipping_address_1',
							label: 'Address 1',
							type: 'string',
						},
						{
							value: 'shipping_address_2',
							label: 'Address 2',
							type: 'string',
						},
						{
							value: 'shipping_city',
							label: 'City',
							type: 'string',
						},
						{
							value: 'shipping_state',
							label: 'State',
							type: 'string',
						},
						{
							value: 'shipping_postcode',
							label: 'Postcode',
							type: 'string',
						},
						{
							value: 'shipping_country',
							label: 'Country',
							type: 'string',
						},
					],
				},
				{
					label: 'Payment',
					options: [
						{
							value: 'payment_method',
							label: 'Payment Method',
							type: 'string',
						},
						{
							value: 'payment_method_title',
							label: 'Payment Method Title',
							type: 'string',
						},
						{
							value: 'transaction_id',
							label: 'Transaction ID',
							type: 'string',
						},
					],
				},
				{
					label: 'Totals',
					options: [
						{
							value: 'order_total',
							label: 'Order Total',
							type: 'number',
						},
						{
							value: 'order_subtotal',
							label: 'Subtotal',
							type: 'number',
						},
						{
							value: 'order_tax',
							label: 'Order Tax',
							type: 'number',
						},
						{
							value: 'order_shipping',
							label: 'Order Shipping',
							type: 'number',
						},
						{
							value: 'order_discount',
							label: 'Order Discount',
							type: 'number',
						},
						{
							value: 'cart_tax',
							label: 'Cart Tax',
							type: 'number',
						},
						{
							value: 'shipping_tax',
							label: 'Shipping Tax',
							type: 'number',
						},
						{
							value: 'total_tax',
							label: 'Total Tax',
							type: 'number',
						},
						{
							value: 'tax_lines',
							label: 'Tax Lines (Array)',
							type: 'string',
						},
					],
				},
				{
					label: 'Lines',
					options: [
						{
							value: 'order_items',
							label: 'Order Items (JSON)',
							type: 'json',
						},
						{
							value: 'item_count',
							label: 'Item Count',
							type: 'number',
						},
						{
							value: 'shipping_method',
							label: 'Shipping Method',
							type: 'string',
						},
						{
							value: 'shipping_lines',
							label: 'Shipping Lines (JSON)',
							type: 'json',
						},
						{
							value: 'fee_lines',
							label: 'Fee Lines (JSON)',
							type: 'json',
						},
						{
							value: 'coupon_lines',
							label: 'Coupon Lines (JSON)',
							type: 'json',
						},
					],
				},
				{
					label: 'Notes & Meta',
					options: [
						{
							value: 'order_notes',
							label: 'Order Notes (JSON)',
							type: 'json',
						},
						{
							value: 'refunds',
							label: 'Refunds (JSON)',
							type: 'json',
						},
						{
							value: 'order_meta',
							label: 'Order Meta (JSON)',
							type: 'json',
						},
						{
							value: 'meta',
							label: 'Custom Field',
							type: 'meta',
							custom: true,
						},
					],
				},
			];
		}

		// Taxonomy Terms
		if (
			contentType === 'taxonomy' ||
			contentType === 'taxonomy_term' ||
			contentType === 'term'
		) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'term_id', label: 'Term ID', type: 'number' },
						{ value: 'name', label: 'Name', type: 'string' },
						{ value: 'slug', label: 'Slug', type: 'string' },
						{
							value: 'description',
							label: 'Description',
							type: 'string',
						},
					],
				},
				{
					label: 'Taxonomy',
					options: [
						{
							value: 'taxonomy',
							label: 'Taxonomy',
							type: 'string',
						},
						{
							value: 'term_taxonomy_id',
							label: 'Term Taxonomy ID',
							type: 'number',
						},
					],
				},
				{
					label: 'Hierarchy',
					options: [
						{
							value: 'parent',
							label: 'Parent Term ID',
							type: 'number',
						},
						{
							value: 'parent_slug',
							label: 'Parent Slug or Name',
							type: 'string',
						},
					],
				},
				{
					label: 'Image',
					options: [
						{
							value: 'image_url',
							label: 'Image URL',
							type: 'string',
						},
					],
				},
				{
					label: 'Stats',
					options: [
						{ value: 'count', label: 'Count', type: 'number' },
					],
				},
				{
					label: 'Custom Fields (Term Meta)',
					options: [
						{
							value: 'term_meta',
							label: 'Term Meta (JSON)',
							type: 'json',
						},
						{
							value: 'meta',
							label: 'Custom Field',
							type: 'meta',
							custom: true,
						},
					],
				},
			];
		}

		if ( contentType === 'custom_post_types' ) {
			return [
				{
					label: 'Standard',
					options: [
						{ value: 'ID', label: 'ID', type: 'number' },
						{
							value: 'post_title',
							label: 'Title',
							type: 'string',
							required: true,
						},
						{
							value: 'post_content',
							label: 'Content',
							type: 'string',
						},
						{
							value: 'post_excerpt',
							label: 'Excerpt',
							type: 'string',
						},
						{
							value: 'post_status',
							label: 'Status',
							type: 'string',
						},
						{
							value: 'post_type',
							label: 'Post Type',
							type: 'string',
						},
						{ value: 'post_date', label: 'Date', type: 'datetime' },
						{
							value: 'post_modified',
							label: 'Modified Date',
							type: 'datetime',
						},
						{
							value: 'menu_order',
							label: 'Menu Order',
							type: 'number',
						},
						{
							value: 'is_sticky',
							label: 'Sticky',
							type: 'boolean',
						},
						{ value: 'post_name', label: 'Slug', type: 'string' },
						{
							value: 'post_parent',
							label: 'Parent ID',
							type: 'number',
						},
						{
							value: 'comment_status',
							label: 'Comment Status',
							type: 'string',
						},
						{
							value: 'ping_status',
							label: 'Ping Status',
							type: 'string',
						},
						{
							value: 'post_password',
							label: 'Post Password',
							type: 'string',
						},
					],
				},
				{
					label: 'Author',
					options: [
						// Keep naming consistent with the Post_Importer (expects `post_author`,
						// plus optional `author_name`/`author_email` fallbacks).
						{
							value: 'post_author',
							label: 'Author ID',
							type: 'number',
						},
						{
							value: 'author_login',
							label: 'Author Login',
							type: 'string',
						},
						{
							value: 'author_name',
							label: 'Author Name',
							type: 'string',
						},
						{
							value: 'author_email',
							label: 'Author Email',
							type: 'string',
						},
					],
				},
				{
					label: 'Media',
					options: [
						{
							value: 'featured_image',
							label: 'Featured Image (Legacy URL)',
							type: 'string',
						},
						{
							value: 'featured_image_url',
							label: 'Featured Image URL',
							type: 'string',
						},
						{
							value: 'featured_image_id',
							label: 'Featured Image ID',
							type: 'number',
						},
						{
							value: 'featured_image_title',
							label: 'Featured Image Title',
							type: 'string',
						},
						{
							value: 'featured_image_caption',
							label: 'Featured Image Caption',
							type: 'string',
						},
						{
							value: 'featured_image_alt',
							label: 'Featured Image Alt Text',
							type: 'string',
						},
					],
				},
				{
					label: 'Other',
					options: [
						{
							value: 'post_parent_slug',
							label: 'Parent Slug',
							type: 'string',
						},
					],
				},
				{
					label: 'Taxonomies',
					options: [
						{
							value: 'taxonomy',
							label: 'Taxonomy',
							type: 'taxonomy',
							custom: true,
						},
					],
				},
				{
					label: 'Custom Fields',
					options: [
						{
							value: 'meta',
							label: 'Custom Field',
							type: 'meta',
							custom: true,
						},
					],
				},
			];
		}

		// WooCommerce Attributes
		if ( contentType === 'woo_attribute' ) {
			return [
				{
					label: 'Basic',
					options: [
						{
							value: 'attribute_id',
							label: 'Attribute ID',
							type: 'number',
						},
						{
							value: 'attribute_name',
							label: 'Attribute Name / Slug (e.g. color)',
							type: 'string',
						},
						{
							value: 'attribute_label',
							label: 'Attribute Label (e.g. Color)',
							type: 'string',
						},
						{
							value: 'attribute_type',
							label: 'Type (select, text)',
							type: 'string',
						},
					],
				},
				{
					label: 'Settings',
					options: [
						{
							value: 'attribute_orderby',
							label: 'Order By (menu_order, name, name_num, id)',
							type: 'string',
						},
						{
							value: 'attribute_public',
							label: 'Enable Archives (1/0)',
							type: 'boolean',
						},
					],
				},
				{
					label: 'Terms',
					options: [
						{
							value: 'attribute_terms',
							label: 'Attribute Terms (JSON array or comma-separated names)',
							type: 'string',
						},
						{
							value: 'term_count',
							label: 'Term Count',
							type: 'number',
						},
					],
				},
			];
		}

		if ( contentType === 'woo_review' ) {
			return [
				{
					label: 'Review',
					options: [
						{
							value: 'comment_ID',
							label: 'Review ID (local/source reference)',
							type: 'number',
						},
						{
							value: 'source_comment_id',
							label: 'Source Review ID (for updates)',
							type: 'string',
						},
						{
							value: 'product_id',
							label: 'Product ID (source/local fallback)',
							type: 'number',
						},
						{
							value: 'source_product_id',
							label: 'Source Product ID (matches imported products)',
							type: 'number',
						},
						{
							value: 'product_title',
							label: 'Product Title (fallback matching)',
							type: 'string',
						},
						{
							value: 'product_slug',
							label: 'Product Slug (recommended matching)',
							type: 'string',
						},
						{
							value: 'product_sku',
							label: 'Product SKU (best matching)',
							type: 'string',
						},
						{
							value: 'comment_author',
							label: 'Author',
							type: 'string',
						},
						{
							value: 'comment_author_email',
							label: 'Author Email',
							type: 'email',
						},
						{
							value: 'comment_author_url',
							label: 'Author URL',
							type: 'url',
						},
						{
							value: 'comment_author_IP',
							label: 'Author IP',
							type: 'string',
						},
						{
							value: 'comment_date',
							label: 'Review Date',
							type: 'datetime',
						},
						{
							value: 'comment_content',
							label: 'Content',
							type: 'string',
						},
						{
							value: 'comment_approved',
							label: 'Status',
							type: 'string',
						},
						{
							value: 'comment_agent',
							label: 'User Agent',
							type: 'string',
						},
						{
							value: 'comment_parent',
							label: 'Parent Review ID',
							type: 'number',
						},
						{ value: 'user_id', label: 'User ID', type: 'number' },
						{ value: 'rating', label: 'Rating', type: 'number' },
						{
							value: 'verified',
							label: 'Verified Owner',
							type: 'boolean',
						},
						{
							value: 'comment_meta',
							label: 'Review Meta JSON',
							type: 'json',
						},
					],
				},
			];
		}

		if ( contentType === 'woo_refund' ) {
			return [
				{
					label: 'Refund',
					options: [
						{
							value: 'refund_id',
							label: 'Refund ID (local/source reference)',
							type: 'number',
						},
						{
							value: 'source_refund_id',
							label: 'Source Refund ID (for updates)',
							type: 'number',
						},
						{
							value: 'parent_order_id',
							label: 'Parent Order ID (source/local fallback)',
							type: 'number',
						},
						{
							value: 'source_parent_order_id',
							label: 'Source Parent Order ID (matches imported orders)',
							type: 'number',
						},
						{
							value: 'parent_order_key',
							label: 'Parent Order Key (best matching)',
							type: 'string',
						},
						{
							value: 'parent_order_number',
							label: 'Parent Order Number',
							type: 'string',
						},
						{ value: 'status', label: 'Status', type: 'string' },
						{
							value: 'date_created',
							label: 'Date Created',
							type: 'datetime',
						},
						{
							value: 'date_modified',
							label: 'Date Modified',
							type: 'datetime',
						},
						{ value: 'amount', label: 'Amount', type: 'number' },
						{ value: 'reason', label: 'Reason', type: 'string' },
						{
							value: 'refunded_by',
							label: 'Refunded By User ID',
							type: 'number',
						},
						{
							value: 'currency',
							label: 'Currency',
							type: 'string',
						},
						{
							value: 'line_items',
							label: 'Line Items JSON',
							type: 'json',
						},
						{ value: 'meta', label: 'Meta JSON', type: 'json' },
					],
				},
			];
		}

		if ( contentType === 'woo_customer' ) {
			return [
				{
					label: 'Customer',
					options: [
						{
							value: 'customer_id',
							label: 'Customer ID (reference)',
							type: 'number',
						},
						{
							value: 'source_customer_id',
							label: 'Source Customer ID (reference)',
							type: 'number',
						},
						{
							value: 'user_id',
							label: 'User ID (reference)',
							type: 'number',
						},
						{
							value: 'source_user_id',
							label: 'Source User ID (reference)',
							type: 'number',
						},
						{
							value: 'email',
							label: 'Email (required, matches existing customers)',
							type: 'email',
						},
						{
							value: 'username',
							label: 'Username',
							type: 'string',
						},
						{
							value: 'first_name',
							label: 'First Name',
							type: 'string',
						},
						{
							value: 'last_name',
							label: 'Last Name',
							type: 'string',
						},
						{
							value: 'display_name',
							label: 'Display Name',
							type: 'string',
						},
						{
							value: 'date_created',
							label: 'Date Created',
							type: 'datetime',
						},
						{
							value: 'date_modified',
							label: 'Date Modified',
							type: 'datetime',
						},
						{
							value: 'is_paying_customer',
							label: 'Is Paying Customer',
							type: 'boolean',
						},
						{
							value: 'order_count',
							label: 'Order Count',
							type: 'number',
						},
						{
							value: 'total_spent',
							label: 'Total Spent',
							type: 'number',
						},
						{
							value: 'last_order_id',
							label: 'Last Order ID',
							type: 'number',
						},
					],
				},
				{
					label: 'Billing',
					options: [
						'billing_first_name',
						'billing_last_name',
						'billing_company',
						'billing_email',
						'billing_phone',
						'billing_address_1',
						'billing_address_2',
						'billing_city',
						'billing_state',
						'billing_postcode',
						'billing_country',
					].map( ( value ) => ( {
						value,
						label: value
							.replace( /_/g, ' ' )
							.replace( /\b\w/g, ( c ) => c.toUpperCase() ),
						type: 'string',
					} ) ),
				},
				{
					label: 'Shipping',
					options: [
						'shipping_first_name',
						'shipping_last_name',
						'shipping_company',
						'shipping_phone',
						'shipping_address_1',
						'shipping_address_2',
						'shipping_city',
						'shipping_state',
						'shipping_postcode',
						'shipping_country',
					].map( ( value ) => ( {
						value,
						label: value
							.replace( /_/g, ' ' )
							.replace( /\b\w/g, ( c ) => c.toUpperCase() ),
						type: 'string',
					} ) ),
				},
				{
					label: 'Meta',
					options: [
						{
							value: 'meta',
							label: 'Customer Meta JSON',
							type: 'json',
						},
					],
				},
			];
		}

		// WooCommerce Coupons
		if ( contentType === 'woo_coupon' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'Coupon ID', type: 'number' },
						{
							value: 'post_title',
							label: 'Coupon Code',
							type: 'string',
						},
						{
							value: 'post_excerpt',
							label: 'Description',
							type: 'string',
						},
						{
							value: 'post_status',
							label: 'Status (publish, draft, pending)',
							type: 'string',
						},
					],
				},
				{
					label: 'Discount',
					options: [
						{
							value: 'discount_type',
							label: 'Discount Type (percent, fixed_cart, fixed_product)',
							type: 'string',
						},
						{
							value: 'coupon_amount',
							label: 'Amount',
							type: 'number',
						},
						{
							value: 'free_shipping',
							label: 'Free Shipping (1/0)',
							type: 'boolean',
						},
					],
				},
				{
					label: 'Usage Restrictions',
					options: [
						{
							value: 'minimum_amount',
							label: 'Minimum Spend',
							type: 'number',
						},
						{
							value: 'maximum_amount',
							label: 'Maximum Spend',
							type: 'number',
						},
						{
							value: 'individual_use',
							label: 'Individual Use Only (1/0)',
							type: 'boolean',
						},
						{
							value: 'exclude_sale_items',
							label: 'Exclude Sale Items (1/0)',
							type: 'boolean',
						},
					],
				},
				{
					label: 'Product Restrictions',
					options: [
						{
							value: 'product_ids',
							label: 'Allowed Products (JSON or comma-separated IDs)',
							type: 'string',
						},
						{
							value: 'excluded_product_ids',
							label: 'Excluded Products (JSON or comma-separated IDs)',
							type: 'string',
						},
						{
							value: 'product_categories',
							label: 'Allowed Categories (JSON or comma-separated IDs)',
							type: 'string',
						},
						{
							value: 'excluded_product_categories',
							label: 'Excluded Categories (JSON or comma-separated IDs)',
							type: 'string',
						},
					],
				},
				{
					label: 'Email Restrictions',
					options: [
						{
							value: 'allowed_emails',
							label: 'Allowed Emails (JSON or comma-separated)',
							type: 'string',
						},
					],
				},
				{
					label: 'Usage Limits',
					options: [
						{
							value: 'usage_limit',
							label: 'Usage Limit (total)',
							type: 'number',
						},
						{
							value: 'usage_limit_per_user',
							label: 'Usage Limit Per User',
							type: 'number',
						},
						{
							value: 'limit_usage_to_x_items',
							label: 'Limit Usage to X Items',
							type: 'number',
						},
						{
							value: 'usage_count',
							label: 'Usage Count',
							type: 'number',
						},
					],
				},
				{
					label: 'Dates',
					options: [
						{
							value: 'date_expires',
							label: 'Expiry Date (YYYY-MM-DD)',
							type: 'date',
						},
						{
							value: 'post_date',
							label: 'Created Date',
							type: 'date',
						},
						{
							value: 'post_modified',
							label: 'Modified Date',
							type: 'date',
						},
					],
				},
				{
					label: 'Custom Fields (Meta)',
					options: [
						{
							value: 'meta',
							label: 'Custom Field',
							type: 'meta',
							custom: true,
						},
					],
				},
			];
		}

		// Default - return post fields with taxonomies and custom fields
		return translateGroups( [
			...baseFields,
			{
				label: 'Taxonomies',
				options: [
					{
						value: 'taxonomy',
						label: 'Taxonomy',
						type: 'taxonomy',
						custom: true,
					},
				],
			},
			{
				label: 'Custom Fields (Meta)',
				options: [
					{
						value: 'meta',
						label: 'Custom Field',
						type: 'meta',
						custom: true,
					},
				],
			},
		] );
	},

	/**
	 * Initialize drag & drop functionality
	 */
	initializeDragDrop() {
		const self = this;
		let draggedElement = null;

		// Drag start on source fields
		jQuery( document ).on(
			'dragstart',
			'.rsl-ie-field-card',
			function ( e ) {
				draggedElement = jQuery( this );
				jQuery( this ).addClass( 'dragging' );

				e.originalEvent.dataTransfer.effectAllowed = 'copy';
				e.originalEvent.dataTransfer.setData(
					'text/html',
					this.innerHTML
				);
			}
		);

		// Drag end
		jQuery( document ).on( 'dragend', '.rsl-ie-field-card', function () {
			jQuery( this ).removeClass( 'dragging' );
			jQuery( '.rsl-ie-target-field' ).removeClass( 'drag-over' );
		} );

		// Drag over target fields
		jQuery( document ).on(
			'dragover',
			'.rsl-ie-target-field',
			function ( e ) {
				e.preventDefault();
				jQuery( this ).addClass( 'drag-over' );
			}
		);

		// Drag leave
		jQuery( document ).on(
			'dragleave',
			'.rsl-ie-target-field',
			function () {
				jQuery( this ).removeClass( 'drag-over' );
			}
		);

		// Drop on target field
		jQuery( document ).on( 'drop', '.rsl-ie-target-field', function ( e ) {
			e.preventDefault();
			jQuery( this ).removeClass( 'drag-over' );

			if ( ! draggedElement ) {
				return;
			}

			const sourceField = draggedElement.data( 'source-field' );
			const sourceIndex = draggedElement.data( 'source-index' );
			const targetField = jQuery( this ).data( 'target-field' );
			const fieldType = jQuery( this ).data( 'field-type' );

			// Create mapping
			self.createMapping(
				sourceField,
				sourceIndex,
				targetField,
				fieldType,
				jQuery( this )
			);

			// Add visual indicator that this source is used (but don't disable it)
			draggedElement.addClass( 'used' );

			// Clear dragged element
			draggedElement = null;

			// Update stats
			self.updateMappingStats();
		} );

		// Remove mapping (from target field)
		jQuery( document ).on(
			'click',
			'.rsl-ie-remove-mapping',
			function ( e ) {
				e.stopPropagation();
				const $targetField = jQuery( this ).closest(
					'.rsl-ie-target-field'
				);
				const sourceIndex = $targetField.data( 'mapped-source-index' );

				self.removeMapping( sourceIndex, $targetField );
			}
		);

		// Remove mapping (from mapped fields section)
		jQuery( document ).on(
			'click',
			'.rsl-ie-remove-row-mapping',
			function ( e ) {
				e.stopPropagation();
				const sourceIndex = jQuery( this ).data( 'source-index' );
				const targetField = jQuery( this )
					.closest( '.rsl-ie-mapping-row' )
					.data( 'target-field' );
				const $targetField = jQuery(
					`.rsl-ie-target-field[data-target-field="${ targetField }"]`
				);

				self.removeMapping( sourceIndex, $targetField );
			}
		);

		// Add function to mapping
		jQuery( document ).on( 'click', '.rsl-ie-add-function', function ( e ) {
			e.stopPropagation();
			if ( ! self.areFieldTransformationsEnabled() ) {
				return;
			}
			const sourceIndex = jQuery( this ).data( 'source-index' );
			const targetField = jQuery( this ).data( 'target-field' );

			self.showFunctionSelector( sourceIndex, targetField );
		} );

		// Remove function from mapping
		jQuery( document ).on(
			'click',
			'.rsl-ie-remove-function',
			function ( e ) {
				e.stopPropagation();
				const functionIndex = jQuery( this ).data( 'function-index' );
				const $row = jQuery( this ).closest( '.rsl-ie-mapping-row' );
				const sourceIndex = $row.data( 'source-index' );
				const targetField = $row.data( 'target-field' );

				self.removeFunction( sourceIndex, targetField, functionIndex );
			}
		);
	},

	/**
	 * Remove mapping
	 */
	removeMapping( sourceIndex, $targetField ) {
		const targetField = $targetField.data( 'target-field' );

		// Remove mapping from target
		$targetField.find( '.rsl-ie-mapped-source' ).remove();
		$targetField.removeClass( 'has-mapping' );
		$targetField.removeData( 'mapped-source-index' );
		$targetField.removeData( 'mapped-source-field' );

		// Check if this source is still used in other mappings BEFORE removing
		// We need to exclude the current mapping being removed
		const allMappings = jQuery(
			`.rsl-ie-mapping-row[data-source-index="${ sourceIndex }"]`
		);
		const otherMappings = allMappings.filter( function () {
			return jQuery( this ).data( 'target-field' ) !== targetField;
		} );
		const stillUsed = otherMappings.length > 0;

		// Remove from mapped fields section (specific target field)
		jQuery(
			`.rsl-ie-mapping-row[data-source-index="${ sourceIndex }"][data-target-field="${ targetField }"]`
		).remove();

		// Remove functions for this mapping
		const mappingKey = `${ sourceIndex }-${ targetField }`;
		if ( this.mappingFunctions && this.mappingFunctions[ mappingKey ] ) {
			delete this.mappingFunctions[ mappingKey ];
		}

		if ( ! stillUsed ) {
			// Remove 'used' class only if not used anywhere else
			jQuery(
				`.rsl-ie-field-card[data-source-index="${ sourceIndex }"]`
			).removeClass( 'used' );
		}

		// Show empty state if no mappings
		if ( jQuery( '.rsl-ie-mapping-row' ).length === 0 ) {
			jQuery( '.rsl-ie-mapped-fields .rsl-ie-empty-state' ).show();
		}

		// Update stats
		this.updateMappingStats();
	},

	/**
	 * Create field mapping
	 */
	createMapping(
		sourceField,
		sourceIndex,
		targetField,
		fieldType,
		$targetElement
	) {
		// Remove existing mapping if any
		$targetElement.find( '.rsl-ie-mapped-source' ).remove();

		// Add mapped source indicator to target
		const mappedHtml = `
			<div class="rsl-ie-mapped-source">
				<span class="rsl-ie-source-name">${ Utils.escapeHtml( sourceField ) }</span>
				<span class="dashicons dashicons-no-alt rsl-ie-remove-mapping"></span>
			</div>
		`;
		$targetElement.find( '.rsl-ie-field-info' ).append( mappedHtml );
		$targetElement.addClass( 'has-mapping' );
		$targetElement
			.removeClass( 'rsl-ie-template-missing' )
			.find( '.rsl-ie-template-missing-note' )
			.remove();
		$targetElement.data( 'mapped-source-index', sourceIndex );
		$targetElement.data( 'mapped-source-field', sourceField );

		// Add to mapped fields section
		this.addToMappedFields(
			sourceField,
			sourceIndex,
			targetField,
			fieldType
		);
	},

	/**
	 * Add mapping to mapped fields section
	 */
	addToMappedFields( sourceField, sourceIndex, targetField, fieldType ) {
		const $container = jQuery( '.rsl-ie-mapped-fields' );

		// Hide empty state
		$container.find( '.rsl-ie-empty-state' ).hide();

		// Remove existing row for this specific combination (если перемапливаем то же поле)
		jQuery(
			`.rsl-ie-mapping-row[data-source-index="${ sourceIndex }"][data-target-field="${ targetField }"]`
		).remove();

		// Get functions for this mapping
		const mappingKey = `${ sourceIndex }-${ targetField }`;
		const functions = this.mappingFunctions?.[ mappingKey ] || [];

		// Build functions HTML
		let functionsHtml = '';
		if ( this.areFieldTransformationsEnabled() && functions.length > 0 ) {
			functionsHtml = '<div class="rsl-ie-mapping-functions">';
			functions.forEach( ( func, index ) => {
				functionsHtml += `
					<span class="rsl-ie-function-badge">
						${ Utils.escapeHtml( func.name ) }
						<button type="button" class="rsl-ie-remove-function" data-function-index="${ index }">×</button>
					</span>
				`;
			} );
			functionsHtml += '</div>';
		}
		const transformationButton = this.areFieldTransformationsEnabled()
			? `
				<button type="button" class="button button-small rsl-ie-add-function" data-source-index="${ sourceIndex }" data-target-field="${ targetField }" title="${
					window.rslIeData.i18n.addTransformationFunction ||
					'Add transformation'
				}">
					<span class="dashicons dashicons-admin-tools"></span>
				</button>
			`
			: '';

		// Add new row
		const html = `
			<div class="rsl-ie-mapping-row" data-source-index="${ sourceIndex }" data-target-field="${ targetField }">
				<div class="rsl-ie-source-col">
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<strong>${ Utils.escapeHtml( sourceField ) }</strong>
				</div>
				<div class="rsl-ie-arrow">→</div>
				<div class="rsl-ie-target-col">
					<span class="dashicons dashicons-wordpress"></span>				<strong>${ targetField }</strong>
			</div>
			${ functionsHtml }
			<div class="rsl-ie-mapping-actions">
				${ transformationButton }
				<button type="button" class="button button-small rsl-ie-remove-row-mapping" data-source-index="${ sourceIndex }" data-target-field="${ targetField }" title="${
					window.rslIeData.i18n.removeMapping || 'Remove mapping'
				}">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		</div>
		`;

		$container.append( html );
	},

	/**
	 * Update mapping statistics
	 */
	updateMappingStats() {
		const totalSourceFields = this.fileData?.columns?.length || 0;

		// Count unique source fields that are used
		const usedSourceIndexes = new Set();
		jQuery( '.rsl-ie-mapping-row' ).each( function () {
			const sourceIndex = jQuery( this ).data( 'source-index' );
			// Only add if sourceIndex is defined (skip if undefined/null)
			if (
				sourceIndex !== undefined &&
				sourceIndex !== null &&
				sourceIndex !== ''
			) {
				usedSourceIndexes.add( sourceIndex );
			}
		} );
		const mappedCount = usedSourceIndexes.size;

		// Show: "X / Y fields mapped" where Y is total source columns
		jQuery( '.rsl-ie-mapped-count' ).text( mappedCount );
		jQuery( '.rsl-ie-total-fields' ).text( totalSourceFields );

		// Enable/disable Next button based on mapping count (only on Step 4)
		if ( this.currentStep === 4 ) {
			const $nextButton = jQuery( '.rsl-ie-next-step' );
			if ( mappedCount === 0 ) {
				$nextButton.prop( 'disabled', true );
			} else {
				$nextButton.prop( 'disabled', false );
			}
		}
	},

	/**
	 * Show transformation selector modal
	 */
	async showFunctionSelector( sourceIndex, targetField ) {
		if ( ! this.areFieldTransformationsEnabled() ) {
			return;
		}
		const functionsAction =
			this.getFieldTransformationAction( 'list' ) ||
			this.getFieldTransformationAction( 'snippets' );
		if ( ! functionsAction ) {
			return;
		}

		// Get mapping key
		const mappingKey = `${ sourceIndex }-${ targetField }`;

		// Get current functions for this mapping
		if ( ! this.mappingFunctions ) {
			this.mappingFunctions = {};
		}
		if ( ! this.mappingFunctions[ mappingKey ] ) {
			this.mappingFunctions[ mappingKey ] = [];
		}

		// Load functions from server
		try {
			const response = await jQuery.ajax( {
				url: window.rslIeData.ajaxUrl,
				type: 'POST',
				data: {
					action: functionsAction,
					nonce: window.rslIeData.nonce,
				},
			} );

			if ( ! response.success ) {
				Utils.showNotice(
					rslIeData.i18n.failedToLoadFunctions ||
						'Failed to load transformations',
					'error'
				);
				return;
			}

			this.showFunctionModal( sourceIndex, targetField, response.data );
		} catch ( error ) {
			Utils.showNotice(
				( rslIeData.i18n.errorLoadingFunctions ||
					'Error loading functions' ) +
					': ' +
					error.message,
				'error'
			);
		}
	},

	/**
	 * Show function modal
	 */
	showFunctionModal( sourceIndex, targetField, functionsData ) {
		const mappingKey = `${ sourceIndex }-${ targetField }`;
		const currentFunctions = this.mappingFunctions[ mappingKey ] || [];
		const sourceField = this.fileData.columns[ sourceIndex ];

		// Store current editing context
		this.currentEditingMapping = { sourceIndex, targetField };

		// Create modal HTML (EXACTLY like export modal structure)
		const modalHtml = `
		<div id="rsl-ie-field-functions-modal" class="rsl-ie-modal" style="display:flex;">
			<div class="rsl-ie-modal-backdrop"></div>
			<div class="rsl-ie-modal-content rsl-ie-field-functions-modal-content">
				<div class="rsl-ie-modal-header">
					<h2 class="rsl-ie-modal-title">
						<span class="dashicons dashicons-admin-generic"></span>
						${
							window.rslIeData.i18n
								.fieldTransformationFunctions ||
							'Field Transformation Functions'
						}
					</h2>
					<button type="button" class="rsl-ie-modal-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="rsl-ie-modal-body">
					<!-- Field Info -->
					<div class="rsl-ie-field-info">
						<div class="rsl-ie-field-info-item">
							<strong>${ window.rslIeData.i18n.field || 'Field' }:</strong>
							<span class="rsl-ie-current-field-label">${ Utils.escapeHtml(
								sourceField
							) }</span>
						</div>
						<div class="rsl-ie-field-info-item">
							<strong>${ window.rslIeData.i18n.type || 'Type' }:</strong>
							<span class="rsl-ie-current-field-type">${ targetField }</span>
						</div>
					</div>

					<!-- Applied Transformations List -->
					<div class="rsl-ie-applied-functions">
						<h3>
							${ window.rslIeData.i18n.appliedFunctions || 'Applied Transformations' }
							<span class="rsl-ie-functions-count">(0)</span>
						</h3>
						
						<div class="rsl-ie-functions-pipeline" id="rsl-ie-functions-pipeline">
							<div class="rsl-ie-no-functions">
								<span class="dashicons dashicons-info"></span>
								<p>${
									window.rslIeData.i18n.noFunctionsApplied ||
									'No transformations applied yet. Add transformations from the list below.'
								}</p>
							</div>
							
							<div class="rsl-ie-function-items" id="rsl-ie-function-items">
								<!-- Transformations will be added here -->
							</div>
						</div>

						<div class="rsl-ie-pipeline-hint">
							<span class="dashicons dashicons-info"></span>
							${
								window.rslIeData.i18n.functionsAppliedInOrder ||
								'Transformations are applied in order from top to bottom. Drag to reorder.'
							}
						</div>
					</div>

					<!-- Available Transformations -->
					<div class="rsl-ie-available-functions">
						<h3>${
							window.rslIeData.i18n.availableFunctions ||
							'Available Transformations'
						}</h3>
						
						<div class="rsl-ie-functions-search">
							<input 
								type="text" 
								id="rsl-ie-functions-search" 
								class="regular-text" 
								placeholder="${
									window.rslIeData.i18n.searchFunctions ||
									'Search transformations...'
								}"
							>
							<span class="dashicons dashicons-search"></span>
						</div>

						<!-- Functions Filter -->
						<div class="rsl-ie-functions-filter">
							<label>
								<input type="radio" name="functions-filter" value="all" checked>
								${ window.rslIeData.i18n.all || 'All' }
							</label>
							<label>
								<input type="radio" name="functions-filter" value="library">
								${ window.rslIeData.i18n.library || 'Library' }
							</label>
							<label>
								<input type="radio" name="functions-filter" value="custom">
								${ window.rslIeData.i18n.custom || 'Custom' }
							</label>
						</div>

						<div class="rsl-ie-functions-list" id="rsl-ie-functions-list">
							<div class="rsl-ie-functions-loading">
								<span class="spinner is-active"></span>
								<p>${
									window.rslIeData.i18n.loadingFunctions ||
									'Loading transformations...'
								}</p>
							</div>
						</div>

						<!-- Quick Add Link -->
						<div class="rsl-ie-functions-quick-add">
							<a href="#" class="rsl-ie-create-new-function">
								<span class="dashicons dashicons-plus-alt"></span>
								${ window.rslIeData.i18n.createNewFunction || 'Create New Transformation' }
							</a>
						</div>
					</div>

					<!-- Preview Section -->
					<div class="rsl-ie-function-preview">
						<h3>${
							window.rslIeData.i18n.previewTransformation ||
							'Preview Transformation'
						}</h3>
						
						<div class="rsl-ie-preview-controls">
							<div class="rsl-ie-preview-input-group">
								<label for="rsl-ie-preview-input">
									${ window.rslIeData.i18n.testValue || 'Test Value' }:
								</label>
								<input 
									type="text" 
									id="rsl-ie-preview-input" 
									class="regular-text" 
									placeholder="${ window.rslIeData.i18n.enterTestValue || 'Enter test value...' }"
								>
							</div>
							<button type="button" class="button rsl-ie-test-pipeline">
								<span class="dashicons dashicons-media-code"></span>
								${ window.rslIeData.i18n.testPipeline || 'Test Pipeline' }
							</button>
						</div>

						<div class="rsl-ie-preview-result" id="rsl-ie-preview-result" style="display:none;">
							<div class="rsl-ie-preview-steps">
								<!-- Steps will be added dynamically -->
							</div>
						</div>
					</div>
				</div>
				<div class="rsl-ie-modal-footer">
					<button type="button" class="button button-secondary rsl-ie-modal-cancel">
						${ window.rslIeData.i18n.cancel || 'Cancel' }
					</button>
					<button type="button" class="button button-primary rsl-ie-save-field-functions">
						<span class="dashicons dashicons-yes"></span>
						${ window.rslIeData.i18n.applyFunctions || 'Apply Transformations' }
					</button>
				</div>
			</div>
		</div>
	`;

		// Remove existing modal
		jQuery( '#rsl-ie-field-functions-modal' ).remove();

		// Add to body
		jQuery( 'body' ).append( modalHtml );
		jQuery( 'body' ).addClass( 'rsl-ie-modal-open' );

		// Load current functions into pipeline
		this.loadCurrentFunctions( currentFunctions );

		// Populate available functions
		this.renderAvailableFunctions( functionsData );

		// Bind modal events
		this.bindFunctionModalEvents( sourceIndex, targetField );
	},

	/**
	 * Load current functions into pipeline
	 */
	loadCurrentFunctions( currentFunctions ) {
		const $container = jQuery( '#rsl-ie-function-items' );
		$container.empty();

		currentFunctions.forEach( ( func ) => {
			this.addFunctionToPipeline( func, false );
		} );

		this.updateFunctionsCount( currentFunctions.length );
		this.toggleNoFunctionsMessage();
	},

	/**
	 * Render available functions (like export)
	 */
	renderAvailableFunctions( functionsData ) {
		const $container = jQuery( '#rsl-ie-functions-list' );
		$container.empty();

		const functions = Array.isArray( functionsData.functions )
			? functionsData.functions
			: Object.entries( functionsData.snippets || {} ).map(
					( [ key, snippet ] ) => ( {
						...snippet,
						id: snippet.id || `snippet_${ key }`,
						category: snippet.category || 'library',
					} )
			  );

		if ( functions.length === 0 ) {
			$container.html( `
			<div class="rsl-ie-functions-empty-state">
				<span class="dashicons dashicons-info"></span>
				<p>${
					window.rslIeData.i18n.noFunctionsAvailableYet ||
					'No transformations available yet.'
				}</p>
			</div>
		` );
			return;
		}

		// Store for later use
		this.availableFunctions = functions;

		functions.forEach( ( func ) => {
			const functionId = func.id || '';
			const item = jQuery( '<div>' )
				.addClass( 'rsl-ie-function-list-item' )
				.attr( 'data-function-id', functionId )
				.attr( 'data-category', func.category || 'custom' )
				.html( `				<div class="rsl-ie-function-list-info">
					<span class="rsl-ie-function-list-name">${ Utils.escapeHtml(
						func.name
					) }</span>
					<span class="rsl-ie-function-list-desc">${ Utils.escapeHtml(
						func.description || ''
					) }</span>
				</div>
				<button type="button" class="button button-small rsl-ie-add-function-btn">${
					window.rslIeData.i18n.add || 'Add'
				}</button>
			` );

			item.find( '.rsl-ie-add-function-btn' ).on( 'click', () => {
				this.addFunctionToPipeline(
					{ id: functionId, name: func.name },
					true
				);
			} );

			$container.append( item );
		} );
	},

	/**
	 * Add function to pipeline
	 */
	addFunctionToPipeline( func, updateArray = true ) {
		const $container = jQuery( '#rsl-ie-function-items' );

		const item = jQuery( '<div>' )
			.addClass( 'rsl-ie-function-item' )
			.attr( 'data-function-id', func.id ).html( `
				<span class="rsl-ie-function-handle dashicons dashicons-menu"></span>
				<div class="rsl-ie-function-info">
					<strong class="rsl-ie-function-name">${ Utils.escapeHtml( func.name ) }</strong>
				</div>
				<div class="rsl-ie-function-actions">
					<button type="button" class="button-small rsl-ie-remove-function">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			` );

		// Remove function event
		item.find( '.rsl-ie-remove-function' ).on( 'click', () => {
			item.remove();
			this.updateFunctionsCount();
			this.toggleNoFunctionsMessage();
		} );

		$container.append( item );

		// Refresh sortable to include the new item
		if ( $container.data( 'ui-sortable' ) ) {
			$container.sortable( 'refresh' );
		}

		if ( updateArray ) {
			this.updateFunctionsCount();
		}

		this.toggleNoFunctionsMessage();
	},

	/**
	 * Update functions count
	 */
	updateFunctionsCount( count = null ) {
		const $countEl = jQuery( '.rsl-ie-functions-count' );
		if ( ! $countEl.length ) return;

		if ( count === null ) {
			count = jQuery(
				'#rsl-ie-function-items .rsl-ie-function-item'
			).length;
		}

		$countEl.text( `(${ count })` );
	},

	/**
	 * Toggle no functions message
	 */
	toggleNoFunctionsMessage() {
		const hasItems =
			jQuery( '#rsl-ie-function-items .rsl-ie-function-item' ).length > 0;
		jQuery( '.rsl-ie-no-functions' ).toggle( ! hasItems );
		jQuery( '#rsl-ie-function-items' ).toggle( hasItems );
	},

	/**
	 * Bind function modal events
	 */
	bindFunctionModalEvents( sourceIndex, targetField ) {
		const self = this;

		// Close modal functions
		const closeModal = function () {
			jQuery( '#rsl-ie-field-functions-modal' ).remove();
			jQuery( 'body' ).removeClass( 'rsl-ie-modal-open' );
		};

		// Close on backdrop click
		jQuery( '.rsl-ie-modal-backdrop' ).on( 'click', closeModal );

		// Close on X button
		jQuery( '.rsl-ie-modal-close' ).on( 'click', closeModal );

		// Close on Cancel button
		jQuery( '.rsl-ie-modal-cancel' ).on( 'click', closeModal );

		// Search transformations
		jQuery( '#rsl-ie-functions-search' ).on( 'input', function () {
			const query = jQuery( this ).val().toLowerCase();

			jQuery( '.rsl-ie-function-list-item' ).each( function () {
				const name = jQuery( this )
					.find( '.rsl-ie-function-list-name' )
					.text()
					.toLowerCase();
				const desc = jQuery( this )
					.find( '.rsl-ie-function-list-desc' )
					.text()
					.toLowerCase();

				jQuery( this ).toggle(
					name.includes( query ) || desc.includes( query )
				);
			} );
		} );

		// Filter functions (All / Library / Custom)
		jQuery( 'input[name="functions-filter"]' ).on( 'change', function () {
			const filterValue = jQuery( this ).val();

			jQuery( '.rsl-ie-function-list-item' ).each( function () {
				const category = jQuery( this ).data( 'category' );

				if ( filterValue === 'all' ) {
					jQuery( this ).show();
				} else if ( filterValue === 'library' ) {
					// Show library functions (snippets)
					jQuery( this ).toggle( category !== 'custom' );
				} else if ( filterValue === 'custom' ) {
					// Show addon transformations
					jQuery( this ).toggle( category === 'custom' );
				}
			} );
		} );

		// Create new function
		jQuery( '.rsl-ie-create-new-function' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( typeof rslIeData !== 'undefined' && rslIeData.functionsUrl ) {
				window.open( rslIeData.functionsUrl, '_blank' );
			}
		} );

		jQuery( '.rsl-ie-test-pipeline' ).on( 'click', function () {
			const testValue = jQuery( '#rsl-ie-preview-input' ).val();

			if ( ! testValue ) {
				Utils.showNotice(
					rslIeData.i18n.enterTestValue ||
						'Please enter a test value',
					'warning'
				);
				return;
			}

			const functions = [];
			jQuery( '#rsl-ie-function-items .rsl-ie-function-item' ).each(
				function () {
					functions.push( jQuery( this ).data( 'function-id' ) );
				}
			);

			if ( functions.length === 0 ) {
				Utils.showNotice(
					rslIeData.i18n.pleaseAddAtLeastOneFunction ||
						'Please add at least one function to test',
					'warning'
				);
				return;
			}

			self.testFunctionPipeline( testValue, functions );
		} );

		// Apply functions (Save button)
		jQuery( '.rsl-ie-save-field-functions' ).on( 'click', function () {
			const selectedFunctions = [];

			jQuery( '#rsl-ie-function-items .rsl-ie-function-item' ).each(
				function () {
					const functionId = jQuery( this ).data( 'function-id' );
					const functionName = jQuery( this )
						.find( '.rsl-ie-function-name' )
						.text();

					selectedFunctions.push( {
						id: functionId,
						name: functionName,
					} );
				}
			);

			self.applyFunctionsToMapping(
				sourceIndex,
				targetField,
				selectedFunctions
			);
			closeModal();
		} );

		// Initialize sortable for drag & drop reordering
		this.initFunctionPipelineSortable();
	},

	/**
	 * Initialize sortable for function pipeline
	 */
	initFunctionPipelineSortable() {
		const $container = jQuery( '#rsl-ie-function-items' );
		if ( ! $container.length || ! jQuery.fn.sortable ) return;

		// Destroy existing instance if present
		if ( $container.data( 'ui-sortable' ) ) {
			$container.sortable( 'destroy' );
		}

		$container.sortable( {
			handle: '.rsl-ie-function-handle',
			placeholder: 'rsl-ie-function-item-placeholder',
			axis: 'y',
		} );
	},

	/**
	 * Test function pipeline
	 */
	async testFunctionPipeline( testValue, functionIds ) {
		if ( ! this.areFieldTransformationsEnabled() ) {
			return;
		}
		const testAction = this.getFieldTransformationAction( 'test' );
		if ( ! testAction ) {
			return;
		}

		const $result = jQuery( '#rsl-ie-preview-result' );
		const $steps = $result.find( '.rsl-ie-preview-steps' );

		$steps.html(
			`<div class="rsl-ie-preview-loading"><span class="spinner is-active"></span> ${ window.rslIeData.i18n.testing }</div>`
		);
		$result.show();

		try {
			const response = await jQuery.ajax( {
				url: window.rslIeData.ajaxUrl,
				type: 'POST',
				data: {
					action: testAction,
					nonce: window.rslIeData.nonce,
					value: testValue,
					functions: functionIds,
				},
			} );
			if ( response.success && response.data.steps ) {
				let html = '';

				// Initial value
				html += `
				<div class="rsl-ie-preview-step">
					<div class="rsl-ie-step-label">${
						window.rslIeData.i18n.initialValue || 'Initial Value'
					}:</div>
					<div class="rsl-ie-step-value">${ Utils.escapeHtml(
						response.data.initial || testValue
					) }</div>
				</div>
			`;

				// Each step
				response.data.steps.forEach( ( step, index ) => {
					const stepNum = index + 1;
					html += `
					<div class="rsl-ie-preview-step">
						<div class="rsl-ie-step-label">${ stepNum }. ${ Utils.escapeHtml(
							step.function_name
						) }:</div>
						<div class="rsl-ie-step-value">${ Utils.escapeHtml( step.output ) }</div>
					</div>
				`;
				} );

				// Final result
				html += `
				<div class="rsl-ie-preview-step rsl-ie-preview-final">
					<div class="rsl-ie-step-label">${
						window.rslIeData.i18n.finalResult || 'Final Result'
					}:</div>
					<div class="rsl-ie-step-value"><strong>${ Utils.escapeHtml(
						response.data.final
					) }</strong></div>
				</div>
			`;

				$steps.html( html );
			} else {
				const message =
					response.message ||
					response.data?.message ||
					window.rslIeData.i18n.failedTestPipeline;

				$steps.html(
					`<div class="notice notice-error inline"><p>${ Utils.escapeHtml(
						message
					) }</p></div>`
				);
			}
		} catch ( error ) {
			const message =
				error?.responseJSON?.message ||
				error?.responseJSON?.data?.message ||
				error?.responseText ||
				error?.message ||
				window.rslIeData.i18n.failedTestPipeline;

			$steps.html(
				`<div class="notice notice-error inline"><p>${
					window.rslIeData.i18n.error
				}: ${ Utils.escapeHtml( message ) }</p></div>`
			);
		}
	},

	/**
	 * Apply functions to mapping
	 */
	applyFunctionsToMapping( sourceIndex, targetField, functions ) {
		const mappingKey = `${ sourceIndex }-${ targetField }`;

		// Store functions
		this.mappingFunctions[ mappingKey ] = functions;

		// Update mapping row display
		this.updateMappingRowFunctions( sourceIndex, targetField, functions );
	},

	/**
	 * Update mapping row with functions
	 */
	updateMappingRowFunctions( sourceIndex, targetField, functions ) {
		const $row = jQuery(
			`.rsl-ie-mapping-row[data-source-index="${ sourceIndex }"][data-target-field="${ targetField }"]`
		);

		// Remove existing functions display
		$row.find( '.rsl-ie-mapping-functions' ).remove();

		if ( functions.length === 0 ) {
			return;
		}

		// Add functions display
		let functionsHtml = '<div class="rsl-ie-mapping-functions">';

		functions.forEach( ( func, index ) => {
			functionsHtml += `
				<span class="rsl-ie-function-badge">
					${ Utils.escapeHtml( func.name ) }
					<button type="button" class="rsl-ie-remove-function" data-function-index="${ index }">×</button>
				</span>
			`;
		} );

		functionsHtml += '</div>';

		// Insert after target column
		$row.find( '.rsl-ie-target-col' ).after( functionsHtml );
	},

	/**
	 * Remove function from mapping
	 */
	removeFunction( sourceIndex, targetField, functionIndex ) {
		const mappingKey = `${ sourceIndex }-${ targetField }`;
		const functions = this.mappingFunctions[ mappingKey ] || [];

		// Remove function
		functions.splice( functionIndex, 1 );

		// Update display
		this.updateMappingRowFunctions( sourceIndex, targetField, functions );
	},

	/**
	 * Initialize field search
	 */
	initializeFieldSearch() {
		// Search source fields
		const $sourceSearch = jQuery( '.rsl-ie-search-source' );
		$sourceSearch.on( 'input', function () {
			const query = jQuery( this ).val().toLowerCase();
			jQuery( '.rsl-ie-field-card' ).each( function () {
				const fieldName = jQuery( this )
					.find( '.rsl-ie-field-name' )
					.text()
					.toLowerCase();
				jQuery( this ).toggle( fieldName.includes( query ) );
			} );
		} );

		// Clear source search button
		$sourceSearch
			.parent()
			.find( '.rsl-ie-clear-search' )
			.on( 'click', function ( e ) {
				e.preventDefault();
				$sourceSearch.val( '' ).focus().trigger( 'input' );
			} );

		// Search target fields
		const performSearch = function () {
			const query = jQuery( this ).val().toLowerCase().trim();

			// Store matched fields per group
			const groupMatches = {};

			// Filter fields and track which groups have matches
			jQuery( '.rsl-ie-target-field' ).each( function () {
				const $field = jQuery( this );
				const fieldName = $field
					.find( '.rsl-ie-field-label' )
					.text()
					.toLowerCase();
				const matches = query === '' || fieldName.includes( query );

				// Find parent group
				const $group = $field.closest( '.rsl-ie-field-group' );
				const groupIndex = $group.index();

				if ( ! groupMatches[ groupIndex ] ) {
					groupMatches[ groupIndex ] = 0;
				}

				if ( matches ) {
					groupMatches[ groupIndex ]++;
				}

				$field.toggle( matches );
			} );

			// Show/hide groups based on matched fields
			jQuery( '#rsl-ie-target-fields .rsl-ie-field-group' ).each(
				function () {
					const $group = jQuery( this );
					const groupIndex = $group.index();
					const hasMatches = groupMatches[ groupIndex ] > 0;
					$group.toggle( query === '' || hasMatches );
				}
			);
		};

		const $targetSearch = jQuery( '.rsl-ie-search-target' );
		$targetSearch.on( 'keyup input', performSearch );

		// Clear target search button
		$targetSearch
			.parent()
			.find( '.rsl-ie-clear-search' )
			.on( 'click', function ( e ) {
				e.preventDefault();
				$targetSearch.val( '' ).focus().trigger( 'input' );
			} );
	},

	/**
	 * Get target fields for content type
	 */
	getTargetFields( contentType ) {
		const fields = {
			post: [
				{
					value: 'post_title',
					label: window.rslIeData.i18n.fieldTitle || 'Title',
				},
				{
					value: 'post_content',
					label: window.rslIeData.i18n.fieldContent || 'Content',
				},
				{
					value: 'post_excerpt',
					label: window.rslIeData.i18n.fieldExcerpt || 'Excerpt',
				},
				{
					value: 'post_status',
					label: window.rslIeData.i18n.fieldStatus || 'Status',
				},
				{
					value: 'post_author',
					label: window.rslIeData.i18n.fieldAuthor || 'Author',
				},
				{
					value: 'post_date',
					label: window.rslIeData.i18n.fieldDate || 'Date',
				},
				{
					value: 'post_name',
					label: window.rslIeData.i18n.fieldSlug || 'Slug',
				},
				{
					value: 'is_sticky',
					label: window.rslIeData.i18n.fieldSticky || 'Sticky',
				},
				{
					value: 'categories',
					label:
						window.rslIeData.i18n.fieldCategories || 'Categories',
				},
				{
					value: 'tags',
					label: window.rslIeData.i18n.fieldTags || 'Tags',
				},
				{
					value: 'featured_image',
					label:
						window.rslIeData.i18n.fieldFeaturedImage ||
						'Featured Image',
				},
			],
			media: [
				{
					value: 'post_title',
					label: window.rslIeData.i18n.fieldTitle || 'Title',
				},
				{
					value: 'post_content',
					label:
						window.rslIeData.i18n.fieldDescription || 'Description',
				},
				{
					value: 'post_excerpt',
					label: window.rslIeData.i18n.fieldCaption || 'Caption',
				},
				{
					value: 'alt_text',
					label: window.rslIeData.i18n.fieldAltText || 'Alt Text',
				},
				{ value: 'guid', label: 'GUID' },
			],
		};

		return fields[ contentType ] || fields.post;
	},

	/**
	 * Auto-map fields
	 */
	autoMapFields( event ) {
		const $button = event
			? jQuery( event.currentTarget )
			: jQuery( '.rsl-ie-auto-map' );
		if ( $button.prop( 'disabled' ) ) {
			return;
		}

		this.setAutoMapLoading( true, $button );

		const contentType = jQuery(
			'input[name="content_type"]:checked'
		).val();
		const hasAcfSourceFields = jQuery( '.rsl-ie-field-card' )
			.toArray()
			.some( ( sourceCard ) =>
				String(
					jQuery( sourceCard ).data( 'source-field' ) || ''
				).startsWith( 'acf_' )
			);
		const hasAcfTargetFields =
			jQuery( '.rsl-ie-target-field[data-target-field^="acf_"]' ).length >
			0;

		if ( hasAcfSourceFields && ! hasAcfTargetFields ) {
			const request = this.loadACFFields( contentType );
			if ( request && typeof request.always === 'function' ) {
				request.always( () => this.runAutoMapFields() );
				return;
			}
		}

		this.runAutoMapFields();
	},

	setAutoMapLoading( isLoading, $button = jQuery( '.rsl-ie-auto-map' ) ) {
		if ( ! $button.length ) {
			return;
		}

		if ( isLoading ) {
			if ( ! $button.data( 'original-html' ) ) {
				$button.data( 'original-html', $button.html() );
			}

			$button
				.prop( 'disabled', true )
				.addClass( 'disabled is-loading' )
				.html(
					'<span class="dashicons dashicons-update rsl-ie-button-spinner"></span> ' +
						( window.rslIeData.i18n.mappingFields || 'Mapping...' )
				);
			return;
		}

		const originalHtml =
			$button.data( 'original-html' ) ||
			'<span class="dashicons dashicons-admin-generic"></span> Auto Map';
		$button
			.prop( 'disabled', false )
			.removeClass( 'disabled is-loading' )
			.html( originalHtml );
	},

	runAutoMapFields() {
		// Clear existing mappings
		this.clearFieldMapping();

		// Wait for DOM to be fully ready before mapping
		setTimeout( () => {
			const mappings = [];
			// First-row value of a column. A whole JSON object (shown as "{...}") can't fill a
			// single-value field such as Author ID; its "author.name" columns can.
			const sampleOf = ( index ) =>
				String(
					this.fileData?.preview?.data?.[ 0 ]?.[ index ] ?? ''
				).trim();
			const isObjectSample = ( index ) => /^\{/.test( sampleOf( index ) );
			const isDateSample = ( index ) =>
				/^\d{4}-\d{2}-\d{2}/.test( sampleOf( index ) );

			// Build a fast lookup for existing target fields (avoids O(N^2) scans).
			// Keyed by lowercased data-target-field.
			const targetByName = new Map();
			jQuery(
				'.rsl-ie-target-field:not(.rsl-ie-custom-field-template)'
			).each( ( i, el ) => {
				const $el = jQuery( el );
				const name = ( $el.data( 'target-field' ) || '' )
					.toString()
					.toLowerCase();
				if ( name ) {
					targetByName.set( name, $el );
				}
			} );

			// PASS 0: Auto-create target fields for prefixed source columns
			// (taxonomy_*, meta_*, acf_*) that don't yet have a matching target.
			// This lets Pass 1 (exact match) pick them up automatically.
			jQuery( '.rsl-ie-field-card' ).each( ( index, sourceCard ) => {
				const $sourceCard = jQuery( sourceCard );
				const sourceField = $sourceCard.data( 'source-field' );
				if ( ! sourceField ) return;

				// Skip if a target field with this exact name already exists.
				if ( targetByName.has( sourceField.toLowerCase() ) ) return;

				// WooCommerce CSV "Meta: key" columns become custom fields (meta_key).
				const metaColumn = String( sourceField )
					.replace( /^\uFEFF/, '' )
					.match( /^meta:\s*(\S+)$/i );
				let metaTargetName = sourceField;
				if ( metaColumn ) {
					metaTargetName = 'meta_' + metaColumn[ 1 ];
				}

				let fieldType = null;
				if ( metaColumn ) {
					fieldType = 'meta';
				} else if ( sourceField.startsWith( 'taxonomy_' ) ) {
					fieldType = 'taxonomy';
				} else if ( sourceField.startsWith( 'acf_' ) ) {
					fieldType = 'meta';
				} else if ( sourceField.startsWith( 'meta_' ) ) {
					fieldType = 'meta';
				} else if ( sourceField.startsWith( '_' ) ) {
					// Portable meta keys (Yoast, templates, etc.) are exported without a `meta_` prefix.
					// Auto-create a meta target so they can be exact-mapped in Pass 1.
					fieldType = 'meta';
				}

				if ( ! fieldType ) return;

				// Find the template group for this field type.
				const $template = jQuery(
					`.rsl-ie-custom-field-template[data-field-type="${ fieldType }"]`
				).first();
				if ( ! $template.length ) return;

				// For taxonomy fields, default format is 'name' (how this plugin exports terms).
				const taxonomyFormat = fieldType === 'taxonomy' ? 'name' : '';

				// Add a real target field with the full prefixed name so Pass 1 can exact-match it.
				const $newTarget = this.addCustomFieldToGroup(
					$template,
					metaTargetName,
					fieldType,
					false,
					taxonomyFormat
				);

				// Keep lookup map in sync for Pass 1.
				if ( $newTarget && $newTarget.length ) {
					targetByName.set( sourceField.toLowerCase(), $newTarget );
				}
			} );

			// PASS 1: Map exact matches first (highest priority)
			jQuery( '.rsl-ie-field-card' ).each( ( index, sourceCard ) => {
				const $sourceCard = jQuery( sourceCard );
				const sourceField = $sourceCard.data( 'source-field' );
				const sourceIndex = $sourceCard.data( 'source-index' );

				if ( ! sourceField ) return;

				const sourceFieldLower = sourceField.toLowerCase();
				const $targetField = targetByName.get( sourceFieldLower );
				if ( ! $targetField || ! $targetField.length ) return;
				if ( $targetField.hasClass( 'has-mapping' ) ) return;

				// Mark mapping on target (UI indicator).
				$targetField.find( '.rsl-ie-mapped-source' ).remove();
				const mappedHtml = `
					<div class="rsl-ie-mapped-source">
						<span class="rsl-ie-source-name">${ Utils.escapeHtml(
							$sourceCard.data( 'source-field' )
						) }</span>
						<span class="dashicons dashicons-no-alt rsl-ie-remove-mapping"></span>
					</div>
				`;
				$targetField.find( '.rsl-ie-field-info' ).append( mappedHtml );
				$targetField.addClass( 'has-mapping' );
				$targetField.data( 'mapped-source-index', sourceIndex );
				$targetField.data(
					'mapped-source-field',
					$sourceCard.data( 'source-field' )
				);

				// Accumulate mappings for the mapped-fields section (rendered in one pass).
				mappings.push( {
					sourceField: $sourceCard.data( 'source-field' ),
					sourceIndex,
					targetField: $targetField.data( 'target-field' ),
					fieldType: $targetField.data( 'field-type' ),
				} );

				$sourceCard.addClass( 'used mapped' );
			} );

			// PASS 1.5: Map well-known column headers (WooCommerce, Shopify, generic
			// spreadsheets) to the intended target field. This runs before fuzzy
			// matching so that, for example, a "Name" column becomes the title and is
			// not captured by the "post_name" (Slug) field through a partial match.
			const headerAliases = {
				post_title: [
					'name',
					'title',
					'product name',
					'product title',
					'post title',
					'page title',
				],
				// "wp:..", "content:encoded" and "dc:creator" come from WordPress export (WXR) files.
				post_name: [ 'handle', 'permalink', 'wp:post_name' ],
				// "html" and "published_at" are the column names of Ghost exports.
				post_content: [
					'body (html)',
					'body',
					'content',
					'html',
					'content:encoded',
				],
				post_date: [
					'published_at',
					'published at',
					'publish date',
					'wp:post_date',
				],
				post_excerpt: [
					'custom_excerpt',
					'custom excerpt',
					'excerpt:encoded',
				],
				product_cat: [
					'categories',
					'category',
					'product categories',
					'product category',
					// The readable term columns of this plugin's own post export.
					'category_names',
					'category names',
				],
				product_tag: [
					'tags',
					'tag',
					'product tags',
					'tag_names',
					'tag names',
				],
				product_url: [ 'external url', 'product url' ],
				button_text: [ 'button text' ],
				featured_image_url: [
					'feature_image',
					'feature image',
					'image src',
					'image url',
					'featured image',
					'featured image url',
					// Nested JSON objects arrive as "image.url" columns.
					'image.url',
					'thumbnail.url',
				],
				featured_image_alt: [ 'image.alt', 'thumbnail.alt' ],
				author_name: [ 'author.name', 'author.display_name' ],
				author_email: [ 'author.email' ],
				author_login: [
					'author.login',
					'author.username',
					'dc:creator',
				],
				sku: [ 'sku', 'variant sku' ],
				regular_price: [ 'variant price', 'price' ],
				product_images: [ 'images' ],
				// "children" is how WebToffee's export lists grouped products.
				grouped_products: [ 'grouped products', 'children' ],
				upsell_skus: [ 'upsells' ],
				crosssell_skus: [ 'cross-sells' ],
				download_limit: [ 'download limit' ],
				download_expiry: [ 'download expiry days' ],
				parent_sku: [ 'parent' ],
				post_status: [
					'published',
					'status',
					'post status',
					'wp:status',
				],
				product_type: [ 'type', 'product type' ],
				stock_status: [ 'in stock?' ],
				comment_status: [ 'allow customer reviews?' ],
				sold_individually: [ 'sold individually?' ],
				purchase_note: [ 'purchase note' ],
				menu_order: [ 'position' ],
				low_stock_amount: [ 'low stock amount' ],
				date_on_sale_from: [
					'date sale price starts',
					'sale_price_dates_from',
				],
				date_on_sale_to: [
					'date sale price ends',
					'sale_price_dates_to',
				],
				// Customer spreadsheets (CRM, mailing tools, other shops).
				billing_address_1: [
					'address',
					'address 1',
					'address1',
					'address line 1',
					'street',
					'street address',
				],
				billing_address_2: [
					'address 2',
					'address2',
					'address line 2',
					'apartment',
					'suite',
				],
				billing_city: [ 'city', 'town' ],
				billing_state: [ 'state', 'province', 'region', 'county' ],
				billing_postcode: [
					'zip',
					'zip code',
					'postcode',
					'postal code',
				],
				billing_country: [ 'country' ],
				billing_phone: [ 'phone', 'phone number', 'telephone' ],
				billing_company: [ 'company' ],
				date_created: [
					'customer since',
					'registered',
					'date registered',
					'registration date',
				],
				// Category and term spreadsheets.
				parent_slug: [
					'parent slug',
					'parent category',
					'parent name',
				],
				image_url: [ 'image', 'thumbnail', 'category image' ],
			};
			const reviewAliases = {};
			Object.entries( {
				source_comment_id: [
					'review id',
					'review_id',
					'id',
					'source review id',
				],
				product_sku: [ 'product sku', 'sku', 'variant sku' ],
				product_slug: [
					'product handle',
					'handle',
					'product slug',
					'slug',
				],
				product_title: [ 'product name', 'product title', 'product' ],
				product_id: [ 'product id', 'product_id' ],
				comment_author: [
					'reviewer name',
					'reviewer',
					'author',
					'author name',
					'name',
					'customer name',
					'display name',
				],
				comment_author_email: [
					'reviewer email',
					'email',
					'author email',
					'customer email',
				],
				comment_content: [
					'review',
					'review text',
					'review body',
					'body',
					'content',
					'comment',
					'message',
				],
				comment_date: [
					'review date',
					'date',
					'created at',
					'submitted at',
					'date created',
				],
				rating: [ 'rating', 'stars', 'score', 'star rating' ],
				verified: [
					'verified',
					'verified buyer',
					'verified purchase',
					'verified owner',
				],
				comment_approved: [
					'status',
					'approved',
					'published',
					'review status',
				],
				comment_parent: [
					'reply to',
					'parent',
					'parent id',
					'parent review id',
					'in reply to',
					'source_comment_parent_id',
				],
				comment_author_IP: [ 'ip', 'ip address' ],
			} ).forEach( ( [ targetKey, aliases ] ) =>
				aliases.forEach(
					( alias ) => ( reviewAliases[ alias ] = targetKey )
				)
			);
			// Blog comments exported from another site or a comment service use
			// similar headers; their targets are the comment fields of this type.
			const commentAliases = {};
			Object.entries( reviewAliases ).forEach( ( [ alias, target ] ) => {
				if (
					0 !== target.indexOf( 'product_' ) &&
					'rating' !== target &&
					'verified' !== target &&
					'source_comment_id' !== target
				) {
					commentAliases[ alias ] = target;
				}
			} );
			Object.entries( {
				source_comment_id: [
					'comment id',
					'comment_id',
					'id',
					'source comment id',
				],
				source_comment_parent_id: [
					'parent',
					'parent id',
					'parent comment id',
					'reply to',
					'in reply to',
				],
				post_slug: [ 'post slug', 'slug', 'post name' ],
				post_title: [ 'post title', 'post' ],
				post_permalink: [
					'post url',
					'post link',
					'permalink',
					'url of post',
				],
				comment_author_url: [ 'website', 'author url', 'url' ],
			} ).forEach( ( [ targetKey, aliases ] ) =>
				aliases.forEach(
					( alias ) => ( commentAliases[ alias ] = targetKey )
				)
			);
			const aliasLookup = new Map();
			Object.keys( headerAliases ).forEach( ( targetKey ) => {
				headerAliases[ targetKey ].forEach( ( alias ) => {
					if ( ! aliasLookup.has( alias ) ) {
						aliasLookup.set( alias, targetKey );
					}
				} );
			} );
			jQuery( '.rsl-ie-field-card:not(.used)' ).each(
				( index, sourceCard ) => {
					const $sourceCard = jQuery( sourceCard );
					const sourceField = $sourceCard.data( 'source-field' );
					const sourceIndex = $sourceCard.data( 'source-index' );
					if ( ! sourceField ) return;

					const normalizedSource = String( sourceField )
						.replace( /^\uFEFF/, '' )
						.trim()
						.toLowerCase();
					// Review exports from other stores and review apps use their own headers.
					const checkedType = jQuery(
						'input[name="content_type"]:checked'
					).val();
					let aliasTarget =
						( 'woo_review' === checkedType
							? reviewAliases[ normalizedSource ]
							: undefined ) ||
						( 'comment' === checkedType
							? commentAliases[ normalizedSource ]
							: undefined ) ||
						aliasLookup.get( normalizedSource );
					if ( ! aliasTarget ) {
						const attributeMatch = normalizedSource.match(
							/^attribute (\d+) (name|value\(s\)|visible|global)$/
						);
						const downloadMatch = normalizedSource.match(
							/^download (\d+) (name|url)$/
						);
						if ( downloadMatch ) {
							aliasTarget = `download_${ downloadMatch[ 1 ] }_${ downloadMatch[ 2 ] }`;
						}
						if ( attributeMatch ) {
							aliasTarget = `attribute_${ attributeMatch[ 1 ] }_${
								'value(s)' === attributeMatch[ 2 ]
									? 'values'
									: attributeMatch[ 2 ]
							}`;
						}
					}
					if ( ! aliasTarget ) return;
					if ( isObjectSample( sourceIndex ) ) return;
					// "Published" is a yes/no status in shop exports but a date in many feeds.
					if (
						'post_status' === aliasTarget &&
						isDateSample( sourceIndex )
					) {
						aliasTarget = 'post_date';
					}

					let $targetField = targetByName.get( aliasTarget );
					// Terms have one image field: "Image URL" columns go there.
					if (
						( ! $targetField || ! $targetField.length ) &&
						'featured_image_url' === aliasTarget
					) {
						$targetField = targetByName.get( 'image_url' );
					}
					// Blog posts have no product taxonomies: send "Categories" / "Tags"
					// columns to the post category and tag taxonomies instead.
					if (
						( ! $targetField || ! $targetField.length ) &&
						'post' ===
							jQuery( 'input[name="content_type"]:checked' ).val()
					) {
						const postTaxonomy = {
							product_cat: 'taxonomy_category',
							product_tag: 'taxonomy_post_tag',
						}[ aliasTarget ];
						if ( postTaxonomy ) {
							$targetField = targetByName.get( postTaxonomy );
							const $taxonomyTemplate = jQuery(
								'.rsl-ie-custom-field-template[data-field-type="taxonomy"]'
							).first();
							if (
								( ! $targetField || ! $targetField.length ) &&
								$taxonomyTemplate.length
							) {
								$targetField = this.addCustomFieldToGroup(
									$taxonomyTemplate,
									postTaxonomy,
									'taxonomy',
									false,
									'name'
								);
								if ( $targetField && $targetField.length ) {
									targetByName.set(
										postTaxonomy,
										$targetField
									);
								}
							}
						}
					}
					if ( ! $targetField || ! $targetField.length ) return;
					if ( $targetField.hasClass( 'has-mapping' ) ) return;

					$targetField.find( '.rsl-ie-mapped-source' ).remove();
					const aliasMappedHtml = `
						<div class="rsl-ie-mapped-source">
							<span class="rsl-ie-source-name">${ Utils.escapeHtml(
								$sourceCard.data( 'source-field' )
							) }</span>
							<span class="dashicons dashicons-no-alt rsl-ie-remove-mapping"></span>
						</div>
					`;
					$targetField
						.find( '.rsl-ie-field-info' )
						.append( aliasMappedHtml );
					$targetField.addClass( 'has-mapping' );
					$targetField.data( 'mapped-source-index', sourceIndex );
					$targetField.data(
						'mapped-source-field',
						$sourceCard.data( 'source-field' )
					);
					mappings.push( {
						sourceField: $sourceCard.data( 'source-field' ),
						sourceIndex,
						targetField: $targetField.data( 'target-field' ),
						fieldType: $targetField.data( 'field-type' ),
					} );
					$sourceCard.addClass( 'used mapped' );
				}
			);

			// PASS 2: Map remaining fields with fuzzy matching
			// In a WordPress export (WXR) file, the comment columns (wp:comment/...)
			// describe the post's comments, and link/guid/pubDate/description are
			// feed values. They must not be fuzzy-matched to post fields such as
			// the author email or an ACF field named "link".
			const $wxrCards = jQuery( '.rsl-ie-field-card' );
			const isWxr = $wxrCards
				.toArray()
				.some(
					( card ) =>
						String( jQuery( card ).data( 'source-field' ) ) ===
						'wp:post_type'
				);
			if ( isWxr ) {
				$wxrCards.removeClass( 'rsl-ie-no-auto' );
				$wxrCards.not( '.used' ).each( ( i, card ) => {
					const field = String(
						jQuery( card ).data( 'source-field' ) || ''
					).toLowerCase();
					if (
						field.startsWith( 'wp:comment/' ) ||
						[
							'link',
							'guid',
							'guid@ispermalink',
							'pubdate',
							'description',
						].includes( field )
					) {
						jQuery( card ).addClass( 'rsl-ie-no-auto' );
					}
				} );
			}
			const genericHeaders = [
				'status',
				'type',
				'unit',
				'value',
				'code',
				'count',
			];
			jQuery( '.rsl-ie-field-card:not(.used):not(.rsl-ie-no-auto)' ).each(
				( index, sourceCard ) => {
					const $sourceCard = jQuery( sourceCard );
					const sourceField = $sourceCard.data( 'source-field' );
					const sourceIndex = $sourceCard.data( 'source-index' );

					if ( ! sourceField ) return;

					const sourceFieldLower = sourceField.toLowerCase();
					let matched = false;
					if ( isObjectSample( sourceIndex ) ) return;

					jQuery(
						'.rsl-ie-target-field:not(.rsl-ie-custom-field-template)'
					).each( ( i, targetField ) => {
						if ( matched ) return;

						const $targetField = jQuery( targetField );
						const targetFieldData =
							$targetField.data( 'target-field' );

						if ( ! targetFieldData ) return;

						// Skip already mapped target fields
						if ( $targetField.hasClass( 'has-mapping' ) ) return;

						const targetFieldValue = targetFieldData.toLowerCase();
						const targetLabel = $targetField
							.find( '.rsl-ie-field-label' )
							.text()
							.toLowerCase();

						// Fuzzy matching: label match, normalized match, or partial match
						let matchType = null;

						if ( sourceFieldLower === targetLabel ) {
							matchType = 'label';
						} else if (
							sourceFieldLower.replace( /_/g, ' ' ) ===
							targetLabel
						) {
							matchType = 'normalized';
						} else if (
							sourceFieldLower.includes( targetFieldValue ) &&
							targetFieldValue.length > 2 &&
							// "Weight Unit" names the unit, not the weight.
							! /\bunits?$/.test( sourceFieldLower ) &&
							// Gallery videos are not gallery images.
							! /video/.test( sourceFieldLower )
						) {
							matchType = 'partial';
						} else if (
							targetFieldValue.includes( sourceFieldLower ) &&
							sourceFieldLower.length > 2 &&
							// A bare generic word ("Status", "Type") is too vague to
							// pick a field such as comment_status or product_type.
							! genericHeaders.includes( sourceFieldLower )
						) {
							matchType = 'partial';
						}

						if ( matchType ) {
							// Mark mapping on target (UI indicator).
							$targetField
								.find( '.rsl-ie-mapped-source' )
								.remove();
							const mappedHtml = `
								<div class="rsl-ie-mapped-source">
									<span class="rsl-ie-source-name">${ Utils.escapeHtml(
										$sourceCard.data( 'source-field' )
									) }</span>
									<span class="dashicons dashicons-no-alt rsl-ie-remove-mapping"></span>
								</div>
							`;
							$targetField
								.find( '.rsl-ie-field-info' )
								.append( mappedHtml );
							$targetField.addClass( 'has-mapping' );
							$targetField.data(
								'mapped-source-index',
								sourceIndex
							);
							$targetField.data(
								'mapped-source-field',
								$sourceCard.data( 'source-field' )
							);

							// Accumulate mappings for the mapped-fields section.
							mappings.push( {
								sourceField: $sourceCard.data( 'source-field' ),
								sourceIndex,
								targetField:
									$targetField.data( 'target-field' ),
								fieldType: $targetField.data( 'field-type' ),
							} );

							$sourceCard.addClass( 'used mapped' );
							matched = true;
						}
					} );
				}
			);

			// Render mapped fields section in a single DOM update (much faster than
			// appending one row per mapping).
			const $container = jQuery( '.rsl-ie-mapped-fields' );
			$container.find( '.rsl-ie-empty-state' ).hide();
			let rowsHtml = '';
			mappings.forEach( ( m ) => {
				const transformationButton =
					this.areFieldTransformationsEnabled()
						? `
							<button type="button" class="button button-small rsl-ie-add-function" data-source-index="${
								m.sourceIndex
							}" data-target-field="${ m.targetField }" title="${
								window.rslIeData.i18n
									.addTransformationFunction ||
								'Add transformation'
							}">
								<span class="dashicons dashicons-admin-tools"></span>
							</button>
					`
						: '';
				rowsHtml += `
					<div class="rsl-ie-mapping-row" data-source-index="${
						m.sourceIndex
					}" data-target-field="${ m.targetField }">
						<div class="rsl-ie-source-col">
							<span class="dashicons dashicons-media-spreadsheet"></span>
							<strong>${ Utils.escapeHtml( m.sourceField ) }</strong>
						</div>
						<div class="rsl-ie-arrow">→</div>
						<div class="rsl-ie-target-col">
							<span class="dashicons dashicons-wordpress"></span>
							<strong>${ m.targetField }</strong>
						</div>
						<div class="rsl-ie-mapping-actions">
							${ transformationButton }
							<button type="button" class="button button-small rsl-ie-remove-row-mapping" data-source-index="${
								m.sourceIndex
							}" data-target-field="${ m.targetField }" title="${
								window.rslIeData.i18n.removeMapping ||
								'Remove mapping'
							}">
								<span class="dashicons dashicons-no-alt"></span>
							</button>
						</div>
					</div>
				`;
			} );
			$container.html( rowsHtml );
		}, 50 );

		// Use setTimeout to ensure DOM is fully updated before counting
		setTimeout( () => {
			this.updateMappingStats();

			// Count actual mapped fields from DOM
			const usedSourceIndexes = new Set();
			jQuery( '.rsl-ie-mapping-row' ).each( function () {
				usedSourceIndexes.add( jQuery( this ).data( 'source-index' ) );
			} );
			const mappedCount = usedSourceIndexes.size;

			const message = (
				window.rslIeData.i18n.autoMappedFields ||
				'Auto-mapped %d fields'
			).replace( '%d', mappedCount );
			Utils.showNotice( message, 'success' );
			this.setAutoMapLoading( false );
		}, 150 );
	},

	/**
	 * Clear field mapping
	 */
	clearFieldMapping() {
		// Clear all mappings
		jQuery( '.rsl-ie-target-field' ).each( function () {
			jQuery( this ).find( '.rsl-ie-mapped-source' ).remove();
			jQuery( this ).removeClass( 'has-mapping' );
			jQuery( this ).removeData( 'mapped-source-index' );
			jQuery( this ).removeData( 'mapped-source-field' );
		} );

		// Unmark all source fields (remove 'used' class)
		jQuery( '.rsl-ie-field-card' ).removeClass( 'used' );
		jQuery( '.rsl-ie-target-field' ).removeClass(
			'rsl-ie-template-missing'
		);
		jQuery( '.rsl-ie-template-missing-note' ).remove();

		// Clear mapped fields section
		jQuery( '.rsl-ie-mapped-fields' ).html( `
			<div class="rsl-ie-empty-state">
				<span class="dashicons dashicons-info"></span>
				<p>${
					window.rslIeData.i18n.dragSourceColumns ||
					'Drag source columns to WordPress fields to create mappings'
				}</p>
			</div>
		` );

		// Clear all functions
		this.mappingFunctions = {};

		this.updateMappingStats();
	},

	/**
	 * Import type key used to group mapping templates.
	 *
	 * @return {string} Import type.
	 */
	getTemplateImportType() {
		const contentType =
			jQuery( 'input[name="content_type"]:checked' ).val() || '';
		if ( contentType === 'custom_post_types' ) {
			const postType = jQuery( '#rsl-ie-custom-post-type' ).val();
			return postType ? `custom_post_types_${ postType }` : contentType;
		}
		return contentType;
	},

	templateText( key, fallback ) {
		return window.rslIeData?.i18n?.[ key ] || fallback;
	},

	/**
	 * Show that a template button is working: disabled, spinner, short label.
	 *
	 * @param {Object}  $button Button.
	 * @param {boolean} busy    Busy state.
	 * @param {string}  label   Label while busy.
	 */
	setTemplateButtonBusy( $button, busy, label = '' ) {
		if ( ! $button || ! $button.length ) {
			return;
		}
		if ( busy ) {
			if ( undefined === $button.data( 'idle-html' ) ) {
				$button.data( 'idle-html', $button.html() );
			}
			$button
				.prop( 'disabled', true )
				.addClass( 'is-busy' )
				.attr( 'aria-busy', 'true' )
				.html(
					'<span class="dashicons dashicons-update rsl-ie-button-spinner" aria-hidden="true"></span> '
				)
				.append( document.createTextNode( label ) );
			return;
		}
		$button
			.prop( 'disabled', false )
			.removeClass( 'is-busy' )
			.removeAttr( 'aria-busy' );
		if ( undefined !== $button.data( 'idle-html' ) ) {
			$button.html( $button.data( 'idle-html' ) );
		}
	},

	/**
	 * Load the saved templates of the current import type into the selector.
	 *
	 * @param {string} selectId Template to select after loading.
	 */
	async loadMappingTemplates( selectId = '' ) {
		const $select = jQuery( '#rsl-ie-template-select' );
		if ( ! $select.length ) {
			return;
		}
		const $bar = jQuery( '#rsl-ie-mapping-templates' );
		const requestId = ( this.templateRequestId || 0 ) + 1;
		this.templateRequestId = requestId;
		$bar.addClass( 'is-loading' ).attr( 'aria-busy', 'true' );
		$select
			.prop( 'disabled', true )
			.empty()
			.append(
				jQuery( '<option>', {
					value: '',
					text: this.templateText(
						'loadingMappingTemplates',
						'Loading templates…'
					),
				} )
			);
		jQuery(
			'.rsl-ie-template-apply, .rsl-ie-template-rename, .rsl-ie-template-delete'
		).prop( 'disabled', true );
		try {
			const data = await Utils.ajax( 'rsl_ie_mapping_template_list', {
				import_type: this.getTemplateImportType(),
			} );
			this.mappingTemplates = data.templates || [];
		} catch ( error ) {
			this.mappingTemplates = [];
		}
		if ( requestId !== this.templateRequestId ) {
			return; // A newer request (another post type) is loading.
		}
		$bar.removeClass( 'is-loading' ).removeAttr( 'aria-busy' );
		$select.prop( 'disabled', false ).empty();
		if ( ! this.mappingTemplates.length ) {
			$select.append(
				jQuery( '<option>', {
					value: '',
					text: this.templateText(
						'noMappingTemplates',
						'— No saved templates —'
					),
				} )
			);
		} else {
			$select.append(
				jQuery( '<option>', {
					value: '',
					text: this.templateText(
						'chooseMappingTemplate',
						'— Choose a template —'
					),
				} )
			);
			this.mappingTemplates.forEach( ( template ) => {
				$select.append(
					jQuery( '<option>', {
						value: template.template_id,
						text: `${ template.name } (${ template.mapping_count })`,
					} )
				);
			} );
		}
		$select.val( selectId || this.activeTemplate?.template_id || '' );
		this.updateTemplateButtons();
	},

	updateTemplateButtons() {
		const hasSelection = !! jQuery( '#rsl-ie-template-select' ).val();
		jQuery(
			'.rsl-ie-template-apply, .rsl-ie-template-rename, .rsl-ie-template-delete'
		).prop( 'disabled', ! hasSelection );
	},

	/**
	 * Show the result of loading a template above the mapping.
	 *
	 * @param {string} html  Message HTML (already escaped).
	 * @param {string} level success|warning.
	 */
	showTemplateNotice( html, level = 'success' ) {
		const $notice = jQuery( '.rsl-ie-template-notice' );
		if ( ! html ) {
			$notice.hide().empty();
			return;
		}
		$notice
			.attr( 'class', `rsl-ie-template-notice notice notice-${ level }` )
			.html( html )
			.show();
	},

	/**
	 * Apply a saved template to the columns of the current file.
	 */
	async applySelectedTemplate() {
		const templateId = jQuery( '#rsl-ie-template-select' ).val();
		if ( ! templateId || ! this.fileData?.columns ) {
			return;
		}
		const $button = jQuery( '.rsl-ie-template-apply' );
		this.setTemplateButtonBusy(
			$button,
			true,
			this.templateText( 'loadingTemplate', 'Loading…' )
		);
		try {
			await this.loadAndApplyTemplate( templateId );
		} finally {
			this.setTemplateButtonBusy( $button, false );
		}
	},

	async loadAndApplyTemplate( templateId ) {
		let template;
		try {
			template = await Utils.ajax( 'rsl_ie_mapping_template_get', {
				template_id: templateId,
			} );
		} catch ( error ) {
			Utils.showNotice( String( error ), 'error' );
			return;
		}

		const needsAcf = ( template.mapping || [] ).some(
			( entry ) =>
				String( entry.target_field ).startsWith( 'acf_' ) &&
				! jQuery(
					`.rsl-ie-target-field[data-target-field="${ CSS.escape(
						entry.target_field
					) }"]`
				).length
		);
		if ( needsAcf ) {
			const request = this.loadACFFields(
				jQuery( 'input[name="content_type"]:checked' ).val()
			);
			if ( request && typeof request.always === 'function' ) {
				await new Promise( ( resolve ) => request.always( resolve ) );
			}
		}

		this.applyTemplate( template );
	},

	/**
	 * Map file columns to fields as the template says, matching columns by name.
	 *
	 * @param {Object} template Template from the server.
	 */
	applyTemplate( template ) {
		const columnKey = ( name ) =>
			String( name ?? '' )
				.replace( /^﻿/, '' )
				.trim()
				.replace( /\s+/g, ' ' )
				.toLowerCase();
		const columns = new Map();
		( this.fileData.columns || [] ).forEach( ( column, index ) => {
			const key = columnKey( column );
			if ( key && ! columns.has( key ) ) {
				columns.set( key, index );
			}
		} );

		this.clearFieldMapping();
		jQuery( '.rsl-ie-target-field' ).removeClass(
			'rsl-ie-template-missing'
		);
		jQuery( '.rsl-ie-template-missing-note' ).remove();

		const missing = [];
		const noTarget = [];
		let mapped = 0;

		( template.mapping || [] ).forEach( ( entry ) => {
			const targetField = String( entry.target_field || '' );
			let $target = jQuery(
				`.rsl-ie-target-field:not(.rsl-ie-custom-field-template)[data-target-field="${ CSS.escape(
					targetField
				) }"]`
			).first();

			if ( ! $target.length ) {
				const fieldType =
					entry.field_type ||
					( targetField.startsWith( 'taxonomy_' )
						? 'taxonomy'
						: /^(meta_|acf_|_)/.test( targetField )
						? 'meta'
						: '' );
				const $group = jQuery(
					`.rsl-ie-custom-field-template[data-field-type="${
						fieldType === 'taxonomy' ? 'taxonomy' : 'meta'
					}"]`
				).first();
				if (
					[ 'taxonomy', 'meta' ].includes( fieldType ) &&
					$group.length &&
					/^[\w\-:. ]{1,191}$/u.test( targetField )
				) {
					$target = this.addCustomFieldToGroup(
						$group,
						targetField,
						fieldType,
						false,
						entry.taxonomy_format || 'name'
					);
				}
			}

			if ( ! $target || ! $target.length ) {
				noTarget.push( entry );
				return;
			}

			if (
				entry.taxonomy_format &&
				$target.is( '[data-taxonomy-format]' )
			) {
				$target.attr( 'data-taxonomy-format', entry.taxonomy_format );
				$target.data( 'taxonomy-format', entry.taxonomy_format );
			}

			const sourceIndex = columns.get( columnKey( entry.source_field ) );
			if ( sourceIndex === undefined ) {
				missing.push( entry );
				$target.addClass( 'rsl-ie-template-missing' );
				$target.find( '.rsl-ie-field-info' ).append(
					jQuery( '<div>', {
						class: 'rsl-ie-template-missing-note',
						text: this.templateText(
							'templateColumnMissing',
							'Column "%s" is not in this file'
						).replace( '%s', entry.source_field ),
					} )
				);
				return;
			}

			const sourceField = this.fileData.columns[ sourceIndex ];
			const functions = ( entry.functions || entry.function_ids || [] )
				.map( ( func ) =>
					typeof func === 'object'
						? { id: func.id, name: func.name || `#${ func.id }` }
						: { id: func, name: `#${ func }` }
				)
				.filter( ( func ) => func.id );
			if ( functions.length && this.areFieldTransformationsEnabled() ) {
				this.mappingFunctions[ `${ sourceIndex }-${ targetField }` ] =
					functions;
			}

			this.createMapping(
				sourceField,
				sourceIndex,
				targetField,
				$target.data( 'field-type' ),
				$target
			);
			jQuery(
				`.rsl-ie-field-card[data-source-index="${ sourceIndex }"]`
			).addClass( 'used mapped' );
			mapped++;
		} );

		this.activeTemplate = {
			template_id: template.template_id,
			name: template.name,
		};
		this.pendingTemplateOptions = template.options || {};
		this.updateMappingStats();

		const esc = ( value ) => Utils.escapeHtml( String( value ) );
		let html = `<p><strong>${ esc(
			this.templateText(
				'templateApplied',
				'Template "%1$s" loaded: %2$d fields mapped.'
			)
				.replace( '%1$s', template.name )
				.replace( '%2$d', mapped )
		) }</strong> ${ esc(
			this.templateText(
				'templateOptionsNext',
				'Its import settings are filled in on the next step.'
			)
		) }</p>`;
		if ( missing.length ) {
			html += `<p>${ esc(
				this.templateText(
					'templateMissingColumns',
					'These columns of the template are not in this file, so their fields stay empty:'
				)
			) } ${ missing
				.map(
					( entry ) =>
						`<code>${ esc( entry.source_field ) }</code> → ${ esc(
							entry.target_field
						) }`
				)
				.join( ', ' ) }</p>`;
		}
		if ( noTarget.length ) {
			html += `<p>${ esc(
				this.templateText(
					'templateMissingFields',
					'These fields do not exist on this site and were skipped:'
				)
			) } ${ noTarget
				.map(
					( entry ) => `<code>${ esc( entry.target_field ) }</code>`
				)
				.join( ', ' ) }</p>`;
		}
		this.showTemplateNotice(
			html,
			missing.length || noTarget.length ? 'warning' : 'success'
		);
	},

	/**
	 * Fill step 5 with the settings of the loaded template, once.
	 */
	applyPendingTemplateOptions() {
		const options = this.pendingTemplateOptions;
		if ( ! options || typeof options !== 'object' ) {
			return;
		}
		this.pendingTemplateOptions = null;

		const setRadio = ( name, value ) => {
			if ( value === undefined || value === null || value === '' ) {
				return;
			}
			const $radio = jQuery(
				`input[name="${ name }"][value="${ CSS.escape(
					String( value )
				) }"]`
			);
			if ( $radio.length ) {
				$radio.prop( 'checked', true ).trigger( 'change' );
			}
		};
		const setChecked = ( selector, value ) => {
			if ( value === undefined ) {
				return;
			}
			const $box = jQuery( selector );
			const checked =
				value === true ||
				value === 'true' ||
				value === 1 ||
				value === '1';
			if ( $box.length && $box.is( ':checked' ) !== checked ) {
				$box.prop( 'checked', checked ).trigger( 'change' );
			}
		};

		if ( options.unique_field ) {
			const $unique = jQuery( '#rsl-ie-unique-field' );
			if (
				$unique.find(
					`option[value="${ CSS.escape( options.unique_field ) }"]`
				).length
			) {
				$unique.val( options.unique_field ).trigger( 'change' );
			}
		}
		setRadio(
			'if_exists',
			options.if_exists || options.duplicate_handling
		);
		setRadio( 'if_not_exists', options.if_not_exists );
		setRadio( 'media_duplicate_mode', options.media_duplicate_mode );
		if ( options.post_status ) {
			const $status = jQuery( '[name="post_status"]' );
			if (
				$status.find(
					`option[value="${ CSS.escape( options.post_status ) }"]`
				).length
			) {
				$status.val( options.post_status );
			}
		}
		if ( options.batch_size ) {
			jQuery( '[name="batch_size"]' ).val(
				parseInt( options.batch_size, 10 ) || 50
			);
		}
		setChecked( '[name="download_images"]', options.download_images );
		setChecked( '#rsl-ie-auto-import-media', options.auto_import_media );
		setChecked( '#rsl-ie-enable-replace-links', options.replace_links );
		this.setRowFilters( options.row_filters, options.row_filters_match );
		if ( options.default_taxonomy ) {
			jQuery( '#rsl-ie-default-taxonomy' ).val(
				options.default_taxonomy
			);
		}
		jQuery( '[name="date_order"]' ).val(
			[ 'dmy', 'mdy' ].includes( options.date_order )
				? options.date_order
				: 'auto'
		);

		const rules = Array.isArray( options.replace_links_rules )
			? options.replace_links_rules
			: [];
		if ( rules.length ) {
			const $rows = jQuery( '.rsl-ie-replace-links-row' );
			$rows.slice( 1 ).remove();
			rules.forEach( ( rule, index ) => {
				if ( index > 0 ) {
					this.addReplaceLinksRow();
				}
				const $row = jQuery( '.rsl-ie-replace-links-row' ).eq( index );
				$row.find( '.rsl-ie-replace-what' ).val( rule.search || '' );
				$row.find( '.rsl-ie-replace-to' ).val( rule.replace || '' );
			} );
			this.updateReplaceLinksRemoveButtons();
		}
	},

	/**
	 * Mapping entries with column names, transformations and field types.
	 *
	 * @return {Array} Template mapping.
	 */
	getTemplateMapping() {
		return this.getFieldMapping().map( ( entry ) => {
			const row = {
				source_field: entry.source_field,
				target_field: entry.target_field,
			};
			const $target = jQuery(
				`.rsl-ie-target-field[data-target-field="${ CSS.escape(
					entry.target_field
				) }"]`
			).first();
			if ( $target.data( 'field-type' ) ) {
				row.field_type = $target.data( 'field-type' );
			}
			if ( entry.taxonomy_format ) {
				row.taxonomy_format = entry.taxonomy_format;
			}
			const functions =
				this.mappingFunctions?.[
					`${ entry.source_index }-${ entry.target_field }`
				] || [];
			if ( this.areFieldTransformationsEnabled() && functions.length ) {
				row.functions = functions
					.map( ( func ) =>
						typeof func === 'object'
							? { id: func.id, name: func.name || '' }
							: { id: func, name: '' }
					)
					.filter( ( func ) => func.id );
			}
			return row;
		} );
	},

	/**
	 * Save the current mapping (and the step 5 settings) as a template.
	 *
	 * @param {Object} event Click event.
	 */
	async saveMappingTemplate( event ) {
		const $button = jQuery( event.currentTarget );
		const mapping = this.getTemplateMapping();
		if ( ! mapping.length ) {
			Utils.showNotice(
				this.templateText(
					'templateNeedsMapping',
					'Map at least one column before saving a template.'
				),
				'error'
			);
			return;
		}
		// eslint-disable-next-line no-alert
		const name = window.prompt(
			this.templateText(
				'templateNamePrompt',
				'Template name (for example "Supplier price list"):'
			),
			this.activeTemplate?.name || ''
		);
		if ( name === null || ! name.trim() ) {
			return;
		}
		const existing = ( this.mappingTemplates || [] ).find(
			( template ) =>
				template.name.toLowerCase() === name.trim().toLowerCase()
		);
		if (
			existing &&
			// eslint-disable-next-line no-alert
			! window.confirm(
				this.templateText(
					'templateReplaceConfirm',
					'A template named "%s" already exists. Replace it?'
				).replace( '%s', existing.name )
			)
		) {
			return;
		}

		const onStep5 = this.currentStep === 5;
		const data = {
			name: name.trim(),
			import_type: this.getTemplateImportType(),
			mapping,
			options:
				onStep5 || jQuery( '#rsl-ie-unique-field' ).val()
					? this.collectImportOptions()
					: this.pendingTemplateOptions || {},
			parser: {
				delimiter: this.fileData?.delimiter || '',
				encoding: this.fileData?.encoding || '',
			},
			overwrite: existing ? 1 : 0,
		};
		this.setTemplateButtonBusy(
			$button,
			true,
			this.templateText( 'savingTemplate', 'Saving…' )
		);
		try {
			const template = await Utils.ajax(
				'rsl_ie_mapping_template_save',
				data
			);
			this.activeTemplate = {
				template_id: template.template_id,
				name: template.name,
			};
			await this.loadMappingTemplates( template.template_id );
			Utils.showNotice(
				( onStep5
					? this.templateText(
							'templateSavedWithSettings',
							'Template "%s" saved with the field mapping and import settings.'
					  )
					: this.templateText(
							'templateSaved',
							'Template "%s" saved. Import settings from the next step are added when you save it there.'
					  )
				).replace( '%s', template.name ),
				'success'
			);
		} catch ( error ) {
			Utils.showNotice( String( error ), 'error' );
		} finally {
			this.setTemplateButtonBusy( $button, false );
		}
	},

	async renameMappingTemplate() {
		const templateId = jQuery( '#rsl-ie-template-select' ).val();
		const template = ( this.mappingTemplates || [] ).find(
			( item ) => item.template_id === templateId
		);
		if ( ! template ) {
			return;
		}
		// eslint-disable-next-line no-alert
		const name = window.prompt(
			this.templateText( 'templateRenamePrompt', 'New template name:' ),
			template.name
		);
		if ( name === null || ! name.trim() || name.trim() === template.name ) {
			return;
		}
		try {
			const saved = await Utils.ajax( 'rsl_ie_mapping_template_rename', {
				template_id: templateId,
				name: name.trim(),
			} );
			if ( this.activeTemplate?.template_id === templateId ) {
				this.activeTemplate.name = saved.name;
			}
			await this.loadMappingTemplates( templateId );
		} catch ( error ) {
			Utils.showNotice( String( error ), 'error' );
		}
	},

	async deleteMappingTemplate() {
		const templateId = jQuery( '#rsl-ie-template-select' ).val();
		const template = ( this.mappingTemplates || [] ).find(
			( item ) => item.template_id === templateId
		);
		if (
			! template ||
			// eslint-disable-next-line no-alert
			! window.confirm(
				this.templateText(
					'templateDeleteConfirm',
					'Delete the template "%s"? Imports made with it are not affected.'
				).replace( '%s', template.name )
			)
		) {
			return;
		}
		try {
			await Utils.ajax( 'rsl_ie_mapping_template_delete', {
				template_id: templateId,
			} );
			if ( this.activeTemplate?.template_id === templateId ) {
				this.activeTemplate = null;
			}
			await this.loadMappingTemplates();
		} catch ( error ) {
			Utils.showNotice( String( error ), 'error' );
		}
	},

	/**
	 * Import settings from step 5, as sent to the importer.
	 *
	 * @param {string} uniqueField Match key; defaults to the selected one.
	 * @return {Object} Options.
	 */
	collectImportOptions( uniqueField = null ) {
		uniqueField = uniqueField ?? jQuery( '#rsl-ie-unique-field' ).val();
		const options = {
			duplicate_handling:
				jQuery( 'input[name="if_exists"]:checked' ).val() || 'update',
			unique_field: uniqueField,
			if_exists:
				jQuery( 'input[name="if_exists"]:checked' ).val() || 'update',
			if_not_exists:
				jQuery( 'input[name="if_not_exists"]:checked' ).val() ||
				'create',
			post_status: jQuery( '[name="post_status"]' ).val(),
			post_type: jQuery( '[name="post_type"]' ).val(),
			download_images: jQuery( '[name="download_images"]' ).is(
				':checked'
			),
			batch_size: parseInt( jQuery( '[name="batch_size"]' ).val() ) || 1,
			auto_import_media: jQuery( '#rsl-ie-auto-import-media' ).is(
				':checked'
			),
			media_duplicate_mode:
				jQuery( 'input[name="media_duplicate_mode"]:checked' ).val() ||
				'skip',
			replace_links: jQuery( '#rsl-ie-enable-replace-links' ).is(
				':checked'
			),
			replace_links_rules: this.getReplaceLinksRules(),
			date_order: jQuery( '[name="date_order"]' ).val() || 'auto',
			row_filters: this.getRowFilters(),
			row_filters_match:
				jQuery( '[name="row_filters_match"]' ).val() || 'all',
		};
		const contentType = jQuery(
			'input[name="content_type"]:checked'
		).val();
		if ( contentType === 'custom_post_types' ) {
			options.custom_post_type = jQuery(
				'#rsl-ie-custom-post-type'
			).val();
		}
		if ( contentType === 'taxonomy' ) {
			options.default_taxonomy =
				jQuery( '#rsl-ie-default-taxonomy' ).val() || '';
		}
		return options;
	},

	/**
	 * Get field mapping
	 */
	getFieldMapping() {
		const mapping = [];

		jQuery( '.rsl-ie-mapping-row' ).each( function () {
			const $row = jQuery( this );
			const sourceIndex = $row.data( 'source-index' );
			const targetField = $row.data( 'target-field' );
			const sourceField = jQuery(
				`.rsl-ie-field-card[data-source-index="${ sourceIndex }"]`
			).data( 'source-field' );

			if ( sourceField && targetField ) {
				const entry = {
					source_index: sourceIndex,
					source_field: sourceField,
					target_field: targetField,
				};

				if ( ImportModule.areFieldTransformationsEnabled() ) {
					const mappingKey = `${ sourceIndex }-${ targetField }`;
					const functions =
						ImportModule.mappingFunctions?.[ mappingKey ] || [];
					const functionIds = functions
						.map( ( func ) =>
							typeof func === 'object' ? func.id : func
						)
						.filter( Boolean );

					if ( functionIds.length > 0 ) {
						entry.function_ids = functionIds;
					}
				}

				// Include taxonomy format when the target is a taxonomy field
				const $targetEl = jQuery(
					`.rsl-ie-target-field[data-target-field="${ targetField }"]`
				);
				if (
					$targetEl.data( 'field-type' ) === 'taxonomy' ||
					$targetEl.data( 'taxonomy-format' )
				) {
					entry.taxonomy_format =
						$targetEl.data( 'taxonomy-format' ) || 'name';
				}

				mapping.push( entry );
			}
		} );

		return mapping;
	},

	/**
	 * Toggle the import search/replace repeater.
	 *
	 * @param {Object} event Change event.
	 */
	toggleReplaceLinksRepeater( event ) {
		const $checkbox = jQuery( event.currentTarget );
		jQuery( '#rsl-ie-replace-links-repeater' ).toggle(
			$checkbox.is( ':checked' )
		);
	},

	/**
	 * Add another import search/replace rule row.
	 */
	addReplaceLinksRow() {
		const $repeater = jQuery( '#rsl-ie-replace-links-repeater' );
		const currentSiteUrl = $repeater.data( 'currentSiteUrl' ) || '';
		const $row = $repeater.find( '.rsl-ie-replace-links-row' ).first();
		const $clone = $row.clone();

		$clone.find( '.rsl-ie-replace-what' ).val( '' );
		$clone.find( '.rsl-ie-replace-to' ).val( currentSiteUrl );

		$repeater.find( '.rsl-ie-replace-links-rows' ).append( $clone );
		this.updateReplaceLinksRemoveButtons();
	},

	/**
	 * Remove one import search/replace rule row.
	 *
	 * @param {Object} event Click event.
	 */
	removeReplaceLinksRow( event ) {
		const $rows = jQuery( '.rsl-ie-replace-links-row' );

		if ( $rows.length <= 1 ) {
			const $row = jQuery( event.currentTarget ).closest(
				'.rsl-ie-replace-links-row'
			);
			$row.find( '.rsl-ie-replace-what' ).val( '' );
			$row.find( '.rsl-ie-replace-to' ).val(
				jQuery( '#rsl-ie-replace-links-repeater' ).data(
					'currentSiteUrl'
				) || ''
			);
			return;
		}

		jQuery( event.currentTarget )
			.closest( '.rsl-ie-replace-links-row' )
			.remove();
		this.updateReplaceLinksRemoveButtons();
	},

	/**
	 * Keep remove buttons sensible when only one replacement row remains.
	 */
	updateReplaceLinksRemoveButtons() {
		const $rows = jQuery( '.rsl-ie-replace-links-row' );
		$rows
			.find( '.rsl-ie-remove-replace-link' )
			.prop( 'disabled', $rows.length <= 1 );
	},

	/**
	 * Fill the row filter column selects with the source file columns.
	 */
	refreshRowFilterColumns() {
		const columns = ( this.fileData && this.fileData.columns ) || [];
		jQuery( '.rsl-ie-row-filter-field' ).each( function () {
			const $select = jQuery( this );
			const current = $select.val() || $select.data( 'pending' ) || '';
			let html = '';
			columns.forEach( ( column ) => {
				const name = String( column );
				html += `<option value="${ Utils.escapeHtml(
					name
				) }">${ Utils.escapeHtml( name ) }</option>`;
			} );
			if ( current && ! columns.map( String ).includes( current ) ) {
				html += `<option value="${ Utils.escapeHtml(
					current
				) }">${ Utils.escapeHtml( current ) }</option>`;
			}
			$select.html( html );
			if ( current ) {
				$select.val( current );
			}
		} );
	},

	addRowFilterRow() {
		const $last = jQuery( '.rsl-ie-row-filter-row' ).last();
		const $row = $last.clone();
		$row.find( '.rsl-ie-row-filter-value' ).val( '' ).show();
		$row.find( '.rsl-ie-row-filter-operator' ).val( 'equals' );
		$row.find( '.rsl-ie-row-filter-field' ).removeData( 'pending' );
		$last.after( $row );
		this.refreshRowFilterColumns();
		return $row;
	},

	getRowFilters() {
		if ( ! jQuery( '#rsl-ie-enable-row-filter' ).is( ':checked' ) ) {
			return [];
		}
		const filters = [];
		jQuery( '.rsl-ie-row-filter-row' ).each( function () {
			const $row = jQuery( this );
			const field = (
				$row.find( '.rsl-ie-row-filter-field' ).val() || ''
			)
				.toString()
				.trim();
			const operator =
				$row.find( '.rsl-ie-row-filter-operator' ).val() || 'equals';
			const value = (
				$row.find( '.rsl-ie-row-filter-value' ).val() || ''
			).toString();
			if ( field !== '' ) {
				filters.push( { field, operator, value } );
			}
		} );
		return filters;
	},

	setRowFilters( filters, match ) {
		const list = Array.isArray( filters ) ? filters : [];
		jQuery( '#rsl-ie-enable-row-filter' ).prop(
			'checked',
			list.length > 0
		);
		jQuery( '#rsl-ie-row-filter-repeater' ).toggle( list.length > 0 );
		jQuery( '[name="row_filters_match"]' ).val(
			match === 'any' ? 'any' : 'all'
		);
		jQuery( '.rsl-ie-row-filter-row' ).slice( 1 ).remove();
		list.forEach( ( filter, index ) => {
			const $row =
				index === 0
					? jQuery( '.rsl-ie-row-filter-row' ).first()
					: this.addRowFilterRow();
			$row.find( '.rsl-ie-row-filter-field' ).data(
				'pending',
				filter.field || ''
			);
			$row.find( '.rsl-ie-row-filter-operator' ).val(
				filter.operator || 'equals'
			);
			$row.find( '.rsl-ie-row-filter-value' )
				.val( filter.value || '' )
				.toggle(
					filter.operator !== 'is_empty' &&
						filter.operator !== 'is_not_empty'
				);
		} );
		this.refreshRowFilterColumns();
	},

	/**
	 * Collect import search/replace rules.
	 *
	 * @return {Array} Replacement rules.
	 */
	getReplaceLinksRules() {
		if ( ! jQuery( '#rsl-ie-enable-replace-links' ).is( ':checked' ) ) {
			return [];
		}

		const rules = [];
		jQuery( '.rsl-ie-replace-links-row' ).each( function () {
			const $row = jQuery( this );
			const search = ( $row.find( '.rsl-ie-replace-what' ).val() || '' )
				.toString()
				.trim();
			const replace = ( $row.find( '.rsl-ie-replace-to' ).val() || '' )
				.toString()
				.trim();

			if ( search !== '' ) {
				rules.push( {
					search,
					replace,
				} );
			}
		} );

		return rules;
	},

	/**
	 * Start import
	 */
	async startImport() {
		const $startButton = jQuery( '.rsl-ie-start-import' );

		if ( $startButton.prop( 'disabled' ) ) {
			return;
		}

		try {
			const contentType = jQuery(
				'input[name="content_type"]:checked'
			).val();
			const uniqueField = jQuery( '#rsl-ie-unique-field' ).val();

			// Validate unique field selection (REQUIRED)
			if ( ! uniqueField ) {
				Utils.showNotice(
					window.rslIeData.i18n.pleaseSelectUniqueField ||
						'Please select a field to check for existing items',
					'error'
				);
				return;
			}

			this.setStartImportLoading( true );

			const data = {
				file_path: this.fileData.file_path,
				import_type: contentType,
				format: this.fileData.format,
				delimiter: this.fileData.delimiter || ',',
				encoding: this.fileData.encoding || 'auto',
				mapping: this.getFieldMapping(),
				options: this.collectImportOptions( uniqueField ),
			};

			// Add custom post type if selected
			if ( contentType === 'custom_post_types' ) {
				data.options.custom_post_type = jQuery(
					'#rsl-ie-custom-post-type'
				).val();
			}

			// Add table name for database_table import
			if ( contentType === 'database_table' ) {
				const createTableIfMissing = jQuery(
					'#rsl-ie-create-table-if-missing'
				).is( ':checked' );
				const tableName = createTableIfMissing
					? jQuery( '#rsl-ie-new-table-name' ).val()
					: this.selectedTableName ||
					  jQuery( '#rsl-ie-import-table-name' ).val();

				if ( ! tableName ) {
					throw new Error(
						createTableIfMissing
							? 'Please enter a new database table name.'
							: window.rslIeData.i18n.pleaseSelectTable ||
							  'Please select a database table from the dropdown to continue.'
					);
				}

				if ( ! /^[A-Za-z0-9_]+$/.test( tableName ) ) {
					throw new Error(
						'Database table names can contain letters, numbers, and underscores only.'
					);
				}

				data.options.table_name = tableName;
				data.options.create_table_if_missing = createTableIfMissing
					? 1
					: 0;
			}

			const response = await Utils.ajax( 'rsl_ie_import_start', data );

			this.jobId = response.job_id;
			this.importStartTime = Date.now();
			this.importStartProcessed = 0;
			this.isCancellingImport = false;
			this.showStep( 6 );
			this.setImportPreparingState( true );
			this.startProgressTracking();
			this.startBatchProcessing();

			Utils.showNotice(
				rslIeData.i18n.importStartedSuccessfully ||
					'Import started successfully',
				'success'
			);
		} catch ( error ) {
			this.setStartImportLoading( false );
			Utils.handleError( error, 'Start import' );
		}
	},

	/**
	 * Toggle Start Import button loading state
	 *
	 * @param {boolean} isLoading Whether the import start request is running.
	 */
	setStartImportLoading( isLoading ) {
		const $button = jQuery( '.rsl-ie-start-import' );
		const $icon = $button.find( '.dashicons' );
		const $text = $button.find( '.rsl-ie-start-import-text' );

		if ( isLoading ) {
			if ( ! $button.data( 'original-text' ) ) {
				$button.data( 'original-text', $text.text() );
			}
			if ( ! $button.data( 'original-icon' ) ) {
				$button.data( 'original-icon', $icon.attr( 'class' ) );
			}

			$button.prop( 'disabled', true ).addClass( 'disabled is-starting' );
			$icon.attr(
				'class',
				'dashicons dashicons-update rsl-ie-button-spinner'
			);
			$text.text(
				window.rslIeData.i18n.startingImport || 'Starting import...'
			);
			return;
		}

		const originalText = $button.data( 'original-text' ) || 'Start Import';
		const originalIcon =
			$button.data( 'original-icon' ) || 'dashicons dashicons-download';

		$button.removeClass( 'is-starting' );
		$icon.attr( 'class', originalIcon );
		$text.text( originalText );
		this.toggleStartImportButton();
	},

	/**
	 * Start batch processing
	 */
	async startBatchProcessing() {
		if (
			this.jobId &&
			! this.isCancellingImport &&
			TabProgress.key !== 'import'
		)
			TabProgress.update( 'import', 0 );
		if (
			this.isCancellingImport ||
			! this.jobId ||
			this.isBatchProcessing
		) {
			return;
		}

		this.isBatchProcessing = true;

		try {
			const response = await Utils.ajax( 'rsl_ie_import_process_batch', {
				job_id: this.jobId,
			} );

			if ( this.isCancellingImport || ! this.jobId ) {
				return;
			}

			// Transform batch response to progress bar format
			const elapsedSec = this.importStartTime
				? ( Date.now() - this.importStartTime ) / 1000
				: 0;
			const percentage = response.progress || 0;
			TabProgress.update( 'import', percentage );
			const processed = response.offset || 0;
			const total = response.result?.total || 0;
			this.setImportPreparingState( percentage <= 0 && processed <= 0 );

			// items/sec based on elapsed time and the rows processed on this page
			if ( null === this.importStartProcessed ) {
				this.importStartProcessed = processed;
			}
			const doneHere = Math.max(
				0,
				processed - ( this.importStartProcessed || 0 )
			);
			const itemsPerSec = elapsedSec > 0 ? doneHere / elapsedSec : 0;

			// Remaining estimate: based on items/sec and remaining items
			let remainingSec = 0;
			if ( itemsPerSec > 0 && total > processed ) {
				remainingSec = ( total - processed ) / itemsPerSec;
			}

			const formatTime = ( sec ) => {
				sec = Math.round( sec );
				if ( sec < 60 ) return sec + 's';
				if ( sec < 3600 )
					return Math.floor( sec / 60 ) + 'm ' + ( sec % 60 ) + 's';
				return (
					Math.floor( sec / 3600 ) +
					'h ' +
					Math.floor( ( sec % 3600 ) / 60 ) +
					'm'
				);
			};

			const progressData = {
				percentage,
				processed,
				total,
				estimates: {
					elapsed_formatted: formatTime( elapsedSec ),
					remaining_formatted:
						remainingSec > 0 ? formatTime( remainingSec ) : '-',
					items_per_second: itemsPerSec,
				},
			};

			// Update progress
			Utils.updateProgressBar( jQuery( '.rsl-ie-step-6' ), progressData );

			if ( response.completed ) {
				// Import completed
				if (
					response.status === 'completed' &&
					! response.result?.error
				) {
					this.onImportComplete( response );
				} else {
					this.onImportFailed( response );
				}
			} else {
				// Process next batch
				this.batchTimeout = setTimeout( () => {
					this.batchTimeout = null;
					this.startBatchProcessing();
				}, 100 );
			}
		} catch ( error ) {
			if ( this.isCancellingImport || ! this.jobId ) {
				return;
			}
			this.onImportFailed( {
				error: error?.message || String( error ),
			} );
		} finally {
			this.isBatchProcessing = false;
		}
	},

	/**
	 * Start progress tracking
	 */
	startProgressTracking() {
		clearInterval( this.progressInterval );
		// Recover a stopped batch chain, including jobs finished by the worker.
		// The in-flight guard prevents concurrent processing of the same job.
		this.progressInterval = setInterval( () => {
			this.updateProgress();
			if (
				this.jobId &&
				! this.isCancellingImport &&
				! this.isBatchProcessing &&
				! this.batchTimeout
			) {
				this.startBatchProcessing();
			}
		}, 2000 );
	},

	/**
	 * Update import progress
	 */
	async updateProgress() {
		if ( ! this.jobId || this.isCancellingImport || this.isProgressPolling )
			return;
		const jobId = this.jobId;
		const tracker = this.progressInterval;
		this.isProgressPolling = true;
		try {
			const progress = await Utils.ajax( 'rsl_ie_import_get_progress', {
				job_id: jobId,
			} );
			if (
				this.jobId !== jobId ||
				this.progressInterval !== tracker ||
				this.isCancellingImport
			)
				return;
			if ( ! [ 'pending', 'processing' ].includes( progress.status ) )
				return;
			const elapsed = this.importStartTime
				? Math.round( ( Date.now() - this.importStartTime ) / 1000 )
				: 0;
			jQuery( '.rsl-ie-step-6 .rsl-ie-elapsed-time' ).text(
				elapsed + 's'
			);
			jQuery( '.rsl-ie-step-6 .rsl-ie-total' ).text(
				progress.total || 0
			);
			if (
				progress.total > 0 &&
				( ! progress.preparation ||
					progress.preparation.phase === 'ready' )
			) {
				const $label = jQuery( '.rsl-ie-import-preparing-text' );
				$label.text( $label.data( 'processing-text' ) );
			}
		} catch ( error ) {
			// A transient status-read failure must not interrupt the import worker.
		} finally {
			this.isProgressPolling = false;
		}
	},

	/**
	 * Adjust the completion card (title, subtitle, icon, error list) to the
	 * actual import outcome.
	 *
	 * @param {Object} result Import result counters and errors.
	 */
	renderImportOutcome( result ) {
		const i18n = window.rslIeData?.i18n || {};
		const $card = jQuery( '.rsl-ie-import-complete-card' );
		const $title = $card.find( '.rsl-ie-complete-title' );
		const $subtitle = $card.find( '.rsl-ie-complete-subtitle' );
		const $icon = $card.find( '.rsl-ie-complete-icon .dashicons' );

		// Remember the default (success) wording once.
		if ( undefined === $title.data( 'default-text' ) ) {
			$title.data( 'default-text', $title.text() );
			$subtitle.data( 'default-text', $subtitle.text() );
		}
		$card.find( '.rsl-ie-complete-errors' ).remove();
		$card.removeClass( 'rsl-ie-complete-warning rsl-ie-complete-error' );
		$icon
			.removeClass( 'dashicons-warning dashicons-dismiss' )
			.addClass( 'dashicons-yes-alt' );
		$title.text( $title.data( 'default-text' ) );
		$subtitle.text( $subtitle.data( 'default-text' ) );

		// The step header is written for the running state; switch it once the import is over.
		const $stepHeader = jQuery( '.rsl-ie-step-6 .rsl-ie-step-header' );
		$stepHeader
			.find( 'h2' )
			.text( i18n.importFinishedTitle || 'Import Finished' );
		$stepHeader
			.find( '.description' )
			.text(
				i18n.importFinishedDescription ||
					'The results of this import are shown below.'
			);

		const failed = parseInt( result.failed, 10 ) || 0;
		const mediaErrors = parseInt( result.media_errors, 10 ) || 0;
		if ( failed < 1 && mediaErrors > 0 ) {
			$card.addClass( 'rsl-ie-complete-warning' );
			$icon
				.removeClass( 'dashicons-yes-alt' )
				.addClass( 'dashicons-warning' );
			$title.text(
				i18n.importWithMediaWarningsTitle ||
					'Import Completed with Warnings'
			);
			$subtitle.text(
				(
					i18n.importWithMediaWarningsSubtitle ||
					'All rows were imported, but %d media files could not be downloaded, so those images will not show on this site. The addresses are listed below and in the Jobs Log.'
				).replace( '%d', mediaErrors )
			);
			const warnings = Array.isArray( result.media_warnings )
				? result.media_warnings
				: [];
			if ( warnings.length ) {
				const $list = jQuery(
					'<ul class="rsl-ie-complete-errors"></ul>'
				).css( {
					textAlign: 'left',
					maxWidth: '640px',
					margin: '12px auto 0',
					color: '#996800',
					overflowWrap: 'anywhere',
				} );
				warnings.slice( 0, 5 ).forEach( ( warning ) => {
					$list.append(
						jQuery( '<li></li>' ).text(
							String( warning.url || '' ) +
								' (' +
								( i18n.row || 'row' ) +
								' ' +
								warning.row +
								'): ' +
								String( warning.message || '' )
						)
					);
				} );
				$subtitle.after( $list );
			}
			return;
		}
		if ( failed < 1 ) {
			this.renderImportWarnings( $card, $title, $subtitle, $icon );
			return;
		}

		const done =
			( parseInt( result.success, 10 ) || 0 ) +
			( parseInt( result.updated, 10 ) || 0 ) +
			( parseInt( result.created, 10 ) || 0 );
		// Rows skipped on purpose (If Match Found = Skip) are not failures.
		const allFailed =
			0 === done && ! ( parseInt( result.skipped, 10 ) > 0 );

		$card.addClass(
			allFailed ? 'rsl-ie-complete-error' : 'rsl-ie-complete-warning'
		);
		$icon
			.removeClass( 'dashicons-yes-alt' )
			.addClass( allFailed ? 'dashicons-dismiss' : 'dashicons-warning' );
		$title.text(
			allFailed
				? i18n.importFailedTitle || 'Import Failed'
				: i18n.importWithErrorsTitle || 'Import Completed with Errors'
		);
		$subtitle.text(
			allFailed
				? i18n.importFailedSubtitle ||
						'No rows could be imported. Check the errors below and the Jobs Log.'
				: 1 === failed
				? i18n.importWithErrorsSubtitleOne ||
				  'One row could not be imported. Check the error below and the Jobs Log.'
				: (
						i18n.importWithErrorsSubtitle ||
						'%d rows could not be imported. Check the errors below and the Jobs Log.'
				  ).replace( '%d', failed )
		);

		// Show the first errors with row, field and how to fix them.
		const errors = Array.isArray( result.errors ) ? result.errors : [];
		if ( errors.length ) {
			const $box = jQuery( '<div class="rsl-ie-complete-errors"></div>' );
			const $list = jQuery( '<ul class="rsl-ie-row-errors"></ul>' );
			const seen = new Set();
			errors.forEach( ( error ) => {
				const message = String( error.message || error );
				const key = `${ error.field || '' }|${ message }`;
				if ( seen.size >= 5 || seen.has( key ) ) {
					return;
				}
				seen.add( key );
				const rows = errors
					.filter(
						( e ) =>
							`${ e.field || '' }|${ String(
								e.message || e
							) }` === key
					)
					.map( ( e ) => e.row )
					.filter( ( row ) => undefined !== row );
				const $item = jQuery( '<li></li>' );
				const where = rows.length
					? ( 1 === rows.length
							? i18n.rowLabel || 'Row'
							: i18n.rowsLabel || 'Rows' ) +
					  ' ' +
					  rows.slice( 0, 5 ).join( ', ' ) +
					  ( rows.length > 5 ? ', …' : '' )
					: '';
				$item.append(
					jQuery( '<strong></strong>' ).text(
						[ where, error.field ].filter( Boolean ).join( ' · ' )
					)
				);
				$item.append(
					jQuery(
						'<span class="rsl-ie-row-error-message"></span>'
					).text( ( where || error.field ? ': ' : '' ) + message )
				);
				if ( error.hint ) {
					$item.append(
						jQuery(
							'<span class="rsl-ie-row-error-hint"></span>'
						).text( error.hint )
					);
				}
				$list.append( $item );
			} );
			$box.append( $list );
			if ( this.jobId ) {
				const $download = jQuery(
					'<button type="button" class="button rsl-ie-download-errors"></button>'
				)
					.append(
						'<span class="dashicons dashicons-download" aria-hidden="true"></span> '
					)
					.append(
						document.createTextNode(
							i18n.downloadErrorRows ||
								'Download rows with errors (CSV)'
						)
					);
				$download.on( 'click', async () => {
					$download.prop( 'disabled', true );
					try {
						await Utils.downloadJobErrorsCsv( this.jobId );
					} catch ( e ) {
						Utils.showNotice( String( e ), 'error' );
					} finally {
						$download.prop( 'disabled', false );
					}
				} );
				$box.append(
					jQuery(
						'<p class="rsl-ie-download-errors-wrap"></p>'
					).append( $download )
				);
			}
			$subtitle.after( $box );
		}
	},

	/**
	 * All rows were imported, but the job log may hold notes the user should see
	 * (for example, an author that was not found). Show them on the card.
	 *
	 * @param {Object} $card     Completion card.
	 * @param {Object} $title    Card title.
	 * @param {Object} $subtitle Card subtitle.
	 * @param {Object} $icon     Card icon.
	 */
	async renderImportWarnings( $card, $title, $subtitle, $icon ) {
		const jobId = this.jobId;
		if ( ! jobId ) {
			return;
		}
		let logs = [];
		try {
			const response = await Utils.ajax( 'rsl_ie_job_get_logs', {
				job_id: jobId,
				level: 'warning',
				limit: 100,
			} );
			logs = Array.isArray( response?.logs ) ? response.logs : [];
		} catch ( e ) {
			return;
		}
		if ( ! logs.length || jobId !== this.jobId ) {
			return;
		}
		const i18n = window.rslIeData?.i18n || {};
		$card.find( '.rsl-ie-complete-errors' ).remove();
		$card.addClass( 'rsl-ie-complete-warning' );
		$icon
			.removeClass( 'dashicons-yes-alt' )
			.addClass( 'dashicons-warning' );
		$title.text(
			i18n.importWithWarningsTitle || 'Import Completed with Warnings'
		);
		$subtitle.text(
			1 === logs.length
				? i18n.importWithWarningsSubtitleOne ||
						'All rows were imported. One note needs your attention; it is also in the Jobs Log.'
				: (
						i18n.importWithWarningsSubtitle ||
						'All rows were imported. %d notes need your attention; they are also in the Jobs Log.'
				  ).replace( '%d', logs.length )
		);
		const $list = jQuery( '<ul class="rsl-ie-complete-errors"></ul>' ).css(
			{
				textAlign: 'left',
				maxWidth: '640px',
				margin: '12px auto 0',
				color: '#996800',
				overflowWrap: 'anywhere',
			}
		);
		logs.slice( 0, 5 ).forEach( ( log ) => {
			$list.append(
				jQuery( '<li></li>' ).text( String( log.message || '' ) )
			);
		} );
		if ( logs.length > 5 ) {
			$list.append( jQuery( '<li></li>' ).text( '…' ) );
		}
		$subtitle.after( $list );
	},

	/**
	 * Handle import completion
	 */
	onImportComplete( response ) {
		clearInterval( this.progressInterval );
		this.progressInterval = null;
		TabProgress.finish( 'import' );
		const result = response.result || {};
		this.setImportPreparingState( false );

		// Hide progress, show results
		jQuery( '.rsl-ie-progress-container' ).hide();
		jQuery( '.rsl-ie-import-results' ).show();
		jQuery( '.rsl-ie-import-complete-card' ).fadeIn();

		// Update statistics
		jQuery( '.rsl-ie-result-success' ).text( result.success || 0 );
		jQuery( '.rsl-ie-result-updated' ).text( result.updated || 0 );
		jQuery( '.rsl-ie-result-created' ).text( result.created || 0 );
		jQuery( '.rsl-ie-result-skipped' ).text( result.skipped || 0 );
		jQuery( '.rsl-ie-result-failed' ).text( result.failed || 0 );

		// Reflect the real outcome in the headline: a run where rows failed must not
		// be presented as a plain success.
		this.renderImportOutcome( result );

		// Calculate duration using the client-side start time for accuracy.
		// Fallback to server job timestamps only when the page was refreshed
		// mid-import (start time not in memory).
		const formatDuration = ( sec ) => {
			sec = Math.max( 0, Math.round( sec ) );
			if ( sec < 60 ) return sec + 's';
			if ( sec < 3600 )
				return Math.floor( sec / 60 ) + 'm ' + ( sec % 60 ) + 's';
			return (
				Math.floor( sec / 3600 ) +
				'h ' +
				Math.floor( ( sec % 3600 ) / 60 ) +
				'm'
			);
		};
		if ( this.importStartTime ) {
			const durSec = ( Date.now() - this.importStartTime ) / 1000;
			jQuery( '.rsl-ie-result-duration' ).text(
				formatDuration( durSec )
			);
		} else {
			const jobData = response.job_data || {};
			if ( jobData.started_at && jobData.completed_at ) {
				const durSec =
					( new Date( jobData.completed_at ) -
						new Date( jobData.started_at ) ) /
					1000;
				jQuery( '.rsl-ie-result-duration' ).text(
					formatDuration( durSec )
				);
			}
		}

		// Update buttons
		jQuery( '.rsl-ie-cancel-import' ).hide();
		jQuery( '.rsl-ie-new-import' ).show();
	},

	/**
	 * Handle import failure
	 */
	onImportFailed( response ) {
		clearTimeout( this.batchTimeout );
		this.batchTimeout = null;
		clearInterval( this.progressInterval );
		this.progressInterval = null;
		TabProgress.finish( 'import', 'failed' );
		this.setImportPreparingState( false );
		const message =
			response?.result?.error ||
			response?.error ||
			response?.status ||
			'Unknown error';
		Utils.showNotice(
			( rslIeData.i18n.importFailed || 'Import failed' ) + ': ' + message,
			'error'
		);

		// Replace the running state with a failed result card, so the step never
		// keeps showing "Preparing import..." after the job has stopped.
		const i18n = window.rslIeData?.i18n || {};
		const result = response?.result || {};
		jQuery( '.rsl-ie-progress-container' ).hide();
		jQuery( '.rsl-ie-import-results' ).show();
		jQuery( '.rsl-ie-import-complete-card' ).fadeIn();
		jQuery( '.rsl-ie-result-success' ).text( result.success || 0 );
		jQuery( '.rsl-ie-result-updated' ).text( result.updated || 0 );
		jQuery( '.rsl-ie-result-created' ).text( result.created || 0 );
		jQuery( '.rsl-ie-result-skipped' ).text( result.skipped || 0 );
		jQuery( '.rsl-ie-result-failed' ).text( result.failed || 0 );
		this.renderImportOutcome( { failed: 0 } );
		const $card = jQuery( '.rsl-ie-import-complete-card' );
		$card
			.removeClass( 'rsl-ie-complete-warning' )
			.addClass( 'rsl-ie-complete-error' );
		$card
			.find( '.rsl-ie-complete-icon .dashicons' )
			.removeClass( 'dashicons-yes-alt dashicons-warning' )
			.addClass( 'dashicons-dismiss' );
		$card
			.find( '.rsl-ie-complete-title' )
			.text( i18n.importFailedTitle || 'Import Failed' );
		$card.find( '.rsl-ie-complete-subtitle' ).text( message );
		jQuery( '.rsl-ie-cancel-import' ).hide();
		jQuery( '.rsl-ie-new-import' ).show();
	},

	/**
	 * Cancel import
	 */
	async cancelImport() {
		if ( ! confirm( window.rslIeData.i18n.confirmCancelImportStep ) ) {
			return;
		}

		this.isCancellingImport = true;
		this.setImportPreparingState( false );
		this.setCancelImportLoading( true );
		if ( this.batchTimeout ) {
			clearTimeout( this.batchTimeout );
			this.batchTimeout = null;
		}

		try {
			await Utils.ajax( 'rsl_ie_import_cancel', { job_id: this.jobId } );
			Utils.showNotice(
				rslIeData.i18n.importCancelled || 'Import cancelled',
				'info'
			);
			this.resetWizard();
		} catch ( error ) {
			this.isCancellingImport = false;
			this.setCancelImportLoading( false );
			Utils.handleError( error, 'Cancel import' );
		}
	},

	/**
	 * Toggle Cancel Import button loading state.
	 *
	 * @param {boolean} isLoading Whether the cancel request is running.
	 */
	setCancelImportLoading( isLoading ) {
		const $button = jQuery( '.rsl-ie-cancel-import' );
		const $icon = $button.find( '.dashicons' );

		if ( isLoading ) {
			if ( ! $button.data( 'original-text' ) ) {
				$button.data( 'original-text', $button.text().trim() );
			}
			if ( ! $button.data( 'original-icon' ) ) {
				$button.data( 'original-icon', $icon.attr( 'class' ) );
			}

			$button
				.prop( 'disabled', true )
				.addClass( 'disabled is-cancelling' );
			$icon.attr(
				'class',
				'dashicons dashicons-update rsl-ie-button-spinner'
			);
			$button
				.contents()
				.filter( function () {
					return this.nodeType === 3;
				} )
				.remove();
			$button.append(
				' ' +
					( window.rslIeData.i18n.cancellingImport ||
						'Cancelling...' )
			);
			return;
		}

		const originalText = $button.data( 'original-text' ) || 'Cancel Import';
		const originalIcon =
			$button.data( 'original-icon' ) || 'dashicons dashicons-no';

		$button
			.prop( 'disabled', false )
			.removeClass( 'disabled is-cancelling' );
		$icon.attr( 'class', originalIcon );
		$button
			.contents()
			.filter( function () {
				return this.nodeType === 3;
			} )
			.remove();
		$button.append( ' ' + originalText );
	},

	/**
	 * Toggle logs visibility
	 */
	toggleLogs() {
		jQuery( '.rsl-ie-logs-container' ).slideToggle();
	},

	setImportPreparingState( isPreparing ) {
		const $container = jQuery(
			'.rsl-ie-step-6 .rsl-ie-progress-container'
		);
		if ( ! $container.length ) {
			return;
		}

		$container.toggleClass( 'is-preparing', !! isPreparing );
	},

	/**
	 * Reset wizard
	 */
	resetWizard() {
		clearInterval( this.progressInterval );
		this.progressInterval = null;
		TabProgress.reset();
		// Fully reset Step 2 upload UI (it can be hidden after a successful upload).
		this.removeFile();

		this.currentStep = 1;
		this.uploadedFile = null;
		this.fileData = null;
		this.jobId = null;
		this.importStartTime = null;
		this.isCancellingImport = false;
		this.isBatchProcessing = false;
		this.pendingTemplateOptions = null;
		if ( this.batchTimeout ) {
			clearTimeout( this.batchTimeout );
			this.batchTimeout = null;
		}
		this.setStartImportLoading( false );
		this.setCancelImportLoading( false );
		this.setAutoMapLoading( false );
		this.setImportPreparingState( false );

		jQuery(
			'#rsl-ie-import input[type="text"], #rsl-ie-import input[type="file"]'
		).val( '' );
		jQuery( '#rsl-ie-import input[type="radio"]:first' ).prop(
			'checked',
			true
		);
		jQuery( '.rsl-ie-file-info' ).hide();
		jQuery( '.rsl-ie-file-conversion' ).hide().text( '' );
		jQuery( '.rsl-ie-upload-area' ).show();
		jQuery( '.rsl-ie-upload-placeholder, .rsl-ie-url-import' ).show();
		jQuery( '.rsl-ie-upload-progress' ).hide();
		jQuery( '.rsl-ie-format-options' ).hide();
		jQuery( '.rsl-ie-import-results' ).hide();

		this.showStep( 1 );
	},

	/**
	 * Load ACF fields dynamically from server
	 */
	loadACFFields( contentType ) {
		if ( typeof rslIeData === 'undefined' ) {
			return;
		}

		if ( ! this.isIntegrationActive( 'acf' ) ) {
			return;
		}

		// For custom post types, ACF field groups are attached to the
		// post type chosen on this step, not to "custom_post_types".
		const postType =
			contentType === 'custom_post_types'
				? jQuery( '#rsl-ie-custom-post-type' ).val() || ''
				: contentType;
		if ( ! postType ) {
			return;
		}

		return jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_acf_fields',
				nonce: rslIeData.nonce,
				post_type: postType,
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data.fields &&
					response.data.fields.length > 0
				) {
					this.renderACFFields( response.data.fields );
				}
			},
			error: ( xhr, status, error ) => {},
		} );
	},

	/**
	 * Render ACF fields as target fields
	 */
	renderACFFields( fields ) {
		const $container = jQuery( '#rsl-ie-target-fields' );

		// Initial loading and an immediate Auto Map can finish concurrently.
		// Keep the first rendered group and its mappings when the second returns.
		if ( $container.find( '.rsl-ie-acf-fields-group' ).length ) {
			return;
		}

		// Create ACF group
		let html = `<div class="rsl-ie-field-group rsl-ie-acf-fields-group">`;
		html += `<div class="rsl-ie-field-group-label">🔧 ACF Fields</div>`;

		fields.forEach( ( field ) => {
			html += `
				<div class="rsl-ie-target-field" data-target-field="acf_${
					field.name
				}" data-field-type="${ field.type || 'string' }">
					<div class="rsl-ie-field-icon">
						<span class="dashicons dashicons-admin-settings"></span>
					</div>
					<div class="rsl-ie-field-info">
						<div class="rsl-ie-field-label">${ field.label }</div>
						<span class="rsl-ie-field-type-badge">acf:${ field.type }</span>
					</div>
				</div>
			`;
		} );

		html += `</div>`;

		// Append to container
		$container.append( html );
	},

	/**
	 * Load Yoast SEO fields dynamically from server
	 */
	loadYoastFields( contentType ) {
		if ( typeof rslIeData === 'undefined' ) {
			return;
		}

		// Don't load Yoast for these content types
		const excludedTypes = [
			'media',
			'user',
			'comment',
			'menu',
			'taxonomy',
			'database_table',
			'woo_attribute',
			'woo_coupon',
			'woo_order',
			'woo_review',
			'woo_refund',
			'woo_customer',
		];

		if (
			! this.isIntegrationActive( 'yoast' ) ||
			excludedTypes.includes( contentType )
		) {
			return;
		}

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_yoast_fields',
				nonce: rslIeData.nonce,
				post_type:
					contentType === 'custom_post_types'
						? jQuery( '#rsl-ie-custom-post-type' ).val() || ''
						: contentType,
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data.fields &&
					response.data.fields.length > 0
				) {
					this.renderYoastFields( response.data.fields );
				}
			},
			error: ( xhr, status, error ) => {},
		} );
	},

	/**
	 * Render Yoast SEO fields as target fields
	 */
	renderYoastFields( fields ) {
		const $container = jQuery( '#rsl-ie-target-fields' );

		// Create Yoast group
		let html = `<div class="rsl-ie-field-group rsl-ie-yoast-fields-group">`;
		html += `<div class="rsl-ie-field-group-label">📊 Yoast SEO</div>`;

		fields.forEach( ( field ) => {
			// Clean up field name (remove _ prefix)
			const fieldName = field.name.replace( /^_/, '' );

			html += `
				<div class="rsl-ie-target-field" data-target-field="${ fieldName }" data-field-type="string">
					<div class="rsl-ie-field-icon">
						<span class="dashicons dashicons-chart-line"></span>
					</div>
					<div class="rsl-ie-field-info">
						<div class="rsl-ie-field-label">${ field.label }</div>
						<span class="rsl-ie-field-type-badge">yoast</span>
					</div>
				</div>
			`;
		} );

		html += `</div>`;

		// Append to container
		$container.append( html );
	},

	/**
	 * Load Rank Math SEO fields dynamically from server
	 */
	loadRankMathFields( contentType ) {
		if ( typeof rslIeData === 'undefined' ) {
			return;
		}

		const excludedTypes = [
			'media',
			'user',
			'comment',
			'menu',
			'taxonomy',
			'database_table',
			'woo_attribute',
			'woo_coupon',
			'woo_order',
			'woo_review',
			'woo_refund',
			'woo_customer',
		];

		if (
			! this.isIntegrationActive( 'rankMath' ) ||
			excludedTypes.includes( contentType )
		) {
			return;
		}

		const postType =
			contentType === 'custom_post_types'
				? jQuery( '#rsl-ie-custom-post-type' ).val()
				: contentType;

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_rank_math_fields',
				nonce: rslIeData.nonce,
				post_type: postType || '',
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data.fields &&
					response.data.fields.length > 0
				) {
					this.renderRankMathFields( response.data.fields );
				}
			},
			error: () => {},
		} );
	},

	/**
	 * Render Rank Math SEO fields as target fields
	 */
	renderRankMathFields( fields ) {
		const $container = jQuery( '#rsl-ie-target-fields' );

		let html = `<div class="rsl-ie-field-group rsl-ie-rank-math-fields-group">`;
		html += `<div class="rsl-ie-field-group-label">📈 Rank Math SEO</div>`;

		fields.forEach( ( field ) => {
			html += `
				<div class="rsl-ie-target-field" data-target-field="${ Utils.escapeHtml(
					field.name
				) }" data-field-type="${ Utils.escapeHtml(
					field.type || 'string'
				) }">
					<div class="rsl-ie-field-icon">
						<span class="dashicons dashicons-chart-area"></span>
					</div>
					<div class="rsl-ie-field-info">
						<div class="rsl-ie-field-label">${ Utils.escapeHtml( field.label ) }</div>
						<span class="rsl-ie-field-type-badge">rank math</span>
					</div>
				</div>
			`;
		} );

		html += `</div>`;

		$container.append( html );
	},

	/**
	 * Load Elementor fields dynamically from server
	 */
	loadElementorFields( contentType ) {
		if ( typeof rslIeData === 'undefined' ) {
			return;
		}

		const excludedTypes = [
			'media',
			'user',
			'comment',
			'menu',
			'taxonomy',
			'database_table',
			'woo_attribute',
			'woo_coupon',
			'woo_order',
			'woo_review',
			'woo_refund',
			'woo_customer',
		];

		if (
			! this.isIntegrationActive( 'elementor' ) ||
			excludedTypes.includes( contentType )
		) {
			return;
		}

		const postType =
			contentType === 'custom_post_types'
				? jQuery( '#rsl-ie-custom-post-type' ).val()
				: contentType;

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_elementor_fields',
				nonce: rslIeData.nonce,
				post_type: postType || '',
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data.fields &&
					response.data.fields.length > 0
				) {
					this.renderElementorFields( response.data.fields );
				}
			},
			error: () => {},
		} );
	},

	/**
	 * Render Elementor fields as target fields
	 */
	renderElementorFields( fields ) {
		const $container = jQuery( '#rsl-ie-target-fields' );

		let html = `<div class="rsl-ie-field-group rsl-ie-elementor-fields-group">`;
		html += `<div class="rsl-ie-field-group-label">🧱 Elementor</div>`;

		fields.forEach( ( field ) => {
			html += `
				<div class="rsl-ie-target-field" data-target-field="${ Utils.escapeHtml(
					field.name
				) }" data-field-type="${ Utils.escapeHtml(
					field.type || 'string'
				) }">
					<div class="rsl-ie-field-icon">
						<span class="dashicons dashicons-layout"></span>
					</div>
					<div class="rsl-ie-field-info">
						<div class="rsl-ie-field-label">${ Utils.escapeHtml( field.label ) }</div>
						<span class="rsl-ie-field-type-badge">elementor</span>
					</div>
				</div>
			`;
		} );

		html += `</div>`;

		$container.append( html );
	},

	/**
	 * Populate unique field options in Step 5
	 */
	populateUniqueFieldOptions() {
		const $select = jQuery( '#rsl-ie-unique-field' );

		// Clear existing options except first
		$select.find( 'option:not(:first)' ).remove();

		// Get all mapped target fields
		const mappedFields = this.getFieldMapping();

		if ( ! mappedFields || mappedFields.length === 0 ) {
			return;
		}

		// Create unique set of target fields
		const uniqueFields = new Set();
		mappedFields.forEach( ( mapping ) => {
			if ( mapping.target_field ) {
				uniqueFields.add( mapping.target_field );
			}
		} );

		// Posts, pages and custom post types are matched by ID, slug, title or a
		// custom field; other mapped columns (content, dates…) cannot identify a post.
		const matchContentType = jQuery(
			'input[name="content_type"]:checked'
		).val();
		if (
			[ 'post', 'page', 'custom_post_types' ].includes( matchContentType )
		) {
			const matchable = [ ...uniqueFields ].filter(
				( field ) =>
					[ 'ID', 'post_name', 'post_title' ].includes( field ) ||
					/^(meta_|acf_|_)/.test( field )
			);
			if ( matchable.length ) {
				uniqueFields.clear();
				matchable.forEach( ( field ) => uniqueFields.add( field ) );
			}
		}

		// Add options for each unique target field
		uniqueFields.forEach( ( field ) => {
			const label = this.getFieldLabel( field );
			$select.append( `<option value="${ field }">${ label }</option>` );
		} );

		const defaultUniqueField = this.getDefaultUniqueField(
			jQuery( 'input[name="content_type"]:checked' ).val(),
			uniqueFields
		);

		if ( defaultUniqueField ) {
			$select.val( defaultUniqueField );
		} else if ( uniqueFields.size === 1 ) {
			$select.find( 'option:eq(1)' ).prop( 'selected', true );
		}

		// Toggle button state initially
		this.toggleStartImportButton();

		// Add change event handler to toggle button
		$select.off( 'change.uniquefield' ).on( 'change.uniquefield', () => {
			this.toggleStartImportButton();
		} );
	},

	/**
	 * Pick the safest default field for detecting existing items.
	 *
	 * The UI only offers fields that are actually mapped, so every preferred
	 * candidate is checked against mapped target fields before it is selected.
	 *
	 * @param {string} contentType  Selected import content type.
	 * @param {Set}    uniqueFields Mapped target fields.
	 *
	 * @return {string} Field name or empty string.
	 */
	getDefaultUniqueField( contentType, uniqueFields ) {
		const normalizedType = this.normalizeImportContentType( contentType );
		const preferredFields = {
			post: [ 'post_name', 'post_title', 'ID' ],
			page: [ 'post_name', 'post_title', 'ID' ],
			custom_post_types: [ 'post_name', 'post_title', 'ID' ],
			media: [ 'file_url', 'guid', 'file_name', 'post_title', 'ID' ],
			menu: [ 'name', 'slug' ],
			user: [ 'user_email', 'user_login', 'ID' ],
			comment: [
				'source_comment_id',
				'comment_ID',
				'comment_author_email',
				'comment_content',
			],
			taxonomy: [ 'term_id', 'slug', 'name' ],
			woo_product: [ 'sku', 'post_name', 'post_title', 'ID' ],
			woo_order: [ 'order_key', 'order_number', 'ID' ],
			woo_coupon: [ 'post_title', 'ID' ],
			woo_attribute: [ 'attribute_name', 'attribute_id' ],
			// Reviews match by the review ID, or by product + reviewer email + date.
			woo_review: [ 'source_comment_id', 'comment_author_email' ],
			woo_refund: [
				'source_refund_id',
				'refund_id',
				'parent_order_key',
				'parent_order_number',
			],
			woo_customer: [ 'email', 'username', 'customer_id', 'user_id' ],
			database_table: [ 'ID', 'id', 'uuid', 'slug', 'name', 'email' ],
		};

		const candidates = preferredFields[ normalizedType ] || [
			'ID',
			'id',
			'slug',
			'name',
			'title',
		];
		const fields = Array.from( uniqueFields );

		for ( const candidate of candidates ) {
			if ( uniqueFields.has( candidate ) ) {
				return candidate;
			}
		}

		if ( normalizedType === 'database_table' ) {
			const idLikeField = fields.find( ( field ) =>
				/(^id$|_id$|uuid$|guid$|key$)/i.test( field )
			);

			if ( idLikeField ) {
				return idLikeField;
			}
		}

		return '';
	},

	/**
	 * Normalize import content type aliases used by free and PRO cards.
	 *
	 * @param {string} contentType Selected content type.
	 *
	 * @return {string} Normalized content type.
	 */
	normalizeImportContentType( contentType ) {
		const aliases = {
			posts: 'post',
			pages: 'page',
			attachment: 'media',
			attachments: 'media',
			menus: 'menu',
			nav_menu: 'menu',
			users: 'user',
			comments: 'comment',
			taxonomy_term: 'taxonomy',
			term: 'taxonomy',
			product: 'woo_product',
			order: 'woo_order',
			orders: 'woo_order',
			coupon: 'woo_coupon',
			coupons: 'woo_coupon',
			review: 'woo_review',
			reviews: 'woo_review',
			refund: 'woo_refund',
			refunds: 'woo_refund',
			customer: 'woo_customer',
			customers: 'woo_customer',
		};

		return aliases[ contentType ] || contentType || '';
	},

	/**
	 * Toggle Start Import button based on unique field selection
	 */
	toggleStartImportButton() {
		const $button = jQuery( '.rsl-ie-start-import' );
		const uniqueField = jQuery( '#rsl-ie-unique-field' ).val();

		if ( uniqueField ) {
			$button.prop( 'disabled', false ).removeClass( 'disabled' );
		} else {
			$button.prop( 'disabled', true ).addClass( 'disabled' );
		}
	},

	/**
	 * Handle media import options visibility based on content type
	 */
	handleMediaImportOptions() {
		const contentType = jQuery(
			'input[name="content_type"]:checked'
		).val();
		const $mediaImportOption = jQuery( '.rsl-ie-media-import-option' );
		jQuery( '.rsl-ie-taxonomy-option' ).toggle(
			contentType === 'taxonomy'
		);
		const $mediaDuplicateOption = jQuery(
			'.rsl-ie-media-duplicate-option'
		);
		const $batchSize = jQuery( '[name="batch_size"]' );

		// Batch size defaults: media library files are downloaded one by one, so 5; everything else 50.
		// The server stops a batch early after its time budget, so larger values cannot time out.
		if ( contentType === 'media' ) {
			$batchSize.val( $batchSize.data( 'media-value' ) ?? 5 );
		} else {
			$batchSize.val( $batchSize.data( 'default-value' ) ?? 50 );
		}

		// Content types that support media import - ONLY these types
		const supportedTypes = [
			'post',
			'page',
			'custom_post_types',
			'product',
			'woo_product',
			'user',
			'menu',
			'menus',
			'nav_menu',
			'comment',
			'comments',
			'woo_review',
			'woo_reviews',
			'taxonomy',
			'taxonomy_terms',
		];

		// Show media options ONLY if contentType is in the supported list
		const shouldShowMediaOptions = supportedTypes.includes( contentType );

		if ( shouldShowMediaOptions ) {
			$mediaImportOption.show();

			// Show duplicate options only if checkbox is checked
			const isChecked = jQuery( '#rsl-ie-auto-import-media' ).is(
				':checked'
			);
			if ( isChecked ) {
				$mediaDuplicateOption.show();
			}
		} else {
			$mediaImportOption.hide();
			$mediaDuplicateOption.hide();
		}
	},

	/**
	 * Toggle media duplicate options when checkbox is changed
	 */
	toggleMediaDuplicateOptions( e ) {
		const $checkbox = jQuery( e.target );
		const $mediaDuplicateOption = jQuery(
			'.rsl-ie-media-duplicate-option'
		);

		if ( $checkbox.is( ':checked' ) ) {
			$mediaDuplicateOption.slideDown( 200 );
		} else {
			$mediaDuplicateOption.slideUp( 200 );
		}
	},

	/**
	 * Get human-readable label for field
	 */
	getFieldLabel( fieldValue ) {
		// Try to find label from target fields
		let label = fieldValue;

		jQuery( '.rsl-ie-target-field' ).each( function () {
			if ( jQuery( this ).data( 'target-field' ) === fieldValue ) {
				const foundLabel = jQuery( this )
					.find( '.rsl-ie-field-label' )
					.text();
				if ( foundLabel ) {
					label = foundLabel;
					return false; // break
				}
			}
		} );

		// Fallback: convert field_name to Field Name
		if ( label === fieldValue ) {
			label = fieldValue
				.replace( /_/g, ' ' )
				.replace( /\b\w/g, ( l ) => l.toUpperCase() );
		}

		return label;
	},
};

export default ImportModule;
