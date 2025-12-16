/**
 * Content Sync Module
 *
 * Handles content synchronization between sites
 */

const ContentSyncModule = {
	/**
	 * Initialize module
	 */
	init() {
		if (!jQuery('#wp-aie-content-sync').length) {
			return;
		}

		this.bindEvents();
		this.loadSites();
		this.loadMySiteInfo();
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $ = jQuery;

		// Add site button
		$('#aie-add-site-btn').on('click', () => this.showAddSiteModal());

		// Save site button
		$('#aie-save-site-btn').on('click', () => this.saveSite());

		// Modal close buttons
		$('.aie-modal-close').on('click', function () {
			$(this).closest('.aie-modal').hide();
		});

		// Close modal on outside click
		$('.aie-modal').on('click', function (e) {
			if ($(e.target).hasClass('aie-modal')) {
				$(this).hide();
			}
		});

		// Toggle my site info
		$('#aie-toggle-my-site').on('click', () => this.toggleMySiteInfo());

		// Copy my API key
		$('#aie-copy-my-key').on('click', () => this.copyMyApiKey());

		// Regenerate my API key
		$('#aie-regenerate-my-key').on('click', () => this.regenerateMyApiKey());

		// Delegated events for dynamic content
		$(document).on('click', '.aie-edit-site', (e) => {
			const siteId = $(e.currentTarget).data('site-id');
			this.showEditSiteModal(siteId);
		});

		$(document).on('click', '.aie-delete-site', (e) => {
			const siteId = $(e.currentTarget).data('site-id');
			this.deleteSite(siteId);
		});

		$(document).on('click', '.aie-test-connection', (e) => {
			const siteId = $(e.currentTarget).data('site-id');
			this.testConnection(siteId);
		});

		$(document).on('click', '.aie-regenerate-key', (e) => {
			const siteId = $(e.currentTarget).data('site-id');
			this.regenerateKey(siteId);
		});
	},

	/**
	 * Load all connected sites
	 */
	loadSites() {
		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'aie_content_sync_get_sites',
				nonce: aieContentSync.nonce,
			},
			success: (response) => {
				if (response.success) {
					this.renderSites(response.data.sites);
					this.updateStats(response.data.stats);
				} else {
					this.showNotice('error', response.data.message || window.aieData.i18n.failedLoadSites);
				}
			},
			error: () => {
				this.showNotice('error', window.aieData.i18n.failedLoadSites);
			},
		});
	},

	/**
	 * Load this site's information
	 */
	loadMySiteInfo() {
		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'aie_content_sync_get_my_key',
				nonce: aieContentSync.nonce,
			},
			success: (response) => {
				if (response.success) {
					jQuery('#aie-my-site-name').val(response.data.site_name);
					jQuery('#aie-my-site-url').val(response.data.site_url);
					jQuery('#aie-my-site-key').val(response.data.site_key);
				}
			},
		});
	},

	/**
	 * Render sites table
	 */
	renderSites(sites) {
		const $ = jQuery;
		const $tbody = $('#aie-sites-list');
		$tbody.empty();

		if (!sites || sites.length === 0) {
			$tbody.html(`
				<tr class="aie-no-sites">
					<td colspan="5" style="text-align: center; padding: 40px;">
						<span class="dashicons dashicons-admin-site" style="font-size: 48px; opacity: 0.3;"></span>
						<p style="margin-top: 40px">No connected sites yet. Add your first connection!</p>
					</td>
				</tr>
			`);
			return;
		}

		sites.forEach((site) => {
			const statusClass = `aie-status-${site.status}`;
			const lastSync = site.last_sync_at
				? new Date(site.last_sync_at).toLocaleString()
				: 'Never';

			// Check if premium is active
			const isPremium = typeof aieContentSync !== 'undefined' && aieContentSync.isPremium;

			// Build actions column only for premium users
			const actionsColumn = isPremium ? `
				<td class="column-actions">
					<button type="button" class="button button-small aie-test-connection" data-site-id="${site.id}" title="Test Connection">
						<span class="dashicons dashicons-update"></span>
					</button>
					<button type="button" class="button button-small aie-edit-site" data-site-id="${site.id}" title="Edit">
						<span class="dashicons dashicons-edit"></span>
					</button>
					<button type="button" class="button button-small aie-delete-site" data-site-id="${site.id}" title="Delete">
						<span class="dashicons dashicons-trash"></span>
					</button>
				</td>
			` : '';

			const row = `
				<tr data-site-id="${site.id}">
					<td class="column-name">
						<strong>${this.escapeHtml(site.name)}</strong>
					</td>
					<td class="column-url">
						<a href="${this.escapeHtml(site.remote_url)}" target="_blank" rel="noopener noreferrer">
							${this.escapeHtml(site.remote_url)}
						</a>
					</td>
					<td class="column-status">
						<span class="aie-status-badge ${statusClass}">
							${this.escapeHtml(site.status)}
						</span>
					</td>
					<td class="column-last-sync">
						${lastSync}
					</td>
					${actionsColumn}
				</tr>
			`;

			$tbody.append(row);
		});
	},

	/**
	 * Update statistics
	 */
	updateStats(stats) {
		jQuery('#aie-stat-total').text(stats.total || 0);
		jQuery('#aie-stat-active').text(stats.active || 0);
		jQuery('#aie-stat-error').text(stats.error || 0);
	},

	/**
	 * Show add site modal
	 */
	showAddSiteModal() {
		const $ = jQuery;
		$('#aie-modal-title').text('Add New Site');
		$('#aie-site-form')[0].reset();
		$('#aie-site-id').val('');
		$('#aie-site-api-key').prop('required', true);
		this.hideModalNotice();
		$('#aie-site-modal').show();
	},

	/**
	 * Show edit site modal
	 */
	showEditSiteModal(siteId) {
		const $ = jQuery;
		const $row = $(`tr[data-site-id="${siteId}"]`);
		const site = this.getSiteFromRow($row);

		if (!site) return;

		$('#aie-modal-title').text('Edit Site');
		$('#aie-site-id').val(siteId);
		$('#aie-site-name').val(site.name);
		$('#aie-site-url').val(site.url);
		$('#aie-site-api-key').prop('required', false).val('');
		this.hideModalNotice();
		$('#aie-site-modal').show();
	},

	/**
	 * Get site data from row
	 */
	getSiteFromRow($row) {
		if (!$row.length) return null;

		return {
			name: $row.find('.column-name strong').text(),
			url: $row.find('.column-url a').attr('href'),
		};
	},

	/**
	 * Save site (add or update)
	 */
	saveSite() {
		const $ = jQuery;
		const $form = $('#aie-site-form');
		const siteId = $('#aie-site-id').val();
		const isEdit = !!siteId;

		// Basic validation
		if (!$form[0].checkValidity()) {
			$form[0].reportValidity();
			return;
		}

		// Hide any previous notifications
		this.hideModalNotice();

		const data = {
			action: isEdit ? 'aie_content_sync_update_site' : 'aie_content_sync_add_site',
			nonce: aieContentSync.nonce,
			name: $('#aie-site-name').val(),
			remote_url: $('#aie-site-url').val(),
			direction: 'bidirectional', // Always bidirectional
		};

		// Get API key value
		const apiKey = $('#aie-site-api-key').val();

		if (isEdit) {
			data.site_id = siteId;
			// If API key was provided during edit, include it (to update/validate)
			if (apiKey && apiKey.trim() !== '') {
				data.api_key = apiKey;
			}
		} else {
			data.api_key = apiKey;
		}

		const $saveBtn = $('#aie-save-site-btn');
		// Check if we're validating API key
		const hasApiKey = data.api_key && data.api_key.trim() !== '';
		const buttonText = (isEdit && !hasApiKey) ? 'Updating...' : 'Validating & Saving...';
		$saveBtn.prop('disabled', true).text(buttonText);

		// Show info message when validating API key
		if (hasApiKey) {
			this.showModalNotice('info', 'Validating API key...', 'Please wait while we verify the connection to the remote site.');
		}

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: data,
			success: (response) => {
				if (response.success) {
					const message = response.data.message || 'Operation completed successfully';
					
					// Check if no changes were made
					if (message.includes('No changes')) {
						this.showModalNotice('info', 'No Changes', message);
						// Close modal after delay
						setTimeout(() => {
							$('#aie-site-modal').hide();
						}, 2000);
					} else {
						this.showModalNotice('success', 'Success!', message);
						// Close modal after short delay
						setTimeout(() => {
							$('#aie-site-modal').hide();
							this.loadSites();
							this.showNotice('success', message);
						}, 1500);
					}
				} else {
					this.showModalNotice('error', 'Validation Failed', response.data.message || 'Failed to save site connection');
				}
			},
			error: (xhr) => {
				let errorTitle = 'Connection Error';
				let errorMessage = 'An unexpected error occurred while trying to save the site connection.';
				let errorDetails = [];

				// Check if we have a response from the server with error message
				// WordPress AJAX sends: { success: false, message: "...", data: {...} }
				if (xhr.responseJSON) {
					// Try to get message from different possible locations
					if (xhr.responseJSON.message) {
						errorMessage = xhr.responseJSON.message;
					} else if (xhr.responseJSON.data && xhr.responseJSON.data.message) {
						errorMessage = xhr.responseJSON.data.message;
					}
					
					// Parse specific error types and add helpful details
					if (errorMessage.includes('Cannot connect to remote site')) {
						errorTitle = 'Connection Failed';
						errorDetails.push('Possible reasons:');
						errorDetails.push('- The URL is incorrect or not accessible');
						errorDetails.push('- The remote site is offline');
						errorDetails.push('- Network or firewall issues are blocking the connection');
					} else if (errorMessage.includes('Invalid API key')) {
						errorTitle = 'Invalid API Key';
						errorDetails.push('To resolve this issue:');
						errorDetails.push('- Go to Content Sync page on the remote site');
						errorDetails.push('- Click "Show Details" to reveal the API key');
						errorDetails.push('- Copy the entire key and paste it here');
					} else if (errorMessage.includes('plugin is not installed') || errorMessage.includes('plugin is not active')) {
						errorTitle = 'Plugin Not Found';
						// No additional details needed, message is clear
					} else if (errorMessage.includes('already connected')) {
						errorTitle = 'Duplicate Connection';
						errorDetails.push('This site URL is already in your connected sites list.');
					} else if (errorMessage.includes('required')) {
						errorTitle = 'Validation Error';
						// Field validation errors are clear enough
					} else {
						errorTitle = 'Error';
						// Use the server message as-is for other errors
					}
				} else if (xhr.status === 0) {
					errorTitle = 'Network Error';
					errorMessage = 'Unable to connect to the server. Please check your internet connection.';
				} else if (xhr.status >= 500) {
					errorTitle = 'Server Error';
					errorMessage = `The server returned an error (${xhr.status}). Please try again later.`;
				} else if (xhr.status === 404) {
					errorTitle = 'Not Found';
					errorMessage = 'The requested endpoint was not found. Please check if the plugin is properly installed.';
				}

				this.showModalNotice('error', errorTitle, errorMessage, errorDetails);
			},
			complete: () => {
				$saveBtn.prop('disabled', false).text('Save Connection');
			},
		});
	},

	/**
	 * Delete site
	 */
	deleteSite(siteId) {
		if (!confirm(window.aieData.i18n.confirmDeleteSiteConnection)) {
			return;
		}

		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'aie_content_sync_delete_site',
				nonce: aieContentSync.nonce,
				site_id: siteId,
			},
			success: (response) => {
				if (response.success) {
					this.showNotice('success', response.data.message);
					this.loadSites();
				} else {
					this.showNotice('error', response.data.message || window.aieData.i18n.failedDeleteSite);
				}
			},
			error: () => {
				this.showNotice('error', window.aieData.i18n.failedDeleteSite);
			},
		});
	},

	/**
	 * Test connection to remote site
	 */
	testConnection(siteId) {
		const $ = jQuery;
		const $btn = $(`.aie-test-connection[data-site-id="${siteId}"]`);
		$btn.prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'aie_content_sync_test_connection',
				nonce: aieContentSync.nonce,
				site_id: siteId,
			},
			success: (response) => {
				if (response.success) {
					this.showNotice('success', response.data.message);
				} else {
					this.showNotice('error', response.data.message || window.aieData.i18n.connectionTestFailed);
				}
				// Always reload sites to update stats
				this.loadSites();
			},
			error: () => {
				this.showNotice('error', window.aieData.i18n.connectionTestFailed);
				this.loadSites();
			},
			complete: () => {
				$btn.prop('disabled', false);
			},
		});
	},

	/**
	 * Regenerate API key for a site
	 */
	regenerateKey(siteId) {
		if (
			!confirm(window.aieData.i18n.confirmRegenerateSiteKey)
		) {
			return;
		}

		jQuery.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'aie_content_sync_regenerate_key',
				nonce: aieContentSync.nonce,
				site_id: siteId,
			},
			success: (response) => {
				if (response.success) {
					this.showNotice('success', response.data.message);
					alert(window.aieData.i18n.newApiKey + response.data.api_key);
				} else {
					this.showNotice('error', response.data.message || window.aieData.i18n.failedRegenerateKey);
				}
			},
			error: () => {
				this.showNotice('error', window.aieData.i18n.failedRegenerateKey);
			},
		});
	},

	/**
	 * Toggle my site info visibility
	 */
	toggleMySiteInfo() {
		const $ = jQuery;
		const $info = $('.aie-my-site-info');
		const $btn = $('#aie-toggle-my-site');

		$info.slideToggle();

		if ($info.is(':visible')) {
			$btn.html('<span class="dashicons dashicons-hidden"></span> Hide Details');
		} else {
			$btn.html('<span class="dashicons dashicons-visibility"></span> Show Details');
		}
	},

	/**
	 * Copy this site's API key to clipboard
	 */
	copyMyApiKey() {
		const $ = jQuery;
		const $input = $('#aie-my-site-key');
		$input.select();
		document.execCommand('copy');

		const $btn = $('#aie-copy-my-key');
		const originalText = $btn.html();

		$btn.html('<span class="dashicons dashicons-yes"></span> Copied!');

		setTimeout(() => {
			$btn.html(originalText);
		}, 2000);

		this.showNotice('success', window.aieData.i18n.apiKeyCopied);
	},

	/**
	 * Regenerate this site's API key
	 */
	regenerateMyApiKey() {
		const $ = jQuery;

		// Confirm action
		if (!confirm(window.aieData.i18n.confirmRegenerateMyKey)) {
			return;
		}

		const $btn = $('#aie-regenerate-my-key');
		const originalText = $btn.html();
		$btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Regenerating...');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'aie_content_sync_regenerate_my_key',
				nonce: aieContentSync.nonce,
			},
			success: (response) => {
				if (response.success) {
					// Update the API key field with new key
					$('#aie-my-site-key').val(response.data.site_key);
					
					// Show success message
					this.showNotice('success', response.data.message);
					
					// Briefly show success state on button
					$btn.html('<span class="dashicons dashicons-yes"></span> Regenerated!');
					setTimeout(() => {
						$btn.html(originalText);
					}, 3000);
				} else {
					this.showNotice('error', response.data.message || window.aieData.i18n.failedRegenerateApiKey);
					$btn.html(originalText);
				}
			},
			error: () => {
				this.showNotice('error', window.aieData.i18n.failedRegenerateApiKey);
				$btn.html(originalText);
			},
			complete: () => {
				$btn.prop('disabled', false);
			},
		});
	},

	/**
	 * Show notice message in modal
	 */
	showModalNotice(type, title, message, details = []) {
		const $ = jQuery;
		const icons = {
			error: 'warning',
			success: 'yes-alt',
			warning: 'info',
			info: 'info-outline'
		};

		const icon = icons[type] || 'info';
		const noticeClass = `notice-${type}`;

		let detailsHtml = '';
		if (details.length > 0) {
			detailsHtml = '<ul>';
			details.forEach(detail => {
				detailsHtml += `<li>${this.escapeHtml(detail)}</li>`;
			});
			detailsHtml += '</ul>';
		}

		const $notification = $(`
			<div class="aie-modal-notification ${noticeClass}">
				<span class="dashicons dashicons-${icon}"></span>
				<div class="aie-notification-content">
					<strong>${this.escapeHtml(title)}</strong>
					<p>${this.escapeHtml(message)}</p>
					${detailsHtml}
				</div>
			</div>
		`);

		$('#aie-modal-notification').html($notification).show();
	},

	/**
	 * Hide modal notice
	 */
	hideModalNotice() {
		jQuery('#aie-modal-notification').hide().empty();
	},

	/**
	 * Show notice message
	 */
	showNotice(type, message) {
		const $ = jQuery;
		const noticeClass =
			type === 'error' ? 'notice-error' : type === 'success' ? 'notice-success' : 'notice-info';

		const $notice = $(`
			<div class="notice ${noticeClass} is-dismissible">
				<p>${this.escapeHtml(message)}</p>
			</div>
		`);

		$('#wp-aie-content-sync h1').after($notice);

		// Auto-dismiss after 5 seconds
		setTimeout(() => {
			$notice.fadeOut(() => $notice.remove());
		}, 5000);

		// Make dismissible
		$notice.on('click', '.notice-dismiss', function () {
			$(this).closest('.notice').fadeOut(() => $(this).remove());
		});
	},

	/**
	 * Escape HTML to prevent XSS
	 */
	escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	},
};

export default ContentSyncModule;
