/**
 * Command Center live refresh.
 *
 * Polls the dashboard AJAX endpoint and swaps the panels markup in place,
 * pausing while the tab is hidden and refreshing immediately when the
 * operator returns to it. If the nonce expires (page left open past the
 * nonce lifetime) or the server keeps failing, polling halts and the
 * Refresh button falls back to a full page reload.
 */
(function ($) {
	'use strict';

	var cfg = window.rpCommandCenter || {};
	var $panels = $('#rp-command-center-panels');

	if (!$panels.length || !cfg.ajax_url || !cfg.nonce) {
		return;
	}

	var interval = parseInt(cfg.interval, 10);
	if (isNaN(interval) || interval < 5000) {
		interval = 30000;
	}

	var $container = $('.rp-command-center');
	var i18n = cfg.i18n || {};
	var timer = null;
	var inflight = false;
	var halted = false;
	var failures = 0;
	var MAX_FAILURES = 5;

	function halt(message) {
		halted = true;
		stop();
		if (message) {
			$('.rp-command-autorefresh-note').text(message).addClass('is-stale');
		}
	}

	function refresh() {
		if (inflight || halted) {
			return;
		}

		inflight = true;
		$container.addClass('is-refreshing');

		$.post(cfg.ajax_url, {
			action: 'rpress_command_center_refresh',
			nonce: cfg.nonce
		})
			.done(function (res) {
				failures = 0;
				if (res && res.success && res.data && res.data.html) {
					$panels.html(res.data.html);
					$('#rp-command-updated-time').text(res.data.updated);
				}
			})
			.fail(function (xhr) {
				if (xhr && (xhr.status === 403 || xhr.status === 401)) {
					halt(i18n.expired || 'Session expired. Use Refresh to reload the page.');
				} else if (++failures >= MAX_FAILURES) {
					halt(i18n.unreachable || 'Live updates paused. Use Refresh to reload the page.');
				}
			})
			.always(function () {
				inflight = false;
				$container.removeClass('is-refreshing');
			});
	}

	function start() {
		stop();
		timer = setInterval(function () {
			if (!document.hidden) {
				refresh();
			}
		}, interval);
	}

	function stop() {
		if (timer) {
			clearInterval(timer);
			timer = null;
		}
	}

	document.addEventListener('visibilitychange', function () {
		if (!document.hidden) {
			refresh();
		}
	});

	$(document).on('click', '#rp-command-refresh', function (e) {
		// Once halted, let the link navigate - a full reload re-authenticates
		// and restarts polling with a fresh nonce.
		if (halted) {
			return;
		}
		e.preventDefault();
		refresh();
	});

	start();
})(jQuery);
