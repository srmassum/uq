/**
 * Qatar Unseen — "add the whole plan to my trip".
 *
 * The one piece of the itinerary page that needs JavaScript, and it is small
 * because trip.js already owns the list: this only presses its add() button once
 * per stop and then reports what happened.
 *
 * It is a separate file rather than part of trip.js because trip.js is emitted
 * on every page, and this markup exists on one. A script that runs everywhere to
 * serve a button that exists in one place is the kind of thing that quietly
 * becomes load-bearing.
 *
 * The button ships hidden and is only revealed once this file has run and found
 * a trip list to add to. Without scripting — or in the impossible case where
 * trip.js failed to load — there is nothing to reveal, so nothing is shown. A
 * button that does nothing is worse than no button.
 *
 * Public surface: none. It reads the markup and drives window.QTrip.
 */
(function () {
  'use strict';

  var S = window.QPLAN_STRINGS || {};

  function s(key, fallback) {
    return typeof S[key] === 'string' && S[key] !== '' ? S[key] : fallback;
  }

  /** ':name' style substitution, mirroring t() on the PHP side. */
  function fill(tpl, vars) {
    return String(tpl || '').replace(/:(\w+)/g, function (m, k) {
      return Object.prototype.hasOwnProperty.call(vars, k) ? String(vars[k]) : m;
    });
  }

  var btn = document.querySelector('[data-plan-add]');
  if (!btn) { return; }

  // Nothing to add to. Leaving the button hidden is the whole fallback.
  if (!window.QTrip || typeof window.QTrip.add !== 'function') { return; }

  var T = window.QTrip;
  var status = document.querySelector('[data-plan-status]');
  var ico = btn.querySelector('.trip-ico');
  var txt = btn.querySelector('.trip-txt');

  // Slugs are validated again inside QTrip.add(), which is what actually decides
  // whether anything is stored — so a hand-edited attribute can only ever name
  // slugs that were going to be allowed anyway.
  var slugs = (btn.getAttribute('data-plan-slugs') || '')
    .split(',')
    .map(function (x) { return x.trim(); })
    .filter(function (x) { return x !== ''; });

  if (slugs.length === 0) { return; }

  /** How many of the plan's stops are on the list right now. */
  function have() {
    var n = 0;
    slugs.forEach(function (slug) { if (T.has(slug)) { n++; } });
    return n;
  }

  function paint() {
    var on  = have();
    var all = on === slugs.length;

    // Filled heart and "the whole plan is on your trip" once every stop is in,
    // so the button's own state answers the question without a second look.
    if (ico) { ico.textContent = all ? '♥' : '♡'; }
    if (txt) { txt.textContent = all ? s('added_all', 'The whole plan is on your trip') : s('add_all', 'Add the whole plan to my trip'); }
    btn.setAttribute('aria-pressed', all ? 'true' : 'false');
  }

  btn.hidden = false;
  btn.addEventListener('click', function () {
    var added = 0;

    // add() returns false for a slug already on the list and for one that is not
    // a valid slug, so this counts only what actually changed — which is what
    // makes "3 stops went onto your trip" true when two were already there.
    slugs.forEach(function (slug) { if (T.add(slug)) { added++; } });

    if (status) {
      status.hidden = false;
      status.textContent = added > 0
        ? fill(s('added_n', ':n stops went onto your trip.'), { n: T.num(added) })
        : s('already', 'Every stop is already on your trip.');
    }

    paint();
  });

  // The list can change from the toolbar's own buttons, from another tab, or
  // from a stop's heart on this page, so the button follows the list rather than
  // assuming it is the only thing writing to it.
  if (typeof T.onChange === 'function') { T.onChange(paint); }

  paint();
})();
