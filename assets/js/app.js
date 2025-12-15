/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@babel/runtime/regenerator/index.js":
/*!**********************************************************!*\
  !*** ./node_modules/@babel/runtime/regenerator/index.js ***!
  \**********************************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

module.exports = __webpack_require__(/*! regenerator-runtime */ "./node_modules/regenerator-runtime/runtime.js");


/***/ }),

/***/ "./src/js/app.js":
/*!***********************!*\
  !*** ./src/js/app.js ***!
  \***********************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _modules_functions__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./modules/functions */ "./src/js/modules/functions.js");
/* harmony import */ var _modules_import__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./modules/import */ "./src/js/modules/import.js");
/* harmony import */ var _modules_export__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./modules/export */ "./src/js/modules/export.js");
/* harmony import */ var _modules_media_sync__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./modules/media_sync */ "./src/js/modules/media_sync.js");
/* harmony import */ var _modules_jobs_log__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./modules/jobs-log */ "./src/js/modules/jobs-log.js");
/* harmony import */ var _modules_content_updater__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./modules/content-updater */ "./src/js/modules/content-updater.js");
/* harmony import */ var _modules_content_sync__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./modules/content-sync */ "./src/js/modules/content-sync.js");
/* harmony import */ var _modules_post_sync__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./modules/post-sync */ "./src/js/modules/post-sync.js");









// Initialize modules when DOM is ready
jQuery(document).ready(function ($) {
  // Initialize import module
  _modules_import__WEBPACK_IMPORTED_MODULE_1__["default"].init();

  // Initialize export module
  _modules_export__WEBPACK_IMPORTED_MODULE_2__["default"].init();

  // Make export module globally accessible for step 3
  window.aieExportModule = _modules_export__WEBPACK_IMPORTED_MODULE_2__["default"];

  // Initialize functions module
  _modules_functions__WEBPACK_IMPORTED_MODULE_0__["default"].init();

  // Initialize media sync module
  _modules_media_sync__WEBPACK_IMPORTED_MODULE_3__["default"].init();

  // Initialize jobs log module
  _modules_jobs_log__WEBPACK_IMPORTED_MODULE_4__["default"].init();

  // Initialize content updater module
  _modules_content_updater__WEBPACK_IMPORTED_MODULE_5__["default"].init();

  // Initialize content sync module
  _modules_content_sync__WEBPACK_IMPORTED_MODULE_6__["default"].init();

  // Initialize post sync module
  console.log('AIE: About to initialize PostSyncModule');
  _modules_post_sync__WEBPACK_IMPORTED_MODULE_7__["default"].init();
  console.log('AIE: PostSyncModule initialized');

  // Make it globally accessible for debugging
  window.aiePostSyncModule = _modules_post_sync__WEBPACK_IMPORTED_MODULE_7__["default"];
});

/***/ }),

/***/ "./src/js/modules/FileUploader.js":
/*!****************************************!*\
  !*** ./src/js/modules/FileUploader.js ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ FileUploader)
/* harmony export */ });
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/regenerator */ "./node_modules/@babel/runtime/regenerator/index.js");
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__);

function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { Promise.resolve(value).then(_next, _throw); } }
function _asyncToGenerator(fn) { return function () { var self = this, args = arguments; return new Promise(function (resolve, reject) { var gen = fn.apply(self, args); function _next(value) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value); } function _throw(err) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err); } _next(undefined); }); }; }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
/**
 * FileUploader - Handles chunked file uploads to bypass PHP upload limits
 * 
 * @package WP_AIE\JS
 */
var FileUploader = /*#__PURE__*/function () {
  /**
   * Constructor
   * 
   * @param {Object} options Upload options
   */
  function FileUploader() {
    var options = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : {};
    _classCallCheck(this, FileUploader);
    this.chunkSize = options.chunkSize || 1024 * 1024; // 1MB chunks by default
    this.file = null;
    this.uploadId = null;
    this.currentChunk = 0;
    this.totalChunks = 0;
    this.uploadedBytes = 0;
    this.startTime = null;
    this.aborted = false;
    this.additionalData = options.additionalData || {}; // Additional data to send with finalize

    // Callbacks
    this.onProgress = options.onProgress || function () {};
    this.onComplete = options.onComplete || function () {};
    this.onError = options.onError || function () {};
    this.onChunkComplete = options.onChunkComplete || function () {};
  }

  /**
   * Start uploading a file
   * 
   * @param {File} file File object to upload
   * @returns {Promise}
   */
  _createClass(FileUploader, [{
    key: "upload",
    value: (function () {
      var _upload = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee(file) {
        var chunk, result;
        return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee$(_context) {
          while (1) {
            switch (_context.prev = _context.next) {
              case 0:
                this.file = file;
                this.uploadId = this.generateUploadId();
                this.currentChunk = 0;
                this.totalChunks = Math.ceil(file.size / this.chunkSize);
                this.uploadedBytes = 0;
                this.startTime = Date.now();
                this.aborted = false;
                _context.prev = 7;
                chunk = 0;
              case 9:
                if (!(chunk < this.totalChunks)) {
                  _context.next = 18;
                  break;
                }
                if (!this.aborted) {
                  _context.next = 12;
                  break;
                }
                throw new Error('Upload aborted');
              case 12:
                this.currentChunk = chunk;
                _context.next = 15;
                return this.uploadChunk(chunk);
              case 15:
                chunk++;
                _context.next = 9;
                break;
              case 18:
                _context.next = 20;
                return this.finalizeUpload();
              case 20:
                result = _context.sent;
                this.onComplete(result);
                return _context.abrupt("return", result);
              case 25:
                _context.prev = 25;
                _context.t0 = _context["catch"](7);
                this.onError(_context.t0);
                throw _context.t0;
              case 29:
              case "end":
                return _context.stop();
            }
          }
        }, _callee, this, [[7, 25]]);
      }));
      function upload(_x) {
        return _upload.apply(this, arguments);
      }
      return upload;
    }()
    /**
     * Upload a single chunk
     * 
     * @param {number} chunkIndex Chunk index
     * @returns {Promise}
     */
    )
  }, {
    key: "uploadChunk",
    value: (function () {
      var _uploadChunk = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee2(chunkIndex) {
        var start, end, chunk, formData, response, data, progress, elapsed, speed;
        return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee2$(_context2) {
          while (1) {
            switch (_context2.prev = _context2.next) {
              case 0:
                start = chunkIndex * this.chunkSize;
                end = Math.min(start + this.chunkSize, this.file.size);
                chunk = this.file.slice(start, end);
                formData = new FormData();
                formData.append('action', 'aie_upload_chunk');
                formData.append('nonce', aieData.nonce);
                formData.append('upload_id', this.uploadId);
                formData.append('chunk_index', chunkIndex);
                formData.append('total_chunks', this.totalChunks);
                formData.append('file_name', this.file.name);
                formData.append('file_size', this.file.size);
                formData.append('chunk', chunk);
                _context2.next = 14;
                return fetch(aieData.ajaxUrl, {
                  method: 'POST',
                  body: formData,
                  credentials: 'same-origin'
                });
              case 14:
                response = _context2.sent;
                if (response.ok) {
                  _context2.next = 17;
                  break;
                }
                throw new Error("HTTP error! status: ".concat(response.status));
              case 17:
                _context2.next = 19;
                return response.json();
              case 19:
                data = _context2.sent;
                if (data.success) {
                  _context2.next = 22;
                  break;
                }
                throw new Error(data.data || 'Failed to upload chunk');
              case 22:
                // Update progress
                this.uploadedBytes = end;
                progress = this.uploadedBytes / this.file.size * 100;
                elapsed = (Date.now() - this.startTime) / 1000; // seconds
                speed = this.uploadedBytes / elapsed; // bytes per second
                this.onProgress({
                  progress: progress,
                  uploadedBytes: this.uploadedBytes,
                  totalBytes: this.file.size,
                  currentChunk: chunkIndex + 1,
                  totalChunks: this.totalChunks,
                  speed: speed,
                  elapsed: elapsed
                });
                this.onChunkComplete(chunkIndex, this.totalChunks);
                return _context2.abrupt("return", data);
              case 29:
              case "end":
                return _context2.stop();
            }
          }
        }, _callee2, this);
      }));
      function uploadChunk(_x2) {
        return _uploadChunk.apply(this, arguments);
      }
      return uploadChunk;
    }()
    /**
     * Finalize upload - tell server to merge chunks
     * 
     * @returns {Promise}
     */
    )
  }, {
    key: "finalizeUpload",
    value: (function () {
      var _finalizeUpload = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee3() {
        var formData, key, response, data;
        return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee3$(_context3) {
          while (1) {
            switch (_context3.prev = _context3.next) {
              case 0:
                formData = new FormData();
                formData.append('action', 'aie_finalize_upload');
                formData.append('nonce', aieData.nonce);
                formData.append('upload_id', this.uploadId);
                formData.append('file_name', this.file.name);
                formData.append('file_size', this.file.size);
                formData.append('total_chunks', this.totalChunks);

                // Append additional data (CSV options, etc.)
                for (key in this.additionalData) {
                  if (this.additionalData.hasOwnProperty(key)) {
                    formData.append(key, this.additionalData[key]);
                  }
                }
                _context3.next = 10;
                return fetch(aieData.ajaxUrl, {
                  method: 'POST',
                  body: formData,
                  credentials: 'same-origin'
                });
              case 10:
                response = _context3.sent;
                if (response.ok) {
                  _context3.next = 13;
                  break;
                }
                throw new Error("HTTP error! status: ".concat(response.status));
              case 13:
                _context3.next = 15;
                return response.json();
              case 15:
                data = _context3.sent;
                if (data.success) {
                  _context3.next = 18;
                  break;
                }
                throw new Error(data.data || 'Failed to finalize upload');
              case 18:
                return _context3.abrupt("return", data.data);
              case 19:
              case "end":
                return _context3.stop();
            }
          }
        }, _callee3, this);
      }));
      function finalizeUpload() {
        return _finalizeUpload.apply(this, arguments);
      }
      return finalizeUpload;
    }()
    /**
     * Abort current upload
     */
    )
  }, {
    key: "abort",
    value: function abort() {
      this.aborted = true;

      // Clean up on server
      if (this.uploadId) {
        var formData = new FormData();
        formData.append('action', 'aie_abort_upload');
        formData.append('nonce', aieData.nonce);
        formData.append('upload_id', this.uploadId);
        fetch(aieData.ajaxUrl, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        })["catch"](function () {
          // Ignore errors on abort
        });
      }
    }

    /**
     * Generate unique upload ID
     * 
     * @returns {string}
     */
  }, {
    key: "generateUploadId",
    value: function generateUploadId() {
      return 'upload_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Format bytes to human readable string
     * 
     * @param {number} bytes Bytes
     * @param {number} decimals Decimal places
     * @returns {string}
     */
  }], [{
    key: "formatBytes",
    value: function formatBytes(bytes) {
      var decimals = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 2;
      if (bytes === 0) return '0 Bytes';
      var k = 1024;
      var dm = decimals < 0 ? 0 : decimals;
      var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
      var i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    /**
     * Format speed to human readable string
     * 
     * @param {number} bytesPerSecond Bytes per second
     * @returns {string}
     */
  }, {
    key: "formatSpeed",
    value: function formatSpeed(bytesPerSecond) {
      return FileUploader.formatBytes(bytesPerSecond) + '/s';
    }

    /**
     * Format time duration
     * 
     * @param {number} seconds Seconds
     * @returns {string}
     */
  }, {
    key: "formatTime",
    value: function formatTime(seconds) {
      if (seconds < 60) {
        return Math.round(seconds) + 's';
      }
      var minutes = Math.floor(seconds / 60);
      var secs = Math.round(seconds % 60);
      return minutes + 'm ' + secs + 's';
    }
  }]);
  return FileUploader;
}();


/***/ }),

/***/ "./src/js/modules/content-sync.js":
/*!****************************************!*\
  !*** ./src/js/modules/content-sync.js ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/**
 * Content Sync Module
 *
 * Handles content synchronization between sites
 */

var ContentSyncModule = {
  /**
   * Initialize module
   */
  init: function init() {
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
  bindEvents: function bindEvents() {
    var _this = this;
    var $ = jQuery;

    // Add site button
    $('#aie-add-site-btn').on('click', function () {
      return _this.showAddSiteModal();
    });

    // Save site button
    $('#aie-save-site-btn').on('click', function () {
      return _this.saveSite();
    });

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
    $('#aie-toggle-my-site').on('click', function () {
      return _this.toggleMySiteInfo();
    });

    // Copy my API key
    $('#aie-copy-my-key').on('click', function () {
      return _this.copyMyApiKey();
    });

    // Regenerate my API key
    $('#aie-regenerate-my-key').on('click', function () {
      return _this.regenerateMyApiKey();
    });

    // Delegated events for dynamic content
    $(document).on('click', '.aie-edit-site', function (e) {
      var siteId = $(e.currentTarget).data('site-id');
      _this.showEditSiteModal(siteId);
    });
    $(document).on('click', '.aie-delete-site', function (e) {
      var siteId = $(e.currentTarget).data('site-id');
      _this.deleteSite(siteId);
    });
    $(document).on('click', '.aie-test-connection', function (e) {
      var siteId = $(e.currentTarget).data('site-id');
      _this.testConnection(siteId);
    });
    $(document).on('click', '.aie-regenerate-key', function (e) {
      var siteId = $(e.currentTarget).data('site-id');
      _this.regenerateKey(siteId);
    });
  },
  /**
   * Load all connected sites
   */
  loadSites: function loadSites() {
    var _this2 = this;
    jQuery.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action: 'aie_content_sync_get_sites',
        nonce: aieContentSync.nonce
      },
      success: function success(response) {
        if (response.success) {
          _this2.renderSites(response.data.sites);
          _this2.updateStats(response.data.stats);
        } else {
          _this2.showNotice('error', response.data.message || window.aieData.i18n.failedLoadSites);
        }
      },
      error: function error() {
        _this2.showNotice('error', window.aieData.i18n.failedLoadSites);
      }
    });
  },
  /**
   * Load this site's information
   */
  loadMySiteInfo: function loadMySiteInfo() {
    jQuery.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action: 'aie_content_sync_get_my_key',
        nonce: aieContentSync.nonce
      },
      success: function success(response) {
        if (response.success) {
          jQuery('#aie-my-site-name').val(response.data.site_name);
          jQuery('#aie-my-site-url').val(response.data.site_url);
          jQuery('#aie-my-site-key').val(response.data.site_key);
        }
      }
    });
  },
  /**
   * Render sites table
   */
  renderSites: function renderSites(sites) {
    var _this3 = this;
    var $ = jQuery;
    var $tbody = $('#aie-sites-list');
    $tbody.empty();
    if (!sites || sites.length === 0) {
      $tbody.html("\n\t\t\t\t<tr class=\"aie-no-sites\">\n\t\t\t\t\t<td colspan=\"5\" style=\"text-align: center; padding: 40px;\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-admin-site\" style=\"font-size: 48px; opacity: 0.3;\"></span>\n\t\t\t\t\t\t<p style=\"margin-top: 40px\">No connected sites yet. Add your first connection!</p>\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t");
      return;
    }
    sites.forEach(function (site) {
      var statusClass = "aie-status-".concat(site.status);
      var lastSync = site.last_sync_at ? new Date(site.last_sync_at).toLocaleString() : 'Never';
      var row = "\n\t\t\t\t<tr data-site-id=\"".concat(site.id, "\">\n\t\t\t\t\t<td class=\"column-name\">\n\t\t\t\t\t\t<strong>").concat(_this3.escapeHtml(site.name), "</strong>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"column-url\">\n\t\t\t\t\t\t<a href=\"").concat(_this3.escapeHtml(site.remote_url), "\" target=\"_blank\" rel=\"noopener noreferrer\">\n\t\t\t\t\t\t\t").concat(_this3.escapeHtml(site.remote_url), "\n\t\t\t\t\t\t</a>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"column-status\">\n\t\t\t\t\t\t<span class=\"aie-status-badge ").concat(statusClass, "\">\n\t\t\t\t\t\t\t").concat(_this3.escapeHtml(site.status), "\n\t\t\t\t\t\t</span>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"column-last-sync\">\n\t\t\t\t\t\t").concat(lastSync, "\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"column-actions\">\n\t\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-test-connection\" data-site-id=\"").concat(site.id, "\" title=\"Test Connection\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-update\"></span>\n\t\t\t\t\t\t</button>\n\t\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-edit-site\" data-site-id=\"").concat(site.id, "\" title=\"Edit\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-edit\"></span>\n\t\t\t\t\t\t</button>\n\t\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-delete-site\" data-site-id=\"").concat(site.id, "\" title=\"Delete\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-trash\"></span>\n\t\t\t\t\t\t</button>\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t");
      $tbody.append(row);
    });
  },
  /**
   * Update statistics
   */
  updateStats: function updateStats(stats) {
    jQuery('#aie-stat-total').text(stats.total || 0);
    jQuery('#aie-stat-active').text(stats.active || 0);
    jQuery('#aie-stat-error').text(stats.error || 0);
  },
  /**
   * Show add site modal
   */
  showAddSiteModal: function showAddSiteModal() {
    var $ = jQuery;
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
  showEditSiteModal: function showEditSiteModal(siteId) {
    var $ = jQuery;
    var $row = $("tr[data-site-id=\"".concat(siteId, "\"]"));
    var site = this.getSiteFromRow($row);
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
  getSiteFromRow: function getSiteFromRow($row) {
    if (!$row.length) return null;
    return {
      name: $row.find('.column-name strong').text(),
      url: $row.find('.column-url a').attr('href')
    };
  },
  /**
   * Save site (add or update)
   */
  saveSite: function saveSite() {
    var _this4 = this;
    var $ = jQuery;
    var $form = $('#aie-site-form');
    var siteId = $('#aie-site-id').val();
    var isEdit = !!siteId;

    // Basic validation
    if (!$form[0].checkValidity()) {
      $form[0].reportValidity();
      return;
    }

    // Hide any previous notifications
    this.hideModalNotice();
    var data = {
      action: isEdit ? 'aie_content_sync_update_site' : 'aie_content_sync_add_site',
      nonce: aieContentSync.nonce,
      name: $('#aie-site-name').val(),
      remote_url: $('#aie-site-url').val(),
      direction: 'bidirectional' // Always bidirectional
    };

    // Get API key value
    var apiKey = $('#aie-site-api-key').val();
    if (isEdit) {
      data.site_id = siteId;
      // If API key was provided during edit, include it (to update/validate)
      if (apiKey && apiKey.trim() !== '') {
        data.api_key = apiKey;
      }
    } else {
      data.api_key = apiKey;
    }
    var $saveBtn = $('#aie-save-site-btn');
    // Check if we're validating API key
    var hasApiKey = data.api_key && data.api_key.trim() !== '';
    var buttonText = isEdit && !hasApiKey ? 'Updating...' : 'Validating & Saving...';
    $saveBtn.prop('disabled', true).text(buttonText);

    // Show info message when validating API key
    if (hasApiKey) {
      this.showModalNotice('info', 'Validating API key...', 'Please wait while we verify the connection to the remote site.');
    }
    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: data,
      success: function success(response) {
        if (response.success) {
          var message = response.data.message || 'Operation completed successfully';

          // Check if no changes were made
          if (message.includes('No changes')) {
            _this4.showModalNotice('info', 'No Changes', message);
            // Close modal after delay
            setTimeout(function () {
              $('#aie-site-modal').hide();
            }, 2000);
          } else {
            _this4.showModalNotice('success', 'Success!', message);
            // Close modal after short delay
            setTimeout(function () {
              $('#aie-site-modal').hide();
              _this4.loadSites();
              _this4.showNotice('success', message);
            }, 1500);
          }
        } else {
          _this4.showModalNotice('error', 'Validation Failed', response.data.message || 'Failed to save site connection');
        }
      },
      error: function error(xhr) {
        var errorTitle = 'Connection Error';
        var errorMessage = 'An unexpected error occurred while trying to save the site connection.';
        var errorDetails = [];

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
          errorMessage = "The server returned an error (".concat(xhr.status, "). Please try again later.");
        } else if (xhr.status === 404) {
          errorTitle = 'Not Found';
          errorMessage = 'The requested endpoint was not found. Please check if the plugin is properly installed.';
        }
        _this4.showModalNotice('error', errorTitle, errorMessage, errorDetails);
      },
      complete: function complete() {
        $saveBtn.prop('disabled', false).text('Save Connection');
      }
    });
  },
  /**
   * Delete site
   */
  deleteSite: function deleteSite(siteId) {
    var _this5 = this;
    if (!confirm(window.aieData.i18n.confirmDeleteSiteConnection)) {
      return;
    }
    jQuery.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action: 'aie_content_sync_delete_site',
        nonce: aieContentSync.nonce,
        site_id: siteId
      },
      success: function success(response) {
        if (response.success) {
          _this5.showNotice('success', response.data.message);
          _this5.loadSites();
        } else {
          _this5.showNotice('error', response.data.message || window.aieData.i18n.failedDeleteSite);
        }
      },
      error: function error() {
        _this5.showNotice('error', window.aieData.i18n.failedDeleteSite);
      }
    });
  },
  /**
   * Test connection to remote site
   */
  testConnection: function testConnection(siteId) {
    var _this6 = this;
    var $ = jQuery;
    var $btn = $(".aie-test-connection[data-site-id=\"".concat(siteId, "\"]"));
    $btn.prop('disabled', true);
    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action: 'aie_content_sync_test_connection',
        nonce: aieContentSync.nonce,
        site_id: siteId
      },
      success: function success(response) {
        if (response.success) {
          _this6.showNotice('success', response.data.message);
        } else {
          _this6.showNotice('error', response.data.message || window.aieData.i18n.connectionTestFailed);
        }
        // Always reload sites to update stats
        _this6.loadSites();
      },
      error: function error() {
        _this6.showNotice('error', window.aieData.i18n.connectionTestFailed);
        _this6.loadSites();
      },
      complete: function complete() {
        $btn.prop('disabled', false);
      }
    });
  },
  /**
   * Regenerate API key for a site
   */
  regenerateKey: function regenerateKey(siteId) {
    var _this7 = this;
    if (!confirm(window.aieData.i18n.confirmRegenerateSiteKey)) {
      return;
    }
    jQuery.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action: 'aie_content_sync_regenerate_key',
        nonce: aieContentSync.nonce,
        site_id: siteId
      },
      success: function success(response) {
        if (response.success) {
          _this7.showNotice('success', response.data.message);
          alert(window.aieData.i18n.newApiKey + response.data.api_key);
        } else {
          _this7.showNotice('error', response.data.message || window.aieData.i18n.failedRegenerateKey);
        }
      },
      error: function error() {
        _this7.showNotice('error', window.aieData.i18n.failedRegenerateKey);
      }
    });
  },
  /**
   * Toggle my site info visibility
   */
  toggleMySiteInfo: function toggleMySiteInfo() {
    var $ = jQuery;
    var $info = $('.aie-my-site-info');
    var $btn = $('#aie-toggle-my-site');
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
  copyMyApiKey: function copyMyApiKey() {
    var $ = jQuery;
    var $input = $('#aie-my-site-key');
    $input.select();
    document.execCommand('copy');
    var $btn = $('#aie-copy-my-key');
    var originalText = $btn.html();
    $btn.html('<span class="dashicons dashicons-yes"></span> Copied!');
    setTimeout(function () {
      $btn.html(originalText);
    }, 2000);
    this.showNotice('success', window.aieData.i18n.apiKeyCopied);
  },
  /**
   * Regenerate this site's API key
   */
  regenerateMyApiKey: function regenerateMyApiKey() {
    var _this8 = this;
    var $ = jQuery;

    // Confirm action
    if (!confirm(window.aieData.i18n.confirmRegenerateMyKey)) {
      return;
    }
    var $btn = $('#aie-regenerate-my-key');
    var originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Regenerating...');
    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action: 'aie_content_sync_regenerate_my_key',
        nonce: aieContentSync.nonce
      },
      success: function success(response) {
        if (response.success) {
          // Update the API key field with new key
          $('#aie-my-site-key').val(response.data.site_key);

          // Show success message
          _this8.showNotice('success', response.data.message);

          // Briefly show success state on button
          $btn.html('<span class="dashicons dashicons-yes"></span> Regenerated!');
          setTimeout(function () {
            $btn.html(originalText);
          }, 3000);
        } else {
          _this8.showNotice('error', response.data.message || window.aieData.i18n.failedRegenerateApiKey);
          $btn.html(originalText);
        }
      },
      error: function error() {
        _this8.showNotice('error', window.aieData.i18n.failedRegenerateApiKey);
        $btn.html(originalText);
      },
      complete: function complete() {
        $btn.prop('disabled', false);
      }
    });
  },
  /**
   * Show notice message in modal
   */
  showModalNotice: function showModalNotice(type, title, message) {
    var _this9 = this;
    var details = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : [];
    var $ = jQuery;
    var icons = {
      error: 'warning',
      success: 'yes-alt',
      warning: 'info',
      info: 'info-outline'
    };
    var icon = icons[type] || 'info';
    var noticeClass = "notice-".concat(type);
    var detailsHtml = '';
    if (details.length > 0) {
      detailsHtml = '<ul>';
      details.forEach(function (detail) {
        detailsHtml += "<li>".concat(_this9.escapeHtml(detail), "</li>");
      });
      detailsHtml += '</ul>';
    }
    var $notification = $("\n\t\t\t<div class=\"aie-modal-notification ".concat(noticeClass, "\">\n\t\t\t\t<span class=\"dashicons dashicons-").concat(icon, "\"></span>\n\t\t\t\t<div class=\"aie-notification-content\">\n\t\t\t\t\t<strong>").concat(this.escapeHtml(title), "</strong>\n\t\t\t\t\t<p>").concat(this.escapeHtml(message), "</p>\n\t\t\t\t\t").concat(detailsHtml, "\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t"));
    $('#aie-modal-notification').html($notification).show();
  },
  /**
   * Hide modal notice
   */
  hideModalNotice: function hideModalNotice() {
    jQuery('#aie-modal-notification').hide().empty();
  },
  /**
   * Show notice message
   */
  showNotice: function showNotice(type, message) {
    var $ = jQuery;
    var noticeClass = type === 'error' ? 'notice-error' : type === 'success' ? 'notice-success' : 'notice-info';
    var $notice = $("\n\t\t\t<div class=\"notice ".concat(noticeClass, " is-dismissible\">\n\t\t\t\t<p>").concat(this.escapeHtml(message), "</p>\n\t\t\t</div>\n\t\t"));
    $('#wp-aie-content-sync h1').after($notice);

    // Auto-dismiss after 5 seconds
    setTimeout(function () {
      $notice.fadeOut(function () {
        return $notice.remove();
      });
    }, 5000);

    // Make dismissible
    $notice.on('click', '.notice-dismiss', function () {
      var _this10 = this;
      $(this).closest('.notice').fadeOut(function () {
        return $(_this10).remove();
      });
    });
  },
  /**
   * Escape HTML to prevent XSS
   */
  escapeHtml: function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ContentSyncModule);

/***/ }),

/***/ "./src/js/modules/content-updater.js":
/*!*******************************************!*\
  !*** ./src/js/modules/content-updater.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utils */ "./src/js/modules/utils.js");
/**
 * Content Updater Module
 *
 * Handles the content updater wizard functionality
 */


var ContentUpdater = {
  currentStep: 1,
  totalSteps: 5,
  // Updated from 4 to 5 steps
  jobId: null,
  progressInterval: null,
  selectedFields: [],
  fieldFunctions: {},
  availableFunctions: [],
  selectedFilters: [],
  // New: Store selected filters
  filteredCount: null,
  // New: Store filtered item count
  /**
   * Initialize module
   */
  init: function init() {
    if (!jQuery('#wp-aie-content-updater').length) {
      return;
    }
    this.bindEvents();
    this.showStep(1);
    this.loadAvailableFunctions();
  },
  /**
   * Bind event handlers
   */
  bindEvents: function bindEvents() {
    var _this = this;
    var $wizard = jQuery('#wp-aie-content-updater');

    // Content type search
    $wizard.on('input', '#aie-updater-content-type-search', function (e) {
      return _this.filterContentTypes(e);
    });

    // Step navigation
    $wizard.on('click', '.aie-updater-next-step', function () {
      return _this.nextStep();
    });
    $wizard.on('click', '.aie-updater-prev-step', function () {
      return _this.prevStep();
    });

    // Content type selection
    $wizard.on('change', 'input[name="updater_content_type"]', function (e) {
      return _this.onContentTypeChange(e);
    });

    // Filter events (Step 2)
    $wizard.on('click', '.aie-updater-add-filter', function () {
      return _this.addFilterRow();
    });
    $wizard.on('click', '.aie-updater-remove-filter', function (e) {
      return _this.removeFilterRow(e);
    });
    $wizard.on('change', '.aie-updater-filter-field', function (e) {
      return _this.onFilterFieldChange(e);
    });
    $wizard.on('change', '.aie-updater-filter-condition', function (e) {
      return _this.onFilterConditionChange(e);
    });
    $wizard.on('change', '.aie-updater-filter-value', function () {
      return _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
        return _this.refreshCount(false);
      }, 500)();
    });
    $wizard.on('click', '.aie-updater-refresh-count', function () {
      return _this.refreshCount(true);
    });

    // Field selection (Step 3)
    $wizard.on('click', '.aie-updater-clear-all-fields', function () {
      return _this.clearAllFields();
    });
    $wizard.on('input', '#aie-updater-fields-search', function (e) {
      return _this.filterFields(e);
    });

    // Function assignment (Step 3) - old handlers kept for compatibility
    $wizard.on('change', '.aie-field-function-select', function (e) {
      return _this.onFunctionChange(e);
    });
    $wizard.on('click', '.aie-apply-function-to-all', function () {
      return _this.applyFunctionToAll();
    });
    $wizard.on('click', '.aie-clear-all-functions', function () {
      return _this.clearAllFunctions();
    });
    $wizard.on('click', '.aie-test-function', function (e) {
      return _this.testFunction(e);
    });

    // Start update (Step 4)
    $wizard.on('click', '.aie-start-update-btn', function () {
      return _this.startUpdate();
    });
    $wizard.on('click', '.aie-cancel-update-btn', function () {
      return _this.cancelUpdate();
    });
    $wizard.on('click', '.aie-start-new-update', function () {
      return _this.startNewUpdate();
    });

    // Setup drag and drop for field selection
    this.setupDragAndDrop();

    // Modal events
    this.initFieldFunctionsModal();
  },
  /**
   * Initialize field functions modal
   */
  initFieldFunctionsModal: function initFieldFunctionsModal() {
    var _this2 = this;
    var $modal = jQuery('#aie-updater-functions-modal');
    if (!$modal.length) return;

    // Close modal
    $modal.find('.aie-modal-close').on('click', function () {
      _this2.closeFieldFunctionsModal();
    });
    $modal.find('.aie-modal-cancel').on('click', function () {
      _this2.closeFieldFunctionsModal();
    });

    // Save functions
    $modal.find('.aie-save-updater-functions').on('click', function () {
      _this2.saveFieldFunctions();
    });

    // Test pipeline
    $modal.find('.aie-test-updater-pipeline').on('click', function () {
      _this2.testFunctionPipeline();
    });

    // Functions search
    $modal.find('#aie-updater-functions-search').on('input', function (e) {
      _this2.filterFunctions(e.target.value);
    });

    // Functions filter
    $modal.find('input[name="updater-functions-filter"]').on('change', function (e) {
      _this2.filterFunctionsByCategory(e.target.value);
    });

    // Create new function button
    $modal.find('.aie-create-new-function').on('click', function (e) {
      e.preventDefault();
      _this2.createNewFunction();
    });
  },
  /**
   * Show specific step
   */
  showStep: function showStep(step) {
    var $wizard = jQuery('#wp-aie-content-updater');

    // Hide all steps
    $wizard.find('.aie-step').removeClass('active');

    // Show target step
    $wizard.find(".aie-updater-step-".concat(step)).addClass('active');

    // Update indicator
    $wizard.find('.aie-step-indicator').removeClass('active completed');
    $wizard.find(".aie-step-indicator[data-step=\"".concat(step, "\"]")).addClass('active');

    // Mark previous steps as completed
    for (var i = 1; i < step; i++) {
      $wizard.find(".aie-step-indicator[data-step=\"".concat(i, "\"]")).addClass('completed');
    }
    this.currentStep = step;

    // Step-specific actions
    switch (step) {
      case 2:
        this.loadFiltersLibrary(); // New: Load filters
        break;
      case 3:
        this.loadFieldsLibrary();
        break;
      case 4:
        this.buildFunctionsTable();
        break;
      case 5:
        this.prepareUpdateSummary();
        break;
    }
  },
  /**
   * Navigate to next step
   */
  nextStep: function nextStep() {
    if (!this.validateCurrentStep()) {
      return;
    }

    // Save filters when leaving step 2
    if (this.currentStep === 2) {
      this.selectedFilters = this.collectFilters();
      console.log('Saved filters:', this.selectedFilters);
    }
    if (this.currentStep < this.totalSteps) {
      this.showStep(this.currentStep + 1);
    }
  },
  /**
   * Navigate to previous step
   */
  prevStep: function prevStep() {
    if (this.currentStep > 1) {
      this.showStep(this.currentStep - 1);
    }
  },
  /**
   * Validate current step before moving to next
   */
  validateCurrentStep: function validateCurrentStep() {
    switch (this.currentStep) {
      case 1:
        // Content type must be selected
        if (!jQuery('input[name="updater_content_type"]:checked').length) {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Please select a content type', 'error');
          return false;
        }
        return true;
      case 2:
        // Filters are optional, always pass
        return true;
      case 3:
        // At least one field must be selected
        if (this.selectedFields.length === 0) {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Please select at least one field to update', 'error');
          return false;
        }
        return true;
      case 4:
        // At least one function must be assigned
        var hasFunction = Object.values(this.fieldFunctions).some(function (functions) {
          return Array.isArray(functions) && functions.length > 0;
        });
        if (!hasFunction) {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Please assign at least one function to a field', 'error');
          return false;
        }
        return true;
      default:
        return true;
    }
  },
  /**
   * Filter content types by search
   */
  filterContentTypes: function filterContentTypes(e) {
    var searchTerm = jQuery(e.target).val().toLowerCase();
    var $contentTypes = jQuery('.aie-content-type');
    var visibleCount = 0;
    $contentTypes.each(function () {
      var $card = jQuery(this);
      var text = $card.find('h3').text().toLowerCase() + $card.find('p').text().toLowerCase();
      if (text.includes(searchTerm)) {
        $card.show();
        visibleCount++;
      } else {
        $card.hide();
      }
    });

    // Show/hide no results message
    if (visibleCount === 0) {
      jQuery('.aie-no-results').show();
    } else {
      jQuery('.aie-no-results').hide();
    }
  },
  /**
   * Handle content type change
   */
  onContentTypeChange: function onContentTypeChange(e) {
    var contentType = jQuery(e.target).val();
    console.log('Content type changed:', contentType);

    // Reset selections for new content type
    this.selectedFields = [];
    this.fieldFunctions = {};
    this.selectedFilters = [];
    this.filteredCount = null; // Reset filtered count when content type changes
  },
  /**
   * Load filters library for selected content type
   */
  loadFiltersLibrary: function loadFiltersLibrary() {
    console.log('loadFiltersLibrary called');

    // Get selected content type
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();
    if (!contentType) {
      console.error('No content type selected');
      return;
    }
    console.log('Loading filters for content type:', contentType);

    // Clear existing filters
    jQuery('#aie-updater-filters-list').empty();

    // Refresh count
    this.refreshCount(true);
  },
  /**
   * Add new filter row
   */
  addFilterRow: function addFilterRow() {
    var _this3 = this;
    var template = document.getElementById('aie-updater-filter-row-template');
    var clone = template.content.cloneNode(true);
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();

    // Populate field options based on content type
    var $fieldSelect = jQuery(clone).find('.aie-updater-filter-field');

    // Use export module's getFieldsByContentType if available
    if (typeof window.aieExportModule !== 'undefined' && window.aieExportModule.getFieldsByContentType) {
      var fields = window.aieExportModule.getFieldsByContentType(contentType);
      fields.forEach(function (group) {
        var $optgroup = jQuery('<optgroup>').attr('label', group.label);
        group.options.forEach(function (option) {
          $optgroup.append(jQuery('<option>').val(option.value).text(option.label).data('type', option.type));
        });
        $fieldSelect.append($optgroup);
      });
    }
    jQuery('#aie-updater-filters-list').append(clone);

    // Trigger count refresh (without spinner)
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
      return _this3.refreshCount(false);
    }, 500)();
  },
  /**
   * Remove filter row
   */
  removeFilterRow: function removeFilterRow(e) {
    var _this4 = this;
    jQuery(e.target).closest('.aie-filter-row').remove();
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
      return _this4.refreshCount(false);
    }, 500)();
  },
  /**
   * Handle filter field change
   */
  onFilterFieldChange: function onFilterFieldChange(e) {
    var _this5 = this;
    var $field = jQuery(e.target);
    var $row = $field.closest('.aie-filter-row');
    var $condition = $row.find('.aie-updater-filter-condition');
    var selectedOption = $field.find('option:selected');
    var fieldType = selectedOption.data('type') || 'string';

    // Populate condition dropdown based on field type
    $condition.empty();
    var conditions = this.getConditionsByFieldType(fieldType);
    conditions.forEach(function (condition) {
      $condition.append(jQuery('<option>').val(condition.value).text(condition.label));
    });
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
      return _this5.refreshCount(false);
    }, 500)();
  },
  /**
   * Handle filter condition change
   */
  onFilterConditionChange: function onFilterConditionChange(e) {
    var _this6 = this;
    var $condition = jQuery(e.target);
    var $row = $condition.closest('.aie-filter-row');
    var $valueWrap = $row.find('.aie-filter-value-wrap');
    var conditionValue = $condition.val();

    // Hide/show value input based on condition
    if (conditionValue === 'is_empty' || conditionValue === 'is_not_empty') {
      $valueWrap.hide();
    } else {
      $valueWrap.show();
    }
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
      return _this6.refreshCount(false);
    }, 500)();
  },
  /**
   * Get conditions by field type
   */
  getConditionsByFieldType: function getConditionsByFieldType(fieldType) {
    var stringConditions = [{
      value: 'equals',
      label: 'Equals'
    }, {
      value: 'not_equals',
      label: 'Not Equals'
    }, {
      value: 'contains',
      label: 'Contains'
    }, {
      value: 'not_contains',
      label: 'Not Contains'
    }, {
      value: 'starts_with',
      label: 'Starts With'
    }, {
      value: 'ends_with',
      label: 'Ends With'
    }, {
      value: 'is_empty',
      label: 'Is Empty'
    }, {
      value: 'is_not_empty',
      label: 'Is Not Empty'
    }];
    var numberConditions = [{
      value: 'equals',
      label: 'Equals'
    }, {
      value: 'not_equals',
      label: 'Not Equals'
    }, {
      value: 'greater',
      label: 'Greater Than'
    }, {
      value: 'less',
      label: 'Less Than'
    }, {
      value: 'equals_or_greater',
      label: 'Greater or Equal'
    }, {
      value: 'equals_or_less',
      label: 'Less or Equal'
    }, {
      value: 'between',
      label: 'Between'
    }];
    var dateConditions = [{
      value: 'equals',
      label: 'On Date'
    }, {
      value: 'before',
      label: 'Before'
    }, {
      value: 'after',
      label: 'After'
    }, {
      value: 'between',
      label: 'Between'
    }];
    switch (fieldType) {
      case 'number':
      case 'id':
        return numberConditions;
      case 'date':
      case 'datetime':
        return dateConditions;
      default:
        return stringConditions;
    }
  },
  /**
   * Refresh item count
   */
  refreshCount: function refreshCount() {
    var _this7 = this;
    var showSpinner = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : true;
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();
    if (!contentType) {
      return;
    }
    var $countValue = jQuery('.aie-count-value');
    var $spinner = jQuery('.aie-filter-summary-top .spinner');
    if (showSpinner) {
      $spinner.addClass('is-active');
    }

    // Collect filters
    var filters = this.collectFilters();
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_updater_get_count',
        nonce: aieData.nonce,
        content_type: contentType,
        filters: JSON.stringify(filters),
        options: {}
      },
      success: function success(response) {
        $spinner.removeClass('is-active');
        if (response.success) {
          $countValue.text(response.data.count);
          // Save the filtered count for later use
          _this7.filteredCount = response.data.count;
        } else {
          $countValue.text('Error');
        }
      },
      error: function error() {
        $spinner.removeClass('is-active');
        $countValue.text('Error');
      }
    });
  },
  /**
   * Collect filters from UI
   */
  collectFilters: function collectFilters() {
    var filters = [];
    jQuery('.aie-filter-row').each(function () {
      var $row = jQuery(this);
      var field = $row.find('.aie-updater-filter-field').val();
      var condition = $row.find('.aie-updater-filter-condition').val();
      var value = $row.find('.aie-updater-filter-value').val();
      if (field && condition) {
        filters.push({
          field: field,
          condition: condition,
          value: value
        });
      }
    });
    return filters;
  },
  /**
   * Load fields library for selected content type
   */
  loadFieldsLibrary: function loadFieldsLibrary() {
    console.log('loadFieldsLibrary called');

    // Get selected content type
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();
    if (!contentType) {
      console.error('No content type selected');
      return;
    }
    console.log('Loading fields for content type:', contentType);

    // Load static fields based on content type
    this.loadStaticFields(contentType);

    // Load dynamic fields for this content type (excluding taxonomies for Content Updater)
    if (contentType === 'post' || contentType.startsWith('post_type_')) {
      var postType = contentType === 'post' ? 'post' : contentType.replace('post_type_', '');
      // Skip taxonomies for Content Updater
      // this.loadTaxonomies( postType );
      this.loadCustomFields(postType);
      this.checkAndLoadACF(postType);
      this.checkAndLoadYoast(postType);
    }
  },
  /**
   * Load static fields based on content type
   */
  loadStaticFields: function loadStaticFields(contentType) {
    var _this8 = this;
    // Get field definitions from export module
    if (typeof window.aieExportModule === 'undefined' || !window.aieExportModule.getFieldsByContentType) {
      console.error('Export module not found or getFieldsByContentType method missing');
      return;
    }
    var fieldGroups = window.aieExportModule.getFieldsByContentType(contentType);
    console.log('Loading static fields for content type:', contentType, fieldGroups);

    // Find the container
    var $library = jQuery('#aie-updater-fields-library');
    if (!$library.length) {
      console.error('Fields library container not found');
      return;
    }

    // Clear existing content
    $library.empty();

    // Add container for fields
    $library.append('<div class="aie-fields-library-body"></div>');
    var $body = $library.find('.aie-fields-library-body');

    // Render each field group as a category
    fieldGroups.forEach(function (group, index) {
      // Skip Custom Filters, selector groups, Taxonomy and Author categories for Content Updater
      if (group.label === 'Custom Filters' || group.label === 'Post Type Selection' || group.label === 'Taxonomy Selection' || group.label === 'Taxonomy' || group.label === 'Author') {
        return;
      }
      var $category = _this8.createFieldCategory(group, index === 0);
      $body.append($category);
    });

    // Add placeholder categories for dynamic fields (excluding Taxonomies)
    $body.append("\n\t\t\t<div class=\"aie-field-category aie-collapsed aie-custom-fields-category\" style=\"display: none;\">\n\t\t\t\t<h4 class=\"aie-field-category-title\">\n\t\t\t\t\t<span class=\"dashicons dashicons-arrow-down-alt2 aie-category-toggle\"></span>\n\t\t\t\t\t<span class=\"dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\tCustom Fields\n\t\t\t\t</h4>\n\t\t\t\t<div class=\"aie-fields-grid aie-custom-fields-grid\"></div>\n\t\t\t</div>\n\t\t\t<div class=\"aie-field-category aie-collapsed aie-acf-fields-category\" style=\"display: none;\">\n\t\t\t\t<h4 class=\"aie-field-category-title\">\n\t\t\t\t\t<span class=\"dashicons dashicons-arrow-down-alt2 aie-category-toggle\"></span>\n\t\t\t\t\t<span class=\"dashicons dashicons-admin-settings\"></span>\n\t\t\t\t\tACF Fields\n\t\t\t\t</h4>\n\t\t\t\t<div class=\"aie-fields-grid aie-acf-fields-grid\">\n\t\t\t\t\t<div class=\"aie-acf-loading\"><span class=\"spinner is-active\"></span><p>Loading ACF fields...</p></div>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t\t<div class=\"aie-field-category aie-collapsed aie-yoast-fields-category\" style=\"display: none;\">\n\t\t\t\t<h4 class=\"aie-field-category-title\">\n\t\t\t\t\t<span class=\"dashicons dashicons-arrow-down-alt2 aie-category-toggle\"></span>\n\t\t\t\t\t<span class=\"dashicons dashicons-chart-line\"></span>\n\t\t\t\t\tYoast SEO\n\t\t\t\t</h4>\n\t\t\t\t<div class=\"aie-fields-grid aie-yoast-fields-grid\">\n\t\t\t\t\t<div class=\"aie-yoast-loading\"><span class=\"spinner is-active\"></span><p>Loading Yoast SEO fields...</p></div>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t");

    // Setup drag and drop
    this.setupFieldsDragAndDrop();

    // Setup category toggle
    this.setupCategoryToggle();
  },
  /**
   * Create a field category element
   */
  createFieldCategory: function createFieldCategory(group) {
    var _this9 = this;
    var isOpen = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
    var $category = jQuery('<div>').addClass('aie-field-category');
    if (!isOpen) {
      $category.addClass('aie-collapsed');
    }
    var $title = jQuery('<h4>').addClass('aie-field-category-title').html("\n\t\t\t<span class=\"dashicons dashicons-arrow-down-alt2 aie-category-toggle\"></span>\n\t\t\t<span class=\"dashicons dashicons-admin-post\"></span>\n\t\t\t".concat(this.escapeHtml(group.label), "\n\t\t"));
    var $grid = jQuery('<div>').addClass('aie-fields-grid');

    // Add fields
    if (group.options && Array.isArray(group.options)) {
      group.options.forEach(function (option) {
        // Skip special filter types
        if (option.type === 'custom_field' || option.type === 'taxonomy_filter' || option.type === 'post_type_selector' || option.type === 'taxonomy_selector' || option.type === 'table_selector') {
          return;
        }
        var $field = _this9.createFieldItem(option);
        $grid.append($field);
      });
    }
    $category.append($title).append($grid);
    return $category;
  },
  /**
   * Create a field item element
   */
  createFieldItem: function createFieldItem(option) {
    var iconClass = this.getFieldIcon(option.type);
    var $item = jQuery('<div>').addClass('aie-field-item').attr('draggable', true).attr('data-field', option.value).attr('data-label', option.label).attr('data-type', option.type || 'text').html("\n\t\t\t\t<span class=\"aie-field-icon dashicons ".concat(iconClass, "\"></span>\n\t\t\t\t<span class=\"aie-field-label\">").concat(this.escapeHtml(option.label), "</span>\n\t\t\t\t<span class=\"aie-field-type\">").concat(this.escapeHtml(option.type || 'text'), "</span>\n\t\t\t"));
    return $item;
  },
  /**
   * Get field icon based on type
   */
  getFieldIcon: function getFieldIcon(type) {
    var iconMap = {
      'text': 'dashicons-text',
      'number': 'dashicons-calculator',
      'date': 'dashicons-calendar-alt',
      'email': 'dashicons-email',
      'url': 'dashicons-admin-links',
      'textarea': 'dashicons-text',
      'select': 'dashicons-menu',
      'checkbox': 'dashicons-yes',
      'radio': 'dashicons-marker',
      'taxonomy': 'dashicons-category',
      'meta': 'dashicons-admin-generic',
      'acf': 'dashicons-admin-settings',
      'yoast': 'dashicons-chart-line'
    };
    return iconMap[type] || 'dashicons-admin-generic';
  },
  /**
   * Setup category toggle
   */
  setupCategoryToggle: function setupCategoryToggle() {
    jQuery(document).off('click.categoryToggle', '.aie-field-category-title');
    jQuery(document).on('click.categoryToggle', '.aie-field-category-title', function (e) {
      // Don't toggle if clicking on "Add all" button
      if (jQuery(e.target).closest('.aie-add-all-fields').length) {
        return;
      }
      jQuery(this).closest('.aie-field-category').toggleClass('aie-collapsed');
    });
  },
  /**
   * Load taxonomies for selected post type
   */
  loadTaxonomies: function loadTaxonomies(postType) {
    var _this10 = this;
    if (typeof aieData === 'undefined') return;
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_get_taxonomies',
        nonce: aieData.nonce,
        post_type: postType
      },
      success: function success(response) {
        console.log('Taxonomies response:', response);
        if (response.success && response.data.taxonomies && response.data.taxonomies.length > 0) {
          _this10.renderTaxonomies(response.data.taxonomies);
          jQuery('.aie-taxonomies-category').show();
        } else {
          jQuery('.aie-taxonomies-category').hide();
        }
      },
      error: function error(xhr, status, _error) {
        console.error('Taxonomies AJAX error:', _error, xhr.responseText);
      }
    });
  },
  /**
   * Render taxonomies
   */
  renderTaxonomies: function renderTaxonomies(taxonomies) {
    var _this11 = this;
    var $grid = jQuery('.aie-taxonomies-grid');
    if (!$grid.length) return;
    $grid.empty();
    taxonomies.forEach(function (taxonomy) {
      var $item = jQuery('<div>').addClass('aie-field-item').attr('draggable', true).attr('data-field', 'taxonomy_' + taxonomy.name).attr('data-label', taxonomy.label).attr('data-type', 'taxonomy').html("\n\t\t\t\t\t<span class=\"aie-field-icon dashicons dashicons-category\"></span>\n\t\t\t\t\t<span class=\"aie-field-label\">".concat(_this11.escapeHtml(taxonomy.label), "</span>\n\t\t\t\t\t<span class=\"aie-field-type\">taxonomy</span>\n\t\t\t\t"));
      $grid.append($item);
    });
  },
  /**
   * Load custom fields for selected post type
   */
  loadCustomFields: function loadCustomFields(postType) {
    var _this12 = this;
    if (typeof aieData === 'undefined') return;
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_get_custom_fields',
        nonce: aieData.nonce,
        post_type: postType
      },
      success: function success(response) {
        console.log('Custom fields response:', response);
        if (response.success && response.data.fields && response.data.fields.length > 0) {
          _this12.renderCustomFields(response.data.fields);
          jQuery('.aie-custom-fields-category').show();
        } else {
          jQuery('.aie-custom-fields-category').hide();
        }
      },
      error: function error(xhr, status, _error2) {
        console.error('Custom fields AJAX error:', _error2, xhr.responseText);
      }
    });
  },
  /**
   * Render custom fields
   */
  renderCustomFields: function renderCustomFields(fields) {
    var _this13 = this;
    var $grid = jQuery('.aie-custom-fields-grid');
    if (!$grid.length) return;
    $grid.empty();
    fields.forEach(function (field) {
      var $item = jQuery('<div>').addClass('aie-field-item').attr('draggable', true).attr('data-field', 'meta_' + field.name).attr('data-label', field.name).attr('data-type', 'meta').html("\n\t\t\t\t\t<span class=\"aie-field-icon dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\t<span class=\"aie-field-label\">".concat(_this13.escapeHtml(field.name), "</span>\n\t\t\t\t\t<span class=\"aie-field-type\">meta</span>\n\t\t\t\t"));
      $grid.append($item);
    });
  },
  /**
   * Check if ACF is active and load ACF fields
   */
  checkAndLoadACF: function checkAndLoadACF(postType) {
    var _this14 = this;
    if (typeof aieData === 'undefined') return;
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_get_acf_fields',
        nonce: aieData.nonce,
        post_type: postType
      },
      success: function success(response) {
        console.log('ACF fields response:', response);
        if (response.success && response.data.fields && response.data.fields.length > 0) {
          _this14.renderACFFields(response.data.fields);
          jQuery('.aie-acf-fields-category').show();
        } else {
          jQuery('.aie-acf-fields-category').hide();
        }
      },
      error: function error(xhr, status, _error3) {
        console.error('ACF fields AJAX error:', _error3, xhr.responseText);
      }
    });
  },
  /**
   * Render ACF fields
   */
  renderACFFields: function renderACFFields(fields) {
    var _this15 = this;
    var $grid = jQuery('.aie-acf-fields-grid');
    if (!$grid.length) return;
    $grid.empty();
    fields.forEach(function (field) {
      var $item = jQuery('<div>').addClass('aie-field-item').attr('draggable', true).attr('data-field', 'acf_' + field.name).attr('data-label', field.label).attr('data-type', 'acf').html("\n\t\t\t\t\t<span class=\"aie-field-icon dashicons dashicons-admin-settings\"></span>\n\t\t\t\t\t<span class=\"aie-field-label\">".concat(_this15.escapeHtml(field.label), "</span>\n\t\t\t\t\t<span class=\"aie-field-type\">acf</span>\n\t\t\t\t"));
      $grid.append($item);
    });
  },
  /**
   * Check if Yoast is active and load Yoast fields
   */
  checkAndLoadYoast: function checkAndLoadYoast(postType) {
    var _this16 = this;
    if (typeof aieData === 'undefined') return;
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_get_yoast_fields',
        nonce: aieData.nonce,
        post_type: postType
      },
      success: function success(response) {
        console.log('Yoast fields response:', response);
        if (response.success && response.data.fields && response.data.fields.length > 0) {
          _this16.renderYoastFields(response.data.fields);
          jQuery('.aie-yoast-fields-category').show();
        } else {
          jQuery('.aie-yoast-fields-category').hide();
        }
      },
      error: function error(xhr, status, _error4) {
        console.error('Yoast fields AJAX error:', _error4, xhr.responseText);
      }
    });
  },
  /**
   * Render Yoast fields
   */
  renderYoastFields: function renderYoastFields(fields) {
    var _this17 = this;
    var $grid = jQuery('.aie-yoast-fields-grid');
    if (!$grid.length) return;
    $grid.empty();
    fields.forEach(function (field) {
      var $item = jQuery('<div>').addClass('aie-field-item').attr('draggable', true).attr('data-field', 'yoast_' + field.name).attr('data-label', field.label).attr('data-type', 'yoast').html("\n\t\t\t\t\t<span class=\"aie-field-icon dashicons dashicons-chart-line\"></span>\n\t\t\t\t\t<span class=\"aie-field-label\">".concat(_this17.escapeHtml(field.label), "</span>\n\t\t\t\t\t<span class=\"aie-field-type\">yoast</span>\n\t\t\t\t"));
      $grid.append($item);
    });
  },
  /**
   * Escape HTML
   */
  escapeHtml: function escapeHtml(text) {
    var map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  },
  /**
   * Setup drag and drop for fields
   */
  setupDragAndDrop: function setupDragAndDrop() {
    // Will be implemented with field selection
  },
  /**
   * Setup drag and drop handlers for field items
   */
  setupFieldsDragAndDrop: function setupFieldsDragAndDrop() {
    var _this18 = this;
    var $items = jQuery('.aie-field-item');
    var $dropzone = jQuery('#aie-updater-dropzone');
    $items.on('dragstart', function (e) {
      var field = jQuery(e.currentTarget).data('field');
      e.originalEvent.dataTransfer.setData('field', field);
      e.originalEvent.dataTransfer.setData('label', jQuery(e.currentTarget).find('.aie-field-label').text());
    });
    $items.on('click', function (e) {
      var $item = jQuery(e.currentTarget);
      _this18.addField($item.data('field'), $item.find('.aie-field-label').text());
    });
    $dropzone.on('dragover', function (e) {
      e.preventDefault();
      $dropzone.addClass('aie-drag-over');
    });
    $dropzone.on('dragleave', function () {
      $dropzone.removeClass('aie-drag-over');
    });
    $dropzone.on('drop', function (e) {
      e.preventDefault();
      $dropzone.removeClass('aie-drag-over');
      var field = e.originalEvent.dataTransfer.getData('field');
      var label = e.originalEvent.dataTransfer.getData('label');
      if (field) {
        _this18.addField(field, label);
      }
    });
  },
  /**
   * Add field to selected fields list
   */
  addField: function addField(field, label) {
    var _this19 = this;
    // Check if already added
    if (this.selectedFields.includes(field)) {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice("Field \"".concat(label, "\" is already selected"), 'warning');
      return;
    }
    this.selectedFields.push(field);
    var $list = jQuery('#aie-updater-fields-list');
    var $placeholder = jQuery('.aie-updater-dropzone-placeholder');
    $placeholder.hide();
    var fieldHtml = "\n\t\t\t<div class=\"aie-selected-field\" data-field=\"".concat(field, "\">\n\t\t\t\t<span class=\"aie-field-drag-handle dashicons dashicons-menu\"></span>\n\t\t\t\t<span class=\"aie-field-name\">").concat(label, "</span>\n\t\t\t\t<button type=\"button\" class=\"aie-remove-field\" title=\"Remove\">\n\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t</button>\n\t\t\t</div>\n\t\t");
    $list.append(fieldHtml);
    this.updateFieldCount();

    // Bind remove handler
    $list.find('.aie-remove-field').last().on('click', function (e) {
      _this19.removeField(jQuery(e.currentTarget).closest('.aie-selected-field').data('field'));
    });
  },
  /**
   * Remove field from selected fields
   */
  removeField: function removeField(field) {
    var index = this.selectedFields.indexOf(field);
    if (index > -1) {
      this.selectedFields.splice(index, 1);
      delete this.fieldFunctions[field];
    }
    jQuery(".aie-selected-field[data-field=\"".concat(field, "\"]")).remove();
    this.updateFieldCount();
    if (this.selectedFields.length === 0) {
      jQuery('.aie-updater-dropzone-placeholder').show();
    }
  },
  /**
   * Clear all selected fields
   */
  clearAllFields: function clearAllFields() {
    if (!confirm('Are you sure you want to clear all selected fields?')) {
      return;
    }
    this.selectedFields = [];
    this.fieldFunctions = {};
    jQuery('#aie-updater-fields-list').empty();
    jQuery('.aie-updater-dropzone-placeholder').show();
    this.updateFieldCount();
  },
  /**
   * Update field count display
   */
  updateFieldCount: function updateFieldCount() {
    jQuery('.aie-fields-count').text(this.selectedFields.length);
  },
  /**
   * Filter fields in library
   */
  filterFields: function filterFields(e) {
    var searchTerm = jQuery(e.target).val().toLowerCase();
    var $fields = jQuery('.aie-field-item');
    $fields.each(function () {
      var $field = jQuery(this);
      var label = $field.find('.aie-field-label').text().toLowerCase();
      if (label.includes(searchTerm)) {
        $field.show();
      } else {
        $field.hide();
      }
    });
  },
  /**
   * Load available functions from server
   */
  loadAvailableFunctions: function loadAvailableFunctions() {
    var _this20 = this;
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_get_functions',
        nonce: aieData.nonce
      },
      success: function success(response) {
        if (response.success && response.data.functions) {
          _this20.availableFunctions = response.data.functions;
          _this20.renderAvailableFunctions();
        }
      }
    });
  },
  /**
   * Build functions assignment table
   */
  buildFunctionsTable: function buildFunctionsTable() {
    var _this21 = this;
    var $tbody = jQuery('#aie-updater-functions-tbody');
    $tbody.empty();
    if (this.selectedFields.length === 0) {
      $tbody.html("\n\t\t\t\t<tr class=\"aie-no-fields-row\">\n\t\t\t\t\t<td colspan=\"4\" class=\"aie-no-fields-message\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t\t\tNo fields selected. Please go back and select fields first.\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t");
      return;
    }
    this.selectedFields.forEach(function (field, index) {
      var functions = _this21.fieldFunctions[field] || [];
      var functionsCount = Array.isArray(functions) ? functions.length : 0;
      var fieldLabel = jQuery(".aie-selected-field[data-field=\"".concat(field, "\"] .aie-field-name")).text() || field;
      var functionsText = 'None';
      if (functionsCount > 0) {
        functionsText = "".concat(functionsCount, " function").concat(functionsCount > 1 ? 's' : '');
      }
      var html = "\n\t\t\t\t<tr data-field=\"".concat(field, "\">\n\t\t\t\t\t<td class=\"aie-field-name-col\">\n\t\t\t\t\t\t<strong>").concat(_this21.escapeHtml(fieldLabel), "</strong>\n\t\t\t\t\t\t<br><code>").concat(_this21.escapeHtml(field), "</code>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"aie-field-type-col\">\n\t\t\t\t\t\t<span class=\"aie-field-type-badge\">Text</span>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"aie-functions-col\">\n\t\t\t\t\t\t<span class=\"aie-functions-count-badge\">").concat(functionsText, "</span>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"aie-actions-col\">\n\t\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-assign-functions\" data-field=\"").concat(field, "\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\t\t\tAssign Functions\n\t\t\t\t\t\t</button>\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t");
      $tbody.append(html);
    });

    // Bind assign functions button
    $tbody.find('.aie-assign-functions').on('click', function (e) {
      var field = jQuery(e.currentTarget).data('field');
      _this21.openFieldFunctionsModal(field);
    });
    this.updateFunctionStats();
  },
  /**
   * Open field functions modal
   */
  openFieldFunctionsModal: function openFieldFunctionsModal(fieldKey) {
    var fieldLabel = jQuery(".aie-selected-field[data-field=\"".concat(fieldKey, "\"] .aie-field-name")).text() || fieldKey;
    var fieldType = 'text'; // Can be enhanced later

    this.currentEditingField = fieldKey;
    var $modal = jQuery('#aie-updater-functions-modal');
    if (!$modal.length) {
      console.error('Functions modal not found');
      return;
    }

    // Set field info
    $modal.find('.aie-current-field-label').text(fieldLabel);
    $modal.find('.aie-current-field-type').text(fieldType);

    // Load current functions
    this.loadCurrentFunctions(fieldKey);

    // Load available functions
    this.renderAvailableFunctions();

    // Show modal
    $modal.css('display', 'flex');
    jQuery('body').addClass('aie-modal-open');

    // Initialize sortable if not already done
    this.initFunctionPipelineSortable();
  },
  /**
   * Close field functions modal
   */
  closeFieldFunctionsModal: function closeFieldFunctionsModal() {
    var $modal = jQuery('#aie-updater-functions-modal');
    if ($modal.length) {
      $modal.css('display', 'none');
      jQuery('body').removeClass('aie-modal-open');

      // Hide preview results
      var $previewResult = $modal.find('#aie-updater-preview-result');
      if ($previewResult.length) {
        $previewResult.css('display', 'none');
      }

      // Clear preview input
      var $previewInput = $modal.find('#aie-updater-preview-input');
      if ($previewInput.length) {
        $previewInput.val('');
      }
    }
    this.currentEditingField = null;
  },
  /**
   * Load current functions for field
   */
  loadCurrentFunctions: function loadCurrentFunctions(fieldKey) {
    var _this22 = this;
    var $container = jQuery('#aie-updater-function-items');
    if (!$container.length) return;
    $container.empty();
    var functions = this.fieldFunctions[fieldKey] || [];
    var $noFunctionsEl = jQuery('.aie-no-functions');
    if (!Array.isArray(functions) || functions.length === 0) {
      if ($noFunctionsEl.length) $noFunctionsEl.show();
      this.updateFunctionsCount(0);
      return;
    }
    if ($noFunctionsEl.length) $noFunctionsEl.hide();
    functions.forEach(function (funcId) {
      var func = _this22.availableFunctions.find(function (f) {
        return f.id == funcId;
      });
      if (func) {
        _this22.addFunctionToPipeline(func, false);
      }
    });
    this.updateFunctionsCount(functions.length);
  },
  /**
   * Add function to pipeline
   */
  addFunctionToPipeline: function addFunctionToPipeline(func) {
    var _this23 = this;
    var updateArray = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : true;
    var $container = jQuery('#aie-updater-function-items');
    if (!$container.length) return;
    var $item = jQuery('<div>').addClass('aie-function-item').attr('data-function-id', func.id).html("\n\t\t\t\t<span class=\"aie-function-handle dashicons dashicons-menu\"></span>\n\t\t\t\t<div class=\"aie-function-info\">\n\t\t\t\t\t<strong class=\"aie-function-name\">".concat(this.escapeHtml(func.name), "</strong>\n\t\t\t\t\t<span class=\"aie-function-desc\">").concat(this.escapeHtml(func.description || ''), "</span>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-function-actions\">\n\t\t\t\t\t<button type=\"button\" class=\"button-small aie-remove-function\" data-function-id=\"").concat(func.id, "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t</div>\n\t\t\t"));

    // Remove function event
    $item.find('.aie-remove-function').on('click', function () {
      $item.remove();
      _this23.updatePipelineFunctions();
      _this23.updateFunctionsCount();
      _this23.toggleNoFunctionsMessage();
    });
    $container.append($item);
    if (updateArray) {
      this.updatePipelineFunctions();
      this.updateFunctionsCount();
    }
    this.toggleNoFunctionsMessage();
  },
  /**
   * Update pipeline functions array
   */
  updatePipelineFunctions: function updatePipelineFunctions() {
    if (!this.currentEditingField) return;
    var functionIds = [];
    jQuery('.aie-function-item').each(function () {
      functionIds.push(jQuery(this).data('function-id'));
    });
    this.fieldFunctions[this.currentEditingField] = functionIds;
  },
  /**
   * Update functions count
   */
  updateFunctionsCount: function updateFunctionsCount() {
    var count = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : null;
    var $countEl = jQuery('.aie-functions-count');
    if (!$countEl.length) return;
    if (count === null) {
      count = jQuery('.aie-function-item').length;
    }
    $countEl.text("(".concat(count, ")"));
  },
  /**
   * Toggle no functions message
   */
  toggleNoFunctionsMessage: function toggleNoFunctionsMessage() {
    var $noFunctionsEl = jQuery('.aie-no-functions');
    var count = jQuery('.aie-function-item').length;
    if (count === 0) {
      $noFunctionsEl.show();
    } else {
      $noFunctionsEl.hide();
    }
  },
  /**
   * Render available functions
   */
  renderAvailableFunctions: function renderAvailableFunctions() {
    var _this24 = this;
    var $list = jQuery('#aie-updater-functions-list');
    if (!$list.length) return;
    $list.empty();
    if (this.availableFunctions.length === 0) {
      $list.html("\n\t\t\t\t<div class=\"aie-no-functions-available\">\n\t\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t\t<p>No functions available. Create a custom function first.</p>\n\t\t\t\t</div>\n\t\t\t");
      return;
    }
    this.availableFunctions.forEach(function (func) {
      var $funcItem = jQuery('<div>').addClass('aie-function-list-item').attr('data-function-id', func.id).attr('data-category', func.category || 'custom').html("\n\t\t\t\t\t<div class=\"aie-function-list-info\">\n\t\t\t\t\t\t<strong class=\"aie-function-list-name\">".concat(_this24.escapeHtml(func.name), "</strong>\n\t\t\t\t\t\t<span class=\"aie-function-list-desc\">").concat(_this24.escapeHtml(func.description || ''), "</span>\n\t\t\t\t\t</div>\n\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-add-function-btn\" data-function-id=\"").concat(func.id, "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-plus-alt\"></span>\n\t\t\t\t\t\tAdd\n\t\t\t\t\t</button>\n\t\t\t\t"));

      // Add function event
      $funcItem.find('.aie-add-function-btn').on('click', function () {
        _this24.addFunctionToPipeline(func, true);
      });
      $list.append($funcItem);
    });
  },
  /**
   * Initialize function pipeline sortable
   */
  initFunctionPipelineSortable: function initFunctionPipelineSortable() {
    var _this25 = this;
    var $container = jQuery('#aie-updater-function-items');
    if (!$container.length) return;

    // Check if already initialized
    if ($container.data('ui-sortable')) {
      return;
    }
    $container.sortable({
      handle: '.aie-function-handle',
      placeholder: 'aie-function-item-placeholder',
      axis: 'y',
      update: function update() {
        _this25.updatePipelineFunctions();
      }
    });
  },
  /**
   * Save field functions
   */
  saveFieldFunctions: function saveFieldFunctions() {
    this.updatePipelineFunctions();
    this.closeFieldFunctionsModal();
    this.buildFunctionsTable(); // Rebuild table to show updated function counts
  },
  /**
   * Handle function selection change
   */
  onFunctionChange: function onFunctionChange(e) {
    var $select = jQuery(e.target);
    var field = $select.data('field');
    var functionId = $select.val();
    this.fieldFunctions[field] = functionId;
    this.updateFunctionStats();
  },
  /**
   * Update function statistics
   */
  updateFunctionStats: function updateFunctionStats() {
    var total = this.selectedFields.length;
    var assigned = 0;
    var noFunction = 0;
    Object.values(this.fieldFunctions).forEach(function (functions) {
      if (Array.isArray(functions) && functions.length > 0) {
        assigned++;
      } else {
        noFunction++;
      }
    });
    noFunction = total - assigned;
    jQuery('.aie-total-fields-stat').text(total);
    jQuery('.aie-functions-assigned-stat').text(assigned);
    jQuery('.aie-no-function-stat').text(noFunction);
  },
  /**
   * Apply function to all fields
   */
  applyFunctionToAll: function applyFunctionToAll() {
    var _this26 = this;
    // Show a dialog to select the function
    var functionId = prompt('Enter function ID to apply to all fields (or leave empty for none):');
    if (functionId === null) {
      return; // Cancelled
    }
    var finalId = functionId.trim() || 'none';
    this.selectedFields.forEach(function (field) {
      _this26.fieldFunctions[field] = finalId;
      jQuery(".aie-field-function-select[data-field=\"".concat(field, "\"]")).val(finalId);
    });
    this.updateFunctionStats();
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('success', 'Function applied to all fields');
  },
  /**
   * Clear all function assignments
   */
  clearAllFunctions: function clearAllFunctions() {
    var _this27 = this;
    if (!confirm('Are you sure you want to clear all function assignments?')) {
      return;
    }
    this.selectedFields.forEach(function (field) {
      _this27.fieldFunctions[field] = [];
      var $row = jQuery("tr[data-field=\"".concat(field, "\"]"));
      $row.find('.aie-updater-field-functions').empty();
    });
    this.buildFunctionsTable();
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('All function assignments cleared', 'success');
  },
  /**
   * Test function with sample input
   */
  testFunction: function testFunction(e) {
    var field = jQuery(e.currentTarget).data('field');
    var functionId = this.fieldFunctions[field];
    if (!functionId || functionId === 'none') {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('No function assigned to this field', 'warning');
      return;
    }

    // Show preview popup (simplified for now)
    var testValue = prompt('Enter a test value:');
    if (testValue === null) {
      return;
    }

    // Test the function
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_functions_execute',
        nonce: aieData.nonce,
        function_id: functionId,
        input_value: testValue
      },
      success: function success(response) {
        if (response.success) {
          alert("Output: ".concat(response.data.output));
        } else {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(response.data.message || 'Function test failed', 'error');
        }
      }
    });
  },
  /**
   * Prepare update summary
   */
  prepareUpdateSummary: function prepareUpdateSummary() {
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();
    var contentTypeLabel = jQuery('input[name="updater_content_type"]:checked').closest('.aie-content-type').find('h3').text();

    // Update summary display
    jQuery('.aie-content-type-summary').text(contentTypeLabel);
    jQuery('.aie-fields-summary').text(this.selectedFields.length);
    var functionsCount = Object.values(this.fieldFunctions).filter(function (fn) {
      return fn && fn !== 'none';
    }).length;
    jQuery('.aie-functions-summary').text(functionsCount);

    // Get count of items (with filters applied)
    // If we already have a filtered count from Step 2, we can use it
    // Otherwise, make a new request
    if (this.filteredCount !== undefined && this.filteredCount !== null) {
      jQuery('.aie-total-items-summary').text(this.filteredCount);
    } else {
      this.getItemCount();
    }
  },
  /**
   * Get count of items to update
   */
  getItemCount: function getItemCount() {
    var _this28 = this;
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();
    var $countValue = jQuery('.aie-total-items-summary');
    $countValue.html('<span class="spinner" style="float:none;margin:0;"></span>');

    // Include filters in the count request
    var filters = this.selectedFilters || [];
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_updater_get_count',
        nonce: aieData.nonce,
        content_type: contentType,
        filters: JSON.stringify(filters),
        options: {}
      },
      success: function success(response) {
        if (response.success) {
          $countValue.text(response.data.count);
          // Save the filtered count
          _this28.filteredCount = response.data.count;
        } else {
          $countValue.text('Error');
        }
      },
      error: function error() {
        $countValue.text('Error');
      }
    });
  },
  /**
   * Start update process
   */
  startUpdate: function startUpdate() {
    var _this29 = this;
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();
    var itemsPerIteration = parseInt(jQuery('#aie-updater-items-per-iteration').val()) || 10;

    // Validate fields and functions
    if (!this.selectedFields || this.selectedFields.length === 0) {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('No fields selected. Please go back and select fields to update.', 'error');
      console.error('selectedFields is empty:', this.selectedFields);
      return;
    }
    if (!this.fieldFunctions || Object.keys(this.fieldFunctions).length === 0) {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('No functions assigned. Please go back and assign functions to fields.', 'error');
      console.error('fieldFunctions is empty:', this.fieldFunctions);
      return;
    }

    // Prepare field functions array (indexed by field position)
    var fieldFunctionsArray = this.selectedFields.map(function (field) {
      return _this29.fieldFunctions[field] || [];
    });
    console.log('Starting update with:', {
      contentType: contentType,
      selectedFields: this.selectedFields,
      fieldFunctions: this.fieldFunctions,
      fieldFunctionsArray: fieldFunctionsArray
    });

    // Show progress section
    jQuery('#aie-updater-config').hide();
    jQuery('#aie-updater-progress').show();
    jQuery('#aie-updater-prev-from-step-4').hide();

    // Start update job
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_updater_start',
        nonce: aieData.nonce,
        content_type: contentType,
        fields: JSON.stringify(this.selectedFields),
        field_functions: JSON.stringify(fieldFunctionsArray),
        filters: JSON.stringify(this.selectedFilters || []),
        options: JSON.stringify({
          items_per_iteration: itemsPerIteration
        })
      },
      success: function success(response) {
        if (response.success) {
          _this29.jobId = response.data.job_id;
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Update started successfully', 'success');

          // Start processing
          _this29.startProgressTracking();
          _this29.processNextBatch();
        } else {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(response.data.message || 'Failed to start update', 'error');
          _this29.showResults('error');
        }
      },
      error: function error() {
        _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Failed to start update', 'error');
        _this29.showResults('error');
      }
    });
  },
  /**
   * Start progress tracking
   */
  startProgressTracking: function startProgressTracking() {
    var _this30 = this;
    this.progressInterval = setInterval(function () {
      _this30.updateProgress();
    }, 2000);
  },
  /**
   * Update progress display
   */
  updateProgress: function updateProgress() {
    var _this31 = this;
    return jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_updater_get_progress',
        nonce: aieData.nonce,
        job_id: this.jobId
      },
      success: function success(response) {
        if (response.success) {
          var progress = response.data;

          // Update progress bar
          jQuery('.aie-progress-bar-fill').css('width', progress.percentage + '%');
          jQuery('.aie-progress-percentage').text(progress.percentage + '%');

          // Update stats
          jQuery('.aie-processed-count').text(progress.processed_items);
          jQuery('.aie-total-count').text(progress.total_items);
          jQuery('.aie-updated-count').text(progress.updated_items);
          jQuery('.aie-skipped-count').text(progress.skipped_items);
          jQuery('.aie-errors-count').text(progress.error_items);

          // Update status
          jQuery('.aie-status-text').text("Processing items... (".concat(progress.processed_items, " / ").concat(progress.total_items, ")"));

          // Check if completed
          if (progress.status === 'completed' || progress.status === 'failed' || progress.status === 'cancelled') {
            _this31.stopProgressTracking();
            _this31.showResults(progress.status, progress);
          }
        }
      }
    });
  },
  /**
   * Process next batch
   */
  processNextBatch: function processNextBatch() {
    var _this32 = this;
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_updater_process_batch',
        nonce: aieData.nonce,
        job_id: this.jobId
      },
      success: function success(response) {
        if (response.success) {
          if (!response.data.completed) {
            // Process next batch
            setTimeout(function () {
              return _this32.processNextBatch();
            }, 500);
          }
        } else {
          console.error('Batch processing error:', response.data);
        }
      },
      error: function error(xhr) {
        console.error('AJAX error:', xhr);
      }
    });
  },
  /**
   * Stop progress tracking
   */
  stopProgressTracking: function stopProgressTracking() {
    if (this.progressInterval) {
      clearInterval(this.progressInterval);
      this.progressInterval = null;
    }
  },
  /**
   * Cancel update
   */
  cancelUpdate: function cancelUpdate() {
    var _this33 = this;
    if (!confirm('Are you sure you want to cancel the update?')) {
      return;
    }
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_updater_cancel',
        nonce: aieData.nonce,
        job_id: this.jobId
      },
      success: function success(response) {
        if (response.success) {
          _this33.stopProgressTracking();
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Update cancelled', 'info');
          _this33.showResults('cancelled');
        }
      }
    });
  },
  /**
   * Show results
   */
  showResults: function showResults(status) {
    var progress = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
    jQuery('#aie-updater-progress').hide();
    jQuery('#aie-updater-results').show();

    // Update final stats
    jQuery('.aie-final-processed').text(progress.processed_items || 0);
    jQuery('.aie-final-updated').text(progress.updated_items || 0);
    jQuery('.aie-final-skipped').text(progress.skipped_items || 0);
    jQuery('.aie-final-errors').text(progress.error_items || 0);

    // Update icon based on status
    var $icon = jQuery('.aie-results-header .dashicons');
    if (status === 'completed') {
      $icon.removeClass('dashicons-warning').addClass('dashicons-yes-alt aie-success-icon');
    } else {
      $icon.removeClass('dashicons-yes-alt').addClass('dashicons-warning');
    }
  },
  /**
   * Start new update
   */
  startNewUpdate: function startNewUpdate() {
    window.location.reload();
  },
  /**
   * Filter functions by search term
   */
  filterFunctions: function filterFunctions(searchTerm) {
    var term = searchTerm.toLowerCase();
    jQuery('.aie-function-list-item').each(function () {
      var $item = jQuery(this);
      var name = $item.find('.aie-function-list-name').text().toLowerCase();
      var desc = $item.find('.aie-function-list-desc').text().toLowerCase();
      if (name.includes(term) || desc.includes(term)) {
        $item.show();
      } else {
        $item.hide();
      }
    });
  },
  /**
   * Filter functions by category
   */
  filterFunctionsByCategory: function filterFunctionsByCategory(category) {
    if (category === 'all') {
      jQuery('.aie-function-list-item').show();
    } else {
      jQuery('.aie-function-list-item').each(function () {
        var $item = jQuery(this);
        var itemCategory = $item.data('category');
        if (itemCategory === category) {
          $item.show();
        } else {
          $item.hide();
        }
      });
    }
  },
  /**
   * Test function pipeline
   */
  testFunctionPipeline: function testFunctionPipeline() {
    var _this34 = this;
    var $input = jQuery('#aie-updater-preview-input');
    var testValue = $input.val();
    if (!testValue) {
      this.showNotice('Please enter a test value', 'warning');
      return;
    }
    var functionIds = [];
    jQuery('.aie-function-item').each(function () {
      functionIds.push(jQuery(this).data('function-id'));
    });
    console.log('Test Pipeline - Function IDs:', functionIds);
    if (functionIds.length === 0) {
      this.showNotice('No functions to test', 'warning');
      return;
    }

    // Check if aieData is available
    if (typeof aieData === 'undefined') {
      console.error('aieData is not defined');
      this.showNotice('Configuration error: aieData not found', 'error');
      return;
    }
    var requestData = {
      action: 'aie_test_function_pipeline',
      nonce: aieData.nonce,
      functions: functionIds,
      value: testValue
    };
    console.log('Test Pipeline - Request data:', requestData);
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: requestData,
      success: function success(response) {
        console.log('Test Pipeline - Response:', response);
        if (response.success) {
          _this34.renderPipelinePreview(testValue, response.data.steps);
        } else {
          _this34.showNotice(response.data.message || 'Test failed', 'error');
          console.error('Test Pipeline Error:', response.data);
        }
      },
      error: function error(xhr, status, _error5) {
        console.error('Test Pipeline AJAX Error:', {
          xhr: xhr,
          status: status,
          error: _error5
        });
        _this34.showNotice('Error testing pipeline', 'error');
      }
    });
  },
  /**
   * Render pipeline preview
   */
  renderPipelinePreview: function renderPipelinePreview(initialValue, steps) {
    var _this35 = this;
    var $container = jQuery('#aie-updater-preview-result');
    if (!$container.length) return;
    var $stepsContainer = $container.find('.aie-preview-steps');
    $stepsContainer.empty();

    // Initial value
    $stepsContainer.append(this.createPreviewStep(0, 'Input', initialValue));

    // Each function step
    if (steps && steps.length > 0) {
      steps.forEach(function (step, index) {
        $stepsContainer.append(_this35.createPreviewStep(index + 1, step.function_name, step.output, step.error));
      });
    }
    $container.show();
  },
  /**
   * Create preview step element
   */
  createPreviewStep: function createPreviewStep(number, name, value) {
    var error = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : false;
    return jQuery('<div>').addClass('aie-preview-step').html("\n\t\t\t\t<div class=\"aie-step-number\">".concat(number, "</div>\n\t\t\t\t<div class=\"aie-step-info\">\n\t\t\t\t\t<span class=\"aie-function-name\">").concat(this.escapeHtml(name), "</span>\n\t\t\t\t\t<span class=\"aie-step-value ").concat(error ? 'error' : '', "\">\n\t\t\t\t\t\t").concat(this.escapeHtml(error ? "Error: ".concat(value) : value), "\n\t\t\t\t\t</span>\n\t\t\t\t</div>\n\t\t\t"));
  },
  /**
   * Show notice (simple console logging to avoid z-index issues with modal)
   */
  showNotice: function showNotice(message) {
    var type = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'info';
    // Log to console
    console.log("[".concat(type.toUpperCase(), "] ").concat(message));
  },
  /**
   * Create new function
   */
  createNewFunction: function createNewFunction() {
    // Open Functions management page in new tab
    if (typeof aieData !== 'undefined' && aieData.functionsUrl) {
      window.open(aieData.functionsUrl, '_blank');
    } else {
      // Fallback - go to admin page
      window.open('/wp-admin/admin.php?page=wp-aie-functions', '_blank');
    }
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ContentUpdater);

/***/ }),

/***/ "./src/js/modules/export-step-3.js":
/*!*****************************************!*\
  !*** ./src/js/modules/export-step-3.js ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ExportStep3)
/* harmony export */ });
function _toConsumableArray(arr) { return _arrayWithoutHoles(arr) || _iterableToArray(arr) || _unsupportedIterableToArray(arr) || _nonIterableSpread(); }
function _nonIterableSpread() { throw new TypeError("Invalid attempt to spread non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _iterableToArray(iter) { if (typeof Symbol !== "undefined" && iter[Symbol.iterator] != null || iter["@@iterator"] != null) return Array.from(iter); }
function _arrayWithoutHoles(arr) { if (Array.isArray(arr)) return _arrayLikeToArray(arr); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
/**
 * Export Step 3: Field Selection with Drag & Drop
 */
var ExportStep3 = /*#__PURE__*/function () {
  function ExportStep3() {
    _classCallCheck(this, ExportStep3);
    this.selectedFields = [];
    this.fieldFunctions = {}; // { fieldKey: [functionId1, functionId2] }
    this.currentEditingField = null;
    this.availableFunctions = [];
    this.isDragging = false;
    this.autoScrollInterval = null;
    this.selectedPostType = null;
    console.log('ExportStep3 constructor: selectedFields initialized to:', this.selectedFields);
    this.init();
  }
  _createClass(ExportStep3, [{
    key: "init",
    value: function init() {
      // Check dependencies
      if (typeof jQuery === 'undefined') {
        console.error('jQuery is not loaded');
        return;
      }
      if (typeof aieData === 'undefined') {
        console.error('aieData is not defined. Make sure scripts are enqueued properly.');
      }
      this.initDragAndDrop();
      this.initFieldSearch();
      this.initCsvBuilderActions();
      this.initFieldFunctionsModal();
      this.initColumnActions();
      this.initCategoryToggle();

      // Load available functions
      this.loadFunctions();

      // Initialize tooltip for next button
      this.toggleNextButton();

      // Don't load dynamic fields immediately
      // They will be loaded when step 3 becomes active
      // this.loadDynamicFields();
    }

    /**
     * Initialize Drag and Drop functionality
     */
  }, {
    key: "initDragAndDrop",
    value: function initDragAndDrop() {
      var _this = this;
      var dropzone = document.getElementById('aie-csv-dropzone');
      var columnsContainer = document.getElementById('aie-csv-columns');
      if (!dropzone || !columnsContainer) return;

      // Make field items draggable
      document.addEventListener('dragstart', function (e) {
        if (e.target.classList.contains('aie-field-item')) {
          _this.isDragging = true;
          document.body.classList.add('aie-dragging');
          e.target.classList.add('dragging');
          e.dataTransfer.effectAllowed = 'copy';
          e.dataTransfer.setData('text/plain', JSON.stringify({
            field: e.target.dataset.field,
            label: e.target.dataset.label,
            type: e.target.dataset.type
          }));
        }

        // Handle column reordering
        if (e.target.classList.contains('aie-csv-column')) {
          _this.isDragging = true;
          document.body.classList.add('aie-dragging');
          e.target.classList.add('dragging');
          e.dataTransfer.effectAllowed = 'move';
          e.dataTransfer.setData('application/column-reorder', e.target.dataset.fieldKey);
        }
      });
      document.addEventListener('dragover', function (e) {
        if (_this.isDragging) {
          _this.handleAutoScroll(e);
        }
      });
      document.addEventListener('dragend', function (e) {
        if (e.target.classList.contains('aie-field-item') || e.target.classList.contains('aie-csv-column')) {
          _this.isDragging = false;
          document.body.classList.remove('aie-dragging');
          e.target.classList.remove('dragging');
          _this.stopAutoScroll();
        }
      });

      // Drop zone events
      dropzone.addEventListener('dragover', function (e) {
        // Only prevent default if we're actually dragging over the dropzone
        // This allows scrolling to continue
        if (e.dataTransfer.types.includes('text/plain') || e.dataTransfer.types.includes('application/column-reorder')) {
          e.preventDefault();
          e.dataTransfer.dropEffect = 'copy';
          dropzone.classList.add('aie-drag-over');
        }
      });
      dropzone.addEventListener('dragleave', function (e) {
        if (e.target === dropzone) {
          dropzone.classList.remove('aie-drag-over');
        }
      });
      dropzone.addEventListener('drop', function (e) {
        // Only prevent default for actual drop events
        var data = e.dataTransfer.getData('text/plain');
        if (data) {
          e.preventDefault();
          e.stopPropagation();
          dropzone.classList.remove('aie-drag-over');
          try {
            var fieldData = JSON.parse(data);
            _this.addFieldToCSV(fieldData);
          } catch (error) {
            console.error('Error adding field:', error);
          }
        }
      });

      // Column reordering
      columnsContainer.addEventListener('dragover', function (e) {
        var dragging = document.querySelector('.aie-csv-column.dragging');

        // Only prevent default when actually reordering columns
        if (dragging) {
          e.preventDefault();
          var afterElement = _this.getDragAfterElement(columnsContainer, e.clientX);
          if (afterElement == null) {
            columnsContainer.appendChild(dragging);
          } else {
            columnsContainer.insertBefore(dragging, afterElement);
          }
        }
      });
      columnsContainer.addEventListener('drop', function (e) {
        var dragging = document.querySelector('.aie-csv-column.dragging');
        if (dragging) {
          e.preventDefault();
          e.stopPropagation();
          _this.updateColumnOrder();
        }
      });
    }

    /**
     * Get element after drag position
     */
  }, {
    key: "getDragAfterElement",
    value: function getDragAfterElement(container, x) {
      var draggableElements = _toConsumableArray(container.querySelectorAll('.aie-csv-column:not(.dragging)'));
      return draggableElements.reduce(function (closest, child) {
        var box = child.getBoundingClientRect();
        var offset = x - box.left - box.width / 2;
        if (offset < 0 && offset > closest.offset) {
          return {
            offset: offset,
            element: child
          };
        } else {
          return closest;
        }
      }, {
        offset: Number.NEGATIVE_INFINITY
      }).element;
    }

    /**
     * Add field to CSV structure
     */
  }, {
    key: "addFieldToCSV",
    value: function addFieldToCSV(fieldData) {
      var field = fieldData.field,
        label = fieldData.label,
        type = fieldData.type;
      var fieldKey = "".concat(field, "_").concat(Date.now());

      // Check if field already exists (prevent duplicates for unique fields)
      var existingField = this.selectedFields.find(function (f) {
        return f.field === field;
      });
      if (existingField && field === 'ID') {
        this.showNotice(window.aieData.i18n.fieldAlreadyAdded, 'warning');
        return;
      }

      // Add to selected fields
      this.selectedFields.push({
        key: fieldKey,
        field: field,
        label: label,
        type: type
      });

      // Render column
      this.renderColumn(fieldKey, field, label, type);

      // Update UI
      this.updateCSVStats();
      this.toggleNextButton();
      this.togglePlaceholder();
    }

    /**
     * Render CSV column
     */
  }, {
    key: "renderColumn",
    value: function renderColumn(fieldKey, field, label, type) {
      var columnsContainer = document.getElementById('aie-csv-columns');
      if (!columnsContainer) return;
      var column = document.createElement('div');
      column.className = 'aie-csv-column';
      column.draggable = true;
      column.dataset.fieldKey = fieldKey;
      column.dataset.field = field;
      var iconClass = this.getFieldIcon(type);
      var hasFunctions = this.fieldFunctions[fieldKey] && this.fieldFunctions[fieldKey].length > 0;
      column.innerHTML = "\n\t\t\t<div class=\"aie-column-header\">\n\t\t\t\t<span class=\"aie-column-icon dashicons ".concat(iconClass, "\"></span>\n\t\t\t\t<div class=\"aie-column-actions\">\n\t\t\t\t\t<button type=\"button\" class=\"aie-edit-column-functions\" title=\"Assign functions\" data-field-key=\"").concat(fieldKey, "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t\t<button type=\"button\" class=\"aie-remove-column\" title=\"Remove\" data-field-key=\"").concat(fieldKey, "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t\t<div class=\"aie-column-label\">").concat(this.escapeHtml(label), "</div>\n\t\t\t<div class=\"aie-column-field\">").concat(this.escapeHtml(field), "</div>\n\t\t\t").concat(hasFunctions ? "\n\t\t\t\t<div class=\"aie-column-badge\">\n\t\t\t\t\t<span class=\"dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\t".concat(this.fieldFunctions[fieldKey].length, " function(s)\n\t\t\t\t</div>\n\t\t\t") : '', "\n\t\t");
      if (hasFunctions) {
        column.classList.add('has-functions');
      }
      columnsContainer.appendChild(column);
    }

    /**
     * Initialize CSV builder actions
     */
  }, {
    key: "initCsvBuilderActions",
    value: function initCsvBuilderActions() {
      var _document$querySelect,
        _this2 = this,
        _document$querySelect2;
      // Clear all fields
      (_document$querySelect = document.querySelector('.aie-clear-all-fields')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.addEventListener('click', function () {
        if (confirm(window.aieData.i18n.confirmRemoveAllFields)) {
          _this2.clearAllFields();
        }
      });

      // Add custom column
      (_document$querySelect2 = document.querySelector('.aie-add-custom-column')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.addEventListener('click', function () {
        _this2.addCustomColumn();
      });
    }

    /**
     * Initialize column actions
     */
  }, {
    key: "initColumnActions",
    value: function initColumnActions() {
      var _this3 = this;
      document.addEventListener('click', function (e) {
        // Remove column
        if (e.target.closest('.aie-remove-column')) {
          var btn = e.target.closest('.aie-remove-column');
          var fieldKey = btn.dataset.fieldKey;
          _this3.removeColumn(fieldKey);
        }

        // Edit column functions
        if (e.target.closest('.aie-edit-column-functions')) {
          var _btn = e.target.closest('.aie-edit-column-functions');
          var _fieldKey = _btn.dataset.fieldKey;
          _this3.openFieldFunctionsModal(_fieldKey);
        }
      });
    }

    /**
     * Remove column
     */
  }, {
    key: "removeColumn",
    value: function removeColumn(fieldKey) {
      // Remove from array
      this.selectedFields = this.selectedFields.filter(function (f) {
        return f.key !== fieldKey;
      });

      // Remove from DOM
      var column = document.querySelector("[data-field-key=\"".concat(fieldKey, "\"]"));
      if (column) {
        column.remove();
      }

      // Remove functions
      delete this.fieldFunctions[fieldKey];

      // Update UI
      this.updateCSVStats();
      this.toggleNextButton();
      this.togglePlaceholder();
    }

    /**
     * Clear all fields
     */
  }, {
    key: "clearAllFields",
    value: function clearAllFields() {
      this.selectedFields = [];
      this.fieldFunctions = {};
      var columnsContainer = document.getElementById('aie-csv-columns');
      if (columnsContainer) {
        columnsContainer.innerHTML = '';
      }
      this.updateCSVStats();
      this.toggleNextButton();
      this.togglePlaceholder();
    }

    /**
     * Add custom column
     */
  }, {
    key: "addCustomColumn",
    value: function addCustomColumn() {
      var label = prompt('Enter column name:');
      if (!label) return;
      var field = 'custom_' + label.toLowerCase().replace(/[^a-z0-9]/g, '_');
      this.addFieldToCSV({
        field: field,
        label: label,
        type: 'custom'
      });
    }

    /**
     * Update column order after drag
     */
  }, {
    key: "updateColumnOrder",
    value: function updateColumnOrder() {
      var _this4 = this;
      var columns = document.querySelectorAll('.aie-csv-column');
      var newOrder = [];
      columns.forEach(function (column) {
        var fieldKey = column.dataset.fieldKey;
        var field = _this4.selectedFields.find(function (f) {
          return f.key === fieldKey;
        });
        if (field) {
          newOrder.push(field);
        }
      });
      this.selectedFields = newOrder;
    }

    /**
     * Update CSV stats
     */
  }, {
    key: "updateCSVStats",
    value: function updateCSVStats() {
      var countElement = document.querySelector('.aie-step-3 .aie-columns-count');
      if (countElement) {
        console.log('Updating CSV stats. Selected fields:', this.selectedFields.length, this.selectedFields);
        countElement.textContent = this.selectedFields.length;
      }
    }

    /**
     * Toggle placeholder visibility
     */
  }, {
    key: "togglePlaceholder",
    value: function togglePlaceholder() {
      var dropzone = document.getElementById('aie-csv-dropzone');
      if (dropzone) {
        if (this.selectedFields.length > 0) {
          dropzone.classList.add('has-columns');
        } else {
          dropzone.classList.remove('has-columns');
        }
      }
    }

    /**
     * Toggle next button
     */
  }, {
    key: "toggleNextButton",
    value: function toggleNextButton() {
      var _this5 = this;
      var nextBtn = document.querySelector('.aie-step-3 .aie-next-step');
      if (nextBtn) {
        var $nextBtn = jQuery(nextBtn);
        var isDisabled = this.selectedFields.length === 0;

        // Remove previous event handlers
        $nextBtn.off('mouseenter.tooltip mouseleave.tooltip');
        if (isDisabled) {
          nextBtn.disabled = true;

          // Show tooltip on hover
          $nextBtn.on('mouseenter.tooltip', function () {
            _this5.showNextButtonTooltip($nextBtn);
          });

          // Hide tooltip on mouse leave
          $nextBtn.on('mouseleave.tooltip', function () {
            _this5.hideNextButtonTooltip($nextBtn);
          });
        } else {
          nextBtn.disabled = false;
          // Hide tooltip if it's shown
          this.hideNextButtonTooltip($nextBtn);
        }
      }
    }

    /**
     * Show custom tooltip on Next button
     */
  }, {
    key: "showNextButtonTooltip",
    value: function showNextButtonTooltip($button) {
      // Remove any existing tooltips
      jQuery('.aie-custom-tooltip').remove();

      // Create tooltip element
      var $tooltip = jQuery('<div>').addClass('aie-custom-tooltip aie-custom-pointer').html("\n\t\t\t\t<div class=\"aie-pointer-icon\">\n\t\t\t\t\t<span class=\"dashicons dashicons-warning\"></span>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-pointer-content\">\n\t\t\t\t\t<h3>No Fields Selected</h3>\n\t\t\t\t\t<p>Please select at least one field to continue with the export.</p>\n\t\t\t\t</div>\n\t\t\t");

      // Append to body
      jQuery('body').append($tooltip);

      // Position tooltip
      var buttonOffset = $button.offset();
      var buttonWidth = $button.outerWidth();
      var tooltipWidth = $tooltip.outerWidth();
      var tooltipHeight = $tooltip.outerHeight();

      // Position above the button, centered
      var left = buttonOffset.left + buttonWidth / 2 - tooltipWidth / 2;
      var top = buttonOffset.top - tooltipHeight - 10; // 10px gap

      $tooltip.css({
        left: left + 'px',
        top: top + 'px',
        zIndex: 9999
      });

      // Fade in
      setTimeout(function () {
        $tooltip.addClass('aie-tooltip-visible');
      }, 10);
    }

    /**
     * Hide custom tooltip
     */
  }, {
    key: "hideNextButtonTooltip",
    value: function hideNextButtonTooltip($button) {
      var $tooltip = jQuery('.aie-custom-tooltip');
      if ($tooltip.length) {
        $tooltip.removeClass('aie-tooltip-visible');

        // Remove after animation
        setTimeout(function () {
          $tooltip.remove();
        }, 200);
      }
    }

    /**
     * Initialize field search
     */
  }, {
    key: "initFieldSearch",
    value: function initFieldSearch() {
      var _this6 = this;
      var searchInput = document.getElementById('aie-fields-search');
      if (!searchInput) return;
      searchInput.addEventListener('input', function (e) {
        var query = e.target.value.toLowerCase();
        _this6.filterFields(query);
      });
    }

    /**
     * Initialize category toggle (collapse/expand)
     */
  }, {
    key: "initCategoryToggle",
    value: function initCategoryToggle() {
      var _this7 = this;
      document.addEventListener('click', function (e) {
        // Handle "Add all" button
        if (e.target.classList.contains('aie-add-all-fields')) {
          e.stopPropagation();
          _this7.addAllFieldsFromCategory(e.target);
          return;
        }

        // Handle category toggle (only if not clicking the button)
        var categoryTitle = e.target.closest('.aie-field-category-title');
        if (!categoryTitle) return;

        // Don't toggle if clicking the "Add all" button
        if (e.target.classList.contains('aie-add-all-fields')) return;
        var category = categoryTitle.closest('.aie-field-category');
        if (category) {
          category.classList.toggle('aie-collapsed');
        }
      });
    }

    /**
     * Add all fields from a category
     */
  }, {
    key: "addAllFieldsFromCategory",
    value: function addAllFieldsFromCategory(button) {
      var _this8 = this;
      var category = button.closest('.aie-field-category');
      if (!category) return;
      var fieldItems = category.querySelectorAll('.aie-field-item:not([style*="display: none"])');
      fieldItems.forEach(function (item) {
        var fieldData = {
          field: item.dataset.field,
          label: item.dataset.label,
          type: item.dataset.type
        };

        // Check if field is not already added
        var exists = _this8.selectedFields.find(function (f) {
          return f.field === fieldData.field;
        });
        if (!exists) {
          _this8.addFieldToCSV(fieldData);
        }
      });
    }

    /**
     * Filter fields by search query
     */
  }, {
    key: "filterFields",
    value: function filterFields(query) {
      var fieldItems = document.querySelectorAll('.aie-field-item');
      var categories = document.querySelectorAll('.aie-field-category');

      // If searching, expand all categories
      if (query.trim() !== '') {
        categories.forEach(function (category) {
          category.classList.remove('aie-collapsed');
        });
      }
      fieldItems.forEach(function (item) {
        var label = item.dataset.label.toLowerCase();
        var field = item.dataset.field.toLowerCase();
        if (label.includes(query) || field.includes(query)) {
          item.style.display = '';
        } else {
          item.style.display = 'none';
        }
      });

      // Hide empty categories when searching
      if (query.trim() !== '') {
        categories.forEach(function (category) {
          var visibleFields = category.querySelectorAll('.aie-field-item:not([style*="display: none"])');
          if (visibleFields.length === 0) {
            category.style.display = 'none';
          } else {
            category.style.display = '';
          }
        });
      } else {
        // Show all categories when not searching
        categories.forEach(function (category) {
          category.style.display = '';
        });
      }
    }

    /**
     * Load fields for a specific group
     */
  }, {
    key: "loadGroupFields",
    value: function loadGroupFields(group) {
      var _this9 = this;
      if (group === 'wordpress') {
        // Already loaded in HTML
        return;
      }
      var content = document.querySelector("[data-group=\"".concat(group, "\"]"));
      if (!content) return;

      // Check if already loaded
      if (content.dataset.loaded === 'true') return;

      // Check if aieData is available
      if (typeof aieData === 'undefined') {
        console.error('aieData is not defined');
        return;
      }

      // Make AJAX request to load fields
      jQuery.ajax({
        url: aieData.ajaxUrl,
        method: 'POST',
        data: {
          action: 'aie_get_field_group',
          nonce: aieData.nonce,
          group: group,
          content_type: this.getCurrentContentType()
        },
        success: function success(response) {
          if (response.success && response.data.fields) {
            _this9.renderGroupFields(content, response.data.fields);
            content.dataset.loaded = 'true';
          }
        }
      });
    }

    /**
     * Render fields for a group
     */
  }, {
    key: "renderGroupFields",
    value: function renderGroupFields(container, fields) {
      var _this10 = this;
      var loadingEl = container.querySelector('.aie-acf-loading, .aie-yoast-loading, .aie-meta-loading');
      if (loadingEl) {
        loadingEl.remove();
      }
      var category = document.createElement('div');
      category.className = 'aie-field-category';
      var grid = document.createElement('div');
      grid.className = 'aie-fields-grid';
      fields.forEach(function (field) {
        var item = document.createElement('div');
        item.className = 'aie-field-item';
        item.draggable = true;
        item.dataset.field = field.key;
        item.dataset.label = field.label;
        item.dataset.type = field.type || 'text';
        var iconClass = _this10.getFieldIcon(field.type);
        item.innerHTML = "\n\t\t\t\t<span class=\"aie-field-icon dashicons ".concat(iconClass, "\"></span>\n\t\t\t\t<span class=\"aie-field-label\">").concat(_this10.escapeHtml(field.label), "</span>\n\t\t\t\t<span class=\"aie-field-type\">").concat(_this10.escapeHtml(field.type), "</span>\n\t\t\t");
        grid.appendChild(item);
      });
      category.appendChild(grid);
      container.appendChild(category);
    }

    /**
     * Get current content type from step 1
     */
  }, {
    key: "getCurrentContentType",
    value: function getCurrentContentType() {
      var selectedType = document.querySelector('input[name="content_type"]:checked');
      if (!selectedType) return 'post';
      var contentType = selectedType.value;

      // For custom_post_types, get the specific post type from the selector
      if (contentType === 'custom_post_types') {
        var postTypeSelector = document.querySelector('.aie-post-type-selector');
        if (postTypeSelector && postTypeSelector.value) {
          return postTypeSelector.value;
        }
        // If no specific type selected yet, return a generic value
        return 'post';
      }
      return contentType;
    }

    /**
     * Load dynamic fields (Taxonomies, Custom Fields, ACF, Yoast)
     */
  }, {
    key: "loadDynamicFields",
    value: function loadDynamicFields() {
      console.log('loadDynamicFields called. Current selectedFields:', this.selectedFields.length);

      // Get selected post type from step 1
      this.selectedPostType = this.getCurrentContentType();
      var contentType = this.getCurrentRealContentType();

      // Load static fields based on content type
      this.loadStaticFields();

      // Load taxonomies for this post type
      this.loadTaxonomies();

      // Load custom fields for this post type
      this.loadCustomFields();

      // Check if ACF is active and load ACF fields
      this.checkAndLoadACF();

      // Check if Yoast is active and load Yoast fields (skip for non-content types)
      var excludedTypes = ['media', 'user', 'menu', 'block_theme_settings', 'taxonomy', 'database_table', 'woo_attribute', 'woo_coupon', 'woo_order'];
      if (!excludedTypes.includes(contentType)) {
        this.checkAndLoadYoast();
      }
    }

    /**
     * Reload dynamic fields (when post type changes)
     */
  }, {
    key: "reloadDynamicFields",
    value: function reloadDynamicFields() {
      console.log('reloadDynamicFields called - clearing and reloading...');

      // Hide and clear ALL dynamic categories (including static ones)
      var allCategories = document.querySelectorAll('.aie-field-category');
      allCategories.forEach(function (category) {
        // Skip custom fields, taxonomies, ACF, Yoast - they will be handled separately
        if (!category.classList.contains('aie-taxonomies-category') && !category.classList.contains('aie-custom-fields-category') && !category.classList.contains('aie-acf-fields-category') && !category.classList.contains('aie-yoast-fields-category')) {
          // This is a static category, hide and clear it
          category.style.display = 'none';
          var grid = category.querySelector('.aie-fields-grid');
          if (grid) grid.innerHTML = '';
        }
      });

      // Hide and clear dynamic categories
      var taxonomiesCategory = document.querySelector('.aie-taxonomies-category');
      var customFieldsCategory = document.querySelector('.aie-custom-fields-category');
      var acfCategory = document.querySelector('.aie-acf-fields-category');
      var yoastCategory = document.querySelector('.aie-yoast-fields-category');
      if (taxonomiesCategory) {
        taxonomiesCategory.style.display = 'none';
        var grid = taxonomiesCategory.querySelector('.aie-taxonomies-grid');
        if (grid) grid.innerHTML = '';
      }
      if (customFieldsCategory) {
        customFieldsCategory.style.display = 'none';
        var _grid = customFieldsCategory.querySelector('.aie-custom-fields-grid');
        if (_grid) _grid.innerHTML = '';
      }
      if (acfCategory) {
        acfCategory.style.display = 'none';
        var _grid2 = acfCategory.querySelector('.aie-acf-fields-grid');
        if (_grid2) {
          _grid2.innerHTML = '<div class="aie-acf-loading"><span class="spinner is-active"></span><p>Loading ACF fields...</p></div>';
        }
      }
      if (yoastCategory) {
        yoastCategory.style.display = 'none';
        var _grid3 = yoastCategory.querySelector('.aie-yoast-fields-grid');
        if (_grid3) {
          _grid3.innerHTML = '<div class="aie-yoast-loading"><span class="spinner is-active"></span><p>Loading Yoast SEO fields...</p></div>';
        }
      }

      // Reload fields
      this.loadDynamicFields();
    }

    /**
     * Load static fields based on content type
     */
  }, {
    key: "loadStaticFields",
    value: function loadStaticFields() {
      var _this11 = this;
      // Get field definitions from parent export module
      if (typeof window.aieExportModule === 'undefined' || !window.aieExportModule.getFieldsByContentType) {
        console.error('Export module not found or getFieldsByContentType method missing');
        return;
      }
      var contentType = this.getCurrentRealContentType();
      var fieldGroups = window.aieExportModule.getFieldsByContentType(contentType);
      console.log('Loading static fields for content type:', contentType, fieldGroups);

      // Find the container for static fields
      var container = document.querySelector('.aie-fields-library-body');
      if (!container) return;

      // Clear existing static categories (keep dynamic ones)
      var existingStatic = container.querySelectorAll('.aie-field-category:not(.aie-taxonomies-category):not(.aie-custom-fields-category):not(.aie-acf-fields-category):not(.aie-yoast-fields-category)');
      existingStatic.forEach(function (cat) {
        return cat.remove();
      });

      // Get reference to taxonomies category to insert before it
      var taxonomiesCategory = container.querySelector('.aie-taxonomies-category');

      // Render each field group as a category
      fieldGroups.forEach(function (group, index) {
        // Skip Custom Filters group and selector groups (they're only for step 2)
        if (group.label === 'Custom Filters' || group.label === 'Post Type Selection' || group.label === 'Taxonomy Selection') {
          return;
        }
        var category = _this11.createFieldCategory(group, index === 0);

        // Insert before taxonomies category
        if (taxonomiesCategory) {
          container.insertBefore(category, taxonomiesCategory);
        } else {
          container.appendChild(category);
        }
      });
    }

    /**
     * Create a field category element
     */
  }, {
    key: "createFieldCategory",
    value: function createFieldCategory(group) {
      var _this12 = this;
      var isOpen = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
      var category = document.createElement('div');
      category.className = 'aie-field-category' + (isOpen ? '' : ' aie-collapsed');
      var title = document.createElement('h4');
      title.className = 'aie-field-category-title';
      title.innerHTML = "\n\t\t\t<span class=\"dashicons dashicons-arrow-down-alt2 aie-category-toggle\"></span>\n\t\t\t<span class=\"dashicons dashicons-admin-post\"></span>\n\t\t\t".concat(this.escapeHtml(group.label), "\n\t\t\t<button type=\"button\" class=\"aie-add-all-fields\" title=\"Add all fields from this category\">\n\t\t\t\tAdd all\n\t\t\t</button>\n\t\t");
      var grid = document.createElement('div');
      grid.className = 'aie-fields-grid';

      // Add fields
      if (group.options && Array.isArray(group.options)) {
        group.options.forEach(function (option) {
          // Skip special filter types
          if (option.type === 'custom_field' || option.type === 'taxonomy_filter' || option.type === 'post_type_selector' || option.type === 'taxonomy_selector' || option.type === 'table_selector') {
            return;
          }
          var field = _this12.createFieldItem(option);
          grid.appendChild(field);
        });
      }
      category.appendChild(title);
      category.appendChild(grid);
      return category;
    }

    /**
     * Create a field item element
     */
  }, {
    key: "createFieldItem",
    value: function createFieldItem(option) {
      var item = document.createElement('div');
      item.className = 'aie-field-item';
      item.draggable = true;
      item.dataset.field = option.value;
      item.dataset.label = option.label;
      item.dataset.type = option.type || 'text';
      var iconClass = this.getFieldIcon(option.type);
      item.innerHTML = "\n\t\t\t<span class=\"aie-field-icon dashicons ".concat(iconClass, "\"></span>\n\t\t\t<span class=\"aie-field-label\">").concat(this.escapeHtml(option.label), "</span>\n\t\t\t<span class=\"aie-field-type\">").concat(this.escapeHtml(option.type || 'text'), "</span>\n\t\t");
      return item;
    }

    /**
     * Get real content type (for custom_post_types returns the radio value, not selector value)
     */
  }, {
    key: "getCurrentRealContentType",
    value: function getCurrentRealContentType() {
      var selectedType = document.querySelector('input[name="content_type"]:checked');
      return selectedType ? selectedType.value : 'post';
    }

    /**
     * Load taxonomies for selected post type
     */
  }, {
    key: "loadTaxonomies",
    value: function loadTaxonomies() {
      var _this13 = this;
      if (typeof aieData === 'undefined') return;
      jQuery.ajax({
        url: aieData.ajaxUrl,
        method: 'POST',
        data: {
          action: 'aie_get_taxonomies',
          nonce: aieData.nonce,
          post_type: this.selectedPostType
        },
        success: function success(response) {
          console.log('Taxonomies response:', response);
          if (response.success && response.data.taxonomies && response.data.taxonomies.length > 0) {
            _this13.renderTaxonomies(response.data.taxonomies);
            // Show the category
            var category = document.querySelector('.aie-taxonomies-category');
            if (category) {
              category.style.display = '';
            }
          } else {
            // Hide the category if no taxonomies
            var _category = document.querySelector('.aie-taxonomies-category');
            if (_category) {
              _category.style.display = 'none';
            }
          }
        },
        error: function error(xhr, status, _error) {
          console.error('Taxonomies AJAX error:', _error, xhr.responseText);
        }
      });
    }

    /**
     * Render taxonomies
     */
  }, {
    key: "renderTaxonomies",
    value: function renderTaxonomies(taxonomies) {
      var _this14 = this;
      var grid = document.querySelector('.aie-taxonomies-grid');
      if (!grid) return;
      grid.innerHTML = '';
      taxonomies.forEach(function (taxonomy) {
        var item = document.createElement('div');
        item.className = 'aie-field-item';
        item.draggable = true;
        item.dataset.field = 'taxonomy_' + taxonomy.name;
        item.dataset.label = taxonomy.label;
        item.dataset.type = 'taxonomy';
        item.innerHTML = "\n\t\t\t\t<span class=\"aie-field-icon dashicons dashicons-category\"></span>\n\t\t\t\t<span class=\"aie-field-label\">".concat(_this14.escapeHtml(taxonomy.label), "</span>\n\t\t\t\t<span class=\"aie-field-type\">taxonomy</span>\n\t\t\t");
        grid.appendChild(item);
      });
    }

    /**
     * Load custom fields for selected post type
     */
  }, {
    key: "loadCustomFields",
    value: function loadCustomFields() {
      var _this15 = this;
      if (typeof aieData === 'undefined') return;
      jQuery.ajax({
        url: aieData.ajaxUrl,
        method: 'POST',
        data: {
          action: 'aie_get_custom_fields',
          nonce: aieData.nonce,
          post_type: this.selectedPostType
        },
        success: function success(response) {
          if (response.success && response.data.fields && response.data.fields.length > 0) {
            _this15.renderCustomFields(response.data.fields);
            // Show the category
            var category = document.querySelector('.aie-custom-fields-category');
            if (category) {
              category.style.display = '';
            }
          } else {
            // Hide the category if no custom fields
            var _category2 = document.querySelector('.aie-custom-fields-category');
            if (_category2) {
              _category2.style.display = 'none';
            }
          }
        }
      });
    }

    /**
     * Render custom fields
     */
  }, {
    key: "renderCustomFields",
    value: function renderCustomFields(fields) {
      var _this16 = this;
      var grid = document.querySelector('.aie-custom-fields-grid');
      if (!grid) return;
      grid.innerHTML = '';
      fields.forEach(function (field) {
        var item = document.createElement('div');
        item.className = 'aie-field-item';
        item.draggable = true;
        item.dataset.field = 'meta_' + field.name;
        item.dataset.label = field.name;
        item.dataset.type = 'meta';
        item.innerHTML = "\n\t\t\t\t<span class=\"aie-field-icon dashicons dashicons-admin-generic\"></span>\n\t\t\t\t<span class=\"aie-field-label\">".concat(_this16.escapeHtml(field.name), "</span>\n\t\t\t\t<span class=\"aie-field-type\">meta</span>\n\t\t\t");
        grid.appendChild(item);
      });
    }

    /**
     * Check if ACF is active and load ACF fields
     */
  }, {
    key: "checkAndLoadACF",
    value: function checkAndLoadACF() {
      var _this17 = this;
      if (typeof aieData === 'undefined') return;
      jQuery.ajax({
        url: aieData.ajaxUrl,
        method: 'POST',
        data: {
          action: 'aie_get_acf_fields',
          nonce: aieData.nonce,
          post_type: this.selectedPostType
        },
        success: function success(response) {
          if (response.success && response.data.fields && response.data.fields.length > 0) {
            _this17.renderACFFields(response.data.fields);
            // Show the ACF category
            var category = document.querySelector('.aie-acf-fields-category');
            if (category) {
              category.style.display = '';
            }
          } else {
            // Hide the category if no ACF fields
            var _category3 = document.querySelector('.aie-acf-fields-category');
            if (_category3) {
              _category3.style.display = 'none';
            }
          }
        }
      });
    }

    /**
     * Render ACF fields
     */
  }, {
    key: "renderACFFields",
    value: function renderACFFields(fields) {
      var _this18 = this;
      var grid = document.querySelector('.aie-acf-fields-grid');
      if (!grid) return;

      // Clear grid completely (removes loading spinner and any existing fields)
      grid.innerHTML = '';
      fields.forEach(function (field) {
        var item = document.createElement('div');
        item.className = 'aie-field-item';
        item.draggable = true;
        item.dataset.field = 'acf_' + field.name;
        item.dataset.label = field.label;
        item.dataset.type = 'acf';
        item.innerHTML = "\n\t\t\t\t<span class=\"aie-field-icon dashicons dashicons-admin-settings\"></span>\n\t\t\t\t<span class=\"aie-field-label\">".concat(_this18.escapeHtml(field.label), "</span>\n\t\t\t\t<span class=\"aie-field-type\">acf</span>\n\t\t\t");
        grid.appendChild(item);
      });
    }

    /**
     * Check if Yoast is active and load Yoast fields
     */
  }, {
    key: "checkAndLoadYoast",
    value: function checkAndLoadYoast() {
      var _this19 = this;
      if (typeof aieData === 'undefined') return;
      jQuery.ajax({
        url: aieData.ajaxUrl,
        method: 'POST',
        data: {
          action: 'aie_get_yoast_fields',
          nonce: aieData.nonce,
          post_type: this.selectedPostType
        },
        success: function success(response) {
          if (response.success && response.data.fields && response.data.fields.length > 0) {
            _this19.renderYoastFields(response.data.fields);
            // Show the Yoast category
            var category = document.querySelector('.aie-yoast-fields-category');
            if (category) {
              category.style.display = '';
            }
          } else {
            // Hide the category if no Yoast fields
            var _category4 = document.querySelector('.aie-yoast-fields-category');
            if (_category4) {
              _category4.style.display = 'none';
            }
          }
        }
      });
    }

    /**
     * Render Yoast fields
     */
  }, {
    key: "renderYoastFields",
    value: function renderYoastFields(fields) {
      var _this20 = this;
      var grid = document.querySelector('.aie-yoast-fields-grid');
      if (!grid) return;

      // Clear grid completely (removes loading spinner and any existing fields)
      grid.innerHTML = '';
      fields.forEach(function (field) {
        var item = document.createElement('div');
        item.className = 'aie-field-item';
        item.draggable = true;
        item.dataset.field = 'yoast_' + field.name;
        item.dataset.label = field.label;
        item.dataset.type = 'yoast';
        item.innerHTML = "\n\t\t\t\t<span class=\"aie-field-icon dashicons dashicons-chart-line\"></span>\n\t\t\t\t<span class=\"aie-field-label\">".concat(_this20.escapeHtml(field.label), "</span>\n\t\t\t\t<span class=\"aie-field-type\">yoast</span>\n\t\t\t");
        grid.appendChild(item);
      });
    }

    /**
     * Initialize Field Functions Modal
     */
  }, {
    key: "initFieldFunctionsModal",
    value: function initFieldFunctionsModal() {
      var _modal$querySelector,
        _this21 = this,
        _modal$querySelector2,
        _modal$querySelector3,
        _modal$querySelector4,
        _modal$querySelector5,
        _modal$querySelector6;
      var modal = document.getElementById('aie-field-functions-modal');
      if (!modal) return;

      // Close modal
      (_modal$querySelector = modal.querySelector('.aie-modal-close')) === null || _modal$querySelector === void 0 ? void 0 : _modal$querySelector.addEventListener('click', function () {
        _this21.closeFieldFunctionsModal();
      });
      (_modal$querySelector2 = modal.querySelector('.aie-modal-cancel')) === null || _modal$querySelector2 === void 0 ? void 0 : _modal$querySelector2.addEventListener('click', function () {
        _this21.closeFieldFunctionsModal();
      });

      // Save functions
      (_modal$querySelector3 = modal.querySelector('.aie-save-field-functions')) === null || _modal$querySelector3 === void 0 ? void 0 : _modal$querySelector3.addEventListener('click', function () {
        _this21.saveFieldFunctions();
      });

      // Test pipeline
      (_modal$querySelector4 = modal.querySelector('.aie-test-pipeline')) === null || _modal$querySelector4 === void 0 ? void 0 : _modal$querySelector4.addEventListener('click', function () {
        _this21.testFunctionPipeline();
      });

      // Functions search
      (_modal$querySelector5 = modal.querySelector('#aie-functions-search')) === null || _modal$querySelector5 === void 0 ? void 0 : _modal$querySelector5.addEventListener('input', function (e) {
        _this21.filterFunctions(e.target.value);
      });

      // Functions filter
      modal.querySelectorAll('input[name="functions-filter"]').forEach(function (radio) {
        radio.addEventListener('change', function (e) {
          _this21.filterFunctionsByCategory(e.target.value);
        });
      });

      // Create new function button
      (_modal$querySelector6 = modal.querySelector('.aie-create-new-function')) === null || _modal$querySelector6 === void 0 ? void 0 : _modal$querySelector6.addEventListener('click', function (e) {
        e.preventDefault();
        _this21.createNewFunction();
      });

      // Initialize sortable for function pipeline
      this.initFunctionPipelineSortable();
    }

    /**
     * Open field functions modal
     */
  }, {
    key: "openFieldFunctionsModal",
    value: function openFieldFunctionsModal(fieldKey) {
      var field = this.selectedFields.find(function (f) {
        return f.key === fieldKey;
      });
      if (!field) return;
      this.currentEditingField = fieldKey;
      var modal = document.getElementById('aie-field-functions-modal');
      if (!modal) return;

      // Set field info
      modal.querySelector('.aie-current-field-label').textContent = field.label;
      modal.querySelector('.aie-current-field-type').textContent = field.type;

      // Load current functions
      this.loadCurrentFunctions(fieldKey);

      // Load available functions
      this.renderAvailableFunctions();

      // Show modal
      modal.style.display = 'flex';
      document.body.classList.add('aie-modal-open');
    }

    /**
     * Close field functions modal
     */
  }, {
    key: "closeFieldFunctionsModal",
    value: function closeFieldFunctionsModal() {
      var modal = document.getElementById('aie-field-functions-modal');
      if (modal) {
        modal.style.display = 'none';
        document.body.classList.remove('aie-modal-open');

        // Hide preview results
        var previewResult = modal.querySelector('#aie-preview-result');
        if (previewResult) {
          previewResult.style.display = 'none';
        }

        // Clear preview input
        var previewInput = modal.querySelector('#aie-preview-input');
        if (previewInput) {
          previewInput.value = '';
        }
      }
      this.currentEditingField = null;
    }

    /**
     * Load current functions for field
     */
  }, {
    key: "loadCurrentFunctions",
    value: function loadCurrentFunctions(fieldKey) {
      var _this22 = this;
      var container = document.getElementById('aie-function-items');
      if (!container) return;
      container.innerHTML = '';
      var functions = this.fieldFunctions[fieldKey] || [];
      var noFunctionsEl = document.querySelector('.aie-no-functions');
      if (functions.length === 0) {
        if (noFunctionsEl) noFunctionsEl.style.display = 'block';
        this.updateFunctionsCount(0);
        return;
      }
      if (noFunctionsEl) noFunctionsEl.style.display = 'none';
      functions.forEach(function (funcId) {
        var func = _this22.availableFunctions.find(function (f) {
          return f.id == funcId;
        });
        if (func) {
          _this22.addFunctionToPipeline(func, false);
        }
      });
      this.updateFunctionsCount(functions.length);
    }

    /**
     * Add function to pipeline
     */
  }, {
    key: "addFunctionToPipeline",
    value: function addFunctionToPipeline(func) {
      var _this23 = this;
      var updateArray = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : true;
      var container = document.getElementById('aie-function-items');
      if (!container) return;
      var item = document.createElement('div');
      item.className = 'aie-function-item';
      item.dataset.functionId = func.id;
      item.innerHTML = "\n\t\t\t<span class=\"aie-function-handle dashicons dashicons-menu\"></span>\n\t\t\t<div class=\"aie-function-info\">\n\t\t\t\t<strong class=\"aie-function-name\">".concat(this.escapeHtml(func.name), "</strong>\n\t\t\t\t<span class=\"aie-function-desc\">").concat(this.escapeHtml(func.description || ''), "</span>\n\t\t\t</div>\n\t\t\t<div class=\"aie-function-actions\">\n\t\t\t\t<button type=\"button\" class=\"button-small aie-remove-function\" data-function-id=\"").concat(func.id, "\">\n\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t</button>\n\t\t\t</div>\n\t\t");

      // Remove function event
      item.querySelector('.aie-remove-function').addEventListener('click', function () {
        item.remove();
        _this23.updatePipelineFunctions();
        _this23.updateFunctionsCount();
        _this23.toggleNoFunctionsMessage();
      });
      container.appendChild(item);
      if (updateArray) {
        this.updatePipelineFunctions();
        this.updateFunctionsCount();
      }
      this.toggleNoFunctionsMessage();
    }

    /**
     * Update pipeline functions array
     */
  }, {
    key: "updatePipelineFunctions",
    value: function updatePipelineFunctions() {
      if (!this.currentEditingField) return;
      var items = document.querySelectorAll('.aie-function-item');
      var functionIds = Array.from(items).map(function (item) {
        return item.dataset.functionId;
      });
      this.fieldFunctions[this.currentEditingField] = functionIds;
    }

    /**
     * Update functions count
     */
  }, {
    key: "updateFunctionsCount",
    value: function updateFunctionsCount() {
      var count = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : null;
      var countEl = document.querySelector('.aie-functions-count');
      if (!countEl) return;
      if (count === null) {
        var items = document.querySelectorAll('.aie-function-item');
        count = items.length;
      }
      countEl.textContent = "(".concat(count, ")");
    }

    /**
     * Toggle no functions message
     */
  }, {
    key: "toggleNoFunctionsMessage",
    value: function toggleNoFunctionsMessage() {
      var noFunctionsEl = document.querySelector('.aie-no-functions');
      var items = document.querySelectorAll('.aie-function-item');
      if (noFunctionsEl) {
        noFunctionsEl.style.display = items.length === 0 ? 'block' : 'none';
      }
    }

    /**
     * Initialize function pipeline sortable
     */
  }, {
    key: "initFunctionPipelineSortable",
    value: function initFunctionPipelineSortable() {
      var _this24 = this;
      var container = document.getElementById('aie-function-items');
      if (!container || !jQuery.fn.sortable) return;
      jQuery(container).sortable({
        handle: '.aie-function-handle',
        placeholder: 'aie-function-item-placeholder',
        update: function update() {
          _this24.updatePipelineFunctions();
        }
      });
    }

    /**
     * Save field functions
     */
  }, {
    key: "saveFieldFunctions",
    value: function saveFieldFunctions() {
      this.updatePipelineFunctions();

      // Update column badge
      var column = document.querySelector("[data-field-key=\"".concat(this.currentEditingField, "\"]"));
      if (column) {
        var functions = this.fieldFunctions[this.currentEditingField] || [];
        var hasFunctions = functions.length > 0;
        if (hasFunctions) {
          column.classList.add('has-functions');
          var badge = column.querySelector('.aie-column-badge');
          if (!badge) {
            badge = document.createElement('div');
            badge.className = 'aie-column-badge';
            column.appendChild(badge);
          }
          badge.innerHTML = "\n\t\t\t\t\t<span class=\"dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\t".concat(functions.length, " function(s)\n\t\t\t\t");
        } else {
          column.classList.remove('has-functions');
          var _badge = column.querySelector('.aie-column-badge');
          if (_badge) _badge.remove();
        }
      }
      this.closeFieldFunctionsModal();
      this.showNotice(window.aieData.i18n.functionsSavedSuccess, 'success');
    }

    /**
     * Load available functions
     */
  }, {
    key: "loadFunctions",
    value: function loadFunctions() {
      var _this25 = this;
      // Check if aieData is available
      if (typeof aieData === 'undefined') {
        console.error('aieData is not defined');
        return;
      }
      jQuery.ajax({
        url: aieData.ajaxUrl,
        method: 'POST',
        data: {
          action: 'aie_get_functions',
          nonce: aieData.nonce
        },
        success: function success(response) {
          if (response.success && response.data.functions) {
            _this25.availableFunctions = response.data.functions;
            _this25.renderAvailableFunctions();
          }
        }
      });
    }

    /**
     * Render available functions
     */
  }, {
    key: "renderAvailableFunctions",
    value: function renderAvailableFunctions() {
      var _this26 = this;
      var container = document.getElementById('aie-functions-list');
      if (!container) return;
      var loadingEl = container.querySelector('.aie-functions-loading');
      if (loadingEl) loadingEl.remove();
      container.innerHTML = '';
      if (this.availableFunctions.length === 0) {
        // Show empty state
        var emptyState = document.createElement('div');
        emptyState.className = 'aie-functions-empty-state';
        emptyState.innerHTML = "\n\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t<p>".concat(this.escapeHtml('No functions available yet.'), "</p>\n\t\t\t\t<p>").concat(this.escapeHtml('Create your first custom function to get started.'), "</p>\n\t\t\t");
        container.appendChild(emptyState);
        return;
      }
      this.availableFunctions.forEach(function (func) {
        var item = document.createElement('div');
        item.className = 'aie-function-list-item';
        item.dataset.functionId = func.id;
        item.dataset.category = func.category || 'custom';
        item.innerHTML = "\n\t\t\t\t<div class=\"aie-function-list-info\">\n\t\t\t\t\t<span class=\"aie-function-list-name\">".concat(_this26.escapeHtml(func.name), "</span>\n\t\t\t\t\t<span class=\"aie-function-list-desc\">").concat(_this26.escapeHtml(func.description || ''), "</span>\n\t\t\t\t</div>\n\t\t\t\t<button type=\"button\" class=\"button button-small\">Add</button>\n\t\t\t");
        item.querySelector('button').addEventListener('click', function () {
          _this26.addFunctionToPipeline(func);
        });
        container.appendChild(item);
      });
    }

    /**
     * Filter functions by search query
     */
  }, {
    key: "filterFunctions",
    value: function filterFunctions(query) {
      var items = document.querySelectorAll('.aie-function-list-item');
      var lowerQuery = query.toLowerCase();
      items.forEach(function (item) {
        var name = item.querySelector('.aie-function-list-name').textContent.toLowerCase();
        var desc = item.querySelector('.aie-function-list-desc').textContent.toLowerCase();
        if (name.includes(lowerQuery) || desc.includes(lowerQuery)) {
          item.style.display = '';
        } else {
          item.style.display = 'none';
        }
      });
    }

    /**
     * Filter functions by category
     */
  }, {
    key: "filterFunctionsByCategory",
    value: function filterFunctionsByCategory(category) {
      var items = document.querySelectorAll('.aie-function-list-item');
      var emptyState = document.querySelector('.aie-functions-empty-state');

      // Don't filter if only empty state is shown
      if (emptyState && items.length === 0) {
        return;
      }
      var visibleCount = 0;
      items.forEach(function (item) {
        if (category === 'all' || item.dataset.category === category) {
          item.style.display = '';
          visibleCount++;
        } else {
          item.style.display = 'none';
        }
      });

      // Show/hide no results message for filtered category
      this.toggleNoResultsMessage(visibleCount, category);
    }

    /**
     * Toggle no results message
     */
  }, {
    key: "toggleNoResultsMessage",
    value: function toggleNoResultsMessage(visibleCount, category) {
      var container = document.getElementById('aie-functions-list');
      if (!container) return;
      var noResults = container.querySelector('.aie-functions-no-results');
      if (visibleCount === 0 && category !== 'all') {
        if (!noResults) {
          noResults = document.createElement('div');
          noResults.className = 'aie-functions-no-results';
          container.appendChild(noResults);
        }
        var categoryLabel = category === 'library' ? 'library' : 'custom';
        noResults.innerHTML = "\n\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t<p>".concat(this.escapeHtml("No ".concat(categoryLabel, " functions found.")), "</p>\n\t\t\t");
        noResults.style.display = 'block';
      } else {
        if (noResults) {
          noResults.style.display = 'none';
        }
      }
    }

    /**
     * Test function pipeline
     */
  }, {
    key: "testFunctionPipeline",
    value: function testFunctionPipeline() {
      var _this27 = this;
      var input = document.getElementById('aie-preview-input').value;
      if (!input) {
        this.showNotice(window.aieData.i18n.enterTestValue, 'warning');
        return;
      }
      var functionIds = this.fieldFunctions[this.currentEditingField] || [];
      if (functionIds.length === 0) {
        this.showNotice(window.aieData.i18n.noFunctionsToTest, 'warning');
        return;
      }

      // Check if aieData is available
      if (typeof aieData === 'undefined') {
        console.error('aieData is not defined');
        this.showNotice(window.aieData.i18n.configErrorAieData, 'error');
        return;
      }

      // Make AJAX request to test pipeline
      jQuery.ajax({
        url: aieData.ajaxUrl,
        method: 'POST',
        data: {
          action: 'aie_test_function_pipeline',
          nonce: aieData.nonce,
          value: input,
          functions: functionIds
        },
        success: function success(response) {
          if (response.success) {
            _this27.renderPipelinePreview(input, response.data.steps);
          } else {
            _this27.showNotice(response.data.message || window.aieData.i18n.testFailed, 'error');
          }
        },
        error: function error() {
          _this27.showNotice(window.aieData.i18n.errorTestingPipeline, 'error');
        }
      });
    }

    /**
     * Render pipeline preview
     */
  }, {
    key: "renderPipelinePreview",
    value: function renderPipelinePreview(initialValue, steps) {
      var _this28 = this;
      var container = document.getElementById('aie-preview-result');
      if (!container) return;
      var stepsContainer = container.querySelector('.aie-preview-steps');
      stepsContainer.innerHTML = '';

      // Initial value
      stepsContainer.appendChild(this.createPreviewStep(0, 'Input', initialValue));

      // Each function step
      steps.forEach(function (step, index) {
        stepsContainer.appendChild(_this28.createPreviewStep(index + 1, step.function_name, step.output, step.error));
      });
      container.style.display = 'block';
    }

    /**
     * Create preview step element
     */
  }, {
    key: "createPreviewStep",
    value: function createPreviewStep(number, name, value) {
      var error = arguments.length > 3 && arguments[3] !== undefined ? arguments[3] : false;
      var step = document.createElement('div');
      step.className = 'aie-preview-step';
      step.innerHTML = "\n\t\t\t<div class=\"aie-preview-step-number\">".concat(number, "</div>\n\t\t\t<div class=\"aie-preview-step-name\">").concat(this.escapeHtml(name), "</div>\n\t\t\t<span class=\"aie-preview-step-arrow dashicons dashicons-arrow-right-alt\"></span>\n\t\t\t<div class=\"aie-preview-step-value ").concat(error ? 'error' : '', "\">\n\t\t\t\t").concat(this.escapeHtml(error ? "Error: ".concat(value) : value), "\n\t\t\t</div>\n\t\t");
      return step;
    }

    /**
     * Get field icon class
     */
  }, {
    key: "getFieldIcon",
    value: function getFieldIcon(type) {
      var icons = {
        post: 'dashicons-admin-post',
        text: 'dashicons-editor-textcolor',
        html: 'dashicons-editor-alignleft',
        number: 'dashicons-tag',
        date: 'dashicons-calendar',
        url: 'dashicons-admin-links',
        media: 'dashicons-format-image',
        taxonomy: 'dashicons-category',
        array: 'dashicons-list-view',
        custom: 'dashicons-admin-generic'
      };
      return icons[type] || 'dashicons-admin-generic';
    }

    /**
     * Escape HTML
     */
  }, {
    key: "escapeHtml",
    value: function escapeHtml(text) {
      var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      };
      return String(text).replace(/[&<>"']/g, function (m) {
        return map[m];
      });
    }

    /**
     * Start auto-scroll monitoring
     */
  }, {
    key: "startAutoScroll",
    value: function startAutoScroll() {
      this.stopAutoScroll();
    }

    /**
     * Stop auto-scroll
     */
  }, {
    key: "stopAutoScroll",
    value: function stopAutoScroll() {
      if (this.autoScrollInterval) {
        clearInterval(this.autoScrollInterval);
        this.autoScrollInterval = null;
      }
    }

    /**
     * Handle auto-scroll when dragging near edges
     */
  }, {
    key: "handleAutoScroll",
    value: function handleAutoScroll(e) {
      var scrollSpeed = 15; // Increased from 10
      var scrollZone = 150; // Increased from 100 - larger trigger zone
      var viewportHeight = window.innerHeight;
      var mouseY = e.clientY;

      // Auto-scroll when mouse is near edges
      if (mouseY < scrollZone) {
        // Scroll up when near top
        var intensity = 1 - mouseY / scrollZone;
        var scrollAmount = scrollSpeed * intensity;
        window.scrollBy(0, -scrollAmount);
      } else if (mouseY > viewportHeight - scrollZone) {
        // Scroll down when near bottom
        var _intensity = (mouseY - (viewportHeight - scrollZone)) / scrollZone;
        var _scrollAmount = scrollSpeed * _intensity;
        window.scrollBy(0, _scrollAmount);
      }
    }

    /**
     * Show notice
     */
  }, {
    key: "showNotice",
    value: function showNotice(message) {
      var type = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'info';
      // You can implement a toast notification system here
      console.log("[".concat(type.toUpperCase(), "] ").concat(message));
    }

    /**
     * Create new function
     */
  }, {
    key: "createNewFunction",
    value: function createNewFunction() {
      // Redirect to Functions management page
      if (typeof aieData !== 'undefined' && aieData.functionsUrl) {
        window.open(aieData.functionsUrl, '_blank');
      } else {
        // Fallback - go to admin page
        window.open('/wp-admin/admin.php?page=wp-aie-functions', '_blank');
      }
    }

    /**
     * Get selected fields data
     */
  }, {
    key: "getSelectedFieldsData",
    value: function getSelectedFieldsData() {
      return {
        fields: this.selectedFields,
        functions: this.fieldFunctions
      };
    }

    /**
     * Set selected fields (for loading saved state)
     */
  }, {
    key: "setSelectedFieldsData",
    value: function setSelectedFieldsData(data) {
      var _this29 = this;
      if (data.fields) {
        this.selectedFields = [];
        data.fields.forEach(function (field) {
          _this29.addFieldToCSV(field);
        });
      }
      if (data.functions) {
        this.fieldFunctions = data.functions;

        // Update column badges
        Object.keys(this.fieldFunctions).forEach(function (fieldKey) {
          var column = document.querySelector("[data-field-key=\"".concat(fieldKey, "\"]"));
          if (column && _this29.fieldFunctions[fieldKey].length > 0) {
            column.classList.add('has-functions');
          }
        });
      }
    }
  }]);
  return ExportStep3;
}();


/***/ }),

/***/ "./src/js/modules/export.js":
/*!**********************************!*\
  !*** ./src/js/modules/export.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/regenerator */ "./node_modules/@babel/runtime/regenerator/index.js");
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utils */ "./src/js/modules/utils.js");
/* harmony import */ var _export_step_3__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./export-step-3 */ "./src/js/modules/export-step-3.js");
var _ExportModule;
function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { Promise.resolve(value).then(_next, _throw); } }
function _asyncToGenerator(fn) { return function () { var self = this, args = arguments; return new Promise(function (resolve, reject) { var gen = fn.apply(self, args); function _next(value) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value); } function _throw(err) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err); } _next(undefined); }); }; }
/**
 * Export Module
 *
 * Handles the export wizard functionality
 */



var ExportModule = (_ExportModule = {
  currentStep: 1,
  totalSteps: 5,
  jobId: null,
  progressInterval: null,
  step3Instance: null,
  /**
   * Initialize module
   */
  init: function init() {
    var _this = this;
    if (!jQuery('#wp-aie-export').length) {
      return;
    }

    // Check if resuming a job BEFORE showing any step
    var urlParams = new URLSearchParams(window.location.search);
    var resumeJobId = urlParams.get('resume_job');
    this.bindEvents();
    if (resumeJobId) {
      // Resume job - go directly to step 5 and start processing
      this.jobId = parseInt(resumeJobId);

      // Show step 5 immediately (don't hide first, let showStep handle it)
      this.showStep(5);

      // Get initial progress first, then start tracking and processing
      this.updateProgress().then(function () {
        // Start progress tracking and batch processing
        _this.startProgressTracking();
        _this.processNextBatch();
      });
    } else {
      this.showStep(1);
    }

    // Initialize Step 3 drag and drop
    this.step3Instance = new _export_step_3__WEBPACK_IMPORTED_MODULE_2__["default"]();
  },
  /**
   * Bind event handlers
   */
  bindEvents: function bindEvents() {
    var _this2 = this;
    var $wizard = jQuery('#wp-aie-export');

    // Content type filter/search
    $wizard.on('input', '#aie-content-type-search', function (e) {
      return _this2.filterContentTypes(e);
    });

    // Step navigation
    $wizard.on('click', '.aie-next-step', function () {
      return _this2.nextStep();
    });
    $wizard.on('click', '.aie-prev-step', function () {
      return _this2.prevStep();
    });

    // Content type
    $wizard.on('change', 'input[name="content_type"]', function (e) {
      return _this2.onContentTypeChange(e);
    });

    // Filters
    $wizard.on('change', '.aie-export-filters input, .aie-export-filters select', _utils__WEBPACK_IMPORTED_MODULE_1__["default"].debounce(function () {
      return _this2.refreshCount(false);
    }, 500));
    $wizard.on('click', '.aie-step-2 .aie-refresh-count', function () {
      return _this2.refreshCount(true);
    });

    // Field selection
    $wizard.on('click', '.aie-select-all-fields', function () {
      return _this2.selectAllFields(true);
    });
    $wizard.on('click', '.aie-deselect-all-fields', function () {
      return _this2.selectAllFields(false);
    });
    $wizard.on('click', '.aie-select-common-fields', function () {
      return _this2.selectCommonFields();
    });

    // Format selection
    $wizard.on('change', 'input[name="format"]', function (e) {
      return _this2.onFormatChange(e);
    });

    // CSV delimiter change
    $wizard.on('change', 'select[name="csv_delimiter"]', function (e) {
      return _this2.onDelimiterChange(e);
    });

    // Export actions
    $wizard.on('click', '.aie-start-export', function () {
      return _this2.startExport();
    });
    $wizard.on('click', '.aie-cancel-export', function () {
      return _this2.cancelExport();
    });
    $wizard.on('click', '.aie-download-file', function () {
      return _this2.downloadFile();
    });
    $wizard.on('click', '.aie-new-export', function () {
      return _this2.newExport();
    });

    // Dynamic Filters
    $wizard.on('click', '.aie-add-filter', function () {
      return _this2.addFilterRow();
    });
    $wizard.on('click', '.aie-remove-filter', function (e) {
      return _this2.removeFilterRow(e);
    });
    $wizard.on('change', '.aie-filter-field', function (e) {
      return _this2.onFilterFieldChange(e);
    });

    // Dynamic filter value changes - auto refresh count when filter is complete
    $wizard.on('change', '.aie-filter-condition', function (e) {
      var $row = jQuery(e.target).closest('.aie-filter-row');
      var $value = $row.find('.aie-filter-value');

      // Clear the value when condition changes
      if ($value.length) {
        $value.val('');
      }

      // Update input type based on condition
      _this2.updateValueInputType($row);
      if (_this2.isFilterRowComplete($row)) {
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].debounce(function () {
          return _this2.refreshCount(false);
        }, 500)();
      }
    });
    $wizard.on('input', '.aie-filter-value', function (e) {
      var $row = jQuery(e.target).closest('.aie-filter-row');
      if (_this2.isFilterRowComplete($row)) {
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].debounce(function () {
          return _this2.refreshCount(false);
        }, 1000)();
      }
    });
    $wizard.on('change', '.aie-filter-value', function (e) {
      var $row = jQuery(e.target).closest('.aie-filter-row');
      if (_this2.isFilterRowComplete($row)) {
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].debounce(function () {
          return _this2.refreshCount(false);
        }, 500)();
      }
    });
  },
  /**
   * Show specific step
   */
  showStep: function showStep(step) {
    var $wizard = jQuery('#wp-aie-export');
    $wizard.find('.aie-step').removeClass('active');
    $wizard.find(".aie-step-".concat(step)).addClass('active');
    $wizard.find('.aie-step-indicator').removeClass('active completed');
    $wizard.find(".aie-step-indicator[data-step=\"".concat(step, "\"]")).addClass('active');
    $wizard.find(".aie-step-indicator[data-step]").filter(function () {
      return jQuery(this).data('step') < step;
    }).addClass('completed');
    this.currentStep = step;
    var previousStep = this.currentStep;
    if (step === 1) {
      // Hide database table selection and info when returning to step 1
      jQuery('.aie-table-selection-section').hide();
      jQuery('.aie-table-info').hide();
      // Reset count only when going back to step 1
      this.resetCount();
    } else if (step === 2) {
      // Check if database_table type is selected
      var contentType = jQuery('input[name="content_type"]:checked').val();
      if (contentType === 'database_table') {
        jQuery('.aie-table-selection-section').show();
        // Only load database tables if coming from step 1 or if table not selected
        var $tableSelect = jQuery('#aie-table-name');
        if (previousStep === 1 || !$tableSelect.val()) {
          this.loadDatabaseTables();
        }
      } else {
        jQuery('.aie-table-selection-section').hide();
      }
      this.refreshCount(false); // Don't show spinner on auto-refresh
    } else if (step === 3) {
      // Load dynamic fields when entering step 3
      if (this.step3Instance) {
        this.step3Instance.loadDynamicFields();
      }
    }
  },
  nextStep: function nextStep() {
    if (this.currentStep < this.totalSteps) {
      var nextStep = this.currentStep + 1;

      // Skip step 2 (filters) for content types that don't need filtering
      if (nextStep === 2 && this.shouldSkipFilters()) {
        nextStep = 3;
      }
      this.showStep(nextStep);
    }
  },
  prevStep: function prevStep() {
    // Clear step 3 fields when going back from step 3
    if (this.currentStep === 3 && this.step3Instance) {
      this.step3Instance.clearAllFields();
    }
    if (this.currentStep > 1) {
      var prevStep = this.currentStep - 1;

      // Skip step 2 (filters) when going back for content types that don't need filtering
      if (prevStep === 2 && this.shouldSkipFilters()) {
        prevStep = 1;
      }

      // Hide table selection when going back to step 1
      if (prevStep === 1) {
        jQuery('.aie-table-selection-section').hide();
        jQuery('.aie-table-info').hide();
      }
      this.showStep(prevStep);
    }
  },
  /**
   * Check if current content type should skip filters step
   */
  shouldSkipFilters: function shouldSkipFilters() {
    var contentType = jQuery('input[name="content_type"]:checked').val();

    // Content types that don't need filtering (go straight from step 1 to step 3)
    var noFilterTypes = ['block_theme_settings'];
    return noFilterTypes.includes(contentType);
  },
  /**
   * Handle content type change
   */
  onContentTypeChange: function onContentTypeChange(e) {
    var contentType = jQuery(e.target).val();

    // Clear existing filters
    jQuery('#aie-filters-list').empty();

    // Show/hide table selection section
    if (contentType === 'database_table') {
      jQuery('.aie-table-selection-section').show();
      jQuery('.aie-custom-filters-section').show();
    } else {
      jQuery('.aie-table-selection-section').hide();
    }

    // Show/hide custom filters section
    // Note: block_theme_settings is excluded - it doesn't need filters (goes straight to step 3)
    var filterableTypes = ['post', 'page', 'media', 'menu', 'user', 'comment', 'custom_post_types', 'taxonomy', 'woo_product', 'woo_order', 'woo_coupon', 'woo_attribute', 'database_table'];
    if (filterableTypes.includes(contentType)) {
      jQuery('.aie-custom-filters-section').show();
    } else {
      jQuery('.aie-custom-filters-section').hide();
    }

    // Update field groups visibility if needed
    if (contentType === 'media') {
      jQuery('.aie-post-field-group').hide();
      jQuery('.aie-media-field-group').show();
    } else {
      jQuery('.aie-post-field-group').show();
      jQuery('.aie-media-field-group').hide();
    }

    // Refresh count (without spinner)
    this.refreshCount(false);
  },
  /**
   * Filter content types based on search input
   */
  filterContentTypes: function filterContentTypes(e) {
    var searchTerm = jQuery(e.target).val().toLowerCase().trim();
    var $contentTypes = jQuery('.aie-content-type');
    var $filterCount = jQuery('.aie-filter-count');
    var $filterCountValue = jQuery('.aie-filter-count-value');
    var $noResults = jQuery('.aie-no-results');
    var visibleCount = 0;
    if (searchTerm === '') {
      // Show all if search is empty
      $contentTypes.show();
      $filterCount.hide();
      $noResults.hide();
      return;
    }

    // Filter content types
    $contentTypes.each(function () {
      var $this = jQuery(this);
      var title = $this.find('h3').text().toLowerCase();
      var description = $this.find('p').text().toLowerCase();

      // Check if search term matches title or description
      if (title.includes(searchTerm) || description.includes(searchTerm)) {
        $this.show();
        visibleCount++;
      } else {
        $this.hide();
      }
    });

    // Update and show count
    $filterCountValue.text(visibleCount);
    $filterCount.show();

    // Show/hide no results message
    if (visibleCount === 0) {
      $noResults.show();
    } else {
      $noResults.hide();
    }
  },
  /**
   * Refresh item count
   */
  refreshCount: function refreshCount() {
    var _arguments = arguments,
      _this3 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee() {
      var showSpinner, $count, $spinner, $refreshBtn, contentType, options, $tableDropdown, dynamicFiltersData, _dynamicFiltersData, postType, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee$(_context) {
        while (1) {
          switch (_context.prev = _context.next) {
            case 0:
              showSpinner = _arguments.length > 0 && _arguments[0] !== undefined ? _arguments[0] : true;
              $count = jQuery('.aie-step-2 .aie-count-value');
              $spinner = jQuery('.aie-step-2 .aie-item-count .spinner');
              $refreshBtn = jQuery('.aie-step-2 .aie-refresh-count');
              if (showSpinner) {
                $spinner.addClass('is-active');
              }
              $refreshBtn.addClass('is-refreshing');
              _context.prev = 6;
              contentType = jQuery('input[name="content_type"]:checked').val(); // Prepare options based on content type
              options = {};
              if (contentType === 'database_table') {
                // For database tables, get table name from dropdown
                $tableDropdown = jQuery('#aie-table-name');
                dynamicFiltersData = _this3.getDynamicFilters();
                options = {
                  table_name: $tableDropdown.val(),
                  filters: dynamicFiltersData.filters
                };
              } else {
                // For other types, use dynamic filters
                _dynamicFiltersData = _this3.getDynamicFilters();
                console.log('Dynamic filters data:', _dynamicFiltersData);

                // Map content type to post_type for post-based exporters
                postType = _this3.getPostTypeForContentType(contentType);
                if (postType) {
                  options.post_type = postType;
                }

                // Add dynamic filters as query parameters
                if (_dynamicFiltersData.filters.length > 0) {
                  options.filters = _dynamicFiltersData.filters;
                }

                // Add custom field filters
                if (_dynamicFiltersData.custom_fields.length > 0) {
                  options.custom_fields = _dynamicFiltersData.custom_fields;
                }

                // Add taxonomy filters
                if (_dynamicFiltersData.taxonomy.length > 0) {
                  options.taxonomy = _dynamicFiltersData.taxonomy;
                }
              }
              console.log('Sending options to backend:', options);
              _context.next = 13;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_export_get_count', {
                export_type: contentType,
                options: options
              });
            case 13:
              response = _context.sent;
              console.log('Received count response:', response);
              $count.text(response.count || 0);

              // Update next button state based on count
              _this3.updateStep2NextButton();
              _context.next = 24;
              break;
            case 19:
              _context.prev = 19;
              _context.t0 = _context["catch"](6);
              $count.text('-');
              console.error('Count error:', _context.t0);

              // Disable next button on error
              _this3.updateStep2NextButton();
            case 24:
              _context.prev = 24;
              $spinner.removeClass('is-active');
              $refreshBtn.removeClass('is-refreshing');
              return _context.finish(24);
            case 28:
            case "end":
              return _context.stop();
          }
        }
      }, _callee, null, [[6, 19, 24, 28]]);
    }))();
  },
  /**
   * Reset count display
   */
  resetCount: function resetCount() {
    var $count = jQuery('.aie-step-2 .aie-count-value');
    var $spinner = jQuery('.aie-step-2 .aie-item-count .spinner');
    var $refreshBtn = jQuery('.aie-step-2 .aie-refresh-count');
    $count.text('-');
    $spinner.removeClass('is-active');
    $refreshBtn.removeClass('is-refreshing');

    // Disable next button when count is reset
    this.updateStep2NextButton();
  },
  /**
   * Update step 2 next button state based on item count
   */
  updateStep2NextButton: function updateStep2NextButton() {
    var _this4 = this;
    var $nextBtn = jQuery('.aie-step-2 .aie-next-step');
    var $count = jQuery('.aie-step-2 .aie-count-value');
    var countText = $count.text();
    var count = parseInt(countText, 10);

    // Check content type for special validation
    var contentType = jQuery('input[name="content_type"]:checked').val();
    var isDisabled = false;
    var tooltipTitle = 'No Data Available';
    var tooltipMessage = 'Adjust your filters or select a different content type to continue with the export.';

    // Remove previous event handlers
    $nextBtn.off('mouseenter.tooltip mouseleave.tooltip');

    // For custom_post_types, check if post type is selected
    if (contentType === 'custom_post_types') {
      var $postTypeSelector = jQuery('.aie-post-type-selector');
      var selectedPostType = $postTypeSelector.val();
      if (!selectedPostType || selectedPostType.trim() === '') {
        isDisabled = true;
        tooltipTitle = 'Post Type Required';
        tooltipMessage = 'Please select a specific post type from the dropdown to continue.';
      }
    }

    // For taxonomy, check if taxonomy is selected
    if (contentType === 'taxonomy') {
      var $taxonomySelector = jQuery('.aie-taxonomy-selector');
      var selectedTaxonomy = $taxonomySelector.val();
      if (!selectedTaxonomy || selectedTaxonomy.trim() === '') {
        isDisabled = true;
        tooltipTitle = 'Taxonomy Required';
        tooltipMessage = 'Please select a specific taxonomy from the dropdown to continue.';
      }
    }

    // For database_table, check if table is selected
    if (contentType === 'database_table') {
      var $tableSelector = jQuery('#aie-table-name');
      var selectedTable = $tableSelector.val();
      if (!selectedTable || selectedTable.trim() === '') {
        isDisabled = true;
        tooltipTitle = 'Table Required';
        tooltipMessage = 'Please select a database table from the dropdown to continue.';
      }
    }

    // Disable if count is 0, NaN, or '-'
    if (!isDisabled && (countText === '-' || isNaN(count) || count === 0)) {
      isDisabled = true;
    }
    if (isDisabled) {
      $nextBtn.prop('disabled', true);

      // Store tooltip data
      $nextBtn.data('tooltip-title', tooltipTitle);
      $nextBtn.data('tooltip-message', tooltipMessage);

      // Show tooltip on hover
      $nextBtn.on('mouseenter.tooltip', function () {
        _this4.showNextButtonTooltip($nextBtn);
      });

      // Hide tooltip on mouse leave
      $nextBtn.on('mouseleave.tooltip', function () {
        _this4.hideNextButtonTooltip($nextBtn);
      });
    } else {
      $nextBtn.prop('disabled', false);

      // Hide tooltip if it's shown
      this.hideNextButtonTooltip($nextBtn);
    }
  },
  /**
   * Show custom tooltip on Next button
   */
  showNextButtonTooltip: function showNextButtonTooltip($button) {
    // Remove any existing tooltips
    jQuery('.aie-custom-tooltip').remove();

    // Get custom tooltip data or use defaults
    var tooltipTitle = $button.data('tooltip-title') || 'No Data Available';
    var tooltipMessage = $button.data('tooltip-message') || 'Adjust your filters or select a different content type to continue with the export.';

    // Create tooltip element
    var $tooltip = jQuery('<div>').addClass('aie-custom-tooltip aie-custom-pointer').html("\n\t\t\t\t<div class=\"aie-pointer-icon\">\n\t\t\t\t\t<span class=\"dashicons dashicons-warning\"></span>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-pointer-content\">\n\t\t\t\t\t<h3>".concat(tooltipTitle, "</h3>\n\t\t\t\t\t<p>").concat(tooltipMessage, "</p>\n\t\t\t\t</div>\n\t\t\t"));
    // Append to body
    jQuery('body').append($tooltip);
    // Position tooltip
    var buttonOffset = $button.offset();
    var buttonWidth = $button.outerWidth();
    var buttonHeight = $button.outerHeight();
    var tooltipWidth = $tooltip.outerWidth();
    var tooltipHeight = $tooltip.outerHeight();
    // Position above the button, centered
    var left = buttonOffset.left + buttonWidth / 2 - tooltipWidth / 2;
    var top = buttonOffset.top - tooltipHeight - 10; // 10px gap
    $tooltip.css({
      left: left + 'px',
      top: top + 'px',
      zIndex: 9999
    });
    // Fade in
    setTimeout(function () {
      $tooltip.addClass('aie-tooltip-visible');
    }, 10);
  },
  /**
   * Hide custom tooltip
   */
  hideNextButtonTooltip: function hideNextButtonTooltip($button) {
    var $tooltip = jQuery('.aie-custom-tooltip');
    if ($tooltip.length) {
      $tooltip.removeClass('aie-tooltip-visible');

      // Remove after animation
      setTimeout(function () {
        $tooltip.remove();
      }, 200);
    }
  },
  /**
   * Map content type to WordPress post_type
   */
  getPostTypeForContentType: function getPostTypeForContentType(contentType) {
    var postTypeMap = {
      post: 'post',
      page: 'page',
      media: 'attachment',
      menu: 'nav_menu_item',
      comment: null,
      // Comments are not post type
      user: null,
      // Users are not post type
      block_theme_settings: 'wp_template',
      taxonomy: null,
      // Taxonomies are not post type
      custom_post_types: null,
      // Will be determined dynamically
      woo_product: 'product',
      woo_order: 'shop_order',
      woo_coupon: 'shop_coupon',
      woo_attribute: null,
      // Attributes are taxonomy-based
      custom_table: null // Not a post type
    };
    return postTypeMap[contentType] || null;
  },
  /**
   * Get filter values
   */
  getFilters: function getFilters() {
    var filters = {};
    var contentType = jQuery('input[name="content_type"]:checked').val();
    if (contentType === 'post') {
      filters.post_type = jQuery('[name="post_type"]').val();
      filters.post_status = jQuery('[name="post_status[]"]').val() || [];
      filters.date_from = jQuery('[name="date_from"]').val();
      filters.date_to = jQuery('[name="date_to"]').val();
      filters.author = jQuery('[name="author"]').val();
      filters.category = jQuery('[name="category"]').val();
      filters.tag = jQuery('[name="tag"]').val();
      filters.search = jQuery('[name="search"]').val();
    } else if (contentType === 'media') {
      filters.mime_type = jQuery('[name="mime_type"]').val();
      filters.date_from = jQuery('[name="media_date_from"]').val();
      filters.date_to = jQuery('[name="media_date_to"]').val();
    }
    return filters;
  },
  /**
   * Get dynamic filters from filter rows
   */
  getDynamicFilters: function getDynamicFilters() {
    var filters = [];
    var customFields = [];
    var taxonomyFilters = [];
    jQuery('.aie-filter-row').each(function (index, row) {
      var $row = jQuery(row);
      var field = $row.find('.aie-filter-field').val();
      var fieldType = $row.find('.aie-filter-field option:selected').data('type');

      // Skip table selector for custom_table type
      if (fieldType === 'table_selector') {
        return;
      }

      // Handle post_type_selector type
      if (fieldType === 'post_type_selector') {
        var _value = $row.find('.aie-filter-value').val();
        if (_value && _value.trim() !== '') {
          filters.push({
            field: 'post_type',
            condition: 'equals',
            // Default condition for post type
            value: _value
          });
        }
        return;
      }

      // Handle taxonomy_selector type
      if (fieldType === 'taxonomy_selector') {
        var _value2 = $row.find('.aie-filter-value').val();
        if (_value2 && _value2.trim() !== '') {
          filters.push({
            field: 'taxonomy',
            condition: 'equals',
            // Default condition for taxonomy
            value: _value2
          });
        }
        return;
      }

      // Handle custom_field type
      if (fieldType === 'custom_field') {
        var name = $row.find('.aie-custom-field-name').val();
        var _condition = $row.find('.aie-custom-field-condition').val();
        var _value3 = $row.find('.aie-custom-field-value').val();
        if (name && _condition) {
          var _noValueConditions = ['is_empty', 'is_not_empty'];
          if (_noValueConditions.includes(_condition) || _value3 && _value3.trim() !== '') {
            customFields.push({
              name: name,
              condition: _condition,
              value: _value3 || ''
            });
          }
        }
        return;
      }

      // Handle taxonomy_filter type
      if (fieldType === 'taxonomy_filter') {
        var taxonomy = $row.find('.aie-taxonomy-name').val();
        var _condition2 = $row.find('.aie-taxonomy-condition').val();
        var terms = $row.find('.aie-taxonomy-terms').val();
        if (taxonomy && _condition2 && terms && terms.trim() !== '') {
          taxonomyFilters.push({
            taxonomy: taxonomy,
            condition: _condition2,
            terms: terms
          });
        }
        return;
      }

      // Handle regular filters
      var condition = $row.find('.aie-filter-condition').val();
      var value = $row.find('.aie-filter-value').val();

      // Skip empty or incomplete filters
      if (!field || !condition) {
        return;
      }

      // For conditions that don't need value
      var noValueConditions = ['is_empty', 'is_not_empty'];
      if (noValueConditions.includes(condition) || value && value.trim() !== '') {
        filters.push({
          field: field,
          condition: condition,
          value: value || ''
        });
      }
    });
    return {
      filters: filters,
      custom_fields: customFields,
      taxonomy: taxonomyFilters
    };
  },
  /**
   * Get custom table filters (deprecated - use getDynamicFilters instead)
   */
  getCustomTableFilters: function getCustomTableFilters() {
    var filters = [];
    jQuery('.aie-filter-row').each(function (index, row) {
      var $row = jQuery(row);
      var field = $row.find('.aie-filter-field').val();
      var condition = $row.find('.aie-filter-condition').val();
      var value = $row.find('.aie-filter-value').val();

      // Skip table selector row and empty filters
      if (!field || field === 'table_name' || !condition) {
        return;
      }
      filters.push({
        field: field,
        condition: condition,
        value: value
      });
    });
    return filters;
  },
  /**
   * Select/deselect all fields
   */
  selectAllFields: function selectAllFields(checked) {
    jQuery('input[name="fields[]"]:visible').prop('checked', checked);
  },
  /**
   * Select common fields only
   */
  selectCommonFields: function selectCommonFields() {
    this.selectAllFields(false);
    var commonFields = ['ID', 'post_title', 'post_content', 'post_status'];
    commonFields.forEach(function (field) {
      jQuery("input[name=\"fields[]\"][value=\"".concat(field, "\"]")).prop('checked', true);
    });
  },
  /**
   * Handle format change
   */
  onFormatChange: function onFormatChange(e) {
    var format = jQuery(e.target).val();
    jQuery('.aie-format-options > div').hide();
    jQuery(".aie-".concat(format, "-options")).show();
  },
  /**
   * Handle delimiter change
   */
  onDelimiterChange: function onDelimiterChange(e) {
    var delimiter = jQuery(e.target).val();
    if (delimiter === 'custom') {
      jQuery('.aie-custom-delimiter-row').show();
    } else {
      jQuery('.aie-custom-delimiter-row').hide();
    }
  },
  /**
   * Get selected fields
   */
  getSelectedFields: function getSelectedFields() {
    // Get fields from Step 3 drag & drop interface
    if (this.step3Instance && this.step3Instance.selectedFields) {
      // Filter out pseudo-fields (selectors that start with _ and are used only for filtering)
      var pseudoFields = ['_post_type', '_taxonomy', '_table_name'];
      return this.step3Instance.selectedFields.map(function (field) {
        return field.field;
      }).filter(function (field) {
        return !pseudoFields.includes(field);
      });
    }

    // Fallback to old checkbox method (if still used somewhere)
    var fields = [];
    jQuery('input[name="fields[]"]:checked').each(function () {
      fields.push(jQuery(this).val());
    });
    return fields;
  },
  /**
   * Start export
   */
  startExport: function startExport() {
    var _this5 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee2() {
      var fields, contentType, dynamicFiltersData, csvDelimiter, customDelimiter, data, convertedFunctions, $tableDropdown, tableName, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee2$(_context2) {
        while (1) {
          switch (_context2.prev = _context2.next) {
            case 0:
              fields = _this5.getSelectedFields(); // If no fields selected (or only pseudo-fields were filtered out), show error
              if (!(fields.length === 0)) {
                _context2.next = 4;
                break;
              }
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Please select at least one field to export', 'error');
              return _context2.abrupt("return");
            case 4:
              _context2.prev = 4;
              contentType = jQuery('input[name="content_type"]:checked').val();
              dynamicFiltersData = _this5.getDynamicFilters(); // Get CSV delimiter
              csvDelimiter = jQuery('[name="csv_delimiter"]').val();
              if (!(csvDelimiter === 'custom')) {
                _context2.next = 15;
                break;
              }
              customDelimiter = jQuery('[name="csv_custom_delimiter"]').val();
              if (customDelimiter) {
                _context2.next = 14;
                break;
              }
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Please enter a custom delimiter', 'error');
              // Set focus to the custom delimiter field
              jQuery('[name="csv_custom_delimiter"]').focus();
              return _context2.abrupt("return");
            case 14:
              csvDelimiter = customDelimiter;
            case 15:
              data = {
                export_type: contentType,
                filters: _this5.getFilters(),
                fields: fields,
                format: jQuery('input[name="format"]:checked').val(),
                format_options: {
                  csv_delimiter: csvDelimiter,
                  csv_include_header: jQuery('[name="csv_include_header"]').is(':checked'),
                  json_pretty_print: jQuery('[name="json_pretty_print"]').is(':checked')
                },
                options: {
                  items_per_iteration: parseInt(jQuery('[name="items_per_iteration"]').val()) || 3
                }
              }; // Add field functions if available
              if (_this5.step3Instance && _this5.step3Instance.fieldFunctions) {
                // Convert field functions from fieldKey (with timestamp) to actual field names
                convertedFunctions = _this5.convertFieldFunctions(_this5.step3Instance.fieldFunctions, _this5.step3Instance.selectedFields);
                if (Object.keys(convertedFunctions).length > 0) {
                  data.field_functions = convertedFunctions;
                }
              }

              // Add dynamic filters
              if (dynamicFiltersData.filters.length > 0) {
                data.dynamic_filters = dynamicFiltersData.filters;
              }

              // Add custom field filters
              if (dynamicFiltersData.custom_fields.length > 0) {
                data.custom_fields = dynamicFiltersData.custom_fields;
              }

              // Add taxonomy filters
              if (dynamicFiltersData.taxonomy.length > 0) {
                data.taxonomy = dynamicFiltersData.taxonomy;
              }

              // For database_table, add table_name
              if (contentType === 'database_table') {
                $tableDropdown = jQuery('#aie-table-name');
                tableName = $tableDropdown.val();
                if (tableName) {
                  data.table_name = tableName;
                }
              }
              _context2.next = 23;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_export_start', data);
            case 23:
              response = _context2.sent;
              _this5.jobId = response.job_id;
              _this5.showStep(5);
              _this5.startProgressTracking();

              // Trigger first batch processing
              _this5.processNextBatch();
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.exportStartedSuccess, 'success');
              _context2.next = 34;
              break;
            case 31:
              _context2.prev = 31;
              _context2.t0 = _context2["catch"](4);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].handleError(_context2.t0, 'Start export');
            case 34:
            case "end":
              return _context2.stop();
          }
        }
      }, _callee2, null, [[4, 31]]);
    }))();
  },
  /**
   * Process next export batch
   */
  processNextBatch: function processNextBatch() {
    var _this6 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee3() {
      var response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee3$(_context3) {
        while (1) {
          switch (_context3.prev = _context3.next) {
            case 0:
              if (_this6.jobId) {
                _context3.next = 2;
                break;
              }
              return _context3.abrupt("return");
            case 2:
              _context3.prev = 2;
              _context3.next = 5;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_export_process_batch', {
                job_id: _this6.jobId
              });
            case 5:
              response = _context3.sent;
              console.log('Batch processing response:', response);

              // If not completed, process next batch after small delay
              if (response && !response.completed) {
                setTimeout(function () {
                  _this6.processNextBatch();
                }, 100);
              }
              _context3.next = 13;
              break;
            case 10:
              _context3.prev = 10;
              _context3.t0 = _context3["catch"](2);
              console.error('Batch processing error:', _context3.t0);
            case 13:
            case "end":
              return _context3.stop();
          }
        }
      }, _callee3, null, [[2, 10]]);
    }))();
  },
  /**
   * Start progress tracking
   */
  startProgressTracking: function startProgressTracking() {
    var _this7 = this;
    this.progressInterval = setInterval(function () {
      _this7.updateProgress();
    }, 2000);
  },
  /**
   * Update progress
   */
  updateProgress: function updateProgress() {
    var _this8 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee4() {
      var response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee4$(_context4) {
        while (1) {
          switch (_context4.prev = _context4.next) {
            case 0:
              _context4.prev = 0;
              _context4.next = 3;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_export_get_progress', {
                job_id: _this8.jobId
              });
            case 3:
              response = _context4.sent;
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].updateProgressBar(jQuery('.aie-step-5'), response);
              if (response.status === 'completed') {
                _this8.onExportComplete(response);
              } else if (response.status === 'failed') {
                _this8.onExportFailed(response);
              }
              _context4.next = 11;
              break;
            case 8:
              _context4.prev = 8;
              _context4.t0 = _context4["catch"](0);
              console.error('Progress update error:', _context4.t0);
            case 11:
            case "end":
              return _context4.stop();
          }
        }
      }, _callee4, null, [[0, 8]]);
    }))();
  },
  /**
   * Handle export completion
   */
  onExportComplete: function onExportComplete(result) {
    var _result$estimates;
    clearInterval(this.progressInterval);

    // Update title
    jQuery('.aie-step-5 h2').text('Export Complete!');

    // Hide the description text
    jQuery('.aie-step-5 .description').hide();

    // Hide progress container
    jQuery('.aie-progress-container').hide();

    // Show results container
    jQuery('.aie-export-results').show();

    // Show and populate the success card
    var $card = jQuery('.aie-export-complete-card');
    $card.show();

    // Use data from result (progress response)
    jQuery('.aie-result-processed').text(result.processed || result.total || 0);
    jQuery('.aie-result-filesize').text(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].formatFileSize(result.file_size || 0));
    jQuery('.aie-result-duration').text(((_result$estimates = result.estimates) === null || _result$estimates === void 0 ? void 0 : _result$estimates.elapsed_formatted) || '0s');
    jQuery('.aie-cancel-export').hide();
    jQuery('.aie-new-export').show();
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.exportCompletedSuccess, 'success');
  },
  /**
   * Handle export failure
   */
  onExportFailed: function onExportFailed(result) {
    clearInterval(this.progressInterval);
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Export failed: ' + (result.error || 'Unknown error'), 'error');
  },
  /**
   * Download export file
   */
  downloadFile: function downloadFile() {
    var _this9 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee5() {
      var response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee5$(_context5) {
        while (1) {
          switch (_context5.prev = _context5.next) {
            case 0:
              _context5.prev = 0;
              _context5.next = 3;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_export_download', {
                job_id: _this9.jobId
              });
            case 3:
              response = _context5.sent;
              if (response.download_url) {
                _utils__WEBPACK_IMPORTED_MODULE_1__["default"].downloadFile(response.download_url, response.filename);
              }
              _context5.next = 10;
              break;
            case 7:
              _context5.prev = 7;
              _context5.t0 = _context5["catch"](0);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].handleError(_context5.t0, 'Download file');
            case 10:
            case "end":
              return _context5.stop();
          }
        }
      }, _callee5, null, [[0, 7]]);
    }))();
  },
  /**
   * Cancel export
   */
  cancelExport: function cancelExport() {
    var _this10 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee6() {
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee6$(_context6) {
        while (1) {
          switch (_context6.prev = _context6.next) {
            case 0:
              if (confirm(window.aieData.i18n.confirmCancelExport)) {
                _context6.next = 2;
                break;
              }
              return _context6.abrupt("return");
            case 2:
              _context6.prev = 2;
              _context6.next = 5;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_export_cancel', {
                job_id: _this10.jobId
              });
            case 5:
              clearInterval(_this10.progressInterval);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.exportCancelled, 'info');
              _this10.resetWizard();
              _context6.next = 13;
              break;
            case 10:
              _context6.prev = 10;
              _context6.t0 = _context6["catch"](2);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].handleError(_context6.t0, 'Cancel export');
            case 13:
            case "end":
              return _context6.stop();
          }
        }
      }, _callee6, null, [[2, 10]]);
    }))();
  },
  /**
   * Start new export - reload the page
   */
  newExport: function newExport() {
    window.location.href = '/wp-admin/admin.php?page=wp-aie-export';
  },
  /**
   * Reset wizard
   */
  resetWizard: function resetWizard() {
    this.currentStep = 1;
    this.jobId = null;
    clearInterval(this.progressInterval);
    jQuery('#wp-aie-export input[type="text"], #wp-aie-export input[type="date"]').val('');
    jQuery('#wp-aie-export input[type="radio"]:first').prop('checked', true);
    jQuery('.aie-export-results').hide();
    this.showStep(1);
  },
  /**
   * Add new filter row
   */
  addFilterRow: function addFilterRow() {
    var _this11 = this;
    var template = document.getElementById('aie-filter-row-template');
    var clone = template.content.cloneNode(true);
    var contentType = jQuery('input[name="content_type"]:checked').val();

    // Populate field options based on content type
    var $fieldSelect = jQuery(clone).find('.aie-filter-field');
    var fields = this.getFieldsByContentType(contentType);
    fields.forEach(function (group) {
      var $optgroup = jQuery('<optgroup>').attr('label', group.label);
      group.options.forEach(function (option) {
        $optgroup.append(jQuery('<option>').val(option.value).text(option.label).data('type', option.type));
      });
      $fieldSelect.append($optgroup);
    });
    jQuery('#aie-filters-list').append(clone);

    // Trigger count refresh (without spinner)
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].debounce(function () {
      return _this11.refreshCount(false);
    }, 500)();
  },
  /**
   * Remove filter row
   */
  removeFilterRow: function removeFilterRow(e) {
    var _this12 = this;
    jQuery(e.target).closest('.aie-filter-row').remove();

    // Trigger count refresh (without spinner)
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].debounce(function () {
      return _this12.refreshCount(false);
    }, 500)();
  },
  /**
   * Handle filter field change
   */
  onFilterFieldChange: function onFilterFieldChange(e) {
    var _this14 = this;
    var $field = jQuery(e.target);
    var $row = $field.closest('.aie-filter-row');
    var $condition = $row.find('.aie-filter-condition');
    var $valueWrap = $row.find('.aie-filter-value-wrap');
    var $value = $row.find('.aie-filter-value');
    var selectedOption = $field.find('option:selected');
    var fieldType = selectedOption.data('type') || 'string';

    // Special handling for custom_field
    if (fieldType === 'custom_field') {
      // Create custom interface for custom field filter
      $condition.closest('.aie-filter-condition-wrap').show();
      $valueWrap.html("\n\t\t\t\t<div class=\"aie-custom-field-inputs\">\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>Field Name</label>\n\t\t\t\t\t\t<input type=\"text\" class=\"aie-custom-field-name\" placeholder=\"Enter custom field name...\" />\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>Condition</label>\n\t\t\t\t\t\t<select class=\"aie-custom-field-condition aie-filter-condition\">\n\t\t\t\t\t\t\t<option value=\"equals\">Equals</option>\n\t\t\t\t\t\t\t<option value=\"not_equals\">Not Equals</option>\n\t\t\t\t\t\t\t<option value=\"contains\">Contains</option>\n\t\t\t\t\t\t\t<option value=\"not_contains\">Not Contains</option>\n\t\t\t\t\t\t\t<option value=\"greater\">Greater Than</option>\n\t\t\t\t\t\t\t<option value=\"less\">Less Than</option>\n\t\t\t\t\t\t\t<option value=\"equals_or_greater\">Greater or Equal</option>\n\t\t\t\t\t\t\t<option value=\"equals_or_less\">Less or Equal</option>\n\t\t\t\t\t\t\t<option value=\"in\">In (comma-separated)</option>\n\t\t\t\t\t\t\t<option value=\"not_in\">Not In (comma-separated)</option>\n\t\t\t\t\t\t\t<option value=\"is_empty\">Is Empty</option>\n\t\t\t\t\t\t\t<option value=\"is_not_empty\">Is Not Empty</option>\n\t\t\t\t\t\t</select>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-input-group aie-custom-field-value-group\">\n\t\t\t\t\t\t<label>Value</label>\n\t\t\t\t\t\t<input type=\"text\" class=\"aie-custom-field-value aie-filter-value\" placeholder=\"Enter value...\" />\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t");
      $condition.closest('.aie-filter-condition-wrap').hide();

      // Handle condition change to show/hide value input
      $row.find('.aie-custom-field-condition').on('change', function () {
        var _this13 = this;
        var condition = jQuery(this).val();
        var $valueGroup = $row.find('.aie-custom-field-value-group');
        if (condition === 'is_empty' || condition === 'is_not_empty') {
          $valueGroup.hide();
        } else {
          $valueGroup.show();
        }
        // Trigger count refresh on condition change
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].debounce(function () {
          return _this13.refreshCount(false);
        }, 500)();
      }.bind(this));

      // Add change event handlers to trigger count refresh
      $row.find('.aie-custom-field-name, .aie-custom-field-value').on('input change', function () {
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].debounce(function () {
          return _this14.refreshCount(false);
        }, 500)();
      });
      return;
    }

    // Special handling for taxonomy_filter
    if (fieldType === 'taxonomy_filter') {
      // Create custom interface for taxonomy filter
      $condition.closest('.aie-filter-condition-wrap').show();
      $valueWrap.html("\n\t\t\t\t<div class=\"aie-taxonomy-filter-inputs\">\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>Taxonomy Name</label>\n\t\t\t\t\t\t<input type=\"text\" class=\"aie-taxonomy-name\" placeholder=\"e.g., category, post_tag, product_cat...\" />\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>Condition</label>\n\t\t\t\t\t\t<select class=\"aie-taxonomy-condition aie-filter-condition\">\n\t\t\t\t\t\t\t<option value=\"in\">Has Term(s) - IN</option>\n\t\t\t\t\t\t\t<option value=\"not_in\">Does Not Have Term(s) - NOT IN</option>\n\t\t\t\t\t\t\t<option value=\"and\">Has All Terms - AND</option>\n\t\t\t\t\t\t</select>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>Terms</label>\n\t\t\t\t\t\t<input type=\"text\" class=\"aie-taxonomy-terms aie-filter-value\" placeholder=\"Enter term slugs (comma-separated)...\" />\n\t\t\t\t\t\t<small>Enter term slugs separated by commas</small>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t");
      $condition.closest('.aie-filter-condition-wrap').hide();

      // Add change event handlers to trigger count refresh
      $row.find('.aie-taxonomy-name, .aie-taxonomy-condition, .aie-taxonomy-terms').on('input change', function () {
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].debounce(function () {
          return _this14.refreshCount(false);
        }, 500)();
      });
      return;
    }

    // Special handling for table_selector
    if (fieldType === 'table_selector') {
      // Hide condition dropdown for table selector
      $condition.closest('.aie-filter-condition-wrap').hide();

      // Replace value input with table selector
      $valueWrap.find('label').text('Select Table');

      // Create a select dropdown for tables
      var $select = jQuery('<select>').addClass('aie-filter-value aie-table-selector').attr('name', 'filter_value[]');

      // Fetch database tables via AJAX
      _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_get_database_tables', {}).then(function (tables) {
        $select.append(jQuery('<option>').val('').text('Select Table...'));
        if (tables && Array.isArray(tables)) {
          tables.forEach(function (table) {
            $select.append(jQuery('<option>').val(table.name).text(table.name));
          });

          // When table is selected, reload filter fields
          $select.on('change', function () {
            var tableName = $select.val();
            if (tableName) {
              _this14.loadTableColumns(tableName);
            }
          });
        }
      })["catch"](function (error) {
        console.error('Error loading tables:', error);
        $select.append(jQuery('<option>').val('').text('Error loading tables'));
      });
      $value.replaceWith($select);
      return;
    }

    // Special handling for post_type_selector
    if (fieldType === 'post_type_selector') {
      // Hide condition dropdown for post type selector
      $condition.closest('.aie-filter-condition-wrap').hide();

      // Replace value input with post type selector
      $valueWrap.find('label').text('Select Post Type');

      // Create a select dropdown for post types
      var _$select = jQuery('<select>').addClass('aie-filter-value aie-post-type-selector').attr('name', 'filter_value[]');

      // Fetch post types via AJAX
      _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_get_post_types', {
        include_hidden: true
      }).then(function (postTypes) {
        _$select.append(jQuery('<option>').val('').text('Select Post Type...'));
        if (postTypes && Array.isArray(postTypes)) {
          postTypes.forEach(function (postType) {
            _$select.append(jQuery('<option>').val(postType.name).text(postType.label + ' (' + postType.name + ')'));
          });

          // When post type is selected, refresh count
          _$select.on('change', function () {
            _utils__WEBPACK_IMPORTED_MODULE_1__["default"].debounce(function () {
              return _this14.refreshCount(false);
            }, 500)();

            // Update step 2 next button state
            _this14.updateStep2NextButton();

            // Reload step 3 fields if currently on step 3
            if (_this14.currentStep === 3 && _this14.step3Instance) {
              _this14.step3Instance.reloadDynamicFields();
            }
          });
        }
      })["catch"](function (error) {
        console.error('Error loading post types:', error);
        _$select.append(jQuery('<option>').val('').text('Error loading post types'));
      });
      $value.replaceWith(_$select);
      return;
    }

    // Special handling for taxonomy_selector
    if (fieldType === 'taxonomy_selector') {
      // Hide condition dropdown for taxonomy selector
      $condition.closest('.aie-filter-condition-wrap').hide();

      // Replace value input with taxonomy selector
      $valueWrap.find('label').text('Select Taxonomy');

      // Create a select dropdown for taxonomies
      var _$select2 = jQuery('<select>').addClass('aie-filter-value aie-taxonomy-selector').attr('name', 'filter_value[]');

      // Fetch taxonomies via AJAX
      _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_get_all_taxonomies', {}).then(function (taxonomies) {
        _$select2.append(jQuery('<option>').val('').text('Select Taxonomy...'));
        if (taxonomies && Array.isArray(taxonomies)) {
          taxonomies.forEach(function (taxonomy) {
            _$select2.append(jQuery('<option>').val(taxonomy.name).text(taxonomy.label + ' (' + taxonomy.name + ')'));
          });

          // When taxonomy is selected, refresh count
          _$select2.on('change', function () {
            _utils__WEBPACK_IMPORTED_MODULE_1__["default"].debounce(function () {
              return _this14.refreshCount(false);
            }, 500)();

            // Update step 2 next button state
            _this14.updateStep2NextButton();

            // Reload step 3 fields if currently on step 3
            if (_this14.currentStep === 3 && _this14.step3Instance) {
              _this14.step3Instance.reloadDynamicFields();
            }
          });
        }
      })["catch"](function (error) {
        console.error('Error loading taxonomies:', error);
        _$select2.append(jQuery('<option>').val('').text('Error loading taxonomies'));
      });
      $value.replaceWith(_$select2);
      return;
    }

    // Show condition dropdown for normal fields
    $condition.closest('.aie-filter-condition-wrap').show();
    $valueWrap.find('label').text('Value');

    // If current input is a select (from post_type_selector or table_selector), replace with input
    if ($value.is('select')) {
      var $input = jQuery('<input>').attr('type', 'text').addClass('aie-filter-value').attr('name', 'filter_value[]').attr('placeholder', 'Enter value...');
      $value.replaceWith($input);
      // Update reference
      $row.find('.aie-filter-value').attr('type', fieldType === 'date' ? 'date' : fieldType === 'number' ? 'number' : 'text');
    } else {
      // Clear existing conditions
      $condition.empty();

      // Populate conditions based on field type
      var conditions = this.getConditionsByFieldType(fieldType);

      // Get the actual field name to filter out inappropriate conditions
      var fieldName = $field.val();

      // Filter conditions based on field
      var filteredConditions = conditions.filter(function (condition) {
        // For ID fields, exclude is_empty and is_not_empty (ID cannot be empty)
        if (fieldName === 'ID' || fieldName === 'comment_ID' || fieldName === 'term_id' || fieldName === 'user_id' || fieldName === 'attribute_id') {
          return condition.value !== 'is_empty' && condition.value !== 'is_not_empty';
        }

        // For date fields, exclude is_empty and is_not_empty (dates typically always have values)
        if (fieldType === 'date') {
          return condition.value !== 'is_empty' && condition.value !== 'is_not_empty';
        }

        // For comment_status, exclude is_empty and is_not_empty (always has a value: open, closed, etc.)
        if (fieldName === 'comment_status') {
          return condition.value !== 'is_empty' && condition.value !== 'is_not_empty';
        }

        // For content and excerpt fields, exclude in and not_in (not practical for long text)
        if (fieldName === 'post_content' || fieldName === 'post_excerpt') {
          return condition.value !== 'in' && condition.value !== 'not_in';
        }
        return true;
      });
      filteredConditions.forEach(function (condition) {
        $condition.append(jQuery('<option>').val(condition.value).text(condition.label));
      });

      // Clear the value when field changes
      $value.val('');

      // Change value input type based on field type
      if (fieldType === 'date') {
        $value.attr('type', 'date');
      } else if (fieldType === 'number') {
        $value.attr('type', 'number');
      } else {
        $value.attr('type', 'text');
      }

      // Update input type based on current condition
      this.updateValueInputType($row);
    }

    // Trigger count refresh (without spinner)
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].debounce(function () {
      return _this14.refreshCount(false);
    }, 500)();
  },
  /**
   * Load table columns dynamically
   */
  loadTableColumns: function loadTableColumns(tableName) {
    var _this15 = this;
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_get_table_columns', {
      table_name: tableName
    }).then(function (columns) {
      if (columns && Array.isArray(columns)) {
        // Store columns for later use
        _this15.tableColumns = columns;

        // Update all filter field dropdowns
        jQuery('.aie-filter-field').each(function (index, element) {
          var $fieldSelect = jQuery(element);
          var currentValue = $fieldSelect.val();

          // Clear and rebuild options
          $fieldSelect.empty();
          $fieldSelect.append(jQuery('<option>').val('').text('Select Field...'));

          // Add columns as options
          columns.forEach(function (column) {
            $fieldSelect.append(jQuery('<option>').val(column.name).text(column.name + ' (' + column.type + ')').data('type', column.data_type));
          });

          // Restore previous value if exists
          if (currentValue) {
            $fieldSelect.val(currentValue);
          }
        });
      }
    })["catch"](function (error) {
      console.error('Error loading table columns:', error);
    });
  },
  /**
   * Get fields by content type
   */
  getFieldsByContentType: function getFieldsByContentType(contentType) {
    var baseFields = [{
      label: 'Standard',
      options: [{
        value: 'post_title',
        label: 'Title',
        type: 'string'
      }, {
        value: 'post_content',
        label: 'Content',
        type: 'string'
      }, {
        value: 'post_excerpt',
        label: 'Excerpt',
        type: 'string'
      }, {
        value: 'post_date',
        label: 'Date',
        type: 'date'
      }, {
        value: 'post_status',
        label: 'Status',
        type: 'string'
      }]
    }, {
      label: 'Other',
      options: [{
        value: 'comment_status',
        label: 'Comment Status',
        type: 'string'
      }, {
        value: 'post_modified',
        label: 'Modified Date',
        type: 'date'
      }, {
        value: '_wp_page_template',
        label: 'Template',
        type: 'string'
      }]
    }, {
      label: 'Custom Filters',
      options: [{
        value: '_custom_field',
        label: '🔧 Custom Field (Meta)',
        type: 'custom_field'
      }, {
        value: '_taxonomy_filter',
        label: '🏷️ Taxonomy Filter',
        type: 'taxonomy_filter'
      }]
    }]; // Customize based on content type
    if (contentType === 'media') {
      return [{
        label: 'Basic',
        options: [{
          value: 'post_title',
          label: 'Title',
          type: 'string'
        }, {
          value: 'post_content',
          label: 'Description',
          type: 'string'
        }, {
          value: 'post_excerpt',
          label: 'Caption',
          type: 'string'
        }, {
          value: 'alt_text',
          label: 'Alt Text',
          type: 'string'
        }]
      }, {
        label: 'File Information',
        options: [{
          value: 'guid',
          label: 'File URL (GUID)',
          type: 'url'
        }, {
          value: 'file_url',
          label: 'File URL',
          type: 'url'
        }, {
          value: 'file_path',
          label: 'File Path (Relative)',
          type: 'string'
        }, {
          value: 'file_name',
          label: 'File Name',
          type: 'string'
        }, {
          value: 'file_extension',
          label: 'File Extension',
          type: 'string'
        }, {
          value: 'post_mime_type',
          label: 'MIME Type',
          type: 'string'
        }, {
          value: 'file_size',
          label: 'File Size (bytes)',
          type: 'number'
        }]
      }, {
        label: 'Image Dimensions',
        options: [{
          value: 'width',
          label: 'Width (px)',
          type: 'number'
        }, {
          value: 'height',
          label: 'Height (px)',
          type: 'number'
        }]
      }, {
        label: 'Dates',
        options: [{
          value: 'post_date',
          label: 'Upload Date',
          type: 'date'
        }, {
          value: 'post_modified',
          label: 'Modified Date',
          type: 'date'
        }]
      }, {
        label: 'Author',
        options: [{
          value: 'post_author',
          label: 'Author ID',
          type: 'number'
        }, {
          value: 'author_name',
          label: 'Author Name',
          type: 'string'
        }, {
          value: 'author_email',
          label: 'Author Email',
          type: 'email'
        }]
      }, {
        label: 'Attachment',
        options: [{
          value: 'post_parent',
          label: 'Attached To (Post ID)',
          type: 'number'
        }, {
          value: 'attached_post_title',
          label: 'Attached Post Title',
          type: 'string'
        }]
      }, {
        label: 'Custom Filters',
        options: [{
          value: '_custom_field',
          label: '🔧 Custom Field (Meta)',
          type: 'custom_field'
        }]
      }];
    }

    // Pages don't have taxonomy section (but taxonomy_filter is still available in Custom Filters)
    if (contentType === 'page') {
      return baseFields.filter(function (group) {
        return group.label !== 'Taxonomy';
      });
    }

    // Menus
    if (contentType === 'menu') {
      return [{
        label: 'Basic',
        options: [{
          value: 'name',
          label: 'Menu Name',
          type: 'string'
        }, {
          value: 'description',
          label: 'Description',
          type: 'string'
        }, {
          value: 'menu_items',
          label: 'Menu Items (Array)',
          type: 'array'
        }]
      }, {
        label: 'Details',
        options: [{
          value: 'count',
          label: 'Items Count',
          type: 'number'
        }, {
          value: 'locations',
          label: 'Theme Locations',
          type: 'string'
        }]
      }, {
        label: 'Custom Filters',
        options: [{
          value: '_custom_field',
          label: '🔧 Custom Field (Meta)',
          type: 'custom_field'
        }]
      }];
    }

    // Users
    if (contentType === 'user') {
      return [{
        label: 'Basic',
        options: [{
          value: 'user_login',
          label: 'Username',
          type: 'string'
        }, {
          value: 'user_email',
          label: 'Email',
          type: 'string'
        }, {
          value: 'display_name',
          label: 'Display Name',
          type: 'string'
        }, {
          value: 'user_nicename',
          label: 'Nice Name',
          type: 'string'
        }]
      }, {
        label: 'Profile',
        options: [{
          value: 'first_name',
          label: 'First Name',
          type: 'string'
        }, {
          value: 'last_name',
          label: 'Last Name',
          type: 'string'
        }, {
          value: 'nickname',
          label: 'Nickname',
          type: 'string'
        }, {
          value: 'description',
          label: 'Bio',
          type: 'string'
        }, {
          value: 'user_url',
          label: 'Website',
          type: 'string'
        }, {
          value: 'avatar_url',
          label: 'Avatar URL',
          type: 'string'
        }]
      }, {
        label: 'Role & Permissions',
        options: [{
          value: 'role',
          label: 'Role',
          type: 'string'
        }, {
          value: 'capabilities',
          label: 'Capabilities (Array)',
          type: 'array'
        }]
      }, {
        label: 'Preferences',
        options: [{
          value: 'locale',
          label: 'Language',
          type: 'string'
        }, {
          value: 'admin_color',
          label: 'Admin Color Scheme',
          type: 'string'
        }, {
          value: 'rich_editing',
          label: 'Visual Editor',
          type: 'boolean'
        }]
      }, {
        label: 'Stats',
        options: [{
          value: 'posts_count',
          label: 'Posts Count',
          type: 'number'
        }, {
          value: 'user_registered',
          label: 'Registration Date',
          type: 'date'
        }, {
          value: 'user_status',
          label: 'User Status',
          type: 'number'
        }]
      }, {
        label: 'Custom Filters',
        options: [{
          value: '_custom_field',
          label: '🔧 Custom Field (Meta)',
          type: 'custom_field'
        }]
      }];
    }

    // Comments
    if (contentType === 'comment') {
      return [{
        label: 'Basic',
        options: [{
          value: 'comment_ID',
          label: 'Comment ID',
          type: 'number'
        }, {
          value: 'comment_post_ID',
          label: 'Post ID',
          type: 'number'
        }, {
          value: 'comment_content',
          label: 'Comment Content',
          type: 'string'
        }, {
          value: 'comment_approved',
          label: 'Status',
          type: 'string'
        }, {
          value: 'comment_type',
          label: 'Comment Type',
          type: 'string'
        }]
      }, {
        label: 'Author',
        options: [{
          value: 'comment_author',
          label: 'Author Name',
          type: 'string'
        }, {
          value: 'comment_author_email',
          label: 'Author Email',
          type: 'string'
        }, {
          value: 'comment_author_url',
          label: 'Author URL',
          type: 'string'
        }, {
          value: 'comment_author_IP',
          label: 'Author IP',
          type: 'string'
        }, {
          value: 'user_id',
          label: 'User ID',
          type: 'number'
        }, {
          value: 'comment_agent',
          label: 'User Agent',
          type: 'string'
        }]
      }, {
        label: 'Related Post',
        options: [{
          value: 'post_title',
          label: 'Post Title',
          type: 'string'
        }, {
          value: 'post_author',
          label: 'Post Author ID',
          type: 'number'
        }]
      }, {
        label: 'Dates',
        options: [{
          value: 'comment_date',
          label: 'Comment Date',
          type: 'date'
        }, {
          value: 'comment_date_gmt',
          label: 'Comment Date (GMT)',
          type: 'date'
        }]
      }, {
        label: 'Hierarchy',
        options: [{
          value: 'comment_parent',
          label: 'Parent Comment ID',
          type: 'number'
        }, {
          value: 'comment_karma',
          label: 'Karma',
          type: 'number'
        }]
      }, {
        label: 'Custom Filters',
        options: [{
          value: '_custom_field',
          label: '🔧 Custom Field (Meta)',
          type: 'custom_field'
        }]
      }];
    }

    // Block Theme Settings
    if (contentType === 'block_theme_settings') {
      return [{
        label: 'Block Theme Components',
        options: [{
          value: 'global_styles',
          label: 'Global Styles (theme.json)',
          type: 'array'
        }, {
          value: 'templates',
          label: 'Custom Templates',
          type: 'array'
        }, {
          value: 'template_parts',
          label: 'Template Parts',
          type: 'array'
        }, {
          value: 'theme_mods',
          label: 'Theme Modifications',
          type: 'array'
        }, {
          value: 'custom_css',
          label: 'Custom CSS',
          type: 'string'
        }]
      }];
    }

    // Custom Post Types
    if (contentType === 'custom_post_types') {
      return [{
        label: 'Post Type Selection',
        options: [{
          value: '_post_type',
          label: 'Post Type (select specific)',
          type: 'post_type_selector'
        }]
      }, {
        label: 'Standard',
        options: [{
          value: 'ID',
          label: 'ID',
          type: 'number'
        }, {
          value: 'post_title',
          label: 'Title',
          type: 'string'
        }, {
          value: 'post_content',
          label: 'Content',
          type: 'string'
        }, {
          value: 'post_excerpt',
          label: 'Excerpt',
          type: 'string'
        }, {
          value: 'post_date',
          label: 'Date',
          type: 'date'
        }, {
          value: 'post_name',
          label: 'Slug',
          type: 'string'
        }, {
          value: 'post_status',
          label: 'Status',
          type: 'string'
        }]
      }, {
        label: 'Author',
        options: [{
          value: 'post_author',
          label: 'Author ID',
          type: 'number'
        }, {
          value: 'author_name',
          label: 'Author Name',
          type: 'string'
        }, {
          value: 'author_email',
          label: 'Author Email',
          type: 'string'
        }]
      }, {
        label: 'Other',
        options: [{
          value: 'post_parent',
          label: 'Parent ID',
          type: 'number'
        }, {
          value: 'post_modified',
          label: 'Modified Date',
          type: 'date'
        }, {
          value: '_wp_page_template',
          label: 'Template',
          type: 'string'
        }]
      }, {
        label: 'Custom Filters',
        options: [{
          value: '_custom_field',
          label: '🔧 Custom Field (Meta)',
          type: 'custom_field'
        }, {
          value: '_taxonomy_filter',
          label: '🏷️ Taxonomy Filter',
          type: 'taxonomy_filter'
        }]
      }];
    } // Taxonomy
    if (contentType === 'taxonomy') {
      return [{
        label: 'Taxonomy Selection',
        options: [{
          value: '_taxonomy',
          label: 'Taxonomy (select specific)',
          type: 'taxonomy_selector'
        }]
      }, {
        label: 'Basic',
        options: [{
          value: 'term_id',
          label: 'Term ID',
          type: 'number'
        }, {
          value: 'name',
          label: 'Term Name',
          type: 'string'
        }, {
          value: 'slug',
          label: 'Term Slug',
          type: 'string'
        }, {
          value: 'description',
          label: 'Description',
          type: 'string'
        }]
      }, {
        label: 'Taxonomy',
        options: [{
          value: 'taxonomy',
          label: 'Taxonomy Type',
          type: 'string'
        }, {
          value: 'term_taxonomy_id',
          label: 'Taxonomy ID',
          type: 'number'
        }]
      }, {
        label: 'Hierarchy',
        options: [{
          value: 'parent',
          label: 'Parent Term ID',
          type: 'number'
        }, {
          value: 'count',
          label: 'Posts Count',
          type: 'number'
        }]
      }, {
        label: 'Custom Filters',
        options: [{
          value: '_custom_field',
          label: '🔧 Term Meta Field',
          type: 'custom_field'
        }]
      }];
    }

    // WooCommerce Products
    if (contentType === 'woo_product') {
      return [{
        label: 'Basic',
        options: [{
          value: 'ID',
          label: 'Product ID',
          type: 'number'
        }, {
          value: 'post_title',
          label: 'Product Name',
          type: 'string'
        }, {
          value: 'post_name',
          label: 'Slug',
          type: 'string'
        }, {
          value: 'post_status',
          label: 'Status',
          type: 'string'
        }, {
          value: 'sku',
          label: 'SKU',
          type: 'string'
        }, {
          value: 'post_author',
          label: 'Author ID',
          type: 'number'
        }]
      }, {
        label: 'Content',
        options: [{
          value: 'post_content',
          label: 'Description',
          type: 'string'
        }, {
          value: 'post_excerpt',
          label: 'Short Description',
          type: 'string'
        }]
      }, {
        label: 'Pricing',
        options: [{
          value: 'regular_price',
          label: 'Regular Price',
          type: 'number'
        }, {
          value: 'sale_price',
          label: 'Sale Price',
          type: 'number'
        }, {
          value: 'tax_status',
          label: 'Tax Status',
          type: 'string'
        }, {
          value: 'tax_class',
          label: 'Tax Class',
          type: 'string'
        }]
      }, {
        label: 'Inventory',
        options: [{
          value: 'stock_quantity',
          label: 'Stock Quantity',
          type: 'number'
        }, {
          value: 'stock_status',
          label: 'Stock Status',
          type: 'string'
        }, {
          value: 'manage_stock',
          label: 'Manage Stock',
          type: 'boolean'
        }, {
          value: 'backorders',
          label: 'Backorders',
          type: 'string'
        }]
      }, {
        label: 'Product Type',
        options: [{
          value: 'product_type',
          label: 'Product Type',
          type: 'string'
        }, {
          value: 'downloadable',
          label: 'Downloadable',
          type: 'boolean'
        }, {
          value: 'virtual',
          label: 'Virtual',
          type: 'boolean'
        }]
      }, {
        label: 'Shipping',
        options: [{
          value: 'weight',
          label: 'Weight',
          type: 'number'
        }, {
          value: 'length',
          label: 'Length',
          type: 'number'
        }, {
          value: 'width',
          label: 'Width',
          type: 'number'
        }, {
          value: 'height',
          label: 'Height',
          type: 'number'
        }, {
          value: 'shipping_class',
          label: 'Shipping Class',
          type: 'string'
        }]
      }, {
        label: 'Media',
        options: [{
          value: 'featured_image',
          label: 'Featured Image',
          type: 'string'
        }, {
          value: 'product_gallery',
          label: 'Gallery Images',
          type: 'array'
        }]
      }, {
        label: 'Taxonomy',
        options: [{
          value: 'product_cat',
          label: 'Categories',
          type: 'string'
        }, {
          value: 'product_tag',
          label: 'Tags',
          type: 'string'
        }]
      }, {
        label: 'Reviews',
        options: [{
          value: 'average_rating',
          label: 'Average Rating',
          type: 'number'
        }, {
          value: 'review_count',
          label: 'Review Count',
          type: 'number'
        }, {
          value: 'comment_status',
          label: 'Reviews Enabled',
          type: 'string'
        }]
      }, {
        label: 'Visibility',
        options: [{
          value: 'featured',
          label: 'Featured',
          type: 'boolean'
        }, {
          value: 'visibility',
          label: 'Catalog Visibility',
          type: 'string'
        }, {
          value: 'total_sales',
          label: 'Total Sales',
          type: 'number'
        }]
      }, {
        label: 'Dates',
        options: [{
          value: 'post_date',
          label: 'Created Date',
          type: 'date'
        }, {
          value: 'post_modified',
          label: 'Modified Date',
          type: 'date'
        }]
      }, {
        label: 'Custom Filters',
        options: [{
          value: '_custom_field',
          label: '🔧 Custom Field (Meta)',
          type: 'custom_field'
        }, {
          value: '_taxonomy_filter',
          label: '🏷️ Taxonomy Filter',
          type: 'taxonomy_filter'
        }]
      }];
    }

    // WooCommerce Orders
    if (contentType === 'woo_order') {
      return [{
        label: 'Basic',
        options: [{
          value: 'ID',
          label: 'Order ID',
          type: 'number'
        }, {
          value: 'order_number',
          label: 'Order Number',
          type: 'string'
        }, {
          value: 'order_status',
          label: 'Status',
          type: 'string'
        }, {
          value: 'order_key',
          label: 'Order Key',
          type: 'string'
        }, {
          value: 'currency',
          label: 'Currency',
          type: 'string'
        }]
      }, {
        label: 'Amounts',
        options: [{
          value: 'order_total',
          label: 'Order Total',
          type: 'number'
        }, {
          value: 'order_subtotal',
          label: 'Subtotal',
          type: 'number'
        }, {
          value: 'order_tax',
          label: 'Tax',
          type: 'number'
        }, {
          value: 'order_shipping',
          label: 'Shipping',
          type: 'number'
        }, {
          value: 'order_discount',
          label: 'Discount',
          type: 'number'
        }]
      }, {
        label: 'Customer',
        options: [{
          value: 'customer_id',
          label: 'Customer ID',
          type: 'number'
        }, {
          value: 'billing_email',
          label: 'Email',
          type: 'string'
        }, {
          value: 'customer_note',
          label: 'Customer Note',
          type: 'string'
        }]
      }, {
        label: 'Billing Address',
        options: [{
          value: 'billing_first_name',
          label: 'First Name',
          type: 'string'
        }, {
          value: 'billing_last_name',
          label: 'Last Name',
          type: 'string'
        }, {
          value: 'billing_company',
          label: 'Company',
          type: 'string'
        }, {
          value: 'billing_address_1',
          label: 'Address 1',
          type: 'string'
        }, {
          value: 'billing_address_2',
          label: 'Address 2',
          type: 'string'
        }, {
          value: 'billing_city',
          label: 'City',
          type: 'string'
        }, {
          value: 'billing_state',
          label: 'State',
          type: 'string'
        }, {
          value: 'billing_postcode',
          label: 'Postcode',
          type: 'string'
        }, {
          value: 'billing_country',
          label: 'Country',
          type: 'string'
        }, {
          value: 'billing_phone',
          label: 'Phone',
          type: 'string'
        }]
      }, {
        label: 'Shipping Address',
        options: [{
          value: 'shipping_first_name',
          label: 'First Name',
          type: 'string'
        }, {
          value: 'shipping_last_name',
          label: 'Last Name',
          type: 'string'
        }, {
          value: 'shipping_company',
          label: 'Company',
          type: 'string'
        }, {
          value: 'shipping_address_1',
          label: 'Address 1',
          type: 'string'
        }, {
          value: 'shipping_address_2',
          label: 'Address 2',
          type: 'string'
        }, {
          value: 'shipping_city',
          label: 'City',
          type: 'string'
        }, {
          value: 'shipping_state',
          label: 'State',
          type: 'string'
        }, {
          value: 'shipping_postcode',
          label: 'Postcode',
          type: 'string'
        }, {
          value: 'shipping_country',
          label: 'Country',
          type: 'string'
        }]
      }, {
        label: 'Order Items',
        options: [{
          value: 'order_items',
          label: 'Order Items (Array)',
          type: 'array'
        }, {
          value: 'item_count',
          label: 'Item Count',
          type: 'number'
        }]
      }, {
        label: 'Payment',
        options: [{
          value: 'payment_method',
          label: 'Payment Method',
          type: 'string'
        }, {
          value: 'payment_method_title',
          label: 'Payment Method Title',
          type: 'string'
        }, {
          value: 'transaction_id',
          label: 'Transaction ID',
          type: 'string'
        }]
      }, {
        label: 'Shipping',
        options: [{
          value: 'shipping_method',
          label: 'Shipping Method',
          type: 'string'
        }]
      }, {
        label: 'Dates',
        options: [{
          value: 'order_date',
          label: 'Order Date',
          type: 'date'
        }, {
          value: 'completed_date',
          label: 'Completed Date',
          type: 'date'
        }, {
          value: 'paid_date',
          label: 'Paid Date',
          type: 'date'
        }]
      }, {
        label: 'Notes',
        options: [{
          value: 'order_notes',
          label: 'Order Notes (Array)',
          type: 'array'
        }]
      }, {
        label: 'Custom Filters',
        options: [{
          value: '_custom_field',
          label: '🔧 Custom Field (Meta)',
          type: 'custom_field'
        }]
      }];
    }

    // WooCommerce Coupons
    if (contentType === 'woo_coupon') {
      return [{
        label: 'Basic',
        options: [{
          value: 'ID',
          label: 'Coupon ID',
          type: 'number'
        }, {
          value: 'post_title',
          label: 'Coupon Code',
          type: 'string'
        }, {
          value: 'post_excerpt',
          label: 'Description',
          type: 'string'
        }, {
          value: 'post_status',
          label: 'Status',
          type: 'string'
        }]
      }, {
        label: 'Discount',
        options: [{
          value: 'discount_type',
          label: 'Discount Type',
          type: 'string'
        }, {
          value: 'coupon_amount',
          label: 'Coupon Amount',
          type: 'number'
        }, {
          value: 'free_shipping',
          label: 'Free Shipping',
          type: 'boolean'
        }]
      }, {
        label: 'Usage Restrictions',
        options: [{
          value: 'minimum_amount',
          label: 'Minimum Spend',
          type: 'number'
        }, {
          value: 'maximum_amount',
          label: 'Maximum Spend',
          type: 'number'
        }, {
          value: 'individual_use',
          label: 'Individual Use Only',
          type: 'boolean'
        }, {
          value: 'exclude_sale_items',
          label: 'Exclude Sale Items',
          type: 'boolean'
        }]
      }, {
        label: 'Product Restrictions',
        options: [{
          value: 'product_ids',
          label: 'Allowed Products',
          type: 'array'
        }, {
          value: 'excluded_product_ids',
          label: 'Excluded Products',
          type: 'array'
        }, {
          value: 'product_categories',
          label: 'Allowed Categories',
          type: 'array'
        }, {
          value: 'excluded_product_categories',
          label: 'Excluded Categories',
          type: 'array'
        }]
      }, {
        label: 'Email Restrictions',
        options: [{
          value: 'allowed_emails',
          label: 'Allowed Emails',
          type: 'array'
        }]
      }, {
        label: 'Usage Limits',
        options: [{
          value: 'usage_count',
          label: 'Usage Count',
          type: 'number'
        }, {
          value: 'usage_limit',
          label: 'Usage Limit Total',
          type: 'number'
        }, {
          value: 'usage_limit_per_user',
          label: 'Usage Limit Per User',
          type: 'number'
        }]
      }, {
        label: 'Dates',
        options: [{
          value: 'date_expires',
          label: 'Expiry Date',
          type: 'date'
        }, {
          value: 'post_date',
          label: 'Created Date',
          type: 'date'
        }, {
          value: 'post_modified',
          label: 'Modified Date',
          type: 'date'
        }]
      }, {
        label: 'Custom Filters',
        options: [{
          value: '_custom_field',
          label: '🔧 Custom Field (Meta)',
          type: 'custom_field'
        }]
      }];
    }

    // WooCommerce Attributes
    if (contentType === 'woo_attribute') {
      return [{
        label: 'Basic',
        options: [{
          value: 'attribute_id',
          label: 'Attribute ID',
          type: 'number'
        }, {
          value: 'attribute_name',
          label: 'Attribute Name',
          type: 'string'
        }, {
          value: 'attribute_label',
          label: 'Attribute Label',
          type: 'string'
        }, {
          value: 'attribute_type',
          label: 'Attribute Type',
          type: 'string'
        }]
      }, {
        label: 'Settings',
        options: [{
          value: 'attribute_orderby',
          label: 'Default Sort Order',
          type: 'string'
        }, {
          value: 'attribute_public',
          label: 'Enable Archives',
          type: 'boolean'
        }]
      }, {
        label: 'Terms',
        options: [{
          value: 'term_count',
          label: 'Terms Count',
          type: 'number'
        }, {
          value: 'attribute_terms',
          label: 'All Terms (Array)',
          type: 'array'
        }]
      }];
    }

    // Database Table - use dynamic columns from selected table
    if (contentType === 'database_table') {
      // If we have columns loaded, use them
      if (this.currentTableColumns && this.currentTableColumns.length > 0) {
        var columnOptions = this.currentTableColumns.map(function (col) {
          var typeLabel = col.is_numeric ? 'number' : col.is_date ? 'date' : 'string';
          return {
            value: col.name,
            label: "".concat(col.name, " (").concat(col.type, ")"),
            type: typeLabel
          };
        });
        return [{
          label: 'Table Columns',
          options: columnOptions
        }];
      }

      // Otherwise show message to select table first
      return [{
        label: 'Table Selection',
        options: [{
          value: '_select_table',
          label: '⚠️ Please select a database table first',
          type: 'info'
        }]
      }];
    }
    return baseFields;
  },
  /**
   * Check if filter row is complete (has all required values)
   */
  isFilterRowComplete: function isFilterRowComplete($row) {
    var field = $row.find('.aie-filter-field').val();
    var condition = $row.find('.aie-filter-condition').val();
    var value = $row.find('.aie-filter-value').val();

    // Field and condition must be selected
    if (!field || !condition) {
      return false;
    }

    // Check if condition requires a value
    var noValueConditions = ['is_empty', 'is_not_empty'];
    if (noValueConditions.includes(condition)) {
      return true; // These conditions don't need a value
    }

    // Value must be filled
    return value && value.trim() !== '';
  },
  /**
   * Update value input type based on condition and field type
   */
  updateValueInputType: function updateValueInputType($row) {
    var $field = $row.find('.aie-filter-field');
    var $condition = $row.find('.aie-filter-condition');
    var $value = $row.find('.aie-filter-value');
    var selectedOption = $field.find('option:selected');
    var fieldType = selectedOption.data('type') || 'string';
    var condition = $condition.val();

    // Skip if value is not an input field
    if (!$value.is('input')) {
      return;
    }

    // For 'is_empty' and 'is_not_empty', hide the value input
    var noValueConditions = ['is_empty', 'is_not_empty'];
    if (noValueConditions.includes(condition)) {
      $value.closest('.aie-filter-value-wrap').hide();
      return;
    } else {
      // Always show the value input for other conditions
      $value.closest('.aie-filter-value-wrap').show();
    }

    // For 'in' and 'not_in' conditions, always use text input to allow comma-separated values
    if (condition === 'in' || condition === 'not_in') {
      $value.attr('type', 'text');
      $value.attr('placeholder', 'Enter values separated by comma (e.g., 1,5,8 or test1,test2)');
      return;
    }

    // For 'between' condition on numbers, use text to allow comma-separated range
    if (condition === 'between' && fieldType === 'number') {
      $value.attr('type', 'text');
      $value.attr('placeholder', 'Enter two numbers separated by comma (e.g., 10,100)');
      return;
    }

    // Otherwise, set type based on field type
    if (fieldType === 'date') {
      $value.attr('type', 'date');
      $value.attr('placeholder', '');
    } else if (fieldType === 'number') {
      $value.attr('type', 'number');
      $value.attr('placeholder', 'Enter number...');
    } else {
      $value.attr('type', 'text');
      $value.attr('placeholder', 'Enter value...');
    }
  },
  /**
   * Get conditions by field type
   */
  getConditionsByFieldType: function getConditionsByFieldType(fieldType) {
    var conditions = {
      string: [{
        value: 'equals',
        label: 'Equals'
      }, {
        value: 'not_equals',
        label: "Doesn't Equal"
      }, {
        value: 'in',
        label: 'In'
      }, {
        value: 'not_in',
        label: 'Not In'
      }, {
        value: 'contains',
        label: 'Contains'
      }, {
        value: 'not_contains',
        label: "Doesn't Contain"
      }, {
        value: 'is_empty',
        label: 'Is Empty'
      }, {
        value: 'is_not_empty',
        label: 'Is Not Empty'
      }],
      number: [{
        value: 'equals',
        label: 'Equals'
      }, {
        value: 'not_equals',
        label: "Doesn't Equal"
      }, {
        value: 'in',
        label: 'In'
      }, {
        value: 'not_in',
        label: 'Not In'
      }, {
        value: 'greater',
        label: 'Greater Than'
      }, {
        value: 'equals_or_greater',
        label: 'Equal To Or Greater Than'
      }, {
        value: 'less',
        label: 'Less Than'
      }, {
        value: 'equals_or_less',
        label: 'Equal To Or Less Than'
      }, {
        value: 'is_empty',
        label: 'Is Empty'
      }, {
        value: 'is_not_empty',
        label: 'Is Not Empty'
      }],
      date: [{
        value: 'equals',
        label: 'Equals'
      }, {
        value: 'not_equals',
        label: "Doesn't Equal"
      }, {
        value: 'greater',
        label: 'Newer Than'
      }, {
        value: 'equals_or_greater',
        label: 'Equal To Or Newer Than'
      }, {
        value: 'less',
        label: 'Older Than'
      }, {
        value: 'equals_or_less',
        label: 'Equal To Or Older Than'
      }, {
        value: 'is_empty',
        label: 'Is Empty'
      }, {
        value: 'is_not_empty',
        label: 'Is Not Empty'
      }]
    };
    return conditions[fieldType] || conditions.string;
  },
  /**
   * Load database tables
   */
  loadDatabaseTables: function loadDatabaseTables() {
    var _this16 = this;
    var $dropdown = jQuery('#aie-table-name');
    var $spinner = jQuery('.aie-table-selector .spinner');
    var $section = jQuery('.aie-table-selection-section');

    // Show section
    $section.show();

    // Show loading state
    $dropdown.prop('disabled', true);
    $spinner.addClass('is-active');

    // Fetch tables via AJAX
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_get_database_tables', {}).then(function (response) {
      console.log('Tables response:', response);
      var tables = response.tables || response || [];
      console.log('Parsed tables:', tables);

      // Clear and populate dropdown
      $dropdown.empty();
      $dropdown.append(jQuery('<option>').val('').text('Select a table...'));
      if (!Array.isArray(tables) || tables.length === 0) {
        $dropdown.append(jQuery('<option>').val('').text('No tables found'));
        $dropdown.prop('disabled', true);
        $spinner.removeClass('is-active');
        return;
      }
      tables.forEach(function (table) {
        $dropdown.append(jQuery('<option>').val(table.table_name).text(table.label));
      });

      // Enable dropdown
      $dropdown.prop('disabled', false);
      $spinner.removeClass('is-active');

      // Handle table selection
      $dropdown.off('change').on('change', function () {
        var tableName = $dropdown.val();
        if (tableName) {
          _this16.loadTableColumns(tableName);
        } else {
          jQuery('.aie-table-info').html('').hide();
          jQuery('#aie-filters-list').empty();
        }
        // Update Next button state based on table selection
        _this16.updateStep2NextButton();
      });
    })["catch"](function (error) {
      console.error('Error loading tables:', error);
      $dropdown.empty();
      $dropdown.append(jQuery('<option>').val('').text('Error loading tables'));
      $dropdown.prop('disabled', true);
      $spinner.removeClass('is-active');
    });
  }
}, _defineProperty(_ExportModule, "loadTableColumns", function loadTableColumns(tableName) {
  var _this17 = this;
  var $tableInfo = jQuery('.aie-table-info');
  var $columnsList = jQuery('.aie-columns-list');
  var $rowCount = jQuery('.aie-table-row-count');
  var $columnCount = jQuery('.aie-table-column-count');

  // Show loading state
  $tableInfo.show();
  $columnsList.html('<p>Loading columns...</p>');

  // Fetch columns via AJAX
  _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_get_table_columns', {
    table_name: tableName
  }).then(function (response) {
    var columns = response.columns || [];

    // Update column count
    $columnCount.text(columns.length);

    // Display columns with types
    $columnsList.empty();
    var $list = jQuery('<ul>').addClass('aie-column-type-list');
    columns.forEach(function (col) {
      var typeIcon = _this17.getColumnTypeIcon(col);
      var typeLabel = col.is_numeric ? 'numeric' : col.is_string ? 'text' : col.is_date ? 'date' : 'other';
      $list.append(jQuery('<li>').html("<span class=\"dashicons ".concat(typeIcon, "\"></span> \n\t\t\t\t\t\t\t<strong>").concat(col.name, "</strong> \n\t\t\t\t\t\t\t<span class=\"column-type\">(").concat(col.type, ")</span>")));
    });
    $columnsList.append($list);

    // Store columns for filter field options
    _this17.currentTableColumns = columns;

    // Clear existing filters
    jQuery('#aie-filters-list').empty();

    // Refresh count to get row count
    _this17.refreshCount(false);
  })["catch"](function (error) {
    console.error('Error loading columns:', error);
    $columnsList.html('<p class="error">Error loading columns</p>');
  });
}), _defineProperty(_ExportModule, "getColumnTypeIcon", function getColumnTypeIcon(column) {
  if (column.is_primary) {
    return 'dashicons-admin-network';
  } else if (column.is_numeric) {
    return 'dashicons-calculator';
  } else if (column.is_date) {
    return 'dashicons-calendar-alt';
  } else if (column.is_string) {
    return 'dashicons-text';
  }
  return 'dashicons-marker';
}), _defineProperty(_ExportModule, "convertFieldFunctions", function convertFieldFunctions(fieldFunctions, selectedFields) {
  var converted = {};
  if (!fieldFunctions || !selectedFields) {
    return converted;
  }

  // Create a map from fieldKey to actual field name
  var keyToFieldMap = {};
  selectedFields.forEach(function (fieldData) {
    keyToFieldMap[fieldData.key] = fieldData.field;
  });

  // Convert fieldKey to actual field name
  Object.keys(fieldFunctions).forEach(function (fieldKey) {
    var actualFieldName = keyToFieldMap[fieldKey];
    if (actualFieldName && fieldFunctions[fieldKey].length > 0) {
      converted[actualFieldName] = fieldFunctions[fieldKey];
    }
  });
  return converted;
}), _ExportModule);
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ExportModule);

/***/ }),

/***/ "./src/js/modules/function_library.js":
/*!********************************************!*\
  !*** ./src/js/modules/function_library.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/regenerator */ "./node_modules/@babel/runtime/regenerator/index.js");
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _utils_notifications__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../utils/notifications */ "./src/js/utils/notifications.js");
function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _unsupportedIterableToArray(arr, i) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }
function _iterableToArrayLimit(arr, i) { var _i = arr == null ? null : typeof Symbol !== "undefined" && arr[Symbol.iterator] || arr["@@iterator"]; if (_i == null) return; var _arr = []; var _n = true; var _d = false; var _s, _e; try { for (_i = _i.call(arr); !(_n = (_s = _i.next()).done); _n = true) { _arr.push(_s.value); if (i && _arr.length === i) break; } } catch (err) { _d = true; _e = err; } finally { try { if (!_n && _i["return"] != null) _i["return"](); } finally { if (_d) throw _e; } } return _arr; }
function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }

function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { Promise.resolve(value).then(_next, _throw); } }
function _asyncToGenerator(fn) { return function () { var self = this, args = arguments; return new Promise(function (resolve, reject) { var gen = fn.apply(self, args); function _next(value) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value); } function _throw(err) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err); } _next(undefined); }); }; }
/**
 * Function Library Browser Module
 *
 * Handles browsing, searching, and importing snippet functions
 */


var FunctionLibrary = {
  functionsModule: null,
  allSnippets: {},
  categories: {},
  currentCategory: '',
  currentSnippet: null,
  /**
   * Initialize the module
   */
  init: function init(functionsModule) {
    this.functionsModule = functionsModule;
  },
  /**
   * Open library modal
   */
  openLibrary: function openLibrary() {
    var _this = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee() {
      var modal;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee$(_context) {
        while (1) {
          switch (_context.prev = _context.next) {
            case 0:
              modal = document.getElementById('aie-snippets-library-modal');
              if (modal) {
                _context.next = 3;
                break;
              }
              return _context.abrupt("return");
            case 3:
              modal.style.display = 'flex';
              document.body.style.overflow = 'hidden';

              // Load snippets if not loaded
              if (!(Object.keys(_this.allSnippets).length === 0)) {
                _context.next = 10;
                break;
              }
              _context.next = 8;
              return _this.loadSnippets();
            case 8:
              _context.next = 11;
              break;
            case 10:
              _this.renderSnippets();
            case 11:
              _this.bindLibraryEvents();
            case 12:
            case "end":
              return _context.stop();
          }
        }
      }, _callee);
    }))();
  },
  /**
   * Bind library modal events
   */
  bindLibraryEvents: function bindLibraryEvents() {
    var _this2 = this;
    // Category filtering
    var categoryItems = document.querySelectorAll('.aie-category-item');
    categoryItems.forEach(function (item) {
      item.addEventListener('click', function (e) {
        var category = e.currentTarget.dataset.category;
        _this2.filterByCategory(category);
      });
    });

    // Search
    var searchInput = document.getElementById('aie-snippet-search');
    if (searchInput) {
      var searchTimeout;
      searchInput.addEventListener('input', function (e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function () {
          _this2.searchSnippets(e.target.value);
        }, 300);
      });
    }

    // Preview modal events
    var previewModal = document.getElementById('aie-snippet-preview-modal');
    if (previewModal) {
      var _previewModal$querySe;
      (_previewModal$querySe = previewModal.querySelector('.aie-customize-snippet')) === null || _previewModal$querySe === void 0 ? void 0 : _previewModal$querySe.addEventListener('click', function () {
        _this2.importSnippet(_this2.currentSnippet, true);
      });
    }
  },
  /**
   * Load snippets from server
   */
  loadSnippets: function loadSnippets() {
    var _arguments = arguments,
      _this3 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee2() {
      var _window$aieData, _window$aieData$i18n;
      var category, grid, _window$aieData2, response, data, _data$data;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee2$(_context2) {
        while (1) {
          switch (_context2.prev = _context2.next) {
            case 0:
              category = _arguments.length > 0 && _arguments[0] !== undefined ? _arguments[0] : '';
              grid = document.getElementById('aie-snippets-grid');
              if (grid) {
                _context2.next = 4;
                break;
              }
              return _context2.abrupt("return");
            case 4:
              // Show loading
              grid.innerHTML = "\n\t\t\t<div class=\"aie-loading-snippets\">\n\t\t\t\t<span class=\"spinner is-active\"></span>\n\t\t\t\t<p>".concat(((_window$aieData = window.aieData) === null || _window$aieData === void 0 ? void 0 : (_window$aieData$i18n = _window$aieData.i18n) === null || _window$aieData$i18n === void 0 ? void 0 : _window$aieData$i18n.loading) || 'Loading snippets...', "</p>\n\t\t\t</div>\n\t\t");
              _context2.prev = 5;
              _context2.next = 8;
              return fetch(window.aieData.ajaxUrl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'aie_functions_get_snippets',
                  nonce: ((_window$aieData2 = window.aieData) === null || _window$aieData2 === void 0 ? void 0 : _window$aieData2.nonce) || '',
                  category: category
                })
              });
            case 8:
              response = _context2.sent;
              _context2.next = 11;
              return response.json();
            case 11:
              data = _context2.sent;
              if (data.success) {
                _context2.next = 14;
                break;
              }
              throw new Error(((_data$data = data.data) === null || _data$data === void 0 ? void 0 : _data$data.message) || 'Failed to load snippets');
            case 14:
              _this3.allSnippets = data.data.snippets || {};
              _this3.categories = data.data.categories || {};
              _this3.renderCategories();
              _this3.renderSnippets();
              _context2.next = 24;
              break;
            case 20:
              _context2.prev = 20;
              _context2.t0 = _context2["catch"](5);
              console.error('Error loading snippets:', _context2.t0);
              grid.innerHTML = "\n\t\t\t\t<div class=\"aie-error-message\">\n\t\t\t\t\t<span class=\"dashicons dashicons-warning\"></span>\n\t\t\t\t\t<p>".concat(_context2.t0.message, "</p>\n\t\t\t\t</div>\n\t\t\t");
            case 24:
            case "end":
              return _context2.stop();
          }
        }
      }, _callee2, null, [[5, 20]]);
    }))();
  },
  /**
   * Render categories sidebar
   */
  renderCategories: function renderCategories() {
    var _window$aieData3,
      _window$aieData3$i18n,
      _this4 = this;
    var categoriesList = document.getElementById('aie-categories-list');
    if (!categoriesList) {
      return;
    }
    var totalSnippets = Object.keys(this.allSnippets).length;
    var html = "\n\t\t\t<li class=\"aie-category-item ".concat(this.currentCategory === '' ? 'active' : '', "\" data-category=\"\">\n\t\t\t\t<span class=\"dashicons dashicons-category\"></span>\n\t\t\t\t<span class=\"aie-category-name\">").concat(((_window$aieData3 = window.aieData) === null || _window$aieData3 === void 0 ? void 0 : (_window$aieData3$i18n = _window$aieData3.i18n) === null || _window$aieData3$i18n === void 0 ? void 0 : _window$aieData3$i18n.all_snippets) || 'All Snippets', "</span>\n\t\t\t\t<span class=\"aie-category-count\">").concat(totalSnippets, "</span>\n\t\t\t</li>\n\t\t");
    Object.entries(this.categories).forEach(function (_ref) {
      var _ref2 = _slicedToArray(_ref, 2),
        category = _ref2[0],
        info = _ref2[1];
      var count = Object.values(_this4.allSnippets).filter(function (s) {
        return s.category === category;
      }).length;
      var isActive = _this4.currentCategory === category;
      html += "\n\t\t\t\t<li class=\"aie-category-item ".concat(isActive ? 'active' : '', "\" data-category=\"").concat(category, "\">\n\t\t\t\t\t<span class=\"dashicons dashicons-").concat(info.icon, "\"></span>\n\t\t\t\t\t<span class=\"aie-category-name\">").concat(info.name, "</span>\n\t\t\t\t\t<span class=\"aie-category-count\">").concat(count, "</span>\n\t\t\t\t</li>\n\t\t\t");
    });
    categoriesList.innerHTML = html;
  },
  /**
   * Render snippet cards
   */
  renderSnippets: function renderSnippets() {
    var _this5 = this,
      _window$aieData5;
    var grid = document.getElementById('aie-snippets-grid');
    if (!grid) {
      return;
    }
    var snippets = Object.entries(this.allSnippets);

    // Filter by category
    if (this.currentCategory) {
      snippets = snippets.filter(function (_ref3) {
        var _ref4 = _slicedToArray(_ref3, 2),
          snippet = _ref4[1];
        return snippet.category === _this5.currentCategory;
      });
    }
    if (snippets.length === 0) {
      var _window$aieData4, _window$aieData4$i18n;
      grid.innerHTML = "\n\t\t\t\t<div class=\"aie-no-snippets\">\n\t\t\t\t\t<span class=\"dashicons dashicons-info\" style=\"width: auto; height: auto;\"></span>\n\t\t\t\t\t<p>".concat(((_window$aieData4 = window.aieData) === null || _window$aieData4 === void 0 ? void 0 : (_window$aieData4$i18n = _window$aieData4.i18n) === null || _window$aieData4$i18n === void 0 ? void 0 : _window$aieData4$i18n.no_snippets) || 'No snippets found', "</p>\n\t\t\t\t</div>\n\t\t\t");
      return;
    }

    // Check if "Use" button should be shown
    var currentPage = ((_window$aieData5 = window.aieData) === null || _window$aieData5 === void 0 ? void 0 : _window$aieData5.currentPage) || '';
    var allowedPages = ['wp-advanced-import-export', 'wp-aie-export', 'wp-aie-content-sync', 'wp-aie-functions' // Add Functions page
    ];
    var showUseButton = allowedPages.includes(currentPage);
    grid.innerHTML = snippets.map(function (_ref5) {
      var _window$aieData6, _window$aieData6$i18n, _window$aieData7, _window$aieData7$i18n;
      var _ref6 = _slicedToArray(_ref5, 2),
        key = _ref6[0],
        snippet = _ref6[1];
      return "\n\t\t\t<div class=\"aie-snippet-card\" data-snippet-key=\"".concat(key, "\">\n\t\t\t\t<div class=\"aie-snippet-header\">\n\t\t\t\t\t<h3 class=\"aie-snippet-name\">").concat(_this5.escapeHtml(snippet.name), "</h3>\n\t\t\t\t\t<span class=\"aie-snippet-category-badge\">").concat(_this5.getCategoryLabel(snippet.category), "</span>\n\t\t\t\t</div>\n\t\t\t\t<p class=\"aie-snippet-description\">").concat(_this5.escapeHtml(snippet.description), "</p>\n\t\t\t\t<div class=\"aie-snippet-tags\">\n\t\t\t\t\t").concat(snippet.tags ? snippet.tags.map(function (tag) {
        return "<span class=\"aie-tag\">".concat(_this5.escapeHtml(tag), "</span>");
      }).join('') : '', "\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-snippet-actions\">\n\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-preview-snippet\" data-snippet-key=\"").concat(key, "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-visibility\"></span>\n\t\t\t\t\t\t").concat(((_window$aieData6 = window.aieData) === null || _window$aieData6 === void 0 ? void 0 : (_window$aieData6$i18n = _window$aieData6.i18n) === null || _window$aieData6$i18n === void 0 ? void 0 : _window$aieData6$i18n.preview) || 'Preview', "\n\t\t\t\t\t</button>\n\t\t\t\t\t").concat(showUseButton ? "<button type=\"button\" class=\"button button-primary button-small aie-quick-import\" data-snippet-key=\"".concat(key, "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-plus\"></span>\n\t\t\t\t\t\t").concat(((_window$aieData7 = window.aieData) === null || _window$aieData7 === void 0 ? void 0 : (_window$aieData7$i18n = _window$aieData7.i18n) === null || _window$aieData7$i18n === void 0 ? void 0 : _window$aieData7$i18n.customize) || 'Customize', "\n\t\t\t\t\t</button>") : '', "\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t");
    }).join('');

    // Bind snippet card events
    grid.querySelectorAll('.aie-preview-snippet').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        var key = e.currentTarget.dataset.snippetKey;
        _this5.previewSnippet(key);
      });
    });
    grid.querySelectorAll('.aie-quick-import').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        var key = e.currentTarget.dataset.snippetKey;
        _this5.importSnippet(key, true); // Changed to true - always customize
      });
    });
  },
  /**
   * Filter snippets by category
   */
  filterByCategory: function filterByCategory(category) {
    this.currentCategory = category;

    // Update active state
    document.querySelectorAll('.aie-category-item').forEach(function (item) {
      item.classList.toggle('active', item.dataset.category === category);
    });
    this.renderSnippets();
  },
  /**
   * Search snippets
   */
  searchSnippets: function searchSnippets(query) {
    var _this6 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee3() {
      var _window$aieData8, _window$aieData8$i18n;
      var grid, _window$aieData9, response, data, _data$data2, originalSnippets;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee3$(_context3) {
        while (1) {
          switch (_context3.prev = _context3.next) {
            case 0:
              grid = document.getElementById('aie-snippets-grid');
              if (grid) {
                _context3.next = 3;
                break;
              }
              return _context3.abrupt("return");
            case 3:
              if (query.trim()) {
                _context3.next = 6;
                break;
              }
              // Reset to current category
              _this6.renderSnippets();
              return _context3.abrupt("return");
            case 6:
              // Show loading
              grid.innerHTML = "\n\t\t\t<div class=\"aie-loading-snippets\">\n\t\t\t\t<span class=\"spinner is-active\"></span>\n\t\t\t\t<p>".concat(((_window$aieData8 = window.aieData) === null || _window$aieData8 === void 0 ? void 0 : (_window$aieData8$i18n = _window$aieData8.i18n) === null || _window$aieData8$i18n === void 0 ? void 0 : _window$aieData8$i18n.searching) || 'Searching...', "</p>\n\t\t\t</div>\n\t\t");
              _context3.prev = 7;
              _context3.next = 10;
              return fetch(window.aieData.ajaxUrl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'aie_functions_search',
                  nonce: ((_window$aieData9 = window.aieData) === null || _window$aieData9 === void 0 ? void 0 : _window$aieData9.nonce) || '',
                  query: query
                })
              });
            case 10:
              response = _context3.sent;
              _context3.next = 13;
              return response.json();
            case 13:
              data = _context3.sent;
              if (data.success) {
                _context3.next = 16;
                break;
              }
              throw new Error(((_data$data2 = data.data) === null || _data$data2 === void 0 ? void 0 : _data$data2.message) || 'Search failed');
            case 16:
              // Temporarily replace snippets with search results
              originalSnippets = _this6.allSnippets;
              _this6.allSnippets = data.data.snippets || {};
              _this6.currentCategory = ''; // Show all results
              _this6.renderSnippets();
              _this6.allSnippets = originalSnippets; // Restore
              _context3.next = 27;
              break;
            case 23:
              _context3.prev = 23;
              _context3.t0 = _context3["catch"](7);
              console.error('Error searching snippets:', _context3.t0);
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showError)(_context3.t0.message);
            case 27:
            case "end":
              return _context3.stop();
          }
        }
      }, _callee3, null, [[7, 23]]);
    }))();
  },
  /**
   * Preview snippet in modal
   */
  previewSnippet: function previewSnippet(snippetKey) {
    var snippet = this.allSnippets[snippetKey];
    if (!snippet) {
      return;
    }
    this.currentSnippet = snippetKey;
    var modal = document.getElementById('aie-snippet-preview-modal');
    if (!modal) {
      return;
    }

    // Fill modal with snippet details
    modal.querySelector('.aie-snippet-title').textContent = snippet.name;
    modal.querySelector('.aie-snippet-description').textContent = snippet.description;
    modal.querySelector('.aie-snippet-category').textContent = this.getCategoryLabel(snippet.category);
    modal.querySelector('.aie-snippet-code').textContent = snippet.code;
    if (snippet.tags && snippet.tags.length > 0) {
      modal.querySelector('.aie-snippet-tags').textContent = snippet.tags.join(', ');
    } else {
      modal.querySelector('.aie-snippet-tags').textContent = 'None';
    }
    if (snippet.example) {
      modal.querySelector('.aie-example-input-value').textContent = snippet.example.input !== undefined ? snippet.example.input : 'N/A';
      modal.querySelector('.aie-example-output-value').textContent = snippet.example.output !== undefined ? snippet.example.output : 'N/A';
    }

    // Show/hide "Use" button based on current page
    // Only show on Import, Export, and Content Sync pages
    var useButton = modal.querySelector('.aie-use-snippet');
    if (useButton) {
      var _window$aieData10;
      var currentPage = ((_window$aieData10 = window.aieData) === null || _window$aieData10 === void 0 ? void 0 : _window$aieData10.currentPage) || '';
      var allowedPages = ['wp-advanced-import-export', 'wp-aie-export', 'wp-aie-content-sync'];
      useButton.style.display = allowedPages.includes(currentPage) ? '' : 'none';
    }
    modal.style.display = 'flex';
  },
  /**
   * Import snippet as function
   */
  importSnippet: function importSnippet(snippetKey) {
    var _arguments2 = arguments,
      _this7 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee4() {
      var customize, snippet, libraryModal, previewModal, editorModal, _window$aieData11, _window$aieData12, _window$aieData12$i, response, data, _data$data3;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee4$(_context4) {
        while (1) {
          switch (_context4.prev = _context4.next) {
            case 0:
              customize = _arguments2.length > 1 && _arguments2[1] !== undefined ? _arguments2[1] : false;
              snippet = _this7.allSnippets[snippetKey];
              if (snippet) {
                _context4.next = 4;
                break;
              }
              return _context4.abrupt("return");
            case 4:
              if (!customize) {
                _context4.next = 19;
                break;
              }
              // Close library and preview modals
              libraryModal = document.getElementById('aie-snippets-library-modal');
              previewModal = document.getElementById('aie-snippet-preview-modal');
              if (libraryModal) {
                libraryModal.style.display = 'none';
              }
              if (previewModal) {
                previewModal.style.display = 'none';
              }
              document.body.style.overflow = '';

              // Use the FunctionsModule method to open editor with snippet data
              if (!(_this7.functionsModule && _this7.functionsModule.openEditorWithSnippet)) {
                _context4.next = 15;
                break;
              }
              _context4.next = 13;
              return _this7.functionsModule.openEditorWithSnippet(snippet);
            case 13:
              _context4.next = 17;
              break;
            case 15:
              // Fallback: Open editor directly (old method)
              editorModal = document.getElementById('aie-function-editor-modal');
              if (editorModal) {
                // Clear any previous errors
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.clearModalErrors)();
                document.getElementById('aie-function-id').value = '';
                document.getElementById('aie-function-name').value = snippet.name;
                document.getElementById('aie-function-description').value = snippet.description;
                document.getElementById('aie-function-category').value = 'custom'; // Always use 'custom' category
                document.getElementById('aie-function-code').value = snippet.code;
                document.getElementById('aie-function-status').value = 'active';
                document.querySelector('.aie-modal-title').textContent = 'Customize Function';
                editorModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
              }
            case 17:
              _context4.next = 37;
              break;
            case 19:
              _context4.prev = 19;
              _context4.next = 22;
              return fetch(window.aieData.ajaxUrl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'aie_functions_import',
                  nonce: ((_window$aieData11 = window.aieData) === null || _window$aieData11 === void 0 ? void 0 : _window$aieData11.nonce) || '',
                  snippet_key: snippetKey
                })
              });
            case 22:
              response = _context4.sent;
              _context4.next = 25;
              return response.json();
            case 25:
              data = _context4.sent;
              if (data.success) {
                _context4.next = 28;
                break;
              }
              throw new Error(((_data$data3 = data.data) === null || _data$data3 === void 0 ? void 0 : _data$data3.message) || 'Import failed');
            case 28:
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showNotice)(((_window$aieData12 = window.aieData) === null || _window$aieData12 === void 0 ? void 0 : (_window$aieData12$i = _window$aieData12.i18n) === null || _window$aieData12$i === void 0 ? void 0 : _window$aieData12$i.snippet_imported) || 'Snippet imported successfully');
              document.body.style.overflow = '';

              // Refresh functions list
              if (_this7.functionsModule) {
                _this7.functionsModule.loadFunctions();
              }
              _context4.next = 37;
              break;
            case 33:
              _context4.prev = 33;
              _context4.t0 = _context4["catch"](19);
              console.error('Error importing snippet:', _context4.t0);
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showError)(_context4.t0.message);
            case 37:
            case "end":
              return _context4.stop();
          }
        }
      }, _callee4, null, [[19, 33]]);
    }))();
  },
  /**
   * Get category label
   */
  getCategoryLabel: function getCategoryLabel(category) {
    var labels = {
      string: 'String Operations',
      date: 'Date & Time',
      numeric: 'Numeric Operations',
      html: 'HTML Operations',
      wordpress: 'WordPress',
      validation: 'Validation',
      advanced: 'Advanced',
      custom: 'Custom'
    };
    return labels[category] || category;
  },
  /**
   * Escape HTML
   */
  escapeHtml: function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (FunctionLibrary);

/***/ }),

/***/ "./src/js/modules/functions.js":
/*!*************************************!*\
  !*** ./src/js/modules/functions.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/regenerator */ "./node_modules/@babel/runtime/regenerator/index.js");
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _utils_notifications__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../utils/notifications */ "./src/js/utils/notifications.js");
/* harmony import */ var _function_library__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./function_library */ "./src/js/modules/function_library.js");

function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { Promise.resolve(value).then(_next, _throw); } }
function _asyncToGenerator(fn) { return function () { var self = this, args = arguments; return new Promise(function (resolve, reject) { var gen = fn.apply(self, args); function _next(value) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value); } function _throw(err) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err); } _next(undefined); }); }; }
/**
 * Custom Functions Management Module
 *
 * Handles CRUD operations for custom transformation functions
 */



var FunctionsModule = {
  currentPage: 1,
  perPage: 20,
  totalPages: 1,
  totalItems: 0,
  filters: {
    search: ''
  },
  codeEditor: null,
  // CodeMirror instance
  /**
   * Initialize the module
   */
  init: function init() {
    if (!document.getElementById('wp-aie-functions')) {
      return;
    }
    this.bindEvents();
    this.loadFunctions();

    // Initialize library module
    _function_library__WEBPACK_IMPORTED_MODULE_2__["default"].init(this);
  },
  /**
   * Bind event handlers
   */
  bindEvents: function bindEvents() {
    var _document$querySelect,
      _this = this,
      _document$querySelect2,
      _document$getElementB,
      _document$querySelect3,
      _document$querySelect4,
      _document$querySelect5,
      _document$querySelect6,
      _document$querySelect7;
    // New function button
    (_document$querySelect = document.querySelector('.aie-new-function')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.addEventListener('click', function () {
      _this.openEditorModal();
    });

    // Browse library button
    (_document$querySelect2 = document.querySelector('.aie-browse-library')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.addEventListener('click', function () {
      _function_library__WEBPACK_IMPORTED_MODULE_2__["default"].openLibrary();
    });

    // Search with debounce
    var searchTimeout;
    (_document$getElementB = document.getElementById('aie-filter-search')) === null || _document$getElementB === void 0 ? void 0 : _document$getElementB.addEventListener('input', function (e) {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(function () {
        _this.filters.search = e.target.value;
        _this.currentPage = 1;
        _this.loadFunctions();
      }, 500);
    });

    // Clear filters
    (_document$querySelect3 = document.querySelector('.aie-filter-clear')) === null || _document$querySelect3 === void 0 ? void 0 : _document$querySelect3.addEventListener('click', function () {
      _this.clearFilters();
    });

    // Pagination
    (_document$querySelect4 = document.querySelector('.aie-prev-page')) === null || _document$querySelect4 === void 0 ? void 0 : _document$querySelect4.addEventListener('click', function () {
      if (_this.currentPage > 1) {
        _this.currentPage--;
        _this.loadFunctions();
      }
    });
    (_document$querySelect5 = document.querySelector('.aie-next-page')) === null || _document$querySelect5 === void 0 ? void 0 : _document$querySelect5.addEventListener('click', function () {
      if (_this.currentPage < _this.totalPages) {
        _this.currentPage++;
        _this.loadFunctions();
      }
    });

    // Modal controls
    document.querySelectorAll('.aie-modal-close, .aie-modal-cancel').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        var modal = e.target.closest('.aie-modal');
        if (modal) {
          _this.closeModal(modal);
        }
      });
    });

    // Save function
    (_document$querySelect6 = document.querySelector('.aie-save-function')) === null || _document$querySelect6 === void 0 ? void 0 : _document$querySelect6.addEventListener('click', function () {
      _this.saveFunction();
    });

    // Test function
    (_document$querySelect7 = document.querySelector('.aie-test-function')) === null || _document$querySelect7 === void 0 ? void 0 : _document$querySelect7.addEventListener('click', function () {
      _this.testFunction();
    });

    // Close modal on backdrop click
    document.querySelectorAll('.aie-modal-backdrop').forEach(function (backdrop) {
      backdrop.addEventListener('click', function (e) {
        var modal = e.target.closest('.aie-modal');
        if (modal) {
          _this.closeModal(modal);
        }
      });
    });
  },
  /**
   * Load functions from server
   */
  loadFunctions: function loadFunctions() {
    var _this2 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee() {
      var _window$aieData, _window$aieData$i18n;
      var tbody, _window$aieData2, response, data, _data$data;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee$(_context) {
        while (1) {
          switch (_context.prev = _context.next) {
            case 0:
              tbody = document.getElementById('aie-functions-tbody');
              if (tbody) {
                _context.next = 3;
                break;
              }
              return _context.abrupt("return");
            case 3:
              // Show loading
              tbody.innerHTML = "\n\t\t\t<tr class=\"aie-loading-row\">\n\t\t\t\t<td colspan=\"3\" style=\"text-align:center;\">\n\t\t\t\t\t<span class=\"spinner is-active\"></span>\n\t\t\t\t\t".concat(((_window$aieData = window.aieData) === null || _window$aieData === void 0 ? void 0 : (_window$aieData$i18n = _window$aieData.i18n) === null || _window$aieData$i18n === void 0 ? void 0 : _window$aieData$i18n.loading) || 'Loading...', "\n\t\t\t\t</td>\n\t\t\t</tr>\n\t\t");
              _context.prev = 4;
              _context.next = 7;
              return fetch(window.aieData.ajaxUrl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'aie_functions_get_all',
                  nonce: ((_window$aieData2 = window.aieData) === null || _window$aieData2 === void 0 ? void 0 : _window$aieData2.nonce) || '',
                  search: _this2.filters.search,
                  page: _this2.currentPage,
                  per_page: _this2.perPage
                })
              });
            case 7:
              response = _context.sent;
              _context.next = 10;
              return response.json();
            case 10:
              data = _context.sent;
              if (data.success) {
                _context.next = 13;
                break;
              }
              throw new Error(data.message || ((_data$data = data.data) === null || _data$data === void 0 ? void 0 : _data$data.message) || 'Failed to load functions');
            case 13:
              _this2.totalPages = data.data.total_pages || 1;
              _this2.totalItems = data.data.total || 0;
              _this2.renderTable(data.data.functions || []);
              _this2.updatePagination();
              _context.next = 23;
              break;
            case 19:
              _context.prev = 19;
              _context.t0 = _context["catch"](4);
              console.error('Error loading functions:', _context.t0);
              tbody.innerHTML = "\n\t\t\t\t<tr>\n\t\t\t\t\t<td colspan=\"4\" style=\"text-align:center; color:#dc3232;\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-warning\"></span>\n\t\t\t\t\t\t".concat(_context.t0.message, "\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t");
            case 23:
            case "end":
              return _context.stop();
          }
        }
      }, _callee, null, [[4, 19]]);
    }))();
  },
  /**
   * Render functions table
   */
  renderTable: function renderTable(functions) {
    var _this3 = this;
    var tbody = document.getElementById('aie-functions-tbody');
    if (!tbody) {
      return;
    }
    if (functions.length === 0) {
      var _window$aieData3, _window$aieData3$i18n;
      tbody.innerHTML = "\n\t\t\t\t<tr>\n\t\t\t\t\t<td colspan=\"4\" style=\"text-align:center; padding:40px;\">\n\t\t\t\t\t\t<div style=\"display:flex; flex-direction:column; align-items:center; gap:10px;\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-info\" style=\"font-size:48px; opacity:0.3;\"></span>\n\t\t\t\t\t\t\t<p style=\"margin:23px 0 0 0; color:#666;\">\n\t\t\t\t\t\t\t\t".concat(((_window$aieData3 = window.aieData) === null || _window$aieData3 === void 0 ? void 0 : (_window$aieData3$i18n = _window$aieData3.i18n) === null || _window$aieData3$i18n === void 0 ? void 0 : _window$aieData3$i18n.no_functions) || 'No functions found. Create your first function or browse the library.', "\n\t\t\t\t\t\t\t</p>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t");
      return;
    }
    tbody.innerHTML = functions.map(function (func) {
      return "\n\t\t\t<tr data-function-id=\"".concat(func.id, "\">\n\t\t\t\t<td class=\"column-name\">\n\t\t\t\t\t<strong>").concat(_this3.escapeHtml(func.name), "</strong>\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-description\">\n\t\t\t\t\t").concat(func.description ? _this3.escapeHtml(func.description) : '<em style="color:#999;">No description</em>', "\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-actions\">\n\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-edit-function\" data-id=\"").concat(func.id, "\" title=\"Edit\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-edit\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-delete-function\" data-id=\"").concat(func.id, "\" title=\"Delete\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-trash\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t</td>\n\t\t\t</tr>\n\t\t");
    }).join('');

    // Bind action buttons
    tbody.querySelectorAll('.aie-edit-function').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        var id = e.currentTarget.dataset.id;
        _this3.openEditorModal(id);
      });
    });
    tbody.querySelectorAll('.aie-delete-function').forEach(function (btn) {
      btn.addEventListener('click', /*#__PURE__*/function () {
        var _ref = _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee2(e) {
          var _window$aieData4, _window$aieData4$i18n;
          var id, confirmed;
          return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee2$(_context2) {
            while (1) {
              switch (_context2.prev = _context2.next) {
                case 0:
                  id = e.currentTarget.dataset.id;
                  _context2.next = 3;
                  return (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.confirmDialog)(((_window$aieData4 = window.aieData) === null || _window$aieData4 === void 0 ? void 0 : (_window$aieData4$i18n = _window$aieData4.i18n) === null || _window$aieData4$i18n === void 0 ? void 0 : _window$aieData4$i18n.confirm_delete) || 'Are you sure you want to delete this function?');
                case 3:
                  confirmed = _context2.sent;
                  if (confirmed) {
                    _this3.deleteFunction(id);
                  }
                case 5:
                case "end":
                  return _context2.stop();
              }
            }
          }, _callee2);
        }));
        return function (_x) {
          return _ref.apply(this, arguments);
        };
      }());
    });
  },
  /**
   * Update pagination controls
   */
  updatePagination: function updatePagination() {
    var currentPageEl = document.querySelector('.aie-current-page');
    var totalPagesEl = document.querySelector('.aie-total-pages');
    var prevBtn = document.querySelector('.aie-prev-page');
    var nextBtn = document.querySelector('.aie-next-page');
    var paginationInfo = document.querySelector('.aie-pagination-info');
    if (currentPageEl) {
      currentPageEl.textContent = this.currentPage;
    }
    if (totalPagesEl) {
      totalPagesEl.textContent = this.totalPages;
    }
    if (prevBtn) {
      prevBtn.disabled = this.currentPage <= 1;
    }
    if (nextBtn) {
      nextBtn.disabled = this.currentPage >= this.totalPages;
    }
    if (paginationInfo) {
      var start = (this.currentPage - 1) * this.perPage + 1;
      var end = Math.min(this.currentPage * this.perPage, this.totalItems);
      paginationInfo.textContent = "Showing ".concat(start, "-").concat(end, " of ").concat(this.totalItems, " functions");
    }
  },
  /**
   * Open function editor modal
   */
  openEditorModal: function openEditorModal() {
    var _arguments = arguments,
      _this4 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee3() {
      var functionId, modal, title, form, codeTextarea, _window$aieData5, _window$aieData5$i18n, _window$aieData6, response, data, _data$data2, func, _window$aieData7, _window$aieData7$i18n, defaultCode;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee3$(_context3) {
        while (1) {
          switch (_context3.prev = _context3.next) {
            case 0:
              functionId = _arguments.length > 0 && _arguments[0] !== undefined ? _arguments[0] : null;
              modal = document.getElementById('aie-function-editor-modal');
              title = modal.querySelector('.aie-modal-title');
              form = document.getElementById('aie-function-form');
              codeTextarea = document.getElementById('aie-function-code');
              if (!(!modal || !form || !codeTextarea)) {
                _context3.next = 7;
                break;
              }
              return _context3.abrupt("return");
            case 7:
              // Clear previous errors
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.clearModalErrors)(modal);

              // Reset form
              form.reset();
              document.getElementById('aie-function-id').value = '';
              document.querySelector('.aie-test-results').style.display = 'none';

              // Clear textarea directly
              codeTextarea.value = '';

              // Show modal and initialize CodeMirror FIRST (before loading data)
              modal.style.display = 'flex';
              document.body.style.overflow = 'hidden';

              // Initialize CodeMirror for code editor if not already initialized
              if (!_this4.codeEditor && window.wp && window.wp.codeEditor) {
                _this4.codeEditor = window.wp.codeEditor.initialize(codeTextarea, {
                  codemirror: {
                    mode: 'php',
                    lineNumbers: true,
                    lineWrapping: true,
                    indentUnit: 4,
                    indentWithTabs: true,
                    autoCloseBrackets: true,
                    matchBrackets: true,
                    styleActiveLine: true,
                    continueComments: true
                  }
                });
              }

              // Clear CodeMirror content after initialization
              if (_this4.codeEditor && _this4.codeEditor.codemirror) {
                _this4.codeEditor.codemirror.setValue('');
                // Force refresh
                _this4.codeEditor.codemirror.clearHistory();
              }
              if (!functionId) {
                _context3.next = 42;
                break;
              }
              // Edit mode - load function data
              title.textContent = ((_window$aieData5 = window.aieData) === null || _window$aieData5 === void 0 ? void 0 : (_window$aieData5$i18n = _window$aieData5.i18n) === null || _window$aieData5$i18n === void 0 ? void 0 : _window$aieData5$i18n.edit_function) || 'Edit Function';
              _context3.prev = 18;
              _context3.next = 21;
              return fetch(window.aieData.ajaxUrl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'aie_functions_get',
                  nonce: ((_window$aieData6 = window.aieData) === null || _window$aieData6 === void 0 ? void 0 : _window$aieData6.nonce) || '',
                  id: functionId
                })
              });
            case 21:
              response = _context3.sent;
              _context3.next = 24;
              return response.json();
            case 24:
              data = _context3.sent;
              if (data.success) {
                _context3.next = 27;
                break;
              }
              throw new Error(data.message || ((_data$data2 = data.data) === null || _data$data2 === void 0 ? void 0 : _data$data2.message) || 'Failed to load function');
            case 27:
              func = data.data;
              document.getElementById('aie-function-id').value = func.id;
              document.getElementById('aie-function-name').value = func.name;
              document.getElementById('aie-function-description').value = func.description || '';
              // Category is now computed (library/custom) - don't set from data
              // document.getElementById( 'aie-function-category' ).value = func.category;
              document.getElementById('aie-function-status').value = func.status;

              // Update CodeMirror with the loaded code
              if (_this4.codeEditor && _this4.codeEditor.codemirror) {
                _this4.codeEditor.codemirror.setValue(func.code || '');
              } else {
                // Fallback to textarea if CodeMirror not initialized
                document.getElementById('aie-function-code').value = func.code || '';
              }
              _context3.next = 40;
              break;
            case 35:
              _context3.prev = 35;
              _context3.t0 = _context3["catch"](18);
              console.error('Error loading function:', _context3.t0);
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(_context3.t0.message, modal);
              return _context3.abrupt("return");
            case 40:
              _context3.next = 45;
              break;
            case 42:
              // Create mode - add default PHP opening tag
              title.textContent = ((_window$aieData7 = window.aieData) === null || _window$aieData7 === void 0 ? void 0 : (_window$aieData7$i18n = _window$aieData7.i18n) === null || _window$aieData7$i18n === void 0 ? void 0 : _window$aieData7$i18n.new_function) || 'New Function';

              // Set default PHP code template
              defaultCode = '<?php\n\n';
              if (_this4.codeEditor && _this4.codeEditor.codemirror) {
                _this4.codeEditor.codemirror.setValue(defaultCode);
                // Position cursor after the opening tag and empty lines
                setTimeout(function () {
                  _this4.codeEditor.codemirror.setCursor({
                    line: 2,
                    ch: 0
                  });
                  _this4.codeEditor.codemirror.focus();
                }, 100);
              } else {
                // Fallback to textarea if CodeMirror not initialized
                codeTextarea.value = defaultCode;
              }
            case 45:
            case "end":
              return _context3.stop();
          }
        }
      }, _callee3, null, [[18, 35]]);
    }))();
  },
  /**
   * Open editor modal with snippet data for customization
   */
  openEditorWithSnippet: function openEditorWithSnippet(snippetData) {
    var _this5 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee4() {
      var modal, title, form, codeTextarea, code;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee4$(_context4) {
        while (1) {
          switch (_context4.prev = _context4.next) {
            case 0:
              modal = document.getElementById('aie-function-editor-modal');
              title = modal.querySelector('.aie-modal-title');
              form = document.getElementById('aie-function-form');
              codeTextarea = document.getElementById('aie-function-code');
              if (!(!modal || !form || !codeTextarea)) {
                _context4.next = 6;
                break;
              }
              return _context4.abrupt("return");
            case 6:
              // Clear previous errors
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.clearModalErrors)(modal);

              // Reset form
              form.reset();
              document.getElementById('aie-function-id').value = '';
              document.querySelector('.aie-test-results').style.display = 'none';

              // Show modal FIRST
              modal.style.display = 'flex';
              document.body.style.overflow = 'hidden';

              // Initialize CodeMirror if not already initialized
              if (!_this5.codeEditor && window.wp && window.wp.codeEditor) {
                _this5.codeEditor = window.wp.codeEditor.initialize(codeTextarea, {
                  codemirror: {
                    mode: 'php',
                    lineNumbers: true,
                    lineWrapping: true,
                    indentUnit: 4,
                    indentWithTabs: true,
                    autoCloseBrackets: true,
                    matchBrackets: true,
                    styleActiveLine: true,
                    continueComments: true
                  }
                });
              }

              // Set title
              title.textContent = 'Customize Function';

              // Fill form with snippet data - always use 'custom' category
              document.getElementById('aie-function-name').value = snippetData.name || '';
              document.getElementById('aie-function-description').value = snippetData.description || '';
              document.getElementById('aie-function-category').value = 'custom';
              document.getElementById('aie-function-status').value = 'active';

              // Prepare code with <?php opening tag
              code = snippetData.code || ''; // Only add <?php if it doesn't already start with it
              if (code && !code.trim().startsWith('<?php') && !code.trim().startsWith('<?')) {
                code = '<?php\n\n' + code;
              }

              // Set code in CodeMirror
              if (_this5.codeEditor && _this5.codeEditor.codemirror) {
                _this5.codeEditor.codemirror.setValue(code);
                // Force refresh to ensure proper rendering
                setTimeout(function () {
                  _this5.codeEditor.codemirror.refresh();
                }, 100);
              } else {
                // Fallback to textarea if CodeMirror not initialized
                codeTextarea.value = code;
              }
            case 21:
            case "end":
              return _context4.stop();
          }
        }
      }, _callee4);
    }))();
  },
  /**
   * Close modal
   */
  closeModal: function closeModal(modal) {
    modal.style.display = 'none';
    document.body.style.overflow = '';
  },
  /**
   * Save function
   */
  saveFunction: function saveFunction() {
    var _this6 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee5() {
      var _window$aieData11;
      var codeTextarea, code, name, category, _window$aieData8, _window$aieData8$i18n, _window$aieData9, _window$aieData9$i18n, _window$aieData10, _window$aieData10$i, functionId, formData, _window$aieData12, _window$aieData12$i, response, contentType, text, data, _data$data3, modal, errorMessage;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee5$(_context5) {
        while (1) {
          switch (_context5.prev = _context5.next) {
            case 0:
              // Clear any previous modal errors
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.clearModalErrors)();

              // Get code from CodeMirror if initialized and sync with textarea
              codeTextarea = document.getElementById('aie-function-code');
              code = codeTextarea.value;
              if (_this6.codeEditor && _this6.codeEditor.codemirror) {
                code = _this6.codeEditor.codemirror.getValue();
                // Sync CodeMirror value back to textarea for validation
                codeTextarea.value = code;
              }

              // Manual validation with user-friendly messages
              name = document.getElementById('aie-function-name').value.trim();
              category = document.getElementById('aie-function-category').value;
              if (name) {
                _context5.next = 10;
                break;
              }
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(((_window$aieData8 = window.aieData) === null || _window$aieData8 === void 0 ? void 0 : (_window$aieData8$i18n = _window$aieData8.i18n) === null || _window$aieData8$i18n === void 0 ? void 0 : _window$aieData8$i18n.name_required) || 'Please enter a function name.');
              document.getElementById('aie-function-name').focus();
              return _context5.abrupt("return");
            case 10:
              if (code.trim()) {
                _context5.next = 14;
                break;
              }
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(((_window$aieData9 = window.aieData) === null || _window$aieData9 === void 0 ? void 0 : (_window$aieData9$i18n = _window$aieData9.i18n) === null || _window$aieData9$i18n === void 0 ? void 0 : _window$aieData9$i18n.code_required) || 'Please enter the PHP code for your function.');
              // Focus on CodeMirror if available, otherwise on textarea
              if (_this6.codeEditor && _this6.codeEditor.codemirror) {
                _this6.codeEditor.codemirror.focus();
              } else {
                codeTextarea.focus();
              }
              return _context5.abrupt("return");
            case 14:
              if (category) {
                _context5.next = 18;
                break;
              }
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(((_window$aieData10 = window.aieData) === null || _window$aieData10 === void 0 ? void 0 : (_window$aieData10$i = _window$aieData10.i18n) === null || _window$aieData10$i === void 0 ? void 0 : _window$aieData10$i.category_required) || 'Please select a category.');
              document.getElementById('aie-function-category').focus();
              return _context5.abrupt("return");
            case 18:
              // Normalize PHP code (add <?php if missing and wrap if needed)
              code = _this6.normalizePhpCode(code);
              functionId = document.getElementById('aie-function-id').value; // Use FormData instead of URLSearchParams to preserve newlines
              formData = new FormData();
              formData.append('action', functionId ? 'aie_functions_update' : 'aie_functions_create');
              formData.append('nonce', ((_window$aieData11 = window.aieData) === null || _window$aieData11 === void 0 ? void 0 : _window$aieData11.nonce) || '');
              formData.append('name', document.getElementById('aie-function-name').value);
              formData.append('description', document.getElementById('aie-function-description').value);
              formData.append('category', document.getElementById('aie-function-category').value);
              formData.append('code', code);
              formData.append('status', document.getElementById('aie-function-status').value);
              if (functionId) {
                formData.append('id', functionId);
              }
              _context5.prev = 29;
              _context5.next = 32;
              return fetch(window.aieData.ajaxUrl, {
                method: 'POST',
                body: formData
              });
            case 32:
              response = _context5.sent;
              // Check if response is JSON
              contentType = response.headers.get('content-type');
              if (!(!contentType || !contentType.includes('application/json'))) {
                _context5.next = 40;
                break;
              }
              _context5.next = 37;
              return response.text();
            case 37:
              text = _context5.sent;
              console.error('Non-JSON response:', text);
              throw new Error('Server error: The function code contains errors that prevent it from being saved. Please check your PHP syntax.');
            case 40:
              _context5.next = 42;
              return response.json();
            case 42:
              data = _context5.sent;
              if (data.success) {
                _context5.next = 46;
                break;
              }
              console.error('Server response error:', data);
              throw new Error(data.message || ((_data$data3 = data.data) === null || _data$data3 === void 0 ? void 0 : _data$data3.message) || 'Failed to save function');
            case 46:
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showNotice)(((_window$aieData12 = window.aieData) === null || _window$aieData12 === void 0 ? void 0 : (_window$aieData12$i = _window$aieData12.i18n) === null || _window$aieData12$i === void 0 ? void 0 : _window$aieData12$i.function_saved) || 'Function saved successfully');
              _this6.closeModal(document.getElementById('aie-function-editor-modal'));
              _this6.loadFunctions();
              _context5.next = 60;
              break;
            case 51:
              _context5.prev = 51;
              _context5.t0 = _context5["catch"](29);
              console.error('Error saving function:', _context5.t0);
              console.error('Error message:', _context5.t0.message);
              modal = document.getElementById('aie-function-editor-modal'); // Improve error message for JSON parse errors
              errorMessage = _context5.t0.message;
              if (errorMessage.includes('Unexpected token') || errorMessage.includes('is not valid JSON')) {
                errorMessage = 'Server error: Unable to save function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.';
              }
              console.log('Final error message:', errorMessage);
              if (modal && modal.style.display === 'flex') {
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(errorMessage, modal);
              } else {
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showError)(errorMessage);
              }
            case 60:
            case "end":
              return _context5.stop();
          }
        }
      }, _callee5, null, [[29, 51]]);
    }))();
  },
  /**
   * Delete function
   */
  deleteFunction: function deleteFunction(functionId) {
    var _this7 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee6() {
      var _window$aieData13, _window$aieData14, _window$aieData14$i, response, data, _data$data4;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee6$(_context6) {
        while (1) {
          switch (_context6.prev = _context6.next) {
            case 0:
              _context6.prev = 0;
              _context6.next = 3;
              return fetch(window.aieData.ajaxUrl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'aie_functions_delete',
                  nonce: ((_window$aieData13 = window.aieData) === null || _window$aieData13 === void 0 ? void 0 : _window$aieData13.nonce) || '',
                  id: functionId
                })
              });
            case 3:
              response = _context6.sent;
              _context6.next = 6;
              return response.json();
            case 6:
              data = _context6.sent;
              if (data.success) {
                _context6.next = 9;
                break;
              }
              throw new Error(data.message || ((_data$data4 = data.data) === null || _data$data4 === void 0 ? void 0 : _data$data4.message) || 'Failed to delete function');
            case 9:
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showNotice)(((_window$aieData14 = window.aieData) === null || _window$aieData14 === void 0 ? void 0 : (_window$aieData14$i = _window$aieData14.i18n) === null || _window$aieData14$i === void 0 ? void 0 : _window$aieData14$i.function_deleted) || 'Function deleted successfully');
              _this7.loadFunctions();
              _context6.next = 17;
              break;
            case 13:
              _context6.prev = 13;
              _context6.t0 = _context6["catch"](0);
              console.error('Error deleting function:', _context6.t0);
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showError)(_context6.t0.message);
            case 17:
            case "end":
              return _context6.stop();
          }
        }
      }, _callee6, null, [[0, 13]]);
    }))();
  },
  /**
   * Test function with sample value
   */
  testFunction: function testFunction() {
    var _this8 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee7() {
      var code, testValueInput, testValue, resultsDiv, modal, _window$aieData15, formData, response, contentType, text, data, _data$data5, errorMessage;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee7$(_context7) {
        while (1) {
          switch (_context7.prev = _context7.next) {
            case 0:
              // Get code from CodeMirror if initialized
              code = document.getElementById('aie-function-code').value;
              if (_this8.codeEditor && _this8.codeEditor.codemirror) {
                code = _this8.codeEditor.codemirror.getValue();
              }
              testValueInput = document.getElementById('aie-test-value');
              testValue = testValueInput.value;
              resultsDiv = document.querySelector('.aie-test-results');
              modal = document.getElementById('aie-function-editor-modal');
              if (code) {
                _context7.next = 9;
                break;
              }
              if (modal && modal.style.display === 'flex') {
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)('Please enter function code first', modal);
              } else {
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showError)('Please enter function code first');
              }
              return _context7.abrupt("return");
            case 9:
              if (!(!testValue || !testValue.trim())) {
                _context7.next = 13;
                break;
              }
              testValueInput.focus();
              testValueInput.select();
              return _context7.abrupt("return");
            case 13:
              // Normalize PHP code (add <?php if missing)
              code = _this8.normalizePhpCode(code);
              _context7.prev = 14;
              // Use FormData to preserve newlines
              formData = new FormData();
              formData.append('action', 'aie_functions_test');
              formData.append('nonce', ((_window$aieData15 = window.aieData) === null || _window$aieData15 === void 0 ? void 0 : _window$aieData15.nonce) || '');
              formData.append('code', code);
              formData.append('value', testValue);
              _context7.next = 22;
              return fetch(window.aieData.ajaxUrl, {
                method: 'POST',
                body: formData
              });
            case 22:
              response = _context7.sent;
              // Check if response is JSON
              contentType = response.headers.get('content-type');
              if (!(!contentType || !contentType.includes('application/json'))) {
                _context7.next = 30;
                break;
              }
              _context7.next = 27;
              return response.text();
            case 27:
              text = _context7.sent;
              console.error('Non-JSON response:', text);
              throw new Error('Server error: The function code contains errors. Please check your PHP syntax.');
            case 30:
              _context7.next = 32;
              return response.json();
            case 32:
              data = _context7.sent;
              if (data.success) {
                _context7.next = 35;
                break;
              }
              throw new Error(data.message || ((_data$data5 = data.data) === null || _data$data5 === void 0 ? void 0 : _data$data5.message) || 'Test failed');
            case 35:
              // Show results
              document.querySelector('.aie-test-input').textContent = data.data.input !== undefined ? data.data.input : testValue;
              document.querySelector('.aie-test-output').textContent = data.data.output !== undefined ? data.data.output : '';
              resultsDiv.style.display = 'block';
              _context7.next = 46;
              break;
            case 40:
              _context7.prev = 40;
              _context7.t0 = _context7["catch"](14);
              console.error('Error testing function:', _context7.t0);

              // Improve error message for JSON parse errors
              errorMessage = _context7.t0.message;
              if (errorMessage.includes('Unexpected token') || errorMessage.includes('is not valid JSON')) {
                errorMessage = 'Server error: Unable to test function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.';
              }
              if (modal && modal.style.display === 'flex') {
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(errorMessage, modal);
              } else {
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showError)(errorMessage);
              }
            case 46:
            case "end":
              return _context7.stop();
          }
        }
      }, _callee7, null, [[14, 40]]);
    }))();
  },
  /**
   * Clear all filters
   */
  clearFilters: function clearFilters() {
    this.filters = {
      search: ''
    };
    document.getElementById('aie-filter-search').value = '';
    this.currentPage = 1;
    this.loadFunctions();
  },
  /**
   * Get category label
   */
  getCategoryLabel: function getCategoryLabel(category) {
    var labels = {
      string: 'String Operations',
      date: 'Date & Time',
      numeric: 'Numeric Operations',
      html: 'HTML Operations',
      wordpress: 'WordPress',
      validation: 'Validation',
      advanced: 'Advanced',
      custom: 'Custom'
    };
    return labels[category] || category;
  },
  /**
   * Get category badge HTML
   */
  getCategoryBadge: function getCategoryBadge(category) {
    if (category === 'library') {
      return '<span class="aie-badge aie-badge-library">Library</span>';
    }
    return '<span class="aie-badge aie-badge-custom">Custom</span>';
  },
  /**
   * Normalize PHP code - ensure it starts with <?php and wrap in function if needed
   */
  normalizePhpCode: function normalizePhpCode(code) {
    if (!code || !code.trim()) {
      return code;
    }
    var trimmedCode = code.trim();

    // Check if code already starts with <?php or <?
    if (trimmedCode.startsWith('<?php') || trimmedCode.startsWith('<?')) {
      // Remove PHP tags for further processing
      trimmedCode = trimmedCode.replace(/^<\?php\s*/i, '').replace(/^<\?\s*/, '').trim(); // Trim again after removing tags
    }

    // Check if code is already a complete function
    var isFunctionDefinition = /^function\s+\w+\s*\(/i.test(trimmedCode);
    if (isFunctionDefinition) {
      // Just add PHP tag
      return '<?php\n' + trimmedCode;
    }

    // Wrap simple expressions/statements in a function
    // Add indentation to each line of user code
    var lines = trimmedCode.split('\n');
    var indentedCode = lines.map(function (line) {
      return '\t' + line;
    }).join('\n');
    return '<?php\n' + 'function transform_value( $value, $args = array() ) {\n' + indentedCode + '\n' + '}';
  },
  /**
   * Get source badge HTML
   */
  getSourceBadge: function getSourceBadge(source) {
    if (source.startsWith('library:')) {
      return '<span class="aie-badge aie-badge-library">Library</span>';
    }
    return '<span class="aie-badge aie-badge-custom">Custom</span>';
  },
  /**
   * Get status badge HTML
   */
  getStatusBadge: function getStatusBadge(status) {
    if (status === 'active') {
      return '<span class="aie-badge aie-badge-active">Active</span>';
    }
    return '<span class="aie-badge aie-badge-inactive">Inactive</span>';
  },
  /**
   * Escape HTML
   */
  escapeHtml: function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (FunctionsModule);

/***/ }),

/***/ "./src/js/modules/import.js":
/*!**********************************!*\
  !*** ./src/js/modules/import.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/regenerator */ "./node_modules/@babel/runtime/regenerator/index.js");
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utils */ "./src/js/modules/utils.js");
/* harmony import */ var _FileUploader__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./FileUploader */ "./src/js/modules/FileUploader.js");
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _unsupportedIterableToArray(arr, i) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }
function _iterableToArrayLimit(arr, i) { var _i = arr == null ? null : typeof Symbol !== "undefined" && arr[Symbol.iterator] || arr["@@iterator"]; if (_i == null) return; var _arr = []; var _n = true; var _d = false; var _s, _e; try { for (_i = _i.call(arr); !(_n = (_s = _i.next()).done); _n = true) { _arr.push(_s.value); if (i && _arr.length === i) break; } } catch (err) { _d = true; _e = err; } finally { try { if (!_n && _i["return"] != null) _i["return"](); } finally { if (_d) throw _e; } } return _arr; }
function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }

function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { Promise.resolve(value).then(_next, _throw); } }
function _asyncToGenerator(fn) { return function () { var self = this, args = arguments; return new Promise(function (resolve, reject) { var gen = fn.apply(self, args); function _next(value) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value); } function _throw(err) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err); } _next(undefined); }); }; }
/**
 * Import Module
 *
 * Handles the import wizard functionality
 */



var ImportModule = {
  // Current wizard state
  currentStep: 1,
  totalSteps: 6,
  uploadedFile: null,
  fileData: null,
  jobId: null,
  progressInterval: null,
  fileUploader: null,
  mappingFunctions: {},
  /**
   * Initialize module
   */
  init: function init() {
    if (!jQuery('#wp-aie-import').length) {
      return;
    }
    this.bindEvents();
    this.showStep(1);
  },
  /**
   * Bind event handlers
   */
  bindEvents: function bindEvents() {
    var _this = this;
    var $wizard = jQuery('#wp-aie-import');

    // Step navigation
    $wizard.on('click', '.aie-next-step', function () {
      return _this.nextStep();
    });
    $wizard.on('click', '.aie-prev-step', function () {
      return _this.prevStep();
    });

    // Content type selection
    $wizard.on('change', 'input[name="content_type"]', function (e) {
      return _this.onContentTypeChange(e);
    });

    // File upload
    jQuery('#aie-select-file').on('click', function () {
      return jQuery('#aie-file-input').click();
    });
    jQuery('#aie-file-input').on('change', function (e) {
      return _this.onFileSelect(e);
    });
    jQuery('.aie-remove-file').on('click', function () {
      return _this.removeFile();
    });

    // Drag & drop
    var $dropZone = jQuery('#aie-upload-area');
    $dropZone.on('dragover', function (e) {
      e.preventDefault();
      $dropZone.addClass('aie-dragover');
    }).on('dragleave', function () {
      $dropZone.removeClass('aie-dragover');
    }).on('drop', function (e) {
      e.preventDefault();
      $dropZone.removeClass('aie-dragover');
      var files = e.originalEvent.dataTransfer.files;
      if (files.length > 0) {
        _this.handleFile(files[0]);
      }
    });

    // CSV delimiter options
    $wizard.on('change', '#csv_delimiter', function (e) {
      _this.onDelimiterChange(e);
      // Reload preview if file is already uploaded
      if (_this.fileData && _this.fileData.file_path) {
        _this.reloadFilePreview();
      }
    });
    $wizard.on('input', '#csv_custom_delimiter', function () {
      _this.validateCustomDelimiter();
    });
    $wizard.on('blur', '#csv_custom_delimiter', function () {
      // Reload preview when custom delimiter is finalized
      if (_this.fileData && _this.fileData.file_path) {
        _this.reloadFilePreview();
      }
    });
    $wizard.on('change', 'input[name="csv_has_header"]', function () {
      // Reload preview when has_header changes
      if (_this.fileData && _this.fileData.file_path) {
        _this.reloadFilePreview();
      }
    });

    // Field mapping
    $wizard.on('click', '.aie-auto-map', function () {
      return _this.autoMapFields();
    });
    $wizard.on('click', '.aie-clear-map', function () {
      return _this.clearFieldMapping();
    });

    // Import actions
    $wizard.on('click', '.aie-start-import', function () {
      return _this.startImport();
    });
    $wizard.on('click', '.aie-cancel-import', function () {
      return _this.cancelImport();
    });
    $wizard.on('click', '.aie-new-import', function () {
      return _this.resetWizard();
    });
    $wizard.on('click', '.aie-toggle-logs', function () {
      return _this.toggleLogs();
    });
  },
  /**
   * Show specific step
   */
  showStep: function showStep(step) {
    var $wizard = jQuery('#wp-aie-import');

    // Hide all steps
    $wizard.find('.aie-step').removeClass('active');

    // Show current step
    $wizard.find(".aie-step-".concat(step)).addClass('active');

    // Update indicators
    $wizard.find('.aie-step-indicator').removeClass('active completed');
    $wizard.find(".aie-step-indicator[data-step=\"".concat(step, "\"]")).addClass('active');
    $wizard.find(".aie-step-indicator[data-step]").filter(function () {
      return jQuery(this).data('step') < step;
    }).addClass('completed');
    this.currentStep = step;

    // Step-specific actions
    if (step === 3) {
      this.loadPreview();

      // Check if there's an error from upload
      if (this.fileData && this.fileData.hasError) {
        // Show error message
        jQuery('.aie-preview-table-container').hide();
        jQuery('.aie-json-preview-container').hide();

        // Show error in preview area
        var errorHtml = "\n\t\t\t\t\t<div class=\"notice notice-error\" style=\"padding: 20px; margin: 20px 0;\">\n\t\t\t\t\t\t<h3 style=\"margin-top: 0;\">\u274C File Validation Failed</h3>\n\t\t\t\t\t\t<p style=\"font-size: 14px;\">".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(this.fileData.error), "</p>\n\t\t\t\t\t\t<p style=\"margin-bottom: 0;\">\n\t\t\t\t\t\t\t<button type=\"button\" class=\"button aie-prev-step\">\n\t\t\t\t\t\t\t\t\u2190 Go Back and Upload a Valid File\n\t\t\t\t\t\t\t</button>\n\t\t\t\t\t\t</p>\n\t\t\t\t\t</div>\n\t\t\t\t");
        jQuery('.aie-step-3 .aie-step-content').prepend(errorHtml);

        // Disable next button
        jQuery('.aie-step-3 .aie-next-step').prop('disabled', true);
      } else {
        // Enable next button if no error
        jQuery('.aie-step-3 .aie-next-step').prop('disabled', false);
      }
    } else if (step === 4) {
      // Disable Next button before building mapping
      jQuery('.aie-next-step').prop('disabled', true);
      this.buildFieldMapping();
    } else if (step === 5) {
      this.populateUniqueFieldOptions();
    }
  },
  /**
   * Go to next step
   */
  nextStep: function nextStep() {
    if (this.currentStep < this.totalSteps) {
      // Validate current step
      if (this.validateStep(this.currentStep)) {
        var contentType = jQuery('input[name="content_type"]:checked').val();

        // For block_theme_settings, skip steps 4 and 5 (go from 3 to 6)
        if (contentType === 'block_theme_settings' && this.currentStep === 3) {
          this.showStep(6); // Skip to import execution
        } else {
          this.showStep(this.currentStep + 1);
        }
      }
    }
  },
  /**
   * Go to previous step
   */
  prevStep: function prevStep() {
    if (this.currentStep > 1) {
      var contentType = jQuery('input[name="content_type"]:checked').val();

      // For block_theme_settings, skip steps 5 and 4 when going back (go from 6 to 3)
      if (contentType === 'block_theme_settings' && this.currentStep === 6) {
        this.showStep(3); // Skip back to preview
      } else {
        this.showStep(this.currentStep - 1);
      }
    }
  },
  /**
   * Validate step
   */
  validateStep: function validateStep(step) {
    switch (step) {
      case 2:
        if (!this.uploadedFile) {
          _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Please upload a file', 'error');
          return false;
        }

        // Validate custom delimiter if selected
        var delimiter = jQuery('#csv_delimiter').val();
        if (delimiter === 'custom') {
          var customDelimiter = jQuery('#csv_custom_delimiter').val().trim();
          if (customDelimiter === '') {
            _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Please enter a custom delimiter', 'error');
            return false;
          }
        }
        break;
      case 4:
        // Validate post type selection for custom post types
        var contentType = jQuery('input[name="content_type"]:checked').val();
        if (contentType === 'custom_post_types') {
          var selectedPostType = jQuery('#aie-custom-post-type').val();
          if (!selectedPostType) {
            _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Please select a post type', 'error');
            return false;
          }
        }

        // Validate field mapping
        var mappedFields = this.getFieldMapping();
        if (!mappedFields || mappedFields.length === 0) {
          _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Please map at least one field', 'error');
          return false;
        }
        break;
    }
    return true;
  },
  /**
   * Handle content type change
   */
  onContentTypeChange: function onContentTypeChange(e) {
    var contentType = jQuery(e.target).val();

    // Show/hide content-specific options
    if (contentType === 'media') {
      jQuery('.aie-post-options').hide();
      jQuery('.aie-media-options').show();
    } else {
      jQuery('.aie-post-options').show();
      jQuery('.aie-media-options').hide();
    }
  },
  /**
   * Handle delimiter dropdown change
   */
  onDelimiterChange: function onDelimiterChange(e) {
    var delimiter = jQuery(e.target).val();
    if (delimiter === 'custom') {
      jQuery('.aie-custom-delimiter-wrapper').show();
      // Validate immediately
      this.validateCustomDelimiter();
    } else {
      jQuery('.aie-custom-delimiter-wrapper').hide();
      // Re-enable next button if file is uploaded and processed
      if (this.fileData && !this.fileData.hasError) {
        jQuery('.aie-step-2 .aie-next-step').prop('disabled', false);
      }
    }
  },
  /**
   * Validate custom delimiter input
   */
  validateCustomDelimiter: function validateCustomDelimiter() {
    var customDelimiter = jQuery('#csv_custom_delimiter').val().trim();
    var delimiter = jQuery('#csv_delimiter').val();

    // Only validate if delimiter is set to custom
    if (delimiter === 'custom') {
      if (customDelimiter === '') {
        // Disable next button if custom delimiter is empty
        jQuery('.aie-step-2 .aie-next-step').prop('disabled', true);
      } else {
        // Enable next button if file is uploaded and custom delimiter is provided
        if (this.fileData && !this.fileData.hasError) {
          jQuery('.aie-step-2 .aie-next-step').prop('disabled', false);
        }
      }
    }
  },
  /**
   * Handle file selection
   */
  onFileSelect: function onFileSelect(e) {
    var file = e.target.files[0];
    if (file) {
      this.handleFile(file);
    }
  },
  /**
   * Handle file upload
   */
  handleFile: function handleFile(file) {
    // Validate file extension only (no size limit with chunked upload)
    var allowedExtensions = ['.csv', '.json'];
    var fileExt = '.' + file.name.split('.').pop().toLowerCase();
    if (!allowedExtensions.includes(fileExt)) {
      _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Invalid file type. Please upload CSV or JSON files only.', 'error');
      return;
    }
    this.uploadedFile = file;

    // Show file info
    jQuery('.aie-upload-placeholder').hide();
    jQuery('.aie-file-info').show();
    jQuery('.aie-file-name').text(file.name);
    jQuery('.aie-file-size').text(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].formatFileSize(file.size));

    // Detect format
    var format = this.detectFormat(file.name);
    jQuery('.aie-file-format').text(format.toUpperCase());

    // Show format options
    if (format === 'csv') {
      jQuery('.aie-format-options').show();
      jQuery('.aie-csv-options').show();
    }

    // Start chunked upload
    this.uploadFileInChunks(file);
  },
  /**
   * Get actual delimiter value (convert 'tab' to \t)
   */
  getDelimiterValue: function getDelimiterValue(delimiter) {
    if (delimiter === 'tab') {
      return '\t';
    }
    return delimiter;
  },
  /**
   * Upload file in chunks
   */
  uploadFileInChunks: function uploadFileInChunks(file) {
    var _this2 = this;
    // Show upload progress
    jQuery('.aie-upload-placeholder').hide();
    jQuery('.aie-file-info').hide();
    jQuery('.aie-upload-progress').show();

    // Collect CSV options if file is CSV
    var fileExt = '.' + file.name.split('.').pop().toLowerCase();
    var csvOptions = {};
    if (fileExt === '.csv') {
      var delimiter = jQuery('#csv_delimiter').val();
      var actualDelimiter = delimiter === 'custom' ? jQuery('#csv_custom_delimiter').val().trim() : this.getDelimiterValue(delimiter);
      csvOptions.delimiter = actualDelimiter;
      csvOptions.has_header = jQuery('input[name="csv_has_header"]').is(':checked');
    }

    // Create uploader instance
    this.fileUploader = new _FileUploader__WEBPACK_IMPORTED_MODULE_2__["default"]({
      chunkSize: 1024 * 1024,
      // 1MB chunks
      additionalData: csvOptions,
      // Pass CSV options to uploader
      onProgress: function onProgress(progress) {
        // Update progress bar
        jQuery('.aie-upload-progress .aie-progress-bar-fill').css('width', progress.progress + '%');
        jQuery('.aie-upload-percentage').text(Math.round(progress.progress) + '%');
        jQuery('.aie-upload-speed').text(_FileUploader__WEBPACK_IMPORTED_MODULE_2__["default"].formatSpeed(progress.speed));
      },
      onComplete: function onComplete(result) {
        console.log('Upload complete, result:', result);

        // Check for validation errors
        if (result.error) {
          console.log('Validation error detected:', result.error);
          _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(result.error, 'error');

          // Show file info but keep upload area visible
          jQuery('.aie-upload-progress').hide();
          jQuery('.aie-upload-placeholder').show();

          // Store error in fileData to show on step 3
          _this2.fileData = {
            error: result.error,
            hasError: true
          };

          // Disable next button due to validation error
          jQuery('.aie-step-2 .aie-next-step').prop('disabled', true);
          return;
        }

        // Upload complete
        _this2.fileData = result;
        console.log('File data stored:', _this2.fileData);

        // Hide upload area completely, show file info
        jQuery('.aie-upload-area').hide();
        jQuery('.aie-file-info').show();

        // Enable next button only if custom delimiter validation passes
        var delimiter = jQuery('#csv_delimiter').val();
        var shouldDisable = delimiter === 'custom' && jQuery('#csv_custom_delimiter').val().trim() === '';
        jQuery('.aie-step-2 .aie-next-step').prop('disabled', shouldDisable);

        // Show success message
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('File uploaded successfully', 'success');

        // Show warning if present
        if (result.warning) {
          _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(result.warning, 'warning');
        }
      },
      onError: function onError(error) {
        // Upload failed
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Upload failed: ' + error.message, 'error');
        _this2.removeFile();
      }
    });

    // Start upload
    this.fileUploader.upload(file);
  },
  /**
   * Remove uploaded file
   */
  removeFile: function removeFile() {
    // Abort upload if in progress
    if (this.fileUploader) {
      this.fileUploader.abort();
      this.fileUploader = null;
    }
    this.uploadedFile = null;
    this.fileData = null;
    jQuery('.aie-file-info').hide();
    jQuery('.aie-upload-area').show();
    jQuery('.aie-format-options').hide();
    jQuery('#aie-file-input').val('');
    jQuery('.aie-step-2 .aie-next-step').prop('disabled', true);
  },
  /**
   * Reload file preview with updated CSV options
   */
  reloadFilePreview: function reloadFilePreview() {
    var _this3 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee() {
      var delimiter, actualDelimiter, csvOptions, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee$(_context) {
        while (1) {
          switch (_context.prev = _context.next) {
            case 0:
              if (!(!_this3.fileData || !_this3.fileData.file_path)) {
                _context.next = 2;
                break;
              }
              return _context.abrupt("return");
            case 2:
              if (!(_this3.fileData.format !== 'csv')) {
                _context.next = 4;
                break;
              }
              return _context.abrupt("return");
            case 4:
              // Collect current CSV options
              delimiter = jQuery('#csv_delimiter').val();
              actualDelimiter = delimiter === 'custom' ? jQuery('#csv_custom_delimiter').val().trim() : _this3.getDelimiterValue(delimiter);
              csvOptions = {
                delimiter: actualDelimiter,
                has_header: jQuery('input[name="csv_has_header"]').is(':checked')
              };
              _context.prev = 7;
              _context.next = 10;
              return jQuery.ajax({
                url: aieData.ajaxUrl,
                method: 'POST',
                data: {
                  action: 'aie_reload_preview',
                  nonce: aieData.nonce,
                  file_path: _this3.fileData.file_path,
                  delimiter: csvOptions.delimiter,
                  has_header: csvOptions.has_header
                }
              });
            case 10:
              response = _context.sent;
              if (response.success) {
                // Update stored file data with new preview
                _this3.fileData.preview = response.data.preview;
                _this3.fileData.columns = response.data.columns;
                _this3.fileData.total_rows = response.data.total_rows;
              }
              _context.next = 17;
              break;
            case 14:
              _context.prev = 14;
              _context.t0 = _context["catch"](7);
              console.error('Error reloading preview:', _context.t0);
            case 17:
            case "end":
              return _context.stop();
          }
        }
      }, _callee, null, [[7, 14]]);
    }))();
  },
  /**
   * Detect file format from filename
   */
  detectFormat: function detectFormat(filename) {
    var ext = filename.split('.').pop().toLowerCase();
    return ['csv', 'json'].includes(ext) ? ext : 'csv';
  },
  /**
   * Load data preview
   */
  loadPreview: function loadPreview() {
    var _this4 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee2() {
      var _this4$fileData$colum;
      var preview, format;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee2$(_context2) {
        while (1) {
          switch (_context2.prev = _context2.next) {
            case 0:
              console.log('loadPreview called, fileData:', _this4.fileData);
              if (_this4.fileData) {
                _context2.next = 4;
                break;
              }
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('No file data available', 'error');
              return _context2.abrupt("return");
            case 4:
              if (!_this4.fileData.hasError) {
                _context2.next = 7;
                break;
              }
              console.log('File has validation error:', _this4.fileData.error);
              return _context2.abrupt("return");
            case 7:
              if (_this4.fileData.preview) {
                _context2.next = 10;
                break;
              }
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('No preview data available', 'error');
              return _context2.abrupt("return");
            case 10:
              preview = _this4.fileData.preview;
              format = _this4.fileData.format || 'csv';
              console.log('Preview data:', preview);
              console.log('Format:', format);

              // Update stats
              jQuery('.aie-total-rows').text(_this4.fileData.total_rows || 0);
              jQuery('.aie-total-columns').text(((_this4$fileData$colum = _this4.fileData.columns) === null || _this4$fileData$colum === void 0 ? void 0 : _this4$fileData$colum.length) || 0);

              // Always show table preview (both CSV and JSON)
              _this4.showTablePreview(preview);
            case 17:
            case "end":
              return _context2.stop();
          }
        }
      }, _callee2);
    }))();
  },
  /**
   * Show JSON preview (expanded first object)
   */
  showJsonPreview: function showJsonPreview(firstObject) {
    // Hide table, show JSON preview
    jQuery('.aie-preview-table-container').hide();
    jQuery('.aie-json-preview-container').show();
    jQuery('.aie-preview-note').text('Showing first object from JSON file');

    // Build JSON preview HTML
    var html = '<div class="aie-json-object">';
    html += '<table class="wp-list-table widefat striped">';
    html += '<thead><tr>';
    html += '<th style="width: 30%;">Field</th>';
    html += '<th style="width: 20%;">Type</th>';
    html += '<th style="width: 50%;">Value</th>';
    html += '</tr></thead><tbody>';

    // Iterate through all fields
    for (var _i = 0, _Object$entries = Object.entries(firstObject); _i < _Object$entries.length; _i++) {
      var _Object$entries$_i = _slicedToArray(_Object$entries[_i], 2),
        key = _Object$entries$_i[0],
        value = _Object$entries$_i[1];
      var type = this.getValueType(value);
      var displayValue = this.formatJsonValue(value);
      html += '<tr>';
      html += "<td><strong>".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(key), "</strong></td>");
      html += "<td><code>".concat(type, "</code></td>");
      html += "<td>".concat(displayValue, "</td>");
      html += '</tr>';
    }
    html += '</tbody></table></div>';
    jQuery('.aie-json-preview').html(html);
  },
  /**
   * Show table preview for CSV
   */
  showTablePreview: function showTablePreview(preview) {
    // Show table, hide JSON preview
    jQuery('.aie-preview-table-container').show();
    jQuery('.aie-json-preview-container').hide();
    jQuery('.aie-preview-note').text('Showing first 5 rows');
    var $table = jQuery('.aie-preview-table');

    // Build table header
    var headerHtml = '<tr>';
    if (preview.headers) {
      preview.headers.forEach(function (header) {
        headerHtml += "<th>".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(header), "</th>");
      });
    }
    headerHtml += '</tr>';
    $table.find('thead').html(headerHtml);

    // Build table body
    var bodyHtml = '';
    if (preview.data) {
      preview.data.forEach(function (row, index) {
        bodyHtml += '<tr>';
        row.forEach(function (cell) {
          // Limit cell content length for preview
          var cellContent = String(cell);

          // If it looks like JSON, format it nicely
          if (cellContent.startsWith('{') || cellContent.startsWith('[')) {
            cellContent = cellContent.substring(0, 150);
            if (String(cell).length > 150) {
              cellContent += '...';
            }
          } else {
            cellContent = cellContent.substring(0, 100);
            if (String(cell).length > 100) {
              cellContent += '...';
            }
          }
          bodyHtml += "<td>".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(cellContent), "</td>");
        });
        bodyHtml += '</tr>';
      });
    }
    $table.find('tbody').html(bodyHtml);

    // Check if table has horizontal scroll
    this.checkTableScroll();
  },
  /**
   * Check if table container has horizontal scroll and add indicator
   */
  checkTableScroll: function checkTableScroll() {
    var $container = jQuery('.aie-preview-table-container');
    if ($container.length) {
      // Use setTimeout to ensure DOM is fully rendered
      setTimeout(function () {
        var container = $container[0];
        var hasScroll = container.scrollWidth > container.clientWidth;
        $container.toggleClass('has-scroll', hasScroll);

        // Add scroll event listener to hide shadow when scrolled to end
        $container.off('scroll.preview').on('scroll.preview', function () {
          var scrollLeft = jQuery(this).scrollLeft();
          var scrollWidth = this.scrollWidth;
          var clientWidth = this.clientWidth;
          var isAtEnd = scrollLeft + clientWidth >= scrollWidth - 5;
          jQuery(this).toggleClass('scrolled-to-end', isAtEnd);
        });
      }, 100);
    }
  },
  /**
   * Get value type for display
   */
  getValueType: function getValueType(value) {
    if (value === null) return 'null';
    if (Array.isArray(value)) return 'array';
    if (_typeof(value) === 'object') return 'object';
    if (typeof value === 'number') return 'number';
    if (typeof value === 'boolean') return 'boolean';
    return 'string';
  },
  /**
   * Format JSON value for display
   */
  formatJsonValue: function formatJsonValue(value) {
    if (value === null) {
      return '<em style="color: #999;">null</em>';
    }
    if (Array.isArray(value)) {
      if (value.length === 0) {
        return '<code>[]</code>';
      }
      var preview = value.slice(0, 3).map(function (v) {
        return JSON.stringify(v);
      }).join(', ');
      var more = value.length > 3 ? " ... +".concat(value.length - 3) : '';
      return "<code>[ ".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(preview)).concat(more, " ]</code>");
    }
    if (_typeof(value) === 'object') {
      var keys = Object.keys(value);
      if (keys.length === 0) {
        return '<code>{}</code>';
      }
      var _preview = keys.slice(0, 2).join(', ');
      var _more = keys.length > 2 ? " ... +".concat(keys.length - 2) : '';
      return "<code>{ ".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(_preview)).concat(_more, " }</code>");
    }
    if (typeof value === 'boolean') {
      return "<code style=\"color: #0073aa;\">".concat(value, "</code>");
    }
    if (typeof value === 'number') {
      return "<code style=\"color: #d63638;\">".concat(value, "</code>");
    }

    // String
    var strValue = String(value);
    var displayValue = strValue.length > 200 ? strValue.substring(0, 200) + '...' : strValue;
    return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(displayValue);
  },
  /**
   * Build field mapping interface (Drag & Drop)
   */
  buildFieldMapping: function buildFieldMapping() {
    console.log('buildFieldMapping called');
    if (!this.fileData || !this.fileData.columns) {
      console.log('No file data or columns');
      return;
    }
    var contentType = jQuery('input[name="content_type"]:checked').val();
    console.log('Content type:', contentType);

    // Show/hide post type selector for custom post types
    this.togglePostTypeSelector(contentType);

    // Show/hide database table selector
    this.toggleDatabaseTableSelector(contentType);

    // Build source fields (from file)
    this.buildSourceFields();

    // Build target fields (WordPress fields or database table columns)
    if (contentType === 'database_table') {
      // Table columns will be loaded after table selection
      jQuery('#aie-target-fields').html('<div class="aie-info">Please select a database table above to see available columns</div>');
    } else {
      this.buildTargetFields(contentType);
    }

    // Load dynamic ACF fields
    this.loadACFFields(contentType);

    // Load dynamic Yoast fields
    this.loadYoastFields(contentType);

    // Initialize drag & drop
    this.initializeDragDrop();

    // Initialize search
    this.initializeFieldSearch();

    // Update stats
    this.updateMappingStats();
  },
  /**
   * Build source fields from uploaded file
   */
  buildSourceFields: function buildSourceFields() {
    var _this5 = this;
    var $container = jQuery('#aie-source-fields');
    var html = '';
    this.fileData.columns.forEach(function (column, index) {
      var _this5$fileData$previ, _this5$fileData$previ2, _this5$fileData$previ3;
      var sampleData = ((_this5$fileData$previ = _this5.fileData.preview) === null || _this5$fileData$previ === void 0 ? void 0 : (_this5$fileData$previ2 = _this5$fileData$previ.data) === null || _this5$fileData$previ2 === void 0 ? void 0 : (_this5$fileData$previ3 = _this5$fileData$previ2[0]) === null || _this5$fileData$previ3 === void 0 ? void 0 : _this5$fileData$previ3[index]) || '';
      var sampleDisplay = String(sampleData).substring(0, 30);
      html += "\n\t\t\t\t<div class=\"aie-field-card\" draggable=\"true\" data-source-field=\"".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(column), "\" data-source-index=\"").concat(index, "\">\n\t\t\t\t\t<div class=\"aie-field-icon\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-media-spreadsheet\"></span>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-field-info\">\n\t\t\t\t\t\t<div class=\"aie-field-name\">").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(column), "</div>\n\t\t\t\t\t\t").concat(sampleDisplay ? "<div class=\"aie-field-sample\">".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(sampleDisplay), "...</div>") : '', "\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t");
    });
    $container.html(html);
  },
  /**
   * Toggle post type selector visibility
   */
  togglePostTypeSelector: function togglePostTypeSelector(contentType) {
    var $selector = jQuery('.aie-post-type-selector');
    console.log('togglePostTypeSelector called with:', contentType);
    console.log('Selector found:', $selector.length);
    console.log('Current display:', $selector.css('display'));
    if (contentType === 'custom_post_types') {
      console.log('Setting display to block');
      $selector.css('display', 'block');
      console.log('After setting, display:', $selector.css('display'));
      this.loadCustomPostTypes();
    } else {
      $selector.css('display', 'none');
    }
  },
  /**
   * Load custom post types via AJAX
   */
  loadCustomPostTypes: function loadCustomPostTypes() {
    var $select = jQuery('#aie-custom-post-type');

    // If already loaded, skip
    if ($select.find('option').length > 1) {
      return;
    }
    jQuery.ajax({
      url: window.aieData.ajaxUrl,
      type: 'POST',
      data: {
        action: 'aie_get_custom_post_types',
        nonce: window.aieData.nonce
      },
      success: function success(response) {
        if (response.success && response.data) {
          var options = '<option value="">-- Select Post Type --</option>';
          response.data.forEach(function (postType) {
            options += "<option value=\"".concat(postType.name, "\">").concat(postType.label, "</option>");
          });
          $select.html(options);
        }
      },
      error: function error(xhr, status, _error) {
        console.error('Error loading post types:', _error);
      }
    });
  },
  /**
   * Toggle database table selector on Step 4
   */
  toggleDatabaseTableSelector: function toggleDatabaseTableSelector(contentType) {
    var $selector = jQuery('.aie-table-selection-section');
    if (contentType === 'database_table') {
      $selector.show();
      this.loadDatabaseTables();
    } else {
      $selector.hide();
    }
  },
  /**
   * Load database tables on Step 4
   */
  loadDatabaseTables: function loadDatabaseTables() {
    var _this6 = this;
    var $select = jQuery('#aie-import-table-name');
    var $spinner = jQuery('.aie-table-selector .spinner');
    var $section = jQuery('.aie-table-selection-section');

    // If already loaded, skip
    if ($select.find('option').length > 1) {
      return;
    }

    // Show section
    $section.show();

    // Show loading
    $select.prop('disabled', true);
    $spinner.addClass('is-active');
    jQuery.ajax({
      url: window.aieData.ajaxUrl,
      type: 'POST',
      data: {
        action: 'aie_get_database_tables',
        nonce: window.aieData.nonce
      },
      success: function success(response) {
        $spinner.removeClass('is-active');
        if (response.success && response.data) {
          var tables = response.data.tables || response.data || [];
          $select.empty();
          $select.append(jQuery('<option>').val('').text('Select a table...'));
          if (!Array.isArray(tables) || tables.length === 0) {
            $select.append(jQuery('<option>').val('').text('No tables found'));
            $select.prop('disabled', true);
            return;
          }
          tables.forEach(function (table) {
            $select.append(jQuery('<option>').val(table.table_name).text(table.label));
          });
          $select.prop('disabled', false);

          // Handle table selection
          $select.off('change').on('change', function () {
            var tableName = $select.val();
            if (tableName) {
              _this6.selectedTableName = tableName;
              _this6.loadTableInfo(tableName);
              _this6.loadTableColumnsForMapping();
            } else {
              jQuery('.aie-table-info').html('').hide();
              jQuery('#aie-target-fields').html('<div class="aie-info">Please select a database table above to see available columns</div>');
            }
          });
        } else {
          $select.html('<option value="">No tables found</option>');
        }
      },
      error: function error(xhr, status, _error2) {
        console.error('Error loading tables:', _error2);
        $spinner.removeClass('is-active');
        $select.html('<option value="">Error loading tables</option>');
      }
    });
  },
  /**
   * Load table info on Step 2
   */
  loadTableInfo: function loadTableInfo(tableName) {
    var $tableInfo = jQuery('.aie-table-info');
    var $columnsList = jQuery('.aie-columns-list');
    var $rowCount = jQuery('.aie-table-row-count');
    var $columnCount = jQuery('.aie-table-column-count');
    $tableInfo.show();
    $columnsList.html('<p>Loading...</p>');
    jQuery.ajax({
      url: window.aieData.ajaxUrl,
      type: 'POST',
      data: {
        action: 'aie_get_table_columns',
        nonce: window.aieData.nonce,
        table_name: tableName
      },
      success: function success(response) {
        if (response.success && response.data) {
          var columns = response.data.columns || [];
          var rowCount = response.data.row_count || 0;
          $rowCount.text(rowCount.toLocaleString());
          $columnCount.text(columns.length);
          var html = '<div class="aie-column-badges">';
          columns.forEach(function (column) {
            html += "<span class=\"aie-column-badge\">".concat(column.name, "</span>");
          });
          html += '</div>';
          $columnsList.html(html);
        }
      },
      error: function error(xhr, status, _error3) {
        console.error('Error loading table columns:', _error3);
        $columnsList.html('<p>Error loading columns</p>');
      }
    });
  },
  /**
   * Load table columns for Step 4 (field mapping)
   */
  loadTableColumnsForMapping: function loadTableColumnsForMapping() {
    var _this7 = this;
    if (!this.selectedTableName) {
      return;
    }
    var $container = jQuery('#aie-target-fields');
    $container.html('<div class="aie-loading">Loading table columns...</div>');
    jQuery.ajax({
      url: window.aieData.ajaxUrl,
      type: 'POST',
      data: {
        action: 'aie_get_table_columns',
        nonce: window.aieData.nonce,
        table_name: this.selectedTableName
      },
      success: function success(response) {
        if (response.success && response.data && response.data.columns) {
          var columns = response.data.columns;
          var html = '<div class="aie-field-group">';
          html += '<div class="aie-field-group-label">Table Columns</div>';
          columns.forEach(function (column) {
            html += "\n\t\t\t\t\t\t\t<div class=\"aie-target-field\" data-target-field=\"".concat(column.name, "\" data-field-type=\"").concat(column.type || 'string', "\">\n\t\t\t\t\t\t\t\t<div class=\"aie-field-icon\">\n\t\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-database\"></span>\n\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t\t<div class=\"aie-field-info\">\n\t\t\t\t\t\t\t\t\t<div class=\"aie-field-label\">").concat(column.name, "</div>\n\t\t\t\t\t\t\t\t\t<span class=\"aie-field-type-badge\">").concat(column.type || 'string', "</span>\n\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t");
          });
          html += '</div>';
          $container.html(html);

          // Re-initialize drag & drop
          _this7.initializeDragDrop();
        } else {
          $container.html('<div class="aie-error">Error loading columns</div>');
        }
      },
      error: function error(xhr, status, _error4) {
        console.error('Error loading columns:', _error4);
        $container.html('<div class="aie-error">Error loading table columns</div>');
      }
    });
  },
  /**
   * Build target WordPress fields
   */
  buildTargetFields: function buildTargetFields(contentType) {
    var $container = jQuery('#aie-target-fields');

    // Get fields for content type
    var fieldGroups = this.getFieldsByContentType(contentType);
    console.log('Field groups:', fieldGroups);
    var html = '';
    fieldGroups.forEach(function (group) {
      html += "<div class=\"aie-field-group\">";
      html += "<div class=\"aie-field-group-label\">".concat(group.label, "</div>");
      group.options.forEach(function (field) {
        // Skip special fields (except template field)
        if (field.value.startsWith('_') && field.value !== '_wp_page_template') {
          return;
        }

        // Custom fields with add button
        if (field.custom) {
          html += "\n\t\t\t\t\t\t<div class=\"aie-target-field aie-custom-field-template\" data-field-type=\"".concat(field.type || 'string', "\" data-multiple=\"").concat(field.multiple || false, "\">\n\t\t\t\t\t\t\t<div class=\"aie-field-icon\">\n\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-plus\"></span>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t<div class=\"aie-field-info\">\n\t\t\t\t\t\t\t\t<div class=\"aie-field-label\">").concat(field.label, "</div>\n\t\t\t\t\t\t\t\t<button type=\"button\" class=\"aie-add-custom-field button button-small\">+ Add</button>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t");
        } else {
          html += "\n\t\t\t\t\t\t<div class=\"aie-target-field\" data-target-field=\"".concat(field.value, "\" data-field-type=\"").concat(field.type || 'string', "\" data-multiple=\"").concat(field.multiple || false, "\">\n\t\t\t\t\t\t\t<div class=\"aie-field-icon\">\n\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-wordpress\"></span>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t<div class=\"aie-field-info\">\n\t\t\t\t\t\t\t\t<div class=\"aie-field-label\">").concat(field.label, "</div>\n\t\t\t\t\t\t\t\t<span class=\"aie-field-type-badge\">").concat(field.type || 'string', "</span>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t");
        }
      });
      html += "</div>";
    });
    $container.html(html);

    // Initialize custom field add buttons
    this.initCustomFieldButtons();
  },
  /**
   * Initialize custom field add buttons
   */
  initCustomFieldButtons: function initCustomFieldButtons() {
    var self = this;
    jQuery('.aie-add-custom-field').off('click').on('click', function () {
      var $button = jQuery(this);
      var $template = $button.closest('.aie-custom-field-template');
      var fieldType = $template.data('field-type');
      var isMultiple = $template.data('multiple');

      // Show modal to add custom field
      self.showCustomFieldModal($template, fieldType, isMultiple);
    });
  },
  /**
   * Show modal to add custom taxonomy or meta field
   */
  showCustomFieldModal: function showCustomFieldModal($template, fieldType, isMultiple) {
    var self = this;
    var isTaxonomy = fieldType === 'taxonomy';
    var isMeta = fieldType === 'meta';
    var title = isTaxonomy ? 'Add Taxonomy Field' : 'Add Custom Field';
    var placeholder = isTaxonomy ? 'Enter taxonomy slug (e.g., category, post_tag, product_cat)' : 'Enter field key (e.g., _custom_price)';
    var icon = isTaxonomy ? 'dashicons-category' : 'dashicons-admin-plugins';

    // Taxonomy format options
    var taxonomyFormatField = isTaxonomy ? "\n\t\t\t<label style=\"margin-top: 15px;\">\n\t\t\t\t<strong>Data Format:</strong>\n\t\t\t\t<select class=\"aie-taxonomy-format regular-text\">\n\t\t\t\t\t<option value=\"id\">Term ID (e.g., 5, 12, 23)</option>\n\t\t\t\t\t<option value=\"slug\">Term Slug (e.g., technology, news)</option>\n\t\t\t\t\t<option value=\"name\" selected>Term Name (e.g., Technology, News)</option>\n\t\t\t\t</select>\n\t\t\t\t<p class=\"description\" style=\"margin-top: 5px;\">\n\t\t\t\t\tSelect the format of taxonomy data in your CSV file.\n\t\t\t\t</p>\n\t\t\t</label>\n\t\t" : '';

    // Create modal HTML (same structure as function modal)
    var modalHtml = "\n\t\t\t<div id=\"aie-custom-field-modal\" class=\"aie-modal\" style=\"display:flex;\">\n\t\t\t\t<div class=\"aie-modal-backdrop\"></div>\n\t\t\t\t<div class=\"aie-modal-content aie-custom-field-modal-content\">\n\t\t\t\t\t<div class=\"aie-modal-header\">\n\t\t\t\t\t\t<h2 class=\"aie-modal-title\">\n\t\t\t\t\t\t\t<span class=\"dashicons ".concat(icon, "\"></span>\n\t\t\t\t\t\t\t").concat(title, "\n\t\t\t\t\t\t</h2>\n\t\t\t\t\t\t<button type=\"button\" class=\"aie-modal-close\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t\t\t</button>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-modal-body\">\n\t\t\t\t\t\t<label>\n\t\t\t\t\t\t\t<strong>Taxonomy Slug:</strong>\n\t\t\t\t\t\t\t<input type=\"text\" class=\"aie-custom-field-input regular-text\" placeholder=\"").concat(placeholder, "\" />\n\t\t\t\t\t\t\t").concat(isTaxonomy ? '<p class="description" style="margin-top: 5px;">The slug of the taxonomy (category, post_tag, or custom taxonomy).</p>' : '', "\n\t\t\t\t\t\t</label>\n\t\t\t\t\t\t").concat(taxonomyFormatField, "\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-modal-footer\">\n\t\t\t\t\t\t<button type=\"button\" class=\"button aie-modal-cancel\">Cancel</button>\n\t\t\t\t\t\t<button type=\"button\" class=\"button button-primary aie-modal-add\">Add Field</button>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t");

    // Add modal to body
    jQuery('body').append(modalHtml);
    var $modal = jQuery('#aie-custom-field-modal');
    var $backdrop = $modal.find('.aie-modal-backdrop');
    var $input = $modal.find('.aie-custom-field-input');

    // Focus input
    setTimeout(function () {
      return $input.focus();
    }, 100);

    // Close modal handlers
    $modal.find('.aie-modal-close, .aie-modal-cancel').on('click', function () {
      $modal.remove();
    });
    $backdrop.on('click', function () {
      $modal.remove();
    });

    // Add field handler
    $modal.find('.aie-modal-add').on('click', function () {
      var fieldValue = $input.val().trim();
      if (!fieldValue) {
        alert('Please enter a field name');
        return;
      }

      // Get taxonomy format if applicable
      var taxonomyFormat = 'name';
      if (isTaxonomy) {
        taxonomyFormat = $modal.find('.aie-taxonomy-format').val();
      }

      // Create new field card
      self.addCustomFieldToGroup($template, fieldValue, fieldType, isMultiple, taxonomyFormat);
      $modal.remove();
    });

    // Enter key to add
    $input.on('keypress', function (e) {
      if (e.which === 13) {
        $modal.find('.aie-modal-add').click();
      }
    });
  },
  /**
   * Add custom field to group
   */
  addCustomFieldToGroup: function addCustomFieldToGroup($template, fieldValue, fieldType, isMultiple, taxonomyFormat) {
    var $group = $template.closest('.aie-field-group');
    var isTaxonomy = fieldType === 'taxonomy';

    // Create label with format info for taxonomy
    var label, badge;
    if (isTaxonomy) {
      var formatLabels = {
        id: 'ID',
        slug: 'Slug',
        name: 'Name'
      };
      label = "".concat(fieldValue);
      badge = "taxonomy (".concat(formatLabels[taxonomyFormat] || 'Name', ")");
    } else {
      label = fieldValue;
      badge = 'meta';
    }

    // Store taxonomy format in data attribute
    var taxonomyFormatAttr = isTaxonomy ? "data-taxonomy-format=\"".concat(taxonomyFormat, "\"") : '';
    var fieldHtml = "\n\t\t\t<div class=\"aie-target-field\" data-target-field=\"".concat(fieldValue, "\" data-field-type=\"").concat(fieldType, "\" data-multiple=\"").concat(isMultiple, "\" ").concat(taxonomyFormatAttr, ">\n\t\t\t\t<div class=\"aie-field-icon\">\n\t\t\t\t\t<span class=\"dashicons ").concat(isTaxonomy ? 'dashicons-category' : 'dashicons-admin-plugins', "\"></span>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-field-info\">\n\t\t\t\t\t<div class=\"aie-field-label\">").concat(label, "</div>\n\t\t\t\t\t<span class=\"aie-field-type-badge\">").concat(badge, "</span>\n\t\t\t\t\t<button type=\"button\" class=\"aie-remove-custom-field\" title=\"Remove\">&times;</button>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t");

    // Insert before template
    $template.before(fieldHtml);

    // Add remove handler
    $group.find('.aie-remove-custom-field').off('click').on('click', function () {
      jQuery(this).closest('.aie-target-field').remove();
    });
  },
  /**
   * Get fields by content type (import-compatible fields)
   */
  getFieldsByContentType: function getFieldsByContentType(contentType) {
    var baseFields = [{
      label: 'Standard',
      options: [{
        value: 'post_title',
        label: 'Title',
        type: 'string'
      }, {
        value: 'post_content',
        label: 'Content',
        type: 'string'
      }, {
        value: 'post_excerpt',
        label: 'Excerpt',
        type: 'string'
      }, {
        value: 'post_date',
        label: 'Date',
        type: 'date'
      }, {
        value: 'post_status',
        label: 'Status',
        type: 'string'
      }, {
        value: 'post_name',
        label: 'Slug',
        type: 'string'
      }, {
        value: '_wp_page_template',
        label: 'Template',
        type: 'string'
      }]
    }, {
      label: 'Author',
      options: [{
        value: 'post_author',
        label: 'Author ID',
        type: 'number'
      }, {
        value: 'author_email',
        label: 'Author Email',
        type: 'email'
      }]
    }, {
      label: 'Media',
      options: [{
        value: 'featured_image',
        label: 'Featured Image URL',
        type: 'string'
      }, {
        value: 'featured_image_id',
        label: 'Featured Image ID',
        type: 'number'
      }]
    }, {
      label: 'Other',
      options: [{
        value: 'comment_status',
        label: 'Comment Status',
        type: 'string'
      }, {
        value: 'post_modified',
        label: 'Modified Date',
        type: 'date'
      }, {
        value: 'menu_order',
        label: 'Menu Order',
        type: 'number'
      }, {
        value: 'post_parent',
        label: 'Parent ID',
        type: 'number'
      }]
    }];

    // Media
    if (contentType === 'media') {
      return [{
        label: 'Basic',
        options: [{
          value: 'post_title',
          label: 'Title',
          type: 'string'
        }, {
          value: 'post_content',
          label: 'Description',
          type: 'string'
        }, {
          value: 'post_excerpt',
          label: 'Caption',
          type: 'string'
        }, {
          value: 'alt_text',
          label: 'Alt Text',
          type: 'string'
        }]
      }, {
        label: 'File',
        options: [{
          value: 'file_url',
          label: 'File URL',
          type: 'url'
        }, {
          value: 'file_name',
          label: 'File Name',
          type: 'string'
        }, {
          value: 'post_mime_type',
          label: 'MIME Type',
          type: 'string'
        }]
      }, {
        label: 'Image',
        options: [{
          value: 'width',
          label: 'Width (px)',
          type: 'number'
        }, {
          value: 'height',
          label: 'Height (px)',
          type: 'number'
        }]
      }, {
        label: 'Other',
        options: [{
          value: 'post_date',
          label: 'Upload Date',
          type: 'date'
        }, {
          value: 'post_author',
          label: 'Author ID',
          type: 'number'
        }, {
          value: 'post_parent',
          label: 'Attached To (Post ID)',
          type: 'number'
        }]
      }, {
        label: 'Custom Fields (Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }

    // Pages (no taxonomies, only custom fields)
    if (contentType === 'page') {
      return [].concat(baseFields, [{
        label: 'Custom Fields (Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }]);
    }

    // Users
    if (contentType === 'user') {
      return [{
        label: 'Basic',
        options: [{
          value: 'user_login',
          label: 'Username',
          type: 'string'
        }, {
          value: 'user_email',
          label: 'Email',
          type: 'email'
        }, {
          value: 'user_pass',
          label: 'Password',
          type: 'string'
        }, {
          value: 'display_name',
          label: 'Display Name',
          type: 'string'
        }, {
          value: 'user_nicename',
          label: 'Nice Name (Slug)',
          type: 'string'
        }]
      }, {
        label: 'Profile',
        options: [{
          value: 'first_name',
          label: 'First Name',
          type: 'string'
        }, {
          value: 'last_name',
          label: 'Last Name',
          type: 'string'
        }, {
          value: 'nickname',
          label: 'Nickname',
          type: 'string'
        }, {
          value: 'description',
          label: 'Bio',
          type: 'string'
        }, {
          value: 'user_url',
          label: 'Website',
          type: 'url'
        }]
      }, {
        label: 'Role',
        options: [{
          value: 'role',
          label: 'Role',
          type: 'string'
        }]
      }, {
        label: 'Other',
        options: [{
          value: 'user_registered',
          label: 'Registration Date',
          type: 'date'
        }, {
          value: 'locale',
          label: 'Language',
          type: 'string'
        }]
      }, {
        label: 'Custom Fields (User Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }

    // WooCommerce Products
    if (contentType === 'woo_product') {
      return [{
        label: 'Basic',
        options: [{
          value: 'ID',
          label: 'Product ID',
          type: 'number'
        }, {
          value: 'post_title',
          label: 'Product Name',
          type: 'string',
          required: true
        }, {
          value: 'post_name',
          label: 'Slug',
          type: 'string'
        }, {
          value: 'post_status',
          label: 'Status',
          type: 'string'
        }, {
          value: 'sku',
          label: 'SKU',
          type: 'string'
        }, {
          value: 'post_author',
          label: 'Author ID',
          type: 'number'
        }]
      }, {
        label: 'Content',
        options: [{
          value: 'post_content',
          label: 'Description',
          type: 'string'
        }, {
          value: 'post_excerpt',
          label: 'Short Description',
          type: 'string'
        }]
      }, {
        label: 'Pricing',
        options: [{
          value: 'regular_price',
          label: 'Regular Price',
          type: 'number'
        }, {
          value: 'sale_price',
          label: 'Sale Price',
          type: 'number'
        }, {
          value: 'date_on_sale_from',
          label: 'Sale Start Date',
          type: 'datetime'
        }, {
          value: 'date_on_sale_to',
          label: 'Sale End Date',
          type: 'datetime'
        }, {
          value: 'tax_status',
          label: 'Tax Status',
          type: 'string'
        }, {
          value: 'tax_class',
          label: 'Tax Class',
          type: 'string'
        }]
      }, {
        label: 'Inventory',
        options: [{
          value: 'stock_quantity',
          label: 'Stock Quantity',
          type: 'number'
        }, {
          value: 'stock_status',
          label: 'Stock Status',
          type: 'string'
        }, {
          value: 'manage_stock',
          label: 'Manage Stock',
          type: 'boolean'
        }, {
          value: 'backorders',
          label: 'Backorders',
          type: 'string'
        }, {
          value: 'sold_individually',
          label: 'Sold Individually',
          type: 'boolean'
        }, {
          value: 'low_stock_amount',
          label: 'Low Stock Threshold',
          type: 'number'
        }]
      }, {
        label: 'Product Type',
        options: [{
          value: 'product_type',
          label: 'Product Type',
          type: 'string'
        }, {
          value: 'downloadable',
          label: 'Downloadable',
          type: 'boolean'
        }, {
          value: 'virtual',
          label: 'Virtual',
          type: 'boolean'
        }]
      }, {
        label: 'Shipping',
        options: [{
          value: 'weight',
          label: 'Weight',
          type: 'number'
        }, {
          value: 'length',
          label: 'Length',
          type: 'number'
        }, {
          value: 'width',
          label: 'Width',
          type: 'number'
        }, {
          value: 'height',
          label: 'Height',
          type: 'number'
        }, {
          value: 'shipping_class',
          label: 'Shipping Class',
          type: 'string'
        }]
      }, {
        label: 'Media',
        options: [{
          value: 'featured_image',
          label: 'Featured Image URL',
          type: 'string'
        }, {
          value: 'featured_image_id',
          label: 'Featured Image ID',
          type: 'number'
        }, {
          value: 'product_gallery',
          label: 'Gallery Image URLs',
          type: 'string'
        }]
      }, {
        label: 'Linked Products',
        options: [{
          value: 'upsell_ids',
          label: 'Upsell IDs',
          type: 'string'
        }, {
          value: 'cross_sell_ids',
          label: 'Cross-sell IDs',
          type: 'string'
        }, {
          value: 'parent_id',
          label: 'Parent Product ID',
          type: 'number'
        }]
      }, {
        label: 'Attributes',
        options: [{
          value: 'attributes',
          label: 'Product Attributes',
          type: 'string'
        }, {
          value: 'default_attributes',
          label: 'Default Attributes',
          type: 'string'
        }]
      }, {
        label: 'Reviews',
        options: [{
          value: 'average_rating',
          label: 'Average Rating',
          type: 'number'
        }, {
          value: 'review_count',
          label: 'Review Count',
          type: 'number'
        }, {
          value: 'comment_status',
          label: 'Reviews Enabled',
          type: 'string'
        }]
      }, {
        label: 'Visibility',
        options: [{
          value: 'featured',
          label: 'Featured',
          type: 'boolean'
        }, {
          value: 'catalog_visibility',
          label: 'Catalog Visibility',
          type: 'string'
        }, {
          value: 'total_sales',
          label: 'Total Sales',
          type: 'number'
        }, {
          value: 'menu_order',
          label: 'Menu Order',
          type: 'number'
        }]
      }, {
        label: 'Dates',
        options: [{
          value: 'post_date',
          label: 'Created Date',
          type: 'datetime'
        }, {
          value: 'post_modified',
          label: 'Modified Date',
          type: 'datetime'
        }]
      }, {
        label: 'Taxonomies',
        options: [{
          value: 'taxonomy',
          label: 'Taxonomy',
          type: 'taxonomy',
          custom: true
        }]
      }, {
        label: 'Custom Fields (Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }

    // WooCommerce Orders
    if (contentType === 'woo_order') {
      return [{
        label: 'Basic',
        options: [{
          value: 'order_id',
          label: 'Order ID',
          type: 'number'
        }, {
          value: 'order_number',
          label: 'Order Number',
          type: 'string'
        }, {
          value: 'order_key',
          label: 'Order Key',
          type: 'string'
        }, {
          value: 'status',
          label: 'Order Status',
          type: 'string'
        }, {
          value: 'currency',
          label: 'Currency',
          type: 'string'
        }, {
          value: 'payment_method',
          label: 'Payment Method',
          type: 'string'
        }, {
          value: 'payment_method_title',
          label: 'Payment Method Title',
          type: 'string'
        }]
      }, {
        label: 'Customer',
        options: [{
          value: 'customer_id',
          label: 'Customer ID',
          type: 'number'
        }, {
          value: 'customer_email',
          label: 'Customer Email',
          type: 'email'
        }, {
          value: 'customer_user_agent',
          label: 'User Agent',
          type: 'string'
        }, {
          value: 'customer_ip_address',
          label: 'Customer IP',
          type: 'string'
        }]
      }, {
        label: 'Billing Address',
        options: [{
          value: 'billing_first_name',
          label: 'First Name',
          type: 'string'
        }, {
          value: 'billing_last_name',
          label: 'Last Name',
          type: 'string'
        }, {
          value: 'billing_company',
          label: 'Company',
          type: 'string'
        }, {
          value: 'billing_address_1',
          label: 'Address Line 1',
          type: 'string'
        }, {
          value: 'billing_address_2',
          label: 'Address Line 2',
          type: 'string'
        }, {
          value: 'billing_city',
          label: 'City',
          type: 'string'
        }, {
          value: 'billing_state',
          label: 'State/Province',
          type: 'string'
        }, {
          value: 'billing_postcode',
          label: 'Postcode',
          type: 'string'
        }, {
          value: 'billing_country',
          label: 'Country',
          type: 'string'
        }, {
          value: 'billing_email',
          label: 'Email',
          type: 'email'
        }, {
          value: 'billing_phone',
          label: 'Phone',
          type: 'string'
        }]
      }, {
        label: 'Shipping Address',
        options: [{
          value: 'shipping_first_name',
          label: 'First Name',
          type: 'string'
        }, {
          value: 'shipping_last_name',
          label: 'Last Name',
          type: 'string'
        }, {
          value: 'shipping_company',
          label: 'Company',
          type: 'string'
        }, {
          value: 'shipping_address_1',
          label: 'Address Line 1',
          type: 'string'
        }, {
          value: 'shipping_address_2',
          label: 'Address Line 2',
          type: 'string'
        }, {
          value: 'shipping_city',
          label: 'City',
          type: 'string'
        }, {
          value: 'shipping_state',
          label: 'State/Province',
          type: 'string'
        }, {
          value: 'shipping_postcode',
          label: 'Postcode',
          type: 'string'
        }, {
          value: 'shipping_country',
          label: 'Country',
          type: 'string'
        }]
      }, {
        label: 'Order Items',
        options: [{
          value: 'line_items',
          label: 'Line Items (JSON)',
          type: 'string'
        }, {
          value: 'shipping_lines',
          label: 'Shipping Lines (JSON)',
          type: 'string'
        }, {
          value: 'fee_lines',
          label: 'Fee Lines (JSON)',
          type: 'string'
        }, {
          value: 'coupon_lines',
          label: 'Coupon Lines (JSON)',
          type: 'string'
        }]
      }, {
        label: 'Totals',
        options: [{
          value: 'order_total',
          label: 'Order Total',
          type: 'number'
        }, {
          value: 'order_subtotal',
          label: 'Subtotal',
          type: 'number'
        }, {
          value: 'order_tax',
          label: 'Tax Total',
          type: 'number'
        }, {
          value: 'order_shipping',
          label: 'Shipping Total',
          type: 'number'
        }, {
          value: 'order_shipping_tax',
          label: 'Shipping Tax',
          type: 'number'
        }, {
          value: 'cart_discount',
          label: 'Cart Discount',
          type: 'number'
        }, {
          value: 'cart_discount_tax',
          label: 'Cart Discount Tax',
          type: 'number'
        }]
      }, {
        label: 'Shipping',
        options: [{
          value: 'shipping_method',
          label: 'Shipping Method',
          type: 'string'
        }, {
          value: 'shipping_method_title',
          label: 'Shipping Method Title',
          type: 'string'
        }]
      }, {
        label: 'Dates',
        options: [{
          value: 'date_created',
          label: 'Date Created',
          type: 'datetime'
        }, {
          value: 'date_modified',
          label: 'Date Modified',
          type: 'datetime'
        }, {
          value: 'date_completed',
          label: 'Date Completed',
          type: 'datetime'
        }, {
          value: 'date_paid',
          label: 'Date Paid',
          type: 'datetime'
        }]
      }, {
        label: 'Other',
        options: [{
          value: 'transaction_id',
          label: 'Transaction ID',
          type: 'string'
        }, {
          value: 'customer_note',
          label: 'Customer Note',
          type: 'string'
        }, {
          value: 'created_via',
          label: 'Created Via',
          type: 'string'
        }, {
          value: 'version',
          label: 'WooCommerce Version',
          type: 'string'
        }, {
          value: 'prices_include_tax',
          label: 'Prices Include Tax',
          type: 'boolean'
        }]
      }, {
        label: 'Custom Fields (Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }

    // WooCommerce Coupons
    if (contentType === 'woo_coupon') {
      return [{
        label: 'Basic',
        options: [{
          value: 'coupon_code',
          label: 'Coupon Code',
          type: 'string',
          required: true
        }, {
          value: 'description',
          label: 'Description',
          type: 'string'
        }, {
          value: 'discount_type',
          label: 'Discount Type',
          type: 'string'
        }, {
          value: 'coupon_amount',
          label: 'Coupon Amount',
          type: 'number'
        }, {
          value: 'free_shipping',
          label: 'Free Shipping',
          type: 'boolean'
        }]
      }, {
        label: 'Usage Restrictions',
        options: [{
          value: 'minimum_amount',
          label: 'Minimum Spend',
          type: 'number'
        }, {
          value: 'maximum_amount',
          label: 'Maximum Spend',
          type: 'number'
        }, {
          value: 'individual_use',
          label: 'Individual Use Only',
          type: 'boolean'
        }, {
          value: 'exclude_sale_items',
          label: 'Exclude Sale Items',
          type: 'boolean'
        }, {
          value: 'product_ids',
          label: 'Product IDs',
          type: 'string'
        }, {
          value: 'excluded_product_ids',
          label: 'Excluded Product IDs',
          type: 'string'
        }, {
          value: 'product_categories',
          label: 'Product Categories',
          type: 'string'
        }, {
          value: 'excluded_product_categories',
          label: 'Excluded Product Categories',
          type: 'string'
        }, {
          value: 'email_restrictions',
          label: 'Allowed Emails',
          type: 'string'
        }]
      }, {
        label: 'Usage Limits',
        options: [{
          value: 'usage_limit',
          label: 'Usage Limit Per Coupon',
          type: 'number'
        }, {
          value: 'usage_limit_per_user',
          label: 'Usage Limit Per User',
          type: 'number'
        }, {
          value: 'limit_usage_to_x_items',
          label: 'Limit Usage to X Items',
          type: 'number'
        }, {
          value: 'usage_count',
          label: 'Usage Count',
          type: 'number'
        }]
      }, {
        label: 'Dates',
        options: [{
          value: 'date_expires',
          label: 'Expiry Date',
          type: 'datetime'
        }, {
          value: 'date_created',
          label: 'Date Created',
          type: 'datetime'
        }, {
          value: 'date_modified',
          label: 'Date Modified',
          type: 'datetime'
        }]
      }, {
        label: 'Custom Fields (Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }

    // WooCommerce Attributes
    if (contentType === 'woo_attribute') {
      return [{
        label: 'Attribute',
        options: [{
          value: 'attribute_name',
          label: 'Attribute Name',
          type: 'string',
          required: true
        }, {
          value: 'attribute_label',
          label: 'Attribute Label',
          type: 'string'
        }, {
          value: 'attribute_type',
          label: 'Attribute Type',
          type: 'string'
        }, {
          value: 'attribute_orderby',
          label: 'Order By',
          type: 'string'
        }, {
          value: 'attribute_public',
          label: 'Enable Archives',
          type: 'boolean'
        }]
      }, {
        label: 'Terms',
        options: [{
          value: 'terms',
          label: 'Attribute Terms (comma-separated)',
          type: 'string'
        }]
      }];
    }

    // Menus
    if (contentType === 'menu') {
      return [{
        label: 'Menu Item',
        options: [{
          value: 'menu_item_title',
          label: 'Title',
          type: 'string'
        }, {
          value: 'menu_item_url',
          label: 'URL',
          type: 'url'
        }, {
          value: 'menu_item_type',
          label: 'Type',
          type: 'string'
        }, {
          value: 'menu_item_object',
          label: 'Object',
          type: 'string'
        }, {
          value: 'menu_item_object_id',
          label: 'Object ID',
          type: 'number'
        }]
      }, {
        label: 'Structure',
        options: [{
          value: 'menu_item_parent',
          label: 'Parent ID',
          type: 'number'
        }, {
          value: 'menu_order',
          label: 'Order',
          type: 'number'
        }]
      }, {
        label: 'Settings',
        options: [{
          value: 'menu_item_target',
          label: 'Target (_blank, _self)',
          type: 'string'
        }, {
          value: 'menu_item_classes',
          label: 'CSS Classes',
          type: 'string'
        }, {
          value: 'menu_item_xfn',
          label: 'Link Relationship (XFN)',
          type: 'string'
        }, {
          value: 'menu_item_description',
          label: 'Description',
          type: 'string'
        }]
      }, {
        label: 'Custom Fields (Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }

    // Comments
    if (contentType === 'comment') {
      return [{
        label: 'Basic',
        options: [{
          value: 'comment_content',
          label: 'Comment Content',
          type: 'string'
        }, {
          value: 'comment_author',
          label: 'Author Name',
          type: 'string'
        }, {
          value: 'comment_author_email',
          label: 'Author Email',
          type: 'email'
        }, {
          value: 'comment_author_url',
          label: 'Author Website',
          type: 'url'
        }]
      }, {
        label: 'Status',
        options: [{
          value: 'comment_approved',
          label: 'Status (0, 1, spam, trash)',
          type: 'string'
        }, {
          value: 'comment_type',
          label: 'Type (comment, pingback, trackback)',
          type: 'string'
        }]
      }, {
        label: 'Post',
        options: [{
          value: 'comment_post_ID',
          label: 'Post ID',
          type: 'number'
        }]
      }, {
        label: 'Other',
        options: [{
          value: 'comment_date',
          label: 'Date',
          type: 'date'
        }, {
          value: 'comment_parent',
          label: 'Parent Comment ID',
          type: 'number'
        }, {
          value: 'comment_author_IP',
          label: 'Author IP',
          type: 'string'
        }, {
          value: 'user_id',
          label: 'User ID',
          type: 'number'
        }]
      }, {
        label: 'Custom Fields (Comment Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }

    // Taxonomy Terms
    if (contentType === 'taxonomy') {
      return [{
        label: 'Basic',
        options: [{
          value: 'term_name',
          label: 'Name',
          type: 'string'
        }, {
          value: 'term_slug',
          label: 'Slug',
          type: 'string'
        }, {
          value: 'term_description',
          label: 'Description',
          type: 'string'
        }]
      }, {
        label: 'Taxonomy',
        options: [{
          value: 'taxonomy',
          label: 'Taxonomy',
          type: 'string'
        }]
      }, {
        label: 'Hierarchy',
        options: [{
          value: 'parent_term_id',
          label: 'Parent Term ID',
          type: 'number'
        }, {
          value: 'parent_term_slug',
          label: 'Parent Term Slug',
          type: 'string'
        }]
      }, {
        label: 'Custom Fields (Term Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }
    if (contentType === 'custom_post_types') {
      return [{
        label: 'Standard',
        options: [{
          value: 'post_title',
          label: 'Title',
          type: 'string',
          required: true
        }, {
          value: 'post_content',
          label: 'Content',
          type: 'string'
        }, {
          value: 'post_excerpt',
          label: 'Excerpt',
          type: 'string'
        }, {
          value: 'post_status',
          label: 'Status',
          type: 'string'
        }, {
          value: 'post_date',
          label: 'Date',
          type: 'datetime'
        }, {
          value: 'post_modified',
          label: 'Modified Date',
          type: 'datetime'
        }, {
          value: 'menu_order',
          label: 'Menu Order',
          type: 'number'
        }, {
          value: 'post_slug',
          label: 'Slug',
          type: 'string'
        }, {
          value: 'comment_status',
          label: 'Comment Status',
          type: 'string'
        }, {
          value: 'ping_status',
          label: 'Ping Status',
          type: 'string'
        }]
      }, {
        label: 'Author',
        options: [{
          value: 'author_id',
          label: 'Author ID',
          type: 'number'
        }, {
          value: 'author_login',
          label: 'Author Login',
          type: 'string'
        }, {
          value: 'author_email',
          label: 'Author Email',
          type: 'string'
        }]
      }, {
        label: 'Media',
        options: [{
          value: 'featured_image',
          label: 'Featured Image URL',
          type: 'string'
        }, {
          value: 'featured_image_id',
          label: 'Featured Image ID',
          type: 'number'
        }]
      }, {
        label: 'Other',
        options: [{
          value: 'post_parent',
          label: 'Parent ID',
          type: 'number'
        }, {
          value: 'post_parent_slug',
          label: 'Parent Slug',
          type: 'string'
        }]
      }, {
        label: 'Taxonomies',
        options: [{
          value: 'taxonomy',
          label: 'Taxonomy',
          type: 'taxonomy',
          custom: true
        }]
      }, {
        label: 'Custom Fields',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }

    // Default - return post fields with taxonomies and custom fields
    return [].concat(baseFields, [{
      label: 'Taxonomies',
      options: [{
        value: 'taxonomy',
        label: 'Taxonomy',
        type: 'taxonomy',
        custom: true
      }]
    }, {
      label: 'Custom Fields (Meta)',
      options: [{
        value: 'meta',
        label: 'Custom Field',
        type: 'meta',
        custom: true
      }]
    }]);
  },
  /**
   * Initialize drag & drop functionality
   */
  initializeDragDrop: function initializeDragDrop() {
    var self = this;
    var draggedElement = null;

    // Drag start on source fields
    jQuery(document).on('dragstart', '.aie-field-card', function (e) {
      draggedElement = jQuery(this);
      jQuery(this).addClass('dragging');
      e.originalEvent.dataTransfer.effectAllowed = 'copy';
      e.originalEvent.dataTransfer.setData('text/html', this.innerHTML);
    });

    // Drag end
    jQuery(document).on('dragend', '.aie-field-card', function () {
      jQuery(this).removeClass('dragging');
      jQuery('.aie-target-field').removeClass('drag-over');
    });

    // Drag over target fields
    jQuery(document).on('dragover', '.aie-target-field', function (e) {
      e.preventDefault();
      jQuery(this).addClass('drag-over');
    });

    // Drag leave
    jQuery(document).on('dragleave', '.aie-target-field', function () {
      jQuery(this).removeClass('drag-over');
    });

    // Drop on target field
    jQuery(document).on('drop', '.aie-target-field', function (e) {
      e.preventDefault();
      jQuery(this).removeClass('drag-over');
      if (!draggedElement) {
        return;
      }
      var sourceField = draggedElement.data('source-field');
      var sourceIndex = draggedElement.data('source-index');
      var targetField = jQuery(this).data('target-field');
      var fieldType = jQuery(this).data('field-type');

      // Create mapping
      self.createMapping(sourceField, sourceIndex, targetField, fieldType, jQuery(this));

      // Add visual indicator that this source is used (but don't disable it)
      draggedElement.addClass('used');

      // Clear dragged element
      draggedElement = null;

      // Update stats
      self.updateMappingStats();
    });

    // Remove mapping (from target field)
    jQuery(document).on('click', '.aie-remove-mapping', function (e) {
      e.stopPropagation();
      var $targetField = jQuery(this).closest('.aie-target-field');
      var sourceIndex = $targetField.data('mapped-source-index');
      self.removeMapping(sourceIndex, $targetField);
    });

    // Remove mapping (from mapped fields section)
    jQuery(document).on('click', '.aie-remove-row-mapping', function (e) {
      e.stopPropagation();
      var sourceIndex = jQuery(this).data('source-index');
      var targetField = jQuery(this).closest('.aie-mapping-row').data('target-field');
      var $targetField = jQuery(".aie-target-field[data-target-field=\"".concat(targetField, "\"]"));
      self.removeMapping(sourceIndex, $targetField);
    });

    // Add function to mapping
    jQuery(document).on('click', '.aie-add-function', function (e) {
      e.stopPropagation();
      var sourceIndex = jQuery(this).data('source-index');
      var targetField = jQuery(this).data('target-field');
      self.showFunctionSelector(sourceIndex, targetField);
    });

    // Remove function from mapping
    jQuery(document).on('click', '.aie-remove-function', function (e) {
      e.stopPropagation();
      var functionIndex = jQuery(this).data('function-index');
      var $row = jQuery(this).closest('.aie-mapping-row');
      var sourceIndex = $row.data('source-index');
      var targetField = $row.data('target-field');
      self.removeFunction(sourceIndex, targetField, functionIndex);
    });
  },
  /**
   * Remove mapping
   */
  removeMapping: function removeMapping(sourceIndex, $targetField) {
    var targetField = $targetField.data('target-field');

    // Remove mapping from target
    $targetField.find('.aie-mapped-source').remove();
    $targetField.removeClass('has-mapping');
    $targetField.removeData('mapped-source-index');
    $targetField.removeData('mapped-source-field');

    // Check if this source is still used in other mappings BEFORE removing
    // We need to exclude the current mapping being removed
    var allMappings = jQuery(".aie-mapping-row[data-source-index=\"".concat(sourceIndex, "\"]"));
    var otherMappings = allMappings.filter(function () {
      return jQuery(this).data('target-field') !== targetField;
    });
    var stillUsed = otherMappings.length > 0;

    // Remove from mapped fields section (specific target field)
    jQuery(".aie-mapping-row[data-source-index=\"".concat(sourceIndex, "\"][data-target-field=\"").concat(targetField, "\"]")).remove();

    // Remove functions for this mapping
    var mappingKey = "".concat(sourceIndex, "-").concat(targetField);
    if (this.mappingFunctions && this.mappingFunctions[mappingKey]) {
      delete this.mappingFunctions[mappingKey];
    }
    if (!stillUsed) {
      // Remove 'used' class only if not used anywhere else
      jQuery(".aie-field-card[data-source-index=\"".concat(sourceIndex, "\"]")).removeClass('used');
    }

    // Show empty state if no mappings
    if (jQuery('.aie-mapping-row').length === 0) {
      jQuery('.aie-mapped-fields .aie-empty-state').show();
    }

    // Update stats
    this.updateMappingStats();
  },
  /**
   * Create field mapping
   */
  createMapping: function createMapping(sourceField, sourceIndex, targetField, fieldType, $targetElement) {
    // Remove existing mapping if any
    $targetElement.find('.aie-mapped-source').remove();

    // Add mapped source indicator to target
    var mappedHtml = "\n\t\t\t<div class=\"aie-mapped-source\">\n\t\t\t\t<span class=\"aie-source-name\">".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(sourceField), "</span>\n\t\t\t\t<span class=\"dashicons dashicons-no-alt aie-remove-mapping\"></span>\n\t\t\t</div>\n\t\t");
    $targetElement.find('.aie-field-info').append(mappedHtml);
    $targetElement.addClass('has-mapping');
    $targetElement.data('mapped-source-index', sourceIndex);
    $targetElement.data('mapped-source-field', sourceField);

    // Add to mapped fields section
    this.addToMappedFields(sourceField, sourceIndex, targetField, fieldType);
  },
  /**
   * Add mapping to mapped fields section
   */
  addToMappedFields: function addToMappedFields(sourceField, sourceIndex, targetField, fieldType) {
    var _this$mappingFunction;
    var $container = jQuery('.aie-mapped-fields');

    // Hide empty state
    $container.find('.aie-empty-state').hide();

    // Remove existing row for this specific combination (если перемапливаем то же поле)
    jQuery(".aie-mapping-row[data-source-index=\"".concat(sourceIndex, "\"][data-target-field=\"").concat(targetField, "\"]")).remove();

    // Get functions for this mapping
    var mappingKey = "".concat(sourceIndex, "-").concat(targetField);
    var functions = ((_this$mappingFunction = this.mappingFunctions) === null || _this$mappingFunction === void 0 ? void 0 : _this$mappingFunction[mappingKey]) || [];

    // Build functions HTML
    var functionsHtml = '';
    if (functions.length > 0) {
      functionsHtml = '<div class="aie-mapping-functions">';
      functions.forEach(function (func, index) {
        functionsHtml += "\n\t\t\t\t\t<span class=\"aie-function-badge\">\n\t\t\t\t\t\t".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(func.name), "\n\t\t\t\t\t\t<button type=\"button\" class=\"aie-remove-function\" data-function-index=\"").concat(index, "\">\xD7</button>\n\t\t\t\t\t</span>\n\t\t\t\t");
      });
      functionsHtml += '</div>';
    }

    // Add new row
    var html = "\n\t\t\t<div class=\"aie-mapping-row\" data-source-index=\"".concat(sourceIndex, "\" data-target-field=\"").concat(targetField, "\">\n\t\t\t\t<div class=\"aie-source-col\">\n\t\t\t\t\t<span class=\"dashicons dashicons-media-spreadsheet\"></span>\n\t\t\t\t\t<strong>").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(sourceField), "</strong>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-arrow\">\u2192</div>\n\t\t\t\t<div class=\"aie-target-col\">\n\t\t\t\t\t<span class=\"dashicons dashicons-wordpress\"></span>\n\t\t\t\t\t<strong>").concat(targetField, "</strong>\n\t\t\t\t</div>\n\t\t\t\t").concat(functionsHtml, "\n\t\t\t\t<div class=\"aie-mapping-actions\">\n\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-add-function\" data-source-index=\"").concat(sourceIndex, "\" data-target-field=\"").concat(targetField, "\" title=\"Add transformation function\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-admin-tools\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-remove-row-mapping\" data-source-index=\"").concat(sourceIndex, "\" data-target-field=\"").concat(targetField, "\" title=\"Remove mapping\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t");
    $container.append(html);
  },
  /**
   * Update mapping statistics
   */
  updateMappingStats: function updateMappingStats() {
    var _this$fileData, _this$fileData$column;
    var totalFields = ((_this$fileData = this.fileData) === null || _this$fileData === void 0 ? void 0 : (_this$fileData$column = _this$fileData.columns) === null || _this$fileData$column === void 0 ? void 0 : _this$fileData$column.length) || 0;

    // Count unique source fields that are used
    var usedSourceIndexes = new Set();
    jQuery('.aie-mapping-row').each(function () {
      usedSourceIndexes.add(jQuery(this).data('source-index'));
    });
    var mappedCount = usedSourceIndexes.size;
    jQuery('.aie-mapped-count').text(mappedCount);
    jQuery('.aie-total-fields').text(totalFields);

    // Enable/disable Next button based on mapping count (only on Step 4)
    if (this.currentStep === 4) {
      var $nextButton = jQuery('.aie-next-step');
      if (mappedCount === 0) {
        $nextButton.prop('disabled', true);
      } else {
        $nextButton.prop('disabled', false);
      }
    }
  },
  /**
   * Show function selector modal
   */
  showFunctionSelector: function showFunctionSelector(sourceIndex, targetField) {
    var _this8 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee3() {
      var mappingKey, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee3$(_context3) {
        while (1) {
          switch (_context3.prev = _context3.next) {
            case 0:
              // Get mapping key
              mappingKey = "".concat(sourceIndex, "-").concat(targetField); // Get current functions for this mapping
              if (!_this8.mappingFunctions) {
                _this8.mappingFunctions = {};
              }
              if (!_this8.mappingFunctions[mappingKey]) {
                _this8.mappingFunctions[mappingKey] = [];
              }

              // Load functions from server
              _context3.prev = 3;
              _context3.next = 6;
              return jQuery.ajax({
                url: window.aieData.ajaxUrl,
                type: 'POST',
                data: {
                  action: 'aie_functions_get_snippets',
                  nonce: window.aieData.nonce
                }
              });
            case 6:
              response = _context3.sent;
              if (response.success) {
                _context3.next = 10;
                break;
              }
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Failed to load functions', 'error');
              return _context3.abrupt("return");
            case 10:
              _this8.showFunctionModal(sourceIndex, targetField, response.data);
              _context3.next = 16;
              break;
            case 13:
              _context3.prev = 13;
              _context3.t0 = _context3["catch"](3);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Error loading functions: ' + _context3.t0.message, 'error');
            case 16:
            case "end":
              return _context3.stop();
          }
        }
      }, _callee3, null, [[3, 13]]);
    }))();
  },
  /**
   * Show function modal
   */
  showFunctionModal: function showFunctionModal(sourceIndex, targetField, functionsData) {
    var mappingKey = "".concat(sourceIndex, "-").concat(targetField);
    var currentFunctions = this.mappingFunctions[mappingKey] || [];
    var sourceField = this.fileData.columns[sourceIndex];

    // Store current editing context
    this.currentEditingMapping = {
      sourceIndex: sourceIndex,
      targetField: targetField
    };

    // Create modal HTML (EXACTLY like export modal structure)
    var modalHtml = "\n\t\t\t<div id=\"aie-field-functions-modal\" class=\"aie-modal\" style=\"display:flex;\">\n\t\t\t\t<div class=\"aie-modal-backdrop\"></div>\n\t\t\t\t<div class=\"aie-modal-content aie-field-functions-modal-content\">\n\t\t\t\t\t<div class=\"aie-modal-header\">\n\t\t\t\t\t\t<h2 class=\"aie-modal-title\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\t\t\tField Transformation Functions\n\t\t\t\t\t\t</h2>\n\t\t\t\t\t\t<button type=\"button\" class=\"aie-modal-close\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t\t\t</button>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-modal-body\">\n\t\t\t\t\t\t<!-- Field Info -->\n\t\t\t\t\t\t<div class=\"aie-field-info\">\n\t\t\t\t\t\t\t<div class=\"aie-field-info-item\">\n\t\t\t\t\t\t\t\t<strong>Field:</strong>\n\t\t\t\t\t\t\t\t<span class=\"aie-current-field-label\">".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(sourceField), "</span>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t<div class=\"aie-field-info-item\">\n\t\t\t\t\t\t\t\t<strong>Type:</strong>\n\t\t\t\t\t\t\t\t<span class=\"aie-current-field-type\">").concat(targetField, "</span>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t<!-- Applied Functions List -->\n\t\t\t\t\t\t<div class=\"aie-applied-functions\">\n\t\t\t\t\t\t\t<h3>\n\t\t\t\t\t\t\t\tApplied Functions\n\t\t\t\t\t\t\t\t<span class=\"aie-functions-count\">(0)</span>\n\t\t\t\t\t\t\t</h3>\n\t\t\t\t\t\t\t\n\t\t\t\t\t\t\t<div class=\"aie-functions-pipeline\" id=\"aie-functions-pipeline\">\n\t\t\t\t\t\t\t\t<div class=\"aie-no-functions\">\n\t\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t\t\t\t\t\t<p>No functions applied yet. Add functions from the list below.</p>\n\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t\t\n\t\t\t\t\t\t\t\t<div class=\"aie-function-items\" id=\"aie-function-items\">\n\t\t\t\t\t\t\t\t\t<!-- Functions will be added here -->\n\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t\t<div class=\"aie-pipeline-hint\">\n\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t\t\t\t\tFunctions are applied in order from top to bottom. Drag to reorder.\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t<!-- Available Functions -->\n\t\t\t\t\t\t<div class=\"aie-available-functions\">\n\t\t\t\t\t\t\t<h3>Available Functions</h3>\n\t\t\t\t\t\t\t\n\t\t\t\t\t\t\t<!-- Search Functions -->\n\t\t\t\t\t\t\t<div class=\"aie-functions-search\">\n\t\t\t\t\t\t\t\t<input \n\t\t\t\t\t\t\t\t\ttype=\"text\" \n\t\t\t\t\t\t\t\t\tid=\"aie-functions-search\" \n\t\t\t\t\t\t\t\t\tclass=\"regular-text\" \n\t\t\t\t\t\t\t\t\tplaceholder=\"Search functions...\"\n\t\t\t\t\t\t\t\t>\n\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-search\"></span>\n\t\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t\t<!-- Functions Filter -->\n\t\t\t\t\t\t\t<div class=\"aie-functions-filter\">\n\t\t\t\t\t\t\t\t<label>\n\t\t\t\t\t\t\t\t\t<input type=\"radio\" name=\"functions-filter\" value=\"all\" checked>\n\t\t\t\t\t\t\t\t\tAll\n\t\t\t\t\t\t\t\t</label>\n\t\t\t\t\t\t\t\t<label>\n\t\t\t\t\t\t\t\t\t<input type=\"radio\" name=\"functions-filter\" value=\"library\">\n\t\t\t\t\t\t\t\t\tLibrary\n\t\t\t\t\t\t\t\t</label>\n\t\t\t\t\t\t\t\t<label>\n\t\t\t\t\t\t\t\t\t<input type=\"radio\" name=\"functions-filter\" value=\"custom\">\n\t\t\t\t\t\t\t\t\tCustom\n\t\t\t\t\t\t\t\t</label>\n\t\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t\t<!-- Functions List -->\n\t\t\t\t\t\t\t<div class=\"aie-functions-list\" id=\"aie-functions-list\">\n\t\t\t\t\t\t\t\t<div class=\"aie-functions-loading\">\n\t\t\t\t\t\t\t\t\t<span class=\"spinner is-active\"></span>\n\t\t\t\t\t\t\t\t\t<p>Loading functions...</p>\n\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t\t<!-- Quick Add Link -->\n\t\t\t\t\t\t\t<div class=\"aie-functions-quick-add\">\n\t\t\t\t\t\t\t\t<a href=\"#\" class=\"aie-create-new-function\">\n\t\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-plus-alt\"></span>\n\t\t\t\t\t\t\t\t\tCreate New Function\n\t\t\t\t\t\t\t\t</a>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t<!-- Preview Section -->\n\t\t\t\t\t\t<div class=\"aie-function-preview\">\n\t\t\t\t\t\t\t<h3>Preview Transformation</h3>\n\t\t\t\t\t\t\t\n\t\t\t\t\t\t\t<div class=\"aie-preview-controls\">\n\t\t\t\t\t\t\t\t<div class=\"aie-preview-input-group\">\n\t\t\t\t\t\t\t\t\t<label for=\"aie-preview-input\">\n\t\t\t\t\t\t\t\t\t\tTest Value:\n\t\t\t\t\t\t\t\t\t</label>\n\t\t\t\t\t\t\t\t\t<input \n\t\t\t\t\t\t\t\t\t\ttype=\"text\" \n\t\t\t\t\t\t\t\t\t\tid=\"aie-preview-input\" \n\t\t\t\t\t\t\t\t\t\tclass=\"regular-text\" \n\t\t\t\t\t\t\t\t\t\tplaceholder=\"Enter test value...\"\n\t\t\t\t\t\t\t\t\t>\n\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t\t<button type=\"button\" class=\"button aie-test-pipeline\">\n\t\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-media-code\"></span>\n\t\t\t\t\t\t\t\t\tTest Pipeline\n\t\t\t\t\t\t\t\t</button>\n\t\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t\t<div class=\"aie-preview-result\" id=\"aie-preview-result\" style=\"display:none;\">\n\t\t\t\t\t\t\t\t<div class=\"aie-preview-steps\">\n\t\t\t\t\t\t\t\t\t<!-- Steps will be added dynamically -->\n\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-modal-footer\">\n\t\t\t\t\t\t<button type=\"button\" class=\"button button-secondary aie-modal-cancel\">\n\t\t\t\t\t\t\tCancel\n\t\t\t\t\t\t</button>\n\t\t\t\t\t\t<button type=\"button\" class=\"button button-primary aie-save-field-functions\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-yes\"></span>\n\t\t\t\t\t\t\tApply Functions\n\t\t\t\t\t\t</button>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t");

    // Remove existing modal
    jQuery('#aie-field-functions-modal').remove();

    // Add to body
    jQuery('body').append(modalHtml);
    jQuery('body').addClass('aie-modal-open');

    // Load current functions into pipeline
    this.loadCurrentFunctions(currentFunctions);

    // Populate available functions
    this.renderAvailableFunctions(functionsData);

    // Bind modal events
    this.bindFunctionModalEvents(sourceIndex, targetField);
  },
  /**
   * Load current functions into pipeline
   */
  loadCurrentFunctions: function loadCurrentFunctions(currentFunctions) {
    var _this9 = this;
    var $container = jQuery('#aie-function-items');
    $container.empty();
    currentFunctions.forEach(function (func) {
      _this9.addFunctionToPipeline(func, false);
    });
    this.updateFunctionsCount(currentFunctions.length);
    this.toggleNoFunctionsMessage();
  },
  /**
   * Render available functions (like export)
   */
  renderAvailableFunctions: function renderAvailableFunctions(functionsData) {
    var _this10 = this;
    var $container = jQuery('#aie-functions-list');
    $container.empty();

    // Get all snippets
    var snippets = functionsData.snippets || {};
    if (Object.keys(snippets).length === 0) {
      $container.html("\n\t\t\t\t<div class=\"aie-functions-empty-state\">\n\t\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t\t<p>No functions available yet.</p>\n\t\t\t\t</div>\n\t\t\t");
      return;
    }

    // Store for later use
    this.availableFunctions = snippets;
    Object.entries(snippets).forEach(function (_ref) {
      var _ref2 = _slicedToArray(_ref, 2),
        key = _ref2[0],
        snippet = _ref2[1];
      var item = jQuery('<div>').addClass('aie-function-list-item').attr('data-function-id', key).attr('data-category', snippet.category || 'custom').html("\n\t\t\t\t\t<div class=\"aie-function-list-info\">\n\t\t\t\t\t\t<span class=\"aie-function-list-name\">".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(snippet.name), "</span>\n\t\t\t\t\t\t<span class=\"aie-function-list-desc\">").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(snippet.description || ''), "</span>\n\t\t\t\t\t</div>\n\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-add-function-btn\">Add</button>\n\t\t\t\t"));
      item.find('.aie-add-function-btn').on('click', function () {
        _this10.addFunctionToPipeline({
          id: key,
          name: snippet.name
        }, true);
      });
      $container.append(item);
    });
  },
  /**
   * Add function to pipeline
   */
  addFunctionToPipeline: function addFunctionToPipeline(func) {
    var _this11 = this;
    var updateArray = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : true;
    var $container = jQuery('#aie-function-items');
    var item = jQuery('<div>').addClass('aie-function-item').attr('data-function-id', func.id).html("\n\t\t\t\t<span class=\"aie-function-handle dashicons dashicons-menu\"></span>\n\t\t\t\t<div class=\"aie-function-info\">\n\t\t\t\t\t<strong class=\"aie-function-name\">".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(func.name), "</strong>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-function-actions\">\n\t\t\t\t\t<button type=\"button\" class=\"button-small aie-remove-function\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t</div>\n\t\t\t"));

    // Remove function event
    item.find('.aie-remove-function').on('click', function () {
      item.remove();
      _this11.updateFunctionsCount();
      _this11.toggleNoFunctionsMessage();
    });
    $container.append(item);
    if (updateArray) {
      this.updateFunctionsCount();
    }
    this.toggleNoFunctionsMessage();
  },
  /**
   * Update functions count
   */
  updateFunctionsCount: function updateFunctionsCount() {
    var count = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : null;
    var $countEl = jQuery('.aie-functions-count');
    if (!$countEl.length) return;
    if (count === null) {
      count = jQuery('#aie-function-items .aie-function-item').length;
    }
    $countEl.text("(".concat(count, ")"));
  },
  /**
   * Toggle no functions message
   */
  toggleNoFunctionsMessage: function toggleNoFunctionsMessage() {
    var hasItems = jQuery('#aie-function-items .aie-function-item').length > 0;
    jQuery('.aie-no-functions').toggle(!hasItems);
    jQuery('#aie-function-items').toggle(hasItems);
  },
  /**
   * Bind function modal events
   */
  bindFunctionModalEvents: function bindFunctionModalEvents(sourceIndex, targetField) {
    var self = this;

    // Close modal functions
    var closeModal = function closeModal() {
      jQuery('#aie-field-functions-modal').remove();
      jQuery('body').removeClass('aie-modal-open');
    };

    // Close on backdrop click
    jQuery('.aie-modal-backdrop').on('click', closeModal);

    // Close on X button
    jQuery('.aie-modal-close').on('click', closeModal);

    // Close on Cancel button
    jQuery('.aie-modal-cancel').on('click', closeModal);

    // Search functions
    jQuery('#aie-functions-search').on('input', function () {
      var query = jQuery(this).val().toLowerCase();
      jQuery('.aie-function-list-item').each(function () {
        var name = jQuery(this).find('.aie-function-list-name').text().toLowerCase();
        var desc = jQuery(this).find('.aie-function-list-desc').text().toLowerCase();
        jQuery(this).toggle(name.includes(query) || desc.includes(query));
      });
    });

    // Filter functions (All / Library / Custom)
    jQuery('input[name="functions-filter"]').on('change', function () {
      var filterValue = jQuery(this).val();
      jQuery('.aie-function-list-item').each(function () {
        var category = jQuery(this).data('category');
        if (filterValue === 'all') {
          jQuery(this).show();
        } else if (filterValue === 'library') {
          // Show library functions (snippets)
          jQuery(this).toggle(category !== 'custom');
        } else if (filterValue === 'custom') {
          // Show custom functions
          jQuery(this).toggle(category === 'custom');
        }
      });
    });

    // Create new function
    jQuery('.aie-create-new-function').on('click', function (e) {
      e.preventDefault();
      _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Creating custom functions will be available in the Functions Library section', 'info');
    });

    // Test Pipeline
    jQuery('.aie-test-pipeline').on('click', function () {
      var testValue = jQuery('#aie-preview-input').val();
      if (!testValue) {
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Please enter a test value', 'warning');
        return;
      }
      var functions = [];
      jQuery('#aie-function-items .aie-function-item').each(function () {
        functions.push(jQuery(this).data('function-id'));
      });
      if (functions.length === 0) {
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Please add at least one function to test', 'warning');
        return;
      }
      self.testFunctionPipeline(testValue, functions);
    });

    // Apply functions (Save button)
    jQuery('.aie-save-field-functions').on('click', function () {
      var selectedFunctions = [];
      jQuery('#aie-function-items .aie-function-item').each(function () {
        var functionId = jQuery(this).data('function-id');
        var functionName = jQuery(this).find('.aie-function-name').text();
        selectedFunctions.push({
          id: functionId,
          name: functionName
        });
      });
      self.applyFunctionsToMapping(sourceIndex, targetField, selectedFunctions);
      closeModal();
    });
  },
  /**
   * Test function pipeline
   */
  testFunctionPipeline: function testFunctionPipeline(testValue, functionIds) {
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee4() {
      var $result, $steps, response, html, _response$data;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee4$(_context4) {
        while (1) {
          switch (_context4.prev = _context4.next) {
            case 0:
              $result = jQuery('#aie-preview-result');
              $steps = $result.find('.aie-preview-steps');
              $steps.html('<div class="aie-preview-loading"><span class="spinner is-active"></span> Testing...</div>');
              $result.show();
              _context4.prev = 4;
              _context4.next = 7;
              return jQuery.ajax({
                url: window.aieData.ajaxUrl,
                type: 'POST',
                data: {
                  action: 'aie_test_function_pipeline',
                  nonce: window.aieData.nonce,
                  test_value: testValue,
                  function_ids: functionIds
                }
              });
            case 7:
              response = _context4.sent;
              if (response.success && response.data.steps) {
                html = ''; // Initial value
                html += "\n\t\t\t\t\t<div class=\"aie-preview-step\">\n\t\t\t\t\t\t<div class=\"aie-step-label\">Initial Value:</div>\n\t\t\t\t\t\t<div class=\"aie-step-value\">".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(response.data.initial || testValue), "</div>\n\t\t\t\t\t</div>\n\t\t\t\t");

                // Each step
                response.data.steps.forEach(function (step, index) {
                  var stepNum = index + 1;
                  html += "\n\t\t\t\t\t\t<div class=\"aie-preview-step\">\n\t\t\t\t\t\t\t<div class=\"aie-step-label\">".concat(stepNum, ". ").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(step.function_name), ":</div>\n\t\t\t\t\t\t\t<div class=\"aie-step-value\">").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(step.output), "</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t");
                });

                // Final result
                html += "\n\t\t\t\t\t<div class=\"aie-preview-step aie-preview-final\">\n\t\t\t\t\t\t<div class=\"aie-step-label\">Final Result:</div>\n\t\t\t\t\t\t<div class=\"aie-step-value\"><strong>".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(response.data["final"]), "</strong></div>\n\t\t\t\t\t</div>\n\t\t\t\t");
                $steps.html(html);
              } else {
                $steps.html("<div class=\"notice notice-error inline\"><p>".concat(((_response$data = response.data) === null || _response$data === void 0 ? void 0 : _response$data.message) || 'Failed to test pipeline', "</p></div>"));
              }
              _context4.next = 14;
              break;
            case 11:
              _context4.prev = 11;
              _context4.t0 = _context4["catch"](4);
              $steps.html("<div class=\"notice notice-error inline\"><p>Error: ".concat(_context4.t0.message, "</p></div>"));
            case 14:
            case "end":
              return _context4.stop();
          }
        }
      }, _callee4, null, [[4, 11]]);
    }))();
  },
  /**
   * Apply functions to mapping
   */
  applyFunctionsToMapping: function applyFunctionsToMapping(sourceIndex, targetField, functions) {
    var mappingKey = "".concat(sourceIndex, "-").concat(targetField);

    // Store functions
    this.mappingFunctions[mappingKey] = functions;

    // Update mapping row display
    this.updateMappingRowFunctions(sourceIndex, targetField, functions);
  },
  /**
   * Update mapping row with functions
   */
  updateMappingRowFunctions: function updateMappingRowFunctions(sourceIndex, targetField, functions) {
    var $row = jQuery(".aie-mapping-row[data-source-index=\"".concat(sourceIndex, "\"][data-target-field=\"").concat(targetField, "\"]"));

    // Remove existing functions display
    $row.find('.aie-mapping-functions').remove();
    if (functions.length === 0) {
      return;
    }

    // Add functions display
    var functionsHtml = '<div class="aie-mapping-functions">';
    functions.forEach(function (func, index) {
      functionsHtml += "\n\t\t\t\t<span class=\"aie-function-badge\">\n\t\t\t\t\t".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(func.name), "\n\t\t\t\t\t<button type=\"button\" class=\"aie-remove-function\" data-function-index=\"").concat(index, "\">\xD7</button>\n\t\t\t\t</span>\n\t\t\t");
    });
    functionsHtml += '</div>';

    // Insert after target column
    $row.find('.aie-target-col').after(functionsHtml);
  },
  /**
   * Remove function from mapping
   */
  removeFunction: function removeFunction(sourceIndex, targetField, functionIndex) {
    var mappingKey = "".concat(sourceIndex, "-").concat(targetField);
    var functions = this.mappingFunctions[mappingKey] || [];

    // Remove function
    functions.splice(functionIndex, 1);

    // Update display
    this.updateMappingRowFunctions(sourceIndex, targetField, functions);
  },
  /**
   * Initialize field search
   */
  initializeFieldSearch: function initializeFieldSearch() {
    // Search source fields
    jQuery('.aie-search-source').on('input', function () {
      var query = jQuery(this).val().toLowerCase();
      jQuery('.aie-field-card').each(function () {
        var fieldName = jQuery(this).find('.aie-field-name').text().toLowerCase();
        jQuery(this).toggle(fieldName.includes(query));
      });
    });

    // Search target fields
    var performSearch = function performSearch() {
      var query = jQuery(this).val().toLowerCase().trim();

      // Store matched fields per group
      var groupMatches = {};

      // Filter fields and track which groups have matches
      jQuery('.aie-target-field').each(function () {
        var $field = jQuery(this);
        var fieldName = $field.find('.aie-field-label').text().toLowerCase();
        var matches = query === '' || fieldName.includes(query);

        // Find parent group
        var $group = $field.closest('.aie-field-group');
        var groupIndex = $group.index();
        if (!groupMatches[groupIndex]) {
          groupMatches[groupIndex] = 0;
        }
        if (matches) {
          groupMatches[groupIndex]++;
        }
        $field.toggle(matches);
      });

      // Show/hide groups based on matched fields
      jQuery('#aie-target-fields .aie-field-group').each(function () {
        var $group = jQuery(this);
        var groupIndex = $group.index();
        var hasMatches = groupMatches[groupIndex] > 0;
        $group.toggle(query === '' || hasMatches);
      });
    };
    jQuery('.aie-search-target').on('keyup input', performSearch);
  },
  /**
   * Get target fields for content type
   */
  getTargetFields: function getTargetFields(contentType) {
    var fields = {
      post: [{
        value: 'post_title',
        label: 'Title'
      }, {
        value: 'post_content',
        label: 'Content'
      }, {
        value: 'post_excerpt',
        label: 'Excerpt'
      }, {
        value: 'post_status',
        label: 'Status'
      }, {
        value: 'post_author',
        label: 'Author'
      }, {
        value: 'post_date',
        label: 'Date'
      }, {
        value: 'post_name',
        label: 'Slug'
      }, {
        value: 'categories',
        label: 'Categories'
      }, {
        value: 'tags',
        label: 'Tags'
      }, {
        value: 'featured_image',
        label: 'Featured Image'
      }],
      media: [{
        value: 'post_title',
        label: 'Title'
      }, {
        value: 'post_content',
        label: 'Description'
      }, {
        value: 'post_excerpt',
        label: 'Caption'
      }, {
        value: 'file_url',
        label: 'File URL'
      }, {
        value: 'alt_text',
        label: 'Alt Text'
      }]
    };
    return fields[contentType] || fields.post;
  },
  /**
   * Auto-map fields
   */
  autoMapFields: function autoMapFields() {
    var _this12 = this;
    var mappedCount = 0;

    // Clear existing mappings
    this.clearFieldMapping();

    // Try to auto-match source to target fields
    jQuery('.aie-field-card').each(function (index, sourceCard) {
      var $sourceCard = jQuery(sourceCard);
      var sourceField = $sourceCard.data('source-field').toLowerCase();
      var sourceIndex = $sourceCard.data('source-index');

      // Try to find matching target
      var matched = false;
      jQuery('.aie-target-field').each(function (i, targetField) {
        if (matched) return;
        var $targetField = jQuery(targetField);
        var targetFieldValue = $targetField.data('target-field').toLowerCase();
        var targetLabel = $targetField.find('.aie-field-label').text().toLowerCase();

        // Check for exact or partial match
        if (sourceField === targetFieldValue || sourceField === targetLabel || sourceField.includes(targetFieldValue) || targetFieldValue.includes(sourceField) || sourceField.replace(/_/g, ' ') === targetLabel) {
          // Create mapping
          _this12.createMapping($sourceCard.data('source-field'), sourceIndex, $targetField.data('target-field'), $targetField.data('field-type'), $targetField);
          $sourceCard.addClass('mapped');
          matched = true;
          mappedCount++;
        }
      });
    });
    this.updateMappingStats();
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice("Auto-mapped ".concat(mappedCount, " fields"), 'success');
  },
  /**
   * Clear field mapping
   */
  clearFieldMapping: function clearFieldMapping() {
    // Clear all mappings
    jQuery('.aie-target-field').each(function () {
      jQuery(this).find('.aie-mapped-source').remove();
      jQuery(this).removeClass('has-mapping');
      jQuery(this).removeData('mapped-source-index');
      jQuery(this).removeData('mapped-source-field');
    });

    // Unmark all source fields (remove 'used' class)
    jQuery('.aie-field-card').removeClass('used');

    // Clear mapped fields section
    jQuery('.aie-mapped-fields').html("\n\t\t\t<div class=\"aie-empty-state\">\n\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t<p>Drag source columns to WordPress fields to create mappings</p>\n\t\t\t</div>\n\t\t");

    // Clear all functions
    this.mappingFunctions = {};
    this.updateMappingStats();
  },
  /**
   * Get field mapping
   */
  getFieldMapping: function getFieldMapping() {
    var mapping = [];
    jQuery('.aie-mapping-row').each(function () {
      var $row = jQuery(this);
      var sourceIndex = $row.data('source-index');
      var targetField = $row.data('target-field');
      var sourceField = jQuery(".aie-field-card[data-source-index=\"".concat(sourceIndex, "\"]")).data('source-field');
      if (sourceField && targetField) {
        mapping.push({
          source_index: sourceIndex,
          source_field: sourceField,
          target_field: targetField,
          function_id: $row.data('function-id') || null
        });
      }
    });
    console.log('Field mapping:', mapping);
    return mapping;
  },
  /**
   * Start import
   */
  startImport: function startImport() {
    var _this13 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee5() {
      var contentType, data, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee5$(_context5) {
        while (1) {
          switch (_context5.prev = _context5.next) {
            case 0:
              _context5.prev = 0;
              contentType = jQuery('input[name="content_type"]:checked').val();
              data = {
                file_path: _this13.fileData.file_path,
                content_type: contentType,
                format: _this13.fileData.format,
                field_mapping: _this13.getFieldMapping(),
                duplicate_handling: jQuery('input[name="duplicate_handling"]:checked').val(),
                post_status: jQuery('[name="post_status"]').val(),
                post_type: jQuery('[name="post_type"]').val(),
                download_images: jQuery('[name="download_images"]').is(':checked'),
                batch_size: parseInt(jQuery('[name="batch_size"]').val()) || 50
              }; // Add custom post type if selected
              if (contentType === 'custom_post_types') {
                data.custom_post_type = jQuery('#aie-custom-post-type').val();
              }
              _context5.next = 6;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_import_start', data);
            case 6:
              response = _context5.sent;
              _this13.jobId = response.job_id;
              _this13.showStep(6);
              _this13.startProgressTracking();
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Import started successfully', 'success');
              _context5.next = 16;
              break;
            case 13:
              _context5.prev = 13;
              _context5.t0 = _context5["catch"](0);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].handleError(_context5.t0, 'Start import');
            case 16:
            case "end":
              return _context5.stop();
          }
        }
      }, _callee5, null, [[0, 13]]);
    }))();
  },
  /**
   * Start progress tracking
   */
  startProgressTracking: function startProgressTracking() {
    var _this14 = this;
    this.progressInterval = setInterval(function () {
      _this14.updateProgress();
    }, 2000);
  },
  /**
   * Update import progress
   */
  updateProgress: function updateProgress() {
    var _this15 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee6() {
      var response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee6$(_context6) {
        while (1) {
          switch (_context6.prev = _context6.next) {
            case 0:
              _context6.prev = 0;
              _context6.next = 3;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_import_get_progress', {
                job_id: _this15.jobId
              });
            case 3:
              response = _context6.sent;
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].updateProgressBar(jQuery('.aie-step-6'), response);
              if (response.status === 'completed') {
                _this15.onImportComplete(response);
              } else if (response.status === 'failed') {
                _this15.onImportFailed(response);
              }
              _context6.next = 11;
              break;
            case 8:
              _context6.prev = 8;
              _context6.t0 = _context6["catch"](0);
              console.error('Progress update error:', _context6.t0);
            case 11:
            case "end":
              return _context6.stop();
          }
        }
      }, _callee6, null, [[0, 8]]);
    }))();
  },
  /**
   * Handle import completion
   */
  onImportComplete: function onImportComplete(result) {
    var _result$estimates;
    clearInterval(this.progressInterval);
    jQuery('.aie-import-results').show();
    jQuery('.aie-result-processed').text(result.processed || 0);
    jQuery('.aie-result-success').text(result.success || 0);
    jQuery('.aie-result-failed').text(result.failed || 0);
    jQuery('.aie-result-duration').text(((_result$estimates = result.estimates) === null || _result$estimates === void 0 ? void 0 : _result$estimates.elapsed_formatted) || '0s');
    jQuery('.aie-cancel-import').hide();
    jQuery('.aie-new-import').show();
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Import completed successfully!', 'success');
  },
  /**
   * Handle import failure
   */
  onImportFailed: function onImportFailed(result) {
    clearInterval(this.progressInterval);
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Import failed: ' + (result.error || 'Unknown error'), 'error');
  },
  /**
   * Cancel import
   */
  cancelImport: function cancelImport() {
    var _this16 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee7() {
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee7$(_context7) {
        while (1) {
          switch (_context7.prev = _context7.next) {
            case 0:
              if (confirm('Are you sure you want to cancel this import?')) {
                _context7.next = 2;
                break;
              }
              return _context7.abrupt("return");
            case 2:
              _context7.prev = 2;
              _context7.next = 5;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_import_cancel', {
                job_id: _this16.jobId
              });
            case 5:
              clearInterval(_this16.progressInterval);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice('Import cancelled', 'info');
              _this16.resetWizard();
              _context7.next = 13;
              break;
            case 10:
              _context7.prev = 10;
              _context7.t0 = _context7["catch"](2);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].handleError(_context7.t0, 'Cancel import');
            case 13:
            case "end":
              return _context7.stop();
          }
        }
      }, _callee7, null, [[2, 10]]);
    }))();
  },
  /**
   * Toggle logs visibility
   */
  toggleLogs: function toggleLogs() {
    jQuery('.aie-logs-container').slideToggle();
  },
  /**
   * Reset wizard
   */
  resetWizard: function resetWizard() {
    this.currentStep = 1;
    this.uploadedFile = null;
    this.fileData = null;
    this.jobId = null;
    clearInterval(this.progressInterval);
    jQuery('#wp-aie-import input[type="text"], #wp-aie-import input[type="file"]').val('');
    jQuery('#wp-aie-import input[type="radio"]:first').prop('checked', true);
    jQuery('.aie-file-info').hide();
    jQuery('.aie-upload-placeholder').show();
    jQuery('.aie-import-results').hide();
    this.showStep(1);
  },
  /**
   * Load ACF fields dynamically from server
   */
  loadACFFields: function loadACFFields(contentType) {
    var _this17 = this;
    if (typeof aieData === 'undefined') {
      return;
    }
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_get_acf_fields',
        nonce: aieData.nonce,
        post_type: contentType
      },
      success: function success(response) {
        if (response.success && response.data.fields && response.data.fields.length > 0) {
          _this17.renderACFFields(response.data.fields);
        }
      },
      error: function error(xhr, status, _error5) {
        console.log('ACF fields load error:', _error5);
      }
    });
  },
  /**
   * Render ACF fields as target fields
   */
  renderACFFields: function renderACFFields(fields) {
    var $container = jQuery('#aie-target-fields');

    // Create ACF group
    var html = "<div class=\"aie-field-group aie-acf-fields-group\">";
    html += "<div class=\"aie-field-group-label\">\uD83D\uDD27 ACF Fields</div>";
    fields.forEach(function (field) {
      html += "\n\t\t\t\t<div class=\"aie-target-field\" data-target-field=\"acf_".concat(field.name, "\" data-field-type=\"").concat(field.type || 'string', "\">\n\t\t\t\t\t<div class=\"aie-field-icon\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-admin-settings\"></span>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-field-info\">\n\t\t\t\t\t\t<div class=\"aie-field-label\">").concat(field.label, "</div>\n\t\t\t\t\t\t<span class=\"aie-field-type-badge\">acf:").concat(field.type, "</span>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t");
    });
    html += "</div>";

    // Append to container
    $container.append(html);
  },
  /**
   * Load Yoast SEO fields dynamically from server
   */
  loadYoastFields: function loadYoastFields(contentType) {
    var _this18 = this;
    if (typeof aieData === 'undefined') {
      return;
    }

    // Don't load Yoast for these content types
    var excludedTypes = ['media', 'user', 'comment', 'menu', 'block_theme_settings', 'taxonomy', 'database_table', 'woo_attribute', 'woo_coupon', 'woo_order'];
    if (excludedTypes.includes(contentType)) {
      return;
    }
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_get_yoast_fields',
        nonce: aieData.nonce,
        post_type: contentType
      },
      success: function success(response) {
        if (response.success && response.data.fields && response.data.fields.length > 0) {
          _this18.renderYoastFields(response.data.fields);
        }
      },
      error: function error(xhr, status, _error6) {
        console.log('Yoast fields load error:', _error6);
      }
    });
  },
  /**
   * Render Yoast SEO fields as target fields
   */
  renderYoastFields: function renderYoastFields(fields) {
    var $container = jQuery('#aie-target-fields');

    // Create Yoast group
    var html = "<div class=\"aie-field-group aie-yoast-fields-group\">";
    html += "<div class=\"aie-field-group-label\">\uD83D\uDCCA Yoast SEO</div>";
    fields.forEach(function (field) {
      // Clean up field name (remove _ prefix)
      var fieldName = field.name.replace(/^_/, '');
      html += "\n\t\t\t\t<div class=\"aie-target-field\" data-target-field=\"".concat(fieldName, "\" data-field-type=\"string\">\n\t\t\t\t\t<div class=\"aie-field-icon\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-chart-line\"></span>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-field-info\">\n\t\t\t\t\t\t<div class=\"aie-field-label\">").concat(field.label, "</div>\n\t\t\t\t\t\t<span class=\"aie-field-type-badge\">yoast</span>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t");
    });
    html += "</div>";

    // Append to container
    $container.append(html);
  },
  /**
   * Populate unique field options in Step 5
   */
  populateUniqueFieldOptions: function populateUniqueFieldOptions() {
    var _this19 = this;
    var $select = jQuery('#aie-unique-field');

    // Clear existing options except first
    $select.find('option:not(:first)').remove();

    // Get all mapped target fields
    var mappedFields = this.getFieldMapping();
    if (!mappedFields || mappedFields.length === 0) {
      return;
    }

    // Create unique set of target fields
    var uniqueFields = new Set();
    mappedFields.forEach(function (mapping) {
      if (mapping.target_field) {
        uniqueFields.add(mapping.target_field);
      }
    });

    // Add options for each unique target field
    uniqueFields.forEach(function (field) {
      var label = _this19.getFieldLabel(field);
      $select.append("<option value=\"".concat(field, "\">").concat(label, "</option>"));
    });

    // Select first field by default if only one
    if (uniqueFields.size === 1) {
      $select.find('option:eq(1)').prop('selected', true);
    }
  },
  /**
   * Get human-readable label for field
   */
  getFieldLabel: function getFieldLabel(fieldValue) {
    // Try to find label from target fields
    var label = fieldValue;
    jQuery('.aie-target-field').each(function () {
      if (jQuery(this).data('target-field') === fieldValue) {
        var foundLabel = jQuery(this).find('.aie-field-label').text();
        if (foundLabel) {
          label = foundLabel;
          return false; // break
        }
      }
    });

    // Fallback: convert field_name to Field Name
    if (label === fieldValue) {
      label = fieldValue.replace(/_/g, ' ').replace(/\b\w/g, function (l) {
        return l.toUpperCase();
      });
    }
    return label;
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ImportModule);

/***/ }),

/***/ "./src/js/modules/jobs-log.js":
/*!************************************!*\
  !*** ./src/js/modules/jobs-log.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/regenerator */ "./node_modules/@babel/runtime/regenerator/index.js");
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utils */ "./src/js/modules/utils.js");

function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { Promise.resolve(value).then(_next, _throw); } }
function _asyncToGenerator(fn) { return function () { var self = this, args = arguments; return new Promise(function (resolve, reject) { var gen = fn.apply(self, args); function _next(value) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value); } function _throw(err) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err); } _next(undefined); }); }; }
/**
 * Jobs Log Module
 *
 * Handles jobs log page functionality
 */


var JobsLogModule = {
  currentPage: 1,
  perPage: 20,
  totalPages: 1,
  totalJobs: 0,
  filters: {
    type: '',
    status: ''
  },
  /**
   * Initialize module
   */
  init: function init() {
    if (!jQuery('#wp-aie-jobs-log').length) {
      return;
    }
    this.bindEvents();
    this.loadJobs();
  },
  /**
   * Bind event handlers
   */
  bindEvents: function bindEvents() {
    var _this = this;
    var $page = jQuery('#wp-aie-jobs-log');

    // Filter buttons
    $page.on('click', '.aie-filter-apply', function () {
      return _this.applyFilters();
    });
    $page.on('click', '.aie-filter-reset', function () {
      return _this.resetFilters();
    });

    // Pagination
    $page.on('click', '.first-page', function () {
      return _this.goToPage(1);
    });
    $page.on('click', '.prev-page', function () {
      return _this.goToPage(_this.currentPage - 1);
    });
    $page.on('click', '.next-page', function () {
      return _this.goToPage(_this.currentPage + 1);
    });
    $page.on('click', '.last-page', function () {
      return _this.goToPage(_this.totalPages);
    });

    // Job actions
    $page.on('click', '.job-action-resume', function (e) {
      return _this.resumeJob(e);
    });
    $page.on('click', '.job-action-restart', function (e) {
      return _this.restartJob(e);
    });
    $page.on('click', '.job-action-retry', function (e) {
      return _this.retryJob(e);
    });
    $page.on('click', '.job-action-delete', function (e) {
      return _this.deleteJob(e);
    });
    $page.on('click', '.job-action-download', function (e) {
      return _this.downloadFile(e);
    });
    $page.on('click', '.job-action-view', function (e) {
      return _this.viewJobDetails(e);
    });

    // Modal close - bind to document for modals outside page container
    jQuery(document).on('click', '.aie-modal-close', function () {
      return _this.closeModal();
    });
    jQuery(document).on('click', '.aie-modal-overlay', function () {
      return _this.closeModal();
    });

    // Confirm delete
    jQuery(document).on('click', '.aie-confirm-delete', function () {
      return _this.confirmDelete();
    });
  },
  /**
   * Apply filters
   */
  applyFilters: function applyFilters() {
    this.filters.type = jQuery('#filter-type').val();
    this.filters.status = jQuery('#filter-status').val();
    this.currentPage = 1;
    this.loadJobs();
  },
  /**
   * Reset filters
   */
  resetFilters: function resetFilters() {
    jQuery('#filter-type').val('');
    jQuery('#filter-status').val('');
    this.filters = {
      type: '',
      status: ''
    };
    this.currentPage = 1;
    this.loadJobs();
  },
  /**
   * Load jobs list
   */
  loadJobs: function loadJobs() {
    var _this2 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee() {
      var $loading, $table, offset, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee$(_context) {
        while (1) {
          switch (_context.prev = _context.next) {
            case 0:
              $loading = jQuery('.aie-jobs-loading');
              $table = jQuery('.aie-jobs-table-wrapper');
              $loading.show();
              $table.hide();
              _context.prev = 4;
              offset = (_this2.currentPage - 1) * _this2.perPage;
              console.log('Loading jobs with params:', {
                type: _this2.filters.type,
                status: _this2.filters.status,
                limit: _this2.perPage,
                offset: offset
              });
              _context.next = 9;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_job_list', {
                type: _this2.filters.type,
                status: _this2.filters.status,
                limit: _this2.perPage,
                offset: offset
              });
            case 9:
              response = _context.sent;
              console.log('Jobs response:', response);
              if (response && response.jobs) {
                _this2.totalJobs = response.total || 0;
                _this2.totalPages = Math.ceil(_this2.totalJobs / _this2.perPage);
                _this2.renderJobs(response.jobs);
                _this2.updatePagination();
              } else {
                console.error('Invalid response format:', response);
                _this2.renderJobs([]);
              }
              _context.next = 19;
              break;
            case 14:
              _context.prev = 14;
              _context.t0 = _context["catch"](4);
              console.error('Error loading jobs:', _context.t0);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.errorLoadingJobs + _context.t0.message, 'error');
              _this2.renderJobs([]);
            case 19:
              _context.prev = 19;
              $loading.hide();
              $table.show();
              return _context.finish(19);
            case 23:
            case "end":
              return _context.stop();
          }
        }
      }, _callee, null, [[4, 14, 19, 23]]);
    }))();
  },
  /**
   * Render jobs table
   */
  renderJobs: function renderJobs(jobs) {
    var _this3 = this;
    var $tbody = jQuery('#jobs-table-body');
    if (!jobs || jobs.length === 0) {
      $tbody.html('<tr class="no-items"><td colspan="9">No jobs found.</td></tr>');
      return;
    }
    var html = '';
    jobs.forEach(function (job) {
      html += _this3.renderJobRow(job);
    });
    $tbody.html(html);
  },
  /**
   * Render single job row
   */
  renderJobRow: function renderJobRow(job) {
    var statusClass = 'status-' + job.status;
    var typeLabel = this.getTypeLabel(job.type);
    var statusLabel = this.getStatusLabel(job.status);
    var progressBar = this.renderProgressBar(job);
    var actions = this.renderActions(job);
    return "\n\t\t\t<tr class=\"job-row ".concat(statusClass, "\" data-job-id=\"").concat(job.id, "\">\n\t\t\t\t<td class=\"column-id\">").concat(job.id, "</td>\n\t\t\t\t<td class=\"column-type\">\n\t\t\t\t\t<span class=\"job-type-badge job-type-").concat(job.type, "\">").concat(typeLabel, "</span>\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-data-type\">").concat(job.data_type, "</td>\n\t\t\t\t<td class=\"column-status\">\n\t\t\t\t\t<span class=\"job-status-badge job-status-").concat(job.status, "\">").concat(statusLabel, "</span>\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-progress\">").concat(progressBar, "</td>\n\t\t\t\t<td class=\"column-items\">\n\t\t\t\t\t<div class=\"items-info\">\n\t\t\t\t\t\t<div><strong>").concat(job.processed_items, "</strong> / ").concat(job.total_items, "</div>\n\t\t\t\t\t\t").concat(job.failed_items > 0 ? "<div class=\"failed-count\">Failed: ".concat(job.failed_items, "</div>") : '', "\n\t\t\t\t\t</div>\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-created\">").concat(this.formatDate(job.created_at), "</td>\n\t\t\t\t<td class=\"column-elapsed\">").concat(job.elapsed_time || '-', "</td>\n\t\t\t\t<td class=\"column-actions\">").concat(actions, "</td>\n\t\t\t</tr>\n\t\t");
  },
  /**
   * Render progress bar
   */
  renderProgressBar: function renderProgressBar(job) {
    var progress = job.progress || 0;
    return "\n\t\t\t<div class=\"progress-bar-wrapper\">\n\t\t\t\t<div class=\"progress-bar\">\n\t\t\t\t\t<div class=\"progress-bar-fill\" style=\"width: ".concat(progress, "%\"></div>\n\t\t\t\t</div>\n\t\t\t\t<span class=\"progress-text\">").concat(progress, "%</span>\n\t\t\t</div>\n\t\t");
  },
  /**
   * Render action buttons
   */
  renderActions: function renderActions(job) {
    var actions = [];

    // View details
    actions.push("<button class=\"button button-small job-action-view\" title=\"View Details\"><span class=\"dashicons dashicons-visibility\"></span></button>");

    // Resume
    if (job.can_resume) {
      actions.push("<button class=\"button button-small job-action-resume\" title=\"Resume\"><span class=\"dashicons dashicons-controls-play\"></span></button>");
    }

    // Retry - always available
    actions.push("<button class=\"button button-small job-action-retry\" title=\"Retry (Create new job with same parameters)\"><span class=\"dashicons dashicons-update\"></span></button>");

    // Download (for exports)
    if (job.type === 'export' && job.file_path && job.status === 'completed') {
      actions.push("<button class=\"button button-small job-action-download\" title=\"Download\"><span class=\"dashicons dashicons-download\"></span></button>");
    }

    // Delete
    if (job.can_delete) {
      actions.push("<button class=\"button button-small job-action-delete\" title=\"Delete\"><span class=\"dashicons dashicons-trash\"></span></button>");
    }
    return "<div class=\"job-actions\">".concat(actions.join(''), "</div>");
  },
  /**
   * Update pagination UI
   */
  updatePagination: function updatePagination() {
    var $pagination = jQuery('.aie-jobs-pagination');
    if (this.totalJobs === 0) {
      $pagination.hide();
      return;
    }
    $pagination.show();

    // Update info text
    var start = (this.currentPage - 1) * this.perPage + 1;
    var end = Math.min(this.currentPage * this.perPage, this.totalJobs);
    jQuery('.displaying-num').text("Showing ".concat(start, "-").concat(end, " of ").concat(this.totalJobs, " jobs"));

    // Update page numbers
    jQuery('.current-page').text(this.currentPage);
    jQuery('.total-pages').text(this.totalPages);

    // Update button states
    jQuery('.first-page, .prev-page').prop('disabled', this.currentPage === 1);
    jQuery('.next-page, .last-page').prop('disabled', this.currentPage >= this.totalPages);
  },
  /**
   * Go to page
   */
  goToPage: function goToPage(page) {
    if (page < 1 || page > this.totalPages || page === this.currentPage) {
      return;
    }
    this.currentPage = page;
    this.loadJobs();
  },
  /**
   * Resume job
   */
  resumeJob: function resumeJob(e) {
    var _this4 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee2() {
      var $button, $row, jobId, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee2$(_context2) {
        while (1) {
          switch (_context2.prev = _context2.next) {
            case 0:
              $button = jQuery(e.currentTarget);
              $row = $button.closest('tr');
              jobId = $row.data('job-id');
              if (confirm(window.aieData.i18n.confirmResumeJob)) {
                _context2.next = 5;
                break;
              }
              return _context2.abrupt("return");
            case 5:
              $button.prop('disabled', true);
              _context2.prev = 6;
              _context2.next = 9;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_job_resume', {
                job_id: jobId
              });
            case 9:
              response = _context2.sent;
              if (response && response.job_id) {
                _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.jobResumedSuccess, 'success');

                // Redirect based on job type
                _this4.redirectToJobPage(response.type, response.job_id);
              }
              _context2.next = 18;
              break;
            case 13:
              _context2.prev = 13;
              _context2.t0 = _context2["catch"](6);
              console.error('Error resuming job:', _context2.t0);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.errorResumingJob + _context2.t0.message, 'error');
              $button.prop('disabled', false);
            case 18:
            case "end":
              return _context2.stop();
          }
        }
      }, _callee2, null, [[6, 13]]);
    }))();
  },
  /**
   * Restart job
   */
  restartJob: function restartJob(e) {
    var _this5 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee3() {
      var $button, $row, jobId, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee3$(_context3) {
        while (1) {
          switch (_context3.prev = _context3.next) {
            case 0:
              $button = jQuery(e.currentTarget);
              $row = $button.closest('tr');
              jobId = $row.data('job-id');
              if (confirm(window.aieData.i18n.confirmRestartJob)) {
                _context3.next = 5;
                break;
              }
              return _context3.abrupt("return");
            case 5:
              $button.prop('disabled', true);
              _context3.prev = 6;
              _context3.next = 9;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_job_restart', {
                job_id: jobId
              });
            case 9:
              response = _context3.sent;
              if (response && response.job_id) {
                _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.jobRestartedSuccess, 'success');

                // Redirect based on job type
                _this5.redirectToJobPage(response.type, response.job_id);
              }
              _context3.next = 18;
              break;
            case 13:
              _context3.prev = 13;
              _context3.t0 = _context3["catch"](6);
              console.error('Error restarting job:', _context3.t0);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.errorRestartingJob + _context3.t0.message, 'error');
              $button.prop('disabled', false);
            case 18:
            case "end":
              return _context3.stop();
          }
        }
      }, _callee3, null, [[6, 13]]);
    }))();
  },
  /**
   * Retry job (create new job with processing status and show progress immediately)
   */
  retryJob: function retryJob(e) {
    var _this6 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee4() {
      var $button, $row, jobId, response, errorMsg;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee4$(_context4) {
        while (1) {
          switch (_context4.prev = _context4.next) {
            case 0:
              $button = jQuery(e.currentTarget);
              $row = $button.closest('tr');
              jobId = $row.data('job-id');
              if (confirm(window.aieData.i18n.confirmRetryJob)) {
                _context4.next = 5;
                break;
              }
              return _context4.abrupt("return");
            case 5:
              $button.prop('disabled', true);
              _context4.prev = 6;
              _context4.next = 9;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_job_retry', {
                job_id: jobId
              });
            case 9:
              response = _context4.sent;
              if (response && response.job_id && response.type) {
                _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.jobCreatedStarting, 'success');

                // Redirect to job page with resume_job parameter to show progress
                _this6.redirectToJobPage(response.type, response.job_id);
              }
              _context4.next = 19;
              break;
            case 13:
              _context4.prev = 13;
              _context4.t0 = _context4["catch"](6);
              console.error('Error retrying job:', _context4.t0);
              errorMsg = _context4.t0 && _context4.t0.message ? _context4.t0.message : 'Unknown error occurred';
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.errorRetryingJob + errorMsg, 'error');
              $button.prop('disabled', false);
            case 19:
            case "end":
              return _context4.stop();
          }
        }
      }, _callee4, null, [[6, 13]]);
    }))();
  },
  /**
   * Delete job
   */
  deleteJob: function deleteJob(e) {
    var $button = jQuery(e.currentTarget);
    var $row = $button.closest('tr');
    var jobId = $row.data('job-id');

    // Store job ID for confirmation
    this.deleteJobId = jobId;

    // Show confirmation modal
    jQuery('#confirm-delete-modal').show();
  },
  /**
   * Confirm delete
   */
  confirmDelete: function confirmDelete() {
    var _this7 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee5() {
      var jobId;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee5$(_context5) {
        while (1) {
          switch (_context5.prev = _context5.next) {
            case 0:
              if (_this7.deleteJobId) {
                _context5.next = 2;
                break;
              }
              return _context5.abrupt("return");
            case 2:
              jobId = _this7.deleteJobId;
              _this7.closeModal();
              _context5.prev = 4;
              _context5.next = 7;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_job_delete', {
                job_id: jobId
              });
            case 7:
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.jobDeletedSuccess, 'success');
              _this7.loadJobs(); // Reload list
              _context5.next = 15;
              break;
            case 11:
              _context5.prev = 11;
              _context5.t0 = _context5["catch"](4);
              console.error('Error deleting job:', _context5.t0);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.errorDeletingJob + _context5.t0.message, 'error');
            case 15:
            case "end":
              return _context5.stop();
          }
        }
      }, _callee5, null, [[4, 11]]);
    }))();
  },
  /**
   * Download file
   */
  downloadFile: function downloadFile(e) {
    var $button = jQuery(e.currentTarget);
    var $row = $button.closest('tr');
    var jobId = $row.data('job-id');

    // Request download URL with nonce from server
    jQuery.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action: 'aie_job_download_url',
        job_id: jobId,
        nonce: aieData.nonce
      },
      success: function success(response) {
        if (response.success && response.data.url) {
          window.location.href = response.data.url;
        } else {
          alert(response.data || window.aieData.i18n.downloadFailed);
        }
      },
      error: function error() {
        alert(window.aieData.i18n.failedGenerateDownloadUrl);
      }
    });
  },
  /**
   * View job details
   */
  viewJobDetails: function viewJobDetails(e) {
    var _this8 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee6() {
      var $button, $row, jobId, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee6$(_context6) {
        while (1) {
          switch (_context6.prev = _context6.next) {
            case 0:
              $button = jQuery(e.currentTarget);
              $row = $button.closest('tr');
              jobId = $row.data('job-id');
              _context6.prev = 3;
              _context6.next = 6;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_job_get', {
                job_id: jobId
              });
            case 6:
              response = _context6.sent;
              if (response) {
                _this8.showJobDetailsModal(response);
              }
              _context6.next = 14;
              break;
            case 10:
              _context6.prev = 10;
              _context6.t0 = _context6["catch"](3);
              console.error('Error loading job details:', _context6.t0);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.errorLoadingJobDetails + _context6.t0.message, 'error');
            case 14:
            case "end":
              return _context6.stop();
          }
        }
      }, _callee6, null, [[3, 10]]);
    }))();
  },
  /**
   * Show job details modal
   */
  showJobDetailsModal: function showJobDetailsModal(job) {
    var html = "\n\t\t\t<div class=\"job-details\">\n\t\t\t\t<table class=\"form-table\">\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>ID:</th>\n\t\t\t\t\t\t<td>".concat(job.id, "</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>Type:</th>\n\t\t\t\t\t\t<td>").concat(this.getTypeLabel(job.type), "</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>Data Type:</th>\n\t\t\t\t\t\t<td>").concat(job.data_type, "</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>File Format:</th>\n\t\t\t\t\t\t<td>").concat(job.file_format, "</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>Status:</th>\n\t\t\t\t\t\t<td><span class=\"job-status-badge job-status-").concat(job.status, "\">").concat(this.getStatusLabel(job.status), "</span></td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>Progress:</th>\n\t\t\t\t\t\t<td>").concat(job.progress || 0, "%</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>Items:</th>\n\t\t\t\t\t\t<td>").concat(job.processed_items, " / ").concat(job.total_items, " (Success: ").concat(job.success_items, ", Failed: ").concat(job.failed_items, ")</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>Created:</th>\n\t\t\t\t\t\t<td>").concat(job.created_at, "</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>Started:</th>\n\t\t\t\t\t\t<td>").concat(job.started_at || '-', "</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>Completed:</th>\n\t\t\t\t\t\t<td>").concat(job.completed_at || '-', "</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>File:</th>\n\t\t\t\t\t\t<td>").concat(job.file_path || '-', "</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t").concat(job.file_size ? "<tr><th>File Size:</th><td>".concat(job.file_size_human, "</td></tr>") : '', "\n\t\t\t\t</table>\n\t\t\t\t\n\t\t\t\t").concat(job.parameters ? "\n\t\t\t\t\t<h3>Parameters</h3>\n\t\t\t\t\t<pre class=\"job-parameters\">".concat(JSON.stringify(job.parameters, null, 2), "</pre>\n\t\t\t\t") : '', "\n\t\t\t</div>\n\t\t");
    jQuery('#job-details-content').html(html);
    jQuery('#job-details-modal').show();
  },
  /**
   * Close modal
   */
  closeModal: function closeModal() {
    jQuery('.aie-modal').hide();
    this.deleteJobId = null;
  },
  /**
   * Redirect to job page
   */
  redirectToJobPage: function redirectToJobPage(type, jobId) {
    var page = '';
    switch (type) {
      case 'export':
        page = 'wp-aie-export';
        break;
      case 'import':
        page = 'wp-advanced-import-export';
        break;
      case 'media_sync':
        page = 'wp-aie-media-sync';
        break;
    }
    if (page) {
      window.location.href = 'admin.php?page=' + page + '&resume_job=' + jobId;
    }
  },
  /**
   * Get type label
   */
  getTypeLabel: function getTypeLabel(type) {
    var labels = {
      'import': 'Import',
      'export': 'Export',
      'media_sync': 'Media Sync'
    };
    return labels[type] || type;
  },
  /**
   * Get status label
   */
  getStatusLabel: function getStatusLabel(status) {
    var labels = {
      'pending': 'Pending',
      'processing': 'Processing',
      'completed': 'Completed',
      'failed': 'Failed',
      'paused': 'Paused',
      'cancelled': 'Cancelled'
    };
    return labels[status] || status;
  },
  /**
   * Format date
   */
  formatDate: function formatDate(dateString) {
    if (!dateString) {
      return '-';
    }
    var date = new Date(dateString);
    return date.toLocaleString();
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (JobsLogModule);

/***/ }),

/***/ "./src/js/modules/media_sync.js":
/*!**************************************!*\
  !*** ./src/js/modules/media_sync.js ***!
  \**************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utils */ "./src/js/modules/utils.js");
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _slicedToArray(arr, i) { return _arrayWithHoles(arr) || _iterableToArrayLimit(arr, i) || _unsupportedIterableToArray(arr, i) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }
function _iterableToArrayLimit(arr, i) { var _i = arr == null ? null : typeof Symbol !== "undefined" && arr[Symbol.iterator] || arr["@@iterator"]; if (_i == null) return; var _arr = []; var _n = true; var _d = false; var _s, _e; try { for (_i = _i.call(arr); !(_n = (_s = _i.next()).done); _n = true) { _arr.push(_s.value); if (i && _arr.length === i) break; } } catch (err) { _d = true; _e = err; } finally { try { if (!_n && _i["return"] != null) _i["return"](); } finally { if (_d) throw _e; } } return _arr; }
function _arrayWithHoles(arr) { if (Array.isArray(arr)) return arr; }
/**
 * Media Sync Module
 *
 * Handles media folder sync functionality
 */


var MediaSyncModule = {
  jobId: null,
  progressInterval: null,
  scannedFiles: [],
  isPaused: false,
  /**
   * Initialize module
   */
  init: function init() {
    if (!jQuery('#wp-aie-media-sync').length) {
      return;
    }
    this.bindEvents();
  },
  /**
   * Bind event handlers
   */
  bindEvents: function bindEvents() {
    var _this = this;
    var $page = jQuery('#wp-aie-media-sync');

    // File type selection
    $page.on('change', '#aie-file-types', function (e) {
      var val = jQuery(e.target).val();
      if (val === 'custom') {
        jQuery('#aie-custom-extensions').show();
      } else {
        jQuery('#aie-custom-extensions').hide();
      }
    });

    // Scan folder button
    $page.on('click', '#aie-scan-folder-btn', function (e) {
      e.preventDefault();
      _this.scanFolder();
    });

    // Select all files
    $page.on('change', '#aie-select-all-files', function (e) {
      var checked = jQuery(e.target).is(':checked');
      jQuery('.aie-file-checkbox').prop('checked', checked);
      _this.updateSelectedCount();
    });

    // Individual file selection
    $page.on('change', '.aie-file-checkbox', function () {
      _this.updateSelectedCount();
    });

    // Start sync button
    $page.on('click', '#aie-start-sync-btn', function (e) {
      e.preventDefault();
      _this.startSync();
    });

    // Pause sync
    $page.on('click', '#aie-pause-sync-btn', function (e) {
      e.preventDefault();
      if (_this.isPaused) {
        _this.resumeSync();
      } else {
        _this.pauseSync();
      }
    });

    // Cancel sync
    $page.on('click', '#aie-cancel-sync-btn', function (e) {
      e.preventDefault();
      _this.cancelSync();
    });

    // Sync another folder
    $page.on('click', '#aie-sync-another-btn', function (e) {
      e.preventDefault();
      _this.resetPage();
    });

    // Browse folders button
    $page.on('click', '#aie-browse-folders-btn', function (e) {
      e.preventDefault();
      _this.openFolderBrowser();
    });

    // Close modal
    $page.on('click', '.aie-modal-close, .aie-modal-overlay', function (e) {
      e.preventDefault();
      _this.closeFolderBrowser();
    });

    // Folder item click
    $page.on('click', '.aie-folder-item', function (e) {
      e.preventDefault();
      var $item = jQuery(e.currentTarget);

      // Toggle selection
      jQuery('.aie-folder-item').removeClass('selected');
      $item.addClass('selected');

      // Enable choose button and update path
      var path = $item.data('path');
      jQuery('#aie-selected-folder-path').val(path);
      jQuery('#aie-choose-folder-btn').prop('disabled', false);
    });

    // Folder double-click to navigate
    $page.on('dblclick', '.aie-folder-item', function (e) {
      e.preventDefault();
      var $item = jQuery(e.currentTarget);
      var path = $item.data('path');
      _this.browseFolders(path);
    });

    // Go up button
    $page.on('click', '#aie-folder-up-btn', function (e) {
      e.preventDefault();
      var currentPath = jQuery('#aie-current-path').data('relative-path');
      if (currentPath) {
        var parts = currentPath.split('/').filter(function (p) {
          return p;
        });
        parts.pop();
        var parentPath = parts.join('/');
        _this.browseFolders(parentPath);
      }
    });

    // Choose folder button
    $page.on('click', '#aie-choose-folder-btn', function (e) {
      e.preventDefault();
      var path = jQuery('#aie-selected-folder-path').val();
      if (path) {
        jQuery('#aie-folder-path').val(path);
        _this.closeFolderBrowser();
      }
    });
  },
  /**
   * Scan folder for media files
   */
  scanFolder: function scanFolder() {
    var _window$aieData,
      _window$aieData2,
      _this2 = this;
    // Reset any previous sync state
    this.jobId = null;
    this.isPaused = false;

    // Hide progress and completion sections if they were visible
    jQuery('#aie-sync-progress-section, #aie-sync-completion').hide();
    var folderPath = jQuery('#aie-folder-path').val().trim();
    if (!folderPath) {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.enterFolderPath, 'error');
      return;
    }

    // If path doesn't start with /, add it
    if (!folderPath.startsWith('/')) {
      folderPath = '/' + folderPath;
    }

    // Remove trailing slash if present
    if (folderPath.length > 1 && folderPath.endsWith('/')) {
      folderPath = folderPath.slice(0, -1);
    }
    var options = {
      recursive: jQuery('#aie-scan-recursive').is(':checked'),
      file_types: jQuery('#aie-file-types').val()
    };
    if (options.file_types === 'custom') {
      options.custom_types = jQuery('#aie-custom-extensions-input').val().split(',').map(function (ext) {
        return ext.trim();
      }).filter(function (ext) {
        return ext;
      });
    }
    jQuery('#aie-scan-folder-btn').prop('disabled', true).text('Scanning...');
    jQuery.ajax({
      url: ((_window$aieData = window.aieData) === null || _window$aieData === void 0 ? void 0 : _window$aieData.ajaxUrl) || window.ajaxurl,
      method: 'POST',
      data: {
        action: 'aie_scan_folder',
        nonce: ((_window$aieData2 = window.aieData) === null || _window$aieData2 === void 0 ? void 0 : _window$aieData2.nonce) || '',
        folder_path: folderPath,
        options: options
      }
    }).done(function (response) {
      if (response.success) {
        _this2.scannedFiles = response.data.files || [];
        if (_this2.scannedFiles.length === 0) {
          // Show empty state message
          _this2.showEmptyState();
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('No files found matching the criteria', 'info');
        } else {
          // Show summary instead of file list
          _this2.displayScanSummary(_this2.scannedFiles);
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice("Found ".concat(_this2.scannedFiles.length, " files ready to sync"), 'success');

          // Show sync options
          jQuery('#aie-sync-options').slideDown();
        }
      } else {
        var _response$data;
        _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(((_response$data = response.data) === null || _response$data === void 0 ? void 0 : _response$data.message) || 'Scan failed', 'error');
      }
    }).fail(function () {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.requestFailed, 'error');
    }).always(function () {
      jQuery('#aie-scan-folder-btn').prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Scan Folder');
    });
  },
  /**
   * Show empty state when no files found
   */
  showEmptyState: function showEmptyState() {
    var $list = jQuery('#aie-file-list');
    $list.html("\n\t\t\t<div class=\"aie-empty-state\">\n\t\t\t\t<span class=\"dashicons dashicons-search\"></span>\n\t\t\t\t<h3>No Files Found</h3>\n\t\t\t\t<p>No files matching your criteria were found in the selected folder.</p>\n\t\t\t\t<div class=\"aie-empty-suggestions\">\n\t\t\t\t\t<strong>Suggestions:</strong>\n\t\t\t\t\t<ul>\n\t\t\t\t\t\t<li>Check if the folder path is correct</li>\n\t\t\t\t\t\t<li>Try enabling \"Scan Recursive\" to search in subfolders</li>\n\t\t\t\t\t\t<li>Change the file type filter</li>\n\t\t\t\t\t\t<li>Make sure the folder contains supported media files</li>\n\t\t\t\t\t</ul>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t");

    // Show scan results section but hide stats
    jQuery('#aie-scan-results .aie-scan-stats').hide();
    jQuery('#aie-scan-results').slideDown();

    // Hide sync options
    jQuery('#aie-sync-options').hide();
  },
  /**
   * Display scan summary (instead of full file list)
   */
  displayScanSummary: function displayScanSummary(files) {
    if (!files || files.length === 0) {
      jQuery('#aie-scan-results').hide();
      return;
    }
    var totalSize = 0;
    var fileTypes = {};
    files.forEach(function (file) {
      totalSize += file.size || 0;

      // Count file types
      var ext = file.name.split('.').pop().toLowerCase();
      fileTypes[ext] = (fileTypes[ext] || 0) + 1;
    });

    // Display summary
    var $list = jQuery('#aie-file-list');
    $list.html("\n\t\t\t<div class=\"aie-scan-summary\">\n\t\t\t\t<div class=\"aie-summary-icon\">\n\t\t\t\t\t<span class=\"dashicons dashicons-yes-alt\"></span>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-summary-content\">\n\t\t\t\t\t<h3>Scan Complete</h3>\n\t\t\t\t\t<p>Found <strong>".concat(files.length, " files</strong> ready for synchronization (Total: <strong>").concat(_utils__WEBPACK_IMPORTED_MODULE_0__["default"].formatBytes(totalSize), "</strong>)</p>\n\t\t\t\t\t<div class=\"aie-file-types\">\n\t\t\t\t\t\t<strong>File Types:</strong>\n\t\t\t\t\t\t").concat(Object.entries(fileTypes).map(function (_ref) {
      var _ref2 = _slicedToArray(_ref, 2),
        ext = _ref2[0],
        count = _ref2[1];
      return "<span class=\"aie-type-badge\">".concat(ext.toUpperCase(), " (").concat(count, ")</span>");
    }).join(''), "\n\t\t\t\t\t</div>\n\t\t\t\t\t<p class=\"aie-summary-note\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t\t\tAll files will be processed in batches. Click \"Start Sync\" below to begin.\n\t\t\t\t\t</p>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t"));

    // Update stats
    jQuery('#aie-total-files').text(files.length);
    jQuery('#aie-total-size').text(_utils__WEBPACK_IMPORTED_MODULE_0__["default"].formatBytes(totalSize));

    // Show stats and scan results
    jQuery('#aie-scan-results .aie-scan-stats').show();
    jQuery('#aie-scan-results').slideDown();
  },
  /**
   * Display scanned files (old method - keeping for compatibility)
   */
  displayFiles: function displayFiles(files) {
    var _this3 = this;
    var $list = jQuery('#aie-file-list');
    $list.empty();
    if (!files || files.length === 0) {
      jQuery('#aie-scan-results').hide();
      return;
    }
    var totalSize = 0;
    files.forEach(function (file) {
      totalSize += file.size || 0;
      var icon = _this3.getFileIcon(file.name);
      var $item = jQuery("\n\t\t\t\t<div class=\"aie-file-item\">\n\t\t\t\t\t<input type=\"checkbox\" class=\"aie-file-checkbox\" value=\"".concat(file.path, "\" checked>\n\t\t\t\t\t<div class=\"aie-file-icon\">\n\t\t\t\t\t\t<span class=\"dashicons ").concat(icon, "\"></span>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-file-info\">\n\t\t\t\t\t\t<div class=\"aie-file-name\">").concat(_this3.escapeHtml(file.name), "</div>\n\t\t\t\t\t\t<div class=\"aie-file-meta\">\n\t\t\t\t\t\t\t<span>").concat(_utils__WEBPACK_IMPORTED_MODULE_0__["default"].formatBytes(file.size), "</span>\n\t\t\t\t\t\t\t<span>").concat(_this3.escapeHtml(file.path), "</span>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t"));
      $list.append($item);
    });

    // Update stats
    jQuery('#aie-total-files').text(files.length);
    jQuery('#aie-total-size').text(_utils__WEBPACK_IMPORTED_MODULE_0__["default"].formatBytes(totalSize));

    // Show stats and scan results
    jQuery('#aie-scan-results .aie-scan-stats').show();
    jQuery('#aie-scan-results').slideDown();
    this.updateSelectedCount();
  },
  /**
   * Get file icon based on extension
   */
  getFileIcon: function getFileIcon(filename) {
    var ext = filename.split('.').pop().toLowerCase();
    var icons = {
      // Images
      jpg: 'dashicons-format-image',
      jpeg: 'dashicons-format-image',
      png: 'dashicons-format-image',
      gif: 'dashicons-format-image',
      webp: 'dashicons-format-image',
      svg: 'dashicons-format-image',
      // Videos
      mp4: 'dashicons-format-video',
      avi: 'dashicons-format-video',
      mov: 'dashicons-format-video',
      wmv: 'dashicons-format-video',
      // Audio
      mp3: 'dashicons-format-audio',
      wav: 'dashicons-format-audio',
      ogg: 'dashicons-format-audio',
      // Documents
      pdf: 'dashicons-pdf',
      doc: 'dashicons-media-document',
      docx: 'dashicons-media-document',
      xls: 'dashicons-media-spreadsheet',
      xlsx: 'dashicons-media-spreadsheet'
    };
    return icons[ext] || 'dashicons-media-default';
  },
  /**
   * Update selected files count
   */
  updateSelectedCount: function updateSelectedCount() {
    var count = jQuery('.aie-file-checkbox:checked').length;
    jQuery('#aie-selected-count').text(count);

    // Update select all checkbox
    var total = jQuery('.aie-file-checkbox').length;
    jQuery('#aie-select-all-files').prop('checked', count === total);
  },
  /**
   * Get selected files
   */
  getSelectedFiles: function getSelectedFiles() {
    var files = [];
    var self = this;
    jQuery('.aie-file-checkbox:checked').each(function () {
      var path = jQuery(this).val();
      var fileData = self.scannedFiles.find(function (f) {
        return f.path === path;
      });
      if (fileData) {
        files.push(fileData);
      }
    });
    return files;
  },
  /**
   * Get sync options
   */
  getOptions: function getOptions() {
    var fileOperation = jQuery('#aie-copy-files').val();
    var batchSize = parseInt(jQuery('#aie-batch-size').val()) || 3;
    return {
      duplicate_check: jQuery('#aie-duplicate-check').val(),
      duplicate_handling: jQuery('#aie-duplicate-handling').val(),
      file_operation: fileOperation,
      copy_files: fileOperation === 'copy',
      generate_thumbnails: true,
      // Always generate thumbnails
      rml_integration: jQuery('#aie-rml-integration').is(':checked'),
      batch_size: batchSize
    };
  },
  /**
   * Start media sync
   */
  startSync: function startSync() {
    var _window$aieData3,
      _window$aieData4,
      _this4 = this;
    if (!this.scannedFiles || this.scannedFiles.length === 0) {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.noFilesToSync, 'error');
      return;
    }

    // Clear any previous progress interval
    if (this.progressInterval) {
      clearInterval(this.progressInterval);
      this.progressInterval = null;
    }

    // Reset state
    this.isPaused = false;
    var folderPath = jQuery('#aie-folder-path').val().trim();
    if (!folderPath) {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.invalidFolderPath, 'error');
      return;
    }

    // Get scan options (for filtering on backend)
    var scanOptions = {
      recursive: jQuery('#aie-scan-recursive').is(':checked'),
      file_types: jQuery('#aie-file-types').val()
    };
    if (scanOptions.file_types === 'custom') {
      scanOptions.custom_types = jQuery('#aie-custom-extensions-input').val().split(',').map(function (ext) {
        return ext.trim();
      }).filter(function (ext) {
        return ext;
      });
    }
    var syncOptions = this.getOptions();

    // Disable button and show loading state
    var $btn = jQuery('#aie-start-sync-btn');
    var originalText = $btn.html();
    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update aie-spin"></span> Starting...');
    jQuery.ajax({
      url: ((_window$aieData3 = window.aieData) === null || _window$aieData3 === void 0 ? void 0 : _window$aieData3.ajaxUrl) || window.ajaxurl,
      method: 'POST',
      data: {
        action: 'aie_start_media_sync',
        nonce: ((_window$aieData4 = window.aieData) === null || _window$aieData4 === void 0 ? void 0 : _window$aieData4.nonce) || '',
        folder_path: folderPath,
        scan_options: scanOptions,
        sync_options: syncOptions
      }
    }).done(function (response) {
      if (response.success) {
        _this4.jobId = response.data.job_id;

        // Hide scan and options sections
        jQuery('.aie-scan-section, .aie-options-section').slideUp();

        // Show progress section immediately
        jQuery('#aie-sync-progress-section').slideDown();

        // Initialize progress at 0%
        jQuery('#aie-progress-fill').css('width', '0%');
        jQuery('#aie-progress-percentage').text('0%');
        jQuery('#aie-sync-status').text('Processing...');

        // Start tracking progress
        _this4.startProgressTracking();

        // Trigger first batch processing immediately
        _this4.triggerBatchProcessing();
        _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.syncStarted, 'success');
      } else {
        var _response$data2;
        _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(((_response$data2 = response.data) === null || _response$data2 === void 0 ? void 0 : _response$data2.message) || 'Failed to start sync', 'error');
        $btn.prop('disabled', false).html(originalText);
      }
    }).fail(function () {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.requestFailed, 'error');
      $btn.prop('disabled', false).html(originalText);
    });
  },
  /**
   * Start progress tracking
   */
  startProgressTracking: function startProgressTracking() {
    var _this5 = this;
    // Check progress every 2 seconds
    this.progressInterval = setInterval(function () {
      _this5.checkProgress();
    }, 2000);

    // Check immediately
    this.checkProgress();
  },
  /**
   * Trigger batch processing (starts the actual work)
   */
  triggerBatchProcessing: function triggerBatchProcessing() {
    var _window$aieData5,
      _window$aieData6,
      _this6 = this;
    jQuery.ajax({
      url: ((_window$aieData5 = window.aieData) === null || _window$aieData5 === void 0 ? void 0 : _window$aieData5.ajaxUrl) || window.ajaxurl,
      method: 'POST',
      data: {
        action: 'aie_process_media_sync_batch',
        nonce: ((_window$aieData6 = window.aieData) === null || _window$aieData6 === void 0 ? void 0 : _window$aieData6.nonce) || '',
        job_id: this.jobId
      }
    }).done(function (response) {
      console.log('Batch processing response:', response);

      // If not completed, process next batch after small delay
      if (response.success && response.data && !response.data.completed) {
        setTimeout(function () {
          _this6.triggerBatchProcessing();
        }, 100);
      }
    }).fail(function (xhr, status, error) {
      console.error('Batch processing failed:', status, error);
    });
  },
  /**
   * Check sync progress
   */
  checkProgress: function checkProgress() {
    var _window$aieData7,
      _window$aieData8,
      _this7 = this;
    jQuery.ajax({
      url: ((_window$aieData7 = window.aieData) === null || _window$aieData7 === void 0 ? void 0 : _window$aieData7.ajaxUrl) || window.ajaxurl,
      method: 'POST',
      data: {
        action: 'aie_get_sync_progress',
        nonce: ((_window$aieData8 = window.aieData) === null || _window$aieData8 === void 0 ? void 0 : _window$aieData8.nonce) || '',
        job_id: this.jobId
      }
    }).done(function (response) {
      console.log('=== Progress Response ===', response);
      if (response.success && response.data) {
        console.log('  Status:', response.data.status);
        console.log('  Progress:', response.data.progress);
        console.log('  Result (raw):', response.data.result);
        console.log('  Result type:', _typeof(response.data.result));
        _this7.updateProgress(response.data);
      } else {
        console.error('Progress error:', response);
      }
    }).fail(function (xhr, status, error) {
      console.error('Progress AJAX failed:', status, error);
    });
  },
  /**
   * Update stats only (helper method)
   */
  updateStats: function updateStats(result) {
    // Ensure result is an object
    if (typeof result === 'string') {
      try {
        result = JSON.parse(result);
      } catch (e) {
        result = {};
      }
    }
    result = result || {};
    var processed = result.processed !== undefined ? result.processed : 0;
    var success = result.success !== undefined ? result.success : 0;
    var skipped = result.skipped !== undefined ? result.skipped : 0;
    var failed = result.failed !== undefined ? result.failed : 0;
    jQuery('#aie-stat-processed').text(processed);
    jQuery('#aie-stat-success').text(success);
    jQuery('#aie-stat-skipped').text(skipped);
    jQuery('#aie-stat-failed').text(failed);
  },
  /**
   * Update progress UI
   */
  updateProgress: function updateProgress(data) {
    console.log('=== Update Progress Called ===');
    console.log('  Raw data:', data);

    // Parse progress as integer (remove decimals)
    var progress = Math.round(parseFloat(data.progress) || 0);
    var status = data.status || 'processing';
    console.log('  Parsed progress:', progress);
    console.log('  Status:', status);

    // Update progress bar
    jQuery('#aie-progress-fill').css('width', progress + '%');
    jQuery('#aie-progress-percentage').text(progress + '%');

    // Update stats - handle both object and null
    var result = data.result;
    console.log('  Result (before parse):', result);
    console.log('  Result type:', _typeof(result));

    // If result is a string, try to parse it
    if (typeof result === 'string') {
      try {
        result = JSON.parse(result);
        console.log('  Result after JSON.parse:', result);
      } catch (e) {
        console.error('  Failed to parse result:', result, e);
        result = {};
      }
    }

    // Ensure result is an object
    result = result || {};
    console.log('  Final result object:', result);
    console.log('  Processed:', result.processed);
    console.log('  Success:', result.success);
    console.log('  Skipped:', result.skipped);
    console.log('  Failed:', result.failed);

    // Update stats with explicit checks
    // Show 0 if undefined (processing hasn't generated results yet)
    var processed = result.processed !== undefined ? result.processed : 0;
    var success = result.success !== undefined ? result.success : 0;
    var skipped = result.skipped !== undefined ? result.skipped : 0;
    var failed = result.failed !== undefined ? result.failed : 0;
    console.log('  Setting values:', {
      processed: processed,
      success: success,
      skipped: skipped,
      failed: failed
    });
    jQuery('#aie-stat-processed').text(processed);
    jQuery('#aie-stat-success').text(success);
    jQuery('#aie-stat-skipped').text(skipped);
    jQuery('#aie-stat-failed').text(failed);
    console.log('  DOM updated');

    // Update status text (fix selector - was #aie-progress-status, should be #aie-sync-status)
    var statusTexts = {
      pending: 'Starting...',
      processing: 'Synchronization in Progress',
      completed: 'Completed',
      failed: 'Failed',
      cancelled: 'Cancelled',
      paused: 'Paused'
    };
    var statusText = statusTexts[status] || 'Processing...';
    console.log('  Setting status text:', statusText);

    // Update both possible selectors to be safe
    jQuery('#aie-sync-status').text(statusText);
    jQuery('#aie-progress-status').text(statusText);

    // Show errors if any
    if (result.errors && result.errors.length > 0) {
      this.displayErrors(result.errors);
    }

    // Check if completed
    if (status === 'completed' || status === 'failed' || status === 'cancelled') {
      clearInterval(this.progressInterval);
      this.showCompletion(data);
    }
  },
  /**
   * Display errors
   */
  displayErrors: function displayErrors(errors) {
    var _this8 = this;
    var $errorLog = jQuery('#aie-error-log');
    var $errorList = jQuery('#aie-error-list');
    $errorList.empty();
    errors.slice(0, 20).forEach(function (error) {
      $errorList.append("<li>".concat(_this8.escapeHtml(error), "</li>"));
    });
    if (errors.length > 20) {
      $errorList.append("<li>... and ".concat(errors.length - 20, " more errors</li>"));
    }
    $errorLog.show();
  },
  /**
   * Show completion
   */
  showCompletion: function showCompletion(data) {
    // Hide progress section
    jQuery('#aie-sync-progress-section').slideUp();

    // Show completion section
    jQuery('#aie-sync-completion').slideDown();

    // Parse result if needed
    var result = data.result;
    if (typeof result === 'string') {
      try {
        result = JSON.parse(result);
      } catch (e) {
        result = {};
      }
    }
    result = result || {};

    // Get stats
    var processed = result.processed || 0;
    var success = result.success || 0;
    var skipped = result.skipped || 0;
    var failed = result.failed || 0;

    // Create beautiful completion message
    var messageHtml = '';
    if (data.status === 'completed') {
      // Success message with emoji and stats
      messageHtml = "\n\t\t\t\t<div style=\"text-align: center; padding: 20px;\">\n\t\t\t\t\t<div style=\"font-size: 64px; margin-bottom: 15px;\">\uD83C\uDF89</div>\n\t\t\t\t\t<h3 style=\"color: #00a32a; margin: 0 0 15px; font-size: 24px;\">Synchronization Complete!</h3>\n\t\t\t\t\t<p style=\"font-size: 16px; color: #1d2327; margin-bottom: 20px;\">\n\t\t\t\t\t\tSuccessfully processed <strong>".concat(processed, "</strong> file").concat(processed !== 1 ? 's' : '', "\n\t\t\t\t\t</p>\n\t\t\t\t\t<div style=\"display: flex; justify-content: center; gap: 30px; flex-wrap: wrap;\">\n\t\t\t\t\t\t<div style=\"text-align: center;\">\n\t\t\t\t\t\t\t<div style=\"font-size: 32px; color: #00a32a; font-weight: 600;\">").concat(success, "</div>\n\t\t\t\t\t\t\t<div style=\"font-size: 12px; color: #646970; text-transform: uppercase;\">\u2705 Imported</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t").concat(skipped > 0 ? "\n\t\t\t\t\t\t<div style=\"text-align: center;\">\n\t\t\t\t\t\t\t<div style=\"font-size: 32px; color: #dba617; font-weight: 600;\">".concat(skipped, "</div>\n\t\t\t\t\t\t\t<div style=\"font-size: 12px; color: #646970; text-transform: uppercase;\">\u23ED\uFE0F Skipped</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t") : '', "\n\t\t\t\t\t\t").concat(failed > 0 ? "\n\t\t\t\t\t\t<div style=\"text-align: center;\">\n\t\t\t\t\t\t\t<div style=\"font-size: 32px; color: #d63638; font-weight: 600;\">".concat(failed, "</div>\n\t\t\t\t\t\t\t<div style=\"font-size: 12px; color: #646970; text-transform: uppercase;\">\u274C Failed</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t") : '', "\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t");
    } else if (data.status === 'failed') {
      messageHtml = "\n\t\t\t\t<div style=\"text-align: center; padding: 20px;\">\n\t\t\t\t\t<div style=\"font-size: 64px; margin-bottom: 15px;\">\u26A0\uFE0F</div>\n\t\t\t\t\t<h3 style=\"color: #d63638; margin: 0 0 15px; font-size: 24px;\">Synchronization Failed</h3>\n\t\t\t\t\t<p style=\"font-size: 16px; color: #646970;\">\n\t\t\t\t\t\tThe synchronization process encountered an error and could not complete.\n\t\t\t\t\t</p>\n\t\t\t\t</div>\n\t\t\t";
    } else if (data.status === 'cancelled') {
      messageHtml = "\n\t\t\t\t<div style=\"text-align: center; padding: 20px;\">\n\t\t\t\t\t<div style=\"font-size: 64px; margin-bottom: 15px;\">\uD83D\uDED1</div>\n\t\t\t\t\t<h3 style=\"color: #dba617; margin: 0 0 15px; font-size: 24px;\">Synchronization Cancelled</h3>\n\t\t\t\t\t<p style=\"font-size: 16px; color: #646970;\">\n\t\t\t\t\t\tProcessed <strong>".concat(processed, "</strong> file").concat(processed !== 1 ? 's' : '', " before cancellation.\n\t\t\t\t\t</p>\n\t\t\t\t</div>\n\t\t\t");
    }
    jQuery('#aie-completion-message').html(messageHtml);
  },
  /**
   * Pause sync
   */
  pauseSync: function pauseSync() {
    var _window$aieData9,
      _window$aieData10,
      _this9 = this;
    jQuery.ajax({
      url: ((_window$aieData9 = window.aieData) === null || _window$aieData9 === void 0 ? void 0 : _window$aieData9.ajaxUrl) || window.ajaxurl,
      method: 'POST',
      data: {
        action: 'aie_pause_media_sync',
        nonce: ((_window$aieData10 = window.aieData) === null || _window$aieData10 === void 0 ? void 0 : _window$aieData10.nonce) || '',
        job_id: this.jobId
      }
    }).done(function (response) {
      if (response.success) {
        _this9.isPaused = true;
        clearInterval(_this9.progressInterval);

        // Update UI
        var $header = jQuery('#aie-sync-progress-section .aie-card-header h2');
        $header.html('<span class="dashicons dashicons-controls-pause"></span> Synchronization Paused');

        // Update status text
        jQuery('#aie-progress-status').text('Paused');
        jQuery('#aie-sync-status').text('Paused');
        var $pauseBtn = jQuery('#aie-pause-sync-btn');
        $pauseBtn.html('<span class="dashicons dashicons-controls-play"></span> Resume');
        _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.syncPaused, 'info');
      }
    });
  },
  /**
   * Resume sync
   */
  resumeSync: function resumeSync() {
    var _window$aieData11,
      _window$aieData12,
      _this10 = this;
    jQuery.ajax({
      url: ((_window$aieData11 = window.aieData) === null || _window$aieData11 === void 0 ? void 0 : _window$aieData11.ajaxUrl) || window.ajaxurl,
      method: 'POST',
      data: {
        action: 'aie_resume_media_sync',
        nonce: ((_window$aieData12 = window.aieData) === null || _window$aieData12 === void 0 ? void 0 : _window$aieData12.nonce) || '',
        job_id: this.jobId
      }
    }).done(function (response) {
      if (response.success) {
        _this10.isPaused = false;

        // Update UI
        var $header = jQuery('#aie-sync-progress-section .aie-card-header h2');
        $header.html('<span class="dashicons dashicons-update aie-spin"></span> Synchronization in Progress');

        // Update status text
        jQuery('#aie-progress-status').text('Synchronization in Progress');
        jQuery('#aie-sync-status').text('Synchronization in Progress');
        var $pauseBtn = jQuery('#aie-pause-sync-btn');
        $pauseBtn.html('<span class="dashicons dashicons-controls-pause"></span> Pause');

        // Restart progress monitoring
        _this10.startProgressTracking();

        // Trigger batch processing to continue
        _this10.triggerBatchProcessing();
        _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.syncResumed, 'success');
      }
    });
  },
  /**
   * Cancel sync
   */
  cancelSync: function cancelSync() {
    var _window$aieData13,
      _window$aieData14,
      _this11 = this;
    if (!confirm(window.aieData.i18n.confirmCancelSync)) {
      return;
    }
    jQuery.ajax({
      url: ((_window$aieData13 = window.aieData) === null || _window$aieData13 === void 0 ? void 0 : _window$aieData13.ajaxUrl) || window.ajaxurl,
      method: 'POST',
      data: {
        action: 'aie_cancel_media_sync',
        nonce: ((_window$aieData14 = window.aieData) === null || _window$aieData14 === void 0 ? void 0 : _window$aieData14.nonce) || '',
        job_id: this.jobId
      }
    }).done(function (response) {
      if (response.success) {
        clearInterval(_this11.progressInterval);
        _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.syncCancelled, 'warning');
        _this11.resetPage();
      }
    });
  },
  /**
   * Reset page to initial state
   */
  resetPage: function resetPage() {
    // Hide all sections
    jQuery('#aie-scan-results, #aie-sync-options, #aie-sync-progress-section, #aie-sync-completion').hide();

    // Show scan section
    jQuery('.aie-scan-section').show();

    // Reset form
    jQuery('#aie-folder-path').val('');
    jQuery('#aie-file-list').empty();

    // Reset Start button
    var $startBtn = jQuery('#aie-start-sync-btn');
    $startBtn.prop('disabled', false);
    $startBtn.html('<span class="dashicons dashicons-controls-play"></span> Start Sync');

    // Reset Scan button
    var $scanBtn = jQuery('#aie-scan-folder-btn');
    $scanBtn.prop('disabled', false).text('Scan Folder');

    // Reset progress bar and stats
    jQuery('#aie-progress-fill').css('width', '0%');
    jQuery('#aie-progress-percentage').text('0%');
    jQuery('#aie-stat-processed').text('0');
    jQuery('#aie-stat-success').text('0');
    jQuery('#aie-stat-skipped').text('0');
    jQuery('#aie-stat-failed').text('0');

    // Reset data
    this.jobId = null;
    this.scannedFiles = [];
    this.isPaused = false;
    if (this.progressInterval) {
      clearInterval(this.progressInterval);
    }

    // Reset pause button to default state
    var $pauseBtn = jQuery('#aie-pause-sync-btn');
    $pauseBtn.html('<span class="dashicons dashicons-controls-pause"></span> Pause');

    // Reset header to default state
    var $header = jQuery('#aie-sync-progress-section .aie-card-header h2');
    $header.html('<span class="dashicons dashicons-update aie-spin"></span> Synchronization in Progress');
  },
  /**
   * Escape HTML
   */
  escapeHtml: function escapeHtml(text) {
    var map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  },
  /**
   * Open folder browser modal
   */
  openFolderBrowser: function openFolderBrowser() {
    jQuery('#aie-folder-browser-modal').fadeIn(200);
    jQuery('body').addClass('aie-modal-open');

    // Load current folder from input or start from root
    var currentFolder = jQuery('#aie-folder-path').val().trim();
    this.browseFolders(currentFolder); // Load current or root uploads directory
  },
  /**
   * Close folder browser modal
   */
  closeFolderBrowser: function closeFolderBrowser() {
    jQuery('#aie-folder-browser-modal').fadeOut(200);
    jQuery('body').removeClass('aie-modal-open');
    // Don't clear selected path - it's just a temporary selection within modal
    jQuery('#aie-choose-folder-btn').prop('disabled', true);
    jQuery('.aie-folder-item').removeClass('selected');
  },
  /**
   * Browse folders via AJAX
   */
  browseFolders: function browseFolders(relativePath) {
    var _window$aieData15,
      _window$aieData16,
      _this12 = this;
    jQuery('#aie-folder-browser-loading').show();
    jQuery('#aie-folder-browser-list').empty();
    jQuery('#aie-folder-browser-empty').hide();
    jQuery('#aie-folder-browser-error').hide();
    jQuery('#aie-selected-folder-path').val('');
    jQuery('#aie-choose-folder-btn').prop('disabled', true);
    jQuery.ajax({
      url: window.ajaxurl || ((_window$aieData15 = window.aieData) === null || _window$aieData15 === void 0 ? void 0 : _window$aieData15.ajaxUrl),
      type: 'POST',
      data: {
        action: 'aie_browse_folders',
        nonce: (_window$aieData16 = window.aieData) === null || _window$aieData16 === void 0 ? void 0 : _window$aieData16.nonce,
        path: relativePath
      }
    }).done(function (response) {
      if (response.success && response.data) {
        _this12.displayFolders(response.data.folders, response.data.current_path);
      } else {
        var _response$data3;
        // Debug output
        console.error('Browse folders failed:', response);
        _this12.showBrowserError(((_response$data3 = response.data) === null || _response$data3 === void 0 ? void 0 : _response$data3.message) || 'Failed to load folders');
      }
    }).fail(function (jqXHR, textStatus, errorThrown) {
      // Debug output
      console.error('AJAX request failed:', {
        status: jqXHR.status,
        statusText: jqXHR.statusText,
        responseJSON: jqXHR.responseJSON,
        responseText: jqXHR.responseText,
        textStatus: textStatus,
        errorThrown: errorThrown
      });

      // Log the full response text for debugging
      if (jqXHR.responseText) {
        console.log('Full response text:', jqXHR.responseText);
      }
      var errorMsg = window.aieData.i18n.requestFailed;

      // Check for WP_Error response
      if (jqXHR.responseJSON) {
        if (jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
          errorMsg = jqXHR.responseJSON.data.message;
        } else if (jqXHR.responseJSON.message) {
          errorMsg = jqXHR.responseJSON.message;
        }
      } else if (jqXHR.responseText) {
        // Try to parse HTML error
        var $html = jQuery('<div>').html(jqXHR.responseText);
        var title = $html.find('title').text();
        if (title) {
          errorMsg = 'Server Error: ' + title;
        } else {
          // Show first line of error
          var firstLine = jqXHR.responseText.split('\n')[0].substring(0, 100);
          if (firstLine) {
            errorMsg = 'Server Error: ' + firstLine;
          }
        }
      }
      if (errorThrown && errorMsg === window.aieData.i18n.requestFailed) {
        errorMsg = 'Request failed: ' + errorThrown;
      }

      // Add status code to message
      if (jqXHR.status && jqXHR.status !== 200) {
        errorMsg += ' (Status: ' + jqXHR.status + ')';
      }
      _this12.showBrowserError(errorMsg);
    }).always(function () {
      jQuery('#aie-folder-browser-loading').hide();
    });
  },
  /**
   * Show error in folder browser
   */
  showBrowserError: function showBrowserError(message) {
    jQuery('#aie-folder-browser-error-message').text(message);
    jQuery('#aie-folder-browser-error').slideDown();
  },
  /**
   * Display folders in browser
   */
  displayFolders: function displayFolders(folders, currentPath) {
    var _this13 = this;
    var $list = jQuery('#aie-folder-browser-list');
    var $currentPath = jQuery('#aie-current-path');
    var $upBtn = jQuery('#aie-folder-up-btn');
    $list.empty();

    // Store base uploads directory path once
    if (!$currentPath.data('base-dir')) {
      var basePath = $currentPath.text().trim();
      $currentPath.data('base-dir', basePath);
    }
    var baseDir = $currentPath.data('base-dir');

    // Update current path display
    if (currentPath) {
      $currentPath.text(baseDir + '/' + currentPath);
    } else {
      $currentPath.text(baseDir);
    }

    // Store current relative path for navigation
    $currentPath.data('relative-path', currentPath);

    // Show/hide up button
    if (currentPath) {
      if (!$upBtn.length) {
        var $upButton = jQuery("\n\t\t\t\t\t<button type=\"button\" id=\"aie-folder-up-btn\" class=\"button\" style=\"margin-bottom: 10px;\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-arrow-up-alt\"></span>\n\t\t\t\t\t\tGo Up\n\t\t\t\t\t</button>\n\t\t\t\t");
        $upButton.insertBefore($list);
      }
    } else {
      $upBtn.remove();
    }

    // Add "Use this folder" option
    var $rootOption = jQuery("\n\t\t\t<div class=\"aie-folder-item aie-folder-current\" data-path=\"".concat(this.escapeHtml(currentPath), "\">\n\t\t\t\t<span class=\"dashicons dashicons-location\"></span>\n\t\t\t\t<span class=\"aie-folder-name\">\n\t\t\t\t\t<strong>. (Use this folder)</strong>\n\t\t\t\t</span>\n\t\t\t</div>\n\t\t"));
    $list.append($rootOption);
    if (!folders || folders.length === 0) {
      jQuery('#aie-folder-browser-empty').show();
      return;
    }

    // Display folders
    folders.forEach(function (folder) {
      var $item = jQuery("\n\t\t\t\t<div class=\"aie-folder-item\" data-path=\"".concat(_this13.escapeHtml(folder.path), "\">\n\t\t\t\t\t<span class=\"dashicons dashicons-category\"></span>\n\t\t\t\t\t<span class=\"aie-folder-name\">").concat(_this13.escapeHtml(folder.name), "</span>\n\t\t\t\t\t<span class=\"dashicons dashicons-arrow-right-alt2\"></span>\n\t\t\t\t</div>\n\t\t\t"));
      $list.append($item);
    });
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (MediaSyncModule);

/***/ }),

/***/ "./src/js/modules/post-sync.js":
/*!*************************************!*\
  !*** ./src/js/modules/post-sync.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/**
 * Post Sync Module
 * 
 * Handles content synchronization from post list screens
 */

var PostSync = {
  /**
   * Initialize the module
   */
  init: function init() {
    console.log('AIE PostSync: Module initialized');
    this.bindEvents();
  },
  /**
   * Bind event handlers
   */
  bindEvents: function bindEvents() {
    var _this = this;
    var $ = jQuery;
    console.log('AIE PostSync: Binding events');

    // Open modal when sync button is clicked
    $(document).on('click', '#aie-sync-content-btn', function (e) {
      console.log('AIE PostSync: Sync button clicked in module', e);
      e.preventDefault();
      e.stopPropagation();
      _this.openSyncModal();
    });

    // Close modal
    $(document).on('click', '.aie-modal-close, .aie-modal', function (e) {
      if (e.target === e.currentTarget) {
        _this.closeSyncModal();
      }
    });

    // Enable/disable sync buttons based on site selection
    $(document).on('change', '#aie-sync-site-select', function () {
      _this.updateSyncButtons();
    });

    // Handle Push button
    $(document).on('click', '#aie-sync-push-btn', function () {
      _this.syncContent('push');
    });

    // Handle Pull button
    $(document).on('click', '#aie-sync-pull-btn', function () {
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
    console.log('AIE PostSync: Opening modal');
    var $ = jQuery;
    var selectedIds = this.getSelectedPostIds();
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
  closeSyncModal: function closeSyncModal() {
    jQuery('#aie-sync-modal').fadeOut(200);
  },
  /**
   * Get selected post IDs
   */
  getSelectedPostIds: function getSelectedPostIds() {
    var $ = jQuery;
    var ids = [];
    $('tbody .check-column input[type="checkbox"]:checked').each(function () {
      var id = $(this).val();
      if (id) {
        ids.push(id);
      }
    });
    return ids;
  },
  /**
   * Update sync button states
   */
  updateSyncButtons: function updateSyncButtons() {
    var $ = jQuery;
    var siteSelected = $('#aie-sync-site-select').val() !== '';
    $('#aie-sync-push-btn, #aie-sync-pull-btn').prop('disabled', !siteSelected);
  },
  /**
   * Sync content (push or pull)
   */
  syncContent: function syncContent(direction) {
    var _this2 = this;
    var $ = jQuery;
    var siteId = $('#aie-sync-site-select').val();
    var postIds = this.getSelectedPostIds();
    if (!siteId) {
      alert(window.aieData.i18n.selectSite);
      return;
    }
    if (postIds.length === 0) {
      alert(window.aieData.i18n.noPostsSelected);
      return;
    }

    // Confirm action
    var siteName = $('#aie-sync-site-select option:selected').text();
    var action = direction === 'push' ? 'push to' : 'pull from';
    var message = "Are you sure you want to ".concat(action, " ").concat(siteName, "?\n\nThis will affect ").concat(postIds.length, " post(s).");
    if (!confirm(message)) {
      return;
    }

    // Show progress
    $('#aie-sync-progress').show();
    $('#aie-sync-result').hide();
    $('.aie-progress-fill').css('width', '0%');
    $('.aie-progress-text').text("Starting ".concat(direction, "..."));

    // Disable buttons
    $('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', true);

    // Make AJAX request
    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action: "aie_content_sync_".concat(direction),
        nonce: aieContentSync.nonce,
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
    var $ = jQuery;
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
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (PostSync);

/***/ }),

/***/ "./src/js/modules/utils.js":
/*!*********************************!*\
  !*** ./src/js/modules/utils.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
function _createForOfIteratorHelper(o, allowArrayLike) { var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"]; if (!it) { if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") { if (it) o = it; var i = 0; var F = function F() {}; return { s: F, n: function n() { if (i >= o.length) return { done: true }; return { done: false, value: o[i++] }; }, e: function e(_e) { throw _e; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var normalCompletion = true, didErr = false, err; return { s: function s() { it = it.call(o); }, n: function n() { var step = it.next(); normalCompletion = step.done; return step; }, e: function e(_e2) { didErr = true; err = _e2; }, f: function f() { try { if (!normalCompletion && it["return"] != null) it["return"](); } finally { if (didErr) throw err; } } }; }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) { arr2[i] = arr[i]; } return arr2; }
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }
function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { _defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
/**
 * Utility Functions
 *
 * Common utilities used across the plugin
 */

var Utils = {
  /**
   * Make AJAX request
   *
   * @param {string} action AJAX action name
   * @param {Object} data Data to send
   * @param {string} method HTTP method (GET|POST)
   * @returns {Promise}
   */
  ajax: function ajax(action) {
    var data = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
    var method = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'POST';
    return new Promise(function (resolve, reject) {
      var _window$aieData, _window$aieData2;
      var ajaxData = _objectSpread({
        action: action,
        nonce: ((_window$aieData = window.aieData) === null || _window$aieData === void 0 ? void 0 : _window$aieData.nonce) || ''
      }, data);
      jQuery.ajax({
        url: ((_window$aieData2 = window.aieData) === null || _window$aieData2 === void 0 ? void 0 : _window$aieData2.ajaxUrl) || '/wp-admin/admin-ajax.php',
        type: method,
        data: ajaxData,
        dataType: 'json'
      }).done(function (response) {
        if (response.success) {
          resolve(response.data || response);
        } else {
          var _response$data;
          reject(((_response$data = response.data) === null || _response$data === void 0 ? void 0 : _response$data.message) || 'Request failed');
        }
      }).fail(function (jqXHR, textStatus, errorThrown) {
        reject("AJAX Error: ".concat(textStatus, " - ").concat(errorThrown));
      });
    });
  },
  /**
   * Format file size
   *
   * @param {number} bytes File size in bytes
   * @returns {string} Formatted size
   */
  formatFileSize: function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    var k = 1024;
    var sizes = ['Bytes', 'KB', 'MB', 'GB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
  },
  /**
   * Format duration
   *
   * @param {number} seconds Duration in seconds
   * @returns {string} Formatted duration
   */
  formatDuration: function formatDuration(seconds) {
    if (seconds < 60) {
      return Math.round(seconds) + 's';
    }
    var minutes = Math.floor(seconds / 60);
    var secs = Math.round(seconds % 60);
    if (minutes < 60) {
      return "".concat(minutes, "m ").concat(secs, "s");
    }
    var hours = Math.floor(minutes / 60);
    var mins = minutes % 60;
    return "".concat(hours, "h ").concat(mins, "m");
  },
  /**
   * Debounce function
   *
   * @param {Function} func Function to debounce
   * @param {number} wait Wait time in ms
   * @returns {Function}
   */
  debounce: function debounce(func) {
    var wait = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 300;
    var timeout;
    return function () {
      for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
        args[_key] = arguments[_key];
      }
      var context = this;
      clearTimeout(timeout);
      timeout = setTimeout(function () {
        return func.apply(context, args);
      }, wait);
    };
  },
  /**
   * Show notice message
   *
   * @param {string} message Message text
   * @param {string} type Notice type (success|error|warning|info)
   */
  showNotice: function showNotice(message) {
    var type = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'info';
    var noticeClass = "notice notice-".concat(type, " is-dismissible");
    var noticeHtml = "\n\t\t\t<div class=\"".concat(noticeClass, "\">\n\t\t\t\t<p>").concat(message, "</p>\n\t\t\t\t<button type=\"button\" class=\"notice-dismiss\">\n\t\t\t\t\t<span class=\"screen-reader-text\">Dismiss this notice.</span>\n\t\t\t\t</button>\n\t\t\t</div>\n\t\t");
    var $notice = jQuery(noticeHtml);
    jQuery('.wrap > h1').after($notice);

    // Auto dismiss after 5 seconds
    setTimeout(function () {
      $notice.fadeOut(function () {
        return $notice.remove();
      });
    }, 5000);

    // Manual dismiss
    $notice.on('click', '.notice-dismiss', function () {
      $notice.fadeOut(function () {
        return $notice.remove();
      });
    });
  },
  /**
   * Validate file
   *
   * @param {File} file File object
   * @param {Array} allowedTypes Allowed MIME types
   * @param {number} maxSize Max size in bytes
   * @returns {Object} Validation result
   */
  validateFile: function validateFile(file) {
    var allowedTypes = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : [];
    var maxSize = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 50 * 1024 * 1024;
    var errors = [];

    // Check file size
    if (file.size > maxSize) {
      errors.push("File size (".concat(this.formatFileSize(file.size), ") exceeds maximum allowed size (").concat(this.formatFileSize(maxSize), ")"));
    }

    // Check file type
    if (allowedTypes.length > 0) {
      var fileExt = file.name.split('.').pop().toLowerCase();
      var isAllowed = allowedTypes.some(function (type) {
        if (type.startsWith('.')) {
          return type.substring(1) === fileExt;
        }
        return file.type === type;
      });
      if (!isAllowed) {
        errors.push("File type .".concat(fileExt, " is not allowed. Allowed types: ").concat(allowedTypes.join(', ')));
      }
    }
    return {
      valid: errors.length === 0,
      errors: errors
    };
  },
  /**
   * Parse CSV string to array
   *
   * @param {string} csv CSV string
   * @param {string} delimiter Delimiter character
   * @returns {Array} Parsed data
   */
  parseCSV: function parseCSV(csv) {
    var delimiter = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : ',';
    var lines = csv.split('\n');
    var result = [];
    var _iterator = _createForOfIteratorHelper(lines),
      _step;
    try {
      for (_iterator.s(); !(_step = _iterator.n()).done;) {
        var line = _step.value;
        if (line.trim() === '') continue;
        var row = [];
        var current = '';
        var inQuotes = false;
        for (var i = 0; i < line.length; i++) {
          var _char = line[i];
          if (_char === '"') {
            inQuotes = !inQuotes;
          } else if (_char === delimiter && !inQuotes) {
            row.push(current.trim());
            current = '';
          } else {
            current += _char;
          }
        }
        row.push(current.trim());
        result.push(row);
      }
    } catch (err) {
      _iterator.e(err);
    } finally {
      _iterator.f();
    }
    return result;
  },
  /**
   * Escape HTML
   *
   * @param {string} html HTML string
   * @returns {string} Escaped HTML
   */
  escapeHtml: function escapeHtml(html) {
    var div = document.createElement('div');
    div.textContent = html;
    return div.innerHTML;
  },
  /**
   * Get URL parameter
   *
   * @param {string} name Parameter name
   * @returns {string|null} Parameter value
   */
  getUrlParameter: function getUrlParameter(name) {
    var urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
  },
  /**
   * Download file from URL
   *
   * @param {string} url File URL
   * @param {string} filename Filename for download
   */
  downloadFile: function downloadFile(url, filename) {
    var link = document.createElement('a');
    link.href = url;
    link.download = filename || 'export.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  },
  /**
   * Generate UUID
   *
   * @returns {string} UUID
   */
  generateUUID: function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0;
      var v = c === 'x' ? r : r & 0x3 | 0x8;
      return v.toString(16);
    });
  },
  /**
   * Create progress bar element
   *
   * @returns {jQuery} Progress bar element
   */
  createProgressBar: function createProgressBar() {
    return jQuery("\n\t\t\t<div class=\"aie-progress-container\">\n\t\t\t\t<div class=\"aie-progress-bar\">\n\t\t\t\t\t<div class=\"aie-progress-bar-fill\" style=\"width: 0%;\"></div>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-progress-stats\">\n\t\t\t\t\t<div class=\"aie-progress-percentage\">0%</div>\n\t\t\t\t\t<div class=\"aie-progress-details\">\n\t\t\t\t\t\t<span class=\"aie-processed\">0</span> / <span class=\"aie-total\">0</span> items\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t");
  },
  /**
   * Update progress bar
   *
   * @param {jQuery} $container Progress container
   * @param {Object} data Progress data
   */
  updateProgressBar: function updateProgressBar($container, data) {
    var percentage = data.percentage || 0;
    var processed = data.processed || 0;
    var total = data.total || 0;
    $container.find('.aie-progress-bar-fill').css('width', percentage + '%');
    $container.find('.aie-progress-percentage').text(Math.round(percentage) + '%');
    $container.find('.aie-processed').text(processed);
    $container.find('.aie-total').text(total);

    // Update estimates if available
    if (data.estimates) {
      if (data.estimates.elapsed_formatted) {
        $container.find('.aie-elapsed-time').text(data.estimates.elapsed_formatted);
      }
      if (data.estimates.remaining_formatted) {
        $container.find('.aie-remaining-time').text(data.estimates.remaining_formatted);
      }
      if (data.estimates.items_per_second) {
        $container.find('.aie-items-per-second').text(data.estimates.items_per_second.toFixed(1) + ' items/s');
      }
    }
  },
  /**
   * Handle errors
   *
   * @param {Error|string} error Error object or message
   * @param {string} context Error context
   */
  handleError: function handleError(error) {
    var context = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '';
    console.error("AIE Error".concat(context ? ' (' + context + ')' : '', ":"), error);
    var message = error.message || error.toString();
    this.showNotice(message, 'error');
  },
  /**
   * Format bytes (alias for formatFileSize)
   *
   * @param {number} bytes File size in bytes
   * @returns {string} Formatted size
   */
  formatBytes: function formatBytes(bytes) {
    return this.formatFileSize(bytes);
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Utils);

/***/ }),

/***/ "./src/js/utils/notifications.js":
/*!***************************************!*\
  !*** ./src/js/utils/notifications.js ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   clearModalErrors: () => (/* binding */ clearModalErrors),
/* harmony export */   confirmDialog: () => (/* binding */ confirmDialog),
/* harmony export */   showError: () => (/* binding */ showError),
/* harmony export */   showModalError: () => (/* binding */ showModalError),
/* harmony export */   showNotice: () => (/* binding */ showNotice)
/* harmony export */ });
/**
 * Notification utilities
 */

/**
 * Show success notice
 */
function showNotice(message) {
  // Use WordPress admin notice
  var notice = document.createElement('div');
  notice.className = 'notice notice-success is-dismissible';
  notice.innerHTML = "<p>".concat(escapeHtml(message), "</p>");
  var container = document.querySelector('.wrap') || document.body;
  container.insertBefore(notice, container.firstChild);

  // Auto dismiss after 5 seconds
  setTimeout(function () {
    notice.remove();
  }, 5000);

  // Make it dismissible
  var dismissButton = document.createElement('button');
  dismissButton.type = 'button';
  dismissButton.className = 'notice-dismiss';
  dismissButton.innerHTML = '<span class="screen-reader-text">Dismiss this notice.</span>';
  dismissButton.addEventListener('click', function () {
    notice.remove();
  });
  notice.appendChild(dismissButton);
}

/**
 * Show error message
 */
function showError(message) {
  var notice = document.createElement('div');
  notice.className = 'notice notice-error is-dismissible';
  notice.innerHTML = "<p>".concat(escapeHtml(message), "</p>");
  var container = document.querySelector('.wrap') || document.body;
  container.insertBefore(notice, container.firstChild);

  // Auto dismiss after 10 seconds
  setTimeout(function () {
    notice.remove();
  }, 10000);

  // Make it dismissible
  var dismissButton = document.createElement('button');
  dismissButton.type = 'button';
  dismissButton.className = 'notice-dismiss';
  dismissButton.innerHTML = '<span class="screen-reader-text">Dismiss this notice.</span>';
  dismissButton.addEventListener('click', function () {
    notice.remove();
  });
  notice.appendChild(dismissButton);
}

/**
 * Show error message inside a modal
 */
function showModalError(message) {
  var modalElement = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
  // If no modal specified, try to find the currently visible modal
  if (!modalElement) {
    modalElement = document.querySelector('.aie-modal[style*="display: flex"]') || document.querySelector('.aie-modal[style*="display:flex"]') || document.querySelector('.aie-modal');
  }
  if (!modalElement) {
    // Fallback to regular error if no modal found
    showError(message);
    return;
  }

  // Remove any existing error notices in the modal
  var existingErrors = modalElement.querySelectorAll('.aie-modal-error');
  existingErrors.forEach(function (el) {
    return el.remove();
  });

  // Create error notice
  var notice = document.createElement('div');
  notice.className = 'notice notice-error is-dismissible aie-modal-error';
  notice.innerHTML = "<p>".concat(escapeHtml(message), "</p>");

  // Find modal content area
  var modalContent = modalElement.querySelector('.aie-modal-content') || modalElement.querySelector('.aie-modal-body') || modalElement;

  // Insert at the top of modal content
  if (modalContent.firstChild) {
    modalContent.insertBefore(notice, modalContent.firstChild);
  } else {
    modalContent.appendChild(notice);
  }

  // Auto dismiss after 10 seconds
  setTimeout(function () {
    notice.remove();
  }, 10000);

  // Make it dismissible
  var dismissButton = document.createElement('button');
  dismissButton.type = 'button';
  dismissButton.className = 'notice-dismiss';
  dismissButton.innerHTML = '<span class="screen-reader-text">Dismiss this notice.</span>';
  dismissButton.addEventListener('click', function () {
    notice.remove();
  });
  notice.appendChild(dismissButton);

  // Scroll to top of modal to show error
  modalContent.scrollTop = 0;
}

/**
 * Clear modal errors
 */
function clearModalErrors() {
  var modalElement = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : null;
  // If no modal specified, try to find the currently visible modal or clear all
  if (!modalElement) {
    var existingErrors = document.querySelectorAll('.aie-modal-error');
    existingErrors.forEach(function (el) {
      return el.remove();
    });
  } else {
    var _existingErrors = modalElement.querySelectorAll('.aie-modal-error');
    _existingErrors.forEach(function (el) {
      return el.remove();
    });
  }
}

/**
 * Show confirmation dialog
 */
function confirmDialog(message) {
  return new Promise(function (resolve) {
    // Use native confirm for simplicity
    var result = confirm(message);
    resolve(result);
  });
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
  var div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

/***/ }),

/***/ "./src/scss/app.scss":
/*!***************************!*\
  !*** ./src/scss/app.scss ***!
  \***************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./node_modules/regenerator-runtime/runtime.js":
/*!*****************************************************!*\
  !*** ./node_modules/regenerator-runtime/runtime.js ***!
  \*****************************************************/
/***/ ((module) => {

/**
 * Copyright (c) 2014-present, Facebook, Inc.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

var runtime = (function (exports) {
  "use strict";

  var Op = Object.prototype;
  var hasOwn = Op.hasOwnProperty;
  var undefined; // More compressible than void 0.
  var $Symbol = typeof Symbol === "function" ? Symbol : {};
  var iteratorSymbol = $Symbol.iterator || "@@iterator";
  var asyncIteratorSymbol = $Symbol.asyncIterator || "@@asyncIterator";
  var toStringTagSymbol = $Symbol.toStringTag || "@@toStringTag";

  function define(obj, key, value) {
    Object.defineProperty(obj, key, {
      value: value,
      enumerable: true,
      configurable: true,
      writable: true
    });
    return obj[key];
  }
  try {
    // IE 8 has a broken Object.defineProperty that only works on DOM objects.
    define({}, "");
  } catch (err) {
    define = function(obj, key, value) {
      return obj[key] = value;
    };
  }

  function wrap(innerFn, outerFn, self, tryLocsList) {
    // If outerFn provided and outerFn.prototype is a Generator, then outerFn.prototype instanceof Generator.
    var protoGenerator = outerFn && outerFn.prototype instanceof Generator ? outerFn : Generator;
    var generator = Object.create(protoGenerator.prototype);
    var context = new Context(tryLocsList || []);

    // The ._invoke method unifies the implementations of the .next,
    // .throw, and .return methods.
    generator._invoke = makeInvokeMethod(innerFn, self, context);

    return generator;
  }
  exports.wrap = wrap;

  // Try/catch helper to minimize deoptimizations. Returns a completion
  // record like context.tryEntries[i].completion. This interface could
  // have been (and was previously) designed to take a closure to be
  // invoked without arguments, but in all the cases we care about we
  // already have an existing method we want to call, so there's no need
  // to create a new function object. We can even get away with assuming
  // the method takes exactly one argument, since that happens to be true
  // in every case, so we don't have to touch the arguments object. The
  // only additional allocation required is the completion record, which
  // has a stable shape and so hopefully should be cheap to allocate.
  function tryCatch(fn, obj, arg) {
    try {
      return { type: "normal", arg: fn.call(obj, arg) };
    } catch (err) {
      return { type: "throw", arg: err };
    }
  }

  var GenStateSuspendedStart = "suspendedStart";
  var GenStateSuspendedYield = "suspendedYield";
  var GenStateExecuting = "executing";
  var GenStateCompleted = "completed";

  // Returning this object from the innerFn has the same effect as
  // breaking out of the dispatch switch statement.
  var ContinueSentinel = {};

  // Dummy constructor functions that we use as the .constructor and
  // .constructor.prototype properties for functions that return Generator
  // objects. For full spec compliance, you may wish to configure your
  // minifier not to mangle the names of these two functions.
  function Generator() {}
  function GeneratorFunction() {}
  function GeneratorFunctionPrototype() {}

  // This is a polyfill for %IteratorPrototype% for environments that
  // don't natively support it.
  var IteratorPrototype = {};
  define(IteratorPrototype, iteratorSymbol, function () {
    return this;
  });

  var getProto = Object.getPrototypeOf;
  var NativeIteratorPrototype = getProto && getProto(getProto(values([])));
  if (NativeIteratorPrototype &&
      NativeIteratorPrototype !== Op &&
      hasOwn.call(NativeIteratorPrototype, iteratorSymbol)) {
    // This environment has a native %IteratorPrototype%; use it instead
    // of the polyfill.
    IteratorPrototype = NativeIteratorPrototype;
  }

  var Gp = GeneratorFunctionPrototype.prototype =
    Generator.prototype = Object.create(IteratorPrototype);
  GeneratorFunction.prototype = GeneratorFunctionPrototype;
  define(Gp, "constructor", GeneratorFunctionPrototype);
  define(GeneratorFunctionPrototype, "constructor", GeneratorFunction);
  GeneratorFunction.displayName = define(
    GeneratorFunctionPrototype,
    toStringTagSymbol,
    "GeneratorFunction"
  );

  // Helper for defining the .next, .throw, and .return methods of the
  // Iterator interface in terms of a single ._invoke method.
  function defineIteratorMethods(prototype) {
    ["next", "throw", "return"].forEach(function(method) {
      define(prototype, method, function(arg) {
        return this._invoke(method, arg);
      });
    });
  }

  exports.isGeneratorFunction = function(genFun) {
    var ctor = typeof genFun === "function" && genFun.constructor;
    return ctor
      ? ctor === GeneratorFunction ||
        // For the native GeneratorFunction constructor, the best we can
        // do is to check its .name property.
        (ctor.displayName || ctor.name) === "GeneratorFunction"
      : false;
  };

  exports.mark = function(genFun) {
    if (Object.setPrototypeOf) {
      Object.setPrototypeOf(genFun, GeneratorFunctionPrototype);
    } else {
      genFun.__proto__ = GeneratorFunctionPrototype;
      define(genFun, toStringTagSymbol, "GeneratorFunction");
    }
    genFun.prototype = Object.create(Gp);
    return genFun;
  };

  // Within the body of any async function, `await x` is transformed to
  // `yield regeneratorRuntime.awrap(x)`, so that the runtime can test
  // `hasOwn.call(value, "__await")` to determine if the yielded value is
  // meant to be awaited.
  exports.awrap = function(arg) {
    return { __await: arg };
  };

  function AsyncIterator(generator, PromiseImpl) {
    function invoke(method, arg, resolve, reject) {
      var record = tryCatch(generator[method], generator, arg);
      if (record.type === "throw") {
        reject(record.arg);
      } else {
        var result = record.arg;
        var value = result.value;
        if (value &&
            typeof value === "object" &&
            hasOwn.call(value, "__await")) {
          return PromiseImpl.resolve(value.__await).then(function(value) {
            invoke("next", value, resolve, reject);
          }, function(err) {
            invoke("throw", err, resolve, reject);
          });
        }

        return PromiseImpl.resolve(value).then(function(unwrapped) {
          // When a yielded Promise is resolved, its final value becomes
          // the .value of the Promise<{value,done}> result for the
          // current iteration.
          result.value = unwrapped;
          resolve(result);
        }, function(error) {
          // If a rejected Promise was yielded, throw the rejection back
          // into the async generator function so it can be handled there.
          return invoke("throw", error, resolve, reject);
        });
      }
    }

    var previousPromise;

    function enqueue(method, arg) {
      function callInvokeWithMethodAndArg() {
        return new PromiseImpl(function(resolve, reject) {
          invoke(method, arg, resolve, reject);
        });
      }

      return previousPromise =
        // If enqueue has been called before, then we want to wait until
        // all previous Promises have been resolved before calling invoke,
        // so that results are always delivered in the correct order. If
        // enqueue has not been called before, then it is important to
        // call invoke immediately, without waiting on a callback to fire,
        // so that the async generator function has the opportunity to do
        // any necessary setup in a predictable way. This predictability
        // is why the Promise constructor synchronously invokes its
        // executor callback, and why async functions synchronously
        // execute code before the first await. Since we implement simple
        // async functions in terms of async generators, it is especially
        // important to get this right, even though it requires care.
        previousPromise ? previousPromise.then(
          callInvokeWithMethodAndArg,
          // Avoid propagating failures to Promises returned by later
          // invocations of the iterator.
          callInvokeWithMethodAndArg
        ) : callInvokeWithMethodAndArg();
    }

    // Define the unified helper method that is used to implement .next,
    // .throw, and .return (see defineIteratorMethods).
    this._invoke = enqueue;
  }

  defineIteratorMethods(AsyncIterator.prototype);
  define(AsyncIterator.prototype, asyncIteratorSymbol, function () {
    return this;
  });
  exports.AsyncIterator = AsyncIterator;

  // Note that simple async functions are implemented on top of
  // AsyncIterator objects; they just return a Promise for the value of
  // the final result produced by the iterator.
  exports.async = function(innerFn, outerFn, self, tryLocsList, PromiseImpl) {
    if (PromiseImpl === void 0) PromiseImpl = Promise;

    var iter = new AsyncIterator(
      wrap(innerFn, outerFn, self, tryLocsList),
      PromiseImpl
    );

    return exports.isGeneratorFunction(outerFn)
      ? iter // If outerFn is a generator, return the full iterator.
      : iter.next().then(function(result) {
          return result.done ? result.value : iter.next();
        });
  };

  function makeInvokeMethod(innerFn, self, context) {
    var state = GenStateSuspendedStart;

    return function invoke(method, arg) {
      if (state === GenStateExecuting) {
        throw new Error("Generator is already running");
      }

      if (state === GenStateCompleted) {
        if (method === "throw") {
          throw arg;
        }

        // Be forgiving, per 25.3.3.3.3 of the spec:
        // https://people.mozilla.org/~jorendorff/es6-draft.html#sec-generatorresume
        return doneResult();
      }

      context.method = method;
      context.arg = arg;

      while (true) {
        var delegate = context.delegate;
        if (delegate) {
          var delegateResult = maybeInvokeDelegate(delegate, context);
          if (delegateResult) {
            if (delegateResult === ContinueSentinel) continue;
            return delegateResult;
          }
        }

        if (context.method === "next") {
          // Setting context._sent for legacy support of Babel's
          // function.sent implementation.
          context.sent = context._sent = context.arg;

        } else if (context.method === "throw") {
          if (state === GenStateSuspendedStart) {
            state = GenStateCompleted;
            throw context.arg;
          }

          context.dispatchException(context.arg);

        } else if (context.method === "return") {
          context.abrupt("return", context.arg);
        }

        state = GenStateExecuting;

        var record = tryCatch(innerFn, self, context);
        if (record.type === "normal") {
          // If an exception is thrown from innerFn, we leave state ===
          // GenStateExecuting and loop back for another invocation.
          state = context.done
            ? GenStateCompleted
            : GenStateSuspendedYield;

          if (record.arg === ContinueSentinel) {
            continue;
          }

          return {
            value: record.arg,
            done: context.done
          };

        } else if (record.type === "throw") {
          state = GenStateCompleted;
          // Dispatch the exception by looping back around to the
          // context.dispatchException(context.arg) call above.
          context.method = "throw";
          context.arg = record.arg;
        }
      }
    };
  }

  // Call delegate.iterator[context.method](context.arg) and handle the
  // result, either by returning a { value, done } result from the
  // delegate iterator, or by modifying context.method and context.arg,
  // setting context.delegate to null, and returning the ContinueSentinel.
  function maybeInvokeDelegate(delegate, context) {
    var method = delegate.iterator[context.method];
    if (method === undefined) {
      // A .throw or .return when the delegate iterator has no .throw
      // method always terminates the yield* loop.
      context.delegate = null;

      if (context.method === "throw") {
        // Note: ["return"] must be used for ES3 parsing compatibility.
        if (delegate.iterator["return"]) {
          // If the delegate iterator has a return method, give it a
          // chance to clean up.
          context.method = "return";
          context.arg = undefined;
          maybeInvokeDelegate(delegate, context);

          if (context.method === "throw") {
            // If maybeInvokeDelegate(context) changed context.method from
            // "return" to "throw", let that override the TypeError below.
            return ContinueSentinel;
          }
        }

        context.method = "throw";
        context.arg = new TypeError(
          "The iterator does not provide a 'throw' method");
      }

      return ContinueSentinel;
    }

    var record = tryCatch(method, delegate.iterator, context.arg);

    if (record.type === "throw") {
      context.method = "throw";
      context.arg = record.arg;
      context.delegate = null;
      return ContinueSentinel;
    }

    var info = record.arg;

    if (! info) {
      context.method = "throw";
      context.arg = new TypeError("iterator result is not an object");
      context.delegate = null;
      return ContinueSentinel;
    }

    if (info.done) {
      // Assign the result of the finished delegate to the temporary
      // variable specified by delegate.resultName (see delegateYield).
      context[delegate.resultName] = info.value;

      // Resume execution at the desired location (see delegateYield).
      context.next = delegate.nextLoc;

      // If context.method was "throw" but the delegate handled the
      // exception, let the outer generator proceed normally. If
      // context.method was "next", forget context.arg since it has been
      // "consumed" by the delegate iterator. If context.method was
      // "return", allow the original .return call to continue in the
      // outer generator.
      if (context.method !== "return") {
        context.method = "next";
        context.arg = undefined;
      }

    } else {
      // Re-yield the result returned by the delegate method.
      return info;
    }

    // The delegate iterator is finished, so forget it and continue with
    // the outer generator.
    context.delegate = null;
    return ContinueSentinel;
  }

  // Define Generator.prototype.{next,throw,return} in terms of the
  // unified ._invoke helper method.
  defineIteratorMethods(Gp);

  define(Gp, toStringTagSymbol, "Generator");

  // A Generator should always return itself as the iterator object when the
  // @@iterator function is called on it. Some browsers' implementations of the
  // iterator prototype chain incorrectly implement this, causing the Generator
  // object to not be returned from this call. This ensures that doesn't happen.
  // See https://github.com/facebook/regenerator/issues/274 for more details.
  define(Gp, iteratorSymbol, function() {
    return this;
  });

  define(Gp, "toString", function() {
    return "[object Generator]";
  });

  function pushTryEntry(locs) {
    var entry = { tryLoc: locs[0] };

    if (1 in locs) {
      entry.catchLoc = locs[1];
    }

    if (2 in locs) {
      entry.finallyLoc = locs[2];
      entry.afterLoc = locs[3];
    }

    this.tryEntries.push(entry);
  }

  function resetTryEntry(entry) {
    var record = entry.completion || {};
    record.type = "normal";
    delete record.arg;
    entry.completion = record;
  }

  function Context(tryLocsList) {
    // The root entry object (effectively a try statement without a catch
    // or a finally block) gives us a place to store values thrown from
    // locations where there is no enclosing try statement.
    this.tryEntries = [{ tryLoc: "root" }];
    tryLocsList.forEach(pushTryEntry, this);
    this.reset(true);
  }

  exports.keys = function(object) {
    var keys = [];
    for (var key in object) {
      keys.push(key);
    }
    keys.reverse();

    // Rather than returning an object with a next method, we keep
    // things simple and return the next function itself.
    return function next() {
      while (keys.length) {
        var key = keys.pop();
        if (key in object) {
          next.value = key;
          next.done = false;
          return next;
        }
      }

      // To avoid creating an additional object, we just hang the .value
      // and .done properties off the next function object itself. This
      // also ensures that the minifier will not anonymize the function.
      next.done = true;
      return next;
    };
  };

  function values(iterable) {
    if (iterable) {
      var iteratorMethod = iterable[iteratorSymbol];
      if (iteratorMethod) {
        return iteratorMethod.call(iterable);
      }

      if (typeof iterable.next === "function") {
        return iterable;
      }

      if (!isNaN(iterable.length)) {
        var i = -1, next = function next() {
          while (++i < iterable.length) {
            if (hasOwn.call(iterable, i)) {
              next.value = iterable[i];
              next.done = false;
              return next;
            }
          }

          next.value = undefined;
          next.done = true;

          return next;
        };

        return next.next = next;
      }
    }

    // Return an iterator with no values.
    return { next: doneResult };
  }
  exports.values = values;

  function doneResult() {
    return { value: undefined, done: true };
  }

  Context.prototype = {
    constructor: Context,

    reset: function(skipTempReset) {
      this.prev = 0;
      this.next = 0;
      // Resetting context._sent for legacy support of Babel's
      // function.sent implementation.
      this.sent = this._sent = undefined;
      this.done = false;
      this.delegate = null;

      this.method = "next";
      this.arg = undefined;

      this.tryEntries.forEach(resetTryEntry);

      if (!skipTempReset) {
        for (var name in this) {
          // Not sure about the optimal order of these conditions:
          if (name.charAt(0) === "t" &&
              hasOwn.call(this, name) &&
              !isNaN(+name.slice(1))) {
            this[name] = undefined;
          }
        }
      }
    },

    stop: function() {
      this.done = true;

      var rootEntry = this.tryEntries[0];
      var rootRecord = rootEntry.completion;
      if (rootRecord.type === "throw") {
        throw rootRecord.arg;
      }

      return this.rval;
    },

    dispatchException: function(exception) {
      if (this.done) {
        throw exception;
      }

      var context = this;
      function handle(loc, caught) {
        record.type = "throw";
        record.arg = exception;
        context.next = loc;

        if (caught) {
          // If the dispatched exception was caught by a catch block,
          // then let that catch block handle the exception normally.
          context.method = "next";
          context.arg = undefined;
        }

        return !! caught;
      }

      for (var i = this.tryEntries.length - 1; i >= 0; --i) {
        var entry = this.tryEntries[i];
        var record = entry.completion;

        if (entry.tryLoc === "root") {
          // Exception thrown outside of any try block that could handle
          // it, so set the completion value of the entire function to
          // throw the exception.
          return handle("end");
        }

        if (entry.tryLoc <= this.prev) {
          var hasCatch = hasOwn.call(entry, "catchLoc");
          var hasFinally = hasOwn.call(entry, "finallyLoc");

          if (hasCatch && hasFinally) {
            if (this.prev < entry.catchLoc) {
              return handle(entry.catchLoc, true);
            } else if (this.prev < entry.finallyLoc) {
              return handle(entry.finallyLoc);
            }

          } else if (hasCatch) {
            if (this.prev < entry.catchLoc) {
              return handle(entry.catchLoc, true);
            }

          } else if (hasFinally) {
            if (this.prev < entry.finallyLoc) {
              return handle(entry.finallyLoc);
            }

          } else {
            throw new Error("try statement without catch or finally");
          }
        }
      }
    },

    abrupt: function(type, arg) {
      for (var i = this.tryEntries.length - 1; i >= 0; --i) {
        var entry = this.tryEntries[i];
        if (entry.tryLoc <= this.prev &&
            hasOwn.call(entry, "finallyLoc") &&
            this.prev < entry.finallyLoc) {
          var finallyEntry = entry;
          break;
        }
      }

      if (finallyEntry &&
          (type === "break" ||
           type === "continue") &&
          finallyEntry.tryLoc <= arg &&
          arg <= finallyEntry.finallyLoc) {
        // Ignore the finally entry if control is not jumping to a
        // location outside the try/catch block.
        finallyEntry = null;
      }

      var record = finallyEntry ? finallyEntry.completion : {};
      record.type = type;
      record.arg = arg;

      if (finallyEntry) {
        this.method = "next";
        this.next = finallyEntry.finallyLoc;
        return ContinueSentinel;
      }

      return this.complete(record);
    },

    complete: function(record, afterLoc) {
      if (record.type === "throw") {
        throw record.arg;
      }

      if (record.type === "break" ||
          record.type === "continue") {
        this.next = record.arg;
      } else if (record.type === "return") {
        this.rval = this.arg = record.arg;
        this.method = "return";
        this.next = "end";
      } else if (record.type === "normal" && afterLoc) {
        this.next = afterLoc;
      }

      return ContinueSentinel;
    },

    finish: function(finallyLoc) {
      for (var i = this.tryEntries.length - 1; i >= 0; --i) {
        var entry = this.tryEntries[i];
        if (entry.finallyLoc === finallyLoc) {
          this.complete(entry.completion, entry.afterLoc);
          resetTryEntry(entry);
          return ContinueSentinel;
        }
      }
    },

    "catch": function(tryLoc) {
      for (var i = this.tryEntries.length - 1; i >= 0; --i) {
        var entry = this.tryEntries[i];
        if (entry.tryLoc === tryLoc) {
          var record = entry.completion;
          if (record.type === "throw") {
            var thrown = record.arg;
            resetTryEntry(entry);
          }
          return thrown;
        }
      }

      // The context.catch method must only be called with a location
      // argument that corresponds to a known catch block.
      throw new Error("illegal catch attempt");
    },

    delegateYield: function(iterable, resultName, nextLoc) {
      this.delegate = {
        iterator: values(iterable),
        resultName: resultName,
        nextLoc: nextLoc
      };

      if (this.method === "next") {
        // Deliberately forget the last sent value so that we don't
        // accidentally pass it on to the delegate.
        this.arg = undefined;
      }

      return ContinueSentinel;
    }
  };

  // Regardless of whether this script is executing as a CommonJS module
  // or not, return the runtime object so that we can declare the variable
  // regeneratorRuntime in the outer scope, which allows this module to be
  // injected easily by `bin/regenerator --include-runtime script.js`.
  return exports;

}(
  // If this script is executing as a CommonJS module, use module.exports
  // as the regeneratorRuntime namespace. Otherwise create a new empty
  // object. Either way, the resulting object will be used to initialize
  // the regeneratorRuntime variable at the top of this file.
   true ? module.exports : 0
));

try {
  regeneratorRuntime = runtime;
} catch (accidentalStrictMode) {
  // This module should not be running in strict mode, so the above
  // assignment should always work unless something is misconfigured. Just
  // in case runtime.js accidentally runs in strict mode, in modern engines
  // we can explicitly access globalThis. In older engines we can escape
  // strict mode using a global Function call. This could conceivably fail
  // if a Content Security Policy forbids using Function, but in that case
  // the proper solution is to fix the accidental strict mode problem. If
  // you've misconfigured your bundler to force strict mode and applied a
  // CSP to forbid Function, and you're not willing to fix either of those
  // problems, please detail your unique predicament in a GitHub issue.
  if (typeof globalThis === "object") {
    globalThis.regeneratorRuntime = runtime;
  } else {
    Function("r", "regeneratorRuntime = r")(runtime);
  }
}


/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"/js/app": 0,
/******/ 			"css/app": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = self["webpackChunkboilerplate"] = self["webpackChunkboilerplate"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	__webpack_require__.O(undefined, ["css/app"], () => (__webpack_require__("./src/js/app.js")))
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["css/app"], () => (__webpack_require__("./src/scss/app.scss")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=app.js.map