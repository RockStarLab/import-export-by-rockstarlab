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
		if (jQuery('#wp-aie-ai-url-importer').length === 0) {
			return;
		}

		this.bindEvents();
		this.loadPostTypes();
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const self = this;

		// Step 1: URL Input
		jQuery('#aie-urls-textarea').on('input', () => self.handleURLInput());
		jQuery('#aie-browse-csv-btn').on('click', () => jQuery('#aie-csv-file-input').click());
		jQuery('#aie-csv-file-input').on('change', (e) => self.handleCSVUpload(e));
		jQuery('.aie-remove-file').on('click', () => self.removeCSVFile());

		// CSV drag & drop
		const $uploadArea = jQuery('#aie-csv-upload-area');
		$uploadArea.on('dragover', (e) => {
			e.preventDefault();
			$uploadArea.addClass('dragover');
		});
		$uploadArea.on('dragleave', () => $uploadArea.removeClass('dragover'));
		$uploadArea.on('drop', (e) => {
			e.preventDefault();
			$uploadArea.removeClass('dragover');
			const files = e.originalEvent.dataTransfer.files;
			if (files.length > 0 && files[0].name.endsWith('.csv')) {
				jQuery('#aie-csv-file-input')[0].files = files;
				self.handleCSVUpload({ target: jQuery('#aie-csv-file-input')[0] });
			}
		});

		// Step navigation
		jQuery('.aie-next-step').on('click', function() {
			const nextStep = jQuery(this).data('next-step');
			self.goToStep(nextStep);
		});
		jQuery('.aie-prev-step').on('click', function() {
			const prevStep = jQuery(this).data('prev-step');
			self.goToStep(prevStep);
		});

		// Step 2: Field mapping
		jQuery('#aie-post-type').on('change', () => self.handlePostTypeChange());
		jQuery('#aie-content-field').on('change', () => self.handleContentFieldChange());

		// Step 3: Test & Preview
		jQuery('#aie-test-connection-btn').on('click', () => self.testConnection());
		jQuery('#aie-preview-btn').on('click', () => self.generatePreview());
		jQuery('#aie-regenerate-preview-btn').on('click', () => self.generatePreview());
		jQuery('#aie-start-import-btn').on('click', () => self.startImport());

		// Step 4: Import progress
		jQuery('#aie-cancel-import-btn').on('click', () => self.cancelImport());
		jQuery('#aie-start-new-import-btn').on('click', () => self.startNewImport());
		jQuery('#aie-view-results-btn').on('click', () => self.viewResults());
	},

	/**
	 * Handle URL textarea input
	 */
	handleURLInput() {
		const text = jQuery('#aie-urls-textarea').val().trim();
		const urls = text.split('\n').filter(url => {
			url = url.trim();
			return url && this.isValidURL(url);
		});

		this.urls = urls;
		this.updateURLCount();
		this.enableNextStep(urls.length > 0);
	},

	/**
	 * Handle CSV file upload
	 */
	async handleCSVUpload(e) {
		const file = e.target.files[0];
		if (!file) return;

		const reader = new FileReader();
		reader.onload = (event) => {
			const csv = event.target.result;
			const urls = this.parseCSV(csv);
			
			this.urls = urls;
			this.updateURLCount();
			this.showFileInfo(file.name);
			this.enableNextStep(urls.length > 0);
			
			// Clear textarea
			jQuery('#aie-urls-textarea').val('');
		};
		reader.readAsText(file);
	},

	/**
	 * Parse CSV file
	 */
	parseCSV(csv) {
		const lines = csv.split('\n');
		const urls = [];

		lines.forEach(line => {
			const columns = line.split(',');
			const url = columns[0].trim();
			if (url && this.isValidURL(url)) {
				urls.push(url);
			}
		});

		return urls;
	},

	/**
	 * Validate URL
	 */
	isValidURL(string) {
		try {
			const url = new URL(string);
			return url.protocol === 'http:' || url.protocol === 'https:';
		} catch {
			return false;
		}
	},

	/**
	 * Update URL count display
	 */
	updateURLCount() {
		const $counter = jQuery('.aie-url-count');
		$counter.find('.count').text(this.urls.length);
		$counter.toggle(this.urls.length > 0);
	},

	/**
	 * Show file info
	 */
	showFileInfo(filename) {
		jQuery('.aie-upload-placeholder').hide();
		jQuery('.aie-file-info').show().find('.file-name').text(filename);
	},

	/**
	 * Remove CSV file
	 */
	removeCSVFile() {
		jQuery('#aie-csv-file-input').val('');
		jQuery('.aie-file-info').hide();
		jQuery('.aie-upload-placeholder').show();
		this.urls = [];
		this.updateURLCount();
		this.enableNextStep(false);
	},

	/**
	 * Enable/disable next step button
	 */
	enableNextStep(enable) {
		jQuery('.aie-step-1 .aie-next-step').prop('disabled', !enable);
	},

	/**
	 * Go to specific step
	 */
	goToStep(step) {
		jQuery('.aie-step').hide().removeClass('aie-step-active');
		jQuery(`.aie-step-${step}`).show().addClass('aie-step-active');
		this.currentStep = step;

		// Special handling for step 3
		if (step === 3) {
			jQuery('#aie-preview-url').text(this.urls[0]);
		}
	},

	/**
	 * Load post types
	 */
	async loadPostTypes() {
		try {
			const response = await Utils.ajax('aie_ai_url_get_post_types', {});
			
			const $select = jQuery('#aie-post-type');
			$select.empty();
			
			response.post_types.forEach(pt => {
				$select.append(`<option value="${pt.value}">${pt.label}</option>`);
			});
		} catch (error) {
			console.error('Failed to load post types:', error);
		}
	},

	/**
	 * Handle post type change
	 */
	async handlePostTypeChange() {
		const postType = jQuery('#aie-post-type').val();
		
		// Load ACF fields if available
		if (jQuery('#aie-content-field').val() === 'acf_field') {
			await this.loadACFFields(postType);
		}
	},

	/**
	 * Handle content field change
	 */
	handleContentFieldChange() {
		const value = jQuery('#aie-content-field').val();
		
		if (value === 'acf_field') {
			jQuery('#aie-acf-field-row').show();
			jQuery('#aie-custom-field-row').hide();
			this.loadACFFields(jQuery('#aie-post-type').val());
		} else if (value === 'custom_field') {
			jQuery('#aie-custom-field-row').show();
			jQuery('#aie-acf-field-row').hide();
		} else {
			jQuery('#aie-acf-field-row').hide();
			jQuery('#aie-custom-field-row').hide();
		}
	},

	/**
	 * Load ACF fields and build tree
	 */
	async loadACFFields(postType) {
		try {
			const response = await Utils.ajax('aie_ai_url_get_acf_fields', { post_type: postType });
			
			this.acfFields = response.fields || [];
			this.renderACFFieldTree(this.acfFields);
			this.bindACFFieldEvents();
		} catch (error) {
			console.error('Failed to load ACF fields:', error);
			const $tree = jQuery('#aie-acf-field-tree');
			$tree.html('<p class="description" style="color: #d63638;">Failed to load ACF fields. Please try again.</p>');
		}
	},

	/**
	 * Render ACF field tree
	 */
	renderACFFieldTree(fields, searchTerm = '') {
		const $tree = jQuery('#aie-acf-field-tree');
		
		if (!fields || fields.length === 0) {
			$tree.html('<p class="description">No ACF fields found for this post type.</p>');
			return;
		}

		let html = '<ul class="aie-acf-field-list">';
		
		fields.forEach(field => {
			html += this.renderACFField(field, searchTerm);
		});
		
		html += '</ul>';
		$tree.html(html);
	},

	/**
	 * Render single ACF field with children
	 */
	renderACFField(field, searchTerm = '') {
		const hasSubFields = field.sub_fields && field.sub_fields.length > 0;
		const isAllowed = field.is_allowed;
		const matchesSearch = !searchTerm || field.label.toLowerCase().includes(searchTerm.toLowerCase());
		
		if (!matchesSearch && !hasSubFields) {
			return '';
		}

		let html = '<li class="aie-acf-field-item">';
		
		// Field header
		html += '<div class="aie-acf-field-header' + (hasSubFields ? ' has-children' : '') + '">';
		
		// Expand/collapse icon for parent fields
		if (hasSubFields) {
			html += '<span class="aie-acf-toggle dashicons dashicons-arrow-right"></span>';
		} else {
			html += '<span class="aie-acf-spacer"></span>';
		}
		
		// Radio button for selectable fields
		if (isAllowed) {
			html += `<label class="aie-acf-field-label">
				<input type="radio" name="acf_field_selection" value="${field.name}" data-key="${field.key}" data-type="${field.type}">
				<span class="field-name">${field.label}</span>
				<span class="field-type">(${field.type})</span>
			</label>`;
		} else {
			html += `<span class="aie-acf-field-label disabled">
				<span class="field-name">${field.label}</span>
				<span class="field-type">(${field.type})</span>
			</span>`;
		}
		
		html += '</div>';
		
		// Sub-fields
		if (hasSubFields) {
			html += '<ul class="aie-acf-field-children" style="display: none;">';
			field.sub_fields.forEach(subField => {
				html += this.renderACFField(subField, searchTerm);
			});
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
		jQuery('#aie-acf-field-search').off('input').on('input', function() {
			const searchTerm = jQuery(this).val();
			self.renderACFFieldTree(self.acfFields, searchTerm);
			self.bindACFFieldEvents();
		});
		
		// Toggle expand/collapse
		jQuery('.aie-acf-toggle').off('click').on('click', function() {
			const $header = jQuery(this).closest('.aie-acf-field-header');
			const $children = $header.siblings('.aie-acf-field-children');
			
			if ($children.is(':visible')) {
				$children.slideUp(200);
				jQuery(this).removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
			} else {
				$children.slideDown(200);
				jQuery(this).removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
			}
		});
		
		// Select field
		// Select field
		jQuery('input[name="acf_field_selection"]').off('change').on('change', function() {
			const fieldName = jQuery(this).val();
			jQuery('#aie-acf-field-select').val(fieldName);
		});
	},

	/**
	 * Test OpenAI connection
	 */
	async testConnection() {
		const $btn = jQuery('#aie-test-connection-btn');
		const $result = jQuery('.aie-test-result');
		
		$btn.prop('disabled', true).text('Testing...');
		$result.hide();

		try {
			const response = await Utils.ajax('aie_ai_url_test_connection', {});
			
			$result.removeClass('error').addClass('success')
				.html(`<span class="dashicons dashicons-yes"></span> ${response.message}`)
				.show();
		} catch (error) {
			const message = error.message || error || 'Connection test failed';
			
			$result.removeClass('success').addClass('error')
				.html(`<span class="dashicons dashicons-no"></span> ${message}`)
				.show();
		} finally {
			$btn.prop('disabled', false).text('Test Connection');
		}
	},

	/**
	 * Generate preview
	 */
	async generatePreview() {
		const $btn = jQuery('#aie-preview-btn');
		const $regenerateBtn = jQuery('#aie-regenerate-preview-btn');
		const $result = jQuery('.aie-preview-result');
		
		$btn.prop('disabled', true).text('Generating Preview...');
		$regenerateBtn.hide();
		$result.hide();

		try {
			const response = await Utils.ajax('aie_ai_url_preview', { url: this.urls[0] });
			
			this.previewData = response;
			this.displayPreview(response);
			
			$result.show();
			$regenerateBtn.show();
			jQuery('#aie-start-import-btn').prop('disabled', false);
		} catch (error) {
			this.showError(error, '.aie-preview-section');
		} finally {
			$btn.prop('disabled', false).text('Generate Preview');
		}
	},

	/**
	 * Show error message with nice formatting
	 */
	showError(error, containerSelector) {
		const message = error.message || error || 'An error occurred';
		const isRateLimit = message.toLowerCase().includes('rate limit');
		
		let noticeClass = 'notice notice-error';
		let title = 'Error';
		
		if (isRateLimit) {
			noticeClass = 'notice notice-warning';
			title = 'Rate Limit Reached';
		}
		
		const errorHtml = `
			<div class="${noticeClass} aie-inline-notice">
				<p><strong>${title}</strong></p>
				<p>${message}</p>
			</div>
		`;
		
		// Remove any existing error notices
		jQuery(containerSelector).find('.aie-inline-notice').remove();
		
		// Add the error notice
		jQuery(containerSelector).prepend(errorHtml);
		
		// Scroll to error
		const noticeElement = jQuery(containerSelector).find('.aie-inline-notice').get(0);
		if (noticeElement) {
			noticeElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		}
	},

	/**
	 * Display preview
	 */
	displayPreview(data) {
		jQuery('.preview-title-content').html(`<h3>${data.title}</h3>`);
		jQuery('.preview-excerpt-content').html(`<p>${data.excerpt}</p>`);
		jQuery('.preview-content-html').html(data.content);
		
		// Display images
		const $imagesList = jQuery('.preview-images-list');
		$imagesList.empty();
		
		if (data.images && data.images.length > 0) {
			data.images.forEach(img => {
				$imagesList.append(`
					<div class="preview-image-item">
						<img src="${img.url}" alt="${img.alt}" style="max-width: 200px; height: auto;">
						<p><small>${img.url}</small></p>
					</div>
				`);
			});
		} else {
			$imagesList.html('<p>No images found</p>');
		}
		
		// Display featured image
		if (data.featured_image) {
			jQuery('.preview-featured-image').html(`
				<img src="${data.featured_image}" alt="Featured" style="max-width: 300px; height: auto;">
			`);
		} else {
			jQuery('.preview-featured-image').html('<p>No featured image selected</p>');
		}
	},

	/**
	 * Start import
	 */
	async startImport() {
		this.settings = {
			urls: this.urls,
			post_type: jQuery('#aie-post-type').val(),
			content_field: jQuery('#aie-content-field').val(),
			acf_field: jQuery('#aie-acf-field-select').val(),
			custom_field_name: jQuery('#aie-custom-field-name').val(),
			timeout: parseInt(jQuery('#aie-request-timeout').val()) || 2
		};

		try {
			const response = await Utils.ajax('aie_ai_url_start_import', this.settings);
			
			this.jobId = response.job_id;
			this.goToStep(4);
			
			// Start progress monitoring
			this.startProgressTracking();
			
			// Start processing batches
			this.processNextBatch();
		} catch (error) {
			console.error('Error starting import:', error);
			this.showError(error, '.aie-step-3 .aie-step-content');
		}
	},

	/**
	 * Start progress tracking with interval
	 */
	startProgressTracking() {
		this.progressInterval = setInterval(() => {
			this.updateProgress();
		}, 2000);
	},

	/**
	 * Process next batch of URLs
	 */
	async processNextBatch() {
		try {
			const response = await Utils.ajax('aie_ai_url_process_batch', {
				job_id: this.jobId
			});

			if (response.completed) {
				// Job is completed
				this.stopProgressTracking();
			} else {
				// Continue processing with a small delay
				setTimeout(() => this.processNextBatch(), 500);
			}
		} catch (error) {
			console.error('Error processing batch:', error);
			this.stopProgressTracking();
		}
	},

	/**
	 * Update progress display
	 */
	/**
	 * Update progress display
	 */
	async updateProgress() {
		if (!this.jobId) return;

		try {
			const response = await Utils.ajax('aie_ai_url_get_progress', {
				job_id: this.jobId
			});

			// Update progress bar
			const progress = Math.round(response.progress);
			jQuery('.aie-progress-fill').css('width', progress + '%');
			jQuery('.aie-progress-fill').text(progress + '%');

			// Update progress text
			jQuery('.aie-progress-text .current').text(response.processed);
			jQuery('.aie-progress-text .total').text(response.total);

			// Update status counts
			jQuery('.success-count').text(response.success_count);
			jQuery('.failed-count').text(response.failed_count);
			jQuery('.import-status-text').text(response.status);

			// Check if completed or failed
			if (response.status === 'completed' || response.status === 'failed') {
				this.stopProgressTracking();
				
				// Show completion UI
				jQuery('#aie-cancel-import-btn').hide();
				jQuery('#aie-start-new-import-btn, #aie-view-results-btn').show();
				
				if (response.status === 'completed') {
					jQuery('.import-status-text').text(
						`Import completed! ${response.success_count} URLs imported successfully.`
					);
				} else if (response.status === 'failed') {
					jQuery('.import-status-text').text(
						`Import failed: ${response.error}`
					);
				}
			}

		} catch (error) {
			console.error('Error polling job progress:', error);
			this.stopProgressTracking();
		}
	},

	/**
	 * Stop progress tracking
	 */
	stopProgressTracking() {
		if (this.progressInterval) {
			clearInterval(this.progressInterval);
			this.progressInterval = null;
		}
	},

	/**
	 * Cancel import
	 */
	async cancelImport() {
		if (!confirm('Are you sure you want to cancel this import?')) {
			return;
		}

		try {
			// Update job status to cancelled
			await Utils.ajax('cancel_job', {
				job_id: this.jobId
			});
			
			// Reset and go back to step 1
			this.jobId = null;
			this.goToStep(1);
		} catch (error) {
			console.error('Error cancelling job:', error);
			alert('Failed to cancel the import. Please try again.');
		}
	},

	/**
	 * Start new import
	 */
	startNewImport() {
		this.urls = [];
		this.previewData = null;
		this.jobId = null;
		jQuery('#aie-urls-textarea').val('');
		this.removeCSVFile();
		this.goToStep(1);
	},

	/**
	 * View results
	 */
	viewResults() {
		const postType = this.settings.post_type;
		window.location.href = `edit.php?post_type=${postType}`;
	}
};

export default AIURLImporter;
