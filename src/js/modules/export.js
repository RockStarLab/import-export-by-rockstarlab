/**
 * Export Module
 *
 * Handles the export wizard functionality
 */

import Utils from './utils';
import ExportStep3 from './export-step-3';

const ExportModule = {
	currentStep: 1,
	totalSteps: 5,
	jobId: null,
	progressInterval: null,
	step3Instance: null,

	/**
	 * Initialize module
	 */
	init() {
		if ( ! jQuery( '#wp-aie-export' ).length ) {
			return;
		}

		this.bindEvents();
		this.showStep( 1 );
		
		// Initialize Step 3 drag and drop
		this.step3Instance = new ExportStep3();
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $wizard = jQuery( '#wp-aie-export' );

		// Content type filter/search
		$wizard.on( 'input', '#aie-content-type-search', ( e ) =>
			this.filterContentTypes( e )
		);

		// Step navigation
		$wizard.on( 'click', '.aie-next-step', () => this.nextStep() );
		$wizard.on( 'click', '.aie-prev-step', () => this.prevStep() );

		// Content type
		$wizard.on( 'change', 'input[name="content_type"]', ( e ) =>
			this.onContentTypeChange( e )
		);

		// Filters
		$wizard.on(
			'change',
			'.aie-export-filters input, .aie-export-filters select',
			Utils.debounce( () => this.refreshCount( false ), 500 )
		);
		$wizard.on( 'click', '.aie-step-2 .aie-refresh-count', () => this.refreshCount( true ) );

		// Field selection
		$wizard.on( 'click', '.aie-select-all-fields', () =>
			this.selectAllFields( true )
		);
		$wizard.on( 'click', '.aie-deselect-all-fields', () =>
			this.selectAllFields( false )
		);
		$wizard.on( 'click', '.aie-select-common-fields', () =>
			this.selectCommonFields()
		);

		// Format selection
		$wizard.on( 'change', 'input[name="format"]', ( e ) =>
			this.onFormatChange( e )
		);
		
		// CSV delimiter change
		$wizard.on( 'change', 'select[name="csv_delimiter"]', ( e ) =>
			this.onDelimiterChange( e )
		);

		// Export actions
		$wizard.on( 'click', '.aie-start-export', () => this.startExport() );
		$wizard.on( 'click', '.aie-cancel-export', () => this.cancelExport() );
		$wizard.on( 'click', '.aie-download-file', () => this.downloadFile() );
		$wizard.on( 'click', '.aie-new-export', () => this.newExport() );

		// Dynamic Filters
		$wizard.on( 'click', '.aie-add-filter', () => this.addFilterRow() );
		$wizard.on( 'click', '.aie-remove-filter', ( e ) =>
			this.removeFilterRow( e )
		);
		$wizard.on( 'change', '.aie-filter-field', ( e ) =>
			this.onFilterFieldChange( e )
		);
		
		// Dynamic filter value changes - auto refresh count when filter is complete
		$wizard.on( 'change', '.aie-filter-condition', ( e ) => {
			const $row = jQuery( e.target ).closest( '.aie-filter-row' );
			const $value = $row.find( '.aie-filter-value' );
			
			// Clear the value when condition changes
			if ( $value.length ) {
				$value.val( '' );
			}
			
			// Update input type based on condition
			this.updateValueInputType( $row );
			
			if ( this.isFilterRowComplete( $row ) ) {
				Utils.debounce( () => this.refreshCount( false ), 500 )();
			}
		} );
		$wizard.on( 'input', '.aie-filter-value', ( e ) => {
			const $row = jQuery( e.target ).closest( '.aie-filter-row' );
			if ( this.isFilterRowComplete( $row ) ) {
				Utils.debounce( () => this.refreshCount( false ), 1000 )();
			}
		} );
		$wizard.on( 'change', '.aie-filter-value', ( e ) => {
			const $row = jQuery( e.target ).closest( '.aie-filter-row' );
			if ( this.isFilterRowComplete( $row ) ) {
				Utils.debounce( () => this.refreshCount( false ), 500 )();
			}
		} );
	},

	/**
	 * Show specific step
	 */
	showStep( step ) {
		const $wizard = jQuery( '#wp-aie-export' );

		$wizard.find( '.aie-step' ).removeClass( 'active' );
		$wizard.find( `.aie-step-${ step }` ).addClass( 'active' );

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
		
		const previousStep = this.currentStep;

		if ( step === 1 ) {
			// Hide database table selection and info when returning to step 1
			jQuery( '.aie-table-selection-section' ).hide();
			jQuery( '.aie-table-info' ).hide();
			// Reset count only when going back to step 1
			this.resetCount();
		} else if ( step === 2 ) {
			// Check if database_table type is selected
			const contentType = jQuery( 'input[name="content_type"]:checked' ).val();
			if ( contentType === 'database_table' ) {
				jQuery( '.aie-table-selection-section' ).show();
				// Only load database tables if coming from step 1 or if table not selected
				const $tableSelect = jQuery( '#aie-table-name' );
				if ( previousStep === 1 || !$tableSelect.val() ) {
					this.loadDatabaseTables();
				}
			} else {
				jQuery( '.aie-table-selection-section' ).hide();
			}
			
			this.refreshCount( false ); // Don't show spinner on auto-refresh
		} else if ( step === 3 ) {
			// Load dynamic fields when entering step 3
			if ( this.step3Instance ) {
				this.step3Instance.loadDynamicFields();
			}
		}
	},

	nextStep() {
		if ( this.currentStep < this.totalSteps ) {
			let nextStep = this.currentStep + 1;
			
			// Skip step 2 (filters) for content types that don't need filtering
			if ( nextStep === 2 && this.shouldSkipFilters() ) {
				nextStep = 3;
			}
			
			this.showStep( nextStep );
		}
	},

	prevStep() {
		// Clear step 3 fields when going back from step 3
		if ( this.currentStep === 3 && this.step3Instance ) {
			this.step3Instance.clearAllFields();
		}
		
		if ( this.currentStep > 1 ) {
			let prevStep = this.currentStep - 1;
			
			// Skip step 2 (filters) when going back for content types that don't need filtering
			if ( prevStep === 2 && this.shouldSkipFilters() ) {
				prevStep = 1;
			}
			
			// Hide table selection when going back to step 1
			if ( prevStep === 1 ) {
				jQuery( '.aie-table-selection-section' ).hide();
				jQuery( '.aie-table-info' ).hide();
			}
			
			this.showStep( prevStep );
		}
	},

	/**
	 * Check if current content type should skip filters step
	 */
	shouldSkipFilters() {
		const contentType = jQuery( 'input[name="content_type"]:checked' ).val();
		
		// Content types that don't need filtering (go straight from step 1 to step 3)
		const noFilterTypes = [ 'block_theme_settings' ];
		
		return noFilterTypes.includes( contentType );
	},

	/**
	 * Handle content type change
	 */
	onContentTypeChange( e ) {
		const contentType = jQuery( e.target ).val();

		// Clear existing filters
		jQuery( '#aie-filters-list' ).empty();

		// Show/hide table selection section
		if ( contentType === 'database_table' ) {
			jQuery( '.aie-table-selection-section' ).show();
			jQuery( '.aie-custom-filters-section' ).show();
		} else {
			jQuery( '.aie-table-selection-section' ).hide();
		}

		// Show/hide custom filters section
		// Note: block_theme_settings is excluded - it doesn't need filters (goes straight to step 3)
		const filterableTypes = [ 
			'post', 'page', 'media', 'menu', 'user', 'comment', 
			'custom_post_types', 'taxonomy',
			'woo_product', 'woo_order', 'woo_coupon', 'woo_attribute',
			'database_table'
		];
		if ( filterableTypes.includes( contentType ) ) {
			jQuery( '.aie-custom-filters-section' ).show();
		} else {
			jQuery( '.aie-custom-filters-section' ).hide();
		}

		// Update field groups visibility if needed
		if ( contentType === 'media' ) {
			jQuery( '.aie-post-field-group' ).hide();
			jQuery( '.aie-media-field-group' ).show();
		} else {
			jQuery( '.aie-post-field-group' ).show();
			jQuery( '.aie-media-field-group' ).hide();
		}

		// Refresh count (without spinner)
		this.refreshCount( false );
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
		let visibleCount = 0;

		if ( searchTerm === '' ) {
			// Show all if search is empty
			$contentTypes.show();
			$filterCount.hide();
			$noResults.hide();
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
		} else {
			$noResults.hide();
		}
	},

	/**
	 * Refresh item count
	 */
	async refreshCount( showSpinner = true ) {
		const $count = jQuery( '.aie-step-2 .aie-count-value' );
		const $spinner = jQuery( '.aie-step-2 .aie-item-count .spinner' );
		const $refreshBtn = jQuery( '.aie-step-2 .aie-refresh-count' );

		if ( showSpinner ) {
			$spinner.addClass( 'is-active' );
		}
		$refreshBtn.addClass( 'is-refreshing' );

		try {
			const contentType = jQuery(
				'input[name="content_type"]:checked'
			).val();

			// Prepare options based on content type
			let options = {};
			
			if ( contentType === 'database_table' ) {
				// For database tables, get table name from dropdown
				const $tableDropdown = jQuery( '#aie-table-name' );
				const dynamicFiltersData = this.getDynamicFilters();
				options = {
					table_name: $tableDropdown.val(),
					filters: dynamicFiltersData.filters,
				};
		} else {
			// For other types, use dynamic filters
			const dynamicFiltersData = this.getDynamicFilters();
			
			console.log('Dynamic filters data:', dynamicFiltersData);
			
			// Map content type to post_type for post-based exporters
			const postType = this.getPostTypeForContentType( contentType );
			if ( postType ) {
				options.post_type = postType;
			}
			
			// Add dynamic filters as query parameters
			if ( dynamicFiltersData.filters.length > 0 ) {
				options.filters = dynamicFiltersData.filters;
			}
			
			// Add custom field filters
			if ( dynamicFiltersData.custom_fields.length > 0 ) {
				options.custom_fields = dynamicFiltersData.custom_fields;
			}
			
			// Add taxonomy filters
			if ( dynamicFiltersData.taxonomy.length > 0 ) {
				options.taxonomy = dynamicFiltersData.taxonomy;
			}
		}

		console.log('Sending options to backend:', options);			const response = await Utils.ajax( 'aie_export_get_count', {
				export_type: contentType,
				options: options,
			} );

			console.log('Received count response:', response);
			$count.text( response.count || 0 );
			
			// Update next button state based on count
			this.updateStep2NextButton();
		} catch ( error ) {
			$count.text( '-' );
			console.error( 'Count error:', error );
			
			// Disable next button on error
			this.updateStep2NextButton();
		} finally {
			$spinner.removeClass( 'is-active' );
			$refreshBtn.removeClass( 'is-refreshing' );
		}
	},

	/**
	 * Reset count display
	 */
	resetCount() {
		const $count = jQuery( '.aie-step-2 .aie-count-value' );
		const $spinner = jQuery( '.aie-step-2 .aie-item-count .spinner' );
		const $refreshBtn = jQuery( '.aie-step-2 .aie-refresh-count' );
		
		$count.text( '-' );
		$spinner.removeClass( 'is-active' );
		$refreshBtn.removeClass( 'is-refreshing' );
		
		// Disable next button when count is reset
		this.updateStep2NextButton();
	},

	/**
	 * Update step 2 next button state based on item count
	 */
	updateStep2NextButton() {
		const $nextBtn = jQuery( '.aie-step-2 .aie-next-step' );
		const $count = jQuery( '.aie-step-2 .aie-count-value' );
		const countText = $count.text();
		const count = parseInt( countText, 10 );
		
		// Remove previous event handlers
		$nextBtn.off( 'mouseenter.tooltip mouseleave.tooltip' );
		
		// Disable if count is 0, NaN, or '-'
		if ( countText === '-' || isNaN( count ) || count === 0 ) {
			$nextBtn.prop( 'disabled', true );
			
			// Show tooltip on hover
			$nextBtn.on( 'mouseenter.tooltip', () => {
				this.showNextButtonTooltip( $nextBtn );
			} );
			
			// Hide tooltip on mouse leave
			$nextBtn.on( 'mouseleave.tooltip', () => {
				this.hideNextButtonTooltip( $nextBtn );
			} );
		} else {
			$nextBtn.prop( 'disabled', false );
			
			// Hide tooltip if it's shown
			this.hideNextButtonTooltip( $nextBtn );
		}
	},

	/**
	 * Show custom tooltip on Next button
	 */
	showNextButtonTooltip( $button ) {
		// Remove any existing tooltips
		jQuery( '.aie-custom-tooltip' ).remove();
		// Create tooltip element
		const $tooltip = jQuery( '<div>' )
			.addClass( 'aie-custom-tooltip aie-custom-pointer' )
			.html( `
				<div class="aie-pointer-icon">
					<span class="dashicons dashicons-warning"></span>
				</div>
				<div class="aie-pointer-content">
					<h3>No Data Available</h3>
					<p>Adjust your filters or select a different content type to continue with the export.</p>
				</div>
			` );
		// Append to body
		jQuery( 'body' ).append( $tooltip );
		// Position tooltip
		const buttonOffset = $button.offset();
		const buttonWidth = $button.outerWidth();
		const buttonHeight = $button.outerHeight();
		const tooltipWidth = $tooltip.outerWidth();
		const tooltipHeight = $tooltip.outerHeight();
		// Position above the button, centered
		const left = buttonOffset.left + ( buttonWidth / 2 ) - ( tooltipWidth / 2 );
		const top = buttonOffset.top - tooltipHeight - 10; // 10px gap
		$tooltip.css( {
			left: left + 'px',
			top: top + 'px',
			zIndex: 9999
		} );
		// Fade in
		setTimeout( () => {
			$tooltip.addClass( 'aie-tooltip-visible' );
		}, 10 );
	},

	/**
	 * Hide custom tooltip
	 */
	hideNextButtonTooltip( $button ) {
		const $tooltip = jQuery( '.aie-custom-tooltip' );
		
		if ( $tooltip.length ) {
			$tooltip.removeClass( 'aie-tooltip-visible' );
			
			// Remove after animation
			setTimeout( () => {
				$tooltip.remove();
			}, 200 );
		}
	},

	/**
	 * Map content type to WordPress post_type
	 */
	getPostTypeForContentType( contentType ) {
		const postTypeMap = {
			post: 'post',
			page: 'page',
			media: 'attachment',
			menu: 'nav_menu_item',
			comment: null, // Comments are not post type
			user: null, // Users are not post type
			block_theme_settings: 'wp_template',
			taxonomy: null, // Taxonomies are not post type
			custom_post_types: null, // Will be determined dynamically
			woo_product: 'product',
			woo_order: 'shop_order',
			woo_coupon: 'shop_coupon',
			woo_attribute: null, // Attributes are taxonomy-based
			custom_table: null, // Not a post type
		};

		return postTypeMap[ contentType ] || null;
	},

	/**
	 * Get filter values
	 */
	getFilters() {
		const filters = {};
		const contentType = jQuery(
			'input[name="content_type"]:checked'
		).val();

		if ( contentType === 'post' ) {
			filters.post_type = jQuery( '[name="post_type"]' ).val();
			filters.post_status =
				jQuery( '[name="post_status[]"]' ).val() || [];
			filters.date_from = jQuery( '[name="date_from"]' ).val();
			filters.date_to = jQuery( '[name="date_to"]' ).val();
			filters.author = jQuery( '[name="author"]' ).val();
			filters.category = jQuery( '[name="category"]' ).val();
			filters.tag = jQuery( '[name="tag"]' ).val();
			filters.search = jQuery( '[name="search"]' ).val();
		} else if ( contentType === 'media' ) {
			filters.mime_type = jQuery( '[name="mime_type"]' ).val();
			filters.date_from = jQuery( '[name="media_date_from"]' ).val();
			filters.date_to = jQuery( '[name="media_date_to"]' ).val();
		}

		return filters;
	},

	/**
	 * Get dynamic filters from filter rows
	 */
	getDynamicFilters() {
		const filters = [];
		const customFields = [];
		const taxonomyFilters = [];
		
		jQuery( '.aie-filter-row' ).each( ( index, row ) => {
			const $row = jQuery( row );
			const field = $row.find( '.aie-filter-field' ).val();
			const fieldType = $row.find( '.aie-filter-field option:selected' ).data( 'type' );

			// Skip table selector for custom_table type
			if ( fieldType === 'table_selector' ) {
				return;
			}

			// Handle post_type_selector type
			if ( fieldType === 'post_type_selector' ) {
				const value = $row.find( '.aie-filter-value' ).val();
				
				if ( value && value.trim() !== '' ) {
					filters.push( {
						field: 'post_type',
						condition: 'equals', // Default condition for post type
						value: value,
					} );
				}
				return;
			}

			// Handle custom_field type
			if ( fieldType === 'custom_field' ) {
				const name = $row.find( '.aie-custom-field-name' ).val();
				const condition = $row.find( '.aie-custom-field-condition' ).val();
				const value = $row.find( '.aie-custom-field-value' ).val();

				if ( name && condition ) {
					const noValueConditions = [ 'is_empty', 'is_not_empty' ];
					if ( noValueConditions.includes( condition ) || ( value && value.trim() !== '' ) ) {
						customFields.push( {
							name: name,
							condition: condition,
							value: value || '',
						} );
					}
				}
				return;
			}

			// Handle taxonomy_filter type
			if ( fieldType === 'taxonomy_filter' ) {
				const taxonomy = $row.find( '.aie-taxonomy-name' ).val();
				const condition = $row.find( '.aie-taxonomy-condition' ).val();
				const terms = $row.find( '.aie-taxonomy-terms' ).val();

				if ( taxonomy && condition && terms && terms.trim() !== '' ) {
					taxonomyFilters.push( {
						taxonomy: taxonomy,
						condition: condition,
						terms: terms,
					} );
				}
				return;
			}

			// Handle regular filters
			const condition = $row.find( '.aie-filter-condition' ).val();
			const value = $row.find( '.aie-filter-value' ).val();

			// Skip empty or incomplete filters
			if ( ! field || ! condition ) {
				return;
			}

			// For conditions that don't need value
			const noValueConditions = [ 'is_empty', 'is_not_empty' ];
			if ( noValueConditions.includes( condition ) || ( value && value.trim() !== '' ) ) {
				filters.push( {
					field: field,
					condition: condition,
					value: value || '',
				} );
			}
		} );

		return {
			filters: filters,
			custom_fields: customFields,
			taxonomy: taxonomyFilters,
		};
	},

	/**
	 * Get custom table filters (deprecated - use getDynamicFilters instead)
	 */
	getCustomTableFilters() {
		const filters = [];
		jQuery( '.aie-filter-row' ).each( ( index, row ) => {
			const $row = jQuery( row );
			const field = $row.find( '.aie-filter-field' ).val();
			const condition = $row.find( '.aie-filter-condition' ).val();
			const value = $row.find( '.aie-filter-value' ).val();

			// Skip table selector row and empty filters
			if ( ! field || field === 'table_name' || ! condition ) {
				return;
			}

			filters.push( {
				field: field,
				condition: condition,
				value: value,
			} );
		} );

		return filters;
	},

	/**
	 * Select/deselect all fields
	 */
	selectAllFields( checked ) {
		jQuery( 'input[name="fields[]"]:visible' ).prop( 'checked', checked );
	},

	/**
	 * Select common fields only
	 */
	selectCommonFields() {
		this.selectAllFields( false );
		const commonFields = [
			'ID',
			'post_title',
			'post_content',
			'post_status',
		];
		commonFields.forEach( ( field ) => {
			jQuery( `input[name="fields[]"][value="${ field }"]` ).prop(
				'checked',
				true
			);
		} );
	},

	/**
	 * Handle format change
	 */
	onFormatChange( e ) {
		const format = jQuery( e.target ).val();

		jQuery( '.aie-format-options > div' ).hide();
		jQuery( `.aie-${ format }-options` ).show();
	},

	/**
	 * Handle delimiter change
	 */
	onDelimiterChange( e ) {
		const delimiter = jQuery( e.target ).val();

		if ( delimiter === 'custom' ) {
			jQuery( '.aie-custom-delimiter-row' ).show();
		} else {
			jQuery( '.aie-custom-delimiter-row' ).hide();
		}
	},

	/**
	 * Get selected fields
	 */
	getSelectedFields() {
		// Get fields from Step 3 drag & drop interface
		if (this.step3Instance && this.step3Instance.selectedFields) {
			return this.step3Instance.selectedFields.map(field => field.field);
		}
		
		// Fallback to old checkbox method (if still used somewhere)
		const fields = [];
		jQuery( 'input[name="fields[]"]:checked' ).each( function () {
			fields.push( jQuery( this ).val() );
		} );
		return fields;
	},

	/**
	 * Start export
	 */
	async startExport() {
		const fields = this.getSelectedFields();

		if ( fields.length === 0 ) {
			Utils.showNotice(
				'Please select at least one field to export',
				'error'
			);
			return;
		}

		try {
			const contentType = jQuery( 'input[name="content_type"]:checked' ).val();
			const dynamicFiltersData = this.getDynamicFilters();
			
			// Get CSV delimiter
			let csvDelimiter = jQuery( '[name="csv_delimiter"]' ).val();
			if ( csvDelimiter === 'custom' ) {
				const customDelimiter = jQuery( '[name="csv_custom_delimiter"]' ).val();
				if ( ! customDelimiter ) {
					Utils.showNotice(
						'Please enter a custom delimiter',
						'error'
					);
					// Set focus to the custom delimiter field
					jQuery( '[name="csv_custom_delimiter"]' ).focus();
					return;
				}
				csvDelimiter = customDelimiter;
			}
			
			const data = {
				export_type: contentType,
				filters: this.getFilters(),
				fields: fields,
				format: jQuery( 'input[name="format"]:checked' ).val(),
				format_options: {
					csv_delimiter: csvDelimiter,
					csv_include_header: jQuery(
						'[name="csv_include_header"]'
					).is( ':checked' ),
					json_pretty_print: jQuery(
						'[name="json_pretty_print"]'
					).is( ':checked' ),
				},
				options: {
					items_per_iteration: parseInt( jQuery( '[name="items_per_iteration"]' ).val() ) || 3,
				},
			};

			// Add field functions if available
			if (this.step3Instance && this.step3Instance.fieldFunctions) {
				data.field_functions = this.step3Instance.fieldFunctions;
			}

			// Add dynamic filters
			if ( dynamicFiltersData.filters.length > 0 ) {
				data.dynamic_filters = dynamicFiltersData.filters;
			}

			// Add custom field filters
			if ( dynamicFiltersData.custom_fields.length > 0 ) {
				data.custom_fields = dynamicFiltersData.custom_fields;
			}

			// Add taxonomy filters
			if ( dynamicFiltersData.taxonomy.length > 0 ) {
				data.taxonomy = dynamicFiltersData.taxonomy;
			}

			const response = await Utils.ajax( 'aie_export_start', data );

			this.jobId = response.job_id;
			this.showStep( 5 );
			this.startProgressTracking();

			// Trigger first batch processing
			this.processNextBatch();

			Utils.showNotice( 'Export started successfully', 'success' );
		} catch ( error ) {
			Utils.handleError( error, 'Start export' );
		}
	},

	/**
	 * Process next export batch
	 */
	async processNextBatch() {
		if ( ! this.jobId ) {
			return;
		}

		try {
			const response = await Utils.ajax( 'aie_export_process_batch', {
				job_id: this.jobId,
			} );
			
			console.log( 'Batch processing response:', response );

			// If not completed, process next batch after small delay
			if ( response && ! response.completed ) {
				setTimeout( () => {
					this.processNextBatch();
				}, 100 );
			}
		} catch ( error ) {
			console.error( 'Batch processing error:', error );
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
	 * Update progress
	 */
	async updateProgress() {
		try {
			const response = await Utils.ajax( 'aie_export_get_progress', {
				job_id: this.jobId,
			} );

			Utils.updateProgressBar( jQuery( '.aie-step-5' ), response );

			if ( response.status === 'completed' ) {
				this.onExportComplete( response );
			} else if ( response.status === 'failed' ) {
				this.onExportFailed( response );
			}
		} catch ( error ) {
			console.error( 'Progress update error:', error );
		}
	},

	/**
	 * Handle export completion
	 */
	onExportComplete( result ) {
		clearInterval( this.progressInterval );

		// Update title
		jQuery( '.aie-step-5 h2' ).text( 'Export Complete!' );
		
		// Hide the description text
		jQuery( '.aie-step-5 .description' ).hide();
		
		// Hide progress container
		jQuery( '.aie-progress-container' ).hide();

		// Show results container
		jQuery( '.aie-export-results' ).show();
		
		// Show and populate the success card
		const $card = jQuery( '.aie-export-complete-card' );
		$card.show();
		
		// Use data from result (progress response)
		jQuery( '.aie-result-processed' ).text( result.processed || result.total || 0 );
		jQuery( '.aie-result-filesize' ).text(
			Utils.formatFileSize( result.file_size || 0 )
		);
		jQuery( '.aie-result-duration' ).text(
			result.estimates?.elapsed_formatted || '0s'
		);

		jQuery( '.aie-cancel-export' ).hide();
		jQuery( '.aie-new-export' ).show();

		Utils.showNotice( 'Export completed successfully!', 'success' );
	},

	/**
	 * Handle export failure
	 */
	onExportFailed( result ) {
		clearInterval( this.progressInterval );
		Utils.showNotice(
			'Export failed: ' + ( result.error || 'Unknown error' ),
			'error'
		);
	},

	/**
	 * Download export file
	 */
	async downloadFile() {
		try {
			const response = await Utils.ajax( 'aie_export_download', {
				job_id: this.jobId,
			} );

			if ( response.download_url ) {
				Utils.downloadFile( response.download_url, response.filename );
			}
		} catch ( error ) {
			Utils.handleError( error, 'Download file' );
		}
	},

	/**
	 * Cancel export
	 */
	async cancelExport() {
		if ( ! confirm( 'Are you sure you want to cancel this export?' ) ) {
			return;
		}

		try {
			await Utils.ajax( 'aie_export_cancel', { job_id: this.jobId } );
			clearInterval( this.progressInterval );
			Utils.showNotice( 'Export cancelled', 'info' );
			this.resetWizard();
		} catch ( error ) {
			Utils.handleError( error, 'Cancel export' );
		}
	},

	/**
	 * Start new export - reload the page
	 */
	newExport() {
		window.location.href = '/wp-admin/admin.php?page=wp-aie-export';
	},

	/**
	 * Reset wizard
	 */
	resetWizard() {
		this.currentStep = 1;
		this.jobId = null;
		clearInterval( this.progressInterval );

		jQuery(
			'#wp-aie-export input[type="text"], #wp-aie-export input[type="date"]'
		).val( '' );
		jQuery( '#wp-aie-export input[type="radio"]:first' ).prop(
			'checked',
			true
		);
		jQuery( '.aie-export-results' ).hide();

		this.showStep( 1 );
	},

	/**
	 * Add new filter row
	 */
	addFilterRow() {
		const template = document.getElementById( 'aie-filter-row-template' );
		const clone = template.content.cloneNode( true );
		const contentType = jQuery( 'input[name="content_type"]:checked' ).val();

		// Populate field options based on content type
		const $fieldSelect = jQuery( clone ).find( '.aie-filter-field' );
		const fields = this.getFieldsByContentType( contentType );

		fields.forEach( ( group ) => {
			const $optgroup = jQuery( '<optgroup>' ).attr(
				'label',
				group.label
			);
			group.options.forEach( ( option ) => {
				$optgroup.append(
					jQuery( '<option>' )
						.val( option.value )
						.text( option.label )
						.data( 'type', option.type )
				);
			} );
			$fieldSelect.append( $optgroup );
		} );

		jQuery( '#aie-filters-list' ).append( clone );

		// Trigger count refresh (without spinner)
		Utils.debounce( () => this.refreshCount( false ), 500 )();
	},

	/**
	 * Remove filter row
	 */
	removeFilterRow( e ) {
		jQuery( e.target ).closest( '.aie-filter-row' ).remove();

		// Trigger count refresh (without spinner)
		Utils.debounce( () => this.refreshCount( false ), 500 )();
	},

	/**
	 * Handle filter field change
	 */
	onFilterFieldChange( e ) {
		const $field = jQuery( e.target );
		const $row = $field.closest( '.aie-filter-row' );
		const $condition = $row.find( '.aie-filter-condition' );
		const $valueWrap = $row.find( '.aie-filter-value-wrap' );
		const $value = $row.find( '.aie-filter-value' );

		const selectedOption = $field.find( 'option:selected' );
		const fieldType = selectedOption.data( 'type' ) || 'string';

		// Special handling for custom_field
		if ( fieldType === 'custom_field' ) {
			// Create custom interface for custom field filter
			$condition.closest( '.aie-filter-condition-wrap' ).show();
			$valueWrap.html( `
				<div class="aie-custom-field-inputs">
					<div class="aie-input-group">
						<label>Field Name</label>
						<input type="text" class="aie-custom-field-name" placeholder="Enter custom field name..." />
					</div>
					<div class="aie-input-group">
						<label>Condition</label>
						<select class="aie-custom-field-condition aie-filter-condition">
							<option value="equals">Equals</option>
							<option value="not_equals">Not Equals</option>
							<option value="contains">Contains</option>
							<option value="not_contains">Not Contains</option>
							<option value="greater">Greater Than</option>
							<option value="less">Less Than</option>
							<option value="equals_or_greater">Greater or Equal</option>
							<option value="equals_or_less">Less or Equal</option>
							<option value="in">In (comma-separated)</option>
							<option value="not_in">Not In (comma-separated)</option>
							<option value="is_empty">Is Empty</option>
							<option value="is_not_empty">Is Not Empty</option>
						</select>
					</div>
					<div class="aie-input-group aie-custom-field-value-group">
						<label>Value</label>
						<input type="text" class="aie-custom-field-value aie-filter-value" placeholder="Enter value..." />
					</div>
				</div>
			` );
			$condition.closest( '.aie-filter-condition-wrap' ).hide();
			
			// Handle condition change to show/hide value input
			$row.find( '.aie-custom-field-condition' ).on( 'change', function() {
				const condition = jQuery( this ).val();
				const $valueGroup = $row.find( '.aie-custom-field-value-group' );
				if ( condition === 'is_empty' || condition === 'is_not_empty' ) {
					$valueGroup.hide();
				} else {
					$valueGroup.show();
				}
				// Trigger count refresh on condition change
				Utils.debounce( () => this.refreshCount( false ), 500 )();
			}.bind( this ) );
			
			// Add change event handlers to trigger count refresh
			$row.find( '.aie-custom-field-name, .aie-custom-field-value' ).on( 'input change', () => {
				Utils.debounce( () => this.refreshCount( false ), 500 )();
			} );
			
			return;
		}

		// Special handling for taxonomy_filter
		if ( fieldType === 'taxonomy_filter' ) {
			// Create custom interface for taxonomy filter
			$condition.closest( '.aie-filter-condition-wrap' ).show();
			$valueWrap.html( `
				<div class="aie-taxonomy-filter-inputs">
					<div class="aie-input-group">
						<label>Taxonomy Name</label>
						<input type="text" class="aie-taxonomy-name" placeholder="e.g., category, post_tag, product_cat..." />
					</div>
					<div class="aie-input-group">
						<label>Condition</label>
						<select class="aie-taxonomy-condition aie-filter-condition">
							<option value="in">Has Term(s) - IN</option>
							<option value="not_in">Does Not Have Term(s) - NOT IN</option>
							<option value="and">Has All Terms - AND</option>
						</select>
					</div>
					<div class="aie-input-group">
						<label>Terms</label>
						<input type="text" class="aie-taxonomy-terms aie-filter-value" placeholder="Enter term slugs (comma-separated)..." />
						<small>Enter term slugs separated by commas</small>
					</div>
				</div>
			` );
			$condition.closest( '.aie-filter-condition-wrap' ).hide();
			
			// Add change event handlers to trigger count refresh
			$row.find( '.aie-taxonomy-name, .aie-taxonomy-condition, .aie-taxonomy-terms' ).on( 'input change', () => {
				Utils.debounce( () => this.refreshCount( false ), 500 )();
			} );
			
			return;
		}

		// Special handling for table_selector
		if ( fieldType === 'table_selector' ) {
			// Hide condition dropdown for table selector
			$condition.closest( '.aie-filter-condition-wrap' ).hide();
			
			// Replace value input with table selector
			$valueWrap.find( 'label' ).text( 'Select Table' );
			
			// Create a select dropdown for tables
			const $select = jQuery( '<select>' )
				.addClass( 'aie-filter-value aie-table-selector' )
				.attr( 'name', 'filter_value[]' );
			
			// Fetch database tables via AJAX
			Utils.ajax( 'aie_get_database_tables', {} ).then( ( tables ) => {
				$select.append( jQuery( '<option>' ).val( '' ).text( 'Select Table...' ) );
				
				if ( tables && Array.isArray( tables ) ) {
					tables.forEach( ( table ) => {
						$select.append(
							jQuery( '<option>' )
								.val( table.name )
								.text( table.name )
						);
					} );

					// When table is selected, reload filter fields
					$select.on( 'change', () => {
						const tableName = $select.val();
						if ( tableName ) {
							this.loadTableColumns( tableName );
						}
					} );
				}
			} ).catch( ( error ) => {
				console.error( 'Error loading tables:', error );
				$select.append( jQuery( '<option>' ).val( '' ).text( 'Error loading tables' ) );
			} );
			
			$value.replaceWith( $select );
			return;
		}

		// Special handling for post_type_selector
		if ( fieldType === 'post_type_selector' ) {
			// Hide condition dropdown for post type selector
			$condition.closest( '.aie-filter-condition-wrap' ).hide();
			
		// Replace value input with post type selector
		$valueWrap.find( 'label' ).text( 'Select Post Type' );
		
		// Create a select dropdown for post types
		const $select = jQuery( '<select>' )
			.addClass( 'aie-filter-value aie-post-type-selector' )
			.attr( 'name', 'filter_value[]' );
		
		// Fetch post types via AJAX
		Utils.ajax( 'aie_get_post_types', {
			include_hidden: true,
		} ).then( ( postTypes ) => {
			$select.append( jQuery( '<option>' ).val( '' ).text( 'Select Post Type...' ) );
			
			if ( postTypes && Array.isArray( postTypes ) ) {
				postTypes.forEach( ( postType ) => {
					$select.append(
						jQuery( '<option>' )
							.val( postType.name )
							.text( postType.label + ' (' + postType.name + ')' )
					);
				} );

				// When post type is selected, refresh count
				$select.on( 'change', () => {
					Utils.debounce( () => this.refreshCount( false ), 500 )();
					
					// Reload step 3 fields if currently on step 3
					if ( this.currentStep === 3 && this.step3Instance ) {
						this.step3Instance.reloadDynamicFields();
					}
				} );
			}
		} ).catch( ( error ) => {
			console.error( 'Error loading post types:', error );
			$select.append( jQuery( '<option>' ).val( '' ).text( 'Error loading post types' ) );
		} );
		
		$value.replaceWith( $select );
		return;
	}		// Show condition dropdown for normal fields
		$condition.closest( '.aie-filter-condition-wrap' ).show();
		$valueWrap.find( 'label' ).text( 'Value' );

		// If current input is a select (from post_type_selector or table_selector), replace with input
		if ( $value.is( 'select' ) ) {
			const $input = jQuery( '<input>' )
				.attr( 'type', 'text' )
				.addClass( 'aie-filter-value' )
				.attr( 'name', 'filter_value[]' )
				.attr( 'placeholder', 'Enter value...' );
			$value.replaceWith( $input );
			// Update reference
			$row.find( '.aie-filter-value' ).attr( 'type', fieldType === 'date' ? 'date' : ( fieldType === 'number' ? 'number' : 'text' ) );
		} else {
			// Clear existing conditions
			$condition.empty();

			// Populate conditions based on field type
			const conditions = this.getConditionsByFieldType( fieldType );
			
			// Get the actual field name to filter out inappropriate conditions
			const fieldName = $field.val();
			
			// Filter conditions based on field
			const filteredConditions = conditions.filter( ( condition ) => {
				// For ID fields, exclude is_empty and is_not_empty (ID cannot be empty)
				if ( fieldName === 'ID' || fieldName === 'comment_ID' || fieldName === 'term_id' || fieldName === 'user_id' || fieldName === 'attribute_id' ) {
					return condition.value !== 'is_empty' && condition.value !== 'is_not_empty';
				}
				
				// For date fields, exclude is_empty and is_not_empty (dates typically always have values)
				if ( fieldType === 'date' ) {
					return condition.value !== 'is_empty' && condition.value !== 'is_not_empty';
				}
				
				// For comment_status, exclude is_empty and is_not_empty (always has a value: open, closed, etc.)
				if ( fieldName === 'comment_status' ) {
					return condition.value !== 'is_empty' && condition.value !== 'is_not_empty';
				}
				
				// For content and excerpt fields, exclude in and not_in (not practical for long text)
				if ( fieldName === 'post_content' || fieldName === 'post_excerpt' ) {
					return condition.value !== 'in' && condition.value !== 'not_in';
				}
				
				return true;
			} );
			
			filteredConditions.forEach( ( condition ) => {
				$condition.append(
					jQuery( '<option>' )
						.val( condition.value )
						.text( condition.label )
				);
			} );

			// Clear the value when field changes
			$value.val( '' );
			
			// Change value input type based on field type
			if ( fieldType === 'date' ) {
				$value.attr( 'type', 'date' );
			} else if ( fieldType === 'number' ) {
				$value.attr( 'type', 'number' );
			} else {
				$value.attr( 'type', 'text' );
			}
			
			// Update input type based on current condition
			this.updateValueInputType( $row );
		}

		// Trigger count refresh (without spinner)
		Utils.debounce( () => this.refreshCount( false ), 500 )();
	},

	/**
	 * Load table columns dynamically
	 */
	loadTableColumns( tableName ) {
		Utils.ajax( 'aie_get_table_columns', {
			table_name: tableName,
		} ).then( ( columns ) => {
			if ( columns && Array.isArray( columns ) ) {
				// Store columns for later use
				this.tableColumns = columns;
				
				// Update all filter field dropdowns
				jQuery( '.aie-filter-field' ).each( ( index, element ) => {
					const $fieldSelect = jQuery( element );
					const currentValue = $fieldSelect.val();
					
					// Clear and rebuild options
					$fieldSelect.empty();
					$fieldSelect.append( jQuery( '<option>' ).val( '' ).text( 'Select Field...' ) );
					
					// Add columns as options
					columns.forEach( ( column ) => {
						$fieldSelect.append(
							jQuery( '<option>' )
								.val( column.name )
								.text( column.name + ' (' + column.type + ')' )
								.data( 'type', column.data_type )
						);
					} );
					
					// Restore previous value if exists
					if ( currentValue ) {
						$fieldSelect.val( currentValue );
					}
				} );
			}
		} ).catch( ( error ) => {
			console.error( 'Error loading table columns:', error );
		} );
	},

	/**
	 * Get fields by content type
	 */
	getFieldsByContentType( contentType ) {
		const baseFields = [
			{
				label: 'Standard',
				options: [
					{ value: 'ID', label: 'ID', type: 'number' },
					{ value: 'post_title', label: 'Title', type: 'string' },
					{ value: 'post_content', label: 'Content', type: 'string' },
					{ value: 'post_excerpt', label: 'Excerpt', type: 'string' },
					{ value: 'post_date', label: 'Date', type: 'date' },
					{ value: 'post_name', label: 'Slug', type: 'string' },
					{ value: 'post_status', label: 'Status', type: 'string' },
				],
			},
			{
				label: 'Taxonomy',
				options: [
					{ value: 'categories', label: 'Categories', type: 'string' },
					{ value: 'tags', label: 'Tags', type: 'string' },
				],
			},
			{
				label: 'Author',
				options: [
					{ value: 'post_author', label: 'Author ID', type: 'number' },
					{ value: 'author_name', label: 'Author Name', type: 'string' },
					{ value: 'author_email', label: 'Author Email', type: 'string' },
				],
			},
		{
			label: 'Other',
			options: [
				{ value: 'comment_status', label: 'Comment Status', type: 'string' },
				{ value: 'post_modified', label: 'Modified Date', type: 'date' },
				{ value: '_wp_page_template', label: 'Template', type: 'string' },
			],
		},
		{
			label: 'Custom Filters',
			options: [
				{ value: '_custom_field', label: '🔧 Custom Field (Meta)', type: 'custom_field' },
				{ value: '_taxonomy_filter', label: '🏷️ Taxonomy Filter', type: 'taxonomy_filter' },
			],
		},
	];		// Customize based on content type
		if ( contentType === 'media' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'ID', type: 'number' },
						{ value: 'post_title', label: 'Title', type: 'string' },
						{ value: 'post_content', label: 'Description', type: 'string' },
						{ value: 'post_excerpt', label: 'Caption', type: 'string' },
						{ value: 'post_name', label: 'Slug', type: 'string' },
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
					label: 'Custom Filters',
					options: [
						{ value: '_custom_field', label: '🔧 Custom Field (Meta)', type: 'custom_field' },
					],
				},
			];
		}

		// Pages don't have taxonomy section (but taxonomy_filter is still available in Custom Filters)
		if ( contentType === 'page' ) {
			return baseFields.filter( group => group.label !== 'Taxonomy' );
		}

		// Menus
		if ( contentType === 'menu' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'term_id', label: 'Menu ID', type: 'number' },
						{ value: 'name', label: 'Menu Name', type: 'string' },
						{ value: 'slug', label: 'Menu Slug', type: 'string' },
						{ value: 'description', label: 'Description', type: 'string' },
						{ value: 'menu_items', label: 'Menu Items (Array)', type: 'array' },
					],
				},
				{
					label: 'Details',
					options: [
						{ value: 'count', label: 'Items Count', type: 'number' },
						{ value: 'locations', label: 'Theme Locations', type: 'string' },
					],
				},
				{
					label: 'Custom Filters',
					options: [
						{ value: '_custom_field', label: '🔧 Custom Field (Meta)', type: 'custom_field' },
					],
				},
			];
		}

		// Users
		if ( contentType === 'user' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'User ID', type: 'number' },
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
					label: 'Custom Filters',
					options: [
						{ value: '_custom_field', label: '🔧 Custom Field (Meta)', type: 'custom_field' },
					],
				},
			];
		}

		// Comments
		if ( contentType === 'comment' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'comment_ID', label: 'Comment ID', type: 'number' },
						{ value: 'comment_post_ID', label: 'Post ID', type: 'number' },
						{ value: 'comment_content', label: 'Comment Content', type: 'string' },
						{ value: 'comment_approved', label: 'Status', type: 'string' },
						{ value: 'comment_type', label: 'Comment Type', type: 'string' },
					],
				},
				{
					label: 'Author',
					options: [
						{ value: 'comment_author', label: 'Author Name', type: 'string' },
						{ value: 'comment_author_email', label: 'Author Email', type: 'string' },
						{ value: 'comment_author_url', label: 'Author URL', type: 'string' },
						{ value: 'comment_author_IP', label: 'Author IP', type: 'string' },
						{ value: 'user_id', label: 'User ID', type: 'number' },
						{ value: 'comment_agent', label: 'User Agent', type: 'string' },
					],
				},
				{
					label: 'Related Post',
					options: [
						{ value: 'post_title', label: 'Post Title', type: 'string' },
						{ value: 'post_author', label: 'Post Author ID', type: 'number' },
					],
				},
				{
					label: 'Dates',
					options: [
						{ value: 'comment_date', label: 'Comment Date', type: 'date' },
						{ value: 'comment_date_gmt', label: 'Comment Date (GMT)', type: 'date' },
					],
				},
				{
					label: 'Hierarchy',
					options: [
						{ value: 'comment_parent', label: 'Parent Comment ID', type: 'number' },
						{ value: 'comment_karma', label: 'Karma', type: 'number' },
					],
				},
				{
					label: 'Custom Filters',
					options: [
						{ value: '_custom_field', label: '🔧 Custom Field (Meta)', type: 'custom_field' },
					],
				},
			];
		}

		// Block Theme Settings
		if ( contentType === 'block_theme_settings' ) {
			return [
				{
					label: 'Settings',
					options: [
						{ value: 'setting_name', label: 'Setting Name', type: 'string' },
						{ value: 'setting_type', label: 'Setting Type', type: 'string' },
						{ value: 'setting_value', label: 'Setting Value', type: 'string' },
					],
				},
			];
		}

		// Custom Post Types
		if ( contentType === 'custom_post_types' ) {
			return [
				{
					label: 'Post Type Selection',
					options: [
						{ value: '_post_type', label: 'Post Type (select specific)', type: 'post_type_selector' },
					],
				},
				{
					label: 'Standard',
					options: [
						{ value: 'ID', label: 'ID', type: 'number' },
						{ value: 'post_title', label: 'Title', type: 'string' },
						{ value: 'post_content', label: 'Content', type: 'string' },
						{ value: 'post_excerpt', label: 'Excerpt', type: 'string' },
						{ value: 'post_date', label: 'Date', type: 'date' },
						{ value: 'post_name', label: 'Slug', type: 'string' },
						{ value: 'post_status', label: 'Status', type: 'string' },
					],
				},
				{
					label: 'Author',
					options: [
						{ value: 'post_author', label: 'Author ID', type: 'number' },
						{ value: 'author_name', label: 'Author Name', type: 'string' },
						{ value: 'author_email', label: 'Author Email', type: 'string' },
					],
				},
			{
				label: 'Other',
				options: [
					{ value: 'post_parent', label: 'Parent ID', type: 'number' },
					{ value: 'post_modified', label: 'Modified Date', type: 'date' },
					{ value: '_wp_page_template', label: 'Template', type: 'string' },
				],
			},
			{
				label: 'Custom Filters',
				options: [
					{ value: '_custom_field', label: '🔧 Custom Field (Meta)', type: 'custom_field' },
					{ value: '_taxonomy_filter', label: '🏷️ Taxonomy Filter', type: 'taxonomy_filter' },
				],
			},
		];
	}		// Taxonomy
		if ( contentType === 'taxonomy' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'term_id', label: 'Term ID', type: 'number' },
						{ value: 'name', label: 'Term Name', type: 'string' },
						{ value: 'slug', label: 'Term Slug', type: 'string' },
						{ value: 'description', label: 'Description', type: 'string' },
					],
				},
				{
					label: 'Taxonomy',
					options: [
						{ value: 'taxonomy', label: 'Taxonomy Type', type: 'string' },
						{ value: 'term_taxonomy_id', label: 'Taxonomy ID', type: 'number' },
					],
				},
				{
					label: 'Hierarchy',
					options: [
						{ value: 'parent', label: 'Parent Term ID', type: 'number' },
						{ value: 'count', label: 'Posts Count', type: 'number' },
					],
				},
				{
					label: 'Custom Filters',
					options: [
						{ value: '_custom_field', label: '🔧 Term Meta Field', type: 'custom_field' },
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
					label: 'Custom Filters',
					options: [
						{ value: '_custom_field', label: '🔧 Custom Field (Meta)', type: 'custom_field' },
						{ value: '_taxonomy_filter', label: '🏷️ Taxonomy Filter', type: 'taxonomy_filter' },
					],
				},
			];
		}

		// WooCommerce Orders
		if ( contentType === 'woo_order' ) {
			return [
				{
					label: 'Basic',
					options: [
						{ value: 'ID', label: 'Order ID', type: 'number' },
						{ value: 'order_number', label: 'Order Number', type: 'string' },
						{ value: 'order_status', label: 'Status', type: 'string' },
						{ value: 'order_key', label: 'Order Key', type: 'string' },
						{ value: 'currency', label: 'Currency', type: 'string' },
					],
				},
				{
					label: 'Amounts',
					options: [
						{ value: 'order_total', label: 'Order Total', type: 'number' },
						{ value: 'order_subtotal', label: 'Subtotal', type: 'number' },
						{ value: 'order_tax', label: 'Tax', type: 'number' },
						{ value: 'order_shipping', label: 'Shipping', type: 'number' },
						{ value: 'order_discount', label: 'Discount', type: 'number' },
					],
				},
				{
					label: 'Customer',
					options: [
						{ value: 'customer_id', label: 'Customer ID', type: 'number' },
						{ value: 'billing_email', label: 'Email', type: 'string' },
						{ value: 'customer_note', label: 'Customer Note', type: 'string' },
					],
				},
				{
					label: 'Billing Address',
					options: [
						{ value: 'billing_first_name', label: 'First Name', type: 'string' },
						{ value: 'billing_last_name', label: 'Last Name', type: 'string' },
						{ value: 'billing_company', label: 'Company', type: 'string' },
						{ value: 'billing_address_1', label: 'Address 1', type: 'string' },
						{ value: 'billing_address_2', label: 'Address 2', type: 'string' },
						{ value: 'billing_city', label: 'City', type: 'string' },
						{ value: 'billing_state', label: 'State', type: 'string' },
						{ value: 'billing_postcode', label: 'Postcode', type: 'string' },
						{ value: 'billing_country', label: 'Country', type: 'string' },
						{ value: 'billing_phone', label: 'Phone', type: 'string' },
					],
				},
				{
					label: 'Shipping Address',
					options: [
						{ value: 'shipping_first_name', label: 'First Name', type: 'string' },
						{ value: 'shipping_last_name', label: 'Last Name', type: 'string' },
						{ value: 'shipping_company', label: 'Company', type: 'string' },
						{ value: 'shipping_address_1', label: 'Address 1', type: 'string' },
						{ value: 'shipping_address_2', label: 'Address 2', type: 'string' },
						{ value: 'shipping_city', label: 'City', type: 'string' },
						{ value: 'shipping_state', label: 'State', type: 'string' },
						{ value: 'shipping_postcode', label: 'Postcode', type: 'string' },
						{ value: 'shipping_country', label: 'Country', type: 'string' },
					],
				},
				{
					label: 'Order Items',
					options: [
						{ value: 'order_items', label: 'Order Items (Array)', type: 'array' },
						{ value: 'item_count', label: 'Item Count', type: 'number' },
					],
				},
				{
					label: 'Payment',
					options: [
						{ value: 'payment_method', label: 'Payment Method', type: 'string' },
						{ value: 'payment_method_title', label: 'Payment Method Title', type: 'string' },
						{ value: 'transaction_id', label: 'Transaction ID', type: 'string' },
					],
				},
				{
					label: 'Shipping',
					options: [
						{ value: 'shipping_method', label: 'Shipping Method', type: 'string' },
					],
				},
				{
					label: 'Dates',
					options: [
						{ value: 'order_date', label: 'Order Date', type: 'date' },
						{ value: 'completed_date', label: 'Completed Date', type: 'date' },
						{ value: 'paid_date', label: 'Paid Date', type: 'date' },
					],
				},
				{
					label: 'Notes',
					options: [
						{ value: 'order_notes', label: 'Order Notes (Array)', type: 'array' },
					],
				},
				{
					label: 'Custom Filters',
					options: [
						{ value: '_custom_field', label: '🔧 Custom Field (Meta)', type: 'custom_field' },
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
						{ value: 'post_title', label: 'Coupon Code', type: 'string' },
						{ value: 'post_excerpt', label: 'Description', type: 'string' },
						{ value: 'post_status', label: 'Status', type: 'string' },
					],
				},
				{
					label: 'Discount',
					options: [
						{ value: 'discount_type', label: 'Discount Type', type: 'string' },
						{ value: 'coupon_amount', label: 'Coupon Amount', type: 'number' },
						{ value: 'free_shipping', label: 'Free Shipping', type: 'boolean' },
					],
				},
				{
					label: 'Usage Restrictions',
					options: [
						{ value: 'minimum_amount', label: 'Minimum Spend', type: 'number' },
						{ value: 'maximum_amount', label: 'Maximum Spend', type: 'number' },
						{ value: 'individual_use', label: 'Individual Use Only', type: 'boolean' },
						{ value: 'exclude_sale_items', label: 'Exclude Sale Items', type: 'boolean' },
					],
				},
				{
					label: 'Product Restrictions',
					options: [
						{ value: 'product_ids', label: 'Allowed Products', type: 'array' },
						{ value: 'excluded_product_ids', label: 'Excluded Products', type: 'array' },
						{ value: 'product_categories', label: 'Allowed Categories', type: 'array' },
						{ value: 'excluded_product_categories', label: 'Excluded Categories', type: 'array' },
					],
				},
				{
					label: 'Email Restrictions',
					options: [
						{ value: 'allowed_emails', label: 'Allowed Emails', type: 'array' },
					],
				},
				{
					label: 'Usage Limits',
					options: [
						{ value: 'usage_count', label: 'Usage Count', type: 'number' },
						{ value: 'usage_limit', label: 'Usage Limit Total', type: 'number' },
						{ value: 'usage_limit_per_user', label: 'Usage Limit Per User', type: 'number' },
					],
				},
				{
					label: 'Dates',
					options: [
						{ value: 'date_expires', label: 'Expiry Date', type: 'date' },
						{ value: 'post_date', label: 'Created Date', type: 'date' },
						{ value: 'post_modified', label: 'Modified Date', type: 'date' },
					],
				},
				{
					label: 'Custom Filters',
					options: [
						{ value: '_custom_field', label: '🔧 Custom Field (Meta)', type: 'custom_field' },
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
						{ value: 'attribute_id', label: 'Attribute ID', type: 'number' },
						{ value: 'attribute_name', label: 'Attribute Name', type: 'string' },
						{ value: 'attribute_label', label: 'Attribute Label', type: 'string' },
						{ value: 'attribute_type', label: 'Attribute Type', type: 'string' },
					],
				},
				{
					label: 'Settings',
					options: [
						{ value: 'attribute_orderby', label: 'Default Sort Order', type: 'string' },
						{ value: 'attribute_public', label: 'Enable Archives', type: 'boolean' },
					],
				},
				{
					label: 'Terms',
					options: [
						{ value: 'term_count', label: 'Terms Count', type: 'number' },
						{ value: 'attribute_terms', label: 'All Terms (Array)', type: 'array' },
					],
				},
			];
		}

		// Database Table - use dynamic columns from selected table
		if ( contentType === 'database_table' ) {
			// If we have columns loaded, use them
			if ( this.currentTableColumns && this.currentTableColumns.length > 0 ) {
				const columnOptions = this.currentTableColumns.map( ( col ) => {
					const typeLabel = col.is_numeric ? 'number' : col.is_date ? 'date' : 'string';
					return {
						value: col.name,
						label: `${col.name} (${col.type})`,
						type: typeLabel
					};
				} );

				return [
					{
						label: 'Table Columns',
						options: columnOptions
					},
				];
			}

			// Otherwise show message to select table first
			return [
				{
					label: 'Table Selection',
					options: [
						{ value: '_select_table', label: '⚠️ Please select a database table first', type: 'info' },
					],
				},
			];
		}

		return baseFields;
	},

	/**
	 * Check if filter row is complete (has all required values)
	 */
	isFilterRowComplete( $row ) {
		const field = $row.find( '.aie-filter-field' ).val();
		const condition = $row.find( '.aie-filter-condition' ).val();
		const value = $row.find( '.aie-filter-value' ).val();

		// Field and condition must be selected
		if ( ! field || ! condition ) {
			return false;
		}

		// Check if condition requires a value
		const noValueConditions = [ 'is_empty', 'is_not_empty' ];
		if ( noValueConditions.includes( condition ) ) {
			return true; // These conditions don't need a value
		}

		// Value must be filled
		return value && value.trim() !== '';
	},
	
	/**
	 * Update value input type based on condition and field type
	 */
	updateValueInputType( $row ) {
		const $field = $row.find( '.aie-filter-field' );
		const $condition = $row.find( '.aie-filter-condition' );
		const $value = $row.find( '.aie-filter-value' );
		
		const selectedOption = $field.find( 'option:selected' );
		const fieldType = selectedOption.data( 'type' ) || 'string';
		const condition = $condition.val();
		
		// Skip if value is not an input field
		if ( ! $value.is( 'input' ) ) {
			return;
		}
		
		// For 'is_empty' and 'is_not_empty', hide the value input
		const noValueConditions = [ 'is_empty', 'is_not_empty' ];
		if ( noValueConditions.includes( condition ) ) {
			$value.closest( '.aie-filter-value-wrap' ).hide();
			return;
		} else {
			// Always show the value input for other conditions
			$value.closest( '.aie-filter-value-wrap' ).show();
		}
		
		// For 'in' and 'not_in' conditions, always use text input to allow comma-separated values
		if ( condition === 'in' || condition === 'not_in' ) {
			$value.attr( 'type', 'text' );
			$value.attr( 'placeholder', 'Enter values separated by comma (e.g., 1,5,8 or test1,test2)' );
			return;
		}
		
		// For 'between' condition on numbers, use text to allow comma-separated range
		if ( condition === 'between' && fieldType === 'number' ) {
			$value.attr( 'type', 'text' );
			$value.attr( 'placeholder', 'Enter two numbers separated by comma (e.g., 10,100)' );
			return;
		}
		
		// Otherwise, set type based on field type
		if ( fieldType === 'date' ) {
			$value.attr( 'type', 'date' );
			$value.attr( 'placeholder', '' );
		} else if ( fieldType === 'number' ) {
			$value.attr( 'type', 'number' );
			$value.attr( 'placeholder', 'Enter number...' );
		} else {
			$value.attr( 'type', 'text' );
			$value.attr( 'placeholder', 'Enter value...' );
		}
	},

	/**
	 * Get conditions by field type
	 */
	getConditionsByFieldType( fieldType ) {
		const conditions = {
			string: [
				{ value: 'equals', label: 'Equals' },
				{ value: 'not_equals', label: "Doesn't Equal" },
				{ value: 'in', label: 'In' },
				{ value: 'not_in', label: 'Not In' },
				{ value: 'contains', label: 'Contains' },
				{ value: 'not_contains', label: "Doesn't Contain" },
				{ value: 'is_empty', label: 'Is Empty' },
				{ value: 'is_not_empty', label: 'Is Not Empty' },
			],
			number: [
				{ value: 'equals', label: 'Equals' },
				{ value: 'not_equals', label: "Doesn't Equal" },
				{ value: 'in', label: 'In' },
				{ value: 'not_in', label: 'Not In' },
				{ value: 'greater', label: 'Greater Than' },
				{ value: 'equals_or_greater', label: 'Equal To Or Greater Than' },
				{ value: 'less', label: 'Less Than' },
				{ value: 'equals_or_less', label: 'Equal To Or Less Than' },
				{ value: 'is_empty', label: 'Is Empty' },
				{ value: 'is_not_empty', label: 'Is Not Empty' },
			],
			date: [
				{ value: 'equals', label: 'Equals' },
				{ value: 'not_equals', label: "Doesn't Equal" },
				{ value: 'greater', label: 'Newer Than' },
				{ value: 'equals_or_greater', label: 'Equal To Or Newer Than' },
				{ value: 'less', label: 'Older Than' },
				{ value: 'equals_or_less', label: 'Equal To Or Older Than' },
				{ value: 'is_empty', label: 'Is Empty' },
				{ value: 'is_not_empty', label: 'Is Not Empty' },
			],
		};

		return conditions[ fieldType ] || conditions.string;
	},

	/**
	 * Load database tables
	 */
	loadDatabaseTables() {
		const $dropdown = jQuery( '#aie-table-name' );
		const $spinner = jQuery( '.aie-table-selector .spinner' );
		const $section = jQuery( '.aie-table-selection-section' );

		// Show section
		$section.show();

		// Show loading state
		$dropdown.prop( 'disabled', true );
		$spinner.addClass( 'is-active' );

		// Fetch tables via AJAX
		Utils.ajax( 'aie_get_database_tables', {} )
			.then( ( response ) => {
				console.log( 'Tables response:', response );
				const tables = response.tables || response || [];
				
				console.log( 'Parsed tables:', tables );
				
				// Clear and populate dropdown
				$dropdown.empty();
				$dropdown.append( jQuery( '<option>' ).val( '' ).text( 'Select a table...' ) );

				if ( !Array.isArray( tables ) || tables.length === 0 ) {
					$dropdown.append( jQuery( '<option>' ).val( '' ).text( 'No tables found' ) );
					$dropdown.prop( 'disabled', true );
					$spinner.removeClass( 'is-active' );
					return;
				}

				tables.forEach( ( table ) => {
					$dropdown.append(
						jQuery( '<option>' )
							.val( table.table_name )
							.text( table.label )
					);
				} );

				// Enable dropdown
				$dropdown.prop( 'disabled', false );
				$spinner.removeClass( 'is-active' );

				// Handle table selection
				$dropdown.off( 'change' ).on( 'change', () => {
					const tableName = $dropdown.val();
					if ( tableName ) {
						this.loadTableColumns( tableName );
					} else {
						jQuery( '.aie-table-info' ).html('').hide();
						jQuery( '#aie-filters-list' ).empty();
					}
				} );
			} )
			.catch( ( error ) => {
				console.error( 'Error loading tables:', error );
				$dropdown.empty();
				$dropdown.append( jQuery( '<option>' ).val( '' ).text( 'Error loading tables' ) );
				$dropdown.prop( 'disabled', true );
				$spinner.removeClass( 'is-active' );
			} );
	},

	/**
	 * Load table columns and show info
	 */
	loadTableColumns( tableName ) {
		const $tableInfo = jQuery( '.aie-table-info' );
		const $columnsList = jQuery( '.aie-columns-list' );
		const $rowCount = jQuery( '.aie-table-row-count' );
		const $columnCount = jQuery( '.aie-table-column-count' );

		// Show loading state
		$tableInfo.show();
		$columnsList.html( '<p>Loading columns...</p>' );

		// Fetch columns via AJAX
		Utils.ajax( 'aie_get_table_columns', { table_name: tableName } )
			.then( ( response ) => {
				const columns = response.columns || [];

				// Update column count
				$columnCount.text( columns.length );

				// Display columns with types
				$columnsList.empty();
				const $list = jQuery( '<ul>' ).addClass( 'aie-column-type-list' );

				columns.forEach( ( col ) => {
					const typeIcon = this.getColumnTypeIcon( col );
					const typeLabel = col.is_numeric ? 'numeric' : col.is_string ? 'text' : col.is_date ? 'date' : 'other';
					
					$list.append(
						jQuery( '<li>' ).html(
							`<span class="dashicons ${ typeIcon }"></span> 
							<strong>${ col.name }</strong> 
							<span class="column-type">(${ col.type })</span>`
						)
					);
				} );

				$columnsList.append( $list );

				// Store columns for filter field options
				this.currentTableColumns = columns;

				// Clear existing filters
				jQuery( '#aie-filters-list' ).empty();

				// Refresh count to get row count
				this.refreshCount( false );
			} )
			.catch( ( error ) => {
				console.error( 'Error loading columns:', error );
				$columnsList.html( '<p class="error">Error loading columns</p>' );
			} );
	},

	/**
	 * Get icon for column type
	 */
	getColumnTypeIcon( column ) {
		if ( column.is_primary ) {
			return 'dashicons-admin-network';
		} else if ( column.is_numeric ) {
			return 'dashicons-calculator';
		} else if ( column.is_date ) {
			return 'dashicons-calendar-alt';
		} else if ( column.is_string ) {
			return 'dashicons-text';
		}
		return 'dashicons-marker';
	},
};

export default ExportModule;
