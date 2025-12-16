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
			alert(window.aieData.i18n.selectAtLeastOnePost);
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
			alert(window.aieData.i18n.selectSite);
			return;
		}

		if (postIds.length === 0) {
			alert(window.aieData.i18n.noPostsSelected);
			return;
		}

		// Confirm action
		const siteName = $('#aie-sync-site-select option:selected').text();
		const action = direction === 'push' ? 'push to' : 'pull from';
		const message = `Are you sure you want to ${action} ${siteName}?\n\nThis will affect ${postIds.length} post(s).`;

		if (!confirm(message)) {
			return;
		}

		// Show enhanced progress
		$('#aie-sync-progress').show();
		$('#aie-sync-result').hide();
		this.updateProgress(0, `Preparing to ${direction} content...`, {
			posts: 0,
			images: 0,
			total: postIds.length
		});

		// Disable buttons
		$('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', true);

		// Simulate progress for better UX
		let simulatedProgress = 10;
		const progressInterval = setInterval(() => {
			if (simulatedProgress < 90) {
				simulatedProgress += 5;
				this.updateProgress(simulatedProgress, `${direction === 'push' ? 'Uploading' : 'Downloading'} content...`, {
					posts: Math.floor((postIds.length * simulatedProgress) / 100),
					total: postIds.length
				});
			}
		}, 300);

		// Make AJAX request
		const nonce = (typeof aiePostSyncData !== 'undefined' && aiePostSyncData.nonce) 
			? aiePostSyncData.nonce 
			: (typeof aieContentSync !== 'undefined' && aieContentSync.nonce) 
				? aieContentSync.nonce 
				: (typeof aieData !== 'undefined' && aieData.nonce) 
					? aieData.nonce 
					: '';

		const ajaxUrl = (typeof ajaxurl !== 'undefined') 
			? ajaxurl 
			: (typeof aiePostSyncData !== 'undefined' && aiePostSyncData.ajaxurl) 
				? aiePostSyncData.ajaxurl 
				: '/wp-admin/admin-ajax.php';

		console.log('AIE PostSync: Using nonce:', nonce);
		console.log('AIE PostSync: Using ajaxUrl:', ajaxUrl);

		const ajaxData = {
			action: `aie_content_sync_${direction}`,
			nonce: nonce,
			site_id: siteId,
			post_ids: postIds,
		};

		console.log('AIE PostSync: Sending data:', ajaxData);

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: ajaxData,
			success: (response) => {
				clearInterval(progressInterval);
				
				if (response.success) {
					const data = response.data || {};
					const imageCount = data.images_synced || 0;
					
					this.updateProgress(100, 'Completed successfully!', {
						posts: postIds.length,
						images: imageCount,
						total: postIds.length
					});

					setTimeout(() => {
						$('#aie-sync-progress').hide();
						
						// Build detailed success message
						let successMsg = response.data.message || 'Sync completed successfully';
						if (data.created && data.updated) {
							successMsg = `✓ Created ${data.created} post(s), Updated ${data.updated} post(s)`;
						}
						if (imageCount > 0) {
							successMsg += `<br>✓ Synced ${imageCount} image(s)`;
						}
						
						this.showResult('success', successMsg);
					}, 800);
				} else {
					this.updateProgress(0, 'Sync failed', {});
					setTimeout(() => {
						$('#aie-sync-progress').hide();
						this.showResult('error', response.data.message || 'Sync failed');
					}, 500);
				}
			},
			error: (xhr) => {
				clearInterval(progressInterval);
				$('#aie-sync-progress').hide();
				
				let errorMessage = 'An error occurred during sync';
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					errorMessage = xhr.responseJSON.data.message;
				}
				
				this.showResult('error', errorMessage);
			},
			complete: () => {
				clearInterval(progressInterval);
				// Re-enable buttons
				$('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', false);
				this.updateSyncButtons();
			},
		});
	},

	/**
	 * Update progress bar with details
	 */
	updateProgress(percent, message, details = {}) {
		const $ = jQuery;
		
		$('.aie-progress-fill').css('width', `${percent}%`);
		
		// Build detailed progress message
		let progressText = `<strong>${message}</strong>`;
		
		if (details.posts !== undefined && details.total) {
			progressText += `<br><span class="progress-details">Posts: ${details.posts}/${details.total}</span>`;
		}
		
		if (details.images !== undefined && details.images > 0) {
			progressText += `<br><span class="progress-details">Images synced: ${details.images}</span>`;
		}
		
		if (percent > 0 && percent < 100) {
			progressText += `<br><span class="progress-percentage">${Math.round(percent)}%</span>`;
		}
		
		$('.aie-progress-text').html(progressText);
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
