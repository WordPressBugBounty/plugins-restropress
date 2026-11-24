/**
 * Storefront messages (3.5.1): one small bar for results and errors that have
 * no field to sit next to.
 *
 *   rpress_notify( message, { type, action, duration } )
 *     type     'success' | 'info' | 'error' (default 'info')
 *     action   { label: 'Undo', onClick: function () {} } (optional)
 *     duration ms before it closes (default 3000, errors 5000, with an action 6000)
 *
 * Phones: bottom centre, above any bar fixed to the bottom (View cart, the
 * checkout Place order bar). Desktop: bottom left. One bar at a time; it waits
 * while hovered or focused. Announced through a polite live region (errors:
 * assertive); focus never moves.
 *
 * Also replaces the old `tata` toast library on the storefront, so code that
 * still calls tata.error( title, message ) gets the same bar.
 *
 * @since 3.5.1
 */
(function (window, document) {
	'use strict';

	var vars = window.rpress_notify_vars || {};
	var host, bar, liveStatus, liveAlert, timer, remaining, startedAt, current;

	function t(key, fallback) {
		return vars[key] || fallback;
	}

	function el(tag, cls, text) {
		var node = document.createElement(tag);
		if (cls) {
			node.className = cls;
		}
		if (text) {
			node.textContent = text;
		}
		return node;
	}

	function setup() {
		if (host) {
			return true;
		}
		if (!document.body) {
			return false;
		}
		host = el('div', 'rp-notify-host');
		// Live regions exist before any message so screen readers pick up changes.
		liveStatus = el('div', 'rp-notify-sr');
		liveStatus.setAttribute('role', 'status');
		liveStatus.setAttribute('aria-live', 'polite');
		liveAlert = el('div', 'rp-notify-sr');
		liveAlert.setAttribute('role', 'alert');
		liveAlert.setAttribute('aria-live', 'assertive');
		var accent = storeAccent();
		if (accent) {
			host.style.setProperty('--rp-notify-accent', accent);
		}
		document.body.appendChild(host);
		document.body.appendChild(liveStatus);
		document.body.appendChild(liveAlert);
		return true;
	}

	// The store's primary colour for the tick and the action button, unless it
	// is too dark to read on the dark bar (then the CSS default green is used).
	function storeAccent() {
		var raw = (window.getComputedStyle(document.documentElement).getPropertyValue('--rpress-theme-primary') || vars.primary || '').trim();
		var m = raw.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
		if (!m) {
			return '';
		}
		var hex = m[1].length === 3 ? m[1].replace(/(.)/g, '$1$1') : m[1];
		var lum = [0, 2, 4].map(function (i) {
			var c = parseInt(hex.substr(i, 2), 16) / 255;
			return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
		});
		var l = 0.2126 * lum[0] + 0.7152 * lum[1] + 0.0722 * lum[2];
		// Contrast against the bar (#1f2328, luminance about 0.016) of at least 4.5:1.
		return (l + 0.05) / (0.016 + 0.05) >= 4.5 ? '#' + hex : '';
	}

	// Height of whatever is fixed to the bottom of the screen (View cart bar,
	// the minimum-order hint stacked on it, checkout Place order bar, an
	// extension's bar): look just above each fixed layer found, from the
	// bottom edge up.
	function fixedLayerTop(y) {
		var top = null;
		var w = window.innerWidth;
		// Probe under where the bar sits: the full width on phones, the bottom-left strip on desktop.
		var xs = w < 768 ? [w / 2, 24, w - 24] : [40, 240, Math.min(440, w / 2)];
		if (w >= 768 && window.getComputedStyle(document.body).direction === 'rtl') {
			xs = xs.map(function (x) { return w - x; });
		}
		xs.forEach(function (x) {
			document.elementsFromPoint(x, y).forEach(function (node) {
				if (host && host.contains(node)) {
					return;
				}
				// The nearest fixed box this element belongs to.
				var anchor = null;
				for (var n = node; n && n !== document.body && n !== document.documentElement; n = n.parentElement) {
					if (window.getComputedStyle(n).position === 'fixed') {
						anchor = n;
						break;
					}
				}
				if (!anchor) {
					return;
				}
				var ar = anchor.getBoundingClientRect();
				if (ar.height >= window.innerHeight * 0.4 || ar.bottom < y - 2) {
					return;
				}
				// The element itself counts too: a hint can stick out above its bar.
				var t = Math.min(ar.top, node.getBoundingClientRect().top);
				top = top === null ? t : Math.min(top, t);
			});
		});
		return top;
	}

	function bottomOffset() {
		if (!document.elementsFromPoint) {
			return 0;
		}
		var h = window.innerHeight;
		// A floating bar (Modern pack) leaves a gap above the screen edge:
		// look a little higher before deciding nothing is there.
		var lift = 4;
		while (lift <= 40 && fixedLayerTop(h - lift) === null) {
			lift += 12;
		}
		var y = lift <= 40 ? h - lift : h - 4;
		for (var i = 0; i < 4; i++) {
			var top = fixedLayerTop(y);
			if (top === null || top >= y) {
				break;
			}
			// Bars stacked on each other often leave a small gap; step over it.
			y = top - 12;
		}
		return Math.max(0, Math.min(Math.round(h * 0.5), Math.round(h - 12 - y)));
	}

	function clearTimer() {
		if (timer) {
			window.clearTimeout(timer);
			timer = null;
		}
	}

	function startTimer(ms) {
		clearTimer();
		remaining = ms;
		startedAt = Date.now();
		timer = window.setTimeout(dismiss, ms);
	}

	function pause() {
		if (timer) {
			clearTimer();
			remaining = Math.max(1200, remaining - (Date.now() - startedAt));
		}
	}

	function resume() {
		if (bar && !timer && !(bar.matches(':hover') || bar.contains(document.activeElement))) {
			startTimer(remaining);
		}
	}

	function dismiss() {
		clearTimer();
		if (!bar) {
			return;
		}
		var old = bar;
		var done = current && current.onClose;
		bar = null;
		current = null;
		old.classList.remove('is-shown');
		old.classList.add('is-leaving');
		window.setTimeout(function () {
			if (old.parentNode) {
				old.parentNode.removeChild(old);
			}
		}, 220);
		if (typeof done === 'function') {
			try { done(); } catch (e) { /* caller's problem */ }
		}
	}

	function announce(message, isError) {
		var region = isError ? liveAlert : liveStatus;
		region.textContent = '';
		// A tick later, so repeating the same message is announced again.
		window.setTimeout(function () { region.textContent = message; }, 60);
	}

	function notify(message, options) {
		options = options || {};
		message = (message === null || message === undefined) ? '' : String(message).trim();
		var type = ['success', 'error', 'info'].indexOf(options.type) !== -1 ? options.type : 'info';
		if (!message) {
			if (type !== 'error') {
				return null;
			}
			message = t('generic_error', 'Something went wrong. Please try again.');
		}
		if (!setup()) {
			document.addEventListener('DOMContentLoaded', function () { notify(message, options); });
			return null;
		}
		if (bar) {
			clearTimer();
			if (bar.parentNode) {
				bar.parentNode.removeChild(bar);
			}
			bar = null;
		}

		var action = options.action && options.action.label && typeof options.action.onClick === 'function' ? options.action : null;
		var node = el('div', 'rp-notify rp-notify-' + type);
		node.appendChild(el('span', 'rp-notify-icon'));
		node.lastChild.setAttribute('aria-hidden', 'true');
		node.appendChild(el('span', 'rp-notify-text', message));
		if (action) {
			var btn = el('button', 'rp-notify-action', action.label);
			btn.type = 'button';
			btn.addEventListener('click', function () {
				dismiss();
				action.onClick();
			});
			node.appendChild(btn);
		}
		var close = el('button', 'rp-notify-close');
		close.type = 'button';
		close.setAttribute('aria-label', t('dismiss', 'Dismiss'));
		close.innerHTML = '<span aria-hidden="true">&times;</span>';
		close.addEventListener('click', dismiss);
		node.appendChild(close);

		node.addEventListener('mouseenter', pause);
		node.addEventListener('mouseleave', resume);
		node.addEventListener('focusin', pause);
		node.addEventListener('focusout', function () { window.setTimeout(resume, 0); });

		host.style.setProperty('--rp-notify-offset', bottomOffset() + 'px');
		host.appendChild(node);
		// Bars can change right after a message (a cart update adds the
		// minimum-order hint): measure again shortly.
		[250, 900, 2000].forEach(function (ms) {
			window.setTimeout(function () {
				if (bar === node) {
					host.style.setProperty('--rp-notify-offset', bottomOffset() + 'px');
				}
			}, ms);
		});
		bar = node;
		current = { onClose: options.onClose };
		// Next frame so the entrance transition runs.
		window.requestAnimationFrame(function () { node.classList.add('is-shown'); });
		announce(action ? message + ' ' + action.label : message, type === 'error');

		var duration = parseInt(options.duration, 10);
		if (!duration) {
			duration = action ? 6000 : (type === 'error' ? 5000 : 3000);
		}
		startTimer(duration);
		return node;
	}

	notify.dismiss = dismiss;
	window.rpress_notify = notify;

	// Escape closes the bar.
	document.addEventListener('keydown', function (e) {
		if (bar && (e.key === 'Escape' || e.key === 'Esc')) {
			dismiss();
		}
	});

	/*
	 * `tata` shim for the storefront: tata.error( title, message, options ).
	 * Titles such as "Error" or "Success" are dropped; a title is shown only
	 * when there is no message.
	 */
	var genericTitles = ['error', 'success', 'info', 'information', 'warning', 'notice', ''];
	function fromTata(type) {
		return function (title, message, opts) {
			var text = (message === undefined || message === null) ? '' : String(message);
			if (!text.trim() && genericTitles.indexOf(String(title || '').trim().toLowerCase()) === -1 && [vars.title_error, vars.title_success].indexOf(title) === -1) {
				text = String(title);
			}
			opts = opts || {};
			return notify(text, { type: type, duration: opts.duration, onClose: opts.onClose });
		};
	}
	window.tata = {
		error: fromTata('error'),
		warn: fromTata('error'),
		success: fromTata('success'),
		info: fromTata('info'),
		log: fromTata('info'),
		text: fromTata('info'),
		ask: fromTata('info'),
		clear: dismiss
	};
})(window, document);
