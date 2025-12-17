/******/ (() => { // webpackBootstrap
/*!******************************************!*\
  !*** ./src/js/modules/gutenberg-sync.js ***!
  \******************************************/
/**
 * Gutenberg Sync Button
 * 
 * Adds sync button to Gutenberg editor sidebar
 */

(function ($) {
  'use strict';

  var GutenbergSync = {
    /**
     * Initialize
     */
    init: function init() {
      var _this = this;
      console.log('AIE: Loading Gutenberg sync button script');
      console.log('AIE: wp object exists:', typeof wp !== 'undefined');
      console.log('AIE: wp.data exists:', typeof wp !== 'undefined' && typeof wp.data !== 'undefined');
      $(document).ready(function () {
        console.log('AIE: Document ready');
        _this.addGutenbergSyncButton();
      });
    },
    /**
     * Add button to Gutenberg editor
     */
    addGutenbergSyncButton: function addGutenbergSyncButton() {
      console.log('AIE: addGutenbergSyncButton called');

      // Check if we're in Gutenberg
      if (typeof wp === 'undefined' || typeof wp.data === 'undefined') {
        console.log('AIE: WordPress editor not detected');
        return false;
      }
      console.log('AIE: WordPress editor detected');

      // Try multiple selectors for the sidebar (different WordPress versions)
      var $sidebar = $('.interface-interface-skeleton__sidebar .edit-post-sidebar');
      if (!$sidebar.length) {
        $sidebar = $('.edit-post-sidebar');
      }
      if (!$sidebar.length) {
        $sidebar = $('.editor-sidebar');
      }
      if (!$sidebar.length) {
        $sidebar = $('.interface-complementary-area');
      }
      console.log('AIE: Sidebar elements found:', $sidebar.length);
      console.log('AIE: Sidebar HTML:', $sidebar.length ? $sidebar[0].className : 'none');
      if ($sidebar.length && !$('#aie-sync-content-btn').length) {
        var _window$aieData, _window$aieData$i18n, _window$aieData2, _window$aieData2$i18n;
        console.log('AIE: Sidebar found, creating sync panel');

        // Create panel container (like Yoast SEO) - opened by default
        var $panel = $('<div>').addClass('components-panel__body aie-gutenberg-sync-panel is-opened').attr('id', 'aie-gutenberg-sync-panel');

        // Create panel header with arrow (same as Yoast SEO)
        var $header = $('<h2>').addClass('components-panel__body-title').html('<button type="button" class="components-button components-panel__body-toggle" aria-expanded="true"><span aria-hidden="true"><svg class="components-panel__arrow" width="24" height="24" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"></path></svg></span>' + (((_window$aieData = window.aieData) === null || _window$aieData === void 0 ? void 0 : (_window$aieData$i18n = _window$aieData.i18n) === null || _window$aieData$i18n === void 0 ? void 0 : _window$aieData$i18n.syncContent) || 'Sync Content') + '</button></h2>');

        // Create panel content - visible by default
        var $content = $('<div>').css({
          'padding': '16px'
        });

        // Create sync button
        var $button = $('<button>').attr('type', 'button').attr('id', 'aie-sync-content-btn').addClass('button button-secondary').css('width', '100%').html('<span class="dashicons dashicons-update" style="margin-top: 3px;"></span> ' + (((_window$aieData2 = window.aieData) === null || _window$aieData2 === void 0 ? void 0 : (_window$aieData2$i18n = _window$aieData2.i18n) === null || _window$aieData2$i18n === void 0 ? void 0 : _window$aieData2$i18n.syncThisPost) || 'Sync This Post'));
        $content.append($button);
        $panel.append($header, $content);

        // Insert after the first panel or at the beginning
        var $firstPanel = $sidebar.find('.components-panel__body').first();
        if ($firstPanel.length) {
          $firstPanel.after($panel);
        } else {
          $sidebar.prepend($panel);
        }

        // Add toggle functionality
        $header.find('button').on('click', function () {
          var $btn = $(this);
          var $panel = $btn.closest('.components-panel__body');
          var isExpanded = $btn.attr('aria-expanded') === 'true';
          $btn.attr('aria-expanded', !isExpanded);
          $panel.toggleClass('is-opened');
          $content.slideToggle(200);
        });
        console.log('AIE: Sync button added successfully');
        return true;
      }
      console.log('AIE: Sidebar not found or button already exists');
      return false;
    },
    /**
     * Retry adding button with increasing intervals
     */
    retryAddButton: function retryAddButton() {
      var _this2 = this;
      var attempts = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 0;
      var maxAttempts = 10;
      var baseDelay = 500;
      if (attempts >= maxAttempts) {
        console.log('AIE: Max retry attempts reached');
        return;
      }
      var success = this.addGutenbergSyncButton();
      if (!success) {
        var delay = baseDelay * Math.pow(1.5, attempts);
        console.log("AIE: Retry attempt ".concat(attempts + 1, "/").concat(maxAttempts, " after ").concat(delay, "ms"));
        setTimeout(function () {
          return _this2.retryAddButton(attempts + 1);
        }, delay);
      }
    }
  };

  // Initialize
  GutenbergSync.init();

  // Try adding button with retries (for when Gutenberg loads slowly)
  setTimeout(function () {
    return GutenbergSync.retryAddButton();
  }, 1000);
})(jQuery);
/******/ })()
;
//# sourceMappingURL=gutenberg-sync.js.map