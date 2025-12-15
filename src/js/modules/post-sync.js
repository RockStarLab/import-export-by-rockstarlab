/**
 * Post Sync Module
 * 
 * Handles content synchronization from post list screens
 */

const PostSync = {
	/**
	 * Initialize the module
	 */
	init() {
		console.log('AIE PostSync: Module initialized');
		this.bindEvents();
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $ = jQuery;

		console.log('AIE PostSync: Binding events');

		// Open modal when sync button is clicked
		$(document).on('click', '#aie-sync-content-btn', (e) => {
			console.log('AIE PostSync: Sync button clicked in module', e);
			e.preventDefault();
			e.stopPropagation();
			this.openSyncModal();
		});

		// Close modal
		$(document).on('click', '.aie-modal-close, .aie-modal', (e) => {
			if (e.target === e.currentTarget) {
				this.closeSyncModal();
			}
		});

		// Enable/disable sync buttons based on site selection
		$(document).on('change', '#aie-sync-site-select', () => {
			this.updateSyncButtons();
		});

		// Handle Push button
		$(document).on('click', '#aie-sync-push-btn', () => {
			this.syncContent('push');
		});

		// Handle Pull button
		$(document).on('click', '#aie-sync-pull-btn', () => {
			this.syncContent('pull');
		});

		// Close modal on Escape key
		$(document).on('keydown', (e) => {
			if (e.key === 'Escape' && $('#aie-sync-modal').is(':visible')) {
				this.closeSyncModal();
			}
		});
	},

	/**
	 * Open sync modal
	 */
	openSyncModal() {
		console.log('AIE PostSync: Opening modal');
		const $ = jQuery;
		const selectedIds = this.getSelectedPostIds();

		console.log('AIE PostSync: Selected IDs:', selectedIds);
		console.log('AIE PostSync: Modal element exists:', $('#aie-sync-modal').length);

		if (selectedIds.length === 0) {
			console.log('AIE PostSync: No posts selected');
			alert('Please select at least one post');
			return;
		}

		// Update selected count
		$('#aie-selected-count').text(selectedIds.length);
		console.log('AIE PostSync: Updated selected count');

		// Reset form
		$('#aie-sync-site-select').val('');
		$('#aie-sync-progress').hide();
		$('#aie-sync-result').hide();
		this.updateSyncButtons();

		console.log('AIE PostSync: About to show modal');
		// Show modal
		$('#aie-sync-modal').fadeIn(200);
		console.log('AIE PostSync: Modal fadeIn called');
	},

	/**
	 * Close sync modal
	 */
	closeSyncModal() {
		jQuery('#aie-sync-modal').fadeOut(200);
	},

	/**
	 * Get selected post IDs
	 */
	getSelectedPostIds() {
		const $ = jQuery;
		const ids = [];

		$('tbody .check-column input[type="checkbox"]:checked').each(function () {
			const id = $(this).val();
			if (id) {
				ids.push(id);
			}
		});

		return ids;
	},

	/**
	 * Update sync button states
	 */
	updateSyncButtons() {
		const $ = jQuery;
		const siteSelected = $('#aie-sync-site-select').val() !== '';

		$('#aie-sync-push-btn, #aie-sync-pull-btn').prop('disabled', !siteSelected);
	},

	/**
	 * Sync content (push or pull)
	 */
	syncContent(direction) {
		const $ = jQuery;
		const siteId = $('#aie-sync-site-select').val();
		const postIds = this.getSelectedPostIds();

		if (!siteId) {
			alert('Please select a site');
			return;
		}

		if (postIds.length === 0) {
			alert('No posts selected');
			return;
		}

		// Confirm action
		const siteName = $('#aie-sync-site-select option:selected').text();
		const action = direction === 'push' ? 'push to' : 'pull from';
		const message = `Are you sure you want to ${action} ${siteName}?\n\nThis will affect ${postIds.length} post(s).`;

		if (!confirm(message)) {
			return;
		}

		// Show progress
		$('#aie-sync-progress').show();
		$('#aie-sync-result').hide();
		$('.aie-progress-fill').css('width', '0%');
		$('.aie-progress-text').text(`Starting ${direction}...`);

		// Disable buttons
		$('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', true);

		// Make AJAX request
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: `aie_content_sync_${direction}`,
				nonce: aieContentSync.nonce,
				site_id: siteId,
				post_ids: postIds,
			},
			success: (response) => {
				if (response.success) {
					$('.aie-progress-fill').css('width', '100%');
					$('.aie-progress-text').text('Completed!');

					setTimeout(() => {
						$('#aie-sync-progress').hide();
						this.showResult('success', response.data.message || 'Sync completed successfully');
					}, 500);
				} else {
					$('#aie-sync-progress').hide();
					this.showResult('error', response.data.message || 'Sync failed');
				}
			},
			error: (xhr) => {
				$('#aie-sync-progress').hide();
				
				let errorMessage = 'An error occurred during sync';
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					errorMessage = xhr.responseJSON.data.message;
				}
				
				this.showResult('error', errorMessage);
			},
			complete: () => {
				// Re-enable buttons
				$('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', false);
				this.updateSyncButtons();
			},
		});
	},

	/**
	 * Show sync result
	 */
	showResult(type, message) {
		const $ = jQuery;
		const $result = $('#aie-sync-result');

		$result
			.removeClass('notice-success notice-error')
			.addClass(`notice notice-${type}`)
			.html(`<p>${message}</p>`)
			.fadeIn(200);

		// Auto-hide success messages
		if (type === 'success') {
			setTimeout(() => {
				$result.fadeOut(200);
				this.closeSyncModal();
				// Reload page to show updated content
				location.reload();
			}, 2000);
		}
	},
};

export default PostSync;
