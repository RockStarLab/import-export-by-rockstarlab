/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/js/app.js":
/*!***********************!*\
  !*** ./src/js/app.js ***!
  \***********************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _modules_functions__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./modules/functions */ "./src/js/modules/functions.js");
/* harmony import */ var _modules_import__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./modules/import */ "./src/js/modules/import.js");
/* harmony import */ var _modules_export__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./modules/export */ "./src/js/modules/export.js");




// Initialize modules when DOM is ready
jQuery(document).ready(function ($) {
  // Initialize import module
  _modules_import__WEBPACK_IMPORTED_MODULE_1__["default"].init();

  // Initialize export module
  _modules_export__WEBPACK_IMPORTED_MODULE_2__["default"].init();

  // Initialize functions module
  _modules_functions__WEBPACK_IMPORTED_MODULE_0__["default"].init();
});

/***/ }),

/***/ "./src/js/modules/export.js":
/*!**********************************!*\
  !*** ./src/js/modules/export.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utils */ "./src/js/modules/utils.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _regeneratorRuntime() { "use strict"; /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime = function _regeneratorRuntime() { return e; }; var t, e = {}, r = Object.prototype, n = r.hasOwnProperty, o = Object.defineProperty || function (t, e, r) { t[e] = r.value; }, i = "function" == typeof Symbol ? Symbol : {}, a = i.iterator || "@@iterator", c = i.asyncIterator || "@@asyncIterator", u = i.toStringTag || "@@toStringTag"; function define(t, e, r) { return Object.defineProperty(t, e, { value: r, enumerable: !0, configurable: !0, writable: !0 }), t[e]; } try { define({}, ""); } catch (t) { define = function define(t, e, r) { return t[e] = r; }; } function wrap(t, e, r, n) { var i = e && e.prototype instanceof Generator ? e : Generator, a = Object.create(i.prototype), c = new Context(n || []); return o(a, "_invoke", { value: makeInvokeMethod(t, r, c) }), a; } function tryCatch(t, e, r) { try { return { type: "normal", arg: t.call(e, r) }; } catch (t) { return { type: "throw", arg: t }; } } e.wrap = wrap; var h = "suspendedStart", l = "suspendedYield", f = "executing", s = "completed", y = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var p = {}; define(p, a, function () { return this; }); var d = Object.getPrototypeOf, v = d && d(d(values([]))); v && v !== r && n.call(v, a) && (p = v); var g = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(p); function defineIteratorMethods(t) { ["next", "throw", "return"].forEach(function (e) { define(t, e, function (t) { return this._invoke(e, t); }); }); } function AsyncIterator(t, e) { function invoke(r, o, i, a) { var c = tryCatch(t[r], t, o); if ("throw" !== c.type) { var u = c.arg, h = u.value; return h && "object" == _typeof(h) && n.call(h, "__await") ? e.resolve(h.__await).then(function (t) { invoke("next", t, i, a); }, function (t) { invoke("throw", t, i, a); }) : e.resolve(h).then(function (t) { u.value = t, i(u); }, function (t) { return invoke("throw", t, i, a); }); } a(c.arg); } var r; o(this, "_invoke", { value: function value(t, n) { function callInvokeWithMethodAndArg() { return new e(function (e, r) { invoke(t, n, e, r); }); } return r = r ? r.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); } }); } function makeInvokeMethod(e, r, n) { var o = h; return function (i, a) { if (o === f) throw Error("Generator is already running"); if (o === s) { if ("throw" === i) throw a; return { value: t, done: !0 }; } for (n.method = i, n.arg = a;;) { var c = n.delegate; if (c) { var u = maybeInvokeDelegate(c, n); if (u) { if (u === y) continue; return u; } } if ("next" === n.method) n.sent = n._sent = n.arg;else if ("throw" === n.method) { if (o === h) throw o = s, n.arg; n.dispatchException(n.arg); } else "return" === n.method && n.abrupt("return", n.arg); o = f; var p = tryCatch(e, r, n); if ("normal" === p.type) { if (o = n.done ? s : l, p.arg === y) continue; return { value: p.arg, done: n.done }; } "throw" === p.type && (o = s, n.method = "throw", n.arg = p.arg); } }; } function maybeInvokeDelegate(e, r) { var n = r.method, o = e.iterator[n]; if (o === t) return r.delegate = null, "throw" === n && e.iterator["return"] && (r.method = "return", r.arg = t, maybeInvokeDelegate(e, r), "throw" === r.method) || "return" !== n && (r.method = "throw", r.arg = new TypeError("The iterator does not provide a '" + n + "' method")), y; var i = tryCatch(o, e.iterator, r.arg); if ("throw" === i.type) return r.method = "throw", r.arg = i.arg, r.delegate = null, y; var a = i.arg; return a ? a.done ? (r[e.resultName] = a.value, r.next = e.nextLoc, "return" !== r.method && (r.method = "next", r.arg = t), r.delegate = null, y) : a : (r.method = "throw", r.arg = new TypeError("iterator result is not an object"), r.delegate = null, y); } function pushTryEntry(t) { var e = { tryLoc: t[0] }; 1 in t && (e.catchLoc = t[1]), 2 in t && (e.finallyLoc = t[2], e.afterLoc = t[3]), this.tryEntries.push(e); } function resetTryEntry(t) { var e = t.completion || {}; e.type = "normal", delete e.arg, t.completion = e; } function Context(t) { this.tryEntries = [{ tryLoc: "root" }], t.forEach(pushTryEntry, this), this.reset(!0); } function values(e) { if (e || "" === e) { var r = e[a]; if (r) return r.call(e); if ("function" == typeof e.next) return e; if (!isNaN(e.length)) { var o = -1, i = function next() { for (; ++o < e.length;) if (n.call(e, o)) return next.value = e[o], next.done = !1, next; return next.value = t, next.done = !0, next; }; return i.next = i; } } throw new TypeError(_typeof(e) + " is not iterable"); } return GeneratorFunction.prototype = GeneratorFunctionPrototype, o(g, "constructor", { value: GeneratorFunctionPrototype, configurable: !0 }), o(GeneratorFunctionPrototype, "constructor", { value: GeneratorFunction, configurable: !0 }), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, u, "GeneratorFunction"), e.isGeneratorFunction = function (t) { var e = "function" == typeof t && t.constructor; return !!e && (e === GeneratorFunction || "GeneratorFunction" === (e.displayName || e.name)); }, e.mark = function (t) { return Object.setPrototypeOf ? Object.setPrototypeOf(t, GeneratorFunctionPrototype) : (t.__proto__ = GeneratorFunctionPrototype, define(t, u, "GeneratorFunction")), t.prototype = Object.create(g), t; }, e.awrap = function (t) { return { __await: t }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, c, function () { return this; }), e.AsyncIterator = AsyncIterator, e.async = function (t, r, n, o, i) { void 0 === i && (i = Promise); var a = new AsyncIterator(wrap(t, r, n, o), i); return e.isGeneratorFunction(r) ? a : a.next().then(function (t) { return t.done ? t.value : a.next(); }); }, defineIteratorMethods(g), define(g, u, "Generator"), define(g, a, function () { return this; }), define(g, "toString", function () { return "[object Generator]"; }), e.keys = function (t) { var e = Object(t), r = []; for (var n in e) r.push(n); return r.reverse(), function next() { for (; r.length;) { var t = r.pop(); if (t in e) return next.value = t, next.done = !1, next; } return next.done = !0, next; }; }, e.values = values, Context.prototype = { constructor: Context, reset: function reset(e) { if (this.prev = 0, this.next = 0, this.sent = this._sent = t, this.done = !1, this.delegate = null, this.method = "next", this.arg = t, this.tryEntries.forEach(resetTryEntry), !e) for (var r in this) "t" === r.charAt(0) && n.call(this, r) && !isNaN(+r.slice(1)) && (this[r] = t); }, stop: function stop() { this.done = !0; var t = this.tryEntries[0].completion; if ("throw" === t.type) throw t.arg; return this.rval; }, dispatchException: function dispatchException(e) { if (this.done) throw e; var r = this; function handle(n, o) { return a.type = "throw", a.arg = e, r.next = n, o && (r.method = "next", r.arg = t), !!o; } for (var o = this.tryEntries.length - 1; o >= 0; --o) { var i = this.tryEntries[o], a = i.completion; if ("root" === i.tryLoc) return handle("end"); if (i.tryLoc <= this.prev) { var c = n.call(i, "catchLoc"), u = n.call(i, "finallyLoc"); if (c && u) { if (this.prev < i.catchLoc) return handle(i.catchLoc, !0); if (this.prev < i.finallyLoc) return handle(i.finallyLoc); } else if (c) { if (this.prev < i.catchLoc) return handle(i.catchLoc, !0); } else { if (!u) throw Error("try statement without catch or finally"); if (this.prev < i.finallyLoc) return handle(i.finallyLoc); } } } }, abrupt: function abrupt(t, e) { for (var r = this.tryEntries.length - 1; r >= 0; --r) { var o = this.tryEntries[r]; if (o.tryLoc <= this.prev && n.call(o, "finallyLoc") && this.prev < o.finallyLoc) { var i = o; break; } } i && ("break" === t || "continue" === t) && i.tryLoc <= e && e <= i.finallyLoc && (i = null); var a = i ? i.completion : {}; return a.type = t, a.arg = e, i ? (this.method = "next", this.next = i.finallyLoc, y) : this.complete(a); }, complete: function complete(t, e) { if ("throw" === t.type) throw t.arg; return "break" === t.type || "continue" === t.type ? this.next = t.arg : "return" === t.type ? (this.rval = this.arg = t.arg, this.method = "return", this.next = "end") : "normal" === t.type && e && (this.next = e), y; }, finish: function finish(t) { for (var e = this.tryEntries.length - 1; e >= 0; --e) { var r = this.tryEntries[e]; if (r.finallyLoc === t) return this.complete(r.completion, r.afterLoc), resetTryEntry(r), y; } }, "catch": function _catch(t) { for (var e = this.tryEntries.length - 1; e >= 0; --e) { var r = this.tryEntries[e]; if (r.tryLoc === t) { var n = r.completion; if ("throw" === n.type) { var o = n.arg; resetTryEntry(r); } return o; } } throw Error("illegal catch attempt"); }, delegateYield: function delegateYield(e, r, n) { return this.delegate = { iterator: values(e), resultName: r, nextLoc: n }, "next" === this.method && (this.arg = t), y; } }, e; }
function asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; }
/**
 * Export Module
 *
 * Handles the export wizard functionality
 */


