/* global rslIeNotices, jQuery */
(function ($) {
	'use strict';
	$(function () {
		const config = rslIeNotices;
		const target = $('#toplevel_page_import-export-by-rockstarlab > a');
		if (!target.length) return;

		function showRestWarning(failed) {
			$('#rsl-ie-rest-warning').toggle(failed);
			const count = Number(config.warningCount) + (failed ? 1 : 0);
			const badge = target.find('.rsl-ie-warning-count');
			badge.toggle(count > 0);
			badge.find('.pending-count').text(count);
			badge.find('.screen-reader-text').text(config.countLabel + ' ' + count);
		}

		// Use the actual HTTP endpoint: internal REST dispatch misses firewall rules.
		// A passing check is remembered for 10 minutes in this tab, so not every admin page
		// load makes an extra request. global:false keeps this request out of jQuery's global
		// ajax events: other plugins' ajaxComplete handlers (ACF on term screens) expect
		// settings.data and threw "Cannot read properties of undefined (reading 'indexOf')".
		const cacheKey = 'rslIeRestOk:' + config.restUrl;
		let cachedOk = false;
		try {
			cachedOk = Date.now() - Number(window.sessionStorage.getItem(cacheKey) || 0) < 600000;
		} catch (e) {
			cachedOk = false;
		}
		if (cachedOk) {
			showRestWarning(false);
		} else {
			$.ajax({
				url: config.restUrl,
				dataType: 'json',
				timeout: 10000,
				global: false,
				headers: { 'X-WP-Nonce': config.restNonce },
			}).done(function (data) {
				const failed = !data || data.namespace !== 'rsl-ie/v1' ||
					!data.routes || !data.routes['/rsl-ie/v1/info'];
				showRestWarning(failed);
				if (!failed) {
					try {
						window.sessionStorage.setItem(cacheKey, String(Date.now()));
					} catch (e) {
						// Storage unavailable: check again on the next page.
					}
				}
			}).fail(function () {
				showRestWarning(true);
			});
		}

		if (!config.pointer || !$.fn.pointer) return;
		target.pointer({
			content: config.content,
			position: { edge: 'left', align: 'center' },
			buttons: function () {
				const button = $('<button type="button" class="button button-secondary"></button>').text(config.dismiss);
				button.on('click', function () {
					button.prop('disabled', true);
					$.post(config.ajaxUrl, {
						action: 'rsl_ie_dismiss_agent_pointer',
						nonce: config.nonce,
					}).done(function (response) {
						if (response && response.success) {
							target.pointer('close');
						} else {
							button.text(config.retry);
						}
					}).fail(function () {
						button.text(config.retry);
					}).always(function () {
						button.prop('disabled', false);
					});
				});
				return $('<div></div>').append(button);
			},
		}).pointer('open');
	});
})(jQuery);
