/**
 * Places guide — "Near me", and making the filter bar behave.
 *
 * The cards already carry their own coordinates, so nearest-first sorting needs
 * no server round trip: the browser asks for a position, measures, and reorders
 * what is on screen. Nothing is stored and nothing is sent anywhere.
 *
 * The button is hidden in the markup and revealed here, because a button that
 * silently does nothing is worse than no button.
 */
(function () {
  'use strict';

  const P     = window.QPLACES;
  const grid  = document.getElementById('place-grid');
  const btn   = document.querySelector('[data-near]');
  const note  = document.getElementById('near-note');
  const form  = document.getElementById('filters-form');

  if (!P || !grid || !btn || !form) return;

  const S = P.strings || {};
  const RTL_DIGITS = (document.body && document.body.getAttribute('data-lang')) === 'ar';

  // ------------------------------------------------------------- the form --

  // Selects submit on change; the search box waits for Enter or the button,
  // because submitting on every keystroke would reload the page mid-word.
  form.querySelectorAll('select').forEach(function (sel) {
    sel.addEventListener('change', function () { form.submit(); });
  });

  // -------------------------------------------------------------- near me --

  if (!navigator.geolocation) {
    // geo.unsupported rather than silence — the visitor should know why.
    btn.hidden = true;
    return;
  }
  btn.hidden = false;

  function digits(str) {
    if (!RTL_DIGITS) return str;
    return String(str).replace(/[0-9]/g, function (d) {
      return '٠١٢٣٤٥٦٧٨٩'[Number(d)];
    });
  }

  /** Great-circle distance in km. Good enough for ordering, which is all it is for. */
  function haversine(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const rad = Math.PI / 180;
    const dLat = (lat2 - lat1) * rad;
    const dLon = (lon2 - lon1) * rad;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(lat1 * rad) * Math.cos(lat2 * rad) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    return 2 * R * Math.asin(Math.min(1, Math.sqrt(a)));
  }

  function say(msg) {
    if (!note) return;
    note.hidden = !msg;
    note.textContent = msg || '';
  }

  function onOk(pos) {
    const meLat = pos.coords.latitude;
    const meLng = pos.coords.longitude;

    const cards = Array.prototype.slice.call(grid.querySelectorAll('.place-card'));

    cards.forEach(function (card) {
      const lat = parseFloat(card.getAttribute('data-lat'));
      const lng = parseFloat(card.getAttribute('data-lng'));
      if (!isFinite(lat) || !isFinite(lng)) {
        card.setAttribute('data-km', '99999');
        return;
      }
      const km = haversine(meLat, meLng, lat, lng);
      card.setAttribute('data-km', km.toFixed(3));

      const meta = card.querySelector('.meta');
      if (!meta) return;

      let pill = meta.querySelector('[data-dist]');
      if (!pill) {
        pill = document.createElement('span');
        pill.className = 'pill dist';
        pill.setAttribute('data-dist', '');
        meta.appendChild(pill);
      }
      const shown = km < 10 ? km.toFixed(1) : String(Math.round(km));
      pill.textContent = String(S.distance || ':km km').replace(':km', digits(shown));
    });

    cards.sort(function (a, b) {
      return parseFloat(a.getAttribute('data-km')) - parseFloat(b.getAttribute('data-km'));
    });

    // Reorder rather than rebuild: the nodes keep their listeners and the
    // browser keeps whatever it has already painted of them.
    const frag = document.createDocumentFragment();
    cards.forEach(function (c) { frag.appendChild(c); });
    grid.appendChild(frag);

    say(S.done || '');
    btn.disabled = false;
    btn.textContent = btn.getAttribute('data-label') || '';
  }

  function onErr(err) {
    const code = err && err.code;
    const msg  = code === 1 ? (S.denied || '')
               : code === 3 ? (S.unavailable || '')
               : (S.unavailable || '');
    say(msg);
    btn.disabled = false;
  }

  btn.setAttribute('data-label', btn.textContent.trim());

  btn.addEventListener('click', function () {
    btn.disabled = true;

    const was = btn.getAttribute('data-label');
    btn.textContent = S.locating || was;
    say(S.locating || '');

    navigator.geolocation.getCurrentPosition(onOk, onErr, {
      enableHighAccuracy: false,   // a city block of error changes nothing here
      timeout: 10000,
      maximumAge: 300000,          // a five-minute-old fix is fine for "nearest"
    });
  });
})();
