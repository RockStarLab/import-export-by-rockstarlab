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
	exportStartTime: null,
	step3Instance: null,

	/**
	 * Initialize module
	 */
	init() {
		if ( ! jQuery( '#rsl-ie-export' ).length ) {
			return;
		}

		// Check if resuming a job BEFORE showing any step
		const urlParams = new URLSearchParams( window.location.search );
		const resumeJobId = urlParams.get( 'resume_job' );
		
		this.bindEvents();
		
		if ( resumeJobId ) {
			// Resume job - go directly to step 5 and start processing
			this.jobId = parseInt( resumeJobId );
			
			// Show step 5 immediately (don't hide first, let showStep handle it)
			this.showStep( 5 );

			// Remove the anti-flash class once we're on the correct step.
			jQuery( '#rsl-ie-export' ).removeClass( 'aie-resuming-job' );
			
			// Get initial progress first, then start tracking and processing
			this.updateProgress().then( () => {
				// Start progress tracking and batch processing
				this.startProgressTracking();
				this.processNextBatch();
			} );
		} else {
			this.showStep( 1 );
		}
		
		// Initialize Step 3 drag and drop
		this.step3Instance = new ExportStep3();
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $wizard = jQuery( '#rsl-ie-export' );

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

		// Prevent selection of premium locked content types
		$wizard.on( 'click', '.aie-content-type.aie-premium-locked', ( e ) => {
			e.preventDefault();
			e.stopPropagation();
			
			// Show upgrade message
			const message = window.aieData.i18n.premiumOnlyFeature;
			Utils.showNotice( message, 'warning' );
			
			// Prevent the radio button from being checked
			const $input = jQuery( e.currentTarget ).find( 'input[type="radio"]' );
			$input.prop( 'checked', false );
			
			return false;
		} );

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
		const $wizard = jQuery( '#rsl-ie-export' );

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
		const noFilterTypes = [];
		
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

		const response = await Utils.ajax( 'aie_export_get_count', {
			export_type: contentType,
			options: options,
		} );

		$count.text( response.count || 0 );
		// If database table is selected, also update table row count in the info panel
		if ( contentType === 'database_table' ) {
			const $tableRowCount = jQuery( '.aie-table-row-count' );
			if ( $tableRowCount.length ) {
				$tableRowCount.text( response.count || 0 );
			}
		}
		// Update next button state based on count
		this.updateStep2NextButton();
	} catch ( error ) {
		$count.text( '-' );
		if ( contentType === 'database_table' ) {
			const $tableRowCount = jQuery( '.aie-table-row-count' );
			if ( $tableRowCount.length ) {
				$tableRowCount.text( '-' );
			}
		}
		
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
		
		// Check content type for special validation
		const contentType = jQuery( 'input[name="content_type"]:checked' ).val();
		let isDisabled = false;
		let tooltipTitle = window.aieData.i18n.noDataAvailable;
		let tooltipMessage = window.aieData.i18n.adjustFiltersMessage;
		
		// Remove previous event handlers
		$nextBtn.off( 'mouseenter.tooltip mouseleave.tooltip' );
		
		// For custom_post_types, check if post type is selected
		if ( contentType === 'custom_post_types' ) {
			const $postTypeSelector = jQuery( '.aie-post-type-selector' );
			const selectedPostType = $postTypeSelector.val();
			
			if ( ! selectedPostType || selectedPostType.trim() === '' ) {
				isDisabled = true;
				tooltipTitle = window.aieData.i18n.postTypeRequired;
				tooltipMessage = window.aieData.i18n.pleaseSelectPostType;
			}
		}
		
		// For taxonomy, check if taxonomy is selected
		if ( contentType === 'taxonomy' ) {
			const $taxonomySelector = jQuery( '.aie-taxonomy-selector' );
			const selectedTaxonomy = $taxonomySelector.val();
			
			if ( ! selectedTaxonomy || selectedTaxonomy.trim() === '' ) {
				isDisabled = true;
				tooltipTitle = window.aieData.i18n.taxonomyRequired;
				tooltipMessage = window.aieData.i18n.pleaseSelectTaxonomy;
			}
		}
		
		// For database_table, check if table is selected
		if ( contentType === 'database_table' ) {
			const $tableSelector = jQuery( '#aie-table-name' );
			const selectedTable = $tableSelector.val();
			
			if ( ! selectedTable || selectedTable.trim() === '' ) {
				isDisabled = true;
				tooltipTitle = window.aieData.i18n.tableRequired;
				tooltipMessage = window.aieData.i18n.pleaseSelectTable;
			}
		}
		
		// Disable if count is 0, NaN, or '-'
		if ( ! isDisabled && ( countText === '-' || isNaN( count ) || count === 0 ) ) {
			isDisabled = true;
		}
		
		if ( isDisabled ) {
			$nextBtn.prop( 'disabled', true );
			
			// Store tooltip data
			$nextBtn.data( 'tooltip-title', tooltipTitle );
			$nextBtn.data( 'tooltip-message', tooltipMessage );
			
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
		
		// Get custom tooltip data or use defaults
		const tooltipTitle = $button.data( 'tooltip-title' ) || window.aieData.i18n.noDataAvailable;
		const tooltipMessage = $button.data( 'tooltip-message' ) || window.aieData.i18n.adjustFiltersMessage;
		
		// Create tooltip element
		const $tooltip = jQuery( '<div>' )
			.addClass( 'aie-custom-tooltip aie-custom-pointer' )
			.html( `
				<div class="aie-pointer-icon">
					<span class="dashicons dashicons-warning"></span>
				</div>
				<div class="aie-pointer-content">
					<h3>${tooltipTitle}</h3>
					<p>${tooltipMessage}</p>
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

			// Handle taxonomy_selector type
			if ( fieldType === 'taxonomy_selector' ) {
				const value = $row.find( '.aie-filter-value' ).val();
				
				if ( value && value.trim() !== '' ) {
					filters.push( {
						field: 'taxonomy',
						condition: 'equals', // Default condition for taxonomy
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
			let value = $row.find( '.aie-filter-value' ).val();

			// Skip empty or incomplete filters
			if ( ! field || ! condition ) {
				return;
			}

			// Normalize date values to YYYY-MM-DD regardless of datepicker locale format
			const fieldTypeForDate = $row.find( '.aie-filter-field option:selected' ).data( 'type' );
			if ( ( fieldTypeForDate === 'date' || fieldTypeForDate === 'datetime' ) && value ) {
				const parsed = new Date( value );
				if ( ! isNaN( parsed.getTime() ) ) {
					value = parsed.toISOString().slice( 0, 10 );
				}
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
			// Filter out pseudo-fields (selectors that start with _ and are used only for filtering)
			const pseudoFields = ['_post_type', '_taxonomy', '_table_name'];
			return this.step3Instance.selectedFields
				.map(field => field.field)
				.filter(field => !pseudoFields.includes(field));
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
		let fields = this.getSelectedFields();

		// If no fields selected (or only pseudo-fields were filtered out), show error
		if ( fields.length === 0 ) {
			Utils.showNotice(
				window.aieData.i18n.pleaseSelectFieldToExport,
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
						window.aieData.i18n.pleaseEnterCustomDelimiter,
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
					).is( ':checked' ),				json_pretty_print: jQuery(
					'[name="json_pretty_print"]'
				).is( ':checked' ),			},
		options: {
			items_per_iteration: parseInt( jQuery( '[name="items_per_iteration"]' ).val() ) || 3,
		},
	};

			// Add field functions if available
			if (this.step3Instance && this.step3Instance.fieldFunctions) {
				// Convert field functions from fieldKey (with timestamp) to actual field names
				const convertedFunctions = this.convertFieldFunctions(this.step3Instance.fieldFunctions, this.step3Instance.selectedFields);
				if (Object.keys(convertedFunctions).length > 0) {
					data.field_functions = convertedFunctions;
				}
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

			// For database_table, add table_name
			if ( contentType === 'database_table' ) {
				const $tableDropdown = jQuery( '#aie-table-name' );
				const tableName = $tableDropdown.val();
				if ( tableName ) {
					data.table_name = tableName;
				}
			}

			const response = await Utils.ajax( 'aie_export_start', data );

			this.jobId = response.job_id;
			this.exportStartTime = Date.now();
			this.showStep( 5 );
			this.startProgressTracking();

			// Trigger first batch processing
			this.processNextBatch();

			Utils.showNotice( window.aieData.i18n.exportStartedSuccess, 'success' );
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

			// Update UI directly on each batch — don't rely solely on 2s polling.
			if ( response ) {
				const elapsedSec   = this.exportStartTime ? ( Date.now() - this.exportStartTime ) / 1000 : 0;
				const processed    = response.processed || 0;
				const total        = response.total || 0;
				const percentage   = response.progress || ( total > 0 ? ( processed / total ) * 100 : 0 );
				const itemsPerSec  = elapsedSec > 0 ? processed / elapsedSec : 0;
				const remainingSec = itemsPerSec > 0 && total > processed ? ( total - processed ) / itemsPerSec : 0;

				const formatTime = ( sec ) => {
					sec = Math.round( sec );
					if ( sec < 60 )   return sec + 's';
					if ( sec < 3600 ) return Math.floor( sec / 60 ) + 'm ' + ( sec % 60 ) + 's';
					return Math.floor( sec / 3600 ) + 'h ' + Math.floor( ( sec % 3600 ) / 60 ) + 'm';
				};

				Utils.updateProgressBar( jQuery( '.aie-step-5' ), {
					percentage,
					processed,
					total,
					estimates: {
						elapsed_formatted:   formatTime( elapsedSec ),
						remaining_formatted: remainingSec > 0 ? formatTime( remainingSec ) : '-',
						items_per_second:    itemsPerSec,
					},
				} );
			}

			// If not completed, process next batch after small delay
			if ( response && ! response.completed ) {
				setTimeout( () => {
					this.processNextBatch();
				}, 100 );
			} else if ( response && response.completed ) {
				// Stop polling — we'll get the final state from the progress endpoint.
				clearInterval( this.progressInterval );
				// Fetch final state to show results (file size, duration, etc.)
				this.updateProgress();
			}
		} catch ( error ) {
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
		}
	},

	/**
	 * Handle export completion
	 */
	onExportComplete( result ) {
		clearInterval( this.progressInterval );

		// Update title
		jQuery( '.aie-step-5 h2' ).text( window.aieData.i18n.exportComplete );
		
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
		const formatExportDuration = ( sec ) => {
			sec = Math.max( 0, Math.round( sec ) );
			if ( sec < 60 )   return sec + 's';
			if ( sec < 3600 ) return Math.floor( sec / 60 ) + 'm ' + ( sec % 60 ) + 's';
			return Math.floor( sec / 3600 ) + 'h ' + Math.floor( ( sec % 3600 ) / 60 ) + 'm';
		};
		const exportDurSec = this.exportStartTime
			? ( Date.now() - this.exportStartTime ) / 1000
			: 0;
		jQuery( '.aie-result-duration' ).text(
			exportDurSec > 0
				? formatExportDuration( exportDurSec )
				: ( result.estimates?.elapsed_formatted || '0s' )
		);

		jQuery( '.aie-cancel-export' ).hide();
		jQuery( '.aie-new-export' ).show();

		Utils.showNotice( window.aieData.i18n.exportCompletedSuccess, 'success' );
	},

	/**
	 * Handle export failure
	 */
	onExportFailed( result ) {
		clearInterval( this.progressInterval );
		const errorMessage =
			result.error ||
			( result.result && result.result.error ) ||
			window.aieData.i18n.unknownError;
		Utils.showNotice(
			window.aieData.i18n.exportFailed + ': ' + errorMessage,
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
		if ( ! confirm( window.aieData.i18n.confirmCancelExport ) ) {
			return;
		}

		try {
			await Utils.ajax( 'aie_export_cancel', { job_id: this.jobId } );
			clearInterval( this.progressInterval );
			Utils.showNotice( window.aieData.i18n.exportCancelled, 'info' );
			this.resetWizard();
		} catch ( error ) {
			Utils.handleError( error, 'Cancel export' );
		}
	},

	/**
	 * Start new export - reload the page
	 */
	newExport() {
		window.location.href = '/wp-admin/admin.php?page=rsl-ie-export';
	},

	/**
	 * Reset wizard
	 */
	resetWizard() {
		this.currentStep = 1;
		this.jobId = null;
		this.exportStartTime = null;
		clearInterval( this.progressInterval );

		jQuery(
			'#rsl-ie-export input[type="text"], #rsl-ie-export input[type="date"]'
		).val( '' );
		jQuery( '#rsl-ie-export input[type="radio"]:first' ).prop(
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

		// Populate field options based on content type (without Featured Image group)
		const $fieldSelect = jQuery( clone ).find( '.aie-filter-field' );
		const fields = this.getFilterFieldsByContentType( contentType );

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
						<label>${window.aieData.i18n.selectField}</label>
						<input type="text" class="aie-custom-field-name" placeholder="${window.aieData.i18n.enterCustomFieldName}" />
					</div>
					<div class="aie-input-group">
						<label>${window.aieData.i18n.condition}</label>
						<select class="aie-custom-field-condition aie-filter-condition">
							<option value="equals">${window.aieData.i18n.equals}</option>
							<option value="not_equals">${window.aieData.i18n.notEquals}</option>
							<option value="contains">${window.aieData.i18n.contains}</option>
							<option value="not_contains">${window.aieData.i18n.notContains}</option>
							<option value="greater">${window.aieData.i18n.greaterThan}</option>
							<option value="less">${window.aieData.i18n.lessThan}</option>
							<option value="equals_or_greater">${window.aieData.i18n.greaterOrEqual}</option>
							<option value="equals_or_less">${window.aieData.i18n.lessOrEqual}</option>
							<option value="in">${window.aieData.i18n.inComma}</option>
							<option value="not_in">${window.aieData.i18n.notInComma}</option>
							<option value="is_empty">${window.aieData.i18n.isEmpty}</option>
							<option value="is_not_empty">${window.aieData.i18n.isNotEmpty}</option>
						</select>
					</div>
					<div class="aie-input-group aie-custom-field-value-group">
						<label>${window.aieData.i18n.value}</label>
						<input type="text" class="aie-custom-field-value aie-filter-value" placeholder="${window.aieData.i18n.enterFilterValue}" />
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
						<input type="text" class="aie-taxonomy-name" placeholder="${window.aieData.i18n.taxonomyPlaceholderExamples}" />
					</div>
					<div class="aie-input-group">
						<label>Condition</label>
						<select class="aie-taxonomy-condition aie-filter-condition">
							<option value="in">${window.aieData.i18n.hasTermsIn}</option>
							<option value="not_in">${window.aieData.i18n.doesNotHaveTermsNotIn}</option>
							<option value="and">${window.aieData.i18n.hasAllTermsAnd}</option>
						</select>
					</div>
					<div class="aie-input-group">
						<label>Terms</label>
						<input type="text" class="aie-taxonomy-terms aie-filter-value" placeholder="${window.aieData.i18n.enterTermSlugs}" />
						<small>${window.aieData.i18n.enterTermSlugs}</small>
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
			$valueWrap.find( 'label' ).text( window.aieData.i18n.selectTable );
			
			// Create a select dropdown for tables
			const $select = jQuery( '<select>' )
				.addClass( 'aie-filter-value aie-table-selector' )
				.attr( 'name', 'filter_value[]' );
			
			// Fetch database tables via AJAX
			Utils.ajax( 'aie_get_database_tables', {} ).then( ( tables ) => {
				$select.append( jQuery( '<option>' ).val( '' ).text( window.aieData.i18n.selectTablePlaceholder ) );
				
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
				$select.append( jQuery( '<option>' ).val( '' ).text( window.aieData.i18n.errorLoadingTables ) );
			} );
			
			$value.replaceWith( $select );
			return;
		}

		// Special handling for post_type_selector
		if ( fieldType === 'post_type_selector' ) {
			// Hide condition dropdown for post type selector
			$condition.closest( '.aie-filter-condition-wrap' ).hide();
			
		// Replace value input with post type selector
		$valueWrap.find( 'label' ).text( window.aieData.i18n.selectPostType );
		
		// Create a select dropdown for post types
		const $select = jQuery( '<select>' )
			.addClass( 'aie-filter-value aie-post-type-selector' )
			.attr( 'name', 'filter_value[]' );
		
		// Fetch post types via AJAX
		Utils.ajax( 'aie_get_post_types', {
			include_hidden: true,
		} ).then( ( postTypes ) => {
			$select.append( jQuery( '<option>' ).val( '' ).text( window.aieData.i18n.selectPostTypePlaceholder ) );
			
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
					
					// Update step 2 next button state
					this.updateStep2NextButton();
					
					// Reload step 3 fields if currently on step 3
					if ( this.currentStep === 3 && this.step3Instance ) {
						this.step3Instance.reloadDynamicFields();
					}
				} );
			}
		} ).catch( ( error ) => {
			$select.append( jQuery( '<option>' ).val( '' ).text( window.aieData.i18n.errorLoadingPostTypes ) );
		} );
		
		$value.replaceWith( $select );
		return;
	}
		
		// Special handling for taxonomy_selector
		if ( fieldType === 'taxonomy_selector' ) {
			// Hide condition dropdown for taxonomy selector
			$condition.closest( '.aie-filter-condition-wrap' ).hide();
			
			// Replace value input with taxonomy selector
			$valueWrap.find( 'label' ).text( window.aieData.i18n.selectTaxonomy );
			
			// Create a select dropdown for taxonomies
			const $select = jQuery( '<select>' )
				.addClass( 'aie-filter-value aie-taxonomy-selector' )
				.attr( 'name', 'filter_value[]' );
			
			// Fetch taxonomies via AJAX
			Utils.ajax( 'aie_get_all_taxonomies', {} ).then( ( taxonomies ) => {
				$select.append( jQuery( '<option>' ).val( '' ).text( window.aieData.i18n.selectTaxonomyPlaceholder ) );
				
				if ( taxonomies && Array.isArray( taxonomies ) ) {
					taxonomies.forEach( ( taxonomy ) => {
						$select.append(
							jQuery( '<option>' )
								.val( taxonomy.name )
								.text( taxonomy.label + ' (' + taxonomy.name + ')' )
						);
					} );

					// When taxonomy is selected, refresh count
					$select.on( 'change', () => {
						Utils.debounce( () => this.refreshCount( false ), 500 )();
						
						// Update step 2 next button state
						this.updateStep2NextButton();
						
						// Reload step 3 fields if currently on step 3
						if ( this.currentStep === 3 && this.step3Instance ) {
							this.step3Instance.reloadDynamicFields();
						}
					} );
				}
			} ).catch( ( error ) => {
				$select.append( jQuery( '<option>' ).val( '' ).text( window.aieData.i18n.errorLoadingTaxonomies ) );
			} );
			
			$value.replaceWith( $select );
			return;
		}

		// Show condition dropdown for normal fields
		$condition.closest( '.aie-filter-condition-wrap' ).show();
		$valueWrap.find( 'label' ).text( window.aieData.i18n.value );

		// Clear existing conditions and populate based on field type
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

		// If current input is a select (from post_type_selector or table_selector), replace with input
		if ( $value.is( 'select' ) ) {
			const $input = jQuery( '<input>' )
				.attr( 'type', 'text' )
				.addClass( 'aie-filter-value' )
				.attr( 'name', 'filter_value[]' )
				.attr( 'placeholder', window.aieData.i18n.enterFilterValue );
			$value.replaceWith( $input );
			// Update reference
			$row.find( '.aie-filter-value' ).attr( 'type', fieldType === 'date' ? 'date' : ( fieldType === 'number' ? 'number' : 'text' ) );
		}

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
					$fieldSelect.append( jQuery( '<option>' ).val( '' ).text( window.aieData.i18n.selectField ) );
					
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
		} );
	},

	/**
	 * Get fields by content type
	 */
	getFieldsByContentType( contentType ) {
		const baseFields = [
			{
				label: window.aieData.i18n.fieldGroupStandard,
				options: [
					{ value: 'ID', label: window.aieData.i18n.fieldId, type: 'number' },
					{ value: 'post_title', label: window.aieData.i18n.fieldTitle, type: 'string' },
					{ value: 'post_content', label: window.aieData.i18n.fieldContent, type: 'string' },
					{ value: 'post_excerpt', label: window.aieData.i18n.fieldExcerpt, type: 'string' },
					{ value: 'post_date', label: window.aieData.i18n.fieldDate, type: 'date' },
					{ value: 'post_name', label: window.aieData.i18n.fieldSlug, type: 'string' },
					{ value: 'post_status', label: window.aieData.i18n.fieldStatus, type: 'string' },
				],
			},
			{
				label: window.aieData.i18n.fieldGroupAuthor,
				options: [
					{ value: 'post_author', label: window.aieData.i18n.fieldAuthorId, type: 'number' },
					{ value: 'author_name', label: window.aieData.i18n.fieldAuthorName, type: 'string' },
					{ value: 'author_email', label: window.aieData.i18n.fieldAuthorEmail, type: 'string' },
				],
			},
			{
				label: window.aieData.i18n.fieldGroupFeaturedImage || 'Featured Image',
				options: [
					{ value: 'featured_image_id', label: window.aieData.i18n.fieldFeaturedImageId || 'Featured Image ID', type: 'number' },
					{ value: 'featured_image_url', label: window.aieData.i18n.fieldFeaturedImageUrl || 'Featured Image URL', type: 'url' },
					{ value: 'featured_image_title', label: window.aieData.i18n.fieldFeaturedImageTitle || 'Featured Image Title', type: 'string' },
					{ value: 'featured_image_caption', label: window.aieData.i18n.fieldFeaturedImageCaption || 'Featured Image Caption', type: 'string' },
				],
			},
			{
				label: window.aieData.i18n.fieldGroupOther,
				options: [
					{ value: 'post_parent', label: window.aieData.i18n.fieldParentId, type: 'number' },
					{ value: 'menu_order', label: window.aieData.i18n.fieldMenuOrder || 'Menu Order', type: 'number' },
					{ value: 'comment_status', label: window.aieData.i18n.fieldCommentStatus, type: 'string' },
					{ value: 'post_modified', label: window.aieData.i18n.fieldModifiedDate, type: 'date' },
					{ value: '_wp_page_template', label: window.aieData.i18n.fieldTemplate, type: 'string' },
				],
			},
			{
				label: window.aieData.i18n.fieldGroupCustomFilters,
				options: [
					{ value: '_custom_field', label: window.aieData.i18n.fieldCustomFieldMeta, type: 'custom_field' },
					{ value: '_taxonomy_filter', label: window.aieData.i18n.fieldTaxonomyFilter, type: 'taxonomy_filter' },
				],
			},
		];
		
		// Customize based on content type
		if ( contentType === 'media' ) {
			return [
				{
					label: window.aieData.i18n.fieldGroupBasic,
					options: [
						{ value: 'ID', label: window.aieData.i18n.fieldId || 'ID', type: 'number' },
						{ value: 'post_title', label: window.aieData.i18n.fieldTitle, type: 'string' },
						{ value: 'post_content', label: window.aieData.i18n.fieldDescription, type: 'string' },
						{ value: 'post_excerpt', label: window.aieData.i18n.fieldCaption, type: 'string' },
						{ value: 'alt_text', label: window.aieData.i18n.fieldAltText, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupFileInformation,
					options: [
						{ value: 'guid', label: window.aieData.i18n.fieldFileUrlGuid, type: 'url' },
						{ value: 'file_url', label: window.aieData.i18n.fieldFileUrl, type: 'url' },
						{ value: 'file_path', label: window.aieData.i18n.fieldFilePathRelative, type: 'string' },
						{ value: 'file_name', label: window.aieData.i18n.fieldFileName, type: 'string' },
						{ value: 'file_extension', label: window.aieData.i18n.fieldFileExtension, type: 'string' },
						{ value: 'post_mime_type', label: window.aieData.i18n.fieldMimeType, type: 'string' },
						{ value: 'file_size', label: window.aieData.i18n.fieldFileSizeBytes, type: 'number' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupImageDimensions,
					options: [
						{ value: 'width', label: window.aieData.i18n.fieldWidthPx, type: 'number' },
						{ value: 'height', label: window.aieData.i18n.fieldHeightPx, type: 'number' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupDates,
					options: [
						{ value: 'post_date', label: window.aieData.i18n.fieldUploadDate, type: 'date' },
						{ value: 'post_modified', label: window.aieData.i18n.fieldModifiedDate, type: 'date' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupAuthor,
					options: [
						{ value: 'post_author', label: window.aieData.i18n.fieldAuthorId, type: 'number' },
						{ value: 'author_name', label: window.aieData.i18n.fieldAuthorName, type: 'string' },
						{ value: 'author_email', label: window.aieData.i18n.fieldAuthorEmail, type: 'email' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupAttachment,
					options: [
						{ value: 'post_parent', label: window.aieData.i18n.fieldAttachedToPostId, type: 'number' },
						{ value: 'attached_post_title', label: window.aieData.i18n.fieldAttachedPostTitle, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupCustomFilters,
					options: [
						{ value: '_custom_field', label: window.aieData.i18n.fieldCustomFieldMeta, type: 'custom_field' },
					],
				},
			];
		}

		// Pages don't have taxonomy section (but taxonomy_filter is still available in Custom Filters)
		if ( contentType === 'page' ) {
			return baseFields.filter( group => group.label !== window.aieData.i18n.fieldGroupTaxonomy );
		}

	// Menus
	if ( contentType === 'menu' ) {
		return [
			{
				label: window.aieData.i18n.fieldGroupBasic,
				options: [
					{ value: 'name', label: window.aieData.i18n.fieldMenuName, type: 'string' },
					{ value: 'menu_items', label: window.aieData.i18n.fieldMenuItemsArray + ' ' + (window.aieData.i18n.includesAcfFields || '(includes ACF fields)'), type: 'array' },
				],
			},
			{
				label: window.aieData.i18n.fieldGroupDetails,
				options: [
					{ value: 'count', label: window.aieData.i18n.fieldItemsCount, type: 'number' },
					{ value: 'locations', label: window.aieData.i18n.fieldThemeLocations, type: 'string' },
				],
			},
			{
				label: window.aieData.i18n.fieldGroupCustomFilters,
				options: [
					{ value: '_custom_field', label: window.aieData.i18n.fieldCustomFieldMeta, type: 'custom_field' },
				],
			},
		];
	}

		// Users
		if ( contentType === 'user' ) {
			return [
				{
					label: window.aieData.i18n.fieldGroupBasic,
					options: [
						{ value: 'ID', label: window.aieData.i18n.fieldId || 'ID', type: 'number' },
						{ value: 'user_login', label: window.aieData.i18n.fieldUsername || 'Username', type: 'string' },
						{ value: 'user_email', label: window.aieData.i18n.fieldEmail || 'Email', type: 'string' },
						{ value: 'display_name', label: window.aieData.i18n.fieldDisplayName || 'Display name', type: 'string' },
						{ value: 'user_nicename', label: window.aieData.i18n.fieldNiceName, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupProfile,
					options: [
						{ value: 'first_name', label: window.aieData.i18n.fieldFirstName, type: 'string' },
						{ value: 'last_name', label: window.aieData.i18n.fieldLastName, type: 'string' },
						{ value: 'nickname', label: window.aieData.i18n.fieldNickname, type: 'string' },
						{ value: 'description', label: window.aieData.i18n.fieldBio, type: 'string' },
						{ value: 'user_url', label: window.aieData.i18n.fieldWebsite || 'Website', type: 'string' },
						{ value: 'avatar_url', label: window.aieData.i18n.fieldAvatarUrl, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupSocialMedia || 'Social Media',
					options: [
						{ value: 'facebook', label: window.aieData.i18n.fieldFacebook || 'Facebook profile URL', type: 'string' },
						{ value: 'instagram', label: window.aieData.i18n.fieldInstagram || 'Instagram profile URL', type: 'string' },
						{ value: 'linkedin', label: window.aieData.i18n.fieldLinkedIn || 'LinkedIn profile URL', type: 'string' },
						{ value: 'myspace', label: window.aieData.i18n.fieldMySpace || 'MySpace profile URL', type: 'string' },
						{ value: 'pinterest', label: window.aieData.i18n.fieldPinterest || 'Pinterest profile URL', type: 'string' },
						{ value: 'soundcloud', label: window.aieData.i18n.fieldSoundCloud || 'SoundCloud profile URL', type: 'string' },
						{ value: 'tumblr', label: window.aieData.i18n.fieldTumblr || 'Tumblr profile URL', type: 'string' },
						{ value: 'wikipedia', label: window.aieData.i18n.fieldWikipedia || 'Wikipedia page about you', type: 'string' },
						{ value: 'twitter', label: window.aieData.i18n.fieldTwitter || 'X username', type: 'string' },
						{ value: 'youtube', label: window.aieData.i18n.fieldYouTube || 'YouTube profile URL', type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupRolePermissions,
					options: [
						{ value: 'role', label: window.aieData.i18n.fieldRole, type: 'string' },
						{ value: 'capabilities', label: window.aieData.i18n.fieldCapabilitiesArray, type: 'array' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupPreferences,
					options: [
						{ value: 'locale', label: window.aieData.i18n.fieldLanguage || 'Language', type: 'string' },
						{ value: 'admin_color', label: window.aieData.i18n.fieldAdminColorScheme, type: 'string' },
						{ value: 'rich_editing', label: window.aieData.i18n.fieldVisualEditor, type: 'boolean' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupStats,
					options: [
						{ value: 'posts_count', label: window.aieData.i18n.fieldPostsCount, type: 'number' },
						{ value: 'user_registered', label: window.aieData.i18n.fieldRegistrationDate, type: 'date' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupCustomFilters,
					options: [
						{ value: '_custom_field', label: window.aieData.i18n.fieldCustomFieldMeta, type: 'custom_field' },
					],
				},
			];
		}

		// Comments
		if ( contentType === 'comment' ) {
			return [
				{
					label: window.aieData.i18n.fieldGroupBasic,
					options: [
						{ value: 'comment_ID', label: window.aieData.i18n.fieldCommentId, type: 'number' },
						{ value: 'comment_post_ID', label: window.aieData.i18n.fieldPostId, type: 'number' },
						{ value: 'comment_content', label: window.aieData.i18n.fieldCommentContent, type: 'string' },
						{ value: 'comment_approved', label: window.aieData.i18n.fieldStatus, type: 'string' },
						{ value: 'comment_type', label: window.aieData.i18n.fieldCommentType, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupAuthor,
					options: [
						{ value: 'comment_author', label: window.aieData.i18n.fieldAuthorName, type: 'string' },
						{ value: 'comment_author_email', label: window.aieData.i18n.fieldAuthorEmail, type: 'string' },
						{ value: 'comment_author_url', label: window.aieData.i18n.fieldAuthorUrl, type: 'string' },
						{ value: 'comment_author_IP', label: window.aieData.i18n.fieldAuthorIp, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupRelatedPost,
					options: [
						{ value: 'post_title', label: window.aieData.i18n.fieldPostTitle, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupDates,
					options: [
						{ value: 'comment_date', label: window.aieData.i18n.fieldCommentDate, type: 'date' },
						{ value: 'comment_date_gmt', label: window.aieData.i18n.fieldCommentDateGmt, type: 'date' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupHierarchy,
					options: [
						{ value: 'comment_parent', label: window.aieData.i18n.fieldParentCommentId, type: 'number' },
						{ value: 'comment_karma', label: window.aieData.i18n.fieldKarma, type: 'number' },
					],
				},
			];
		}

		// Custom Post Types
		if ( contentType === 'custom_post_types' ) {
			return [
				{
					label: window.aieData.i18n.fieldGroupPostTypeSelection,
					options: [
						{ value: '_post_type', label: window.aieData.i18n.fieldPostTypeSelectSpecific, type: 'post_type_selector' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupStandard,
					options: [
						{ value: 'ID', label: window.aieData.i18n.fieldId, type: 'number' },
						{ value: 'post_title', label: window.aieData.i18n.fieldTitle, type: 'string' },
						{ value: 'post_content', label: window.aieData.i18n.fieldContent, type: 'string' },
						{ value: 'post_excerpt', label: window.aieData.i18n.fieldExcerpt, type: 'string' },
						{ value: 'post_date', label: window.aieData.i18n.fieldDate, type: 'date' },
						{ value: 'post_name', label: window.aieData.i18n.fieldSlug, type: 'string' },
						{ value: 'post_status', label: window.aieData.i18n.fieldStatus, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupAuthor,
					options: [
						{ value: 'post_author', label: window.aieData.i18n.fieldAuthorId, type: 'number' },
						{ value: 'author_name', label: window.aieData.i18n.fieldAuthorName, type: 'string' },
						{ value: 'author_email', label: window.aieData.i18n.fieldAuthorEmail, type: 'string' },
					],
				},
			{
				label: window.aieData.i18n.fieldGroupOther,
				options: [
					{ value: 'post_parent', label: window.aieData.i18n.fieldParentId, type: 'number' },
					{ value: 'post_modified', label: window.aieData.i18n.fieldModifiedDate, type: 'date' },
					{ value: '_wp_page_template', label: window.aieData.i18n.fieldTemplate, type: 'string' },
				],
			},
			{
				label: window.aieData.i18n.fieldGroupCustomFilters,
				options: [
					{ value: '_custom_field', label: window.aieData.i18n.fieldCustomFieldMeta, type: 'custom_field' },
					{ value: '_taxonomy_filter', label: window.aieData.i18n.fieldTaxonomyFilter, type: 'taxonomy_filter' },
				],
			},
		];
	}		// Taxonomy
		if ( contentType === 'taxonomy' ) {
			return [
				{
					label: window.aieData.i18n.fieldGroupTaxonomySelection,
					options: [
						{ value: '_taxonomy', label: window.aieData.i18n.fieldTaxonomySelectSpecific, type: 'taxonomy_selector' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupBasic,
					options: [
						{ value: 'term_id', label: window.aieData.i18n.fieldTermId, type: 'number' },
						{ value: 'name', label: window.aieData.i18n.fieldTermName, type: 'string' },
						{ value: 'slug', label: window.aieData.i18n.fieldTermSlug, type: 'string' },
						{ value: 'description', label: window.aieData.i18n.fieldDescription, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupHierarchy,
					options: [
						{ value: 'parent', label: window.aieData.i18n.fieldParentTermId, type: 'number' },
						{ value: 'count', label: window.aieData.i18n.fieldPostsCount, type: 'number' },
					],
				},
			];
		}

		// WooCommerce Products
		if ( contentType === 'woo_product' ) {
			return [
				{
					label: window.aieData.i18n.fieldGroupBasic,
					options: [
						{ value: 'ID', label: window.aieData.i18n.fieldProductId, type: 'number' },
						{ value: 'post_title', label: window.aieData.i18n.fieldProductName, type: 'string' },
						{ value: 'post_name', label: window.aieData.i18n.fieldSlug, type: 'string' },
						{ value: 'post_status', label: window.aieData.i18n.fieldStatus, type: 'string' },
						{ value: 'sku', label: window.aieData.i18n.fieldSku, type: 'string' },
						{ value: 'post_author', label: window.aieData.i18n.fieldAuthorId, type: 'number' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupContent,
					options: [
						{ value: 'post_content', label: window.aieData.i18n.fieldDescription, type: 'string' },
						{ value: 'post_excerpt', label: window.aieData.i18n.fieldShortDescription, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupPricing,
					options: [
						{ value: 'regular_price', label: window.aieData.i18n.fieldRegularPrice, type: 'number' },
						{ value: 'sale_price', label: window.aieData.i18n.fieldSalePrice, type: 'number' },
						{ value: 'tax_status', label: window.aieData.i18n.fieldTaxStatus, type: 'string' },
						{ value: 'tax_class', label: window.aieData.i18n.fieldTaxClass, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupInventory,
					options: [
						{ value: 'stock_quantity', label: window.aieData.i18n.fieldStockQuantity, type: 'number' },
						{ value: 'stock_status', label: window.aieData.i18n.fieldStockStatus, type: 'string' },
						{ value: 'manage_stock', label: window.aieData.i18n.fieldManageStock, type: 'boolean' },
						{ value: 'backorders', label: window.aieData.i18n.fieldBackorders, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupProductType,
					options: [
						{ value: 'product_type', label: window.aieData.i18n.fieldProductType, type: 'string' },
						{ value: 'downloadable', label: window.aieData.i18n.fieldDownloadable, type: 'boolean' },
						{ value: 'virtual', label: window.aieData.i18n.fieldVirtual, type: 'boolean' },
					],
				},			{
				label: window.aieData.i18n.fieldGroupShipping,
				options: [
					{ value: 'weight', label: window.aieData.i18n.fieldWeight, type: 'number' },
					{ value: 'length', label: window.aieData.i18n.fieldLength, type: 'number' },
					{ value: 'width', label: window.aieData.i18n.fieldWidth, type: 'number' },
					{ value: 'height', label: window.aieData.i18n.fieldHeight, type: 'number' },
					{ value: 'shipping_class', label: window.aieData.i18n.fieldShippingClass, type: 'string' },
				],
			},
			{
				label: window.aieData.i18n.fieldGroupFeaturedImage || 'Featured Image',
				options: [
					{ value: 'featured_image_id', label: window.aieData.i18n.fieldFeaturedImageId || 'Featured Image ID', type: 'number' },
					{ value: 'featured_image_url', label: window.aieData.i18n.fieldFeaturedImageUrl || 'Featured Image URL', type: 'url' },
					{ value: 'featured_image_title', label: window.aieData.i18n.fieldFeaturedImageTitle || 'Featured Image Title', type: 'string' },
					{ value: 'featured_image_caption', label: window.aieData.i18n.fieldFeaturedImageCaption || 'Featured Image Caption', type: 'string' },
				],
			},
			{
				label: window.aieData.i18n.fieldGroupMedia,
				options: [
					{ value: 'product_gallery', label: window.aieData.i18n.fieldGalleryImages, type: 'array' },
					{ value: 'variations', label: window.aieData.i18n.fieldVariations || 'Variations (JSON)', type: 'json' },
				],
			},
				{
					label: window.aieData.i18n.fieldGroupTaxonomy,
					options: [
						{ value: 'product_cat', label: window.aieData.i18n.fieldCategories, type: 'string' },
						{ value: 'product_tag', label: window.aieData.i18n.fieldTags, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupReviews,
					options: [
						{ value: 'average_rating', label: window.aieData.i18n.fieldAverageRating, type: 'number' },
						{ value: 'review_count', label: window.aieData.i18n.fieldReviewCount, type: 'number' },
						{ value: 'comment_status', label: window.aieData.i18n.fieldReviewsEnabled, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupVisibility,
					options: [
						{ value: 'featured', label: window.aieData.i18n.fieldFeatured, type: 'boolean' },
						{ value: 'visibility', label: window.aieData.i18n.fieldCatalogVisibility, type: 'string' },
						{ value: 'total_sales', label: window.aieData.i18n.fieldTotalSales, type: 'number' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupDates,
					options: [
						{ value: 'post_date', label: window.aieData.i18n.fieldCreatedDate, type: 'date' },
						{ value: 'post_modified', label: window.aieData.i18n.fieldModifiedDate, type: 'date' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupCustomFilters,
					options: [
						{ value: '_custom_field', label: window.aieData.i18n.fieldCustomFieldMeta, type: 'custom_field' },
						{ value: '_taxonomy_filter', label: window.aieData.i18n.fieldTaxonomyFilter, type: 'taxonomy_filter' },
					],
				},
			];
		}

		// WooCommerce Orders
		if ( contentType === 'woo_order' ) {
			return [
				{
					label: window.aieData.i18n.fieldGroupBasic,
					options: [
						{ value: 'ID', label: window.aieData.i18n.fieldOrderId, type: 'number' },
						{ value: 'order_number', label: window.aieData.i18n.fieldOrderNumber, type: 'string' },
						{ value: 'order_status', label: window.aieData.i18n.fieldStatus, type: 'string' },
						{ value: 'order_key', label: window.aieData.i18n.fieldOrderKey, type: 'string' },
						{ value: 'currency', label: window.aieData.i18n.fieldCurrency, type: 'string' },
					],
				},			{
				label: window.aieData.i18n.fieldGroupAmounts,
				options: [
					{ value: 'order_total', label: window.aieData.i18n.fieldOrderTotal, type: 'number' },
					{ value: 'order_subtotal', label: window.aieData.i18n.fieldSubtotal, type: 'number' },
					{ value: 'order_tax', label: window.aieData.i18n.fieldTax, type: 'number' },
					{ value: 'order_shipping', label: window.aieData.i18n.fieldShipping, type: 'number' },
					{ value: 'order_discount', label: window.aieData.i18n.fieldDiscount, type: 'number' },
					{ value: 'cart_tax', label: 'Cart Tax', type: 'number' },
					{ value: 'shipping_tax', label: 'Shipping Tax', type: 'number' },
					{ value: 'total_tax', label: 'Total Tax', type: 'number' },
				],
			},			{
				label: window.aieData.i18n.fieldGroupCustomer,
				options: [
					{ value: 'customer_id', label: window.aieData.i18n.fieldCustomerId, type: 'number' },
					{ value: 'billing_email', label: window.aieData.i18n.fieldEmail, type: 'string' },
					{ value: 'customer_note', label: window.aieData.i18n.fieldCustomerNote, type: 'string' },
					{ value: 'customer_ip_address', label: 'Customer IP Address', type: 'string' },
					{ value: 'customer_user_agent', label: 'Customer User Agent', type: 'string' },
				],
			},
				{
					label: window.aieData.i18n.fieldGroupBillingAddress,
					options: [
						{ value: 'billing_first_name', label: window.aieData.i18n.fieldFirstName, type: 'string' },
						{ value: 'billing_last_name', label: window.aieData.i18n.fieldLastName, type: 'string' },
						{ value: 'billing_company', label: window.aieData.i18n.fieldCompany, type: 'string' },
						{ value: 'billing_address_1', label: window.aieData.i18n.fieldAddress1, type: 'string' },
						{ value: 'billing_address_2', label: window.aieData.i18n.fieldAddress2, type: 'string' },
						{ value: 'billing_city', label: window.aieData.i18n.fieldCity, type: 'string' },
						{ value: 'billing_state', label: window.aieData.i18n.fieldState, type: 'string' },
						{ value: 'billing_postcode', label: window.aieData.i18n.fieldPostcode, type: 'string' },
						{ value: 'billing_country', label: window.aieData.i18n.fieldCountry, type: 'string' },
						{ value: 'billing_phone', label: window.aieData.i18n.fieldPhone, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupShippingAddress,
					options: [
						{ value: 'shipping_first_name', label: window.aieData.i18n.fieldFirstName, type: 'string' },
						{ value: 'shipping_last_name', label: window.aieData.i18n.fieldLastName, type: 'string' },
						{ value: 'shipping_company', label: window.aieData.i18n.fieldCompany, type: 'string' },
						{ value: 'shipping_address_1', label: window.aieData.i18n.fieldAddress1, type: 'string' },
						{ value: 'shipping_address_2', label: window.aieData.i18n.fieldAddress2, type: 'string' },
						{ value: 'shipping_city', label: window.aieData.i18n.fieldCity, type: 'string' },
						{ value: 'shipping_state', label: window.aieData.i18n.fieldState, type: 'string' },
						{ value: 'shipping_postcode', label: window.aieData.i18n.fieldPostcode, type: 'string' },
						{ value: 'shipping_country', label: window.aieData.i18n.fieldCountry, type: 'string' },
					],
				},			{
				label: window.aieData.i18n.fieldGroupOrderItems,
				options: [
					{ value: 'order_items', label: window.aieData.i18n.fieldOrderItemsArray, type: 'array' },
					{ value: 'item_count', label: window.aieData.i18n.fieldItemCount, type: 'number' },
					{ value: 'shipping_lines', label: 'Shipping Lines', type: 'array' },
					{ value: 'fee_lines', label: 'Fee Lines', type: 'array' },
					{ value: 'coupon_lines', label: 'Coupon Lines', type: 'array' },
				],
			},
				{
					label: window.aieData.i18n.fieldGroupPayment,
					options: [
						{ value: 'payment_method', label: window.aieData.i18n.fieldPaymentMethod, type: 'string' },
						{ value: 'payment_method_title', label: window.aieData.i18n.fieldPaymentMethodTitle, type: 'string' },
						{ value: 'transaction_id', label: window.aieData.i18n.fieldTransactionId, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupShipping,
					options: [
						{ value: 'shipping_method', label: window.aieData.i18n.fieldShippingMethod, type: 'string' },
					],
				},			{
				label: window.aieData.i18n.fieldGroupDates,
				options: [
					{ value: 'order_date', label: window.aieData.i18n.fieldOrderDate, type: 'date' },
					{ value: 'date_modified', label: 'Date Modified', type: 'date' },
					{ value: 'completed_date', label: window.aieData.i18n.fieldCompletedDate, type: 'date' },
					{ value: 'paid_date', label: window.aieData.i18n.fieldPaidDate, type: 'date' },
				],
			},			{
				label: window.aieData.i18n.fieldGroupNotes,
				options: [
					{ value: 'order_notes', label: window.aieData.i18n.fieldOrderNotesArray, type: 'array' },
					{ value: 'order_meta', label: 'Order Meta', type: 'array' },
				],
			},
				{
					label: window.aieData.i18n.fieldGroupCustomFilters,
					options: [
						{ value: '_custom_field', label: window.aieData.i18n.fieldCustomFieldMeta, type: 'custom_field' },
					],
				},
			];
		}

		// WooCommerce Coupons
		if ( contentType === 'woo_coupon' ) {
			return [
				{
					label: window.aieData.i18n.fieldGroupBasic,
					options: [
						{ value: 'ID', label: window.aieData.i18n.fieldCouponId, type: 'number' },
						{ value: 'post_title', label: window.aieData.i18n.fieldCouponCode, type: 'string' },
						{ value: 'post_excerpt', label: window.aieData.i18n.fieldDescription, type: 'string' },
						{ value: 'post_status', label: window.aieData.i18n.fieldStatus, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupDiscount,
					options: [
						{ value: 'discount_type', label: window.aieData.i18n.fieldDiscountType, type: 'string' },
						{ value: 'coupon_amount', label: window.aieData.i18n.fieldCouponAmount, type: 'number' },
						{ value: 'free_shipping', label: window.aieData.i18n.fieldFreeShipping, type: 'boolean' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupUsageRestrictions,
					options: [
						{ value: 'minimum_amount', label: window.aieData.i18n.fieldMinimumSpend, type: 'number' },
						{ value: 'maximum_amount', label: window.aieData.i18n.fieldMaximumSpend, type: 'number' },
						{ value: 'individual_use', label: window.aieData.i18n.fieldIndividualUseOnly, type: 'boolean' },
						{ value: 'exclude_sale_items', label: window.aieData.i18n.fieldExcludeSaleItems, type: 'boolean' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupProductRestrictions,
					options: [
						{ value: 'product_ids', label: window.aieData.i18n.fieldAllowedProducts, type: 'array' },
						{ value: 'excluded_product_ids', label: window.aieData.i18n.fieldExcludedProducts, type: 'array' },
						{ value: 'product_categories', label: window.aieData.i18n.fieldAllowedCategories, type: 'array' },
						{ value: 'excluded_product_categories', label: window.aieData.i18n.fieldExcludedCategories, type: 'array' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupEmailRestrictions,
					options: [
						{ value: 'allowed_emails', label: window.aieData.i18n.fieldAllowedEmails, type: 'array' },
					],
				},			{
				label: window.aieData.i18n.fieldGroupUsageLimits,
				options: [
					{ value: 'usage_count', label: window.aieData.i18n.fieldUsageCount, type: 'number' },
					{ value: 'usage_limit', label: window.aieData.i18n.fieldUsageLimitTotal, type: 'number' },
					{ value: 'usage_limit_per_user', label: window.aieData.i18n.fieldUsageLimitPerUser, type: 'number' },
					{ value: 'limit_usage_to_x_items', label: 'Limit Usage to X Items', type: 'number' },
				],
			},
				{
					label: window.aieData.i18n.fieldGroupDates,
					options: [
						{ value: 'date_expires', label: window.aieData.i18n.fieldExpiryDate, type: 'date' },
						{ value: 'post_date', label: window.aieData.i18n.fieldCreatedDate, type: 'date' },
						{ value: 'post_modified', label: window.aieData.i18n.fieldModifiedDate, type: 'date' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupCustomFilters,
					options: [
						{ value: '_custom_field', label: window.aieData.i18n.fieldCustomFieldMeta, type: 'custom_field' },
					],
				},
			];
		}

		// WooCommerce Attributes
		if ( contentType === 'woo_attribute' ) {
			return [
				{
					label: window.aieData.i18n.fieldGroupBasic,
					options: [
						{ value: 'attribute_id', label: window.aieData.i18n.fieldAttributeId, type: 'number' },
						{ value: 'attribute_name', label: window.aieData.i18n.fieldAttributeName, type: 'string' },
						{ value: 'attribute_label', label: window.aieData.i18n.fieldAttributeLabel, type: 'string' },
						{ value: 'attribute_type', label: window.aieData.i18n.fieldAttributeType, type: 'string' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupSettings,
					options: [
						{ value: 'attribute_orderby', label: window.aieData.i18n.fieldDefaultSortOrder, type: 'string' },
						{ value: 'attribute_public', label: window.aieData.i18n.fieldEnableArchives, type: 'boolean' },
					],
				},
				{
					label: window.aieData.i18n.fieldGroupTerms,
					options: [
						{ value: 'term_count', label: window.aieData.i18n.fieldTermsCount, type: 'number' },
						{ value: 'attribute_terms', label: window.aieData.i18n.fieldAllTermsArray, type: 'array' },
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
						label: window.aieData.i18n.fieldGroupTableColumns,
						options: columnOptions
					},
				];
			}

			// Otherwise show message to select table first
			return [
				{
					label: window.aieData.i18n.fieldGroupTableSelection,
					options: [
						{ value: '_select_table', label: window.aieData.i18n.fieldPleaseSelectTable, type: 'info' },
					],
				},
			];
		}

		return baseFields;
	},

	/**
	 * Get filter fields by content type (for Step 2: Filter Data)
	 * Same as getFieldsByContentType but excludes Featured Image group
	 */
	getFilterFieldsByContentType( contentType ) {
		// Get all fields first
		const allFields = this.getFieldsByContentType( contentType );
		
		// Groups to always exclude
		const excludedLabels = [
			window.aieData.i18n.fieldGroupFeaturedImage || 'Featured Image',
		];

		// For woo_coupon, also exclude these groups from filters
		if ( contentType === 'woo_coupon' ) {
			excludedLabels.push(
				window.aieData.i18n.fieldGroupDiscount,
				window.aieData.i18n.fieldGroupUsageRestrictions,
				window.aieData.i18n.fieldGroupProductRestrictions,
				window.aieData.i18n.fieldGroupEmailRestrictions,
				window.aieData.i18n.fieldGroupUsageLimits,
				window.aieData.i18n.fieldGroupCustomFilters
			);
		}

		return allFields.filter( group => ! excludedLabels.includes( group.label ) );
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
			$value.attr( 'placeholder', window.aieData.i18n.enterValuesCommaSeparated );
			return;
		}
		
		// For 'between' condition on numbers, use text to allow comma-separated range
		if ( condition === 'between' && fieldType === 'number' ) {
			$value.attr( 'type', 'text' );
			$value.attr( 'placeholder', window.aieData.i18n.enterTwoNumbersCommaSeparated );
			return;
		}
		
		// Otherwise, set type based on field type
		if ( fieldType === 'date' ) {
			$value.attr( 'type', 'date' );
			$value.attr( 'placeholder', '' );
		} else if ( fieldType === 'number' ) {
			$value.attr( 'type', 'number' );
			$value.attr( 'placeholder', window.aieData.i18n.enterNumberPlaceholder );
		} else {
			$value.attr( 'type', 'text' );
			$value.attr( 'placeholder', window.aieData.i18n.enterFilterValue );
		}
	},

	/**
	 * Get conditions by field type
	 */
	getConditionsByFieldType( fieldType ) {
		const conditions = {
			string: [
				{ value: 'equals', label: window.aieData.i18n.equals },
				{ value: 'not_equals', label: window.aieData.i18n.notEquals },
				{ value: 'in', label: window.aieData.i18n.inFilter },
				{ value: 'not_in', label: window.aieData.i18n.notInFilter },
				{ value: 'contains', label: window.aieData.i18n.contains },
				{ value: 'not_contains', label: window.aieData.i18n.notContains },
				{ value: 'is_empty', label: window.aieData.i18n.isEmpty },
				{ value: 'is_not_empty', label: window.aieData.i18n.isNotEmpty },
			],
			number: [
				{ value: 'equals', label: window.aieData.i18n.equals },
				{ value: 'not_equals', label: window.aieData.i18n.notEquals },
				{ value: 'in', label: window.aieData.i18n.inFilter },
				{ value: 'not_in', label: window.aieData.i18n.notInFilter },
				{ value: 'greater', label: window.aieData.i18n.greaterThan },
				{ value: 'equals_or_greater', label: window.aieData.i18n.greaterOrEqual },
				{ value: 'less', label: window.aieData.i18n.lessThan },
				{ value: 'equals_or_less', label: window.aieData.i18n.lessOrEqual },
				{ value: 'is_empty', label: window.aieData.i18n.isEmpty },
				{ value: 'is_not_empty', label: window.aieData.i18n.isNotEmpty },
			],
			date: [
				{ value: 'equals', label: window.aieData.i18n.equals },
				{ value: 'not_equals', label: window.aieData.i18n.notEquals },
				{ value: 'greater', label: window.aieData.i18n.newerThan || window.aieData.i18n.greaterThan },
				{ value: 'equals_or_greater', label: window.aieData.i18n.greaterOrEqual },
				{ value: 'less', label: window.aieData.i18n.olderThan || window.aieData.i18n.lessThan },
				{ value: 'equals_or_less', label: window.aieData.i18n.lessOrEqual },
				{ value: 'is_empty', label: window.aieData.i18n.isEmpty },
				{ value: 'is_not_empty', label: window.aieData.i18n.isNotEmpty },
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
				const tables = response.tables || response || [];
				
				
				// Clear and populate dropdown
				$dropdown.empty();
				$dropdown.append( jQuery( '<option>' ).val( '' ).text( window.aieData.i18n.selectTable ) );

				if ( !Array.isArray( tables ) || tables.length === 0 ) {
					$dropdown.append( jQuery( '<option>' ).val( '' ).text( window.aieData.i18n.noTablesFound ) );
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
					// Update Next button state based on table selection
					this.updateStep2NextButton();
				} );
			} )
			.catch( ( error ) => {
				$dropdown.empty();
				$dropdown.append( jQuery( '<option>' ).val( '' ).text( window.aieData.i18n.errorLoadingTables ) );
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
		$columnsList.html( `<p>${window.aieData.i18n.loadingTableColumns}</p>` );

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
				$columnsList.html( `<p class="error">${window.aieData.i18n.errorLoadingColumns}</p>` );
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

	/**
	 * Convert field functions from fieldKey (with timestamp) to actual field names
	 * 
	 * @param {Object} fieldFunctions - Object with fieldKey as keys and function IDs as values
	 * @param {Array} selectedFields - Array of selected fields with { key, field, label, type }
	 * @return {Object} - Object with actual field names as keys
	 */
	convertFieldFunctions( fieldFunctions, selectedFields ) {
		const converted = {};
		
		if ( ! fieldFunctions || ! selectedFields ) {
			return converted;
		}

		// Create a map from fieldKey to actual field name
		const keyToFieldMap = {};
		selectedFields.forEach( fieldData => {
			keyToFieldMap[ fieldData.key ] = fieldData.field;
		} );

		// Convert fieldKey to actual field name
		Object.keys( fieldFunctions ).forEach( fieldKey => {
			const actualFieldName = keyToFieldMap[ fieldKey ];
			if ( actualFieldName && fieldFunctions[ fieldKey ].length > 0 ) {
				converted[ actualFieldName ] = fieldFunctions[ fieldKey ];
			}
		} );

		return converted;
	},

};

export default ExportModule;
