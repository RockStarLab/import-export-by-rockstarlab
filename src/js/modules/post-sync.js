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
		this.bindEvents();
	},

	/**
	 * Bind event handlers
	 */
	bindEvents() {
		const $ = jQuery;

		// Open modal when sync button is clicked
		$(document).on('click', '#aie-sync-content-btn', (e) => {
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
		const $ = jQuery;
		const selectedIds = this.getSelectedPostIds();

		if (selectedIds.length === 0) {
			alert(window.aieData.i18n.selectAtLeastOnePost);
			return;
		}

		// Update selected count
		$('#aie-selected-count').text(selectedIds.length);

		// Reset form
		$('#aie-sync-site-select').val('');
		$('#aie-sync-progress').hide();
		$('#aie-sync-result').hide();
		this.updateSyncButtons();

		// Show modal
		$('#aie-sync-modal').fadeIn(200);
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
		const actionText = direction === 'push' 
			? (window.aieData?.i18n?.pushTo || 'push to')
			: (window.aieData?.i18n?.pullFrom || 'pull from');
		
		const message = (window.aieData?.i18n?.confirmSyncAction || 'Are you sure you want to %1$s %2$s?\n\nThis will affect %3$s post(s).')
			.replace('%1$s', actionText)
			.replace('%2$s', siteName)
			.replace('%3$s', postIds.length);

		if (!confirm(message)) {
			return;
		}

		// Show enhanced progress
		$('#aie-sync-progress').show();
		$('#aie-sync-result').hide();
		
		const preparingMsg = direction === 'push'
			? (window.aieData?.i18n?.preparingToPush || 'Preparing to push content...')
			: (window.aieData?.i18n?.preparingToPull || 'Preparing to pull content...');
		
		this.updateProgress(0, preparingMsg, {
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
				const progressMsg = direction === 'push'
					? (window.aieData?.i18n?.uploadingContent || 'Uploading content...')
					: (window.aieData?.i18n?.downloadingContent || 'Downloading content...');
				
				this.updateProgress(simulatedProgress, progressMsg, {
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

		const ajaxData = {
			action: `aie_content_sync_${direction}`,
			nonce: nonce,
			site_id: siteId,
			post_ids: postIds,
		};

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: ajaxData,
			success: (response) => {
				clearInterval(progressInterval);
						if (response.success) {
				const data = response.data || {};
				const imageCount = data.images_synced || 0;
				
				this.updateProgress(100, window.aieData?.i18n?.operationCompleted || 'Completed successfully!', {
					posts: postIds.length,
					images: imageCount,
					total: postIds.length
				});

					setTimeout(() => {
						$('#aie-sync-progress').hide();
						
						// Build detailed success message
						let successMsg = response.data.message || (window.aieData?.i18n?.syncCompletedSuccessfully || 'Sync completed successfully');
						if (data.created && data.updated) {
							const createdUpdatedMsg = (window.aieData?.i18n?.createdPosts || '✓ Created %d post(s), Updated %d post(s)')
								.replace('%d', data.created)
								.replace('%d', data.updated);
							successMsg = createdUpdatedMsg;
						}
						if (imageCount > 0) {
							const syncedImagesMsg = (window.aieData?.i18n?.syncedImages || '✓ Synced %d image(s)')
								.replace('%d', imageCount);
							successMsg += `<br>${syncedImagesMsg}`;
						}
						
						this.showResult('success', successMsg);
					}, 800);
				} else {
					this.updateProgress(0, window.aieData?.i18n?.syncFailed || 'Sync failed', {});
					setTimeout(() => {
						$('#aie-sync-progress').hide();
						this.showResult('error', response.data.message || (window.aieData?.i18n?.syncFailed || 'Sync failed'));
					}, 500);
				}
			},
			error: (xhr) => {
				clearInterval(progressInterval);
				$('#aie-sync-progress').hide();
				
				let errorMessage = window.aieData?.i18n?.errorOccurredDuringSync || 'An error occurred during sync';
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
			const postsMsg = (window.aieData?.i18n?.postsProgress || 'Posts: %1$s/%2$s')
				.replace('%1$s', details.posts)
				.replace('%2$s', details.total);
			progressText += `<br><span class="progress-details">${postsMsg}</span>`;
		}
		
		if (details.images !== undefined && details.images > 0) {
			const imagesMsg = (window.aieData?.i18n?.imagesSyncedProgress || 'Images synced: %d')
				.replace('%d', details.images);
			progressText += `<br><span class="progress-details">${imagesMsg}</span>`;
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