var ExportModule = {
  currentStep: 1,
  totalSteps: 5,
  jobId: null,
  progressInterval: null,
  /**
   * Initialize module
   */
  init: function init() {
    if (!jQuery('#wp-aie-export').length) {
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
    var $wizard = jQuery('#wp-aie-export');

    // Step navigation
    $wizard.on('click', '.aie-next-step', function () {
      return _this.nextStep();
    });
    $wizard.on('click', '.aie-prev-step', function () {
      return _this.prevStep();
    });

    // Content type
    $wizard.on('change', 'input[name="content_type"]', function (e) {
      return _this.onContentTypeChange(e);
    });

    // Filters
    $wizard.on('change', '.aie-export-filters input, .aie-export-filters select', _utils__WEBPACK_IMPORTED_MODULE_0__["default"].debounce(function () {
      return _this.refreshCount();
    }, 500));
    $wizard.on('click', '.aie-refresh-count', function () {
      return _this.refreshCount();
    });

    // Field selection
    $wizard.on('click', '.aie-select-all-fields', function () {
      return _this.selectAllFields(true);
    });
    $wizard.on('click', '.aie-deselect-all-fields', function () {
      return _this.selectAllFields(false);
    });
    $wizard.on('click', '.aie-select-common-fields', function () {
      return _this.selectCommonFields();
    });

    // Format selection
    $wizard.on('change', 'input[name="format"]', function (e) {
      return _this.onFormatChange(e);
    });

    // Export actions
    $wizard.on('click', '.aie-start-export', function () {
      return _this.startExport();
    });
    $wizard.on('click', '.aie-cancel-export', function () {
      return _this.cancelExport();
    });
    $wizard.on('click', '.aie-download-file', function () {
      return _this.downloadFile();
    });
    $wizard.on('click', '.aie-new-export', function () {
      return _this.resetWizard();
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
    if (step === 2) {
      this.refreshCount();
    }
  },
  nextStep: function nextStep() {
    if (this.currentStep < this.totalSteps) {
      this.showStep(this.currentStep + 1);
    }
  },
  prevStep: function prevStep() {
    if (this.currentStep > 1) {
      this.showStep(this.currentStep - 1);
    }
  },
  /**
   * Handle content type change
   */
  onContentTypeChange: function onContentTypeChange(e) {
    var contentType = jQuery(e.target).val();
    if (contentType === 'media') {
      jQuery('.aie-post-filters').hide();
      jQuery('.aie-media-filters').show();
      jQuery('.aie-post-field-group').hide();
      jQuery('.aie-media-field-group').show();
    } else {
      jQuery('.aie-post-filters').show();
      jQuery('.aie-media-filters').hide();
      jQuery('.aie-post-field-group').show();
      jQuery('.aie-media-field-group').hide();
    }
  },
  /**
   * Refresh item count
   */
  refreshCount: function refreshCount() {
    var _this2 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee() {
      var filters, $count, $spinner, response;
      return _regeneratorRuntime().wrap(function _callee$(_context) {
        while (1) switch (_context.prev = _context.next) {
          case 0:
            filters = _this2.getFilters();
            $count = jQuery('.aie-count-value');
            $spinner = jQuery('.aie-filter-summary .spinner');
            $spinner.addClass('is-active');
            _context.prev = 4;
            _context.next = 7;
            return _utils__WEBPACK_IMPORTED_MODULE_0__["default"].ajax('aie_export_get_count', {
              content_type: jQuery('input[name="content_type"]:checked').val(),
              filters: filters
            });
          case 7:
            response = _context.sent;
            $count.text(response.count || 0);
            _context.next = 15;
            break;
          case 11:
            _context.prev = 11;
            _context.t0 = _context["catch"](4);
            $count.text('-');
            console.error('Count error:', _context.t0);
          case 15:
            _context.prev = 15;
            $spinner.removeClass('is-active');
            return _context.finish(15);
          case 18:
          case "end":
            return _context.stop();
        }
      }, _callee, null, [[4, 11, 15, 18]]);
    }))();
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
   * Get selected fields
   */
  getSelectedFields: function getSelectedFields() {
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
    var _this3 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee2() {
      var fields, data, response;
      return _regeneratorRuntime().wrap(function _callee2$(_context2) {
        while (1) switch (_context2.prev = _context2.next) {
          case 0:
            fields = _this3.getSelectedFields();
            if (!(fields.length === 0)) {
              _context2.next = 4;
              break;
            }
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Please select at least one field to export', 'error');
            return _context2.abrupt("return");
          case 4:
            _context2.prev = 4;
            data = {
              content_type: jQuery('input[name="content_type"]:checked').val(),
              filters: _this3.getFilters(),
              fields: fields,
              format: jQuery('input[name="format"]:checked').val(),
              format_options: {
                csv_delimiter: jQuery('[name="csv_delimiter"]').val(),
                csv_encoding: jQuery('[name="csv_encoding"]').val(),
                csv_include_header: jQuery('[name="csv_include_header"]').is(':checked'),
                json_pretty_print: jQuery('[name="json_pretty_print"]').is(':checked'),
                xml_root: jQuery('[name="xml_root"]').val(),
                xml_item: jQuery('[name="xml_item"]').val()
              }
            };
            _context2.next = 8;
            return _utils__WEBPACK_IMPORTED_MODULE_0__["default"].ajax('aie_export_start', data);
          case 8:
            response = _context2.sent;
            _this3.jobId = response.job_id;
            _this3.showStep(5);
            _this3.startProgressTracking();
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Export started successfully', 'success');
            _context2.next = 18;
            break;
          case 15:
            _context2.prev = 15;
            _context2.t0 = _context2["catch"](4);
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].handleError(_context2.t0, 'Start export');
          case 18:
          case "end":
            return _context2.stop();
        }
      }, _callee2, null, [[4, 15]]);
    }))();
  },
  /**
   * Start progress tracking
   */
  startProgressTracking: function startProgressTracking() {
    var _this4 = this;
    this.progressInterval = setInterval(function () {
      _this4.updateProgress();
    }, 2000);
  },
  /**
   * Update progress
   */
  updateProgress: function updateProgress() {
    var _this5 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee3() {
      var response;
      return _regeneratorRuntime().wrap(function _callee3$(_context3) {
        while (1) switch (_context3.prev = _context3.next) {
          case 0:
            _context3.prev = 0;
            _context3.next = 3;
            return _utils__WEBPACK_IMPORTED_MODULE_0__["default"].ajax('aie_export_get_progress', {
              job_id: _this5.jobId
            });
          case 3:
            response = _context3.sent;
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].updateProgressBar(jQuery('.aie-step-5'), response);
            if (response.status === 'completed') {
              _this5.onExportComplete(response);
            } else if (response.status === 'failed') {
              _this5.onExportFailed(response);
            }
            _context3.next = 11;
            break;
          case 8:
            _context3.prev = 8;
            _context3.t0 = _context3["catch"](0);
            console.error('Progress update error:', _context3.t0);
          case 11:
          case "end":
            return _context3.stop();
        }
      }, _callee3, null, [[0, 8]]);
    }))();
  },
  /**
   * Handle export completion
   */
  onExportComplete: function onExportComplete(result) {
    var _result$estimates;
    clearInterval(this.progressInterval);
    jQuery('.aie-export-results').show();
    jQuery('.aie-result-processed').text(result.processed || 0);
    jQuery('.aie-result-filesize').text(_utils__WEBPACK_IMPORTED_MODULE_0__["default"].formatFileSize(result.file_size || 0));
    jQuery('.aie-result-duration').text(((_result$estimates = result.estimates) === null || _result$estimates === void 0 ? void 0 : _result$estimates.elapsed_formatted) || '0s');
    jQuery('.aie-cancel-export').hide();
    jQuery('.aie-new-export').show();
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Export completed successfully!', 'success');
  },
  /**
   * Handle export failure
   */
  onExportFailed: function onExportFailed(result) {
    clearInterval(this.progressInterval);
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Export failed: ' + (result.error || 'Unknown error'), 'error');
  },
  /**
   * Download export file
   */
  downloadFile: function downloadFile() {
    var _this6 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee4() {
      var response;
      return _regeneratorRuntime().wrap(function _callee4$(_context4) {
        while (1) switch (_context4.prev = _context4.next) {
          case 0:
            _context4.prev = 0;
            _context4.next = 3;
            return _utils__WEBPACK_IMPORTED_MODULE_0__["default"].ajax('aie_export_download', {
              job_id: _this6.jobId
            });
          case 3:
            response = _context4.sent;
            if (response.download_url) {
              _utils__WEBPACK_IMPORTED_MODULE_0__["default"].downloadFile(response.download_url, response.filename);
            }
            _context4.next = 10;
            break;
          case 7:
            _context4.prev = 7;
            _context4.t0 = _context4["catch"](0);
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].handleError(_context4.t0, 'Download file');
          case 10:
          case "end":
            return _context4.stop();
        }
      }, _callee4, null, [[0, 7]]);
    }))();
  },
  /**
   * Cancel export
   */
  cancelExport: function cancelExport() {
    var _this7 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee5() {
      return _regeneratorRuntime().wrap(function _callee5$(_context5) {
        while (1) switch (_context5.prev = _context5.next) {
          case 0:
            if (confirm('Are you sure you want to cancel this export?')) {
              _context5.next = 2;
              break;
            }
            return _context5.abrupt("return");
          case 2:
            _context5.prev = 2;
            _context5.next = 5;
            return _utils__WEBPACK_IMPORTED_MODULE_0__["default"].ajax('aie_export_cancel', {
              job_id: _this7.jobId
            });
          case 5:
            clearInterval(_this7.progressInterval);
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Export cancelled', 'info');
            _this7.resetWizard();
            _context5.next = 13;
            break;
          case 10:
            _context5.prev = 10;
            _context5.t0 = _context5["catch"](2);
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].handleError(_context5.t0, 'Cancel export');
          case 13:
          case "end":
            return _context5.stop();
        }
      }, _callee5, null, [[2, 10]]);
    }))();
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
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ExportModule);

/***/ }),

/***/ "./src/js/modules/function_library.js":
/*!********************************************!*\
  !*** ./src/js/modules/function_library.js ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _utils_notifications__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../utils/notifications */ "./src/js/utils/notifications.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _slicedToArray(r, e) { return _arrayWithHoles(r) || _iterableToArrayLimit(r, e) || _unsupportedIterableToArray(r, e) || _nonIterableRest(); }
