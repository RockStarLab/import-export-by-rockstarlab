import TabProgress from '../utils/tab-progress';
/**
 * Export Module
 *
 * Handles the export wizard functionality
 */

import Utils from './utils';
import ExportStep3 from './export-step-3';

import { __, sprintf } from '@wordpress/i18n';

/**
 * The "Language" filter option when Polylang is active (filters by its language taxonomy).
 *
 * @return {Array} Zero or one filter option.
 */
function languageFilterOptions() {
	const languages = window.rslIeData?.languages || {};
	return Object.keys( languages ).length
		? [
				{
					value: '_language_filter',
					label: __( 'Language (Polylang)', 'import-export-by-rockstarlab' ),
					type: 'language_filter',
				},
		  ]
		: [];
}

/**
 * Translated label of a customer billing or shipping field ("billing_city" → "City").
 *
 * @param {string} value Field key.
 * @return {string} Label.
 */
function customerAddressLabel( value ) {
	const labels = {
		first_name: __( 'First Name', 'import-export-by-rockstarlab' ),
		last_name: __( 'Last Name', 'import-export-by-rockstarlab' ),
		company: __( 'Company', 'import-export-by-rockstarlab' ),
		email: __( 'Email', 'import-export-by-rockstarlab' ),
		phone: __( 'Phone', 'import-export-by-rockstarlab' ),
		address_1: __( 'Address 1', 'import-export-by-rockstarlab' ),
		address_2: __( 'Address 2', 'import-export-by-rockstarlab' ),
		city: __( 'City', 'import-export-by-rockstarlab' ),
		state: __( 'State', 'import-export-by-rockstarlab' ),
		postcode: __( 'Postcode', 'import-export-by-rockstarlab' ),
		country: __( 'Country', 'import-export-by-rockstarlab' ),
	};
	return labels[ value.replace( /^(billing|shipping)_/, '' ) ] || value;
}
const ExportModule = {
	currentStep: 1,
	totalSteps: 5,
	jobId: null,
	progressInterval: null,
	exportStartTime: null,
	step3Instance: null,
	urlTypesLoaded: false,
	mediaLibraryPrefillIds: [],
	postListPrefillIds: [],
	taxonomyTermPrefillIds: [],

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
		this.step3Instance = new ExportStep3();

		if ( resumeJobId ) {
			// Resume job - go directly to step 5 and start processing
			this.jobId = parseInt( resumeJobId );

			// Show step 5 immediately (don't hide first, let showStep handle it)
			this.showStep( 5 );

			// Remove the anti-flash class once we're on the correct step.
			jQuery( '#rsl-ie-export' ).removeClass( 'rsl-ie-resuming-job' );

			// Get initial progress first, then start tracking and processing
			this.updateProgress().then( ( status ) => {
				if ( [ 'completed', 'failed' ].includes( status ) ) return;
				// Start progress tracking and batch processing
				this.startProgressTracking();
				this.processNextBatch();
			} );
		} else if ( ! this.applyUrlPrefill( urlParams ) ) {
			this.showStep( 1 );
		}
	},

	/**
	 * Apply pre-filled export settings from safe admin URL parameters.
	 *
	 * @param {URLSearchParams} urlParams Current URL parameters.
	 * @return {boolean} Whether a prefill flow was started.
	 */
	applyUrlPrefill( urlParams ) {
		const prefillType = urlParams.get( 'rsl_ie_prefill' );

		if ( prefillType === 'media_library' ) {
			const ids = this.getValidPrefillIds( urlParams.get( 'media_ids' ) );

			if ( ! ids.length ) {
				return false;
			}

			this.applyMediaLibraryPrefill( ids );
			return true;
		}

		if ( prefillType === 'post_list' ) {
			const ids = this.getValidPrefillIds( urlParams.get( 'post_ids' ) );
			const postType = String( urlParams.get( 'post_type' ) || '' )
				.trim()
				.replace( /[^a-zA-Z0-9_-]/g, '' );

			if ( ! postType ) {
				return false;
			}

			if ( ! ids.length ) {
				this.applyPostTypeFilterPrefill( postType );
				return true;
			}

			this.applyPostListPrefill( postType, ids );
			return true;
		}

		if ( prefillType === 'taxonomy_terms' ) {
			const ids = this.getValidPrefillIds( urlParams.get( 'term_ids' ) );
			const taxonomy = String( urlParams.get( 'taxonomy' ) || '' )
				.trim()
				.replace( /[^a-zA-Z0-9_-]/g, '' );

			if ( ! taxonomy ) {
				return false;
			}

			if ( ! ids.length ) {
				this.applyTaxonomyFilterPrefill( taxonomy );
				return true;
			}

			this.applyTaxonomyTermsPrefill( taxonomy, ids );
			return true;
		}

		return false;
	},

	/**
	 * Sanitize comma-separated IDs from the Media Library bulk export shortcut.
	 *
	 * @param {string|null} rawIds Raw comma-separated IDs.
	 * @return {Array<string>} Valid positive integer IDs as strings.
	 */
	getValidPrefillIds( rawIds ) {
		return String( rawIds || '' )
			.split( ',' )
			.map( ( id ) => id.trim() )
			.filter( ( id ) => /^\d+$/.test( id ) && parseInt( id, 10 ) > 0 )
			.filter( ( id, index, ids ) => ids.indexOf( id ) === index );
	},

	/**
	 * Jump from Media Library selection into a media export with an ID filter.
	 *
	 * @param {Array<string>} ids Selected attachment IDs.
	 */
	applyMediaLibraryPrefill( ids ) {
		const $mediaContentType = jQuery(
			'input[name="content_type"][value="media"]'
		);

		if ( ! $mediaContentType.length ) {
			this.showStep( 1 );
			return;
		}

		this.mediaLibraryPrefillIds = ids;

		$mediaContentType.prop( 'checked', true ).trigger( 'change' );
		this.showStep( 2 );

		jQuery( '#rsl-ie-filters-list' ).empty();
		this.addFilterRow();

		const $row = jQuery( '#rsl-ie-filters-list .rsl-ie-filter-row' ).last();
		const $field = $row.find( '.rsl-ie-filter-field' );
		const $condition = $row.find( '.rsl-ie-filter-condition' );

		$field.val( 'ID' ).trigger( 'change' );
		$condition.val( 'in' ).trigger( 'change' );
		$row.find( '.rsl-ie-filter-value' )
			.val( ids.join( ',' ) )
			.trigger( 'change' );

		this.refreshCount( false ).finally( () => {
			this.showStep( 3 );
		} );
	},

	/**
	 * Jump from a post list selection into an export with an ID filter.
	 *
	 * @param {string} postType Selected WordPress post type.
	 * @param {Array<string>} ids Selected post IDs.
	 */
	applyPostListPrefill( postType, ids ) {
		const contentType = this.getContentTypeForPostTypePrefill( postType );
		const $contentType = jQuery(
			`input[name="content_type"][value="${ contentType }"]`
		);

		if ( ! $contentType.length ) {
			this.showStep( 1 );
			return;
		}

		this.postListPrefillIds = ids;

		$contentType.prop( 'checked', true ).trigger( 'change' );
		this.showStep( 2 );

		jQuery( '#rsl-ie-filters-list' ).empty();

		if ( contentType === 'custom_post_types' ) {
			this.addFilterRow();
			const $postTypeRow = jQuery(
				'#rsl-ie-filters-list .rsl-ie-filter-row'
			).last();
			$postTypeRow
				.find( '.rsl-ie-filter-field' )
				.val( '_post_type' )
				.trigger( 'change' );

			this.waitForFilterValueOption( $postTypeRow, postType )
				.then( ( $select ) => {
					$select.val( postType ).trigger( 'change' );
					this.addPostListIdFilterAndShowStep3( ids );
				} )
				.catch( () => {
					this.showStep( 2 );
				} );
			return;
		}

		this.addPostListIdFilterAndShowStep3( ids );
	},

	/**
	 * Open Step 2 with the custom post type selector ready when no IDs were sent.
	 *
	 * @param {string} postType Selected WordPress post type.
	 */
	applyPostTypeFilterPrefill( postType ) {
		const contentType = this.getContentTypeForPostTypePrefill( postType );
		const $contentType = jQuery(
			`input[name="content_type"][value="${ contentType }"]`
		);

		if ( ! $contentType.length ) {
			this.showStep( 1 );
			return;
		}

		$contentType.prop( 'checked', true ).trigger( 'change' );
		this.showStep( 2 );

		if ( contentType !== 'custom_post_types' ) {
			return;
		}

		jQuery( '#rsl-ie-filters-list' ).empty();
		this.addFilterRow( '_post_type' );

		const $postTypeRow = jQuery(
			'#rsl-ie-filters-list .rsl-ie-filter-row'
		).last();

		this.waitForFilterValueOption( $postTypeRow, postType )
			.then( ( $select ) => {
				$select.val( postType ).trigger( 'change' );
			} )
			.catch( () => {
				this.updateStep2NextButton();
			} );
	},

	/**
	 * Map WordPress post type to export wizard content type.
	 *
	 * @param {string} postType WordPress post type.
	 * @return {string} Export wizard content type.
	 */
	getContentTypeForPostTypePrefill( postType ) {
		const map = {
			post: 'post',
			page: 'page',
			product: 'woo_product',
			shop_order: 'woo_order',
			shop_coupon: 'woo_coupon',
		};

		return map[ postType ] || 'custom_post_types';
	},

	/**
	 * Add selected post IDs filter and move to field selection.
	 *
	 * @param {Array<string>} ids Selected post IDs.
	 */
	addPostListIdFilterAndShowStep3( ids ) {
		this.addFilterRow();

		const $row = jQuery( '#rsl-ie-filters-list .rsl-ie-filter-row' ).last();
		const $field = $row.find( '.rsl-ie-filter-field' );
		const $condition = $row.find( '.rsl-ie-filter-condition' );

		$field.val( 'ID' ).trigger( 'change' );
		$condition.val( 'in' ).trigger( 'change' );
		$row.find( '.rsl-ie-filter-value' )
			.val( ids.join( ',' ) )
			.trigger( 'change' );

		this.refreshCount( false ).finally( () => {
			this.showStep( 3 );
		} );
	},

	/**
	 * Jump from a taxonomy term list selection into term export with filters.
	 *
	 * @param {string} taxonomy Selected taxonomy.
	 * @param {Array<string>} ids Selected term IDs.
	 */
	applyTaxonomyTermsPrefill( taxonomy, ids ) {
		const $contentType = jQuery(
			'input[name="content_type"][value="taxonomy"]'
		);

		if ( ! $contentType.length ) {
			this.showStep( 1 );
			return;
		}

		this.taxonomyTermPrefillIds = ids;

		$contentType.prop( 'checked', true ).trigger( 'change' );
		this.showStep( 2 );

		jQuery( '#rsl-ie-filters-list' ).empty();
		this.addFilterRow();

		const $taxonomyRow = jQuery(
			'#rsl-ie-filters-list .rsl-ie-filter-row'
		).last();
		$taxonomyRow
			.find( '.rsl-ie-filter-field' )
			.val( '_taxonomy' )
			.trigger( 'change' );

		this.waitForFilterValueOption( $taxonomyRow, taxonomy )
			.then( ( $select ) => {
				$select.val( taxonomy ).trigger( 'change' );
				this.addTaxonomyTermIdFilterAndShowStep3( ids );
			} )
			.catch( () => {
				this.showStep( 2 );
			} );
	},

	/**
	 * Open Step 2 with the taxonomy selector ready when no term IDs were sent.
	 *
	 * @param {string} taxonomy Selected taxonomy.
	 */
	applyTaxonomyFilterPrefill( taxonomy ) {
		const $contentType = jQuery(
			'input[name="content_type"][value="taxonomy"]'
		);

		if ( ! $contentType.length ) {
			this.showStep( 1 );
			return;
		}

		$contentType.prop( 'checked', true ).trigger( 'change' );
		this.showStep( 2 );

		jQuery( '#rsl-ie-filters-list' ).empty();
		this.addFilterRow( '_taxonomy' );

		const $taxonomyRow = jQuery(
			'#rsl-ie-filters-list .rsl-ie-filter-row'
		).last();

		this.waitForFilterValueOption( $taxonomyRow, taxonomy )
			.then( ( $select ) => {
				$select.val( taxonomy ).trigger( 'change' );
			} )
			.catch( () => {
				this.updateStep2NextButton();
			} );
	},

	/**
	 * Wait until an AJAX-backed filter select contains a specific option.
	 *
	 * @param {jQuery} $row Filter row.
	 * @param {string} value Expected option value.
	 * @param {number} timeout Timeout in milliseconds.
	 * @return {Promise<jQuery>} Resolved select element.
	 */
	waitForFilterValueOption( $row, value, timeout = 5000 ) {
		const started = Date.now();

		return new Promise( ( resolve, reject ) => {
			const check = () => {
				const $select = $row.find( '.rsl-ie-filter-value' );

				if (
					$select.length &&
					$select.is( 'select' ) &&
					$select.find( `option[value="${ value }"]` ).length
				) {
					resolve( $select );
					return;
				}

				if ( Date.now() - started >= timeout ) {
					reject();
					return;
				}

				window.setTimeout( check, 50 );
			};

			check();
		} );
	},

	/**
	 * Add selected term IDs filter and move to field selection.
	 *
	 * @param {Array<string>} ids Selected term IDs.
	 */
	addTaxonomyTermIdFilterAndShowStep3( ids ) {
		this.addFilterRow();

		const $row = jQuery( '#rsl-ie-filters-list .rsl-ie-filter-row' ).last();
		const $field = $row.find( '.rsl-ie-filter-field' );
		const $condition = $row.find( '.rsl-ie-filter-condition' );

		$field.val( 'term_id' ).trigger( 'change' );
		$condition.val( 'in' ).trigger( 'change' );
		$row.find( '.rsl-ie-filter-value' )
			.val( ids.join( ',' ) )
			.trigger( 'change' );

		this.refreshCount( false ).finally( () => {
			this.showStep( 3 );
		} );
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $wizard = jQuery( '#rsl-ie-export' );

		// Content type filter/search
		$wizard.on( 'input', '#rsl-ie-content-type-search', ( e ) =>
			this.filterContentTypes( e )
		);

		// Step navigation
		$wizard.on( 'click', '.rsl-ie-next-step', () => this.nextStep() );
		$wizard.on( 'click', '.rsl-ie-prev-step', () => this.prevStep() );

		// Content type
		$wizard.on( 'change', 'input[name="content_type"]', ( e ) =>
			this.onContentTypeChange( e )
		);

		// Filters
		$wizard.on(
			'change',
			'.rsl-ie-export-filters input, .rsl-ie-export-filters select',
			() => this.queueCountRefresh()
		);
		$wizard.on( 'click', '.rsl-ie-step-2 .rsl-ie-refresh-count', () =>
			this.refreshCount( true )
		);
		$wizard.on( 'change', '.rsl-ie-url-export-type-checkbox', () => {
			this.updateUrlExportGroupStates();
			this.refreshCount( false );
		} );
		$wizard.on( 'change', '.rsl-ie-url-export-bulk-checkbox', ( e ) => {
			this.toggleUrlExportBulkCategory( e );
		} );

		// Field selection
		$wizard.on( 'click', '.rsl-ie-select-all-fields', () =>
			this.selectAllFields( true )
		);
		$wizard.on( 'click', '.rsl-ie-deselect-all-fields', () =>
			this.selectAllFields( false )
		);
		$wizard.on( 'click', '.rsl-ie-select-common-fields', () =>
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
		$wizard.on( 'click', '.rsl-ie-start-export', ( e ) =>
			this.startExport( e )
		);
		$wizard.on( 'click', '.rsl-ie-cancel-export', () =>
			this.cancelExport()
		);
		$wizard.on( 'click', '.rsl-ie-download-file', ( e ) =>
			this.downloadFile( e )
		);
		$wizard.on( 'click', '.rsl-ie-new-export', () => this.newExport() );

		// Form entries: choosing a form loads its columns.
		$wizard.on( 'change', '#rsl-ie-form-entry-form', () =>
			this.onFormEntryFormChange()
		);

		// Dynamic Filters
		$wizard.on( 'click', '.rsl-ie-add-filter', () => this.addFilterRow() );
		$wizard.on( 'click', '.rsl-ie-remove-filter', ( e ) =>
			this.removeFilterRow( e )
		);
		$wizard.on( 'change', '.rsl-ie-filter-field', ( e ) =>
			this.onFilterFieldChange( e )
		);

		// Dynamic filter value changes - auto refresh count when filter is complete
		$wizard.on( 'change', '.rsl-ie-filter-condition', ( e ) => {
			// Taxonomy operators share the same terms input and refresh handler.
			if ( jQuery( e.target ).is( '.rsl-ie-taxonomy-condition' ) ) {
				return;
			}

			const $row = jQuery( e.target ).closest( '.rsl-ie-filter-row' );
			const $value = $row.find( '.rsl-ie-filter-value' );

			// Clear the value when condition changes
			if ( $value.length ) {
				$value.val( '' );
			}

			// Update input type based on condition
			this.updateValueInputType( $row );

			if ( this.isFilterRowComplete( $row ) ) {
				this.queueCountRefresh();
			}
		} );
		$wizard.on( 'input', '.rsl-ie-filter-value', ( e ) => {
			const $row = jQuery( e.target ).closest( '.rsl-ie-filter-row' );
			if ( this.isFilterRowComplete( $row ) ) {
				this.queueCountRefresh();
			}
		} );
		$wizard.on( 'change', '.rsl-ie-filter-value', ( e ) => {
			const $row = jQuery( e.target ).closest( '.rsl-ie-filter-row' );
			if ( this.isFilterRowComplete( $row ) ) {
				this.queueCountRefresh();
			}
		} );
	},

	/**
	 * Show specific step
	 */
	showStep( step ) {
		const $wizard = jQuery( '#rsl-ie-export' );
		const previousStep = this.currentStep;

		$wizard.find( '.rsl-ie-step' ).removeClass( 'active' );
		$wizard.find( `.rsl-ie-step-${ step }` ).addClass( 'active' );

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

		if ( step === 4 ) {
			this.ensureCustomExportFileName();
			// The variation option only applies to WooCommerce products.
			jQuery( '.rsl-ie-variation-rows-option' ).toggle(
				'woo_product' ===
					jQuery( 'input[name="content_type"]:checked' ).val()
			);
			// The order item option only applies to WooCommerce orders.
			jQuery( '.rsl-ie-line-item-rows-option' ).toggle(
				'woo_order' ===
					jQuery( 'input[name="content_type"]:checked' ).val()
			);
		}

		if ( step === 1 ) {
			// Hide database table selection and info when returning to step 1
			jQuery( '.rsl-ie-table-selection-section' ).hide();
			jQuery( '.rsl-ie-table-info' ).hide();
			// Reset count only when going back to step 1
			this.resetCount();
		} else if ( step === 2 ) {
			// Check if database_table type is selected
			const contentType = jQuery(
				'input[name="content_type"]:checked'
			).val();
			if ( contentType === 'database_table' ) {
				jQuery( '.rsl-ie-table-selection-section' ).show();
				// Only load database tables if coming from step 1 or if table not selected
				const $tableSelect = jQuery( '#rsl-ie-table-name' );
				if ( previousStep === 1 || ! $tableSelect.val() ) {
					this.loadDatabaseTables();
				}
			} else {
				jQuery( '.rsl-ie-table-selection-section' ).hide();
			}

			if ( contentType === 'urls' ) {
				jQuery( '.rsl-ie-url-export-section' ).show();
				jQuery( '.rsl-ie-custom-filters-section' ).hide();
				this.loadUrlExportTypes();
			} else {
				jQuery( '.rsl-ie-url-export-section' ).hide();
			}

			if ( contentType === 'form_entries' ) {
				jQuery( '.rsl-ie-form-entry-section' ).show();
				this.loadFormEntryForms();
			} else {
				jQuery( '.rsl-ie-form-entry-section' ).hide();
			}

			this.ensureDefaultSpecificFilter( contentType );
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

			if ( nextStep === 3 && this.isUrlsExport() ) {
				nextStep = 4;
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

			if ( prevStep === 3 && this.isUrlsExport() ) {
				prevStep = 2;
			}

			// Hide table selection when going back to step 1
			if ( prevStep === 1 ) {
				jQuery( '.rsl-ie-table-selection-section' ).hide();
				jQuery( '.rsl-ie-table-info' ).hide();
			}

			this.showStep( prevStep );
		}
	},

	/**
	 * Check if current content type should skip filters step
	 */
	shouldSkipFilters() {
		const contentType = jQuery(
			'input[name="content_type"]:checked'
		).val();

		// Content types that don't need filtering (go straight from step 1 to step 3)
		const noFilterTypes = [];

		return noFilterTypes.includes( contentType );
	},

	isUrlsExport() {
		return jQuery( 'input[name="content_type"]:checked' ).val() === 'urls';
	},

	/**
	 * Open the selector filter immediately for content types that need a specific subtype.
	 *
	 * @param {string} contentType Selected export content type.
	 */
	ensureDefaultSpecificFilter( contentType ) {
		if ( jQuery( '#rsl-ie-filters-list .rsl-ie-filter-row' ).length ) {
			return;
		}

		if ( contentType === 'custom_post_types' ) {
			this.addFilterRow( '_post_type' );
		}

		if ( contentType === 'taxonomy' ) {
			this.addFilterRow( '_taxonomy' );
		}
	},

	/**
	 * Handle content type change
	 */
	onContentTypeChange( e ) {
		const contentType = jQuery( e.target ).val();

		// Clear existing filters
		jQuery( '#rsl-ie-filters-list' ).empty();

		// Show/hide table selection section
		if ( contentType === 'database_table' ) {
			jQuery( '.rsl-ie-table-selection-section' ).show();
			jQuery( '.rsl-ie-custom-filters-section' ).show();
		} else {
			jQuery( '.rsl-ie-table-selection-section' ).hide();
		}

		if ( contentType === 'urls' ) {
			jQuery( '.rsl-ie-url-export-section' ).show();
			jQuery( '.rsl-ie-custom-filters-section' ).hide();
			this.loadUrlExportTypes();
		} else {
			jQuery( '.rsl-ie-url-export-section' ).hide();
		}

		// Show/hide custom filters section
		const filterableTypes = [
			'post',
			'page',
			'media',
			'menu',
			'user',
			'comment',
			'custom_post_types',
			'taxonomy',
			'woo_product',
			'woo_order',
			'woo_coupon',
			'woo_attribute',
			'woo_review',
			'woo_refund',
			'woo_customer',
			'database_table',
			'form_entries',
		];
		if ( contentType === 'form_entries' ) {
			jQuery( '.rsl-ie-form-entry-section' ).show();
		} else {
			jQuery( '.rsl-ie-form-entry-section' ).hide();
		}
		if (
			contentType !== 'urls' &&
			filterableTypes.includes( contentType )
		) {
			jQuery( '.rsl-ie-custom-filters-section' ).show();
		} else {
			jQuery( '.rsl-ie-custom-filters-section' ).hide();
		}

		// Update field groups visibility if needed
		if ( contentType === 'media' ) {
			jQuery( '.rsl-ie-post-field-group' ).hide();
			jQuery( '.rsl-ie-media-field-group' ).show();
		} else {
			jQuery( '.rsl-ie-post-field-group' ).show();
			jQuery( '.rsl-ie-media-field-group' ).hide();
		}

		// Refresh count (without spinner)
		this.refreshCount( false );
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
	 * Refresh the count once filters stop changing: one request for a burst of changes
	 * instead of one per change (a new debounced function per call never debounced).
	 */
	queueCountRefresh() {
		clearTimeout( this.countRefreshTimer );
		this.countRefreshTimer = setTimeout(
			() => this.refreshCount( false ),
			500
		);
	},

	/**
	 * Refresh item count
	 */
	async refreshCount( showSpinner = true ) {
		// Filter changes fire several count requests; only the newest may update the number,
		// or a slower answer for older filters overwrites the current count.
		this.countRequest = ( this.countRequest || 0 ) + 1;
		const request = this.countRequest;
		const isCurrent = () => request === this.countRequest;
		const $count = jQuery( '.rsl-ie-step-2 .rsl-ie-count-value' );
		const $spinner = jQuery( '.rsl-ie-step-2 .rsl-ie-item-count .spinner' );
		const $refreshBtn = jQuery( '.rsl-ie-step-2 .rsl-ie-refresh-count' );

		if ( showSpinner ) {
			$spinner.addClass( 'is-active' );
		}
		$refreshBtn.addClass( 'is-refreshing' );

		let contentType = jQuery( 'input[name="content_type"]:checked' ).val();

		try {
			// Prepare options based on content type
			let options = {};

			if ( contentType === 'urls' ) {
				options = {
					content_types: this.getSelectedUrlContentTypes(),
				};
			} else if ( contentType === 'database_table' ) {
				// For database tables, get table name from dropdown
				const $tableDropdown = jQuery( '#rsl-ie-table-name' );
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

			this.applySelectionPrefillToOptions( options, contentType );

			// Nothing to count before the post type / table is chosen: the request was refused
			// with a 403 ("premium content type … not available") in the console every time.
			const hasPostTypeFilter = ( options.filters || [] ).some(
				( filter ) => filter.field === 'post_type' && filter.value
			);
			// Taxonomy terms showed the category count (89) while Next stayed disabled
			// until a taxonomy was chosen.
			const hasTaxonomyFilter = ( options.filters || [] ).some(
				( filter ) => filter.field === 'taxonomy' && filter.value
			);
			if (
				( contentType === 'custom_post_types' &&
					! options.post_type &&
					! hasPostTypeFilter ) ||
				( contentType === 'taxonomy' && ! hasTaxonomyFilter ) ||
				( contentType === 'database_table' && ! options.table_name )
			) {
				$count.text( '-' );
				this.updateStep2NextButton();
				return;
			}

			if ( contentType === 'form_entries' ) {
				const selection = this.getFormEntrySelection();
				if ( ! selection ) {
					$count.text( '-' );
					this.updateStep2NextButton();
					return;
				}
				Object.assign( options, selection );
				jQuery( '.rsl-ie-form-entry-error' ).hide().text( '' );
			}

			const response = await Utils.ajax( 'rsl_ie_export_get_count', {
				export_type: contentType,
				options: options,
			} );
			if ( ! isCurrent() ) {
				return;
			}

			$count.text( response.count || 0 );
			// If database table is selected, also update table row count in the info panel
			if ( contentType === 'database_table' ) {
				const $tableRowCount = jQuery( '.rsl-ie-table-row-count' );
				if ( $tableRowCount.length ) {
					$tableRowCount.text( response.count || 0 );
				}
			}
			// Update next button state based on count
			this.updateStep2NextButton();
		} catch ( error ) {
			if ( ! isCurrent() ) {
				return;
			}
			$count.text( '-' );
			if ( contentType === 'form_entries' ) {
				jQuery( '.rsl-ie-form-entry-error' )
					.text( ( error && error.message ) || '' )
					.toggle( !! ( error && error.message ) );
			}
			if ( contentType === 'database_table' ) {
				const $tableRowCount = jQuery( '.rsl-ie-table-row-count' );
				if ( $tableRowCount.length ) {
					$tableRowCount.text( '-' );
				}
			}

			// Disable next button on error
			this.updateStep2NextButton();
		} finally {
			if ( isCurrent() ) {
				$spinner.removeClass( 'is-active' );
				$refreshBtn.removeClass( 'is-refreshing' );
			}
		}
	},

	/**
	 * Reset count display
	 */
	resetCount() {
		const $count = jQuery( '.rsl-ie-step-2 .rsl-ie-count-value' );
		const $spinner = jQuery( '.rsl-ie-step-2 .rsl-ie-item-count .spinner' );
		const $refreshBtn = jQuery( '.rsl-ie-step-2 .rsl-ie-refresh-count' );

		$count.text( '-' );
		$spinner.removeClass( 'is-active' );
		$refreshBtn.removeClass( 'is-refreshing' );

		// Disable next button when count is reset
		this.updateStep2NextButton();
	},

	async loadUrlExportTypes() {
		const $container = jQuery( '.rsl-ie-url-export-types' );

		if ( this.urlTypesLoaded || ! $container.length ) {
			return;
		}

		$container.html(
			'<p class="description">' +
				__(
					'Loading URL content types...',
					'import-export-by-rockstarlab'
				) +
				'</p>'
		);

		try {
			const response = await Utils.ajax(
				'rsl_ie_export_get_url_types',
				{}
			);
			const types = response.types || [];

			if ( ! types.length ) {
				$container.html(
					'<p class="description">' +
						__(
							'No public URL content types found.',
							'import-export-by-rockstarlab'
						) +
						'</p>'
				);
				this.urlTypesLoaded = true;
				this.updateStep2NextButton();
				return;
			}

			$container.html( this.renderUrlExportTypeGroups( types ) );
			this.updateUrlExportGroupStates();
			this.urlTypesLoaded = true;
			this.refreshCount( false );
		} catch ( error ) {
			$container.html(
				'<p class="description">' +
					__(
						'Could not load URL content types.',
						'import-export-by-rockstarlab'
					) +
					'</p>'
			);
			this.updateStep2NextButton();
		}
	},

	renderUrlExportTypeGroups( types ) {
		const postTypes = types.filter( ( type ) => type.kind === 'post_type' );
		const childTypes = types.filter( ( type ) => {
			const objectTypes = Array.isArray( type.objectTypes )
				? type.objectTypes
				: [];
			return objectTypes.length > 0;
		} );
		const standardTypes = types.filter( ( type ) => {
			const objectTypes = Array.isArray( type.objectTypes )
				? type.objectTypes
				: [];
			return type.kind !== 'post_type' && objectTypes.length === 0;
		} );
		const renderedChildren = new Set();

		const bulkControls = this.renderUrlExportBulkControls();

		const standardGroup = standardTypes.length
			? `
				<div class="rsl-ie-url-export-group">
					<div class="rsl-ie-url-export-group-header">
						<span class="dashicons dashicons-admin-site-alt3"></span>
						<div>
							<h4>${ __( 'Standard WordPress URLs', 'import-export-by-rockstarlab' ) }</h4>
							<p>${ __(
								'Homepage, archives, feeds, search and REST URLs',
								'import-export-by-rockstarlab'
							) }</p>
						</div>
					</div>
					<div class="rsl-ie-url-export-group-items">
						${ standardTypes
							.map( ( type ) =>
								this.renderUrlExportTypeCard( type )
							)
							.join( '' ) }
					</div>
				</div>
			`
			: '';

		const groups = postTypes
			.map( ( postType ) => {
				const relatedTypes = childTypes.filter( ( type ) => {
					const objectTypes = Array.isArray( type.objectTypes )
						? type.objectTypes
						: [];
					return objectTypes.includes( postType.name );
				} );

				relatedTypes.forEach( ( type ) =>
					renderedChildren.add( type.value || type.name )
				);

				return `
					<div class="rsl-ie-url-export-group">
						<div class="rsl-ie-url-export-group-header">
							<span class="dashicons dashicons-admin-post"></span>
							<div>
								<h4>${ this.escapeHtml( postType.label ) }</h4>
								<p>${ __(
									'Single URLs, archives, feeds, REST endpoint and related taxonomies',
									'import-export-by-rockstarlab'
								) }</p>
							</div>
						</div>
						<div class="rsl-ie-url-export-group-items">
							${ this.renderUrlExportTypeCard( postType ) }
							${
								relatedTypes.length
									? `
										<div class="rsl-ie-url-export-taxonomies-label">${ __(
											'Related URL sources',
											'import-export-by-rockstarlab'
										) }</div>
										${ relatedTypes
											.map( ( type ) =>
												this.renderUrlExportTypeCard(
													type,
													true
												)
											)
											.join( '' ) }
									`
									: ''
							}
						</div>
					</div>
				`;
			} )
			.join( '' );

		const orphanChildren = childTypes.filter(
			( type ) => ! renderedChildren.has( type.value || type.name )
		);

		if ( orphanChildren.length ) {
			return (
				bulkControls +
				standardGroup +
				groups +
				`
					<div class="rsl-ie-url-export-group">
						<div class="rsl-ie-url-export-group-header">
							<span class="dashicons dashicons-category"></span>
							<div>
								<h4>${ __( 'Other URL sources', 'import-export-by-rockstarlab' ) }</h4>
								<p>${ __(
									'Sources not attached to the visible post type groups',
									'import-export-by-rockstarlab'
								) }</p>
							</div>
						</div>
						<div class="rsl-ie-url-export-group-items">
							${ orphanChildren
								.map( ( type ) =>
									this.renderUrlExportTypeCard( type, true )
								)
								.join( '' ) }
						</div>
					</div>
				`
			);
		}

		return bulkControls + standardGroup + groups;
	},

	renderUrlExportBulkControls() {
		const controls = [
			{
				category: 'posts',
				label: __( 'Posts', 'import-export-by-rockstarlab' ),
			},
			{
				category: 'taxonomies',
				label: __( 'Taxonomies', 'import-export-by-rockstarlab' ),
			},
			{
				category: 'rest',
				label: __(
					'REST API Endpoints',
					'import-export-by-rockstarlab'
				),
			},
			{
				category: 'rss_feeds',
				label: __( 'RSS Feeds', 'import-export-by-rockstarlab' ),
			},
			{
				category: 'atom_feeds',
				label: __( 'Atom Feeds', 'import-export-by-rockstarlab' ),
			},
			{
				category: 'comments_feeds',
				label: __( 'Comments Feeds', 'import-export-by-rockstarlab' ),
			},
		];

		return `
			<div class="rsl-ie-url-export-bulk-controls">
				<div>
					<h4>${ __( 'Bulk select URL groups', 'import-export-by-rockstarlab' ) }</h4>
					<p>${ __(
						'Quickly select or deselect matching sources across all registered content types.',
						'import-export-by-rockstarlab'
					) }</p>
				</div>
				<div class="rsl-ie-url-export-bulk-list">
					${ controls
						.map(
							( control ) => `
								<label class="rsl-ie-url-export-bulk-item">
									<input
										type="checkbox"
										class="rsl-ie-url-export-bulk-checkbox"
										data-url-category="${ this.escapeHtml( control.category ) }"
										${
											this.isUrlExportBulkCategoryCheckedByDefault(
												control.category
											)
												? 'checked'
												: ''
										}
									>
									<span>${ this.escapeHtml( control.label ) }</span>
								</label>
							`
						)
						.join( '' ) }
				</div>
			</div>
		`;
	},

	isUrlExportBulkCategoryCheckedByDefault( category ) {
		return [ 'posts', 'taxonomies' ].includes( category );
	},

	getUrlSourceKindLabel( kind ) {
		const labels = {
			post_type: __( 'Post type', 'import-export-by-rockstarlab' ),
			taxonomy: __( 'Taxonomy', 'import-export-by-rockstarlab' ),
			standard: __( 'Standard', 'import-export-by-rockstarlab' ),
			feed: __( 'Feed', 'import-export-by-rockstarlab' ),
			rest: __( 'REST endpoint', 'import-export-by-rockstarlab' ),
			post_type_archive: __(
				'Post type archive',
				'import-export-by-rockstarlab'
			),
			post_type_feed: __(
				'Post type feed',
				'import-export-by-rockstarlab'
			),
			rest_post_type: __(
				'REST endpoint',
				'import-export-by-rockstarlab'
			),
		};

		return (
			labels[ kind ] ||
			kind ||
			__( 'URL source', 'import-export-by-rockstarlab' )
		);
	},

	renderUrlExportTypeCard( type, isChild = false ) {
		const categories = this.getUrlSourceBulkCategories( type );
		const isChecked = this.isUrlSourceCheckedByDefault( type );

		return `
			<label class="rsl-ie-url-export-type${
				isChild ? ' rsl-ie-url-export-type-child' : ''
			}${ isChecked ? ' is-checked' : '' }">
				<input
					type="checkbox"
					class="rsl-ie-url-export-type-checkbox"
					value="${ this.escapeHtml( type.value || type.name ) }"
					data-url-categories="${ this.escapeHtml( categories.join( ' ' ) ) }"
					${ isChecked ? 'checked' : '' }
				>
				<span class="rsl-ie-url-export-type-card">
					<span class="rsl-ie-url-export-type-main">
						<span class="rsl-ie-url-export-type-title">${ this.escapeHtml(
							type.label
						) }</span>
						<span class="rsl-ie-url-export-type-slug">
							<span>type: <code>${ this.escapeHtml(
								this.getUrlSourceKindLabel( type.kind )
							) }</code></span>
							<span>name: <code>${ this.escapeHtml( type.name ) }</code></span>
							<span>slug: <code>${ this.escapeHtml( type.slug || type.name ) }</code></span>
						</span>
						${
							type.description
								? `<span class="rsl-ie-url-export-type-description">${ this.escapeHtml(
										type.description
								  ) }</span>`
								: ''
						}
					</span>
					<span class="rsl-ie-url-export-type-count">
						<strong>${ parseInt( type.count, 10 ) || 0 }</strong>
						<span>URLs</span>
					</span>
				</span>
			</label>
		`;
	},

	getUrlSourceBulkCategories( type ) {
		if ( type.kind === 'post_type' ) {
			return [ 'posts' ];
		}

		if ( type.kind === 'taxonomy' ) {
			return [ 'taxonomies' ];
		}

		if ( [ 'rest', 'rest_post_type' ].includes( type.kind ) ) {
			return [ 'rest' ];
		}

		if (
			type.kind === 'post_type_feed' ||
			( type.kind === 'feed' && type.name === 'main' )
		) {
			return [ 'rss_feeds' ];
		}

		if ( type.kind === 'feed' && type.name === 'atom' ) {
			return [ 'atom_feeds' ];
		}

		if ( type.kind === 'feed' && type.name === 'comments' ) {
			return [ 'comments_feeds' ];
		}

		return [];
	},

	isUrlSourceCheckedByDefault( type ) {
		return ! [
			'feed',
			'rest',
			'post_type_feed',
			'rest_post_type',
		].includes( type.kind );
	},

	toggleUrlExportBulkCategory( e ) {
		const $checkbox = jQuery( e.currentTarget );
		const category = $checkbox.data( 'url-category' );
		const isChecked = $checkbox.is( ':checked' );

		jQuery( '.rsl-ie-url-export-type-checkbox' ).each( function () {
			const categories = String(
				jQuery( this ).data( 'url-categories' ) || ''
			).split( ' ' );
			if ( categories.includes( category ) ) {
				jQuery( this ).prop( 'checked', isChecked );
			}
		} );

		this.updateUrlExportGroupStates();
		this.refreshCount( false );
	},

	updateUrlExportGroupStates() {
		jQuery( '.rsl-ie-url-export-type-checkbox' ).each( function () {
			const $input = jQuery( this );
			$input
				.closest( '.rsl-ie-url-export-type' )
				.toggleClass( 'is-checked', $input.is( ':checked' ) );
		} );

		jQuery( '.rsl-ie-url-export-group' ).each( function () {
			const $group = jQuery( this );
			const hasChecked =
				$group.find( '.rsl-ie-url-export-type-checkbox:checked' )
					.length > 0;
			$group.toggleClass( 'is-empty', ! hasChecked );
		} );
	},

	getSelectedUrlContentTypes() {
		const types = [];
		jQuery( '.rsl-ie-url-export-type-checkbox:checked' ).each( function () {
			types.push( jQuery( this ).val() );
		} );
		return types;
	},

	/**
	 * Update step 2 next button state based on item count
	 */
	updateStep2NextButton() {
		const $nextBtn = jQuery( '.rsl-ie-step-2 .rsl-ie-next-step' );
		const $count = jQuery( '.rsl-ie-step-2 .rsl-ie-count-value' );
		const countText = $count.text();
		const count = parseInt( countText, 10 );

		// Check content type for special validation
		const contentType = jQuery(
			'input[name="content_type"]:checked'
		).val();
		let isDisabled = false;
		let tooltipTitle = window.rslIeData.i18n.noDataAvailable;
		let tooltipMessage = window.rslIeData.i18n.adjustFiltersMessage;

		// Remove previous event handlers
		$nextBtn.off( 'mouseenter.tooltip mouseleave.tooltip' );

		// For custom_post_types, check if post type is selected
		if ( contentType === 'custom_post_types' ) {
			const $postTypeSelector = jQuery( '.rsl-ie-post-type-selector' );
			const selectedPostType = $postTypeSelector.val();

			if ( ! selectedPostType || selectedPostType.trim() === '' ) {
				isDisabled = true;
				tooltipTitle = window.rslIeData.i18n.postTypeRequired;
				tooltipMessage = window.rslIeData.i18n.pleaseSelectPostType;
			}
		}

		// For taxonomy, check if taxonomy is selected
		if ( contentType === 'taxonomy' ) {
			const $taxonomySelector = jQuery( '.rsl-ie-taxonomy-selector' );
			const selectedTaxonomy = $taxonomySelector.val();

			if ( ! selectedTaxonomy || selectedTaxonomy.trim() === '' ) {
				isDisabled = true;
				tooltipTitle = window.rslIeData.i18n.taxonomyRequired;
				tooltipMessage = window.rslIeData.i18n.pleaseSelectTaxonomy;
			}
		}

		// For database_table, check if table is selected
		if ( contentType === 'database_table' ) {
			const $tableSelector = jQuery( '#rsl-ie-table-name' );
			const selectedTable = $tableSelector.val();

			if ( ! selectedTable || selectedTable.trim() === '' ) {
				isDisabled = true;
				tooltipTitle = window.rslIeData.i18n.tableRequired;
				tooltipMessage = window.rslIeData.i18n.pleaseSelectTable;
			}
		}

		if (
			contentType === 'form_entries' &&
			! this.getFormEntrySelection()
		) {
			isDisabled = true;
			tooltipTitle =
				window.rslIeData.i18n.formRequired ||
				__( 'Select a form', 'import-export-by-rockstarlab' );
			tooltipMessage =
				window.rslIeData.i18n.pleaseSelectForm ||
				__(
					'Please choose the form whose entries you want to export.',
					'import-export-by-rockstarlab'
				);
		}

		if (
			contentType === 'urls' &&
			this.getSelectedUrlContentTypes().length === 0
		) {
			isDisabled = true;
			tooltipTitle = __(
				'Select URL types',
				'import-export-by-rockstarlab'
			);
			tooltipMessage = __(
				'Please select at least one public content type.',
				'import-export-by-rockstarlab'
			);
		}

		// Disable if count is 0, NaN, or '-'
		if (
			! isDisabled &&
			( countText === '-' || isNaN( count ) || count === 0 )
		) {
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
		jQuery( '.rsl-ie-custom-tooltip' ).remove();

		// Get custom tooltip data or use defaults
		const tooltipTitle =
			$button.data( 'tooltip-title' ) ||
			window.rslIeData.i18n.noDataAvailable;
		const tooltipMessage =
			$button.data( 'tooltip-message' ) ||
			window.rslIeData.i18n.adjustFiltersMessage;

		// Create tooltip element
		const $tooltip = jQuery( '<div>' ).addClass(
			'rsl-ie-custom-tooltip rsl-ie-custom-pointer'
		).html( `
				<div class="rsl-ie-pointer-icon">
					<span class="dashicons dashicons-warning"></span>
				</div>
				<div class="rsl-ie-pointer-content">
					<h3>${ tooltipTitle }</h3>
					<p>${ tooltipMessage }</p>
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
		const left = buttonOffset.left + buttonWidth / 2 - tooltipWidth / 2;
		const top = buttonOffset.top - tooltipHeight - 10; // 10px gap
		$tooltip.css( {
			left: left + 'px',
			top: top + 'px',
			zIndex: 9999,
		} );
		// Fade in
		setTimeout( () => {
			$tooltip.addClass( 'rsl-ie-tooltip-visible' );
		}, 10 );
	},

	/**
	 * Hide custom tooltip
	 */
	hideNextButtonTooltip( $button ) {
		const $tooltip = jQuery( '.rsl-ie-custom-tooltip' );

		if ( $tooltip.length ) {
			$tooltip.removeClass( 'rsl-ie-tooltip-visible' );

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
			woo_review: null,
			woo_refund: null,
			woo_customer: null,
			woo_shipping_zone: null,
			woo_tax_rate: null,
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

		jQuery( '.rsl-ie-filter-row' ).each( ( index, row ) => {
			const $row = jQuery( row );
			const field = $row.find( '.rsl-ie-filter-field' ).val();
			const fieldType = $row
				.find( '.rsl-ie-filter-field option:selected' )
				.data( 'type' );

			// Skip table selector for custom_table type
			if ( fieldType === 'table_selector' ) {
				return;
			}

			// Handle post_type_selector type
			if ( fieldType === 'post_type_selector' ) {
				const value = $row.find( '.rsl-ie-filter-value' ).val();

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
				const value = $row.find( '.rsl-ie-filter-value' ).val();

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
				const name = $row.find( '.rsl-ie-custom-field-name' ).val();
				const condition = $row
					.find( '.rsl-ie-custom-field-condition' )
					.val();
				const value = $row.find( '.rsl-ie-custom-field-value' ).val();

				if ( name && condition ) {
					const noValueConditions = [ 'is_empty', 'is_not_empty' ];
					if (
						noValueConditions.includes( condition ) ||
						( value && value.trim() !== '' )
					) {
						customFields.push( {
							name: name,
							condition: condition,
							value: value || '',
						} );
					}
				}
				return;
			}

			// Handle language_filter type (Polylang language taxonomy)
			if ( fieldType === 'language_filter' ) {
				const languages = $row.find( '.rsl-ie-language-terms' ).val() || [];
				if ( languages.length ) {
					taxonomyFilters.push( {
						taxonomy: 'language',
						condition: $row.find( '.rsl-ie-language-condition' ).val() || 'in',
						terms: languages.join( ',' ),
					} );
				}
				return;
			}

			// Handle taxonomy_filter type
			if ( fieldType === 'taxonomy_filter' ) {
				const taxonomy = $row.find( '.rsl-ie-taxonomy-name' ).val();
				const condition = $row
					.find( '.rsl-ie-taxonomy-condition' )
					.val();
				const terms = $row.find( '.rsl-ie-taxonomy-terms' ).val();

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
			const condition = $row.find( '.rsl-ie-filter-condition' ).val();
			let value = $row.find( '.rsl-ie-filter-value' ).val();

			// Skip empty or incomplete filters
			if ( ! field || ! condition ) {
				return;
			}

			// Normalize date values to YYYY-MM-DD regardless of datepicker locale format
			const fieldTypeForDate = $row
				.find( '.rsl-ie-filter-field option:selected' )
				.data( 'type' );
			if (
				( fieldTypeForDate === 'date' ||
					fieldTypeForDate === 'datetime' ) &&
				value
			) {
				const parsed = new Date( value );
				if ( ! isNaN( parsed.getTime() ) ) {
					value = parsed.toISOString().slice( 0, 10 );
				}
			}

			// For conditions that don't need value
			const noValueConditions = [ 'is_empty', 'is_not_empty' ];
			if (
				noValueConditions.includes( condition ) ||
				( value && value.trim() !== '' )
			) {
				filters.push( {
					field: field,
					condition: condition,
					value: value || '',
				} );
			}
		} );

		return {
			filters: this.getFiltersWithSelectionPrefill( filters ),
			custom_fields: customFields,
			taxonomy: taxonomyFilters,
		};
	},

	/**
	 * Keep bulk-export selected IDs locked into export requests.
	 *
	 * The visible Step 2 filter row is useful for the user, but WordPress/admin UI
	 * transitions can rebuild wizard state. This keeps the actual count/start
	 * payload constrained to the original selected object IDs.
	 *
	 * @param {Array<Object>} filters Dynamic filters collected from the UI.
	 * @return {Array<Object>} Dynamic filters with selected IDs applied.
	 */
	getFiltersWithSelectionPrefill( filters ) {
		const contentType = jQuery(
			'input[name="content_type"]:checked'
		).val();

		const prefillFilter = this.getSelectionPrefillFilter( contentType );

		if ( ! prefillFilter ) {
			return filters;
		}

		const nonPrefillFilters = filters.filter(
			( filter ) =>
				! (
					filter &&
					( filter.field === 'ID' || filter.field === 'term_id' )
				)
		);

		return [ ...nonPrefillFilters, prefillFilter ];
	},

	/**
	 * Add selected object IDs to export options for backend persistence.
	 *
	 * @param {Object} options Export options.
	 * @param {string} contentType Selected export content type.
	 */
	applySelectionPrefillToOptions( options, contentType ) {
		if ( contentType === 'media' && this.mediaLibraryPrefillIds.length ) {
			options.media_library_selected_ids = [
				...this.mediaLibraryPrefillIds,
			];
		}

		if (
			this.isPostListPrefillContentType( contentType ) &&
			this.postListPrefillIds.length
		) {
			options.post_list_selected_ids = [ ...this.postListPrefillIds ];
		}

		if (
			contentType === 'taxonomy' &&
			this.taxonomyTermPrefillIds.length
		) {
			options.taxonomy_term_selected_ids = [
				...this.taxonomyTermPrefillIds,
			];
		}
	},

	/**
	 * Check whether the current content type is backed by a WP post table.
	 *
	 * @param {string} contentType Selected export content type.
	 * @return {boolean} Whether selected post IDs can be locked for this type.
	 */
	isPostListPrefillContentType( contentType ) {
		return [
			'post',
			'page',
			'custom_post_types',
			'woo_product',
			'woo_order',
			'woo_coupon',
		].includes( contentType );
	},

	/**
	 * Get the locked selected-ID filter.
	 *
	 * @param {string} contentType Selected export content type.
	 * @return {Object} Dynamic filter object.
	 */
	getSelectionPrefillFilter( contentType ) {
		if ( contentType === 'media' && this.mediaLibraryPrefillIds.length ) {
			return {
				field: 'ID',
				condition: 'in',
				value: this.mediaLibraryPrefillIds.join( ',' ),
			};
		}

		if (
			this.isPostListPrefillContentType( contentType ) &&
			this.postListPrefillIds.length
		) {
			return {
				field: 'ID',
				condition: 'in',
				value: this.postListPrefillIds.join( ',' ),
			};
		}

		if (
			contentType === 'taxonomy' &&
			this.taxonomyTermPrefillIds.length
		) {
			return {
				field: 'term_id',
				condition: 'in',
				value: this.taxonomyTermPrefillIds.join( ',' ),
			};
		}

		return null;
	},

	/**
	 * Get the locked Media Library selected-ID filter.
	 *
	 * @return {Object} Dynamic filter object.
	 */
	getMediaLibraryPrefillFilter() {
		return {
			field: 'ID',
			condition: 'in',
			value: this.mediaLibraryPrefillIds.join( ',' ),
		};
	},

	/**
	 * Get custom table filters (deprecated - use getDynamicFilters instead)
	 */
	getCustomTableFilters() {
		const filters = [];
		jQuery( '.rsl-ie-filter-row' ).each( ( index, row ) => {
			const $row = jQuery( row );
			const field = $row.find( '.rsl-ie-filter-field' ).val();
			const condition = $row.find( '.rsl-ie-filter-condition' ).val();
			const value = $row.find( '.rsl-ie-filter-value' ).val();

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

		jQuery( '.rsl-ie-format-options > div' ).hide();
		jQuery( `.rsl-ie-${ format }-options` ).show();
		jQuery( '.rsl-ie-common-options' ).show();
		this.ensureCustomExportFileName();
	},

	/**
	 * Build the default export filename template.
	 *
	 * @return {string} Default export filename.
	 */
	getDefaultExportFileName() {
		const contentType =
			jQuery( 'input[name="content_type"]:checked' ).val() || 'export';

		return `export-${ contentType }-{job_id}`;
	},

	/**
	 * Ensure the custom export filename field has a useful default.
	 */
	ensureCustomExportFileName() {
		const $field = jQuery( '[name="custom_export_file_name"]' );
		if ( ! $field.length ) {
			return;
		}

		const current = String( $field.val() || '' ).trim();
		const previousDefault = $field.data( 'defaultValue' ) || '';
		const nextDefault = this.getDefaultExportFileName();

		if ( ! current || current === previousDefault ) {
			$field.val( nextDefault );
		}

		$field.data( 'defaultValue', nextDefault );
	},

	/**
	 * Handle delimiter change
	 */
	onDelimiterChange( e ) {
		const delimiter = jQuery( e.target ).val();

		if ( delimiter === 'custom' ) {
			jQuery( '.rsl-ie-custom-delimiter-row' ).show();
		} else {
			jQuery( '.rsl-ie-custom-delimiter-row' ).hide();
		}
	},

	/**
	 * Get selected fields
	 */
	getSelectedFields() {
		if ( this.isUrlsExport() ) {
			return [ 'url' ];
		}

		// Get fields from Step 3 drag & drop interface
		if ( this.step3Instance && this.step3Instance.selectedFields ) {
			// Filter out pseudo-fields (selectors that start with _ and are used only for filtering)
			const pseudoFields = [ '_post_type', '_taxonomy', '_table_name' ];
			return this.step3Instance.selectedFields
				.map( ( field ) => field.field )
				.filter( ( field ) => ! pseudoFields.includes( field ) );
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
	 *
	 * @param {Event} e Click event.
	 */
	async startExport( e ) {
		const $button = e
			? jQuery( e.currentTarget )
			: jQuery( '.rsl-ie-start-export' );

		if ( $button.hasClass( 'is-starting' ) ) {
			return;
		}

		let fields = this.getSelectedFields();

		// If no fields selected (or only pseudo-fields were filtered out), show error
		if ( fields.length === 0 ) {
			Utils.showNotice(
				window.rslIeData.i18n.pleaseSelectFieldToExport,
				'error'
			);
			return;
		}

		this.setStartExportButtonLoading( $button, true );

		try {
			const contentType = jQuery(
				'input[name="content_type"]:checked'
			).val();
			const dynamicFiltersData = this.getDynamicFilters();

			// Get CSV delimiter
			let csvDelimiter = jQuery( '[name="csv_delimiter"]' ).val();
			if ( csvDelimiter === 'custom' ) {
				const customDelimiter = jQuery(
					'[name="csv_custom_delimiter"]'
				).val();
				if ( ! customDelimiter ) {
					Utils.showNotice(
						window.rslIeData.i18n.pleaseEnterCustomDelimiter,
						'error'
					);
					// Set focus to the custom delimiter field
					jQuery( '[name="csv_custom_delimiter"]' ).focus();
					this.setStartExportButtonLoading( $button, false );
					return;
				}
				csvDelimiter = customDelimiter;
			}

			const customExportFileName = String(
				jQuery( '[name="custom_export_file_name"]' ).val() || ''
			).trim();
			if ( ! customExportFileName ) {
				Utils.showNotice(
					__(
						'Please enter a custom export file name.',
						'import-export-by-rockstarlab'
					),
					'error'
				);
				jQuery( '[name="custom_export_file_name"]' ).focus();
				this.setStartExportButtonLoading( $button, false );
				return;
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
					csv_use_bom: jQuery( '[name="csv_use_bom"]' ).is(
						':checked'
					),
					json_pretty_print: jQuery(
						'[name="json_pretty_print"]'
					).is( ':checked' ),
					xml_pretty_print: jQuery( '[name="xml_pretty_print"]' ).is(
						':checked'
					),
					spreadsheet_include_header: jQuery(
						'[name="spreadsheet_include_header"]'
					).is( ':checked' ),
				},
				options: {
					custom_export_file_name: customExportFileName,
					items_per_iteration:
						parseInt(
							jQuery( '[name="items_per_iteration"]' ).val()
						) || 50,
				},
			};

			this.applySelectionPrefillToOptions( data.options, contentType );

			// Product feeds: one row per variation instead of the variable product.
			if (
				'woo_product' === contentType &&
				jQuery( '[name="variation_rows"]' ).is( ':checked' )
			) {
				data.options.variation_rows = true;
			}

			// Order exports: one row per order item.
			if (
				'woo_order' === contentType &&
				jQuery( '[name="line_item_rows"]' ).is( ':checked' )
			) {
				data.options.line_item_rows = true;
			}

			// Upload the finished file to a saved FTP/SFTP server folder.
			const remoteServer = String(
				jQuery( '[name="remote_destination_server"]' ).val() || ''
			);
			if ( remoteServer ) {
				const folder = String(
					jQuery( '[name="remote_destination_folder"]' ).val() || '/'
				)
					.trim()
					.replace( /^\/+|\/+$/g, '' );
				data.options.remote_destination =
					'rsl-remote://' +
					remoteServer +
					'/' +
					( folder ? folder + '/' : '' );
			}

			// Renamed columns and fixed values for custom columns.
			if (
				this.step3Instance &&
				'function' === typeof this.step3Instance.getColumnSettings
			) {
				const columns = this.step3Instance.getColumnSettings();
				if ( Object.keys( columns.headers ).length ) {
					data.options.column_headers = columns.headers;
				}
				if ( Object.keys( columns.values ).length ) {
					data.options.custom_values = columns.values;
				}
			}

			if ( contentType === 'form_entries' ) {
				const selection = this.getFormEntrySelection();
				if ( ! selection ) {
					Utils.showNotice(
						window.rslIeData.i18n.pleaseSelectForm ||
							__(
								'Please choose a form.',
								'import-export-by-rockstarlab'
							),
						'error'
					);
					this.setStartExportButtonLoading( $button, false );
					return;
				}
				Object.assign( data.options, selection );
			}

			if ( contentType === 'urls' ) {
				const contentTypes = this.getSelectedUrlContentTypes();
				if ( contentTypes.length === 0 ) {
					Utils.showNotice(
						__(
							'Please select at least one URL content type.',
							'import-export-by-rockstarlab'
						),
						'error'
					);
					this.setStartExportButtonLoading( $button, false );
					return;
				}
				data.options.content_types = contentTypes;
			}

			if (
				window.rslIeData?.fieldTransformationsEnabled &&
				this.step3Instance &&
				this.step3Instance.fieldFunctions
			) {
				// Convert field functions from fieldKey (with timestamp) to actual field names
				const convertedFunctions = this.convertFieldFunctions(
					this.step3Instance.fieldFunctions,
					this.step3Instance.selectedFields
				);
				if ( Object.keys( convertedFunctions ).length > 0 ) {
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
				const $tableDropdown = jQuery( '#rsl-ie-table-name' );
				const tableName = $tableDropdown.val();
				if ( tableName ) {
					data.table_name = tableName;
				}
			}

			const response = await Utils.ajax( 'rsl_ie_export_start', data );

			this.jobId = response.job_id;
			this.exportStartTime = Date.now();
			this.showStep( 5 );
			this.startProgressTracking();

			// Trigger first batch processing
			this.processNextBatch();

			Utils.showNotice(
				window.rslIeData.i18n.exportStartedSuccess,
				'success'
			);
		} catch ( error ) {
			Utils.handleError(
				error,
				__( 'Start export', 'import-export-by-rockstarlab' )
			);
		} finally {
			this.setStartExportButtonLoading( $button, false );
		}
	},

	/**
	 * Toggle start export button loading state.
	 *
	 * @param {jQuery} $button Start export button.
	 * @param {boolean} isLoading Loading state.
	 */
	setStartExportButtonLoading( $button, isLoading ) {
		if ( ! $button || ! $button.length ) {
			return;
		}

		if ( isLoading ) {
			$button.data( 'original-html', $button.html() );
			$button
				.addClass( 'is-starting' )
				.prop( 'disabled', true )
				.attr( 'aria-busy', 'true' )
				.html(
					'<span class="dashicons dashicons-update rsl-ie-button-spinner"></span><span>Starting...</span>'
				);
			return;
		}

		$button
			.removeClass( 'is-starting' )
			.prop( 'disabled', false )
			.removeAttr( 'aria-busy' );

		if ( $button.data( 'original-html' ) ) {
			$button.html( $button.data( 'original-html' ) );
			$button.removeData( 'original-html' );
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
			const response = await Utils.ajax( 'rsl_ie_export_process_batch', {
				job_id: this.jobId,
			} );

			// Update UI directly on each batch — don't rely solely on 2s polling.
			if ( response ) {
				const elapsedSec = this.exportStartTime
					? ( Date.now() - this.exportStartTime ) / 1000
					: 0;
				const processed = response.processed || 0;
				const total = response.total || 0;
				const percentage =
					response.progress ||
					( total > 0 ? ( processed / total ) * 100 : 0 );
				const itemsPerSec = elapsedSec > 0 ? processed / elapsedSec : 0;
				const remainingSec =
					itemsPerSec > 0 && total > processed
						? ( total - processed ) / itemsPerSec
						: 0;

				const formatTime = ( sec ) => {
					return Utils.formatDuration(
						Math.max( 0, Math.round( sec ) )
					);
				};

				TabProgress.update( 'export', percentage );
				Utils.updateProgressBar( jQuery( '.rsl-ie-step-5' ), {
					percentage,
					processed,
					total,
					estimates: {
						elapsed_formatted: formatTime( elapsedSec ),
						remaining_formatted:
							remainingSec > 0 ? formatTime( remainingSec ) : '-',
						items_per_second: itemsPerSec,
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
		} catch ( error ) {}
	},

	/**
	 * Start progress tracking
	 */
	startProgressTracking() {
		TabProgress.update( 'export' );
		this.progressInterval = setInterval( () => {
			this.updateProgress();
		}, 2000 );
	},

	/**
	 * Update progress
	 */
	async updateProgress() {
		try {
			const response = await Utils.ajax( 'rsl_ie_export_get_progress', {
				job_id: this.jobId,
			} );

			TabProgress.update(
				'export',
				response.percentage ?? response.progress
			);
			Utils.updateProgressBar( jQuery( '.rsl-ie-step-5' ), response );

			if ( response.status === 'completed' ) {
				this.onExportComplete( response );
			} else if ( response.status === 'failed' ) {
				this.onExportFailed( response );
			}
			return response.status;
		} catch ( error ) {
			TabProgress.finish( 'export', 'failed' );
		}
	},

	/**
	 * Handle export completion
	 */
	onExportComplete( result ) {
		TabProgress.finish( 'export' );
		clearInterval( this.progressInterval );

		// Update title
		jQuery( '.rsl-ie-step-5 h2' ).text(
			window.rslIeData.i18n.exportComplete
		);

		// Hide the description text
		jQuery( '.rsl-ie-step-5 .description' ).hide();

		// Hide progress container
		jQuery( '.rsl-ie-progress-container' ).hide();

		// Show results container
		jQuery( '.rsl-ie-export-results' ).show();

		// Show and populate the success card
		const $card = jQuery( '.rsl-ie-export-complete-card' );
		$card.show();

		// Use data from result (progress response)
		jQuery( '.rsl-ie-result-processed' ).text(
			result.processed || result.total || 0
		);
		jQuery( '.rsl-ie-result-filesize' ).text(
			Utils.formatFileSize( result.file_size || 0 )
		);
		const formatExportDuration = ( sec ) => {
			return Utils.formatDuration( Math.max( 0, Math.round( sec ) ) );
		};
		const exportDurSec = this.exportStartTime
			? ( Date.now() - this.exportStartTime ) / 1000
			: 0;
		jQuery( '.rsl-ie-result-duration' ).text(
			exportDurSec > 0
				? formatExportDuration( exportDurSec )
				: result.estimates?.elapsed_formatted || '0s'
		);

		// Upload to a saved FTP/SFTP server.
		const upload = result.remote_upload;
		const $upload = jQuery( '.rsl-ie-remote-upload-result' );
		$upload.removeClass( 'is-uploaded is-failed' ).hide();
		if ( upload && upload.status ) {
			const i18n = window.rslIeData.i18n || {};
			const ok = 'uploaded' === upload.status;
			$upload
				.addClass( ok ? 'is-uploaded' : 'is-failed' )
				.text(
					(
						( ok
							? i18n.remoteUploaded
							: i18n.remoteUploadFailed ) ||
						( ok
							? __(
									'Uploaded to %1$s: %2$s',
									'import-export-by-rockstarlab'
							  )
							: __(
									'Upload to %1$s failed: %2$s',
									'import-export-by-rockstarlab'
							  ) )
					)
						.replace( '%1$s', upload.server || '' )
						.replace(
							'%2$s',
							ok ? upload.path || '' : upload.error || ''
						)
				)
				.show();
		}

		jQuery( '.rsl-ie-cancel-export' ).hide();
		jQuery( '.rsl-ie-new-export' ).show();

		Utils.showNotice(
			window.rslIeData.i18n.exportCompletedSuccess,
			'success'
		);
	},

	/**
	 * Handle export failure
	 */
	onExportFailed( result ) {
		TabProgress.finish( 'export', 'failed' );
		clearInterval( this.progressInterval );
		const errorMessage =
			result.error ||
			( result.result && result.result.error ) ||
			window.rslIeData.i18n.unknownError;
		Utils.showNotice(
			window.rslIeData.i18n.exportFailed + ': ' + errorMessage,
			'error'
		);
	},

	/**
	 * Download export file
	 *
	 * @param {Event} e Click event.
	 */
	async downloadFile( e ) {
		const $button = e
			? jQuery( e.currentTarget )
			: jQuery( '.rsl-ie-download-file' );

		if ( $button.hasClass( 'is-downloading' ) ) {
			return;
		}

		this.setDownloadButtonLoading( $button, true );

		try {
			const downloadAsZip = jQuery( '[name="download_zip"]' ).is(
				':checked:not(:disabled)'
			);

			const response = await Utils.ajax( 'rsl_ie_export_download', {
				job_id: this.jobId,
				download_zip: downloadAsZip ? 1 : 0,
			} );

			if ( response.download_url ) {
				Utils.downloadFile( response.download_url, response.filename );
			}
		} catch ( error ) {
			Utils.handleError(
				error,
				__( 'Download file', 'import-export-by-rockstarlab' )
			);
		} finally {
			setTimeout( () => {
				this.setDownloadButtonLoading( $button, false );
			}, 600 );
		}
	},

	/**
	 * Toggle download button loading state.
	 *
	 * @param {jQuery} $button Download button.
	 * @param {boolean} isLoading Loading state.
	 */
	setDownloadButtonLoading( $button, isLoading ) {
		if ( ! $button || ! $button.length ) {
			return;
		}

		if ( isLoading ) {
			$button.data( 'original-html', $button.html() );
			$button
				.addClass( 'is-downloading' )
				.prop( 'disabled', true )
				.attr( 'aria-busy', 'true' )
				.html(
					'<span class="dashicons dashicons-update rsl-ie-download-spinner"></span><span>Downloading...</span>'
				);
			return;
		}

		$button
			.removeClass( 'is-downloading' )
			.prop( 'disabled', false )
			.removeAttr( 'aria-busy' );

		if ( $button.data( 'original-html' ) ) {
			$button.html( $button.data( 'original-html' ) );
			$button.removeData( 'original-html' );
		}
	},

	/**
	 * Cancel export
	 */
	async cancelExport() {
		if ( ! confirm( window.rslIeData.i18n.confirmCancelExport ) ) {
			return;
		}

		try {
			await Utils.ajax( 'rsl_ie_export_cancel', { job_id: this.jobId } );
			clearInterval( this.progressInterval );
			Utils.showNotice( window.rslIeData.i18n.exportCancelled, 'info' );
			this.resetWizard();
		} catch ( error ) {
			Utils.handleError(
				error,
				__( 'Cancel export', 'import-export-by-rockstarlab' )
			);
		}
	},

	/**
	 * Start new export - reload the page
	 */
	newExport() {
		window.location.href =
			window.rslIeData?.exportUrl || window.location.href;
	},

	/**
	 * Reset wizard
	 */
	resetWizard() {
		TabProgress.reset( 'export' );
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
		jQuery( '.rsl-ie-export-results' ).hide();

		this.showStep( 1 );
	},

	/**
	 * Add new filter row
	 */
	addFilterRow( selectedField = '' ) {
		const template = document.getElementById(
			'rsl-ie-filter-row-template'
		);
		const clone = template.content.cloneNode( true );
		const contentType = jQuery(
			'input[name="content_type"]:checked'
		).val();

		// Populate field options based on content type (without Featured Image group)
		const $fieldSelect = jQuery( clone ).find( '.rsl-ie-filter-field' );
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

		jQuery( '#rsl-ie-filters-list' ).append( clone );

		if ( selectedField ) {
			const $row = jQuery(
				'#rsl-ie-filters-list .rsl-ie-filter-row'
			).last();
			$row.find( '.rsl-ie-filter-field' )
				.val( selectedField )
				.trigger( 'change' );
		}

		// Trigger count refresh (without spinner)
		this.queueCountRefresh();
	},

	/**
	 * Remove filter row
	 */
	removeFilterRow( e ) {
		jQuery( e.target ).closest( '.rsl-ie-filter-row' ).remove();

		// Trigger count refresh (without spinner)
		this.queueCountRefresh();
	},

	/**
	 * Handle filter field change
	 */
	onFilterFieldChange( e ) {
		const $field = jQuery( e.target );
		const $row = $field.closest( '.rsl-ie-filter-row' );
		const $condition = $row.find( '.rsl-ie-filter-condition' );
		const $valueWrap = $row.find( '.rsl-ie-filter-value-wrap' );
		const $value = $row.find( '.rsl-ie-filter-value' );

		const selectedOption = $field.find( 'option:selected' );
		const fieldType = selectedOption.data( 'type' ) || 'string';

		// Special handling for custom_field
		if ( fieldType === 'custom_field' ) {
			// Create custom interface for custom field filter
			$condition.closest( '.rsl-ie-filter-condition-wrap' ).show();
			$valueWrap.html( `
				<div class="rsl-ie-custom-field-inputs">
					<div class="rsl-ie-input-group">
						<label>${
							window.rslIeData.i18n.customFieldName ||
							__(
								'Custom field name',
								'import-export-by-rockstarlab'
							)
						}</label>
						<input type="text" class="rsl-ie-custom-field-name" placeholder="${
							window.rslIeData.i18n.enterCustomFieldName
						}" />
					</div>
					<div class="rsl-ie-input-group">
						<label>${ window.rslIeData.i18n.condition }</label>
						<select class="rsl-ie-custom-field-condition rsl-ie-filter-condition">
							<option value="equals">${ window.rslIeData.i18n.equals }</option>
							<option value="not_equals">${ window.rslIeData.i18n.notEquals }</option>
							<option value="contains">${ window.rslIeData.i18n.contains }</option>
							<option value="not_contains">${ window.rslIeData.i18n.notContains }</option>
							<option value="greater">${ window.rslIeData.i18n.greaterThan }</option>
							<option value="less">${ window.rslIeData.i18n.lessThan }</option>
							<option value="equals_or_greater">${
								window.rslIeData.i18n.greaterOrEqual
							}</option>
							<option value="equals_or_less">${ window.rslIeData.i18n.lessOrEqual }</option>
							<option value="in">${ window.rslIeData.i18n.inComma }</option>
							<option value="not_in">${ window.rslIeData.i18n.notInComma }</option>
							<option value="is_empty">${ window.rslIeData.i18n.isEmpty }</option>
							<option value="is_not_empty">${ window.rslIeData.i18n.isNotEmpty }</option>
						</select>
					</div>
					<div class="rsl-ie-input-group rsl-ie-custom-field-value-group">
						<label>${ window.rslIeData.i18n.value }</label>
						<input type="text" class="rsl-ie-custom-field-value rsl-ie-filter-value" placeholder="${
							window.rslIeData.i18n.enterFilterValue
						}" />
					</div>
				</div>
			` );
			$condition.closest( '.rsl-ie-filter-condition-wrap' ).hide();

			// Handle condition change to show/hide value input
			$row.find( '.rsl-ie-custom-field-condition' ).on(
				'change',
				function ( event ) {
					// `this` is bound to the wizard, so read the select from the event.
					const condition = jQuery( event.currentTarget ).val();
					const $valueGroup = $row.find(
						'.rsl-ie-custom-field-value-group'
					);
					if (
						condition === 'is_empty' ||
						condition === 'is_not_empty'
					) {
						$valueGroup.hide();
					} else {
						$valueGroup.show();
					}
					// Trigger count refresh on condition change
					this.queueCountRefresh();
				}.bind( this )
			);

			// Add change event handlers to trigger count refresh
			$row.find(
				'.rsl-ie-custom-field-name, .rsl-ie-custom-field-value'
			).on( 'input change', () => {
				this.queueCountRefresh();
			} );

			return;
		}

		// Language (Polylang): choose languages instead of typing the taxonomy.
		if ( fieldType === 'language_filter' ) {
			const languages = window.rslIeData?.languages || {};
			const $select = jQuery(
				'<select multiple class="rsl-ie-language-terms rsl-ie-filter-value"></select>'
			)
				.attr( 'size', Math.min( 8, Object.keys( languages ).length ) )
				.css( { width: '100%', height: 'auto', padding: '4px' } );
			Object.keys( languages ).forEach( ( slug ) => {
				$select.append(
					jQuery( '<option>' ).val( slug ).text( languages[ slug ] )
				);
			} );
			$valueWrap.html( `
				<div class="rsl-ie-taxonomy-filter-inputs">
					<div class="rsl-ie-input-group">
						<label>${ __( 'Condition', 'import-export-by-rockstarlab' ) }</label>
						<select class="rsl-ie-language-condition">
							<option value="in">${ __( 'In these languages', 'import-export-by-rockstarlab' ) }</option>
							<option value="not_in">${ __( 'Not in these languages', 'import-export-by-rockstarlab' ) }</option>
						</select>
					</div>
					<div class="rsl-ie-input-group rsl-ie-language-terms-wrap">
						<label>${ __( 'Languages', 'import-export-by-rockstarlab' ) }</label>
					</div>
				</div>
			` );
			$valueWrap.find( '.rsl-ie-language-terms-wrap' ).append( $select );
			$condition.closest( '.rsl-ie-filter-condition-wrap' ).hide();
			$row.find( '.rsl-ie-language-condition, .rsl-ie-language-terms' ).on(
				'change',
				() => {
					this.queueCountRefresh();
				}
			);
			return;
		}

		// Special handling for taxonomy_filter
		if ( fieldType === 'taxonomy_filter' ) {
			// Create custom interface for taxonomy filter
			$condition.closest( '.rsl-ie-filter-condition-wrap' ).show();
			$valueWrap.html( `
				<div class="rsl-ie-taxonomy-filter-inputs">
					<div class="rsl-ie-input-group">
						<label>${ __( 'Taxonomy Name', 'import-export-by-rockstarlab' ) }</label>
						<input type="text" class="rsl-ie-taxonomy-name" placeholder="${
							window.rslIeData.i18n.taxonomyPlaceholderExamples
						}" />
					</div>
					<div class="rsl-ie-input-group">
						<label>${ __( 'Condition', 'import-export-by-rockstarlab' ) }</label>
						<select class="rsl-ie-taxonomy-condition rsl-ie-filter-condition">
							<option value="in">${ window.rslIeData.i18n.hasTermsIn }</option>
							<option value="not_in">${ window.rslIeData.i18n.doesNotHaveTermsNotIn }</option>
							<option value="and">${ window.rslIeData.i18n.hasAllTermsAnd }</option>
						</select>
					</div>
					<div class="rsl-ie-input-group">
						<label>${ __( 'Terms', 'import-export-by-rockstarlab' ) }</label>
						<input type="text" class="rsl-ie-taxonomy-terms rsl-ie-filter-value" placeholder="${
							window.rslIeData.i18n.enterTermSlugs
						}" />
						<small>${ window.rslIeData.i18n.enterTermSlugs }</small>
					</div>
				</div>
			` );
			$condition.closest( '.rsl-ie-filter-condition-wrap' ).hide();

			// Add change event handlers to trigger count refresh
			$row.find(
				'.rsl-ie-taxonomy-name, .rsl-ie-taxonomy-condition, .rsl-ie-taxonomy-terms'
			).on( 'input change', () => {
				this.queueCountRefresh();
			} );

			return;
		}

		// Special handling for table_selector
		if ( fieldType === 'table_selector' ) {
			// Hide condition dropdown for table selector
			$condition.closest( '.rsl-ie-filter-condition-wrap' ).hide();

			// Replace value input with table selector
			$valueWrap
				.find( 'label' )
				.text( window.rslIeData.i18n.selectTable );

			// Create a select dropdown for tables
			const $select = jQuery( '<select>' )
				.addClass( 'rsl-ie-filter-value rsl-ie-table-selector' )
				.attr( 'name', 'filter_value[]' );

			// Fetch database tables via AJAX
			Utils.ajax( 'rsl_ie_get_database_tables', {} )
				.then( ( tables ) => {
					$select.append(
						jQuery( '<option>' )
							.val( '' )
							.text(
								window.rslIeData.i18n.selectTablePlaceholder
							)
					);

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
				} )
				.catch( ( error ) => {
					$select.append(
						jQuery( '<option>' )
							.val( '' )
							.text( window.rslIeData.i18n.errorLoadingTables )
					);
				} );

			$value.replaceWith( $select );
			return;
		}

		// Special handling for post_type_selector
		if ( fieldType === 'post_type_selector' ) {
			// Hide condition dropdown for post type selector
			$condition.closest( '.rsl-ie-filter-condition-wrap' ).hide();

			// Replace value input with post type selector
			$valueWrap
				.find( 'label' )
				.text( window.rslIeData.i18n.selectPostType );

			// Create a select dropdown for post types
			const $select = jQuery( '<select>' )
				.addClass(
					'rsl-ie-filter-value rsl-ie-post-type-selector is-loading'
				)
				.attr( 'name', 'filter_value[]' )
				.prop( 'disabled', true )
				.append(
					jQuery( '<option>' )
						.val( '' )
						.text( window.rslIeData.i18n.loading )
				);
			this.setLoadingSelectSpinner( $select );

			// Fetch post types via AJAX
			Utils.ajax( 'rsl_ie_get_post_types', {
				include_hidden: true,
			} )
				.then( ( postTypes ) => {
					$select.empty().prop( 'disabled', false );
					$select.append(
						jQuery( '<option>' )
							.val( '' )
							.text(
								window.rslIeData.i18n.selectPostTypePlaceholder
							)
					);

					if ( postTypes && Array.isArray( postTypes ) ) {
						postTypes.forEach( ( postType ) => {
							$select.append(
								jQuery( '<option>' )
									.val( postType.name )
									.text(
										postType.label +
											' (' +
											postType.name +
											')'
									)
							);
						} );

						// When post type is selected, refresh count
						$select.on( 'change', () => {
							this.queueCountRefresh();

							// Update step 2 next button state
							this.updateStep2NextButton();

							// Reload step 3 fields if currently on step 3
							if (
								this.currentStep === 3 &&
								this.step3Instance
							) {
								this.step3Instance.reloadDynamicFields();
							}
						} );
					}
				} )
				.catch( ( error ) => {
					$select.empty().prop( 'disabled', false );
					$select.append(
						jQuery( '<option>' )
							.val( '' )
							.text( window.rslIeData.i18n.errorLoadingPostTypes )
					);
				} )
				.finally( () => {
					$select.removeClass( 'is-loading' );
				} );

			$value.replaceWith( $select );
			return;
		}

		// Special handling for taxonomy_selector
		if ( fieldType === 'taxonomy_selector' ) {
			// Hide condition dropdown for taxonomy selector
			$condition.closest( '.rsl-ie-filter-condition-wrap' ).hide();

			// Replace value input with taxonomy selector
			$valueWrap
				.find( 'label' )
				.text( window.rslIeData.i18n.selectTaxonomy );

			// Create a select dropdown for taxonomies
			const $select = jQuery( '<select>' )
				.addClass(
					'rsl-ie-filter-value rsl-ie-taxonomy-selector is-loading'
				)
				.attr( 'name', 'filter_value[]' )
				.prop( 'disabled', true )
				.append(
					jQuery( '<option>' )
						.val( '' )
						.text( window.rslIeData.i18n.loading )
				);
			this.setLoadingSelectSpinner( $select );

			// Fetch taxonomies via AJAX
			Utils.ajax( 'rsl_ie_get_all_taxonomies', {} )
				.then( ( taxonomies ) => {
					$select.empty().prop( 'disabled', false );
					$select.append(
						jQuery( '<option>' )
							.val( '' )
							.text(
								window.rslIeData.i18n.selectTaxonomyPlaceholder
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

						// When taxonomy is selected, refresh count
						$select.on( 'change', () => {
							this.queueCountRefresh();

							// Update step 2 next button state
							this.updateStep2NextButton();

							// Reload step 3 fields if currently on step 3
							if (
								this.currentStep === 3 &&
								this.step3Instance
							) {
								this.step3Instance.reloadDynamicFields();
							}
						} );
					}
				} )
				.catch( ( error ) => {
					$select.empty().prop( 'disabled', false );
					$select.append(
						jQuery( '<option>' )
							.val( '' )
							.text(
								window.rslIeData.i18n.errorLoadingTaxonomies
							)
					);
				} )
				.finally( () => {
					$select.removeClass( 'is-loading' );
				} );

			$value.replaceWith( $select );
			return;
		}

		// Show condition dropdown for normal fields
		$condition.closest( '.rsl-ie-filter-condition-wrap' ).show();
		$valueWrap.find( 'label' ).text( window.rslIeData.i18n.value );

		// Clear existing conditions and populate based on field type
		$condition.empty();

		// Populate conditions based on field type
		const conditions = this.getConditionsByFieldType( fieldType );

		// Get the actual field name to filter out inappropriate conditions
		const fieldName = $field.val();

		// Filter conditions based on field
		const isFormEntries =
			jQuery( 'input[name="content_type"]:checked' ).val() ===
			'form_entries';
		const filteredConditions = conditions.filter( ( condition ) => {
			// Form entries: the conditions the form plugins can search by.
			if ( isFormEntries ) {
				let allowed = [
					'equals',
					'not_equals',
					'contains',
					'not_contains',
				];
				if ( fieldName === 'date_created' ) {
					allowed = [
						'equals',
						'greater',
						'less',
						'equals_or_greater',
						'equals_or_less',
						'between',
					];
				} else if ( fieldName === 'status' ) {
					allowed = [ 'equals' ];
				} else if ( fieldName === 'entry_id' ) {
					allowed = [ 'equals', 'in' ];
				}
				return allowed.includes( condition.value );
			}
			// For ID fields, exclude is_empty and is_not_empty (ID cannot be empty)
			if (
				fieldName === 'ID' ||
				fieldName === 'comment_ID' ||
				fieldName === 'term_id' ||
				fieldName === 'user_id' ||
				fieldName === 'attribute_id'
			) {
				return (
					condition.value !== 'is_empty' &&
					condition.value !== 'is_not_empty'
				);
			}

			// For date fields, exclude is_empty and is_not_empty (dates typically always have values)
			if ( fieldType === 'date' ) {
				return (
					condition.value !== 'is_empty' &&
					condition.value !== 'is_not_empty'
				);
			}

			// For comment_status, exclude is_empty and is_not_empty (always has a value: open, closed, etc.)
			if ( fieldName === 'comment_status' ) {
				return (
					condition.value !== 'is_empty' &&
					condition.value !== 'is_not_empty'
				);
			}

			// For content and excerpt fields, exclude in and not_in (not practical for long text)
			if (
				fieldName === 'post_content' ||
				fieldName === 'post_excerpt'
			) {
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
				.addClass( 'rsl-ie-filter-value' )
				.attr( 'name', 'filter_value[]' )
				.attr( 'placeholder', window.rslIeData.i18n.enterFilterValue );
			$value.replaceWith( $input );
			// Update reference
			$row.find( '.rsl-ie-filter-value' ).attr(
				'type',
				fieldType === 'date'
					? 'date'
					: fieldType === 'number'
					? 'number'
					: 'text'
			);
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
		this.queueCountRefresh();
	},

	/**
	 * Use the WordPress admin spinner as a select background while options load.
	 *
	 * @param {jQuery} $select Loading select.
	 */
	setLoadingSelectSpinner( $select ) {
		const adminUrl = String( window.rslIeData?.adminUrl || '' );
		const spinnerUrl = adminUrl
			? `${ adminUrl.replace( /\/?$/, '/' ) }images/spinner.gif`
			: '/wp-admin/images/spinner.gif';

		$select.css( '--rsl-ie-loading-spinner-url', `url("${ spinnerUrl }")` );
	},

	/**
	 * Load table columns dynamically
	 */
	loadTableColumns( tableName ) {
		Utils.ajax( 'rsl_ie_get_table_columns', {
			table_name: tableName,
		} )
			.then( ( columns ) => {
				if ( columns && Array.isArray( columns ) ) {
					// Store columns for later use
					this.tableColumns = columns;

					// Update all filter field dropdowns
					jQuery( '.rsl-ie-filter-field' ).each(
						( index, element ) => {
							const $fieldSelect = jQuery( element );
							const currentValue = $fieldSelect.val();

							// Clear and rebuild options
							$fieldSelect.empty();
							$fieldSelect.append(
								jQuery( '<option>' )
									.val( '' )
									.text( window.rslIeData.i18n.selectField )
							);

							// Add columns as options
							columns.forEach( ( column ) => {
								$fieldSelect.append(
									jQuery( '<option>' )
										.val( column.name )
										.text(
											column.name +
												' (' +
												column.type +
												')'
										)
										.data( 'type', column.data_type )
								);
							} );

							// Restore previous value if exists
							if ( currentValue ) {
								$fieldSelect.val( currentValue );
							}
						}
					);
				}
			} )
			.catch( ( error ) => {} );
	},

	/**
	 * Selected form for a form entries export, or null.
	 *
	 * @return {Object|null} { form_source, form_id }.
	 */
	getFormEntrySelection() {
		const value = String( jQuery( '#rsl-ie-form-entry-form' ).val() || '' );
		const match = value.match( /^(gravityforms|flamingo):(\d+)$/ );
		return match
			? { form_source: match[ 1 ], form_id: parseInt( match[ 2 ], 10 ) }
			: null;
	},

	/**
	 * Load the forms once.
	 */
	loadFormEntryForms() {
		const $select = jQuery( '#rsl-ie-form-entry-form' );
		if ( this.formEntryFormsLoaded || this.formEntryFormsLoading ) {
			return;
		}
		this.formEntryFormsLoading = true;
		$select.prop( 'disabled', true );
		this.setLoadingSelectSpinner( $select );
		$select.addClass( 'is-loading' );
		Utils.ajax( 'rsl_ie_form_entry_forms', {} )
			.then( ( response ) => {
				const i18n = window.rslIeData.i18n || {};
				const forms = ( response && response.forms ) || [];
				$select.empty();
				$select.append(
					jQuery( '<option>' )
						.val( '' )
						.text(
							forms.length
								? i18n.selectForm ||
										__(
											'Select a form...',
											'import-export-by-rockstarlab'
										)
								: i18n.noForms ||
										__(
											'No forms found',
											'import-export-by-rockstarlab'
										)
						)
				);
				const groups = {
					gravityforms: __(
						'Gravity Forms',
						'import-export-by-rockstarlab'
					),
					flamingo: __(
						'Contact Form 7 (Flamingo)',
						'import-export-by-rockstarlab'
					),
				};
				Object.keys( groups ).forEach( ( source ) => {
					const items = forms.filter( ( f ) => f.source === source );
					if ( ! items.length ) {
						return;
					}
					const $group = jQuery( '<optgroup>' ).attr(
						'label',
						groups[ source ]
					);
					items.forEach( ( form ) => {
						$group.append(
							jQuery( '<option>' )
								.val( `${ form.source }:${ form.form_id }` )
								.text( `${ form.title } (${ form.entries })` )
						);
					} );
					$select.append( $group );
				} );
				this.formEntryFormsLoaded = true;
			} )
			.catch( ( error ) => {
				$select.empty().append(
					jQuery( '<option>' )
						.val( '' )
						.text(
							( error && error.message ) ||
								__( 'Error', 'import-export-by-rockstarlab' )
						)
				);
			} )
			.finally( () => {
				this.formEntryFormsLoading = false;
				$select.prop( 'disabled', false ).removeClass( 'is-loading' );
				this.updateStep2NextButton();
			} );
	},

	/**
	 * A form was chosen: load its columns, reset the filters and count.
	 */
	onFormEntryFormChange() {
		const selection = this.getFormEntrySelection();
		jQuery( '#rsl-ie-filters-list' ).empty();
		this.formEntryFields = [];
		jQuery( '.rsl-ie-form-entry-error' ).hide().text( '' );
		if ( ! selection ) {
			this.refreshCount( false );
			return;
		}
		const token = ( this.formEntryFieldsToken || 0 ) + 1;
		this.formEntryFieldsToken = token;
		jQuery( '.rsl-ie-form-entry-section .spinner' ).addClass( 'is-active' );
		Utils.ajax( 'rsl_ie_form_entry_fields', selection )
			.then( ( response ) => {
				if ( token !== this.formEntryFieldsToken ) {
					return;
				}
				this.formEntryFields = ( response && response.fields ) || [];
				this.formEntryDefaults =
					( response && response.default_fields ) || [];
			} )
			.catch( ( error ) => {
				jQuery( '.rsl-ie-form-entry-error' )
					.text( ( error && error.message ) || '' )
					.show();
			} )
			.finally( () => {
				jQuery( '.rsl-ie-form-entry-section .spinner' ).removeClass(
					'is-active'
				);
				this.refreshCount( false );
			} );
	},

	/**
	 * Field groups of the selected form for the filter and field steps.
	 *
	 * @return {Array} Groups.
	 */
	getFormEntryFieldGroups() {
		const i18n = window.rslIeData.i18n || {};
		const fields = this.formEntryFields || [];
		if ( ! fields.length ) {
			return [
				{
					label:
						i18n.fieldGroupFormEntry ||
						__( 'Entry', 'import-export-by-rockstarlab' ),
					options: [
						{
							value: '_select_form',
							label:
								i18n.pleaseSelectForm ||
								__(
									'Please choose a form first.',
									'import-export-by-rockstarlab'
								),
							type: 'info',
						},
					],
				},
			];
		}
		const entry = [ 'entry_id', 'date_created', 'status' ];
		const visitor = [ 'source_url', 'ip', 'user_agent', 'user' ];
		const types = {
			entry_id: 'number',
			date_created: 'date',
			source_url: 'url',
		};
		const toOption = ( f ) => ( {
			value: f.name,
			label: f.label,
			type: types[ f.name ] || 'string',
		} );
		return [
			{
				label:
					i18n.fieldGroupFormEntry ||
					__( 'Entry', 'import-export-by-rockstarlab' ),
				options: fields
					.filter( ( f ) => entry.includes( f.name ) )
					.map( toOption ),
			},
			{
				label:
					i18n.fieldGroupFormFields ||
					__( 'Form fields', 'import-export-by-rockstarlab' ),
				options: fields
					.filter(
						( f ) =>
							! entry.includes( f.name ) &&
							! visitor.includes( f.name )
					)
					.map( toOption ),
			},
			{
				label:
					i18n.fieldGroupFormVisitor ||
					__( 'Visitor', 'import-export-by-rockstarlab' ),
				options: fields
					.filter( ( f ) => visitor.includes( f.name ) )
					.map( toOption ),
			},
		];
	},

	/**
	 * Get fields by content type
	 */
	getFieldsByContentType( contentType ) {
		const baseFields = [
			{
				label: window.rslIeData.i18n.fieldGroupStandard,
				options: [
					{
						value: 'ID',
						label: window.rslIeData.i18n.fieldId,
						type: 'number',
					},
					{
						value: 'post_title',
						label: window.rslIeData.i18n.fieldTitle,
						type: 'string',
					},
					{
						value: 'post_content',
						label: window.rslIeData.i18n.fieldContent,
						type: 'string',
					},
					{
						value: 'post_excerpt',
						label: window.rslIeData.i18n.fieldExcerpt,
						type: 'string',
					},
					{
						value: 'post_date',
						label: window.rslIeData.i18n.fieldDate,
						type: 'date',
					},
					{
						value: 'post_name',
						label: window.rslIeData.i18n.fieldSlug,
						type: 'string',
					},
					{
						value: 'post_status',
						label: window.rslIeData.i18n.fieldStatus,
						type: 'string',
					},
				],
			},
			{
				label: window.rslIeData.i18n.fieldGroupAuthor,
				options: [
					{
						value: 'post_author',
						label: window.rslIeData.i18n.fieldAuthorId,
						type: 'number',
					},
					{
						value: 'author_name',
						label: window.rslIeData.i18n.fieldAuthorName,
						type: 'string',
					},
					{
						value: 'author_email',
						label: window.rslIeData.i18n.fieldAuthorEmail,
						type: 'string',
					},
				],
			},
			{
				label:
					window.rslIeData.i18n.fieldGroupFeaturedImage ||
					__( 'Featured Image', 'import-export-by-rockstarlab' ),
				options: [
					{
						value: 'featured_image_id',
						label:
							window.rslIeData.i18n.fieldFeaturedImageId ||
							__(
								'Featured Image ID',
								'import-export-by-rockstarlab'
							),
						type: 'number',
					},
					{
						value: 'featured_image_url',
						label:
							window.rslIeData.i18n.fieldFeaturedImageUrl ||
							__(
								'Featured Image URL',
								'import-export-by-rockstarlab'
							),
						type: 'url',
					},
					{
						value: 'featured_image_title',
						label:
							window.rslIeData.i18n.fieldFeaturedImageTitle ||
							__(
								'Featured Image Title',
								'import-export-by-rockstarlab'
							),
						type: 'string',
					},
					{
						value: 'featured_image_caption',
						label:
							window.rslIeData.i18n.fieldFeaturedImageCaption ||
							__(
								'Featured Image Caption',
								'import-export-by-rockstarlab'
							),
						type: 'string',
					},
					{
						value: 'featured_image_alt',
						label: __(
							'Featured Image Alt Text',
							'import-export-by-rockstarlab'
						),
						type: 'string',
					},
				],
			},
			{
				label: window.rslIeData.i18n.fieldGroupOther,
				options: [
					{
						value: 'post_parent',
						label: window.rslIeData.i18n.fieldParentId,
						type: 'number',
					},
					{
						value: 'menu_order',
						label:
							window.rslIeData.i18n.fieldMenuOrder ||
							__( 'Menu Order', 'import-export-by-rockstarlab' ),
						type: 'number',
					},
					{
						value: 'is_sticky',
						label:
							window.rslIeData.i18n.fieldSticky ||
							__( 'Sticky', 'import-export-by-rockstarlab' ),
						type: 'boolean',
					},
					{
						value: 'comment_status',
						label: window.rslIeData.i18n.fieldCommentStatus,
						type: 'string',
					},
					{
						value: 'post_password',
						label:
							window.rslIeData.i18n.fieldPostPassword ||
							__(
								'Post Password',
								'import-export-by-rockstarlab'
							),
						type: 'string',
					},
					{
						value: 'post_modified',
						label: window.rslIeData.i18n.fieldModifiedDate,
						type: 'date',
					},
					{
						value: '_wp_page_template',
						label: window.rslIeData.i18n.fieldTemplate,
						type: 'string',
					},
				],
			},
			{
				label: window.rslIeData.i18n.fieldGroupCustomFilters,
				options: [
					{
						value: '_custom_field',
						label: window.rslIeData.i18n.fieldCustomFieldMeta,
						type: 'custom_field',
					},
					{
						value: '_taxonomy_filter',
						label: window.rslIeData.i18n.fieldTaxonomyFilter,
						type: 'taxonomy_filter',
					},
					...languageFilterOptions(),
				],
			},
		];

		// Customize based on content type
		if ( contentType === 'media' ) {
			return [
				{
					label: window.rslIeData.i18n.fieldGroupBasic,
					options: [
						{
							value: 'ID',
							label:
								window.rslIeData.i18n.fieldId ||
								__( 'ID', 'import-export-by-rockstarlab' ),
							type: 'number',
						},
						{
							value: 'post_title',
							label: window.rslIeData.i18n.fieldTitle,
							type: 'string',
						},
						{
							value: 'post_content',
							label: window.rslIeData.i18n.fieldDescription,
							type: 'string',
						},
						{
							value: 'post_excerpt',
							label: window.rslIeData.i18n.fieldCaption,
							type: 'string',
						},
						{
							value: 'alt_text',
							label: window.rslIeData.i18n.fieldAltText,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupFileInformation,
					options: [
						{
							value: 'guid',
							label: window.rslIeData.i18n.fieldFileUrlGuid,
							type: 'url',
						},
						{
							value: 'file_url',
							label: window.rslIeData.i18n.fieldFileUrl,
							type: 'url',
						},
						{
							value: 'file_path',
							label: window.rslIeData.i18n.fieldFilePathRelative,
							type: 'string',
						},
						{
							value: 'file_name',
							label: window.rslIeData.i18n.fieldFileName,
							type: 'string',
						},
						{
							value: 'file_extension',
							label: window.rslIeData.i18n.fieldFileExtension,
							type: 'string',
						},
						{
							value: 'post_mime_type',
							label: window.rslIeData.i18n.fieldMimeType,
							type: 'string',
						},
						{
							value: 'file_size',
							label: window.rslIeData.i18n.fieldFileSizeBytes,
							type: 'number',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupImageDimensions,
					options: [
						{
							value: 'width',
							label: window.rslIeData.i18n.fieldWidthPx,
							type: 'number',
						},
						{
							value: 'height',
							label: window.rslIeData.i18n.fieldHeightPx,
							type: 'number',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupDates,
					options: [
						{
							value: 'post_date',
							label: window.rslIeData.i18n.fieldUploadDate,
							type: 'date',
						},
						{
							value: 'post_modified',
							label: window.rslIeData.i18n.fieldModifiedDate,
							type: 'date',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupAuthor,
					options: [
						{
							value: 'post_author',
							label: window.rslIeData.i18n.fieldAuthorId,
							type: 'number',
						},
						{
							value: 'author_name',
							label: window.rslIeData.i18n.fieldAuthorName,
							type: 'string',
						},
						{
							value: 'author_email',
							label: window.rslIeData.i18n.fieldAuthorEmail,
							type: 'email',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupAttachment,
					options: [
						{
							value: 'post_parent',
							label: window.rslIeData.i18n.fieldAttachedToPostId,
							type: 'number',
						},
						{
							value: 'attached_post_title',
							label: window.rslIeData.i18n.fieldAttachedPostTitle,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupCustomFilters,
					options: [
						{
							value: '_custom_field',
							label: window.rslIeData.i18n.fieldCustomFieldMeta,
							type: 'custom_field',
						},
					],
				},
			];
		}

		// Pages don't have taxonomy section (but taxonomy_filter is still available in Custom Filters)
		if ( contentType === 'page' ) {
			return baseFields.filter(
				( group ) =>
					group.label !== window.rslIeData.i18n.fieldGroupTaxonomy
			);
		}

		// Menus
		if ( contentType === 'menu' ) {
			return [
				{
					label: window.rslIeData.i18n.fieldGroupBasic,
					options: [
						{
							value: 'term_id',
							label: __(
								'Source Menu ID',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'name',
							label: window.rslIeData.i18n.fieldMenuName,
							type: 'string',
						},
						{
							value: 'menu_items',
							label:
								__(
									'Menu Items Portable JSON',
									'import-export-by-rockstarlab'
								) +
								' ' +
								( window.rslIeData.i18n.includesAcfFields ||
									__(
										'(includes ACF fields)',
										'import-export-by-rockstarlab'
									) ),
							type: 'array',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupDetails,
					options: [
						{
							value: 'count',
							label: window.rslIeData.i18n.fieldItemsCount,
							type: 'number',
						},
						{
							value: 'locations',
							label: window.rslIeData.i18n.fieldThemeLocations,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupCustomFilters,
					options: [
						{
							value: '_custom_field',
							label: window.rslIeData.i18n.fieldCustomFieldMeta,
							type: 'custom_field',
						},
					],
				},
			];
		}

		// Users
		if ( contentType === 'user' ) {
			return [
				{
					label: window.rslIeData.i18n.fieldGroupBasic,
					options: [
						{
							value: 'ID',
							label:
								window.rslIeData.i18n.fieldId ||
								__( 'ID', 'import-export-by-rockstarlab' ),
							type: 'number',
						},
						{
							value: 'user_login',
							label:
								window.rslIeData.i18n.fieldUsername ||
								__(
									'Username',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'user_email',
							label:
								window.rslIeData.i18n.fieldEmail ||
								__( 'Email', 'import-export-by-rockstarlab' ),
							type: 'string',
						},
						{
							value: 'display_name',
							label:
								window.rslIeData.i18n.fieldDisplayName ||
								__(
									'Display name',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'user_nicename',
							label: window.rslIeData.i18n.fieldNiceName,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupProfile,
					options: [
						{
							value: 'first_name',
							label: window.rslIeData.i18n.fieldFirstName,
							type: 'string',
						},
						{
							value: 'last_name',
							label: window.rslIeData.i18n.fieldLastName,
							type: 'string',
						},
						{
							value: 'nickname',
							label: window.rslIeData.i18n.fieldNickname,
							type: 'string',
						},
						{
							value: 'description',
							label: window.rslIeData.i18n.fieldBio,
							type: 'string',
						},
						{
							value: 'user_url',
							label:
								window.rslIeData.i18n.fieldWebsite ||
								__( 'Website', 'import-export-by-rockstarlab' ),
							type: 'string',
						},
						{
							value: 'avatar_url',
							label: window.rslIeData.i18n.fieldAvatarUrl,
							type: 'string',
						},
					],
				},
				{
					label:
						window.rslIeData.i18n.fieldGroupSocialMedia ||
						__( 'Social Media', 'import-export-by-rockstarlab' ),
					options: [
						{
							value: 'facebook',
							label:
								window.rslIeData.i18n.fieldFacebook ||
								__(
									'Facebook profile URL',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'instagram',
							label:
								window.rslIeData.i18n.fieldInstagram ||
								__(
									'Instagram profile URL',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'linkedin',
							label:
								window.rslIeData.i18n.fieldLinkedIn ||
								__(
									'LinkedIn profile URL',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'myspace',
							label:
								window.rslIeData.i18n.fieldMySpace ||
								__(
									'MySpace profile URL',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'pinterest',
							label:
								window.rslIeData.i18n.fieldPinterest ||
								__(
									'Pinterest profile URL',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'soundcloud',
							label:
								window.rslIeData.i18n.fieldSoundCloud ||
								__(
									'SoundCloud profile URL',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'tumblr',
							label:
								window.rslIeData.i18n.fieldTumblr ||
								__(
									'Tumblr profile URL',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'wikipedia',
							label:
								window.rslIeData.i18n.fieldWikipedia ||
								__(
									'Wikipedia page about you',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'twitter',
							label:
								window.rslIeData.i18n.fieldTwitter ||
								__(
									'X username',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'youtube',
							label:
								window.rslIeData.i18n.fieldYouTube ||
								__(
									'YouTube profile URL',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupRolePermissions,
					options: [
						{
							value: 'role',
							label: window.rslIeData.i18n.fieldRole,
							type: 'string',
						},
						{
							value: 'roles',
							label:
								window.rslIeData.i18n.fieldRoles ||
								__( 'Roles', 'import-export-by-rockstarlab' ),
							type: 'array',
						},
						{
							value: 'capabilities',
							label: window.rslIeData.i18n.fieldCapabilitiesArray,
							type: 'array',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupPreferences,
					options: [
						{
							value: 'locale',
							label:
								window.rslIeData.i18n.fieldLanguage ||
								__(
									'Language',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'admin_color',
							label: window.rslIeData.i18n.fieldAdminColorScheme,
							type: 'string',
						},
						{
							value: 'rich_editing',
							label: window.rslIeData.i18n.fieldVisualEditor,
							type: 'boolean',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupStats,
					options: [
						{
							value: 'posts_count',
							label: window.rslIeData.i18n.fieldPostsCount,
							type: 'number',
						},
						{
							value: 'user_registered',
							label: window.rslIeData.i18n.fieldRegistrationDate,
							type: 'date',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupCustomFilters,
					options: [
						{
							value: 'user_meta',
							label:
								window.rslIeData.i18n.fieldUserMeta ||
								__(
									'User meta',
									'import-export-by-rockstarlab'
								),
							type: 'object',
						},
						{
							value: '_custom_field',
							label: window.rslIeData.i18n.fieldCustomFieldMeta,
							type: 'custom_field',
						},
					],
				},
			];
		}

		// Comments
		if ( contentType === 'comment' ) {
			return [
				{
					label: window.rslIeData.i18n.fieldGroupBasic,
					options: [
						{
							value: 'comment_ID',
							label: __(
								'Comment ID (local/source reference)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'source_comment_id',
							label: __(
								'Source Comment ID (for updates)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'comment_post_ID',
							label: __(
								'Post ID (source/local fallback)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'source_post_id',
							label: __(
								'Source Post ID (matches imported posts)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'post_permalink',
							label:
								window.rslIeData.i18n.fieldPermalink ||
								__(
									'Post Permalink (matching hint)',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'post_type',
							label:
								window.rslIeData.i18n.fieldPostType ||
								__(
									'Post type',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'post_slug',
							label: __(
								'Post Slug (recommended matching)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'comment_content',
							label: window.rslIeData.i18n.fieldCommentContent,
							type: 'string',
						},
						{
							value: 'comment_approved',
							label: window.rslIeData.i18n.fieldStatus,
							type: 'string',
						},
						{
							value: 'comment_type',
							label: window.rslIeData.i18n.fieldCommentType,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupAuthor,
					options: [
						{
							value: 'comment_author',
							label: window.rslIeData.i18n.fieldAuthorName,
							type: 'string',
						},
						{
							value: 'comment_author_email',
							label: window.rslIeData.i18n.fieldAuthorEmail,
							type: 'string',
						},
						{
							value: 'comment_author_url',
							label: window.rslIeData.i18n.fieldAuthorUrl,
							type: 'string',
						},
						{
							value: 'comment_author_IP',
							label: window.rslIeData.i18n.fieldAuthorIp,
							type: 'string',
						},
						{
							value: 'user_id',
							label:
								window.rslIeData.i18n.fieldUserId ||
								__( 'User ID', 'import-export-by-rockstarlab' ),
							type: 'number',
						},
						{
							value: 'comment_agent',
							label:
								window.rslIeData.i18n.fieldUserAgent ||
								__(
									'User agent',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupRelatedPost,
					options: [
						{
							value: 'post_title',
							label: window.rslIeData.i18n.fieldPostTitle,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupDates,
					options: [
						{
							value: 'comment_date',
							label: window.rslIeData.i18n.fieldCommentDate,
							type: 'date',
						},
						{
							value: 'comment_date_gmt',
							label: window.rslIeData.i18n.fieldCommentDateGmt,
							type: 'date',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupHierarchy,
					options: [
						{
							value: 'comment_parent',
							label: __(
								'Parent Comment ID (source reference)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'source_comment_parent_id',
							label: __(
								'Source Parent Comment ID (for threaded comments)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'comment_karma',
							label: window.rslIeData.i18n.fieldKarma,
							type: 'number',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupCustomFilters,
					options: [
						{
							value: 'comment_meta',
							label:
								window.rslIeData.i18n.fieldCommentMeta ||
								__(
									'Comment meta',
									'import-export-by-rockstarlab'
								),
							type: 'object',
						},
						{
							value: '_custom_field',
							label: window.rslIeData.i18n.fieldCustomFieldMeta,
							type: 'custom_field',
						},
					],
				},
			];
		}

		// Custom Post Types
		if ( contentType === 'custom_post_types' ) {
			return [
				{
					label: window.rslIeData.i18n.fieldGroupPostTypeSelection,
					options: [
						{
							value: '_post_type',
							label: window.rslIeData.i18n
								.fieldPostTypeSelectSpecific,
							type: 'post_type_selector',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupStandard,
					options: [
						{
							value: 'ID',
							label: window.rslIeData.i18n.fieldId,
							type: 'number',
						},
						{
							value: 'post_title',
							label: window.rslIeData.i18n.fieldTitle,
							type: 'string',
						},
						{
							value: 'post_content',
							label: window.rslIeData.i18n.fieldContent,
							type: 'string',
						},
						{
							value: 'post_excerpt',
							label: window.rslIeData.i18n.fieldExcerpt,
							type: 'string',
						},
						{
							value: 'post_date',
							label: window.rslIeData.i18n.fieldDate,
							type: 'date',
						},
						{
							value: 'post_name',
							label: window.rslIeData.i18n.fieldSlug,
							type: 'string',
						},
						{
							value: 'post_status',
							label: window.rslIeData.i18n.fieldStatus,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupAuthor,
					options: [
						{
							value: 'post_author',
							label: window.rslIeData.i18n.fieldAuthorId,
							type: 'number',
						},
						{
							value: 'author_name',
							label: window.rslIeData.i18n.fieldAuthorName,
							type: 'string',
						},
						{
							value: 'author_email',
							label: window.rslIeData.i18n.fieldAuthorEmail,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupOther,
					options: [
						{
							value: 'post_parent',
							label: window.rslIeData.i18n.fieldParentId,
							type: 'number',
						},
						{
							value: 'post_modified',
							label: window.rslIeData.i18n.fieldModifiedDate,
							type: 'date',
						},
						{
							value: 'is_sticky',
							label:
								window.rslIeData.i18n.fieldSticky ||
								__( 'Sticky', 'import-export-by-rockstarlab' ),
							type: 'boolean',
						},
						{
							value: '_wp_page_template',
							label: window.rslIeData.i18n.fieldTemplate,
							type: 'string',
						},
					],
				},
				{
					label:
						window.rslIeData.i18n.fieldGroupMedia ||
						__( 'Media', 'import-export-by-rockstarlab' ),
					options: [
						{
							value: 'featured_image',
							label:
								window.rslIeData.i18n.fieldFeaturedImage ||
								__(
									'Featured Image',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'featured_image_id',
							label:
								window.rslIeData.i18n.fieldFeaturedImageId ||
								__(
									'Featured Image ID',
									'import-export-by-rockstarlab'
								),
							type: 'number',
						},
						{
							value: 'featured_image_url',
							label:
								window.rslIeData.i18n.fieldFeaturedImageUrl ||
								__(
									'Featured Image URL',
									'import-export-by-rockstarlab'
								),
							type: 'url',
						},
						{
							value: 'featured_image_title',
							label:
								window.rslIeData.i18n.fieldFeaturedImageTitle ||
								__(
									'Featured Image Title',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'featured_image_caption',
							label:
								window.rslIeData.i18n
									.fieldFeaturedImageCaption ||
								'Featured Image Caption',
							type: 'string',
						},
						{
							value: 'featured_image_alt',
							label: __(
								'Featured Image Alt Text',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupCustomFilters,
					options: [
						{
							value: '_custom_field',
							label: window.rslIeData.i18n.fieldCustomFieldMeta,
							type: 'custom_field',
						},
						{
							value: '_taxonomy_filter',
							label: window.rslIeData.i18n.fieldTaxonomyFilter,
							type: 'taxonomy_filter',
						},
						...languageFilterOptions(),
					],
				},
			];
		} // Taxonomy
		if ( contentType === 'taxonomy' ) {
			return [
				{
					label: window.rslIeData.i18n.fieldGroupTaxonomySelection,
					options: [
						{
							value: '_taxonomy',
							label: window.rslIeData.i18n
								.fieldTaxonomySelectSpecific,
							type: 'taxonomy_selector',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupBasic,
					options: [
						{
							value: 'term_id',
							label: window.rslIeData.i18n.fieldTermId,
							type: 'number',
						},
						{
							value: 'name',
							label: window.rslIeData.i18n.fieldTermName,
							type: 'string',
						},
						{
							value: 'slug',
							label: window.rslIeData.i18n.fieldTermSlug,
							type: 'string',
						},
						{
							value: 'taxonomy',
							label: __(
								'Taxonomy',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'term_taxonomy_id',
							label: __(
								'Term Taxonomy ID',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'term_group',
							label: __(
								'Term Group',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'description',
							label: window.rslIeData.i18n.fieldDescription,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupHierarchy,
					options: [
						{
							value: 'parent',
							label: window.rslIeData.i18n.fieldParentTermId,
							type: 'number',
						},
						{
							value: 'parent_slug',
							label: __(
								'Parent Slug',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'count',
							label: window.rslIeData.i18n.fieldPostsCount,
							type: 'number',
						},
					],
				},
				{
					label: __( 'Image', 'import-export-by-rockstarlab' ),
					options: [
						{
							value: 'image_url',
							label: __(
								'Image URL',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
					],
				},
				{
					label: __(
						'Custom Fields (Term Meta)',
						'import-export-by-rockstarlab'
					),
					options: [
						{
							value: 'term_meta',
							label: __(
								'Term Meta (JSON)',
								'import-export-by-rockstarlab'
							),
							type: 'object',
						},
					],
				},
			];
		}

		// WooCommerce Products
		if ( contentType === 'woo_product' ) {
			return [
				{
					label: window.rslIeData.i18n.fieldGroupBasic,
					options: [
						{
							value: 'ID',
							label: window.rslIeData.i18n.fieldProductId,
							type: 'number',
						},
						{
							value: 'post_title',
							label: window.rslIeData.i18n.fieldProductName,
							type: 'string',
						},
						{
							value: 'post_name',
							label: window.rslIeData.i18n.fieldSlug,
							type: 'string',
						},
						{
							value: 'post_status',
							label: window.rslIeData.i18n.fieldStatus,
							type: 'string',
						},
						{
							value: 'sku',
							label: window.rslIeData.i18n.fieldSku,
							type: 'string',
						},
						{
							value: 'post_author',
							label: window.rslIeData.i18n.fieldAuthorId,
							type: 'number',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupContent,
					options: [
						{
							value: 'post_content',
							label: window.rslIeData.i18n.fieldDescription,
							type: 'string',
						},
						{
							value: 'post_excerpt',
							label: window.rslIeData.i18n.fieldShortDescription,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupPricing,
					options: [
						{
							value: 'regular_price',
							label: window.rslIeData.i18n.fieldRegularPrice,
							type: 'number',
						},
						{
							value: 'sale_price',
							label: window.rslIeData.i18n.fieldSalePrice,
							type: 'number',
						},
						{
							value: 'tax_status',
							label: window.rslIeData.i18n.fieldTaxStatus,
							type: 'string',
						},
						{
							value: 'tax_class',
							label: window.rslIeData.i18n.fieldTaxClass,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupInventory,
					options: [
						{
							value: 'stock_quantity',
							label: window.rslIeData.i18n.fieldStockQuantity,
							type: 'number',
						},
						{
							value: 'stock_status',
							label: window.rslIeData.i18n.fieldStockStatus,
							type: 'string',
						},
						{
							value: 'manage_stock',
							label: window.rslIeData.i18n.fieldManageStock,
							type: 'boolean',
						},
						{
							value: 'backorders',
							label: window.rslIeData.i18n.fieldBackorders,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupProductType,
					options: [
						{
							value: 'product_type',
							label: window.rslIeData.i18n.fieldProductType,
							type: 'string',
						},
						{
							value: 'parent_sku',
							label:
								window.rslIeData.i18n.fieldParentSku ||
								__(
									'Parent SKU',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'product_title',
							label:
								window.rslIeData.i18n.fieldProductTitle ||
								__(
									'Product Title (parent on variation rows)',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'product_slug',
							label:
								window.rslIeData.i18n.fieldProductSlug ||
								__(
									'Product Slug (parent on variation rows)',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'variation_attributes',
							label:
								window.rslIeData.i18n.fieldVariationOptions ||
								__(
									'Variation Options',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						...[ 1, 2, 3 ].flatMap( ( n ) => [
							{
								value: `option${ n }_name`,
								label: (
									window.rslIeData.i18n.fieldOptionName ||
									__(
										'Option %d Name',
										'import-export-by-rockstarlab'
									)
								).replace( '%d', n ),
								type: 'string',
							},
							{
								value: `option${ n }_value`,
								label: (
									window.rslIeData.i18n.fieldOptionValue ||
									__(
										'Option %d Value',
										'import-export-by-rockstarlab'
									)
								).replace( '%d', n ),
								type: 'string',
							},
						] ),
						{
							value: 'product_url',
							label: __(
								'External Product URL',
								'import-export-by-rockstarlab'
							),
							type: 'url',
						},
						{
							value: 'button_text',
							label: __(
								'External Button Text',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'downloadable',
							label: window.rslIeData.i18n.fieldDownloadable,
							type: 'boolean',
						},
						{
							value: 'virtual',
							label: window.rslIeData.i18n.fieldVirtual,
							type: 'boolean',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupShipping,
					options: [
						{
							value: 'weight',
							label: window.rslIeData.i18n.fieldWeight,
							type: 'number',
						},
						{
							value: 'length',
							label: window.rslIeData.i18n.fieldLength,
							type: 'number',
						},
						{
							value: 'width',
							label: window.rslIeData.i18n.fieldWidth,
							type: 'number',
						},
						{
							value: 'height',
							label: window.rslIeData.i18n.fieldHeight,
							type: 'number',
						},
						{
							value: 'shipping_class',
							label: window.rslIeData.i18n.fieldShippingClass,
							type: 'string',
						},
					],
				},
				{
					label:
						window.rslIeData.i18n.fieldGroupFeaturedImage ||
						__( 'Featured Image', 'import-export-by-rockstarlab' ),
					options: [
						{
							value: 'featured_image_id',
							label:
								window.rslIeData.i18n.fieldFeaturedImageId ||
								__(
									'Featured Image ID',
									'import-export-by-rockstarlab'
								),
							type: 'number',
						},
						{
							value: 'featured_image_url',
							label:
								window.rslIeData.i18n.fieldFeaturedImageUrl ||
								__(
									'Featured Image URL',
									'import-export-by-rockstarlab'
								),
							type: 'url',
						},
						{
							value: 'featured_image_title',
							label:
								window.rslIeData.i18n.fieldFeaturedImageTitle ||
								__(
									'Featured Image Title',
									'import-export-by-rockstarlab'
								),
							type: 'string',
						},
						{
							value: 'featured_image_caption',
							label:
								window.rslIeData.i18n
									.fieldFeaturedImageCaption ||
								'Featured Image Caption',
							type: 'string',
						},
						{
							value: 'featured_image_alt',
							label: __(
								'Featured Image Alt Text',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupMedia,
					options: [
						{
							value: 'product_gallery',
							label: window.rslIeData.i18n.fieldGalleryImages,
							type: 'array',
						},
						{
							value: 'variations',
							label:
								window.rslIeData.i18n.fieldVariations ||
								__(
									'Variations (JSON)',
									'import-export-by-rockstarlab'
								),
							type: 'json',
						},
						{
							value: 'product_attributes',
							label: __(
								'Product Attributes (JSON)',
								'import-export-by-rockstarlab'
							),
							type: 'json',
						},
						{
							value: 'grouped_products',
							label: __(
								'Grouped Products (JSON)',
								'import-export-by-rockstarlab'
							),
							type: 'json',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupTaxonomy,
					options: [
						{
							value: 'product_cat',
							label: window.rslIeData.i18n.fieldCategories,
							type: 'string',
						},
						{
							value: 'product_tag',
							label: window.rslIeData.i18n.fieldTags,
							type: 'string',
						},
						{
							value: 'product_brand',
							label: __(
								'Brands',
								'import-export-by-rockstarlab'
							),
							type: 'json',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupReviews,
					options: [
						{
							value: 'average_rating',
							label: window.rslIeData.i18n.fieldAverageRating,
							type: 'number',
						},
						{
							value: 'review_count',
							label: window.rslIeData.i18n.fieldReviewCount,
							type: 'number',
						},
						{
							value: 'comment_status',
							label: window.rslIeData.i18n.fieldReviewsEnabled,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupVisibility,
					options: [
						{
							value: 'featured',
							label: window.rslIeData.i18n.fieldFeatured,
							type: 'boolean',
						},
						{
							value: 'visibility',
							label: window.rslIeData.i18n.fieldCatalogVisibility,
							type: 'string',
						},
						{
							value: 'total_sales',
							label: window.rslIeData.i18n.fieldTotalSales,
							type: 'number',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupDates,
					options: [
						{
							value: 'post_date',
							label: window.rslIeData.i18n.fieldCreatedDate,
							type: 'date',
						},
						{
							value: 'post_modified',
							label: window.rslIeData.i18n.fieldModifiedDate,
							type: 'date',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupCustomFilters,
					options: [
						{
							value: '_custom_field',
							label: window.rslIeData.i18n.fieldCustomFieldMeta,
							type: 'custom_field',
						},
						{
							value: '_taxonomy_filter',
							label: window.rslIeData.i18n.fieldTaxonomyFilter,
							type: 'taxonomy_filter',
						},
						...languageFilterOptions(),
					],
				},
			];
		}

		// WooCommerce Orders
		if ( contentType === 'woo_order' ) {
			return [
				{
					label: window.rslIeData.i18n.fieldGroupBasic,
					options: [
						{
							value: 'ID',
							label: window.rslIeData.i18n.fieldOrderId,
							type: 'number',
						},
						{
							value: 'order_number',
							label: window.rslIeData.i18n.fieldOrderNumber,
							type: 'string',
						},
						{
							value: 'order_status',
							label: window.rslIeData.i18n.fieldStatus,
							type: 'string',
						},
						{
							value: 'order_key',
							label: window.rslIeData.i18n.fieldOrderKey,
							type: 'string',
						},
						{
							value: 'currency',
							label: window.rslIeData.i18n.fieldCurrency,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupAmounts,
					options: [
						{
							value: 'order_total',
							label: window.rslIeData.i18n.fieldOrderTotal,
							type: 'number',
						},
						{
							value: 'order_subtotal',
							label: window.rslIeData.i18n.fieldSubtotal,
							type: 'number',
						},
						{
							value: 'order_tax',
							label: window.rslIeData.i18n.fieldTax,
							type: 'number',
						},
						{
							value: 'order_shipping',
							label: window.rslIeData.i18n.fieldShipping,
							type: 'number',
						},
						{
							value: 'order_discount',
							label: window.rslIeData.i18n.fieldDiscount,
							type: 'number',
						},
						{
							value: 'cart_tax',
							label: __(
								'Cart Tax',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'shipping_tax',
							label: __(
								'Shipping Tax',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'total_tax',
							label: __(
								'Total Tax',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'tax_lines',
							label: __(
								'Tax Lines (Array)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupCustomer,
					options: [
						{
							value: 'customer_id',
							label: window.rslIeData.i18n.fieldCustomerId,
							type: 'number',
						},
						{
							value: 'billing_email',
							label: window.rslIeData.i18n.fieldEmail,
							type: 'string',
						},
						{
							value: 'customer_note',
							label: window.rslIeData.i18n.fieldCustomerNote,
							type: 'string',
						},
						{
							value: 'customer_ip_address',
							label: __(
								'Customer IP Address',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'customer_user_agent',
							label: __(
								'Customer User Agent',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupBillingAddress,
					options: [
						{
							value: 'billing_first_name',
							label: window.rslIeData.i18n.fieldFirstName,
							type: 'string',
						},
						{
							value: 'billing_last_name',
							label: window.rslIeData.i18n.fieldLastName,
							type: 'string',
						},
						{
							value: 'billing_company',
							label: window.rslIeData.i18n.fieldCompany,
							type: 'string',
						},
						{
							value: 'billing_address_1',
							label: window.rslIeData.i18n.fieldAddress1,
							type: 'string',
						},
						{
							value: 'billing_address_2',
							label: window.rslIeData.i18n.fieldAddress2,
							type: 'string',
						},
						{
							value: 'billing_city',
							label: window.rslIeData.i18n.fieldCity,
							type: 'string',
						},
						{
							value: 'billing_state',
							label: window.rslIeData.i18n.fieldState,
							type: 'string',
						},
						{
							value: 'billing_postcode',
							label: window.rslIeData.i18n.fieldPostcode,
							type: 'string',
						},
						{
							value: 'billing_country',
							label: window.rslIeData.i18n.fieldCountry,
							type: 'string',
						},
						{
							value: 'billing_phone',
							label: window.rslIeData.i18n.fieldPhone,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupShippingAddress,
					options: [
						{
							value: 'shipping_first_name',
							label: window.rslIeData.i18n.fieldFirstName,
							type: 'string',
						},
						{
							value: 'shipping_last_name',
							label: window.rslIeData.i18n.fieldLastName,
							type: 'string',
						},
						{
							value: 'shipping_company',
							label: window.rslIeData.i18n.fieldCompany,
							type: 'string',
						},
						{
							value: 'shipping_address_1',
							label: window.rslIeData.i18n.fieldAddress1,
							type: 'string',
						},
						{
							value: 'shipping_address_2',
							label: window.rslIeData.i18n.fieldAddress2,
							type: 'string',
						},
						{
							value: 'shipping_city',
							label: window.rslIeData.i18n.fieldCity,
							type: 'string',
						},
						{
							value: 'shipping_state',
							label: window.rslIeData.i18n.fieldState,
							type: 'string',
						},
						{
							value: 'shipping_postcode',
							label: window.rslIeData.i18n.fieldPostcode,
							type: 'string',
						},
						{
							value: 'shipping_country',
							label: window.rslIeData.i18n.fieldCountry,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupOrderItems,
					options: [
						{
							value: 'order_items',
							label: window.rslIeData.i18n.fieldOrderItemsArray,
							type: 'array',
						},
						{
							value: 'item_name',
							label: __(
								'Item Name',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'item_sku',
							label: __(
								'Item SKU',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'item_quantity',
							label: __(
								'Item Quantity',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'item_subtotal',
							label: __(
								'Item Subtotal',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'item_total',
							label: __(
								'Item Total',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'item_tax',
							label: __(
								'Item Tax',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'item_product_id',
							label: __(
								'Item Product ID',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'item_variation_id',
							label: __(
								'Item Variation ID',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'item_count',
							label: window.rslIeData.i18n.fieldItemCount,
							type: 'number',
						},
						{
							value: 'shipping_lines',
							label: __(
								'Shipping Lines',
								'import-export-by-rockstarlab'
							),
							type: 'array',
						},
						{
							value: 'fee_lines',
							label: __(
								'Fee Lines',
								'import-export-by-rockstarlab'
							),
							type: 'array',
						},
						{
							value: 'coupon_lines',
							label: __(
								'Coupon Lines',
								'import-export-by-rockstarlab'
							),
							type: 'array',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupPayment,
					options: [
						{
							value: 'payment_method',
							label: window.rslIeData.i18n.fieldPaymentMethod,
							type: 'string',
						},
						{
							value: 'payment_method_title',
							label: window.rslIeData.i18n
								.fieldPaymentMethodTitle,
							type: 'string',
						},
						{
							value: 'transaction_id',
							label: window.rslIeData.i18n.fieldTransactionId,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupShipping,
					options: [
						{
							value: 'shipping_method',
							label: window.rslIeData.i18n.fieldShippingMethod,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupDates,
					options: [
						{
							value: 'order_date',
							label: window.rslIeData.i18n.fieldOrderDate,
							type: 'date',
						},
						{
							value: 'date_modified',
							label: __(
								'Date Modified',
								'import-export-by-rockstarlab'
							),
							type: 'date',
						},
						{
							value: 'completed_date',
							label: window.rslIeData.i18n.fieldCompletedDate,
							type: 'date',
						},
						{
							value: 'paid_date',
							label: window.rslIeData.i18n.fieldPaidDate,
							type: 'date',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupNotes,
					options: [
						{
							value: 'order_notes',
							label: window.rslIeData.i18n.fieldOrderNotesArray,
							type: 'array',
						},
						{
							value: 'refunds',
							label:
								window.rslIeData.i18n.fieldRefundsArray ||
								__(
									'Refunds (Array)',
									'import-export-by-rockstarlab'
								),
							type: 'array',
						},
						{
							value: 'order_meta',
							label: __(
								'Order Meta',
								'import-export-by-rockstarlab'
							),
							type: 'array',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupCustomFilters,
					options: [
						{
							value: '_custom_field',
							label: window.rslIeData.i18n.fieldCustomFieldMeta,
							type: 'custom_field',
						},
					],
				},
			];
		}

		// WooCommerce Coupons
		if ( contentType === 'woo_coupon' ) {
			return [
				{
					label: window.rslIeData.i18n.fieldGroupBasic,
					options: [
						{
							value: 'ID',
							label: window.rslIeData.i18n.fieldCouponId,
							type: 'number',
						},
						{
							value: 'post_title',
							label: window.rslIeData.i18n.fieldCouponCode,
							type: 'string',
						},
						{
							value: 'post_excerpt',
							label: window.rslIeData.i18n.fieldDescription,
							type: 'string',
						},
						{
							value: 'post_status',
							label: window.rslIeData.i18n.fieldStatus,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupDiscount,
					options: [
						{
							value: 'discount_type',
							label: window.rslIeData.i18n.fieldDiscountType,
							type: 'string',
						},
						{
							value: 'coupon_amount',
							label: window.rslIeData.i18n.fieldCouponAmount,
							type: 'number',
						},
						{
							value: 'free_shipping',
							label: window.rslIeData.i18n.fieldFreeShipping,
							type: 'boolean',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupUsageRestrictions,
					options: [
						{
							value: 'minimum_amount',
							label: window.rslIeData.i18n.fieldMinimumSpend,
							type: 'number',
						},
						{
							value: 'maximum_amount',
							label: window.rslIeData.i18n.fieldMaximumSpend,
							type: 'number',
						},
						{
							value: 'individual_use',
							label: window.rslIeData.i18n.fieldIndividualUseOnly,
							type: 'boolean',
						},
						{
							value: 'exclude_sale_items',
							label: window.rslIeData.i18n.fieldExcludeSaleItems,
							type: 'boolean',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupProductRestrictions,
					options: [
						{
							value: 'product_ids',
							label: window.rslIeData.i18n.fieldAllowedProducts,
							type: 'array',
						},
						{
							value: 'excluded_product_ids',
							label: window.rslIeData.i18n.fieldExcludedProducts,
							type: 'array',
						},
						{
							value: 'product_categories',
							label: window.rslIeData.i18n.fieldAllowedCategories,
							type: 'array',
						},
						{
							value: 'excluded_product_categories',
							label: window.rslIeData.i18n
								.fieldExcludedCategories,
							type: 'array',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupEmailRestrictions,
					options: [
						{
							value: 'allowed_emails',
							label: window.rslIeData.i18n.fieldAllowedEmails,
							type: 'array',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupUsageLimits,
					options: [
						{
							value: 'usage_count',
							label: window.rslIeData.i18n.fieldUsageCount,
							type: 'number',
						},
						{
							value: 'usage_limit',
							label: window.rslIeData.i18n.fieldUsageLimitTotal,
							type: 'number',
						},
						{
							value: 'usage_limit_per_user',
							label: window.rslIeData.i18n.fieldUsageLimitPerUser,
							type: 'number',
						},
						{
							value: 'limit_usage_to_x_items',
							label: __(
								'Limit Usage to X Items',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						// Customers who used the coupon (billing email or user ID), one entry per use.
						{
							value: 'used_by',
							label:
								window.rslIeData.i18n.fieldUsedBy ||
								__(
									'Used By (Array)',
									'import-export-by-rockstarlab'
								),
							type: 'array',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupDates,
					options: [
						{
							value: 'date_expires',
							label: window.rslIeData.i18n.fieldExpiryDate,
							type: 'date',
						},
						{
							value: 'post_date',
							label: window.rslIeData.i18n.fieldCreatedDate,
							type: 'date',
						},
						{
							value: 'post_modified',
							label: window.rslIeData.i18n.fieldModifiedDate,
							type: 'date',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupCustomFilters,
					options: [
						{
							value: '_custom_field',
							label: window.rslIeData.i18n.fieldCustomFieldMeta,
							type: 'custom_field',
						},
					],
				},
			];
		}

		// WooCommerce Attributes
		if ( contentType === 'woo_attribute' ) {
			return [
				{
					label: window.rslIeData.i18n.fieldGroupBasic,
					options: [
						{
							value: 'attribute_id',
							label: window.rslIeData.i18n.fieldAttributeId,
							type: 'number',
						},
						{
							value: 'attribute_name',
							label: window.rslIeData.i18n.fieldAttributeName,
							type: 'string',
						},
						{
							value: 'attribute_label',
							label: window.rslIeData.i18n.fieldAttributeLabel,
							type: 'string',
						},
						{
							value: 'attribute_type',
							label: window.rslIeData.i18n.fieldAttributeType,
							type: 'string',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupSettings,
					options: [
						{
							value: 'attribute_orderby',
							label: window.rslIeData.i18n.fieldDefaultSortOrder,
							type: 'string',
						},
						{
							value: 'attribute_public',
							label: window.rslIeData.i18n.fieldEnableArchives,
							type: 'boolean',
						},
					],
				},
				{
					label: window.rslIeData.i18n.fieldGroupTerms,
					options: [
						{
							value: 'term_count',
							label: window.rslIeData.i18n.fieldTermsCount,
							type: 'number',
						},
						{
							value: 'attribute_terms',
							label: window.rslIeData.i18n.fieldAllTermsArray,
							type: 'array',
						},
					],
				},
			];
		}

		if ( contentType === 'woo_review' ) {
			return [
				{
					label: __( 'Review', 'import-export-by-rockstarlab' ),
					options: [
						{
							value: 'comment_ID',
							label: __(
								'Review ID (local/source reference)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'source_comment_id',
							label: __(
								'Source Review ID (for updates)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'product_id',
							label: __(
								'Product ID (source/local fallback)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'source_product_id',
							label: __(
								'Source Product ID (matches imported products)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'product_title',
							label: __(
								'Product Title (fallback matching)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'product_slug',
							label: __(
								'Product Slug (recommended matching)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'product_sku',
							label: __(
								'Product SKU (best matching)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'comment_author',
							label: __(
								'Author',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'comment_author_email',
							label: __(
								'Author Email',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'comment_content',
							label: __(
								'Content',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'comment_approved',
							label: __(
								'Status',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'comment_parent',
							label: __(
								'Parent Review ID',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'rating',
							label: __(
								'Rating',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'verified',
							label: __(
								'Verified Owner',
								'import-export-by-rockstarlab'
							),
							type: 'boolean',
						},
						{
							value: 'comment_date',
							label: __( 'Date', 'import-export-by-rockstarlab' ),
							type: 'date',
						},
						{
							value: 'comment_meta',
							label: __(
								'Review Meta',
								'import-export-by-rockstarlab'
							),
							type: 'array',
						},
					],
				},
			];
		}

		if ( contentType === 'woo_refund' ) {
			return [
				{
					label: __( 'Refund', 'import-export-by-rockstarlab' ),
					options: [
						{
							value: 'refund_id',
							label: __(
								'Refund ID (local/source reference)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'source_refund_id',
							label: __(
								'Source Refund ID (for updates)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'parent_order_id',
							label: __(
								'Parent Order ID (source/local fallback)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'source_parent_order_id',
							label: __(
								'Source Parent Order ID (matches imported orders)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'parent_order_key',
							label: __(
								'Parent Order Key (best matching)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'parent_order_number',
							label: __(
								'Parent Order Number',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'status',
							label: __(
								'Status',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'date_created',
							label: __(
								'Date Created',
								'import-export-by-rockstarlab'
							),
							type: 'date',
						},
						{
							value: 'date_modified',
							label: __(
								'Date Modified',
								'import-export-by-rockstarlab'
							),
							type: 'date',
						},
						{
							value: 'amount',
							label: __(
								'Amount',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'reason',
							label: __(
								'Reason',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'refunded_by',
							label: __(
								'Refunded By User ID',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'currency',
							label: __(
								'Currency',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'line_items',
							label: __(
								'Line Items JSON',
								'import-export-by-rockstarlab'
							),
							type: 'json',
						},
						{
							value: 'meta',
							label: __(
								'Meta JSON',
								'import-export-by-rockstarlab'
							),
							type: 'json',
						},
					],
				},
			];
		}

		if ( contentType === 'woo_tax_rate' ) {
			return [
				{
					label: __( 'Tax Rate', 'import-export-by-rockstarlab' ),
					options: [
						{
							value: 'tax_rate_id',
							label: __(
								'Tax Rate ID (reference)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'tax_rate_class',
							label: __(
								'Tax Class (slug, empty = Standard)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'tax_rate_class_name',
							label: __(
								'Tax Class Name',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'tax_rate_country',
							label: __(
								'Country Code',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'tax_rate_state',
							label: __(
								'State Code',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'tax_rate_postcodes',
							label: __(
								'Postcodes / ZIPs (separated by ;)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'tax_rate_cities',
							label: __(
								'Cities (separated by ;)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'tax_rate',
							label: __(
								'Rate %',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'tax_rate_name',
							label: __(
								'Tax Name',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'tax_rate_priority',
							label: __(
								'Priority',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'tax_rate_compound',
							label: __(
								'Compound (1/0)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'tax_rate_shipping',
							label: __(
								'Shipping (1/0)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'tax_rate_order',
							label: __(
								'Order',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
					],
				},
			];
		}

		if ( contentType === 'woo_shipping_zone' ) {
			return [
				{
					label: __(
						'Shipping Zone',
						'import-export-by-rockstarlab'
					),
					options: [
						{
							value: 'zone_id',
							label: __(
								'Zone ID (reference, 0 = Locations not covered by your other zones)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'zone_name',
							label: __(
								'Zone Name (matches zones on import)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'zone_order',
							label: __(
								'Zone Order',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'zone_locations',
							label: __(
								'Zone Regions (country:DE, state:US:CA, postcode:90210)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'shipping_methods',
							label: __(
								'Shipping Methods JSON',
								'import-export-by-rockstarlab'
							),
							type: 'json',
						},
						{
							value: 'shipping_methods_summary',
							label: __(
								'Shipping Methods (summary)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
					],
				},
			];
		}

		if ( contentType === 'woo_customer' ) {
			return [
				{
					label: __( 'Customer', 'import-export-by-rockstarlab' ),
					options: [
						{
							value: 'customer_id',
							label: __(
								'Customer ID (reference)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'source_customer_id',
							label: __(
								'Source Customer ID (reference)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'user_id',
							label: __(
								'User ID (reference)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'source_user_id',
							label: __(
								'Source User ID (reference)',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'email',
							label: __(
								'Email (matches customers on import)',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'username',
							label: __(
								'Username',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'first_name',
							label: __(
								'First Name',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'last_name',
							label: __(
								'Last Name',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'display_name',
							label: __(
								'Display Name',
								'import-export-by-rockstarlab'
							),
							type: 'string',
						},
						{
							value: 'date_created',
							label: __(
								'Date Created',
								'import-export-by-rockstarlab'
							),
							type: 'date',
						},
						{
							value: 'date_modified',
							label: __(
								'Date Modified',
								'import-export-by-rockstarlab'
							),
							type: 'date',
						},
						{
							value: 'is_paying_customer',
							label: __(
								'Paying Customer',
								'import-export-by-rockstarlab'
							),
							type: 'boolean',
						},
						{
							value: 'order_count',
							label: __(
								'Order Count',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'total_spent',
							label: __(
								'Total Spent',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'last_order_id',
							label: __(
								'Last Order ID',
								'import-export-by-rockstarlab'
							),
							type: 'number',
						},
						{
							value: 'last_order_date',
							label: __(
								'Last Order Date',
								'import-export-by-rockstarlab'
							),
							type: 'date',
						},
					],
				},
				{
					label: __( 'Billing', 'import-export-by-rockstarlab' ),
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
						label: customerAddressLabel( value ),
						type: 'string',
					} ) ),
				},
				{
					label: __( 'Shipping', 'import-export-by-rockstarlab' ),
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
						label: customerAddressLabel( value ),
						type: 'string',
					} ) ),
				},
				{
					label: __( 'Meta', 'import-export-by-rockstarlab' ),
					options: [
						{
							value: 'meta',
							label: __(
								'Customer Meta JSON',
								'import-export-by-rockstarlab'
							),
							type: 'json',
						},
					],
				},
			];
		}

		if ( contentType === 'form_entries' ) {
			return this.getFormEntryFieldGroups();
		}

		// Database Table - use dynamic columns from selected table
		if ( contentType === 'database_table' ) {
			// If we have columns loaded, use them
			if (
				this.currentTableColumns &&
				this.currentTableColumns.length > 0
			) {
				const columnOptions = this.currentTableColumns.map( ( col ) => {
					const typeLabel = col.is_numeric
						? 'number'
						: col.is_date
						? 'date'
						: 'string';
					return {
						value: col.name,
						label: `${ col.name } (${ col.type })`,
						type: typeLabel,
						displayType: col.type,
					};
				} );

				return [
					{
						label: window.rslIeData.i18n.fieldGroupTableColumns,
						options: columnOptions,
					},
				];
			}

			// Otherwise show message to select table first
			return [
				{
					label: window.rslIeData.i18n.fieldGroupTableSelection,
					options: [
						{
							value: '_select_table',
							label: window.rslIeData.i18n.fieldPleaseSelectTable,
							type: 'info',
						},
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
			window.rslIeData.i18n.fieldGroupFeaturedImage ||
				__( 'Featured Image', 'import-export-by-rockstarlab' ),
		];

		// For woo_coupon, product restrictions (lists of product references) and custom
		// filters stay out of the filters. Discount, usage restrictions, allowed emails and
		// usage limits (Usage Count, Used By) filter on the exported values.
		if ( contentType === 'woo_coupon' ) {
			excludedLabels.push(
				window.rslIeData.i18n.fieldGroupProductRestrictions,
				window.rslIeData.i18n.fieldGroupCustomFilters
			);
		}

		return allFields
			.filter( ( group ) => ! excludedLabels.includes( group.label ) )
			.map( ( group ) => {
				return group;
			} )
			.filter( ( group ) => group.options.length > 0 );
	},

	/**
	 * Check if filter row is complete (has all required values)
	 */
	isFilterRowComplete( $row ) {
		const field = $row.find( '.rsl-ie-filter-field' ).val();
		const condition = $row.find( '.rsl-ie-filter-condition' ).val();
		const value = $row.find( '.rsl-ie-filter-value' ).val();

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
		const $field = $row.find( '.rsl-ie-filter-field' );
		const $condition = $row.find( '.rsl-ie-filter-condition' );
		const $value = $row.find( '.rsl-ie-filter-value' );

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
			// Always show the value input for other conditions
			$value.closest( '.rsl-ie-filter-value-wrap' ).show();
		}

		// For 'in' and 'not_in' conditions, always use text input to allow comma-separated values
		if ( condition === 'in' || condition === 'not_in' ) {
			$value.attr( 'type', 'text' );
			$value.attr(
				'placeholder',
				window.rslIeData.i18n.enterValuesCommaSeparated
			);
			return;
		}

		// For 'between' condition on numbers, use text to allow comma-separated range
		if ( condition === 'between' && fieldType === 'number' ) {
			$value.attr( 'type', 'text' );
			$value.attr(
				'placeholder',
				window.rslIeData.i18n.enterTwoNumbersCommaSeparated
			);
			return;
		}

		// Otherwise, set type based on field type
		if ( fieldType === 'date' ) {
			$value.attr( 'type', 'date' );
			$value.attr( 'placeholder', '' );
		} else if ( fieldType === 'number' ) {
			$value.attr( 'type', 'number' );
			$value.attr(
				'placeholder',
				window.rslIeData.i18n.enterNumberPlaceholder
			);
		} else {
			$value.attr( 'type', 'text' );
			$value.attr(
				'placeholder',
				window.rslIeData.i18n.enterFilterValue
			);
		}
	},

	/**
	 * Get conditions by field type
	 */
	getConditionsByFieldType( fieldType ) {
		const conditions = {
			string: [
				{ value: 'equals', label: window.rslIeData.i18n.equals },
				{ value: 'not_equals', label: window.rslIeData.i18n.notEquals },
				{ value: 'in', label: window.rslIeData.i18n.inFilter },
				{ value: 'not_in', label: window.rslIeData.i18n.notInFilter },
				{ value: 'contains', label: window.rslIeData.i18n.contains },
				{
					value: 'not_contains',
					label: window.rslIeData.i18n.notContains,
				},
				{ value: 'is_empty', label: window.rslIeData.i18n.isEmpty },
				{
					value: 'is_not_empty',
					label: window.rslIeData.i18n.isNotEmpty,
				},
			],
			number: [
				{ value: 'equals', label: window.rslIeData.i18n.equals },
				{ value: 'not_equals', label: window.rslIeData.i18n.notEquals },
				{ value: 'in', label: window.rslIeData.i18n.inFilter },
				{ value: 'not_in', label: window.rslIeData.i18n.notInFilter },
				{ value: 'greater', label: window.rslIeData.i18n.greaterThan },
				{
					value: 'equals_or_greater',
					label: window.rslIeData.i18n.greaterOrEqual,
				},
				{ value: 'less', label: window.rslIeData.i18n.lessThan },
				{
					value: 'equals_or_less',
					label: window.rslIeData.i18n.lessOrEqual,
				},
				{ value: 'is_empty', label: window.rslIeData.i18n.isEmpty },
				{
					value: 'is_not_empty',
					label: window.rslIeData.i18n.isNotEmpty,
				},
			],
			date: [
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
				{
					value: 'is_not_empty',
					label: window.rslIeData.i18n.isNotEmpty,
				},
			],
		};

		return conditions[ fieldType ] || conditions.string;
	},

	/**
	 * Load database tables
	 */
	loadDatabaseTables() {
		const $dropdown = jQuery( '#rsl-ie-table-name' );
		const $spinner = jQuery( '.rsl-ie-table-selector .spinner' );
		const $section = jQuery( '.rsl-ie-table-selection-section' );

		// Show section
		$section.show();

		// Show loading state
		$dropdown.prop( 'disabled', true );
		$spinner.addClass( 'is-active' );

		// Fetch tables via AJAX
		Utils.ajax( 'rsl_ie_get_database_tables', {} )
			.then( ( response ) => {
				const tables = response.tables || response || [];

				// Clear and populate dropdown
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

				// Enable dropdown
				$dropdown.prop( 'disabled', false );
				$spinner.removeClass( 'is-active' );

				// Handle table selection
				$dropdown.off( 'change' ).on( 'change', () => {
					const tableName = $dropdown.val();
					if ( tableName ) {
						this.loadTableColumns( tableName );
					} else {
						jQuery( '.rsl-ie-table-info' ).html( '' ).hide();
						jQuery( '#rsl-ie-filters-list' ).empty();
					}
					// Update Next button state based on table selection
					this.updateStep2NextButton();
				} );
			} )
			.catch( ( error ) => {
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
	 * Load table columns and show info
	 */
	loadTableColumns( tableName ) {
		const $tableInfo = jQuery( '.rsl-ie-table-info' );
		const $columnsList = jQuery( '.rsl-ie-columns-list' );
		const $rowCount = jQuery( '.rsl-ie-table-row-count' );
		const $columnCount = jQuery( '.rsl-ie-table-column-count' );

		// Show loading state
		$tableInfo.show();
		$columnsList.html(
			`<p>${ window.rslIeData.i18n.loadingTableColumns }</p>`
		);

		// Fetch columns via AJAX
		Utils.ajax( 'rsl_ie_get_table_columns', { table_name: tableName } )
			.then( ( response ) => {
				const columns = response.columns || [];

				// Update column count
				$columnCount.text( columns.length );
				$rowCount.text(
					Number.isFinite( Number( response.row_count ) )
						? response.row_count
						: '-'
				);

				// Display columns with types
				$columnsList.empty();
				const $list = jQuery( '<ul>' ).addClass(
					'rsl-ie-column-type-list'
				);

				columns.forEach( ( col ) => {
					const typeIcon = this.getColumnTypeIcon( col );
					const typeLabel = col.is_numeric
						? 'numeric'
						: col.is_string
						? 'text'
						: col.is_date
						? 'date'
						: 'other';

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
				jQuery( '#rsl-ie-filters-list' ).empty();

				// Refresh count to get row count
				this.refreshCount( false );
			} )
			.catch( ( error ) => {
				$columnsList.html(
					`<p class="error">${ window.rslIeData.i18n.errorLoadingColumns }</p>`
				);
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
		selectedFields.forEach( ( fieldData ) => {
			keyToFieldMap[ fieldData.key ] = fieldData.field;
		} );

		// Convert fieldKey to actual field name
		Object.keys( fieldFunctions ).forEach( ( fieldKey ) => {
			const actualFieldName = keyToFieldMap[ fieldKey ];
			if ( actualFieldName && fieldFunctions[ fieldKey ].length > 0 ) {
				converted[ actualFieldName ] = fieldFunctions[ fieldKey ];
			}
		} );

		return converted;
	},

	escapeHtml( text ) {
		const div = document.createElement( 'div' );
		div.textContent = text == null ? '' : String( text );
		return div.innerHTML;
	},
};

export default ExportModule;
