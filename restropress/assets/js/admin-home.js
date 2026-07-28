/* RestroPress onboarding v4 - guided rail, steps, live preview. */
(function ($) {
  'use strict';

  var cfg = window.rpressOnboarding || {};
  var STEPS = ['profile', 'appearance', 'menu', 'config', 'payments', 'golive'];
  var DIET = ['Vegetarian', 'Vegan', 'Gluten-free', 'Dairy-free', 'Nut-free', 'Halal', 'Kosher', 'Spicy'];

  var $root = $('.rp-onboard');
  if (!$root.length) { return; }

  // Standalone "Import Menu" screen (Menu Items -> Import) reuses this view +
  // JS without the wizard rail/steps/preview. In that mode we pin to the menu
  // step and redirect to the menu list after publishing.
  var menuImportMode = $root.data('mode') === 'menu_import';

  var state = {
    idx: 0,
    done: false,
    menuSub: 'choose',
    track: 'ai',
    sample: 'cafe',
    parsed: false,
    items: [],            // current menu items for preview/review
    jobId: 0,
    testOk: false,
    completed: {},        // step -> true
    cur: $root.data('cur') || '$',
    vegmark: String($root.data('vegmark')) === '1'
  };

  /* ---------- helpers ---------- */
  function esc(s){ return $('<div>').text(s == null ? '' : s).html(); }
  function ajax(action, data){
    return $.post(cfg.ajaxUrl, $.extend({ action: action, nonce: cfg.nonce }, data));
  }
  function getErrMsg(xhr){
    if (xhr && xhr.responseJSON) {
      if (xhr.responseJSON.data && xhr.responseJSON.data.message) { return xhr.responseJSON.data.message; }
      if (xhr.responseJSON.message) { return xhr.responseJSON.message; }
    }
    if (typeof xhr === 'string' && xhr) { return xhr; }
    return cfg.errorText || 'Something went wrong. Please try again.';
  }
  // type: success | error | warn | info - the toast colour must match the
  // message type (errors were showing green before).
  function notice(msg, type){
    type = type && window.tata && tata[type] ? type : 'success';
    if (window.tata && tata[type]) { tata[type]('', msg); }
  }
  // Upload/parse problems also surface inline on the page (kit notice style),
  // so the user sees them even if the toast is missed.
  function inlineError(msg){
    var $sub = $('.rp-ob-pane[data-pane="menu"] [data-sub]:not([hidden])').first();
    if (!$sub.length) { return; }
    $sub.find('.rp-ob-inline-err').remove();
    $('<div class="rp-notice rp-notice-error rp-ob-inline-err" role="alert"></div>').text(msg)
      .prependTo($sub);
  }
  function clearInlineError(){ $('.rp-ob-inline-err').remove(); }

  /* ---------- dietary normalization ---------- */
  var DIET_CANON = {};
  var DIET_KEYS = DIET.map(function (d) { return d.toLowerCase().replace(/[^a-z]/g, ''); });
  DIET.forEach(function (d, n) { DIET_CANON[DIET_KEYS[n]] = d; });
  [['veg', 'Vegetarian'], ['gf', 'Gluten-free'], ['df', 'Dairy-free'], ['hot', 'Spicy'], ['nonveg', '']].forEach(function (p) { DIET_CANON[p[0]] = p[1]; });
  function canonDiet(v){
    if (!v) { return ''; }
    var key = String(v).toLowerCase().replace(/[^a-z]/g, '');
    if (DIET_CANON.hasOwnProperty(key)) { return DIET_CANON[key]; }
    // Map decorated variants (e.g. "Gluten-Free Option", "Vegan friendly")
    // onto the standard label they contain — longest match first so
    // "vegetarian" wins over the "veg" alias.
    for (var n = 0; n < DIET_KEYS.length; n++) {
      if (DIET_KEYS[n].length > 3 && key.indexOf(DIET_KEYS[n]) > -1) { return DIET_CANON[DIET_KEYS[n]]; }
    }
    // Keep genuinely-custom labels (Title Case) so they still survive import.
    return String(v).trim().replace(/\s+/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }
  function normDietary(arr){
    if (!Array.isArray(arr)) { return []; }
    var seen = {}, out = [];
    arr.forEach(function (v) { var c = canonDiet(v); if (c && !seen[c.toLowerCase()]) { seen[c.toLowerCase()] = 1; out.push(c); } });
    return out;
  }
  function normItem(i){
    i.dietary = normDietary(i.dietary);
    i.variants = Array.isArray(i.variants) ? i.variants : [];
    i.modifiers = Array.isArray(i.modifiers) ? i.modifiers : [];
    if (!i.cat) { i.cat = 'Menu'; }
    return i;
  }

  /* ---------- rail / navigation ---------- */
  function render(){
    var key = state.done ? 'done' : STEPS[state.idx];
    $('.rp-ob-pane').attr('hidden', true);
    $('.rp-ob-pane[data-pane="' + key + '"]').removeAttr('hidden');
    if (key === 'menu') { applyMenuSub(); }
    buildRail();
    $('.rp-ob-scroll').scrollTop(0);
    $('#rp-ob-stepof').text(state.done ? cfg.launchedText || 'Complete' : ('Step ' + (state.idx + 1) + ' of ' + STEPS.length));
    syncFooter();
  }
  function buildRail(){
    $('#rp-ob-steps .rp-ob-step').each(function (i) {
      var k = $(this).data('step');
      $(this).toggleClass('active', !state.done && k === STEPS[state.idx]);
      $(this).toggleClass('done', !!state.completed[k] || state.done);
      $(this).find('.rp-ob-dot').html((state.completed[k] || state.done) ? '✓' : (i + 1));
    });
    var doneCount = STEPS.filter(function (k) { return state.completed[k]; }).length;
    var pct = state.done ? 100 : Math.round(doneCount / STEPS.length * 100);
    $('#rp-ob-pbar').css('width', pct + '%');
    $('#rp-ob-pmeta').text(state.done ? 'All set - you’re live' : (doneCount + ' of ' + STEPS.length + ' complete'));
  }
  function syncFooter(){
    var $f = $('#rp-ob-footer'), $b = $('#rp-ob-back'), $n = $('#rp-ob-next');
    if (state.done) { $f.hide(); return; } $f.css('display', 'flex');
    $b.css('visibility', ( state.idx === 0 || ( menuImportMode && state.menuSub === 'choose' ) ) ? 'hidden' : 'visible');
    $n.prop('disabled', false);
    var key = STEPS[state.idx];
    // Note: the UI kit sets .rp-btn{display:... !important}, so jQuery's
    // hide()/show() inline styles lose — toggle a class instead.
    $n.removeClass('rp-ob-hide');
    if (key === 'menu') {
      if (state.menuSub === 'review' || state.menuSub === 'manual') { $n.text('Save menu'); }
      else if (state.menuSub === 'choose') { $n.text('Continue'); }
      else if (state.menuSub === 'sample') { $n.text('Load sample menu'); }
      else { $n.addClass('rp-ob-hide'); }
    } else if (key === 'golive') {
      $n.text(cfg.finishText || 'Finish & go live').prop('disabled', !state.testOk);
    } else {
      $n.text(cfg.nextText || 'Save and continue');
    }
    // On the standalone importer the footer only earns its space when it holds a
    // primary action (Save menu / Load sample). On choose/AI/spreadsheet the cards
    // and each track's own controls drive things, so drop the footer bar entirely
    // rather than show a lone redundant "Continue".
    if (menuImportMode) {
      var footerHasAction = (key === 'menu' && (state.menuSub === 'review' || state.menuSub === 'manual' || state.menuSub === 'sample'));
      $f.css('display', footerHasAction ? 'flex' : 'none');
    }
  }
  function goStep(i){ if (state.done) state.done = false; state.idx = Math.max(0, Math.min(STEPS.length - 1, i)); render(); }
  function advance(){ if (state.idx < STEPS.length - 1) state.idx++; render(); }

  $('#rp-ob-steps').on('click', '.rp-ob-step', function () { goStep(STEPS.indexOf($(this).data('step'))); });
  $('#rp-ob-back').on('click', function () {
    var key = STEPS[state.idx];
    if (key === 'menu' && state.menuSub !== 'choose') { menuSub(state.menuSub === 'review' ? state.track : 'choose'); return; }
    if (menuImportMode) { return; } // no steps before "menu" on the standalone importer
    if (state.idx > 0) goStep(state.idx - 1);
  });
  $('#rp-ob-next').on('click', onNext);

  /* ---------- step data ---------- */
  function collect(pane){
    var data = {};
    $('.rp-ob-pane[data-pane="' + pane + '"]').find('input,select,textarea').each(function () {
      var name = $(this).attr('name'); if (!name) return;
      if ($(this).attr('type') === 'checkbox') { data[name] = $(this).is(':checked') ? ($(this).val() || 1) : ''; }
      else { data[name] = $(this).val(); }
    });
    return data;
  }
  function saveStep(step, data, next){
    return ajax('rpress_onboarding_save_step', { step: step, data: data, next_step: next || '' });
  }

  function onNext(){
    var key = STEPS[state.idx];
    var $n = $('#rp-ob-next'); $n.prop('disabled', true);

    if (key === 'profile') {
      saveStep('profile', collect('profile'), 'appearance').done(function () { state.completed.profile = true; advance(); })
        .fail(function (xhr) { $n.prop('disabled', false); notice(getErrMsg(xhr), 'error'); });
      return;
    }
    if (key === 'appearance') {
      var pack = $('.rp-ob-pane[data-pane="appearance"] input[name="template_pack"]:checked').val() || 'classic';
      saveStep('appearance', {
        template_pack: pack,
        primary_color: $('#rp-ob-theme-color').val() || '',
        template: $('#rp-ob-layout-val').val() || 'list'
      }, 'menu').done(function () { state.completed.appearance = true; advance(); })
        .fail(function (xhr) { $n.prop('disabled', false); notice(getErrMsg(xhr), 'error'); });
      return;
    }
    if (key === 'menu') {
      if (state.menuSub === 'choose') { pickTrack(state.track); $n.prop('disabled', false); return; }
      if (state.menuSub === 'sample') { loadSample(); $n.prop('disabled', false); return; }
      if (state.menuSub === 'review' || state.menuSub === 'manual') { commitMenu(); return; }
      $n.prop('disabled', false); return;
    }
    if (key === 'config') {
      var d = collect('config');
      var svc = $('#rp-ob-svc-val').val();
      // chain the existing controller steps
      var minDelivery = parseFloat(d.minimum_order_price) || 0, minPickup = parseFloat(d.minimum_order_price_pickup) || 0;
      saveStep('ordering', {
        enable_service: svc, prep_time: d.prep_time, enable_asap_option: d.enable_asap_option,
        default_service: (svc === 'delivery' ? 'delivery' : 'pickup'),
        allow_minimum_order: (minDelivery > 0 || minPickup > 0) ? 1 : '',
        minimum_order_price: minDelivery > 0 ? d.minimum_order_price : '',
        minimum_order_price_pickup: minPickup > 0 ? d.minimum_order_price_pickup : ''
      })
        .always(function () { return saveStep('hours', { open_time: d.open_time, close_time: d.close_time }); })
        .then(function () { return saveStep('operations', { enable_order_notification: d.enable_order_notification, admin_notice_emails: d.admin_notice_emails, enable_food_type: d.enable_food_type }); })
        .always(function () { state.completed.config = true; advance(); });
      return;
    }
    if (key === 'payments') {
      saveStep('payments', paymentsData())
        .always(function () { state.completed.payments = true; advance(); });
      return;
    }
    if (key === 'golive') {
      saveStep('launch', { confirm_test_order: 1 }).done(function () { state.completed.golive = true; state.done = true; render(); })
        .fail(function (xhr) { $n.prop('disabled', false); notice(getErrMsg(xhr), 'error'); });
      return;
    }
  }

  /* ---------- profile: states + clock ---------- */
  function loadStates(country, preselect){
    var $state = $('#rp-ob-state');
    if (!country) { return; }
    // rpress_get_states echoes a full <select> of states (or "nostates").
    $.post(cfg.ajaxUrl, { action: 'rpress_get_states', country: country, field_name: 'base_state', security: cfg.getStatesNonce }, function (resp) {
      if (typeof resp === 'string' && resp.indexOf('<option') > -1) {
        var opts = $('<div>').html(resp).find('option');
        $state.empty().append(opts);
        if (preselect) { $state.val(preselect); }
      } else {
        // Country has no state list - keep a neutral placeholder.
        $state.html('<option value="">' + '-' + '</option>');
      }
    });
  }
  $('#rp-ob-country').on('change', function () { loadStates($(this).val(), ''); });
  // Populate states on load when a country is already saved (and re-select the saved state).
  (function () {
    var $c = $('#rp-ob-country');
    if ($c.val()) { loadStates($c.val(), $('#rp-ob-state').data('selected') || ''); }
  })();
  $('#rp-ob-name').on('input', function () { $('#rp-ob-pv-name').text($(this).val() || $root.data('store-name') || 'Your Restaurant'); });
  $('#rp-ob-currency').on('change', function () {
    var t = $(this).find('option:selected').text();
    var m = t.match(/[^\w\s]/); state.cur = (m ? m[0] : '$'); refreshPreview(); $('#rp-ob-pv-cart').text('View cart · ' + state.cur + '0');
  });

  $('#rp-ob-tf').on('click', 'button', function () {
    $('#rp-ob-tf button').removeClass('on'); $(this).addClass('on');
    $('#rp-ob-tf-val').val($(this).data('tf')); tick(); updatePvHours();
  });

  // Appearance step: menu-layout segmented toggle + live theme-colour hex.
  $('#rp-ob-layout').on('click', 'button', function () {
    $('#rp-ob-layout button').removeClass('on'); $(this).addClass('on');
    $('#rp-ob-layout-val').val($(this).data('layout'));
  });
  $('#rp-ob-theme-color').on('input', function () { $('#rp-ob-theme-hex').text(($(this).val() || '').toUpperCase()); });
  // Format an "HH:MM" input value per the 12h/24h choice from the profile step.
  function fmtTime(v){
    if (!v) { return ''; }
    var p = String(v).split(':'), h = parseInt(p[0], 10), m = p[1] || '00';
    if (isNaN(h)) { return v; }
    if ($('#rp-ob-tf-val').val() === '24') { return ('0' + h).slice(-2) + ':' + m; }
    return ((h % 12) || 12) + ':' + m + ' ' + (h < 12 ? 'AM' : 'PM');
  }
  // Keep the phone-preview hours in sync with the Ordering hours fields.
  function updatePvHours(){
    var o = $('input[name="open_time"]').val(), c = $('input[name="close_time"]').val();
    if (o && c) { $('#rp-ob-pv-hours').text(fmtTime(o) + '–' + fmtTime(c)); }
  }
  $root.on('input change', 'input[name="open_time"],input[name="close_time"]', updatePvHours);
  updatePvHours();
  function tick(){
    var $el = $('#rp-ob-clocknow'); if (!$el.length) return;
    var tf = $('#rp-ob-tf-val').val();
    var val = $('#rp-ob-tz').val() || '';
    var h, mm;
    // Preferred: resolve the chosen IANA zone (e.g. "Asia/Kolkata") natively.
    var named = /^[A-Za-z]+(?:\/[A-Za-z0-9_+\-]+)+$/.test(val) || val === 'UTC';
    if (named) {
      try {
        var parts = new Intl.DateTimeFormat('en-GB', { timeZone: val, hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(new Date());
        var get = function (t) { var p = parts.filter(function (x) { return x.type === t; })[0]; return p ? p.value : null; };
        h = parseInt(get('hour'), 10); mm = get('minute');
        if (h === 24) h = 0; // some engines emit "24" at midnight
      } catch (e) { named = false; }
    }
    if (!named || isNaN(h)) {
      // Manual offset selection ("+5.5", "UTC+5:30", "-4", …): derive hours offset.
      var off = 0;
      var sel = $('#rp-ob-tz option:selected').text();
      var src = /[+\-]/.test(val) ? val : sel;
      var m = src.match(/([+\-]?\d{1,2})(?::(\d{2})|\.(\d+))?/);
      if (m) {
        off = parseInt(m[1], 10);
        var sign = off < 0 ? -1 : 1;
        if (m[2]) off += sign * parseInt(m[2], 10) / 60;         // HH:MM
        else if (m[3]) off += sign * parseFloat('0.' + m[3]);    // decimal hours (+5.5)
      }
      var now = new Date(); var utc = now.getTime() + now.getTimezoneOffset() * 60000; var d = new Date(utc + off * 3600000);
      h = d.getHours(); mm = ('0' + d.getMinutes()).slice(-2);
    }
    $el.text(tf === '24' ? (('0' + h).slice(-2) + ':' + mm) : (((h % 12) || 12) + ':' + mm + ' ' + (h < 12 ? 'AM' : 'PM')));
  }
  tick(); setInterval(tick, 30000);
  $('#rp-ob-tz').on('change', tick);

  /* ---------- menu sub-views ---------- */
  function applyMenuSub(){ $('.rp-ob-pane[data-pane="menu"] [data-sub]').attr('hidden', true).filter('[data-sub="' + state.menuSub + '"]').removeAttr('hidden'); }
  function menuSub(v){ state.menuSub = v; applyMenuSub(); syncFooter(); $('.rp-ob-scroll').scrollTop(0); }
  function pickTrack(t){
    state.track = t;
    $('.rp-ob-track[data-track]').removeClass('sel').filter('[data-track="' + t + '"]').addClass('sel');
    if (t === 'ai') menuSub('ai');
    else if (t === 'csv') menuSub('csv');
    else if (t === 'sample') menuSub('sample');
    else { menuSub('manual'); if (!$('#rp-ob-manualrows .rp-ob-ritem').length) addRow(); }
  }
  $('.rp-ob-track[data-track]').on('click', function () { pickTrack($(this).data('track')); });
  $('.rp-ob-track[data-sample]').on('click', function () { state.sample = $(this).data('sample'); $('.rp-ob-track[data-sample]').removeClass('sel'); $(this).addClass('sel'); });
  $('.rp-ob-sub-back').on('click', function () { menuSub('choose'); });
  $('.rp-ob-pick-manual').on('click', function (e) { e.preventDefault(); pickTrack('manual'); });
  $('.rp-ob-pick-csv').on('click', function (e) { e.preventDefault(); pickTrack('csv'); });

  /* AI upload */
  $('.rp-ob-aiopt').on('click', function (e) { if (e.target.tagName !== 'INPUT') { var c = $(this).find('input'); c.prop('checked', !c.prop('checked')); } $(this).toggleClass('on', $(this).find('input').is(':checked')); });
  // Use the native .click() (not jQuery .trigger) so the browser treats it
  // as a real user gesture and opens the file picker.
  $('#rp-ob-dz').on('click', function (e) { if (e.target.id !== 'rp-ob-file') { document.getElementById('rp-ob-file').click(); } });
  $('#rp-ob-file').on('change', function () { if (this.files && this.files.length) uploadMenu(this.files); });
  // Drag-and-drop onto the AI drop zone (matches the spreadsheet track). Without
  // these, dropping a file did nothing - only click-to-choose worked.
  $('#rp-ob-dz').on('dragover dragenter', function (e) { e.preventDefault(); $(this).addClass('rp-ob-dz-over'); });
  $('#rp-ob-dz').on('dragleave dragend drop', function () { $(this).removeClass('rp-ob-dz-over'); });
  $('#rp-ob-dz').on('drop', function (e) {
    e.preventDefault();
    var dt = e.originalEvent && e.originalEvent.dataTransfer;
    if (dt && dt.files && dt.files.length) { uploadMenu(dt.files); }
  });
  // Spreadsheet track: the direct RestroPress CSV importer
  // (RPRESS_Batch_FoodItems_Import via RPRESS_Import in rp-admin.js). We give its
  // file input the same drop-zone UX as the AI track, then auto-submit so the
  // column-mapping step appears. AI is NOT involved here.
  (function () {
    var $csvForm = $('#rpress-import-fooditems');
    var csvInput = document.getElementById('rpress-fooditems-import-file');
    if (!$csvForm.length || !csvInput) { return; }
    var $dz = $('#rp-csv-dz');
    var $title = $dz.find('.rp-csv-dz-title');
    var $hint = $('#rp-csv-dzhint');
    var $ic = $('#rp-csv-dz-ic');
    var $actions = $('#rp-csv-actions');
    var defTitle = $title.text();
    var defHint = $hint.text();
    var defIc = $ic.text();

    function resetCsv() {
      try { csvInput.value = ''; } catch (err) {}
      $dz.removeClass('rp-csv-dz-has-file');
      $title.text(defTitle); $hint.text(defHint); $ic.text(defIc);
      $actions.prop('hidden', true);
    }
    function showChosen(name) {
      $dz.addClass('rp-csv-dz-has-file');
      $title.text(name);
      $hint.text(cfg.csvReadyText || 'Ready to import — click “Upload & map columns”.');
      $ic.text('✅');
      $actions.prop('hidden', false);
    }

    $dz.on('click', function (e) { if (e.target.id !== 'rpress-fooditems-import-file' && !$dz.hasClass('rp-csv-dz-has-file')) { csvInput.click(); } });
    $dz.on('dragover', function (e) { e.preventDefault(); $(this).addClass('rp-ob-dz-over'); });
    $dz.on('dragleave dragend drop', function () { $(this).removeClass('rp-ob-dz-over'); });
    $dz.on('drop', function (e) {
      e.preventDefault();
      var dt = e.originalEvent && e.originalEvent.dataTransfer;
      if (dt && dt.files && dt.files.length) {
        try { csvInput.files = dt.files; } catch (err) { /* older browsers: read-only files */ }
        $(csvInput).trigger('change');
      }
    });
    // Pick a file -> show its name + an explicit Upload button (no blind auto-submit,
    // so a wrong file can be swapped before anything is uploaded).
    $(csvInput).on('change', function () {
      if (this.files && this.files.length) { showChosen(this.files[0].name); }
    });
    $csvForm.on('click', '.rp-csv-change', function (e) { e.preventDefault(); resetCsv(); });
  })();

  /* AI provider selection (WordPress AI / OpenAI / Gemini + key) */
  function aiProviderData(){
    return { enabled: 'yes', provider: $('#rp-ob-ai-provider').val() || 'wordpress', api_key: $('#rp-ob-ai-key').val() || '', model: '' };
  }
  function saveAiSettings(){ return saveStep('ai', aiProviderData()); }
  $('#rp-ob-ai-provider').on('change', function () {
    var isWp = $(this).val() === 'wordpress';
    $('#rp-ob-ai-key-wrap').prop('hidden', isWp);
    aiStatus('', isWp ? 'Test to check your site’s built-in AI connection.' : 'Add your API key, then save & test.');
    saveAiSettings();
  });
  $('#rp-ob-ai-key').on('blur', function () { saveAiSettings(); });
  function aiStatus(state, msg){ $('#rp-ob-ai-status-text').removeClass('is-ok is-error is-loading').addClass(state ? ('is-' + state) : '').text(msg); }
  $('#rp-ob-ai-test').on('click', function () {
    var $b = $(this); $b.prop('disabled', true);
    aiStatus('loading', 'Testing connection…');
    ajax('rpress_onboarding_test_ai', { data: aiProviderData() })
      .done(function (res) { aiStatus('ok', (res && res.data && res.data.message) || 'Connected'); })
      .fail(function (xhr) { var m = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message; aiStatus('error', m || 'Not connected yet'); })
      .always(function () { $b.prop('disabled', false); });
  });

  /* progress UI */
  function showProgress(label){ $('#rp-ob-parse').prop('hidden', false); $('#rp-ob-parse-label').text(label); }
  function setProgress(frac){ $('#rp-ob-parse-fill').css('width', Math.max(2, Math.min(100, Math.round(frac * 100))) + '%'); }
  function hideProgress(){ $('#rp-ob-parse').prop('hidden', true); setProgress(0); }

  // Upload one file; reports upload progress via onUp(0..1), resolves with {items, jobId}.
  function uploadOne(file, onUp){
    return $.Deferred(function (d) {
      var fd = new FormData();
      fd.append('action', 'rpress_onboarding_upload_menu');
      fd.append('nonce', cfg.nonce);
      fd.append('consent', $('#rp-ob-consent').is(':checked') ? 'yes' : 'no');
      fd.append('generate_descriptions', $('#rp-ob-gendesc').is(':checked') ? 'yes' : 'no');
      fd.append('menu_file', file);
      $.ajax({
        url: cfg.ajaxUrl, method: 'POST', data: fd, processData: false, contentType: false,
        xhr: function () { var x = new window.XMLHttpRequest(); if (x.upload && onUp) { x.upload.addEventListener('progress', function (e) { if (e.lengthComputable) onUp(e.loaded / e.total); }); } return x; }
      }).done(function (res) {
        if (res && res.success && res.data) { d.resolve({ items: flattenPayload(res.data.payload), jobId: res.data.job_id || 0 }); }
        else { d.reject((res && res.data && res.data.message) || cfg.errorText); }
      }).fail(function (xhr) { d.reject((xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || cfg.errorText); });
    }).promise();
  }

  // Parse one or more files sequentially, merging results, with a progress bar.
  function uploadMenu(fileList){
    saveAiSettings(); // persist the chosen provider/key before parsing
    var files = Array.prototype.slice.call(fileList || []);
    if (!files.length) { return; }
    clearInlineError();
    var merged = [], i = 0, total = files.length;
    showProgress(total > 1 ? ('Parsing file 1 of ' + total + '…') : 'Uploading…');
    function step(){
      if (i >= total) {
        state.items = merged; state.parsed = true;
        hideProgress(); buildReview(); refreshPreview(); menuSub('review');
        return;
      }
      $('#rp-ob-parse-label').text(total > 1 ? ('Parsing file ' + (i + 1) + ' of ' + total + '…') : 'Reading your menu with AI…');
      uploadOne(files[i], function (frac) { setProgress((i + frac * 0.9) / total); })
        .done(function (r) { merged = merged.concat(r.items); if (r.jobId) state.jobId = r.jobId; i++; setProgress(i / total); step(); })
        .fail(function (msg) { hideProgress(); inlineError(msg); notice(msg, 'error'); $('#rp-ob-dzhint,#rp-ob-csvhint').text('Try again'); });
    }
    step();
  }

  function flattenPayload(payload){
    var out = [];
    if (!payload || !payload.categories) return out;
    payload.categories.forEach(function (cat) {
      (cat.items || []).forEach(function (it) {
        out.push(normItem({
          name: it.name || '', cat: cat.name || 'Menu', price: it.price || '',
          desc: it.description || '', dietary: it.dietary || [],
          variants: it.variants || [], modifiers: it.modifiers || [],
          conf: typeof it.confidence === 'number' ? Math.round(it.confidence * 100) : 100,
          warnings: it.warnings || []
        }));
      });
    });
    return out;
  }

  /* review grid */
  function vmark(i){ if (!state.vegmark || (i.veg !== true && i.veg !== false)) return ''; return '<span class="rp-ob-vmark' + (i.veg === false ? ' nonveg' : '') + '"></span> '; }
  function dietOn(i, d){ return (i.dietary || []).some(function (x) { return String(x).toLowerCase() === String(d).toLowerCase(); }); }
  // Wrap a price input with the store currency symbol shown inside the field.
  function money(input){ return '<span class="rp-ob-money"><span class="rp-ob-cur">' + esc(state.cur) + '</span>' + input + '</span>'; }
  function minVariantPrice(i){
    var nums = (i.variants || []).map(function (v) { return parseFloat(v.price); }).filter(function (n) { return !isNaN(n); });
    return nums.length ? Math.min.apply(null, nums).toFixed(2) : '';
  }
  function metaHtml(i){
    var meta = '';
    // No category badge here - the item already sits under its category
    // heading, so repeating it on every row is noise.
    (i.dietary || []).forEach(function (d) { meta += '<span class="rp-badge is-success">' + esc(d) + '</span>'; });
    if (i.variants && i.variants.length) { meta += '<span class="rp-badge rp-ob-binfo">' + i.variants.length + ' size' + (i.variants.length > 1 ? 's' : '') + '</span>'; }
    if (i.modifiers && i.modifiers.length) {
      var opts = i.modifiers.reduce(function (a, m) { return a + ((m.options || []).length); }, 0);
      meta += '<span class="rp-badge rp-ob-binfo">' + i.modifiers.length + ' add-on' + (i.modifiers.length > 1 ? 's' : '') + (opts ? ' · ' + opts + ' option' + (opts > 1 ? 's' : '') : '') + '</span>';
    }
    return meta;
  }
  function variantRows(i){
    return (i.variants || []).map(function (v, vi) {
      return '<div class="rp-ob-vrow">' +
        '<input type="text" data-ix="' + i._ix + '" data-v="' + vi + '" data-vf="name" value="' + esc(v.name || '') + '" placeholder="Size name e.g. Small">' +
        money('<input type="text" class="rp-ob-vprice" data-ix="' + i._ix + '" data-v="' + vi + '" data-vf="price" value="' + esc(v.price || '') + '" placeholder="0.00">') +
        '<button type="button" class="rp-ob-x" data-act="delvar" data-ix="' + i._ix + '" data-v="' + vi + '" title="Remove size">✕</button>' +
        '</div>';
    }).join('');
  }
  function modGroups(i){
    return (i.modifiers || []).map(function (m, mi) {
      var opts = (m.options || []).map(function (o, oi) {
        return '<div class="rp-ob-modopt">' +
          '<input type="text" data-ix="' + i._ix + '" data-m="' + mi + '" data-o="' + oi + '" data-of="name" value="' + esc(o.name || '') + '" placeholder="Option e.g. Extra cheese">' +
          money('<input type="text" class="rp-ob-vprice" data-ix="' + i._ix + '" data-m="' + mi + '" data-o="' + oi + '" data-of="price" value="' + esc(o.price || '') + '" placeholder="0.00">') +
          '<button type="button" class="rp-ob-x" data-act="delopt" data-ix="' + i._ix + '" data-m="' + mi + '" data-o="' + oi + '" title="Remove option">✕</button>' +
          '</div>';
      }).join('');
      return '<div class="rp-ob-modgroup">' +
        '<div class="rp-ob-modhd">' +
          '<input type="text" data-ix="' + i._ix + '" data-m="' + mi + '" data-mf="name" value="' + esc(m.name || '') + '" placeholder="Group name e.g. Extra toppings">' +
          '<select data-ix="' + i._ix + '" data-m="' + mi + '" data-mf="type"><option value="single"' + (m.type === 'single' ? ' selected' : '') + '>Choose one</option><option value="multiple"' + (m.type !== 'single' ? ' selected' : '') + '>Choose many</option></select>' +
          '<button type="button" class="rp-ob-x" data-act="delgrp" data-ix="' + i._ix + '" data-m="' + mi + '" title="Remove group">✕</button>' +
        '</div>' +
        '<div class="rp-ob-modbody"><div class="rp-ob-modopts">' + opts + '</div>' +
        '<button type="button" class="rp-btn rp-btn-secondary rp-ob-btn-sm rp-ob-addmini" data-act="addopt" data-ix="' + i._ix + '" data-m="' + mi + '">+ Add option</button></div>' +
        '</div>';
    }).join('');
  }
  function sectionLabel(text, hint){
    return '<div class="rp-ob-seclbl">' + text + (hint ? ' <span class="rp-ob-sechint">' + hint + '</span>' : '') + '</div>';
  }
  function itemDetail(i){
    var isVar = !!(i.variants && i.variants.length);
    var d = '';
    // Basics
    d += '<div class="rp-ob-sec">' + sectionLabel('Basics') +
      '<label class="rp-ob-fld"><span>Item name</span><input type="text" data-ix="' + i._ix + '" data-f="name" value="' + esc(i.name || '') + '" placeholder="Item name"></label>' +
      '<label class="rp-ob-fld"><span>Description</span><textarea data-ix="' + i._ix + '" data-f="desc" placeholder="Short description shown to customers">' + esc(i.desc || '') + '</textarea></label>' +
      '</div>';
    // Pricing
    d += '<div class="rp-ob-sec">' + sectionLabel('Pricing', 'how this item is priced') +
      '<div class="rp-ob-seg rp-ob-priceseg">' +
        '<button type="button" data-act="setprice" data-mode="single" data-ix="' + i._ix + '" class="' + (isVar ? '' : 'on') + '">Single price</button>' +
        '<button type="button" data-act="setprice" data-mode="sizes" data-ix="' + i._ix + '" class="' + (isVar ? 'on' : '') + '">Multiple sizes</button>' +
      '</div>';
    if (isVar) {
      d += '<div class="rp-ob-colhead"><span>Size name</span><span>Price</span><span></span></div>' +
        '<div class="rp-ob-vtable">' + variantRows(i) + '</div>' +
        '<button type="button" class="rp-btn rp-btn-secondary rp-ob-btn-sm rp-ob-addmini" data-act="addvar" data-ix="' + i._ix + '">+ Add size</button>';
    } else {
      d += '<div class="rp-ob-priceone">' + money('<input type="text" data-ix="' + i._ix + '" data-f="price" value="' + esc(i.price || '') + '" placeholder="0.00">') + '</div>';
    }
    d += '</div>';
    // Dietary
    d += '<div class="rp-ob-sec">' + sectionLabel('Dietary labels', 'shown as filters & badges to customers') +
      '<div class="rp-ob-dietsel">' +
      DIET.map(function (x) { return '<span class="rp-ob-dchip' + (dietOn(i, x) ? ' on' : '') + '" data-ix="' + i._ix + '" data-d="' + esc(x) + '">' + x + '</span>'; }).join('') + '</div>' +
      '</div>';
    // Add-ons
    d += '<div class="rp-ob-sec">' + sectionLabel('Add-ons & modifiers', 'optional extras customers can choose') +
      '<div class="rp-ob-modwrap">' + modGroups(i) +
      ((i.modifiers && i.modifiers.length) ? '' : '<div class="rp-ob-empty">No add-ons yet.</div>') + '</div>' +
      '<button type="button" class="rp-btn rp-btn-secondary rp-ob-btn-sm" data-act="addgrp" data-ix="' + i._ix + '">+ Add add-on group</button>' +
      '</div>';
    return d;
  }
  function priceLabel(i){
    if (i.variants && i.variants.length) { return 'By size'; }
    return i.price ? (state.cur + esc(i.price)) : 'Set price';
  }
  function thumbHtml(i){
    if (i.image) { return '<span class="rp-ob-rthumb has-img" data-ix="' + i._ix + '" title="Change photo" style="background-image:url(' + esc(i.image) + ')"></span>'; }
    return '<span class="rp-ob-rthumb" data-ix="' + i._ix + '" title="Add photo">🍽️</span>';
  }
  function itemCard(i){
    var isVar = !!(i.variants && i.variants.length);
    var flagged = (i.warnings && i.warnings.length) || (!i.price && !isVar) || i.conf < 75;
    var sub = isVar ? ('<span class="rp-ob-pricesub">from ' + state.cur + (minVariantPrice(i) || '0.00') + '</span>') : '';
    return $('<div class="rp-ob-ritem' + (flagged ? ' open' : '') + '" data-ix="' + i._ix + '" draggable="true">' +
      '<div class="rp-ob-rsum">' +
      '<span class="rp-ob-drag" title="Drag to reorder">⋮⋮</span>' +
      thumbHtml(i) +
      '<div class="rp-ob-rname">' + vmark(i) + '<span class="rp-ob-rtitle">' + esc(i.name) + '</span><div class="rp-ob-rmeta">' + metaHtml(i) + '</div></div>' +
      '<div class="rp-ob-rprice' + (isVar ? ' rp-ob-bysize' : '') + '">' + priceLabel(i) + sub + '</div>' +
      '<div class="rp-ob-conf' + (i.conf < 75 ? ' low' : '') + '" title="Import confidence - how sure the importer is it read this item correctly. Amber items deserve a closer look.">' + i.conf + '%</div>' +
      '<span class="rp-ob-chev">⌄</span></div>' +
      (flagged && i.warnings && i.warnings.length ? '<div class="rp-ob-flag">⚠ ' + esc(i.warnings.join(' · ')) + '</div>' : '') +
      '<div class="rp-ob-rdetail">' + itemDetail(i) + '</div></div>');
  }
  function rerenderItem(ix){
    var i = state.items[ix]; if (!i) { return; } i._ix = ix;
    var $old = $('#rp-ob-review .rp-ob-ritem[data-ix="' + ix + '"]');
    var open = $old.hasClass('open');
    var $new = itemCard(i); if (open) { $new.addClass('open'); }
    $old.replaceWith($new);
    refreshPreview();
  }
  function buildReview(){
    var $g = $('#rp-ob-review'); $g.empty();
    state.items.forEach(function (i, ix) { i._ix = ix; });
    var byCat = {}; state.items.forEach(function (i) { (byCat[i.cat || 'Menu'] = byCat[i.cat || 'Menu'] || []).push(i); });
    Object.keys(byCat).forEach(function (cat) {
      var n = byCat[cat].length;
      var $box = $('<div class="rp-ob-rev"><div class="rp-ob-rev-head">' +
        '<input type="text" class="rp-ob-cat-rename" data-cat="' + esc(cat) + '" value="' + esc(cat) + '" title="Rename this category" aria-label="Category name">' +
        '<span class="rp-ob-rev-count">' + n + ' item' + (n > 1 ? 's' : '') + '</span></div></div>');
      byCat[cat].forEach(function (i) { $box.append(itemCard(i)); });
      $g.append($box);
    });
  }
  // Rename a category for every item in the group (commit on blur/Enter).
  $('#rp-ob-review').on('keydown', '.rp-ob-cat-rename', function (e) { if (e.key === 'Enter') { e.preventDefault(); this.blur(); } });
  $('#rp-ob-review').on('blur', '.rp-ob-cat-rename', function () {
    var from = String($(this).data('cat')), to = $.trim(this.value);
    if (!to || to === from) { this.value = from; return; }
    state.items.forEach(function (i) { if ((i.cat || 'Menu') === from) { i.cat = to; } });
    buildReview(); refreshPreview();
  });
  $('#rp-ob-review').on('click', '.rp-ob-rsum', function (e) { if ($(e.target).is('input,select,button,textarea') || $(e.target).closest('.rp-ob-rthumb,.rp-ob-drag').length) { return; } $(this).closest('.rp-ob-ritem').toggleClass('open'); });

  // Per-item image: open the WordPress media library and store the chosen attachment.
  $('#rp-ob-review').on('click', '.rp-ob-rthumb', function (e) {
    e.stopPropagation();
    var ix = +this.dataset.ix, it = state.items[ix]; if (!it) { return; }
    if (!window.wp || !wp.media) { notice(cfg.errorText || 'Media library unavailable', 'error'); return; }
    if (!state._mediaFrame) {
      state._mediaFrame = wp.media({ title: 'Choose item photo', button: { text: 'Use this photo' }, multiple: false, library: { type: 'image' } });
      state._mediaFrame.on('select', function () {
        var att = state._mediaFrame.state().get('selection').first().toJSON();
        var t = state._mediaIx; var item = state.items[t]; if (!item) { return; }
        item.image_id = att.id;
        item.image = (att.sizes && att.sizes.thumbnail) ? att.sizes.thumbnail.url : att.url;
        rerenderItem(t);
      });
    }
    state._mediaIx = ix;
    state._mediaFrame.open();
  });

  // Add / remove sizes, add-on groups and options; switch pricing mode.
  $('#rp-ob-review').on('click', '[data-act]', function (e) {
    e.preventDefault(); e.stopPropagation();
    var el = this, ix = +el.dataset.ix, it = state.items[ix]; if (!it) { return; }
    var act = el.dataset.act;
    if (act === 'setprice') {
      if (el.dataset.mode === 'sizes') {
        if (!it.variants || !it.variants.length) { it.variants = [{ name: '', price: it.price || '' }]; }
      } else if (it.variants && it.variants.length) {
        if (!it.price) { it.price = it.variants[0].price || ''; }
        it.variants = [];
      }
    } else if (act === 'addvar') {
      it.variants = it.variants || []; it.variants.push({ name: '', price: '' });
    } else if (act === 'delvar') {
      it.variants.splice(+el.dataset.v, 1);
      if (!it.variants.length && !it.price) { it.price = ''; }
    } else if (act === 'addgrp') {
      it.modifiers = it.modifiers || []; it.modifiers.push({ name: '', type: 'multiple', options: [{ name: '', price: '' }] });
    } else if (act === 'delgrp') {
      it.modifiers.splice(+el.dataset.m, 1);
    } else if (act === 'addopt') {
      var g = it.modifiers[+el.dataset.m]; g.options = g.options || []; g.options.push({ name: '', price: '' });
    } else if (act === 'delopt') {
      it.modifiers[+el.dataset.m].options.splice(+el.dataset.o, 1);
    } else { return; }
    rerenderItem(ix);
  });

  $('#rp-ob-review').on('click', '.rp-ob-dchip', function () {
    var ix = +this.dataset.ix, d = String(this.dataset.d), it = state.items[ix];
    it.dietary = it.dietary || [];
    var pos = -1; it.dietary.forEach(function (x, n) { if (String(x).toLowerCase() === d.toLowerCase()) { pos = n; } });
    if (pos > -1) { it.dietary.splice(pos, 1); } else { it.dietary.push(d); }
    $(this).toggleClass('on');
    $(this).closest('.rp-ob-ritem').find('.rp-ob-rmeta').first().html(metaHtml(it));
    refreshPreview();
  });
  $('#rp-ob-review').on('input', 'input[data-f],textarea[data-f]', function () {
    var ix = +this.dataset.ix, f = this.dataset.f, it = state.items[ix]; it[f] = this.value;
    var $card = $(this).closest('.rp-ob-ritem');
    if (f === 'name' || f === 'price') { $card.find('.rp-ob-rmeta').first().html(metaHtml(it)); }
    if (f === 'name') { $card.find('.rp-ob-rtitle').text(it.name); }
    if (f === 'price') { $card.find('.rp-ob-rprice').not('.rp-ob-bysize').text(priceLabel(it)); }
    refreshPreview();
  });
  // Persist variant / modifier text edits back to state (no re-render needed).
  $('#rp-ob-review').on('input change', 'input[data-vf],input[data-of],input[data-mf],select[data-mf]', function () {
    var el = this, ix = +el.dataset.ix, it = state.items[ix]; if (!it) { return; }
    if (el.dataset.vf && it.variants && it.variants[+el.dataset.v]) {
      it.variants[+el.dataset.v][el.dataset.vf] = el.value;
      if (el.dataset.vf === 'price') { $(el).closest('.rp-ob-ritem').find('.rp-ob-pricesub').text('from ' + state.cur + (minVariantPrice(it) || '0.00')); }
    } else if (el.dataset.of && it.modifiers && it.modifiers[+el.dataset.m] && it.modifiers[+el.dataset.m].options[+el.dataset.o]) {
      it.modifiers[+el.dataset.m].options[+el.dataset.o][el.dataset.of] = el.value;
    } else if (el.dataset.mf && it.modifiers && it.modifiers[+el.dataset.m]) {
      it.modifiers[+el.dataset.m][el.dataset.mf] = el.value;
    }
    refreshPreview();
  });

  // Drag to reorder items within their category.
  var dragIx = null;
  $('#rp-ob-review').on('dragstart', '.rp-ob-ritem', function (e) {
    dragIx = +this.dataset.ix; this.classList.add('rp-ob-dragging');
    if (e.originalEvent.dataTransfer) { e.originalEvent.dataTransfer.effectAllowed = 'move'; }
  });
  $('#rp-ob-review').on('dragend', '.rp-ob-ritem', function () { this.classList.remove('rp-ob-dragging'); $('.rp-ob-dragover').removeClass('rp-ob-dragover'); });
  $('#rp-ob-review').on('dragover', '.rp-ob-ritem', function (e) {
    if (dragIx === null) { return; }
    var over = +this.dataset.ix;
    if ((state.items[over] && state.items[dragIx]) && (state.items[over].cat || 'Menu') === (state.items[dragIx].cat || 'Menu')) {
      e.preventDefault(); $(this).addClass('rp-ob-dragover');
    }
  });
  $('#rp-ob-review').on('dragleave', '.rp-ob-ritem', function () { $(this).removeClass('rp-ob-dragover'); });
  $('#rp-ob-review').on('drop', '.rp-ob-ritem', function (e) {
    e.preventDefault();
    var over = +this.dataset.ix;
    if (dragIx === null || over === dragIx) { dragIx = null; return; }
    var moved = state.items[dragIx], targetItem = state.items[over];
    state.items.splice(state.items.indexOf(moved), 1);
    state.items.splice(state.items.indexOf(targetItem) + (over > dragIx ? 1 : 0), 0, moved);
    dragIx = null; buildReview(); refreshPreview();
  });

  /* manual rows */
  function addRow(name, price){
    var $r = $('<div class="rp-ob-ritem"><div class="rp-ob-rsum" style="grid-template-columns:1fr 96px 40px">' +
      '<input type="text" class="m-n" placeholder="Item name" value="' + esc(name || '') + '">' +
      '<input type="text" class="m-p" placeholder="price" value="' + esc(price || '') + '">' +
      '<button type="button" class="rp-ob-row-x">✕</button></div></div>');
    $('#rp-ob-manualrows').append($r); syncManual();
  }
  $('#rp-ob-addrow').on('click', function () { addRow(); });
  $('#rp-ob-manualrows').on('click', '.rp-ob-row-x', function () { $(this).closest('.rp-ob-ritem').remove(); syncManual(); });
  $('#rp-ob-manualrows').on('input', 'input', syncManual);
  function syncManual(){
    state.items = [];
    $('#rp-ob-manualrows .rp-ob-ritem').each(function () {
      var n = $(this).find('.m-n').val(); if (n) state.items.push({ name: n, cat: 'Menu', price: $(this).find('.m-p').val(), dietary: [], variants: [], modifiers: [] });
    });
    refreshPreview();
  }

  /* commit menu - AI/CSV (has job) via publish_menu; sample/manual via publish_items */
  function commitMenu(){
    var $n = $('#rp-ob-next'); $n.prop('disabled', true);
    var n = state.items.length;
    var finish = function () {
      if (menuImportMode) {
        notice(n + ' ' + (cfg.publishedText || 'menu items published.'), 'success');
        var url = $root.data('menu-list');
        if (url) { setTimeout(function () { window.location = url; }, 900); }
        return;
      }
      state.completed.menu = true; $('#rp-ob-vmenu').text(n + ' items live on the ordering page'); refreshPreview(); advance();
    };
    var fail = function (xhr) { $n.prop('disabled', false); notice(getErrMsg(xhr), 'error'); };
    var payload = JSON.stringify({ categories: regroup() });
    if (state.jobId) {
      ajax('rpress_onboarding_publish_menu', { job_id: state.jobId, mode: 'publish', payload: payload }).done(finish).fail(fail);
    } else {
      ajax('rpress_onboarding_publish_items', { payload: payload, mode: 'publish', is_sample: state.isSample ? 1 : 0 }).done(finish).fail(fail);
    }
  }
  function regroup(){
    var byCat = {};
    state.items.forEach(function (i) {
      (byCat[i.cat || 'Menu'] = byCat[i.cat || 'Menu'] || []).push({ name: i.name, price: i.price, description: i.desc, dietary: i.dietary, food_type: i.food_type || '', variants: i.variants, modifiers: i.modifiers, image_id: i.image_id || 0 });
    });
    return Object.keys(byCat).map(function (c) { return { name: c, items: byCat[c] }; });
  }

  /* ---------- sample menus (client-side, published via publish_items) ---------- */
  var SAMPLE_MENUS = {
    cafe: [
      { name: 'Cappuccino', cat: 'Coffee', price: '3.50', desc: 'Rich espresso with steamed milk & foam.', food_type: 'veg', dietary: ['Vegetarian'], variants: [{ name: 'Small', price: '3.50' }, { name: 'Large', price: '4.50' }], modifiers: [{ name: 'Milk', type: 'single', options: [{ name: 'Whole', price: '0' }, { name: 'Oat', price: '0.60' }] }] },
      { name: 'Avocado Toast', cat: 'Brunch', price: '7.00', desc: 'Sourdough, smashed avocado, chilli & lime.', food_type: 'veg', dietary: ['Vegetarian'], variants: [], modifiers: [] },
      { name: 'Blueberry Muffin', cat: 'Bakery', price: '3.00', desc: 'Baked fresh each morning.', food_type: 'veg', dietary: ['Vegetarian'], variants: [], modifiers: [] }
    ],
    pizza: [
      { name: 'Margherita', cat: 'Pizzas', price: '11.00', desc: 'Tomato, mozzarella & basil.', food_type: 'veg', dietary: ['Vegetarian'], variants: [{ name: '9"', price: '11.00' }, { name: '12"', price: '15.00' }], modifiers: [{ name: 'Extra toppings', type: 'multiple', options: [{ name: 'Mushroom', price: '1.50' }, { name: 'Olives', price: '1.50' }] }] },
      { name: 'Pepperoni', cat: 'Pizzas', price: '13.00', desc: 'Loaded with pepperoni & mozzarella.', food_type: 'non_veg', dietary: [], variants: [{ name: '9"', price: '13.00' }, { name: '12"', price: '17.00' }], modifiers: [] },
      { name: 'Garlic Bread', cat: 'Sides', price: '5.00', desc: 'Wood-fired with garlic butter.', food_type: 'veg', dietary: ['Vegetarian'], variants: [], modifiers: [] }
    ],
    diner: [
      { name: 'Classic Cheeseburger', cat: 'Burgers', price: '9.00', desc: 'Beef patty, cheddar, pickles & house sauce.', food_type: 'non_veg', dietary: [], variants: [], modifiers: [{ name: 'Add', type: 'multiple', options: [{ name: 'Bacon', price: '1.50' }, { name: 'Extra patty', price: '2.50' }] }] },
      { name: 'Loaded Fries', cat: 'Sides', price: '5.50', desc: 'Cheese, jalapeños & ranch.', food_type: 'veg', dietary: ['Vegetarian'], variants: [{ name: 'Regular', price: '5.50' }, { name: 'Large', price: '7.00' }], modifiers: [] },
      { name: 'Vanilla Shake', cat: 'Drinks', price: '4.50', desc: 'Hand-spun thick shake.', food_type: 'veg', dietary: ['Vegetarian'], variants: [], modifiers: [] }
    ],
    healthy: [
      { name: 'Quinoa Power Bowl', cat: 'Bowls', price: '10.50', desc: 'Quinoa, roasted veg, chickpeas & tahini.', food_type: 'veg', dietary: ['Vegan', 'Gluten-free'], variants: [], modifiers: [{ name: 'Add protein', type: 'single', options: [{ name: 'Tofu', price: '2.00' }, { name: 'Grilled chicken', price: '3.00' }] }] },
      { name: 'Falafel Wrap', cat: 'Wraps', price: '9.00', desc: 'Falafel, hummus & pickled veg.', food_type: 'veg', dietary: ['Vegan'], variants: [], modifiers: [] },
      { name: 'Green Detox Juice', cat: 'Juices', price: '5.50', desc: 'Spinach, apple, cucumber & ginger.', food_type: 'veg', dietary: ['Vegan', 'Gluten-free'], variants: [], modifiers: [] }
    ]
  };
  function loadSample(){
    var data = SAMPLE_MENUS[state.sample] || SAMPLE_MENUS.cafe;
    state.items = JSON.parse(JSON.stringify(data)).map(function (i) { i.conf = 100; i.warnings = []; return normItem(i); });
    state.jobId = 0;
    state.isSample = true;
    state.parsed = true;
    buildReview();
    refreshPreview();
    menuSub('review');
  }

  /* ---------- config / payments ---------- */
  $('#rp-ob-svc').on('click', 'button', function () {
    $('#rp-ob-svc button').removeClass('on'); $(this).addClass('on');
    var v = $(this).data('svc'); $('#rp-ob-svc-val').val(v);
    $('#rp-ob-pv-svc').text({ delivery_and_pickup: 'Pickup & delivery', pickup: 'Pickup only', delivery: 'Delivery only' }[v]);
  });
  $('#rp-ob-vegmark').on('change', function () { state.vegmark = $(this).is(':checked'); if (state.menuSub === 'review') buildReview(); refreshPreview(); });
  $('.rp-ob-switch').on('click', function () {
    $(this).toggleClass('on');
    var pay = $(this).data('pay'), on = $(this).hasClass('on');
    // Online gateways (paypal, stripe) reveal a status line + track a flag.
    if (pay === 'paypal' || pay === 'stripe') {
      $(this).closest('.rp-ob-pay').find('.rp-ob-paymeta').attr('hidden', !on);
      $('#rp-ob-' + pay + '-enabled').val(on ? '1' : '0');
    }
  });
  // Onboarding only enables/disables gateways; configuration lives on each
  // gateway's settings page reached via the "Configure" link.
  function paymentsData(){
    return {
      cash_gateway: 1,
      paypal_enabled: $('#rp-ob-paypal-enabled').val() || '0',
      stripe_enabled: $('#rp-ob-stripe-enabled').val() || '0'
    };
  }

  /* ---------- go live ---------- */
  $('#rp-ob-testconfirm').on('change', function () {
    state.testOk = $(this).is(':checked');
    $('#rp-ob-testrow .rp-ob-vd').toggleClass('rp-ob-vd-pending', !state.testOk).html(state.testOk ? '✓' : '○');
    syncFooter();
  });
  $('#rp-ob-copy').on('click', function () { var inp = $('.rp-ob-share input')[0]; if (inp) { inp.select(); document.execCommand('copy'); notice('Copied'); } });

  /* ---------- live preview ---------- */
  function refreshPreview(){
    var $b = $('#rp-ob-pv-body');
    if (!state.items.length) { $b.html('<div class="rp-ob-ph-empty"><div>🍽️</div><div>Your menu will appear here as you add it.</div></div>'); return; }
    var byCat = {}; state.items.forEach(function (i) { (byCat[i.cat || 'Menu'] = byCat[i.cat || 'Menu'] || []).push(i); });
    var h = '';
    Object.keys(byCat).forEach(function (cat) {
      h += '<div class="rp-ob-ph-cat">' + esc(cat) + '</div>';
      byCat[cat].forEach(function (i) {
        var dt = (i.dietary && i.dietary.length) ? '<div class="d">' + esc(i.dietary.join(' · ')) + '</div>' : '';
        h += '<div class="rp-ob-ph-it"><span class="t">🍽️</span><div class="i"><b>' + vmark(i) + esc(i.name) + '</b>' + (i.desc ? '<div class="d">' + esc(i.desc) + '</div>' : '') + dt + '</div><span class="p">' + (i.price ? state.cur + esc(i.price) : '-') + '</span></div>';
      });
    });
    $b.html(h);
  }

  /* ---------- init ---------- */
  tick(); setInterval(tick, 15000);
  // Restore saved progress: map server task keys onto rail steps, tick them,
  // and resume at the first incomplete step (or the launched state).
  if (menuImportMode) {
    // Pin to the menu step so onNext()'s menu branch and syncFooter() drive
    // the upload -> review -> publish flow; render() shows the menu pane.
    state.idx = STEPS.indexOf('menu');
    state.menuSub = 'choose';
  } else {
    (function () {
      var MAP = { profile: 'profile', menu: 'menu', review: 'menu', ordering: 'config', hours: 'config', operations: 'config', payments: 'payments', launch: 'golive' };
      String($root.data('completed') || '').split(',').forEach(function (k) {
        if (MAP[k]) { state.completed[MAP[k]] = true; }
      });
      if ($root.hasClass('is-launched')) { state.done = true; return; }
      for (var n = 0; n < STEPS.length; n++) { if (!state.completed[STEPS[n]]) { state.idx = n; break; } }
    })();
  }
  render();

})(jQuery);
