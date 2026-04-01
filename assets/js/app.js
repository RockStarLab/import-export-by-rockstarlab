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
/* harmony import */ var _modules_ai_url_importer__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./modules/ai-url-importer */ "./src/js/modules/ai-url-importer.js");










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
  _modules_post_sync__WEBPACK_IMPORTED_MODULE_7__["default"].init();
  window.aiePostSyncModule = _modules_post_sync__WEBPACK_IMPORTED_MODULE_7__["default"];

  // Initialize AI URL Importer module
  _modules_ai_url_importer__WEBPACK_IMPORTED_MODULE_8__["default"].init();
});

/***/ }),

/***/ "./src/js/modules/BackupWarningModal.js":
/*!**********************************************!*\
  !*** ./src/js/modules/BackupWarningModal.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/**
 * Backup Warning Modal
 *
 * Shows a warning modal before import to ensure users have created a backup
 */

var BackupWarningModal = {
  /**
   * Show backup warning modal
   *
   * @param {Function} onConfirm Callback when user confirms
   * @param {Function} onCancel  Callback when user cancels
   */
  show: function show(onConfirm, onCancel) {
    var _this = this;
    // Check if user has disabled the warning
    if (this.isWarningDisabled()) {
      if (typeof onConfirm === 'function') {
        onConfirm();
      }
      return;
    }

    // Create modal HTML
    var modalHtml = this.getModalHtml();

    // Add to DOM
    jQuery('body').append(modalHtml);

    // Get modal elements
    var $overlay = jQuery('.aie-backup-warning-overlay');
    var $modal = jQuery('.aie-backup-warning-modal');
    var $confirmBtn = $modal.find('.aie-backup-confirm');
    var $cancelBtn = $modal.find('.aie-backup-cancel');
    var $backupCheckbox = $modal.find('#aie-backup-created');
    var $dontShowCheckbox = $modal.find('#aie-backup-dont-show');

    // Initially disable confirm button
    $confirmBtn.prop('disabled', true);

    // Enable confirm button only when backup checkbox is checked
    $backupCheckbox.on('change', function () {
      $confirmBtn.prop('disabled', !jQuery(this).is(':checked'));
    });

    // Handle confirm
    $confirmBtn.on('click', function () {
      // Save "don't show again" preference
      if ($dontShowCheckbox.is(':checked')) {
        _this.disableWarning();
      }

      // Close modal
      _this.close();

      // Call confirm callback
      if (typeof onConfirm === 'function') {
        onConfirm();
      }
    });

    // Handle cancel
    $cancelBtn.on('click', function () {
      _this.close();
      if (typeof onCancel === 'function') {
        onCancel();
      }
    });

    // Handle overlay click (close)
    $overlay.on('click', function (e) {
      if (e.target === $overlay[0]) {
        _this.close();
        if (typeof onCancel === 'function') {
          onCancel();
        }
      }
    });

    // Handle ESC key
    jQuery(document).on('keydown.aie-backup-modal', function (e) {
      if (e.key === 'Escape') {
        _this.close();
        if (typeof onCancel === 'function') {
          onCancel();
        }
      }
    });

    // Prevent body scroll
    jQuery('body').css('overflow', 'hidden');
  },
  /**
   * Close modal
   */
  close: function close() {
    jQuery('.aie-backup-warning-overlay').fadeOut(200, function () {
      jQuery(this).remove();
    });
    jQuery('body').css('overflow', '');
    jQuery(document).off('keydown.aie-backup-modal');
  },
  /**
   * Check if warning is disabled
   *
   * @return {boolean}
   */
  isWarningDisabled: function isWarningDisabled() {
    return localStorage.getItem('aie_backup_warning_disabled') === 'true';
  },
  /**
   * Disable warning (don't show again)
   */
  disableWarning: function disableWarning() {
    localStorage.setItem('aie_backup_warning_disabled', 'true');
  },
  /**
   * Enable warning (reset)
   */
  enableWarning: function enableWarning() {
    localStorage.removeItem('aie_backup_warning_disabled');
  },
  /**
   * Get modal HTML
   *
   * @return {string}
   */
  getModalHtml: function getModalHtml() {
    var i18n = typeof aieData !== 'undefined' && aieData.i18n ? aieData.i18n : {};
    return "\n\t\t\t<div class=\"aie-backup-warning-overlay\">\n\t\t\t\t<div class=\"aie-backup-warning-modal\">\n\t\t\t\t\t<div class=\"aie-backup-warning-header\">\n\t\t\t\t\t\t<div class=\"aie-warning-icon\">\u26A0\uFE0F</div>\n\t\t\t\t\t\t<h2>".concat(i18n.backupWarningTitle || 'Important: Create a Backup!', "</h2>\n\t\t\t\t\t\t<p>").concat(i18n.backupWarningSubtitle || 'This action can modify or delete existing data', "</p>\n\t\t\t\t\t</div>\n\n\t\t\t\t\t<div class=\"aie-backup-warning-body\">\n\t\t\t\t\t\t<div class=\"aie-warning-message\">\n\t\t\t\t\t\t\t<p><strong>").concat(i18n.backupWarningRisks || 'Action may lead to:', "</strong></p>\n\t\t\t\t\t\t\t<p>\n\t\t\t\t\t\t\t\t\u2022 ").concat(i18n.backupRisk1 || 'Overwriting existing posts, pages, and records', "<br>\n\t\t\t\t\t\t\t\t\u2022 ").concat(i18n.backupRisk2 || 'Modifying metadata and taxonomies', "<br>\n\t\t\t\t\t\t\t\t\u2022 ").concat(i18n.backupRisk3 || 'Data loss due to incorrect field mapping', "<br>\n\t\t\t\t\t\t\t\t\u2022 ").concat(i18n.backupRisk4 || 'Conflicts with existing IDs', "\n\t\t\t\t\t\t\t</p>\n\t\t\t\t\t\t\t<p><strong>").concat(i18n.backupWarningImportant || 'Rollback may be impossible, especially for updated data!', "</strong></p>\n\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t<div class=\"aie-backup-recommendations\">\n\t\t\t\t\t\t\t<h3>").concat(i18n.backupRecommendations || 'Recommended backup methods:', "</h3>\n\t\t\t\t\t\t\t\n\t\t\t\t\t\t\t<div class=\"aie-backup-options\">\n\t\t\t\t\t\t\t\t<div class=\"aie-backup-option\">\n\t\t\t\t\t\t\t\t\t<h4>\n\t\t\t\t\t\t\t\t\t\t<span>UpdraftPlus</span>\n\t\t\t\t\t\t\t\t\t\t<span class=\"aie-badge aie-badge-free\">FREE</span>\n\t\t\t\t\t\t\t\t\t</h4>\n\t\t\t\t\t\t\t\t\t<p>").concat(i18n.backupUpdraftPlus || 'Popular backup plugin with cloud storage support', "</p>\n\t\t\t\t\t\t\t\t\t<a href=\"https://wordpress.org/plugins/updraftplus/\" target=\"_blank\" class=\"aie-backup-link\">\n\t\t\t\t\t\t\t\t\t\t").concat(i18n.viewPlugin || 'View plugin', "\n\t\t\t\t\t\t\t\t\t</a>\n\t\t\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t\t\t<div class=\"aie-backup-option\">\n\t\t\t\t\t\t\t\t\t<h4>\n\t\t\t\t\t\t\t\t\t\t<span>BackWPup</span>\n\t\t\t\t\t\t\t\t\t\t<span class=\"aie-badge aie-badge-free\">FREE</span>\n\t\t\t\t\t\t\t\t\t</h4>\n\t\t\t\t\t\t\t\t\t<p>").concat(i18n.backupBackWPup || 'Automatic database and file backups', "</p>\n\t\t\t\t\t\t\t\t\t<a href=\"https://wordpress.org/plugins/backwpup/\" target=\"_blank\" class=\"aie-backup-link\">\n\t\t\t\t\t\t\t\t\t\t").concat(i18n.viewPlugin || 'View plugin', "\n\t\t\t\t\t\t\t\t\t</a>\n\t\t\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t\t\t<div class=\"aie-backup-option\">\n\t\t\t\t\t\t\t\t\t<h4>\n\t\t\t\t\t\t\t\t\t\t<span>All-in-One WP Migration</span>\n\t\t\t\t\t\t\t\t\t\t<span class=\"aie-badge aie-badge-free\">FREE</span>\n\t\t\t\t\t\t\t\t\t</h4>\n\t\t\t\t\t\t\t\t\t<p>").concat(i18n.backupAllInOne || 'Complete site export in a single file', "</p>\n\t\t\t\t\t\t\t\t\t<a href=\"https://wordpress.org/plugins/all-in-one-wp-migration/\" target=\"_blank\" class=\"aie-backup-link\">\n\t\t\t\t\t\t\t\t\t\t").concat(i18n.viewPlugin || 'View plugin', "\n\t\t\t\t\t\t\t\t\t</a>\n\t\t\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t\t\t<div class=\"aie-backup-option\">\n\t\t\t\t\t\t\t\t\t<h4>\n\t\t\t\t\t\t\t\t\t\t<span>").concat(i18n.hostingBackup || 'Hosting Backup', "</span>\n\t\t\t\t\t\t\t\t\t\t<span class=\"aie-badge\">").concat(i18n.recommended || 'RECOMMENDED', "</span>\n\t\t\t\t\t\t\t\t\t</h4>\n\t\t\t\t\t\t\t\t\t<p>").concat(i18n.hostingBackupDesc || 'Use built-in backup tools from your hosting provider (cPanel, Plesk, WP Engine, etc.)', "</p>\n\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t<div class=\"aie-backup-checkboxes\">\n\t\t\t\t\t\t\t<div class=\"aie-backup-checkbox aie-checkbox-required\">\n\t\t\t\t\t\t\t\t<input type=\"checkbox\" id=\"aie-backup-created\">\n\t\t\t\t\t\t\t\t<label for=\"aie-backup-created\">\n\t\t\t\t\t\t\t\t\t<strong>").concat(i18n.backupConfirm || 'I have created a database backup and understand the irreversibility of data updates', "</strong>\n\t\t\t\t\t\t\t\t</label>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t<div class=\"aie-backup-checkbox\">\n\t\t\t\t\t\t\t\t<input type=\"checkbox\" id=\"aie-backup-dont-show\">\n\t\t\t\t\t\t\t\t<label for=\"aie-backup-dont-show\">\n\t\t\t\t\t\t\t\t\t").concat(i18n.backupDontShow || 'Don\'t show this warning again', "\n\t\t\t\t\t\t\t\t</label>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\n\t\t\t\t\t<div class=\"aie-backup-warning-footer\">\n\t\t\t\t\t\t<button type=\"button\" class=\"aie-button aie-button-secondary aie-backup-cancel\">\n\t\t\t\t\t\t\t").concat(i18n.cancel || 'Cancel', "\n\t\t\t\t\t\t</button>\n\t\t\t\t\t\t<button type=\"button\" class=\"aie-button aie-button-primary aie-backup-confirm\" disabled>\n\t\t\t\t\t\t\t\u2713 ").concat(i18n.backupContinue || 'Continue', "\n\t\t\t\t\t\t</button>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t");
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (BackupWarningModal);

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

/***/ "./src/js/modules/ai-url-importer.js":
/*!*******************************************!*\
  !*** ./src/js/modules/ai-url-importer.js ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/regenerator */ "./node_modules/@babel/runtime/regenerator/index.js");
/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _utils_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utils.js */ "./src/js/modules/utils.js");

function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) { try { var info = gen[key](arg); var value = info.value; } catch (error) { reject(error); return; } if (info.done) { resolve(value); } else { Promise.resolve(value).then(_next, _throw); } }
function _asyncToGenerator(fn) { return function () { var self = this, args = arguments; return new Promise(function (resolve, reject) { var gen = fn.apply(self, args); function _next(value) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value); } function _throw(err) { asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err); } _next(undefined); }); }; }
/**
 * AI URL Importer Module
 * 
 * Handles the AI-powered URL import workflow
 */


var AIURLImporter = {
  urls: [],
  currentStep: 1,
  settings: {},
  jobId: null,
  previewData: null,
  acfFields: [],
  progressInterval: null,
  /**
   * Initialize module
   */
  init: function init() {
    if (jQuery('#wp-aie-ai-url-importer').length === 0) {
      return;
    }
    this.bindEvents();
    this.loadPostTypes();

    // Check if resuming a job from Jobs Log
    var urlParams = new URLSearchParams(window.location.search);
    var resumeJobId = urlParams.get('resume_job');
    if (resumeJobId) {
      this.jobId = parseInt(resumeJobId);
      this.goToStep(4);
      this.startProgressTracking();
      this.processNextBatch();
    }
  },
  /**
   * Bind event handlers
   */
  bindEvents: function bindEvents() {
    var self = this;

    // Step 1: URL Input
    jQuery('#aie-urls-textarea').on('input', function () {
      return self.handleURLInput();
    });
    jQuery('#aie-browse-csv-btn').on('click', function () {
      return jQuery('#aie-csv-file-input').click();
    });
    jQuery('#aie-csv-file-input').on('change', function (e) {
      return self.handleTXTUpload(e);
    });
    jQuery('.aie-remove-file').on('click', function () {
      return self.removeTXTFile();
    });

    // TXT drag & drop
    var $uploadArea = jQuery('#aie-csv-upload-area');
    $uploadArea.on('dragover', function (e) {
      e.preventDefault();
      $uploadArea.addClass('dragover');
    });
    $uploadArea.on('dragleave', function () {
      return $uploadArea.removeClass('dragover');
    });
    $uploadArea.on('drop', function (e) {
      e.preventDefault();
      $uploadArea.removeClass('dragover');
      var files = e.originalEvent.dataTransfer.files;
      if (files.length > 0 && files[0].name.endsWith('.txt')) {
        jQuery('#aie-csv-file-input')[0].files = files;
        self.handleTXTUpload({
          target: jQuery('#aie-csv-file-input')[0]
        });
      }
    });

    // Step navigation
    jQuery('.aie-next-step').on('click', function () {
      var nextStep = jQuery(this).data('next-step');
      self.goToStep(nextStep);
    });
    jQuery('.aie-prev-step').on('click', function () {
      var prevStep = jQuery(this).data('prev-step');
      self.goToStep(prevStep);
    });

    // Step 2: Field mapping
    jQuery('#aie-post-type').on('change', function () {
      return self.handlePostTypeChange();
    });
    jQuery('#aie-content-field').on('change', function () {
      return self.handleContentFieldChange();
    });

    // Step 3: Test & Preview
    jQuery('#aie-test-connection-btn').on('click', function () {
      return self.testConnection();
    });
    jQuery('#aie-preview-btn').on('click', function () {
      return self.generatePreview();
    });
    jQuery('#aie-regenerate-preview-btn').on('click', function () {
      return self.generatePreview();
    });
    jQuery('#aie-start-import-btn').on('click', function () {
      return self.startImport();
    });

    // Step 4: Import progress
    jQuery('#aie-cancel-import-btn').on('click', function () {
      return self.cancelImport();
    });
    jQuery('#aie-start-new-import-btn').on('click', function () {
      return self.startNewImport();
    });
    jQuery('#aie-view-results-btn').on('click', function () {
      return self.viewResults();
    });
  },
  /**
   * Handle URL textarea input
   */
  handleURLInput: function handleURLInput() {
    var _this = this;
    var text = jQuery('#aie-urls-textarea').val().trim();
    var urls = text.split('\n').filter(function (url) {
      url = url.trim();
      return url && _this.isValidURL(url);
    });
    this.urls = urls;
    this.updateURLCount();
    this.enableNextStep(urls.length > 0);
  },
  /**
   * Handle TXT file upload
   */
  handleTXTUpload: function handleTXTUpload(e) {
    var _this2 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee() {
      var file, reader;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee$(_context) {
        while (1) {
          switch (_context.prev = _context.next) {
            case 0:
              file = e.target.files[0];
              if (file) {
                _context.next = 3;
                break;
              }
              return _context.abrupt("return");
            case 3:
              reader = new FileReader();
              reader.onload = function (event) {
                var text = event.target.result;
                var urls = _this2.parseTXT(text);
                _this2.urls = urls;
                _this2.updateURLCount();
                _this2.showFileInfo(file.name);
                _this2.enableNextStep(urls.length > 0);

                // Clear textarea
                jQuery('#aie-urls-textarea').val('');
              };
              reader.readAsText(file);
            case 6:
            case "end":
              return _context.stop();
          }
        }
      }, _callee);
    }))();
  },
  /**
   * Parse TXT file (one URL per line)
   */
  parseTXT: function parseTXT(text) {
    var _this3 = this;
    var lines = text.split('\n');
    var urls = [];
    lines.forEach(function (line) {
      var url = line.trim();
      if (url && _this3.isValidURL(url)) {
        urls.push(url);
      }
    });
    return urls;
  },
  /**
   * Validate URL
   */
  isValidURL: function isValidURL(string) {
    try {
      var url = new URL(string);
      return url.protocol === 'http:' || url.protocol === 'https:';
    } catch (_unused) {
      return false;
    }
  },
  /**
   * Update URL count display
   */
  updateURLCount: function updateURLCount() {
    var $counter = jQuery('.aie-url-count');
    $counter.find('.count').text(this.urls.length);
    $counter.toggle(this.urls.length > 0);
  },
  /**
   * Show file info
   */
  showFileInfo: function showFileInfo(filename) {
    jQuery('.aie-upload-placeholder').hide();
    jQuery('.aie-file-info').show().find('.file-name').text(filename);
  },
  /**
   * Remove TXT file
   */
  removeTXTFile: function removeTXTFile() {
    jQuery('#aie-csv-file-input').val('');
    jQuery('.aie-file-info').hide();
    jQuery('.aie-upload-placeholder').show();
    this.urls = [];
    this.updateURLCount();
    this.enableNextStep(false);
  },
  /**
   * Enable/disable next step button
   */
  enableNextStep: function enableNextStep(enable) {
    jQuery('.aie-step-1 .aie-next-step').prop('disabled', !enable);
  },
  /**
   * Go to specific step
   */
  goToStep: function goToStep(step) {
    var prevStep = this.currentStep;
    jQuery('.aie-step').hide().removeClass('aie-step-active');
    jQuery(".aie-step-".concat(step)).show().addClass('aie-step-active');
    this.currentStep = step;

    // Reset Step 3 preview whenever entering it (URL may have changed)
    if (step === 3) {
      this.previewData = null;
      jQuery('.aie-preview-result').hide();
      jQuery('.aie-inline-notice').remove();
      jQuery('#aie-preview-btn').show().prop('disabled', false).text(window.aieData.i18n.generatePreview);
      jQuery('#aie-regenerate-preview-btn').hide();
      jQuery('#aie-start-import-btn').prop('disabled', true);
      jQuery('#aie-preview-url').text(this.urls[0]);
    }
  },
  /**
   * Load post types
   */
  loadPostTypes: function loadPostTypes() {
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee2() {
      var response, $select;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee2$(_context2) {
        while (1) {
          switch (_context2.prev = _context2.next) {
            case 0:
              _context2.prev = 0;
              _context2.next = 3;
              return _utils_js__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_ai_url_get_post_types', {});
            case 3:
              response = _context2.sent;
              $select = jQuery('#aie-post-type');
              $select.empty();
              response.post_types.forEach(function (pt) {
                $select.append("<option value=\"".concat(pt.value, "\">").concat(pt.label, "</option>"));
              });
              _context2.next = 12;
              break;
            case 9:
              _context2.prev = 9;
              _context2.t0 = _context2["catch"](0);
              console.error('Failed to load post types:', _context2.t0);
            case 12:
            case "end":
              return _context2.stop();
          }
        }
      }, _callee2, null, [[0, 9]]);
    }))();
  },
  /**
   * Handle post type change
   */
  handlePostTypeChange: function handlePostTypeChange() {
    var _this4 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee3() {
      var postType;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee3$(_context3) {
        while (1) {
          switch (_context3.prev = _context3.next) {
            case 0:
              postType = jQuery('#aie-post-type').val(); // Load ACF fields if available
              if (!(jQuery('#aie-content-field').val() === 'acf_field')) {
                _context3.next = 4;
                break;
              }
              _context3.next = 4;
              return _this4.loadACFFields(postType);
            case 4:
            case "end":
              return _context3.stop();
          }
        }
      }, _callee3);
    }))();
  },
  /**
   * Handle content field change
   */
  handleContentFieldChange: function handleContentFieldChange() {
    var value = jQuery('#aie-content-field').val();
    if (value === 'acf_field') {
      jQuery('#aie-acf-field-row').show();
      jQuery('#aie-custom-field-row').hide();
      this.loadACFFields(jQuery('#aie-post-type').val());
    } else if (value === 'custom_field') {
      jQuery('#aie-custom-field-row').show();
      jQuery('#aie-acf-field-row').hide();
    } else {
      jQuery('#aie-acf-field-row').hide();
      jQuery('#aie-custom-field-row').hide();
    }
  },
  /**
   * Load ACF fields and build tree
   */
  loadACFFields: function loadACFFields(postType) {
    var _this5 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee4() {
      var response, $tree;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee4$(_context4) {
        while (1) {
          switch (_context4.prev = _context4.next) {
            case 0:
              _context4.prev = 0;
              _context4.next = 3;
              return _utils_js__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_ai_url_get_acf_fields', {
                post_type: postType
              });
            case 3:
              response = _context4.sent;
              _this5.acfFields = response.fields || [];
              _this5.renderACFFieldTree(_this5.acfFields);
              _this5.bindACFFieldEvents();
              _context4.next = 14;
              break;
            case 9:
              _context4.prev = 9;
              _context4.t0 = _context4["catch"](0);
              console.error('Failed to load ACF fields:', _context4.t0);
              $tree = jQuery('#aie-acf-field-tree');
              $tree.html("<p class=\"description\" style=\"color: #d63638;\">".concat(window.aieData.i18n.failedLoadAcfFields, "</p>"));
            case 14:
            case "end":
              return _context4.stop();
          }
        }
      }, _callee4, null, [[0, 9]]);
    }))();
  },
  /**
   * Render ACF field tree
   */
  renderACFFieldTree: function renderACFFieldTree(fields) {
    var _this6 = this;
    var searchTerm = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '';
    var $tree = jQuery('#aie-acf-field-tree');
    if (!fields || fields.length === 0) {
      $tree.html("<p class=\"description\">".concat(window.aieData.i18n.noAcfFields, "</p>"));
      return;
    }
    var html = '<ul class="aie-acf-field-list">';
    fields.forEach(function (field) {
      html += _this6.renderACFField(field, searchTerm);
    });
    html += '</ul>';
    $tree.html(html);
  },
  /**
   * Render single ACF field with children
   */
  renderACFField: function renderACFField(field) {
    var _this7 = this;
    var searchTerm = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : '';
    var hasSubFields = field.sub_fields && field.sub_fields.length > 0;
    var isAllowed = field.is_allowed;
    var matchesSearch = !searchTerm || field.label.toLowerCase().includes(searchTerm.toLowerCase());
    if (!matchesSearch && !hasSubFields) {
      return '';
    }
    var html = '<li class="aie-acf-field-item">';

    // Field header
    html += '<div class="aie-acf-field-header' + (hasSubFields ? ' has-children' : '') + '">';

    // Expand/collapse icon for parent fields
    if (hasSubFields) {
      html += '<span class="aie-acf-toggle dashicons dashicons-arrow-right"></span>';
    } else {
      html += '<span class="aie-acf-spacer"></span>';
    }

    // Radio button for selectable fields
    if (isAllowed) {
      html += "<label class=\"aie-acf-field-label\">\n\t\t\t\t<input type=\"radio\" name=\"acf_field_selection\" value=\"".concat(field.name, "\" data-key=\"").concat(field.key, "\" data-type=\"").concat(field.type, "\">\n\t\t\t\t<span class=\"field-name\">").concat(field.label, "</span>\n\t\t\t\t<span class=\"field-type\">(").concat(field.type, ")</span>\n\t\t\t</label>");
    } else {
      html += "<span class=\"aie-acf-field-label disabled\">\n\t\t\t\t<span class=\"field-name\">".concat(field.label, "</span>\n\t\t\t\t<span class=\"field-type\">(").concat(field.type, ")</span>\n\t\t\t</span>");
    }
    html += '</div>';

    // Sub-fields
    if (hasSubFields) {
      html += '<ul class="aie-acf-field-children" style="display: none;">';
      field.sub_fields.forEach(function (subField) {
        html += _this7.renderACFField(subField, searchTerm);
      });
      html += '</ul>';
    }
    html += '</li>';
    return html;
  },
  /**
   * Bind ACF field browser events
   */
  bindACFFieldEvents: function bindACFFieldEvents() {
    var self = this;

    // Search
    jQuery('#aie-acf-field-search').off('input').on('input', function () {
      var searchTerm = jQuery(this).val();
      self.renderACFFieldTree(self.acfFields, searchTerm);
      self.bindACFFieldEvents();
    });

    // Toggle expand/collapse
    jQuery('.aie-acf-toggle').off('click').on('click', function () {
      var $header = jQuery(this).closest('.aie-acf-field-header');
      var $children = $header.siblings('.aie-acf-field-children');
      if ($children.is(':visible')) {
        $children.slideUp(200);
        jQuery(this).removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
      } else {
        $children.slideDown(200);
        jQuery(this).removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
      }
    });

    // Select field
    // Select field
    jQuery('input[name="acf_field_selection"]').off('change').on('change', function () {
      var fieldName = jQuery(this).val();
      jQuery('#aie-acf-field-select').val(fieldName);
    });
  },
  /**
   * Test OpenAI connection
   */
  testConnection: function testConnection() {
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee5() {
      var $btn, $result, response, message;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee5$(_context5) {
        while (1) {
          switch (_context5.prev = _context5.next) {
            case 0:
              $btn = jQuery('#aie-test-connection-btn');
              $result = jQuery('.aie-test-result');
              $btn.prop('disabled', true).text(window.aieData.i18n.testing);
              $result.hide();
              _context5.prev = 4;
              _context5.next = 7;
              return _utils_js__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_ai_url_test_connection', {});
            case 7:
              response = _context5.sent;
              $result.removeClass('error').addClass('success').html("<span class=\"dashicons dashicons-yes\"></span> ".concat(response.message)).show();
              _context5.next = 15;
              break;
            case 11:
              _context5.prev = 11;
              _context5.t0 = _context5["catch"](4);
              message = _context5.t0.message || _context5.t0 || 'Connection test failed';
              $result.removeClass('success').addClass('error').html("<span class=\"dashicons dashicons-no\"></span> ".concat(message)).show();
            case 15:
              _context5.prev = 15;
              $btn.prop('disabled', false).text(window.aieData.i18n.testConnection);
              return _context5.finish(15);
            case 18:
            case "end":
              return _context5.stop();
          }
        }
      }, _callee5, null, [[4, 11, 15, 18]]);
    }))();
  },
  /**
   * Generate preview
   */
  generatePreview: function generatePreview() {
    var _this8 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee6() {
      var $btn, $regenerateBtn, $result, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee6$(_context6) {
        while (1) {
          switch (_context6.prev = _context6.next) {
            case 0:
              $btn = jQuery('#aie-preview-btn');
              $regenerateBtn = jQuery('#aie-regenerate-preview-btn');
              $result = jQuery('.aie-preview-result'); // Always show the primary button in loading state, hide regenerate while loading
              $btn.show().prop('disabled', true).text(window.aieData.i18n.generatingPreview);
              $regenerateBtn.hide();
              $result.hide();
              _context6.prev = 6;
              _context6.next = 9;
              return _utils_js__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_ai_url_preview', {
                url: _this8.urls[0]
              });
            case 9:
              response = _context6.sent;
              _this8.previewData = response;
              _this8.displayPreview(response);
              $result.show();
              // After success: replace Generate with Regenerate
              $btn.hide();
              $regenerateBtn.show();
              jQuery('#aie-start-import-btn').prop('disabled', false);
              _context6.next = 22;
              break;
            case 18:
              _context6.prev = 18;
              _context6.t0 = _context6["catch"](6);
              _this8.showError(_context6.t0, '.aie-preview-section');
              // On error: restore Generate button
              $btn.prop('disabled', false).text(window.aieData.i18n.generatePreview);
            case 22:
            case "end":
              return _context6.stop();
          }
        }
      }, _callee6, null, [[6, 18]]);
    }))();
  },
  /**
   * Show error message with nice formatting
   */
  showError: function showError(error, containerSelector) {
    var message = error.message || error || window.aieData.i18n.errorOccurred;
    var isRateLimit = message.toLowerCase().includes('rate limit');
    var noticeClass = 'notice notice-error';
    var title = window.aieData.i18n.error;
    if (isRateLimit) {
      noticeClass = 'notice notice-warning';
      title = window.aieData.i18n.rateLimitReached;
    }
    var errorHtml = "\n\t\t\t<div class=\"".concat(noticeClass, " aie-inline-notice\">\n\t\t\t\t<p><strong>").concat(title, "</strong></p>\n\t\t\t\t<p>").concat(message, "</p>\n\t\t\t</div>\n\t\t");

    // Remove any existing error notices
    jQuery(containerSelector).find('.aie-inline-notice').remove();

    // Add the error notice
    jQuery(containerSelector).prepend(errorHtml);

    // Scroll to error
    var noticeElement = jQuery(containerSelector).find('.aie-inline-notice').get(0);
    if (noticeElement) {
      noticeElement.scrollIntoView({
        behavior: 'smooth',
        block: 'nearest'
      });
    }
  },
  /**
   * Display preview
   */
  displayPreview: function displayPreview(data) {
    jQuery('.preview-title-content').html("<h3>".concat(data.title, "</h3>"));
    jQuery('.preview-excerpt-content').html("<p>".concat(data.excerpt, "</p>"));
    jQuery('.preview-content-html').html(data.content);

    // Show content stats and truncation warning
    var contentText = jQuery('.preview-content-html').text();
    var charCount = contentText.length;
    var wordCount = contentText.trim().split(/\s+/).filter(function (w) {
      return w.length > 0;
    }).length;
    var statsHtml = "<p class=\"aie-content-stats\">\uD83D\uDCC4 ".concat(wordCount, " ").concat(window.aieData.i18n.words || 'words', ", ").concat(charCount.toLocaleString(), " ").concat(window.aieData.i18n.characters || 'characters', "</p>");
    if (data.truncated) {
      statsHtml += "<div class=\"aie-truncation-warning notice notice-warning inline\"><p>\u26A0\uFE0F ".concat(window.aieData.i18n.contentTruncated || 'Warning: the article content was truncated by the AI because it exceeded the token limit. The imported post will contain incomplete content. Consider using a shorter article or a more powerful model.', "</p></div>");
    }
    jQuery('.aie-preview-content-stats').remove();
    jQuery('.preview-content-html').after("<div class=\"aie-preview-content-stats\">".concat(statsHtml, "</div>"));

    // Display images
    var $imagesList = jQuery('.preview-images-list');
    $imagesList.empty();
    if (data.images && data.images.length > 0) {
      data.images.forEach(function (img) {
        $imagesList.append("\n\t\t\t\t\t<div class=\"preview-image-item\">\n\t\t\t\t\t\t<img src=\"".concat(img.url, "\" alt=\"").concat(img.alt, "\" style=\"max-width: 200px; height: auto;\">\n\t\t\t\t\t\t<p><small>").concat(img.url, "</small></p>\n\t\t\t\t\t</div>\n\t\t\t\t"));
      });
    } else {
      $imagesList.html("<p>".concat(window.aieData.i18n.noImagesFound, "</p>"));
    }

    // Display featured image
    if (data.featured_image) {
      jQuery('.preview-featured-image').html("\n\t\t\t\t<img src=\"".concat(data.featured_image, "\" alt=\"Featured\" style=\"max-width: 300px; height: auto;\">\n\t\t\t"));
    } else {
      jQuery('.preview-featured-image').html("<p>".concat(window.aieData.i18n.noFeaturedImage, "</p>"));
    }
  },
  /**
   * Start import
   */
  startImport: function startImport() {
    var _this9 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee7() {
      var response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee7$(_context7) {
        while (1) {
          switch (_context7.prev = _context7.next) {
            case 0:
              _this9.settings = {
                urls: _this9.urls,
                post_type: jQuery('#aie-post-type').val(),
                content_field: jQuery('#aie-content-field').val(),
                acf_field: jQuery('#aie-acf-field-select').val(),
                custom_field_name: jQuery('#aie-custom-field-name').val(),
                timeout: parseInt(jQuery('#aie-request-timeout').val()) || 2
              };
              _context7.prev = 1;
              _context7.next = 4;
              return _utils_js__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_ai_url_start_import', _this9.settings);
            case 4:
              response = _context7.sent;
              _this9.jobId = response.job_id;
              _this9.goToStep(4);

              // Start progress monitoring
              _this9.startProgressTracking();

              // Start processing batches
              _this9.processNextBatch();
              _context7.next = 15;
              break;
            case 11:
              _context7.prev = 11;
              _context7.t0 = _context7["catch"](1);
              console.error('Error starting import:', _context7.t0);
              _this9.showError(_context7.t0, '.aie-step-3 .aie-step-content');
            case 15:
            case "end":
              return _context7.stop();
          }
        }
      }, _callee7, null, [[1, 11]]);
    }))();
  },
  /**
   * Start progress tracking with interval
   */
  startProgressTracking: function startProgressTracking() {
    var _this10 = this;
    this.progressInterval = setInterval(function () {
      _this10.updateProgress();
    }, 2000);
  },
  /**
   * Process next batch of URLs
   */
  processNextBatch: function processNextBatch() {
    var _this11 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee8() {
      var response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee8$(_context8) {
        while (1) {
          switch (_context8.prev = _context8.next) {
            case 0:
              _context8.prev = 0;
              _context8.next = 3;
              return _utils_js__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_ai_url_process_batch', {
                job_id: _this11.jobId
              });
            case 3:
              response = _context8.sent;
              if (!response.completed) {
                _context8.next = 10;
                break;
              }
              // Job is completed — stop interval and force a final UI update
              _this11.stopProgressTracking();
              _context8.next = 8;
              return _this11.updateProgress();
            case 8:
              _context8.next = 11;
              break;
            case 10:
              // Continue processing with a small delay
              setTimeout(function () {
                return _this11.processNextBatch();
              }, 500);
            case 11:
              _context8.next = 18;
              break;
            case 13:
              _context8.prev = 13;
              _context8.t0 = _context8["catch"](0);
              console.error('Error processing batch:', _context8.t0);
              // Don't stop progress tracking on error — let the polling interval
              // detect the final job status (completed / failed) from the DB.
              // Force an immediate progress check right now.
              _context8.next = 18;
              return _this11.updateProgress();
            case 18:
            case "end":
              return _context8.stop();
          }
        }
      }, _callee8, null, [[0, 13]]);
    }))();
  },
  /**
   * Update progress display
   */
  /**
   * Update progress display
   */
  updateProgress: function updateProgress() {
    var _this12 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee9() {
      var response, progress, completedText, failedText;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee9$(_context9) {
        while (1) {
          switch (_context9.prev = _context9.next) {
            case 0:
              if (_this12.jobId) {
                _context9.next = 2;
                break;
              }
              return _context9.abrupt("return");
            case 2:
              _context9.prev = 2;
              _context9.next = 5;
              return _utils_js__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_ai_url_get_progress', {
                job_id: _this12.jobId
              });
            case 5:
              response = _context9.sent;
              // Update progress bar
              progress = Math.round(response.progress);
              jQuery('.aie-progress-fill').css('width', progress + '%');
              jQuery('.aie-progress-fill').text(progress + '%');

              // Update progress text
              jQuery('.aie-progress-text .current').text(response.processed);
              jQuery('.aie-progress-text .total').text(response.total);

              // Update status counts
              jQuery('.success-count').text(response.success_count);
              jQuery('.failed-count').text(response.failed_count);
              jQuery('.import-status-text').text(response.status);

              // Check if completed or failed
              if (response.status === 'completed' || response.status === 'failed') {
                _this12.stopProgressTracking();

                // Show completion UI
                jQuery('#aie-cancel-import-btn').hide();
                jQuery('#aie-start-new-import-btn, #aie-view-results-btn').show();
                if (response.status === 'completed') {
                  completedText = window.aieData.i18n.importCompleted.replace('%s', response.success_count);
                  jQuery('.import-status-text').text(completedText);
                } else if (response.status === 'failed') {
                  failedText = window.aieData.i18n.importFailed.replace('%s', response.error);
                  jQuery('.import-status-text').text(failedText);
                }
              }
              _context9.next = 21;
              break;
            case 17:
              _context9.prev = 17;
              _context9.t0 = _context9["catch"](2);
              console.error('Error polling job progress:', _context9.t0);
              _this12.stopProgressTracking();
            case 21:
            case "end":
              return _context9.stop();
          }
        }
      }, _callee9, null, [[2, 17]]);
    }))();
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
   * Cancel import
   */
  cancelImport: function cancelImport() {
    var _this13 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee10() {
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee10$(_context10) {
        while (1) {
          switch (_context10.prev = _context10.next) {
            case 0:
              if (confirm(window.aieData.i18n.confirmCancelImport)) {
                _context10.next = 2;
                break;
              }
              return _context10.abrupt("return");
            case 2:
              _context10.prev = 2;
              _context10.next = 5;
              return _utils_js__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('cancel_job', {
                job_id: _this13.jobId
              });
            case 5:
              // Reset and go back to step 1
              _this13.jobId = null;
              _this13.goToStep(1);
              _context10.next = 13;
              break;
            case 9:
              _context10.prev = 9;
              _context10.t0 = _context10["catch"](2);
              console.error('Error cancelling job:', _context10.t0);
              alert(window.aieData.i18n.failedCancelImport);
            case 13:
            case "end":
              return _context10.stop();
          }
        }
      }, _callee10, null, [[2, 9]]);
    }))();
  },
  /**
   * Start new import
   */
  startNewImport: function startNewImport() {
    this.urls = [];
    this.previewData = null;
    this.jobId = null;
    jQuery('#aie-urls-textarea').val('');
    this.removeTXTFile();
    this.goToStep(1);
  },
  /**
   * View results
   */
  viewResults: function viewResults() {
    var postType = this.settings.post_type;
    window.location.href = "edit.php?post_type=".concat(postType);
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (AIURLImporter);

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
      $tbody.html("\n\t\t\t\t<tr class=\"aie-no-sites\">\n\t\t\t\t\t<td colspan=\"5\" style=\"text-align: center; padding: 40px;\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-admin-site\" style=\"font-size: 48px; opacity: 0.3;\"></span>\n\t\t\t\t\t\t<p style=\"margin-top: 40px\">".concat(window.aieData.i18n.noConnectedSites || 'No connected sites yet. Add your first connection!', "</p>\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t"));
      return;
    }
    sites.forEach(function (site) {
      var statusClass = "aie-status-".concat(site.status);
      var lastSync = site.last_sync_at ? new Date(site.last_sync_at).toLocaleString() : window.aieData.i18n.never || 'Never';
      var row = "\n\t\t\t\t<tr data-site-id=\"".concat(site.id, "\">\n\t\t\t\t\t<td class=\"column-name\">\n\t\t\t\t\t\t<strong>").concat(_this3.escapeHtml(site.name), "</strong>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"column-url\">\n\t\t\t\t\t\t<a href=\"").concat(_this3.escapeHtml(site.remote_url), "\" target=\"_blank\" rel=\"noopener noreferrer\">\n\t\t\t\t\t\t\t").concat(_this3.escapeHtml(site.remote_url), "\n\t\t\t\t\t\t</a>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"column-status\">\n\t\t\t\t\t\t<span class=\"aie-status-badge ").concat(statusClass, "\">\n\t\t\t\t\t\t\t").concat(_this3.escapeHtml(site.status), "\n\t\t\t\t\t\t</span>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"column-last-sync\">\n\t\t\t\t\t\t").concat(lastSync, "\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"column-actions\">\n\t\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-test-connection\" data-site-id=\"").concat(site.id, "\" title=\"").concat(window.aieData.i18n.testConnection || 'Test Connection', "\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-update\"></span>\n\t\t\t\t\t\t</button>\n\t\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-edit-site\" data-site-id=\"").concat(site.id, "\" title=\"").concat(window.aieData.i18n.edit || 'Edit', "\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-edit\"></span>\n\t\t\t\t\t\t</button>\n\t\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-delete-site\" data-site-id=\"").concat(site.id, "\" title=\"").concat(window.aieData.i18n["delete"] || 'Delete', "\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-trash\"></span>\n\t\t\t\t\t\t</button>\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t");
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
    $('#aie-modal-title').text(window.aieData.i18n.addNewSite || 'Add New Site');
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
    $('#aie-modal-title').text(window.aieData.i18n.editSite || 'Edit Site');
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
    var buttonText = isEdit && !hasApiKey ? window.aieData.i18n.updating || 'Updating...' : window.aieData.i18n.validatingSaving || 'Validating & Saving...';
    $saveBtn.prop('disabled', true).text(buttonText);

    // Show info message when validating API key
    if (hasApiKey) {
      this.showModalNotice('info', window.aieData.i18n.validatingApiKey || 'Validating API key...', window.aieData.i18n.pleaseWaitVerifying || 'Please wait while we verify the connection to the remote site.');
    }
    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: data,
      success: function success(response) {
        if (response.success) {
          var message = response.data.message || window.aieData.i18n.operationCompleted || 'Operation completed successfully';

          // Check if no changes were made
          if (message.includes('No changes')) {
            _this4.showModalNotice('info', window.aieData.i18n.noChanges || 'No Changes', message);
            // Close modal after delay
            setTimeout(function () {
              $('#aie-site-modal').hide();
            }, 2000);
          } else {
            _this4.showModalNotice('success', window.aieData.i18n.success || 'Success!', message);
            // Close modal after short delay
            setTimeout(function () {
              $('#aie-site-modal').hide();
              _this4.loadSites();
              _this4.showNotice('success', message);
            }, 1500);
          }
        } else {
          _this4.showModalNotice('error', window.aieData.i18n.validationFailed || 'Validation Failed', response.data.message || window.aieData.i18n.failedSaveSiteConnection || 'Failed to save site connection');
        }
      },
      error: function error(xhr) {
        var errorTitle = window.aieData.i18n.connectionError || 'Connection Error';
        var errorMessage = window.aieData.i18n.unexpectedError || 'An unexpected error occurred while trying to save the site connection.';
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
            errorTitle = window.aieData.i18n.connectionFailed || 'Connection Failed';
            errorDetails.push(window.aieData.i18n.possibleReasons || 'Possible reasons:');
            errorDetails.push(window.aieData.i18n.urlIncorrect || '- The URL is incorrect or not accessible');
            errorDetails.push(window.aieData.i18n.remoteSiteOffline || '- The remote site is offline');
            errorDetails.push(window.aieData.i18n.networkFirewall || '- Network or firewall issues are blocking the connection');
          } else if (errorMessage.includes('Invalid API key')) {
            errorTitle = window.aieData.i18n.invalidApiKey || 'Invalid API Key';
            errorDetails.push(window.aieData.i18n.toResolveIssue || 'To resolve this issue:');
            errorDetails.push(window.aieData.i18n.goToContentSync || '- Go to Content Sync page on the remote site');
            errorDetails.push(window.aieData.i18n.clickShowDetails || '- Click "Show Details" to reveal the API key');
            errorDetails.push(window.aieData.i18n.copyEntireKey || '- Copy the entire key and paste it here');
          } else if (errorMessage.includes('plugin is not installed') || errorMessage.includes('plugin is not active')) {
            errorTitle = window.aieData.i18n.pluginNotFound || 'Plugin Not Found';
            // No additional details needed, message is clear
          } else if (errorMessage.includes('already connected')) {
            errorTitle = window.aieData.i18n.duplicateConnection || 'Duplicate Connection';
            errorDetails.push(window.aieData.i18n.siteAlreadyConnected || 'This site URL is already in your connected sites list.');
          } else if (errorMessage.includes('required')) {
            errorTitle = window.aieData.i18n.validationError || 'Validation Error';
            // Field validation errors are clear enough
          } else {
            errorTitle = window.aieData.i18n.error || 'Error';
            // Use the server message as-is for other errors
          }
        } else if (xhr.status === 0) {
          errorTitle = window.aieData.i18n.networkError || 'Network Error';
          errorMessage = window.aieData.i18n.unableConnectServer || 'Unable to connect to the server. Please check your internet connection.';
        } else if (xhr.status >= 500) {
          errorTitle = window.aieData.i18n.serverError || 'Server Error';
          errorMessage = (window.aieData.i18n.serverReturnedError || 'The server returned an error (%s). Please try again later.').replace('%s', xhr.status);
        } else if (xhr.status === 404) {
          errorTitle = window.aieData.i18n.notFound || 'Not Found';
          errorMessage = window.aieData.i18n.endpointNotFound || 'The requested endpoint was not found. Please check if the plugin is properly installed.';
        }
        _this4.showModalNotice('error', errorTitle, errorMessage, errorDetails);
      },
      complete: function complete() {
        $saveBtn.prop('disabled', false).text(window.aieData.i18n.saveConnection || 'Save Connection');
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
    $info.slideToggle(200, function () {
      if ($info.is(':visible')) {
        $btn.html("<span class=\"dashicons dashicons-hidden\"></span> ".concat(window.aieData.i18n.hideDetails || 'Hide Details'));
      } else {
        $btn.html("<span class=\"dashicons dashicons-visibility\"></span> ".concat(window.aieData.i18n.showDetails || 'Show Details'));
      }
    });
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
    $btn.html("<span class=\"dashicons dashicons-yes\"></span> ".concat(window.aieData.i18n.copied || 'Copied!'));
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
    $btn.prop('disabled', true).html("<span class=\"dashicons dashicons-update\"></span> ".concat(window.aieData.i18n.regenerating || 'Regenerating...'));
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
          $btn.html("<span class=\"dashicons dashicons-yes\"></span> ".concat(window.aieData.i18n.regenerated || 'Regenerated!'));
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
/* harmony import */ var _BackupWarningModal__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./BackupWarningModal */ "./src/js/modules/BackupWarningModal.js");
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }
function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { _defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
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
  selectedFieldTypes: {},
  selectedTableName: '',
  currentTableColumns: [],
  fieldFunctions: {},
  availableFunctions: [],
  selectedFilters: [],
  // Store selected filters
  selectedTaxonomyFilters: [],
  // Store selected taxonomy filters
  filteredCount: null,
  // Store filtered item count
  /**
   * Initialize module
   */
  init: function init() {
    var _this = this;
    if (!jQuery('#wp-aie-content-updater').length) {
      return;
    }

    // Check if resuming a job from Jobs Log
    var urlParams = new URLSearchParams(window.location.search);
    var resumeJobId = urlParams.get('resume_job');
    this.bindEvents();
    if (resumeJobId) {
      this.jobId = parseInt(resumeJobId, 10);
      if (Number.isNaN(this.jobId)) {
        this.showStep(1);
        this.loadAvailableFunctions();
        return;
      }
      this.showStep(5);

      // Show progress UI immediately when resuming
      jQuery('#aie-updater-config').hide();
      jQuery('#aie-updater-progress').show();
      jQuery('#aie-updater-prev-from-step-4').hide();

      // Get current state and continue processing
      this.updateProgress().then(function () {
        _this.startProgressTracking();
        _this.processNextBatch();
      });
    } else {
      this.showStep(1);
    }
    this.loadAvailableFunctions();
  },
  /**
   * Bind event handlers
   */
  bindEvents: function bindEvents() {
    var _this2 = this;
    var $wizard = jQuery('#wp-aie-content-updater');

    // Content type search
    $wizard.on('input', '#aie-updater-content-type-search', function (e) {
      return _this2.filterContentTypes(e);
    });

    // Step navigation
    $wizard.on('click', '.aie-updater-next-step', function () {
      return _this2.nextStep();
    });
    $wizard.on('click', '.aie-updater-prev-step', function () {
      return _this2.prevStep();
    });

    // Content type selection
    $wizard.on('change', 'input[name="updater_content_type"]', function (e) {
      return _this2.onContentTypeChange(e);
    });

    // Prevent selection of premium locked content types
    $wizard.on('click', '.aie-content-type.aie-premium-locked', function (e) {
      e.preventDefault();
      e.stopPropagation();

      // Show upgrade message
      var message = window.aieData.i18n.premiumOnlyFeature;
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(message, 'warning');

      // Prevent the radio button from being checked
      var $input = jQuery(e.currentTarget).find('input[type="radio"]');
      $input.prop('checked', false);
      return false;
    });

    // Filter events (Step 2)
    $wizard.on('click', '.aie-updater-add-filter', function () {
      return _this2.addFilterRow();
    });
    $wizard.on('click', '.aie-updater-remove-filter', function (e) {
      return _this2.removeFilterRow(e);
    });
    $wizard.on('change', '.aie-updater-filter-field', function (e) {
      return _this2.onFilterFieldChange(e);
    });
    $wizard.on('change', '.aie-updater-filter-condition', function (e) {
      return _this2.onFilterConditionChange(e);
    });
    $wizard.on('change', '.aie-updater-filter-value', function () {
      return _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
        return _this2.refreshCount(false);
      }, 500)();
    });
    $wizard.on('click', '.aie-updater-refresh-count', function () {
      return _this2.refreshCount(true);
    });
    $wizard.on('change', '#aie-updater-table-name', function (e) {
      return _this2.onDatabaseTableChange(e);
    });

    // Field selection (Step 3)
    $wizard.on('click', '.aie-updater-clear-all-fields', function () {
      return _this2.clearAllFields();
    });
    $wizard.on('input', '#aie-updater-fields-search', function (e) {
      return _this2.filterFields(e);
    });
    $wizard.on('click', '.aie-clear-search', function (e) {
      e.preventDefault();
      var $search = jQuery('#aie-updater-fields-search');
      $search.val('');
      $search.trigger('input');
      $search.focus();
    });
    $wizard.on('click', '.aie-add-all-fields', function (e) {
      e.preventDefault();
      _this2.addAllFieldsFromCategory(e.currentTarget);
    });

    // Function assignment (Step 3) - old handlers kept for compatibility
    $wizard.on('change', '.aie-field-function-select', function (e) {
      return _this2.onFunctionChange(e);
    });
    $wizard.on('click', '.aie-apply-function-to-all', function () {
      return _this2.applyFunctionToAll();
    });
    $wizard.on('click', '.aie-clear-all-functions', function () {
      return _this2.clearAllFunctions();
    });
    $wizard.on('click', '.aie-test-function', function (e) {
      return _this2.testFunction(e);
    });

    // Start update (Step 4)
    $wizard.on('click', '.aie-start-update-btn', function () {
      return _this2.startUpdate();
    });
    $wizard.on('click', '.aie-cancel-update-btn', function () {
      return _this2.cancelUpdate();
    });
    $wizard.on('click', '.aie-start-new-update', function () {
      return _this2.startNewUpdate();
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
    var _this3 = this;
    var $modal = jQuery('#aie-updater-functions-modal');
    if (!$modal.length) return;

    // Close modal
    $modal.find('.aie-modal-close').on('click', function () {
      _this3.closeFieldFunctionsModal();
    });
    $modal.find('.aie-modal-cancel').on('click', function () {
      _this3.closeFieldFunctionsModal();
    });

    // Save functions
    $modal.find('.aie-save-updater-functions').on('click', function () {
      _this3.saveFieldFunctions();
    });
    $modal.find('.aie-test-updater-pipeline').on('click', function () {
      _this3.testFunctionPipeline();
    });

    // Functions search
    $modal.find('#aie-updater-functions-search').on('input', function (e) {
      _this3.filterFunctions(e.target.value);
    });

    // Functions filter
    $modal.find('input[name="updater-functions-filter"]').on('change', function (e) {
      _this3.filterFunctionsByCategory(e.target.value);
    });

    // Create new function button
    $modal.find('.aie-create-new-function').on('click', function (e) {
      e.preventDefault();
      _this3.createNewFunction();
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
    var _this4 = this;
    if (!this.validateCurrentStep()) {
      return;
    }

    // Show backup warning when leaving step 1 (content type selection)
    if (this.currentStep === 1) {
      _BackupWarningModal__WEBPACK_IMPORTED_MODULE_1__["default"].show(function () {
        // User confirmed backup - proceed to next step
        _this4.proceedToNextStep();
      }, function () {
        // User cancelled - stay on current step
      });
      return;
    }
    this.proceedToNextStep();
  },
  /**
   * Proceed to next step (after validation and backup warning)
   */
  proceedToNextStep: function proceedToNextStep() {
    // Save filters when leaving step 2
    if (this.currentStep === 2) {
      var collectedFilters = this.collectFilters();
      this.selectedFilters = collectedFilters.filters;
      this.selectedTaxonomyFilters = collectedFilters.taxonomy;
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
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.pleaseSelectContentType, 'error');
          return false;
        }
        return true;
      case 2:
        // Database table must be selected for database_table type
        if (this.isDatabaseTableType() && !this.getSelectedTableName()) {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.pleaseSelectTable, 'error');
          jQuery('#aie-updater-table-name').trigger('focus');
          return false;
        }

        // Taxonomy terms require at least one taxonomy filter
        if (this.requiresFilter() && !this.hasRequiredFilter()) {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.pleaseAddFilter || 'Please add at least one filter to narrow down the items.', 'error');
          return false;
        }
        return true;
      case 3:
        // At least one field must be selected
        if (this.selectedFields.length === 0) {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.pleaseSelectAtLeastOneField, 'error');
          return false;
        }
        return true;
      case 4:
        // At least one function must be assigned
        var hasFunction = Object.values(this.fieldFunctions).some(function (functions) {
          return Array.isArray(functions) && functions.length > 0;
        });
        if (!hasFunction) {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.pleaseAssignFunction, 'error');
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
    var $nextStepBtn = jQuery('.aie-updater-step-1 .aie-next-step');
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
      // Disable Next button when no results found
      $nextStepBtn.prop('disabled', true);
    } else {
      jQuery('.aie-no-results').hide();
      // Enable Next button when results are visible
      $nextStepBtn.prop('disabled', false);
    }
  },
  /**
   * Handle content type change
   */
  onContentTypeChange: function onContentTypeChange(e) {
    var contentType = jQuery(e.target).val();

    // Reset selections for new content type
    this.selectedFields = [];
    this.selectedFieldTypes = {};
    this.selectedTableName = '';
    this.currentTableColumns = [];
    this.fieldFunctions = {};
    this.selectedFilters = [];
    this.selectedTaxonomyFilters = [];
    this.filteredCount = null; // Reset filtered count when content type changes

    if (this.isDatabaseTableType(contentType)) {
      jQuery('.aie-table-selection-section').show();
    } else {
      jQuery('.aie-table-selection-section').hide();
      jQuery('.aie-table-info').hide();
      jQuery('#aie-updater-table-name').val('');
      if (window.aieExportModule) {
        window.aieExportModule.currentTableColumns = [];
      }
    }
  },
  /**
   * Load filters library for selected content type
   */
  loadFiltersLibrary: function loadFiltersLibrary() {
    // Get selected content type
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();
    if (!contentType) {
      return;
    }

    // Clear existing filters
    jQuery('#aie-updater-filters-list').empty();
    if (this.isDatabaseTableType(contentType)) {
      jQuery('.aie-table-selection-section').show();
      this.loadDatabaseTables();
    } else {
      jQuery('.aie-table-selection-section').hide();
      jQuery('.aie-table-info').hide();
    }

    // Immediately update Next button state (may disable it for types that require a filter)
    this.updateStep2NextButton();

    // Refresh count
    this.refreshCount(true);
  },
  /**
   * Returns true for content types that must have at least one filter before proceeding
   */
  requiresFilter: function requiresFilter() {
    var contentType = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : null;
    var ct = contentType || jQuery('input[name="updater_content_type"]:checked').val();
    return ct === 'taxonomy';
  },
  /**
   * Returns true when the required filter for the current content type is present and has a value
   */
  hasRequiredFilter: function hasRequiredFilter() {
    var collectedFilters = this.collectFilters();
    // For taxonomy: need at least one filter with field === 'taxonomy' and a non-empty value
    var taxonomyFilter = collectedFilters.filters.find(function (f) {
      return f.field === 'taxonomy' && (f.value || '').trim() !== '';
    });
    return !!taxonomyFilter;
  },
  /**
   * Enable / disable the Step 2 "Next Step" button based on filter requirements
   */
  updateStep2NextButton: function updateStep2NextButton() {
    if (this.currentStep !== 2) {
      return;
    }
    var $btn = jQuery('.aie-updater-step-2 .aie-updater-next-step');
    if ($btn.length === 0) {
      return;
    }
    var needsFilter = this.requiresFilter();
    var hasFilter = needsFilter ? this.hasRequiredFilter() : true;
    $btn.prop('disabled', !hasFilter);
    $btn.toggleClass('button-disabled', !hasFilter);
  },
  /**
   * Check if selected type is database table
   */
  isDatabaseTableType: function isDatabaseTableType() {
    var contentType = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : null;
    var selectedType = contentType || jQuery('input[name="updater_content_type"]:checked').val();
    return selectedType === 'database_table';
  },
  /**
   * Get selected database table name
   */
  getSelectedTableName: function getSelectedTableName() {
    return this.selectedTableName || jQuery('#aie-updater-table-name').val() || '';
  },
  /**
   * Build options payload for updater AJAX calls
   */
  buildRequestOptions: function buildRequestOptions(contentType) {
    var extraOptions = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {};
    var options = _objectSpread({}, extraOptions);
    if (this.isDatabaseTableType(contentType)) {
      var tableName = this.getSelectedTableName();
      if (tableName) {
        options.table_name = tableName;
      }
    }
    return options;
  },
  /**
   * Load database table list for updater
   */
  loadDatabaseTables: function loadDatabaseTables() {
    var _this5 = this;
    var $dropdown = jQuery('#aie-updater-table-name');
    var $spinner = jQuery('.aie-table-selector .spinner');
    if (!$dropdown.length) {
      return;
    }
    $dropdown.prop('disabled', true);
    $spinner.addClass('is-active');
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].ajax('aie_get_database_tables', {}).then(function (response) {
      var tables = response.tables || response || [];
      $dropdown.empty();
      $dropdown.append(jQuery('<option>').val('').text(window.aieData.i18n.selectTable));
      if (!Array.isArray(tables) || tables.length === 0) {
        $dropdown.append(jQuery('<option>').val('').text(window.aieData.i18n.noTablesFound));
        $dropdown.prop('disabled', true);
        $spinner.removeClass('is-active');
        return;
      }
      tables.forEach(function (table) {
        $dropdown.append(jQuery('<option>').val(table.table_name).text(table.label));
      });
      $dropdown.prop('disabled', false);
      $spinner.removeClass('is-active');
      var currentTable = _this5.getSelectedTableName();
      if (currentTable) {
        $dropdown.val(currentTable);
        if ($dropdown.val()) {
          _this5.loadDatabaseTableColumns(currentTable);
        }
      }
    })["catch"](function () {
      $dropdown.empty();
      $dropdown.append(jQuery('<option>').val('').text(window.aieData.i18n.errorLoadingTables));
      $dropdown.prop('disabled', true);
      $spinner.removeClass('is-active');
    });
  },
  /**
   * Handle selected database table change
   */
  onDatabaseTableChange: function onDatabaseTableChange(e) {
    var tableName = jQuery(e.target).val() || '';
    this.selectedTableName = tableName;
    if (!tableName) {
      this.currentTableColumns = [];
      jQuery('.aie-table-info').hide();
      jQuery('.aie-columns-list').empty();
      jQuery('.aie-table-row-count').text('-');
      jQuery('.aie-table-column-count').text('-');
      if (window.aieExportModule) {
        window.aieExportModule.currentTableColumns = [];
      }
      this.refreshCount(false);
      return;
    }
    this.loadDatabaseTableColumns(tableName);
  },
  /**
   * Load selected database table columns and stats
   */
  loadDatabaseTableColumns: function loadDatabaseTableColumns(tableName) {
    var _this6 = this;
    var $tableInfo = jQuery('.aie-table-info');
    var $columnsList = jQuery('.aie-columns-list');
    var $rowCount = jQuery('.aie-table-row-count');
    var $columnCount = jQuery('.aie-table-column-count');
    $tableInfo.show();
    $columnsList.html("<p>".concat(window.aieData.i18n.loadingTableColumns, "</p>"));
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].ajax('aie_get_table_columns', {
      table_name: tableName
    }).then(function (response) {
      var columns = response.columns || [];
      var rowCount = response.row_count || 0;
      _this6.currentTableColumns = columns;
      if (window.aieExportModule) {
        window.aieExportModule.currentTableColumns = columns;
      }
      $rowCount.text(rowCount);
      $columnCount.text(columns.length);
      $columnsList.empty();
      var $list = jQuery('<ul>').addClass('aie-column-type-list');
      columns.forEach(function (col) {
        $list.append(jQuery('<li>').html("<strong>".concat(_this6.escapeHtml(col.name), "</strong> <span class=\"column-type\">(").concat(_this6.escapeHtml(col.type), ")</span>")));
      });
      $columnsList.append($list);
      _this6.refreshCount(false);
    })["catch"](function () {
      _this6.currentTableColumns = [];
      if (window.aieExportModule) {
        window.aieExportModule.currentTableColumns = [];
      }
      $columnsList.html("<p>".concat(window.aieData.i18n.errorLoadingColumns, "</p>"));
      $rowCount.text('-');
      $columnCount.text('-');
    });
  },
  /**
   * Add new filter row
   */
  addFilterRow: function addFilterRow() {
    var _this7 = this;
    var template = document.getElementById('aie-updater-filter-row-template');
    var clone = template.content.cloneNode(true);
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();
    if (this.isDatabaseTableType(contentType) && !this.getSelectedTableName()) {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.pleaseSelectTable, 'warning');
      jQuery('#aie-updater-table-name').trigger('focus');
      return;
    }

    // Populate field options based on content type
    var $fieldSelect = jQuery(clone).find('.aie-updater-filter-field');

    // Use export module's getFilterFieldsByContentType if available (excludes Featured Image group)
    if (typeof window.aieExportModule !== 'undefined' && window.aieExportModule.getFilterFieldsByContentType) {
      var fields = window.aieExportModule.getFilterFieldsByContentType(contentType);

      // Exclude certain fields from Content Updater filters for the 'user' content type
      if (contentType === 'user') {
        var userExcludedFields = ['capabilities', 'user_registered', 'posts_count', 'rich_editing', 'admin_color', 'locale'];
        fields = fields.map(function (group) {
          return _objectSpread(_objectSpread({}, group), {}, {
            options: group.options.filter(function (opt) {
              return !userExcludedFields.includes(opt.value);
            })
          });
        }).filter(function (group) {
          return group.options.length > 0;
        });
      }
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
      return _this7.refreshCount(false);
    }, 500)();
  },
  /**
   * Remove filter row
   */
  removeFilterRow: function removeFilterRow(e) {
    var _this8 = this;
    jQuery(e.target).closest('.aie-filter-row').remove();
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
      return _this8.refreshCount(false);
    }, 500)();
  },
  /**
   * Handle filter field change
   */
  onFilterFieldChange: function onFilterFieldChange(e) {
    var _this9 = this;
    var $field = jQuery(e.target);
    var $row = $field.closest('.aie-filter-row');
    var $condition = $row.find('.aie-updater-filter-condition');
    var $conditionWrap = $condition.closest('.aie-filter-condition-wrap');
    var $valueWrap = $row.find('.aie-filter-value-wrap');
    var selectedOption = $field.find('option:selected');
    var fieldType = selectedOption.data('type') || 'string';

    // Remove any previously injected custom UIs when switching field type
    $row.find('.aie-updater-meta-key-wrap').remove();
    $row.find('.aie-filter-row-inner').removeClass('has-meta-key');
    $row.find('.aie-taxonomy-filter-inputs').closest('.aie-filter-value-wrap').find('.aie-taxonomy-filter-inputs').remove();

    // Restore standard condition/value wrap if they were hidden by a previous custom type
    $conditionWrap.show();
    $valueWrap.show().html("<label>".concat(window.aieData.i18n.value || 'Value', "</label>\n\t\t\t<input type=\"text\" class=\"aie-updater-filter-value\" name=\"updater_filter_value[]\" placeholder=\"").concat(window.aieData.i18n.enterFilterValue || '', "\">"));

    // Special handling for post_type_selector — replicate export.js behaviour
    if (fieldType === 'post_type_selector') {
      $conditionWrap.hide();
      $valueWrap.find('label').text(window.aieData.i18n.selectPostType || 'Post Type');

      // Replace text input with a <select> populated from AJAX
      var $select = jQuery('<select>').addClass('aie-updater-filter-value aie-post-type-selector').attr('name', 'updater_filter_value[]');
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].ajax('aie_get_post_types', {
        include_hidden: true
      }).then(function (postTypes) {
        $select.append(jQuery('<option>').val('').text(window.aieData.i18n.selectPostTypePlaceholder || '— Select post type —'));
        if (postTypes && Array.isArray(postTypes)) {
          postTypes.forEach(function (pt) {
            $select.append(jQuery('<option>').val(pt.name).text(pt.label + ' (' + pt.name + ')'));
          });
        }
        $select.on('change', function () {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
            return _this9.refreshCount(false);
          }, 500)();
        });
      })["catch"](function () {
        $select.append(jQuery('<option>').val('').text(window.aieData.i18n.errorLoadingPostTypes || 'Error loading post types'));
      });
      $valueWrap.find('.aie-updater-filter-value').replaceWith($select);
      return;
    }

    // Special handling for taxonomy_selector — replicate export.js behaviour
    if (fieldType === 'taxonomy_selector') {
      $conditionWrap.hide();
      $valueWrap.find('label').text(window.aieData.i18n.selectTaxonomy || 'Select Taxonomy');
      var _$select = jQuery('<select>').addClass('aie-updater-filter-value aie-taxonomy-selector').attr('name', 'updater_filter_value[]');
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].ajax('aie_get_all_taxonomies', {}).then(function (taxonomies) {
        _$select.append(jQuery('<option>').val('').text(window.aieData.i18n.selectTaxonomyPlaceholder || '— Select taxonomy —'));
        if (taxonomies && Array.isArray(taxonomies)) {
          taxonomies.forEach(function (taxonomy) {
            _$select.append(jQuery('<option>').val(taxonomy.name).text(taxonomy.label + ' (' + taxonomy.name + ')'));
          });
        }
        _$select.on('change', function () {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
            return _this9.refreshCount(false);
          }, 500)();
        });
      })["catch"](function () {
        _$select.append(jQuery('<option>').val('').text(window.aieData.i18n.errorLoadingTaxonomies || 'Error loading taxonomies'));
      });
      $valueWrap.find('.aie-updater-filter-value').replaceWith(_$select);
      return;
    }

    // Special handling for taxonomy_filter — replicate export.js behaviour
    if (fieldType === 'taxonomy_filter') {
      $conditionWrap.hide();
      $valueWrap.html("\n\t\t\t\t<div class=\"aie-taxonomy-filter-inputs\">\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>".concat(window.aieData.i18n.taxonomyName || 'Taxonomy Name', "</label>\n\t\t\t\t\t\t<input type=\"text\" class=\"aie-taxonomy-name\" placeholder=\"").concat(window.aieData.i18n.taxonomyPlaceholderExamples, "\" />\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>").concat(window.aieData.i18n.condition || 'Condition', "</label>\n\t\t\t\t\t\t<select class=\"aie-taxonomy-condition aie-updater-filter-condition\">\n\t\t\t\t\t\t\t<option value=\"in\">").concat(window.aieData.i18n.hasTermsIn, "</option>\n\t\t\t\t\t\t\t<option value=\"not_in\">").concat(window.aieData.i18n.doesNotHaveTermsNotIn, "</option>\n\t\t\t\t\t\t\t<option value=\"and\">").concat(window.aieData.i18n.hasAllTermsAnd, "</option>\n\t\t\t\t\t\t</select>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>").concat(window.aieData.i18n.terms || 'Terms', "</label>\n\t\t\t\t\t\t<input type=\"text\" class=\"aie-taxonomy-terms aie-updater-filter-value\" placeholder=\"").concat(window.aieData.i18n.enterTermSlugs, "\" />\n\t\t\t\t\t\t<small class=\"description\">").concat(window.aieData.i18n.enterTermSlugs, "</small>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t"));

      // Trigger count refresh when any taxonomy filter field changes
      $row.find('.aie-taxonomy-name, .aie-taxonomy-condition, .aie-taxonomy-terms').on('input change', function () {
        _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
          return _this9.refreshCount(false);
        }, 500)();
      });
      return;
    }

    // Handle custom_field type — show a text input for the actual meta key name
    if (fieldType === 'custom_field') {
      var label = window.aieData.i18n.customFieldName || 'Meta Key';
      var placeholder = window.aieData.i18n.enterCustomFieldName || 'Enter meta key name…';
      var $metaKeyWrap = jQuery("<div class=\"aie-updater-meta-key-wrap\">\n\t\t\t\t\t<label>".concat(label, "</label>\n\t\t\t\t\t<input type=\"text\" class=\"aie-updater-custom-meta-key\" placeholder=\"").concat(placeholder, "\" />\n\t\t\t\t</div>"));
      $row.find('.aie-filter-row-inner').addClass('has-meta-key');
      $row.find('.aie-filter-field-wrap').after($metaKeyWrap);
      // Refresh count when the meta key name changes
      $metaKeyWrap.find('.aie-updater-custom-meta-key').on('input', function () {
        _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
          return _this9.refreshCount(false);
        }, 500)();
      });
    }

    // Populate condition dropdown based on field type
    $condition.empty();
    var conditions = this.getConditionsByFieldType(fieldType);
    conditions.forEach(function (condition) {
      $condition.append(jQuery('<option>').val(condition.value).text(condition.label));
    });

    // Clear value and update input type based on field type
    $row.find('.aie-updater-filter-value').val('');
    this.updateValueInputType($row);
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
      return _this9.refreshCount(false);
    }, 500)();
  },
  /**
   * Handle filter condition change
   */
  onFilterConditionChange: function onFilterConditionChange(e) {
    var _this10 = this;
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

    // Update input type based on condition and field type
    this.updateValueInputType($row);
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
      return _this10.refreshCount(false);
    }, 500)();
  },
  /**
   * Update value input type based on condition and field type
   */
  updateValueInputType: function updateValueInputType($row) {
    var $field = $row.find('.aie-updater-filter-field');
    var $condition = $row.find('.aie-updater-filter-condition');
    var $value = $row.find('.aie-updater-filter-value');
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
      $value.closest('.aie-filter-value-wrap').show();
    }

    // For 'in' and 'not_in', always use text to allow comma-separated values
    if (condition === 'in' || condition === 'not_in') {
      $value.attr('type', 'text');
      $value.attr('placeholder', window.aieData.i18n.enterValuesCommaSeparated || 'value1, value2, ...');
      return;
    }

    // Set input type based on field type
    if (fieldType === 'date' || fieldType === 'datetime') {
      $value.attr('type', 'date');
      $value.attr('placeholder', '');
    } else if (fieldType === 'number' || fieldType === 'id') {
      $value.attr('type', 'number');
      $value.attr('placeholder', window.aieData.i18n.enterNumberPlaceholder || '');
    } else {
      $value.attr('type', 'text');
      $value.attr('placeholder', window.aieData.i18n.enterFilterValue || '');
    }
  },
  /**
   * Get conditions by field type
   */
  getConditionsByFieldType: function getConditionsByFieldType(fieldType) {
    var stringConditions = [{
      value: 'equals',
      label: window.aieData.i18n.equals
    }, {
      value: 'not_equals',
      label: window.aieData.i18n.notEquals
    }, {
      value: 'in',
      label: window.aieData.i18n.inFilter || 'In (comma-separated)'
    }, {
      value: 'not_in',
      label: window.aieData.i18n.notInFilter || 'Not In (comma-separated)'
    }, {
      value: 'contains',
      label: window.aieData.i18n.contains
    }, {
      value: 'not_contains',
      label: window.aieData.i18n.notContains
    }, {
      value: 'starts_with',
      label: window.aieData.i18n.startsWith
    }, {
      value: 'ends_with',
      label: window.aieData.i18n.endsWith
    }, {
      value: 'is_empty',
      label: window.aieData.i18n.isEmpty
    }, {
      value: 'is_not_empty',
      label: window.aieData.i18n.isNotEmpty
    }];
    var numberConditions = [{
      value: 'equals',
      label: window.aieData.i18n.equals
    }, {
      value: 'not_equals',
      label: window.aieData.i18n.notEquals
    }, {
      value: 'in',
      label: window.aieData.i18n.inFilter || 'In (comma-separated)'
    }, {
      value: 'not_in',
      label: window.aieData.i18n.notInFilter || 'Not In (comma-separated)'
    }, {
      value: 'greater',
      label: window.aieData.i18n.greaterThan
    }, {
      value: 'less',
      label: window.aieData.i18n.lessThan
    }, {
      value: 'equals_or_greater',
      label: window.aieData.i18n.greaterOrEqual
    }, {
      value: 'equals_or_less',
      label: window.aieData.i18n.lessOrEqual
    }, {
      value: 'between',
      label: window.aieData.i18n.between
    }];
    var dateConditions = [{
      value: 'equals',
      label: window.aieData.i18n.equals
    }, {
      value: 'not_equals',
      label: window.aieData.i18n.notEquals
    }, {
      value: 'greater',
      label: window.aieData.i18n.newerThan || window.aieData.i18n.greaterThan
    }, {
      value: 'equals_or_greater',
      label: window.aieData.i18n.greaterOrEqual
    }, {
      value: 'less',
      label: window.aieData.i18n.olderThan || window.aieData.i18n.lessThan
    }, {
      value: 'equals_or_less',
      label: window.aieData.i18n.lessOrEqual
    }, {
      value: 'is_empty',
      label: window.aieData.i18n.isEmpty
    }, {
      value: 'is_not_empty',
      label: window.aieData.i18n.isNotEmpty
    }];
    switch (fieldType) {
      case 'number':
      case 'id':
        return numberConditions;
      case 'date':
      case 'datetime':
        return dateConditions;
      case 'custom_field':
        return [{
          value: 'equals',
          label: window.aieData.i18n.equals
        }, {
          value: 'not_equals',
          label: window.aieData.i18n.notEquals
        }, {
          value: 'contains',
          label: window.aieData.i18n.contains
        }, {
          value: 'not_contains',
          label: window.aieData.i18n.notContains
        }, {
          value: 'starts_with',
          label: window.aieData.i18n.startsWith
        }, {
          value: 'ends_with',
          label: window.aieData.i18n.endsWith
        }, {
          value: 'greater',
          label: window.aieData.i18n.greaterThan
        }, {
          value: 'less',
          label: window.aieData.i18n.lessThan
        }, {
          value: 'equals_or_greater',
          label: window.aieData.i18n.greaterOrEqual
        }, {
          value: 'equals_or_less',
          label: window.aieData.i18n.lessOrEqual
        }, {
          value: 'in',
          label: window.aieData.i18n.inComma || 'In (comma-separated)'
        }, {
          value: 'not_in',
          label: window.aieData.i18n.notInComma || 'Not In (comma-separated)'
        }, {
          value: 'is_empty',
          label: window.aieData.i18n.isEmpty
        }, {
          value: 'is_not_empty',
          label: window.aieData.i18n.isNotEmpty
        }];
      default:
        return stringConditions;
    }
  },
  /**
   * Refresh item count
   */
  refreshCount: function refreshCount() {
    var _this11 = this;
    var showSpinner = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : true;
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();
    if (!contentType) {
      return;
    }
    if (this.isDatabaseTableType(contentType) && !this.getSelectedTableName()) {
      jQuery('.aie-count-value').text('-');
      this.filteredCount = null;
      return;
    }
    var $countValue = jQuery('.aie-count-value');
    var $spinner = jQuery('.aie-filter-summary-top .spinner');
    if (showSpinner) {
      $spinner.addClass('is-active');
    }

    // Collect filters
    var collectedFilters = this.collectFilters();
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_updater_get_count',
        nonce: aieData.nonce,
        content_type: contentType,
        filters: JSON.stringify(collectedFilters.filters),
        taxonomy: JSON.stringify(collectedFilters.taxonomy),
        options: this.buildRequestOptions(contentType)
      },
      success: function success(response) {
        $spinner.removeClass('is-active');
        if (response.success) {
          $countValue.text(response.data.count);
          // Save the filtered count for later use
          _this11.filteredCount = response.data.count;
        } else {
          $countValue.text(window.aieData.i18n.error);
        }
        // Update Step 2 Next button state for types that require filters
        _this11.updateStep2NextButton();
      },
      error: function error() {
        $spinner.removeClass('is-active');
        $countValue.text(window.aieData.i18n.error);
      }
    });
  },
  /**
   * Collect filters from UI
   *
   * @return {{ filters: Array, taxonomy: Array }}
   */
  collectFilters: function collectFilters() {
    var filters = [];
    var taxonomyFilters = [];
    jQuery('.aie-filter-row').each(function () {
      var $row = jQuery(this);
      var $fieldSelect = $row.find('.aie-updater-filter-field');
      var field = $fieldSelect.val();
      var fieldType = $fieldSelect.find('option:selected').data('type');

      // Handle post_type_selector type — map _post_type → post_type filter
      if (fieldType === 'post_type_selector') {
        var _value = ($row.find('.aie-updater-filter-value').val() || '').trim();
        if (_value) {
          filters.push({
            field: 'post_type',
            condition: 'equals',
            value: _value
          });
        }
        return;
      }

      // Handle taxonomy_selector type — map _taxonomy → taxonomy filter
      if (fieldType === 'taxonomy_selector') {
        var _value2 = ($row.find('.aie-updater-filter-value').val() || '').trim();
        if (_value2) {
          filters.push({
            field: 'taxonomy',
            condition: 'equals',
            value: _value2
          });
        }
        return;
      }

      // Handle taxonomy_filter type — collect into taxonomy array
      if (fieldType === 'taxonomy_filter') {
        var taxonomy = ($row.find('.aie-taxonomy-name').val() || '').trim();
        var _condition = $row.find('.aie-taxonomy-condition').val();
        var terms = ($row.find('.aie-taxonomy-terms').val() || '').trim();
        if (taxonomy && _condition && terms) {
          taxonomyFilters.push({
            taxonomy: taxonomy,
            condition: _condition,
            terms: terms
          });
        }
        return; // skip regular filter processing
      }
      var condition = $row.find('.aie-updater-filter-condition').val();
      var value = $row.find('.aie-updater-filter-value').val();

      // When the "Custom Field / Meta" placeholder is selected, use the
      // actual meta key that the user typed into the extra input.
      if (field === '_custom_field') {
        field = ($row.find('.aie-updater-custom-meta-key').val() || '').trim();
        if (!field) {
          return; // Skip this row — no meta key entered yet
        }
      }

      // Normalize date values to YYYY-MM-DD so the backend SQL comparison works
      // regardless of the locale format the datepicker uses (e.g. 03/27/2026 → 2026-03-27)
      if ((fieldType === 'date' || fieldType === 'datetime') && value) {
        var parsed = new Date(value);
        if (!isNaN(parsed.getTime())) {
          value = parsed.toISOString().slice(0, 10);
        }
      }
      if (field && condition) {
        filters.push({
          field: field,
          condition: condition,
          value: value
        });
      }
    });
    return {
      filters: filters,
      taxonomy: taxonomyFilters
    };
  },
  /**
   * Load fields library for selected content type
   */
  loadFieldsLibrary: function loadFieldsLibrary() {
    // Get selected content type
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();
    if (!contentType) {
      return;
    }

    // Load static fields based on content type
    this.loadStaticFields(contentType);

    // Load dynamic fields for this content type (excluding taxonomies for Content Updater)
    var postType = this.getPostTypeForDynamicFields(contentType);
    if (postType) {
      // Skip taxonomies for Content Updater
      // this.loadTaxonomies( postType );
      if (contentType !== 'media') {
        this.loadCustomFields(postType);
      }
      if (contentType !== 'media') {
        this.checkAndLoadACF(postType);
      }
      if (contentType !== 'user' && contentType !== 'media') {
        this.checkAndLoadYoast(postType);
      }
    }
  },
  /**
   * Load static fields based on content type
   */
  loadStaticFields: function loadStaticFields(contentType) {
    var _this12 = this;
    // Get field definitions from export module
    if (typeof window.aieExportModule === 'undefined' || !window.aieExportModule.getFieldsByContentType) {
      return;
    }
    var fieldGroups = window.aieExportModule.getFieldsByContentType(contentType);

    // Find the container
    var $library = jQuery('#aie-updater-fields-library');
    if (!$library.length) {
      return;
    }

    // Clear existing content
    $library.empty();

    // Add container for fields
    $library.append('<div class="aie-fields-library-body"></div>');
    var $body = $library.find('.aie-fields-library-body');

    // Groups / fields to exclude for specific content types
    var userExcludedGroups = [window.aieData.i18n.fieldGroupPreferences, window.aieData.i18n.fieldGroupStats];
    var userExcludedFields = ['capabilities', 'user_login'];
    var commentExcludedFields = ['post_title'];

    // Render each field group as a category
    fieldGroups.forEach(function (group, index) {
      // Skip Custom Filters, selector groups, Taxonomy and Author categories for Content Updater
      if (group.label === 'Custom Filters' || group.label === 'Post Type Selection' || group.label === 'Taxonomy Selection' || group.label === 'Taxonomy' || group.label === 'Author') {
        return;
      }

      // For user content type: skip excluded groups
      if (contentType === 'user' && userExcludedGroups.includes(group.label)) {
        return;
      }

      // For user content type: filter out excluded fields within a group
      var renderGroup = group;
      if (contentType === 'user' && userExcludedFields.length) {
        renderGroup = _objectSpread(_objectSpread({}, group), {}, {
          options: group.options.filter(function (opt) {
            return !userExcludedFields.includes(opt.value);
          })
        });
        if (renderGroup.options.length === 0) {
          return;
        }
      }

      // For comment content type: filter out excluded fields within a group
      if (contentType === 'comment' && commentExcludedFields.length && Array.isArray(renderGroup.options)) {
        renderGroup = _objectSpread(_objectSpread({}, renderGroup), {}, {
          options: renderGroup.options.filter(function (opt) {
            return !commentExcludedFields.includes(opt.value);
          })
        });
        if (renderGroup.options.length === 0) {
          return;
        }
      }

      // For taxonomy content type: hide computed fields that should not be updated directly
      if (contentType === 'taxonomy' && Array.isArray(renderGroup.options)) {
        renderGroup = _objectSpread(_objectSpread({}, renderGroup), {}, {
          options: renderGroup.options.filter(function (opt) {
            return opt.value !== 'count';
          })
        });
        if (renderGroup.options.length === 0) {
          return;
        }
      }
      var $category = _this12.createFieldCategory(renderGroup, index === 0);
      $body.append($category);
    });

    // Add placeholder categories for dynamic fields (excluding Taxonomies)
    $body.append("\n\t\t\t<div class=\"aie-field-category aie-collapsed aie-custom-fields-category\" style=\"display: none;\">\n\t\t\t\t<h4 class=\"aie-field-category-title\">\n\t\t\t\t\t<span class=\"dashicons dashicons-arrow-down-alt2 aie-category-toggle\"></span>\n\t\t\t\t\t<span class=\"dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\tCustom Fields\n\t\t\t\t\t<button type=\"button\" class=\"aie-add-all-fields\" title=\"".concat(window.aieData.i18n.addAllFieldsTitle, "\">\n\t\t\t\t\t\t").concat(window.aieData.i18n.addAll, "\n\t\t\t\t\t</button>\n\t\t\t\t</h4>\n\t\t\t\t<div class=\"aie-fields-grid aie-custom-fields-grid\"></div>\n\t\t\t</div>\n\t\t\t<div class=\"aie-field-category aie-collapsed aie-acf-fields-category\" style=\"display: none;\">\n\t\t\t\t<h4 class=\"aie-field-category-title\">\n\t\t\t\t\t<span class=\"dashicons dashicons-arrow-down-alt2 aie-category-toggle\"></span>\n\t\t\t\t\t<span class=\"dashicons dashicons-admin-settings\"></span>\n\t\t\t\t\tACF Fields\n\t\t\t\t\t<button type=\"button\" class=\"aie-add-all-fields\" title=\"").concat(window.aieData.i18n.addAllFieldsTitle, "\">\n\t\t\t\t\t\t").concat(window.aieData.i18n.addAll, "\n\t\t\t\t\t</button>\n\t\t\t\t</h4>\n\t\t\t\t<div class=\"aie-fields-grid aie-acf-fields-grid\">\n\t\t\t\t\t<div class=\"aie-acf-loading\"><span class=\"spinner is-active\"></span><p>Loading ACF fields...</p></div>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t\t<div class=\"aie-field-category aie-collapsed aie-yoast-fields-category\" style=\"display: none;\">\n\t\t\t\t<h4 class=\"aie-field-category-title\">\n\t\t\t\t\t<span class=\"dashicons dashicons-arrow-down-alt2 aie-category-toggle\"></span>\n\t\t\t\t\t<span class=\"dashicons dashicons-chart-line\"></span>\n\t\t\t\t\tYoast SEO\n\t\t\t\t\t<button type=\"button\" class=\"aie-add-all-fields\" title=\"").concat(window.aieData.i18n.addAllFieldsTitle, "\">\n\t\t\t\t\t\t").concat(window.aieData.i18n.addAll, "\n\t\t\t\t\t</button>\n\t\t\t\t</h4>\n\t\t\t\t<div class=\"aie-fields-grid aie-yoast-fields-grid\">\n\t\t\t\t\t<div class=\"aie-yoast-loading\"><span class=\"spinner is-active\"></span><p>Loading Yoast SEO fields...</p></div>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t"));

    // Setup drag and drop
    this.setupFieldsDragAndDrop();

    // Setup category toggle
    this.setupCategoryToggle();
  },
  /**
   * Create a field category element
   */
  createFieldCategory: function createFieldCategory(group) {
    var _this13 = this;
    var isOpen = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;
    var $category = jQuery('<div>').addClass('aie-field-category');
    if (!isOpen) {
      $category.addClass('aie-collapsed');
    }
    var $title = jQuery('<h4>').addClass('aie-field-category-title').html("\n\t\t\t<span class=\"dashicons dashicons-arrow-down-alt2 aie-category-toggle\"></span>\n\t\t\t<span class=\"dashicons dashicons-admin-post\"></span>\n\t\t\t".concat(this.escapeHtml(group.label), "\n\t\t\t<button type=\"button\" class=\"aie-add-all-fields\" title=\"").concat(window.aieData.i18n.addAllFieldsTitle, "\">\n\t\t\t\t").concat(window.aieData.i18n.addAll, "\n\t\t\t</button>\n\t\t"));
    var $grid = jQuery('<div>').addClass('aie-fields-grid');

    // Add fields
    if (group.options && Array.isArray(group.options)) {
      // ID fields that must not be editable in the Content Updater
      var idFields = ['ID', 'comment_ID', 'term_id'];
      group.options.forEach(function (option) {
        // Skip special filter types
        if (option.type === 'custom_field' || option.type === 'taxonomy_filter' || option.type === 'post_type_selector' || option.type === 'taxonomy_selector' || option.type === 'table_selector') {
          return;
        }

        // Skip ID fields — record IDs must not be updated
        if (idFields.includes(option.value)) {
          return;
        }
        var $field = _this13.createFieldItem(option);
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
    var _this14 = this;
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
        if (response.success && response.data.taxonomies && response.data.taxonomies.length > 0) {
          _this14.renderTaxonomies(response.data.taxonomies);
          jQuery('.aie-taxonomies-category').show();
        } else {
          jQuery('.aie-taxonomies-category').hide();
        }
      },
      error: function error(xhr, status, _error) {}
    });
  },
  /**
   * Render taxonomies
   */
  renderTaxonomies: function renderTaxonomies(taxonomies) {
    var _this15 = this;
    var $grid = jQuery('.aie-taxonomies-grid');
    if (!$grid.length) return;
    $grid.empty();
    taxonomies.forEach(function (taxonomy) {
      var $item = jQuery('<div>').addClass('aie-field-item').attr('draggable', true).attr('data-field', 'taxonomy_' + taxonomy.name).attr('data-label', taxonomy.label).attr('data-type', 'taxonomy').html("\n\t\t\t\t\t<span class=\"aie-field-icon dashicons dashicons-category\"></span>\n\t\t\t\t\t<span class=\"aie-field-label\">".concat(_this15.escapeHtml(taxonomy.label), "</span>\n\t\t\t\t\t<span class=\"aie-field-type\">taxonomy</span>\n\t\t\t\t"));
      $grid.append($item);
    });
  },
  /**
   * Get real post type for loading dynamic fields (ACF/Yoast/Custom fields)
   *
   * @param {string} contentType
   * @return {string|null}
   */
  getPostTypeForDynamicFields: function getPostTypeForDynamicFields(contentType) {
    if (contentType === 'post' || contentType === 'page') {
      return contentType;
    }
    if (contentType === 'custom_post_types') {
      var $ptSelector = jQuery('.aie-post-type-selector');
      return $ptSelector.length ? $ptSelector.val() || null : null;
    }
    var typeMap = {
      woo_product: 'product',
      woo_order: 'shop_order',
      woo_coupon: 'shop_coupon',
      media: 'attachment',
      user: 'user'
    };
    if (typeMap[contentType]) {
      return typeMap[contentType];
    }
    if (contentType.startsWith('post_type_')) {
      return contentType.replace('post_type_', '');
    }
    return null;
  },
  /**
   * Load custom fields for selected post type
   */
  loadCustomFields: function loadCustomFields(postType) {
    var _this16 = this;
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
        if (response.success && response.data.fields && response.data.fields.length > 0) {
          _this16.renderCustomFields(response.data.fields);
          jQuery('.aie-custom-fields-category').show();
        } else {
          jQuery('.aie-custom-fields-category').hide();
        }
      },
      error: function error(xhr, status, _error2) {}
    });
  },
  /**
   * Render custom fields
   */
  renderCustomFields: function renderCustomFields(fields) {
    var _this17 = this;
    var $grid = jQuery('.aie-custom-fields-grid');
    if (!$grid.length) return;
    $grid.empty();
    fields.forEach(function (field) {
      var $item = jQuery('<div>').addClass('aie-field-item').attr('draggable', true).attr('data-field', 'meta_' + field.name).attr('data-label', field.name).attr('data-type', 'meta').html("\n\t\t\t\t\t<span class=\"aie-field-icon dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\t<span class=\"aie-field-label\">".concat(_this17.escapeHtml(field.name), "</span>\n\t\t\t\t\t<span class=\"aie-field-type\">meta</span>\n\t\t\t\t"));
      $grid.append($item);
    });
  },
  /**
   * Check if ACF is active and load ACF fields
   */
  checkAndLoadACF: function checkAndLoadACF(postType) {
    var _this18 = this;
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
        if (response.success && response.data.fields && response.data.fields.length > 0) {
          _this18.renderACFFields(response.data.fields);
          jQuery('.aie-acf-fields-category').show();
        } else {
          jQuery('.aie-acf-fields-category').hide();
        }
      },
      error: function error(xhr, status, _error3) {}
    });
  },
  /**
   * Render ACF fields
   */
  renderACFFields: function renderACFFields(fields) {
    var _this19 = this;
    var $grid = jQuery('.aie-acf-fields-grid');
    if (!$grid.length) return;
    $grid.empty();
    fields.forEach(function (field) {
      var $item = jQuery('<div>').addClass('aie-field-item').attr('draggable', true).attr('data-field', 'acf_' + field.name).attr('data-label', field.label).attr('data-type', 'acf').html("\n\t\t\t\t\t<span class=\"aie-field-icon dashicons dashicons-admin-settings\"></span>\n\t\t\t\t\t<span class=\"aie-field-label\">".concat(_this19.escapeHtml(field.label), "</span>\n\t\t\t\t\t<span class=\"aie-field-type\">acf</span>\n\t\t\t\t"));
      $grid.append($item);
    });
  },
  /**
   * Check if Yoast is active and load Yoast fields
   */
  checkAndLoadYoast: function checkAndLoadYoast(postType) {
    var _this20 = this;
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
        if (response.success && response.data.fields && response.data.fields.length > 0) {
          _this20.renderYoastFields(response.data.fields);
          jQuery('.aie-yoast-fields-category').show();
        } else {
          jQuery('.aie-yoast-fields-category').hide();
        }
      },
      error: function error(xhr, status, _error4) {}
    });
  },
  /**
   * Render Yoast fields
   */
  renderYoastFields: function renderYoastFields(fields) {
    var _this21 = this;
    var $grid = jQuery('.aie-yoast-fields-grid');
    if (!$grid.length) return;
    $grid.empty();
    fields.forEach(function (field) {
      var $item = jQuery('<div>').addClass('aie-field-item').attr('draggable', true).attr('data-field', 'yoast_' + field.name).attr('data-label', field.label).attr('data-type', 'yoast').html("\n\t\t\t\t\t<span class=\"aie-field-icon dashicons dashicons-chart-line\"></span>\n\t\t\t\t\t<span class=\"aie-field-label\">".concat(_this21.escapeHtml(field.label), "</span>\n\t\t\t\t\t<span class=\"aie-field-type\">yoast</span>\n\t\t\t\t"));
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
   * Setup drag and drop handlers for field items.
   *
   * Uses event delegation on the library container so that items added
   * asynchronously (ACF, Yoast, custom fields loaded via AJAX after the
   * initial render) are automatically covered without needing to re-bind.
   */
  setupFieldsDragAndDrop: function setupFieldsDragAndDrop() {
    var _this22 = this;
    var $library = jQuery('#aie-updater-fields-library');
    var $dropzone = jQuery('#aie-updater-dropzone');

    // Remove any previously-attached delegated handlers to avoid duplicates
    // when the library is rebuilt (e.g. content-type change).
    $library.off('dragstart.aie-dnd click.aie-dnd');

    // Delegated dragstart — fires for every .aie-field-item inside the library,
    // including ones added later by renderACFFields / renderYoastFields / renderCustomFields.
    $library.on('dragstart.aie-dnd', '.aie-field-item', function (e) {
      var $item = jQuery(e.currentTarget);
      var field = $item.data('field');
      var type = $item.data('type') || 'text';
      e.originalEvent.dataTransfer.setData('field', field);
      e.originalEvent.dataTransfer.setData('label', $item.find('.aie-field-label').text());
      e.originalEvent.dataTransfer.setData('type', type);
    });

    // Delegated click — click-to-add also works for dynamic items.
    $library.on('click.aie-dnd', '.aie-field-item', function (e) {
      var $item = jQuery(e.currentTarget);
      _this22.addField($item.data('field'), $item.find('.aie-field-label').text(), $item.data('type') || 'text');
    });

    // Dropzone handlers — also remove before re-adding to prevent duplicate fires.
    $dropzone.off('dragover.aie-dnd dragleave.aie-dnd drop.aie-dnd');
    $dropzone.on('dragover.aie-dnd', function (e) {
      e.preventDefault();
      $dropzone.addClass('aie-drag-over');
    });
    $dropzone.on('dragleave.aie-dnd', function () {
      $dropzone.removeClass('aie-drag-over');
    });
    $dropzone.on('drop.aie-dnd', function (e) {
      e.preventDefault();
      $dropzone.removeClass('aie-drag-over');
      var field = e.originalEvent.dataTransfer.getData('field');
      var label = e.originalEvent.dataTransfer.getData('label');
      var type = e.originalEvent.dataTransfer.getData('type') || 'text';
      if (field) {
        _this22.addField(field, label, type);
      }
    });
  },
  /**
   * Add field to selected fields list
   */
  addField: function addField(field, label) {
    var _this23 = this;
    var type = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : 'text';
    // Check if already added
    if (this.selectedFields.includes(field)) {
      var message = window.aieData.i18n.fieldAlreadySelected.replace('%s', label);
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(message, 'warning');
      return;
    }
    this.selectedFields.push(field);
    this.selectedFieldTypes[field] = String(type || 'text').toLowerCase();
    var $list = jQuery('#aie-updater-fields-list');
    var $placeholder = jQuery('.aie-updater-dropzone-placeholder');
    $placeholder.hide();
    var fieldHtml = "\n\t\t\t<div class=\"aie-selected-field\" data-field=\"".concat(field, "\" data-type=\"").concat(this.escapeHtml(String(type || 'text').toLowerCase()), "\">\n\t\t\t\t<span class=\"aie-field-drag-handle dashicons dashicons-menu\"></span>\n\t\t\t\t<span class=\"aie-field-name\">").concat(label, "</span>\n\t\t\t\t<button type=\"button\" class=\"aie-remove-field\" title=\"Remove\">\n\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t</button>\n\t\t\t</div>\n\t\t");
    $list.append(fieldHtml);
    this.updateFieldCount();

    // Bind remove handler
    $list.find('.aie-remove-field').last().on('click', function (e) {
      _this23.removeField(jQuery(e.currentTarget).closest('.aie-selected-field').data('field'));
    });
  },
  /**
   * Add all fields from a category
   */
  addAllFieldsFromCategory: function addAllFieldsFromCategory(button) {
    var _this24 = this;
    var $category = jQuery(button).closest('.aie-field-category');
    if (!$category.length) {
      return;
    }
    var $fieldItems = $category.find('.aie-field-item:not([style*="display: none"])');
    $fieldItems.each(function (index, item) {
      var $item = jQuery(item);
      var field = String($item.data('field') || '');
      var label = String($item.data('label') || field);
      var type = String($item.data('type') || 'text');
      if (field) {
        _this24.addField(field, label, type);
      }
    });
  },
  /**
   * Remove field from selected fields
   */
  removeField: function removeField(field) {
    var index = this.selectedFields.indexOf(field);
    if (index > -1) {
      this.selectedFields.splice(index, 1);
      delete this.selectedFieldTypes[field];
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
    if (!confirm(window.aieData.i18n.confirmClearFields)) {
      return;
    }
    this.selectedFields = [];
    this.selectedFieldTypes = {};
    this.fieldFunctions = {};
    jQuery('#aie-updater-fields-list').empty();
    jQuery('.aie-updater-dropzone-placeholder').show();
    this.updateFieldCount();
  },
  /**
   * Get normalized field type key
   */
  getFieldTypeKey: function getFieldTypeKey(field) {
    if (this.selectedFieldTypes[field]) {
      return String(this.selectedFieldTypes[field]).toLowerCase();
    }
    var domType = jQuery(".aie-selected-field[data-field=\"".concat(field, "\"]")).data('type');
    return String(domType || 'text').toLowerCase();
  },
  /**
   * Get display label for field type
   */
  getFieldTypeLabel: function getFieldTypeLabel(field) {
    return this.getFieldTypeKey(field).replace(/_/g, ' ').toUpperCase();
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
    var _this25 = this;
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
  },
  /**
   * Build functions assignment table
   */
  buildFunctionsTable: function buildFunctionsTable() {
    var _this26 = this;
    var $tbody = jQuery('#aie-updater-functions-tbody');
    $tbody.empty();
    if (this.selectedFields.length === 0) {
      $tbody.html("\n\t\t\t\t<tr class=\"aie-no-fields-row\">\n\t\t\t\t\t<td colspan=\"4\" class=\"aie-no-fields-message\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t\t\t".concat(window.aieData.i18n.noFieldsSelected, "\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t"));
      return;
    }
    this.selectedFields.forEach(function (field, index) {
      var functions = _this26.fieldFunctions[field] || [];
      var functionsCount = Array.isArray(functions) ? functions.length : 0;
      var fieldLabel = jQuery(".aie-selected-field[data-field=\"".concat(field, "\"] .aie-field-name")).text() || field;
      var fieldTypeLabel = _this26.getFieldTypeLabel(field);
      var functionsText = 'None';
      if (functionsCount > 0) {
        functionsText = "".concat(functionsCount, " function").concat(functionsCount > 1 ? 's' : '');
      }
      var html = "\n\t\t\t\t<tr data-field=\"".concat(field, "\">\n\t\t\t\t\t<td class=\"aie-field-name-col\">\n\t\t\t\t\t\t<strong>").concat(_this26.escapeHtml(fieldLabel), "</strong>\n\t\t\t\t\t\t<br><code>").concat(_this26.escapeHtml(field), "</code>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"aie-field-type-col\">\n\t\t\t\t\t\t<span class=\"aie-field-type-badge\">").concat(_this26.escapeHtml(fieldTypeLabel), "</span>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"aie-functions-col\">\n\t\t\t\t\t\t<span class=\"aie-functions-count-badge\">").concat(functionsText, "</span>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td class=\"aie-actions-col\">\n\t\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-assign-functions\" data-field=\"").concat(field, "\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\t\t\t").concat(window.aieData.i18n.assignFunctions, "\n\t\t\t\t\t\t</button>\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t");
      $tbody.append(html);
    });

    // Bind assign functions button
    $tbody.find('.aie-assign-functions').on('click', function (e) {
      var field = jQuery(e.currentTarget).data('field');
      _this26.openFieldFunctionsModal(field);
    });
    this.updateFunctionStats();
  },
  /**
   * Open field functions modal
   */
  openFieldFunctionsModal: function openFieldFunctionsModal(fieldKey) {
    var fieldLabel = jQuery(".aie-selected-field[data-field=\"".concat(fieldKey, "\"] .aie-field-name")).text() || fieldKey;
    var fieldType = this.getFieldTypeLabel(fieldKey);
    this.currentEditingField = fieldKey;
    var $modal = jQuery('#aie-updater-functions-modal');
    if (!$modal.length) {
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
    var _this27 = this;
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
      var func = _this27.availableFunctions.find(function (f) {
        return f.id == funcId;
      });
      if (func) {
        _this27.addFunctionToPipeline(func, false);
      }
    });
    this.updateFunctionsCount(functions.length);
  },
  /**
   * Add function to pipeline
   */
  addFunctionToPipeline: function addFunctionToPipeline(func) {
    var _this28 = this;
    var updateArray = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : true;
    var $container = jQuery('#aie-updater-function-items');
    if (!$container.length) return;
    var $item = jQuery('<div>').addClass('aie-function-item').attr('data-function-id', func.id).html("\n\t\t\t\t<span class=\"aie-function-handle dashicons dashicons-menu\"></span>\n\t\t\t\t<div class=\"aie-function-info\">\n\t\t\t\t\t<strong class=\"aie-function-name\">".concat(this.escapeHtml(func.name), "</strong>\n\t\t\t\t\t<span class=\"aie-function-desc\">").concat(this.escapeHtml(func.description || ''), "</span>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-function-actions\">\n\t\t\t\t\t<button type=\"button\" class=\"button-small aie-remove-function\" data-function-id=\"").concat(func.id, "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t</div>\n\t\t\t"));

    // Remove function event
    $item.find('.aie-remove-function').on('click', function () {
      $item.remove();
      _this28.updatePipelineFunctions();
      _this28.updateFunctionsCount();
      _this28.toggleNoFunctionsMessage();
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
    var _this29 = this;
    var $list = jQuery('#aie-updater-functions-list');
    if (!$list.length) return;
    $list.empty();
    if (this.availableFunctions.length === 0) {
      $list.html("\n\t\t\t\t<div class=\"aie-no-functions-available\">\n\t\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t\t<p>".concat(window.aieData.i18n.noFunctionsAvailable, "</p>\n\t\t\t\t</div>\n\t\t\t"));
      return;
    }
    this.availableFunctions.forEach(function (func) {
      var $funcItem = jQuery('<div>').addClass('aie-function-list-item').attr('data-function-id', func.id).attr('data-category', func.category || 'custom').html("\n\t\t\t\t\t<div class=\"aie-function-list-info\">\n\t\t\t\t\t\t<strong class=\"aie-function-list-name\">".concat(_this29.escapeHtml(func.name), "</strong>\n\t\t\t\t\t\t<span class=\"aie-function-list-desc\">").concat(_this29.escapeHtml(func.description || ''), "</span>\n\t\t\t\t\t</div>\n\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-add-function-btn\" data-function-id=\"").concat(func.id, "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-plus-alt\"></span>\n\t\t\t\t\t\t").concat(window.aieData.i18n.add, "\n\t\t\t\t\t</button>\n\t\t\t\t"));

      // Add function event
      $funcItem.find('.aie-add-function-btn').on('click', function () {
        _this29.addFunctionToPipeline(func, true);
      });
      $list.append($funcItem);
    });
  },
  /**
   * Initialize function pipeline sortable
   */
  initFunctionPipelineSortable: function initFunctionPipelineSortable() {
    var _this30 = this;
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
        _this30.updatePipelineFunctions();
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
    var _this31 = this;
    // Show a dialog to select the function
    var functionId = prompt('Enter function ID to apply to all fields (or leave empty for none):');
    if (functionId === null) {
      return; // Cancelled
    }
    var finalId = functionId.trim() || 'none';
    this.selectedFields.forEach(function (field) {
      _this31.fieldFunctions[field] = finalId;
      jQuery(".aie-field-function-select[data-field=\"".concat(field, "\"]")).val(finalId);
    });
    this.updateFunctionStats();
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('success', 'Function applied to all fields');
  },
  /**
   * Clear all function assignments
   */
  clearAllFunctions: function clearAllFunctions() {
    var _this32 = this;
    if (!confirm(window.aieData.i18n.confirmClearFunctions)) {
      return;
    }
    this.selectedFields.forEach(function (field) {
      _this32.fieldFunctions[field] = [];
      var $row = jQuery("tr[data-field=\"".concat(field, "\"]"));
      $row.find('.aie-updater-field-functions').empty();
    });
    this.buildFunctionsTable();
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.functionAssignmentsCleared, 'success');
  },
  /**
   * Test function with sample input
   */
  testFunction: function testFunction(e) {
    var field = jQuery(e.currentTarget).data('field');
    var functionId = this.fieldFunctions[field];
    if (!functionId || functionId === 'none') {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.noFunctionAssigned, 'warning');
      return;
    }

    // Show preview popup (simplified for now)
    var testValue = prompt(window.aieData.i18n.enterTestValue);
    if (testValue === null) {
      return;
    }
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
          var outputText = window.aieData.i18n.functionOutput.replace('%s', response.data.output);
          alert(outputText);
        } else {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(response.data.message || window.aieData.i18n.functionTestFailed, 'error');
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
    var _this33 = this;
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();
    var $countValue = jQuery('.aie-total-items-summary');
    if (this.isDatabaseTableType(contentType) && !this.getSelectedTableName()) {
      $countValue.text('-');
      return;
    }
    $countValue.html('<span class="spinner" style="float:none;margin:0;"></span>');

    // Include filters in the count request
    var filters = this.selectedFilters || [];
    var taxonomy = this.selectedTaxonomyFilters || [];
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: {
        action: 'aie_updater_get_count',
        nonce: aieData.nonce,
        content_type: contentType,
        filters: JSON.stringify(filters),
        taxonomy: JSON.stringify(taxonomy),
        options: this.buildRequestOptions(contentType)
      },
      success: function success(response) {
        if (response.success) {
          $countValue.text(response.data.count);
          // Save the filtered count
          _this33.filteredCount = response.data.count;
        } else {
          $countValue.text(window.aieData.i18n.error);
        }
      },
      error: function error() {
        $countValue.text(window.aieData.i18n.error);
      }
    });
  },
  /**
   * Start update process
   */
  startUpdate: function startUpdate() {
    var _this34 = this;
    var contentType = jQuery('input[name="updater_content_type"]:checked').val();
    var itemsPerIteration = parseInt(jQuery('#aie-updater-items-per-iteration').val()) || 10;

    // Validate fields and functions
    if (!this.selectedFields || this.selectedFields.length === 0) {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.noFieldsSelectedError, 'error');
      return;
    }
    if (!this.fieldFunctions || Object.keys(this.fieldFunctions).length === 0) {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.noFunctionsAssigned, 'error');
      return;
    }

    // Show backup warning modal before starting update
    _BackupWarningModal__WEBPACK_IMPORTED_MODULE_1__["default"].show(function () {
      // User confirmed backup - proceed with update
      _this34.executeUpdate(contentType, itemsPerIteration);
    }, function () {
      // User cancelled - do nothing
    });
  },
  /**
   * Execute update process (after backup confirmation)
   */
  executeUpdate: function executeUpdate(contentType, itemsPerIteration) {
    var _this35 = this;
    // Prepare field functions array (indexed by field position)
    var fieldFunctionsArray = this.selectedFields.map(function (field) {
      return _this35.fieldFunctions[field] || [];
    });
    var options = this.buildRequestOptions(contentType, {
      items_per_iteration: itemsPerIteration
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
        taxonomy: JSON.stringify(this.selectedTaxonomyFilters || []),
        options: JSON.stringify(options)
      },
      success: function success(response) {
        if (response.success) {
          _this35.jobId = response.data.job_id;
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.updateStarted, 'success');

          // Start processing
          _this35.startProgressTracking();
          _this35.processNextBatch();
        } else {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(response.data.message || window.aieData.i18n.failedStartUpdate, 'error');
          _this35.showResults('error');
        }
      },
      error: function error() {
        _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.failedStartUpdate, 'error');
        _this35.showResults('error');
      }
    });
  },
  /**
   * Start progress tracking
   */
  startProgressTracking: function startProgressTracking() {
    var _this36 = this;
    this.progressInterval = setInterval(function () {
      _this36.updateProgress();
    }, 2000);
  },
  /**
   * Update progress display
   */
  updateProgress: function updateProgress() {
    var _this37 = this;
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
          var processingText = window.aieData.i18n.processingItems.replace('%1$s', progress.processed_items).replace('%2$s', progress.total_items);
          jQuery('.aie-status-text').text(processingText);

          // Check if completed
          if (progress.status === 'completed' || progress.status === 'failed' || progress.status === 'cancelled') {
            _this37.stopProgressTracking();
            _this37.showResults(progress.status, progress);
          }
        }
      }
    });
  },
  /**
   * Process next batch
   */
  processNextBatch: function processNextBatch() {
    var _this38 = this;
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
              return _this38.processNextBatch();
            }, 500);
          }
        } else {}
      },
      error: function error(xhr) {}
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
    var _this39 = this;
    if (!confirm(window.aieData.i18n.confirmCancelUpdate)) {
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
          _this39.stopProgressTracking();
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.updateCancelled, 'info');
          _this39.showResults('cancelled');
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
    var _this40 = this;
    var $input = jQuery('#aie-updater-preview-input');
    var testValue = $input.val();
    if (!testValue) {
      this.showNotice(window.aieData.i18n.pleaseEnterTestValue, 'warning');
      return;
    }
    var functionIds = [];
    jQuery('.aie-function-item').each(function () {
      functionIds.push(jQuery(this).data('function-id'));
    });
    if (functionIds.length === 0) {
      this.showNotice(window.aieData.i18n.noFunctionsToTest, 'warning');
      return;
    }

    // Check if aieData is available
    if (typeof aieData === 'undefined') {
      this.showNotice(window.aieData.i18n.configurationError, 'error');
      return;
    }
    var requestData = {
      action: 'aie_test_function_pipeline',
      nonce: aieData.nonce,
      functions: functionIds,
      value: testValue
    };
    jQuery.ajax({
      url: aieData.ajaxUrl,
      method: 'POST',
      data: requestData,
      success: function success(response) {
        if (response.success) {
          _this40.renderPipelinePreview(testValue, response.data.steps);
        } else {
          _this40.showNotice(response.data.message || window.aieData.i18n.testFailed, 'error');
        }
      },
      error: function error(xhr, status, _error5) {
        _this40.showNotice(window.aieData.i18n.errorTestingPipeline, 'error');
      }
    });
  },
  /**
   * Render pipeline preview
   */
  renderPipelinePreview: function renderPipelinePreview(initialValue, steps) {
    var _this41 = this;
    var $container = jQuery('#aie-updater-preview-result');
    if (!$container.length) return;
    var $stepsContainer = $container.find('.aie-preview-steps');
    $stepsContainer.empty();

    // Initial value
    $stepsContainer.append(this.createPreviewStep(0, window.aieData.i18n.input, initialValue));

    // Each function step
    if (steps && steps.length > 0) {
      steps.forEach(function (step, index) {
        $stepsContainer.append(_this41.createPreviewStep(index + 1, step.function_name, step.output, step.error));
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
  } // Log to console
  ,
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
    this.init();
  }
  _createClass(ExportStep3, [{
    key: "init",
    value: function init() {
      // Check dependencies
      if (typeof jQuery === 'undefined') {
        return;
      }
      if (typeof aieData === 'undefined') {}
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
          } catch (error) {}
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
      column.innerHTML = "\n\t\t\t<div class=\"aie-column-header\">\n\t\t\t\t<span class=\"aie-column-icon dashicons ".concat(iconClass, "\"></span>\n\t\t\t\t<div class=\"aie-column-actions\">\n\t\t\t\t\t<button type=\"button\" class=\"aie-edit-column-functions\" title=\"").concat(window.aieData.i18n.assignFunctionsTitle, "\" data-field-key=\"").concat(fieldKey, "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t\t<button type=\"button\" class=\"aie-remove-column\" title=\"").concat(window.aieData.i18n.remove, "\" data-field-key=\"").concat(fieldKey, "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t\t<div class=\"aie-column-label\">").concat(this.escapeHtml(label), "</div>\n\t\t\t<div class=\"aie-column-field\">").concat(this.escapeHtml(field), "</div>\n\t\t\t").concat(hasFunctions ? "\n\t\t\t\t<div class=\"aie-column-badge\">\n\t\t\t\t\t<span class=\"dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\t".concat(this.fieldFunctions[fieldKey].length, " ").concat(window.aieData.i18n.functions, "\n\t\t\t\t</div>\n\t\t\t") : '', "\n\t\t");
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
      var label = prompt(window.aieData.i18n.enterColumnName);
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
      var $tooltip = jQuery('<div>').addClass('aie-custom-tooltip aie-custom-pointer').html("\n\t\t\t\t<div class=\"aie-pointer-icon\">\n\t\t\t\t\t<span class=\"dashicons dashicons-warning\"></span>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-pointer-content\">\n\t\t\t\t\t<h3>".concat(window.aieData.i18n.noFieldsSelected, "</h3>\n\t\t\t\t\t<p>").concat(window.aieData.i18n.pleaseSelectFieldMessage, "</p>\n\t\t\t\t</div>\n\t\t\t"));

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

      // Search input handler
      searchInput.addEventListener('input', function (e) {
        var query = e.target.value.toLowerCase();
        _this6.filterFields(query);
      });

      // Clear button handler
      var clearBtn = searchInput.parentElement.querySelector('.aie-clear-search');
      if (clearBtn) {
        clearBtn.addEventListener('click', function (e) {
          e.preventDefault();
          searchInput.value = '';
          searchInput.focus();
          _this6.filterFields('');
        });
      }
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
        // When clearing search, restore initial visibility state
        categories.forEach(function (category) {
          // Check if category has any field items
          var fieldItems = category.querySelectorAll('.aie-field-item');

          // If category has no field items (not loaded), keep it hidden
          if (fieldItems.length === 0) {
            category.style.display = 'none';
          } else {
            // If category has items, show it
            category.style.display = '';
          }
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
      // Get selected post type from step 1
      this.selectedPostType = this.getCurrentContentType();
      var contentType = this.getCurrentRealContentType();

      // Load static fields based on content type
      this.loadStaticFields();

      // Types that are not post types and should not load taxonomies/custom fields
      var nonPostTypes = ['taxonomy', 'user', 'menu', 'comment', 'database_table', 'woo_attribute'];

      // Load taxonomies only for actual post types
      if (!nonPostTypes.includes(contentType)) {
        this.loadTaxonomies();
      }

      // Load custom fields only for actual post types
      if (!nonPostTypes.includes(contentType)) {
        this.loadCustomFields();
      }

      // Check if ACF is active and load ACF fields (skip for non-supported types)
      var acfExcludedTypes = ['taxonomy', 'menu', 'comment', 'database_table', 'woo_attribute', 'woo_coupon', 'woo_order'];
      if (!acfExcludedTypes.includes(contentType)) {
        this.checkAndLoadACF();
      }

      // Check if Yoast is active and load Yoast fields (skip for non-content types)
      var excludedTypes = ['media', 'user', 'menu', 'comment', 'taxonomy', 'database_table', 'woo_attribute', 'woo_coupon', 'woo_order'];
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
          _grid2.innerHTML = "<div class=\"aie-acf-loading\"><span class=\"spinner is-active\"></span><p>".concat(window.aieData.i18n.loadingAcfFields, "</p></div>");
        }
      }
      if (yoastCategory) {
        yoastCategory.style.display = 'none';
        var _grid3 = yoastCategory.querySelector('.aie-yoast-fields-grid');
        if (_grid3) {
          _grid3.innerHTML = "<div class=\"aie-yoast-loading\"><span class=\"spinner is-active\"></span><p>".concat(window.aieData.i18n.loadingYoastFields, "</p></div>");
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
        return;
      }
      var contentType = this.getCurrentRealContentType();
      var fieldGroups = window.aieExportModule.getFieldsByContentType(contentType);

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
      title.innerHTML = "\n\t\t\t<span class=\"dashicons dashicons-arrow-down-alt2 aie-category-toggle\"></span>\n\t\t\t<span class=\"dashicons dashicons-admin-post\"></span>\n\t\t\t".concat(this.escapeHtml(group.label), "\n\t\t\t<button type=\"button\" class=\"aie-add-all-fields\" title=\"").concat(window.aieData.i18n.addAllFieldsTitle, "\">\n\t\t\t\t").concat(window.aieData.i18n.addAll, "\n\t\t\t</button>\n\t\t");
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
        error: function error(xhr, status, _error) {}
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
      if (typeof aieData === 'undefined') {
        return;
      }
      var contentType = this.getCurrentRealContentType();
      var requestData = {
        action: 'aie_get_acf_fields',
        nonce: aieData.nonce
      };

      // For taxonomy content type, send taxonomy parameter
      if (contentType === 'taxonomy') {
        var taxonomySelector = document.querySelector('.aie-taxonomy-selector');
        if (taxonomySelector && taxonomySelector.value) {
          requestData.taxonomy = taxonomySelector.value;
        } else {
          // If no taxonomy selected yet, hide ACF category
          var category = document.querySelector('.aie-acf-fields-category');
          if (category) {
            category.style.display = 'none';
          }
          return;
        }
      } else {
        // For other content types, send post_type parameter
        requestData.post_type = this.selectedPostType;
      }
      jQuery.ajax({
        url: aieData.ajaxUrl,
        method: 'POST',
        data: requestData,
        success: function success(response) {
          if (response.success && response.data.fields && response.data.fields.length > 0) {
            _this17.renderACFFields(response.data.fields);
            // Show the ACF category
            var _category3 = document.querySelector('.aie-acf-fields-category');
            if (_category3) {
              _category3.style.display = '';
            }
          } else {
            // Hide the category if no ACF fields
            var _category4 = document.querySelector('.aie-acf-fields-category');
            if (_category4) {
              _category4.style.display = 'none';
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
            var _category5 = document.querySelector('.aie-yoast-fields-category');
            if (_category5) {
              _category5.style.display = 'none';
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
        item.dataset.field = field.name; // Use field name as-is, it already includes the full meta key
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
          badge.innerHTML = "\n\t\t\t\t\t<span class=\"dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\t".concat(functions.length, " ").concat(window.aieData.i18n.functions, "\n\t\t\t\t");
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
        emptyState.innerHTML = "\n\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t<p>".concat(window.aieData.i18n.noFunctionsAvailableYet, "</p>\n\t\t\t\t<p>").concat(window.aieData.i18n.createFirstFunction, "</p>\n\t\t\t");
        container.appendChild(emptyState);
        return;
      }
      this.availableFunctions.forEach(function (func) {
        var item = document.createElement('div');
        item.className = 'aie-function-list-item';
        item.dataset.functionId = func.id;
        item.dataset.category = func.category || 'custom';
        item.innerHTML = "\n\t\t\t\t<div class=\"aie-function-list-info\">\n\t\t\t\t\t<span class=\"aie-function-list-name\">".concat(_this26.escapeHtml(func.name), "</span>\n\t\t\t\t\t<span class=\"aie-function-list-desc\">").concat(_this26.escapeHtml(func.description || ''), "</span>\n\t\t\t\t</div>\n\t\t\t\t<button type=\"button\" class=\"button button-small\">").concat(window.aieData.i18n.add, "</button>\n\t\t\t");
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
        noResults.innerHTML = "\n\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t<p>".concat(window.aieData.i18n.noFunctionsFound.replace('%s', categoryLabel), "</p>\n\t\t\t");
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
        this.showNotice(window.aieData.i18n.configErrorAieData, 'error');
        return;
      }
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
      stepsContainer.appendChild(this.createPreviewStep(0, window.aieData.i18n.input, initialValue));

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
      step.innerHTML = "\n\t\t\t<div class=\"aie-preview-step-number\">".concat(number, "</div>\n\t\t\t<div class=\"aie-preview-step-name\">").concat(this.escapeHtml(name), "</div>\n\t\t\t<span class=\"aie-preview-step-arrow dashicons dashicons-arrow-right-alt\"></span>\n\t\t\t<div class=\"aie-preview-step-value ").concat(error ? 'error' : '', "\">\n\t\t\t\t").concat(this.escapeHtml(error ? window.aieData.i18n.errorLabel.replace('%s', value) : value), "\n\t\t\t</div>\n\t\t");
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
    } // You can implement a toast notification system here

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
  exportStartTime: null,
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

    // Prevent selection of premium locked content types
    $wizard.on('click', '.aie-content-type.aie-premium-locked', function (e) {
      e.preventDefault();
      e.stopPropagation();

      // Show upgrade message
      var message = window.aieData.i18n.premiumOnlyFeature;
      _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(message, 'warning');

      // Prevent the radio button from being checked
      var $input = jQuery(e.currentTarget).find('input[type="radio"]');
      $input.prop('checked', false);
      return false;
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
      var _contentType = jQuery('input[name="content_type"]:checked').val();
      if (_contentType === 'database_table') {
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
    var noFilterTypes = [];
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
    var $nextStepBtn = jQuery('.aie-step-1 .aie-next-step');
    var visibleCount = 0;
    if (searchTerm === '') {
      // Show all if search is empty
      $contentTypes.show();
      $filterCount.hide();
      $noResults.hide();
      $nextStepBtn.prop('disabled', false);
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
      // Disable Next button when no results found
      $nextStepBtn.prop('disabled', true);
    } else {
      $noResults.hide();
      // Enable Next button when results are visible
      $nextStepBtn.prop('disabled', false);
    }
  },
  /**
   * Refresh item count
   */
  refreshCount: function refreshCount() {
    var _arguments = arguments,
      _this3 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee() {
      var showSpinner, $count, $spinner, $refreshBtn, _contentType2, options, $tableDropdown, dynamicFiltersData, _dynamicFiltersData, postType, response, $tableRowCount, _$tableRowCount;
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
              _contentType2 = jQuery('input[name="content_type"]:checked').val(); // Prepare options based on content type
              options = {};
              if (_contentType2 === 'database_table') {
                // For database tables, get table name from dropdown
                $tableDropdown = jQuery('#aie-table-name');
                dynamicFiltersData = _this3.getDynamicFilters();
                options = {
                  table_name: $tableDropdown.val(),
                  filters: dynamicFiltersData.filters
                };
              } else {
                // For other types, use dynamic filters
                _dynamicFiltersData = _this3.getDynamicFilters(); // Map content type to post_type for post-based exporters
                postType = _this3.getPostTypeForContentType(_contentType2);
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
              _context.next = 12;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_export_get_count', {
                export_type: _contentType2,
                options: options
              });
            case 12:
              response = _context.sent;
              $count.text(response.count || 0);
              // If database table is selected, also update table row count in the info panel
              if (_contentType2 === 'database_table') {
                $tableRowCount = jQuery('.aie-table-row-count');
                if ($tableRowCount.length) {
                  $tableRowCount.text(response.count || 0);
                }
              }
              // Update next button state based on count
              _this3.updateStep2NextButton();
              _context.next = 23;
              break;
            case 18:
              _context.prev = 18;
              _context.t0 = _context["catch"](6);
              $count.text('-');
              if (contentType === 'database_table') {
                _$tableRowCount = jQuery('.aie-table-row-count');
                if (_$tableRowCount.length) {
                  _$tableRowCount.text('-');
                }
              }

              // Disable next button on error
              _this3.updateStep2NextButton();
            case 23:
              _context.prev = 23;
              $spinner.removeClass('is-active');
              $refreshBtn.removeClass('is-refreshing');
              return _context.finish(23);
            case 27:
            case "end":
              return _context.stop();
          }
        }
      }, _callee, null, [[6, 18, 23, 27]]);
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
    var tooltipTitle = window.aieData.i18n.noDataAvailable;
    var tooltipMessage = window.aieData.i18n.adjustFiltersMessage;

    // Remove previous event handlers
    $nextBtn.off('mouseenter.tooltip mouseleave.tooltip');

    // For custom_post_types, check if post type is selected
    if (contentType === 'custom_post_types') {
      var $postTypeSelector = jQuery('.aie-post-type-selector');
      var selectedPostType = $postTypeSelector.val();
      if (!selectedPostType || selectedPostType.trim() === '') {
        isDisabled = true;
        tooltipTitle = window.aieData.i18n.postTypeRequired;
        tooltipMessage = window.aieData.i18n.pleaseSelectPostType;
      }
    }

    // For taxonomy, check if taxonomy is selected
    if (contentType === 'taxonomy') {
      var $taxonomySelector = jQuery('.aie-taxonomy-selector');
      var selectedTaxonomy = $taxonomySelector.val();
      if (!selectedTaxonomy || selectedTaxonomy.trim() === '') {
        isDisabled = true;
        tooltipTitle = window.aieData.i18n.taxonomyRequired;
        tooltipMessage = window.aieData.i18n.pleaseSelectTaxonomy;
      }
    }

    // For database_table, check if table is selected
    if (contentType === 'database_table') {
      var $tableSelector = jQuery('#aie-table-name');
      var selectedTable = $tableSelector.val();
      if (!selectedTable || selectedTable.trim() === '') {
        isDisabled = true;
        tooltipTitle = window.aieData.i18n.tableRequired;
        tooltipMessage = window.aieData.i18n.pleaseSelectTable;
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
    var tooltipTitle = $button.data('tooltip-title') || window.aieData.i18n.noDataAvailable;
    var tooltipMessage = $button.data('tooltip-message') || window.aieData.i18n.adjustFiltersMessage;

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

      // Normalize date values to YYYY-MM-DD regardless of datepicker locale format
      var fieldTypeForDate = $row.find('.aie-filter-field option:selected').data('type');
      if ((fieldTypeForDate === 'date' || fieldTypeForDate === 'datetime') && value) {
        var parsed = new Date(value);
        if (!isNaN(parsed.getTime())) {
          value = parsed.toISOString().slice(0, 10);
        }
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
      var fields, _contentType3, dynamicFiltersData, csvDelimiter, customDelimiter, data, convertedFunctions, $tableDropdown, tableName, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee2$(_context2) {
        while (1) {
          switch (_context2.prev = _context2.next) {
            case 0:
              fields = _this5.getSelectedFields(); // If no fields selected (or only pseudo-fields were filtered out), show error
              if (!(fields.length === 0)) {
                _context2.next = 4;
                break;
              }
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.pleaseSelectFieldToExport, 'error');
              return _context2.abrupt("return");
            case 4:
              _context2.prev = 4;
              _contentType3 = jQuery('input[name="content_type"]:checked').val();
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
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.pleaseEnterCustomDelimiter, 'error');
              // Set focus to the custom delimiter field
              jQuery('[name="csv_custom_delimiter"]').focus();
              return _context2.abrupt("return");
            case 14:
              csvDelimiter = customDelimiter;
            case 15:
              data = {
                export_type: _contentType3,
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
              if (_contentType3 === 'database_table') {
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
              _this5.exportStartTime = Date.now();
              _this5.showStep(5);
              _this5.startProgressTracking();

              // Trigger first batch processing
              _this5.processNextBatch();
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.exportStartedSuccess, 'success');
              _context2.next = 35;
              break;
            case 32:
              _context2.prev = 32;
              _context2.t0 = _context2["catch"](4);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].handleError(_context2.t0, 'Start export');
            case 35:
            case "end":
              return _context2.stop();
          }
        }
      }, _callee2, null, [[4, 32]]);
    }))();
  },
  /**
   * Process next export batch
   */
  processNextBatch: function processNextBatch() {
    var _this6 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee3() {
      var response, elapsedSec, processed, total, percentage, itemsPerSec, remainingSec, formatTime;
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
              // Update UI directly on each batch — don't rely solely on 2s polling.
              if (response) {
                elapsedSec = _this6.exportStartTime ? (Date.now() - _this6.exportStartTime) / 1000 : 0;
                processed = response.processed || 0;
                total = response.total || 0;
                percentage = response.progress || (total > 0 ? processed / total * 100 : 0);
                itemsPerSec = elapsedSec > 0 ? processed / elapsedSec : 0;
                remainingSec = itemsPerSec > 0 && total > processed ? (total - processed) / itemsPerSec : 0;
                formatTime = function formatTime(sec) {
                  sec = Math.round(sec);
                  if (sec < 60) return sec + 's';
                  if (sec < 3600) return Math.floor(sec / 60) + 'm ' + sec % 60 + 's';
                  return Math.floor(sec / 3600) + 'h ' + Math.floor(sec % 3600 / 60) + 'm';
                };
                _utils__WEBPACK_IMPORTED_MODULE_1__["default"].updateProgressBar(jQuery('.aie-step-5'), {
                  percentage: percentage,
                  processed: processed,
                  total: total,
                  estimates: {
                    elapsed_formatted: formatTime(elapsedSec),
                    remaining_formatted: remainingSec > 0 ? formatTime(remainingSec) : '-',
                    items_per_second: itemsPerSec
                  }
                });
              }

              // If not completed, process next batch after small delay
              if (response && !response.completed) {
                setTimeout(function () {
                  _this6.processNextBatch();
                }, 100);
              } else if (response && response.completed) {
                // Stop polling — we'll get the final state from the progress endpoint.
                clearInterval(_this6.progressInterval);
                // Fetch final state to show results (file size, duration, etc.)
                _this6.updateProgress();
              }
              _context3.next = 12;
              break;
            case 10:
              _context3.prev = 10;
              _context3.t0 = _context3["catch"](2);
            case 12:
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
              _context4.next = 10;
              break;
            case 8:
              _context4.prev = 8;
              _context4.t0 = _context4["catch"](0);
            case 10:
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
    jQuery('.aie-step-5 h2').text(window.aieData.i18n.exportComplete);

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
    var formatExportDuration = function formatExportDuration(sec) {
      sec = Math.max(0, Math.round(sec));
      if (sec < 60) return sec + 's';
      if (sec < 3600) return Math.floor(sec / 60) + 'm ' + sec % 60 + 's';
      return Math.floor(sec / 3600) + 'h ' + Math.floor(sec % 3600 / 60) + 'm';
    };
    var exportDurSec = this.exportStartTime ? (Date.now() - this.exportStartTime) / 1000 : 0;
    jQuery('.aie-result-duration').text(exportDurSec > 0 ? formatExportDuration(exportDurSec) : ((_result$estimates = result.estimates) === null || _result$estimates === void 0 ? void 0 : _result$estimates.elapsed_formatted) || '0s');
    jQuery('.aie-cancel-export').hide();
    jQuery('.aie-new-export').show();
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.exportCompletedSuccess, 'success');
  },
  /**
   * Handle export failure
   */
  onExportFailed: function onExportFailed(result) {
    clearInterval(this.progressInterval);
    var errorMessage = result.error || result.result && result.result.error || window.aieData.i18n.unknownError;
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.exportFailed + ': ' + errorMessage, 'error');
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
    this.exportStartTime = null;
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

    // Populate field options based on content type (without Featured Image group)
    var $fieldSelect = jQuery(clone).find('.aie-filter-field');
    var fields = this.getFilterFieldsByContentType(contentType);
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
      $valueWrap.html("\n\t\t\t\t<div class=\"aie-custom-field-inputs\">\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>".concat(window.aieData.i18n.selectField, "</label>\n\t\t\t\t\t\t<input type=\"text\" class=\"aie-custom-field-name\" placeholder=\"").concat(window.aieData.i18n.enterCustomFieldName, "\" />\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>").concat(window.aieData.i18n.condition, "</label>\n\t\t\t\t\t\t<select class=\"aie-custom-field-condition aie-filter-condition\">\n\t\t\t\t\t\t\t<option value=\"equals\">").concat(window.aieData.i18n.equals, "</option>\n\t\t\t\t\t\t\t<option value=\"not_equals\">").concat(window.aieData.i18n.notEquals, "</option>\n\t\t\t\t\t\t\t<option value=\"contains\">").concat(window.aieData.i18n.contains, "</option>\n\t\t\t\t\t\t\t<option value=\"not_contains\">").concat(window.aieData.i18n.notContains, "</option>\n\t\t\t\t\t\t\t<option value=\"greater\">").concat(window.aieData.i18n.greaterThan, "</option>\n\t\t\t\t\t\t\t<option value=\"less\">").concat(window.aieData.i18n.lessThan, "</option>\n\t\t\t\t\t\t\t<option value=\"equals_or_greater\">").concat(window.aieData.i18n.greaterOrEqual, "</option>\n\t\t\t\t\t\t\t<option value=\"equals_or_less\">").concat(window.aieData.i18n.lessOrEqual, "</option>\n\t\t\t\t\t\t\t<option value=\"in\">").concat(window.aieData.i18n.inComma, "</option>\n\t\t\t\t\t\t\t<option value=\"not_in\">").concat(window.aieData.i18n.notInComma, "</option>\n\t\t\t\t\t\t\t<option value=\"is_empty\">").concat(window.aieData.i18n.isEmpty, "</option>\n\t\t\t\t\t\t\t<option value=\"is_not_empty\">").concat(window.aieData.i18n.isNotEmpty, "</option>\n\t\t\t\t\t\t</select>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-input-group aie-custom-field-value-group\">\n\t\t\t\t\t\t<label>").concat(window.aieData.i18n.value, "</label>\n\t\t\t\t\t\t<input type=\"text\" class=\"aie-custom-field-value aie-filter-value\" placeholder=\"").concat(window.aieData.i18n.enterFilterValue, "\" />\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t"));
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
      $valueWrap.html("\n\t\t\t\t<div class=\"aie-taxonomy-filter-inputs\">\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>Taxonomy Name</label>\n\t\t\t\t\t\t<input type=\"text\" class=\"aie-taxonomy-name\" placeholder=\"".concat(window.aieData.i18n.taxonomyPlaceholderExamples, "\" />\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>Condition</label>\n\t\t\t\t\t\t<select class=\"aie-taxonomy-condition aie-filter-condition\">\n\t\t\t\t\t\t\t<option value=\"in\">").concat(window.aieData.i18n.hasTermsIn, "</option>\n\t\t\t\t\t\t\t<option value=\"not_in\">").concat(window.aieData.i18n.doesNotHaveTermsNotIn, "</option>\n\t\t\t\t\t\t\t<option value=\"and\">").concat(window.aieData.i18n.hasAllTermsAnd, "</option>\n\t\t\t\t\t\t</select>\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-input-group\">\n\t\t\t\t\t\t<label>Terms</label>\n\t\t\t\t\t\t<input type=\"text\" class=\"aie-taxonomy-terms aie-filter-value\" placeholder=\"").concat(window.aieData.i18n.enterTermSlugs, "\" />\n\t\t\t\t\t\t<small>").concat(window.aieData.i18n.enterTermSlugs, "</small>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t"));
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
      $valueWrap.find('label').text(window.aieData.i18n.selectTable);

      // Create a select dropdown for tables
      var $select = jQuery('<select>').addClass('aie-filter-value aie-table-selector').attr('name', 'filter_value[]');

      // Fetch database tables via AJAX
      _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_get_database_tables', {}).then(function (tables) {
        $select.append(jQuery('<option>').val('').text(window.aieData.i18n.selectTablePlaceholder));
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
        $select.append(jQuery('<option>').val('').text(window.aieData.i18n.errorLoadingTables));
      });
      $value.replaceWith($select);
      return;
    }

    // Special handling for post_type_selector
    if (fieldType === 'post_type_selector') {
      // Hide condition dropdown for post type selector
      $condition.closest('.aie-filter-condition-wrap').hide();

      // Replace value input with post type selector
      $valueWrap.find('label').text(window.aieData.i18n.selectPostType);

      // Create a select dropdown for post types
      var _$select = jQuery('<select>').addClass('aie-filter-value aie-post-type-selector').attr('name', 'filter_value[]');

      // Fetch post types via AJAX
      _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_get_post_types', {
        include_hidden: true
      }).then(function (postTypes) {
        _$select.append(jQuery('<option>').val('').text(window.aieData.i18n.selectPostTypePlaceholder));
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
        _$select.append(jQuery('<option>').val('').text(window.aieData.i18n.errorLoadingPostTypes));
      });
      $value.replaceWith(_$select);
      return;
    }

    // Special handling for taxonomy_selector
    if (fieldType === 'taxonomy_selector') {
      // Hide condition dropdown for taxonomy selector
      $condition.closest('.aie-filter-condition-wrap').hide();

      // Replace value input with taxonomy selector
      $valueWrap.find('label').text(window.aieData.i18n.selectTaxonomy);

      // Create a select dropdown for taxonomies
      var _$select2 = jQuery('<select>').addClass('aie-filter-value aie-taxonomy-selector').attr('name', 'filter_value[]');

      // Fetch taxonomies via AJAX
      _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_get_all_taxonomies', {}).then(function (taxonomies) {
        _$select2.append(jQuery('<option>').val('').text(window.aieData.i18n.selectTaxonomyPlaceholder));
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
        _$select2.append(jQuery('<option>').val('').text(window.aieData.i18n.errorLoadingTaxonomies));
      });
      $value.replaceWith(_$select2);
      return;
    }

    // Show condition dropdown for normal fields
    $condition.closest('.aie-filter-condition-wrap').show();
    $valueWrap.find('label').text(window.aieData.i18n.value);

    // Clear existing conditions and populate based on field type
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

    // If current input is a select (from post_type_selector or table_selector), replace with input
    if ($value.is('select')) {
      var $input = jQuery('<input>').attr('type', 'text').addClass('aie-filter-value').attr('name', 'filter_value[]').attr('placeholder', window.aieData.i18n.enterFilterValue);
      $value.replaceWith($input);
      // Update reference
      $row.find('.aie-filter-value').attr('type', fieldType === 'date' ? 'date' : fieldType === 'number' ? 'number' : 'text');
    }

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
          $fieldSelect.append(jQuery('<option>').val('').text(window.aieData.i18n.selectField));

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
    })["catch"](function (error) {});
  },
  /**
   * Get fields by content type
   */
  getFieldsByContentType: function getFieldsByContentType(contentType) {
    var baseFields = [{
      label: window.aieData.i18n.fieldGroupStandard,
      options: [{
        value: 'ID',
        label: window.aieData.i18n.fieldId,
        type: 'number'
      }, {
        value: 'post_title',
        label: window.aieData.i18n.fieldTitle,
        type: 'string'
      }, {
        value: 'post_content',
        label: window.aieData.i18n.fieldContent,
        type: 'string'
      }, {
        value: 'post_excerpt',
        label: window.aieData.i18n.fieldExcerpt,
        type: 'string'
      }, {
        value: 'post_date',
        label: window.aieData.i18n.fieldDate,
        type: 'date'
      }, {
        value: 'post_name',
        label: window.aieData.i18n.fieldSlug,
        type: 'string'
      }, {
        value: 'post_status',
        label: window.aieData.i18n.fieldStatus,
        type: 'string'
      }]
    }, {
      label: window.aieData.i18n.fieldGroupAuthor,
      options: [{
        value: 'post_author',
        label: window.aieData.i18n.fieldAuthorId,
        type: 'number'
      }, {
        value: 'author_name',
        label: window.aieData.i18n.fieldAuthorName,
        type: 'string'
      }, {
        value: 'author_email',
        label: window.aieData.i18n.fieldAuthorEmail,
        type: 'string'
      }]
    }, {
      label: window.aieData.i18n.fieldGroupFeaturedImage || 'Featured Image',
      options: [{
        value: 'featured_image_id',
        label: window.aieData.i18n.fieldFeaturedImageId || 'Featured Image ID',
        type: 'number'
      }, {
        value: 'featured_image_url',
        label: window.aieData.i18n.fieldFeaturedImageUrl || 'Featured Image URL',
        type: 'url'
      }, {
        value: 'featured_image_title',
        label: window.aieData.i18n.fieldFeaturedImageTitle || 'Featured Image Title',
        type: 'string'
      }, {
        value: 'featured_image_caption',
        label: window.aieData.i18n.fieldFeaturedImageCaption || 'Featured Image Caption',
        type: 'string'
      }]
    }, {
      label: window.aieData.i18n.fieldGroupOther,
      options: [{
        value: 'post_parent',
        label: window.aieData.i18n.fieldParentId,
        type: 'number'
      }, {
        value: 'menu_order',
        label: window.aieData.i18n.fieldMenuOrder || 'Menu Order',
        type: 'number'
      }, {
        value: 'comment_status',
        label: window.aieData.i18n.fieldCommentStatus,
        type: 'string'
      }, {
        value: 'post_modified',
        label: window.aieData.i18n.fieldModifiedDate,
        type: 'date'
      }, {
        value: '_wp_page_template',
        label: window.aieData.i18n.fieldTemplate,
        type: 'string'
      }]
    }, {
      label: window.aieData.i18n.fieldGroupCustomFilters,
      options: [{
        value: '_custom_field',
        label: window.aieData.i18n.fieldCustomFieldMeta,
        type: 'custom_field'
      }, {
        value: '_taxonomy_filter',
        label: window.aieData.i18n.fieldTaxonomyFilter,
        type: 'taxonomy_filter'
      }]
    }];

    // Customize based on content type
    if (contentType === 'media') {
      return [{
        label: window.aieData.i18n.fieldGroupBasic,
        options: [{
          value: 'ID',
          label: window.aieData.i18n.fieldId || 'ID',
          type: 'number'
        }, {
          value: 'post_title',
          label: window.aieData.i18n.fieldTitle,
          type: 'string'
        }, {
          value: 'post_content',
          label: window.aieData.i18n.fieldDescription,
          type: 'string'
        }, {
          value: 'post_excerpt',
          label: window.aieData.i18n.fieldCaption,
          type: 'string'
        }, {
          value: 'alt_text',
          label: window.aieData.i18n.fieldAltText,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupFileInformation,
        options: [{
          value: 'guid',
          label: window.aieData.i18n.fieldFileUrlGuid,
          type: 'url'
        }, {
          value: 'file_url',
          label: window.aieData.i18n.fieldFileUrl,
          type: 'url'
        }, {
          value: 'file_path',
          label: window.aieData.i18n.fieldFilePathRelative,
          type: 'string'
        }, {
          value: 'file_name',
          label: window.aieData.i18n.fieldFileName,
          type: 'string'
        }, {
          value: 'file_extension',
          label: window.aieData.i18n.fieldFileExtension,
          type: 'string'
        }, {
          value: 'post_mime_type',
          label: window.aieData.i18n.fieldMimeType,
          type: 'string'
        }, {
          value: 'file_size',
          label: window.aieData.i18n.fieldFileSizeBytes,
          type: 'number'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupImageDimensions,
        options: [{
          value: 'width',
          label: window.aieData.i18n.fieldWidthPx,
          type: 'number'
        }, {
          value: 'height',
          label: window.aieData.i18n.fieldHeightPx,
          type: 'number'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupDates,
        options: [{
          value: 'post_date',
          label: window.aieData.i18n.fieldUploadDate,
          type: 'date'
        }, {
          value: 'post_modified',
          label: window.aieData.i18n.fieldModifiedDate,
          type: 'date'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupAuthor,
        options: [{
          value: 'post_author',
          label: window.aieData.i18n.fieldAuthorId,
          type: 'number'
        }, {
          value: 'author_name',
          label: window.aieData.i18n.fieldAuthorName,
          type: 'string'
        }, {
          value: 'author_email',
          label: window.aieData.i18n.fieldAuthorEmail,
          type: 'email'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupAttachment,
        options: [{
          value: 'post_parent',
          label: window.aieData.i18n.fieldAttachedToPostId,
          type: 'number'
        }, {
          value: 'attached_post_title',
          label: window.aieData.i18n.fieldAttachedPostTitle,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupCustomFilters,
        options: [{
          value: '_custom_field',
          label: window.aieData.i18n.fieldCustomFieldMeta,
          type: 'custom_field'
        }]
      }];
    }

    // Pages don't have taxonomy section (but taxonomy_filter is still available in Custom Filters)
    if (contentType === 'page') {
      return baseFields.filter(function (group) {
        return group.label !== window.aieData.i18n.fieldGroupTaxonomy;
      });
    }

    // Menus
    if (contentType === 'menu') {
      return [{
        label: window.aieData.i18n.fieldGroupBasic,
        options: [{
          value: 'name',
          label: window.aieData.i18n.fieldMenuName,
          type: 'string'
        }, {
          value: 'menu_items',
          label: window.aieData.i18n.fieldMenuItemsArray + ' ' + (window.aieData.i18n.includesAcfFields || '(includes ACF fields)'),
          type: 'array'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupDetails,
        options: [{
          value: 'count',
          label: window.aieData.i18n.fieldItemsCount,
          type: 'number'
        }, {
          value: 'locations',
          label: window.aieData.i18n.fieldThemeLocations,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupCustomFilters,
        options: [{
          value: '_custom_field',
          label: window.aieData.i18n.fieldCustomFieldMeta,
          type: 'custom_field'
        }]
      }];
    }

    // Users
    if (contentType === 'user') {
      return [{
        label: window.aieData.i18n.fieldGroupBasic,
        options: [{
          value: 'ID',
          label: window.aieData.i18n.fieldId || 'ID',
          type: 'number'
        }, {
          value: 'user_login',
          label: window.aieData.i18n.fieldUsername || 'Username',
          type: 'string'
        }, {
          value: 'user_email',
          label: window.aieData.i18n.fieldEmail || 'Email',
          type: 'string'
        }, {
          value: 'display_name',
          label: window.aieData.i18n.fieldDisplayName || 'Display name',
          type: 'string'
        }, {
          value: 'user_nicename',
          label: window.aieData.i18n.fieldNiceName,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupProfile,
        options: [{
          value: 'first_name',
          label: window.aieData.i18n.fieldFirstName,
          type: 'string'
        }, {
          value: 'last_name',
          label: window.aieData.i18n.fieldLastName,
          type: 'string'
        }, {
          value: 'nickname',
          label: window.aieData.i18n.fieldNickname,
          type: 'string'
        }, {
          value: 'description',
          label: window.aieData.i18n.fieldBio,
          type: 'string'
        }, {
          value: 'user_url',
          label: window.aieData.i18n.fieldWebsite || 'Website',
          type: 'string'
        }, {
          value: 'avatar_url',
          label: window.aieData.i18n.fieldAvatarUrl,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupSocialMedia || 'Social Media',
        options: [{
          value: 'facebook',
          label: window.aieData.i18n.fieldFacebook || 'Facebook profile URL',
          type: 'string'
        }, {
          value: 'instagram',
          label: window.aieData.i18n.fieldInstagram || 'Instagram profile URL',
          type: 'string'
        }, {
          value: 'linkedin',
          label: window.aieData.i18n.fieldLinkedIn || 'LinkedIn profile URL',
          type: 'string'
        }, {
          value: 'myspace',
          label: window.aieData.i18n.fieldMySpace || 'MySpace profile URL',
          type: 'string'
        }, {
          value: 'pinterest',
          label: window.aieData.i18n.fieldPinterest || 'Pinterest profile URL',
          type: 'string'
        }, {
          value: 'soundcloud',
          label: window.aieData.i18n.fieldSoundCloud || 'SoundCloud profile URL',
          type: 'string'
        }, {
          value: 'tumblr',
          label: window.aieData.i18n.fieldTumblr || 'Tumblr profile URL',
          type: 'string'
        }, {
          value: 'wikipedia',
          label: window.aieData.i18n.fieldWikipedia || 'Wikipedia page about you',
          type: 'string'
        }, {
          value: 'twitter',
          label: window.aieData.i18n.fieldTwitter || 'X username',
          type: 'string'
        }, {
          value: 'youtube',
          label: window.aieData.i18n.fieldYouTube || 'YouTube profile URL',
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupRolePermissions,
        options: [{
          value: 'role',
          label: window.aieData.i18n.fieldRole,
          type: 'string'
        }, {
          value: 'capabilities',
          label: window.aieData.i18n.fieldCapabilitiesArray,
          type: 'array'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupPreferences,
        options: [{
          value: 'locale',
          label: window.aieData.i18n.fieldLanguage || 'Language',
          type: 'string'
        }, {
          value: 'admin_color',
          label: window.aieData.i18n.fieldAdminColorScheme,
          type: 'string'
        }, {
          value: 'rich_editing',
          label: window.aieData.i18n.fieldVisualEditor,
          type: 'boolean'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupStats,
        options: [{
          value: 'posts_count',
          label: window.aieData.i18n.fieldPostsCount,
          type: 'number'
        }, {
          value: 'user_registered',
          label: window.aieData.i18n.fieldRegistrationDate,
          type: 'date'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupCustomFilters,
        options: [{
          value: '_custom_field',
          label: window.aieData.i18n.fieldCustomFieldMeta,
          type: 'custom_field'
        }]
      }];
    }

    // Comments
    if (contentType === 'comment') {
      return [{
        label: window.aieData.i18n.fieldGroupBasic,
        options: [{
          value: 'comment_ID',
          label: window.aieData.i18n.fieldCommentId,
          type: 'number'
        }, {
          value: 'comment_post_ID',
          label: window.aieData.i18n.fieldPostId,
          type: 'number'
        }, {
          value: 'comment_content',
          label: window.aieData.i18n.fieldCommentContent,
          type: 'string'
        }, {
          value: 'comment_approved',
          label: window.aieData.i18n.fieldStatus,
          type: 'string'
        }, {
          value: 'comment_type',
          label: window.aieData.i18n.fieldCommentType,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupAuthor,
        options: [{
          value: 'comment_author',
          label: window.aieData.i18n.fieldAuthorName,
          type: 'string'
        }, {
          value: 'comment_author_email',
          label: window.aieData.i18n.fieldAuthorEmail,
          type: 'string'
        }, {
          value: 'comment_author_url',
          label: window.aieData.i18n.fieldAuthorUrl,
          type: 'string'
        }, {
          value: 'comment_author_IP',
          label: window.aieData.i18n.fieldAuthorIp,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupRelatedPost,
        options: [{
          value: 'post_title',
          label: window.aieData.i18n.fieldPostTitle,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupDates,
        options: [{
          value: 'comment_date',
          label: window.aieData.i18n.fieldCommentDate,
          type: 'date'
        }, {
          value: 'comment_date_gmt',
          label: window.aieData.i18n.fieldCommentDateGmt,
          type: 'date'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupHierarchy,
        options: [{
          value: 'comment_parent',
          label: window.aieData.i18n.fieldParentCommentId,
          type: 'number'
        }, {
          value: 'comment_karma',
          label: window.aieData.i18n.fieldKarma,
          type: 'number'
        }]
      }];
    }

    // Custom Post Types
    if (contentType === 'custom_post_types') {
      return [{
        label: window.aieData.i18n.fieldGroupPostTypeSelection,
        options: [{
          value: '_post_type',
          label: window.aieData.i18n.fieldPostTypeSelectSpecific,
          type: 'post_type_selector'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupStandard,
        options: [{
          value: 'ID',
          label: window.aieData.i18n.fieldId,
          type: 'number'
        }, {
          value: 'post_title',
          label: window.aieData.i18n.fieldTitle,
          type: 'string'
        }, {
          value: 'post_content',
          label: window.aieData.i18n.fieldContent,
          type: 'string'
        }, {
          value: 'post_excerpt',
          label: window.aieData.i18n.fieldExcerpt,
          type: 'string'
        }, {
          value: 'post_date',
          label: window.aieData.i18n.fieldDate,
          type: 'date'
        }, {
          value: 'post_name',
          label: window.aieData.i18n.fieldSlug,
          type: 'string'
        }, {
          value: 'post_status',
          label: window.aieData.i18n.fieldStatus,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupAuthor,
        options: [{
          value: 'post_author',
          label: window.aieData.i18n.fieldAuthorId,
          type: 'number'
        }, {
          value: 'author_name',
          label: window.aieData.i18n.fieldAuthorName,
          type: 'string'
        }, {
          value: 'author_email',
          label: window.aieData.i18n.fieldAuthorEmail,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupOther,
        options: [{
          value: 'post_parent',
          label: window.aieData.i18n.fieldParentId,
          type: 'number'
        }, {
          value: 'post_modified',
          label: window.aieData.i18n.fieldModifiedDate,
          type: 'date'
        }, {
          value: '_wp_page_template',
          label: window.aieData.i18n.fieldTemplate,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupCustomFilters,
        options: [{
          value: '_custom_field',
          label: window.aieData.i18n.fieldCustomFieldMeta,
          type: 'custom_field'
        }, {
          value: '_taxonomy_filter',
          label: window.aieData.i18n.fieldTaxonomyFilter,
          type: 'taxonomy_filter'
        }]
      }];
    } // Taxonomy
    if (contentType === 'taxonomy') {
      return [{
        label: window.aieData.i18n.fieldGroupTaxonomySelection,
        options: [{
          value: '_taxonomy',
          label: window.aieData.i18n.fieldTaxonomySelectSpecific,
          type: 'taxonomy_selector'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupBasic,
        options: [{
          value: 'term_id',
          label: window.aieData.i18n.fieldTermId,
          type: 'number'
        }, {
          value: 'name',
          label: window.aieData.i18n.fieldTermName,
          type: 'string'
        }, {
          value: 'slug',
          label: window.aieData.i18n.fieldTermSlug,
          type: 'string'
        }, {
          value: 'description',
          label: window.aieData.i18n.fieldDescription,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupHierarchy,
        options: [{
          value: 'parent',
          label: window.aieData.i18n.fieldParentTermId,
          type: 'number'
        }, {
          value: 'count',
          label: window.aieData.i18n.fieldPostsCount,
          type: 'number'
        }]
      }];
    }

    // WooCommerce Products
    if (contentType === 'woo_product') {
      return [{
        label: window.aieData.i18n.fieldGroupBasic,
        options: [{
          value: 'ID',
          label: window.aieData.i18n.fieldProductId,
          type: 'number'
        }, {
          value: 'post_title',
          label: window.aieData.i18n.fieldProductName,
          type: 'string'
        }, {
          value: 'post_name',
          label: window.aieData.i18n.fieldSlug,
          type: 'string'
        }, {
          value: 'post_status',
          label: window.aieData.i18n.fieldStatus,
          type: 'string'
        }, {
          value: 'sku',
          label: window.aieData.i18n.fieldSku,
          type: 'string'
        }, {
          value: 'post_author',
          label: window.aieData.i18n.fieldAuthorId,
          type: 'number'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupContent,
        options: [{
          value: 'post_content',
          label: window.aieData.i18n.fieldDescription,
          type: 'string'
        }, {
          value: 'post_excerpt',
          label: window.aieData.i18n.fieldShortDescription,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupPricing,
        options: [{
          value: 'regular_price',
          label: window.aieData.i18n.fieldRegularPrice,
          type: 'number'
        }, {
          value: 'sale_price',
          label: window.aieData.i18n.fieldSalePrice,
          type: 'number'
        }, {
          value: 'tax_status',
          label: window.aieData.i18n.fieldTaxStatus,
          type: 'string'
        }, {
          value: 'tax_class',
          label: window.aieData.i18n.fieldTaxClass,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupInventory,
        options: [{
          value: 'stock_quantity',
          label: window.aieData.i18n.fieldStockQuantity,
          type: 'number'
        }, {
          value: 'stock_status',
          label: window.aieData.i18n.fieldStockStatus,
          type: 'string'
        }, {
          value: 'manage_stock',
          label: window.aieData.i18n.fieldManageStock,
          type: 'boolean'
        }, {
          value: 'backorders',
          label: window.aieData.i18n.fieldBackorders,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupProductType,
        options: [{
          value: 'product_type',
          label: window.aieData.i18n.fieldProductType,
          type: 'string'
        }, {
          value: 'downloadable',
          label: window.aieData.i18n.fieldDownloadable,
          type: 'boolean'
        }, {
          value: 'virtual',
          label: window.aieData.i18n.fieldVirtual,
          type: 'boolean'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupShipping,
        options: [{
          value: 'weight',
          label: window.aieData.i18n.fieldWeight,
          type: 'number'
        }, {
          value: 'length',
          label: window.aieData.i18n.fieldLength,
          type: 'number'
        }, {
          value: 'width',
          label: window.aieData.i18n.fieldWidth,
          type: 'number'
        }, {
          value: 'height',
          label: window.aieData.i18n.fieldHeight,
          type: 'number'
        }, {
          value: 'shipping_class',
          label: window.aieData.i18n.fieldShippingClass,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupFeaturedImage || 'Featured Image',
        options: [{
          value: 'featured_image_id',
          label: window.aieData.i18n.fieldFeaturedImageId || 'Featured Image ID',
          type: 'number'
        }, {
          value: 'featured_image_url',
          label: window.aieData.i18n.fieldFeaturedImageUrl || 'Featured Image URL',
          type: 'url'
        }, {
          value: 'featured_image_title',
          label: window.aieData.i18n.fieldFeaturedImageTitle || 'Featured Image Title',
          type: 'string'
        }, {
          value: 'featured_image_caption',
          label: window.aieData.i18n.fieldFeaturedImageCaption || 'Featured Image Caption',
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupMedia,
        options: [{
          value: 'product_gallery',
          label: window.aieData.i18n.fieldGalleryImages,
          type: 'array'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupTaxonomy,
        options: [{
          value: 'product_cat',
          label: window.aieData.i18n.fieldCategories,
          type: 'string'
        }, {
          value: 'product_tag',
          label: window.aieData.i18n.fieldTags,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupReviews,
        options: [{
          value: 'average_rating',
          label: window.aieData.i18n.fieldAverageRating,
          type: 'number'
        }, {
          value: 'review_count',
          label: window.aieData.i18n.fieldReviewCount,
          type: 'number'
        }, {
          value: 'comment_status',
          label: window.aieData.i18n.fieldReviewsEnabled,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupVisibility,
        options: [{
          value: 'featured',
          label: window.aieData.i18n.fieldFeatured,
          type: 'boolean'
        }, {
          value: 'visibility',
          label: window.aieData.i18n.fieldCatalogVisibility,
          type: 'string'
        }, {
          value: 'total_sales',
          label: window.aieData.i18n.fieldTotalSales,
          type: 'number'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupDates,
        options: [{
          value: 'post_date',
          label: window.aieData.i18n.fieldCreatedDate,
          type: 'date'
        }, {
          value: 'post_modified',
          label: window.aieData.i18n.fieldModifiedDate,
          type: 'date'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupCustomFilters,
        options: [{
          value: '_custom_field',
          label: window.aieData.i18n.fieldCustomFieldMeta,
          type: 'custom_field'
        }, {
          value: '_taxonomy_filter',
          label: window.aieData.i18n.fieldTaxonomyFilter,
          type: 'taxonomy_filter'
        }]
      }];
    }

    // WooCommerce Orders
    if (contentType === 'woo_order') {
      return [{
        label: window.aieData.i18n.fieldGroupBasic,
        options: [{
          value: 'ID',
          label: window.aieData.i18n.fieldOrderId,
          type: 'number'
        }, {
          value: 'order_number',
          label: window.aieData.i18n.fieldOrderNumber,
          type: 'string'
        }, {
          value: 'order_status',
          label: window.aieData.i18n.fieldStatus,
          type: 'string'
        }, {
          value: 'order_key',
          label: window.aieData.i18n.fieldOrderKey,
          type: 'string'
        }, {
          value: 'currency',
          label: window.aieData.i18n.fieldCurrency,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupAmounts,
        options: [{
          value: 'order_total',
          label: window.aieData.i18n.fieldOrderTotal,
          type: 'number'
        }, {
          value: 'order_subtotal',
          label: window.aieData.i18n.fieldSubtotal,
          type: 'number'
        }, {
          value: 'order_tax',
          label: window.aieData.i18n.fieldTax,
          type: 'number'
        }, {
          value: 'order_shipping',
          label: window.aieData.i18n.fieldShipping,
          type: 'number'
        }, {
          value: 'order_discount',
          label: window.aieData.i18n.fieldDiscount,
          type: 'number'
        }, {
          value: 'cart_tax',
          label: 'Cart Tax',
          type: 'number'
        }, {
          value: 'shipping_tax',
          label: 'Shipping Tax',
          type: 'number'
        }, {
          value: 'total_tax',
          label: 'Total Tax',
          type: 'number'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupCustomer,
        options: [{
          value: 'customer_id',
          label: window.aieData.i18n.fieldCustomerId,
          type: 'number'
        }, {
          value: 'billing_email',
          label: window.aieData.i18n.fieldEmail,
          type: 'string'
        }, {
          value: 'customer_note',
          label: window.aieData.i18n.fieldCustomerNote,
          type: 'string'
        }, {
          value: 'customer_ip_address',
          label: 'Customer IP Address',
          type: 'string'
        }, {
          value: 'customer_user_agent',
          label: 'Customer User Agent',
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupBillingAddress,
        options: [{
          value: 'billing_first_name',
          label: window.aieData.i18n.fieldFirstName,
          type: 'string'
        }, {
          value: 'billing_last_name',
          label: window.aieData.i18n.fieldLastName,
          type: 'string'
        }, {
          value: 'billing_company',
          label: window.aieData.i18n.fieldCompany,
          type: 'string'
        }, {
          value: 'billing_address_1',
          label: window.aieData.i18n.fieldAddress1,
          type: 'string'
        }, {
          value: 'billing_address_2',
          label: window.aieData.i18n.fieldAddress2,
          type: 'string'
        }, {
          value: 'billing_city',
          label: window.aieData.i18n.fieldCity,
          type: 'string'
        }, {
          value: 'billing_state',
          label: window.aieData.i18n.fieldState,
          type: 'string'
        }, {
          value: 'billing_postcode',
          label: window.aieData.i18n.fieldPostcode,
          type: 'string'
        }, {
          value: 'billing_country',
          label: window.aieData.i18n.fieldCountry,
          type: 'string'
        }, {
          value: 'billing_phone',
          label: window.aieData.i18n.fieldPhone,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupShippingAddress,
        options: [{
          value: 'shipping_first_name',
          label: window.aieData.i18n.fieldFirstName,
          type: 'string'
        }, {
          value: 'shipping_last_name',
          label: window.aieData.i18n.fieldLastName,
          type: 'string'
        }, {
          value: 'shipping_company',
          label: window.aieData.i18n.fieldCompany,
          type: 'string'
        }, {
          value: 'shipping_address_1',
          label: window.aieData.i18n.fieldAddress1,
          type: 'string'
        }, {
          value: 'shipping_address_2',
          label: window.aieData.i18n.fieldAddress2,
          type: 'string'
        }, {
          value: 'shipping_city',
          label: window.aieData.i18n.fieldCity,
          type: 'string'
        }, {
          value: 'shipping_state',
          label: window.aieData.i18n.fieldState,
          type: 'string'
        }, {
          value: 'shipping_postcode',
          label: window.aieData.i18n.fieldPostcode,
          type: 'string'
        }, {
          value: 'shipping_country',
          label: window.aieData.i18n.fieldCountry,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupOrderItems,
        options: [{
          value: 'order_items',
          label: window.aieData.i18n.fieldOrderItemsArray,
          type: 'array'
        }, {
          value: 'item_count',
          label: window.aieData.i18n.fieldItemCount,
          type: 'number'
        }, {
          value: 'shipping_lines',
          label: 'Shipping Lines',
          type: 'array'
        }, {
          value: 'fee_lines',
          label: 'Fee Lines',
          type: 'array'
        }, {
          value: 'coupon_lines',
          label: 'Coupon Lines',
          type: 'array'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupPayment,
        options: [{
          value: 'payment_method',
          label: window.aieData.i18n.fieldPaymentMethod,
          type: 'string'
        }, {
          value: 'payment_method_title',
          label: window.aieData.i18n.fieldPaymentMethodTitle,
          type: 'string'
        }, {
          value: 'transaction_id',
          label: window.aieData.i18n.fieldTransactionId,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupShipping,
        options: [{
          value: 'shipping_method',
          label: window.aieData.i18n.fieldShippingMethod,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupDates,
        options: [{
          value: 'order_date',
          label: window.aieData.i18n.fieldOrderDate,
          type: 'date'
        }, {
          value: 'date_modified',
          label: 'Date Modified',
          type: 'date'
        }, {
          value: 'completed_date',
          label: window.aieData.i18n.fieldCompletedDate,
          type: 'date'
        }, {
          value: 'paid_date',
          label: window.aieData.i18n.fieldPaidDate,
          type: 'date'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupNotes,
        options: [{
          value: 'order_notes',
          label: window.aieData.i18n.fieldOrderNotesArray,
          type: 'array'
        }, {
          value: 'order_meta',
          label: 'Order Meta',
          type: 'array'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupCustomFilters,
        options: [{
          value: '_custom_field',
          label: window.aieData.i18n.fieldCustomFieldMeta,
          type: 'custom_field'
        }]
      }];
    }

    // WooCommerce Coupons
    if (contentType === 'woo_coupon') {
      return [{
        label: window.aieData.i18n.fieldGroupBasic,
        options: [{
          value: 'ID',
          label: window.aieData.i18n.fieldCouponId,
          type: 'number'
        }, {
          value: 'post_title',
          label: window.aieData.i18n.fieldCouponCode,
          type: 'string'
        }, {
          value: 'post_excerpt',
          label: window.aieData.i18n.fieldDescription,
          type: 'string'
        }, {
          value: 'post_status',
          label: window.aieData.i18n.fieldStatus,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupDiscount,
        options: [{
          value: 'discount_type',
          label: window.aieData.i18n.fieldDiscountType,
          type: 'string'
        }, {
          value: 'coupon_amount',
          label: window.aieData.i18n.fieldCouponAmount,
          type: 'number'
        }, {
          value: 'free_shipping',
          label: window.aieData.i18n.fieldFreeShipping,
          type: 'boolean'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupUsageRestrictions,
        options: [{
          value: 'minimum_amount',
          label: window.aieData.i18n.fieldMinimumSpend,
          type: 'number'
        }, {
          value: 'maximum_amount',
          label: window.aieData.i18n.fieldMaximumSpend,
          type: 'number'
        }, {
          value: 'individual_use',
          label: window.aieData.i18n.fieldIndividualUseOnly,
          type: 'boolean'
        }, {
          value: 'exclude_sale_items',
          label: window.aieData.i18n.fieldExcludeSaleItems,
          type: 'boolean'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupProductRestrictions,
        options: [{
          value: 'product_ids',
          label: window.aieData.i18n.fieldAllowedProducts,
          type: 'array'
        }, {
          value: 'excluded_product_ids',
          label: window.aieData.i18n.fieldExcludedProducts,
          type: 'array'
        }, {
          value: 'product_categories',
          label: window.aieData.i18n.fieldAllowedCategories,
          type: 'array'
        }, {
          value: 'excluded_product_categories',
          label: window.aieData.i18n.fieldExcludedCategories,
          type: 'array'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupEmailRestrictions,
        options: [{
          value: 'allowed_emails',
          label: window.aieData.i18n.fieldAllowedEmails,
          type: 'array'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupUsageLimits,
        options: [{
          value: 'usage_count',
          label: window.aieData.i18n.fieldUsageCount,
          type: 'number'
        }, {
          value: 'usage_limit',
          label: window.aieData.i18n.fieldUsageLimitTotal,
          type: 'number'
        }, {
          value: 'usage_limit_per_user',
          label: window.aieData.i18n.fieldUsageLimitPerUser,
          type: 'number'
        }, {
          value: 'limit_usage_to_x_items',
          label: 'Limit Usage to X Items',
          type: 'number'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupDates,
        options: [{
          value: 'date_expires',
          label: window.aieData.i18n.fieldExpiryDate,
          type: 'date'
        }, {
          value: 'post_date',
          label: window.aieData.i18n.fieldCreatedDate,
          type: 'date'
        }, {
          value: 'post_modified',
          label: window.aieData.i18n.fieldModifiedDate,
          type: 'date'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupCustomFilters,
        options: [{
          value: '_custom_field',
          label: window.aieData.i18n.fieldCustomFieldMeta,
          type: 'custom_field'
        }]
      }];
    }

    // WooCommerce Attributes
    if (contentType === 'woo_attribute') {
      return [{
        label: window.aieData.i18n.fieldGroupBasic,
        options: [{
          value: 'attribute_id',
          label: window.aieData.i18n.fieldAttributeId,
          type: 'number'
        }, {
          value: 'attribute_name',
          label: window.aieData.i18n.fieldAttributeName,
          type: 'string'
        }, {
          value: 'attribute_label',
          label: window.aieData.i18n.fieldAttributeLabel,
          type: 'string'
        }, {
          value: 'attribute_type',
          label: window.aieData.i18n.fieldAttributeType,
          type: 'string'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupSettings,
        options: [{
          value: 'attribute_orderby',
          label: window.aieData.i18n.fieldDefaultSortOrder,
          type: 'string'
        }, {
          value: 'attribute_public',
          label: window.aieData.i18n.fieldEnableArchives,
          type: 'boolean'
        }]
      }, {
        label: window.aieData.i18n.fieldGroupTerms,
        options: [{
          value: 'term_count',
          label: window.aieData.i18n.fieldTermsCount,
          type: 'number'
        }, {
          value: 'attribute_terms',
          label: window.aieData.i18n.fieldAllTermsArray,
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
          label: window.aieData.i18n.fieldGroupTableColumns,
          options: columnOptions
        }];
      }

      // Otherwise show message to select table first
      return [{
        label: window.aieData.i18n.fieldGroupTableSelection,
        options: [{
          value: '_select_table',
          label: window.aieData.i18n.fieldPleaseSelectTable,
          type: 'info'
        }]
      }];
    }
    return baseFields;
  },
  /**
   * Get filter fields by content type (for Step 2: Filter Data)
   * Same as getFieldsByContentType but excludes Featured Image group
   */
  getFilterFieldsByContentType: function getFilterFieldsByContentType(contentType) {
    // Get all fields first
    var allFields = this.getFieldsByContentType(contentType);

    // Groups to always exclude
    var excludedLabels = [window.aieData.i18n.fieldGroupFeaturedImage || 'Featured Image'];

    // For woo_coupon, also exclude these groups from filters
    if (contentType === 'woo_coupon') {
      excludedLabels.push(window.aieData.i18n.fieldGroupDiscount, window.aieData.i18n.fieldGroupUsageRestrictions, window.aieData.i18n.fieldGroupProductRestrictions, window.aieData.i18n.fieldGroupEmailRestrictions, window.aieData.i18n.fieldGroupUsageLimits, window.aieData.i18n.fieldGroupCustomFilters);
    }
    return allFields.filter(function (group) {
      return !excludedLabels.includes(group.label);
    });
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
      $value.attr('placeholder', window.aieData.i18n.enterValuesCommaSeparated);
      return;
    }

    // For 'between' condition on numbers, use text to allow comma-separated range
    if (condition === 'between' && fieldType === 'number') {
      $value.attr('type', 'text');
      $value.attr('placeholder', window.aieData.i18n.enterTwoNumbersCommaSeparated);
      return;
    }

    // Otherwise, set type based on field type
    if (fieldType === 'date') {
      $value.attr('type', 'date');
      $value.attr('placeholder', '');
    } else if (fieldType === 'number') {
      $value.attr('type', 'number');
      $value.attr('placeholder', window.aieData.i18n.enterNumberPlaceholder);
    } else {
      $value.attr('type', 'text');
      $value.attr('placeholder', window.aieData.i18n.enterFilterValue);
    }
  },
  /**
   * Get conditions by field type
   */
  getConditionsByFieldType: function getConditionsByFieldType(fieldType) {
    var conditions = {
      string: [{
        value: 'equals',
        label: window.aieData.i18n.equals
      }, {
        value: 'not_equals',
        label: window.aieData.i18n.notEquals
      }, {
        value: 'in',
        label: window.aieData.i18n.inFilter
      }, {
        value: 'not_in',
        label: window.aieData.i18n.notInFilter
      }, {
        value: 'contains',
        label: window.aieData.i18n.contains
      }, {
        value: 'not_contains',
        label: window.aieData.i18n.notContains
      }, {
        value: 'is_empty',
        label: window.aieData.i18n.isEmpty
      }, {
        value: 'is_not_empty',
        label: window.aieData.i18n.isNotEmpty
      }],
      number: [{
        value: 'equals',
        label: window.aieData.i18n.equals
      }, {
        value: 'not_equals',
        label: window.aieData.i18n.notEquals
      }, {
        value: 'in',
        label: window.aieData.i18n.inFilter
      }, {
        value: 'not_in',
        label: window.aieData.i18n.notInFilter
      }, {
        value: 'greater',
        label: window.aieData.i18n.greaterThan
      }, {
        value: 'equals_or_greater',
        label: window.aieData.i18n.greaterOrEqual
      }, {
        value: 'less',
        label: window.aieData.i18n.lessThan
      }, {
        value: 'equals_or_less',
        label: window.aieData.i18n.lessOrEqual
      }, {
        value: 'is_empty',
        label: window.aieData.i18n.isEmpty
      }, {
        value: 'is_not_empty',
        label: window.aieData.i18n.isNotEmpty
      }],
      date: [{
        value: 'equals',
        label: window.aieData.i18n.equals
      }, {
        value: 'not_equals',
        label: window.aieData.i18n.notEquals
      }, {
        value: 'greater',
        label: window.aieData.i18n.newerThan || window.aieData.i18n.greaterThan
      }, {
        value: 'equals_or_greater',
        label: window.aieData.i18n.greaterOrEqual
      }, {
        value: 'less',
        label: window.aieData.i18n.olderThan || window.aieData.i18n.lessThan
      }, {
        value: 'equals_or_less',
        label: window.aieData.i18n.lessOrEqual
      }, {
        value: 'is_empty',
        label: window.aieData.i18n.isEmpty
      }, {
        value: 'is_not_empty',
        label: window.aieData.i18n.isNotEmpty
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
      var tables = response.tables || response || [];

      // Clear and populate dropdown
      $dropdown.empty();
      $dropdown.append(jQuery('<option>').val('').text(window.aieData.i18n.selectTable));
      if (!Array.isArray(tables) || tables.length === 0) {
        $dropdown.append(jQuery('<option>').val('').text(window.aieData.i18n.noTablesFound));
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
      $dropdown.empty();
      $dropdown.append(jQuery('<option>').val('').text(window.aieData.i18n.errorLoadingTables));
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
  $columnsList.html("<p>".concat(window.aieData.i18n.loadingTableColumns, "</p>"));

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
    $columnsList.html("<p class=\"error\">".concat(window.aieData.i18n.errorLoadingColumns, "</p>"));
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
              _context2.next = 23;
              break;
            case 20:
              _context2.prev = 20;
              _context2.t0 = _context2["catch"](5);
              grid.innerHTML = "\n\t\t\t\t<div class=\"aie-error-message\">\n\t\t\t\t\t<span class=\"dashicons dashicons-warning\"></span>\n\t\t\t\t\t<p>".concat(_context2.t0.message, "</p>\n\t\t\t\t</div>\n\t\t\t");
            case 23:
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
              _context3.next = 26;
              break;
            case 23:
              _context3.prev = 23;
              _context3.t0 = _context3["catch"](7);
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showError)(_context3.t0.message);
            case 26:
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
              _context4.next = 36;
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
              _context4.next = 36;
              break;
            case 33:
              _context4.prev = 33;
              _context4.t0 = _context4["catch"](19);
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showError)(_context4.t0.message);
            case 36:
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
    var _window$aieData13, _window$aieData13$i, _window$aieData14, _window$aieData14$i, _window$aieData15, _window$aieData15$i, _window$aieData16, _window$aieData16$i, _window$aieData17, _window$aieData17$i, _window$aieData18, _window$aieData18$i, _window$aieData19, _window$aieData19$i, _window$aieData20, _window$aieData20$i;
    var labels = {
      string: ((_window$aieData13 = window.aieData) === null || _window$aieData13 === void 0 ? void 0 : (_window$aieData13$i = _window$aieData13.i18n) === null || _window$aieData13$i === void 0 ? void 0 : _window$aieData13$i.categoryStringOperations) || 'String Operations',
      date: ((_window$aieData14 = window.aieData) === null || _window$aieData14 === void 0 ? void 0 : (_window$aieData14$i = _window$aieData14.i18n) === null || _window$aieData14$i === void 0 ? void 0 : _window$aieData14$i.categoryDateTime) || 'Date & Time',
      numeric: ((_window$aieData15 = window.aieData) === null || _window$aieData15 === void 0 ? void 0 : (_window$aieData15$i = _window$aieData15.i18n) === null || _window$aieData15$i === void 0 ? void 0 : _window$aieData15$i.categoryNumericOperations) || 'Numeric Operations',
      html: ((_window$aieData16 = window.aieData) === null || _window$aieData16 === void 0 ? void 0 : (_window$aieData16$i = _window$aieData16.i18n) === null || _window$aieData16$i === void 0 ? void 0 : _window$aieData16$i.categoryHtmlOperations) || 'HTML Operations',
      wordpress: ((_window$aieData17 = window.aieData) === null || _window$aieData17 === void 0 ? void 0 : (_window$aieData17$i = _window$aieData17.i18n) === null || _window$aieData17$i === void 0 ? void 0 : _window$aieData17$i.categoryWordPress) || 'WordPress',
      validation: ((_window$aieData18 = window.aieData) === null || _window$aieData18 === void 0 ? void 0 : (_window$aieData18$i = _window$aieData18.i18n) === null || _window$aieData18$i === void 0 ? void 0 : _window$aieData18$i.categoryValidation) || 'Validation',
      advanced: ((_window$aieData19 = window.aieData) === null || _window$aieData19 === void 0 ? void 0 : (_window$aieData19$i = _window$aieData19.i18n) === null || _window$aieData19$i === void 0 ? void 0 : _window$aieData19$i.categoryAdvanced) || 'Advanced',
      custom: ((_window$aieData20 = window.aieData) === null || _window$aieData20 === void 0 ? void 0 : (_window$aieData20$i = _window$aieData20.i18n) === null || _window$aieData20$i === void 0 ? void 0 : _window$aieData20$i.categoryCustom) || 'Custom'
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
      _document$querySelect7,
      _document$querySelect8;
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
    (_document$querySelect7 = document.querySelector('.aie-test-function')) === null || _document$querySelect7 === void 0 ? void 0 : _document$querySelect7.addEventListener('click', function () {
      _this.testFunction();
    });

    // AI Generate button
    (_document$querySelect8 = document.querySelector('.aie-generate-with-ai')) === null || _document$querySelect8 === void 0 ? void 0 : _document$querySelect8.addEventListener('click', function () {
      _this.openAIPromptModal();
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
      var tbody, _window$aieData2, response, data, _data$data, _window$aieData3, _window$aieData3$i18n;
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
              throw new Error(data.message || ((_data$data = data.data) === null || _data$data === void 0 ? void 0 : _data$data.message) || ((_window$aieData3 = window.aieData) === null || _window$aieData3 === void 0 ? void 0 : (_window$aieData3$i18n = _window$aieData3.i18n) === null || _window$aieData3$i18n === void 0 ? void 0 : _window$aieData3$i18n.failedToLoadFunctions) || 'Failed to load functions');
            case 13:
              _this2.totalPages = data.data.total_pages || 1;
              _this2.totalItems = data.data.total || 0;
              _this2.renderTable(data.data.functions || []);
              _this2.updatePagination();
              _context.next = 22;
              break;
            case 19:
              _context.prev = 19;
              _context.t0 = _context["catch"](4);
              tbody.innerHTML = "\n\t\t\t\t<tr>\n\t\t\t\t\t<td colspan=\"4\" style=\"text-align:center; color:#dc3232;\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-warning\"></span>\n\t\t\t\t\t\t".concat(_context.t0.message, "\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t");
            case 22:
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
      var _window$aieData4, _window$aieData4$i18n;
      tbody.innerHTML = "\n\t\t\t\t<tr>\n\t\t\t\t\t<td colspan=\"4\" style=\"text-align:center; padding:40px;\">\n\t\t\t\t\t\t<div style=\"display:flex; flex-direction:column; align-items:center; gap:10px;\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-info\" style=\"font-size:48px; opacity:0.3;\"></span>\n\t\t\t\t\t\t\t<p style=\"margin:23px 0 0 0; color:#666;\">\n\t\t\t\t\t\t\t\t".concat(((_window$aieData4 = window.aieData) === null || _window$aieData4 === void 0 ? void 0 : (_window$aieData4$i18n = _window$aieData4.i18n) === null || _window$aieData4$i18n === void 0 ? void 0 : _window$aieData4$i18n.no_functions) || 'No functions found. Create your first function or browse the library.', "\n\t\t\t\t\t\t\t</p>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t");
      return;
    }
    tbody.innerHTML = functions.map(function (func) {
      var _window$aieData5, _window$aieData5$i18n, _window$aieData6, _window$aieData6$i18n, _window$aieData7, _window$aieData7$i18n;
      return "\n\t\t\t<tr data-function-id=\"".concat(func.id, "\">\n\t\t\t\t<td class=\"column-name\">\n\t\t\t\t\t<strong>").concat(_this3.escapeHtml(func.name), "</strong>\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-description\">\n\t\t\t\t\t").concat(func.description ? _this3.escapeHtml(func.description) : "<em style=\"color:#999;\">".concat(((_window$aieData5 = window.aieData) === null || _window$aieData5 === void 0 ? void 0 : (_window$aieData5$i18n = _window$aieData5.i18n) === null || _window$aieData5$i18n === void 0 ? void 0 : _window$aieData5$i18n.noDescription) || 'No description', "</em>"), "\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-actions\">\n\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-edit-function\" data-id=\"").concat(func.id, "\" title=\"").concat(((_window$aieData6 = window.aieData) === null || _window$aieData6 === void 0 ? void 0 : (_window$aieData6$i18n = _window$aieData6.i18n) === null || _window$aieData6$i18n === void 0 ? void 0 : _window$aieData6$i18n.editButton) || 'Edit', "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-edit\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t\t").concat(
      // Hide delete button for library snippets
      // Check type='library' or source starts with 'library:' or id starts with 'snippet_'
      func.type !== 'library' && !(func.source && func.source.startsWith('library:')) && !(func.id && func.id.toString().startsWith('snippet_')) ? "<button type=\"button\" class=\"button button-small aie-delete-function\" data-id=\"".concat(func.id, "\" title=\"").concat(((_window$aieData7 = window.aieData) === null || _window$aieData7 === void 0 ? void 0 : (_window$aieData7$i18n = _window$aieData7.i18n) === null || _window$aieData7$i18n === void 0 ? void 0 : _window$aieData7$i18n.deleteButton) || 'Delete', "\">\n\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-trash\"></span>\n\t\t\t\t\t\t\t</button>") : '', "\n\t\t\t\t</td>\n\t\t\t</tr>\n\t\t");
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
          var _window$aieData8, _window$aieData8$i18n;
          var id, confirmed;
          return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee2$(_context2) {
            while (1) {
              switch (_context2.prev = _context2.next) {
                case 0:
                  id = e.currentTarget.dataset.id;
                  _context2.next = 3;
                  return (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.confirmDialog)(((_window$aieData8 = window.aieData) === null || _window$aieData8 === void 0 ? void 0 : (_window$aieData8$i18n = _window$aieData8.i18n) === null || _window$aieData8$i18n === void 0 ? void 0 : _window$aieData8$i18n.confirm_delete) || 'Are you sure you want to delete this function?');
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
      var _window$aieData9, _window$aieData9$i18n;
      var start = (this.currentPage - 1) * this.perPage + 1;
      var end = Math.min(this.currentPage * this.perPage, this.totalItems);
      var text = ((_window$aieData9 = window.aieData) === null || _window$aieData9 === void 0 ? void 0 : (_window$aieData9$i18n = _window$aieData9.i18n) === null || _window$aieData9$i18n === void 0 ? void 0 : _window$aieData9$i18n.showingFunctions) || 'Showing %1$s-%2$s of %3$s functions';
      paginationInfo.textContent = text.replace('%1$s', start).replace('%2$s', end).replace('%3$s', this.totalItems);
    }
  },
  /**
   * Open function editor modal
   */
  openEditorModal: function openEditorModal() {
    var _arguments = arguments,
      _this4 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee3() {
      var functionId, modal, title, form, codeTextarea, _window$aieData10, _window$aieData10$i, _window$aieData11, response, data, _data$data2, _window$aieData12, _window$aieData12$i, func, isLibrarySnippet, _window$aieData13, _window$aieData13$i, _window$aieData14, _window$aieData14$i, _window$aieData15, _window$aieData15$i, infoBox, modalBody, _form, _window$aieData16, _window$aieData16$i, defaultCode;
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
                _context3.next = 43;
                break;
              }
              // Edit mode - load function data
              title.textContent = ((_window$aieData10 = window.aieData) === null || _window$aieData10 === void 0 ? void 0 : (_window$aieData10$i = _window$aieData10.i18n) === null || _window$aieData10$i === void 0 ? void 0 : _window$aieData10$i.edit_function) || 'Edit Function';
              _context3.prev = 18;
              _context3.next = 21;
              return fetch(window.aieData.ajaxUrl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'aie_functions_get',
                  nonce: ((_window$aieData11 = window.aieData) === null || _window$aieData11 === void 0 ? void 0 : _window$aieData11.nonce) || '',
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
              throw new Error(data.message || ((_data$data2 = data.data) === null || _data$data2 === void 0 ? void 0 : _data$data2.message) || ((_window$aieData12 = window.aieData) === null || _window$aieData12 === void 0 ? void 0 : (_window$aieData12$i = _window$aieData12.i18n) === null || _window$aieData12$i === void 0 ? void 0 : _window$aieData12$i.failedToLoadFunction) || 'Failed to load function');
            case 27:
              func = data.data; // Check if this is a library snippet
              isLibrarySnippet = functionId.toString().startsWith('snippet_');
              if (isLibrarySnippet) {
                // Show info that editing a snippet will create a new custom function
                title.textContent = ((_window$aieData13 = window.aieData) === null || _window$aieData13 === void 0 ? void 0 : (_window$aieData13$i = _window$aieData13.i18n) === null || _window$aieData13$i === void 0 ? void 0 : _window$aieData13$i.customize_snippet) || 'Customize Snippet';
                infoBox = document.createElement('div');
                infoBox.className = 'notice notice-info';
                infoBox.style.marginTop = '15px';
                infoBox.innerHTML = "\n\t\t\t\t\t\t<p><strong>".concat(((_window$aieData14 = window.aieData) === null || _window$aieData14 === void 0 ? void 0 : (_window$aieData14$i = _window$aieData14.i18n) === null || _window$aieData14$i === void 0 ? void 0 : _window$aieData14$i.snippet_customize_title) || 'Customizing Library Snippet', "</strong></p>\n\t\t\t\t\t\t<p>").concat(((_window$aieData15 = window.aieData) === null || _window$aieData15 === void 0 ? void 0 : (_window$aieData15$i = _window$aieData15.i18n) === null || _window$aieData15$i === void 0 ? void 0 : _window$aieData15$i.snippet_customize_info) || 'You are customizing a library snippet. Your changes will be saved as a new custom function.', "</p>\n\t\t\t\t\t");
                modalBody = modal.querySelector('.aie-modal-body');
                _form = modalBody.querySelector('form');
                modalBody.insertBefore(infoBox, _form);
              }
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
              _context3.next = 41;
              break;
            case 37:
              _context3.prev = 37;
              _context3.t0 = _context3["catch"](18);
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(_context3.t0.message, modal);
              return _context3.abrupt("return");
            case 41:
              _context3.next = 46;
              break;
            case 43:
              // Create mode - add default PHP opening tag
              title.textContent = ((_window$aieData16 = window.aieData) === null || _window$aieData16 === void 0 ? void 0 : (_window$aieData16$i = _window$aieData16.i18n) === null || _window$aieData16$i === void 0 ? void 0 : _window$aieData16$i.new_function) || 'New Function';

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
            case 46:
            case "end":
              return _context3.stop();
          }
        }
      }, _callee3, null, [[18, 37]]);
    }))();
  },
  /**
   * Open editor modal with snippet data for customization
   */
  openEditorWithSnippet: function openEditorWithSnippet(snippetData) {
    var _this5 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee4() {
      var _window$aieData17, _window$aieData17$i;
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
              title.textContent = ((_window$aieData17 = window.aieData) === null || _window$aieData17 === void 0 ? void 0 : (_window$aieData17$i = _window$aieData17.i18n) === null || _window$aieData17$i === void 0 ? void 0 : _window$aieData17$i.customizeFunction) || 'Customize Function';

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

    // Remove any info boxes that were added (e.g., snippet customization info)
    var infoBoxes = modal.querySelectorAll('.aie-info-box, .notice');
    infoBoxes.forEach(function (box) {
      // Only remove dynamically added info boxes, not permanent ones
      if (!box.hasAttribute('data-permanent')) {
        box.remove();
      }
    });
  },
  /**
   * Save function
   */
  saveFunction: function saveFunction() {
    var _this6 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee5() {
      var _window$aieData21;
      var codeTextarea, code, name, category, _window$aieData18, _window$aieData18$i, _window$aieData19, _window$aieData19$i, _window$aieData20, _window$aieData20$i, functionId, formData, _data$data4, _window$aieData24, _window$aieData24$i, response, contentType, _window$aieData22, _window$aieData22$i, text, data, _data$data3, _window$aieData23, _window$aieData23$i, originalName, savedFunction, successMessage, _window$aieData25, _window$aieData25$i, modal, errorMessage, _window$aieData26, _window$aieData26$i;
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
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(((_window$aieData18 = window.aieData) === null || _window$aieData18 === void 0 ? void 0 : (_window$aieData18$i = _window$aieData18.i18n) === null || _window$aieData18$i === void 0 ? void 0 : _window$aieData18$i.name_required) || 'Please enter a function name.');
              document.getElementById('aie-function-name').focus();
              return _context5.abrupt("return");
            case 10:
              if (code.trim()) {
                _context5.next = 14;
                break;
              }
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(((_window$aieData19 = window.aieData) === null || _window$aieData19 === void 0 ? void 0 : (_window$aieData19$i = _window$aieData19.i18n) === null || _window$aieData19$i === void 0 ? void 0 : _window$aieData19$i.code_required) || 'Please enter the PHP code for your function.');
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
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(((_window$aieData20 = window.aieData) === null || _window$aieData20 === void 0 ? void 0 : (_window$aieData20$i = _window$aieData20.i18n) === null || _window$aieData20$i === void 0 ? void 0 : _window$aieData20$i.category_required) || 'Please select a category.');
              document.getElementById('aie-function-category').focus();
              return _context5.abrupt("return");
            case 18:
              // Normalize PHP code (add <?php if missing and wrap if needed)
              code = _this6.normalizePhpCode(code);
              functionId = document.getElementById('aie-function-id').value; // Use FormData instead of URLSearchParams to preserve newlines
              formData = new FormData();
              formData.append('action', functionId ? 'aie_functions_update' : 'aie_functions_create');
              formData.append('nonce', ((_window$aieData21 = window.aieData) === null || _window$aieData21 === void 0 ? void 0 : _window$aieData21.nonce) || '');
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
                _context5.next = 39;
                break;
              }
              _context5.next = 37;
              return response.text();
            case 37:
              text = _context5.sent;
              throw new Error(((_window$aieData22 = window.aieData) === null || _window$aieData22 === void 0 ? void 0 : (_window$aieData22$i = _window$aieData22.i18n) === null || _window$aieData22$i === void 0 ? void 0 : _window$aieData22$i.serverErrorPhpSyntax) || 'Server error: The function code contains errors that prevent it from being saved. Please check your PHP syntax.');
            case 39:
              _context5.next = 41;
              return response.json();
            case 41:
              data = _context5.sent;
              if (data.success) {
                _context5.next = 44;
                break;
              }
              throw new Error(data.message || ((_data$data3 = data.data) === null || _data$data3 === void 0 ? void 0 : _data$data3.message) || ((_window$aieData23 = window.aieData) === null || _window$aieData23 === void 0 ? void 0 : (_window$aieData23$i = _window$aieData23.i18n) === null || _window$aieData23$i === void 0 ? void 0 : _window$aieData23$i.failedToSaveFunction) || 'Failed to save function');
            case 44:
              // Check if the function name was automatically changed
              originalName = document.getElementById('aie-function-name').value.trim();
              savedFunction = (_data$data4 = data.data) === null || _data$data4 === void 0 ? void 0 : _data$data4["function"];
              successMessage = ((_window$aieData24 = window.aieData) === null || _window$aieData24 === void 0 ? void 0 : (_window$aieData24$i = _window$aieData24.i18n) === null || _window$aieData24$i === void 0 ? void 0 : _window$aieData24$i.function_saved) || 'Function saved successfully';
              if (savedFunction && savedFunction.name && savedFunction.name !== originalName) {
                successMessage = (((_window$aieData25 = window.aieData) === null || _window$aieData25 === void 0 ? void 0 : (_window$aieData25$i = _window$aieData25.i18n) === null || _window$aieData25$i === void 0 ? void 0 : _window$aieData25$i.function_saved_with_new_name) || 'Function saved successfully. Name was automatically changed to "{name}" to avoid conflicts.').replace('{name}', savedFunction.name);
              }
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showNotice)(successMessage);
              _this6.closeModal(document.getElementById('aie-function-editor-modal'));
              _this6.loadFunctions();
              _context5.next = 59;
              break;
            case 53:
              _context5.prev = 53;
              _context5.t0 = _context5["catch"](29);
              modal = document.getElementById('aie-function-editor-modal'); // Improve error message for JSON parse errors
              errorMessage = _context5.t0.message;
              if (errorMessage.includes('Unexpected token') || errorMessage.includes('is not valid JSON')) {
                errorMessage = ((_window$aieData26 = window.aieData) === null || _window$aieData26 === void 0 ? void 0 : (_window$aieData26$i = _window$aieData26.i18n) === null || _window$aieData26$i === void 0 ? void 0 : _window$aieData26$i.serverErrorUnableToSave) || 'Server error: Unable to save function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.';
              }
              if (modal && modal.style.display === 'flex') {
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(errorMessage, modal);
              } else {
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showError)(errorMessage);
              }
            case 59:
            case "end":
              return _context5.stop();
          }
        }
      }, _callee5, null, [[29, 53]]);
    }))();
  },
  /**
   * Delete function
   */
  deleteFunction: function deleteFunction(functionId) {
    var _this7 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee6() {
      var _window$aieData27, _window$aieData29, _window$aieData29$i, response, data, _data$data5, _window$aieData28, _window$aieData28$i;
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
                  nonce: ((_window$aieData27 = window.aieData) === null || _window$aieData27 === void 0 ? void 0 : _window$aieData27.nonce) || '',
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
              throw new Error(data.message || ((_data$data5 = data.data) === null || _data$data5 === void 0 ? void 0 : _data$data5.message) || ((_window$aieData28 = window.aieData) === null || _window$aieData28 === void 0 ? void 0 : (_window$aieData28$i = _window$aieData28.i18n) === null || _window$aieData28$i === void 0 ? void 0 : _window$aieData28$i.failedToDeleteFunction) || 'Failed to delete function');
            case 9:
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showNotice)(((_window$aieData29 = window.aieData) === null || _window$aieData29 === void 0 ? void 0 : (_window$aieData29$i = _window$aieData29.i18n) === null || _window$aieData29$i === void 0 ? void 0 : _window$aieData29$i.function_deleted) || 'Function deleted successfully');
              _this7.loadFunctions();
              _context6.next = 16;
              break;
            case 13:
              _context6.prev = 13;
              _context6.t0 = _context6["catch"](0);
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showError)(_context6.t0.message);
            case 16:
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
      var code, testValueInput, testValue, resultsDiv, modal, _window$aieData30, _window$aieData30$i, _window$aieData31, _window$aieData31$i, _window$aieData32, formData, response, contentType, _window$aieData33, _window$aieData33$i, text, data, _data$data6, _window$aieData34, _window$aieData34$i, errorMessage, _window$aieData35, _window$aieData35$i;
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
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(((_window$aieData30 = window.aieData) === null || _window$aieData30 === void 0 ? void 0 : (_window$aieData30$i = _window$aieData30.i18n) === null || _window$aieData30$i === void 0 ? void 0 : _window$aieData30$i.pleaseEnterFunctionCode) || 'Please enter function code first', modal);
              } else {
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showError)(((_window$aieData31 = window.aieData) === null || _window$aieData31 === void 0 ? void 0 : (_window$aieData31$i = _window$aieData31.i18n) === null || _window$aieData31$i === void 0 ? void 0 : _window$aieData31$i.pleaseEnterFunctionCode) || 'Please enter function code first');
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
              formData.append('nonce', ((_window$aieData32 = window.aieData) === null || _window$aieData32 === void 0 ? void 0 : _window$aieData32.nonce) || '');
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
                _context7.next = 29;
                break;
              }
              _context7.next = 27;
              return response.text();
            case 27:
              text = _context7.sent;
              throw new Error(((_window$aieData33 = window.aieData) === null || _window$aieData33 === void 0 ? void 0 : (_window$aieData33$i = _window$aieData33.i18n) === null || _window$aieData33$i === void 0 ? void 0 : _window$aieData33$i.serverErrorFunctionErrors) || 'Server error: The function code contains errors. Please check your PHP syntax.');
            case 29:
              _context7.next = 31;
              return response.json();
            case 31:
              data = _context7.sent;
              if (data.success) {
                _context7.next = 34;
                break;
              }
              throw new Error(data.message || ((_data$data6 = data.data) === null || _data$data6 === void 0 ? void 0 : _data$data6.message) || ((_window$aieData34 = window.aieData) === null || _window$aieData34 === void 0 ? void 0 : (_window$aieData34$i = _window$aieData34.i18n) === null || _window$aieData34$i === void 0 ? void 0 : _window$aieData34$i.testFailed) || 'Test failed');
            case 34:
              // Show results
              document.querySelector('.aie-test-input').textContent = data.data.input !== undefined ? data.data.input : testValue;
              document.querySelector('.aie-test-output').textContent = data.data.output !== undefined ? data.data.output : '';
              resultsDiv.style.display = 'block';
              _context7.next = 44;
              break;
            case 39:
              _context7.prev = 39;
              _context7.t0 = _context7["catch"](14);
              // Improve error message for JSON parse errors
              errorMessage = _context7.t0.message;
              if (errorMessage.includes('Unexpected token') || errorMessage.includes('is not valid JSON')) {
                errorMessage = ((_window$aieData35 = window.aieData) === null || _window$aieData35 === void 0 ? void 0 : (_window$aieData35$i = _window$aieData35.i18n) === null || _window$aieData35$i === void 0 ? void 0 : _window$aieData35$i.serverErrorUnableToTest) || 'Server error: Unable to test function. The code may contain syntax errors or forbidden constructs. Check the browser console for details.';
              }
              if (modal && modal.style.display === 'flex') {
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(errorMessage, modal);
              } else {
                (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showError)(errorMessage);
              }
            case 44:
            case "end":
              return _context7.stop();
          }
        }
      }, _callee7, null, [[14, 39]]);
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
    var _window$aieData36, _window$aieData36$i, _window$aieData37, _window$aieData37$i, _window$aieData38, _window$aieData38$i, _window$aieData39, _window$aieData39$i, _window$aieData40, _window$aieData40$i, _window$aieData41, _window$aieData41$i, _window$aieData42, _window$aieData42$i, _window$aieData43, _window$aieData43$i;
    var labels = {
      string: ((_window$aieData36 = window.aieData) === null || _window$aieData36 === void 0 ? void 0 : (_window$aieData36$i = _window$aieData36.i18n) === null || _window$aieData36$i === void 0 ? void 0 : _window$aieData36$i.categoryStringOperations) || 'String Operations',
      date: ((_window$aieData37 = window.aieData) === null || _window$aieData37 === void 0 ? void 0 : (_window$aieData37$i = _window$aieData37.i18n) === null || _window$aieData37$i === void 0 ? void 0 : _window$aieData37$i.categoryDateTime) || 'Date & Time',
      numeric: ((_window$aieData38 = window.aieData) === null || _window$aieData38 === void 0 ? void 0 : (_window$aieData38$i = _window$aieData38.i18n) === null || _window$aieData38$i === void 0 ? void 0 : _window$aieData38$i.categoryNumericOperations) || 'Numeric Operations',
      html: ((_window$aieData39 = window.aieData) === null || _window$aieData39 === void 0 ? void 0 : (_window$aieData39$i = _window$aieData39.i18n) === null || _window$aieData39$i === void 0 ? void 0 : _window$aieData39$i.categoryHtmlOperations) || 'HTML Operations',
      wordpress: ((_window$aieData40 = window.aieData) === null || _window$aieData40 === void 0 ? void 0 : (_window$aieData40$i = _window$aieData40.i18n) === null || _window$aieData40$i === void 0 ? void 0 : _window$aieData40$i.categoryWordPress) || 'WordPress',
      validation: ((_window$aieData41 = window.aieData) === null || _window$aieData41 === void 0 ? void 0 : (_window$aieData41$i = _window$aieData41.i18n) === null || _window$aieData41$i === void 0 ? void 0 : _window$aieData41$i.categoryValidation) || 'Validation',
      advanced: ((_window$aieData42 = window.aieData) === null || _window$aieData42 === void 0 ? void 0 : (_window$aieData42$i = _window$aieData42.i18n) === null || _window$aieData42$i === void 0 ? void 0 : _window$aieData42$i.categoryAdvanced) || 'Advanced',
      custom: ((_window$aieData43 = window.aieData) === null || _window$aieData43 === void 0 ? void 0 : (_window$aieData43$i = _window$aieData43.i18n) === null || _window$aieData43$i === void 0 ? void 0 : _window$aieData43$i.categoryCustom) || 'Custom'
    };
    return labels[category] || category;
  },
  /**
   * Get category badge HTML
   */
  getCategoryBadge: function getCategoryBadge(category) {
    var _window$aieData45, _window$aieData45$i;
    if (category === 'library') {
      var _window$aieData44, _window$aieData44$i;
      return "<span class=\"aie-badge aie-badge-library\">".concat(((_window$aieData44 = window.aieData) === null || _window$aieData44 === void 0 ? void 0 : (_window$aieData44$i = _window$aieData44.i18n) === null || _window$aieData44$i === void 0 ? void 0 : _window$aieData44$i.badgeLibrary) || 'Library', "</span>");
    }
    return "<span class=\"aie-badge aie-badge-custom\">".concat(((_window$aieData45 = window.aieData) === null || _window$aieData45 === void 0 ? void 0 : (_window$aieData45$i = _window$aieData45.i18n) === null || _window$aieData45$i === void 0 ? void 0 : _window$aieData45$i.badgeCustom) || 'Custom', "</span>");
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
      // Code already has PHP tags, just return as-is
      return trimmedCode;
    }

    // No PHP tags, add them
    return '<?php\n' + trimmedCode;
  },
  /**
   * Get source badge HTML
   */
  getSourceBadge: function getSourceBadge(source) {
    var _window$aieData47, _window$aieData47$i;
    if (source.startsWith('library:')) {
      var _window$aieData46, _window$aieData46$i;
      return "<span class=\"aie-badge aie-badge-library\">".concat(((_window$aieData46 = window.aieData) === null || _window$aieData46 === void 0 ? void 0 : (_window$aieData46$i = _window$aieData46.i18n) === null || _window$aieData46$i === void 0 ? void 0 : _window$aieData46$i.badgeLibrary) || 'Library', "</span>");
    }
    return "<span class=\"aie-badge aie-badge-custom\">".concat(((_window$aieData47 = window.aieData) === null || _window$aieData47 === void 0 ? void 0 : (_window$aieData47$i = _window$aieData47.i18n) === null || _window$aieData47$i === void 0 ? void 0 : _window$aieData47$i.badgeCustom) || 'Custom', "</span>");
  },
  /**
   * Get status badge HTML
   */
  getStatusBadge: function getStatusBadge(status) {
    var _window$aieData49, _window$aieData49$i;
    if (status === 'active') {
      var _window$aieData48, _window$aieData48$i;
      return "<span class=\"aie-badge aie-badge-active\">".concat(((_window$aieData48 = window.aieData) === null || _window$aieData48 === void 0 ? void 0 : (_window$aieData48$i = _window$aieData48.i18n) === null || _window$aieData48$i === void 0 ? void 0 : _window$aieData48$i.badgeActive) || 'Active', "</span>");
    }
    return "<span class=\"aie-badge aie-badge-inactive\">".concat(((_window$aieData49 = window.aieData) === null || _window$aieData49 === void 0 ? void 0 : (_window$aieData49$i = _window$aieData49.i18n) === null || _window$aieData49$i === void 0 ? void 0 : _window$aieData49$i.badgeInactive) || 'Inactive', "</span>");
  },
  /**
   * Escape HTML
   */
  escapeHtml: function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },
  /**
   * Open AI prompt modal
   */
  openAIPromptModal: function openAIPromptModal() {
    var _window$aieData50;
    // Check if API key is configured
    if (!((_window$aieData50 = window.aieData) !== null && _window$aieData50 !== void 0 && _window$aieData50.hasOpenAIApiKey)) {
      var _window$aieData51, _window$aieData52, _window$aieData52$i;
      var optionsUrl = ((_window$aieData51 = window.aieData) === null || _window$aieData51 === void 0 ? void 0 : _window$aieData51.optionsUrl) || 'admin.php?page=wp-aie-plugin-options';
      var message = ((_window$aieData52 = window.aieData) === null || _window$aieData52 === void 0 ? void 0 : (_window$aieData52$i = _window$aieData52.i18n) === null || _window$aieData52$i === void 0 ? void 0 : _window$aieData52$i.apiKeyNotConfigured) || 'OpenAI API key is not configured. Please configure it in Plugin Options to use AI generation.\n\nDo you want to go to Plugin Options now?';
      if (confirm(message)) {
        window.location.href = optionsUrl;
      }
      return;
    }
    var modal = document.getElementById('aie-ai-prompt-modal');
    if (!modal) {
      return;
    }

    // Clear previous prompt
    document.getElementById('aie-ai-prompt').value = '';

    // Hide generating state
    var generatingDiv = modal.querySelector('.aie-ai-generating');
    if (generatingDiv) {
      generatingDiv.style.display = 'none';
    }

    // Show modal
    modal.style.display = 'flex';
    document.body.classList.add('aie-modal-open');

    // Focus on prompt textarea
    setTimeout(function () {
      var _document$getElementB2;
      (_document$getElementB2 = document.getElementById('aie-ai-prompt')) === null || _document$getElementB2 === void 0 ? void 0 : _document$getElementB2.focus();
    }, 100);

    // Bind AI modal events (if not already bound)
    this.bindAIModalEvents();
  },
  /**
   * Bind AI modal events
   */
  bindAIModalEvents: function bindAIModalEvents() {
    var _this9 = this;
    // Prevent multiple bindings
    if (this.aiModalEventsBound) {
      return;
    }
    this.aiModalEventsBound = true;
    var modal = document.getElementById('aie-ai-prompt-modal');
    if (!modal) {
      return;
    }

    // Example prompt links
    modal.querySelectorAll('.aie-use-example').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var prompt = e.target.dataset.prompt;
        document.getElementById('aie-ai-prompt').value = prompt;
      });
    });

    // Generate code button
    var generateBtn = modal.querySelector('.aie-generate-code');
    if (generateBtn && !generateBtn.dataset.bound) {
      generateBtn.dataset.bound = 'true';
      generateBtn.addEventListener('click', function () {
        _this9.generateFunctionWithAI();
      });
    }
  },
  /**
   * Generate function with AI
   */
  generateFunctionWithAI: function generateFunctionWithAI() {
    var _this10 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee8() {
      var modal, prompt, _window$aieData53, _window$aieData53$i, generatingDiv, generateBtn, _window$aieData54, _window$aieData59, _window$aieData59$i, response, _window$aieData55, _window$aieData55$i, contentType, _window$aieData56, _window$aieData56$i, data, _data$data7, _window$aieData57, _window$aieData57$i, errorMessage, _window$aieData58, _window$aieData58$i, codeTextarea, codeWithPhp, nameInput, descInput, _errorMessage, _window$aieData60, _window$aieData60$i, _window$aieData61, _window$aieData61$i, _window$aieData62, _window$aieData62$i;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee8$(_context8) {
        while (1) {
          switch (_context8.prev = _context8.next) {
            case 0:
              modal = document.getElementById('aie-ai-prompt-modal');
              prompt = document.getElementById('aie-ai-prompt').value.trim();
              if (prompt) {
                _context8.next = 6;
                break;
              }
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(((_window$aieData53 = window.aieData) === null || _window$aieData53 === void 0 ? void 0 : (_window$aieData53$i = _window$aieData53.i18n) === null || _window$aieData53$i === void 0 ? void 0 : _window$aieData53$i.prompt_required) || 'Please describe what you want the function to do.', modal);
              document.getElementById('aie-ai-prompt').focus();
              return _context8.abrupt("return");
            case 6:
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.clearModalErrors)();

              // Show generating state
              generatingDiv = modal.querySelector('.aie-ai-generating');
              generateBtn = modal.querySelector('.aie-generate-code');
              if (generatingDiv) {
                generatingDiv.style.display = 'block';
              }
              if (generateBtn) {
                generateBtn.disabled = true;
              }
              _context8.prev = 11;
              _context8.next = 14;
              return fetch(window.aieData.ajaxUrl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                  action: 'aie_functions_generate_with_ai',
                  nonce: ((_window$aieData54 = window.aieData) === null || _window$aieData54 === void 0 ? void 0 : _window$aieData54.nonce) || '',
                  prompt: prompt
                })
              });
            case 14:
              response = _context8.sent;
              if (response.ok) {
                _context8.next = 17;
                break;
              }
              throw new Error(((_window$aieData55 = window.aieData) === null || _window$aieData55 === void 0 ? void 0 : (_window$aieData55$i = _window$aieData55.i18n) === null || _window$aieData55$i === void 0 ? void 0 : _window$aieData55$i.ai_server_error) || "Server error: ".concat(response.status, " ").concat(response.statusText));
            case 17:
              // Check if response is JSON
              contentType = response.headers.get('content-type');
              if (!(!contentType || !contentType.includes('application/json'))) {
                _context8.next = 20;
                break;
              }
              throw new Error(((_window$aieData56 = window.aieData) === null || _window$aieData56 === void 0 ? void 0 : (_window$aieData56$i = _window$aieData56.i18n) === null || _window$aieData56$i === void 0 ? void 0 : _window$aieData56$i.ai_invalid_response) || 'Invalid server response. Please try again or contact support.');
            case 20:
              _context8.next = 22;
              return response.json();
            case 22:
              data = _context8.sent;
              if (data.success) {
                _context8.next = 26;
                break;
              }
              // Extract error message with fallback
              errorMessage = data.message || ((_data$data7 = data.data) === null || _data$data7 === void 0 ? void 0 : _data$data7.message) || data.data || ((_window$aieData57 = window.aieData) === null || _window$aieData57 === void 0 ? void 0 : (_window$aieData57$i = _window$aieData57.i18n) === null || _window$aieData57$i === void 0 ? void 0 : _window$aieData57$i.ai_generation_failed) || 'Failed to generate function with AI. Please try again.';
              throw new Error(errorMessage);
            case 26:
              if (!(!data.data || !data.data.code)) {
                _context8.next = 28;
                break;
              }
              throw new Error(((_window$aieData58 = window.aieData) === null || _window$aieData58 === void 0 ? void 0 : (_window$aieData58$i = _window$aieData58.i18n) === null || _window$aieData58$i === void 0 ? void 0 : _window$aieData58$i.ai_no_code) || 'AI did not return any code. Please try a different prompt.');
            case 28:
              // Insert generated code into the function editor
              codeTextarea = document.getElementById('aie-function-code');
              if (codeTextarea) {
                // Prepend <?php tag and comment before generated code
                codeWithPhp = '<?php\n\n' + data.data.code;
                codeTextarea.value = codeWithPhp;

                // Update CodeMirror if available
                if (_this10.codeEditor && _this10.codeEditor.codemirror) {
                  _this10.codeEditor.codemirror.setValue(codeWithPhp);
                }
              }

              // Optionally set function name if provided
              if (data.data.name) {
                nameInput = document.getElementById('aie-function-name');
                if (nameInput && !nameInput.value) {
                  nameInput.value = data.data.name;
                }
              }

              // Optionally set description if provided
              if (data.data.description) {
                descInput = document.getElementById('aie-function-description');
                if (descInput && !descInput.value) {
                  descInput.value = data.data.description;
                }
              }

              // Close AI modal
              _this10.closeModal(modal);

              // Show success message
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showNotice)(((_window$aieData59 = window.aieData) === null || _window$aieData59 === void 0 ? void 0 : (_window$aieData59$i = _window$aieData59.i18n) === null || _window$aieData59$i === void 0 ? void 0 : _window$aieData59$i.ai_generated) || 'AI has generated your function code! Please review and test it before saving.');
              _context8.next = 44;
              break;
            case 36:
              _context8.prev = 36;
              _context8.t0 = _context8["catch"](11);
              console.error('AI generation error:', _context8.t0);

              // Determine appropriate error message
              _errorMessage = _context8.t0.message; // Handle network errors
              if (_context8.t0.name === 'TypeError' && _context8.t0.message.includes('fetch')) {
                _errorMessage = ((_window$aieData60 = window.aieData) === null || _window$aieData60 === void 0 ? void 0 : (_window$aieData60$i = _window$aieData60.i18n) === null || _window$aieData60$i === void 0 ? void 0 : _window$aieData60$i.ai_network_error) || 'Network error. Please check your internet connection and try again.';
              }

              // Handle timeout errors
              if (_context8.t0.name === 'AbortError' || _context8.t0.message.includes('timeout')) {
                _errorMessage = ((_window$aieData61 = window.aieData) === null || _window$aieData61 === void 0 ? void 0 : (_window$aieData61$i = _window$aieData61.i18n) === null || _window$aieData61$i === void 0 ? void 0 : _window$aieData61$i.ai_timeout_error) || 'Request timed out. The AI service may be busy. Please try again.';
              }

              // Handle JSON parse errors
              if (_context8.t0.name === 'SyntaxError' && _context8.t0.message.includes('JSON')) {
                _errorMessage = ((_window$aieData62 = window.aieData) === null || _window$aieData62 === void 0 ? void 0 : (_window$aieData62$i = _window$aieData62.i18n) === null || _window$aieData62$i === void 0 ? void 0 : _window$aieData62$i.ai_invalid_response) || 'Received invalid response from server. Please try again.';
              }
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_1__.showModalError)(_errorMessage, modal);
            case 44:
              _context8.prev = 44;
              // Hide generating state
              if (generatingDiv) {
                generatingDiv.style.display = 'none';
              }
              if (generateBtn) {
                generateBtn.disabled = false;
              }
              return _context8.finish(44);
            case 48:
            case "end":
              return _context8.stop();
          }
        }
      }, _callee8, null, [[11, 36, 44, 48]]);
    }))();
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
/* harmony import */ var _BackupWarningModal__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./BackupWarningModal */ "./src/js/modules/BackupWarningModal.js");
function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); enumerableOnly && (symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; })), keys.push.apply(keys, symbols); } return keys; }
function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = null != arguments[i] ? arguments[i] : {}; i % 2 ? ownKeys(Object(source), !0).forEach(function (key) { _defineProperty(target, key, source[key]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)) : ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } return target; }
function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
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
  importStartTime: null,
  /**
   * Initialize module
   */
  init: function init() {
    if (!jQuery('#wp-aie-import').length) {
      return;
    }

    // Check if resuming a job from Jobs Log BEFORE showing any step.
    var urlParams = new URLSearchParams(window.location.search);
    var resumeJobId = urlParams.get('resume_job');
    this.bindEvents();
    if (resumeJobId) {
      // Resume job – go directly to step 6 (progress) and start batch processing.
      this.jobId = parseInt(resumeJobId);
      this.showStep(6);
      this.startBatchProcessing();
    } else {
      this.showStep(1);
    }
  },
  /**
   * Bind event handlers
   */
  bindEvents: function bindEvents() {
    var _this = this;
    var $wizard = jQuery('#wp-aie-import');

    // Content type filter/search
    $wizard.on('input', '#aie-content-type-search', function (e) {
      return _this.filterContentTypes(e);
    });

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

    // Prevent selection of premium locked content types
    $wizard.on('click', '.aie-content-type.aie-premium-locked', function (e) {
      e.preventDefault();
      e.stopPropagation();

      // Show upgrade message
      var message = aieData.i18n.premiumOnlyFeature || 'This content type is only available in the Premium version. Upgrade to unlock this feature.';
      _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(message, 'warning');

      // Prevent the radio button from being checked
      var $input = jQuery(e.currentTarget).find('input[type="radio"]');
      $input.prop('checked', false);
      return false;
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

    // Media import options
    $wizard.on('change', '#aie-auto-import-media', function (e) {
      _this.toggleMediaDuplicateOptions(e);
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
        var errorHtml = "\n\t\t\t\t\t<div class=\"notice notice-error\" style=\"padding: 20px; margin: 20px 0;\">\n\t\t\t\t\t\t<h3 style=\"margin-top: 0;\">\u274C ".concat(aieData.i18n.fileValidationFailed || 'File Validation Failed', "</h3>\n\t\t\t\t\t\t<p style=\"font-size: 14px;\">").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(this.fileData.error), "</p>\n\t\t\t\t\t\t<p style=\"margin-bottom: 0;\">\n\t\t\t\t\t\t\t<button type=\"button\" class=\"button aie-prev-step\">\n\t\t\t\t\t\t\t\t\u2190 ").concat(aieData.i18n.goBackUploadValidFile || 'Go Back and Upload a Valid File', "\n\t\t\t\t\t\t\t</button>\n\t\t\t\t\t\t</p>\n\t\t\t\t\t</div>\n\t\t\t\t");
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
      this.handleMediaImportOptions();
    }
  },
  /**
   * Go to next step
   */
  nextStep: function nextStep() {
    var _this2 = this;
    if (this.currentStep < this.totalSteps) {
      // Show backup warning when leaving step 1 (content type selection)
      if (this.currentStep === 1) {
        _BackupWarningModal__WEBPACK_IMPORTED_MODULE_3__["default"].show(function () {
          // User confirmed backup - proceed to next step
          if (_this2.validateStep(_this2.currentStep)) {
            _this2.showStep(_this2.currentStep + 1);
          }
        }, function () {
          // User cancelled - stay on current step
          // Do nothing
        });
        return;
      }

      // Validate current step
      if (this.validateStep(this.currentStep)) {
        this.showStep(this.currentStep + 1);
      }
    }
  },
  /**
   * Go to previous step
   */
  prevStep: function prevStep() {
    if (this.currentStep > 1) {
      this.showStep(this.currentStep - 1);
    }
  },
  /**
   * Validate step
   */
  validateStep: function validateStep(step) {
    switch (step) {
      case 2:
        if (!this.uploadedFile) {
          _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.pleaseUploadFile || 'Please upload a file', 'error');
          return false;
        }

        // Validate custom delimiter if selected
        var delimiter = jQuery('#csv_delimiter').val();
        if (delimiter === 'custom') {
          var customDelimiter = jQuery('#csv_custom_delimiter').val().trim();
          if (customDelimiter === '') {
            _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.pleaseEnterCustomDelimiter || 'Please enter a custom delimiter', 'error');
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
            _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.pleaseSelectPostType || 'Please select a post type', 'error');
            return false;
          }
        }

        // Validate field mapping
        var mappedFields = this.getFieldMapping();
        if (!mappedFields || mappedFields.length === 0) {
          _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.mapFields || 'Please map at least one field', 'error');
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
   * Filter content types based on search input
   */
  filterContentTypes: function filterContentTypes(e) {
    var searchTerm = jQuery(e.target).val().toLowerCase().trim();
    var $contentTypes = jQuery('.aie-content-type');
    var $filterCount = jQuery('.aie-filter-count');
    var $filterCountValue = jQuery('.aie-filter-count-value');
    var $noResults = jQuery('.aie-no-results');
    var $nextStepBtn = jQuery('.aie-step-1 .aie-next-step');
    var visibleCount = 0;
    if (searchTerm === '') {
      // Show all if search is empty
      $contentTypes.show();
      $filterCount.hide();
      $noResults.hide();
      $nextStepBtn.prop('disabled', false);
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
      // Disable Next button when no results found
      $nextStepBtn.prop('disabled', true);
    } else {
      $noResults.hide();
      // Enable Next button when results are visible
      $nextStepBtn.prop('disabled', false);
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
    var allowedExtensions = ['.csv'];
    var fileExt = '.' + file.name.split('.').pop().toLowerCase();
    if (!allowedExtensions.includes(fileExt)) {
      _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.invalidFileTypeCsv || 'Invalid file type. Please upload CSV files only.', 'error');
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
    var _this3 = this;
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
        // Check for validation errors
        if (result.error) {
          _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(result.error, 'error');

          // Show file info but keep upload area visible
          jQuery('.aie-upload-progress').hide();
          jQuery('.aie-upload-placeholder').show();

          // Store error in fileData to show on step 3
          _this3.fileData = {
            error: result.error,
            hasError: true
          };

          // Disable next button due to validation error
          jQuery('.aie-step-2 .aie-next-step').prop('disabled', true);
          return;
        }

        // Upload complete
        _this3.fileData = result;
        // Store delimiter used so it can be passed to the import job
        _this3.fileData.delimiter = csvOptions.delimiter || ',';

        // Hide upload area completely, show file info
        jQuery('.aie-upload-area').hide();
        jQuery('.aie-file-info').show();

        // Enable next button only if custom delimiter validation passes
        var delimiter = jQuery('#csv_delimiter').val();
        var shouldDisable = delimiter === 'custom' && jQuery('#csv_custom_delimiter').val().trim() === '';
        jQuery('.aie-step-2 .aie-next-step').prop('disabled', shouldDisable);

        // Show success message
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.fileUploadedSuccessfully || 'File uploaded successfully', 'success');

        // Show warning if present
        if (result.warning) {
          _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(result.warning, 'warning');
        }
      },
      onError: function onError(error) {
        // Upload failed
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice((aieData.i18n.uploadFailed || 'Upload failed') + ': ' + error.message, 'error');
        _this3.removeFile();
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
    jQuery('.aie-upload-placeholder').show();
    jQuery('.aie-upload-progress').hide();
    jQuery('.aie-format-options').hide();
    jQuery('#aie-file-input').val('');
    jQuery('.aie-step-2 .aie-next-step').prop('disabled', true);
  },
  /**
   * Reload file preview with updated CSV options
   */
  reloadFilePreview: function reloadFilePreview() {
    var _this4 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee() {
      var delimiter, actualDelimiter, csvOptions, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee$(_context) {
        while (1) {
          switch (_context.prev = _context.next) {
            case 0:
              if (!(!_this4.fileData || !_this4.fileData.file_path)) {
                _context.next = 2;
                break;
              }
              return _context.abrupt("return");
            case 2:
              if (!(_this4.fileData.format !== 'csv')) {
                _context.next = 4;
                break;
              }
              return _context.abrupt("return");
            case 4:
              // Collect current CSV options
              delimiter = jQuery('#csv_delimiter').val();
              actualDelimiter = delimiter === 'custom' ? jQuery('#csv_custom_delimiter').val().trim() : _this4.getDelimiterValue(delimiter);
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
                  file_path: _this4.fileData.file_path,
                  delimiter: csvOptions.delimiter,
                  has_header: csvOptions.has_header
                }
              });
            case 10:
              response = _context.sent;
              if (response.success) {
                // Update stored file data with new preview AND delimiter
                _this4.fileData.preview = response.data.preview;
                _this4.fileData.columns = response.data.columns;
                _this4.fileData.total_rows = response.data.total_rows;
                _this4.fileData.delimiter = csvOptions.delimiter;
              }
              _context.next = 16;
              break;
            case 14:
              _context.prev = 14;
              _context.t0 = _context["catch"](7);
            case 16:
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
    return 'csv';
  },
  /**
   * Load data preview
   */
  loadPreview: function loadPreview() {
    var _this5 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee2() {
      var _this5$fileData$colum;
      var preview, format;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee2$(_context2) {
        while (1) {
          switch (_context2.prev = _context2.next) {
            case 0:
              if (_this5.fileData) {
                _context2.next = 3;
                break;
              }
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.noFileDataAvailable || 'No file data available', 'error');
              return _context2.abrupt("return");
            case 3:
              if (!_this5.fileData.hasError) {
                _context2.next = 5;
                break;
              }
              return _context2.abrupt("return");
            case 5:
              if (_this5.fileData.preview) {
                _context2.next = 8;
                break;
              }
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.noPreviewDataAvailable || 'No preview data available', 'error');
              return _context2.abrupt("return");
            case 8:
              preview = _this5.fileData.preview;
              format = _this5.fileData.format || 'csv'; // Update stats
              jQuery('.aie-total-rows').text(_this5.fileData.total_rows || 0);
              jQuery('.aie-total-columns').text(((_this5$fileData$colum = _this5.fileData.columns) === null || _this5$fileData$colum === void 0 ? void 0 : _this5$fileData$colum.length) || 0);

              // Always show table preview (both CSV and JSON)
              _this5.showTablePreview(preview);
            case 13:
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
    jQuery('.aie-preview-note').text(window.aieData.i18n.showingFirstRows);
    var $table = jQuery('.aie-preview-table'); // Build table header
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
    if (!this.fileData || !this.fileData.columns) {
      return;
    }
    var contentType = jQuery('input[name="content_type"]:checked').val();

    // Show/hide post type selector for custom post types
    this.togglePostTypeSelector(contentType);

    // Show/hide database table selector
    this.toggleDatabaseTableSelector(contentType);

    // Build source fields (from file)
    this.buildSourceFields();

    // Build target fields (WordPress fields or database table columns)
    if (contentType === 'database_table') {
      // Table columns will be loaded after table selection
      jQuery('#aie-target-fields').html("<div class=\"aie-info\">".concat(window.aieData.i18n.pleaseSelectTable, "</div>"));
    } else {
      this.buildTargetFields(contentType);
    } // Load dynamic ACF fields
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
    var _this6 = this;
    var $container = jQuery('#aie-source-fields');
    var html = '';
    this.fileData.columns.forEach(function (column, index) {
      var _this6$fileData$previ, _this6$fileData$previ2, _this6$fileData$previ3;
      var sampleData = ((_this6$fileData$previ = _this6.fileData.preview) === null || _this6$fileData$previ === void 0 ? void 0 : (_this6$fileData$previ2 = _this6$fileData$previ.data) === null || _this6$fileData$previ2 === void 0 ? void 0 : (_this6$fileData$previ3 = _this6$fileData$previ2[0]) === null || _this6$fileData$previ3 === void 0 ? void 0 : _this6$fileData$previ3[index]) || '';
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
    if (contentType === 'custom_post_types') {
      $selector.css('display', 'block');
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
          var options = '<option value="">-- ' + (aieData.i18n.selectPostType || 'Select Post Type') + ' --</option>';
          response.data.forEach(function (postType) {
            options += "<option value=\"".concat(postType.name, "\">").concat(postType.label, "</option>");
          });
          $select.html(options);
        }
      },
      error: function error(xhr, status, _error) {}
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
    var _this7 = this;
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
          $select.append(jQuery('<option>').val('').text(window.aieData.i18n.selectTable));
          if (!Array.isArray(tables) || tables.length === 0) {
            $select.append(jQuery('<option>').val('').text(window.aieData.i18n.noTablesFound));
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
              _this7.selectedTableName = tableName;
              _this7.loadTableInfo(tableName);
              _this7.loadTableColumnsForMapping();
            } else {
              jQuery('.aie-table-info').html('').hide();
              jQuery('#aie-target-fields').html('<div class="aie-info">' + (window.aieData.i18n.pleaseSelectTable || 'Please select a database table above to see available columns') + '</div>');
            }
          });
        } else {
          $select.html("<option value=\"\">".concat(window.aieData.i18n.noTablesFound, "</option>"));
        }
      },
      error: function error(xhr, status, _error2) {
        $spinner.removeClass('is-active');
        $select.html("<option value=\"\">".concat(window.aieData.i18n.errorLoadingTables, "</option>"));
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
        $columnsList.html("<p>".concat(window.aieData.i18n.errorLoadingColumns, "</p>"));
      }
    });
  },
  /**
   * Load table columns for Step 4 (field mapping)
   */
  loadTableColumnsForMapping: function loadTableColumnsForMapping() {
    var _this8 = this;
    if (!this.selectedTableName) {
      return;
    }
    var $container = jQuery('#aie-target-fields');
    $container.html("<div class=\"aie-loading\">".concat(window.aieData.i18n.loadingTableColumns, "</div>"));
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
          html += '<div class="aie-field-group-label">' + (window.aieData.i18n.fieldGroupTableColumns || 'Table Columns') + '</div>';
          columns.forEach(function (column) {
            html += "\n\t\t\t\t\t\t\t<div class=\"aie-target-field\" data-target-field=\"".concat(column.name, "\" data-field-type=\"").concat(column.type || 'string', "\">\n\t\t\t\t\t\t\t\t<div class=\"aie-field-icon\">\n\t\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-database\"></span>\n\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t\t<div class=\"aie-field-info\">\n\t\t\t\t\t\t\t\t\t<div class=\"aie-field-label\">").concat(column.name, "</div>\n\t\t\t\t\t\t\t\t\t<span class=\"aie-field-type-badge\">").concat(column.type || 'string', "</span>\n\t\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t");
          });
          html += '</div>';
          $container.html(html);

          // Re-initialize drag & drop
          _this8.initializeDragDrop();
        } else {
          $container.html("<div class=\"aie-error\">".concat(window.aieData.i18n.errorLoadingColumns, "</div>"));
        }
      },
      error: function error(xhr, status, _error4) {
        $container.html("<div class=\"aie-error\">".concat(window.aieData.i18n.errorLoadingColumns, "</div>"));
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
          html += "\n\t\t\t\t\t<div class=\"aie-target-field aie-custom-field-template\" data-field-type=\"".concat(field.type || 'string', "\" data-multiple=\"").concat(field.multiple || false, "\">\n\t\t\t\t\t\t<div class=\"aie-field-icon\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-plus\"></span>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t<div class=\"aie-field-info\">\n\t\t\t\t\t\t\t<div class=\"aie-field-label\">").concat(field.label, "</div>\n\t\t\t\t\t\t\t<button type=\"button\" class=\"aie-add-custom-field button button-small\">+ ").concat(window.aieData.i18n.add || 'Add', "</button>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\t\t\t\t");
        } else {
          html += "\n\t\t\t\t\t<div class=\"aie-target-field\" data-target-field=\"".concat(field.value, "\" data-field-type=\"").concat(field.type || 'string', "\" data-multiple=\"").concat(field.multiple || false, "\">\n\t\t\t\t\t\t<div class=\"aie-field-icon\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-wordpress\"></span>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t<div class=\"aie-field-info\">\n\t\t\t\t\t\t\t<div class=\"aie-field-label\">").concat(field.label, "</div>\n\t\t\t\t\t\t\t<span class=\"aie-field-type-badge\">").concat(field.type || 'string', "</span>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\t\t\t\t");
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
    var title = isTaxonomy ? window.aieData.i18n.addTaxonomyField || 'Add Taxonomy Field' : window.aieData.i18n.addCustomField || 'Add Custom Field';
    var placeholder = isTaxonomy ? window.aieData.i18n.enterTaxonomySlug || 'Enter taxonomy slug (e.g., category, post_tag, product_cat)' : window.aieData.i18n.enterFieldKey || 'Enter field key (e.g., _custom_price)';
    var icon = isTaxonomy ? 'dashicons-category' : 'dashicons-admin-plugins';

    // Taxonomy format options
    var taxonomyFormatField = isTaxonomy ? "\n\t\t\t<label style=\"margin-top: 15px;\">\n\t\t\t\t<strong>".concat(window.aieData.i18n.dataFormat || 'Data Format', ":</strong>\n\t\t\t\t<select class=\"aie-taxonomy-format regular-text\">\n\t\t\t\t\t<option value=\"id\">").concat(window.aieData.i18n.termIdFormat || 'Term ID (e.g., 5, 12, 23)', "</option>\n\t\t\t\t\t<option value=\"slug\">").concat(window.aieData.i18n.termSlugFormat || 'Term Slug (e.g., technology, news)', "</option>\n\t\t\t\t\t<option value=\"name\" selected>").concat(window.aieData.i18n.termNameFormat || 'Term Name (e.g., Technology, News)', "</option>\n\t\t\t\t</select>\n\t\t\t\t<p class=\"description\" style=\"margin-top: 5px;\">\n\t\t\t\t\t").concat(window.aieData.i18n.selectTaxonomyDataFormat || 'Select the format of taxonomy data in your CSV file.', "\n\t\t\t\t</p>\n\t\t\t</label>\n\t\t") : '';

    // Create modal HTML (same structure as function modal)
    var modalHtml = "\n\t\t\t<div id=\"aie-custom-field-modal\" class=\"aie-modal\" style=\"display:flex;\">\n\t\t\t\t<div class=\"aie-modal-backdrop\"></div>\n\t\t\t\t<div class=\"aie-modal-content aie-custom-field-modal-content\">\n\t\t\t\t\t<div class=\"aie-modal-header\">\n\t\t\t\t\t\t<h2 class=\"aie-modal-title\">\n\t\t\t\t\t\t\t<span class=\"dashicons ".concat(icon, "\"></span>\n\t\t\t\t\t\t\t").concat(title, "\n\t\t\t\t\t\t</h2>\n\t\t\t\t\t\t<button type=\"button\" class=\"aie-modal-close\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t\t\t</button>\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-modal-body\">\n\t\t\t\t\t<label>\n\t\t\t\t\t\t<strong>").concat(isTaxonomy ? window.aieData.i18n.taxonomySlugLabel || 'Taxonomy Slug' : window.aieData.i18n.metaKeyLabel || 'Meta Key', ":</strong>\n\t\t\t\t\t\t<input type=\"text\" class=\"aie-custom-field-input regular-text\" placeholder=\"").concat(placeholder, "\" />\n\t\t\t\t\t\t").concat(isTaxonomy ? '<p class="description" style="margin-top: 5px;">' + (window.aieData.i18n.taxonomySlugDescription || 'The slug of the taxonomy (category, post_tag, or custom taxonomy).') + '</p>' : isMeta ? '<p class="description" style="margin-top: 5px;">' + (window.aieData.i18n.metaKeyDescription || 'The meta key for the custom field (e.g., _custom_price, my_custom_field).') + '</p>' : '', "\n\t\t\t\t\t</label>\n\t\t\t\t\t").concat(taxonomyFormatField, "\n\t\t\t\t\t</div>\n\t\t\t\t\t<div class=\"aie-modal-footer\">\n\t\t\t\t\t\t<button type=\"button\" class=\"button aie-modal-cancel\">").concat(window.aieData.i18n.cancel || 'Cancel', "</button>\n\t\t\t\t\t\t<button type=\"button\" class=\"button button-primary aie-modal-add\">").concat(window.aieData.i18n.addField || 'Add Field', "</button>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t");

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
        alert(window.aieData.i18n.pleaseEnterFieldName);
        return;
      } // Get taxonomy format if applicable
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
    // Helper function to get translated field group label
    var t = function t(key, fallback) {
      var _window$aieData, _window$aieData$i18n;
      return ((_window$aieData = window.aieData) === null || _window$aieData === void 0 ? void 0 : (_window$aieData$i18n = _window$aieData.i18n) === null || _window$aieData$i18n === void 0 ? void 0 : _window$aieData$i18n['fieldGroup' + key.replace(/[^A-Za-z]/g, '')]) || fallback;
    };

    // Helper to translate field groups
    var translateGroups = function translateGroups(groups) {
      return groups.map(function (group) {
        return _objectSpread(_objectSpread({}, group), {}, {
          label: t(group.label, group.label)
        });
      });
    };
    var baseFields = [{
      label: t('Standard', 'Standard'),
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
      label: t('Author', 'Author'),
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
      return translateGroups([{
        label: 'Basic',
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
        }, {
          value: 'guid',
          label: 'GUID',
          type: 'string'
        }]
      }, {
        label: 'File',
        options: [{
          value: 'file_url',
          label: 'File URL',
          type: 'url'
        }, {
          value: 'file_path',
          label: 'File Path',
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
        label: 'Other',
        options: [{
          value: 'post_date',
          label: 'Upload Date',
          type: 'date'
        }, {
          value: 'post_modified',
          label: 'Modified Date',
          type: 'date'
        }, {
          value: 'post_parent',
          label: 'Attached To (Post ID)',
          type: 'number'
        }, {
          value: 'attached_post_title',
          label: 'Attached Post Title',
          type: 'string'
        }]
      }]);
    }

    // Menus (classic nav_menu / nav_menu_item export)
    if (contentType === 'menu' || contentType === 'menus' || contentType === 'nav_menu') {
      return translateGroups([{
        label: 'Basic',
        options: [{
          value: 'name',
          label: 'Menu Name',
          type: 'string'
        }, {
          value: 'slug',
          label: 'Menu Slug',
          type: 'string'
        }, {
          value: 'description',
          label: 'Description',
          type: 'string'
        }, {
          value: 'count',
          label: 'Item Count',
          type: 'number'
        }]
      }, {
        label: 'Items',
        options: [{
          value: 'menu_items',
          label: 'Menu Items (JSON)',
          type: 'json'
        }]
      }]);
    }

    // Pages (no taxonomies, only custom fields)
    if (contentType === 'page') {
      return translateGroups([].concat(baseFields, [{
        label: 'Custom Fields (Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }]));
    }

    // Users
    if (contentType === 'user') {
      return [{
        label: 'Basic',
        options: [{
          value: 'ID',
          label: 'User ID',
          type: 'number'
        }, {
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
        }, {
          value: 'avatar_url',
          label: 'Avatar URL',
          type: 'url'
        }]
      }, {
        label: 'Social Media',
        options: [{
          value: 'facebook',
          label: 'Facebook',
          type: 'string'
        }, {
          value: 'instagram',
          label: 'Instagram',
          type: 'string'
        }, {
          value: 'linkedin',
          label: 'LinkedIn',
          type: 'string'
        }, {
          value: 'myspace',
          label: 'MySpace',
          type: 'string'
        }, {
          value: 'pinterest',
          label: 'Pinterest',
          type: 'string'
        }, {
          value: 'soundcloud',
          label: 'SoundCloud',
          type: 'string'
        }, {
          value: 'tumblr',
          label: 'Tumblr',
          type: 'string'
        }, {
          value: 'wikipedia',
          label: 'Wikipedia',
          type: 'string'
        }, {
          value: 'twitter',
          label: 'Twitter/X',
          type: 'string'
        }, {
          value: 'youtube',
          label: 'YouTube',
          type: 'string'
        }]
      }, {
        label: 'Role & Permissions',
        options: [{
          value: 'role',
          label: 'Role',
          type: 'string'
        }, {
          value: 'roles',
          label: 'Roles (comma-separated)',
          type: 'string'
        }, {
          value: 'capabilities',
          label: 'Capabilities (JSON)',
          type: 'string'
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
        }]
      }, {
        label: 'Custom Fields (User Meta)',
        options: [{
          value: 'user_meta',
          label: 'User Meta (JSON)',
          type: 'json'
        }, {
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }

    // Comments
    if (contentType === 'comment' || contentType === 'comments') {
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
          label: 'Status (1/0/spam)',
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
          type: 'email'
        }, {
          value: 'comment_author_url',
          label: 'Author URL',
          type: 'url'
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
          type: 'datetime'
        }, {
          value: 'comment_date_gmt',
          label: 'Comment Date (GMT)',
          type: 'datetime'
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
        label: 'Custom Fields (Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }

    // WooCommerce Products
    if (contentType === 'product' || contentType === 'woo_product') {
      return [{
        label: 'Basic Info',
        options: [{
          value: 'ID',
          label: 'Product ID',
          type: 'number'
        }, {
          value: 'post_title',
          label: 'Product Title',
          type: 'string'
        }, {
          value: 'post_name',
          label: 'Slug',
          type: 'string'
        }, {
          value: 'post_status',
          label: 'Status (publish, draft, pending)',
          type: 'string'
        }, {
          value: 'post_author',
          label: 'Author ID',
          type: 'number'
        }, {
          value: 'post_content',
          label: 'Description',
          type: 'string'
        }, {
          value: 'post_excerpt',
          label: 'Short Description',
          type: 'string'
        }, {
          value: 'comment_status',
          label: 'Reviews Enabled',
          type: 'string'
        }]
      }, {
        label: 'Product Data',
        options: [{
          value: 'sku',
          label: 'SKU',
          type: 'string'
        }, {
          value: 'regular_price',
          label: 'Regular Price',
          type: 'number'
        }, {
          value: 'sale_price',
          label: 'Sale Price',
          type: 'number'
        }, {
          value: 'product_type',
          label: 'Product Type (simple, variable, grouped, external)',
          type: 'string'
        }, {
          value: 'downloadable',
          label: 'Downloadable (yes, no)',
          type: 'string'
        }, {
          value: 'virtual',
          label: 'Virtual (yes, no)',
          type: 'string'
        }, {
          value: 'featured',
          label: 'Featured (yes, no)',
          type: 'string'
        }, {
          value: 'visibility',
          label: 'Catalog Visibility',
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
          label: 'Stock Status (instock, outofstock, onbackorder)',
          type: 'string'
        }, {
          value: 'manage_stock',
          label: 'Manage Stock (yes, no)',
          type: 'string'
        }, {
          value: 'backorders',
          label: 'Backorders (yes, no, notify)',
          type: 'string'
        }]
      }, {
        label: 'Tax',
        options: [{
          value: 'tax_status',
          label: 'Tax Status (taxable, shipping, none)',
          type: 'string'
        }, {
          value: 'tax_class',
          label: 'Tax Class',
          type: 'string'
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
        label: 'Images',
        options: [{
          value: 'featured_image_id',
          label: 'Featured Image ID',
          type: 'number'
        }, {
          value: 'featured_image_url',
          label: 'Featured Image URL',
          type: 'url'
        }, {
          value: 'featured_image_title',
          label: 'Featured Image Title',
          type: 'string'
        }, {
          value: 'featured_image_caption',
          label: 'Featured Image Caption',
          type: 'string'
        }, {
          value: 'product_gallery',
          label: 'Gallery Image IDs (comma-separated)',
          type: 'string'
        }]
      }, {
        label: 'Categories & Tags',
        options: [{
          value: 'product_cat',
          label: 'Categories (comma-separated)',
          type: 'string'
        }, {
          value: 'product_tag',
          label: 'Tags (comma-separated)',
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
        }]
      }, {
        label: 'Stats',
        options: [{
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
        label: 'SEO (Yoast)',
        options: [{
          value: '_yoast_wpseo_title',
          label: 'SEO Title',
          type: 'string'
        }, {
          value: '_yoast_wpseo_metadesc',
          label: 'Meta Description',
          type: 'string'
        }, {
          value: '_yoast_wpseo_focuskw',
          label: 'Focus Keyword',
          type: 'string'
        }, {
          value: '_yoast_wpseo_canonical',
          label: 'Canonical URL',
          type: 'url'
        }, {
          value: '_yoast_wpseo_meta-robots-noindex',
          label: 'Meta Robots No Index',
          type: 'string'
        }, {
          value: '_yoast_wpseo_meta-robots-nofollow',
          label: 'Meta Robots No Follow',
          type: 'string'
        }, {
          value: '_yoast_wpseo_opengraph-title',
          label: 'OpenGraph Title',
          type: 'string'
        }, {
          value: '_yoast_wpseo_opengraph-description',
          label: 'OpenGraph Description',
          type: 'string'
        }, {
          value: '_yoast_wpseo_opengraph-image',
          label: 'OpenGraph Image',
          type: 'url'
        }, {
          value: '_yoast_wpseo_twitter-title',
          label: 'Twitter Title',
          type: 'string'
        }, {
          value: '_yoast_wpseo_twitter-description',
          label: 'Twitter Description',
          type: 'string'
        }, {
          value: '_yoast_wpseo_twitter-image',
          label: 'Twitter Image',
          type: 'url'
        }]
      }, {
        label: 'Custom Fields (Product Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }

    // Taxonomy Terms
    if (contentType === 'taxonomy' || contentType === 'taxonomy_term' || contentType === 'term') {
      return [{
        label: 'Basic',
        options: [{
          value: 'term_id',
          label: 'Term ID',
          type: 'number'
        }, {
          value: 'name',
          label: 'Name',
          type: 'string'
        }, {
          value: 'slug',
          label: 'Slug',
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
          label: 'Taxonomy',
          type: 'string'
        }, {
          value: 'term_taxonomy_id',
          label: 'Term Taxonomy ID',
          type: 'number'
        }]
      }, {
        label: 'Hierarchy',
        options: [{
          value: 'parent',
          label: 'Parent Term ID',
          type: 'number'
        }]
      }, {
        label: 'Stats',
        options: [{
          value: 'count',
          label: 'Count',
          type: 'number'
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
          label: 'Attribute Name / Slug (e.g. color)',
          type: 'string'
        }, {
          value: 'attribute_label',
          label: 'Attribute Label (e.g. Color)',
          type: 'string'
        }, {
          value: 'attribute_type',
          label: 'Type (select, text)',
          type: 'string'
        }]
      }, {
        label: 'Settings',
        options: [{
          value: 'attribute_orderby',
          label: 'Order By (menu_order, name, name_num, id)',
          type: 'string'
        }, {
          value: 'attribute_public',
          label: 'Enable Archives (1/0)',
          type: 'boolean'
        }]
      }, {
        label: 'Terms',
        options: [{
          value: 'attribute_terms',
          label: 'Attribute Terms (JSON array or comma-separated names)',
          type: 'string'
        }, {
          value: 'term_count',
          label: 'Term Count',
          type: 'number'
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
          label: 'Status (publish, draft, pending)',
          type: 'string'
        }]
      }, {
        label: 'Discount',
        options: [{
          value: 'discount_type',
          label: 'Discount Type (percent, fixed_cart, fixed_product)',
          type: 'string'
        }, {
          value: 'coupon_amount',
          label: 'Amount',
          type: 'number'
        }, {
          value: 'free_shipping',
          label: 'Free Shipping (1/0)',
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
          label: 'Individual Use Only (1/0)',
          type: 'boolean'
        }, {
          value: 'exclude_sale_items',
          label: 'Exclude Sale Items (1/0)',
          type: 'boolean'
        }]
      }, {
        label: 'Product Restrictions',
        options: [{
          value: 'product_ids',
          label: 'Allowed Products (JSON or comma-separated IDs)',
          type: 'string'
        }, {
          value: 'excluded_product_ids',
          label: 'Excluded Products (JSON or comma-separated IDs)',
          type: 'string'
        }, {
          value: 'product_categories',
          label: 'Allowed Categories (JSON or comma-separated IDs)',
          type: 'string'
        }, {
          value: 'excluded_product_categories',
          label: 'Excluded Categories (JSON or comma-separated IDs)',
          type: 'string'
        }]
      }, {
        label: 'Email Restrictions',
        options: [{
          value: 'allowed_emails',
          label: 'Allowed Emails (JSON or comma-separated)',
          type: 'string'
        }]
      }, {
        label: 'Usage Limits',
        options: [{
          value: 'usage_limit',
          label: 'Usage Limit (total)',
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
          label: 'Expiry Date (YYYY-MM-DD)',
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
        label: 'Custom Fields (Meta)',
        options: [{
          value: 'meta',
          label: 'Custom Field',
          type: 'meta',
          custom: true
        }]
      }];
    }

    // Default - return post fields with taxonomies and custom fields
    return translateGroups([].concat(baseFields, [{
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
    }]));
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
    var html = "\n\t\t\t<div class=\"aie-mapping-row\" data-source-index=\"".concat(sourceIndex, "\" data-target-field=\"").concat(targetField, "\">\n\t\t\t\t<div class=\"aie-source-col\">\n\t\t\t\t\t<span class=\"dashicons dashicons-media-spreadsheet\"></span>\n\t\t\t\t\t<strong>").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(sourceField), "</strong>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-arrow\">\u2192</div>\n\t\t\t\t<div class=\"aie-target-col\">\n\t\t\t\t\t<span class=\"dashicons dashicons-wordpress\"></span>\t\t\t\t<strong>").concat(targetField, "</strong>\n\t\t\t</div>\n\t\t\t").concat(functionsHtml, "\n\t\t\t<div class=\"aie-mapping-actions\">\n\t\t\t\t<button type=\"button\" class=\"button button-small aie-add-function\" data-source-index=\"").concat(sourceIndex, "\" data-target-field=\"").concat(targetField, "\" title=\"").concat(window.aieData.i18n.addTransformationFunction || 'Add transformation function', "\">\n\t\t\t\t\t<span class=\"dashicons dashicons-admin-tools\"></span>\n\t\t\t\t</button>\n\t\t\t\t<button type=\"button\" class=\"button button-small aie-remove-row-mapping\" data-source-index=\"").concat(sourceIndex, "\" data-target-field=\"").concat(targetField, "\" title=\"").concat(window.aieData.i18n.removeMapping || 'Remove mapping', "\">\n\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t</button>\n\t\t\t</div>\n\t\t</div>\n\t\t");
    $container.append(html);
  },
  /**
   * Update mapping statistics
   */
  updateMappingStats: function updateMappingStats() {
    var _this$fileData, _this$fileData$column;
    var totalSourceFields = ((_this$fileData = this.fileData) === null || _this$fileData === void 0 ? void 0 : (_this$fileData$column = _this$fileData.columns) === null || _this$fileData$column === void 0 ? void 0 : _this$fileData$column.length) || 0;

    // Count unique source fields that are used
    var usedSourceIndexes = new Set();
    jQuery('.aie-mapping-row').each(function () {
      var sourceIndex = jQuery(this).data('source-index');
      // Only add if sourceIndex is defined (skip if undefined/null)
      if (sourceIndex !== undefined && sourceIndex !== null && sourceIndex !== '') {
        usedSourceIndexes.add(sourceIndex);
      }
    });
    var mappedCount = usedSourceIndexes.size;

    // Show: "X / Y fields mapped" where Y is total source columns
    jQuery('.aie-mapped-count').text(mappedCount);
    jQuery('.aie-total-fields').text(totalSourceFields);

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
    var _this9 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee3() {
      var mappingKey, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee3$(_context3) {
        while (1) {
          switch (_context3.prev = _context3.next) {
            case 0:
              // Get mapping key
              mappingKey = "".concat(sourceIndex, "-").concat(targetField); // Get current functions for this mapping
              if (!_this9.mappingFunctions) {
                _this9.mappingFunctions = {};
              }
              if (!_this9.mappingFunctions[mappingKey]) {
                _this9.mappingFunctions[mappingKey] = [];
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
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.failedToLoadFunctions || 'Failed to load functions', 'error');
              return _context3.abrupt("return");
            case 10:
              _this9.showFunctionModal(sourceIndex, targetField, response.data);
              _context3.next = 16;
              break;
            case 13:
              _context3.prev = 13;
              _context3.t0 = _context3["catch"](3);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice((aieData.i18n.errorLoadingFunctions || 'Error loading functions') + ': ' + _context3.t0.message, 'error');
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
    var modalHtml = "\n\t\t<div id=\"aie-field-functions-modal\" class=\"aie-modal\" style=\"display:flex;\">\n\t\t\t<div class=\"aie-modal-backdrop\"></div>\n\t\t\t<div class=\"aie-modal-content aie-field-functions-modal-content\">\n\t\t\t\t<div class=\"aie-modal-header\">\n\t\t\t\t\t<h2 class=\"aie-modal-title\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-admin-generic\"></span>\n\t\t\t\t\t\t".concat(window.aieData.i18n.fieldTransformationFunctions || 'Field Transformation Functions', "\n\t\t\t\t\t</h2>\n\t\t\t\t\t<button type=\"button\" class=\"aie-modal-close\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-modal-body\">\n\t\t\t\t\t<!-- Field Info -->\n\t\t\t\t\t<div class=\"aie-field-info\">\n\t\t\t\t\t\t<div class=\"aie-field-info-item\">\n\t\t\t\t\t\t\t<strong>").concat(window.aieData.i18n.field || 'Field', ":</strong>\n\t\t\t\t\t\t\t<span class=\"aie-current-field-label\">").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(sourceField), "</span>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t<div class=\"aie-field-info-item\">\n\t\t\t\t\t\t\t<strong>").concat(window.aieData.i18n.type || 'Type', ":</strong>\n\t\t\t\t\t\t\t<span class=\"aie-current-field-type\">").concat(targetField, "</span>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\n\t\t\t\t\t<!-- Applied Functions List -->\n\t\t\t\t\t<div class=\"aie-applied-functions\">\n\t\t\t\t\t\t<h3>\n\t\t\t\t\t\t\t").concat(window.aieData.i18n.appliedFunctions || 'Applied Functions', "\n\t\t\t\t\t\t\t<span class=\"aie-functions-count\">(0)</span>\n\t\t\t\t\t\t</h3>\n\t\t\t\t\t\t\n\t\t\t\t\t\t<div class=\"aie-functions-pipeline\" id=\"aie-functions-pipeline\">\n\t\t\t\t\t\t\t<div class=\"aie-no-functions\">\n\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t\t\t\t\t<p>").concat(window.aieData.i18n.noFunctionsApplied || 'No functions applied yet. Add functions from the list below.', "</p>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t\n\t\t\t\t\t\t\t<div class=\"aie-function-items\" id=\"aie-function-items\">\n\t\t\t\t\t\t\t\t<!-- Functions will be added here -->\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t<div class=\"aie-pipeline-hint\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t\t\t\t").concat(window.aieData.i18n.functionsAppliedInOrder || 'Functions are applied in order from top to bottom. Drag to reorder.', "\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\n\t\t\t\t\t<!-- Available Functions -->\n\t\t\t\t\t<div class=\"aie-available-functions\">\n\t\t\t\t\t\t<h3>").concat(window.aieData.i18n.availableFunctions || 'Available Functions', "</h3>\n\t\t\t\t\t\t\n\t\t\t\t\t\t<!-- Search Functions -->\n\t\t\t\t\t\t<div class=\"aie-functions-search\">\n\t\t\t\t\t\t\t<input \n\t\t\t\t\t\t\t\ttype=\"text\" \n\t\t\t\t\t\t\t\tid=\"aie-functions-search\" \n\t\t\t\t\t\t\t\tclass=\"regular-text\" \n\t\t\t\t\t\t\t\tplaceholder=\"").concat(window.aieData.i18n.searchFunctions || 'Search functions...', "\"\n\t\t\t\t\t\t\t>\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-search\"></span>\n\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t<!-- Functions Filter -->\n\t\t\t\t\t\t<div class=\"aie-functions-filter\">\n\t\t\t\t\t\t\t<label>\n\t\t\t\t\t\t\t\t<input type=\"radio\" name=\"functions-filter\" value=\"all\" checked>\n\t\t\t\t\t\t\t\t").concat(window.aieData.i18n.all || 'All', "\n\t\t\t\t\t\t\t</label>\n\t\t\t\t\t\t\t<label>\n\t\t\t\t\t\t\t\t<input type=\"radio\" name=\"functions-filter\" value=\"library\">\n\t\t\t\t\t\t\t\t").concat(window.aieData.i18n.library || 'Library', "\n\t\t\t\t\t\t\t</label>\n\t\t\t\t\t\t\t<label>\n\t\t\t\t\t\t\t\t<input type=\"radio\" name=\"functions-filter\" value=\"custom\">\n\t\t\t\t\t\t\t\t").concat(window.aieData.i18n.custom || 'Custom', "\n\t\t\t\t\t\t\t</label>\n\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t<!-- Functions List -->\n\t\t\t\t\t\t<div class=\"aie-functions-list\" id=\"aie-functions-list\">\n\t\t\t\t\t\t\t<div class=\"aie-functions-loading\">\n\t\t\t\t\t\t\t\t<span class=\"spinner is-active\"></span>\n\t\t\t\t\t\t\t\t<p>").concat(window.aieData.i18n.loadingFunctions || 'Loading functions...', "</p>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t<!-- Quick Add Link -->\n\t\t\t\t\t\t<div class=\"aie-functions-quick-add\">\n\t\t\t\t\t\t\t<a href=\"#\" class=\"aie-create-new-function\">\n\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-plus-alt\"></span>\n\t\t\t\t\t\t\t\t").concat(window.aieData.i18n.createNewFunction || 'Create New Function', "\n\t\t\t\t\t\t\t</a>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\n\t\t\t\t\t<!-- Preview Section -->\n\t\t\t\t\t<div class=\"aie-function-preview\">\n\t\t\t\t\t\t<h3>").concat(window.aieData.i18n.previewTransformation || 'Preview Transformation', "</h3>\n\t\t\t\t\t\t\n\t\t\t\t\t\t<div class=\"aie-preview-controls\">\n\t\t\t\t\t\t\t<div class=\"aie-preview-input-group\">\n\t\t\t\t\t\t\t\t<label for=\"aie-preview-input\">\n\t\t\t\t\t\t\t\t\t").concat(window.aieData.i18n.testValue || 'Test Value', ":\n\t\t\t\t\t\t\t\t</label>\n\t\t\t\t\t\t\t\t<input \n\t\t\t\t\t\t\t\t\ttype=\"text\" \n\t\t\t\t\t\t\t\t\tid=\"aie-preview-input\" \n\t\t\t\t\t\t\t\t\tclass=\"regular-text\" \n\t\t\t\t\t\t\t\t\tplaceholder=\"").concat(window.aieData.i18n.enterTestValue || 'Enter test value...', "\"\n\t\t\t\t\t\t\t\t>\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t\t<button type=\"button\" class=\"button aie-test-pipeline\">\n\t\t\t\t\t\t\t\t<span class=\"dashicons dashicons-media-code\"></span>\n\t\t\t\t\t\t\t\t").concat(window.aieData.i18n.testPipeline || 'Test Pipeline', "\n\t\t\t\t\t\t\t</button>\n\t\t\t\t\t\t</div>\n\n\t\t\t\t\t\t<div class=\"aie-preview-result\" id=\"aie-preview-result\" style=\"display:none;\">\n\t\t\t\t\t\t\t<div class=\"aie-preview-steps\">\n\t\t\t\t\t\t\t\t<!-- Steps will be added dynamically -->\n\t\t\t\t\t\t\t</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-modal-footer\">\n\t\t\t\t\t<button type=\"button\" class=\"button button-secondary aie-modal-cancel\">\n\t\t\t\t\t\t").concat(window.aieData.i18n.cancel || 'Cancel', "\n\t\t\t\t\t</button>\n\t\t\t\t\t<button type=\"button\" class=\"button button-primary aie-save-field-functions\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-yes\"></span>\n\t\t\t\t\t\t").concat(window.aieData.i18n.applyFunctions || 'Apply Functions', "\n\t\t\t\t\t</button>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t</div>\n\t");

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
    var _this10 = this;
    var $container = jQuery('#aie-function-items');
    $container.empty();
    currentFunctions.forEach(function (func) {
      _this10.addFunctionToPipeline(func, false);
    });
    this.updateFunctionsCount(currentFunctions.length);
    this.toggleNoFunctionsMessage();
  },
  /**
   * Render available functions (like export)
   */
  renderAvailableFunctions: function renderAvailableFunctions(functionsData) {
    var _this11 = this;
    var $container = jQuery('#aie-functions-list');
    $container.empty();

    // Get all snippets
    var snippets = functionsData.snippets || {};
    if (Object.keys(snippets).length === 0) {
      $container.html("\n\t\t\t<div class=\"aie-functions-empty-state\">\n\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t<p>".concat(window.aieData.i18n.noFunctionsAvailableYet || 'No functions available yet.', "</p>\n\t\t\t</div>\n\t\t"));
      return;
    }

    // Store for later use
    this.availableFunctions = snippets;
    Object.entries(snippets).forEach(function (_ref) {
      var _ref2 = _slicedToArray(_ref, 2),
        key = _ref2[0],
        snippet = _ref2[1];
      var item = jQuery('<div>').addClass('aie-function-list-item').attr('data-function-id', key).attr('data-category', snippet.category || 'custom').html("\t\t\t\t<div class=\"aie-function-list-info\">\n\t\t\t\t\t<span class=\"aie-function-list-name\">".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(snippet.name), "</span>\n\t\t\t\t\t<span class=\"aie-function-list-desc\">").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(snippet.description || ''), "</span>\n\t\t\t\t</div>\n\t\t\t\t<button type=\"button\" class=\"button button-small aie-add-function-btn\">").concat(window.aieData.i18n.add || 'Add', "</button>\n\t\t\t"));
      item.find('.aie-add-function-btn').on('click', function () {
        _this11.addFunctionToPipeline({
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
    var _this12 = this;
    var updateArray = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : true;
    var $container = jQuery('#aie-function-items');
    var item = jQuery('<div>').addClass('aie-function-item').attr('data-function-id', func.id).html("\n\t\t\t\t<span class=\"aie-function-handle dashicons dashicons-menu\"></span>\n\t\t\t\t<div class=\"aie-function-info\">\n\t\t\t\t\t<strong class=\"aie-function-name\">".concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(func.name), "</strong>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-function-actions\">\n\t\t\t\t\t<button type=\"button\" class=\"button-small aie-remove-function\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-no-alt\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t</div>\n\t\t\t"));

    // Remove function event
    item.find('.aie-remove-function').on('click', function () {
      item.remove();
      _this12.updateFunctionsCount();
      _this12.toggleNoFunctionsMessage();
    });
    $container.append(item);

    // Refresh sortable to include the new item
    if ($container.data('ui-sortable')) {
      $container.sortable('refresh');
    }
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
      if (typeof aieData !== 'undefined' && aieData.functionsUrl) {
        window.open(aieData.functionsUrl, '_blank');
      } else {
        window.open('/wp-admin/admin.php?page=wp-aie-functions', '_blank');
      }
    });
    jQuery('.aie-test-pipeline').on('click', function () {
      var testValue = jQuery('#aie-preview-input').val();
      if (!testValue) {
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.enterTestValue || 'Please enter a test value', 'warning');
        return;
      }
      var functions = [];
      jQuery('#aie-function-items .aie-function-item').each(function () {
        functions.push(jQuery(this).data('function-id'));
      });
      if (functions.length === 0) {
        _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.pleaseAddAtLeastOneFunction || 'Please add at least one function to test', 'warning');
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

    // Initialize sortable for drag & drop reordering
    this.initFunctionPipelineSortable();
  },
  /**
   * Initialize sortable for function pipeline
   */
  initFunctionPipelineSortable: function initFunctionPipelineSortable() {
    var $container = jQuery('#aie-function-items');
    if (!$container.length || !jQuery.fn.sortable) return;

    // Destroy existing instance if present
    if ($container.data('ui-sortable')) {
      $container.sortable('destroy');
    }
    $container.sortable({
      handle: '.aie-function-handle',
      placeholder: 'aie-function-item-placeholder',
      axis: 'y'
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
              $steps.html("<div class=\"aie-preview-loading\"><span class=\"spinner is-active\"></span> ".concat(window.aieData.i18n.testing, "</div>"));
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
                html += "\n\t\t\t\t<div class=\"aie-preview-step\">\n\t\t\t\t\t<div class=\"aie-step-label\">".concat(window.aieData.i18n.initialValue || 'Initial Value', ":</div>\n\t\t\t\t\t<div class=\"aie-step-value\">").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(response.data.initial || testValue), "</div>\n\t\t\t\t</div>\n\t\t\t");

                // Each step
                response.data.steps.forEach(function (step, index) {
                  var stepNum = index + 1;
                  html += "\n\t\t\t\t\t<div class=\"aie-preview-step\">\n\t\t\t\t\t\t<div class=\"aie-step-label\">".concat(stepNum, ". ").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(step.function_name), ":</div>\n\t\t\t\t\t\t<div class=\"aie-step-value\">").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(step.output), "</div>\n\t\t\t\t\t</div>\n\t\t\t\t");
                });

                // Final result
                html += "\n\t\t\t\t<div class=\"aie-preview-step aie-preview-final\">\n\t\t\t\t\t<div class=\"aie-step-label\">".concat(window.aieData.i18n.finalResult || 'Final Result', ":</div>\n\t\t\t\t\t<div class=\"aie-step-value\"><strong>").concat(_utils__WEBPACK_IMPORTED_MODULE_1__["default"].escapeHtml(response.data["final"]), "</strong></div>\n\t\t\t\t</div>\n\t\t\t");
                $steps.html(html);
              } else {
                $steps.html("<div class=\"notice notice-error inline\"><p>".concat(((_response$data = response.data) === null || _response$data === void 0 ? void 0 : _response$data.message) || window.aieData.i18n.failedTestPipeline, "</p></div>"));
              }
              _context4.next = 14;
              break;
            case 11:
              _context4.prev = 11;
              _context4.t0 = _context4["catch"](4);
              $steps.html("<div class=\"notice notice-error inline\"><p>".concat(window.aieData.i18n.error, ": ").concat(_context4.t0.message, "</p></div>"));
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
    var $sourceSearch = jQuery('.aie-search-source');
    $sourceSearch.on('input', function () {
      var query = jQuery(this).val().toLowerCase();
      jQuery('.aie-field-card').each(function () {
        var fieldName = jQuery(this).find('.aie-field-name').text().toLowerCase();
        jQuery(this).toggle(fieldName.includes(query));
      });
    });

    // Clear source search button
    $sourceSearch.parent().find('.aie-clear-search').on('click', function (e) {
      e.preventDefault();
      $sourceSearch.val('').focus().trigger('input');
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
    var $targetSearch = jQuery('.aie-search-target');
    $targetSearch.on('keyup input', performSearch);

    // Clear target search button
    $targetSearch.parent().find('.aie-clear-search').on('click', function (e) {
      e.preventDefault();
      $targetSearch.val('').focus().trigger('input');
    });
  },
  /**
   * Get target fields for content type
   */
  getTargetFields: function getTargetFields(contentType) {
    var fields = {
      post: [{
        value: 'post_title',
        label: window.aieData.i18n.fieldTitle || 'Title'
      }, {
        value: 'post_content',
        label: window.aieData.i18n.fieldContent || 'Content'
      }, {
        value: 'post_excerpt',
        label: window.aieData.i18n.fieldExcerpt || 'Excerpt'
      }, {
        value: 'post_status',
        label: window.aieData.i18n.fieldStatus || 'Status'
      }, {
        value: 'post_author',
        label: window.aieData.i18n.fieldAuthor || 'Author'
      }, {
        value: 'post_date',
        label: window.aieData.i18n.fieldDate || 'Date'
      }, {
        value: 'post_name',
        label: window.aieData.i18n.fieldSlug || 'Slug'
      }, {
        value: 'categories',
        label: window.aieData.i18n.fieldCategories || 'Categories'
      }, {
        value: 'tags',
        label: window.aieData.i18n.fieldTags || 'Tags'
      }, {
        value: 'featured_image',
        label: window.aieData.i18n.fieldFeaturedImage || 'Featured Image'
      }],
      media: [{
        value: 'post_title',
        label: window.aieData.i18n.fieldTitle || 'Title'
      }, {
        value: 'post_content',
        label: window.aieData.i18n.fieldDescription || 'Description'
      }, {
        value: 'post_excerpt',
        label: window.aieData.i18n.fieldCaption || 'Caption'
      }, {
        value: 'alt_text',
        label: window.aieData.i18n.fieldAltText || 'Alt Text'
      }, {
        value: 'guid',
        label: 'GUID'
      }]
    };
    return fields[contentType] || fields.post;
  },
  /**
   * Auto-map fields
   */
  autoMapFields: function autoMapFields() {
    var _this13 = this;
    // Clear existing mappings
    this.clearFieldMapping();

    // Wait for DOM to be fully ready before mapping
    setTimeout(function () {
      // PASS 0: Auto-create target fields for prefixed source columns
      // (taxonomy_*, meta_*, acf_*) that don't yet have a matching target.
      // This lets Pass 1 (exact match) pick them up automatically.
      jQuery('.aie-field-card').each(function (index, sourceCard) {
        var $sourceCard = jQuery(sourceCard);
        var sourceField = $sourceCard.data('source-field');
        if (!sourceField) return;

        // Skip if a target field with this exact name already exists.
        if (jQuery(".aie-target-field[data-target-field=\"".concat(sourceField, "\"]")).length) return;
        var fieldType = null;
        if (sourceField.startsWith('taxonomy_')) {
          fieldType = 'taxonomy';
        } else if (sourceField.startsWith('acf_')) {
          fieldType = 'meta';
        } else if (sourceField.startsWith('meta_')) {
          fieldType = 'meta';
        }
        if (!fieldType) return;

        // Find the template group for this field type.
        var $template = jQuery(".aie-custom-field-template[data-field-type=\"".concat(fieldType, "\"]")).first();
        if (!$template.length) return;

        // For taxonomy fields, default format is 'name' (how this plugin exports terms).
        var taxonomyFormat = fieldType === 'taxonomy' ? 'name' : '';

        // Add a real target field with the full prefixed name so Pass 1 can exact-match it.
        _this13.addCustomFieldToGroup($template, sourceField, fieldType, false, taxonomyFormat);
      });

      // PASS 1: Map exact matches first (highest priority)
      jQuery('.aie-field-card').each(function (index, sourceCard) {
        var $sourceCard = jQuery(sourceCard);
        var sourceField = $sourceCard.data('source-field');
        var sourceIndex = $sourceCard.data('source-index');
        if (!sourceField) return;
        var sourceFieldLower = sourceField.toLowerCase();
        var matched = false;

        // Look for EXACT match only
        jQuery('.aie-target-field:not(.aie-custom-field-template)').each(function (i, targetField) {
          if (matched) return;
          var $targetField = jQuery(targetField);
          var targetFieldData = $targetField.data('target-field');
          if (!targetFieldData) return;
          if ($targetField.hasClass('has-mapping')) return;
          var targetFieldValue = targetFieldData.toLowerCase();

          // ONLY exact match in pass 1
          if (sourceFieldLower === targetFieldValue) {
            _this13.createMapping($sourceCard.data('source-field'), sourceIndex, $targetField.data('target-field'), $targetField.data('field-type'), $targetField);
            $sourceCard.addClass('used mapped');
            matched = true;
          }
        });
      });

      // PASS 2: Map remaining fields with fuzzy matching
      jQuery('.aie-field-card:not(.used)').each(function (index, sourceCard) {
        var $sourceCard = jQuery(sourceCard);
        var sourceField = $sourceCard.data('source-field');
        var sourceIndex = $sourceCard.data('source-index');
        if (!sourceField) return;
        var sourceFieldLower = sourceField.toLowerCase();
        var matched = false;
        jQuery('.aie-target-field:not(.aie-custom-field-template)').each(function (i, targetField) {
          if (matched) return;
          var $targetField = jQuery(targetField);
          var targetFieldData = $targetField.data('target-field');
          if (!targetFieldData) return;

          // Skip already mapped target fields
          if ($targetField.hasClass('has-mapping')) return;
          var targetFieldValue = targetFieldData.toLowerCase();
          var targetLabel = $targetField.find('.aie-field-label').text().toLowerCase();

          // Fuzzy matching: label match, normalized match, or partial match
          var matchType = null;
          if (sourceFieldLower === targetLabel) {
            matchType = 'label';
          } else if (sourceFieldLower.replace(/_/g, ' ') === targetLabel) {
            matchType = 'normalized';
          } else if (sourceFieldLower.includes(targetFieldValue) && targetFieldValue.length > 2) {
            matchType = 'partial';
          } else if (targetFieldValue.includes(sourceFieldLower) && sourceFieldLower.length > 2) {
            matchType = 'partial';
          }
          if (matchType) {
            _this13.createMapping($sourceCard.data('source-field'), sourceIndex, $targetField.data('target-field'), $targetField.data('field-type'), $targetField);
            $sourceCard.addClass('used mapped');
            matched = true;
          }
        });
      });
    }, 50);

    // Use setTimeout to ensure DOM is fully updated before counting
    setTimeout(function () {
      _this13.updateMappingStats();

      // Count actual mapped fields from DOM
      var usedSourceIndexes = new Set();
      jQuery('.aie-mapping-row').each(function () {
        usedSourceIndexes.add(jQuery(this).data('source-index'));
      });
      var mappedCount = usedSourceIndexes.size;
      var message = (window.aieData.i18n.autoMappedFields || 'Auto-mapped %d fields').replace('%d', mappedCount);
      _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(message, 'success');
    }, 150);
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
    jQuery('.aie-mapped-fields').html("\n\t\t\t<div class=\"aie-empty-state\">\n\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t<p>".concat(window.aieData.i18n.dragSourceColumns || 'Drag source columns to WordPress fields to create mappings', "</p>\n\t\t\t</div>\n\t\t"));

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
        var entry = {
          source_index: sourceIndex,
          source_field: sourceField,
          target_field: targetField,
          function_id: $row.data('function-id') || null
        };

        // Include taxonomy format when the target is a taxonomy field
        var $targetEl = jQuery(".aie-target-field[data-target-field=\"".concat(targetField, "\"]"));
        if ($targetEl.data('field-type') === 'taxonomy' || $targetEl.data('taxonomy-format')) {
          entry.taxonomy_format = $targetEl.data('taxonomy-format') || 'name';
        }
        mapping.push(entry);
      }
    });
    return mapping;
  },
  /**
   * Start import
   */
  startImport: function startImport() {
    var _this14 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee5() {
      var contentType, uniqueField, data, response;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee5$(_context5) {
        while (1) {
          switch (_context5.prev = _context5.next) {
            case 0:
              _context5.prev = 0;
              contentType = jQuery('input[name="content_type"]:checked').val();
              uniqueField = jQuery('#aie-unique-field').val(); // Validate unique field selection (REQUIRED)
              if (uniqueField) {
                _context5.next = 6;
                break;
              }
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.pleaseSelectUniqueField || 'Please select a field to check for existing items', 'error');
              return _context5.abrupt("return");
            case 6:
              data = {
                file_path: _this14.fileData.file_path,
                import_type: contentType,
                format: _this14.fileData.format,
                delimiter: _this14.fileData.delimiter || ',',
                mapping: _this14.getFieldMapping(),
                options: {
                  duplicate_handling: jQuery('input[name="if_exists"]:checked').val() || 'update',
                  unique_field: uniqueField,
                  if_exists: jQuery('input[name="if_exists"]:checked').val() || 'update',
                  if_not_exists: jQuery('input[name="if_not_exists"]:checked').val() || 'create',
                  post_status: jQuery('[name="post_status"]').val(),
                  post_type: jQuery('[name="post_type"]').val(),
                  download_images: jQuery('[name="download_images"]').is(':checked'),
                  batch_size: parseInt(jQuery('[name="batch_size"]').val()) || 1,
                  auto_import_media: jQuery('#aie-auto-import-media').is(':checked'),
                  media_duplicate_mode: jQuery('input[name="media_duplicate_mode"]:checked').val() || 'skip'
                }
              }; // Add custom post type if selected
              if (contentType === 'custom_post_types') {
                data.options.custom_post_type = jQuery('#aie-custom-post-type').val();
              }

              // Add table name for database_table import
              if (contentType === 'database_table') {
                data.options.table_name = _this14.selectedTableName || jQuery('#aie-import-table-name').val();
              }
              _context5.next = 11;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_import_start', data);
            case 11:
              response = _context5.sent;
              _this14.jobId = response.job_id;
              _this14.importStartTime = Date.now();
              _this14.showStep(6);
              _this14.startBatchProcessing();
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.importStartedSuccessfully || 'Import started successfully', 'success');
              _context5.next = 22;
              break;
            case 19:
              _context5.prev = 19;
              _context5.t0 = _context5["catch"](0);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].handleError(_context5.t0, 'Start import');
            case 22:
            case "end":
              return _context5.stop();
          }
        }
      }, _callee5, null, [[0, 19]]);
    }))();
  },
  /**
   * Start batch processing
   */
  startBatchProcessing: function startBatchProcessing() {
    var _this15 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee6() {
      var _response$result, response, elapsedSec, percentage, processed, total, itemsPerSec, remainingSec, formatTime, progressData;
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee6$(_context6) {
        while (1) {
          switch (_context6.prev = _context6.next) {
            case 0:
              _context6.prev = 0;
              _context6.next = 3;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_import_process_batch', {
                job_id: _this15.jobId
              });
            case 3:
              response = _context6.sent;
              // Transform batch response to progress bar format
              elapsedSec = _this15.importStartTime ? (Date.now() - _this15.importStartTime) / 1000 : 0;
              percentage = response.progress || 0;
              processed = response.offset || 0;
              total = ((_response$result = response.result) === null || _response$result === void 0 ? void 0 : _response$result.total) || 0; // items/sec based on elapsed time
              itemsPerSec = elapsedSec > 0 ? processed / elapsedSec : 0; // Remaining estimate: based on items/sec and remaining items
              remainingSec = 0;
              if (itemsPerSec > 0 && total > processed) {
                remainingSec = (total - processed) / itemsPerSec;
              }
              formatTime = function formatTime(sec) {
                sec = Math.round(sec);
                if (sec < 60) return sec + 's';
                if (sec < 3600) return Math.floor(sec / 60) + 'm ' + sec % 60 + 's';
                return Math.floor(sec / 3600) + 'h ' + Math.floor(sec % 3600 / 60) + 'm';
              };
              progressData = {
                percentage: percentage,
                processed: processed,
                total: total,
                estimates: {
                  elapsed_formatted: formatTime(elapsedSec),
                  remaining_formatted: remainingSec > 0 ? formatTime(remainingSec) : '-',
                  items_per_second: itemsPerSec
                }
              }; // Update progress
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].updateProgressBar(jQuery('.aie-step-6'), progressData);
              if (response.completed) {
                // Import completed
                if (response.result) {
                  _this15.onImportComplete(response);
                } else {
                  _this15.onImportFailed(response);
                }
              } else {
                // Process next batch
                setTimeout(function () {
                  _this15.startBatchProcessing();
                }, 100);
              }
              _context6.next = 21;
              break;
            case 17:
              _context6.prev = 17;
              _context6.t0 = _context6["catch"](0);
              clearInterval(_this15.progressInterval);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].handleError(_context6.t0, 'Process batch');
            case 21:
            case "end":
              return _context6.stop();
          }
        }
      }, _callee6, null, [[0, 17]]);
    }))();
  },
  /**
   * Start progress tracking
   */
  startProgressTracking: function startProgressTracking() {
    // Not used anymore - batch processing updates progress directly
  },
  /**
   * Update import progress
   */
  updateProgress: function updateProgress() {
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee7() {
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee7$(_context7) {
        while (1) {
          switch (_context7.prev = _context7.next) {
            case 0:
            case "end":
              return _context7.stop();
          }
        }
      }, _callee7);
    }))();
  } // Not used anymore - batch processing updates progress directly
  ,
  /**
   * Handle import completion
   */
  onImportComplete: function onImportComplete(response) {
    var result = response.result || {};

    // Hide progress, show results
    jQuery('.aie-progress-container').hide();
    jQuery('.aie-import-results').show();
    jQuery('.aie-import-complete-card').fadeIn();

    // Update statistics
    jQuery('.aie-result-success').text(result.success || 0);
    jQuery('.aie-result-updated').text(result.updated || 0);
    jQuery('.aie-result-created').text(result.created || 0);
    jQuery('.aie-result-skipped').text(result.skipped || 0);
    jQuery('.aie-result-failed').text(result.failed || 0);

    // Calculate duration using the client-side start time for accuracy.
    // Fallback to server job timestamps only when the page was refreshed
    // mid-import (start time not in memory).
    var formatDuration = function formatDuration(sec) {
      sec = Math.max(0, Math.round(sec));
      if (sec < 60) return sec + 's';
      if (sec < 3600) return Math.floor(sec / 60) + 'm ' + sec % 60 + 's';
      return Math.floor(sec / 3600) + 'h ' + Math.floor(sec % 3600 / 60) + 'm';
    };
    if (this.importStartTime) {
      var durSec = (Date.now() - this.importStartTime) / 1000;
      jQuery('.aie-result-duration').text(formatDuration(durSec));
    } else {
      var jobData = response.job_data || {};
      if (jobData.started_at && jobData.completed_at) {
        var _durSec = (new Date(jobData.completed_at) - new Date(jobData.started_at)) / 1000;
        jQuery('.aie-result-duration').text(formatDuration(_durSec));
      }
    }

    // Update buttons
    jQuery('.aie-cancel-import').hide();
    jQuery('.aie-new-import').show();
  },
  /**
   * Handle import failure
   */
  onImportFailed: function onImportFailed(response) {
    _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice((aieData.i18n.importFailed || 'Import failed') + ': ' + (response.error || 'Unknown error'), 'error');
  },
  /**
   * Cancel import
   */
  cancelImport: function cancelImport() {
    var _this16 = this;
    return _asyncToGenerator( /*#__PURE__*/_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().mark(function _callee8() {
      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_0___default().wrap(function _callee8$(_context8) {
        while (1) {
          switch (_context8.prev = _context8.next) {
            case 0:
              if (confirm(window.aieData.i18n.confirmCancelImportStep)) {
                _context8.next = 2;
                break;
              }
              return _context8.abrupt("return");
            case 2:
              _context8.prev = 2;
              _context8.next = 5;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_import_cancel', {
                job_id: _this16.jobId
              });
            case 5:
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(aieData.i18n.importCancelled || 'Import cancelled', 'info');
              _this16.resetWizard();
              _context8.next = 12;
              break;
            case 9:
              _context8.prev = 9;
              _context8.t0 = _context8["catch"](2);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].handleError(_context8.t0, 'Cancel import');
            case 12:
            case "end":
              return _context8.stop();
          }
        }
      }, _callee8, null, [[2, 9]]);
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
    this.importStartTime = null;
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
      error: function error(xhr, status, _error5) {}
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
    var excludedTypes = ['media', 'user', 'comment', 'menu', 'taxonomy', 'database_table', 'woo_attribute', 'woo_coupon', 'woo_order'];
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
      error: function error(xhr, status, _error6) {}
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

    // Toggle button state initially
    this.toggleStartImportButton();

    // Add change event handler to toggle button
    $select.off('change.uniquefield').on('change.uniquefield', function () {
      _this19.toggleStartImportButton();
    });
  },
  /**
   * Toggle Start Import button based on unique field selection
   */
  toggleStartImportButton: function toggleStartImportButton() {
    var $button = jQuery('.aie-start-import');
    var uniqueField = jQuery('#aie-unique-field').val();
    if (uniqueField) {
      $button.prop('disabled', false).removeClass('disabled');
    } else {
      $button.prop('disabled', true).addClass('disabled');
    }
  },
  /**
   * Handle media import options visibility based on content type
   */
  handleMediaImportOptions: function handleMediaImportOptions() {
    var contentType = jQuery('input[name="content_type"]:checked').val();
    var $mediaImportOption = jQuery('.aie-media-import-option');
    var $mediaDuplicateOption = jQuery('.aie-media-duplicate-option');
    var $batchSize = jQuery('[name="batch_size"]');

    // Adjust batch size default: media downloads are slow, use 1; everything else uses 50.
    if (contentType === 'media') {
      var _$batchSize$data;
      $batchSize.val((_$batchSize$data = $batchSize.data('media-value')) !== null && _$batchSize$data !== void 0 ? _$batchSize$data : 1);
    } else {
      var _$batchSize$data2;
      $batchSize.val((_$batchSize$data2 = $batchSize.data('default-value')) !== null && _$batchSize$data2 !== void 0 ? _$batchSize$data2 : 1);
    }

    // Content types that support media import - ONLY these types
    var supportedTypes = ['post', 'page', 'custom_post_types', 'product'];

    // Show media options ONLY if contentType is in the supported list
    var shouldShowMediaOptions = supportedTypes.includes(contentType);
    if (shouldShowMediaOptions) {
      $mediaImportOption.show();

      // Show duplicate options only if checkbox is checked
      var isChecked = jQuery('#aie-auto-import-media').is(':checked');
      if (isChecked) {
        $mediaDuplicateOption.show();
      }
    } else {
      $mediaImportOption.hide();
      $mediaDuplicateOption.hide();
    }
  },
  /**
   * Toggle media duplicate options when checkbox is changed
   */
  toggleMediaDuplicateOptions: function toggleMediaDuplicateOptions(e) {
    var $checkbox = jQuery(e.target);
    var $mediaDuplicateOption = jQuery('.aie-media-duplicate-option');
    if ($checkbox.is(':checked')) {
      $mediaDuplicateOption.slideDown(200);
    } else {
      $mediaDuplicateOption.slideUp(200);
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
              _context.next = 8;
              return _utils__WEBPACK_IMPORTED_MODULE_1__["default"].ajax('aie_job_list', {
                type: _this2.filters.type,
                status: _this2.filters.status,
                limit: _this2.perPage,
                offset: offset
              });
            case 8:
              response = _context.sent;
              if (response && response.jobs) {
                _this2.totalJobs = response.total || 0;
                _this2.totalPages = Math.ceil(_this2.totalJobs / _this2.perPage);
                _this2.renderJobs(response.jobs);
                _this2.updatePagination();
              } else {
                _this2.renderJobs([]);
              }
              _context.next = 16;
              break;
            case 12:
              _context.prev = 12;
              _context.t0 = _context["catch"](4);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.errorLoadingJobs + _context.t0.message, 'error');
              _this2.renderJobs([]);
            case 16:
              _context.prev = 16;
              $loading.hide();
              $table.show();
              return _context.finish(16);
            case 20:
            case "end":
              return _context.stop();
          }
        }
      }, _callee, null, [[4, 12, 16, 20]]);
    }))();
  },
  /**
   * Render jobs table
   */
  renderJobs: function renderJobs(jobs) {
    var _this3 = this;
    var $tbody = jQuery('#jobs-table-body');
    if (!jobs || jobs.length === 0) {
      $tbody.html("<tr class=\"no-items\"><td colspan=\"9\">".concat(window.aieData.i18n.noJobsFound, "</td></tr>"));
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
    return "\n\t\t\t<tr class=\"job-row ".concat(statusClass, "\" data-job-id=\"").concat(job.id, "\">\n\t\t\t\t<td class=\"column-id\">").concat(job.id, "</td>\n\t\t\t\t<td class=\"column-type\">\n\t\t\t\t\t<span class=\"job-type-badge job-type-").concat(job.type, "\">").concat(typeLabel, "</span>\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-data-type\">").concat(job.data_type, "</td>\n\t\t\t\t<td class=\"column-status\">\n\t\t\t\t\t<span class=\"job-status-badge job-status-").concat(job.status, "\">").concat(statusLabel, "</span>\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-progress\">").concat(progressBar, "</td>\n\t\t\t\t<td class=\"column-items\">\n\t\t\t\t\t<div class=\"items-info\">\n\t\t\t\t\t\t<div><strong>").concat(job.processed_items, "</strong> / ").concat(job.total_items, "</div>\n\t\t\t\t\t\t").concat(job.failed_items > 0 ? "<div class=\"failed-count\">".concat(window.aieData.i18n.failed || 'Failed', ": ").concat(job.failed_items, "</div>") : '', "\n\t\t\t\t\t</div>\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-created\">").concat(this.formatDate(job.created_at), "</td>\n\t\t\t\t<td class=\"column-elapsed\">").concat(job.elapsed_time || '-', "</td>\n\t\t\t\t<td class=\"column-actions\">").concat(actions, "</td>\n\t\t\t</tr>\n\t\t");
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
    actions.push("<button class=\"button button-small job-action-view\" title=\"".concat(window.aieData.i18n.viewDetails || 'View Details', "\"><span class=\"dashicons dashicons-visibility\"></span></button>"));

    // Resume
    if (job.can_resume) {
      actions.push("<button class=\"button button-small job-action-resume\" title=\"".concat(window.aieData.i18n.resume || 'Resume', "\"><span class=\"dashicons dashicons-controls-play\"></span></button>"));
    }

    // Retry - not available for media_sync jobs (files may have been moved)
    if (job.type !== 'media_sync') {
      var isPremiumType = window.aieData.premiumDataTypes && window.aieData.premiumDataTypes.includes(job.data_type);
      var licenseRequired = isPremiumType && !window.aieData.isPremium;
      if (licenseRequired) {
        actions.push("<button class=\"button button-small\" disabled title=\"".concat(window.aieData.i18n.retryRequiresPremium || 'Premium license required to retry this job', "\"><span class=\"dashicons dashicons-lock\"></span></button>"));
      } else {
        actions.push("<button class=\"button button-small job-action-retry\" title=\"".concat(window.aieData.i18n.retry || 'Retry (Create new job with same parameters)', "\"><span class=\"dashicons dashicons-update\"></span></button>"));
      }
    }

    // Download (for exports)
    if (job.type === 'export' && job.file_path && job.status === 'completed') {
      actions.push("<button class=\"button button-small job-action-download\" title=\"".concat(window.aieData.i18n.download || 'Download', "\"><span class=\"dashicons dashicons-download\"></span></button>"));
    }

    // Delete
    if (job.can_delete) {
      actions.push("<button class=\"button button-small job-action-delete\" title=\"".concat(window.aieData.i18n["delete"] || 'Delete', "\"><span class=\"dashicons dashicons-trash\"></span></button>"));
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
    var showingText = window.aieData.i18n.showingJobs.replace('%1$s', start).replace('%2$s', end).replace('%3$s', this.totalJobs);
    jQuery('.displaying-num').text(showingText); // Update page numbers
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
                _this4.redirectToJobPage(response.type, response.job_id, response.data_type);
              }
              _context2.next = 17;
              break;
            case 13:
              _context2.prev = 13;
              _context2.t0 = _context2["catch"](6);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.errorResumingJob + _context2.t0.message, 'error');
              $button.prop('disabled', false);
            case 17:
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

                // For media_sync jobs, reload the page to show the new job
                // For other job types, redirect to their specific pages
                if (response.type === 'media_sync') {
                  setTimeout(function () {
                    window.location.reload();
                  }, 1000);
                } else {
                  // Redirect based on job type
                  _this5.redirectToJobPage(response.type, response.job_id, response.data_type);
                }
              }
              _context3.next = 17;
              break;
            case 13:
              _context3.prev = 13;
              _context3.t0 = _context3["catch"](6);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.errorRestartingJob + _context3.t0.message, 'error');
              $button.prop('disabled', false);
            case 17:
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

                // For media_sync jobs, reload the page to show the processing job
                // For other job types, redirect to their specific pages
                if (response.type === 'media_sync') {
                  // Reload current page to show the processing job in the list
                  setTimeout(function () {
                    window.location.reload();
                  }, 1000);
                } else {
                  // Redirect to job page with resume_job parameter to show progress
                  _this6.redirectToJobPage(response.type, response.job_id, response.data_type);
                }
              }
              _context4.next = 18;
              break;
            case 13:
              _context4.prev = 13;
              _context4.t0 = _context4["catch"](6);
              errorMsg = _context4.t0 && _context4.t0.message ? _context4.t0.message : 'Unknown error occurred';
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.errorRetryingJob + errorMsg, 'error');
              $button.prop('disabled', false);
            case 18:
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
              _context5.next = 14;
              break;
            case 11:
              _context5.prev = 11;
              _context5.t0 = _context5["catch"](4);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.errorDeletingJob + _context5.t0.message, 'error');
            case 14:
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
              _context6.next = 13;
              break;
            case 10:
              _context6.prev = 10;
              _context6.t0 = _context6["catch"](3);
              _utils__WEBPACK_IMPORTED_MODULE_1__["default"].showNotice(window.aieData.i18n.errorLoadingJobDetails + _context6.t0.message, 'error');
            case 13:
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
    var html = "\n\t\t\t<div class=\"job-details\">\n\t\t\t\t<table class=\"form-table\">\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>".concat(window.aieData.i18n.jobId || 'ID', ":</th>\n\t\t\t\t\t\t<td>").concat(job.id, "</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>").concat(window.aieData.i18n.jobType || 'Type', ":</th>\n\t\t\t\t\t\t<td>").concat(this.getTypeLabel(job.type), "</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t").concat(job.data_type ? "<tr>\n\t\t\t\t\t\t<th>".concat(window.aieData.i18n.jobDataType || 'Data Type', ":</th>\n\t\t\t\t\t\t<td>").concat(job.data_type, "</td>\n\t\t\t\t\t</tr>") : '', "\n\t\t\t\t\t").concat(job.file_format ? "<tr>\n\t\t\t\t\t\t<th>".concat(window.aieData.i18n.jobFileFormat || 'File Format', ":</th>\n\t\t\t\t\t\t<td>").concat(job.file_format, "</td>\n\t\t\t\t\t</tr>") : '', "\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>").concat(window.aieData.i18n.jobStatus || 'Status', ":</th>\n\t\t\t\t\t\t<td><span class=\"job-status-badge job-status-").concat(job.status, "\">").concat(this.getStatusLabel(job.status), "</span></td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>").concat(window.aieData.i18n.jobProgress || 'Progress', ":</th>\n\t\t\t\t\t\t<td>").concat(job.progress || 0, "%</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t<tr>\n\t\t\t\t\t\t<th>").concat(window.aieData.i18n.jobItems || 'Items', ":</th>\n\t\t\t\t\t\t<td>").concat(job.processed_items, " / ").concat(job.total_items, " (").concat(window.aieData.i18n.jobSuccess || 'Success', ": ").concat(job.success_items, ", ").concat(window.aieData.i18n.failed || 'Failed', ": ").concat(job.failed_items, ")</td>\n\t\t\t\t\t</tr>\n\t\t\t\t\t").concat(job.created_at ? "<tr>\n\t\t\t\t\t\t<th>".concat(window.aieData.i18n.jobCreated || 'Created', ":</th>\n\t\t\t\t\t\t<td>").concat(job.created_at, "</td>\n\t\t\t\t\t</tr>") : '', "\n\t\t\t\t\t").concat(job.started_at ? "<tr>\n\t\t\t\t\t\t<th>".concat(window.aieData.i18n.jobStarted || 'Started', ":</th>\n\t\t\t\t\t\t<td>").concat(job.started_at, "</td>\n\t\t\t\t\t</tr>") : '', "\n\t\t\t\t\t").concat(job.completed_at ? "<tr>\n\t\t\t\t\t\t<th>".concat(window.aieData.i18n.jobCompleted || 'Completed', ":</th>\n\t\t\t\t\t\t<td>").concat(job.completed_at, "</td>\n\t\t\t\t\t</tr>") : '', "\n\t\t\t\t\t").concat(job.file_path ? "<tr>\n\t\t\t\t\t\t<th>".concat(window.aieData.i18n.jobFile || 'File', ":</th>\n\t\t\t\t\t\t<td>").concat(job.file_path, "</td>\n\t\t\t\t\t</tr>") : '', "\n\t\t\t\t\t").concat(job.file_size ? "<tr><th>".concat(window.aieData.i18n.jobFileSize || 'File Size', ":</th><td>").concat(job.file_size_human, "</td></tr>") : '', "\n\t\t\t\t</table>\n\t\t\t\t\n\t\t\t\t").concat(job.parameters ? "\n\t\t\t\t\t<h3>".concat(window.aieData.i18n.jobParameters || 'Parameters', "</h3>\n\t\t\t\t\t<pre class=\"job-parameters\">").concat(JSON.stringify(job.parameters, null, 2), "</pre>\n\t\t\t\t") : '', "\n\t\t\t</div>\n\t\t");
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
  redirectToJobPage: function redirectToJobPage(type, jobId, dataType) {
    var page = '';

    // AI URL importer jobs have type='import' but data_type='ai_url'
    if (type === 'import' && dataType === 'ai_url') {
      page = 'wp-aie-ai-url-importer';
    } else {
      switch (type) {
        case 'export':
          page = 'wp-aie-export';
          break;
        case 'import':
          page = 'wp-aie-import';
          break;
        case 'update':
          page = 'wp-aie-content-updater';
          break;
        case 'media_sync':
          page = 'wp-aie-media-sync';
          break;
      }
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
      'import': window.aieData.i18n.typeImport || 'Import',
      'export': window.aieData.i18n.typeExport || 'Export',
      'update': window.aieData.i18n.typeUpdate || 'Update',
      'media_sync': window.aieData.i18n.typeMediaSync || 'Media Sync'
    };
    return labels[type] || type;
  },
  /**
   * Get status label
   */
  getStatusLabel: function getStatusLabel(status) {
    var labels = {
      'pending': window.aieData.i18n.statusPending || 'Pending',
      'processing': window.aieData.i18n.statusProcessing || 'Processing',
      'completed': window.aieData.i18n.statusCompleted || 'Completed',
      'failed': window.aieData.i18n.statusFailed || 'Failed',
      'paused': window.aieData.i18n.statusPaused || 'Paused',
      'cancelled': window.aieData.i18n.statusCancelled || 'Cancelled'
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

      // Re-scan if files already scanned
      if (_this.scannedFiles.length > 0) {
        _this.scanFolder();
      }
    });

    // Custom extensions change - auto re-scan with debounce
    $page.on('input', '#aie-custom-extensions-input', _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
      // Re-scan if files already scanned and custom type selected
      if (_this.scannedFiles.length > 0 && jQuery('#aie-file-types').val() === 'custom') {
        _this.scanFolder();
      }
    }, 500));

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
      var parentPath = jQuery('#aie-folder-up-btn').data('parent');
      if (parentPath) {
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

    // Remove trailing slash if present (keep leading slash for absolute paths)
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
    jQuery('#aie-scan-folder-btn').prop('disabled', true).text(window.aieData.i18n.scanning);
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
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.noFilesFoundCriteria || 'No files found matching the criteria', 'info');
        } else {
          // Show file list with checkboxes for selection
          _this2.displayFiles(_this2.scannedFiles);
          var message = (window.aieData.i18n.foundFilesReadyToSync || 'Found %d files ready to sync').replace('%d', _this2.scannedFiles.length);
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(message, 'success');

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
      jQuery('#aie-scan-folder-btn').prop('disabled', false).html("<span class=\"dashicons dashicons-search\"></span> ".concat(window.aieData.i18n.scanFolder || 'Scan Folder'));
    });
  },
  /**
   * Show empty state when no files found
   */
  showEmptyState: function showEmptyState() {
    var $list = jQuery('#aie-file-list');
    $list.html("\n\t\t\t<div class=\"aie-empty-state\">\n\t\t\t\t<span class=\"dashicons dashicons-search\"></span>\n\t\t\t\t<h3>".concat(window.aieData.i18n.noFilesFoundTitle || 'No Files Found', "</h3>\n\t\t\t\t<p>").concat(window.aieData.i18n.noFilesFoundDesc || 'No files matching your criteria were found in the selected folder.', "</p>\n\t\t\t\t<div class=\"aie-empty-suggestions\">\n\t\t\t\t\t<strong>").concat(window.aieData.i18n.suggestions || 'Suggestions', ":</strong>\n\t\t\t\t\t<ul>\n\t\t\t\t\t\t<li>").concat(window.aieData.i18n.checkFolderPath || 'Check if the folder path is correct', "</li>\n\t\t\t\t\t\t<li>").concat(window.aieData.i18n.enableScanRecursive || 'Try enabling "Scan Recursive" to search in subfolders', "</li>\n\t\t\t\t\t\t<li>").concat(window.aieData.i18n.changeFileTypeFilter || 'Change the file type filter', "</li>\n\t\t\t\t\t\t<li>").concat(window.aieData.i18n.makeSureFolderContains || 'Make sure the folder contains supported media files', "</li>\n\t\t\t\t\t</ul>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t"));

    // Hide file selection controls
    jQuery('.aie-file-list-controls').hide();

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
    var foundMessage = (window.aieData.i18n.foundFilesReadySync || 'Found %1$s files ready for synchronization (Total: %2$s)').replace('%1$s', "<strong>".concat(files.length, "</strong>")).replace('%2$s', "<strong>".concat(_utils__WEBPACK_IMPORTED_MODULE_0__["default"].formatBytes(totalSize), "</strong>"));
    $list.html("\n\t\t\t<div class=\"aie-scan-summary\">\n\t\t\t\t<div class=\"aie-summary-icon\">\n\t\t\t\t\t<span class=\"dashicons dashicons-yes-alt\"></span>\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-summary-content\">\n\t\t\t\t\t<h3>".concat(window.aieData.i18n.scanComplete || 'Scan Complete', "</h3>\n\t\t\t\t\t<p>").concat(foundMessage, "</p>\n\t\t\t\t\t<div class=\"aie-file-types\">\n\t\t\t\t\t\t<strong>").concat(window.aieData.i18n.fileTypes || 'File Types', ":</strong>\n\t\t\t\t\t\t").concat(Object.entries(fileTypes).map(function (_ref) {
      var _ref2 = _slicedToArray(_ref, 2),
        ext = _ref2[0],
        count = _ref2[1];
      return "<span class=\"aie-type-badge\">".concat(ext.toUpperCase(), " (").concat(count, ")</span>");
    }).join(''), "\n\t\t\t\t\t</div>\n\t\t\t\t\t<p class=\"aie-summary-note\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-info\"></span>\n\t\t\t\t\t\t").concat(window.aieData.i18n.filesProcessedBatches || 'All files will be processed in batches. Click "Start Sync" below to begin.', "\n\t\t\t\t\t</p>\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t"));

    // Update stats
    jQuery('#aie-total-files').text(files.length);
    jQuery('#aie-total-size').text(_utils__WEBPACK_IMPORTED_MODULE_0__["default"].formatBytes(totalSize));
    jQuery('#aie-selected-count').text(files.length);

    // Show stats and scan results
    jQuery('#aie-scan-results .aie-scan-stats').show();
    jQuery('#aie-scan-results').slideDown();
  },
  /**
   * Display scanned files with checkboxes for selection
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

    // Show file selection controls
    jQuery('.aie-file-list-controls').show();

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

    // Get selected files
    var selectedFiles = this.getSelectedFiles();
    if (selectedFiles.length === 0) {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(window.aieData.i18n.noFilesSelected || 'Please select at least one file to sync', 'error');
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
    $btn.prop('disabled', true).html("<span class=\"dashicons dashicons-update aie-spin\"></span> ".concat(window.aieData.i18n.starting));
    jQuery.ajax({
      url: ((_window$aieData3 = window.aieData) === null || _window$aieData3 === void 0 ? void 0 : _window$aieData3.ajaxUrl) || window.ajaxurl,
      method: 'POST',
      data: {
        action: 'aie_start_media_sync',
        nonce: ((_window$aieData4 = window.aieData) === null || _window$aieData4 === void 0 ? void 0 : _window$aieData4.nonce) || '',
        folder_path: folderPath,
        selected_files: selectedFiles,
        // Send selected files
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
        jQuery('#aie-sync-status').text(window.aieData.i18n.processing);

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
      // If not completed, process next batch after small delay
      if (response.success && response.data && !response.data.completed) {
        setTimeout(function () {
          _this6.triggerBatchProcessing();
        }, 100);
      }
    }).fail(function (xhr, status, error) {});
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
      if (response.success && response.data) {
        _this7.updateProgress(response.data);
      } else {}
    }).fail(function (xhr, status, error) {});
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
    // Parse progress as integer (remove decimals)
    var progress = Math.round(parseFloat(data.progress) || 0);
    var status = data.status || 'processing';

    // Update progress bar
    jQuery('#aie-progress-fill').css('width', progress + '%');
    jQuery('#aie-progress-percentage').text(progress + '%');

    // Update stats - handle both object and null
    var result = data.result;

    // If result is a string, try to parse it
    if (typeof result === 'string') {
      try {
        result = JSON.parse(result);
      } catch (e) {
        result = {};
      }
    }

    // Ensure result is an object
    result = result || {};

    // Update stats with explicit checks
    // Show 0 if undefined (processing hasn't generated results yet)
    var processed = result.processed !== undefined ? result.processed : 0;
    var success = result.success !== undefined ? result.success : 0;
    var skipped = result.skipped !== undefined ? result.skipped : 0;
    var failed = result.failed !== undefined ? result.failed : 0;
    jQuery('#aie-stat-processed').text(processed);
    jQuery('#aie-stat-success').text(success);
    jQuery('#aie-stat-skipped').text(skipped);
    jQuery('#aie-stat-failed').text(failed);

    // Update status text (fix selector - was #aie-progress-status, should be #aie-sync-status)
    var statusTexts = {
      pending: window.aieData.i18n.starting || 'Starting...',
      processing: window.aieData.i18n.syncInProgress || 'Synchronization in Progress',
      completed: window.aieData.i18n.statusCompleted || 'Completed',
      failed: window.aieData.i18n.statusFailed || 'Failed',
      cancelled: window.aieData.i18n.statusCancelled || 'Cancelled',
      paused: window.aieData.i18n.statusPaused || 'Paused'
    };
    var statusText = statusTexts[status] || 'Processing...';

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
      var moreErrorsMsg = (window.aieData.i18n.andMoreErrors || '... and %d more errors').replace('%d', errors.length - 20);
      $errorList.append("<li>".concat(moreErrorsMsg, "</li>"));
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
      var processedMsg = processed !== 1 ? (window.aieData.i18n.successfullyProcessedPlural || 'Successfully processed %s files').replace('%s', "<strong>".concat(processed, "</strong>")) : (window.aieData.i18n.successfullyProcessed || 'Successfully processed %s file').replace('%s', "<strong>".concat(processed, "</strong>"));
      messageHtml = "\n\t\t\t\t<div style=\"text-align: center; padding: 20px;\">\n\t\t\t\t\t<div style=\"font-size: 64px; margin-bottom: 15px;\">\uD83C\uDF89</div>\n\t\t\t\t\t<h3 style=\"color: #00a32a; margin: 0 0 15px; font-size: 24px;\">".concat(window.aieData.i18n.syncCompleteTitle || 'Synchronization Complete!', "</h3>\n\t\t\t\t\t<p style=\"font-size: 16px; color: #1d2327; margin-bottom: 20px;\">\n\t\t\t\t\t\t").concat(processedMsg, "\n\t\t\t\t\t</p>\n\t\t\t\t\t<div style=\"display: flex; justify-content: center; gap: 30px; flex-wrap: wrap;\">\n\t\t\t\t\t\t<div style=\"text-align: center;\">\n\t\t\t\t\t\t\t<div style=\"font-size: 32px; color: #00a32a; font-weight: 600;\">").concat(success, "</div>\n\t\t\t\t\t\t\t<div style=\"font-size: 12px; color: #646970; text-transform: uppercase;\">\u2705 ").concat(window.aieData.i18n.imported || 'Imported', "</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t").concat(skipped > 0 ? "\n\t\t\t\t\t\t<div style=\"text-align: center;\">\n\t\t\t\t\t\t\t<div style=\"font-size: 32px; color: #dba617; font-weight: 600;\">".concat(skipped, "</div>\n\t\t\t\t\t\t\t<div style=\"font-size: 12px; color: #646970; text-transform: uppercase;\">\u23ED\uFE0F ").concat(window.aieData.i18n.skipped || 'Skipped', "</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t") : '', "\n\t\t\t\t\t\t").concat(failed > 0 ? "\n\t\t\t\t\t\t<div style=\"text-align: center;\">\n\t\t\t\t\t\t\t<div style=\"font-size: 32px; color: #d63638; font-weight: 600;\">".concat(failed, "</div>\n\t\t\t\t\t\t\t<div style=\"font-size: 12px; color: #646970; text-transform: uppercase;\">\u274C ").concat(window.aieData.i18n.statusFailed || 'Failed', "</div>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t\t") : '', "\n\t\t\t\t\t</div>\n\t\t\t\t</div>\n\t\t\t");
    } else if (data.status === 'failed') {
      messageHtml = "\n\t\t\t\t<div style=\"text-align: center; padding: 20px;\">\n\t\t\t\t\t<div style=\"font-size: 64px; margin-bottom: 15px;\">\u26A0\uFE0F</div>\n\t\t\t\t\t<h3 style=\"color: #d63638; margin: 0 0 15px; font-size: 24px;\">".concat(window.aieData.i18n.syncFailedTitle || 'Synchronization Failed', "</h3>\n\t\t\t\t\t<p style=\"font-size: 16px; color: #646970;\">\n\t\t\t\t\t\t").concat(window.aieData.i18n.syncFailedDesc || 'The synchronization process encountered an error and could not complete.', "\n\t\t\t\t\t</p>\n\t\t\t\t</div>\n\t\t\t");
    } else if (data.status === 'cancelled') {
      var cancelledMsg = processed !== 1 ? (window.aieData.i18n.processedBeforeCancellationPlural || 'Processed %s files before cancellation.').replace('%s', "<strong>".concat(processed, "</strong>")) : (window.aieData.i18n.processedBeforeCancellation || 'Processed %s file before cancellation.').replace('%s', "<strong>".concat(processed, "</strong>"));
      messageHtml = "\n\t\t\t\t<div style=\"text-align: center; padding: 20px;\">\n\t\t\t\t\t<div style=\"font-size: 64px; margin-bottom: 15px;\">\uD83D\uDED1</div>\n\t\t\t\t\t<h3 style=\"color: #dba617; margin: 0 0 15px; font-size: 24px;\">".concat(window.aieData.i18n.syncCancelledTitle || 'Synchronization Cancelled', "</h3>\n\t\t\t\t\t<p style=\"font-size: 16px; color: #646970;\">\n\t\t\t\t\t\t").concat(cancelledMsg, "\n\t\t\t\t\t</p>\n\t\t\t\t</div>\n\t\t\t");
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
        $header.html("<span class=\"dashicons dashicons-controls-pause\"></span> ".concat(window.aieData.i18n.syncPaused));

        // Update status text
        jQuery('#aie-progress-status').text(window.aieData.i18n.paused);
        jQuery('#aie-sync-status').text(window.aieData.i18n.paused);
        var $pauseBtn = jQuery('#aie-pause-sync-btn');
        $pauseBtn.html("<span class=\"dashicons dashicons-controls-play\"></span> ".concat(window.aieData.i18n.resume));
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
        $header.html("<span class=\"dashicons dashicons-update aie-spin\"></span> ".concat(window.aieData.i18n.syncInProgress));

        // Update status text
        jQuery('#aie-progress-status').text(window.aieData.i18n.syncInProgress);
        jQuery('#aie-sync-status').text(window.aieData.i18n.syncInProgress);
        var $pauseBtn = jQuery('#aie-pause-sync-btn');
        $pauseBtn.html("<span class=\"dashicons dashicons-controls-pause\"></span> ".concat(window.aieData.i18n.pause));

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

    // Show file selection controls again
    jQuery('.aie-file-list-controls').show();

    // Reset Start button
    var $startBtn = jQuery('#aie-start-sync-btn');
    $startBtn.prop('disabled', false);
    $startBtn.html("<span class=\"dashicons dashicons-controls-play\"></span> ".concat(window.aieData.i18n.startSync));

    // Reset Scan button
    var $scanBtn = jQuery('#aie-scan-folder-btn');
    $scanBtn.prop('disabled', false).text(window.aieData.i18n.scanFolder);

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
    $pauseBtn.html("<span class=\"dashicons dashicons-controls-pause\"></span> ".concat(window.aieData.i18n.pause || 'Pause'));

    // Reset header to default state
    var $header = jQuery('#aie-sync-progress-section .aie-card-header h2');
    $header.html("<span class=\"dashicons dashicons-update aie-spin\"></span> ".concat(window.aieData.i18n.syncInProgress || 'Synchronization in Progress'));
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
        _this12.displayFolders(response.data.folders, response.data.current_path, response.data.can_go_up, response.data.parent_path);
      } else {
        var _response$data3;
        _this12.showBrowserError(((_response$data3 = response.data) === null || _response$data3 === void 0 ? void 0 : _response$data3.message) || 'Failed to load folders');
      }
    }).fail(function (jqXHR, textStatus, errorThrown) {
      if (jqXHR.responseText) {}
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
  displayFolders: function displayFolders(folders, currentPath, canGoUp, parentPath) {
    var _this13 = this;
    var $list = jQuery('#aie-folder-browser-list');
    var $currentPath = jQuery('#aie-current-path');
    $list.empty();

    // Update current path display (currentPath is now an absolute server path)
    $currentPath.text(currentPath);

    // Remove any existing up button before re-rendering
    jQuery('#aie-folder-up-btn').remove();

    // Show "Go Up" button when parent directory is accessible within WordPress
    if (canGoUp && parentPath) {
      var $upButton = jQuery("\n\t\t\t\t<button type=\"button\" id=\"aie-folder-up-btn\" class=\"button\" data-parent=\"".concat(this.escapeHtml(parentPath), "\" style=\"margin-bottom: 10px;\">\n\t\t\t\t\t<span class=\"dashicons dashicons-arrow-up-alt\"></span>\n\t\t\t\t\t").concat(window.aieData.i18n.goUp || 'Go Up', "\n\t\t\t\t</button>\n\t\t\t"));
      $upButton.insertBefore($list);
    }

    // Add "Use this folder" option — currentPath is always an absolute path (truthy),
    // fixing the bug where selecting the root uploads folder did nothing.
    var $rootOption = jQuery("\n\t\t\t<div class=\"aie-folder-item aie-folder-current\" data-path=\"".concat(this.escapeHtml(currentPath), "\">\n\t\t\t\t<span class=\"dashicons dashicons-location\"></span>\n\t\t\t\t<span class=\"aie-folder-name\">\n\t\t\t\t\t<strong>").concat(window.aieData.i18n.useThisFolder || '. (Use this folder)', "</strong>\n\t\t\t\t</span>\n\t\t\t</div>\n\t\t"));
    $list.append($rootOption);
    if (!folders || folders.length === 0) {
      jQuery('#aie-folder-browser-empty').show();
      return;
    }

    // Display subfolders
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
    this.bindEvents();
  },
  /**
   * Bind event handlers
   */
  bindEvents: function bindEvents() {
    var _this = this;
    var $ = jQuery;

    // Open modal when sync button is clicked
    $(document).on('click', '#aie-sync-content-btn', function (e) {
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
    var $ = jQuery;
    var selectedIds = this.getSelectedPostIds();
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
    var _window$aieData,
      _window$aieData$i18n,
      _window$aieData2,
      _window$aieData2$i18n,
      _window$aieData3,
      _window$aieData3$i18n,
      _window$aieData4,
      _window$aieData4$i18n,
      _window$aieData5,
      _window$aieData5$i18n,
      _this2 = this;
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
    var actionText = direction === 'push' ? ((_window$aieData = window.aieData) === null || _window$aieData === void 0 ? void 0 : (_window$aieData$i18n = _window$aieData.i18n) === null || _window$aieData$i18n === void 0 ? void 0 : _window$aieData$i18n.pushTo) || 'push to' : ((_window$aieData2 = window.aieData) === null || _window$aieData2 === void 0 ? void 0 : (_window$aieData2$i18n = _window$aieData2.i18n) === null || _window$aieData2$i18n === void 0 ? void 0 : _window$aieData2$i18n.pullFrom) || 'pull from';
    var message = (((_window$aieData3 = window.aieData) === null || _window$aieData3 === void 0 ? void 0 : (_window$aieData3$i18n = _window$aieData3.i18n) === null || _window$aieData3$i18n === void 0 ? void 0 : _window$aieData3$i18n.confirmSyncAction) || 'Are you sure you want to %1$s %2$s?\n\nThis will affect %3$s post(s).').replace('%1$s', actionText).replace('%2$s', siteName).replace('%3$s', postIds.length);
    if (!confirm(message)) {
      return;
    }

    // Show enhanced progress
    $('#aie-sync-progress').show();
    $('#aie-sync-result').hide();
    var preparingMsg = direction === 'push' ? ((_window$aieData4 = window.aieData) === null || _window$aieData4 === void 0 ? void 0 : (_window$aieData4$i18n = _window$aieData4.i18n) === null || _window$aieData4$i18n === void 0 ? void 0 : _window$aieData4$i18n.preparingToPush) || 'Preparing to push content...' : ((_window$aieData5 = window.aieData) === null || _window$aieData5 === void 0 ? void 0 : (_window$aieData5$i18n = _window$aieData5.i18n) === null || _window$aieData5$i18n === void 0 ? void 0 : _window$aieData5$i18n.preparingToPull) || 'Preparing to pull content...';
    this.updateProgress(0, preparingMsg, {
      posts: 0,
      images: 0,
      total: postIds.length
    });

    // Disable buttons
    $('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', true);

    // Simulate progress for better UX
    var simulatedProgress = 10;
    var progressInterval = setInterval(function () {
      if (simulatedProgress < 90) {
        var _window$aieData6, _window$aieData6$i18n, _window$aieData7, _window$aieData7$i18n;
        simulatedProgress += 5;
        var progressMsg = direction === 'push' ? ((_window$aieData6 = window.aieData) === null || _window$aieData6 === void 0 ? void 0 : (_window$aieData6$i18n = _window$aieData6.i18n) === null || _window$aieData6$i18n === void 0 ? void 0 : _window$aieData6$i18n.uploadingContent) || 'Uploading content...' : ((_window$aieData7 = window.aieData) === null || _window$aieData7 === void 0 ? void 0 : (_window$aieData7$i18n = _window$aieData7.i18n) === null || _window$aieData7$i18n === void 0 ? void 0 : _window$aieData7$i18n.downloadingContent) || 'Downloading content...';
        _this2.updateProgress(simulatedProgress, progressMsg, {
          posts: Math.floor(postIds.length * simulatedProgress / 100),
          total: postIds.length
        });
      }
    }, 300);

    // Make AJAX request
    var nonce = typeof aiePostSyncData !== 'undefined' && aiePostSyncData.nonce ? aiePostSyncData.nonce : typeof aieContentSync !== 'undefined' && aieContentSync.nonce ? aieContentSync.nonce : typeof aieData !== 'undefined' && aieData.nonce ? aieData.nonce : '';
    var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : typeof aiePostSyncData !== 'undefined' && aiePostSyncData.ajaxurl ? aiePostSyncData.ajaxurl : '/wp-admin/admin-ajax.php';
    var ajaxData = {
      action: "aie_content_sync_".concat(direction),
      nonce: nonce,
      site_id: siteId,
      post_ids: postIds
    };
    $.ajax({
      url: ajaxUrl,
      type: 'POST',
      data: ajaxData,
      success: function success(response) {
        clearInterval(progressInterval);
        if (response.success) {
          var _window$aieData8, _window$aieData8$i18n;
          var data = response.data || {};
          var imageCount = data.images_synced || 0;
          _this2.updateProgress(100, ((_window$aieData8 = window.aieData) === null || _window$aieData8 === void 0 ? void 0 : (_window$aieData8$i18n = _window$aieData8.i18n) === null || _window$aieData8$i18n === void 0 ? void 0 : _window$aieData8$i18n.operationCompleted) || 'Completed successfully!', {
            posts: postIds.length,
            images: imageCount,
            total: postIds.length
          });
          setTimeout(function () {
            var _window$aieData9, _window$aieData9$i18n;
            $('#aie-sync-progress').hide();

            // Build detailed success message
            var successMsg = response.data.message || ((_window$aieData9 = window.aieData) === null || _window$aieData9 === void 0 ? void 0 : (_window$aieData9$i18n = _window$aieData9.i18n) === null || _window$aieData9$i18n === void 0 ? void 0 : _window$aieData9$i18n.syncCompletedSuccessfully) || 'Sync completed successfully';
            if (data.created && data.updated) {
              var _window$aieData10, _window$aieData10$i;
              var createdUpdatedMsg = (((_window$aieData10 = window.aieData) === null || _window$aieData10 === void 0 ? void 0 : (_window$aieData10$i = _window$aieData10.i18n) === null || _window$aieData10$i === void 0 ? void 0 : _window$aieData10$i.createdPosts) || '✓ Created %d post(s), Updated %d post(s)').replace('%d', data.created).replace('%d', data.updated);
              successMsg = createdUpdatedMsg;
            }
            if (imageCount > 0) {
              var _window$aieData11, _window$aieData11$i;
              var syncedImagesMsg = (((_window$aieData11 = window.aieData) === null || _window$aieData11 === void 0 ? void 0 : (_window$aieData11$i = _window$aieData11.i18n) === null || _window$aieData11$i === void 0 ? void 0 : _window$aieData11$i.syncedImages) || '✓ Synced %d image(s)').replace('%d', imageCount);
              successMsg += "<br>".concat(syncedImagesMsg);
            }
            _this2.showResult('success', successMsg);
          }, 800);
        } else {
          var _window$aieData12, _window$aieData12$i;
          _this2.updateProgress(0, ((_window$aieData12 = window.aieData) === null || _window$aieData12 === void 0 ? void 0 : (_window$aieData12$i = _window$aieData12.i18n) === null || _window$aieData12$i === void 0 ? void 0 : _window$aieData12$i.syncFailed) || 'Sync failed', {});
          setTimeout(function () {
            var _window$aieData13, _window$aieData13$i;
            $('#aie-sync-progress').hide();
            _this2.showResult('error', response.data.message || ((_window$aieData13 = window.aieData) === null || _window$aieData13 === void 0 ? void 0 : (_window$aieData13$i = _window$aieData13.i18n) === null || _window$aieData13$i === void 0 ? void 0 : _window$aieData13$i.syncFailed) || 'Sync failed');
          }, 500);
        }
      },
      error: function error(xhr) {
        var _window$aieData14, _window$aieData14$i;
        clearInterval(progressInterval);
        $('#aie-sync-progress').hide();
        var errorMessage = ((_window$aieData14 = window.aieData) === null || _window$aieData14 === void 0 ? void 0 : (_window$aieData14$i = _window$aieData14.i18n) === null || _window$aieData14$i === void 0 ? void 0 : _window$aieData14$i.errorOccurredDuringSync) || 'An error occurred during sync';
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          errorMessage = xhr.responseJSON.data.message;
        }
        _this2.showResult('error', errorMessage);
      },
      complete: function complete() {
        clearInterval(progressInterval);
        // Re-enable buttons
        $('#aie-sync-push-btn, #aie-sync-pull-btn, #aie-sync-site-select').prop('disabled', false);
        _this2.updateSyncButtons();
      }
    });
  },
  /**
   * Update progress bar with details
   */
  updateProgress: function updateProgress(percent, message) {
    var details = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : {};
    var $ = jQuery;
    $('.aie-progress-fill').css('width', "".concat(percent, "%"));

    // Build detailed progress message
    var progressText = "<strong>".concat(message, "</strong>");
    if (details.posts !== undefined && details.total) {
      var _window$aieData15, _window$aieData15$i;
      var postsMsg = (((_window$aieData15 = window.aieData) === null || _window$aieData15 === void 0 ? void 0 : (_window$aieData15$i = _window$aieData15.i18n) === null || _window$aieData15$i === void 0 ? void 0 : _window$aieData15$i.postsProgress) || 'Posts: %1$s/%2$s').replace('%1$s', details.posts).replace('%2$s', details.total);
      progressText += "<br><span class=\"progress-details\">".concat(postsMsg, "</span>");
    }
    if (details.images !== undefined && details.images > 0) {
      var _window$aieData16, _window$aieData16$i;
      var imagesMsg = (((_window$aieData16 = window.aieData) === null || _window$aieData16 === void 0 ? void 0 : (_window$aieData16$i = _window$aieData16.i18n) === null || _window$aieData16$i === void 0 ? void 0 : _window$aieData16$i.imagesSyncedProgress) || 'Images synced: %d').replace('%d', details.images);
      progressText += "<br><span class=\"progress-details\">".concat(imagesMsg, "</span>");
    }
    if (percent > 0 && percent < 100) {
      progressText += "<br><span class=\"progress-percentage\">".concat(Math.round(percent), "%</span>");
    }
    $('.aie-progress-text').html(progressText);
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
          reject(((_response$data = response.data) === null || _response$data === void 0 ? void 0 : _response$data.message) || response.data || 'Request failed');
        }
      }).fail(function (jqXHR, textStatus, errorThrown) {
        // Try to parse error response
        var errorMessage = 'Request failed';
        if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
          errorMessage = jqXHR.responseJSON.data.message;
        } else if (jqXHR.responseText) {
          try {
            var _parsed$data;
            var parsed = JSON.parse(jqXHR.responseText);
            errorMessage = ((_parsed$data = parsed.data) === null || _parsed$data === void 0 ? void 0 : _parsed$data.message) || parsed.message || errorMessage;
          } catch (e) {
            errorMessage = errorThrown || textStatus || errorMessage;
          }
        } else if (errorThrown) {
          errorMessage = errorThrown;
        }
        reject(errorMessage);
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
    var _window$aieData5, _window$aieData5$i18n;
    if (seconds < 60) {
      var _window$aieData3, _window$aieData3$i18n;
      return (((_window$aieData3 = window.aieData) === null || _window$aieData3 === void 0 ? void 0 : (_window$aieData3$i18n = _window$aieData3.i18n) === null || _window$aieData3$i18n === void 0 ? void 0 : _window$aieData3$i18n.timeFormatSeconds) || '%ds').replace('%d', Math.round(seconds));
    }
    var minutes = Math.floor(seconds / 60);
    var secs = Math.round(seconds % 60);
    if (minutes < 60) {
      var _window$aieData4, _window$aieData4$i18n;
      return (((_window$aieData4 = window.aieData) === null || _window$aieData4 === void 0 ? void 0 : (_window$aieData4$i18n = _window$aieData4.i18n) === null || _window$aieData4$i18n === void 0 ? void 0 : _window$aieData4$i18n.timeFormatMinutesSeconds) || '%1$sm %2$ss').replace('%1$s', minutes).replace('%2$s', secs);
    }
    var hours = Math.floor(minutes / 60);
    var mins = minutes % 60;
    return (((_window$aieData5 = window.aieData) === null || _window$aieData5 === void 0 ? void 0 : (_window$aieData5$i18n = _window$aieData5.i18n) === null || _window$aieData5$i18n === void 0 ? void 0 : _window$aieData5$i18n.timeFormatHoursMinutes) || '%1$sh %2$sm').replace('%1$s', hours).replace('%2$s', mins);
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
    var _window$aieData6, _window$aieData6$i18n;
    var type = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'info';
    var noticeClass = 'notice notice-' + type + ' is-dismissible';
    var dismissText = ((_window$aieData6 = window.aieData) === null || _window$aieData6 === void 0 ? void 0 : (_window$aieData6$i18n = _window$aieData6.i18n) === null || _window$aieData6$i18n === void 0 ? void 0 : _window$aieData6$i18n.dismissNotice) || 'Dismiss this notice.';

    // Remove all existing notices to show only one at a time
    jQuery('.wrap > .notice').remove();
    var $notice = jQuery('<div class="' + noticeClass + '">' + '<p>' + message + '</p>' + '<button type="button" class="notice-dismiss">' + '<span class="screen-reader-text">' + dismissText + '</span>' + '</button>' + '</div>');
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
      var _window$aieData7, _window$aieData7$i18n;
      var fileSizeMsg = (((_window$aieData7 = window.aieData) === null || _window$aieData7 === void 0 ? void 0 : (_window$aieData7$i18n = _window$aieData7.i18n) === null || _window$aieData7$i18n === void 0 ? void 0 : _window$aieData7$i18n.fileSizeExceeds) || 'File size (%1$s) exceeds maximum allowed size (%2$s)').replace('%1$s', this.formatFileSize(file.size)).replace('%2$s', this.formatFileSize(maxSize));
      errors.push(fileSizeMsg);
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
        var _window$aieData8, _window$aieData8$i18n;
        var fileTypeMsg = (((_window$aieData8 = window.aieData) === null || _window$aieData8 === void 0 ? void 0 : (_window$aieData8$i18n = _window$aieData8.i18n) === null || _window$aieData8$i18n === void 0 ? void 0 : _window$aieData8$i18n.fileTypeNotAllowed) || 'File type .%1$s is not allowed. Allowed types: %2$s').replace('%1$s', fileExt).replace('%2$s', allowedTypes.join(', '));
        errors.push(fileTypeMsg);
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
    var _window$aieData9, _window$aieData9$i18n;
    var itemsText = ((_window$aieData9 = window.aieData) === null || _window$aieData9 === void 0 ? void 0 : (_window$aieData9$i18n = _window$aieData9.i18n) === null || _window$aieData9$i18n === void 0 ? void 0 : _window$aieData9$i18n.jobItems) || 'items';
    return jQuery('<div class="aie-progress-container">' + '<div class="aie-progress-bar">' + '<div class="aie-progress-bar-fill" style="width: 0%;"></div>' + '</div>' + '<div class="aie-progress-stats">' + '<div class="aie-progress-percentage">0%</div>' + '<div class="aie-progress-details">' + '<span class="aie-processed">0</span> / <span class="aie-total">0</span> ' + itemsText + '</div>' + '</div>' + '</div>');
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
  var _window$aieData, _window$aieData$i18n;
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
  dismissButton.innerHTML = "<span class=\"screen-reader-text\">".concat(((_window$aieData = window.aieData) === null || _window$aieData === void 0 ? void 0 : (_window$aieData$i18n = _window$aieData.i18n) === null || _window$aieData$i18n === void 0 ? void 0 : _window$aieData$i18n.dismissNotice) || 'Dismiss this notice.', "</span>");
  dismissButton.addEventListener('click', function () {
    notice.remove();
  });
  notice.appendChild(dismissButton);
}

/**
 * Show error message
 */
function showError(message) {
  var _window$aieData2, _window$aieData2$i18n;
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
  dismissButton.innerHTML = "<span class=\"screen-reader-text\">".concat(((_window$aieData2 = window.aieData) === null || _window$aieData2 === void 0 ? void 0 : (_window$aieData2$i18n = _window$aieData2.i18n) === null || _window$aieData2$i18n === void 0 ? void 0 : _window$aieData2$i18n.dismissNotice) || 'Dismiss this notice.', "</span>");
  dismissButton.addEventListener('click', function () {
    notice.remove();
  });
  notice.appendChild(dismissButton);
}

/**
 * Show error message inside a modal
 */
function showModalError(message) {
  var _window$aieData3, _window$aieData3$i18n;
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
  dismissButton.innerHTML = "<span class=\"screen-reader-text\">".concat(((_window$aieData3 = window.aieData) === null || _window$aieData3 === void 0 ? void 0 : (_window$aieData3$i18n = _window$aieData3.i18n) === null || _window$aieData3$i18n === void 0 ? void 0 : _window$aieData3$i18n.dismissNotice) || 'Dismiss this notice.', "</span>");
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