/**
 * RestroPress allergen filter (3.5).
 *
 * "Hide items containing" chips next to the menu search. Each card's badge
 * block carries data-allergens / data-allergen-traces (slugs); this copies
 * them onto the .rpress_fooditem card and toggles .rp-allergen-hidden.
 * It uses its own class, so it stacks with the live search (rp-search-hidden)
 * without touching it. Category titles with nothing left are hidden too.
 */
(function () {
  'use strict';

  var STORE_KEY = 'rpressAllergenFilter';
  var HIDDEN = 'rp-allergen-hidden';
  var i18n = window.rpressAllergens || {};

  function tokens(value) {
    return String(value || '').split(/\s+/).filter(Boolean);
  }

  function readStore() {
    try {
      var raw = window.localStorage.getItem(STORE_KEY);
      var data = raw ? JSON.parse(raw) : null;
      if (data && Array.isArray(data.slugs)) {
        return { slugs: data.slugs, traces: !!data.traces };
      }
    } catch (e) { /* storage unavailable */ }
    return { slugs: [], traces: false };
  }

  function writeStore(state) {
    try {
      window.localStorage.setItem(STORE_KEY, JSON.stringify(state));
    } catch (e) { /* storage unavailable */ }
  }

  // Copy the slugs from the badge block onto the card itself.
  function tagCards() {
    var blocks = document.querySelectorAll('.rp-allergens--card[data-allergens]');
    Array.prototype.forEach.call(blocks, function (block) {
      var card = block.closest('.rpress_fooditem');
      if (!card) return;
      card.setAttribute('data-allergens', block.getAttribute('data-allergens') || '');
      card.setAttribute('data-allergen-traces', block.getAttribute('data-allergen-traces') || '');
    });
  }

  function init(root) {
    var chips = root.querySelectorAll('.rp-allergen-chip');
    var tracesBox = root.querySelector('[data-rp-allergen-traces]');
    var countEl = root.querySelector('[data-rp-allergen-count]');
    var statusEl = root.querySelector('[data-rp-allergen-status]');
    var clearBtn = root.querySelector('[data-rp-allergen-clear]');
    var available = {};
    Array.prototype.forEach.call(chips, function (chip) {
      available[chip.getAttribute('data-allergen')] = true;
    });

    var state = readStore();
    state.slugs = state.slugs.filter(function (s) { return available[s]; });

    function sync() {
      Array.prototype.forEach.call(chips, function (chip) {
        var on = state.slugs.indexOf(chip.getAttribute('data-allergen')) !== -1;
        chip.setAttribute('aria-pressed', on ? 'true' : 'false');
        chip.classList.toggle('is-active', on);
      });
      if (tracesBox) tracesBox.checked = state.traces;
      if (countEl) {
        var count = state.slugs.length ? String(state.slugs.length) : '';
        if (countEl.textContent !== count) countEl.textContent = count;
        countEl.hidden = !state.slugs.length;
      }
    }

    function apply() {
      tagCards();
      var hiddenCount = 0;
      var cards = document.querySelectorAll('.rpress_fooditem');
      Array.prototype.forEach.call(cards, function (card) {
        var hide = false;
        if (state.slugs.length) {
          var list = tokens(card.getAttribute('data-allergens'));
          if (state.traces) list = list.concat(tokens(card.getAttribute('data-allergen-traces')));
          hide = list.some(function (s) { return state.slugs.indexOf(s) !== -1; });
        }
        card.classList.toggle(HIDDEN, hide);
        if (hide) hiddenCount++;
      });

      // Hide category headings (list) and grid wrappers left with no items.
      var titles = document.querySelectorAll('.rpress-element-title[data-term-id]');
      Array.prototype.forEach.call(titles, function (title) {
        var id = title.getAttribute('data-term-id');
        var items = document.querySelectorAll('.rpress_fooditem[data-term-id="' + id + '"]');
        var empty = items.length > 0 && Array.prototype.every.call(items, function (it) { return it.classList.contains(HIDDEN); });
        title.classList.toggle(HIDDEN, empty);
      });
      var wraps = document.querySelectorAll('.rpress-grid-main-wrap');
      Array.prototype.forEach.call(wraps, function (wrap) {
        var items = wrap.querySelectorAll('.rpress_fooditem');
        var empty = items.length > 0 && Array.prototype.every.call(items, function (it) { return it.classList.contains(HIDDEN); });
        wrap.classList.toggle(HIDDEN, empty);
      });

      if (statusEl) {
        var tpl = hiddenCount === 1 ? (i18n.hiddenOne || '%d item hidden') : (i18n.hiddenMany || '%d items hidden');
        var text = state.slugs.length ? tpl.replace('%d', hiddenCount) : '';
        if (statusEl.textContent !== text) statusEl.textContent = text;
      }
    }

    function update() {
      writeStore(state);
      sync();
      apply();
    }

    Array.prototype.forEach.call(chips, function (chip) {
      chip.addEventListener('click', function () {
        var slug = chip.getAttribute('data-allergen');
        var at = state.slugs.indexOf(slug);
        if (at === -1) state.slugs.push(slug); else state.slugs.splice(at, 1);
        update();
      });
    });
    if (tracesBox) {
      tracesBox.addEventListener('change', function () {
        state.traces = tracesBox.checked;
        update();
      });
    }
    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        state.slugs = [];
        update();
      });
    }

    // Close the panel on Escape or a click outside it.
    var details = root.querySelector('details');
    if (details) {
      document.addEventListener('click', function (e) {
        if (details.open && !root.contains(e.target)) details.open = false;
      });
      // The panel opens towards the start edge of the button; flip it when
      // that would run off the screen (button near the start edge).
      details.addEventListener('toggle', function () {
        var panel = details.querySelector('.rp-allergen-filter__panel');
        if (!panel) return;
        panel.classList.remove('is-flipped');
        if (!details.open || window.getComputedStyle(panel).position !== 'absolute') return;
        var r = panel.getBoundingClientRect();
        if (r.left < 8 || r.right > window.innerWidth - 8) panel.classList.add('is-flipped');
      });
      root.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && details.open) {
          details.open = false;
          var summary = details.querySelector('summary');
          if (summary) summary.focus();
        }
      });
    }

    // Menus can re-render items (category loads, time-based menus): re-apply.
    if (window.MutationObserver) {
      var pending = null;
      var observer = new MutationObserver(function (mutations) {
        // Ignore changes inside the filter itself (status text, count).
        var relevant = mutations.some(function (m) {
          return m.addedNodes && m.addedNodes.length && !root.contains(m.target);
        });
        if (!relevant || pending) return;
        pending = window.setTimeout(function () { pending = null; apply(); }, 50);
      });
      var lists = document.querySelectorAll('.rpress_fooditems_list, .rpress_fooditems_grid, .rpress-section');
      Array.prototype.forEach.call(lists, function (list) {
        observer.observe(list, { childList: true, subtree: true });
      });
    }

    sync();
    apply();
  }

  function boot() {
    var roots = document.querySelectorAll('[data-rp-allergen-filter]');
    if (!roots.length) return;
    // One filter per page drives the menu; extra copies stay inert.
    init(roots[0]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
