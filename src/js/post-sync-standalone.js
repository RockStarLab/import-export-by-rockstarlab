/**
 * Post Sync Standalone Module
 * 
 * Handles content synchronization from post list screens
 * This is a standalone version that doesn't require the main app.js
 */

(function($) {
	'use strict';

	const PostSync = {
		// Flag to track if sync is in progress
		isSyncing: false,

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
			e.stopPropagation();
			const $modal = $(e.currentTarget).closest('.aie-modal');
			const modalId = $modal.attr('id');
			
			if (modalId === 'aie-browse-modal') {
				this.closeBrowseModal(false); // Close everything when clicking X button
			} else if (modalId === 'aie-mapping-modal') {
				this.closeMappingModal();
			} else if (modalId === 'aie-sync-modal') {
				this.closeSyncModal();
			}
		});

		$(document).on('click', '.aie-modal', (e) => {
			if (e.target === e.currentTarget) {
				const modalId = $(e.target).attr('id');
				if (modalId === 'aie-browse-modal') {
					this.closeBrowseModal(false); // Close everything when clicking outside modal
				} else if (modalId === 'aie-mapping-modal') {
					this.closeMappingModal();
				} else {
					this.closeSyncModal();
				}
			}
		});

		// Close modal on backdrop click
		$(document).on('click', '.aie-modal-backdrop', (e) => {
			const $modal = $(e.target).closest('.aie-modal');
			if ($modal.attr('id') === 'aie-browse-modal') {
				this.closeBrowseModal(false); // Close everything when clicking backdrop
			}
		});			// Enable/disable sync buttons based on site selection
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

			// Handle Browse Remote button
			$(document).on('click', '#aie-browse-remote-btn, #aie-browse-remote-btn-alt', (e) => {
				e.preventDefault();
				this.openBrowseModal();
			});

			// Browse modal - Search (debounced)
			let searchTimeout;
			$(document).on('input', '#aie-browse-search', (e) => {
				clearTimeout(searchTimeout);
				searchTimeout = setTimeout(() => {
					this.browseState.searchQuery = $(e.target).val();
					this.browseState.currentPage = 1;
					this.loadRemotePosts();
				}, 500);
			});

			// Browse modal - Status filter
			$(document).on('click', '.aie-filter-item', (e) => {
				const $item = $(e.currentTarget);
				$('.aie-filter-item').removeClass('active');
				$item.addClass('active');
				
				this.browseState.currentFilter = $item.data('status');
				this.browseState.currentPage = 1;
				this.loadRemotePosts();
			});

			// Browse modal - Post toggle (expand/collapse)
			$(document).on('click', '.aie-post-toggle', (e) => {
				e.stopPropagation();
				const $toggle = $(e.currentTarget);
				const $item = $toggle.closest('.aie-post-item');
				const postId = parseInt($item.data('post-id'));
				const $children = $item.next('.aie-post-children');

				if ($toggle.hasClass('expanded')) {
					// Collapse
					$toggle.removeClass('expanded');
					$children.slideUp(200);
					this.browseState.expandedPosts.delete(postId);
				} else {
					// Expand
					$toggle.addClass('expanded');
					
					// Load children if not loaded yet
					if ($children.children().length === 0) {
						this.loadChildrenPosts(postId, $children);
					} else {
						$children.slideDown(200);
					}
					
					this.browseState.expandedPosts.add(postId);
				}
			});

			// Browse modal - Post checkbox
			$(document).on('change', '.aie-post-checkbox', (e) => {
				const $checkbox = $(e.currentTarget);
				const postId = parseInt($checkbox.val());
				const $item = $checkbox.closest('.aie-post-item');

				if ($checkbox.prop('checked')) {
					this.browseState.selectedPosts.add(postId);
					$item.addClass('selected');
				} else {
					this.browseState.selectedPosts.delete(postId);
					$item.removeClass('selected');
				}

				this.updateBrowseSelection();
			});

			// Browse modal - Pagination
			$(document).on('click', '#aie-browse-prev-page', () => {
				if (this.browseState.currentPage > 1) {
					this.browseState.currentPage--;
					this.loadRemotePosts();
				}
			});

			$(document).on('click', '#aie-browse-next-page', () => {
				if (this.browseState.currentPage < this.browseState.totalPages) {
					this.browseState.currentPage++;
					this.loadRemotePosts();
				}
			});

			// Browse modal - Cancel button
			$(document).on('click', '#aie-browse-cancel-btn', (e) => {
				e.preventDefault();
				this.closeBrowseModal();
			});

			// Browse modal - Pull button
			$(document).on('click', '#aie-browse-pull-btn', (e) => {
				e.preventDefault();
				this.pullSelectedPosts();
			});

			// Close modal on Escape key
			$(document).on('keydown', (e) => {
				if (e.key === 'Escape' && $('#aie-sync-modal').is(':visible')) {
					this.closeSyncModal();
				}
				if (e.key === 'Escape' && $('#aie-browse-modal').is(':visible')) {
					this.closeBrowseModal(false); // Close everything when pressing Escape
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
				// If we're on the post edit page, keep the original behavior (ask to save)
				if (isEditPage) {
					alert('Please save the post first');
					return;
				}
				// If we're on the posts list (no selection), open the Browse & Pull modal directly
				this.openBrowseModal();
				return;
			}

		// Update selected count with proper text
		const countText = selectedIds.length === 1 ? '1 post' : `${selectedIds.length} posts`;
		$('#aie-selected-count').text(countText);
		
		console.log('AIE PostSync Standalone: Updated selected count');
		
		// Reset form
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
		closeSyncModal(keepSiteSelection = false) {
			// Reset syncing flag
			this.isSyncing = false;

			$('#aie-sync-modal').fadeOut(200, () => {
				// Reset site selection (unless specified to keep it)
				if (!keepSiteSelection) {
					$('#aie-sync-site-select').val('');
				}
				
				// Reset modal state - show initial sections again
				$('.aie-sync-info, .aie-form-group, .aie-sync-direction, .aie-browse-section').css('display', '');
				$('#aie-sync-progress, #aie-sync-result, .aie-no-selection-message').css('display', 'none');
				$('.aie-progress-fill').css('width', '0%');
				
				// Update button states (unless we're keeping site selection for browse modal)
				if (!keepSiteSelection) {
					this.updateSyncButtons();
				}
			});
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
			// Don't update UI if sync is in progress
			if (this.isSyncing) {
				return;
			}

			const siteSelected = $('#aie-sync-site-select').val() !== '';
			const selectedIds = this.getSelectedPostIds();
			
			// Show/hide sections based on post selection
			if (selectedIds.length > 0) {
				// Has selected posts - show push/pull and browse sections
				$('.aie-sync-direction').show();
				$('.aie-browse-section').show();
				$('.aie-sync-info').show();
				$('.aie-no-selection-message').hide();
				$('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-browse-remote-btn').prop('disabled', !siteSelected);
			} else {
				// No posts selected - hide push/pull, show only browse message
				$('.aie-sync-direction').hide();
				$('.aie-browse-section').hide();
				$('.aie-sync-info').hide();
				$('.aie-no-selection-message').show();
				$('#aie-browse-remote-btn-alt').prop('disabled', !siteSelected);
			}
			
			// If we have a pending browse modal request and site is now selected, open it
			if (this.pendingBrowseModal && siteSelected) {
				this.pendingBrowseModal = false;
				this.closeSyncModal(true); // Keep site selection when transitioning to browse modal
				// Small delay to allow modal close animation
				setTimeout(() => {
					this.openBrowseModal();
				}, 250);
			}
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

			// Store sync direction and open mapping modal
			this.currentSyncDirection = direction;
			this.currentSiteId = siteId;
			this.currentPostIds = postIds;
			
			// Close site selection modal
			this.closeSyncModal();
			
			// Open mapping modal
			this.openMappingModal(direction, siteId, postIds);
		},

		/**
		 * Open mapping modal
		 */
		openMappingModal(direction, siteId, postIds) {
			$('#aie-mapping-modal').fadeIn(200);
			$('#aie-mapping-loading').show();
			$('#aie-mapping-table-container').hide();
			$('#aie-mapping-confirm-btn').prop('disabled', true);
			
			// Load local posts info and remote posts list
			this.loadMappingData(direction, siteId, postIds);
		},

		/**
		 * Close mapping modal
		 */
		closeMappingModal() {
			$('#aie-mapping-modal').fadeOut(200);
		},

		/**
		 * Load mapping data
		 */
		loadMappingData(direction, siteId, postIds) {
			const nonce = (typeof aiePostSyncData !== 'undefined' && aiePostSyncData.nonce) 
				? aiePostSyncData.nonce 
				: '';
			const ajaxUrl = (typeof aiePostSyncData !== 'undefined' && aiePostSyncData.ajaxurl) 
				? aiePostSyncData.ajaxurl 
				: ajaxurl;

			// Get remote posts list
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'aie_content_sync_get_remote_posts',
					nonce: nonce,
					site_id: siteId,
					post_type: 'any',
				},
				success: (response) => {
					if (response.success && response.data.posts) {
						this.remotePosts = response.data.posts;
						this.renderMappingTable(postIds, response.data.posts);
						$('#aie-mapping-loading').hide();
						$('#aie-mapping-table-container').fadeIn(200);
						$('#aie-mapping-confirm-btn').prop('disabled', false);
					} else {
						alert('Failed to load remote posts: ' + (response.data?.message || 'Unknown error'));
						this.closeMappingModal();
					}
				},
				error: (xhr) => {
					alert('Failed to connect to remote site');
					this.closeMappingModal();
				}
			});
		},

		/**
		 * Render mapping table
		 */
		renderMappingTable(localPostIds, remotePosts) {
			const $tbody = $('#aie-mapping-tbody');
			$tbody.empty();

			localPostIds.forEach(postId => {
				const row = this.createMappingRow(postId, remotePosts);
				$tbody.append(row);
			});

			// Bind events
			this.bindMappingEvents();
		},

		/**
		 * Create mapping table row
		 */
		createMappingRow(postId, remotePosts) {
			// Get local post info
			const postTitle = $(`#post-${postId} .row-title`).text() || 
							  $('.editor-post-title__input').val() || 
							  'Post #' + postId;
			const postType = $('body').attr('class').match(/post-type-(\S+)/)?.[1] || 'post';

			const $row = $('<tr>').attr('data-local-id', postId);

			// Local post column
			const $localCol = $('<td>').addClass('aie-local-post').html(`
				<div class="aie-local-post-info">
					<h4>${postTitle}</h4>
					<div class="aie-post-meta">
						<span class="aie-post-type">${postType}</span>
						<span class="aie-post-id">ID: ${postId}</span>
					</div>
				</div>
			`);

			// Arrow column
			const $arrowCol = $('<td>').addClass('aie-sync-arrow').html('→');

			// Remote action column
			const $remoteCol = $('<td>').addClass('aie-remote-post');
			const $select = $('<select>').addClass('aie-remote-select').attr('data-local-id', postId);

			// Add "Create New" option
			$select.append(`<option value="new" class="aie-option-new">➕ Create New Post</option>`);
			$select.append(`<option value="" disabled>──────────</option>`);

			// Add remote posts options
			remotePosts.forEach(post => {
				$select.append(`<option value="${post.id}" class="aie-option-update">🔄 Update: ${post.title} (ID: ${post.id})</option>`);
			});

			const $wrapper = $('<div>').addClass('aie-remote-select-wrapper aie-action-new');
			$wrapper.append($select);
			$remoteCol.append($wrapper);

			$row.append($localCol, $arrowCol, $remoteCol);

			return $row;
		},

		/**
		 * Bind mapping events
		 */
		bindMappingEvents() {
			// Close mapping modal
			$(document).off('click', '.aie-modal-close').on('click', '.aie-modal-close', (e) => {
				this.closeMappingModal();
			});

			// Cancel button
			$(document).off('click', '#aie-mapping-cancel-btn').on('click', '#aie-mapping-cancel-btn', (e) => {
				e.preventDefault();
				this.closeMappingModal();
				// Reopen site selection modal
				this.openSyncModal();
			});

			// Confirm button
			$(document).off('click', '#aie-mapping-confirm-btn').on('click', '#aie-mapping-confirm-btn', (e) => {
				e.preventDefault();
				this.confirmMapping();
			});

			// Auto-match button
			$(document).off('click', '#aie-auto-match-btn').on('click', '#aie-auto-match-btn', (e) => {
				e.preventDefault();
				this.autoMatchByTitle();
			});

			// Create all new button
			$(document).off('click', '#aie-create-all-new-btn').on('click', '#aie-create-all-new-btn', (e) => {
				e.preventDefault();
				$('.aie-remote-select').val('new').trigger('change');
			});

			// Select change
			$(document).off('change', '.aie-remote-select').on('change', '.aie-remote-select', function() {
				const value = $(this).val();
				const $wrapper = $(this).closest('.aie-remote-select-wrapper');
				
				$wrapper.removeClass('aie-action-new aie-action-update');
				if (value === 'new') {
					$wrapper.addClass('aie-action-new');
				} else {
					$wrapper.addClass('aie-action-update');
				}
			});
		},

		/**
		 * Auto-match posts by title
		 */
		autoMatchByTitle() {
			$('.aie-remote-select').each((i, select) => {
				const $select = $(select);
				const localId = $select.data('local-id');
				const localTitle = $(`[data-local-id="${localId}"] .aie-local-post-info h4`).text().trim().toLowerCase();

				// Find matching remote post by title
				let matchFound = false;
				$select.find('option').each(function() {
					const optionText = $(this).text().toLowerCase();
					const optionValue = $(this).val();
					
					if (optionValue !== 'new' && optionValue !== '' && optionText.includes(localTitle)) {
						$select.val(optionValue).trigger('change');
						matchFound = true;
						return false; // break
					}
				});

				// If no match, set to "new"
				if (!matchFound) {
					$select.val('new').trigger('change');
				}
			});
		},

		/**
		 * Confirm mapping and start sync
		 */
		confirmMapping() {
			// Collect mapping
			const postMapping = {};
			$('.aie-remote-select').each(function() {
				const localId = $(this).data('local-id');
				const remoteId = $(this).val();
				
				if (remoteId === 'new') {
					postMapping[localId] = null;
				} else if (remoteId) {
					postMapping[localId] = parseInt(remoteId);
				}
			});

			console.log('Post Mapping:', postMapping);

			// Close mapping modal
			this.closeMappingModal();

			// Start actual sync with mapping
			this.performSync(this.currentSyncDirection, this.currentSiteId, this.currentPostIds, postMapping);
		},

		/**
		 * Perform actual sync
		 */
		performSync(direction, siteId, postIds, postMapping) {
			// Set syncing flag
			this.isSyncing = true;

			// Open sync modal to show progress
			$('#aie-sync-modal').fadeIn(200);
			
			// Hide initial content and show progress
			$('.aie-sync-info, .aie-form-group, .aie-sync-direction, .aie-browse-section, .aie-no-selection-message').css('display', 'none');
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

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: `aie_content_sync_${direction}`,
					nonce: nonce,
					site_id: siteId,
					post_ids: postIds,
					post_mapping: JSON.stringify(postMapping),
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
		 * Open browse remote posts modal
		 */
		openBrowseModal() {
			const siteId = $('#aie-sync-site-select').val();
			if (!siteId) {
				// If no site selected, open the main sync modal to select a site first
				$('#aie-sync-site-select').val('');
				$('#aie-sync-progress').hide();
				$('#aie-sync-result').hide();
				this.updateSyncButtons();
				
				$('.aie-sync-direction').hide();
				$('.aie-sync-info').hide();
				
				$('#aie-sync-modal').fadeIn(200);
				
				// Store flag that we want to open browse modal after site selection
				this.pendingBrowseModal = true;
				return;
			}

			// Initialize browse state
			this.browseState = {
				siteId: siteId,
				postType: this.getCurrentPostType(),
				currentPage: 1,
				totalPages: 1,
				selectedPosts: new Set(),
				currentFilter: '',
				searchQuery: '',
				expandedPosts: new Set(),
			};

			// Reset modal UI
			$('#aie-browse-search').val('');
			$('#aie-browse-posts-tree').empty().hide();
			$('#aie-browse-loading').show();
			$('#aie-browse-pagination').hide();
			$('#aie-browse-pull-btn').prop('disabled', true);
			$('#aie-browse-selected-count').text('0');
			
			// Reset filters
			$('.aie-filter-item').removeClass('active');
			$('.aie-filter-item[data-status=""]').addClass('active');
			
			// Show browse modal
			$('#aie-browse-modal').fadeIn(200);

			// Load remote posts
			this.loadRemotePosts();
		},

		/**
		 * Close browse modal
		 * @param {boolean} returnToChooseSite - If true, return to Choose Site modal; if false, close everything
		 */
		closeBrowseModal(returnToChooseSite = true) {
			$('#aie-browse-modal').fadeOut(200, () => {
				if (returnToChooseSite) {
					// Return back to Choose Site modal with site selection preserved
					$('#aie-sync-modal').fadeIn(200, () => {
						// Update UI to reflect current state (hide Push/Pull if no posts selected)
						this.updateSyncButtons();
					});
				} else {
					// Close everything and reset
					$('#aie-sync-site-select').val('').trigger('change');
					this.updateSyncButtons();
				}
			});
		},

		/**
		 * Get current post type from screen
		 */
		getCurrentPostType() {
			// Try to get from post edit screen
			if ($('#post_type').length) {
				return $('#post_type').val();
			}

			// Try to get from post list screen
			if (window.typenow) {
				return window.typenow;
			}

			// Try to get from URL
			const urlParams = new URLSearchParams(window.location.search);
			if (urlParams.has('post_type')) {
				return urlParams.get('post_type');
			}

			// Default to post
			return 'post';
		},

		/**
		 * Load remote posts with pagination and filters
		 */
		loadRemotePosts() {
			if (typeof aiePostSyncData === 'undefined') {
				this.showBrowseError('Plugin data not loaded. Please refresh the page.');
				return;
			}

			const ajaxUrl = aiePostSyncData.ajaxurl; // lowercase 'ajaxurl' to match PHP localization
			const nonce = aiePostSyncData.nonce;

			$('#aie-browse-loading').show();
			$('#aie-browse-posts-tree').hide();

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'aie_content_sync_get_remote_posts',
					nonce: nonce,
					site_id: this.browseState.siteId,
					post_type: this.browseState.postType,
					search: this.browseState.searchQuery,
					status: this.browseState.currentFilter,
				page: this.browseState.currentPage,
				per_page: 20,
			},
			success: (response) => {
				console.log('Browse posts response:', response);
				if (response.success && response.data && response.data.posts) {
					this.renderPostsTree(response.data.posts);
					this.updatePagination(response.data);
					this.updateFilterCounts(response.data.status_counts);
				} else {
					console.error('Invalid response:', response);
					const errorMsg = (response.data && response.data.message) ? response.data.message : 'Failed to load posts';
					this.showBrowseError(errorMsg);
				}
			},
			error: (xhr) => {
				console.error('AJAX error:', xhr);
				let errorMessage = 'An error occurred while loading posts';
				if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					errorMessage = xhr.responseJSON.data.message;
				}
				this.showBrowseError(errorMessage);
			},
		});
	},		/**
		 * Render posts tree
		 */
		renderPostsTree(posts) {
			$('#aie-browse-loading').hide();

			if (!posts || posts.length === 0) {
				$('#aie-browse-posts-tree').html(`
					<div class="aie-loading-posts">
						<span class="dashicons dashicons-admin-post" style="font-size: 48px; opacity: 0.3; width: auto; height: auto;"></span>
						<p>No posts found</p>
					</div>
				`).show();
				return;
			}

			const $tree = $('#aie-browse-posts-tree');
			$tree.empty();

			posts.forEach(post => {
				const $item = this.createPostItem(post);
				$tree.append($item);
			});

			$tree.show();
			this.updateBrowseSelection();
		},

		/**
		 * Create post item element
		 */
		createPostItem(post) {
			const hasChildren = post.children_count > 0;
			const isSelected = this.browseState.selectedPosts.has(post.ID);
			const isExpanded = this.browseState.expandedPosts.has(post.ID);

			const date = new Date(post.post_modified);
			const formattedDate = date.toLocaleDateString();

			const $item = $(`
				<div class="aie-post-item ${isSelected ? 'selected' : ''} ${hasChildren ? 'has-children' : ''}" data-post-id="${post.ID}">
					${hasChildren ? `<button type="button" class="aie-post-toggle ${isExpanded ? 'expanded' : ''}">
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>` : '<span style="width: 28px; display: inline-block;"></span>'}
					<input type="checkbox" class="aie-post-checkbox" value="${post.ID}" ${isSelected ? 'checked' : ''} />
					<span class="aie-post-icon">
						<span class="dashicons dashicons-admin-post"></span>
					</span>
					<div class="aie-post-info">
						<div class="aie-post-title">${this.escapeHtml(post.post_title || '(No title)')}</div>
						<div class="aie-post-meta">
							<span class="aie-post-status ${post.post_status}">${post.post_status}</span>
							<span class="aie-post-date">${formattedDate}</span>
							${hasChildren ? `<span class="aie-post-children-count">${post.children_count} ${post.children_count === 1 ? 'child' : 'children'}</span>` : ''}
						</div>
					</div>
				</div>
			`);

			// Add children container if has children
			if (hasChildren) {
				const $children = $('<div class="aie-post-children" style="display: none;"></div>');
				$item.after($children);
			}

			return $item;
		},

		/**
		 * Update pagination controls
		 */
		updatePagination(data) {
			if (!data.pages || data.pages <= 1) {
				$('#aie-browse-pagination').hide();
				return;
			}

			this.browseState.currentPage = data.current_page;
			this.browseState.totalPages = data.pages;

			$('#aie-browse-current-page').text(data.current_page);
			$('#aie-browse-total-pages').text(data.pages);

			$('#aie-browse-prev-page').prop('disabled', data.current_page <= 1);
			$('#aie-browse-next-page').prop('disabled', data.current_page >= data.pages);

			$('#aie-browse-pagination').show();
		},

		/**
		 * Update filter counts
		 */
		updateFilterCounts(counts) {
			if (!counts) return;

			$('.aie-filter-item[data-status=""]').find('.aie-filter-count').text(counts.all || 0);
			$('.aie-filter-item[data-status="publish"]').find('.aie-filter-count').text(counts.publish || 0);
			$('.aie-filter-item[data-status="draft"]').find('.aie-filter-count').text(counts.draft || 0);
			$('.aie-filter-item[data-status="pending"]').find('.aie-filter-count').text(counts.pending || 0);
		},

		/**
		 * Load children posts
		 */
		loadChildrenPosts(parentId, $childrenContainer) {
			if (typeof aiePostSyncData === 'undefined') {
				$childrenContainer.html('<div style="padding: 10px; color: #d63638;">Plugin data not loaded</div>');
				return;
			}

			const ajaxUrl = aiePostSyncData.ajaxurl; // lowercase 'ajaxurl' to match PHP localization
			const nonce = aiePostSyncData.nonce;

			// Show loading
			$childrenContainer.html('<div style="padding: 10px; text-align: center;"><span class="spinner is-active"></span></div>').show();

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'aie_content_sync_get_children_posts',
					nonce: nonce,
					site_id: this.browseState.siteId,
					parent_id: parentId,
				},
				success: (response) => {
					if (response.success && response.data.children) {
						$childrenContainer.empty();
						response.data.children.forEach(child => {
							const $childItem = this.createPostItem(child);
							$childrenContainer.append($childItem);
						});
					} else {
						$childrenContainer.html('<div style="padding: 10px; color: #d63638;">Failed to load children</div>');
					}
				},
				error: () => {
					$childrenContainer.html('<div style="padding: 10px; color: #d63638;">Error loading children</div>');
				},
			});
		},

		/**
		 * Show browse error
		 */
		showBrowseError(message) {
			$('#aie-browse-loading').hide();
			$('#aie-browse-posts-tree').html(`
				<div class="aie-loading-posts">
					<span class="dashicons dashicons-warning" style="font-size: 48px; opacity: 0.3; width: auto; height: auto;"></span>
					<p>${this.escapeHtml(message)}</p>
				</div>
			`).show();
		},

		/**
		 * Update browse selection count and button state
		 */
		updateBrowseSelection() {
			const count = this.browseState.selectedPosts.size;

			// Update count display
			$('#aie-browse-selected-count').text(count);

			// Enable/disable pull button
			$('#aie-browse-pull-btn').prop('disabled', count === 0);
		},

		/**
		 * Pull selected posts from remote site
		 */
		pullSelectedPosts() {
			if (this.browseState.selectedPosts.size === 0) {
				alert('Please select at least one post');
				return;
			}

			const remoteIds = Array.from(this.browseState.selectedPosts);
			const siteId = this.browseState.siteId;

			// Create post mapping - all selected posts will be created as new
			const postMapping = {};
			remoteIds.forEach(remoteId => {
				postMapping[remoteId] = 'new';
			});

			// Close browse modal
			this.closeBrowseModal();

			// Show main sync modal with progress
			$('#aie-sync-modal').fadeIn(200);
			
			// Hide initial content and show only progress
			$('.aie-sync-info, .aie-form-group, .aie-sync-direction, .aie-browse-section, .aie-no-selection-message').css('display', 'none');
			$('#aie-sync-progress').show();
			$('.aie-progress-fill').css('width', '0%');
			$('.aie-progress-text').text('Pulling posts...');
			$('#aie-sync-result').hide();

			// Disable buttons during sync
			$('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', true);

			// Animate progress
			setTimeout(() => {
				$('.aie-progress-fill').css('width', '50%');
			}, 100);

			// Perform pull with mapping
			this.performSync('pull', siteId, remoteIds, postMapping);
		},

		/**
		 * Escape HTML to prevent XSS
		 */
		escapeHtml(text) {
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		},

		/**
		 * Show sync result
		 */
		showResult(type, message) {
			// Reset syncing flag
			this.isSyncing = false;

			const $result = $('#aie-sync-result');
			
			// Make sure initial sections stay hidden when showing result
			$('.aie-sync-info, .aie-form-group, .aie-sync-direction, .aie-browse-section, .aie-no-selection-message').css('display', 'none');

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
