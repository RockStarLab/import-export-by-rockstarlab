/**
 * Import Module
 *
 * Handles the import wizard functionality
 */

import Utils from './utils';
import FileUploader from './FileUploader';

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

		// Step navigation
		$wizard.on( 'click', '.aie-next-step', () => this.nextStep() );
		$wizard.on( 'click', '.aie-prev-step', () => this.prevStep() );

		// Content type selection
		$wizard.on( 'change', 'input[name="content_type"]', ( e ) =>
			this.onContentTypeChange( e )
		);

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
						<h3 style="margin-top: 0;">❌ File Validation Failed</h3>
						<p style="font-size: 14px;">${ Utils.escapeHtml( this.fileData.error ) }</p>
						<p style="margin-bottom: 0;">
							<button type="button" class="button aie-prev-step">
								← Go Back and Upload a Valid File
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
		}
	},

	/**
	 * Go to next step
	 */
	nextStep() {
		if ( this.currentStep < this.totalSteps ) {
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
					Utils.showNotice( 'Please upload a file', 'error' );
					return false;
				}
				
				// Validate custom delimiter if selected
				const delimiter = jQuery( '#csv_delimiter' ).val();
				if ( delimiter === 'custom' ) {
					const customDelimiter = jQuery( '#csv_custom_delimiter' ).val().trim();
					if ( customDelimiter === '' ) {
						Utils.showNotice( 'Please enter a custom delimiter', 'error' );
						return false;
					}
				}
				break;
			case 4:
				// Validate field mapping
				const mappedFields = this.getFieldMapping();
				if ( ! mappedFields || mappedFields.length === 0 ) {
					Utils.showNotice(
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
			jQuery( '.aie-post-options' ).hide();
			jQuery( '.aie-media-options' ).show();
		} else {
			jQuery( '.aie-post-options' ).show();
			jQuery( '.aie-media-options' ).hide();
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
				'Invalid file type. Please upload CSV or JSON files only.',
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
				console.log( 'Upload complete, result:', result );
				
				// Check for validation errors
				if ( result.error ) {
					console.log( 'Validation error detected:', result.error );
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
				console.log( 'File data stored:', this.fileData );
				
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
				Utils.showNotice( 'File uploaded successfully', 'success' );

				// Show warning if present
				if ( result.warning ) {
					Utils.showNotice( result.warning, 'warning' );
				}
			},
			onError: ( error ) => {
				// Upload failed
				Utils.showNotice(
					'Upload failed: ' + error.message,
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
			console.error( 'Error reloading preview:', error );
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
		console.log( 'loadPreview called, fileData:', this.fileData );
		
		if ( ! this.fileData ) {
			Utils.showNotice( 'No file data available', 'error' );
			return;
		}

		// Check if there's an error
		if ( this.fileData.hasError ) {
			console.log( 'File has validation error:', this.fileData.error );
			return; // Error display is handled in showStep
		}

		if ( ! this.fileData.preview ) {
			Utils.showNotice( 'No preview data available', 'error' );
			return;
		}

		const preview = this.fileData.preview;
		const format = this.fileData.format || 'csv';

		console.log( 'Preview data:', preview );
		console.log( 'Format:', format );

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
		jQuery( '.aie-preview-note' ).text( 'Showing first 5 rows' );

		const $table = jQuery( '.aie-preview-table' );

		// Build table header
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
		console.log( 'buildFieldMapping called' );
		
		if ( ! this.fileData || ! this.fileData.columns ) {
			console.log( 'No file data or columns' );
			return;
		}

		const contentType = jQuery( 'input[name="content_type"]:checked' ).val();
		console.log( 'Content type:', contentType );
		
		// Build source fields (from file)
		this.buildSourceFields();
		
		// Build target fields (WordPress fields)
		this.buildTargetFields( contentType );
		
		// Load dynamic ACF fields
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
	 * Build target WordPress fields
	 */
	buildTargetFields( contentType ) {
		const $container = jQuery( '#aie-target-fields' );
		
		// Get fields for content type
		const fieldGroups = this.getFieldsByContentType( contentType );
		console.log( 'Field groups:', fieldGroups );
		
		let html = '';

		fieldGroups.forEach( ( group ) => {
			html += `<div class="aie-field-group">`;
			html += `<div class="aie-field-group-label">${ group.label }</div>`;
			
			group.options.forEach( ( field ) => {
				// Skip special fields (except template field)
				if ( field.value.startsWith( '_' ) && field.value !== '_wp_page_template' ) {
					return;
				}

				// Custom fields with add button
				if ( field.custom ) {
					html += `
						<div class="aie-target-field aie-custom-field-template" data-field-type="${ field.type || 'string' }" data-multiple="${ field.multiple || false }">
							<div class="aie-field-icon">
								<span class="dashicons dashicons-plus"></span>
							</div>
							<div class="aie-field-info">
								<div class="aie-field-label">${ field.label }</div>
								<button type="button" class="aie-add-custom-field button button-small">+ Add</button>
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
		
		const title = isTaxonomy ? 'Add Taxonomy Field' : 'Add Custom Field';
		const placeholder = isTaxonomy ? 'Enter taxonomy slug (e.g., category, post_tag, product_cat)' : 'Enter field key (e.g., _custom_price)';
		const icon = isTaxonomy ? 'dashicons-category' : 'dashicons-admin-plugins';
		
		// Taxonomy format options
		const taxonomyFormatField = isTaxonomy ? `
			<label style="margin-top: 15px;">
				<strong>Data Format:</strong>
				<select class="aie-taxonomy-format regular-text">
					<option value="id">Term ID (e.g., 5, 12, 23)</option>
					<option value="slug">Term Slug (e.g., technology, news)</option>
					<option value="name" selected>Term Name (e.g., Technology, News)</option>
				</select>
				<p class="description" style="margin-top: 5px;">
					Select the format of taxonomy data in your CSV file.
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
						</button>
					</div>
					<div class="aie-modal-body">
						<label>
							<strong>Taxonomy Slug:</strong>
							<input type="text" class="aie-custom-field-input regular-text" placeholder="${ placeholder }" />
							${ isTaxonomy ? '<p class="description" style="margin-top: 5px;">The slug of the taxonomy (category, post_tag, or custom taxonomy).</p>' : '' }
						</label>
						${ taxonomyFormatField }
					</div>
					<div class="aie-modal-footer">
						<button type="button" class="button aie-modal-cancel">Cancel</button>
						<button type="button" class="button button-primary aie-modal-add">Add Field</button>
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
				alert( 'Please enter a field name' );
				return;
			}
			
			// Get taxonomy format if applicable
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
		const baseFields = [
			{
				label: 'Standard',
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
				label: 'Author',
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
					{ value: 'menu_order', label: 'Menu Order', type: 'number' },
					{ value: 'post_parent', label: 'Parent ID', type: 'number' },
				],
			},
		];

		// Media
		if ( contentType === 'media' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'post_title', label: 'Title', type: 'string' },
						{ value: 'post_content', label: 'Description', type: 'string' },
						{ value: 'post_excerpt', label: 'Caption', type: 'string' },
						{ value: 'alt_text', label: 'Alt Text', type: 'string' },
					],
				},
				{
					label: 'File Information',
					options: [
						{ value: 'guid', label: 'File URL (GUID)', type: 'url' },
						{ value: 'file_url', label: 'File URL', type: 'url' },
						{ value: 'file_path', label: 'File Path (Relative)', type: 'string' },
						{ value: 'file_name', label: 'File Name', type: 'string' },
						{ value: 'file_extension', label: 'File Extension', type: 'string' },
						{ value: 'post_mime_type', label: 'MIME Type', type: 'string' },
						{ value: 'file_size', label: 'File Size (bytes)', type: 'number' },
					],
				},
				{
					label: 'Image Dimensions',
					options: [
						{ value: 'width', label: 'Width (px)', type: 'number' },
						{ value: 'height', label: 'Height (px)', type: 'number' },
					],
				},
				{
					label: 'Dates',
					options: [
						{ value: 'post_date', label: 'Upload Date', type: 'date' },
						{ value: 'post_modified', label: 'Modified Date', type: 'date' },
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
					label: 'Attachment',
					options: [
						{ value: 'post_parent', label: 'Attached To (Post ID)', type: 'number' },
						{ value: 'attached_post_title', label: 'Attached Post Title', type: 'string' },
					],
				},
				{
					label: 'Custom Fields (Meta)',
					options: [
						{ value: 'meta_key', label: 'Meta Key (for any custom field)', type: 'string' },
						{ value: 'meta_value', label: 'Meta Value', type: 'string' },
					],
				},
			];
		}

		// Pages (uses base fields, no taxonomy section)
		if ( contentType === 'page' ) {
			return baseFields;
		}

		// Users
		if ( contentType === 'user' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'user_login', label: 'Username', type: 'string' },
						{ value: 'user_email', label: 'Email', type: 'string' },
						{ value: 'display_name', label: 'Display Name', type: 'string' },
						{ value: 'user_nicename', label: 'Nice Name', type: 'string' },
					],
				},
				{
					label: 'Profile',
					options: [
						{ value: 'first_name', label: 'First Name', type: 'string' },
						{ value: 'last_name', label: 'Last Name', type: 'string' },
						{ value: 'nickname', label: 'Nickname', type: 'string' },
						{ value: 'description', label: 'Bio', type: 'string' },
						{ value: 'user_url', label: 'Website', type: 'string' },
						{ value: 'avatar_url', label: 'Avatar URL', type: 'string' },
					],
				},
				{
					label: 'Role & Permissions',
					options: [
						{ value: 'role', label: 'Role', type: 'string' },
						{ value: 'capabilities', label: 'Capabilities (Array)', type: 'array' },
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
						{ value: 'user_status', label: 'User Status', type: 'number' },
					],
				},
				{
					label: 'Custom Fields (User Meta)',
					options: [
						{ value: 'meta_key', label: 'User Meta Key', type: 'string' },
						{ value: 'meta_value', label: 'User Meta Value', type: 'string' },
					],
				},
			];
		}

		// WooCommerce Products
		if ( contentType === 'woo_product' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'Product ID', type: 'number' },
						{ value: 'post_title', label: 'Product Name', type: 'string' },
						{ value: 'post_name', label: 'Slug', type: 'string' },
						{ value: 'post_status', label: 'Status', type: 'string' },
						{ value: 'sku', label: 'SKU', type: 'string' },
						{ value: 'post_author', label: 'Author ID', type: 'number' },
					],
				},
				{
					label: 'Content',
					options: [
						{ value: 'post_content', label: 'Description', type: 'string' },
						{ value: 'post_excerpt', label: 'Short Description', type: 'string' },
					],
				},
				{
					label: 'Pricing',
					options: [
						{ value: 'regular_price', label: 'Regular Price', type: 'number' },
						{ value: 'sale_price', label: 'Sale Price', type: 'number' },
						{ value: 'tax_status', label: 'Tax Status', type: 'string' },
						{ value: 'tax_class', label: 'Tax Class', type: 'string' },
					],
				},
				{
					label: 'Inventory',
					options: [
						{ value: 'stock_quantity', label: 'Stock Quantity', type: 'number' },
						{ value: 'stock_status', label: 'Stock Status', type: 'string' },
						{ value: 'manage_stock', label: 'Manage Stock', type: 'boolean' },
						{ value: 'backorders', label: 'Backorders', type: 'string' },
					],
				},
				{
					label: 'Product Type',
					options: [
						{ value: 'product_type', label: 'Product Type', type: 'string' },
						{ value: 'downloadable', label: 'Downloadable', type: 'boolean' },
						{ value: 'virtual', label: 'Virtual', type: 'boolean' },
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
					label: 'Media',
					options: [
						{ value: 'featured_image', label: 'Featured Image', type: 'string' },
						{ value: 'product_gallery', label: 'Gallery Images', type: 'array' },
					],
				},
				{
					label: 'Taxonomy',
					options: [
						{ value: 'product_cat', label: 'Categories', type: 'string' },
						{ value: 'product_tag', label: 'Tags', type: 'string' },
					],
				},
				{
					label: 'Reviews',
					options: [
						{ value: 'average_rating', label: 'Average Rating', type: 'number' },
						{ value: 'review_count', label: 'Review Count', type: 'number' },
						{ value: 'comment_status', label: 'Reviews Enabled', type: 'string' },
					],
				},
				{
					label: 'Visibility',
					options: [
						{ value: 'featured', label: 'Featured', type: 'boolean' },
						{ value: 'visibility', label: 'Catalog Visibility', type: 'string' },
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
					label: 'Custom Fields (Meta)',
					options: [
						{ value: 'meta_key', label: 'Meta Key (for any custom field)', type: 'string' },
						{ value: 'meta_value', label: 'Meta Value', type: 'string' },
					],
				},
			];
		}

		// Default - return post fields with taxonomies and custom fields
		return [
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
		];
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
					<span class="dashicons dashicons-wordpress"></span>
					<strong>${ targetField }</strong>
				</div>
				${ functionsHtml }
				<div class="aie-mapping-actions">
					<button type="button" class="button button-small aie-add-function" data-source-index="${ sourceIndex }" data-target-field="${ targetField }" title="Add transformation function">
						<span class="dashicons dashicons-admin-tools"></span>
					</button>
					<button type="button" class="button button-small aie-remove-row-mapping" data-source-index="${ sourceIndex }" data-target-field="${ targetField }" title="Remove mapping">
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
		const totalFields = this.fileData?.columns?.length || 0;
		
		// Count unique source fields that are used
		const usedSourceIndexes = new Set();
		jQuery( '.aie-mapping-row' ).each( function() {
			usedSourceIndexes.add( jQuery( this ).data( 'source-index' ) );
		} );
		const mappedCount = usedSourceIndexes.size;

		jQuery( '.aie-mapped-count' ).text( mappedCount );
		jQuery( '.aie-total-fields' ).text( totalFields );

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
				Utils.showNotice( 'Failed to load functions', 'error' );
				return;
			}

			this.showFunctionModal( sourceIndex, targetField, response.data );
		} catch ( error ) {
			Utils.showNotice( 'Error loading functions: ' + error.message, 'error' );
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
							Field Transformation Functions
						</h2>
						<button type="button" class="aie-modal-close">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
					<div class="aie-modal-body">
						<!-- Field Info -->
						<div class="aie-field-info">
							<div class="aie-field-info-item">
								<strong>Field:</strong>
								<span class="aie-current-field-label">${ Utils.escapeHtml( sourceField ) }</span>
							</div>
							<div class="aie-field-info-item">
								<strong>Type:</strong>
								<span class="aie-current-field-type">${ targetField }</span>
							</div>
						</div>

						<!-- Applied Functions List -->
						<div class="aie-applied-functions">
							<h3>
								Applied Functions
								<span class="aie-functions-count">(0)</span>
							</h3>
							
							<div class="aie-functions-pipeline" id="aie-functions-pipeline">
								<div class="aie-no-functions">
									<span class="dashicons dashicons-info"></span>
									<p>No functions applied yet. Add functions from the list below.</p>
								</div>
								
								<div class="aie-function-items" id="aie-function-items">
									<!-- Functions will be added here -->
								</div>
							</div>

							<div class="aie-pipeline-hint">
								<span class="dashicons dashicons-info"></span>
								Functions are applied in order from top to bottom. Drag to reorder.
							</div>
						</div>

						<!-- Available Functions -->
						<div class="aie-available-functions">
							<h3>Available Functions</h3>
							
							<!-- Search Functions -->
							<div class="aie-functions-search">
								<input 
									type="text" 
									id="aie-functions-search" 
									class="regular-text" 
									placeholder="Search functions..."
								>
								<span class="dashicons dashicons-search"></span>
							</div>

							<!-- Functions Filter -->
							<div class="aie-functions-filter">
								<label>
									<input type="radio" name="functions-filter" value="all" checked>
									All
								</label>
								<label>
									<input type="radio" name="functions-filter" value="library">
									Library
								</label>
								<label>
									<input type="radio" name="functions-filter" value="custom">
									Custom
								</label>
							</div>

							<!-- Functions List -->
							<div class="aie-functions-list" id="aie-functions-list">
								<div class="aie-functions-loading">
									<span class="spinner is-active"></span>
									<p>Loading functions...</p>
								</div>
							</div>

							<!-- Quick Add Link -->
							<div class="aie-functions-quick-add">
								<a href="#" class="aie-create-new-function">
									<span class="dashicons dashicons-plus-alt"></span>
									Create New Function
								</a>
							</div>
						</div>

						<!-- Preview Section -->
						<div class="aie-function-preview">
							<h3>Preview Transformation</h3>
							
							<div class="aie-preview-controls">
								<div class="aie-preview-input-group">
									<label for="aie-preview-input">
										Test Value:
									</label>
									<input 
										type="text" 
										id="aie-preview-input" 
										class="regular-text" 
										placeholder="Enter test value..."
									>
								</div>
								<button type="button" class="button aie-test-pipeline">
									<span class="dashicons dashicons-media-code"></span>
									Test Pipeline
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
							Cancel
						</button>
						<button type="button" class="button button-primary aie-save-field-functions">
							<span class="dashicons dashicons-yes"></span>
							Apply Functions
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
					<p>No functions available yet.</p>
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
				.html( `
					<div class="aie-function-list-info">
						<span class="aie-function-list-name">${ Utils.escapeHtml( snippet.name ) }</span>
						<span class="aie-function-list-desc">${ Utils.escapeHtml( snippet.description || '' ) }</span>
					</div>
					<button type="button" class="button button-small aie-add-function-btn">Add</button>
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
			Utils.showNotice( 'Creating custom functions will be available in the Functions Library section', 'info' );
		} );

		// Test Pipeline
		jQuery( '.aie-test-pipeline' ).on( 'click', function () {
			const testValue = jQuery( '#aie-preview-input' ).val();
			
			if ( ! testValue ) {
				Utils.showNotice( 'Please enter a test value', 'warning' );
				return;
			}

			const functions = [];
			jQuery( '#aie-function-items .aie-function-item' ).each( function () {
				functions.push( jQuery( this ).data( 'function-id' ) );
			} );

			if ( functions.length === 0 ) {
				Utils.showNotice( 'Please add at least one function to test', 'warning' );
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
		
		$steps.html( '<div class="aie-preview-loading"><span class="spinner is-active"></span> Testing...</div>' );
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
						<div class="aie-step-label">Initial Value:</div>
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
						<div class="aie-step-label">Final Result:</div>
						<div class="aie-step-value"><strong>${ Utils.escapeHtml( response.data.final ) }</strong></div>
					</div>
				`;

				$steps.html( html );
			} else {
				$steps.html( `<div class="notice notice-error inline"><p>${ response.data?.message || 'Failed to test pipeline' }</p></div>` );
			}
		} catch ( error ) {
			$steps.html( `<div class="notice notice-error inline"><p>Error: ${ error.message }</p></div>` );
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
		jQuery( '.aie-search-source' ).on( 'input', function () {
			const query = jQuery( this ).val().toLowerCase();
			jQuery( '.aie-field-card' ).each( function () {
				const fieldName = jQuery( this ).find( '.aie-field-name' ).text().toLowerCase();
				jQuery( this ).toggle( fieldName.includes( query ) );
			} );
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
		
		jQuery( '.aie-search-target' ).on( 'keyup input', performSearch );
	},

	/**
	 * Get target fields for content type
	 */
	getTargetFields( contentType ) {
		const fields = {
			post: [
				{ value: 'post_title', label: 'Title' },
				{ value: 'post_content', label: 'Content' },
				{ value: 'post_excerpt', label: 'Excerpt' },
				{ value: 'post_status', label: 'Status' },
				{ value: 'post_author', label: 'Author' },
				{ value: 'post_date', label: 'Date' },
				{ value: 'post_name', label: 'Slug' },
				{ value: 'categories', label: 'Categories' },
				{ value: 'tags', label: 'Tags' },
				{ value: 'featured_image', label: 'Featured Image' },
			],
			media: [
				{ value: 'post_title', label: 'Title' },
				{ value: 'post_content', label: 'Description' },
				{ value: 'post_excerpt', label: 'Caption' },
				{ value: 'file_url', label: 'File URL' },
				{ value: 'alt_text', label: 'Alt Text' },
			],
		};

		return fields[ contentType ] || fields.post;
	},

	/**
	 * Auto-map fields
	 */
	autoMapFields() {
		let mappedCount = 0;

		// Clear existing mappings
		this.clearFieldMapping();

		// Try to auto-match source to target fields
		jQuery( '.aie-field-card' ).each( ( index, sourceCard ) => {
			const $sourceCard = jQuery( sourceCard );
			const sourceField = $sourceCard.data( 'source-field' ).toLowerCase();
			const sourceIndex = $sourceCard.data( 'source-index' );

			// Try to find matching target
			let matched = false;

			jQuery( '.aie-target-field' ).each( ( i, targetField ) => {
				if ( matched ) return;

				const $targetField = jQuery( targetField );
				const targetFieldValue = $targetField.data( 'target-field' ).toLowerCase();
				const targetLabel = $targetField.find( '.aie-field-label' ).text().toLowerCase();

				// Check for exact or partial match
				if (
					sourceField === targetFieldValue ||
					sourceField === targetLabel ||
					sourceField.includes( targetFieldValue ) ||
					targetFieldValue.includes( sourceField ) ||
					sourceField.replace( /_/g, ' ' ) === targetLabel
				) {
					// Create mapping
					this.createMapping(
						$sourceCard.data( 'source-field' ),
						sourceIndex,
						$targetField.data( 'target-field' ),
						$targetField.data( 'field-type' ),
						$targetField
					);

					$sourceCard.addClass( 'mapped' );
					matched = true;
					mappedCount++;
				}
			} );
		} );

		this.updateMappingStats();
		Utils.showNotice( `Auto-mapped ${ mappedCount } fields`, 'success' );
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
				<p>Drag source columns to WordPress fields to create mappings</p>
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

		console.log( 'Field mapping:', mapping );
		return mapping;
	},

	/**
	 * Start import
	 */
	async startImport() {
		try {
			const data = {
				file_path: this.fileData.file_path,
				content_type: jQuery(
					'input[name="content_type"]:checked'
				).val(),
				format: this.fileData.format,
				field_mapping: this.getFieldMapping(),
				duplicate_handling: jQuery(
					'input[name="duplicate_handling"]:checked'
				).val(),
				post_status: jQuery( '[name="post_status"]' ).val(),
				post_type: jQuery( '[name="post_type"]' ).val(),
				download_images: jQuery( '[name="download_images"]' ).is(
					':checked'
				),
				batch_size:
					parseInt( jQuery( '[name="batch_size"]' ).val() ) || 50,
			};

			const response = await Utils.ajax( 'aie_import_start', data );

			this.jobId = response.job_id;
			this.showStep( 6 );
			this.startProgressTracking();

			Utils.showNotice( 'Import started successfully', 'success' );
		} catch ( error ) {
			Utils.handleError( error, 'Start import' );
		}
	},

	/**
	 * Start progress tracking
	 */
	startProgressTracking() {
		this.progressInterval = setInterval( () => {
			this.updateProgress();
		}, 2000 );
	},

	/**
	 * Update import progress
	 */
	async updateProgress() {
		try {
			const response = await Utils.ajax( 'aie_import_get_progress', {
				job_id: this.jobId,
			} );

			Utils.updateProgressBar( jQuery( '.aie-step-6' ), response );

			if ( response.status === 'completed' ) {
				this.onImportComplete( response );
			} else if ( response.status === 'failed' ) {
				this.onImportFailed( response );
			}
		} catch ( error ) {
			console.error( 'Progress update error:', error );
		}
	},

	/**
	 * Handle import completion
	 */
	onImportComplete( result ) {
		clearInterval( this.progressInterval );

		jQuery( '.aie-import-results' ).show();
		jQuery( '.aie-result-processed' ).text( result.processed || 0 );
		jQuery( '.aie-result-success' ).text( result.success || 0 );
		jQuery( '.aie-result-failed' ).text( result.failed || 0 );
		jQuery( '.aie-result-duration' ).text(
			result.estimates?.elapsed_formatted || '0s'
		);

		jQuery( '.aie-cancel-import' ).hide();
		jQuery( '.aie-new-import' ).show();

		Utils.showNotice( 'Import completed successfully!', 'success' );
	},

	/**
	 * Handle import failure
	 */
	onImportFailed( result ) {
		clearInterval( this.progressInterval );
		Utils.showNotice(
			'Import failed: ' + ( result.error || 'Unknown error' ),
			'error'
		);
	},

	/**
	 * Cancel import
	 */
	async cancelImport() {
		if ( ! confirm( 'Are you sure you want to cancel this import?' ) ) {
			return;
		}

		try {
			await Utils.ajax( 'aie_import_cancel', { job_id: this.jobId } );
			clearInterval( this.progressInterval );
			Utils.showNotice( 'Import cancelled', 'info' );
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
		clearInterval( this.progressInterval );

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
				console.log( 'ACF fields load error:', error );
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
			'menu',
			'block_theme_settings',
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
				console.log( 'Yoast fields load error:', error );
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
