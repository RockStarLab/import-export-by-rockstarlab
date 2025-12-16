/******/ (() => { // webpackBootstrap
/*!****************************************!*\
  !*** ./src/js/post-sync-standalone.js ***!
  \****************************************/
/**
 * Post Sync Standalone Module
 * 
 * Handles content synchronization from post list screens
 * This is a standalone version that doesn't require the main app.js
 */

(function ($) {
  'use strict';

  var PostSync = {
    // Flag to track if sync is in progress
    isSyncing: false,
    /**
     * Initialize the module
     */
    init: function init() {
      console.log('AIE PostSync Standalone: Module initialized');
      this.bindEvents();
    },
    /**
     * Bind event handlers
     */
    bindEvents: function bindEvents() {
      var _this = this;
      console.log('AIE PostSync Standalone: Binding events');

      // Open modal when sync button is clicked
      $(document).on('click', '#aie-sync-content-btn', function (e) {
        console.log('AIE PostSync Standalone: Sync button clicked');
        e.preventDefault();
        e.stopPropagation();
        _this.openSyncModal();
      });

      // Close modal
      $(document).on('click', '.aie-modal-close', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $modal = $(e.currentTarget).closest('.aie-modal');
        var modalId = $modal.attr('id');
        if (modalId === 'aie-browse-modal') {
          _this.closeBrowseModal();
        } else if (modalId === 'aie-mapping-modal') {
          _this.closeMappingModal();
        } else if (modalId === 'aie-sync-modal') {
          _this.closeSyncModal();
        }
      });
      $(document).on('click', '.aie-modal', function (e) {
        if (e.target === e.currentTarget) {
          var modalId = $(e.target).attr('id');
          if (modalId === 'aie-browse-modal') {
            _this.closeBrowseModal();
          } else if (modalId === 'aie-mapping-modal') {
            _this.closeMappingModal();
          } else {
            _this.closeSyncModal();
          }
        }
      });

      // Close modal on backdrop click
      $(document).on('click', '.aie-modal-backdrop', function (e) {
        var $modal = $(e.target).closest('.aie-modal');
        if ($modal.attr('id') === 'aie-browse-modal') {
          _this.closeBrowseModal();
        }
      });

      // Enable/disable sync buttons based on site selection
      $(document).on('change', '#aie-sync-site-select', function () {
        _this.updateSyncButtons();
      });

      // Handle Push button
      $(document).on('click', '#aie-sync-push-btn', function (e) {
        e.preventDefault();
        _this.syncContent('push');
      });

      // Handle Pull button
      $(document).on('click', '#aie-sync-pull-btn', function (e) {
        e.preventDefault();
        _this.syncContent('pull');
      });

      // Handle Browse Remote button
      $(document).on('click', '#aie-browse-remote-btn, #aie-browse-remote-btn-alt', function (e) {
        e.preventDefault();
        _this.openBrowseModal();
      });

      // Browse modal - Search (debounced)
      var searchTimeout;
      $(document).on('input', '#aie-browse-search', function (e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function () {
          _this.browseState.searchQuery = $(e.target).val();
          _this.browseState.currentPage = 1;
          _this.loadRemotePosts();
        }, 500);
      });

      // Browse modal - Status filter
      $(document).on('click', '.aie-filter-item', function (e) {
        var $item = $(e.currentTarget);
        $('.aie-filter-item').removeClass('active');
        $item.addClass('active');
        _this.browseState.currentFilter = $item.data('status');
        _this.browseState.currentPage = 1;
        _this.loadRemotePosts();
      });

      // Browse modal - Post toggle (expand/collapse)
      $(document).on('click', '.aie-post-toggle', function (e) {
        e.stopPropagation();
        var $toggle = $(e.currentTarget);
        var $item = $toggle.closest('.aie-post-item');
        var postId = parseInt($item.data('post-id'));
        var $children = $item.next('.aie-post-children');
        if ($toggle.hasClass('expanded')) {
          // Collapse
          $toggle.removeClass('expanded');
          $children.slideUp(200);
          _this.browseState.expandedPosts["delete"](postId);
        } else {
          // Expand
          $toggle.addClass('expanded');

          // Load children if not loaded yet
          if ($children.children().length === 0) {
            _this.loadChildrenPosts(postId, $children);
          } else {
            $children.slideDown(200);
          }
          _this.browseState.expandedPosts.add(postId);
        }
      });

      // Browse modal - Post checkbox
      $(document).on('change', '.aie-post-checkbox', function (e) {
        var $checkbox = $(e.currentTarget);
        var postId = parseInt($checkbox.val());
        var $item = $checkbox.closest('.aie-post-item');
        if ($checkbox.prop('checked')) {
          _this.browseState.selectedPosts.add(postId);
          $item.addClass('selected');
        } else {
          _this.browseState.selectedPosts["delete"](postId);
          $item.removeClass('selected');
        }
        _this.updateBrowseSelection();
      });

      // Browse modal - Pagination
      $(document).on('click', '#aie-browse-prev-page', function () {
        if (_this.browseState.currentPage > 1) {
          _this.browseState.currentPage--;
          _this.loadRemotePosts();
        }
      });
      $(document).on('click', '#aie-browse-next-page', function () {
        if (_this.browseState.currentPage < _this.browseState.totalPages) {
          _this.browseState.currentPage++;
          _this.loadRemotePosts();
        }
      });

      // Browse modal - Cancel button
      $(document).on('click', '#aie-browse-cancel-btn', function (e) {
        e.preventDefault();
        _this.closeBrowseModal();
      });

      // Browse modal - Pull button
      $(document).on('click', '#aie-browse-pull-btn', function (e) {
        e.preventDefault();
        _this.pullSelectedPosts();
      });

      // Close modal on Escape key
      $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#aie-sync-modal').is(':visible')) {
          _this.closeSyncModal();
        }
        if (e.key === 'Escape' && $('#aie-browse-modal').is(':visible')) {
          _this.closeBrowseModal();
        }
      });
    },
    /**
     * Open sync modal
     */
    openSyncModal: function openSyncModal() {
      console.log('AIE PostSync Standalone: Opening modal');
      var selectedIds = this.getSelectedPostIds();
      console.log('AIE PostSync Standalone: Selected IDs:', selectedIds);
      console.log('AIE PostSync Standalone: Modal element exists:', $('#aie-sync-modal').length);
      if (selectedIds.length === 0) {
        console.log('AIE PostSync Standalone: No posts selected');
        var isEditPage = $('#post_ID').length > 0;
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
      var countText = selectedIds.length === 1 ? '1 post' : "".concat(selectedIds.length, " posts");
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
    closeSyncModal: function closeSyncModal() {
      // Reset syncing flag
      this.isSyncing = false;
      $('#aie-sync-modal').fadeOut(200, function () {
        // Reset modal state - show initial sections again
        $('.aie-sync-info, .aie-form-group, .aie-sync-direction, .aie-browse-section').css('display', '');
        $('#aie-sync-progress, #aie-sync-result, .aie-no-selection-message').css('display', 'none');
        $('.aie-progress-fill').css('width', '0%');
      });
    },
    /**
     * Get selected post IDs
     */
    getSelectedPostIds: function getSelectedPostIds() {
      var ids = [];

      // Check if we're on post edit page
      var postIdInput = $('#post_ID');
      if (postIdInput.length && postIdInput.val()) {
        // Single post edit page
        ids.push(postIdInput.val());
      } else {
        // Post list page - get checked items
        $('tbody .check-column input[type="checkbox"]:checked').each(function () {
          var id = $(this).val();
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
    updateSyncButtons: function updateSyncButtons() {
      var _this2 = this;
      // Don't update UI if sync is in progress
      if (this.isSyncing) {
        return;
      }
      var siteSelected = $('#aie-sync-site-select').val() !== '';
      var selectedIds = this.getSelectedPostIds();

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
        this.closeSyncModal();
        // Small delay to allow modal close animation
        setTimeout(function () {
          _this2.openBrowseModal();
        }, 250);
      }
    },
    /**
     * Sync content (push or pull)
     */
    syncContent: function syncContent(direction) {
      var siteId = $('#aie-sync-site-select').val();
      var postIds = this.getSelectedPostIds();
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
    openMappingModal: function openMappingModal(direction, siteId, postIds) {
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
    closeMappingModal: function closeMappingModal() {
      $('#aie-mapping-modal').fadeOut(200);
    },
    /**
     * Load mapping data
     */
    loadMappingData: function loadMappingData(direction, siteId, postIds) {
      var _this3 = this;
      var nonce = typeof aiePostSyncData !== 'undefined' && aiePostSyncData.nonce ? aiePostSyncData.nonce : '';
      var ajaxUrl = typeof aiePostSyncData !== 'undefined' && aiePostSyncData.ajaxurl ? aiePostSyncData.ajaxurl : ajaxurl;

      // Get remote posts list
      $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: {
          action: 'aie_content_sync_get_remote_posts',
          nonce: nonce,
          site_id: siteId,
          post_type: 'any'
        },
        success: function success(response) {
          if (response.success && response.data.posts) {
            _this3.remotePosts = response.data.posts;
            _this3.renderMappingTable(postIds, response.data.posts);
            $('#aie-mapping-loading').hide();
            $('#aie-mapping-table-container').fadeIn(200);
            $('#aie-mapping-confirm-btn').prop('disabled', false);
          } else {
            var _response$data;
            alert('Failed to load remote posts: ' + (((_response$data = response.data) === null || _response$data === void 0 ? void 0 : _response$data.message) || 'Unknown error'));
            _this3.closeMappingModal();
          }
        },
        error: function error(xhr) {
          alert('Failed to connect to remote site');
          _this3.closeMappingModal();
        }
      });
    },
    /**
     * Render mapping table
     */
    renderMappingTable: function renderMappingTable(localPostIds, remotePosts) {
      var _this4 = this;
      var $tbody = $('#aie-mapping-tbody');
      $tbody.empty();
      localPostIds.forEach(function (postId) {
        var row = _this4.createMappingRow(postId, remotePosts);
        $tbody.append(row);
      });

      // Bind events
      this.bindMappingEvents();
    },
    /**
     * Create mapping table row
     */
    createMappingRow: function createMappingRow(postId, remotePosts) {
      var _$$attr$match;
      // Get local post info
      var postTitle = $("#post-".concat(postId, " .row-title")).text() || $('.editor-post-title__input').val() || 'Post #' + postId;
      var postType = ((_$$attr$match = $('body').attr('class').match(/post-type-(\S+)/)) === null || _$$attr$match === void 0 ? void 0 : _$$attr$match[1]) || 'post';
      var $row = $('<tr>').attr('data-local-id', postId);

      // Local post column
      var $localCol = $('<td>').addClass('aie-local-post').html("\n\t\t\t\t<div class=\"aie-local-post-info\">\n\t\t\t\t\t<h4>".concat(postTitle, "</h4>\n\t\t\t\t\t<div class=\"aie-post-meta\">\n\t\t\t\t\t\t<span class=\"aie-post-type\">").concat(postType, "</span>\n\t\t\t\t\t\t<span class=\"aie-post-id\">ID: ").concat(postId, "</span>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t"));

      // Arrow column
      var $arrowCol = $('<td>').addClass('aie-sync-arrow').html('→');

      // Remote action column
      var $remoteCol = $('<td>').addClass('aie-remote-post');
      var $select = $('<select>').addClass('aie-remote-select').attr('data-local-id', postId);

      // Add "Create New" option
      $select.append("<option value=\"new\" class=\"aie-option-new\">\u2795 Create New Post</option>");
      $select.append("<option value=\"\" disabled>\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500</option>");

      // Add remote posts options
      remotePosts.forEach(function (post) {
        $select.append("<option value=\"".concat(post.id, "\" class=\"aie-option-update\">\uD83D\uDD04 Update: ").concat(post.title, " (ID: ").concat(post.id, ")</option>"));
      });
      var $wrapper = $('<div>').addClass('aie-remote-select-wrapper aie-action-new');
      $wrapper.append($select);
      $remoteCol.append($wrapper);
      $row.append($localCol, $arrowCol, $remoteCol);
      return $row;
    },
    /**
     * Bind mapping events
     */
    bindMappingEvents: function bindMappingEvents() {
      var _this5 = this;
      // Close mapping modal
      $(document).off('click', '.aie-modal-close').on('click', '.aie-modal-close', function (e) {
        _this5.closeMappingModal();
      });

      // Cancel button
      $(document).off('click', '#aie-mapping-cancel-btn').on('click', '#aie-mapping-cancel-btn', function (e) {
        e.preventDefault();
        _this5.closeMappingModal();
        // Reopen site selection modal
        _this5.openSyncModal();
      });

      // Confirm button
      $(document).off('click', '#aie-mapping-confirm-btn').on('click', '#aie-mapping-confirm-btn', function (e) {
        e.preventDefault();
        _this5.confirmMapping();
      });

      // Auto-match button
      $(document).off('click', '#aie-auto-match-btn').on('click', '#aie-auto-match-btn', function (e) {
        e.preventDefault();
        _this5.autoMatchByTitle();
      });

      // Create all new button
      $(document).off('click', '#aie-create-all-new-btn').on('click', '#aie-create-all-new-btn', function (e) {
        e.preventDefault();
        $('.aie-remote-select').val('new').trigger('change');
      });

      // Select change
      $(document).off('change', '.aie-remote-select').on('change', '.aie-remote-select', function () {
        var value = $(this).val();
        var $wrapper = $(this).closest('.aie-remote-select-wrapper');
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
    autoMatchByTitle: function autoMatchByTitle() {
      $('.aie-remote-select').each(function (i, select) {
        var $select = $(select);
        var localId = $select.data('local-id');
        var localTitle = $("[data-local-id=\"".concat(localId, "\"] .aie-local-post-info h4")).text().trim().toLowerCase();

        // Find matching remote post by title
        var matchFound = false;
        $select.find('option').each(function () {
          var optionText = $(this).text().toLowerCase();
          var optionValue = $(this).val();
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
    confirmMapping: function confirmMapping() {
      // Collect mapping
      var postMapping = {};
      $('.aie-remote-select').each(function () {
        var localId = $(this).data('local-id');
        var remoteId = $(this).val();
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
    performSync: function performSync(direction, siteId, postIds, postMapping) {
      var _this6 = this;
      // Set syncing flag
      this.isSyncing = true;

      // Open sync modal to show progress
      $('#aie-sync-modal').fadeIn(200);

      // Hide initial content and show progress
      $('.aie-sync-info, .aie-form-group, .aie-sync-direction, .aie-browse-section, .aie-no-selection-message').css('display', 'none');
      $('#aie-sync-progress').show();
      $('#aie-sync-result').hide();
      $('.aie-progress-fill').css('width', '0%');
      $('.aie-progress-text').text("Starting ".concat(direction, "..."));

      // Disable buttons
      $('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', true);

      // Make AJAX request
      var nonce = typeof aiePostSyncData !== 'undefined' && aiePostSyncData.nonce ? aiePostSyncData.nonce : '';
      var ajaxUrl = typeof aiePostSyncData !== 'undefined' && aiePostSyncData.ajaxurl ? aiePostSyncData.ajaxurl : ajaxurl;
      $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: {
          action: "aie_content_sync_".concat(direction),
          nonce: nonce,
          site_id: siteId,
          post_ids: postIds,
          post_mapping: JSON.stringify(postMapping)
        },
        success: function success(response) {
          if (response.success) {
            $('.aie-progress-fill').css('width', '100%');
            $('.aie-progress-text').text('Completed!');
            setTimeout(function () {
              $('#aie-sync-progress').hide();
              _this6.showResult('success', response.data.message || 'Sync completed successfully');
            }, 500);
          } else {
            $('#aie-sync-progress').hide();
            _this6.showResult('error', response.data.message || 'Sync failed');
          }
        },
        error: function error(xhr) {
          $('#aie-sync-progress').hide();
          var errorMessage = 'An error occurred during sync';
          if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            errorMessage = xhr.responseJSON.data.message;
          }
          _this6.showResult('error', errorMessage);
        },
        complete: function complete() {
          // Re-enable buttons
          $('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', false);
          _this6.updateSyncButtons();
        }
      });
    },
    /**
     * Open browse remote posts modal
     */
    openBrowseModal: function openBrowseModal() {
      var siteId = $('#aie-sync-site-select').val();
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
        expandedPosts: new Set()
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
     */
    closeBrowseModal: function closeBrowseModal() {
      $('#aie-browse-modal').fadeOut(200, function () {
        // Return back to Choose Site modal
        $('#aie-sync-modal').fadeIn(200);
      });
    },
    /**
     * Get current post type from screen
     */
    getCurrentPostType: function getCurrentPostType() {
      // Try to get from post edit screen
      if ($('#post_type').length) {
        return $('#post_type').val();
      }

      // Try to get from post list screen
      if (window.typenow) {
        return window.typenow;
      }

      // Try to get from URL
      var urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('post_type')) {
        return urlParams.get('post_type');
      }

      // Default to post
      return 'post';
    },
    /**
     * Load remote posts with pagination and filters
     */
    loadRemotePosts: function loadRemotePosts() {
      var _this7 = this;
      if (typeof aiePostSyncData === 'undefined') {
        this.showBrowseError('Plugin data not loaded. Please refresh the page.');
        return;
      }
      var ajaxUrl = aiePostSyncData.ajaxurl; // lowercase 'ajaxurl' to match PHP localization
      var nonce = aiePostSyncData.nonce;
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
          per_page: 20
        },
        success: function success(response) {
          console.log('Browse posts response:', response);
          if (response.success && response.data && response.data.posts) {
            _this7.renderPostsTree(response.data.posts);
            _this7.updatePagination(response.data);
            _this7.updateFilterCounts(response.data.status_counts);
          } else {
            console.error('Invalid response:', response);
            var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to load posts';
            _this7.showBrowseError(errorMsg);
          }
        },
        error: function error(xhr) {
          console.error('AJAX error:', xhr);
          var errorMessage = 'An error occurred while loading posts';
          if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            errorMessage = xhr.responseJSON.data.message;
          }
          _this7.showBrowseError(errorMessage);
        }
      });
    },
    /**
    * Render posts tree
    */
    renderPostsTree: function renderPostsTree(posts) {
      var _this8 = this;
      $('#aie-browse-loading').hide();
      if (!posts || posts.length === 0) {
        $('#aie-browse-posts-tree').html("\n\t\t\t\t\t<div class=\"aie-loading-posts\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-admin-post\" style=\"font-size: 48px; opacity: 0.3; width: auto; height: auto;\"></span>\n\t\t\t\t\t\t<p>No posts found</p>\n\t\t\t\t\t</div>\n\t\t\t\t").show();
        return;
      }
      var $tree = $('#aie-browse-posts-tree');
      $tree.empty();
      posts.forEach(function (post) {
        var $item = _this8.createPostItem(post);
        $tree.append($item);
      });
      $tree.show();
      this.updateBrowseSelection();
    },
    /**
     * Create post item element
     */
    createPostItem: function createPostItem(post) {
      var hasChildren = post.children_count > 0;
      var isSelected = this.browseState.selectedPosts.has(post.ID);
      var isExpanded = this.browseState.expandedPosts.has(post.ID);
      var date = new Date(post.post_modified);
      var formattedDate = date.toLocaleDateString();
      var $item = $("\n\t\t\t\t<div class=\"aie-post-item ".concat(isSelected ? 'selected' : '', " ").concat(hasChildren ? 'has-children' : '', "\" data-post-id=\"").concat(post.ID, "\">\n\t\t\t\t\t").concat(hasChildren ? "<button type=\"button\" class=\"aie-post-toggle ".concat(isExpanded ? 'expanded' : '', "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-arrow-right-alt2\"></span>\n\t\t\t\t\t</button>") : '<span style="width: 28px; display: inline-block;"></span>', "\n\t\t\t\t\t<input type=\"checkbox\" class=\"aie-post-checkbox\" value=\"").concat(post.ID, "\" ").concat(isSelected ? 'checked' : '', " />\n\t\t\t\t\t<span class=\"aie-post-icon\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-admin-post\"></span>\n\t\t\t\t\t</span>\n\t\t\t\t\t<div class=\"aie-post-info\">\n\t\t\t\t\t\t<div class=\"aie-post-title\">").concat(this.escapeHtml(post.post_title || '(No title)'), "</div>\n\t\t\t\t\t\t<div class=\"aie-post-meta\">\n\t\t\t\t\t\t\t<span class=\"aie-post-status ").concat(post.post_status, "\">").concat(post.post_status, "</span>\n\t\t\t\t\t\t\t<span class=\"aie-post-date\">").concat(formattedDate, "</span>\n\t\t\t\t\t\t\t").concat(hasChildren ? "<span class=\"aie-post-children-count\">".concat(post.children_count, " ").concat(post.children_count === 1 ? 'child' : 'children', "</span>") : '', "\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t"));

      // Add children container if has children
      if (hasChildren) {
        var $children = $('<div class="aie-post-children" style="display: none;"></div>');
        $item.after($children);
      }
      return $item;
    },
    /**
     * Update pagination controls
     */
    updatePagination: function updatePagination(data) {
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
    updateFilterCounts: function updateFilterCounts(counts) {
      if (!counts) return;
      $('.aie-filter-item[data-status=""]').find('.aie-filter-count').text(counts.all || 0);
      $('.aie-filter-item[data-status="publish"]').find('.aie-filter-count').text(counts.publish || 0);
      $('.aie-filter-item[data-status="draft"]').find('.aie-filter-count').text(counts.draft || 0);
      $('.aie-filter-item[data-status="pending"]').find('.aie-filter-count').text(counts.pending || 0);
    },
    /**
     * Load children posts
     */
    loadChildrenPosts: function loadChildrenPosts(parentId, $childrenContainer) {
      var _this9 = this;
      if (typeof aiePostSyncData === 'undefined') {
        $childrenContainer.html('<div style="padding: 10px; color: #d63638;">Plugin data not loaded</div>');
        return;
      }
      var ajaxUrl = aiePostSyncData.ajaxurl; // lowercase 'ajaxurl' to match PHP localization
      var nonce = aiePostSyncData.nonce;

      // Show loading
      $childrenContainer.html('<div style="padding: 10px; text-align: center;"><span class="spinner is-active"></span></div>').show();
      $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: {
          action: 'aie_content_sync_get_children_posts',
          nonce: nonce,
          site_id: this.browseState.siteId,
          parent_id: parentId
        },
        success: function success(response) {
          if (response.success && response.data.children) {
            $childrenContainer.empty();
            response.data.children.forEach(function (child) {
              var $childItem = _this9.createPostItem(child);
              $childrenContainer.append($childItem);
            });
          } else {
            $childrenContainer.html('<div style="padding: 10px; color: #d63638;">Failed to load children</div>');
          }
        },
        error: function error() {
          $childrenContainer.html('<div style="padding: 10px; color: #d63638;">Error loading children</div>');
        }
      });
    },
    /**
     * Show browse error
     */
    showBrowseError: function showBrowseError(message) {
      $('#aie-browse-loading').hide();
      $('#aie-browse-posts-tree').html("\n\t\t\t\t<div class=\"aie-loading-posts\">\n\t\t\t\t\t<span class=\"dashicons dashicons-warning\" style=\"font-size: 48px; opacity: 0.3; width: auto; height: auto;\"></span>\n\t\t\t\t\t<p>".concat(this.escapeHtml(message), "</p>\n\t\t\t\t</div>\n\t\t\t")).show();
    },
    /**
     * Update browse selection count and button state
     */
    updateBrowseSelection: function updateBrowseSelection() {
      var count = this.browseState.selectedPosts.size;

      // Update count display
      $('#aie-browse-selected-count').text(count);

      // Enable/disable pull button
      $('#aie-browse-pull-btn').prop('disabled', count === 0);
    },
    /**
     * Pull selected posts from remote site
     */
    pullSelectedPosts: function pullSelectedPosts() {
      if (this.browseState.selectedPosts.size === 0) {
        alert('Please select at least one post');
        return;
      }
      var remoteIds = Array.from(this.browseState.selectedPosts);
      var siteId = this.browseState.siteId;

      // Create post mapping - all selected posts will be created as new
      var postMapping = {};
      remoteIds.forEach(function (remoteId) {
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
      setTimeout(function () {
        $('.aie-progress-fill').css('width', '50%');
      }, 100);

      // Perform pull with mapping
      this.performSync('pull', siteId, remoteIds, postMapping);
    },
    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml: function escapeHtml(text) {
      var div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    },
    /**
     * Show sync result
     */
    showResult: function showResult(type, message) {
      var _this10 = this;
      // Reset syncing flag
      this.isSyncing = false;
      var $result = $('#aie-sync-result');

      // Make sure initial sections stay hidden when showing result
      $('.aie-sync-info, .aie-form-group, .aie-sync-direction, .aie-browse-section, .aie-no-selection-message').css('display', 'none');
      $result.removeClass('notice-success notice-error').addClass("notice notice-".concat(type)).html("<p>".concat(message, "</p>")).fadeIn(200);

      // Auto-hide success messages
      if (type === 'success') {
        setTimeout(function () {
          $result.fadeOut(200);
          _this10.closeSyncModal();
          // Reload page to show updated content
          location.reload();
        }, 2000);
      }
    }
  };

  // Initialize on document ready
  $(document).ready(function () {
    PostSync.init();
  });

  // Make it globally accessible
  window.aiePostSync = PostSync;
})(jQuery);
/******/ })()
;
//# sourceMappingURL=post-sync-standalone.js.map