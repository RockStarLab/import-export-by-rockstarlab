/**
 * Content Updater Module
 *
 * Handles the content updater wizard functionality
 */

import Utils from './utils';
import BackupWarningModal from './BackupWarningModal';

const ContentUpdater = {
	currentStep: 1,
	totalSteps: 5, // Updated from 4 to 5 steps
	jobId: null,
	progressInterval: null,
	selectedFields: [],
	selectedFieldTypes: {},
	selectedTableName: '',
	currentTableColumns: [],
	fieldFunctions: {},
	availableFunctions: [],
	selectedFilters: [], // Store selected filters
	selectedTaxonomyFilters: [], // Store selected taxonomy filters
	filteredCount: null, // Store filtered item count

	/**
	 * Initialize module
	 */
	init() {
		if ( ! jQuery( '#rsl-ie-content-updater' ).length ) {
			return;
		}

		// Check if resuming a job from Jobs Log
		const urlParams = new URLSearchParams( window.location.search );
		const resumeJobId = urlParams.get( 'resume_job' );

		this.bindEvents();

		if ( resumeJobId ) {
			this.jobId = parseInt( resumeJobId, 10 );
			if ( Number.isNaN( this.jobId ) ) {
				this.showStep( 1 );
				this.loadAvailableFunctions();
				return;
			}
			this.showStep( 5 );

			// Show progress UI immediately when resuming
			jQuery( '#rsl-ie-updater-config' ).hide();
			jQuery( '#rsl-ie-updater-progress' ).show();
			jQuery( '#rsl-ie-updater-prev-from-step-4' ).hide();

			// Get current state and continue processing
			this.updateProgress().then( () => {
				this.startProgressTracking();
				this.processNextBatch();
			} );
		} else {
			this.showStep( 1 );
		}

		this.loadAvailableFunctions();
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $wizard = jQuery( '#rsl-ie-content-updater' );

		// Content type search
		$wizard.on( 'input', '#rsl-ie-updater-content-type-search', ( e ) =>
			this.filterContentTypes( e )
		);

		// Step navigation
		$wizard.on( 'click', '.rsl-ie-updater-next-step', () =>
			this.nextStep()
		);
		$wizard.on( 'click', '.rsl-ie-updater-prev-step', () =>
			this.prevStep()
		);

		// Content type selection
		$wizard.on( 'change', 'input[name="updater_content_type"]', ( e ) =>
			this.onContentTypeChange( e )
		);

		// Prevent selection of premium locked content types
		$wizard.on(
			'click',
			'.rsl-ie-content-type.rsl-ie-premium-locked',
			( e ) => {
				e.preventDefault();
				e.stopPropagation();

				// Show upgrade message
				const message = window.rslIeData.i18n.premiumOnlyFeature;
				Utils.showNotice( message, 'warning' );

				// Prevent the radio button from being checked
				const $input = jQuery( e.currentTarget ).find(
					'input[type="radio"]'
				);
				$input.prop( 'checked', false );

				return false;
			}
		);

		// Filter events (Step 2)
		$wizard.on( 'click', '.rsl-ie-updater-add-filter', () =>
			this.addFilterRow()
		);
		$wizard.on( 'click', '.rsl-ie-updater-remove-filter', ( e ) =>
			this.removeFilterRow( e )
		);
		$wizard.on( 'change', '.rsl-ie-updater-filter-field', ( e ) =>
			this.onFilterFieldChange( e )
		);
		$wizard.on( 'change', '.rsl-ie-updater-filter-condition', ( e ) =>
			this.onFilterConditionChange( e )
		);
		$wizard.on( 'change', '.rsl-ie-updater-filter-value', () =>
			Utils.debounce( () => this.refreshCount( false ), 500 )()
		);
		$wizard.on( 'click', '.rsl-ie-updater-refresh-count', () =>
			this.refreshCount( true )
		);
		$wizard.on( 'change', '#rsl-ie-updater-table-name', ( e ) =>
			this.onDatabaseTableChange( e )
		);

		// Field selection (Step 3)
		$wizard.on( 'click', '.rsl-ie-updater-clear-all-fields', () =>
			this.clearAllFields()
		);
		$wizard.on( 'input', '#rsl-ie-updater-fields-search', ( e ) =>
			this.filterFields( e )
		);
		$wizard.on( 'click', '.rsl-ie-clear-search', ( e ) => {
			e.preventDefault();
			const $search = jQuery( '#rsl-ie-updater-fields-search' );
			$search.val( '' );
			$search.trigger( 'input' );
			$search.focus();
		} );
		$wizard.on( 'click', '.rsl-ie-add-all-fields', ( e ) => {
			e.preventDefault();
			this.addAllFieldsFromCategory( e.currentTarget );
		} );

		// Function assignment (Step 3) - old handlers kept for compatibility
		$wizard.on( 'change', '.rsl-ie-field-function-select', ( e ) =>
			this.onFunctionChange( e )
		);
		$wizard.on( 'click', '.rsl-ie-apply-function-to-all', () =>
			this.applyFunctionToAll()
		);
		$wizard.on( 'click', '.rsl-ie-clear-all-functions', () =>
			this.clearAllFunctions()
		);
		$wizard.on( 'click', '.rsl-ie-test-function', ( e ) =>
			this.testFunction( e )
		);

		// Start update (Step 4)
		$wizard.on( 'click', '.rsl-ie-start-update-btn', () =>
			this.startUpdate()
		);
		$wizard.on( 'click', '.rsl-ie-cancel-update-btn', () =>
			this.cancelUpdate()
		);
		$wizard.on( 'click', '.rsl-ie-start-new-update', () =>
			this.startNewUpdate()
		);

		// Setup drag and drop for field selection
		this.setupDragAndDrop();

		// Modal events
		this.initFieldFunctionsModal();
	},

	/**
	 * Initialize field functions modal
	 */
	initFieldFunctionsModal() {
		const $modal = jQuery( '#rsl-ie-updater-functions-modal' );
		if ( ! $modal.length ) return;

		// Close modal
		$modal.find( '.rsl-ie-modal-close' ).on( 'click', () => {
			this.closeFieldFunctionsModal();
		} );

		$modal.find( '.rsl-ie-modal-cancel' ).on( 'click', () => {
			this.closeFieldFunctionsModal();
		} );

		// Save functions
		$modal.find( '.rsl-ie-save-updater-functions' ).on( 'click', () => {
			this.saveFieldFunctions();
		} );

		$modal.find( '.rsl-ie-test-updater-pipeline' ).on( 'click', () => {
			this.testFunctionPipeline();
		} );

		// Functions search
		$modal
			.find( '#rsl-ie-updater-functions-search' )
			.on( 'input', ( e ) => {
				this.filterFunctions( e.target.value );
			} );

		// Functions filter
		$modal
			.find( 'input[name="updater-functions-filter"]' )
			.on( 'change', ( e ) => {
				this.filterFunctionsByCategory( e.target.value );
			} );

		// Create new function button
		$modal.find( '.rsl-ie-create-new-function' ).on( 'click', ( e ) => {
			e.preventDefault();
			this.createNewFunction();
		} );
	},

	/**
	 * Show specific step
	 */
	showStep( step ) {
		const $wizard = jQuery( '#rsl-ie-content-updater' );

		// Hide all steps
		$wizard.find( '.rsl-ie-step' ).removeClass( 'active' );

		// Show target step
		$wizard.find( `.rsl-ie-updater-step-${ step }` ).addClass( 'active' );

		// Update indicator
		$wizard
			.find( '.rsl-ie-step-indicator' )
			.removeClass( 'active completed' );
		$wizard
			.find( `.rsl-ie-step-indicator[data-step="${ step }"]` )
			.addClass( 'active' );

		// Mark previous steps as completed
		for ( let i = 1; i < step; i++ ) {
			$wizard
				.find( `.rsl-ie-step-indicator[data-step="${ i }"]` )
				.addClass( 'completed' );
		}

		this.currentStep = step;

		// Step-specific actions
		switch ( step ) {
			case 2:
				this.loadFiltersLibrary(); // New: Load filters
				break;
			case 3:
				this.loadFieldsLibrary();
				break;
			case 4:
				this.buildFunctionsTable();
				break;
			case 5:
				this.prepareUpdateSummary();
				break;
		}
	},

	/**
	 * Navigate to next step
	 */
	nextStep() {
		if ( ! this.validateCurrentStep() ) {
			return;
		}

		// Show backup warning when leaving step 1 (content type selection)
		if ( this.currentStep === 1 ) {
			BackupWarningModal.show(
				() => {
					// User confirmed backup - proceed to next step
					this.proceedToNextStep();
				},
				() => {
					// User cancelled - stay on current step
				}
			);
			return;
		}

		this.proceedToNextStep();
	},

	/**
	 * Proceed to next step (after validation and backup warning)
	 */
	proceedToNextStep() {
		// Save filters when leaving step 2
		if ( this.currentStep === 2 ) {
			const collectedFilters = this.collectFilters();
			this.selectedFilters = collectedFilters.filters;
			this.selectedTaxonomyFilters = collectedFilters.taxonomy;
		}

		if ( this.currentStep < this.totalSteps ) {
			this.showStep( this.currentStep + 1 );
		}
	},

	/**
	 * Navigate to previous step
	 */
	prevStep() {
		if ( this.currentStep > 1 ) {
			this.showStep( this.currentStep - 1 );
		}
	},

	/**
	 * Validate current step before moving to next
	 */
	validateCurrentStep() {
		switch ( this.currentStep ) {
			case 1:
				// Content type must be selected
				if (
					! jQuery( 'input[name="updater_content_type"]:checked' )
						.length
				) {
					Utils.showNotice(
						window.rslIeData.i18n.pleaseSelectContentType,
						'error'
					);
					return false;
				}
				return true;

			case 2:
				// Database table must be selected for database_table type
				if (
					this.isDatabaseTableType() &&
					! this.getSelectedTableName()
				) {
					Utils.showNotice(
						window.rslIeData.i18n.pleaseSelectTable,
						'error'
					);
					jQuery( '#rsl-ie-updater-table-name' ).trigger( 'focus' );
					return false;
				}

				// Taxonomy terms require at least one taxonomy filter
				if ( this.requiresFilter() && ! this.hasRequiredFilter() ) {
					Utils.showNotice(
						window.rslIeData.i18n.pleaseAddFilter ||
							'Please add at least one filter to narrow down the items.',
						'error'
					);
					return false;
				}

				return true;

			case 3:
				// At least one field must be selected
				if ( this.selectedFields.length === 0 ) {
					Utils.showNotice(
						window.rslIeData.i18n.pleaseSelectAtLeastOneField,
						'error'
					);
					return false;
				}
				return true;

			case 4:
				// At least one function must be assigned
				const hasFunction = Object.values( this.fieldFunctions ).some(
					( functions ) => {
						return (
							Array.isArray( functions ) && functions.length > 0
						);
					}
				);
				if ( ! hasFunction ) {
					Utils.showNotice(
						window.rslIeData.i18n.pleaseAssignFunction,
						'error'
					);
					return false;
				}
				return true;

			default:
				return true;
		}
	},

	/**
	 * Filter content types by search
	 */
	filterContentTypes( e ) {
		const searchTerm = jQuery( e.target ).val().toLowerCase();
		const $contentTypes = jQuery( '.rsl-ie-content-type' );
		const $nextStepBtn = jQuery(
			'.rsl-ie-updater-step-1 .rsl-ie-next-step'
		);
		let visibleCount = 0;

		$contentTypes.each( function () {
			const $card = jQuery( this );
			const text =
				$card.find( 'h3' ).text().toLowerCase() +
				$card.find( 'p' ).text().toLowerCase();

			if ( text.includes( searchTerm ) ) {
				$card.show();
				visibleCount++;
			} else {
				$card.hide();
			}
		} );

		// Show/hide no results message
		if ( visibleCount === 0 ) {
			jQuery( '.rsl-ie-no-results' ).show();
			// Disable Next button when no results found
			$nextStepBtn.prop( 'disabled', true );
		} else {
			jQuery( '.rsl-ie-no-results' ).hide();
			// Enable Next button when results are visible
			$nextStepBtn.prop( 'disabled', false );
		}
	},

	/**
	 * Handle content type change
	 */
	onContentTypeChange( e ) {
		const contentType = jQuery( e.target ).val();

		// Reset selections for new content type
		this.selectedFields = [];
		this.selectedFieldTypes = {};
		this.selectedTableName = '';
		this.currentTableColumns = [];
		this.fieldFunctions = {};
		this.selectedFilters = [];
		this.selectedTaxonomyFilters = [];
		this.filteredCount = null; // Reset filtered count when content type changes

		if ( this.isDatabaseTableType( contentType ) ) {
			jQuery( '.rsl-ie-table-selection-section' ).show();
		} else {
			jQuery( '.rsl-ie-table-selection-section' ).hide();
			jQuery( '.rsl-ie-table-info' ).hide();
			jQuery( '#rsl-ie-updater-table-name' ).val( '' );
			if ( window.rslIeExportModule ) {
				window.rslIeExportModule.currentTableColumns = [];
			}
		}
	},

	/**
	 * Load filters library for selected content type
	 */
	loadFiltersLibrary() {
		// Get selected content type
		const contentType = jQuery(
			'input[name="updater_content_type"]:checked'
		).val();
		if ( ! contentType ) {
			return;
		}

		// Clear existing filters
		jQuery( '#rsl-ie-updater-filters-list' ).empty();

		if ( this.isDatabaseTableType( contentType ) ) {
			jQuery( '.rsl-ie-table-selection-section' ).show();
			this.loadDatabaseTables();
		} else {
			jQuery( '.rsl-ie-table-selection-section' ).hide();
			jQuery( '.rsl-ie-table-info' ).hide();
		}

		// Immediately update Next button state (may disable it for types that require a filter)
		this.updateStep2NextButton();

		// Refresh count
		this.refreshCount( true );
	},

	/**
	 * Returns true for content types that must have at least one filter before proceeding
	 */
	requiresFilter( contentType = null ) {
		const ct =
			contentType ||
			jQuery( 'input[name="updater_content_type"]:checked' ).val();
		return ct === 'taxonomy';
	},

	/**
	 * Returns true when the required filter for the current content type is present and has a value
	 */
	hasRequiredFilter() {
		const collectedFilters = this.collectFilters();
		// For taxonomy: need at least one filter with field === 'taxonomy' and a non-empty value
		const taxonomyFilter = collectedFilters.filters.find(
			( f ) => f.field === 'taxonomy' && ( f.value || '' ).trim() !== ''
		);
		return !! taxonomyFilter;
	},

	/**
	 * Enable / disable the Step 2 "Next Step" button based on filter requirements
	 */
	updateStep2NextButton() {
		if ( this.currentStep !== 2 ) {
			return;
		}
		const $btn = jQuery(
			'.rsl-ie-updater-step-2 .rsl-ie-updater-next-step'
		);
		if ( $btn.length === 0 ) {
			return;
		}
		const needsFilter = this.requiresFilter();
		const hasFilter = needsFilter ? this.hasRequiredFilter() : true;
		$btn.prop( 'disabled', ! hasFilter );
		$btn.toggleClass( 'button-disabled', ! hasFilter );
	},

	/**
	 * Check if selected type is database table
	 */
	isDatabaseTableType( contentType = null ) {
		const selectedType =
			contentType ||
			jQuery( 'input[name="updater_content_type"]:checked' ).val();
		return selectedType === 'database_table';
	},

	/**
	 * Get selected database table name
	 */
	getSelectedTableName() {
		return (
			this.selectedTableName ||
			jQuery( '#rsl-ie-updater-table-name' ).val() ||
			''
		);
	},

	/**
	 * Build options payload for updater AJAX calls
	 */
	buildRequestOptions( contentType, extraOptions = {} ) {
		const options = { ...extraOptions };

		if ( this.isDatabaseTableType( contentType ) ) {
			const tableName = this.getSelectedTableName();
			if ( tableName ) {
				options.table_name = tableName;
			}
		}

		return options;
	},

	/**
	 * Load database table list for updater
	 */
	loadDatabaseTables() {
		const $dropdown = jQuery( '#rsl-ie-updater-table-name' );
		const $spinner = jQuery( '.rsl-ie-table-selector .spinner' );

		if ( ! $dropdown.length ) {
			return;
		}

		$dropdown.prop( 'disabled', true );
		$spinner.addClass( 'is-active' );

		Utils.ajax( 'rsl_ie_get_database_tables', {} )
			.then( ( response ) => {
				const tables = response.tables || response || [];

				$dropdown.empty();
				$dropdown.append(
					jQuery( '<option>' )
						.val( '' )
						.text( window.rslIeData.i18n.selectTable )
				);

				if ( ! Array.isArray( tables ) || tables.length === 0 ) {
					$dropdown.append(
						jQuery( '<option>' )
							.val( '' )
							.text( window.rslIeData.i18n.noTablesFound )
					);
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

				$dropdown.prop( 'disabled', false );
				$spinner.removeClass( 'is-active' );

				const currentTable = this.getSelectedTableName();
				if ( currentTable ) {
					$dropdown.val( currentTable );
					if ( $dropdown.val() ) {
						this.loadDatabaseTableColumns( currentTable );
					}
				}
			} )
			.catch( () => {
				$dropdown.empty();
				$dropdown.append(
					jQuery( '<option>' )
						.val( '' )
						.text( window.rslIeData.i18n.errorLoadingTables )
				);
				$dropdown.prop( 'disabled', true );
				$spinner.removeClass( 'is-active' );
			} );
	},

	/**
	 * Handle selected database table change
	 */
	onDatabaseTableChange( e ) {
		const tableName = jQuery( e.target ).val() || '';
		this.selectedTableName = tableName;

		if ( ! tableName ) {
			this.currentTableColumns = [];
			jQuery( '.rsl-ie-table-info' ).hide();
			jQuery( '.rsl-ie-columns-list' ).empty();
			jQuery( '.rsl-ie-table-row-count' ).text( '-' );
			jQuery( '.rsl-ie-table-column-count' ).text( '-' );
			if ( window.rslIeExportModule ) {
				window.rslIeExportModule.currentTableColumns = [];
			}
			this.refreshCount( false );
			return;
		}

		this.loadDatabaseTableColumns( tableName );
	},

	/**
	 * Load selected database table columns and stats
	 */
	loadDatabaseTableColumns( tableName ) {
		const $tableInfo = jQuery( '.rsl-ie-table-info' );
		const $columnsList = jQuery( '.rsl-ie-columns-list' );
		const $rowCount = jQuery( '.rsl-ie-table-row-count' );
		const $columnCount = jQuery( '.rsl-ie-table-column-count' );

		$tableInfo.show();
		$columnsList.html(
			`<p>${ window.rslIeData.i18n.loadingTableColumns }</p>`
		);

		Utils.ajax( 'rsl_ie_get_table_columns', { table_name: tableName } )
			.then( ( response ) => {
				const columns = response.columns || [];
				const rowCount = response.row_count || 0;

				this.currentTableColumns = columns;
				if ( window.rslIeExportModule ) {
					window.rslIeExportModule.currentTableColumns = columns;
				}

				$rowCount.text( rowCount );
				$columnCount.text( columns.length );

				$columnsList.empty();
				const $list = jQuery( '<ul>' ).addClass(
					'rsl-ie-column-type-list'
				);

				columns.forEach( ( col ) => {
					$list.append(
						jQuery( '<li>' ).html(
							`<strong>${ this.escapeHtml(
								col.name
							) }</strong> <span class="column-type">(${ this.escapeHtml(
								col.type
							) })</span>`
						)
					);
				} );

				$columnsList.append( $list );
				this.refreshCount( false );
			} )
			.catch( () => {
				this.currentTableColumns = [];
				if ( window.rslIeExportModule ) {
					window.rslIeExportModule.currentTableColumns = [];
				}
				$columnsList.html(
					`<p>${ window.rslIeData.i18n.errorLoadingColumns }</p>`
				);
				$rowCount.text( '-' );
				$columnCount.text( '-' );
			} );
	},

	/**
	 * Add new filter row
	 */
	addFilterRow() {
		const template = document.getElementById(
			'rsl-ie-updater-filter-row-template'
		);
		const clone = template.content.cloneNode( true );
		const contentType = jQuery(
			'input[name="updater_content_type"]:checked'
		).val();

		if (
			this.isDatabaseTableType( contentType ) &&
			! this.getSelectedTableName()
		) {
			Utils.showNotice(
				window.rslIeData.i18n.pleaseSelectTable,
				'warning'
			);
			jQuery( '#rsl-ie-updater-table-name' ).trigger( 'focus' );
			return;
		}

		// Populate field options based on content type
		const $fieldSelect = jQuery( clone ).find(
			'.rsl-ie-updater-filter-field'
		);

		// Use export module's getFilterFieldsByContentType if available (excludes Featured Image group)
		if (
			typeof window.rslIeExportModule !== 'undefined' &&
			window.rslIeExportModule.getFilterFieldsByContentType
		) {
			let fields =
				window.rslIeExportModule.getFilterFieldsByContentType(
					contentType
				);

			// Exclude certain fields from Content Updater filters for the 'user' content type
			if ( contentType === 'user' ) {
				const userExcludedFields = [
					'capabilities',
					'user_registered',
					'posts_count',
					'rich_editing',
					'admin_color',
					'locale',
				];
				fields = fields
					.map( ( group ) => ( {
						...group,
						options: group.options.filter(
							( opt ) =>
								! userExcludedFields.includes( opt.value )
						),
					} ) )
					.filter( ( group ) => group.options.length > 0 );
			}

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
		}

		jQuery( '#rsl-ie-updater-filters-list' ).append( clone );

		// Trigger count refresh (without spinner)
		Utils.debounce( () => this.refreshCount( false ), 500 )();
	},

	/**
	 * Remove filter row
	 */
	removeFilterRow( e ) {
		jQuery( e.target ).closest( '.rsl-ie-filter-row' ).remove();
		Utils.debounce( () => this.refreshCount( false ), 500 )();
	},

	/**
	 * Handle filter field change
	 */
	onFilterFieldChange( e ) {
		const $field = jQuery( e.target );
		const $row = $field.closest( '.rsl-ie-filter-row' );
		const $condition = $row.find( '.rsl-ie-updater-filter-condition' );
		const $conditionWrap = $condition.closest(
			'.rsl-ie-filter-condition-wrap'
		);
		const $valueWrap = $row.find( '.rsl-ie-filter-value-wrap' );

		const selectedOption = $field.find( 'option:selected' );
		const fieldType = selectedOption.data( 'type' ) || 'string';

		// Remove any previously injected custom UIs when switching field type
		$row.find( '.rsl-ie-updater-meta-key-wrap' ).remove();
		$row.find( '.rsl-ie-filter-row-inner' ).removeClass( 'has-meta-key' );
		$row.find( '.rsl-ie-taxonomy-filter-inputs' )
			.closest( '.rsl-ie-filter-value-wrap' )
			.find( '.rsl-ie-taxonomy-filter-inputs' )
			.remove();

		// Restore standard condition/value wrap if they were hidden by a previous custom type
		$conditionWrap.show();
		$valueWrap.show().html(
			`<label>${ window.rslIeData.i18n.value || 'Value' }</label>
			<input type="text" class="rsl-ie-updater-filter-value" name="updater_filter_value[]" placeholder="${
				window.rslIeData.i18n.enterFilterValue || ''
			}">`
		);

		// Special handling for post_type_selector — replicate export.js behaviour
		if ( fieldType === 'post_type_selector' ) {
			$conditionWrap.hide();
			$valueWrap
				.find( 'label' )
				.text( window.rslIeData.i18n.selectPostType || 'Post Type' );

			// Replace text input with a <select> populated from AJAX
			const $select = jQuery( '<select>' )
				.addClass(
					'rsl-ie-updater-filter-value rsl-ie-post-type-selector'
				)
				.attr( 'name', 'updater_filter_value[]' );

			Utils.ajax( 'rsl_ie_get_post_types', { include_hidden: true } )
				.then( ( postTypes ) => {
					$select.append(
						jQuery( '<option>' )
							.val( '' )
							.text(
								window.rslIeData.i18n
									.selectPostTypePlaceholder ||
									'— Select post type —'
							)
					);
					if ( postTypes && Array.isArray( postTypes ) ) {
						postTypes.forEach( ( pt ) => {
							$select.append(
								jQuery( '<option>' )
									.val( pt.name )
									.text( pt.label + ' (' + pt.name + ')' )
							);
						} );
					}
					$select.on( 'change', () => {
						Utils.debounce(
							() => this.refreshCount( false ),
							500
						)();
					} );
				} )
				.catch( () => {
					$select.append(
						jQuery( '<option>' )
							.val( '' )
							.text(
								window.rslIeData.i18n.errorLoadingPostTypes ||
									'Error loading post types'
							)
					);
				} );

			$valueWrap
				.find( '.rsl-ie-updater-filter-value' )
				.replaceWith( $select );
			return;
		}

		// Special handling for taxonomy_selector — replicate export.js behaviour
		if ( fieldType === 'taxonomy_selector' ) {
			$conditionWrap.hide();
			$valueWrap
				.find( 'label' )
				.text(
					window.rslIeData.i18n.selectTaxonomy || 'Select Taxonomy'
				);

			const $select = jQuery( '<select>' )
				.addClass(
					'rsl-ie-updater-filter-value rsl-ie-taxonomy-selector'
				)
				.attr( 'name', 'updater_filter_value[]' );

			Utils.ajax( 'rsl_ie_get_all_taxonomies', {} )
				.then( ( taxonomies ) => {
					$select.append(
						jQuery( '<option>' )
							.val( '' )
							.text(
								window.rslIeData.i18n
									.selectTaxonomyPlaceholder ||
									'— Select taxonomy —'
							)
					);
					if ( taxonomies && Array.isArray( taxonomies ) ) {
						taxonomies.forEach( ( taxonomy ) => {
							$select.append(
								jQuery( '<option>' )
									.val( taxonomy.name )
									.text(
										taxonomy.label +
											' (' +
											taxonomy.name +
											')'
									)
							);
						} );
					}
					$select.on( 'change', () => {
						Utils.debounce(
							() => this.refreshCount( false ),
							500
						)();
					} );
				} )
				.catch( () => {
					$select.append(
						jQuery( '<option>' )
							.val( '' )
							.text(
								window.rslIeData.i18n.errorLoadingTaxonomies ||
									'Error loading taxonomies'
							)
					);
				} );

			$valueWrap
				.find( '.rsl-ie-updater-filter-value' )
				.replaceWith( $select );
			return;
		}

		// Special handling for taxonomy_filter — replicate export.js behaviour
		if ( fieldType === 'taxonomy_filter' ) {
			$conditionWrap.hide();
			$valueWrap.html( `
				<div class="rsl-ie-taxonomy-filter-inputs">
					<div class="rsl-ie-input-group">
						<label>${ window.rslIeData.i18n.taxonomyName || 'Taxonomy Name' }</label>
						<input type="text" class="rsl-ie-taxonomy-name" placeholder="${
							window.rslIeData.i18n.taxonomyPlaceholderExamples
						}" />
					</div>
					<div class="rsl-ie-input-group">
						<label>${ window.rslIeData.i18n.condition || 'Condition' }</label>
						<select class="rsl-ie-taxonomy-condition rsl-ie-updater-filter-condition">
							<option value="in">${ window.rslIeData.i18n.hasTermsIn }</option>
							<option value="not_in">${ window.rslIeData.i18n.doesNotHaveTermsNotIn }</option>
							<option value="and">${ window.rslIeData.i18n.hasAllTermsAnd }</option>
						</select>
					</div>
					<div class="rsl-ie-input-group">
						<label>${ window.rslIeData.i18n.terms || 'Terms' }</label>
						<input type="text" class="rsl-ie-taxonomy-terms rsl-ie-updater-filter-value" placeholder="${
							window.rslIeData.i18n.enterTermSlugs
						}" />
						<small class="description">${ window.rslIeData.i18n.enterTermSlugs }</small>
					</div>
				</div>
			` );

			// Trigger count refresh when any taxonomy filter field changes
			$row.find(
				'.rsl-ie-taxonomy-name, .rsl-ie-taxonomy-condition, .rsl-ie-taxonomy-terms'
			).on( 'input change', () => {
				Utils.debounce( () => this.refreshCount( false ), 500 )();
			} );

			return;
		}

		// Handle custom_field type — show a text input for the actual meta key name
		if ( fieldType === 'custom_field' ) {
			const label = window.rslIeData.i18n.customFieldName || 'Meta Key';
			const placeholder =
				window.rslIeData.i18n.enterCustomFieldName ||
				'Enter meta key name…';
			const $metaKeyWrap = jQuery(
				`<div class="rsl-ie-updater-meta-key-wrap">
					<label>${ label }</label>
					<input type="text" class="rsl-ie-updater-custom-meta-key" placeholder="${ placeholder }" />
				</div>`
			);
			$row.find( '.rsl-ie-filter-row-inner' ).addClass( 'has-meta-key' );
			$row.find( '.rsl-ie-filter-field-wrap' ).after( $metaKeyWrap );
			// Refresh count when the meta key name changes
			$metaKeyWrap
				.find( '.rsl-ie-updater-custom-meta-key' )
				.on( 'input', () => {
					Utils.debounce( () => this.refreshCount( false ), 500 )();
				} );
		}

		// Populate condition dropdown based on field type
		$condition.empty();

		const conditions = this.getConditionsByFieldType( fieldType );
		conditions.forEach( ( condition ) => {
			$condition.append(
				jQuery( '<option>' )
					.val( condition.value )
					.text( condition.label )
			);
		} );

		// Clear value and update input type based on field type
		$row.find( '.rsl-ie-updater-filter-value' ).val( '' );
		this.updateValueInputType( $row );

		Utils.debounce( () => this.refreshCount( false ), 500 )();
	},

	/**
	 * Handle filter condition change
	 */
	onFilterConditionChange( e ) {
		const $condition = jQuery( e.target );
		const $row = $condition.closest( '.rsl-ie-filter-row' );
		const $valueWrap = $row.find( '.rsl-ie-filter-value-wrap' );
		const conditionValue = $condition.val();

		// Hide/show value input based on condition
		if (
			conditionValue === 'is_empty' ||
			conditionValue === 'is_not_empty'
		) {
			$valueWrap.hide();
		} else {
			$valueWrap.show();
		}

		// Update input type based on condition and field type
		this.updateValueInputType( $row );

		Utils.debounce( () => this.refreshCount( false ), 500 )();
	},

	/**
	 * Update value input type based on condition and field type
	 */
	updateValueInputType( $row ) {
		const $field = $row.find( '.rsl-ie-updater-filter-field' );
		const $condition = $row.find( '.rsl-ie-updater-filter-condition' );
		const $value = $row.find( '.rsl-ie-updater-filter-value' );

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
			$value.closest( '.rsl-ie-filter-value-wrap' ).hide();
			return;
		} else {
			$value.closest( '.rsl-ie-filter-value-wrap' ).show();
		}

		// For 'in' and 'not_in', always use text to allow comma-separated values
		if ( condition === 'in' || condition === 'not_in' ) {
			$value.attr( 'type', 'text' );
			$value.attr(
				'placeholder',
				window.rslIeData.i18n.enterValuesCommaSeparated ||
					'value1, value2, ...'
			);
			return;
		}

		// Set input type based on field type
		if ( fieldType === 'date' || fieldType === 'datetime' ) {
			$value.attr( 'type', 'date' );
			$value.attr( 'placeholder', '' );
		} else if ( fieldType === 'number' || fieldType === 'id' ) {
			$value.attr( 'type', 'number' );
			$value.attr(
				'placeholder',
				window.rslIeData.i18n.enterNumberPlaceholder || ''
			);
		} else {
			$value.attr( 'type', 'text' );
			$value.attr(
				'placeholder',
				window.rslIeData.i18n.enterFilterValue || ''
			);
		}
	},

	/**
	 * Get conditions by field type
	 */
	getConditionsByFieldType( fieldType ) {
		const stringConditions = [
			{ value: 'equals', label: window.rslIeData.i18n.equals },
			{ value: 'not_equals', label: window.rslIeData.i18n.notEquals },
			{
				value: 'in',
				label: window.rslIeData.i18n.inFilter || 'In (comma-separated)',
			},
			{
				value: 'not_in',
				label:
					window.rslIeData.i18n.notInFilter ||
					'Not In (comma-separated)',
			},
			{ value: 'contains', label: window.rslIeData.i18n.contains },
			{ value: 'not_contains', label: window.rslIeData.i18n.notContains },
			{ value: 'starts_with', label: window.rslIeData.i18n.startsWith },
			{ value: 'ends_with', label: window.rslIeData.i18n.endsWith },
			{ value: 'is_empty', label: window.rslIeData.i18n.isEmpty },
			{ value: 'is_not_empty', label: window.rslIeData.i18n.isNotEmpty },
		];

		const numberConditions = [
			{ value: 'equals', label: window.rslIeData.i18n.equals },
			{ value: 'not_equals', label: window.rslIeData.i18n.notEquals },
			{
				value: 'in',
				label: window.rslIeData.i18n.inFilter || 'In (comma-separated)',
			},
			{
				value: 'not_in',
				label:
					window.rslIeData.i18n.notInFilter ||
					'Not In (comma-separated)',
			},
			{ value: 'greater', label: window.rslIeData.i18n.greaterThan },
			{ value: 'less', label: window.rslIeData.i18n.lessThan },
			{
				value: 'equals_or_greater',
				label: window.rslIeData.i18n.greaterOrEqual,
			},
			{
				value: 'equals_or_less',
				label: window.rslIeData.i18n.lessOrEqual,
			},
			{ value: 'between', label: window.rslIeData.i18n.between },
		];

		const dateConditions = [
			{ value: 'equals', label: window.rslIeData.i18n.equals },
			{ value: 'not_equals', label: window.rslIeData.i18n.notEquals },
			{
				value: 'greater',
				label:
					window.rslIeData.i18n.newerThan ||
					window.rslIeData.i18n.greaterThan,
			},
			{
				value: 'equals_or_greater',
				label: window.rslIeData.i18n.greaterOrEqual,
			},
			{
				value: 'less',
				label:
					window.rslIeData.i18n.olderThan ||
					window.rslIeData.i18n.lessThan,
			},
			{
				value: 'equals_or_less',
				label: window.rslIeData.i18n.lessOrEqual,
			},
			{ value: 'is_empty', label: window.rslIeData.i18n.isEmpty },
			{ value: 'is_not_empty', label: window.rslIeData.i18n.isNotEmpty },
		];

		switch ( fieldType ) {
			case 'number':
			case 'id':
				return numberConditions;
			case 'date':
			case 'datetime':
				return dateConditions;
			case 'custom_field':
				return [
					{ value: 'equals', label: window.rslIeData.i18n.equals },
					{
						value: 'not_equals',
						label: window.rslIeData.i18n.notEquals,
					},
					{
						value: 'contains',
						label: window.rslIeData.i18n.contains,
					},
					{
						value: 'not_contains',
						label: window.rslIeData.i18n.notContains,
					},
					{
						value: 'starts_with',
						label: window.rslIeData.i18n.startsWith,
					},
					{
						value: 'ends_with',
						label: window.rslIeData.i18n.endsWith,
					},
					{
						value: 'greater',
						label: window.rslIeData.i18n.greaterThan,
					},
					{ value: 'less', label: window.rslIeData.i18n.lessThan },
					{
						value: 'equals_or_greater',
						label: window.rslIeData.i18n.greaterOrEqual,
					},
					{
						value: 'equals_or_less',
						label: window.rslIeData.i18n.lessOrEqual,
					},
					{
						value: 'in',
						label:
							window.rslIeData.i18n.inComma ||
							'In (comma-separated)',
					},
					{
						value: 'not_in',
						label:
							window.rslIeData.i18n.notInComma ||
							'Not In (comma-separated)',
					},
					{ value: 'is_empty', label: window.rslIeData.i18n.isEmpty },
					{
						value: 'is_not_empty',
						label: window.rslIeData.i18n.isNotEmpty,
					},
				];
			default:
				return stringConditions;
		}
	},

	/**
	 * Refresh item count
	 */
	refreshCount( showSpinner = true ) {
		const contentType = jQuery(
			'input[name="updater_content_type"]:checked'
		).val();
		if ( ! contentType ) {
			return;
		}

		if (
			this.isDatabaseTableType( contentType ) &&
			! this.getSelectedTableName()
		) {
			jQuery( '.rsl-ie-count-value' ).text( '-' );
			this.filteredCount = null;
			return;
		}

		const $countValue = jQuery( '.rsl-ie-count-value' );
		const $spinner = jQuery( '.rsl-ie-filter-summary-top .spinner' );

		if ( showSpinner ) {
			$spinner.addClass( 'is-active' );
		}

		// Collect filters
		const collectedFilters = this.collectFilters();

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_updater_get_count',
				nonce: rslIeData.nonce,
				content_type: contentType,
				filters: JSON.stringify( collectedFilters.filters ),
				taxonomy: JSON.stringify( collectedFilters.taxonomy ),
				options: this.buildRequestOptions( contentType ),
			},
			success: ( response ) => {
				$spinner.removeClass( 'is-active' );
				if ( response.success ) {
					$countValue.text( response.data.count );
					// Save the filtered count for later use
					this.filteredCount = response.data.count;
				} else {
					$countValue.text( window.rslIeData.i18n.error );
				}
				// Update Step 2 Next button state for types that require filters
				this.updateStep2NextButton();
			},
			error: () => {
				$spinner.removeClass( 'is-active' );
				$countValue.text( window.rslIeData.i18n.error );
			},
		} );
	},

	/**
	 * Collect filters from UI
	 *
	 * @return {{ filters: Array, taxonomy: Array }}
	 */
	collectFilters() {
		const filters = [];
		const taxonomyFilters = [];

		jQuery( '.rsl-ie-filter-row' ).each( function () {
			const $row = jQuery( this );
			const $fieldSelect = $row.find( '.rsl-ie-updater-filter-field' );
			let field = $fieldSelect.val();
			const fieldType = $fieldSelect
				.find( 'option:selected' )
				.data( 'type' );

			// Handle post_type_selector type — map _post_type → post_type filter
			if ( fieldType === 'post_type_selector' ) {
				const value = (
					$row.find( '.rsl-ie-updater-filter-value' ).val() || ''
				).trim();
				if ( value ) {
					filters.push( {
						field: 'post_type',
						condition: 'equals',
						value,
					} );
				}
				return;
			}

			// Handle taxonomy_selector type — map _taxonomy → taxonomy filter
			if ( fieldType === 'taxonomy_selector' ) {
				const value = (
					$row.find( '.rsl-ie-updater-filter-value' ).val() || ''
				).trim();
				if ( value ) {
					filters.push( {
						field: 'taxonomy',
						condition: 'equals',
						value,
					} );
				}
				return;
			}

			// Handle taxonomy_filter type — collect into taxonomy array
			if ( fieldType === 'taxonomy_filter' ) {
				const taxonomy = (
					$row.find( '.rsl-ie-taxonomy-name' ).val() || ''
				).trim();
				const condition = $row
					.find( '.rsl-ie-taxonomy-condition' )
					.val();
				const terms = (
					$row.find( '.rsl-ie-taxonomy-terms' ).val() || ''
				).trim();

				if ( taxonomy && condition && terms ) {
					taxonomyFilters.push( { taxonomy, condition, terms } );
				}
				return; // skip regular filter processing
			}

			const condition = $row
				.find( '.rsl-ie-updater-filter-condition' )
				.val();
			let value = $row.find( '.rsl-ie-updater-filter-value' ).val();

			// When the "Custom Field / Meta" placeholder is selected, use the
			// actual meta key that the user typed into the extra input.
			if ( field === '_custom_field' ) {
				field = (
					$row.find( '.rsl-ie-updater-custom-meta-key' ).val() || ''
				).trim();
				if ( ! field ) {
					return; // Skip this row — no meta key entered yet
				}
			}

			// Normalize date values to YYYY-MM-DD so the backend SQL comparison works
			// regardless of the locale format the datepicker uses (e.g. 03/27/2026 → 2026-03-27)
			if (
				( fieldType === 'date' || fieldType === 'datetime' ) &&
				value
			) {
				const parsed = new Date( value );
				if ( ! isNaN( parsed.getTime() ) ) {
					value = parsed.toISOString().slice( 0, 10 );
				}
			}

			if ( field && condition ) {
				filters.push( {
					field: field,
					condition: condition,
					value: value,
				} );
			}
		} );

		return { filters, taxonomy: taxonomyFilters };
	},

	/**
	 * Load fields library for selected content type
	 */
	loadFieldsLibrary() {
		// Get selected content type
		const contentType = jQuery(
			'input[name="updater_content_type"]:checked'
		).val();
		if ( ! contentType ) {
			return;
		}

		// Load static fields based on content type
		this.loadStaticFields( contentType );

		// Load dynamic fields for this content type (excluding taxonomies for Content Updater)
		const postType = this.getPostTypeForDynamicFields( contentType );
		if ( postType ) {
			// Skip taxonomies for Content Updater
			// this.loadTaxonomies( postType );
			if ( contentType !== 'media' ) {
				this.loadCustomFields( postType );
			}
			if ( contentType !== 'media' ) {
				this.checkAndLoadACF( postType );
			}
			if ( contentType !== 'user' && contentType !== 'media' ) {
				this.checkAndLoadYoast( postType );
			}
		}
	},

	/**
	 * Load static fields based on content type
	 */
	loadStaticFields( contentType ) {
		// Get field definitions from export module
		if (
			typeof window.rslIeExportModule === 'undefined' ||
			! window.rslIeExportModule.getFieldsByContentType
		) {
			return;
		}

		const fieldGroups =
			window.rslIeExportModule.getFieldsByContentType( contentType );

		// Find the container
		const $library = jQuery( '#rsl-ie-updater-fields-library' );
		if ( ! $library.length ) {
			return;
		}

		// Clear existing content
		$library.empty();

		// Add container for fields
		$library.append( '<div class="rsl-ie-fields-library-body"></div>' );
		const $body = $library.find( '.rsl-ie-fields-library-body' );

		// Groups / fields to exclude for specific content types
		const userExcludedGroups = [
			window.rslIeData.i18n.fieldGroupPreferences,
			window.rslIeData.i18n.fieldGroupStats,
		];
		const userExcludedFields = [ 'capabilities', 'user_login' ];
		const commentExcludedFields = [ 'post_title' ];

		// Render each field group as a category
		fieldGroups.forEach( ( group, index ) => {
			// Skip Custom Filters, selector groups, Taxonomy and Author categories for Content Updater
			if (
				group.label === 'Custom Filters' ||
				group.label === 'Post Type Selection' ||
				group.label === 'Taxonomy Selection' ||
				group.label === 'Taxonomy' ||
				group.label === 'Author'
			) {
				return;
			}

			// For user content type: skip excluded groups
			if (
				contentType === 'user' &&
				userExcludedGroups.includes( group.label )
			) {
				return;
			}

			// For user content type: filter out excluded fields within a group
			let renderGroup = group;
			if ( contentType === 'user' && userExcludedFields.length ) {
				renderGroup = {
					...group,
					options: group.options.filter(
						( opt ) => ! userExcludedFields.includes( opt.value )
					),
				};
				if ( renderGroup.options.length === 0 ) {
					return;
				}
			}

			// For comment content type: filter out excluded fields within a group
			if (
				contentType === 'comment' &&
				commentExcludedFields.length &&
				Array.isArray( renderGroup.options )
			) {
				renderGroup = {
					...renderGroup,
					options: renderGroup.options.filter(
						( opt ) => ! commentExcludedFields.includes( opt.value )
					),
				};
				if ( renderGroup.options.length === 0 ) {
					return;
				}
			}

			// For taxonomy content type: hide computed fields that should not be updated directly
			if (
				contentType === 'taxonomy' &&
				Array.isArray( renderGroup.options )
			) {
				renderGroup = {
					...renderGroup,
					options: renderGroup.options.filter(
						( opt ) => opt.value !== 'count'
					),
				};
				if ( renderGroup.options.length === 0 ) {
					return;
				}
			}

			const $category = this.createFieldCategory(
				renderGroup,
				index === 0
			);
			$body.append( $category );
		} );

		// Add placeholder categories for dynamic fields (excluding Taxonomies)
		$body.append( `
			<div class="rsl-ie-field-category rsl-ie-collapsed rsl-ie-custom-fields-category" style="display: none;">
				<h4 class="rsl-ie-field-category-title">
					<span class="dashicons dashicons-arrow-down-alt2 rsl-ie-category-toggle"></span>
					<span class="dashicons dashicons-admin-generic"></span>
					Custom Fields
					<button type="button" class="rsl-ie-add-all-fields" title="${ window.rslIeData.i18n.addAllFieldsTitle }">
						${ window.rslIeData.i18n.addAll }
					</button>
				</h4>
				<div class="rsl-ie-fields-grid rsl-ie-custom-fields-grid"></div>
			</div>
			<div class="rsl-ie-field-category rsl-ie-collapsed rsl-ie-acf-fields-category" style="display: none;">
				<h4 class="rsl-ie-field-category-title">
					<span class="dashicons dashicons-arrow-down-alt2 rsl-ie-category-toggle"></span>
					<span class="dashicons dashicons-admin-settings"></span>
					ACF Fields
					<button type="button" class="rsl-ie-add-all-fields" title="${ window.rslIeData.i18n.addAllFieldsTitle }">
						${ window.rslIeData.i18n.addAll }
					</button>
				</h4>
				<div class="rsl-ie-fields-grid rsl-ie-acf-fields-grid">
					<div class="rsl-ie-acf-loading"><span class="spinner is-active"></span><p>Loading ACF fields...</p></div>
				</div>
			</div>
			<div class="rsl-ie-field-category rsl-ie-collapsed rsl-ie-yoast-fields-category" style="display: none;">
				<h4 class="rsl-ie-field-category-title">
					<span class="dashicons dashicons-arrow-down-alt2 rsl-ie-category-toggle"></span>
					<span class="dashicons dashicons-chart-line"></span>
					Yoast SEO
					<button type="button" class="rsl-ie-add-all-fields" title="${ window.rslIeData.i18n.addAllFieldsTitle }">
						${ window.rslIeData.i18n.addAll }
					</button>
				</h4>
				<div class="rsl-ie-fields-grid rsl-ie-yoast-fields-grid">
					<div class="rsl-ie-yoast-loading"><span class="spinner is-active"></span><p>Loading Yoast SEO fields...</p></div>
				</div>
			</div>
		` );

		// Setup drag and drop
		this.setupFieldsDragAndDrop();

		// Setup category toggle
		this.setupCategoryToggle();
	},

	/**
	 * Create a field category element
	 */
	createFieldCategory( group, isOpen = false ) {
		const $category = jQuery( '<div>' ).addClass( 'rsl-ie-field-category' );
		if ( ! isOpen ) {
			$category.addClass( 'rsl-ie-collapsed' );
		}

		const $title = jQuery( '<h4>' ).addClass(
			'rsl-ie-field-category-title'
		).html( `
			<span class="dashicons dashicons-arrow-down-alt2 rsl-ie-category-toggle"></span>
			<span class="dashicons dashicons-admin-post"></span>
			${ this.escapeHtml( group.label ) }
			<button type="button" class="rsl-ie-add-all-fields" title="${
				window.rslIeData.i18n.addAllFieldsTitle
			}">
				${ window.rslIeData.i18n.addAll }
			</button>
		` );

		const $grid = jQuery( '<div>' ).addClass( 'rsl-ie-fields-grid' );

		// Add fields
		if ( group.options && Array.isArray( group.options ) ) {
			// ID fields that must not be editable in the Content Updater
			const idFields = [ 'ID', 'comment_ID', 'term_id' ];

			group.options.forEach( ( option ) => {
				// Skip special filter types
				if (
					option.type === 'custom_field' ||
					option.type === 'taxonomy_filter' ||
					option.type === 'post_type_selector' ||
					option.type === 'taxonomy_selector' ||
					option.type === 'table_selector'
				) {
					return;
				}

				// Skip ID fields — record IDs must not be updated
				if ( idFields.includes( option.value ) ) {
					return;
				}

				const $field = this.createFieldItem( option );
				$grid.append( $field );
			} );
		}

		$category.append( $title ).append( $grid );
		return $category;
	},

	/**
	 * Create a field item element
	 */
	createFieldItem( option ) {
		const iconClass = this.getFieldIcon( option.type );

		const $item = jQuery( '<div>' )
			.addClass( 'rsl-ie-field-item' )
			.attr( 'draggable', true )
			.attr( 'data-field', option.value )
			.attr( 'data-label', option.label )
			.attr( 'data-type', option.type || 'text' ).html( `
				<span class="rsl-ie-field-icon dashicons ${ iconClass }"></span>
				<span class="rsl-ie-field-label">${ this.escapeHtml( option.label ) }</span>
				<span class="rsl-ie-field-type">${ this.escapeHtml(
					option.type || 'text'
				) }</span>
			` );

		return $item;
	},

	/**
	 * Get field icon based on type
	 */
	getFieldIcon( type ) {
		const iconMap = {
			text: 'dashicons-text',
			number: 'dashicons-calculator',
			date: 'dashicons-calendar-alt',
			email: 'dashicons-email',
			url: 'dashicons-admin-links',
			textarea: 'dashicons-text',
			select: 'dashicons-menu',
			checkbox: 'dashicons-yes',
			radio: 'dashicons-marker',
			taxonomy: 'dashicons-category',
			meta: 'dashicons-admin-generic',
			acf: 'dashicons-admin-settings',
			yoast: 'dashicons-chart-line',
		};

		return iconMap[ type ] || 'dashicons-admin-generic';
	},

	/**
	 * Setup category toggle
	 */
	setupCategoryToggle() {
		jQuery( document ).off(
			'click.categoryToggle',
			'.rsl-ie-field-category-title'
		);
		jQuery( document ).on(
			'click.categoryToggle',
			'.rsl-ie-field-category-title',
			function ( e ) {
				// Don't toggle if clicking on "Add all" button
				if (
					jQuery( e.target ).closest( '.rsl-ie-add-all-fields' )
						.length
				) {
					return;
				}

				jQuery( this )
					.closest( '.rsl-ie-field-category' )
					.toggleClass( 'rsl-ie-collapsed' );
			}
		);
	},

	/**
	 * Load taxonomies for selected post type
	 */
	loadTaxonomies( postType ) {
		if ( typeof rslIeData === 'undefined' ) return;

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_taxonomies',
				nonce: rslIeData.nonce,
				post_type: postType,
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data.taxonomies &&
					response.data.taxonomies.length > 0
				) {
					this.renderTaxonomies( response.data.taxonomies );
					jQuery( '.rsl-ie-taxonomies-category' ).show();
				} else {
					jQuery( '.rsl-ie-taxonomies-category' ).hide();
				}
			},
			error: ( xhr, status, error ) => {},
		} );
	},

	/**
	 * Render taxonomies
	 */
	renderTaxonomies( taxonomies ) {
		const $grid = jQuery( '.rsl-ie-taxonomies-grid' );
		if ( ! $grid.length ) return;

		$grid.empty();

		taxonomies.forEach( ( taxonomy ) => {
			const $item = jQuery( '<div>' )
				.addClass( 'rsl-ie-field-item' )
				.attr( 'draggable', true )
				.attr( 'data-field', 'taxonomy_' + taxonomy.name )
				.attr( 'data-label', taxonomy.label )
				.attr( 'data-type', 'taxonomy' ).html( `
					<span class="rsl-ie-field-icon dashicons dashicons-category"></span>
					<span class="rsl-ie-field-label">${ this.escapeHtml( taxonomy.label ) }</span>
					<span class="rsl-ie-field-type">taxonomy</span>
				` );

			$grid.append( $item );
		} );
	},

	/**
	 * Get real post type for loading dynamic fields (ACF/Yoast/Custom fields)
	 *
	 * @param {string} contentType
	 * @return {string|null}
	 */
	getPostTypeForDynamicFields( contentType ) {
		if ( contentType === 'post' || contentType === 'page' ) {
			return contentType;
		}

		if ( contentType === 'custom_post_types' ) {
			const $ptSelector = jQuery( '.rsl-ie-post-type-selector' );
			return $ptSelector.length ? $ptSelector.val() || null : null;
		}

		const typeMap = {
			woo_product: 'product',
			woo_order: 'shop_order',
			woo_coupon: 'shop_coupon',
			media: 'attachment',
			user: 'user',
		};

		if ( typeMap[ contentType ] ) {
			return typeMap[ contentType ];
		}

		if ( contentType.startsWith( 'post_type_' ) ) {
			return contentType.replace( 'post_type_', '' );
		}

		return null;
	},

	/**
	 * Load custom fields for selected post type
	 */
	loadCustomFields( postType ) {
		if ( typeof rslIeData === 'undefined' ) return;

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_custom_fields',
				nonce: rslIeData.nonce,
				post_type: postType,
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data.fields &&
					response.data.fields.length > 0
				) {
					this.renderCustomFields( response.data.fields );
					jQuery( '.rsl-ie-custom-fields-category' ).show();
				} else {
					jQuery( '.rsl-ie-custom-fields-category' ).hide();
				}
			},
			error: ( xhr, status, error ) => {},
		} );
	},

	/**
	 * Render custom fields
	 */
	renderCustomFields( fields ) {
		const $grid = jQuery( '.rsl-ie-custom-fields-grid' );
		if ( ! $grid.length ) return;

		$grid.empty();

		fields.forEach( ( field ) => {
			const $item = jQuery( '<div>' )
				.addClass( 'rsl-ie-field-item' )
				.attr( 'draggable', true )
				.attr( 'data-field', 'meta_' + field.name )
				.attr( 'data-label', field.name )
				.attr( 'data-type', 'meta' ).html( `
					<span class="rsl-ie-field-icon dashicons dashicons-admin-generic"></span>
					<span class="rsl-ie-field-label">${ this.escapeHtml( field.name ) }</span>
					<span class="rsl-ie-field-type">meta</span>
				` );

			$grid.append( $item );
		} );
	},

	/**
	 * Check if ACF is active and load ACF fields
	 */
	checkAndLoadACF( postType ) {
		if ( typeof rslIeData === 'undefined' ) return;

		jQuery.ajax( {
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
					jQuery( '.rsl-ie-acf-fields-category' ).show();
				} else {
					jQuery( '.rsl-ie-acf-fields-category' ).hide();
				}
			},
			error: ( xhr, status, error ) => {},
		} );
	},

	/**
	 * Render ACF fields
	 */
	renderACFFields( fields ) {
		const $grid = jQuery( '.rsl-ie-acf-fields-grid' );
		if ( ! $grid.length ) return;

		$grid.empty();

		fields.forEach( ( field ) => {
			const $item = jQuery( '<div>' )
				.addClass( 'rsl-ie-field-item' )
				.attr( 'draggable', true )
				.attr( 'data-field', 'acf_' + field.name )
				.attr( 'data-label', field.label )
				.attr( 'data-type', 'acf' ).html( `
					<span class="rsl-ie-field-icon dashicons dashicons-admin-settings"></span>
					<span class="rsl-ie-field-label">${ this.escapeHtml( field.label ) }</span>
					<span class="rsl-ie-field-type">acf</span>
				` );

			$grid.append( $item );
		} );
	},

	/**
	 * Check if Yoast is active and load Yoast fields
	 */
	checkAndLoadYoast( postType ) {
		if ( typeof rslIeData === 'undefined' ) return;

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_yoast_fields',
				nonce: rslIeData.nonce,
				post_type: postType,
			},
			success: ( response ) => {
				if (
					response.success &&
					response.data.fields &&
					response.data.fields.length > 0
				) {
					this.renderYoastFields( response.data.fields );
					jQuery( '.rsl-ie-yoast-fields-category' ).show();
				} else {
					jQuery( '.rsl-ie-yoast-fields-category' ).hide();
				}
			},
			error: ( xhr, status, error ) => {},
		} );
	},

	/**
	 * Render Yoast fields
	 */
	renderYoastFields( fields ) {
		const $grid = jQuery( '.rsl-ie-yoast-fields-grid' );
		if ( ! $grid.length ) return;

		$grid.empty();

		fields.forEach( ( field ) => {
			const $item = jQuery( '<div>' )
				.addClass( 'rsl-ie-field-item' )
				.attr( 'draggable', true )
				.attr( 'data-field', 'yoast_' + field.name )
				.attr( 'data-label', field.label )
				.attr( 'data-type', 'yoast' ).html( `
					<span class="rsl-ie-field-icon dashicons dashicons-chart-line"></span>
					<span class="rsl-ie-field-label">${ this.escapeHtml( field.label ) }</span>
					<span class="rsl-ie-field-type">yoast</span>
				` );

			$grid.append( $item );
		} );
	},

	/**
	 * Escape HTML
	 */
	escapeHtml( text ) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;',
		};
		return String( text ).replace( /[&<>"']/g, ( m ) => map[ m ] );
	},

	/**
	 * Setup drag and drop for fields
	 */
	setupDragAndDrop() {
		// Will be implemented with field selection
	},

	/**
	 * Setup drag and drop handlers for field items.
	 *
	 * Uses event delegation on the library container so that items added
	 * asynchronously (ACF, Yoast, custom fields loaded via AJAX after the
	 * initial render) are automatically covered without needing to re-bind.
	 */
	setupFieldsDragAndDrop() {
		const $library = jQuery( '#rsl-ie-updater-fields-library' );
		const $dropzone = jQuery( '#rsl-ie-updater-dropzone' );

		// Remove any previously-attached delegated handlers to avoid duplicates
		// when the library is rebuilt (e.g. content-type change).
		$library.off( 'dragstart.rsl-ie-dnd click.rsl-ie-dnd' );

		// Delegated dragstart — fires for every .rsl-ie-field-item inside the library,
		// including ones added later by renderACFFields / renderYoastFields / renderCustomFields.
		$library.on( 'dragstart.rsl-ie-dnd', '.rsl-ie-field-item', ( e ) => {
			const $item = jQuery( e.currentTarget );
			const field = $item.data( 'field' );
			const type = $item.data( 'type' ) || 'text';
			e.originalEvent.dataTransfer.setData( 'field', field );
			e.originalEvent.dataTransfer.setData(
				'label',
				$item.find( '.rsl-ie-field-label' ).text()
			);
			e.originalEvent.dataTransfer.setData( 'type', type );
		} );

		// Delegated click — click-to-add also works for dynamic items.
		$library.on( 'click.rsl-ie-dnd', '.rsl-ie-field-item', ( e ) => {
			const $item = jQuery( e.currentTarget );
			this.addField(
				$item.data( 'field' ),
				$item.find( '.rsl-ie-field-label' ).text(),
				$item.data( 'type' ) || 'text'
			);
		} );

		// Dropzone handlers — also remove before re-adding to prevent duplicate fires.
		$dropzone.off(
			'dragover.rsl-ie-dnd dragleave.rsl-ie-dnd drop.rsl-ie-dnd'
		);

		$dropzone.on( 'dragover.rsl-ie-dnd', ( e ) => {
			e.preventDefault();
			$dropzone.addClass( 'rsl-ie-drag-over' );
		} );

		$dropzone.on( 'dragleave.rsl-ie-dnd', () => {
			$dropzone.removeClass( 'rsl-ie-drag-over' );
		} );

		$dropzone.on( 'drop.rsl-ie-dnd', ( e ) => {
			e.preventDefault();
			$dropzone.removeClass( 'rsl-ie-drag-over' );

			const field = e.originalEvent.dataTransfer.getData( 'field' );
			const label = e.originalEvent.dataTransfer.getData( 'label' );
			const type =
				e.originalEvent.dataTransfer.getData( 'type' ) || 'text';

			if ( field ) {
				this.addField( field, label, type );
			}
		} );
	},

	/**
	 * Add field to selected fields list
	 */
	addField( field, label, type = 'text' ) {
		// Check if already added
		if ( this.selectedFields.includes( field ) ) {
			const message = window.rslIeData.i18n.fieldAlreadySelected.replace(
				'%s',
				label
			);
			Utils.showNotice( message, 'warning' );
			return;
		}

		this.selectedFields.push( field );
		this.selectedFieldTypes[ field ] = String(
			type || 'text'
		).toLowerCase();

		const $list = jQuery( '#rsl-ie-updater-fields-list' );
		const $placeholder = jQuery( '.rsl-ie-updater-dropzone-placeholder' );

		$placeholder.hide();

		const fieldHtml = `
			<div class="rsl-ie-selected-field" data-field="${ field }" data-type="${ this.escapeHtml(
				String( type || 'text' ).toLowerCase()
			) }">
				<span class="rsl-ie-field-drag-handle dashicons dashicons-menu"></span>
				<span class="rsl-ie-field-name">${ label }</span>
				<button type="button" class="rsl-ie-remove-field" title="Remove">
					<span class="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		`;

		$list.append( fieldHtml );
		this.updateFieldCount();

		// Bind remove handler
		$list
			.find( '.rsl-ie-remove-field' )
			.last()
			.on( 'click', ( e ) => {
				this.removeField(
					jQuery( e.currentTarget )
						.closest( '.rsl-ie-selected-field' )
						.data( 'field' )
				);
			} );
	},

	/**
	 * Add all fields from a category
	 */
	addAllFieldsFromCategory( button ) {
		const $category = jQuery( button ).closest( '.rsl-ie-field-category' );
		if ( ! $category.length ) {
			return;
		}

		const $fieldItems = $category.find(
			'.rsl-ie-field-item:not([style*="display: none"])'
		);

		$fieldItems.each( ( index, item ) => {
			const $item = jQuery( item );
			const field = String( $item.data( 'field' ) || '' );
			const label = String( $item.data( 'label' ) || field );
			const type = String( $item.data( 'type' ) || 'text' );

			if ( field ) {
				this.addField( field, label, type );
			}
		} );
	},

	/**
	 * Remove field from selected fields
	 */
	removeField( field ) {
		const index = this.selectedFields.indexOf( field );
		if ( index > -1 ) {
			this.selectedFields.splice( index, 1 );
			delete this.selectedFieldTypes[ field ];
			delete this.fieldFunctions[ field ];
		}

		jQuery( `.rsl-ie-selected-field[data-field="${ field }"]` ).remove();
		this.updateFieldCount();

		if ( this.selectedFields.length === 0 ) {
			jQuery( '.rsl-ie-updater-dropzone-placeholder' ).show();
		}
	},

	/**
	 * Clear all selected fields
	 */
	clearAllFields() {
		if ( ! confirm( window.rslIeData.i18n.confirmClearFields ) ) {
			return;
		}

		this.selectedFields = [];
		this.selectedFieldTypes = {};
		this.fieldFunctions = {};
		jQuery( '#rsl-ie-updater-fields-list' ).empty();
		jQuery( '.rsl-ie-updater-dropzone-placeholder' ).show();
		this.updateFieldCount();
	},

	/**
	 * Get normalized field type key
	 */
	getFieldTypeKey( field ) {
		if ( this.selectedFieldTypes[ field ] ) {
			return String( this.selectedFieldTypes[ field ] ).toLowerCase();
		}

		const domType = jQuery(
			`.rsl-ie-selected-field[data-field="${ field }"]`
		).data( 'type' );
		return String( domType || 'text' ).toLowerCase();
	},

	/**
	 * Get display label for field type
	 */
	getFieldTypeLabel( field ) {
		return this.getFieldTypeKey( field ).replace( /_/g, ' ' ).toUpperCase();
	},

	/**
	 * Update field count display
	 */
	updateFieldCount() {
		jQuery( '.rsl-ie-fields-count' ).text( this.selectedFields.length );
	},

	/**
	 * Filter fields in library
	 */
	filterFields( e ) {
		const searchTerm = jQuery( e.target ).val().toLowerCase();
		const $fields = jQuery( '.rsl-ie-field-item' );

		$fields.each( function () {
			const $field = jQuery( this );
			const label = $field
				.find( '.rsl-ie-field-label' )
				.text()
				.toLowerCase();

			if ( label.includes( searchTerm ) ) {
				$field.show();
			} else {
				$field.hide();
			}
		} );
	},

	/**
	 * Load available functions from server
	 */
	loadAvailableFunctions() {
		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_get_functions',
				nonce: rslIeData.nonce,
			},
			success: ( response ) => {
				if ( response.success && response.data.functions ) {
					this.availableFunctions = response.data.functions;
					this.renderAvailableFunctions();
				}
			},
		} );
	},

	/**
	 * Build functions assignment table
	 */
	buildFunctionsTable() {
		const $tbody = jQuery( '#rsl-ie-updater-functions-tbody' );
		$tbody.empty();

		if ( this.selectedFields.length === 0 ) {
			$tbody.html( `
				<tr class="rsl-ie-no-fields-row">
					<td colspan="4" class="rsl-ie-no-fields-message">
						<span class="dashicons dashicons-info"></span>
						${ window.rslIeData.i18n.noFieldsSelected }
					</td>
				</tr>
			` );
			return;
		}

		this.selectedFields.forEach( ( field, index ) => {
			const functions = this.fieldFunctions[ field ] || [];
			const functionsCount = Array.isArray( functions )
				? functions.length
				: 0;
			const fieldLabel =
				jQuery(
					`.rsl-ie-selected-field[data-field="${ field }"] .rsl-ie-field-name`
				).text() || field;
			const fieldTypeLabel = this.getFieldTypeLabel( field );

			let functionsText = 'None';
			if ( functionsCount > 0 ) {
				functionsText = `${ functionsCount } function${
					functionsCount > 1 ? 's' : ''
				}`;
			}

			let html = `
				<tr data-field="${ field }">
					<td class="rsl-ie-field-name-col">
						<strong>${ this.escapeHtml( fieldLabel ) }</strong>
						<br><code>${ this.escapeHtml( field ) }</code>
					</td>
					<td class="rsl-ie-field-type-col">
						<span class="rsl-ie-field-type-badge">${ this.escapeHtml(
							fieldTypeLabel
						) }</span>
					</td>
					<td class="rsl-ie-functions-col">
						<span class="rsl-ie-functions-count-badge">${ functionsText }</span>
					</td>
					<td class="rsl-ie-actions-col">
						<button type="button" class="button button-small rsl-ie-assign-functions" data-field="${ field }">
							<span class="dashicons dashicons-admin-generic"></span>
							${ window.rslIeData.i18n.assignFunctions }
						</button>
					</td>
				</tr>
			`;

			$tbody.append( html );
		} );

		// Bind assign functions button
		$tbody.find( '.rsl-ie-assign-functions' ).on( 'click', ( e ) => {
			const field = jQuery( e.currentTarget ).data( 'field' );
			this.openFieldFunctionsModal( field );
		} );

		this.updateFunctionStats();
	},

	/**
	 * Open field functions modal
	 */
	openFieldFunctionsModal( fieldKey ) {
		const fieldLabel =
			jQuery(
				`.rsl-ie-selected-field[data-field="${ fieldKey }"] .rsl-ie-field-name`
			).text() || fieldKey;
		const fieldType = this.getFieldTypeLabel( fieldKey );

		this.currentEditingField = fieldKey;

		const $modal = jQuery( '#rsl-ie-updater-functions-modal' );
		if ( ! $modal.length ) {
			return;
		}

		// Set field info
		$modal.find( '.rsl-ie-current-field-label' ).text( fieldLabel );
		$modal.find( '.rsl-ie-current-field-type' ).text( fieldType );

		// Load current functions
		this.loadCurrentFunctions( fieldKey );

		// Load available functions
		this.renderAvailableFunctions();

		// Show modal
		$modal.css( 'display', 'flex' );
		jQuery( 'body' ).addClass( 'rsl-ie-modal-open' );

		// Initialize sortable if not already done
		this.initFunctionPipelineSortable();
	},

	/**
	 * Close field functions modal
	 */
	closeFieldFunctionsModal() {
		const $modal = jQuery( '#rsl-ie-updater-functions-modal' );
		if ( $modal.length ) {
			$modal.css( 'display', 'none' );
			jQuery( 'body' ).removeClass( 'rsl-ie-modal-open' );

			// Hide preview results
			const $previewResult = $modal.find(
				'#rsl-ie-updater-preview-result'
			);
			if ( $previewResult.length ) {
				$previewResult.css( 'display', 'none' );
			}

			// Clear preview input
			const $previewInput = $modal.find(
				'#rsl-ie-updater-preview-input'
			);
			if ( $previewInput.length ) {
				$previewInput.val( '' );
			}
		}
		this.currentEditingField = null;
	},

	/**
	 * Load current functions for field
	 */
	loadCurrentFunctions( fieldKey ) {
		const $container = jQuery( '#rsl-ie-updater-function-items' );
		if ( ! $container.length ) return;

		$container.empty();

		const functions = this.fieldFunctions[ fieldKey ] || [];
		const $noFunctionsEl = jQuery( '.rsl-ie-no-functions' );

		if ( ! Array.isArray( functions ) || functions.length === 0 ) {
			if ( $noFunctionsEl.length ) $noFunctionsEl.show();
			this.updateFunctionsCount( 0 );
			return;
		}

		if ( $noFunctionsEl.length ) $noFunctionsEl.hide();

		functions.forEach( ( funcId ) => {
			const func = this.availableFunctions.find(
				( f ) => f.id == funcId
			);
			if ( func ) {
				this.addFunctionToPipeline( func, false );
			}
		} );

		this.updateFunctionsCount( functions.length );
	},

	/**
	 * Add function to pipeline
	 */
	addFunctionToPipeline( func, updateArray = true ) {
		const $container = jQuery( '#rsl-ie-updater-function-items' );
		if ( ! $container.length ) return;

		const $item = jQuery( '<div>' )
			.addClass( 'rsl-ie-function-item' )
			.attr( 'data-function-id', func.id ).html( `
				<span class="rsl-ie-function-handle dashicons dashicons-menu"></span>
				<div class="rsl-ie-function-info">
					<strong class="rsl-ie-function-name">${ this.escapeHtml( func.name ) }</strong>
					<span class="rsl-ie-function-desc">${ this.escapeHtml(
						func.description || ''
					) }</span>
				</div>
				<div class="rsl-ie-function-actions">
					<button type="button" class="button-small rsl-ie-remove-function" data-function-id="${
						func.id
					}">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			` );

		// Remove function event
		$item.find( '.rsl-ie-remove-function' ).on( 'click', () => {
			$item.remove();
			this.updatePipelineFunctions();
			this.updateFunctionsCount();
			this.toggleNoFunctionsMessage();
		} );

		$container.append( $item );

		if ( updateArray ) {
			this.updatePipelineFunctions();
			this.updateFunctionsCount();
		}

		this.toggleNoFunctionsMessage();
	},

	/**
	 * Update pipeline functions array
	 */
	updatePipelineFunctions() {
		if ( ! this.currentEditingField ) return;

		const functionIds = [];
		jQuery( '.rsl-ie-function-item' ).each( function () {
			functionIds.push( jQuery( this ).data( 'function-id' ) );
		} );

		this.fieldFunctions[ this.currentEditingField ] = functionIds;
	},

	/**
	 * Update functions count
	 */
	updateFunctionsCount( count = null ) {
		const $countEl = jQuery( '.rsl-ie-functions-count' );
		if ( ! $countEl.length ) return;

		if ( count === null ) {
			count = jQuery( '.rsl-ie-function-item' ).length;
		}

		$countEl.text( `(${ count })` );
	},

	/**
	 * Toggle no functions message
	 */
	toggleNoFunctionsMessage() {
		const $noFunctionsEl = jQuery( '.rsl-ie-no-functions' );
		const count = jQuery( '.rsl-ie-function-item' ).length;

		if ( count === 0 ) {
			$noFunctionsEl.show();
		} else {
			$noFunctionsEl.hide();
		}
	},

	/**
	 * Render available functions
	 */
	renderAvailableFunctions() {
		const $list = jQuery( '#rsl-ie-updater-functions-list' );
		if ( ! $list.length ) return;

		$list.empty();

		if ( this.availableFunctions.length === 0 ) {
			$list.html( `
				<div class="rsl-ie-no-functions-available">
					<span class="dashicons dashicons-info"></span>
					<p>${ window.rslIeData.i18n.noFunctionsAvailable }</p>
				</div>
			` );
			return;
		}

		this.availableFunctions.forEach( ( func ) => {
			const $funcItem = jQuery( '<div>' )
				.addClass( 'rsl-ie-function-list-item' )
				.attr( 'data-function-id', func.id )
				.attr( 'data-category', func.category || 'custom' ).html( `
					<div class="rsl-ie-function-list-info">
						<strong class="rsl-ie-function-list-name">${ this.escapeHtml(
							func.name
						) }</strong>
						<span class="rsl-ie-function-list-desc">${ this.escapeHtml(
							func.description || ''
						) }</span>
					</div>
					<button type="button" class="button button-small rsl-ie-add-function-btn" data-function-id="${
						func.id
					}">
						<span class="dashicons dashicons-plus-alt"></span>
						${ window.rslIeData.i18n.add }
					</button>
				` );

			// Add function event
			$funcItem.find( '.rsl-ie-add-function-btn' ).on( 'click', () => {
				this.addFunctionToPipeline( func, true );
			} );

			$list.append( $funcItem );
		} );
	},

	/**
	 * Initialize function pipeline sortable
	 */
	initFunctionPipelineSortable() {
		const $container = jQuery( '#rsl-ie-updater-function-items' );
		if ( ! $container.length ) return;

		// Check if already initialized
		if ( $container.data( 'ui-sortable' ) ) {
			return;
		}

		$container.sortable( {
			handle: '.rsl-ie-function-handle',
			placeholder: 'rsl-ie-function-item-placeholder',
			axis: 'y',
			update: () => {
				this.updatePipelineFunctions();
			},
		} );
	},

	/**
	 * Save field functions
	 */
	saveFieldFunctions() {
		this.updatePipelineFunctions();
		this.closeFieldFunctionsModal();
		this.buildFunctionsTable(); // Rebuild table to show updated function counts
	},

	/**
	 * Handle function selection change
	 */
	onFunctionChange( e ) {
		const $select = jQuery( e.target );
		const field = $select.data( 'field' );
		const functionId = $select.val();

		this.fieldFunctions[ field ] = functionId;
		this.updateFunctionStats();
	},

	/**
	 * Update function statistics
	 */
	updateFunctionStats() {
		const total = this.selectedFields.length;
		let assigned = 0;
		let noFunction = 0;

		Object.values( this.fieldFunctions ).forEach( ( functions ) => {
			if ( Array.isArray( functions ) && functions.length > 0 ) {
				assigned++;
			} else {
				noFunction++;
			}
		} );

		noFunction = total - assigned;

		jQuery( '.rsl-ie-total-fields-stat' ).text( total );
		jQuery( '.rsl-ie-functions-assigned-stat' ).text( assigned );
		jQuery( '.rsl-ie-no-function-stat' ).text( noFunction );
	},

	/**
	 * Apply function to all fields
	 */
	applyFunctionToAll() {
		// Show a dialog to select the function
		const functionId = prompt(
			'Enter function ID to apply to all fields (or leave empty for none):'
		);

		if ( functionId === null ) {
			return; // Cancelled
		}

		const finalId = functionId.trim() || 'none';

		this.selectedFields.forEach( ( field ) => {
			this.fieldFunctions[ field ] = finalId;
			jQuery(
				`.rsl-ie-field-function-select[data-field="${ field }"]`
			).val( finalId );
		} );

		this.updateFunctionStats();
		Utils.showNotice( 'success', 'Function applied to all fields' );
	},

	/**
	 * Clear all function assignments
	 */
	clearAllFunctions() {
		if ( ! confirm( window.rslIeData.i18n.confirmClearFunctions ) ) {
			return;
		}

		this.selectedFields.forEach( ( field ) => {
			this.fieldFunctions[ field ] = [];
			const $row = jQuery( `tr[data-field="${ field }"]` );
			$row.find( '.rsl-ie-updater-field-functions' ).empty();
		} );

		this.buildFunctionsTable();
		Utils.showNotice(
			window.rslIeData.i18n.functionAssignmentsCleared,
			'success'
		);
	},

	/**
	 * Test function with sample input
	 */
	testFunction( e ) {
		const field = jQuery( e.currentTarget ).data( 'field' );
		const functionId = this.fieldFunctions[ field ];

		if ( ! functionId || functionId === 'none' ) {
			Utils.showNotice(
				window.rslIeData.i18n.noFunctionAssigned,
				'warning'
			);
			return;
		}

		// Show preview popup (simplified for now)
		const testValue = prompt( window.rslIeData.i18n.enterTestValue );
		if ( testValue === null ) {
			return;
		}

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_functions_execute',
				nonce: rslIeData.nonce,
				function_id: functionId,
				input_value: testValue,
			},
			success: ( response ) => {
				if ( response.success ) {
					const outputText =
						window.rslIeData.i18n.functionOutput.replace(
							'%s',
							response.data.output
						);
					alert( outputText );
				} else {
					Utils.showNotice(
						response.data.message ||
							window.rslIeData.i18n.functionTestFailed,
						'error'
					);
				}
			},
		} );
	},

	/**
	 * Prepare update summary
	 */
	prepareUpdateSummary() {
		const contentType = jQuery(
			'input[name="updater_content_type"]:checked'
		).val();
		const contentTypeLabel = jQuery(
			'input[name="updater_content_type"]:checked'
		)
			.closest( '.rsl-ie-content-type' )
			.find( 'h3' )
			.text();

		// Update summary display
		jQuery( '.rsl-ie-content-type-summary' ).text( contentTypeLabel );
		jQuery( '.rsl-ie-fields-summary' ).text( this.selectedFields.length );

		const functionsCount = Object.values( this.fieldFunctions ).filter(
			( fn ) => fn && fn !== 'none'
		).length;
		jQuery( '.rsl-ie-functions-summary' ).text( functionsCount );

		// Get count of items (with filters applied)
		// If we already have a filtered count from Step 2, we can use it
		// Otherwise, make a new request
		if ( this.filteredCount !== undefined && this.filteredCount !== null ) {
			jQuery( '.rsl-ie-total-items-summary' ).text( this.filteredCount );
		} else {
			this.getItemCount();
		}
	},

	/**
	 * Get count of items to update
	 */
	getItemCount() {
		const contentType = jQuery(
			'input[name="updater_content_type"]:checked'
		).val();
		const $countValue = jQuery( '.rsl-ie-total-items-summary' );

		if (
			this.isDatabaseTableType( contentType ) &&
			! this.getSelectedTableName()
		) {
			$countValue.text( '-' );
			return;
		}

		$countValue.html(
			'<span class="spinner" style="float:none;margin:0;"></span>'
		);

		// Include filters in the count request
		const filters = this.selectedFilters || [];
		const taxonomy = this.selectedTaxonomyFilters || [];

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_updater_get_count',
				nonce: rslIeData.nonce,
				content_type: contentType,
				filters: JSON.stringify( filters ),
				taxonomy: JSON.stringify( taxonomy ),
				options: this.buildRequestOptions( contentType ),
			},
			success: ( response ) => {
				if ( response.success ) {
					$countValue.text( response.data.count );
					// Save the filtered count
					this.filteredCount = response.data.count;
				} else {
					$countValue.text( window.rslIeData.i18n.error );
				}
			},
			error: () => {
				$countValue.text( window.rslIeData.i18n.error );
			},
		} );
	},

	/**
	 * Start update process
	 */
	startUpdate() {
		const contentType = jQuery(
			'input[name="updater_content_type"]:checked'
		).val();
		const itemsPerIteration =
			parseInt( jQuery( '#rsl-ie-updater-items-per-iteration' ).val() ) ||
			10;

		// Validate fields and functions
		if ( ! this.selectedFields || this.selectedFields.length === 0 ) {
			Utils.showNotice(
				window.rslIeData.i18n.noFieldsSelectedError,
				'error'
			);
			return;
		}

		if (
			! this.fieldFunctions ||
			Object.keys( this.fieldFunctions ).length === 0
		) {
			Utils.showNotice(
				window.rslIeData.i18n.noFunctionsAssigned,
				'error'
			);
			return;
		}

		// Show backup warning modal before starting update
		BackupWarningModal.show(
			() => {
				// User confirmed backup - proceed with update
				this.executeUpdate( contentType, itemsPerIteration );
			},
			() => {
				// User cancelled - do nothing
			}
		);
	},

	/**
	 * Execute update process (after backup confirmation)
	 */
	executeUpdate( contentType, itemsPerIteration ) {
		// Prepare field functions array (indexed by field position)
		const fieldFunctionsArray = this.selectedFields.map(
			( field ) => this.fieldFunctions[ field ] || []
		);
		const options = this.buildRequestOptions( contentType, {
			items_per_iteration: itemsPerIteration,
		} );

		// Show progress section
		jQuery( '#rsl-ie-updater-config' ).hide();
		jQuery( '#rsl-ie-updater-progress' ).show();
		jQuery( '#rsl-ie-updater-prev-from-step-4' ).hide();

		// Start update job
		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_updater_start',
				nonce: rslIeData.nonce,
				content_type: contentType,
				fields: JSON.stringify( this.selectedFields ),
				field_functions: JSON.stringify( fieldFunctionsArray ),
				filters: JSON.stringify( this.selectedFilters || [] ),
				taxonomy: JSON.stringify( this.selectedTaxonomyFilters || [] ),
				options: JSON.stringify( options ),
			},
			success: ( response ) => {
				if ( response.success ) {
					this.jobId = response.data.job_id;
					Utils.showNotice(
						window.rslIeData.i18n.updateStarted,
						'success'
					);

					// Start processing
					this.startProgressTracking();
					this.processNextBatch();
				} else {
					Utils.showNotice(
						response.data.message ||
							window.rslIeData.i18n.failedStartUpdate,
						'error'
					);
					this.showResults( 'error' );
				}
			},
			error: () => {
				Utils.showNotice(
					window.rslIeData.i18n.failedStartUpdate,
					'error'
				);
				this.showResults( 'error' );
			},
		} );
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
	 * Update progress display
	 */
	updateProgress() {
		return jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_updater_get_progress',
				nonce: rslIeData.nonce,
				job_id: this.jobId,
			},
			success: ( response ) => {
				if ( response.success ) {
					const progress = response.data;

					// Update progress bar
					jQuery( '.rsl-ie-progress-bar-fill' ).css(
						'width',
						progress.percentage + '%'
					);
					jQuery( '.rsl-ie-progress-percentage' ).text(
						progress.percentage + '%'
					);

					// Update stats
					jQuery( '.rsl-ie-processed-count' ).text(
						progress.processed_items
					);
					jQuery( '.rsl-ie-total-count' ).text(
						progress.total_items
					);
					jQuery( '.rsl-ie-updated-count' ).text(
						progress.updated_items
					);
					jQuery( '.rsl-ie-skipped-count' ).text(
						progress.skipped_items
					);
					jQuery( '.rsl-ie-errors-count' ).text(
						progress.error_items
					);

					// Update status
					const processingText = window.rslIeData.i18n.processingItems
						.replace( '%1$s', progress.processed_items )
						.replace( '%2$s', progress.total_items );
					jQuery( '.rsl-ie-status-text' ).text( processingText );

					// Check if completed
					if (
						progress.status === 'completed' ||
						progress.status === 'failed' ||
						progress.status === 'cancelled'
					) {
						this.stopProgressTracking();
						this.showResults( progress.status, progress );
					}
				}
			},
		} );
	},

	/**
	 * Process next batch
	 */
	processNextBatch() {
		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_updater_process_batch',
				nonce: rslIeData.nonce,
				job_id: this.jobId,
			},
			success: ( response ) => {
				if ( response.success ) {
					if ( ! response.data.completed ) {
						// Process next batch
						setTimeout( () => this.processNextBatch(), 500 );
					}
				} else {
				}
			},
			error: ( xhr ) => {},
		} );
	},

	/**
	 * Stop progress tracking
	 */
	stopProgressTracking() {
		if ( this.progressInterval ) {
			clearInterval( this.progressInterval );
			this.progressInterval = null;
		}
	},

	/**
	 * Cancel update
	 */
	cancelUpdate() {
		if ( ! confirm( window.rslIeData.i18n.confirmCancelUpdate ) ) {
			return;
		}

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: {
				action: 'rsl_ie_updater_cancel',
				nonce: rslIeData.nonce,
				job_id: this.jobId,
			},
			success: ( response ) => {
				if ( response.success ) {
					this.stopProgressTracking();
					Utils.showNotice(
						window.rslIeData.i18n.updateCancelled,
						'info'
					);
					this.showResults( 'cancelled' );
				}
			},
		} );
	},

	/**
	 * Show results
	 */
	showResults( status, progress = {} ) {
		jQuery( '#rsl-ie-updater-progress' ).hide();
		jQuery( '#rsl-ie-updater-results' ).show();

		// Update final stats
		jQuery( '.rsl-ie-final-processed' ).text(
			progress.processed_items || 0
		);
		jQuery( '.rsl-ie-final-updated' ).text( progress.updated_items || 0 );
		jQuery( '.rsl-ie-final-skipped' ).text( progress.skipped_items || 0 );
		jQuery( '.rsl-ie-final-errors' ).text( progress.error_items || 0 );

		// Update icon based on status
		const $icon = jQuery( '.rsl-ie-results-header .dashicons' );
		if ( status === 'completed' ) {
			$icon
				.removeClass( 'dashicons-warning' )
				.addClass( 'dashicons-yes-alt rsl-ie-success-icon' );
		} else {
			$icon
				.removeClass( 'dashicons-yes-alt' )
				.addClass( 'dashicons-warning' );
		}
	},

	/**
	 * Start new update
	 */
	startNewUpdate() {
		window.location.reload();
	},

	/**
	 * Filter functions by search term
	 */
	filterFunctions( searchTerm ) {
		const term = searchTerm.toLowerCase();
		jQuery( '.rsl-ie-function-list-item' ).each( function () {
			const $item = jQuery( this );
			const name = $item
				.find( '.rsl-ie-function-list-name' )
				.text()
				.toLowerCase();
			const desc = $item
				.find( '.rsl-ie-function-list-desc' )
				.text()
				.toLowerCase();

			if ( name.includes( term ) || desc.includes( term ) ) {
				$item.show();
			} else {
				$item.hide();
			}
		} );
	},

	/**
	 * Filter functions by category
	 */
	filterFunctionsByCategory( category ) {
		if ( category === 'all' ) {
			jQuery( '.rsl-ie-function-list-item' ).show();
		} else {
			jQuery( '.rsl-ie-function-list-item' ).each( function () {
				const $item = jQuery( this );
				const itemCategory = $item.data( 'category' );

				if ( itemCategory === category ) {
					$item.show();
				} else {
					$item.hide();
				}
			} );
		}
	},

	/**
	 * Test function pipeline
	 */
	testFunctionPipeline() {
		const $input = jQuery( '#rsl-ie-updater-preview-input' );
		const testValue = $input.val();

		if ( ! testValue ) {
			this.showNotice(
				window.rslIeData.i18n.pleaseEnterTestValue,
				'warning'
			);
			return;
		}

		const functionIds = [];
		jQuery( '.rsl-ie-function-item' ).each( function () {
			functionIds.push( jQuery( this ).data( 'function-id' ) );
		} );

		if ( functionIds.length === 0 ) {
			this.showNotice(
				window.rslIeData.i18n.noFunctionsToTest,
				'warning'
			);
			return;
		}

		// Check if rslIeData is available
		if ( typeof rslIeData === 'undefined' ) {
			this.showNotice(
				window.rslIeData.i18n.configurationError,
				'error'
			);
			return;
		}

		const requestData = {
			action: 'rsl_ie_test_function_pipeline',
			nonce: rslIeData.nonce,
			functions: functionIds,
			value: testValue,
		};

		jQuery.ajax( {
			url: rslIeData.ajaxUrl,
			method: 'POST',
			data: requestData,
			success: ( response ) => {
				if ( response.success ) {
					this.renderPipelinePreview(
						testValue,
						response.data.steps
					);
				} else {
					this.showNotice(
						response.data.message ||
							window.rslIeData.i18n.testFailed,
						'error'
					);
				}
			},
			error: ( xhr, status, error ) => {
				this.showNotice(
					window.rslIeData.i18n.errorTestingPipeline,
					'error'
				);
			},
		} );
	},

	/**
	 * Render pipeline preview
	 */
	renderPipelinePreview( initialValue, steps ) {
		const $container = jQuery( '#rsl-ie-updater-preview-result' );
		if ( ! $container.length ) return;

		const $stepsContainer = $container.find( '.rsl-ie-preview-steps' );
		$stepsContainer.empty();

		// Initial value
		$stepsContainer.append(
			this.createPreviewStep(
				0,
				window.rslIeData.i18n.input,
				initialValue
			)
		);

		// Each function step
		if ( steps && steps.length > 0 ) {
			steps.forEach( ( step, index ) => {
				$stepsContainer.append(
					this.createPreviewStep(
						index + 1,
						step.function_name,
						step.output,
						step.error
					)
				);
			} );
		}

		$container.show();
	},

	/**
	 * Create preview step element
	 */
	createPreviewStep( number, name, value, error = false ) {
		return jQuery( '<div>' ).addClass( 'rsl-ie-preview-step' ).html( `
				<div class="rsl-ie-step-number">${ number }</div>
				<div class="rsl-ie-step-info">
					<span class="rsl-ie-function-name">${ this.escapeHtml( name ) }</span>
					<span class="rsl-ie-step-value ${ error ? 'error' : '' }">
						${ this.escapeHtml( error ? `Error: ${ value }` : value ) }
					</span>
				</div>
			` );
	},

	/**
	 * Show notice (simple console logging to avoid z-index issues with modal)
	 */
	showNotice( message, type = 'info' ) {
		// Log to console
	},

	/**
	 * Create new function
	 */
	createNewFunction() {
		// Open Functions management page in new tab
		if ( typeof rslIeData !== 'undefined' && rslIeData.functionsUrl ) {
			window.open( rslIeData.functionsUrl, '_blank' );
		} else {
			// Fallback - go to admin page
			window.open(
				'/wp-admin/admin.php?page=rsl-ie-functions',
				'_blank'
			);
		}
	},
};

export default ContentUpdater;
