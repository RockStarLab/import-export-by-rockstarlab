/**
 * Post Sync Standalone Module
 * 
 * Handles content synchronization from post list screens
 * This is a standalone version that doesn't require the main app.js
 */

(function($) {
	'use strict';

	const PostSync = {
		/**
		 * Initialize the module
		 */
		init() {
			console.log('AIE PostSync Standalone: Module initialized');
			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents() {
			console.log('AIE PostSync Standalone: Binding events');

			// Open modal when sync button is clicked
			$(document).on('click', '#aie-sync-content-btn', (e) => {
				console.log('AIE PostSync Standalone: Sync button clicked');
				e.preventDefault();
				e.stopPropagation();
				this.openSyncModal();
			});

			// Close modal
			$(document).on('click', '.aie-modal-close', (e) => {
				e.preventDefault();
				this.closeSyncModal();
			});

			$(document).on('click', '.aie-modal', (e) => {
				if (e.target === e.currentTarget) {
					this.closeSyncModal();
				}
			});

			// Enable/disable sync buttons based on site selection
			$(document).on('change', '#aie-sync-site-select', () => {
				this.updateSyncButtons();
			});

			// Handle Push button
			$(document).on('click', '#aie-sync-push-btn', (e) => {
				e.preventDefault();
				this.syncContent('push');
			});

			// Handle Pull button
			$(document).on('click', '#aie-sync-pull-btn', (e) => {
				e.preventDefault();
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
			console.log('AIE PostSync Standalone: Opening modal');
			const selectedIds = this.getSelectedPostIds();

			console.log('AIE PostSync Standalone: Selected IDs:', selectedIds);
			console.log('AIE PostSync Standalone: Modal element exists:', $('#aie-sync-modal').length);

			if (selectedIds.length === 0) {
				console.log('AIE PostSync Standalone: No posts selected');
				const isEditPage = $('#post_ID').length > 0;
				alert(isEditPage ? 'Please save the post first' : 'Please select at least one post');
				return;
			}

		// Update selected count with proper text and hide for single post
		const countText = selectedIds.length === 1 ? '1 post' : `${selectedIds.length} posts`;
		$('#aie-selected-count').text(countText);
		
		// Hide "Selected posts" section for single post
		if (selectedIds.length === 1) {
			$('.aie-sync-info').hide();
		} else {
			$('.aie-sync-info').show();
		}
		
		console.log('AIE PostSync Standalone: Updated selected count');			// Reset form
			$('#aie-sync-site-select').val('');
			$('#aie-sync-progress').hide();
			$('#aie-sync-result').hide();
			this.updateSyncButtons();

			console.log('AIE PostSync Standalone: About to show modal');
			// Show modal
			$('#aie-sync-modal').fadeIn(200);
			console.log('AIE PostSync Standalone: Modal fadeIn called');
		},

		/**
		 * Close sync modal
		 */
		closeSyncModal() {
			$('#aie-sync-modal').fadeOut(200);
		},

		/**
		 * Get selected post IDs
		 */
		getSelectedPostIds() {
			const ids = [];

			// Check if we're on post edit page
			const postIdInput = $('#post_ID');
			if (postIdInput.length && postIdInput.val()) {
				// Single post edit page
				ids.push(postIdInput.val());
			} else {
				// Post list page - get checked items
				$('tbody .check-column input[type="checkbox"]:checked').each(function() {
					const id = $(this).val();
					if (id) {
						ids.push(id);
					}
				});
			}

			return ids;
		},

		/**
		 * Update sync button states
		 */
		updateSyncButtons() {
			const siteSelected = $('#aie-sync-site-select').val() !== '';
			$('#aie-sync-push-btn, #aie-sync-pull-btn').prop('disabled', !siteSelected);
		},

		/**
		 * Sync content (push or pull)
		 */
		syncContent(direction) {
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
		const siteFullText = $('#aie-sync-site-select option:selected').text();
		// Extract only site name (before opening parenthesis)
		const siteName = siteFullText.split('(')[0].trim();
		const action = direction === 'push' ? 'push to' : 'pull from';
		const postsText = postIds.length === 1 ? '1 post' : `${postIds.length} posts`;
		const message = `Are you sure you want to ${action} ${siteName}? This will affect ${postsText}.`;

		if (!confirm(message)) {
			return;
		}			// Show progress
			$('#aie-sync-progress').show();
			$('#aie-sync-result').hide();
			$('.aie-progress-fill').css('width', '0%');
			$('.aie-progress-text').text(`Starting ${direction}...`);

			// Disable buttons
			$('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', true);

			// Make AJAX request
			const nonce = (typeof aiePostSyncData !== 'undefined' && aiePostSyncData.nonce) 
				? aiePostSyncData.nonce 
				: '';
			const ajaxUrl = (typeof aiePostSyncData !== 'undefined' && aiePostSyncData.ajaxurl) 
				? aiePostSyncData.ajaxurl 
				: ajaxurl;

			console.log('AIE PostSync Standalone: Using nonce:', nonce);
			console.log('AIE PostSync Standalone: Using ajaxUrl:', ajaxUrl);

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: `aie_content_sync_${direction}`,
					nonce: nonce,
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

	// Initialize on document ready
	$(document).ready(() => {
		PostSync.init();
	});

	// Make it globally accessible
	window.aiePostSync = PostSync;

})(jQuery);
