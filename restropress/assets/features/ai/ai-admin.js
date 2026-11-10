/**
 * RestroPress AI Assistant (3.5): settings section, food item editor helpers
 * and the bulk description review screen.
 *
 * Every AI result fills a field or a draft for the merchant to review. This
 * script never submits the post form, and only saves alt text or bulk drafts
 * when the merchant clicks the matching Save button.
 */
(function ($) {
  'use strict';

  var cfg = window.rpressAI;
  if (!cfg || !$) { return; }
  var t = cfg.i18n || {};

  function post(action, data) {
    return $.ajax({
      url: cfg.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      data: $.extend({ action: action, nonce: cfg.nonce }, data || {})
    }).then(function (res) {
      if (res && res.success) { return res.data || {}; }
      return $.Deferred().reject({ responseJSON: res }).promise();
    });
  }

  // Normalise a failed request into { title, message, action_url, action_label }.
  function errorData(xhr) {
    var json = xhr && xhr.responseJSON;
    if (!json && xhr && xhr.responseText) {
      try { json = JSON.parse(xhr.responseText); } catch (e) { json = null; }
    }
    var data = json && json.data ? json.data : (json || {});
    if (typeof data === 'string') { data = { message: data }; }
    if (!data.message) { data.message = t.error || 'The AI request failed. Please try again.'; }
    return data;
  }

  function safeUrl(url) {
    return (typeof url === 'string' && (/^https?:\/\//i.test(url) || url.indexOf('admin.php') === 0)) ? url : '';
  }

  // Render a status message without injecting HTML from the server.
  function showMessage($el, type, data) {
    data = typeof data === 'string' ? { message: data } : (data || {});
    $el.empty().removeClass('is-ok is-error is-loading is-info').addClass(type ? 'is-' + type : '').prop('hidden', false);
    if (data.title && type === 'error') { $('<strong>').text(data.title + ' ').appendTo($el); }
    $('<span>').text(data.message || '').appendTo($el);
    var url = safeUrl(data.action_url) || (type === 'error' && data.error_code === 'configuration' ? cfg.settingsUrl : '');
    if (url) {
      $el.append(' ');
      $('<a>').attr({ href: url, target: /^https?:/i.test(url) && url.indexOf(location.host) === -1 ? '_blank' : null, rel: 'noopener' })
        .text(data.action_label || t.openSettings || 'Open AI settings').appendTo($el);
    } else if (type === 'error' && data.settings_url) {
      $el.append(' ');
      $('<a>').attr('href', cfg.settingsUrl).text(t.openSettings || 'Open AI settings').appendTo($el);
    }
  }

  function textToHtml(text) {
    return String(text).split(/\n{2,}/).map(function (p) {
      return '<p>' + $('<div>').text(p.trim()).html().replace(/\n/g, '<br>') + '</p>';
    }).join('');
  }

  /* ---------------- Settings: AI Assistant ---------------- */
  function initSettings() {
    var $wrap = $('#rp-ai-settings');
    if (!$wrap.length) { return; }
    var models = cfg.models || {};
    var $provider = $('#rp-ai-provider'), $key = $('#rp-ai-key'), $model = $('#rp-ai-model');
    var suggestions = {
      openai: ['gpt-4o-mini', 'gpt-4.1-mini', 'gpt-4o'],
      gemini: ['gemini-3.5-flash-lite', 'gemini-2.5-flash'],
      claude: ['claude-haiku-4-5', 'claude-sonnet-4-5']
    };

    function keyPlaceholder(provider) {
      var hint = models[provider] && models[provider].keyHint;
      return hint ? String(t.savedKey || 'Saved key ending in %s.').replace('%s', hint) : (t.pasteKey || 'Paste your provider API key');
    }

    function syncProvider() {
      var p = $provider.val();
      var isWp = p === 'wordpress';
      $wrap.find('.rp-ai-key-row, .rp-ai-model-row').prop('hidden', isWp);
      $key.val('').attr({ type: 'password', placeholder: keyPlaceholder(p) });
      $('#rp-ai-key-toggle').attr({ 'aria-pressed': 'false', 'aria-label': t.showKey || 'Show API key' })
        .find('.dashicons').addClass('dashicons-visibility').removeClass('dashicons-hidden');
      $wrap.find('.rp-ai-remove-key').prop('hidden', !(models[p] && models[p].keyHint))
        .find('input').prop('checked', false);
      if (!isWp && models[p]) {
        $model.val(models[p].saved || '').attr('placeholder', models[p]['default'] || '');
      }
      var $list = $('#rp-ai-model-list').empty();
      (suggestions[p] || []).forEach(function (m) { $('<option>').attr('value', m).appendTo($list); });
      $('#rp-ai-test-result').empty().prop('hidden', true);
    }

    $provider.on('change', syncProvider);
    syncProvider();

    $('#rp-ai-key-toggle').on('click', function () {
      var show = $key.attr('type') === 'password';
      $key.attr('type', show ? 'text' : 'password');
      $(this).attr({ 'aria-pressed': show ? 'true' : 'false', 'aria-label': show ? (t.hideKey || 'Hide API key') : (t.showKey || 'Show API key') })
        .find('.dashicons').toggleClass('dashicons-visibility', !show).toggleClass('dashicons-hidden', show);
      $key.trigger('focus');
    });

    $('#rp-ai-test').on('click', function () {
      var $b = $(this), $out = $('#rp-ai-test-result'), p = $provider.val();
      $b.prop('disabled', true);
      showMessage($out, 'loading', t.testing || 'Testing connection...');
      post('rpress_ai_test', {
        data: {
          enabled: $('#rp-ai-enabled').is(':checked') ? 'yes' : 'no',
          provider: p,
          api_key: $key.val() || '',
          model: $model.val() || ''
        }
      }).done(function (res) {
        if (models[p]) { models[p].keyHint = res.key_hint || models[p].keyHint; models[p].saved = $model.val() || ''; }
        $key.val('').attr({ type: 'password', placeholder: keyPlaceholder(p) });
        showMessage($out, 'ok', res.message || 'Connected');
        $('#rp-ai-status-summary').text(res.message || '');
        $wrap.find('.rp-ai-status-card').removeClass('is-off').addClass('is-ready');
      }).fail(function (xhr) {
        var data = errorData(xhr);
        if (models[p] && data.key_hint) { models[p].keyHint = data.key_hint; $key.val('').attr('placeholder', keyPlaceholder(p)); }
        showMessage($out, 'error', data);
      }).always(function () { $b.prop('disabled', false); });
    });
  }

  /* ---------------- Food item editor ---------------- */
  function editorContent() {
    var ed = window.tinymce && window.tinymce.get && window.tinymce.get('rp_fi_content');
    if (ed && !ed.isHidden()) { return ed.getContent({ format: 'text' }); }
    return $('#rp_fi_content').val() || '';
  }

  function setEditorContent(text) {
    var html = textToHtml(text);
    var ed = window.tinymce && window.tinymce.get && window.tinymce.get('rp_fi_content');
    if (ed && !ed.isHidden()) { ed.setContent(html); ed.setDirty(true); ed.save(); }
    $('#rp_fi_content').val(html).trigger('change');
  }

  function itemContext() {
    var cats = $('select[name="food_categories[]"] option:selected').map(function () { return $(this).text(); }).get();
    return {
      name: $('#title').val() || '',
      excerpt: $('#excerpt').val() || '',
      content: editorContent(),
      price: $('#rpress_price').val() || '',
      categories: cats
    };
  }

  function postId() { return parseInt($('#post_ID').val(), 10) || 0; }

  function flash($el) {
    $el.addClass('rp-ai-filled');
    setTimeout(function () { $el.removeClass('rp-ai-filled'); }, 2400);
  }

  function writerTools($field, field) {
    var $tools = $('<div class="rp-ai-tools">');
    var $write = $('<button type="button" class="button button-small rp-ai-btn">').attr('data-ai-write', field)
      .append('<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span> ').append(document.createTextNode(t.write || 'Write with AI'));
    var $improve = $('<button type="button" class="button button-small rp-ai-btn">').attr('data-ai-improve', field).text(t.improve || 'Improve');
    var $msg = $('<div class="rp-ai-msg" role="status" aria-live="polite" hidden>');
    $tools.append($write, $improve, $msg);
    var $help = $field.find('.rp-fi-desc-help').first();
    if ($help.length) { $help.after($tools); } else { $field.prepend($tools); }

    function run(mode, $btn) {
      var item = itemContext();
      var current = field === 'excerpt' ? item.excerpt : item.content;
      if (!item.name.trim()) { showMessage($msg, 'error', t.needName); return; }
      if (mode === 'improve' && !String(current).trim()) { showMessage($msg, 'error', t.needText); return; }
      $tools.find('button').prop('disabled', true);
      showMessage($msg, 'loading', t.working || 'Writing...');
      post('rpress_ai_write', { post_id: postId(), field: field, mode: mode, item: item })
        .done(function (res) {
          if (field === 'excerpt') {
            $('#excerpt').val(res.text || '').trigger('input').trigger('change');
            flash($('#excerpt'));
          } else {
            setEditorContent(res.text || '');
            flash($field.find('.wp-editor-wrap'));
          }
          showMessage($msg, 'ok', t.filled);
        })
        .fail(function (xhr) { showMessage($msg, 'error', errorData(xhr)); })
        .always(function () { $tools.find('button').prop('disabled', false); });
    }
    $write.on('click', function () { run('write', $(this)); });
    $improve.on('click', function () { run('improve', $(this)); });
  }

  // Select suggested names in a select (select2 or plain) or checkbox list.
  function selectNames($inputs, names) {
    var wanted = names.map(function (n) { return String(n).toLowerCase(); });
    var matched = [];
    if ($inputs.is('select')) {
      var vals = $inputs.val() || [];
      $inputs.find('option').each(function () {
        var text = $(this).text().trim().toLowerCase(), val = String($(this).val()).toLowerCase();
        var idx = wanted.indexOf(text) > -1 ? wanted.indexOf(text) : wanted.indexOf(val);
        if (idx > -1) { if (vals.indexOf($(this).val()) === -1) { vals.push($(this).val()); } matched.push(names[idx]); }
      });
      $inputs.val(vals).trigger('change');
    } else {
      $inputs.each(function () {
        var $cb = $(this), label = $cb.closest('label').text().trim().toLowerCase(), val = String($cb.val()).toLowerCase();
        var idx = wanted.indexOf(label) > -1 ? wanted.indexOf(label) : wanted.indexOf(val);
        if (idx > -1) { $cb.prop('checked', true).trigger('change'); matched.push(names[idx]); }
      });
    }
    return matched;
  }

  function suggestButton($host, kind, $target) {
    var $tools = $host.find('.rp-ai-tools');
    if (!$tools.length) {
      $tools = $('<div class="rp-ai-tools">');
      var $help = $host.find('.rp-fi-tax-help').first();
      if ($help.length) { $help.after($tools); } else { $host.append($tools); }
    }
    var $btn = $('<button type="button" class="button button-small rp-ai-btn">').attr('data-ai-suggest', kind)
      .append('<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span> ')
      .append(document.createTextNode(kind === 'allergens' ? (t.suggestAllergen || 'Suggest allergens') : (t.suggestDietary || 'Suggest dietary labels')));
    var $msg = $('<div class="rp-ai-msg" role="status" aria-live="polite" hidden>');
    $tools.append($btn, $msg);

    $btn.on('click', function () {
      var item = itemContext();
      if (!item.name.trim()) { showMessage($msg, 'error', t.needName); return; }
      $btn.prop('disabled', true);
      showMessage($msg, 'loading', t.suggesting || 'Checking...');
      post('rpress_ai_suggest', { post_id: postId(), kind: kind, item: item })
        .done(function (res) {
          var parts = [];
          var labels = res.labels || [];
          var matched = $target && $target.length ? selectNames($target, labels) : [];
          var shown = matched.length ? matched : labels;
          if (shown.length) { parts.push((t.suggested || 'AI pre-selected:') + ' ' + shown.join(', ') + '.'); }
          if (kind === 'dietary' && res.food_type) {
            var $radios = $('input[name="rpress_food_type"]');
            var label = res.food_type === 'veg' ? t.veg : t.nonVeg;
            if ($radios.filter(':checked').val() === '' || !$radios.filter(':checked').length) {
              $radios.filter('[value="' + res.food_type + '"]').prop('checked', true).trigger('change');
            }
            parts.push((t.foodType || 'Food type:') + ' ' + label + '.');
          }
          if ((res.other || []).length) { parts.push((t.notInList || 'Also suggested:') + ' ' + res.other.join(', ') + '.'); }
          if (!parts.length) { parts.push(t.noSuggestion); }
          if (res.reason) { parts.push(res.reason); }
          parts.push(t.verify);
          showMessage($msg, 'info', parts.join(' '));
          if ($target && $target.length) { flash($target.next('.select2').length ? $target.next('.select2') : $target.closest('.rp-fi-tax-field')); }
        })
        .fail(function (xhr) { showMessage($msg, 'error', errorData(xhr)); })
        .always(function () { $btn.prop('disabled', false); });
    });
  }

  function altTools() {
    var $actions = $('.rp-fi-image-actions').first();
    if (!$actions.length) { return; }
    var $box = $('<div class="rp-ai-alt" hidden>');
    var $gen = $('<button type="button" class="button button-small rp-ai-btn" id="rp-ai-alt-generate">')
      .append('<span class="dashicons dashicons-admin-customizer" aria-hidden="true"></span> ').append(document.createTextNode(t.altGenerate || 'Generate alt text'));
    var $edit = $('<div class="rp-ai-alt-edit" hidden>');
    var $label = $('<label for="rp-ai-alt-input">').text(t.altLabel || 'Image alt text');
    var $input = $('<input type="text" id="rp-ai-alt-input" class="widefat" maxlength="150">');
    var $save = $('<button type="button" class="button button-small button-primary">').text(t.altSave || 'Save alt text');
    var $msg = $('<div class="rp-ai-msg" role="status" aria-live="polite" hidden>');
    $edit.append($label, $input, $save);
    $box.append($gen, $edit, $msg);
    $actions.after($box);

    var knownAlt = {};
    if (cfg.thumbId) { knownAlt[cfg.thumbId] = cfg.thumbAlt || ''; }

    function thumbId() {
      var v = parseInt($('#_thumbnail_id').val(), 10);
      if (isNaN(v)) { v = parseInt(cfg.thumbId, 10) || 0; }
      return v > 0 ? v : 0;
    }

    function refresh() {
      var id = thumbId();
      $edit.prop('hidden', true);
      $msg.prop('hidden', true);
      if (!id) { $box.prop('hidden', true); return; }
      if (Object.prototype.hasOwnProperty.call(knownAlt, id)) { $box.prop('hidden', !!String(knownAlt[id]).trim()); return; }
      $box.prop('hidden', true);
      if (window.wp && wp.media && wp.media.attachment) {
        var model = wp.media.attachment(id);
        model.fetch().done(function () { knownAlt[id] = model.get('alt') || ''; if (thumbId() === id) { $box.prop('hidden', !!String(knownAlt[id]).trim()); } });
      }
    }

    $gen.on('click', function () {
      var id = thumbId();
      if (!id) { return; }
      $gen.prop('disabled', true);
      showMessage($msg, 'loading', t.working || 'Writing...');
      post('rpress_ai_alt_text', { post_id: postId(), attachment_id: id, item: itemContext() })
        .done(function (res) { $input.val(res.alt || ''); $edit.prop('hidden', false); $msg.prop('hidden', true); $input.trigger('focus'); })
        .fail(function (xhr) { showMessage($msg, 'error', errorData(xhr)); })
        .always(function () { $gen.prop('disabled', false); });
    });

    $save.on('click', function () {
      var id = thumbId();
      $save.prop('disabled', true);
      post('rpress_ai_save_alt', { attachment_id: id, alt: $input.val() || '' })
        .done(function (res) {
          knownAlt[id] = res.alt || '';
          $edit.prop('hidden', true);
          $gen.prop('hidden', true);
          showMessage($msg, 'ok', t.altSaved || 'Alt text saved.');
          if (window.wp && wp.media && wp.media.attachment) { wp.media.attachment(id).set('alt', res.alt || ''); }
        })
        .fail(function (xhr) { showMessage($msg, 'error', errorData(xhr)); })
        .always(function () { $save.prop('disabled', false); });
    });

    $(document).ajaxComplete(function (e, xhr, settings) {
      var data = settings && settings.data;
      if (typeof data === 'string' && data.indexOf('action=set-post-thumbnail') > -1) {
        setTimeout(function () { $gen.prop('hidden', false); refresh(); }, 50);
      }
    });
    refresh();
  }

  function initEditor() {
    var f = cfg.features || {};
    if (f.writer) {
      var $excerpt = $('#excerpt').closest('.rp-fi-desc-field');
      var $content = $('#rp_fi_content').closest('.rp-fi-desc-field');
      if (!$content.length) { $content = $('label[for="rp_fi_content"]').closest('.rp-fi-desc-field'); }
      if ($excerpt.length) { writerTools($excerpt, 'excerpt'); }
      if ($content.length) { writerTools($content, 'description'); }
    }
    if (f.dietary) {
      var $diet = $('#rp-fi-dietary-select');
      var $dietHost = $diet.closest('.rp-fi-tax-field');
      if (cfg.hasDietary && $dietHost.length) { suggestButton($dietHost, 'dietary', $diet); }
      if (cfg.hasAllergens) {
        var $all = $('[name="tax_input[rpress_allergen][]"]');
        var $allHost = $all.first().closest('.rp-fi-tax-field');
        if (!$allHost.length && $all.length) { $allHost = $all.first().parent(); }
        if (!$allHost.length) { $allHost = $dietHost; }
        if ($allHost.length) { suggestButton($allHost, 'allergens', $all); }
      }
    }
    if (f.alt_text) { altTools(); }
  }

  /* ---------------- Bulk review ---------------- */
  function initReview() {
    var $wrap = $('#rp-ai-review');
    if (!$wrap.length) { return; }
    var $rows = $wrap.find('.rp-ai-review-row');
    var $status = $('#rp-ai-review-status');
    var $bar = $('#rp-ai-review-progress');

    function progress(frac) { $bar.prop('hidden', false).find('i').css('width', Math.round(frac * 100) + '%'); }
    function fmt(str, a, b) { return String(str || '').replace('%1$d', a).replace('%2$d', b).replace('%d', a); }

    $('#rp-ai-review-generate').on('click', function () {
      var $b = $(this);
      var queue = $rows.filter(function () { return !$(this).hasClass('is-saved'); }).toArray();
      var total = queue.length, done = 0, stopped = false;
      if (!total) { return; }
      $b.prop('disabled', true);

      function next() {
        if (stopped || !queue.length) {
          $b.prop('disabled', false);
          if (!stopped) { showMessage($status, 'ok', t.generated); }
          $('#rp-ai-review-accept-all, #rp-ai-review-save').prop('disabled', !$rows.find('.rp-ai-accept:not(:disabled)').length);
          return;
        }
        var $row = $(queue.shift());
        showMessage($status, 'loading', fmt(t.generating || 'Generating %1$d of %2$d...', done + 1, total));
        $row.addClass('is-working');
        post('rpress_ai_bulk_generate', { post_id: $row.data('id') })
          .done(function (res) {
            $row.removeClass('is-error');
            $row.find('.rp-ai-row-error').remove();
            if (res.excerpt) { $row.find('.rp-ai-draft-excerpt').val(res.excerpt).prop('disabled', false); }
            if (res.description) { $row.find('.rp-ai-draft-description').val(res.description).prop('disabled', false); }
            if (res.excerpt || res.description) { $row.find('.rp-ai-accept').prop({ disabled: false, checked: false }); }
          })
          .fail(function (xhr) {
            var data = errorData(xhr);
            $row.addClass('is-error');
            $row.find('.rp-ai-row-error').remove();
            $('<div class="rp-ai-row-error">').text(data.message || t.failedRow).appendTo($row.find('td').eq(1));
            if (data.error_code === 'configuration' || data.error_code === 'authentication' || data.error_code === 'billing') {
              stopped = true;
              showMessage($status, 'error', data);
            }
          })
          .always(function () {
            $row.removeClass('is-working');
            done++;
            progress(done / total);
            next();
          });
      }
      next();
    });

    $('#rp-ai-review-accept-all').on('click', function () {
      $rows.find('.rp-ai-accept:not(:disabled)').prop('checked', true);
    });

    $('#rp-ai-review-save').on('click', function () {
      var $b = $(this);
      var items = [];
      $rows.each(function () {
        var $row = $(this);
        if (!$row.find('.rp-ai-accept').is(':checked') || $row.hasClass('is-saved')) { return; }
        items.push({
          id: $row.data('id'),
          excerpt: $row.find('.rp-ai-draft-excerpt').val() || '',
          description: $row.find('.rp-ai-draft-description').val() || ''
        });
      });
      if (!items.length) { showMessage($status, 'error', t.nothingAccepted); return; }
      $b.prop('disabled', true);
      showMessage($status, 'loading', t.saving || 'Saving...');
      post('rpress_ai_bulk_save', { items: items })
        .done(function (res) {
          (res.saved || []).forEach(function (id) {
            var $row = $rows.filter('[data-id="' + id + '"]');
            $row.addClass('is-saved').find('textarea, .rp-ai-accept').prop('disabled', true);
          });
          showMessage($status, 'ok', res.message || ((res.saved || []).length === 1 && t.savedOne ? t.savedOne : fmt(t.saved, (res.saved || []).length)));
        })
        .fail(function (xhr) { showMessage($status, 'error', errorData(xhr)); })
        .always(function () { $b.prop('disabled', false); });
    });
  }

  $(function () {
    if (cfg.context === 'settings') { initSettings(); }
    if (cfg.context === 'editor') { initEditor(); }
    if (cfg.context === 'review') { initReview(); }
  });
})(window.jQuery);
