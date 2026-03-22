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

	/**
	 * Initialize module
	 */
	init() {
		if ( ! jQuery( '#wp-aie-import' ).length ) {
			return;
		}

		this.bindEvents();
		this.showStep( 1 );
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $wizard = jQuery( '#wp-aie-import' );

		// Content type filter/search
		$wizard.on( 'input', '#aie-content-type-search', ( e ) =>
			this.filterContentTypes( e )
		);

		// Step navigation
		$wizard.on( 'click', '.aie-next-step', () => this.nextStep() );
		$wizard.on( 'click', '.aie-prev-step', () => this.prevStep() );

		// Content type selection
		$wizard.on( 'change', 'input[name="content_type"]', ( e ) =>
			this.onContentTypeChange( e )
		);

		// Prevent selection of premium locked content types
		$wizard.on( 'click', '.aie-content-type.aie-premium-locked', ( e ) => {
			e.preventDefault();
			e.stopPropagation();
			
			// Show upgrade message
			const message = aieData.i18n.premiumOnlyFeature || 'This content type is only available in the Premium version. Upgrade to unlock this feature.';
			Utils.showNotice( message, 'warning' );
			
			// Prevent the radio button from being checked
			const $input = jQuery( e.currentTarget ).find( 'input[type="radio"]' );
			$input.prop( 'checked', false );
			
			return false;
		} );

		// File upload
		jQuery( '#aie-select-file' ).on( 'click', () =>
			jQuery( '#aie-file-input' ).click()
		);
		jQuery( '#aie-file-input' ).on( 'change', ( e ) =>
			this.onFileSelect( e )
		);
		jQuery( '.aie-remove-file' ).on( 'click', () => this.removeFile() );

		// Drag & drop
		const $dropZone = jQuery( '#aie-upload-area' );
		$dropZone
			.on( 'dragover', ( e ) => {
				e.preventDefault();
				$dropZone.addClass( 'aie-dragover' );
			} )
			.on( 'dragleave', () => {
				$dropZone.removeClass( 'aie-dragover' );
			} )
			.on( 'drop', ( e ) => {
				e.preventDefault();
				$dropZone.removeClass( 'aie-dragover' );
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
		});
		$wizard.on( 'input', '#csv_custom_delimiter', () => {
			this.validateCustomDelimiter();
		});
		$wizard.on( 'blur', '#csv_custom_delimiter', () => {
			// Reload preview when custom delimiter is finalized
			if ( this.fileData && this.fileData.file_path ) {
				this.reloadFilePreview();
			}
		});
		$wizard.on( 'change', 'input[name="csv_has_header"]', () => {
			// Reload preview when has_header changes
			if ( this.fileData && this.fileData.file_path ) {
				this.reloadFilePreview();
			}
		});

		// Field mapping
		$wizard.on( 'click', '.aie-auto-map', () => this.autoMapFields() );
		$wizard.on( 'click', '.aie-clear-map', () => this.clearFieldMapping() );

		// Import actions
		$wizard.on( 'click', '.aie-start-import', () => this.startImport() );
		$wizard.on( 'click', '.aie-cancel-import', () => this.cancelImport() );
		$wizard.on( 'click', '.aie-new-import', () => this.resetWizard() );
		$wizard.on( 'click', '.aie-toggle-logs', () => this.toggleLogs() );

		// Media import options
		$wizard.on( 'change', '#aie-auto-import-media', ( e ) => {
			this.toggleMediaDuplicateOptions( e );
		} );
	},

	/**
	 * Show specific step
	 */
	showStep( step ) {
		const $wizard = jQuery( '#wp-aie-import' );

		// Hide all steps
		$wizard.find( '.aie-step' ).removeClass( 'active' );

		// Show current step
		$wizard.find( `.aie-step-${ step }` ).addClass( 'active' );

		// Update indicators
		$wizard.find( '.aie-step-indicator' ).removeClass( 'active completed' );
		$wizard
			.find( `.aie-step-indicator[data-step="${ step }"]` )
			.addClass( 'active' );
		$wizard
			.find( `.aie-step-indicator[data-step]` )
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
				jQuery( '.aie-preview-table-container' ).hide();
				jQuery( '.aie-json-preview-container' ).hide();
				
				// Show error in preview area
				const errorHtml = `
					<div class="notice notice-error" style="padding: 20px; margin: 20px 0;">
						<h3 style="margin-top: 0;">❌ ${ aieData.i18n.fileValidationFailed || 'File Validation Failed' }</h3>
						<p style="font-size: 14px;">${ Utils.escapeHtml( this.fileData.error ) }</p>
						<p style="margin-bottom: 0;">
							<button type="button" class="button aie-prev-step">
								← ${ aieData.i18n.goBackUploadValidFile || 'Go Back and Upload a Valid File' }
							</button>
						</p>
					</div>
				`;
				jQuery( '.aie-step-3 .aie-step-content' ).prepend( errorHtml );
				
				// Disable next button
				jQuery( '.aie-step-3 .aie-next-step' ).prop( 'disabled', true );
			} else {
				// Enable next button if no error
				jQuery( '.aie-step-3 .aie-next-step' ).prop( 'disabled', false );
			}
		} else if ( step === 4 ) {
			// Disable Next button before building mapping
			jQuery( '.aie-next-step' ).prop( 'disabled', true );
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
					Utils.showNotice( aieData.i18n.pleaseUploadFile || 'Please upload a file', 'error' );
					return false;
				}
				
				// Validate custom delimiter if selected
				const delimiter = jQuery( '#csv_delimiter' ).val();
				if ( delimiter === 'custom' ) {
					const customDelimiter = jQuery( '#csv_custom_delimiter' ).val().trim();
					if ( customDelimiter === '' ) {
						Utils.showNotice( aieData.i18n.pleaseEnterCustomDelimiter || 'Please enter a custom delimiter', 'error' );
						return false;
					}
				}
				break;
			case 4:
				// Validate post type selection for custom post types
				const contentType = jQuery( 'input[name="content_type"]:checked' ).val();
				if ( contentType === 'custom_post_types' ) {
					const selectedPostType = jQuery( '#aie-custom-post-type' ).val();
					if ( ! selectedPostType ) {
						Utils.showNotice( aieData.i18n.pleaseSelectPostType || 'Please select a post type', 'error' );
						return false;
					}
				}
				
				// Validate field mapping
				const mappedFields = this.getFieldMapping();
				if ( ! mappedFields || mappedFields.length === 0 ) {
					Utils.showNotice(
						aieData.i18n.mapFields || 'Please map at least one field',
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
			jQuery( '.aie-post-options' ).hide();
			jQuery( '.aie-media-options' ).show();
		} else {
			jQuery( '.aie-post-options' ).show();
			jQuery( '.aie-media-options' ).hide();
		}
	},

	/**
	 * Filter content types based on search input
	 */
	filterContentTypes( e ) {
		const searchTerm = jQuery( e.target ).val().toLowerCase().trim();
		const $contentTypes = jQuery( '.aie-content-type' );
		const $filterCount = jQuery( '.aie-filter-count' );
		const $filterCountValue = jQuery( '.aie-filter-count-value' );
		const $noResults = jQuery( '.aie-no-results' );
		const $nextStepBtn = jQuery( '.aie-step-1 .aie-next-step' );
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
			if ( title.includes( searchTerm ) || description.includes( searchTerm ) ) {
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
			jQuery( '.aie-custom-delimiter-wrapper' ).show();
			// Validate immediately
			this.validateCustomDelimiter();
		} else {
			jQuery( '.aie-custom-delimiter-wrapper' ).hide();
			// Re-enable next button if file is uploaded and processed
			if ( this.fileData && ! this.fileData.hasError ) {
				jQuery( '.aie-step-2 .aie-next-step' ).prop( 'disabled', false );
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
				jQuery( '.aie-step-2 .aie-next-step' ).prop( 'disabled', true );
			} else {
				// Enable next button if file is uploaded and custom delimiter is provided
				if ( this.fileData && ! this.fileData.hasError ) {
					jQuery( '.aie-step-2 .aie-next-step' ).prop( 'disabled', false );
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
		const allowedExtensions = [ '.csv', '.json' ];
		const fileExt = '.' + file.name.split( '.' ).pop().toLowerCase();
		
		if ( ! allowedExtensions.includes( fileExt ) ) {
			Utils.showNotice(
				aieData.i18n.invalidFileTypeCsvJson || 'Invalid file type. Please upload CSV or JSON files only.',
				'error'
			);
			return;
		}

		this.uploadedFile = file;

		// Show file info
		jQuery( '.aie-upload-placeholder' ).hide();
		jQuery( '.aie-file-info' ).show();
		jQuery( '.aie-file-name' ).text( file.name );
		jQuery( '.aie-file-size' ).text( Utils.formatFileSize( file.size ) );

		// Detect format
		const format = this.detectFormat( file.name );
		jQuery( '.aie-file-format' ).text( format.toUpperCase() );

		// Show format options
		if ( format === 'csv' ) {
			jQuery( '.aie-format-options' ).show();
			jQuery( '.aie-csv-options' ).show();
		}

		// Start chunked upload
		this.uploadFileInChunks( file );
	},

	/**
	 * Get actual delimiter value (convert 'tab' to \t)
	 */
	getDelimiterValue( delimiter ) {
		if ( delimiter === 'tab' ) {
			return '\t';
		}
		return delimiter;
	},

	/**
	 * Upload file in chunks
	 */
	uploadFileInChunks( file ) {
		// Show upload progress
		jQuery( '.aie-upload-placeholder' ).hide();
		jQuery( '.aie-file-info' ).hide();
		jQuery( '.aie-upload-progress' ).show();

		// Collect CSV options if file is CSV
		const fileExt = '.' + file.name.split( '.' ).pop().toLowerCase();
		const csvOptions = {};
		
		if ( fileExt === '.csv' ) {
			const delimiter = jQuery( '#csv_delimiter' ).val();
			const actualDelimiter = delimiter === 'custom' ? 
				jQuery( '#csv_custom_delimiter' ).val().trim() : 
				this.getDelimiterValue( delimiter );
			csvOptions.delimiter = actualDelimiter;
			csvOptions.has_header = jQuery( 'input[name="csv_has_header"]' ).is( ':checked' );
		}

		// Create uploader instance
		this.fileUploader = new FileUploader( {
			chunkSize: 1024 * 1024, // 1MB chunks
			additionalData: csvOptions, // Pass CSV options to uploader
			onProgress: ( progress ) => {
				// Update progress bar
				jQuery( '.aie-upload-progress .aie-progress-bar-fill' ).css(
					'width',
					progress.progress + '%'
				);
				jQuery( '.aie-upload-percentage' ).text(
					Math.round( progress.progress ) + '%'
				);
				jQuery( '.aie-upload-speed' ).text(
					FileUploader.formatSpeed( progress.speed )
				);
			},
			onComplete: ( result ) => {
				
				// Check for validation errors
				if ( result.error ) {
					Utils.showNotice( result.error, 'error' );
					
					// Show file info but keep upload area visible
					jQuery( '.aie-upload-progress' ).hide();
					jQuery( '.aie-upload-placeholder' ).show();
					
					// Store error in fileData to show on step 3
					this.fileData = {
						error: result.error,
						hasError: true
					};
					
					// Disable next button due to validation error
					jQuery( '.aie-step-2 .aie-next-step' ).prop(
						'disabled',
						true
					);
					
					return;
				}

				// Upload complete
				this.fileData = result;
				
				// Hide upload area completely, show file info
				jQuery( '.aie-upload-area' ).hide();
				jQuery( '.aie-file-info' ).show();

				// Enable next button only if custom delimiter validation passes
				const delimiter = jQuery( '#csv_delimiter' ).val();
				const shouldDisable = delimiter === 'custom' && 
					jQuery( '#csv_custom_delimiter' ).val().trim() === '';
				
				jQuery( '.aie-step-2 .aie-next-step' ).prop(
					'disabled',
					shouldDisable
				);

				// Show success message
				Utils.showNotice( aieData.i18n.fileUploadedSuccessfully || 'File uploaded successfully', 'success' );

				// Show warning if present
				if ( result.warning ) {
					Utils.showNotice( result.warning, 'warning' );
				}
			},
			onError: ( error ) => {
				// Upload failed
				Utils.showNotice(
					( aieData.i18n.uploadFailed || 'Upload failed' ) + ': ' + error.message,
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

		jQuery( '.aie-file-info' ).hide();
		jQuery( '.aie-upload-area' ).show();
		jQuery( '.aie-format-options' ).hide();
		jQuery( '#aie-file-input' ).val( '' );
		jQuery( '.aie-step-2 .aie-next-step' ).prop( 'disabled', true );
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
		const actualDelimiter = delimiter === 'custom' ? 
			jQuery( '#csv_custom_delimiter' ).val().trim() : 
			this.getDelimiterValue( delimiter );
		const csvOptions = {
			delimiter: actualDelimiter,
			has_header: jQuery( 'input[name="csv_has_header"]' ).is( ':checked' )
		};

		try {
			// Send request to regenerate preview with new options
			const response = await jQuery.ajax( {
				url: aieData.ajaxUrl,
				method: 'POST',
				data: {
					action: 'aie_reload_preview',
					nonce: aieData.nonce,
					file_path: this.fileData.file_path,
					delimiter: csvOptions.delimiter,
					has_header: csvOptions.has_header
				}
			} );

			if ( response.success ) {
				// Update stored file data with new preview
				this.fileData.preview = response.data.preview;
				this.fileData.columns = response.data.columns;
				this.fileData.total_rows = response.data.total_rows;
			}
		} catch ( error ) {
		}
	},

	/**
	 * Detect file format from filename
	 */
	detectFormat( filename ) {
		const ext = filename.split( '.' ).pop().toLowerCase();
		return [ 'csv', 'json' ].includes( ext ) ? ext : 'csv';
	},

	/**
	 * Load data preview
	 */
	async loadPreview() {
		
		if ( ! this.fileData ) {
			Utils.showNotice( aieData.i18n.noFileDataAvailable || 'No file data available', 'error' );
			return;
		}

		// Check if there's an error
		if ( this.fileData.hasError ) {
			return; // Error display is handled in showStep
		}

		if ( ! this.fileData.preview ) {
			Utils.showNotice( aieData.i18n.noPreviewDataAvailable || 'No preview data available', 'error' );
			return;
		}

		const preview = this.fileData.preview;
		const format = this.fileData.format || 'csv';

		// Update stats
		jQuery( '.aie-total-rows' ).text( this.fileData.total_rows || 0 );
		jQuery( '.aie-total-columns' ).text(
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
		jQuery( '.aie-preview-table-container' ).hide();
		jQuery( '.aie-json-preview-container' ).show();
		jQuery( '.aie-preview-note' ).text(
			'Showing first object from JSON file'
		);

		// Build JSON preview HTML
		let html = '<div class="aie-json-object">';
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

		jQuery( '.aie-json-preview' ).html( html );
	},

	/**
	 * Show table preview for CSV
	 */
	showTablePreview( preview ) {
	// Show table, hide JSON preview
	jQuery( '.aie-preview-table-container' ).show();
	jQuery( '.aie-json-preview-container' ).hide();
	jQuery( '.aie-preview-note' ).text( window.aieData.i18n.showingFirstRows );

	const $table = jQuery( '.aie-preview-table' );		// Build table header
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
					if ( cellContent.startsWith( '{' ) || cellContent.startsWith( '[' ) ) {
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
		const $container = jQuery( '.aie-preview-table-container' );
		
		if ( $container.length ) {
			// Use setTimeout to ensure DOM is fully rendered
			setTimeout( () => {
				const container = $container[ 0 ];
				const hasScroll = container.scrollWidth > container.clientWidth;
				
				$container.toggleClass( 'has-scroll', hasScroll );

				// Add scroll event listener to hide shadow when scrolled to end
				$container.off( 'scroll.preview' ).on( 'scroll.preview', function () {
					const scrollLeft = jQuery( this ).scrollLeft();
					const scrollWidth = this.scrollWidth;
					const clientWidth = this.clientWidth;
					const isAtEnd = scrollLeft + clientWidth >= scrollWidth - 5;
					
					jQuery( this ).toggleClass( 'scrolled-to-end', isAtEnd );
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

		const contentType = jQuery( 'input[name="content_type"]:checked' ).val();
		
		// Show/hide post type selector for custom post types
		this.togglePostTypeSelector( contentType );
		
		// Show/hide database table selector
		this.toggleDatabaseTableSelector( contentType );
		
		// Build source fields (from file)
		this.buildSourceFields();
		
	
	// Build target fields (WordPress fields or database table columns)
	if ( contentType === 'database_table' ) {
		// Table columns will be loaded after table selection
		jQuery( '#aie-target-fields' ).html( `<div class="aie-info">${window.aieData.i18n.pleaseSelectTable}</div>` );
	} else {
		this.buildTargetFields( contentType );
	}		// Load dynamic ACF fields
		this.loadACFFields( contentType );
		
		// Load dynamic Yoast fields
		this.loadYoastFields( contentType );
		
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
		const $container = jQuery( '#aie-source-fields' );
		let html = '';

		this.fileData.columns.forEach( ( column, index ) => {
			const sampleData = this.fileData.preview?.data?.[ 0 ]?.[ index ] || '';
			const sampleDisplay = String( sampleData ).substring( 0, 30 );

			html += `
				<div class="aie-field-card" draggable="true" data-source-field="${ Utils.escapeHtml( column ) }" data-source-index="${ index }">
					<div class="aie-field-icon">
						<span class="dashicons dashicons-media-spreadsheet"></span>
					</div>
					<div class="aie-field-info">
						<div class="aie-field-name">${ Utils.escapeHtml( column ) }</div>
						${ sampleDisplay ? `<div class="aie-field-sample">${ Utils.escapeHtml( sampleDisplay ) }...</div>` : '' }
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
		const $selector = jQuery( '.aie-post-type-selector' );
		
		
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
		const $select = jQuery( '#aie-custom-post-type' );
		
		// If already loaded, skip
		if ( $select.find( 'option' ).length > 1 ) {
			return;
		}
		
		jQuery.ajax( {
			url: window.aieData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aie_get_custom_post_types',
				nonce: window.aieData.nonce,
			},
			success: ( response ) => {
				if ( response.success && response.data ) {
					let options = '<option value="">-- ' + ( aieData.i18n.selectPostType || 'Select Post Type' ) + ' --</option>';
					
					response.data.forEach( ( postType ) => {
						options += `<option value="${ postType.name }">${ postType.label }</option>`;
					} );
					
					$select.html( options );
				}
			},
			error: ( xhr, status, error ) => {
			}
		} );
	},

	/**
	 * Toggle database table selector on Step 4
	 */
	toggleDatabaseTableSelector( contentType ) {
		const $selector = jQuery( '.aie-table-selection-section' );
		
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
		const $select = jQuery( '#aie-import-table-name' );
		const $spinner = jQuery( '.aie-table-selector .spinner' );
		const $section = jQuery( '.aie-table-selection-section' );
		
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
			url: window.aieData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aie_get_database_tables',
				nonce: window.aieData.nonce,
			},
			success: ( response ) => {
				$spinner.removeClass( 'is-active' );
				
			if ( response.success && response.data ) {
				const tables = response.data.tables || response.data || [];
				
				$select.empty();
				$select.append( jQuery( '<option>' ).val( '' ).text( window.aieData.i18n.selectTable ) );
				
				if ( !Array.isArray( tables ) || tables.length === 0 ) {
					$select.append( jQuery( '<option>' ).val( '' ).text( window.aieData.i18n.noTablesFound ) );
					$select.prop( 'disabled', true );
					return;
				}					tables.forEach( ( table ) => {
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
							this.selectedTableName = tableName;						this.loadTableInfo( tableName );
						this.loadTableColumnsForMapping();
					} else {
						jQuery( '.aie-table-info' ).html('').hide();
						jQuery( '#aie-target-fields' ).html( '<div class="aie-info">' + ( window.aieData.i18n.pleaseSelectTable || 'Please select a database table above to see available columns' ) + '</div>' );
					}
				} );
			} else {
				$select.html( `<option value="">${window.aieData.i18n.noTablesFound}</option>` );
			}
			},
			error: ( xhr, status, error ) => {
				$spinner.removeClass( 'is-active' );
				$select.html( `<option value="">${window.aieData.i18n.errorLoadingTables}</option>` );
			}
		} );
	},

	/**
	 * Load table info on Step 2
	 */
	loadTableInfo( tableName ) {
		const $tableInfo = jQuery( '.aie-table-info' );
		const $columnsList = jQuery( '.aie-columns-list' );
		const $rowCount = jQuery( '.aie-table-row-count' );
		const $columnCount = jQuery( '.aie-table-column-count' );
		
		$tableInfo.show();
		$columnsList.html( '<p>Loading...</p>' );
		
		jQuery.ajax( {
			url: window.aieData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aie_get_table_columns',
				nonce: window.aieData.nonce,
				table_name: tableName,
			},
			success: ( response ) => {
				if ( response.success && response.data ) {
					const columns = response.data.columns || [];
					const rowCount = response.data.row_count || 0;
					
					$rowCount.text( rowCount.toLocaleString() );
					$columnCount.text( columns.length );
					
					let html = '<div class="aie-column-badges">';
					columns.forEach( ( column ) => {
						html += `<span class="aie-column-badge">${ column.name }</span>`;
					} );
					html += '</div>';
					
					$columnsList.html( html );
				}
			},
			error: ( xhr, status, error ) => {
				$columnsList.html( `<p>${window.aieData.i18n.errorLoadingColumns}</p>` );
			}
		} );
	},

	/**
	 * Load table columns for Step 4 (field mapping)
	 */
	loadTableColumnsForMapping() {
		if ( !this.selectedTableName ) {
			return;
		}
		
		const $container = jQuery( '#aie-target-fields' );
		
		$container.html( `<div class="aie-loading">${window.aieData.i18n.loadingTableColumns}</div>` );
		
		jQuery.ajax( {
			url: window.aieData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'aie_get_table_columns',
				nonce: window.aieData.nonce,
				table_name: this.selectedTableName,
			},
			success: ( response ) => {
				if ( response.success && response.data && response.data.columns ) {
					const columns = response.data.columns;
					
					let html = '<div class="aie-field-group">';
					html += '<div class="aie-field-group-label">' + ( window.aieData.i18n.fieldGroupTableColumns || 'Table Columns' ) + '</div>';
					
					columns.forEach( ( column ) => {
						html += `
							<div class="aie-target-field" data-target-field="${ column.name }" data-field-type="${ column.type || 'string' }">
								<div class="aie-field-icon">
									<span class="dashicons dashicons-database"></span>
								</div>
								<div class="aie-field-info">
									<div class="aie-field-label">${ column.name }</div>
									<span class="aie-field-type-badge">${ column.type || 'string' }</span>
								</div>
							</div>
						`;
					} );
					
					html += '</div>';
					$container.html( html );
					
					// Re-initialize drag & drop
					this.initializeDragDrop();
				} else {
					$container.html( `<div class="aie-error">${window.aieData.i18n.errorLoadingColumns}</div>` );
				}
			},
			error: ( xhr, status, error ) => {
				$container.html( `<div class="aie-error">${window.aieData.i18n.errorLoadingColumns}</div>` );
			}
		} );
	},

	/**
	 * Build target WordPress fields
	 */
	buildTargetFields( contentType ) {
		const $container = jQuery( '#aie-target-fields' );
		
		// Get fields for content type
		const fieldGroups = this.getFieldsByContentType( contentType );
		
		let html = '';

		fieldGroups.forEach( ( group ) => {
			html += `<div class="aie-field-group">`;
			html += `<div class="aie-field-group-label">${ group.label }</div>`;
			
			group.options.forEach( ( field ) => {
				// Skip special fields (except template field)
				if ( field.value.startsWith( '_' ) && field.value !== '_wp_page_template' ) {
					return;			}

			// Custom fields with add button
			if ( field.custom ) {
				html += `
					<div class="aie-target-field aie-custom-field-template" data-field-type="${ field.type || 'string' }" data-multiple="${ field.multiple || false }">
						<div class="aie-field-icon">
							<span class="dashicons dashicons-plus"></span>
						</div>
						<div class="aie-field-info">
							<div class="aie-field-label">${ field.label }</div>
							<button type="button" class="aie-add-custom-field button button-small">+ ${ window.aieData.i18n.add || 'Add' }</button>
						</div>
					</div>
				`;
			} else {
				html += `
					<div class="aie-target-field" data-target-field="${ field.value }" data-field-type="${ field.type || 'string' }" data-multiple="${ field.multiple || false }">
						<div class="aie-field-icon">
							<span class="dashicons dashicons-wordpress"></span>
						</div>
						<div class="aie-field-info">
							<div class="aie-field-label">${ field.label }</div>
							<span class="aie-field-type-badge">${ field.type || 'string' }</span>
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
		
		jQuery( '.aie-add-custom-field' ).off( 'click' ).on( 'click', function () {
			const $button = jQuery( this );
			const $template = $button.closest( '.aie-custom-field-template' );
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
		
		const title = isTaxonomy ? ( window.aieData.i18n.addTaxonomyField || 'Add Taxonomy Field' ) : ( window.aieData.i18n.addCustomField || 'Add Custom Field' );
		const placeholder = isTaxonomy ? ( window.aieData.i18n.enterTaxonomySlug || 'Enter taxonomy slug (e.g., category, post_tag, product_cat)' ) : ( window.aieData.i18n.enterFieldKey || 'Enter field key (e.g., _custom_price)' );
		const icon = isTaxonomy ? 'dashicons-category' : 'dashicons-admin-plugins';
		
		// Taxonomy format options
		const taxonomyFormatField = isTaxonomy ? `
			<label style="margin-top: 15px;">
				<strong>${ window.aieData.i18n.dataFormat || 'Data Format' }:</strong>
				<select class="aie-taxonomy-format regular-text">
					<option value="id">${ window.aieData.i18n.termIdFormat || 'Term ID (e.g., 5, 12, 23)' }</option>
					<option value="slug">${ window.aieData.i18n.termSlugFormat || 'Term Slug (e.g., technology, news)' }</option>
					<option value="name" selected>${ window.aieData.i18n.termNameFormat || 'Term Name (e.g., Technology, News)' }</option>
				</select>
				<p class="description" style="margin-top: 5px;">
					${ window.aieData.i18n.selectTaxonomyDataFormat || 'Select the format of taxonomy data in your CSV file.' }
				</p>
			</label>
		` : '';
		
		// Create modal HTML (same structure as function modal)
		const modalHtml = `
			<div id="aie-custom-field-modal" class="aie-modal" style="display:flex;">
				<div class="aie-modal-backdrop"></div>
				<div class="aie-modal-content aie-custom-field-modal-content">
					<div class="aie-modal-header">
						<h2 class="aie-modal-title">
							<span class="dashicons ${ icon }"></span>
							${ title }
						</h2>
						<button type="button" class="aie-modal-close">
							<span class="dashicons dashicons-no-alt"></span>
						</button>				</div>
				<div class="aie-modal-body">
					<label>
						<strong>${ isTaxonomy ? ( window.aieData.i18n.taxonomySlugLabel || 'Taxonomy Slug' ) : ( window.aieData.i18n.metaKeyLabel || 'Meta Key' ) }:</strong>
						<input type="text" class="aie-custom-field-input regular-text" placeholder="${ placeholder }" />
						${ isTaxonomy ? '<p class="description" style="margin-top: 5px;">' + ( window.aieData.i18n.taxonomySlugDescription || 'The slug of the taxonomy (category, post_tag, or custom taxonomy).' ) + '</p>' : ( isMeta ? '<p class="description" style="margin-top: 5px;">' + ( window.aieData.i18n.metaKeyDescription || 'The meta key for the custom field (e.g., _custom_price, my_custom_field).' ) + '</p>' : '' ) }
					</label>
					${ taxonomyFormatField }
					</div>
					<div class="aie-modal-footer">
						<button type="button" class="button aie-modal-cancel">${ window.aieData.i18n.cancel || 'Cancel' }</button>
						<button type="button" class="button button-primary aie-modal-add">${ window.aieData.i18n.addField || 'Add Field' }</button>
					</div>
				</div>
			</div>
		`;
		
		// Add modal to body
		jQuery( 'body' ).append( modalHtml );
		
		const $modal = jQuery( '#aie-custom-field-modal' );
		const $backdrop = $modal.find( '.aie-modal-backdrop' );
		const $input = $modal.find( '.aie-custom-field-input' );
		
		// Focus input
		setTimeout( () => $input.focus(), 100 );
		
		// Close modal handlers
		$modal.find( '.aie-modal-close, .aie-modal-cancel' ).on( 'click', function () {
			$modal.remove();
		} );
		
		$backdrop.on( 'click', function () {
			$modal.remove();
		} );
		
		// Add field handler
		$modal.find( '.aie-modal-add' ).on( 'click', function () {
			const fieldValue = $input.val().trim();
			
		if ( ! fieldValue ) {
			alert( window.aieData.i18n.pleaseEnterFieldName );
			return;
		}			// Get taxonomy format if applicable
			let taxonomyFormat = 'name';
			if ( isTaxonomy ) {
				taxonomyFormat = $modal.find( '.aie-taxonomy-format' ).val();
			}
			
			// Create new field card
			self.addCustomFieldToGroup( $template, fieldValue, fieldType, isMultiple, taxonomyFormat );
			
			$modal.remove();
		} );
		
		// Enter key to add
		$input.on( 'keypress', function ( e ) {
			if ( e.which === 13 ) {
				$modal.find( '.aie-modal-add' ).click();
			}
		} );
	},

	/**
	 * Add custom field to group
	 */
	addCustomFieldToGroup( $template, fieldValue, fieldType, isMultiple, taxonomyFormat ) {
		const $group = $template.closest( '.aie-field-group' );
		const isTaxonomy = fieldType === 'taxonomy';
		
		// Create label with format info for taxonomy
		let label, badge;
		if ( isTaxonomy ) {
			const formatLabels = {
				id: 'ID',
				slug: 'Slug',
				name: 'Name'
			};
			label = `${ fieldValue }`;
			badge = `taxonomy (${ formatLabels[ taxonomyFormat ] || 'Name' })`;
		} else {
			label = fieldValue;
			badge = 'meta';
		}
		
		// Store taxonomy format in data attribute
		const taxonomyFormatAttr = isTaxonomy ? `data-taxonomy-format="${ taxonomyFormat }"` : '';
		
		const fieldHtml = `
			<div class="aie-target-field" data-target-field="${ fieldValue }" data-field-type="${ fieldType }" data-multiple="${ isMultiple }" ${ taxonomyFormatAttr }>
				<div class="aie-field-icon">
					<span class="dashicons ${ isTaxonomy ? 'dashicons-category' : 'dashicons-admin-plugins' }"></span>
				</div>
				<div class="aie-field-info">
					<div class="aie-field-label">${ label }</div>
					<span class="aie-field-type-badge">${ badge }</span>
					<button type="button" class="aie-remove-custom-field" title="Remove">&times;</button>
				</div>
			</div>
		`;
		
		// Insert before template
		$template.before( fieldHtml );
		
		// Add remove handler
		$group.find( '.aie-remove-custom-field' ).off( 'click' ).on( 'click', function () {
			jQuery( this ).closest( '.aie-target-field' ).remove();
		} );
	},

	/**
	 * Get fields by content type (import-compatible fields)
	 */
	getFieldsByContentType( contentType ) {
		// Helper function to get translated field group label
		const t = ( key, fallback ) => window.aieData?.i18n?.[ 'fieldGroup' + key.replace(/[^A-Za-z]/g, '') ] || fallback;
		
		// Helper to translate field groups
		const translateGroups = ( groups ) => {
			return groups.map( group => ({
				...group,
				label: t( group.label, group.label )
			}));
		};
		
		const baseFields = [
			{
				label: t( 'Standard', 'Standard' ),
				options: [
					{ value: 'post_title', label: 'Title', type: 'string' },
					{ value: 'post_content', label: 'Content', type: 'string' },
					{ value: 'post_excerpt', label: 'Excerpt', type: 'string' },
					{ value: 'post_date', label: 'Date', type: 'date' },
					{ value: 'post_status', label: 'Status', type: 'string' },
					{ value: 'post_name', label: 'Slug', type: 'string' },
					{ value: '_wp_page_template', label: 'Template', type: 'string' },
				],
			},
			{
				label: t( 'Author', 'Author' ),
				options: [
					{ value: 'post_author', label: 'Author ID', type: 'number' },
					{ value: 'author_email', label: 'Author Email', type: 'email' },
				],
			},
			{
				label: 'Media',
				options: [
					{ value: 'featured_image', label: 'Featured Image URL', type: 'string' },
					{ value: 'featured_image_id', label: 'Featured Image ID', type: 'number' },
				],
			},
			{
				label: 'Other',
				options: [
					{ value: 'comment_status', label: 'Comment Status', type: 'string' },
					{ value: 'post_modified', label: 'Modified Date', type: 'date' },
					{ value: 'menu_order', label: 'Menu Order', type: 'number' },				{ value: 'post_parent', label: 'Parent ID', type: 'number' },
			],
		},
	];

	// Media
		if ( contentType === 'media' ) {
			return translateGroups([
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'ID', type: 'number' },
						{ value: 'post_title', label: 'Title', type: 'string' },
						{ value: 'post_content', label: 'Description', type: 'string' },
						{ value: 'post_excerpt', label: 'Caption', type: 'string' },
						{ value: 'alt_text', label: 'Alt Text', type: 'string' },
						{ value: 'guid', label: 'GUID', type: 'string' },
					],
				},
				{
					label: 'File',
					options: [
						{ value: 'file_url', label: 'File URL', type: 'url' },
						{ value: 'file_path', label: 'File Path', type: 'string' },
						{ value: 'file_name', label: 'File Name', type: 'string' },
						{ value: 'file_extension', label: 'File Extension', type: 'string' },
						{ value: 'post_mime_type', label: 'MIME Type', type: 'string' },
						{ value: 'file_size', label: 'File Size (bytes)', type: 'number' },
					],
				},
				{
					label: 'Image',
					options: [
						{ value: 'width', label: 'Width (px)', type: 'number' },
						{ value: 'height', label: 'Height (px)', type: 'number' },
					],
				},
				{
					label: 'Author',
					options: [
						{ value: 'post_author', label: 'Author ID', type: 'number' },
						{ value: 'author_name', label: 'Author Name', type: 'string' },
						{ value: 'author_email', label: 'Author Email', type: 'email' },
					],
				},
				{
					label: 'Other',
					options: [
						{ value: 'post_date', label: 'Upload Date', type: 'date' },
						{ value: 'post_modified', label: 'Modified Date', type: 'date' },
						{ value: 'post_parent', label: 'Attached To (Post ID)', type: 'number' },
						{ value: 'attached_post_title', label: 'Attached Post Title', type: 'string' },
					],
				},
			]);
		}

		// Pages (no taxonomies, only custom fields)
		if ( contentType === 'page' ) {
			return translateGroups([
				...baseFields,
				{
					label: 'Custom Fields (Meta)',
					options: [					{ value: 'meta', label: 'Custom Field', type: 'meta', custom: true },
				],
			},
		]);
		}

		// Users
		if ( contentType === 'user' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'User ID', type: 'number' },
						{ value: 'user_login', label: 'Username', type: 'string' },
						{ value: 'user_email', label: 'Email', type: 'email' },
						{ value: 'user_pass', label: 'Password', type: 'string' },
						{ value: 'display_name', label: 'Display Name', type: 'string' },
						{ value: 'user_nicename', label: 'Nice Name (Slug)', type: 'string' },
					],
				},
				{
					label: 'Profile',
					options: [
						{ value: 'first_name', label: 'First Name', type: 'string' },
						{ value: 'last_name', label: 'Last Name', type: 'string' },
						{ value: 'nickname', label: 'Nickname', type: 'string' },
						{ value: 'description', label: 'Bio', type: 'string' },
						{ value: 'user_url', label: 'Website', type: 'url' },
						{ value: 'avatar_url', label: 'Avatar URL', type: 'url' },
					],
				},
				{
					label: 'Social Media',
					options: [
						{ value: 'facebook', label: 'Facebook', type: 'string' },
						{ value: 'instagram', label: 'Instagram', type: 'string' },
						{ value: 'linkedin', label: 'LinkedIn', type: 'string' },
						{ value: 'myspace', label: 'MySpace', type: 'string' },
						{ value: 'pinterest', label: 'Pinterest', type: 'string' },
						{ value: 'soundcloud', label: 'SoundCloud', type: 'string' },
						{ value: 'tumblr', label: 'Tumblr', type: 'string' },
						{ value: 'wikipedia', label: 'Wikipedia', type: 'string' },
						{ value: 'twitter', label: 'Twitter/X', type: 'string' },
						{ value: 'youtube', label: 'YouTube', type: 'string' },
					],
				},
				{
					label: 'Role & Permissions',
					options: [
						{ value: 'role', label: 'Role', type: 'string' },
						{ value: 'roles', label: 'Roles (comma-separated)', type: 'string' },
						{ value: 'capabilities', label: 'Capabilities (JSON)', type: 'string' },
					],
				},
				{
					label: 'Preferences',
					options: [
						{ value: 'locale', label: 'Language', type: 'string' },
						{ value: 'admin_color', label: 'Admin Color Scheme', type: 'string' },
						{ value: 'rich_editing', label: 'Visual Editor', type: 'boolean' },
					],
				},
				{
					label: 'Stats',
					options: [
						{ value: 'posts_count', label: 'Posts Count', type: 'number' },
						{ value: 'user_registered', label: 'Registration Date', type: 'date' },
					],
				},
				{
					label: 'Custom Fields (User Meta)',
					options: [
						{ value: 'meta', label: 'Custom Field', type: 'meta', custom: true },
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
						{ value: 'post_title', label: 'Product Title', type: 'string' },
						{ value: 'post_name', label: 'Slug', type: 'string' },
						{ value: 'post_status', label: 'Status (publish, draft, pending)', type: 'string' },
						{ value: 'post_author', label: 'Author ID', type: 'number' },
						{ value: 'post_content', label: 'Description', type: 'string' },
						{ value: 'post_excerpt', label: 'Short Description', type: 'string' },
						{ value: 'comment_status', label: 'Reviews Enabled', type: 'string' },
					],
				},
				{
					label: 'Product Data',
					options: [
						{ value: 'sku', label: 'SKU', type: 'string' },
						{ value: 'regular_price', label: 'Regular Price', type: 'number' },
						{ value: 'sale_price', label: 'Sale Price', type: 'number' },
						{ value: 'product_type', label: 'Product Type (simple, variable, grouped, external)', type: 'string' },
						{ value: 'downloadable', label: 'Downloadable (yes, no)', type: 'string' },
						{ value: 'virtual', label: 'Virtual (yes, no)', type: 'string' },
						{ value: 'featured', label: 'Featured (yes, no)', type: 'string' },
						{ value: 'visibility', label: 'Catalog Visibility', type: 'string' },
					],
				},
				{
					label: 'Inventory',
					options: [
						{ value: 'stock_quantity', label: 'Stock Quantity', type: 'number' },
						{ value: 'stock_status', label: 'Stock Status (instock, outofstock, onbackorder)', type: 'string' },
						{ value: 'manage_stock', label: 'Manage Stock (yes, no)', type: 'string' },
						{ value: 'backorders', label: 'Backorders (yes, no, notify)', type: 'string' },
					],
				},
				{
					label: 'Tax',
					options: [
						{ value: 'tax_status', label: 'Tax Status (taxable, shipping, none)', type: 'string' },
						{ value: 'tax_class', label: 'Tax Class', type: 'string' },
					],
				},
				{
					label: 'Shipping',
					options: [
						{ value: 'weight', label: 'Weight', type: 'number' },
						{ value: 'length', label: 'Length', type: 'number' },
						{ value: 'width', label: 'Width', type: 'number' },
						{ value: 'height', label: 'Height', type: 'number' },
						{ value: 'shipping_class', label: 'Shipping Class', type: 'string' },
					],
				},
				{
					label: 'Images',
					options: [
						{ value: 'featured_image_id', label: 'Featured Image ID', type: 'number' },
						{ value: 'featured_image_url', label: 'Featured Image URL', type: 'url' },
						{ value: 'featured_image_title', label: 'Featured Image Title', type: 'string' },
						{ value: 'featured_image_caption', label: 'Featured Image Caption', type: 'string' },
						{ value: 'product_gallery', label: 'Gallery Image IDs (comma-separated)', type: 'string' },
					],
				},
				{
					label: 'Categories & Tags',
					options: [
						{ value: 'product_cat', label: 'Categories (comma-separated)', type: 'string' },
						{ value: 'product_tag', label: 'Tags (comma-separated)', type: 'string' },
					],
				},
				{
					label: 'Reviews',
					options: [
						{ value: 'average_rating', label: 'Average Rating', type: 'number' },
						{ value: 'review_count', label: 'Review Count', type: 'number' },
					],
				},
				{
					label: 'Stats',
					options: [
						{ value: 'total_sales', label: 'Total Sales', type: 'number' },
					],
				},
				{
					label: 'Dates',
					options: [
						{ value: 'post_date', label: 'Created Date', type: 'date' },
						{ value: 'post_modified', label: 'Modified Date', type: 'date' },
					],
				},
				{
					label: 'SEO (Yoast)',
					options: [
						{ value: '_yoast_wpseo_title', label: 'SEO Title', type: 'string' },
						{ value: '_yoast_wpseo_metadesc', label: 'Meta Description', type: 'string' },
						{ value: '_yoast_wpseo_focuskw', label: 'Focus Keyword', type: 'string' },
						{ value: '_yoast_wpseo_canonical', label: 'Canonical URL', type: 'url' },
						{ value: '_yoast_wpseo_meta-robots-noindex', label: 'Meta Robots No Index', type: 'string' },
						{ value: '_yoast_wpseo_meta-robots-nofollow', label: 'Meta Robots No Follow', type: 'string' },
						{ value: '_yoast_wpseo_opengraph-title', label: 'OpenGraph Title', type: 'string' },
						{ value: '_yoast_wpseo_opengraph-description', label: 'OpenGraph Description', type: 'string' },
						{ value: '_yoast_wpseo_opengraph-image', label: 'OpenGraph Image', type: 'url' },
						{ value: '_yoast_wpseo_twitter-title', label: 'Twitter Title', type: 'string' },
						{ value: '_yoast_wpseo_twitter-description', label: 'Twitter Description', type: 'string' },
						{ value: '_yoast_wpseo_twitter-image', label: 'Twitter Image', type: 'url' },
					],
				},
				{
					label: 'Custom Fields (Product Meta)',
					options: [
						{ value: 'meta', label: 'Custom Field', type: 'meta', custom: true },
					],
				},
			];
		}

		// Taxonomy Terms
		if ( contentType === 'taxonomy' || contentType === 'taxonomy_term' || contentType === 'term' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'term_id', label: 'Term ID', type: 'number' },
						{ value: 'name', label: 'Name', type: 'string' },
						{ value: 'slug', label: 'Slug', type: 'string' },
						{ value: 'description', label: 'Description', type: 'string' },
					],
				},
				{
					label: 'Taxonomy',
					options: [
						{ value: 'taxonomy', label: 'Taxonomy', type: 'string' },
						{ value: 'term_taxonomy_id', label: 'Term Taxonomy ID', type: 'number' },
					],
				},
				{
					label: 'Hierarchy',
					options: [
						{ value: 'parent', label: 'Parent Term ID', type: 'number' },
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
						{ value: 'meta', label: 'Custom Field', type: 'meta', custom: true },
					],
				},
			];
		}

		if ( contentType === 'custom_post_types' ) {
			return [
				{
					label: 'Standard',
					options: [
						{ value: 'post_title', label: 'Title', type: 'string', required: true },
						{ value: 'post_content', label: 'Content', type: 'string' },
						{ value: 'post_excerpt', label: 'Excerpt', type: 'string' },
						{ value: 'post_status', label: 'Status', type: 'string' },
						{ value: 'post_date', label: 'Date', type: 'datetime' },
						{ value: 'post_modified', label: 'Modified Date', type: 'datetime' },
						{ value: 'menu_order', label: 'Menu Order', type: 'number' },
						{ value: 'post_slug', label: 'Slug', type: 'string' },
						{ value: 'comment_status', label: 'Comment Status', type: 'string' },
						{ value: 'ping_status', label: 'Ping Status', type: 'string' },
					],
				},
				{
					label: 'Author',
					options: [
						{ value: 'author_id', label: 'Author ID', type: 'number' },
						{ value: 'author_login', label: 'Author Login', type: 'string' },
						{ value: 'author_email', label: 'Author Email', type: 'string' },
					],
				},
				{
					label: 'Media',
					options: [
						{ value: 'featured_image', label: 'Featured Image URL', type: 'string' },
						{ value: 'featured_image_id', label: 'Featured Image ID', type: 'number' },
					],
				},
				{
					label: 'Other',
					options: [
						{ value: 'post_parent', label: 'Parent ID', type: 'number' },
						{ value: 'post_parent_slug', label: 'Parent Slug', type: 'string' },
					],
				},
				{
					label: 'Taxonomies',
					options: [
						{ value: 'taxonomy', label: 'Taxonomy', type: 'taxonomy', custom: true },
					],
				},
				{
					label: 'Custom Fields',
					options: [
						{ value: 'meta', label: 'Custom Field', type: 'meta', custom: true },
					],
				},
			];
		}

	// Default - return post fields with taxonomies and custom fields
	return translateGroups([
		...baseFields,
		{
			label: 'Taxonomies',
				options: [
					{ value: 'taxonomy', label: 'Taxonomy', type: 'taxonomy', custom: true },
				],
			},
			{
				label: 'Custom Fields (Meta)',
				options: [
					{ value: 'meta', label: 'Custom Field', type: 'meta', custom: true },
				],
			},
		]);
	},

	/**
	 * Initialize drag & drop functionality
	 */
	initializeDragDrop() {
		const self = this;
		let draggedElement = null;

		// Drag start on source fields
		jQuery( document ).on( 'dragstart', '.aie-field-card', function ( e ) {
			draggedElement = jQuery( this );
			jQuery( this ).addClass( 'dragging' );
			
			e.originalEvent.dataTransfer.effectAllowed = 'copy';
			e.originalEvent.dataTransfer.setData( 'text/html', this.innerHTML );
		} );

		// Drag end
		jQuery( document ).on( 'dragend', '.aie-field-card', function () {
			jQuery( this ).removeClass( 'dragging' );
			jQuery( '.aie-target-field' ).removeClass( 'drag-over' );
		} );

		// Drag over target fields
		jQuery( document ).on( 'dragover', '.aie-target-field', function ( e ) {
			e.preventDefault();
			jQuery( this ).addClass( 'drag-over' );
		} );

		// Drag leave
		jQuery( document ).on( 'dragleave', '.aie-target-field', function () {
			jQuery( this ).removeClass( 'drag-over' );
		} );

		// Drop on target field
		jQuery( document ).on( 'drop', '.aie-target-field', function ( e ) {
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
			self.createMapping( sourceField, sourceIndex, targetField, fieldType, jQuery( this ) );

			// Add visual indicator that this source is used (but don't disable it)
			draggedElement.addClass( 'used' );
			
			// Clear dragged element
			draggedElement = null;

			// Update stats
			self.updateMappingStats();
		} );

		// Remove mapping (from target field)
		jQuery( document ).on( 'click', '.aie-remove-mapping', function ( e ) {
			e.stopPropagation();
			const $targetField = jQuery( this ).closest( '.aie-target-field' );
			const sourceIndex = $targetField.data( 'mapped-source-index' );

			self.removeMapping( sourceIndex, $targetField );
		} );

		// Remove mapping (from mapped fields section)
		jQuery( document ).on( 'click', '.aie-remove-row-mapping', function ( e ) {
			e.stopPropagation();
			const sourceIndex = jQuery( this ).data( 'source-index' );
			const targetField = jQuery( this ).closest( '.aie-mapping-row' ).data( 'target-field' );
			const $targetField = jQuery( `.aie-target-field[data-target-field="${ targetField }"]` );

			self.removeMapping( sourceIndex, $targetField );
		} );

		// Add function to mapping
		jQuery( document ).on( 'click', '.aie-add-function', function ( e ) {
			e.stopPropagation();
			const sourceIndex = jQuery( this ).data( 'source-index' );
			const targetField = jQuery( this ).data( 'target-field' );

			self.showFunctionSelector( sourceIndex, targetField );
		} );

		// Remove function from mapping
		jQuery( document ).on( 'click', '.aie-remove-function', function ( e ) {
			e.stopPropagation();
			const functionIndex = jQuery( this ).data( 'function-index' );
			const $row = jQuery( this ).closest( '.aie-mapping-row' );
			const sourceIndex = $row.data( 'source-index' );
			const targetField = $row.data( 'target-field' );

			self.removeFunction( sourceIndex, targetField, functionIndex );
		} );
	},

	/**
	 * Remove mapping
	 */
	removeMapping( sourceIndex, $targetField ) {
		const targetField = $targetField.data( 'target-field' );
		
		// Remove mapping from target
		$targetField.find( '.aie-mapped-source' ).remove();
		$targetField.removeClass( 'has-mapping' );
		$targetField.removeData( 'mapped-source-index' );
		$targetField.removeData( 'mapped-source-field' );

		// Check if this source is still used in other mappings BEFORE removing
		// We need to exclude the current mapping being removed
		const allMappings = jQuery( `.aie-mapping-row[data-source-index="${ sourceIndex }"]` );
		const otherMappings = allMappings.filter( function() {
			return jQuery( this ).data( 'target-field' ) !== targetField;
		} );
		const stillUsed = otherMappings.length > 0;

		// Remove from mapped fields section (specific target field)
		jQuery( `.aie-mapping-row[data-source-index="${ sourceIndex }"][data-target-field="${ targetField }"]` ).remove();

		// Remove functions for this mapping
		const mappingKey = `${ sourceIndex }-${ targetField }`;
		if ( this.mappingFunctions && this.mappingFunctions[ mappingKey ] ) {
			delete this.mappingFunctions[ mappingKey ];
		}

		if ( ! stillUsed ) {
			// Remove 'used' class only if not used anywhere else
			jQuery( `.aie-field-card[data-source-index="${ sourceIndex }"]` ).removeClass( 'used' );
		}

		// Show empty state if no mappings
		if ( jQuery( '.aie-mapping-row' ).length === 0 ) {
			jQuery( '.aie-mapped-fields .aie-empty-state' ).show();
		}

		// Update stats
		this.updateMappingStats();
	},

	/**
	 * Create field mapping
	 */
	createMapping( sourceField, sourceIndex, targetField, fieldType, $targetElement ) {
		// Remove existing mapping if any
		$targetElement.find( '.aie-mapped-source' ).remove();

		// Add mapped source indicator to target
		const mappedHtml = `
			<div class="aie-mapped-source">
				<span class="aie-source-name">${ Utils.escapeHtml( sourceField ) }</span>
				<span class="dashicons dashicons-no-alt aie-remove-mapping"></span>
			</div>
		`;
		$targetElement.find( '.aie-field-info' ).append( mappedHtml );
		$targetElement.addClass( 'has-mapping' );
		$targetElement.data( 'mapped-source-index', sourceIndex );
		$targetElement.data( 'mapped-source-field', sourceField );

		// Add to mapped fields section
		this.addToMappedFields( sourceField, sourceIndex, targetField, fieldType );
	},

	/**
	 * Add mapping to mapped fields section
	 */
	addToMappedFields( sourceField, sourceIndex, targetField, fieldType ) {
		const $container = jQuery( '.aie-mapped-fields' );
		
		// Hide empty state
		$container.find( '.aie-empty-state' ).hide();

		// Remove existing row for this specific combination (если перемапливаем то же поле)
		jQuery( `.aie-mapping-row[data-source-index="${ sourceIndex }"][data-target-field="${ targetField }"]` ).remove();

		// Get functions for this mapping
		const mappingKey = `${ sourceIndex }-${ targetField }`;
		const functions = this.mappingFunctions?.[ mappingKey ] || [];

		// Build functions HTML
		let functionsHtml = '';
		if ( functions.length > 0 ) {
			functionsHtml = '<div class="aie-mapping-functions">';
			functions.forEach( ( func, index ) => {
				functionsHtml += `
					<span class="aie-function-badge">
						${ Utils.escapeHtml( func.name ) }
						<button type="button" class="aie-remove-function" data-function-index="${ index }">×</button>
					</span>
				`;
			} );
			functionsHtml += '</div>';
		}

		// Add new row
		const html = `
			<div class="aie-mapping-row" data-source-index="${ sourceIndex }" data-target-field="${ targetField }">
				<div class="aie-source-col">
					<span class="dashicons dashicons-media-spreadsheet"></span>
					<strong>${ Utils.escapeHtml( sourceField ) }</strong>
				</div>
				<div class="aie-arrow">→</div>
				<div class="aie-target-col">
					<span class="dashicons dashicons-wordpress"></span>				<strong>${ targetField }</strong>
			</div>
			${ functionsHtml }
			<div class="aie-mapping-actions">
				<button type="button" class="button button-small aie-add-function" data-source-index="${ sourceIndex }" data-target-field="${ targetField }" title="${ window.aieData.i18n.addTransformationFunction || 'Add transformation function' }">
					<span class="dashicons dashicons-admin-tools"></span>
				</button>
				<button type="button" class="button button-small aie-remove-row-mapping" data-source-index="${ sourceIndex }" data-target-field="${ targetField }" title="${ window.aieData.i18n.removeMapping || 'Remove mapping' }">
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
		jQuery( '.aie-mapping-row' ).each( function() {
			const sourceIndex = jQuery( this ).data( 'source-index' );
			// Only add if sourceIndex is defined (skip if undefined/null)
			if ( sourceIndex !== undefined && sourceIndex !== null && sourceIndex !== '' ) {
				usedSourceIndexes.add( sourceIndex );
			}
		} );
		const mappedCount = usedSourceIndexes.size;

		// Show: "X / Y fields mapped" where Y is total source columns
		jQuery( '.aie-mapped-count' ).text( mappedCount );
		jQuery( '.aie-total-fields' ).text( totalSourceFields );

		// Enable/disable Next button based on mapping count (only on Step 4)
		if ( this.currentStep === 4 ) {
			const $nextButton = jQuery( '.aie-next-step' );
			if ( mappedCount === 0 ) {
				$nextButton.prop( 'disabled', true );
			} else {
				$nextButton.prop( 'disabled', false );
			}
		}
	},

	/**
	 * Show function selector modal
	 */
	async showFunctionSelector( sourceIndex, targetField ) {
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
				url: window.aieData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aie_functions_get_snippets',
					nonce: window.aieData.nonce,
				},
			} );

			if ( ! response.success ) {
				Utils.showNotice( aieData.i18n.failedToLoadFunctions || 'Failed to load functions', 'error' );
				return;
			}

			this.showFunctionModal( sourceIndex, targetField, response.data );
		} catch ( error ) {
			Utils.showNotice( ( aieData.i18n.errorLoadingFunctions || 'Error loading functions' ) + ': ' + error.message, 'error' );
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
		<div id="aie-field-functions-modal" class="aie-modal" style="display:flex;">
			<div class="aie-modal-backdrop"></div>
			<div class="aie-modal-content aie-field-functions-modal-content">
				<div class="aie-modal-header">
					<h2 class="aie-modal-title">
						<span class="dashicons dashicons-admin-generic"></span>
						${ window.aieData.i18n.fieldTransformationFunctions || 'Field Transformation Functions' }
					</h2>
					<button type="button" class="aie-modal-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="aie-modal-body">
					<!-- Field Info -->
					<div class="aie-field-info">
						<div class="aie-field-info-item">
							<strong>${ window.aieData.i18n.field || 'Field' }:</strong>
							<span class="aie-current-field-label">${ Utils.escapeHtml( sourceField ) }</span>
						</div>
						<div class="aie-field-info-item">
							<strong>${ window.aieData.i18n.type || 'Type' }:</strong>
							<span class="aie-current-field-type">${ targetField }</span>
						</div>
					</div>

					<!-- Applied Functions List -->
					<div class="aie-applied-functions">
						<h3>
							${ window.aieData.i18n.appliedFunctions || 'Applied Functions' }
							<span class="aie-functions-count">(0)</span>
						</h3>
						
						<div class="aie-functions-pipeline" id="aie-functions-pipeline">
							<div class="aie-no-functions">
								<span class="dashicons dashicons-info"></span>
								<p>${ window.aieData.i18n.noFunctionsApplied || 'No functions applied yet. Add functions from the list below.' }</p>
							</div>
							
							<div class="aie-function-items" id="aie-function-items">
								<!-- Functions will be added here -->
							</div>
						</div>

						<div class="aie-pipeline-hint">
							<span class="dashicons dashicons-info"></span>
							${ window.aieData.i18n.functionsAppliedInOrder || 'Functions are applied in order from top to bottom. Drag to reorder.' }
						</div>
					</div>

					<!-- Available Functions -->
					<div class="aie-available-functions">
						<h3>${ window.aieData.i18n.availableFunctions || 'Available Functions' }</h3>
						
						<!-- Search Functions -->
						<div class="aie-functions-search">
							<input 
								type="text" 
								id="aie-functions-search" 
								class="regular-text" 
								placeholder="${ window.aieData.i18n.searchFunctions || 'Search functions...' }"
							>
							<span class="dashicons dashicons-search"></span>
						</div>

						<!-- Functions Filter -->
						<div class="aie-functions-filter">
							<label>
								<input type="radio" name="functions-filter" value="all" checked>
								${ window.aieData.i18n.all || 'All' }
							</label>
							<label>
								<input type="radio" name="functions-filter" value="library">
								${ window.aieData.i18n.library || 'Library' }
							</label>
							<label>
								<input type="radio" name="functions-filter" value="custom">
								${ window.aieData.i18n.custom || 'Custom' }
							</label>
						</div>

						<!-- Functions List -->
						<div class="aie-functions-list" id="aie-functions-list">
							<div class="aie-functions-loading">
								<span class="spinner is-active"></span>
								<p>${ window.aieData.i18n.loadingFunctions || 'Loading functions...' }</p>
							</div>
						</div>

						<!-- Quick Add Link -->
						<div class="aie-functions-quick-add">
							<a href="#" class="aie-create-new-function">
								<span class="dashicons dashicons-plus-alt"></span>
								${ window.aieData.i18n.createNewFunction || 'Create New Function' }
							</a>
						</div>
					</div>

					<!-- Preview Section -->
					<div class="aie-function-preview">
						<h3>${ window.aieData.i18n.previewTransformation || 'Preview Transformation' }</h3>
						
						<div class="aie-preview-controls">
							<div class="aie-preview-input-group">
								<label for="aie-preview-input">
									${ window.aieData.i18n.testValue || 'Test Value' }:
								</label>
								<input 
									type="text" 
									id="aie-preview-input" 
									class="regular-text" 
									placeholder="${ window.aieData.i18n.enterTestValue || 'Enter test value...' }"
								>
							</div>
							<button type="button" class="button aie-test-pipeline">
								<span class="dashicons dashicons-media-code"></span>
								${ window.aieData.i18n.testPipeline || 'Test Pipeline' }
							</button>
						</div>

						<div class="aie-preview-result" id="aie-preview-result" style="display:none;">
							<div class="aie-preview-steps">
								<!-- Steps will be added dynamically -->
							</div>
						</div>
					</div>
				</div>
				<div class="aie-modal-footer">
					<button type="button" class="button button-secondary aie-modal-cancel">
						${ window.aieData.i18n.cancel || 'Cancel' }
					</button>
					<button type="button" class="button button-primary aie-save-field-functions">
						<span class="dashicons dashicons-yes"></span>
						${ window.aieData.i18n.applyFunctions || 'Apply Functions' }
					</button>
				</div>
			</div>
		</div>
	`;

		// Remove existing modal
		jQuery( '#aie-field-functions-modal' ).remove();
		
		// Add to body
		jQuery( 'body' ).append( modalHtml );
		jQuery( 'body' ).addClass( 'aie-modal-open' );

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
		const $container = jQuery( '#aie-function-items' );
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
		const $container = jQuery( '#aie-functions-list' );
		$container.empty();

		// Get all snippets
		const snippets = functionsData.snippets || {};

	if ( Object.keys( snippets ).length === 0 ) {
		$container.html( `
			<div class="aie-functions-empty-state">
				<span class="dashicons dashicons-info"></span>
				<p>${ window.aieData.i18n.noFunctionsAvailableYet || 'No functions available yet.' }</p>
			</div>
		` );
		return;
	}

		// Store for later use
		this.availableFunctions = snippets;

		Object.entries( snippets ).forEach( ( [ key, snippet ] ) => {
			const item = jQuery( '<div>' )
				.addClass( 'aie-function-list-item' )
				.attr( 'data-function-id', key )
				.attr( 'data-category', snippet.category || 'custom' )
				.html( `				<div class="aie-function-list-info">
					<span class="aie-function-list-name">${ Utils.escapeHtml( snippet.name ) }</span>
					<span class="aie-function-list-desc">${ Utils.escapeHtml( snippet.description || '' ) }</span>
				</div>
				<button type="button" class="button button-small aie-add-function-btn">${ window.aieData.i18n.add || 'Add' }</button>
			` );

		item.find( '.aie-add-function-btn' ).on( 'click', () => {
				this.addFunctionToPipeline( { id: key, name: snippet.name }, true );
			} );

			$container.append( item );
		} );
	},

	/**
	 * Add function to pipeline
	 */
	addFunctionToPipeline( func, updateArray = true ) {
		const $container = jQuery( '#aie-function-items' );

		const item = jQuery( '<div>' )
			.addClass( 'aie-function-item' )
			.attr( 'data-function-id', func.id )
			.html( `
				<span class="aie-function-handle dashicons dashicons-menu"></span>
				<div class="aie-function-info">
					<strong class="aie-function-name">${ Utils.escapeHtml( func.name ) }</strong>
				</div>
				<div class="aie-function-actions">
					<button type="button" class="button-small aie-remove-function">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			` );

		// Remove function event
		item.find( '.aie-remove-function' ).on( 'click', () => {
			item.remove();
			this.updateFunctionsCount();
			this.toggleNoFunctionsMessage();
		} );

		$container.append( item );

		if ( updateArray ) {
			this.updateFunctionsCount();
		}

		this.toggleNoFunctionsMessage();
	},

	/**
	 * Update functions count
	 */
	updateFunctionsCount( count = null ) {
		const $countEl = jQuery( '.aie-functions-count' );
		if ( ! $countEl.length ) return;

		if ( count === null ) {
			count = jQuery( '#aie-function-items .aie-function-item' ).length;
		}

		$countEl.text( `(${ count })` );
	},

	/**
	 * Toggle no functions message
	 */
	toggleNoFunctionsMessage() {
		const hasItems = jQuery( '#aie-function-items .aie-function-item' ).length > 0;
		jQuery( '.aie-no-functions' ).toggle( ! hasItems );
		jQuery( '#aie-function-items' ).toggle( hasItems );
	},

	/**
	 * Bind function modal events
	 */
	bindFunctionModalEvents( sourceIndex, targetField ) {
		const self = this;

		// Close modal functions
		const closeModal = function() {
			jQuery( '#aie-field-functions-modal' ).remove();
			jQuery( 'body' ).removeClass( 'aie-modal-open' );
		};

		// Close on backdrop click
		jQuery( '.aie-modal-backdrop' ).on( 'click', closeModal );

		// Close on X button
		jQuery( '.aie-modal-close' ).on( 'click', closeModal );

		// Close on Cancel button
		jQuery( '.aie-modal-cancel' ).on( 'click', closeModal );

		// Search functions
		jQuery( '#aie-functions-search' ).on( 'input', function () {
			const query = jQuery( this ).val().toLowerCase();
			
			jQuery( '.aie-function-list-item' ).each( function () {
				const name = jQuery( this ).find( '.aie-function-list-name' ).text().toLowerCase();
				const desc = jQuery( this ).find( '.aie-function-list-desc' ).text().toLowerCase();
				
				jQuery( this ).toggle( name.includes( query ) || desc.includes( query ) );
			} );
		} );

		// Filter functions (All / Library / Custom)
		jQuery( 'input[name="functions-filter"]' ).on( 'change', function () {
			const filterValue = jQuery( this ).val();
			
			jQuery( '.aie-function-list-item' ).each( function () {
				const category = jQuery( this ).data( 'category' );
				
				if ( filterValue === 'all' ) {
					jQuery( this ).show();
				} else if ( filterValue === 'library' ) {
					// Show library functions (snippets)
					jQuery( this ).toggle( category !== 'custom' );
				} else if ( filterValue === 'custom' ) {
					// Show custom functions
					jQuery( this ).toggle( category === 'custom' );
				}
			} );
		} );

		// Create new function
		jQuery( '.aie-create-new-function' ).on( 'click', function ( e ) {
			e.preventDefault();
			Utils.showNotice( aieData.i18n.createFunctionsInLibrary || 'Creating custom functions will be available in the Functions Library section', 'info' );
		} );

		jQuery( '.aie-test-pipeline' ).on( 'click', function () {
			const testValue = jQuery( '#aie-preview-input' ).val();
			
			if ( ! testValue ) {
				Utils.showNotice( aieData.i18n.enterTestValue || 'Please enter a test value', 'warning' );
				return;
			}

			const functions = [];
			jQuery( '#aie-function-items .aie-function-item' ).each( function () {
				functions.push( jQuery( this ).data( 'function-id' ) );
			} );

			if ( functions.length === 0 ) {
				Utils.showNotice( aieData.i18n.pleaseAddAtLeastOneFunction || 'Please add at least one function to test', 'warning' );
				return;
			}

			self.testFunctionPipeline( testValue, functions );
		} );

		// Apply functions (Save button)
		jQuery( '.aie-save-field-functions' ).on( 'click', function () {
			const selectedFunctions = [];
			
			jQuery( '#aie-function-items .aie-function-item' ).each( function () {
				const functionId = jQuery( this ).data( 'function-id' );
				const functionName = jQuery( this ).find( '.aie-function-name' ).text();
				
				selectedFunctions.push( {
					id: functionId,
					name: functionName,
				} );
			} );

			self.applyFunctionsToMapping( sourceIndex, targetField, selectedFunctions );
			closeModal();
		} );
	},

	/**
	 * Test function pipeline
	 */
	async testFunctionPipeline( testValue, functionIds ) {
		const $result = jQuery( '#aie-preview-result' );
		const $steps = $result.find( '.aie-preview-steps' );
		
		$steps.html( `<div class="aie-preview-loading"><span class="spinner is-active"></span> ${window.aieData.i18n.testing}</div>` );
		$result.show();

		try {
			const response = await jQuery.ajax( {
				url: window.aieData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'aie_test_function_pipeline',
					nonce: window.aieData.nonce,
					test_value: testValue,
					function_ids: functionIds,
				},
			} );
		if ( response.success && response.data.steps ) {
			let html = '';
			
			// Initial value
			html += `
				<div class="aie-preview-step">
					<div class="aie-step-label">${ window.aieData.i18n.initialValue || 'Initial Value' }:</div>
					<div class="aie-step-value">${ Utils.escapeHtml( response.data.initial || testValue ) }</div>
				</div>
			`;

			// Each step
			response.data.steps.forEach( ( step, index ) => {
				const stepNum = index + 1;
				html += `
					<div class="aie-preview-step">
						<div class="aie-step-label">${ stepNum }. ${ Utils.escapeHtml( step.function_name ) }:</div>
						<div class="aie-step-value">${ Utils.escapeHtml( step.output ) }</div>
					</div>
				`;
			} );

			// Final result
			html += `
				<div class="aie-preview-step aie-preview-final">
					<div class="aie-step-label">${ window.aieData.i18n.finalResult || 'Final Result' }:</div>
					<div class="aie-step-value"><strong>${ Utils.escapeHtml( response.data.final ) }</strong></div>
				</div>
			`;

			$steps.html( html );
		} else {
				$steps.html( `<div class="notice notice-error inline"><p>${ response.data?.message || window.aieData.i18n.failedTestPipeline }</p></div>` );
			}
		} catch ( error ) {
			$steps.html( `<div class="notice notice-error inline"><p>${window.aieData.i18n.error}: ${ error.message }</p></div>` );
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
		const $row = jQuery( `.aie-mapping-row[data-source-index="${ sourceIndex }"][data-target-field="${ targetField }"]` );
		
		// Remove existing functions display
		$row.find( '.aie-mapping-functions' ).remove();

		if ( functions.length === 0 ) {
			return;
		}

		// Add functions display
		let functionsHtml = '<div class="aie-mapping-functions">';
		
		functions.forEach( ( func, index ) => {
			functionsHtml += `
				<span class="aie-function-badge">
					${ Utils.escapeHtml( func.name ) }
					<button type="button" class="aie-remove-function" data-function-index="${ index }">×</button>
				</span>
			`;
		} );
		
		functionsHtml += '</div>';

		// Insert after target column
		$row.find( '.aie-target-col' ).after( functionsHtml );
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
		const $sourceSearch = jQuery( '.aie-search-source' );
		$sourceSearch.on( 'input', function () {
			const query = jQuery( this ).val().toLowerCase();
			jQuery( '.aie-field-card' ).each( function () {
				const fieldName = jQuery( this ).find( '.aie-field-name' ).text().toLowerCase();
				jQuery( this ).toggle( fieldName.includes( query ) );
			} );
		} );

		// Clear source search button
		$sourceSearch.parent().find( '.aie-clear-search' ).on( 'click', function ( e ) {
			e.preventDefault();
			$sourceSearch.val( '' ).focus().trigger( 'input' );
		} );

		// Search target fields
		const performSearch = function () {
			const query = jQuery( this ).val().toLowerCase().trim();
			
			// Store matched fields per group
			const groupMatches = {};
			
			// Filter fields and track which groups have matches
			jQuery( '.aie-target-field' ).each( function () {
				const $field = jQuery( this );
				const fieldName = $field.find( '.aie-field-label' ).text().toLowerCase();
				const matches = query === '' || fieldName.includes( query );
				
				// Find parent group
				const $group = $field.closest( '.aie-field-group' );
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
			jQuery( '#aie-target-fields .aie-field-group' ).each( function () {
				const $group = jQuery( this );
				const groupIndex = $group.index();
				const hasMatches = groupMatches[ groupIndex ] > 0;
				$group.toggle( query === '' || hasMatches );
			} );
		};
		
		const $targetSearch = jQuery( '.aie-search-target' );
		$targetSearch.on( 'keyup input', performSearch );

		// Clear target search button
		$targetSearch.parent().find( '.aie-clear-search' ).on( 'click', function ( e ) {
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
				{ value: 'post_title', label: window.aieData.i18n.fieldTitle || 'Title' },
				{ value: 'post_content', label: window.aieData.i18n.fieldContent || 'Content' },
				{ value: 'post_excerpt', label: window.aieData.i18n.fieldExcerpt || 'Excerpt' },
				{ value: 'post_status', label: window.aieData.i18n.fieldStatus || 'Status' },
				{ value: 'post_author', label: window.aieData.i18n.fieldAuthor || 'Author' },
				{ value: 'post_date', label: window.aieData.i18n.fieldDate || 'Date' },
				{ value: 'post_name', label: window.aieData.i18n.fieldSlug || 'Slug' },
				{ value: 'categories', label: window.aieData.i18n.fieldCategories || 'Categories' },
				{ value: 'tags', label: window.aieData.i18n.fieldTags || 'Tags' },
				{ value: 'featured_image', label: window.aieData.i18n.fieldFeaturedImage || 'Featured Image' },
			],
			media: [
				{ value: 'post_title', label: window.aieData.i18n.fieldTitle || 'Title' },
				{ value: 'post_content', label: window.aieData.i18n.fieldDescription || 'Description' },
				{ value: 'post_excerpt', label: window.aieData.i18n.fieldCaption || 'Caption' },
				{ value: 'alt_text', label: window.aieData.i18n.fieldAltText || 'Alt Text' },
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
			// PASS 1: Map exact matches first (highest priority)
			jQuery( '.aie-field-card' ).each( ( index, sourceCard ) => {
				const $sourceCard = jQuery( sourceCard );
				const sourceField = $sourceCard.data( 'source-field' );
				const sourceIndex = $sourceCard.data( 'source-index' );
				
				if ( ! sourceField ) return;
				
				const sourceFieldLower = sourceField.toLowerCase();
				let matched = false;

				// Look for EXACT match only
				jQuery( '.aie-target-field:not(.aie-custom-field-template)' ).each( ( i, targetField ) => {
					if ( matched ) return;

					const $targetField = jQuery( targetField );
					const targetFieldData = $targetField.data( 'target-field' );
					
					if ( ! targetFieldData ) return;
					if ( $targetField.hasClass( 'has-mapping' ) ) return;
					
					const targetFieldValue = targetFieldData.toLowerCase();
					
					// ONLY exact match in pass 1
					if ( sourceFieldLower === targetFieldValue ) {
						this.createMapping(
							$sourceCard.data( 'source-field' ),
							sourceIndex,
							$targetField.data( 'target-field' ),
							$targetField.data( 'field-type' ),
							$targetField
						);

						$sourceCard.addClass( 'mapped' );
						matched = true;
					}
				} );
			} );
			
			// PASS 2: Map remaining fields with fuzzy matching
			jQuery( '.aie-field-card:not(.mapped)' ).each( ( index, sourceCard ) => {
				const $sourceCard = jQuery( sourceCard );
				const sourceField = $sourceCard.data( 'source-field' );
				const sourceIndex = $sourceCard.data( 'source-index' );
				
				if ( ! sourceField ) return;
				
				const sourceFieldLower = sourceField.toLowerCase();
				let matched = false;

				jQuery( '.aie-target-field:not(.aie-custom-field-template)' ).each( ( i, targetField ) => {
					if ( matched ) return;

					const $targetField = jQuery( targetField );
					const targetFieldData = $targetField.data( 'target-field' );
					
					if ( ! targetFieldData ) return;
					
					// Skip already mapped target fields
					if ( $targetField.hasClass( 'has-mapping' ) ) return;
					
					const targetFieldValue = targetFieldData.toLowerCase();
					const targetLabel = $targetField.find( '.aie-field-label' ).text().toLowerCase();

					// Fuzzy matching: label match, normalized match, or partial match
					let matchType = null;
					
					if ( sourceFieldLower === targetLabel ) {
						matchType = 'label';
					}
					else if ( sourceFieldLower.replace( /_/g, ' ' ) === targetLabel ) {
						matchType = 'normalized';
					}
					else if ( sourceFieldLower.includes( targetFieldValue ) && targetFieldValue.length > 2 ) {
						matchType = 'partial';
					}
					else if ( targetFieldValue.includes( sourceFieldLower ) && sourceFieldLower.length > 2 ) {
						matchType = 'partial';
					}
					
					if ( matchType ) {
						this.createMapping(
							$sourceCard.data( 'source-field' ),
							sourceIndex,
							$targetField.data( 'target-field' ),
							$targetField.data( 'field-type' ),
							$targetField
						);

						$sourceCard.addClass( 'mapped' );
						matched = true;
					}
				} );
			} );
		}, 50 );

		// Use setTimeout to ensure DOM is fully updated before counting
		setTimeout( () => {
			this.updateMappingStats();
			
			// Count actual mapped fields from DOM
			const usedSourceIndexes = new Set();
			jQuery( '.aie-mapping-row' ).each( function() {
				usedSourceIndexes.add( jQuery( this ).data( 'source-index' ) );
			} );
			const mappedCount = usedSourceIndexes.size;
			
			const message = ( window.aieData.i18n.autoMappedFields || 'Auto-mapped %d fields' ).replace( '%d', mappedCount );
			Utils.showNotice( message, 'success' );
		}, 150 );
	},

	/**
	 * Clear field mapping
	 */
	clearFieldMapping() {
		// Clear all mappings
		jQuery( '.aie-target-field' ).each( function () {
			jQuery( this ).find( '.aie-mapped-source' ).remove();
			jQuery( this ).removeClass( 'has-mapping' );
			jQuery( this ).removeData( 'mapped-source-index' );
			jQuery( this ).removeData( 'mapped-source-field' );
		} );

		// Unmark all source fields (remove 'used' class)
		jQuery( '.aie-field-card' ).removeClass( 'used' );

		// Clear mapped fields section
		jQuery( '.aie-mapped-fields' ).html( `
			<div class="aie-empty-state">
				<span class="dashicons dashicons-info"></span>
				<p>${ window.aieData.i18n.dragSourceColumns || 'Drag source columns to WordPress fields to create mappings' }</p>
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

		jQuery( '.aie-mapping-row' ).each( function () {
			const $row = jQuery( this );
			const sourceIndex = $row.data( 'source-index' );
			const targetField = $row.data( 'target-field' );
			const sourceField = jQuery( `.aie-field-card[data-source-index="${ sourceIndex }"]` ).data( 'source-field' );

			if ( sourceField && targetField ) {
				mapping.push( {
					source_index: sourceIndex,
					source_field: sourceField,
					target_field: targetField,
					function_id: $row.data( 'function-id' ) || null,
				} );
			}
		} );

		return mapping;
	},

	/**
	 * Start import
	 */
	async startImport() {
		try {
			const contentType = jQuery( 'input[name="content_type"]:checked' ).val();
			const uniqueField = jQuery( '#aie-unique-field' ).val();
			
			// Validate unique field selection (REQUIRED)
			if ( ! uniqueField ) {
				Utils.showNotice(
					window.aieData.i18n.pleaseSelectUniqueField || 'Please select a field to check for existing items',
					'error'
				);
				return;
			}
			
			const data = {
				file_path: this.fileData.file_path,
				import_type: contentType,
				format: this.fileData.format,
				mapping: this.getFieldMapping(),
				options: {
					duplicate_handling: jQuery(
						'input[name="if_exists"]:checked'
					).val() || 'update',
					unique_field: uniqueField,
					if_exists: jQuery( 'input[name="if_exists"]:checked' ).val() || 'update',
					if_not_exists: jQuery( 'input[name="if_not_exists"]:checked' ).val() || 'create',
					post_status: jQuery( '[name="post_status"]' ).val(),
					post_type: jQuery( '[name="post_type"]' ).val(),
					download_images: jQuery( '[name="download_images"]' ).is(
						':checked'
					),
					batch_size:
						parseInt( jQuery( '[name="batch_size"]' ).val() ) || 50,
					auto_import_media: jQuery( '#aie-auto-import-media' ).is( ':checked' ),
					media_duplicate_mode: jQuery( 'input[name="media_duplicate_mode"]:checked' ).val() || 'skip',
				}
			};
			
			// Add custom post type if selected
			if ( contentType === 'custom_post_types' ) {
				data.options.custom_post_type = jQuery( '#aie-custom-post-type' ).val();
			}
			
			// Add table name for database_table import
			if ( contentType === 'database_table' ) {
				data.options.table_name = this.selectedTableName || jQuery( '#aie-import-table-name' ).val();
			}

			const response = await Utils.ajax( 'aie_import_start', data );

			this.jobId = response.job_id;
			this.showStep( 6 );
			this.startBatchProcessing();

			Utils.showNotice( aieData.i18n.importStartedSuccessfully || 'Import started successfully', 'success' );
		} catch ( error ) {
			Utils.handleError( error, 'Start import' );
		}
	},

	/**
	 * Start batch processing
	 */
	async startBatchProcessing() {
		try {
			const response = await Utils.ajax( 'aie_import_process_batch', {
				job_id: this.jobId,
			} );

			// Transform batch response to progress bar format
			const progressData = {
				percentage: response.progress || 0,
				processed: response.offset || 0,
				total: response.result?.total || 0,
				estimates: {
					elapsed_formatted: '',
					remaining_formatted: '',
					items_per_second: 0,
				},
			};

			// Update progress
			Utils.updateProgressBar( jQuery( '.aie-step-6' ), progressData );

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
		jQuery( '.aie-progress-container' ).hide();
		jQuery( '.aie-import-results' ).show();
		jQuery( '.aie-import-complete-card' ).fadeIn();
		
		// Update statistics
		jQuery( '.aie-result-success' ).text( result.success || 0 );
		jQuery( '.aie-result-updated' ).text( result.updated || 0 );
		jQuery( '.aie-result-created' ).text( result.created || 0 );
		jQuery( '.aie-result-skipped' ).text( result.skipped || 0 );
		jQuery( '.aie-result-failed' ).text( result.failed || 0 );
		
		// Calculate duration
		const jobData = response.job_data || {};
		if ( jobData.started_at && jobData.completed_at ) {
			const start = new Date( jobData.started_at );
			const end = new Date( jobData.completed_at );
			const duration = Math.round( ( end - start ) / 1000 );
			jQuery( '.aie-result-duration' ).text( duration + 's' );
		}
		
		// Update buttons
		jQuery( '.aie-cancel-import' ).hide();
		jQuery( '.aie-new-import' ).show();
	},

	/**
	 * Handle import failure
	 */
	onImportFailed( response ) {
		Utils.showNotice(
			( aieData.i18n.importFailed || 'Import failed' ) + ': ' + ( response.error || 'Unknown error' ),
			'error'
		);
	},

	/**
	 * Cancel import
	 */
	async cancelImport() {
		if ( ! confirm( window.aieData.i18n.confirmCancelImportStep ) ) {
			return;
		}
		
		try {
			await Utils.ajax( 'aie_import_cancel', { job_id: this.jobId } );
			Utils.showNotice( aieData.i18n.importCancelled || 'Import cancelled', 'info' );
			this.resetWizard();
		} catch ( error ) {
			Utils.handleError( error, 'Cancel import' );
		}
	},

	/**
	 * Toggle logs visibility
	 */
	toggleLogs() {
		jQuery( '.aie-logs-container' ).slideToggle();
	},

	/**
	 * Reset wizard
	 */
	resetWizard() {
		this.currentStep = 1;
		this.uploadedFile = null;
		this.fileData = null;
		this.jobId = null;

		jQuery(
			'#wp-aie-import input[type="text"], #wp-aie-import input[type="file"]'
		).val( '' );
		jQuery( '#wp-aie-import input[type="radio"]:first' ).prop(
			'checked',
			true
		);
		jQuery( '.aie-file-info' ).hide();
		jQuery( '.aie-upload-placeholder' ).show();
		jQuery( '.aie-import-results' ).hide();

		this.showStep( 1 );
	},

	/**
	 * Load ACF fields dynamically from server
	 */
	loadACFFields( contentType ) {
		if ( typeof aieData === 'undefined' ) {
			return;
		}

		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_acf_fields',
				nonce: aieData.nonce,
				post_type: contentType,
			},
			success: ( response ) => {
				if ( response.success && response.data.fields && response.data.fields.length > 0 ) {
					this.renderACFFields( response.data.fields );
				}
			},
			error: ( xhr, status, error ) => {
			},
		} );
	},

	/**
	 * Render ACF fields as target fields
	 */
	renderACFFields( fields ) {
		const $container = jQuery( '#aie-target-fields' );

		// Create ACF group
		let html = `<div class="aie-field-group aie-acf-fields-group">`;
		html += `<div class="aie-field-group-label">🔧 ACF Fields</div>`;

		fields.forEach( ( field ) => {
			html += `
				<div class="aie-target-field" data-target-field="acf_${ field.name }" data-field-type="${ field.type || 'string' }">
					<div class="aie-field-icon">
						<span class="dashicons dashicons-admin-settings"></span>
					</div>
					<div class="aie-field-info">
						<div class="aie-field-label">${ field.label }</div>
						<span class="aie-field-type-badge">acf:${ field.type }</span>
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
		if ( typeof aieData === 'undefined' ) {
			return;
		}

		// Don't load Yoast for these content types
		const excludedTypes = [
			'media',
			'user',
			'comment',
			'menu',
			'taxonomy',		'database_table',
		'woo_attribute',
		'woo_coupon',
		'woo_order',
	];

	if ( excludedTypes.includes( contentType ) ) {
			return;
		}

		jQuery.ajax( {
			url: aieData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'aie_get_yoast_fields',
				nonce: aieData.nonce,
				post_type: contentType,
			},
			success: ( response ) => {
				if ( response.success && response.data.fields && response.data.fields.length > 0 ) {
					this.renderYoastFields( response.data.fields );
				}
			},
			error: ( xhr, status, error ) => {
			},
		} );
	},

	/**
	 * Render Yoast SEO fields as target fields
	 */
	renderYoastFields( fields ) {
		const $container = jQuery( '#aie-target-fields' );

		// Create Yoast group
		let html = `<div class="aie-field-group aie-yoast-fields-group">`;
		html += `<div class="aie-field-group-label">📊 Yoast SEO</div>`;

		fields.forEach( ( field ) => {
			// Clean up field name (remove _ prefix)
			const fieldName = field.name.replace( /^_/, '' );
			
			html += `
				<div class="aie-target-field" data-target-field="${ fieldName }" data-field-type="string">
					<div class="aie-field-icon">
						<span class="dashicons dashicons-chart-line"></span>
					</div>
					<div class="aie-field-info">
						<div class="aie-field-label">${ field.label }</div>
						<span class="aie-field-type-badge">yoast</span>
					</div>
				</div>
			`;
		} );

		html += `</div>`;

		// Append to container
		$container.append( html );
	},

	/**
	 * Populate unique field options in Step 5
	 */
	populateUniqueFieldOptions() {
		const $select = jQuery( '#aie-unique-field' );
		
		// Clear existing options except first
		$select.find( 'option:not(:first)' ).remove();
		
		// Get all mapped target fields
		const mappedFields = this.getFieldMapping();
		
		if ( ! mappedFields || mappedFields.length === 0 ) {
			return;
		}
		
		// Create unique set of target fields
		const uniqueFields = new Set();
		mappedFields.forEach( mapping => {
			if ( mapping.target_field ) {
				uniqueFields.add( mapping.target_field );
			}
		} );
		
		// Add options for each unique target field
		uniqueFields.forEach( field => {
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
		const $button = jQuery( '.aie-start-import' );
		const uniqueField = jQuery( '#aie-unique-field' ).val();
		
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
		const contentType = jQuery( 'input[name="content_type"]:checked' ).val();
		const $mediaImportOption = jQuery( '.aie-media-import-option' );
		const $mediaDuplicateOption = jQuery( '.aie-media-duplicate-option' );
		
		// Content types that support media import - ONLY these types
		const supportedTypes = [ 'post', 'page', 'custom_post_types', 'product' ];
		
		// Show media options ONLY if contentType is in the supported list
		const shouldShowMediaOptions = supportedTypes.includes( contentType );
		
		if ( shouldShowMediaOptions ) {
			$mediaImportOption.show();
			
			// Show duplicate options only if checkbox is checked
			const isChecked = jQuery( '#aie-auto-import-media' ).is( ':checked' );
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
		const $mediaDuplicateOption = jQuery( '.aie-media-duplicate-option' );
		
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
		
		jQuery( '.aie-target-field' ).each( function() {
			if ( jQuery( this ).data( 'target-field' ) === fieldValue ) {
				const foundLabel = jQuery( this ).find( '.aie-field-label' ).text();
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
				.replace( /\b\w/g, l => l.toUpperCase() );
		}
		
		return label;
	},
};

export default ImportModule;
