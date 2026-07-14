/**
 * AI URL Importer Module
 *
 * Handles the AI-powered URL import workflow
 */

import Utils from './utils.js';

const AIURLImporter = {
	urls: [],
	currentStep: 1,
	settings: {},
	jobId: null,
	previewData: null,
	acfFields: [],
	progressInterval: null,

	/**
	 * Initialize module
	 */
	init() {
		if ( jQuery( '#rsl-ie-ai-url-importer' ).length === 0 ) {
			return;
		}

		this.bindEvents();
		this.loadPostTypes();

		// Check if resuming a job from Jobs Log
		const urlParams = new URLSearchParams( window.location.search );
		const resumeJobId = urlParams.get( 'resume_job' );
		if ( resumeJobId ) {
			this.jobId = parseInt( resumeJobId );
			this.goToStep( 4 );
			this.startProgressTracking();
			this.processNextBatch();
		}
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const self = this;

		// Step 1: URL Input
		jQuery( '#rsl-ie-urls-textarea' ).on( 'input', () =>
			self.handleURLInput()
		);
		jQuery( '#rsl-ie-browse-csv-btn' ).on( 'click', () =>
			jQuery( '#rsl-ie-csv-file-input' ).click()
		);
		jQuery( '#rsl-ie-csv-file-input' ).on( 'change', ( e ) =>
			self.handleTXTUpload( e )
		);
		jQuery( '.rsl-ie-remove-file' ).on( 'click', () =>
			self.removeTXTFile()
		);

		// TXT drag & drop
		const $uploadArea = jQuery( '#rsl-ie-csv-upload-area' );
		$uploadArea.on( 'dragover', ( e ) => {
			e.preventDefault();
			$uploadArea.addClass( 'dragover' );
		} );
		$uploadArea.on( 'dragleave', () =>
			$uploadArea.removeClass( 'dragover' )
		);
		$uploadArea.on( 'drop', ( e ) => {
			e.preventDefault();
			$uploadArea.removeClass( 'dragover' );
			const files = e.originalEvent.dataTransfer.files;
			if ( files.length > 0 && files[ 0 ].name.endsWith( '.txt' ) ) {
				jQuery( '#rsl-ie-csv-file-input' )[ 0 ].files = files;
				self.handleTXTUpload( {
					target: jQuery( '#rsl-ie-csv-file-input' )[ 0 ],
				} );
			}
		} );

		// Step navigation
		jQuery( '.rsl-ie-next-step' ).on( 'click', function () {
			const nextStep = jQuery( this ).data( 'next-step' );
			self.goToStep( nextStep );
		} );
		jQuery( '.rsl-ie-prev-step' ).on( 'click', function () {
			const prevStep = jQuery( this ).data( 'prev-step' );
			self.goToStep( prevStep );
		} );

		// Step 2: Field mapping
		jQuery( '#rsl-ie-post-type' ).on( 'change', () =>
			self.handlePostTypeChange()
		);
		jQuery( '#rsl-ie-content-field' ).on( 'change', () =>
			self.handleContentFieldChange()
		);

		// Step 3: Test & Preview
		jQuery( '#rsl-ie-test-connection-btn' ).on( 'click', () =>
			self.testConnection()
		);
		jQuery( '#rsl-ie-preview-btn' ).on( 'click', () =>
			self.generatePreview( 'auto' )
		);
		jQuery( '#rsl-ie-regenerate-preview-btn' ).on( 'click', () =>
			self.generatePreview( 'alternate' )
		);
		jQuery( '#rsl-ie-start-import-btn' ).on( 'click', () =>
			self.startImport()
		);

		// Step 4: Import progress
		jQuery( '#rsl-ie-cancel-import-btn' ).on( 'click', () =>
			self.cancelImport()
		);
		jQuery( '#rsl-ie-start-new-import-btn' ).on( 'click', () =>
			self.startNewImport()
		);
		jQuery( '#rsl-ie-view-results-btn' ).on( 'click', () =>
			self.viewResults()
		);
	},

	/**
	 * Handle URL textarea input
	 */
	handleURLInput() {
		const text = jQuery( '#rsl-ie-urls-textarea' ).val().trim();
		const urls = text.split( '\n' ).filter( ( url ) => {
			url = url.trim();
			return url && this.isValidURL( url );
		} );

		this.urls = urls;
		this.updateURLCount();
		this.enableNextStep( urls.length > 0 );
	},

	/**
	 * Handle TXT file upload
	 */
	async handleTXTUpload( e ) {
		const file = e.target.files[ 0 ];
		if ( ! file ) return;

		const reader = new FileReader();
		reader.onload = ( event ) => {
			const text = event.target.result;
			const urls = this.parseTXT( text );

			this.urls = urls;
			this.updateURLCount();
			this.showFileInfo( file.name );
			this.enableNextStep( urls.length > 0 );

			// Clear textarea
			jQuery( '#rsl-ie-urls-textarea' ).val( '' );
		};
		reader.readAsText( file );
	},

	/**
	 * Parse TXT file (one URL per line)
	 */
	parseTXT( text ) {
		const lines = text.split( '\n' );
		const urls = [];

		lines.forEach( ( line ) => {
			const url = line.trim();
			if ( url && this.isValidURL( url ) ) {
				urls.push( url );
			}
		} );

		return urls;
	},

	/**
	 * Validate URL
	 */
	isValidURL( string ) {
		try {
			const url = new URL( string );
			return url.protocol === 'http:' || url.protocol === 'https:';
		} catch {
			return false;
		}
	},

	/**
	 * Update URL count display
	 */
	updateURLCount() {
		const $counter = jQuery( '.rsl-ie-url-count' );
		$counter.find( '.count' ).text( this.urls.length );
		$counter.toggle( this.urls.length > 0 );
	},

	/**
	 * Show file info
	 */
	showFileInfo( filename ) {
		jQuery( '.rsl-ie-upload-placeholder' ).hide();
		jQuery( '.rsl-ie-file-info' )
			.show()
			.find( '.file-name' )
			.text( filename );
	},

	/**
	 * Remove TXT file
	 */
	removeTXTFile() {
		jQuery( '#rsl-ie-csv-file-input' ).val( '' );
		jQuery( '.rsl-ie-file-info' ).hide();
		jQuery( '.rsl-ie-upload-placeholder' ).show();
		this.urls = [];
		this.updateURLCount();
		this.enableNextStep( false );
	},

	/**
	 * Enable/disable next step button
	 */
	enableNextStep( enable ) {
		jQuery( '.rsl-ie-step-1 .rsl-ie-next-step' ).prop(
			'disabled',
			! enable
		);
	},

	/**
	 * Go to specific step
	 */
	goToStep( step ) {
		const prevStep = this.currentStep;

		jQuery( '.rsl-ie-step' ).hide().removeClass( 'rsl-ie-step-active' );
		jQuery( `.rsl-ie-step-${ step }` )
			.show()
			.addClass( 'rsl-ie-step-active' );
		this.currentStep = step;

		// Reset Step 3 preview whenever entering it (URL may have changed)
		if ( step === 3 ) {
			this.previewData = null;
			jQuery( '.rsl-ie-preview-result' ).hide();
			jQuery( '.rsl-ie-inline-notice' ).remove();
			jQuery( '#rsl-ie-preview-btn' )
				.show()
				.prop( 'disabled', false )
				.text( window.rslIeData.i18n.generatePreview );
			jQuery( '#rsl-ie-regenerate-preview-btn' ).hide();
			jQuery( '#rsl-ie-start-import-btn' ).prop( 'disabled', true );
			jQuery( '#rsl-ie-preview-url' ).text( this.urls[ 0 ] );
		}
	},

	/**
	 * Load post types
	 */
	async loadPostTypes() {
		try {
			const response = await Utils.ajax(
				'rsl_ie_ai_url_get_post_types',
				{}
			);

			const $select = jQuery( '#rsl-ie-post-type' );
			$select.empty();

			response.post_types.forEach( ( pt ) => {
				$select.append(
					`<option value="${ pt.value }">${ pt.label }</option>`
				);
			} );
		} catch ( error ) {
			console.error( 'Failed to load post types:', error );
		}
	},

	/**
	 * Handle post type change
	 */
	async handlePostTypeChange() {
		const postType = jQuery( '#rsl-ie-post-type' ).val();

		// Load ACF fields if available
		if ( jQuery( '#rsl-ie-content-field' ).val() === 'acf_field' ) {
			await this.loadACFFields( postType );
		}
	},

	/**
	 * Handle content field change
	 */
	handleContentFieldChange() {
		const value = jQuery( '#rsl-ie-content-field' ).val();

		if ( value === 'acf_field' ) {
			jQuery( '#rsl-ie-acf-field-row' ).show();
			jQuery( '#rsl-ie-custom-field-row' ).hide();
			this.loadACFFields( jQuery( '#rsl-ie-post-type' ).val() );
		} else if ( value === 'custom_field' ) {
			jQuery( '#rsl-ie-custom-field-row' ).show();
			jQuery( '#rsl-ie-acf-field-row' ).hide();
		} else {
			jQuery( '#rsl-ie-acf-field-row' ).hide();
			jQuery( '#rsl-ie-custom-field-row' ).hide();
		}
	},

	/**
	 * Load ACF fields and build tree
	 */
	async loadACFFields( postType ) {
		try {
			const response = await Utils.ajax( 'rsl_ie_ai_url_get_acf_fields', {
				post_type: postType,
			} );

			this.acfFields = response.fields || [];
			this.renderACFFieldTree( this.acfFields );
			this.bindACFFieldEvents();
		} catch ( error ) {
			console.error( 'Failed to load ACF fields:', error );
			const $tree = jQuery( '#rsl-ie-acf-field-tree' );
			$tree.html(
				`<p class="description" style="color: #d63638;">${ window.rslIeData.i18n.failedLoadAcfFields }</p>`
			);
		}
	},

	/**
	 * Render ACF field tree
	 */
	renderACFFieldTree( fields, searchTerm = '' ) {
		const $tree = jQuery( '#rsl-ie-acf-field-tree' );

		if ( ! fields || fields.length === 0 ) {
			$tree.html(
				`<p class="description">${ window.rslIeData.i18n.noAcfFields }</p>`
			);
			return;
		}

		let html = '<ul class="rsl-ie-acf-field-list">';

		fields.forEach( ( field ) => {
			html += this.renderACFField( field, searchTerm );
		} );

		html += '</ul>';
		$tree.html( html );
	},

	/**
	 * Render single ACF field with children
	 */
	renderACFField( field, searchTerm = '' ) {
		const hasSubFields = field.sub_fields && field.sub_fields.length > 0;
		const isAllowed = field.is_allowed;
		const matchesSearch =
			! searchTerm ||
			field.label.toLowerCase().includes( searchTerm.toLowerCase() );

		if ( ! matchesSearch && ! hasSubFields ) {
			return '';
		}

		let html = '<li class="rsl-ie-acf-field-item">';

		// Field header
		html +=
			'<div class="rsl-ie-acf-field-header' +
			( hasSubFields ? ' has-children' : '' ) +
			'">';

		// Expand/collapse icon for parent fields
		if ( hasSubFields ) {
			html +=
				'<span class="rsl-ie-acf-toggle dashicons dashicons-arrow-right"></span>';
		} else {
			html += '<span class="rsl-ie-acf-spacer"></span>';
		}

		// Radio button for selectable fields
		if ( isAllowed ) {
			html += `<label class="rsl-ie-acf-field-label">
				<input type="radio" name="acf_field_selection" value="${ field.name }" data-key="${ field.key }" data-type="${ field.type }">
				<span class="field-name">${ field.label }</span>
				<span class="field-type">(${ field.type })</span>
			</label>`;
		} else {
			html += `<span class="rsl-ie-acf-field-label disabled">
				<span class="field-name">${ field.label }</span>
				<span class="field-type">(${ field.type })</span>
			</span>`;
		}

		html += '</div>';

		// Sub-fields
		if ( hasSubFields ) {
			html +=
				'<ul class="rsl-ie-acf-field-children" style="display: none;">';
			field.sub_fields.forEach( ( subField ) => {
				html += this.renderACFField( subField, searchTerm );
			} );
			html += '</ul>';
		}

		html += '</li>';

		return html;
	},

	/**
	 * Bind ACF field browser events
	 */
	bindACFFieldEvents() {
		const self = this;

		// Search
		jQuery( '#rsl-ie-acf-field-search' )
			.off( 'input' )
			.on( 'input', function () {
				const searchTerm = jQuery( this ).val();
				self.renderACFFieldTree( self.acfFields, searchTerm );
				self.bindACFFieldEvents();
			} );

		// Toggle expand/collapse
		jQuery( '.rsl-ie-acf-toggle' )
			.off( 'click' )
			.on( 'click', function () {
				const $header = jQuery( this ).closest(
					'.rsl-ie-acf-field-header'
				);
				const $children = $header.siblings(
					'.rsl-ie-acf-field-children'
				);

				if ( $children.is( ':visible' ) ) {
					$children.slideUp( 200 );
					jQuery( this )
						.removeClass( 'dashicons-arrow-down' )
						.addClass( 'dashicons-arrow-right' );
				} else {
					$children.slideDown( 200 );
					jQuery( this )
						.removeClass( 'dashicons-arrow-right' )
						.addClass( 'dashicons-arrow-down' );
				}
			} );

		// Select field
		// Select field
		jQuery( 'input[name="acf_field_selection"]' )
			.off( 'change' )
			.on( 'change', function () {
				const fieldName = jQuery( this ).val();
				jQuery( '#rsl-ie-acf-field-select' ).val( fieldName );
			} );
	},

	/**
	 * Test OpenAI connection
	 */
	async testConnection() {
		const $btn = jQuery( '#rsl-ie-test-connection-btn' );
		const $result = jQuery( '.rsl-ie-test-result' );

		$btn.prop( 'disabled', true ).text( window.rslIeData.i18n.testing );
		$result.hide();

		try {
			const response = await Utils.ajax(
				'rsl_ie_ai_url_test_connection',
				{}
			);

			$result
				.removeClass( 'error' )
				.addClass( 'success' )
				.html(
					`<span class="dashicons dashicons-yes"></span> ${ response.message }`
				)
				.show();
		} catch ( error ) {
			const message = error.message || error || 'Connection test failed';

			$result
				.removeClass( 'success' )
				.addClass( 'error' )
				.html(
					`<span class="dashicons dashicons-no"></span> ${ message }`
				)
				.show();
		} finally {
			$btn.prop( 'disabled', false ).text(
				window.rslIeData.i18n.testConnection
			);
		}
	},

	/**
	 * Generate preview
	 */
	async generatePreview( extractionMode = 'auto' ) {
		const $btn = jQuery( '#rsl-ie-preview-btn' );
		const $regenerateBtn = jQuery( '#rsl-ie-regenerate-preview-btn' );
		const $result = jQuery( '.rsl-ie-preview-result' );

		// Always show the primary button in loading state, hide regenerate while loading
		$btn.show()
			.prop( 'disabled', true )
			.text( window.rslIeData.i18n.generatingPreview );
		$regenerateBtn.hide();
		$result.hide();

		try {
			const response = await Utils.ajax( 'rsl_ie_ai_url_preview', {
				url: this.urls[ 0 ],
				extraction_mode: extractionMode,
			} );

			this.previewData = response;
			this.displayPreview( response );

			$result.show();
			// After success: replace Generate with Regenerate
			$btn.hide();
			$regenerateBtn.show();
			jQuery( '#rsl-ie-start-import-btn' ).prop( 'disabled', false );
		} catch ( error ) {
			this.showError( error, '.rsl-ie-preview-section' );
			// On error: restore Generate button
			$btn.prop( 'disabled', false ).text(
				window.rslIeData.i18n.generatePreview
			);
		}
	},

	/**
	 * Show error message with nice formatting
	 */
	showError( error, containerSelector ) {
		const message =
			error.message || error || window.rslIeData.i18n.errorOccurred;
		const isRateLimit = message.toLowerCase().includes( 'rate limit' );

		let noticeClass = 'notice notice-error';
		let title = window.rslIeData.i18n.error;

		if ( isRateLimit ) {
			noticeClass = 'notice notice-warning';
			title = window.rslIeData.i18n.rateLimitReached;
		}

		const errorHtml = `
			<div class="${ noticeClass } rsl-ie-inline-notice">
				<p><strong>${ title }</strong></p>
				<p>${ message }</p>
			</div>
		`;

		// Remove any existing error notices
		jQuery( containerSelector ).find( '.rsl-ie-inline-notice' ).remove();

		// Add the error notice
		jQuery( containerSelector ).prepend( errorHtml );

		// Scroll to error
		const noticeElement = jQuery( containerSelector )
			.find( '.rsl-ie-inline-notice' )
			.get( 0 );
		if ( noticeElement ) {
			noticeElement.scrollIntoView( {
				behavior: 'smooth',
				block: 'nearest',
			} );
		}
	},

	/**
	 * Display preview
	 */
	displayPreview( data ) {
		jQuery( '.preview-title-content' ).html( `<h3>${ data.title }</h3>` );
		jQuery( '.preview-excerpt-content' ).html( `<p>${ data.excerpt }</p>` );
		jQuery( '.preview-content-html' ).html( data.content );

		// Show content stats and truncation warning
		const contentText = jQuery( '.preview-content-html' ).text();
		const charCount = contentText.length;
		const wordCount = contentText
			.trim()
			.split( /\s+/ )
			.filter( ( w ) => w.length > 0 ).length;

		let statsHtml = `<p class="rsl-ie-content-stats">📄 ${ wordCount } ${
			window.rslIeData.i18n.words || 'words'
		}, ${ charCount.toLocaleString() } ${
			window.rslIeData.i18n.characters || 'characters'
		}</p>`;

		if ( data.truncated ) {
			statsHtml += `<div class="rsl-ie-truncation-warning notice notice-warning inline"><p>⚠️ ${
				window.rslIeData.i18n.contentTruncated ||
				'Warning: the article content was truncated by the AI because it exceeded the token limit. The imported post will contain incomplete content. Consider using a shorter article or a more powerful model.'
			}</p></div>`;
		}

		jQuery( '.rsl-ie-preview-content-stats' ).remove();
		jQuery( '.preview-content-html' ).after(
			`<div class="rsl-ie-preview-content-stats">${ statsHtml }</div>`
		);

		// Display images
		const $imagesList = jQuery( '.preview-images-list' );
		$imagesList.empty();

		if ( data.images && data.images.length > 0 ) {
			data.images.forEach( ( img ) => {
				$imagesList.append( `
					<div class="preview-image-item">
						<img src="${ img.url }" alt="${ img.alt }" style="max-width: 200px; height: auto;">
						<p><small>${ img.url }</small></p>
					</div>
				` );
			} );
		} else {
			$imagesList.html(
				`<p>${ window.rslIeData.i18n.noImagesFound }</p>`
			);
		}

		// Display featured image
		if ( data.featured_image ) {
			jQuery( '.preview-featured-image' ).html( `
				<img src="${ data.featured_image }" alt="Featured" style="max-width: 300px; height: auto;">
			` );
		} else {
			jQuery( '.preview-featured-image' ).html(
				`<p>${ window.rslIeData.i18n.noFeaturedImage }</p>`
			);
		}
	},

	/**
	 * Start import
	 */
	async startImport() {
		this.settings = {
			urls: this.urls,
			post_type: jQuery( '#rsl-ie-post-type' ).val(),
			content_field: jQuery( '#rsl-ie-content-field' ).val(),
			acf_field: jQuery( '#rsl-ie-acf-field-select' ).val(),
			custom_field_name: jQuery( '#rsl-ie-custom-field-name' ).val(),
			request_delay:
				parseInt( jQuery( '#rsl-ie-request-timeout' ).val() ) || 0,
		};

		try {
			const response = await Utils.ajax(
				'rsl_ie_ai_url_start_import',
				this.settings
			);

			this.jobId = response.job_id;
			this.goToStep( 4 );

			// Start progress monitoring
			this.startProgressTracking();

			// Start processing batches
			this.processNextBatch();
		} catch ( error ) {
			console.error( 'Error starting import:', error );
			this.showError( error, '.rsl-ie-step-3 .rsl-ie-step-content' );
		}
	},

	/**
	 * Start progress tracking with interval
	 */
	startProgressTracking() {
		this.progressInterval = setInterval( () => {
			this.updateProgress();
		}, 2000 );
	},

	/**
	 * Process next batch of URLs
	 */
	async processNextBatch() {
		try {
			const response = await Utils.ajax( 'rsl_ie_ai_url_process_batch', {
				job_id: this.jobId,
			} );

			if ( response.completed ) {
				// Job is completed — stop interval and force a final UI update
				this.stopProgressTracking();
				await this.updateProgress();
			} else {
				// Continue processing with a small delay
				setTimeout( () => this.processNextBatch(), 500 );
			}
		} catch ( error ) {
			console.error( 'Error processing batch:', error );
			// Don't stop progress tracking on error — let the polling interval
			// detect the final job status (completed / failed) from the DB.
			// Force an immediate progress check right now.
			await this.updateProgress();
		}
	},

	/**
	 * Update progress display
	 */
	/**
	 * Update progress display
	 */
	async updateProgress() {
		if ( ! this.jobId ) return;

		try {
			const response = await Utils.ajax( 'rsl_ie_ai_url_get_progress', {
				job_id: this.jobId,
			} );

			// Update progress bar
			const progress = Math.round( response.progress );
			jQuery( '.rsl-ie-progress-fill' ).css( 'width', progress + '%' );
			jQuery( '.rsl-ie-progress-fill' ).text( progress + '%' );

			// Update progress text
			jQuery( '.rsl-ie-progress-text .current' ).text(
				response.processed
			);
			jQuery( '.rsl-ie-progress-text .total' ).text( response.total );

			// Update status counts
			jQuery( '.success-count' ).text( response.success_count );
			jQuery( '.failed-count' ).text( response.failed_count );
			jQuery( '.import-status-text' ).text( response.status );

			// Check if completed or failed
			if (
				response.status === 'completed' ||
				response.status === 'failed'
			) {
				this.stopProgressTracking();

				// Show completion UI
				jQuery( '#rsl-ie-cancel-import-btn' ).hide();
				jQuery(
					'#rsl-ie-start-new-import-btn, #rsl-ie-view-results-btn'
				).show();

				if ( response.status === 'completed' ) {
					const completedText =
						window.rslIeData.i18n.importCompleted.replace(
							'%s',
							response.success_count
						);
					jQuery( '.import-status-text' ).text( completedText );
				} else if ( response.status === 'failed' ) {
					const failedText =
						window.rslIeData.i18n.importFailed.replace(
							'%s',
							response.error
						);
					jQuery( '.import-status-text' ).text( failedText );
				}
			}
		} catch ( error ) {
			console.error( 'Error polling job progress:', error );
			this.stopProgressTracking();
		}
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
	 * Cancel import
	 */
	async cancelImport() {
		if ( ! confirm( window.rslIeData.i18n.confirmCancelImport ) ) {
			return;
		}

		try {
			// Update job status to cancelled
			await Utils.ajax( 'cancel_job', {
				job_id: this.jobId,
			} );

			// Reset and go back to step 1
			this.jobId = null;
			this.goToStep( 1 );
		} catch ( error ) {
			console.error( 'Error cancelling job:', error );
			alert( window.rslIeData.i18n.failedCancelImport );
		}
	},

	/**
	 * Start new import
	 */
	startNewImport() {
		this.urls = [];
		this.previewData = null;
		this.jobId = null;
		jQuery( '#rsl-ie-urls-textarea' ).val( '' );
		this.removeTXTFile();
		this.goToStep( 1 );
	},

	/**
	 * View results
	 */
	viewResults() {
		const postType = this.settings.post_type;
		window.location.href = `edit.php?post_type=${ postType }`;
	},
};

export default AIURLImporter;
