/**
 * "Switch to RestroPress" importer screen.
 *
 * Steps: source -> upload -> preview (dry run) -> run (batched) -> summary.
 * All text from the server is inserted with textContent, never as HTML.
 */
(function () {
	'use strict';

	var data = window.rpressImporter;
	var root = document.querySelector('.rpi-wrap');
	if (!data || !root) {
		return;
	}
	var t = data.i18n;
	var state = { source: '', accepts: [], jobId: 0, preview: null };

	function $(selector) {
		return root.querySelector(selector);
	}
	function $all(selector) {
		return Array.prototype.slice.call(root.querySelectorAll(selector));
	}
	function el(tag, text, className) {
		var node = document.createElement(tag);
		if (text !== undefined && text !== null) {
			node.textContent = String(text);
		}
		if (className) {
			node.className = className;
		}
		return node;
	}
	function sprintf(str, value) {
		return String(str).replace('%d', value).replace('%s', value);
	}

	function showError(message) {
		var box = $('#rpi-error');
		box.textContent = message || t.failed;
		box.hidden = false;
		box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}
	function clearError() {
		$('#rpi-error').hidden = true;
	}

	function go(step) {
		clearError();
		$all('.rpi-panel').forEach(function (panel) {
			panel.hidden = panel.getAttribute('data-panel') !== step;
		});
		var reached = true;
		$all('.rpi-steps li').forEach(function (li) {
			var current = li.getAttribute('data-step') === step;
			li.classList.toggle('is-active', current);
			li.classList.toggle('is-done', reached && !current);
			if (current) {
				reached = false;
			}
		});
	}

	function post(action, fields) {
		var body = fields instanceof FormData ? fields : new FormData();
		if (!(fields instanceof FormData) && fields) {
			Object.keys(fields).forEach(function (key) {
				body.append(key, fields[key]);
			});
		}
		body.append('action', 'rpress_importer_' + action);
		body.append('nonce', data.nonce);
		return fetch(data.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (response) {
				return response.json().catch(function () {
					throw new Error(t.failed);
				});
			})
			.then(function (json) {
				if (!json || !json.success) {
					throw new Error(json && json.data && json.data.message ? json.data.message : t.failed);
				}
				return json.data;
			});
	}

	function wait(ms) {
		return new Promise(function (resolve) {
			setTimeout(resolve, ms);
		});
	}

	/* Step 1: source ------------------------------------------------------ */

	function chooseSource(button) {
		state.source = button.getAttribute('data-source');
		state.accepts = (button.getAttribute('data-accepts') || '').split(',').filter(Boolean);
		$('#rpi-upload-title').textContent = button.querySelector('.rpi-source-title').textContent;
		$all('.rpi-help').forEach(function (help) {
			help.hidden = help.getAttribute('data-help') !== state.source;
		});
		var fileWrap = $('#rpi-file-wrap');
		var input = $('#rpi-file');
		input.value = '';
		fileWrap.hidden = !state.accepts.length;
		input.setAttribute('accept', state.accepts.map(function (ext) { return '.' + ext; }).join(','));
		go('upload');
	}

	$all('.rpi-source[data-source]').forEach(function (button) {
		button.addEventListener('click', function () {
			if (!button.disabled) {
				chooseSource(button);
			}
		});
	});
	$all('[data-back]').forEach(function (button) {
		button.addEventListener('click', function () {
			go(button.getAttribute('data-back'));
		});
	});

	/* Step 2: upload ------------------------------------------------------ */

	$('#rpi-upload-form').addEventListener('submit', function (event) {
		event.preventDefault();
		clearError();
		var form = new FormData();
		form.append('source', state.source);
		if (state.accepts.length) {
			var file = $('#rpi-file').files[0];
			if (!file) {
				showError(t.chooseFile);
				return;
			}
			if (data.maxUpload && file.size > data.maxUpload) {
				showError(t.tooBig);
				return;
			}
			form.append('file', file);
		}
		var button = $('#rpi-read');
		var label = button.textContent;
		button.disabled = true;
		button.textContent = t.reading;
		post('parse', form)
			.then(function (preview) {
				state.jobId = preview.job_id;
				state.preview = preview;
				renderPreview(preview);
				go('preview');
			})
			.catch(function (error) {
				showError(error.message);
			})
			.then(function () {
				button.disabled = false;
				button.textContent = label;
			});
	});

	/* Step 3: preview ----------------------------------------------------- */

	function renderCounts(container, counts, labels) {
		container.textContent = '';
		Object.keys(labels).forEach(function (key) {
			if (counts[key] === undefined || (!counts[key] && ['categories', 'items', 'created'].indexOf(key) === -1)) {
				return;
			}
			var box = el('div', null, 'rpi-count' + (key === 'warnings' || key === 'failed' || key === 'image_failures' ? ' is-warning' : ''));
			box.appendChild(el('strong', counts[key]));
			box.appendChild(el('span', labels[key]));
			container.appendChild(box);
		});
	}

	function options() {
		var mode = root.querySelector('input[name="rpi-mode"]:checked');
		var dupes = root.querySelector('input[name="rpi-duplicates"]:checked');
		return {
			mode: mode ? mode.value : 'add',
			duplicates: dupes ? dupes.value : 'skip',
			settings: $('#rpi-settings').checked ? 1 : 0,
			customers: $('#rpi-customers').checked ? 1 : 0
		};
	}

	function actionLabel(row, opts) {
		if (!row.exists || opts.mode === 'replace') {
			return t.create;
		}
		return opts.duplicates === 'update' ? t.update : t.skip;
	}

	function refreshActions() {
		var opts = options();
		$('#rpi-dupes').hidden = opts.mode === 'replace';
		$all('#rpi-preview tbody tr').forEach(function (tr, index) {
			var row = state.preview.rows[index];
			var cell = tr.querySelector('.rpi-action');
			cell.textContent = actionLabel(row, opts);
			tr.classList.toggle('is-skip', !!row.exists && opts.mode !== 'replace' && opts.duplicates === 'skip');
		});
	}

	function renderPreview(preview) {
		renderCounts($('#rpi-counts'), Object.assign({}, preview.counts, { customers: preview.customers }), t.counts);

		var warnings = $('#rpi-global-warnings');
		warnings.textContent = '';
		preview.warnings.forEach(function (warning) {
			warnings.appendChild(el('li', warning));
		});
		warnings.hidden = !preview.warnings.length;

		$('#rpi-existing-count').textContent = preview.existing ? '(' + preview.existing + ')' : '';

		// Options the account or site cannot use are disabled; the reason is in the warnings above.
		var perms = preview.permissions || {};
		[['rpi-mode', 'replace', 'add', perms.replace], ['rpi-duplicates', 'update', 'skip', perms.update]].forEach(function (rule) {
			var input = root.querySelector('input[name="' + rule[0] + '"][value="' + rule[1] + '"]');
			var fallback = root.querySelector('input[name="' + rule[0] + '"][value="' + rule[2] + '"]');
			if (!input) {
				return;
			}
			input.disabled = rule[3] === false;
			if (input.disabled && input.checked && fallback) {
				fallback.checked = true;
			}
		});

		var settingsBox = $('#rpi-settings-box');
		var settingsList = $('#rpi-settings-list');
		settingsList.textContent = '';
		preview.settings.forEach(function (line) {
			settingsList.appendChild(el('li', line));
		});
		settingsBox.hidden = !(preview.settings.length && data.canSettings);
		$('#rpi-settings').checked = false;

		$('#rpi-customers-box').hidden = !(preview.customers && data.canSettings);
		$('#rpi-customers-label').textContent = t.counts.customers + ': ' + preview.customers;
		$('#rpi-customers').checked = false;

		var map = $('#rpi-field-map');
		map.textContent = '';
		Object.keys(preview.field_map || {}).forEach(function (key) {
			var tr = el('tr');
			tr.appendChild(el('th', key));
			tr.appendChild(el('td', preview.field_map[key]));
			map.appendChild(tr);
		});

		var table = $('#rpi-preview');
		var head = table.querySelector('thead tr');
		head.textContent = '';
		['category', 'name', 'price', 'addons', 'dietary', 'image', 'action', 'warnings'].forEach(function (key) {
			head.appendChild(el('th', t.columns[key]));
		});
		var body = table.querySelector('tbody');
		body.textContent = '';
		preview.rows.forEach(function (row) {
			var tr = el('tr');
			tr.appendChild(el('td', row.category));
			var name = el('td');
			name.appendChild(el('strong', row.name));
			if (row.status === 'draft') {
				name.appendChild(el('span', ' (' + t.draft + ')', 'rpi-muted'));
			}
			tr.appendChild(name);
			tr.appendChild(el('td', row.sizes || row.price));
			tr.appendChild(el('td', row.addons || ''));
			tr.appendChild(el('td', row.dietary || ''));
			tr.appendChild(el('td', row.image ? t.yes : ''));
			tr.appendChild(el('td', '', 'rpi-action'));
			var warn = el('td');
			if (row.warnings.length) {
				var list = el('ul', null, 'rpi-row-warnings');
				row.warnings.forEach(function (warning) {
					list.appendChild(el('li', warning));
				});
				warn.appendChild(list);
				tr.classList.add('has-warning');
			}
			tr.appendChild(warn);
			body.appendChild(tr);
		});
		table.parentNode.hidden = !preview.rows.length;

		$('#rpi-start').textContent = preview.rows.length ? (1 === preview.rows.length && t.importOne ? t.importOne : sprintf(t.importN, preview.rows.length)) : t.importOnly;
		refreshActions();
	}

	$all('input[name="rpi-mode"], input[name="rpi-duplicates"]').forEach(function (input) {
		input.addEventListener('change', refreshActions);
	});

	$('#rpi-discard').addEventListener('click', function () {
		if (state.jobId) {
			post('discard', { job_id: state.jobId }).catch(function () {});
		}
		state.jobId = 0;
		go('source');
	});

	/* Step 4: run --------------------------------------------------------- */

	function setProgress(done, total, text) {
		var pct = total ? Math.round((done / total) * 100) : 0;
		$('#rpi-bar').style.width = pct + '%';
		$('.rpi-progress').setAttribute('aria-valuenow', pct);
		$('#rpi-progress-text').textContent = text || (total ? done + ' / ' + total : '');
	}

	function loop(action, title, retries) {
		retries = retries || 0;
		return post(action, { job_id: state.jobId })
			.then(function (result) {
				if (result.total !== undefined) {
					setProgress(result.done, result.total);
				}
				if (result.finished) {
					return result.summary ? result.summary : post('summary', { job_id: state.jobId });
				}
				return wait(result.busy ? 1500 : 50).then(function () {
					return loop(action, title, 0);
				});
			})
			.catch(function (error) {
				if (retries < 3) {
					return wait(2000).then(function () {
						return loop(action, title, retries + 1);
					});
				}
				throw error;
			});
	}

	function run(startFirst) {
		go('run');
		$('#rpi-run-title').textContent = t.importing;
		setProgress(0, state.preview ? state.preview.counts.items : 0);
		var first = startFirst ? post('start', Object.assign({ job_id: state.jobId }, options())) : Promise.resolve({});
		first
			.then(function (result) {
				if (result.total !== undefined) {
					setProgress(result.done, result.total);
				}
				return result.finished ? post('summary', { job_id: state.jobId }) : loop('step');
			})
			.then(showSummary)
			.catch(function (error) {
				go(startFirst ? 'preview' : 'source');
				showError(error.message);
			});
	}

	$('#rpi-start').addEventListener('click', function () {
		if (options().mode === 'replace' && !window.confirm(t.replaceConfirm)) {
			return;
		}
		run(true);
	});

	$all('.rpi-resume').forEach(function (button) {
		button.addEventListener('click', function () {
			state.jobId = parseInt(button.getAttribute('data-job'), 10);
			state.preview = null;
			run(false);
		});
	});

	/* Step 5: summary and undo -------------------------------------------- */

	function showSummary(summary) {
		go('summary');
		var undone = summary.status === 'undone';
		$('#rpi-summary-title').textContent = undone ? t.undone : t.done;
		renderCounts($('#rpi-result'), summary.counts, t.result);
		var log = $('#rpi-log');
		log.textContent = '';
		(summary.log || []).forEach(function (entry) {
			log.appendChild(el('li', entry.message, 'rpi-log-' + entry.level));
		});
		$('#rpi-undo').hidden = undone;
	}

	function undo(jobId) {
		if (!window.confirm(t.undoConfirm)) {
			return;
		}
		state.jobId = jobId;
		go('run');
		$('#rpi-run-title').textContent = t.undoing;
		setProgress(0, 0, t.undoing);
		post('undo', { job_id: jobId })
			.then(function (result) {
				return result.finished ? result.summary : loop('undo_step');
			})
			.then(showSummary)
			.catch(function (error) {
				go('summary');
				showError(error.message);
			});
	}

	$('#rpi-undo').addEventListener('click', function () {
		undo(state.jobId);
	});
	$all('.rpi-undo-recent').forEach(function (button) {
		button.addEventListener('click', function () {
			undo(parseInt(button.getAttribute('data-job'), 10));
		});
	});

	/* Preselected source (?source=gloriafood) ------------------------------ */

	var preselect = $('[data-panel="source"]').getAttribute('data-selected');
	if (preselect) {
		var button = root.querySelector('.rpi-source[data-source="' + preselect.replace(/[^a-z0-9_-]/g, '') + '"]');
		if (button && !button.disabled) {
			chooseSource(button);
		}
	}
})();
