/**
 * Qatar Unseen — "My trip", the want-to-go list.
 *
 * Two separate ideas live on this site and they must never be confused:
 *
 *   the map      — places you have *been to*      (QMap, 'qatar-unseen-v1')
 *   the trip     — places you *want to go to*     (this file, 'qatar-trip-v1')
 *
 * So this owns its own storage key, and reading it can never mark a place as
 * visited or the other way round. A place can be both, or neither.
 *
 * Nothing here talks to the server. The list never leaves the browser — there is
 * no account to attach it to — which is why trip.php renders the rows on the
 * client from a payload of every place, and why the list survives a reload but
 * not a browser wipe.
 *
 * Markup contract, so no page needs its own wiring:
 *   <button data-trip="SLUG" data-trip-name="NAME">…</button>
 *   <span data-trip-count></span>          filled with the number on the trip
 *
 * Public surface: window.QTrip
 */
(function () {
  'use strict';

  var KEY = 'qatar-trip-v1';

  // A cap, not a product decision: past this the list is a data-entry mistake
  // and the JSON-in-localStorage starts to be worth guarding.
  var MAX = 300;

  // Slugs go into URLs and into the hash-less payload, so they are validated on
  // the way in and on the way out — a hand-edited localStorage entry must not be
  // able to become a link to somewhere else.
  var SLUG = /^[a-z0-9-]{1,96}$/;

  var S = window.QTRIP_STRINGS || {};

  /** A translated string, with an English fallback so a gap is never blank. */
  function s(key, fallback) {
    return typeof S[key] === 'string' && S[key] !== '' ? S[key] : fallback;
  }

  /** ':name' style substitution, mirroring t() on the PHP side. */
  function fill(tpl, vars) {
    return String(tpl || '').replace(/:(\w+)/g, function (m, k) {
      return Object.prototype.hasOwnProperty.call(vars, k) ? String(vars[k]) : m;
    });
  }

  // Arabic-Indic digits when the page is Arabic, the same swap PHP's num()
  // makes, so a count does not read as Latin numerals inside an Arabic sentence.
  var DIGITS = '٠١٢٣٤٥٦٧٨٩';
  function num(n) {
    var str = String(n);
    return S.lang === 'ar'
      ? str.replace(/[0-9]/g, function (d) { return DIGITS[+d]; })
      : str;
  }

  // ------------------------------------------------------------- the store --

  var list = read();
  var subs = [];

  function read() {
    var raw = null;
    try { raw = localStorage.getItem(KEY); } catch (e) { return []; }
    if (!raw) { return []; }

    var data;
    try { data = JSON.parse(raw); } catch (e) { return []; }
    if (!Array.isArray(data)) { return []; }

    var out = [];
    data.forEach(function (slug) {
      if (typeof slug === 'string' && SLUG.test(slug) && out.indexOf(slug) === -1) {
        out.push(slug);
      }
    });
    return out.slice(0, MAX);
  }

  function write() {
    try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) {
      // Storage full or disabled (private window, blocked cookies). The list
      // still works for this page view; it just will not survive the reload.
    }
  }

  function changed() {
    write();
    paintCounts();
    paintButtons();
    subs.forEach(function (fn) { try { fn(list.slice()); } catch (e) { console.error(e); } });
    document.dispatchEvent(new CustomEvent('qtrip:change', { detail: list.slice() }));
  }

  // ------------------------------------------------------------------- api --

  var API = {
    all: function () { return list.slice(); },
    has:  function (slug) { return list.indexOf(String(slug)) !== -1; },
    count: function () { return list.length; },

    add: function (slug) {
      slug = String(slug);
      if (!SLUG.test(slug) || list.indexOf(slug) !== -1 || list.length >= MAX) { return false; }
      list.push(slug);
      changed();
      return true;
    },

    remove: function (slug) {
      slug = String(slug);
      var i = list.indexOf(slug);
      if (i === -1) { return false; }
      list.splice(i, 1);
      changed();
      return true;
    },

    toggle: function (slug) {
      return API.has(slug) ? (API.remove(slug), false) : (API.add(slug), true);
    },

    clear: function () {
      if (list.length === 0) { return; }
      list = [];
      changed();
    },

    /** Fires after any change, including one made in another tab. */
    onChange: function (fn) { if (typeof fn === 'function') { subs.push(fn); } },

    /** 95 → "1 h 35 min", 0/null → '' — the same shape as places_duration(). */
    time: function (mins) {
      mins = parseInt(mins, 10);
      if (!mins || mins <= 0) { return ''; }
      if (mins < 60) { return num(mins) + ' ' + s('unit_min', 'min'); }
      var h = Math.floor(mins / 60), m = mins % 60;
      var out = num(h) + ' ' + s('unit_hr', 'h');
      return m > 0 ? out + ' ' + num(m) + ' ' + s('unit_min', 'min') : out;
    },

    strings: s,
    num: num,
    fill: fill,
  };

  window.QTrip = API;

  // ----------------------------------------------------------------- chrome --

  /**
   * The number beside the "My trip" link in the top bar.
   *
   * A count of zero is hidden rather than shown as "0": an empty list is the
   * normal state on a first visit, and a badge that reads zero all the time is
   * noise that stops meaning anything.
   */
  function paintCounts() {
    var n = list.length;
    document.querySelectorAll('[data-trip-count]').forEach(function (el) {
      el.textContent = n > 0 ? num(n) : '';
      el.hidden = n === 0;
    });
    document.querySelectorAll('[data-trip-has]').forEach(function (el) {
      el.hidden = n === 0;
    });
  }

  /** One toggle button, drawn to match the state it is in. */
  function paintButton(btn) {
    var slug = btn.getAttribute('data-trip');
    var name = btn.getAttribute('data-trip-name') || '';
    var on   = API.has(slug);

    btn.setAttribute('aria-pressed', on ? 'true' : 'false');

    var ico = btn.querySelector('.trip-ico');
    if (ico) {
      // Hollow means "not on the list", filled means "on it". The glyph is
      // decorative; aria-pressed above is what a screen reader reads, so the
      // character carries no meaning on its own.
      ico.textContent = on ? '♥' : '♡';
    }

    var txt = btn.querySelector('.trip-txt');
    if (txt) {
      txt.textContent = on ? s('trip_added', 'On your trip') : s('trip_add', 'Want to go');
    } else {
      // Only a button with no visible text needs a label. On the guide's cards
      // the button is a heart and nothing else, so this is the only thing a
      // screen reader has to go on; on the place page the visible word is the
      // label, and an aria-label there would replace it with a different
      // sentence — which is how a button ends up announced as something other
      // than what it says.
      btn.setAttribute('aria-label', on
        ? fill(s('trip_remove_aria', 'Remove :name from my trip'), { name: name })
        : fill(s('trip_add_aria', 'Add :name to my trip'), { name: name }));
    }
  }

  function paintButtons() {
    document.querySelectorAll('[data-trip]').forEach(paintButton);
  }

  function toast(msg) {
    var el = document.querySelector('.toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'toast';
      el.setAttribute('role', 'status');
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.remove('err');
    el.classList.add('show');
    clearTimeout(toast.timer);
    toast.timer = setTimeout(function () { el.classList.remove('show'); }, 2600);
  }

  // Delegated, so a card rendered later by places.js or the trip page works
  // without being wired up again.
  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-trip]') : null;
    if (!btn) { return; }

    // The card button sits next to a link, not inside one, but the guard costs
    // nothing and a future nesting mistake would otherwise navigate away.
    ev.preventDefault();

    var slug = btn.getAttribute('data-trip');
    var name = btn.getAttribute('data-trip-name') || '';
    if (!SLUG.test(slug)) { return; }

    var on = API.toggle(slug);
    toast(on
      ? fill(s('trip_add_one', ':name is on your trip.'), { name: name })
      : fill(s('trip_remove_one', ':name came off your trip.'), { name: name }));
    btn.focus({ preventScroll: true });
  });

  // Another tab changing the list should show up here too — the count in the
  // top bar disagreeing with the trip page is the kind of bug nobody reports.
  window.addEventListener('storage', function (ev) {
    if (ev.key !== null && ev.key !== KEY) { return; }
    list = read();
    paintCounts();
    paintButtons();
    subs.forEach(function (fn) { try { fn(list.slice()); } catch (e) { console.error(e); } });
  });

  paintCounts();
  paintButtons();
})();