function _nonIterableRest() { throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
function _iterableToArrayLimit(r, l) { var t = null == r ? null : "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (null != t) { var e, n, i, u, a = [], f = !0, o = !1; try { if (i = (t = t.call(r)).next, 0 === l) { if (Object(t) !== t) return; f = !1; } else for (; !(f = (e = i.call(t)).done) && (a.push(e.value), a.length !== l); f = !0); } catch (r) { o = !0, n = r; } finally { try { if (!f && null != t["return"] && (u = t["return"](), Object(u) !== u)) return; } finally { if (o) throw n; } } return a; } }
function _arrayWithHoles(r) { if (Array.isArray(r)) return r; }
function _regeneratorRuntime() { "use strict"; /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime = function _regeneratorRuntime() { return e; }; var t, e = {}, r = Object.prototype, n = r.hasOwnProperty, o = Object.defineProperty || function (t, e, r) { t[e] = r.value; }, i = "function" == typeof Symbol ? Symbol : {}, a = i.iterator || "@@iterator", c = i.asyncIterator || "@@asyncIterator", u = i.toStringTag || "@@toStringTag"; function define(t, e, r) { return Object.defineProperty(t, e, { value: r, enumerable: !0, configurable: !0, writable: !0 }), t[e]; } try { define({}, ""); } catch (t) { define = function define(t, e, r) { return t[e] = r; }; } function wrap(t, e, r, n) { var i = e && e.prototype instanceof Generator ? e : Generator, a = Object.create(i.prototype), c = new Context(n || []); return o(a, "_invoke", { value: makeInvokeMethod(t, r, c) }), a; } function tryCatch(t, e, r) { try { return { type: "normal", arg: t.call(e, r) }; } catch (t) { return { type: "throw", arg: t }; } } e.wrap = wrap; var h = "suspendedStart", l = "suspendedYield", f = "executing", s = "completed", y = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var p = {}; define(p, a, function () { return this; }); var d = Object.getPrototypeOf, v = d && d(d(values([]))); v && v !== r && n.call(v, a) && (p = v); var g = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(p); function defineIteratorMethods(t) { ["next", "throw", "return"].forEach(function (e) { define(t, e, function (t) { return this._invoke(e, t); }); }); } function AsyncIterator(t, e) { function invoke(r, o, i, a) { var c = tryCatch(t[r], t, o); if ("throw" !== c.type) { var u = c.arg, h = u.value; return h && "object" == _typeof(h) && n.call(h, "__await") ? e.resolve(h.__await).then(function (t) { invoke("next", t, i, a); }, function (t) { invoke("throw", t, i, a); }) : e.resolve(h).then(function (t) { u.value = t, i(u); }, function (t) { return invoke("throw", t, i, a); }); } a(c.arg); } var r; o(this, "_invoke", { value: function value(t, n) { function callInvokeWithMethodAndArg() { return new e(function (e, r) { invoke(t, n, e, r); }); } return r = r ? r.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); } }); } function makeInvokeMethod(e, r, n) { var o = h; return function (i, a) { if (o === f) throw Error("Generator is already running"); if (o === s) { if ("throw" === i) throw a; return { value: t, done: !0 }; } for (n.method = i, n.arg = a;;) { var c = n.delegate; if (c) { var u = maybeInvokeDelegate(c, n); if (u) { if (u === y) continue; return u; } } if ("next" === n.method) n.sent = n._sent = n.arg;else if ("throw" === n.method) { if (o === h) throw o = s, n.arg; n.dispatchException(n.arg); } else "return" === n.method && n.abrupt("return", n.arg); o = f; var p = tryCatch(e, r, n); if ("normal" === p.type) { if (o = n.done ? s : l, p.arg === y) continue; return { value: p.arg, done: n.done }; } "throw" === p.type && (o = s, n.method = "throw", n.arg = p.arg); } }; } function maybeInvokeDelegate(e, r) { var n = r.method, o = e.iterator[n]; if (o === t) return r.delegate = null, "throw" === n && e.iterator["return"] && (r.method = "return", r.arg = t, maybeInvokeDelegate(e, r), "throw" === r.method) || "return" !== n && (r.method = "throw", r.arg = new TypeError("The iterator does not provide a '" + n + "' method")), y; var i = tryCatch(o, e.iterator, r.arg); if ("throw" === i.type) return r.method = "throw", r.arg = i.arg, r.delegate = null, y; var a = i.arg; return a ? a.done ? (r[e.resultName] = a.value, r.next = e.nextLoc, "return" !== r.method && (r.method = "next", r.arg = t), r.delegate = null, y) : a : (r.method = "throw", r.arg = new TypeError("iterator result is not an object"), r.delegate = null, y); } function pushTryEntry(t) { var e = { tryLoc: t[0] }; 1 in t && (e.catchLoc = t[1]), 2 in t && (e.finallyLoc = t[2], e.afterLoc = t[3]), this.tryEntries.push(e); } function resetTryEntry(t) { var e = t.completion || {}; e.type = "normal", delete e.arg, t.completion = e; } function Context(t) { this.tryEntries = [{ tryLoc: "root" }], t.forEach(pushTryEntry, this), this.reset(!0); } function values(e) { if (e || "" === e) { var r = e[a]; if (r) return r.call(e); if ("function" == typeof e.next) return e; if (!isNaN(e.length)) { var o = -1, i = function next() { for (; ++o < e.length;) if (n.call(e, o)) return next.value = e[o], next.done = !1, next; return next.value = t, next.done = !0, next; }; return i.next = i; } } throw new TypeError(_typeof(e) + " is not iterable"); } return GeneratorFunction.prototype = GeneratorFunctionPrototype, o(g, "constructor", { value: GeneratorFunctionPrototype, configurable: !0 }), o(GeneratorFunctionPrototype, "constructor", { value: GeneratorFunction, configurable: !0 }), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, u, "GeneratorFunction"), e.isGeneratorFunction = function (t) { var e = "function" == typeof t && t.constructor; return !!e && (e === GeneratorFunction || "GeneratorFunction" === (e.displayName || e.name)); }, e.mark = function (t) { return Object.setPrototypeOf ? Object.setPrototypeOf(t, GeneratorFunctionPrototype) : (t.__proto__ = GeneratorFunctionPrototype, define(t, u, "GeneratorFunction")), t.prototype = Object.create(g), t; }, e.awrap = function (t) { return { __await: t }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, c, function () { return this; }), e.AsyncIterator = AsyncIterator, e.async = function (t, r, n, o, i) { void 0 === i && (i = Promise); var a = new AsyncIterator(wrap(t, r, n, o), i); return e.isGeneratorFunction(r) ? a : a.next().then(function (t) { return t.done ? t.value : a.next(); }); }, defineIteratorMethods(g), define(g, u, "Generator"), define(g, a, function () { return this; }), define(g, "toString", function () { return "[object Generator]"; }), e.keys = function (t) { var e = Object(t), r = []; for (var n in e) r.push(n); return r.reverse(), function next() { for (; r.length;) { var t = r.pop(); if (t in e) return next.value = t, next.done = !1, next; } return next.done = !0, next; }; }, e.values = values, Context.prototype = { constructor: Context, reset: function reset(e) { if (this.prev = 0, this.next = 0, this.sent = this._sent = t, this.done = !1, this.delegate = null, this.method = "next", this.arg = t, this.tryEntries.forEach(resetTryEntry), !e) for (var r in this) "t" === r.charAt(0) && n.call(this, r) && !isNaN(+r.slice(1)) && (this[r] = t); }, stop: function stop() { this.done = !0; var t = this.tryEntries[0].completion; if ("throw" === t.type) throw t.arg; return this.rval; }, dispatchException: function dispatchException(e) { if (this.done) throw e; var r = this; function handle(n, o) { return a.type = "throw", a.arg = e, r.next = n, o && (r.method = "next", r.arg = t), !!o; } for (var o = this.tryEntries.length - 1; o >= 0; --o) { var i = this.tryEntries[o], a = i.completion; if ("root" === i.tryLoc) return handle("end"); if (i.tryLoc <= this.prev) { var c = n.call(i, "catchLoc"), u = n.call(i, "finallyLoc"); if (c && u) { if (this.prev < i.catchLoc) return handle(i.catchLoc, !0); if (this.prev < i.finallyLoc) return handle(i.finallyLoc); } else if (c) { if (this.prev < i.catchLoc) return handle(i.catchLoc, !0); } else { if (!u) throw Error("try statement without catch or finally"); if (this.prev < i.finallyLoc) return handle(i.finallyLoc); } } } }, abrupt: function abrupt(t, e) { for (var r = this.tryEntries.length - 1; r >= 0; --r) { var o = this.tryEntries[r]; if (o.tryLoc <= this.prev && n.call(o, "finallyLoc") && this.prev < o.finallyLoc) { var i = o; break; } } i && ("break" === t || "continue" === t) && i.tryLoc <= e && e <= i.finallyLoc && (i = null); var a = i ? i.completion : {}; return a.type = t, a.arg = e, i ? (this.method = "next", this.next = i.finallyLoc, y) : this.complete(a); }, complete: function complete(t, e) { if ("throw" === t.type) throw t.arg; return "break" === t.type || "continue" === t.type ? this.next = t.arg : "return" === t.type ? (this.rval = this.arg = t.arg, this.method = "return", this.next = "end") : "normal" === t.type && e && (this.next = e), y; }, finish: function finish(t) { for (var e = this.tryEntries.length - 1; e >= 0; --e) { var r = this.tryEntries[e]; if (r.finallyLoc === t) return this.complete(r.completion, r.afterLoc), resetTryEntry(r), y; } }, "catch": function _catch(t) { for (var e = this.tryEntries.length - 1; e >= 0; --e) { var r = this.tryEntries[e]; if (r.tryLoc === t) { var n = r.completion; if ("throw" === n.type) { var o = n.arg; resetTryEntry(r); } return o; } } throw Error("illegal catch attempt"); }, delegateYield: function delegateYield(e, r, n) { return this.delegate = { iterator: values(e), resultName: r, nextLoc: n }, "next" === this.method && (this.arg = t), y; } }, e; }
function asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; }
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
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee() {
      var modal;
      return _regeneratorRuntime().wrap(function _callee$(_context) {
        while (1) switch (_context.prev = _context.next) {
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
      var _previewModal$querySe, _previewModal$querySe2;
      (_previewModal$querySe = previewModal.querySelector('.aie-use-snippet')) === null || _previewModal$querySe === void 0 || _previewModal$querySe.addEventListener('click', function () {
        _this2.importSnippet(_this2.currentSnippet, false);
      });
      (_previewModal$querySe2 = previewModal.querySelector('.aie-customize-snippet')) === null || _previewModal$querySe2 === void 0 || _previewModal$querySe2.addEventListener('click', function () {
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
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee2() {
      var _window$aieData;
      var category, grid, _window$aieData2, response, data, _data$data;
      return _regeneratorRuntime().wrap(function _callee2$(_context2) {
        while (1) switch (_context2.prev = _context2.next) {
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
            grid.innerHTML = "\n\t\t\t<div class=\"aie-loading-snippets\">\n\t\t\t\t<span class=\"spinner is-active\"></span>\n\t\t\t\t<p>".concat(((_window$aieData = window.aieData) === null || _window$aieData === void 0 || (_window$aieData = _window$aieData.i18n) === null || _window$aieData === void 0 ? void 0 : _window$aieData.loading) || 'Loading snippets...', "</p>\n\t\t\t</div>\n\t\t");
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
      }, _callee2, null, [[5, 20]]);
    }))();
  },
  /**
   * Render categories sidebar
   */
  renderCategories: function renderCategories() {
    var _window$aieData3,
      _this4 = this;
    var categoriesList = document.getElementById('aie-categories-list');
    if (!categoriesList) {
      return;
    }
    var totalSnippets = Object.keys(this.allSnippets).length;
    var html = "\n\t\t\t<li class=\"aie-category-item ".concat(this.currentCategory === '' ? 'active' : '', "\" data-category=\"\">\n\t\t\t\t<span class=\"dashicons dashicons-category\"></span>\n\t\t\t\t<span class=\"aie-category-name\">").concat(((_window$aieData3 = window.aieData) === null || _window$aieData3 === void 0 || (_window$aieData3 = _window$aieData3.i18n) === null || _window$aieData3 === void 0 ? void 0 : _window$aieData3.all_snippets) || 'All Snippets', "</span>\n\t\t\t\t<span class=\"aie-category-count\">").concat(totalSnippets, "</span>\n\t\t\t</li>\n\t\t");
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
      var _window$aieData4;
      grid.innerHTML = "\n\t\t\t\t<div class=\"aie-no-snippets\">\n\t\t\t\t\t<span class=\"dashicons dashicons-info\" style=\"width: auto; height: auto;\"></span>\n\t\t\t\t\t<p>".concat(((_window$aieData4 = window.aieData) === null || _window$aieData4 === void 0 || (_window$aieData4 = _window$aieData4.i18n) === null || _window$aieData4 === void 0 ? void 0 : _window$aieData4.no_snippets) || 'No snippets found', "</p>\n\t\t\t\t</div>\n\t\t\t");
      return;
    }

    // Check if "Use" button should be shown
    var currentPage = ((_window$aieData5 = window.aieData) === null || _window$aieData5 === void 0 ? void 0 : _window$aieData5.currentPage) || '';
    var allowedPages = ['wp-advanced-import-export', 'wp-aie-export', 'wp-aie-content-sync', 'wp-aie-functions' // Add Functions page
    ];
    var showUseButton = allowedPages.includes(currentPage);
    grid.innerHTML = snippets.map(function (_ref5) {
      var _window$aieData6, _window$aieData7;
      var _ref6 = _slicedToArray(_ref5, 2),
        key = _ref6[0],
        snippet = _ref6[1];
      return "\n\t\t\t<div class=\"aie-snippet-card\" data-snippet-key=\"".concat(key, "\">\n\t\t\t\t<div class=\"aie-snippet-header\">\n\t\t\t\t\t<h3 class=\"aie-snippet-name\">").concat(_this5.escapeHtml(snippet.name), "</h3>\n\t\t\t\t\t<span class=\"aie-snippet-category-badge\">").concat(_this5.getCategoryLabel(snippet.category), "</span>\n\t\t\t\t</div>\n\t\t\t\t<p class=\"aie-snippet-description\">").concat(_this5.escapeHtml(snippet.description), "</p>\n\t\t\t\t<div class=\"aie-snippet-tags\">\n\t\t\t\t\t").concat(snippet.tags ? snippet.tags.map(function (tag) {
        return "<span class=\"aie-tag\">".concat(_this5.escapeHtml(tag), "</span>");
      }).join('') : '', "\n\t\t\t\t</div>\n\t\t\t\t<div class=\"aie-snippet-actions\">\n\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-preview-snippet\" data-snippet-key=\"").concat(key, "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-visibility\"></span>\n\t\t\t\t\t\t").concat(((_window$aieData6 = window.aieData) === null || _window$aieData6 === void 0 || (_window$aieData6 = _window$aieData6.i18n) === null || _window$aieData6 === void 0 ? void 0 : _window$aieData6.preview) || 'Preview', "\n\t\t\t\t\t</button>\n\t\t\t\t\t").concat(showUseButton ? "<button type=\"button\" class=\"button button-primary button-small aie-quick-import\" data-snippet-key=\"".concat(key, "\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-plus\"></span>\n\t\t\t\t\t\t").concat(((_window$aieData7 = window.aieData) === null || _window$aieData7 === void 0 || (_window$aieData7 = _window$aieData7.i18n) === null || _window$aieData7 === void 0 ? void 0 : _window$aieData7.use) || 'Use', "\n\t\t\t\t\t</button>") : '', "\n\t\t\t\t</div>\n\t\t\t</div>\n\t\t");
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
        _this5.importSnippet(key, false);
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
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee3() {
      var _window$aieData8;
      var grid, _window$aieData9, response, data, _data$data2, originalSnippets;
      return _regeneratorRuntime().wrap(function _callee3$(_context3) {
        while (1) switch (_context3.prev = _context3.next) {
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
            grid.innerHTML = "\n\t\t\t<div class=\"aie-loading-snippets\">\n\t\t\t\t<span class=\"spinner is-active\"></span>\n\t\t\t\t<p>".concat(((_window$aieData8 = window.aieData) === null || _window$aieData8 === void 0 || (_window$aieData8 = _window$aieData8.i18n) === null || _window$aieData8 === void 0 ? void 0 : _window$aieData8.searching) || 'Searching...', "</p>\n\t\t\t</div>\n\t\t");
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
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showError)(_context3.t0.message);
          case 27:
          case "end":
            return _context3.stop();
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
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee4() {
      var customize, snippet, libraryModal, previewModal, editorModal, _window$aieData11, _window$aieData12, response, data, _data$data3;
      return _regeneratorRuntime().wrap(function _callee4$(_context4) {
        while (1) switch (_context4.prev = _context4.next) {
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
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.clearModalErrors)();
              document.getElementById('aie-function-id').value = '';
              document.getElementById('aie-function-name').value = snippet.name;
              document.getElementById('aie-function-description').value = snippet.description;
              document.getElementById('aie-function-category').value = snippet.category;
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
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showNotice)(((_window$aieData12 = window.aieData) === null || _window$aieData12 === void 0 || (_window$aieData12 = _window$aieData12.i18n) === null || _window$aieData12 === void 0 ? void 0 : _window$aieData12.snippet_imported) || 'Snippet imported successfully');
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
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showError)(_context4.t0.message);
          case 37:
          case "end":
            return _context4.stop();
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

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _utils_notifications__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../utils/notifications */ "./src/js/utils/notifications.js");
/* harmony import */ var _function_library__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./function_library */ "./src/js/modules/function_library.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _regeneratorRuntime() { "use strict"; /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime = function _regeneratorRuntime() { return e; }; var t, e = {}, r = Object.prototype, n = r.hasOwnProperty, o = Object.defineProperty || function (t, e, r) { t[e] = r.value; }, i = "function" == typeof Symbol ? Symbol : {}, a = i.iterator || "@@iterator", c = i.asyncIterator || "@@asyncIterator", u = i.toStringTag || "@@toStringTag"; function define(t, e, r) { return Object.defineProperty(t, e, { value: r, enumerable: !0, configurable: !0, writable: !0 }), t[e]; } try { define({}, ""); } catch (t) { define = function define(t, e, r) { return t[e] = r; }; } function wrap(t, e, r, n) { var i = e && e.prototype instanceof Generator ? e : Generator, a = Object.create(i.prototype), c = new Context(n || []); return o(a, "_invoke", { value: makeInvokeMethod(t, r, c) }), a; } function tryCatch(t, e, r) { try { return { type: "normal", arg: t.call(e, r) }; } catch (t) { return { type: "throw", arg: t }; } } e.wrap = wrap; var h = "suspendedStart", l = "suspendedYield", f = "executing", s = "completed", y = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var p = {}; define(p, a, function () { return this; }); var d = Object.getPrototypeOf, v = d && d(d(values([]))); v && v !== r && n.call(v, a) && (p = v); var g = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(p); function defineIteratorMethods(t) { ["next", "throw", "return"].forEach(function (e) { define(t, e, function (t) { return this._invoke(e, t); }); }); } function AsyncIterator(t, e) { function invoke(r, o, i, a) { var c = tryCatch(t[r], t, o); if ("throw" !== c.type) { var u = c.arg, h = u.value; return h && "object" == _typeof(h) && n.call(h, "__await") ? e.resolve(h.__await).then(function (t) { invoke("next", t, i, a); }, function (t) { invoke("throw", t, i, a); }) : e.resolve(h).then(function (t) { u.value = t, i(u); }, function (t) { return invoke("throw", t, i, a); }); } a(c.arg); } var r; o(this, "_invoke", { value: function value(t, n) { function callInvokeWithMethodAndArg() { return new e(function (e, r) { invoke(t, n, e, r); }); } return r = r ? r.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); } }); } function makeInvokeMethod(e, r, n) { var o = h; return function (i, a) { if (o === f) throw Error("Generator is already running"); if (o === s) { if ("throw" === i) throw a; return { value: t, done: !0 }; } for (n.method = i, n.arg = a;;) { var c = n.delegate; if (c) { var u = maybeInvokeDelegate(c, n); if (u) { if (u === y) continue; return u; } } if ("next" === n.method) n.sent = n._sent = n.arg;else if ("throw" === n.method) { if (o === h) throw o = s, n.arg; n.dispatchException(n.arg); } else "return" === n.method && n.abrupt("return", n.arg); o = f; var p = tryCatch(e, r, n); if ("normal" === p.type) { if (o = n.done ? s : l, p.arg === y) continue; return { value: p.arg, done: n.done }; } "throw" === p.type && (o = s, n.method = "throw", n.arg = p.arg); } }; } function maybeInvokeDelegate(e, r) { var n = r.method, o = e.iterator[n]; if (o === t) return r.delegate = null, "throw" === n && e.iterator["return"] && (r.method = "return", r.arg = t, maybeInvokeDelegate(e, r), "throw" === r.method) || "return" !== n && (r.method = "throw", r.arg = new TypeError("The iterator does not provide a '" + n + "' method")), y; var i = tryCatch(o, e.iterator, r.arg); if ("throw" === i.type) return r.method = "throw", r.arg = i.arg, r.delegate = null, y; var a = i.arg; return a ? a.done ? (r[e.resultName] = a.value, r.next = e.nextLoc, "return" !== r.method && (r.method = "next", r.arg = t), r.delegate = null, y) : a : (r.method = "throw", r.arg = new TypeError("iterator result is not an object"), r.delegate = null, y); } function pushTryEntry(t) { var e = { tryLoc: t[0] }; 1 in t && (e.catchLoc = t[1]), 2 in t && (e.finallyLoc = t[2], e.afterLoc = t[3]), this.tryEntries.push(e); } function resetTryEntry(t) { var e = t.completion || {}; e.type = "normal", delete e.arg, t.completion = e; } function Context(t) { this.tryEntries = [{ tryLoc: "root" }], t.forEach(pushTryEntry, this), this.reset(!0); } function values(e) { if (e || "" === e) { var r = e[a]; if (r) return r.call(e); if ("function" == typeof e.next) return e; if (!isNaN(e.length)) { var o = -1, i = function next() { for (; ++o < e.length;) if (n.call(e, o)) return next.value = e[o], next.done = !1, next; return next.value = t, next.done = !0, next; }; return i.next = i; } } throw new TypeError(_typeof(e) + " is not iterable"); } return GeneratorFunction.prototype = GeneratorFunctionPrototype, o(g, "constructor", { value: GeneratorFunctionPrototype, configurable: !0 }), o(GeneratorFunctionPrototype, "constructor", { value: GeneratorFunction, configurable: !0 }), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, u, "GeneratorFunction"), e.isGeneratorFunction = function (t) { var e = "function" == typeof t && t.constructor; return !!e && (e === GeneratorFunction || "GeneratorFunction" === (e.displayName || e.name)); }, e.mark = function (t) { return Object.setPrototypeOf ? Object.setPrototypeOf(t, GeneratorFunctionPrototype) : (t.__proto__ = GeneratorFunctionPrototype, define(t, u, "GeneratorFunction")), t.prototype = Object.create(g), t; }, e.awrap = function (t) { return { __await: t }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, c, function () { return this; }), e.AsyncIterator = AsyncIterator, e.async = function (t, r, n, o, i) { void 0 === i && (i = Promise); var a = new AsyncIterator(wrap(t, r, n, o), i); return e.isGeneratorFunction(r) ? a : a.next().then(function (t) { return t.done ? t.value : a.next(); }); }, defineIteratorMethods(g), define(g, u, "Generator"), define(g, a, function () { return this; }), define(g, "toString", function () { return "[object Generator]"; }), e.keys = function (t) { var e = Object(t), r = []; for (var n in e) r.push(n); return r.reverse(), function next() { for (; r.length;) { var t = r.pop(); if (t in e) return next.value = t, next.done = !1, next; } return next.done = !0, next; }; }, e.values = values, Context.prototype = { constructor: Context, reset: function reset(e) { if (this.prev = 0, this.next = 0, this.sent = this._sent = t, this.done = !1, this.delegate = null, this.method = "next", this.arg = t, this.tryEntries.forEach(resetTryEntry), !e) for (var r in this) "t" === r.charAt(0) && n.call(this, r) && !isNaN(+r.slice(1)) && (this[r] = t); }, stop: function stop() { this.done = !0; var t = this.tryEntries[0].completion; if ("throw" === t.type) throw t.arg; return this.rval; }, dispatchException: function dispatchException(e) { if (this.done) throw e; var r = this; function handle(n, o) { return a.type = "throw", a.arg = e, r.next = n, o && (r.method = "next", r.arg = t), !!o; } for (var o = this.tryEntries.length - 1; o >= 0; --o) { var i = this.tryEntries[o], a = i.completion; if ("root" === i.tryLoc) return handle("end"); if (i.tryLoc <= this.prev) { var c = n.call(i, "catchLoc"), u = n.call(i, "finallyLoc"); if (c && u) { if (this.prev < i.catchLoc) return handle(i.catchLoc, !0); if (this.prev < i.finallyLoc) return handle(i.finallyLoc); } else if (c) { if (this.prev < i.catchLoc) return handle(i.catchLoc, !0); } else { if (!u) throw Error("try statement without catch or finally"); if (this.prev < i.finallyLoc) return handle(i.finallyLoc); } } } }, abrupt: function abrupt(t, e) { for (var r = this.tryEntries.length - 1; r >= 0; --r) { var o = this.tryEntries[r]; if (o.tryLoc <= this.prev && n.call(o, "finallyLoc") && this.prev < o.finallyLoc) { var i = o; break; } } i && ("break" === t || "continue" === t) && i.tryLoc <= e && e <= i.finallyLoc && (i = null); var a = i ? i.completion : {}; return a.type = t, a.arg = e, i ? (this.method = "next", this.next = i.finallyLoc, y) : this.complete(a); }, complete: function complete(t, e) { if ("throw" === t.type) throw t.arg; return "break" === t.type || "continue" === t.type ? this.next = t.arg : "return" === t.type ? (this.rval = this.arg = t.arg, this.method = "return", this.next = "end") : "normal" === t.type && e && (this.next = e), y; }, finish: function finish(t) { for (var e = this.tryEntries.length - 1; e >= 0; --e) { var r = this.tryEntries[e]; if (r.finallyLoc === t) return this.complete(r.completion, r.afterLoc), resetTryEntry(r), y; } }, "catch": function _catch(t) { for (var e = this.tryEntries.length - 1; e >= 0; --e) { var r = this.tryEntries[e]; if (r.tryLoc === t) { var n = r.completion; if ("throw" === n.type) { var o = n.arg; resetTryEntry(r); } return o; } } throw Error("illegal catch attempt"); }, delegateYield: function delegateYield(e, r, n) { return this.delegate = { iterator: values(e), resultName: r, nextLoc: n }, "next" === this.method && (this.arg = t), y; } }, e; }
function asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; }
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
    status: '',
    category: '',
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
    _function_library__WEBPACK_IMPORTED_MODULE_1__["default"].init(this);
  },
  /**
   * Bind event handlers
   */
  bindEvents: function bindEvents() {
    var _document$querySelect,
      _this = this,
      _document$querySelect2,
      _document$getElementB,
      _document$getElementB2,
      _document$getElementB3,
      _document$querySelect3,
      _document$querySelect4,
      _document$querySelect5,
      _document$querySelect6,
      _document$querySelect7;
    // New function button
    (_document$querySelect = document.querySelector('.aie-new-function')) === null || _document$querySelect === void 0 || _document$querySelect.addEventListener('click', function () {
      _this.openEditorModal();
    });

    // Browse library button
    (_document$querySelect2 = document.querySelector('.aie-browse-library')) === null || _document$querySelect2 === void 0 || _document$querySelect2.addEventListener('click', function () {
      _function_library__WEBPACK_IMPORTED_MODULE_1__["default"].openLibrary();
    });

    // Filter controls
    (_document$getElementB = document.getElementById('aie-filter-status')) === null || _document$getElementB === void 0 || _document$getElementB.addEventListener('change', function (e) {
      _this.filters.status = e.target.value;
      _this.currentPage = 1;
      _this.loadFunctions();
    });
    (_document$getElementB2 = document.getElementById('aie-filter-category')) === null || _document$getElementB2 === void 0 || _document$getElementB2.addEventListener('change', function (e) {
      _this.filters.category = e.target.value;
      _this.currentPage = 1;
      _this.loadFunctions();
    });

    // Search with debounce
    var searchTimeout;
    (_document$getElementB3 = document.getElementById('aie-filter-search')) === null || _document$getElementB3 === void 0 || _document$getElementB3.addEventListener('input', function (e) {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(function () {
        _this.filters.search = e.target.value;
        _this.currentPage = 1;
        _this.loadFunctions();
      }, 500);
    });

    // Clear filters
    (_document$querySelect3 = document.querySelector('.aie-filter-clear')) === null || _document$querySelect3 === void 0 || _document$querySelect3.addEventListener('click', function () {
      _this.clearFilters();
    });

    // Pagination
    (_document$querySelect4 = document.querySelector('.aie-prev-page')) === null || _document$querySelect4 === void 0 || _document$querySelect4.addEventListener('click', function () {
      if (_this.currentPage > 1) {
        _this.currentPage--;
        _this.loadFunctions();
      }
    });
    (_document$querySelect5 = document.querySelector('.aie-next-page')) === null || _document$querySelect5 === void 0 || _document$querySelect5.addEventListener('click', function () {
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
    (_document$querySelect6 = document.querySelector('.aie-save-function')) === null || _document$querySelect6 === void 0 || _document$querySelect6.addEventListener('click', function () {
      _this.saveFunction();
    });

    // Test function
    (_document$querySelect7 = document.querySelector('.aie-test-function')) === null || _document$querySelect7 === void 0 || _document$querySelect7.addEventListener('click', function () {
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
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee() {
      var _window$aieData;
      var tbody, _window$aieData2, response, data, _data$data;
      return _regeneratorRuntime().wrap(function _callee$(_context) {
        while (1) switch (_context.prev = _context.next) {
          case 0:
            tbody = document.getElementById('aie-functions-tbody');
            if (tbody) {
              _context.next = 3;
              break;
            }
            return _context.abrupt("return");
          case 3:
            // Show loading
            tbody.innerHTML = "\n\t\t\t<tr class=\"aie-loading-row\">\n\t\t\t\t<td colspan=\"6\" style=\"text-align:center;\">\n\t\t\t\t\t<span class=\"spinner is-active\"></span>\n\t\t\t\t\t".concat(((_window$aieData = window.aieData) === null || _window$aieData === void 0 || (_window$aieData = _window$aieData.i18n) === null || _window$aieData === void 0 ? void 0 : _window$aieData.loading) || 'Loading...', "\n\t\t\t\t</td>\n\t\t\t</tr>\n\t\t");
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
                status: _this2.filters.status,
                category: _this2.filters.category,
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
            tbody.innerHTML = "\n\t\t\t\t<tr>\n\t\t\t\t\t<td colspan=\"6\" style=\"text-align:center; color:#dc3232;\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-warning\"></span>\n\t\t\t\t\t\t".concat(_context.t0.message, "\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t");
          case 23:
          case "end":
            return _context.stop();
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
      var _window$aieData3;
      tbody.innerHTML = "\n\t\t\t\t<tr>\n\t\t\t\t\t<td colspan=\"6\" style=\"text-align:center; padding:40px;\">\n\t\t\t\t\t\t<div style=\"display:flex; flex-direction:column; align-items:center; gap:10px;\">\n\t\t\t\t\t\t\t<span class=\"dashicons dashicons-info\" style=\"font-size:48px; opacity:0.3;\"></span>\n\t\t\t\t\t\t\t<p style=\"margin:23px 0 0 0; color:#666;\">\n\t\t\t\t\t\t\t\t".concat(((_window$aieData3 = window.aieData) === null || _window$aieData3 === void 0 || (_window$aieData3 = _window$aieData3.i18n) === null || _window$aieData3 === void 0 ? void 0 : _window$aieData3.no_functions) || 'No functions found. Create your first function or browse the library.', "\n\t\t\t\t\t\t\t</p>\n\t\t\t\t\t\t</div>\n\t\t\t\t\t</td>\n\t\t\t\t</tr>\n\t\t\t");
      return;
    }
    tbody.innerHTML = functions.map(function (func) {
      return "\n\t\t\t<tr data-function-id=\"".concat(func.id, "\">\n\t\t\t\t<td class=\"column-name\">\n\t\t\t\t\t<strong>").concat(_this3.escapeHtml(func.name), "</strong>\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-description\">\n\t\t\t\t\t").concat(func.description ? _this3.escapeHtml(func.description) : '<em style="color:#999;">No description</em>', "\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-category\">\n\t\t\t\t\t").concat(_this3.getCategoryBadge(func.category), "\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-status\">\n\t\t\t\t\t").concat(_this3.getStatusBadge(func.status), "\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-usage\">\n\t\t\t\t\t").concat(func.usage_count || 0, "\n\t\t\t\t</td>\n\t\t\t\t<td class=\"column-actions\">\n\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-edit-function\" data-id=\"").concat(func.id, "\" title=\"Edit\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-edit\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t\t<button type=\"button\" class=\"button button-small aie-delete-function\" data-id=\"").concat(func.id, "\" title=\"Delete\">\n\t\t\t\t\t\t<span class=\"dashicons dashicons-trash\"></span>\n\t\t\t\t\t</button>\n\t\t\t\t</td>\n\t\t\t</tr>\n\t\t");
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
        var _ref = _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee2(e) {
          var _window$aieData4;
          var id, confirmed;
          return _regeneratorRuntime().wrap(function _callee2$(_context2) {
            while (1) switch (_context2.prev = _context2.next) {
              case 0:
                id = e.currentTarget.dataset.id;
                _context2.next = 3;
                return (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.confirmDialog)(((_window$aieData4 = window.aieData) === null || _window$aieData4 === void 0 || (_window$aieData4 = _window$aieData4.i18n) === null || _window$aieData4 === void 0 ? void 0 : _window$aieData4.confirm_delete) || 'Are you sure you want to delete this function?');
              case 3:
                confirmed = _context2.sent;
                if (confirmed) {
                  _this3.deleteFunction(id);
                }
              case 5:
              case "end":
                return _context2.stop();
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
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee3() {
      var functionId, modal, title, form, codeTextarea, _window$aieData5, _window$aieData6, response, data, _data$data2, func, _window$aieData7, defaultCode;
      return _regeneratorRuntime().wrap(function _callee3$(_context3) {
        while (1) switch (_context3.prev = _context3.next) {
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
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.clearModalErrors)(modal);

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
            title.textContent = ((_window$aieData5 = window.aieData) === null || _window$aieData5 === void 0 || (_window$aieData5 = _window$aieData5.i18n) === null || _window$aieData5 === void 0 ? void 0 : _window$aieData5.edit_function) || 'Edit Function';
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
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showModalError)(_context3.t0.message, modal);
            return _context3.abrupt("return");
          case 40:
            _context3.next = 45;
            break;
          case 42:
            // Create mode - add default PHP opening tag
            title.textContent = ((_window$aieData7 = window.aieData) === null || _window$aieData7 === void 0 || (_window$aieData7 = _window$aieData7.i18n) === null || _window$aieData7 === void 0 ? void 0 : _window$aieData7.new_function) || 'New Function';

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
      }, _callee3, null, [[18, 35]]);
    }))();
  },
  /**
   * Open editor modal with snippet data for customization
   */
  openEditorWithSnippet: function openEditorWithSnippet(snippetData) {
    var _this5 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee4() {
      var modal, title, form, codeTextarea, code;
      return _regeneratorRuntime().wrap(function _callee4$(_context4) {
        while (1) switch (_context4.prev = _context4.next) {
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
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.clearModalErrors)(modal);

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

            // Fill form with snippet data
            document.getElementById('aie-function-name').value = snippetData.name || '';
            document.getElementById('aie-function-description').value = snippetData.description || '';
            document.getElementById('aie-function-category').value = snippetData.category || 'custom';
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
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee5() {
      var _window$aieData11;
      var codeTextarea, code, name, category, _window$aieData8, _window$aieData9, _window$aieData10, functionId, formData, _window$aieData12, response, contentType, text, data, _data$data3, modal, errorMessage;
      return _regeneratorRuntime().wrap(function _callee5$(_context5) {
        while (1) switch (_context5.prev = _context5.next) {
          case 0:
            // Clear any previous modal errors
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.clearModalErrors)();

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
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showModalError)(((_window$aieData8 = window.aieData) === null || _window$aieData8 === void 0 || (_window$aieData8 = _window$aieData8.i18n) === null || _window$aieData8 === void 0 ? void 0 : _window$aieData8.name_required) || 'Please enter a function name.');
            document.getElementById('aie-function-name').focus();
            return _context5.abrupt("return");
          case 10:
            if (code.trim()) {
              _context5.next = 14;
              break;
            }
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showModalError)(((_window$aieData9 = window.aieData) === null || _window$aieData9 === void 0 || (_window$aieData9 = _window$aieData9.i18n) === null || _window$aieData9 === void 0 ? void 0 : _window$aieData9.code_required) || 'Please enter the PHP code for your function.');
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
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showModalError)(((_window$aieData10 = window.aieData) === null || _window$aieData10 === void 0 || (_window$aieData10 = _window$aieData10.i18n) === null || _window$aieData10 === void 0 ? void 0 : _window$aieData10.category_required) || 'Please select a category.');
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
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showNotice)(((_window$aieData12 = window.aieData) === null || _window$aieData12 === void 0 || (_window$aieData12 = _window$aieData12.i18n) === null || _window$aieData12 === void 0 ? void 0 : _window$aieData12.function_saved) || 'Function saved successfully');
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
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showModalError)(errorMessage, modal);
            } else {
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showError)(errorMessage);
            }
          case 60:
          case "end":
            return _context5.stop();
        }
      }, _callee5, null, [[29, 51]]);
    }))();
  },
  /**
   * Delete function
   */
  deleteFunction: function deleteFunction(functionId) {
    var _this7 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee6() {
      var _window$aieData13, _window$aieData14, response, data, _data$data4;
      return _regeneratorRuntime().wrap(function _callee6$(_context6) {
        while (1) switch (_context6.prev = _context6.next) {
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
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showNotice)(((_window$aieData14 = window.aieData) === null || _window$aieData14 === void 0 || (_window$aieData14 = _window$aieData14.i18n) === null || _window$aieData14 === void 0 ? void 0 : _window$aieData14.function_deleted) || 'Function deleted successfully');
            _this7.loadFunctions();
            _context6.next = 17;
            break;
          case 13:
            _context6.prev = 13;
            _context6.t0 = _context6["catch"](0);
            console.error('Error deleting function:', _context6.t0);
            (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showError)(_context6.t0.message);
          case 17:
          case "end":
            return _context6.stop();
        }
      }, _callee6, null, [[0, 13]]);
    }))();
  },
  /**
   * Test function with sample value
   */
  testFunction: function testFunction() {
    var _this8 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee7() {
      var code, testValueInput, testValue, resultsDiv, modal, _window$aieData15, formData, response, contentType, text, data, _data$data5, errorMessage;
      return _regeneratorRuntime().wrap(function _callee7$(_context7) {
        while (1) switch (_context7.prev = _context7.next) {
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
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showModalError)('Please enter function code first', modal);
            } else {
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showError)('Please enter function code first');
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
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showModalError)(errorMessage, modal);
            } else {
              (0,_utils_notifications__WEBPACK_IMPORTED_MODULE_0__.showError)(errorMessage);
            }
          case 46:
          case "end":
            return _context7.stop();
        }
      }, _callee7, null, [[14, 40]]);
    }))();
  },
  /**
   * Clear all filters
   */
  clearFilters: function clearFilters() {
    this.filters = {
      status: '',
      category: '',
      search: ''
    };
    document.getElementById('aie-filter-status').value = '';
    document.getElementById('aie-filter-category').value = '';
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

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utils */ "./src/js/modules/utils.js");
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _regeneratorRuntime() { "use strict"; /*! regenerator-runtime -- Copyright (c) 2014-present, Facebook, Inc. -- license (MIT): https://github.com/facebook/regenerator/blob/main/LICENSE */ _regeneratorRuntime = function _regeneratorRuntime() { return e; }; var t, e = {}, r = Object.prototype, n = r.hasOwnProperty, o = Object.defineProperty || function (t, e, r) { t[e] = r.value; }, i = "function" == typeof Symbol ? Symbol : {}, a = i.iterator || "@@iterator", c = i.asyncIterator || "@@asyncIterator", u = i.toStringTag || "@@toStringTag"; function define(t, e, r) { return Object.defineProperty(t, e, { value: r, enumerable: !0, configurable: !0, writable: !0 }), t[e]; } try { define({}, ""); } catch (t) { define = function define(t, e, r) { return t[e] = r; }; } function wrap(t, e, r, n) { var i = e && e.prototype instanceof Generator ? e : Generator, a = Object.create(i.prototype), c = new Context(n || []); return o(a, "_invoke", { value: makeInvokeMethod(t, r, c) }), a; } function tryCatch(t, e, r) { try { return { type: "normal", arg: t.call(e, r) }; } catch (t) { return { type: "throw", arg: t }; } } e.wrap = wrap; var h = "suspendedStart", l = "suspendedYield", f = "executing", s = "completed", y = {}; function Generator() {} function GeneratorFunction() {} function GeneratorFunctionPrototype() {} var p = {}; define(p, a, function () { return this; }); var d = Object.getPrototypeOf, v = d && d(d(values([]))); v && v !== r && n.call(v, a) && (p = v); var g = GeneratorFunctionPrototype.prototype = Generator.prototype = Object.create(p); function defineIteratorMethods(t) { ["next", "throw", "return"].forEach(function (e) { define(t, e, function (t) { return this._invoke(e, t); }); }); } function AsyncIterator(t, e) { function invoke(r, o, i, a) { var c = tryCatch(t[r], t, o); if ("throw" !== c.type) { var u = c.arg, h = u.value; return h && "object" == _typeof(h) && n.call(h, "__await") ? e.resolve(h.__await).then(function (t) { invoke("next", t, i, a); }, function (t) { invoke("throw", t, i, a); }) : e.resolve(h).then(function (t) { u.value = t, i(u); }, function (t) { return invoke("throw", t, i, a); }); } a(c.arg); } var r; o(this, "_invoke", { value: function value(t, n) { function callInvokeWithMethodAndArg() { return new e(function (e, r) { invoke(t, n, e, r); }); } return r = r ? r.then(callInvokeWithMethodAndArg, callInvokeWithMethodAndArg) : callInvokeWithMethodAndArg(); } }); } function makeInvokeMethod(e, r, n) { var o = h; return function (i, a) { if (o === f) throw Error("Generator is already running"); if (o === s) { if ("throw" === i) throw a; return { value: t, done: !0 }; } for (n.method = i, n.arg = a;;) { var c = n.delegate; if (c) { var u = maybeInvokeDelegate(c, n); if (u) { if (u === y) continue; return u; } } if ("next" === n.method) n.sent = n._sent = n.arg;else if ("throw" === n.method) { if (o === h) throw o = s, n.arg; n.dispatchException(n.arg); } else "return" === n.method && n.abrupt("return", n.arg); o = f; var p = tryCatch(e, r, n); if ("normal" === p.type) { if (o = n.done ? s : l, p.arg === y) continue; return { value: p.arg, done: n.done }; } "throw" === p.type && (o = s, n.method = "throw", n.arg = p.arg); } }; } function maybeInvokeDelegate(e, r) { var n = r.method, o = e.iterator[n]; if (o === t) return r.delegate = null, "throw" === n && e.iterator["return"] && (r.method = "return", r.arg = t, maybeInvokeDelegate(e, r), "throw" === r.method) || "return" !== n && (r.method = "throw", r.arg = new TypeError("The iterator does not provide a '" + n + "' method")), y; var i = tryCatch(o, e.iterator, r.arg); if ("throw" === i.type) return r.method = "throw", r.arg = i.arg, r.delegate = null, y; var a = i.arg; return a ? a.done ? (r[e.resultName] = a.value, r.next = e.nextLoc, "return" !== r.method && (r.method = "next", r.arg = t), r.delegate = null, y) : a : (r.method = "throw", r.arg = new TypeError("iterator result is not an object"), r.delegate = null, y); } function pushTryEntry(t) { var e = { tryLoc: t[0] }; 1 in t && (e.catchLoc = t[1]), 2 in t && (e.finallyLoc = t[2], e.afterLoc = t[3]), this.tryEntries.push(e); } function resetTryEntry(t) { var e = t.completion || {}; e.type = "normal", delete e.arg, t.completion = e; } function Context(t) { this.tryEntries = [{ tryLoc: "root" }], t.forEach(pushTryEntry, this), this.reset(!0); } function values(e) { if (e || "" === e) { var r = e[a]; if (r) return r.call(e); if ("function" == typeof e.next) return e; if (!isNaN(e.length)) { var o = -1, i = function next() { for (; ++o < e.length;) if (n.call(e, o)) return next.value = e[o], next.done = !1, next; return next.value = t, next.done = !0, next; }; return i.next = i; } } throw new TypeError(_typeof(e) + " is not iterable"); } return GeneratorFunction.prototype = GeneratorFunctionPrototype, o(g, "constructor", { value: GeneratorFunctionPrototype, configurable: !0 }), o(GeneratorFunctionPrototype, "constructor", { value: GeneratorFunction, configurable: !0 }), GeneratorFunction.displayName = define(GeneratorFunctionPrototype, u, "GeneratorFunction"), e.isGeneratorFunction = function (t) { var e = "function" == typeof t && t.constructor; return !!e && (e === GeneratorFunction || "GeneratorFunction" === (e.displayName || e.name)); }, e.mark = function (t) { return Object.setPrototypeOf ? Object.setPrototypeOf(t, GeneratorFunctionPrototype) : (t.__proto__ = GeneratorFunctionPrototype, define(t, u, "GeneratorFunction")), t.prototype = Object.create(g), t; }, e.awrap = function (t) { return { __await: t }; }, defineIteratorMethods(AsyncIterator.prototype), define(AsyncIterator.prototype, c, function () { return this; }), e.AsyncIterator = AsyncIterator, e.async = function (t, r, n, o, i) { void 0 === i && (i = Promise); var a = new AsyncIterator(wrap(t, r, n, o), i); return e.isGeneratorFunction(r) ? a : a.next().then(function (t) { return t.done ? t.value : a.next(); }); }, defineIteratorMethods(g), define(g, u, "Generator"), define(g, a, function () { return this; }), define(g, "toString", function () { return "[object Generator]"; }), e.keys = function (t) { var e = Object(t), r = []; for (var n in e) r.push(n); return r.reverse(), function next() { for (; r.length;) { var t = r.pop(); if (t in e) return next.value = t, next.done = !1, next; } return next.done = !0, next; }; }, e.values = values, Context.prototype = { constructor: Context, reset: function reset(e) { if (this.prev = 0, this.next = 0, this.sent = this._sent = t, this.done = !1, this.delegate = null, this.method = "next", this.arg = t, this.tryEntries.forEach(resetTryEntry), !e) for (var r in this) "t" === r.charAt(0) && n.call(this, r) && !isNaN(+r.slice(1)) && (this[r] = t); }, stop: function stop() { this.done = !0; var t = this.tryEntries[0].completion; if ("throw" === t.type) throw t.arg; return this.rval; }, dispatchException: function dispatchException(e) { if (this.done) throw e; var r = this; function handle(n, o) { return a.type = "throw", a.arg = e, r.next = n, o && (r.method = "next", r.arg = t), !!o; } for (var o = this.tryEntries.length - 1; o >= 0; --o) { var i = this.tryEntries[o], a = i.completion; if ("root" === i.tryLoc) return handle("end"); if (i.tryLoc <= this.prev) { var c = n.call(i, "catchLoc"), u = n.call(i, "finallyLoc"); if (c && u) { if (this.prev < i.catchLoc) return handle(i.catchLoc, !0); if (this.prev < i.finallyLoc) return handle(i.finallyLoc); } else if (c) { if (this.prev < i.catchLoc) return handle(i.catchLoc, !0); } else { if (!u) throw Error("try statement without catch or finally"); if (this.prev < i.finallyLoc) return handle(i.finallyLoc); } } } }, abrupt: function abrupt(t, e) { for (var r = this.tryEntries.length - 1; r >= 0; --r) { var o = this.tryEntries[r]; if (o.tryLoc <= this.prev && n.call(o, "finallyLoc") && this.prev < o.finallyLoc) { var i = o; break; } } i && ("break" === t || "continue" === t) && i.tryLoc <= e && e <= i.finallyLoc && (i = null); var a = i ? i.completion : {}; return a.type = t, a.arg = e, i ? (this.method = "next", this.next = i.finallyLoc, y) : this.complete(a); }, complete: function complete(t, e) { if ("throw" === t.type) throw t.arg; return "break" === t.type || "continue" === t.type ? this.next = t.arg : "return" === t.type ? (this.rval = this.arg = t.arg, this.method = "return", this.next = "end") : "normal" === t.type && e && (this.next = e), y; }, finish: function finish(t) { for (var e = this.tryEntries.length - 1; e >= 0; --e) { var r = this.tryEntries[e]; if (r.finallyLoc === t) return this.complete(r.completion, r.afterLoc), resetTryEntry(r), y; } }, "catch": function _catch(t) { for (var e = this.tryEntries.length - 1; e >= 0; --e) { var r = this.tryEntries[e]; if (r.tryLoc === t) { var n = r.completion; if ("throw" === n.type) { var o = n.arg; resetTryEntry(r); } return o; } } throw Error("illegal catch attempt"); }, delegateYield: function delegateYield(e, r, n) { return this.delegate = { iterator: values(e), resultName: r, nextLoc: n }, "next" === this.method && (this.arg = t), y; } }, e; }
function asyncGeneratorStep(n, t, e, r, o, a, c) { try { var i = n[a](c), u = i.value; } catch (n) { return void e(n); } i.done ? t(u) : Promise.resolve(u).then(r, o); }
function _asyncToGenerator(n) { return function () { var t = this, e = arguments; return new Promise(function (r, o) { var a = n.apply(t, e); function _next(n) { asyncGeneratorStep(a, r, o, _next, _throw, "next", n); } function _throw(n) { asyncGeneratorStep(a, r, o, _next, _throw, "throw", n); } _next(void 0); }); }; }
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
    } else if (step === 4) {
      this.buildFieldMapping();
    }
  },
  /**
   * Go to next step
   */
  nextStep: function nextStep() {
    if (this.currentStep < this.totalSteps) {
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
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Please upload a file', 'error');
          return false;
        }
        break;
      case 4:
        // Validate field mapping
        var mappedFields = this.getFieldMapping();
        if (Object.keys(mappedFields).length === 0) {
          _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Please map at least one field', 'error');
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
    // Validate file
    var validation = _utils__WEBPACK_IMPORTED_MODULE_0__["default"].validateFile(file, ['.csv', '.json', '.xml'], 50 * 1024 * 1024);
    if (!validation.valid) {
      _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice(validation.errors.join('<br>'), 'error');
      return;
    }
    this.uploadedFile = file;

    // Show file info
    jQuery('.aie-upload-placeholder').hide();
    jQuery('.aie-file-info').show();
    jQuery('.aie-file-name').text(file.name);
    jQuery('.aie-file-size').text(_utils__WEBPACK_IMPORTED_MODULE_0__["default"].formatFileSize(file.size));

    // Detect format
    var format = this.detectFormat(file.name);
    jQuery('.aie-file-format').text(format.toUpperCase());

    // Show format options
    if (format === 'csv') {
      jQuery('.aie-format-options').show();
      jQuery('.aie-csv-options').show();
    }

    // Enable next button
    jQuery('.aie-step-2 .aie-next-step').prop('disabled', false);

    // Upload file
    this.uploadFile(file);
  },
  /**
   * Upload file to server
   */
  uploadFile: function uploadFile(file) {
    var _this2 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee() {
      var _window$aieData;
      var formData, _window$aieData2, response, _response$data;
      return _regeneratorRuntime().wrap(function _callee$(_context) {
        while (1) switch (_context.prev = _context.next) {
          case 0:
            formData = new FormData();
            formData.append('file', file);
            formData.append('action', 'aie_import_upload_file');
            formData.append('nonce', ((_window$aieData = window.aieData) === null || _window$aieData === void 0 ? void 0 : _window$aieData.nonce) || '');
            formData.append('content_type', jQuery('input[name="content_type"]:checked').val());
            _context.prev = 5;
            _context.next = 8;
            return jQuery.ajax({
              url: ((_window$aieData2 = window.aieData) === null || _window$aieData2 === void 0 ? void 0 : _window$aieData2.ajaxUrl) || '/wp-admin/admin-ajax.php',
              type: 'POST',
              data: formData,
              processData: false,
              contentType: false,
              dataType: 'json'
            });
          case 8:
            response = _context.sent;
            if (!response.success) {
              _context.next = 14;
              break;
            }
            _this2.fileData = response.data;
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('File uploaded successfully', 'success');
            _context.next = 15;
            break;
          case 14:
            throw new Error(((_response$data = response.data) === null || _response$data === void 0 ? void 0 : _response$data.message) || 'Upload failed');
          case 15:
            _context.next = 21;
            break;
          case 17:
            _context.prev = 17;
            _context.t0 = _context["catch"](5);
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].handleError(_context.t0, 'File upload');
            _this2.removeFile();
          case 21:
          case "end":
            return _context.stop();
        }
      }, _callee, null, [[5, 17]]);
    }))();
  },
  /**
   * Remove uploaded file
   */
  removeFile: function removeFile() {
    this.uploadedFile = null;
    this.fileData = null;
    jQuery('.aie-file-info').hide();
    jQuery('.aie-upload-placeholder').show();
    jQuery('.aie-format-options').hide();
    jQuery('#aie-file-input').val('');
    jQuery('.aie-step-2 .aie-next-step').prop('disabled', true);
  },
  /**
   * Detect file format from filename
   */
  detectFormat: function detectFormat(filename) {
    var ext = filename.split('.').pop().toLowerCase();
    return ['csv', 'json', 'xml'].includes(ext) ? ext : 'csv';
  },
  /**
   * Load data preview
   */
  loadPreview: function loadPreview() {
    var _this3 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee2() {
      var _this3$fileData$colum;
      var preview, $table, headerHtml, bodyHtml;
      return _regeneratorRuntime().wrap(function _callee2$(_context2) {
        while (1) switch (_context2.prev = _context2.next) {
          case 0:
            if (!(!_this3.fileData || !_this3.fileData.preview)) {
              _context2.next = 2;
              break;
            }
            return _context2.abrupt("return");
          case 2:
            preview = _this3.fileData.preview;
            $table = jQuery('.aie-preview-table'); // Update stats
            jQuery('.aie-total-rows').text(_this3.fileData.total_rows || 0);
            jQuery('.aie-total-columns').text(((_this3$fileData$colum = _this3.fileData.columns) === null || _this3$fileData$colum === void 0 ? void 0 : _this3$fileData$colum.length) || 0);

            // Build table header
            headerHtml = '<tr>';
            if (preview.headers) {
              preview.headers.forEach(function (header) {
                headerHtml += "<th>".concat(_utils__WEBPACK_IMPORTED_MODULE_0__["default"].escapeHtml(header), "</th>");
              });
            }
            headerHtml += '</tr>';
            $table.find('thead').html(headerHtml);

            // Build table body
            bodyHtml = '';
            if (preview.data) {
              preview.data.forEach(function (row, index) {
                bodyHtml += '<tr>';
                row.forEach(function (cell) {
                  var cellContent = _utils__WEBPACK_IMPORTED_MODULE_0__["default"].escapeHtml(String(cell).substring(0, 100));
                  bodyHtml += "<td>".concat(cellContent, "</td>");
                });
                bodyHtml += '</tr>';
              });
            }
            $table.find('tbody').html(bodyHtml);
          case 13:
          case "end":
            return _context2.stop();
        }
      }, _callee2);
    }))();
  },
  /**
   * Build field mapping interface
   */
  buildFieldMapping: function buildFieldMapping() {
    var _this4 = this;
    if (!this.fileData || !this.fileData.columns) {
      return;
    }
    var contentType = jQuery('input[name="content_type"]:checked').val();
    var targetFields = this.getTargetFields(contentType);
    var $tbody = jQuery('.aie-mapping-body');
    var html = '';
    this.fileData.columns.forEach(function (column, index) {
      var _this4$fileData$previ, _window$aieData3;
      var sampleData = ((_this4$fileData$previ = _this4.fileData.preview) === null || _this4$fileData$previ === void 0 || (_this4$fileData$previ = _this4$fileData$previ.data) === null || _this4$fileData$previ === void 0 || (_this4$fileData$previ = _this4$fileData$previ[0]) === null || _this4$fileData$previ === void 0 ? void 0 : _this4$fileData$previ[index]) || '';
      html += "\n\t\t\t\t<tr>\n\t\t\t\t\t<td><strong>".concat(_utils__WEBPACK_IMPORTED_MODULE_0__["default"].escapeHtml(column), "</strong></td>\n\t\t\t\t\t<td>\n\t\t\t\t\t\t<select name=\"field_map[").concat(index, "]\" class=\"regular-text\">\n\t\t\t\t\t\t\t<option value=\"\">-- ").concat(((_window$aieData3 = window.aieData) === null || _window$aieData3 === void 0 || (_window$aieData3 = _window$aieData3.i18n) === null || _window$aieData3 === void 0 ? void 0 : _window$aieData3.skip) || 'Skip', " --</option>\n\t\t\t\t\t\t\t").concat(targetFields.map(function (field) {
        return "<option value=\"".concat(field.value, "\">").concat(field.label, "</option>");
      }).join(''), "\n\t\t\t\t\t\t</select>\n\t\t\t\t\t</td>\n\t\t\t\t\t<td><code>").concat(_utils__WEBPACK_IMPORTED_MODULE_0__["default"].escapeHtml(String(sampleData).substring(0, 50)), "</code></td>\n\t\t\t\t</tr>\n\t\t\t");
    });
    $tbody.html(html);
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
    var $selects = jQuery('.aie-mapping-body select');
    $selects.each(function () {
      var $select = jQuery(this);
      var columnName = $select.closest('tr').find('strong').text().toLowerCase();

      // Try to find matching field
      $select.find('option').each(function () {
        var optionValue = jQuery(this).val().toLowerCase();
        var optionLabel = jQuery(this).text().toLowerCase();
        if (columnName === optionValue || columnName === optionLabel || columnName.includes(optionValue) || optionValue.includes(columnName)) {
          $select.val(jQuery(this).val());
          return false;
        }
      });
    });
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Auto-mapping completed', 'success');
  },
  /**
   * Clear field mapping
   */
  clearFieldMapping: function clearFieldMapping() {
    jQuery('.aie-mapping-body select').val('');
  },
  /**
   * Get field mapping
   */
  getFieldMapping: function getFieldMapping() {
    var mapping = {};
    jQuery('.aie-mapping-body select').each(function () {
      var _$select$attr$match;
      var $select = jQuery(this);
      var sourceIndex = (_$select$attr$match = $select.attr('name').match(/\[(\d+)\]/)) === null || _$select$attr$match === void 0 ? void 0 : _$select$attr$match[1];
      var targetField = $select.val();
      if (targetField && sourceIndex !== undefined) {
        mapping[sourceIndex] = targetField;
      }
    });
    return mapping;
  },
  /**
   * Start import
   */
  startImport: function startImport() {
    var _this5 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee3() {
      var data, response;
      return _regeneratorRuntime().wrap(function _callee3$(_context3) {
        while (1) switch (_context3.prev = _context3.next) {
          case 0:
            _context3.prev = 0;
            data = {
              file_path: _this5.fileData.file_path,
              content_type: jQuery('input[name="content_type"]:checked').val(),
              format: _this5.fileData.format,
              field_mapping: _this5.getFieldMapping(),
              duplicate_handling: jQuery('input[name="duplicate_handling"]:checked').val(),
              post_status: jQuery('[name="post_status"]').val(),
              post_type: jQuery('[name="post_type"]').val(),
              download_images: jQuery('[name="download_images"]').is(':checked'),
              batch_size: parseInt(jQuery('[name="batch_size"]').val()) || 50
            };
            _context3.next = 4;
            return _utils__WEBPACK_IMPORTED_MODULE_0__["default"].ajax('aie_import_start', data);
          case 4:
            response = _context3.sent;
            _this5.jobId = response.job_id;
            _this5.showStep(6);
            _this5.startProgressTracking();
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Import started successfully', 'success');
            _context3.next = 14;
            break;
          case 11:
            _context3.prev = 11;
            _context3.t0 = _context3["catch"](0);
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].handleError(_context3.t0, 'Start import');
          case 14:
          case "end":
            return _context3.stop();
        }
      }, _callee3, null, [[0, 11]]);
    }))();
  },
  /**
   * Start progress tracking
   */
  startProgressTracking: function startProgressTracking() {
    var _this6 = this;
    this.progressInterval = setInterval(function () {
      _this6.updateProgress();
    }, 2000);
  },
  /**
   * Update import progress
   */
  updateProgress: function updateProgress() {
    var _this7 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee4() {
      var response;
      return _regeneratorRuntime().wrap(function _callee4$(_context4) {
        while (1) switch (_context4.prev = _context4.next) {
          case 0:
            _context4.prev = 0;
            _context4.next = 3;
            return _utils__WEBPACK_IMPORTED_MODULE_0__["default"].ajax('aie_import_get_progress', {
              job_id: _this7.jobId
            });
          case 3:
            response = _context4.sent;
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].updateProgressBar(jQuery('.aie-step-6'), response);
            if (response.status === 'completed') {
              _this7.onImportComplete(response);
            } else if (response.status === 'failed') {
              _this7.onImportFailed(response);
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
      }, _callee4, null, [[0, 8]]);
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
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Import completed successfully!', 'success');
  },
  /**
   * Handle import failure
   */
  onImportFailed: function onImportFailed(result) {
    clearInterval(this.progressInterval);
    _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Import failed: ' + (result.error || 'Unknown error'), 'error');
  },
  /**
   * Cancel import
   */
  cancelImport: function cancelImport() {
    var _this8 = this;
    return _asyncToGenerator( /*#__PURE__*/_regeneratorRuntime().mark(function _callee5() {
      return _regeneratorRuntime().wrap(function _callee5$(_context5) {
        while (1) switch (_context5.prev = _context5.next) {
          case 0:
            if (confirm('Are you sure you want to cancel this import?')) {
              _context5.next = 2;
              break;
            }
            return _context5.abrupt("return");
          case 2:
            _context5.prev = 2;
            _context5.next = 5;
            return _utils__WEBPACK_IMPORTED_MODULE_0__["default"].ajax('aie_import_cancel', {
              job_id: _this8.jobId
            });
          case 5:
            clearInterval(_this8.progressInterval);
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].showNotice('Import cancelled', 'info');
            _this8.resetWizard();
            _context5.next = 13;
            break;
          case 10:
            _context5.prev = 10;
            _context5.t0 = _context5["catch"](2);
            _utils__WEBPACK_IMPORTED_MODULE_0__["default"].handleError(_context5.t0, 'Cancel import');
          case 13:
          case "end":
            return _context5.stop();
        }
      }, _callee5, null, [[2, 10]]);
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
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (ImportModule);

/***/ }),

/***/ "./src/js/modules/utils.js":
/*!*********************************!*\
  !*** ./src/js/modules/utils.js ***!
  \*********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _createForOfIteratorHelper(r, e) { var t = "undefined" != typeof Symbol && r[Symbol.iterator] || r["@@iterator"]; if (!t) { if (Array.isArray(r) || (t = _unsupportedIterableToArray(r)) || e && r && "number" == typeof r.length) { t && (r = t); var _n = 0, F = function F() {}; return { s: F, n: function n() { return _n >= r.length ? { done: !0 } : { done: !1, value: r[_n++] }; }, e: function e(r) { throw r; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var o, a = !0, u = !1; return { s: function s() { t = t.call(r); }, n: function n() { var r = t.next(); return a = r.done, r; }, e: function e(r) { u = !0, o = r; }, f: function f() { try { a || null == t["return"] || t["return"](); } finally { if (u) throw o; } } }; }
function _unsupportedIterableToArray(r, a) { if (r) { if ("string" == typeof r) return _arrayLikeToArray(r, a); var t = {}.toString.call(r).slice(8, -1); return "Object" === t && r.constructor && (t = r.constructor.name), "Map" === t || "Set" === t ? Array.from(r) : "Arguments" === t || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(t) ? _arrayLikeToArray(r, a) : void 0; } }
function _arrayLikeToArray(r, a) { (null == a || a > r.length) && (a = r.length); for (var e = 0, n = Array(a); e < a; e++) n[e] = r[e]; return n; }
function ownKeys(e, r) { var t = Object.keys(e); if (Object.getOwnPropertySymbols) { var o = Object.getOwnPropertySymbols(e); r && (o = o.filter(function (r) { return Object.getOwnPropertyDescriptor(e, r).enumerable; })), t.push.apply(t, o); } return t; }
function _objectSpread(e) { for (var r = 1; r < arguments.length; r++) { var t = null != arguments[r] ? arguments[r] : {}; r % 2 ? ownKeys(Object(t), !0).forEach(function (r) { _defineProperty(e, r, t[r]); }) : Object.getOwnPropertyDescriptors ? Object.defineProperties(e, Object.getOwnPropertyDescriptors(t)) : ownKeys(Object(t)).forEach(function (r) { Object.defineProperty(e, r, Object.getOwnPropertyDescriptor(t, r)); }); } return e; }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
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
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (Utils);

/***/ }),

/***/ "./src/js/utils/notifications.js":
/*!***************************************!*\
  !*** ./src/js/utils/notifications.js ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

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

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


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