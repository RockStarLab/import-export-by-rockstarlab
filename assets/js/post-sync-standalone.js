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
        _this.closeSyncModal();
      });
      $(document).on('click', '.aie-modal', function (e) {
        if (e.target === e.currentTarget) {
          _this.closeSyncModal();
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

      // Close modal on Escape key
      $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#aie-sync-modal').is(':visible')) {
          _this.closeSyncModal();
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
        alert(isEditPage ? 'Please save the post first' : 'Please select at least one post');
        return;
      }

      // Update selected count with proper text and hide for single post
      var countText = selectedIds.length === 1 ? '1 post' : "".concat(selectedIds.length, " posts");
      $('#aie-selected-count').text(countText);

      // Hide "Selected posts" section for single post
      if (selectedIds.length === 1) {
        $('.aie-sync-info').hide();
      } else {
        $('.aie-sync-info').show();
      }
      console.log('AIE PostSync Standalone: Updated selected count'); // Reset form
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
      $('#aie-sync-modal').fadeOut(200);
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
      var siteSelected = $('#aie-sync-site-select').val() !== '';
      $('#aie-sync-push-btn, #aie-sync-pull-btn').prop('disabled', !siteSelected);
    },
    /**
     * Sync content (push or pull)
     */
    syncContent: function syncContent(direction) {
      var _this2 = this;
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

      // Confirm action
      var siteFullText = $('#aie-sync-site-select option:selected').text();
      // Extract only site name (before opening parenthesis)
      var siteName = siteFullText.split('(')[0].trim();
      var action = direction === 'push' ? 'push to' : 'pull from';
      var postsText = postIds.length === 1 ? '1 post' : "".concat(postIds.length, " posts");
      var message = "Are you sure you want to ".concat(action, " ").concat(siteName, "? This will affect ").concat(postsText, ".");
      if (!confirm(message)) {
        return;
      } // Show progress
      $('#aie-sync-progress').show();
      $('#aie-sync-result').hide();
      $('.aie-progress-fill').css('width', '0%');
      $('.aie-progress-text').text("Starting ".concat(direction, "..."));

      // Disable buttons
      $('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', true);

      // Make AJAX request
      var nonce = typeof aiePostSyncData !== 'undefined' && aiePostSyncData.nonce ? aiePostSyncData.nonce : '';
      var ajaxUrl = typeof aiePostSyncData !== 'undefined' && aiePostSyncData.ajaxurl ? aiePostSyncData.ajaxurl : ajaxurl;
      console.log('AIE PostSync Standalone: Using nonce:', nonce);
      console.log('AIE PostSync Standalone: Using ajaxUrl:', ajaxUrl);
      $.ajax({
        url: ajaxUrl,
        type: 'POST',
        data: {
          action: "aie_content_sync_".concat(direction),
          nonce: nonce,
          site_id: siteId,
          post_ids: postIds
        },
        success: function success(response) {
          if (response.success) {
            $('.aie-progress-fill').css('width', '100%');
            $('.aie-progress-text').text('Completed!');
            setTimeout(function () {
              $('#aie-sync-progress').hide();
              _this2.showResult('success', response.data.message || 'Sync completed successfully');
            }, 500);
          } else {
            $('#aie-sync-progress').hide();
            _this2.showResult('error', response.data.message || 'Sync failed');
          }
        },
        error: function error(xhr) {
          $('#aie-sync-progress').hide();
          var errorMessage = 'An error occurred during sync';
          if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
            errorMessage = xhr.responseJSON.data.message;
          }
          _this2.showResult('error', errorMessage);
        },
        complete: function complete() {
          // Re-enable buttons
          $('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', false);
          _this2.updateSyncButtons();
        }
      });
    },
    /**
     * Show sync result
     */
    showResult: function showResult(type, message) {
      var _this3 = this;
      var $result = $('#aie-sync-result');
      $result.removeClass('notice-success notice-error').addClass("notice notice-".concat(type)).html("<p>".concat(message, "</p>")).fadeIn(200);

      // Auto-hide success messages
      if (type === 'success') {
        setTimeout(function () {
          $result.fadeOut(200);
          _this3.closeSyncModal();
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