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
	fileUploader: null,
	mappingFunctions: {},
	importStartTime: null,

	/**
	 * Initialize module
	 */
	areFieldTransformationsEnabled() {
		return !! window.rslIeData?.fieldTransformationsEnabled;
	},

	getFieldTransformationAction( key ) {
		return window.rslIeData?.fieldTransformationActions?.[ key ] || '';
	},

	init() {
		if ( ! jQuery( '#rsl-ie-import' ).length ) {
			return;
		}

		// Check if resuming a job from Jobs Log BEFORE showing any step.
		const urlParams = new URLSearchParams( window.location.search );
		const resumeJobId = urlParams.get( 'resume_job' );

		this.bindEvents();

		if ( resumeJobId ) {
			// Resume job – go directly to step 6 (progress) and start batch processing.
			this.jobId = parseInt( resumeJobId );
			this.showStep( 6 );
			this.startBatchProcessing();
		} else {
			this.showStep( 1 );
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
		$wizard.on( 'change', 'input[name="csv_has_header"]', () => {
			// Reload preview when has_header changes
			if ( this.fileData && this.fileData.file_path ) {
				this.reloadFilePreview();
			}
		} );

		// Field mapping
		$wizard.on( 'click', '.rsl-ie-auto-map', () => this.autoMapFields() );
		$wizard.on( 'click', '.rsl-ie-clear-map', () =>
			this.clearFieldMapping()
		);

		// Import actions
		$wizard.on( 'click', '.rsl-ie-start-import', () => this.startImport() );
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
		} else if ( step === 5 ) {
			this.populateUniqueFieldOptions();
			this.handleMediaImportOptions();
		}
	},

	/**
	 * Go to next step
	 */
	nextStep() {
		if ( this.currentStep < this.totalSteps ) {
			// Show backup warning when leaving step 1 (content type selection)
			if ( this.currentStep === 1 ) {
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
		const allowedExtensions = [ '.csv', '.xml', '.xlsx', '.ods', '.zip' ];
		const fileExt = '.' + file.name.split( '.' ).pop().toLowerCase();

		if ( ! allowedExtensions.includes( fileExt ) ) {
			Utils.showNotice(
				rslIeData.i18n.invalidFileTypeCsv ||
					'Invalid file type. Please upload CSV, XML, XLSX, ODS, or ZIP files only.',
				'error'
			);
			return;
		}

		this.uploadedFile = file;

		// Show file info
		jQuery( '.rsl-ie-upload-placeholder' ).hide();
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
		jQuery( '.rsl-ie-upload-placeholder' ).hide();
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
					jQuery( '.rsl-ie-upload-placeholder' ).show();

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
		jQuery( '.rsl-ie-upload-area' ).show();
		jQuery( '.rsl-ie-upload-placeholder' ).show();
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
				},
			} );

			if ( response.success ) {
				// Update stored file data with new preview AND delimiter
				this.fileData.preview = response.data.preview;
				this.fileData.columns = response.data.columns;
				this.fileData.total_rows = response.data.total_rows;
				this.fileData.delimiter = csvOptions.delimiter;
			}
		} catch ( error ) {}
	},

	/**
	 * Detect file format from filename
	 */
	detectFormat( filename ) {
		const extension = filename.split( '.' ).pop().toLowerCase();
		const supported = [ 'csv', 'xml', 'xlsx', 'ods', 'zip' ];

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
		} else {
			$selector.hide();
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

					$select.prop( 'disabled', false );

					// Handle table selection
					$select.off( 'change' ).on( 'change', () => {
						const tableName = $select.val();
						if ( tableName ) {
							this.selectedTableName = tableName;
							this.loadTableInfo( tableName );
							this.loadTableColumnsForMapping();
						} else {
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
					html += `
					<div class="rsl-ie-target-field" data-target-field="${
						field.value
					}" data-field-type="${
						field.type || 'string'
					}" data-multiple="${ field.multiple || false }">
						<div class="rsl-ie-field-icon">
							<span class="dashicons dashicons-wordpress"></span>
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

		// Create label with format info for taxonomy
		let label, badge;
		if ( isTaxonomy ) {
			const formatLabels = {
				id: 'ID',
				slug: 'Slug',
				name: 'Name',
			};
			label = `${ fieldValue }`;
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
						{ value: 'name', label: 'Menu Name', type: 'string' },
						{ value: 'slug', label: 'Menu Slug', type: 'string' },
						{
							value: 'description',
							label: 'Description',
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
							label: 'Menu Items (JSON)',
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
							label: 'Comment ID',
							type: 'number',
						},
						{
							value: 'comment_post_ID',
							label: 'Post ID',
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
						{ value: 'user_id', label: 'User ID', type: 'number' },
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
							label: 'Post Permalink',
							type: 'url',
						},
						{
							value: 'post_type',
							label: 'Post Type',
							type: 'string',
						},
						{
							value: 'post_slug',
							label: 'Post Slug',
							type: 'string',
						},
						{
							value: 'post_title',
							label: 'Post Title',
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
							label: 'Parent Comment ID',
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
							value: 'grouped_products',
							label: 'Grouped Products (JSON)',
							type: 'json',
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
					label: 'SEO (Yoast)',
					options: [
						{
							value: '_yoast_wpseo_title',
							label: 'SEO Title',
							type: 'string',
						},
						{
							value: '_yoast_wpseo_metadesc',
							label: 'Meta Description',
							type: 'string',
						},
						{
							value: '_yoast_wpseo_focuskw',
							label: 'Focus Keyword',
							type: 'string',
						},
						{
							value: '_yoast_wpseo_canonical',
							label: 'Canonical URL',
							type: 'url',
						},
						{
							value: '_yoast_wpseo_meta-robots-noindex',
							label: 'Meta Robots No Index',
							type: 'string',
						},
						{
							value: '_yoast_wpseo_meta-robots-nofollow',
							label: 'Meta Robots No Follow',
							type: 'string',
						},
						{
							value: '_yoast_wpseo_opengraph-title',
							label: 'OpenGraph Title',
							type: 'string',
						},
						{
							value: '_yoast_wpseo_opengraph-description',
							label: 'OpenGraph Description',
							type: 'string',
						},
						{
							value: '_yoast_wpseo_opengraph-image',
							label: 'OpenGraph Image',
							type: 'url',
						},
						{
							value: '_yoast_wpseo_twitter-title',
							label: 'Twitter Title',
							type: 'string',
						},
						{
							value: '_yoast_wpseo_twitter-description',
							label: 'Twitter Description',
							type: 'string',
						},
						{
							value: '_yoast_wpseo_twitter-image',
							label: 'Twitter Image',
							type: 'url',
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
							value: 'author_name',
							label: 'Author Login',
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
	autoMapFields() {
		// Clear existing mappings
		this.clearFieldMapping();

		// Wait for DOM to be fully ready before mapping
		setTimeout( () => {
			const mappings = [];

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

				let fieldType = null;
				if ( sourceField.startsWith( 'taxonomy_' ) ) {
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
					sourceField,
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

			// PASS 2: Map remaining fields with fuzzy matching
			jQuery( '.rsl-ie-field-card:not(.used)' ).each(
				( index, sourceCard ) => {
					const $sourceCard = jQuery( sourceCard );
					const sourceField = $sourceCard.data( 'source-field' );
					const sourceIndex = $sourceCard.data( 'source-index' );

					if ( ! sourceField ) return;

					const sourceFieldLower = sourceField.toLowerCase();
					let matched = false;

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
							targetFieldValue.length > 2
						) {
							matchType = 'partial';
						} else if (
							targetFieldValue.includes( sourceFieldLower ) &&
							sourceFieldLower.length > 2
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
	 * Start import
	 */
	async startImport() {
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

			const data = {
				file_path: this.fileData.file_path,
				import_type: contentType,
				format: this.fileData.format,
				delimiter: this.fileData.delimiter || ',',
				mapping: this.getFieldMapping(),
				options: {
					duplicate_handling:
						jQuery( 'input[name="if_exists"]:checked' ).val() ||
						'update',
					unique_field: uniqueField,
					if_exists:
						jQuery( 'input[name="if_exists"]:checked' ).val() ||
						'update',
					if_not_exists:
						jQuery( 'input[name="if_not_exists"]:checked' ).val() ||
						'create',
					post_status: jQuery( '[name="post_status"]' ).val(),
					post_type: jQuery( '[name="post_type"]' ).val(),
					download_images: jQuery( '[name="download_images"]' ).is(
						':checked'
					),
					batch_size:
						parseInt( jQuery( '[name="batch_size"]' ).val() ) || 1,
					auto_import_media: jQuery( '#rsl-ie-auto-import-media' ).is(
						':checked'
					),
					media_duplicate_mode:
						jQuery(
							'input[name="media_duplicate_mode"]:checked'
						).val() || 'skip',
				},
			};

			// Add custom post type if selected
			if ( contentType === 'custom_post_types' ) {
				data.options.custom_post_type = jQuery(
					'#rsl-ie-custom-post-type'
				).val();
			}

			// Add table name for database_table import
			if ( contentType === 'database_table' ) {
				data.options.table_name =
					this.selectedTableName ||
					jQuery( '#rsl-ie-import-table-name' ).val();
			}

			const response = await Utils.ajax( 'rsl_ie_import_start', data );

			this.jobId = response.job_id;
			this.importStartTime = Date.now();
			this.showStep( 6 );
			this.startBatchProcessing();

			Utils.showNotice(
				rslIeData.i18n.importStartedSuccessfully ||
					'Import started successfully',
				'success'
			);
		} catch ( error ) {
			Utils.handleError( error, 'Start import' );
		}
	},

	/**
	 * Start batch processing
	 */
	async startBatchProcessing() {
		try {
			const response = await Utils.ajax( 'rsl_ie_import_process_batch', {
				job_id: this.jobId,
			} );

			// Transform batch response to progress bar format
			const elapsedSec = this.importStartTime
				? ( Date.now() - this.importStartTime ) / 1000
				: 0;
			const percentage = response.progress || 0;
			const processed = response.offset || 0;
			const total = response.result?.total || 0;

			// items/sec based on elapsed time
			const itemsPerSec = elapsedSec > 0 ? processed / elapsedSec : 0;

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
				if ( response.result ) {
					this.onImportComplete( response );
				} else {
					this.onImportFailed( response );
				}
			} else {
				// Process next batch
				setTimeout( () => {
					this.startBatchProcessing();
				}, 100 );
			}
		} catch ( error ) {
			clearInterval( this.progressInterval );
			Utils.handleError( error, 'Process batch' );
		}
	},

	/**
	 * Start progress tracking
	 */
	startProgressTracking() {
		// Not used anymore - batch processing updates progress directly
	},

	/**
	 * Update import progress
	 */
	async updateProgress() {
		// Not used anymore - batch processing updates progress directly
	},

	/**
	 * Handle import completion
	 */
	onImportComplete( response ) {
		const result = response.result || {};

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
		Utils.showNotice(
			( rslIeData.i18n.importFailed || 'Import failed' ) +
				': ' +
				( response.error || 'Unknown error' ),
			'error'
		);
	},

	/**
	 * Cancel import
	 */
	async cancelImport() {
		if ( ! confirm( window.rslIeData.i18n.confirmCancelImportStep ) ) {
			return;
		}

		try {
			await Utils.ajax( 'rsl_ie_import_cancel', { job_id: this.jobId } );
			Utils.showNotice(
				rslIeData.i18n.importCancelled || 'Import cancelled',
				'info'
			);
			this.resetWizard();
		} catch ( error ) {
			Utils.handleError( error, 'Cancel import' );
		}
	},

	/**
	 * Toggle logs visibility
	 */
	toggleLogs() {
		jQuery( '.rsl-ie-logs-container' ).slideToggle();
	},

	/**
	 * Reset wizard
	 */
	resetWizard() {
		// Fully reset Step 2 upload UI (it can be hidden after a successful upload).
		this.removeFile();

		this.currentStep = 1;
		this.uploadedFile = null;
		this.fileData = null;
		this.jobId = null;
		this.importStartTime = null;

		jQuery(
			'#rsl-ie-import input[type="text"], #rsl-ie-import input[type="file"]'
		).val( '' );
		jQuery( '#rsl-ie-import input[type="radio"]:first' ).prop(
			'checked',
			true
		);
		jQuery( '.rsl-ie-file-info' ).hide();
		jQuery( '.rsl-ie-upload-area' ).show();
		jQuery( '.rsl-ie-upload-placeholder' ).show();
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

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_acf_fields',
				nonce: rslIeData.nonce,
				post_type: contentType,
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
		];

		if ( excludedTypes.includes( contentType ) ) {
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
		];

		if ( excludedTypes.includes( contentType ) ) {
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
				<div class="rsl-ie-target-field" data-target-field="${ this.escapeHtml(
					field.name
				) }" data-field-type="${ this.escapeHtml(
					field.type || 'string'
				) }">
					<div class="rsl-ie-field-icon">
						<span class="dashicons dashicons-chart-area"></span>
					</div>
					<div class="rsl-ie-field-info">
						<div class="rsl-ie-field-label">${ this.escapeHtml( field.label ) }</div>
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
		];

		if ( excludedTypes.includes( contentType ) ) {
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
				<div class="rsl-ie-target-field" data-target-field="${ this.escapeHtml(
					field.name
				) }" data-field-type="${ this.escapeHtml(
					field.type || 'string'
				) }">
					<div class="rsl-ie-field-icon">
						<span class="dashicons dashicons-layout"></span>
					</div>
					<div class="rsl-ie-field-info">
						<div class="rsl-ie-field-label">${ this.escapeHtml( field.label ) }</div>
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

		// Add options for each unique target field
		uniqueFields.forEach( ( field ) => {
			const label = this.getFieldLabel( field );
			$select.append( `<option value="${ field }">${ label }</option>` );
		} );

		// Select first field by default if only one
		if ( uniqueFields.size === 1 ) {
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
		const $mediaDuplicateOption = jQuery(
			'.rsl-ie-media-duplicate-option'
		);
		const $batchSize = jQuery( '[name="batch_size"]' );

		// Adjust batch size default: media downloads are slow, use 1; everything else uses 50.
		if ( contentType === 'media' ) {
			$batchSize.val( $batchSize.data( 'media-value' ) ?? 1 );
		} else {
			$batchSize.val( $batchSize.data( 'default-value' ) ?? 1 );
		}

		// Content types that support media import - ONLY these types
		const supportedTypes = [
			'post',
			'page',
			'custom_post_types',
			'product',
			'woo_product',
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
