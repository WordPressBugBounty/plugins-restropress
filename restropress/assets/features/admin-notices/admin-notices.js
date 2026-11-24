/**
 * Admin notification center (3.5.1): the bell panel, dismissing from the
 * panel, the problem banner or the Dashboard card, and queued toasts.
 */
(function () {
	'use strict';

	var vars = window.rpress_admin_notices || {};

	function notify(message, type, onClose) {
		if (typeof window.rpress_notify === 'function') {
			window.rpress_notify(message, { type: type || 'success', onClose: onClose });
		}
	}

	// Confirmations queued by PHP for this screen, one after another.
	function showToasts() {
		var data = document.getElementById('rpress-admin-toasts');
		if (!data) {
			return;
		}
		var list = [];
		try {
			list = JSON.parse(data.textContent || '[]');
		} catch (e) {
			list = [];
		}
		(function next() {
			var t = list.shift();
			if (t) {
				notify(t.message, t.type, next);
			}
		})();
	}

	var node, link, panel, lastFocus;

	function isOpen() {
		return panel && !panel.hidden;
	}

	function place() {
		var bar = document.getElementById('wpadminbar');
		var r = bar ? bar.getBoundingClientRect() : null;
		panel.style.top = (r ? Math.max(0, r.bottom) : 32) + 'px';
	}

	function open() {
		lastFocus = document.activeElement;
		place();
		panel.hidden = false;
		link.setAttribute('aria-expanded', 'true');
		node.classList.add('is-open');
		var heading = document.getElementById('rpress-notice-panel-title');
		if (heading) {
			heading.focus();
		}
	}

	function close(restoreFocus) {
		if (!isOpen()) {
			return;
		}
		panel.hidden = true;
		link.setAttribute('aria-expanded', 'false');
		node.classList.remove('is-open');
		if (restoreFocus !== false) {
			(lastFocus && document.contains(lastFocus) ? lastFocus : link).focus();
		}
	}

	function updateCounts(count, problems) {
		if (node) {
			var badge = node.querySelector('.rpress-bell-count');
			if (badge) {
				badge.textContent = String(count);
				badge.classList.toggle('is-empty', !count);
				badge.classList.toggle('has-problems', problems > 0);
			}
			var label = count === 1 ? vars.label1 : (count ? String(vars.label || '').replace('%d', count) : vars.label0);
			var sr = node.querySelector('.rpress-bell-label');
			if (sr && label) {
				sr.textContent = label;
			}
			if (label) {
				link.setAttribute('title', label);
			}
		}
		var menu = document.querySelector('#adminmenu .rpress-problem-count');
		if (menu) {
			if (problems > 0) {
				var n = menu.querySelector('.pending-count');
				if (n) {
					n.textContent = String(problems);
				}
			} else {
				menu.parentNode.removeChild(menu);
			}
		}
		if (panel) {
			var empty = panel.querySelector('.rpress-notice-empty');
			if (empty) {
				empty.hidden = count > 0;
			}
		}
	}

	function dismiss(button) {
		var id = button.getAttribute('data-notice');
		if (!id || button.disabled) {
			return;
		}
		button.disabled = true;
		var body = new URLSearchParams();
		body.append('action', 'rpress_dismiss_admin_notice');
		body.append('nonce', vars.nonce || '');
		body.append('id', id);
		fetch(vars.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success) {
					throw new Error('dismiss');
				}
				var inPanel = panel && panel.contains(button);
				document.querySelectorAll('.rpress-notice-item[data-notice="' + id + '"]').forEach(function (item) {
					var wrap = item.closest('.rpress-problem-banner, .rpress-notice-card');
					(wrap || item).parentNode.removeChild(wrap || item);
				});
				updateCounts(res.data.count, res.data.problems);
				// Keep keyboard users in the panel after the item goes away.
				if (inPanel) {
					var nextBtn = panel.querySelector('.rpress-notice-item a, .rpress-notice-item button');
					(nextBtn || document.getElementById('rpress-notice-panel-title')).focus();
				}
			})
			.catch(function () {
				button.disabled = false;
				notify(vars.failed, 'error');
			});
	}

	function init() {
		showToasts();
		node = document.getElementById('wp-admin-bar-rpress-notifications');
		panel = document.getElementById('rpress-notice-panel');
		link = node ? node.querySelector('a.ab-item') : null;
		if (panel && panel.parentNode !== document.body) {
			document.body.appendChild(panel);
		}

		document.addEventListener('click', function (e) {
			var dismissBtn = e.target.closest ? e.target.closest('.rpress-notice-dismiss') : null;
			if (dismissBtn) {
				e.preventDefault();
				dismiss(dismissBtn);
				return;
			}
			if (link && panel && e.target.closest && e.target.closest('#wp-admin-bar-rpress-notifications')) {
				e.preventDefault();
				if (isOpen()) {
					close();
				} else {
					open();
				}
				return;
			}
			if (isOpen() && !panel.contains(e.target)) {
				close(false);
			}
		});

		if (!link || !panel) {
			return;
		}
		link.setAttribute('role', 'button');
		link.setAttribute('aria-expanded', 'false');
		link.setAttribute('aria-controls', 'rpress-notice-panel');
		// Space opens it too, like a button.
		link.addEventListener('keydown', function (e) {
			if (e.key === ' ') {
				e.preventDefault();
				link.click();
			}
		});
		panel.querySelector('.rpress-notice-panel-close').addEventListener('click', function () {
			close();
		});
		document.addEventListener('keydown', function (e) {
			if ((e.key === 'Escape' || e.key === 'Esc') && isOpen()) {
				e.preventDefault();
				e.stopPropagation();
				close();
			}
		}, true);
		window.addEventListener('resize', function () {
			if (isOpen()) {
				place();
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
