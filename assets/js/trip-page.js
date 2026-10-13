/**
 * Qatar Unseen — the trip page renderer.
 *
 * Loaded only by trip.php. The shared wishlist logic (the store, the toggle
 * buttons, the nav badge) is in trip.js, which every page loads; this file is
 * only the part that draws the list, so no other page pays for it.
 *
 * Everything here is built with createElement and textContent rather than with
 * innerHTML. Place names come from the database and can be edited by anybody
 * with a panel account, so they are treated as data on the way in — the payload
 * is JSON, and nothing in it is ever handed to the HTML parser.
 *
 * The order of the list is the order the places were added, because that is the
 * order the visitor chose and the only one the site can honestly claim to know.
 */
(function () {
  'use strict';

  var D = window.QTRIP_DATA;
  var T = window.QTrip;

  if (!D || !T || !document.querySelector('[data-trip-page]')) { return; }

  var S = D.strings || {};
  var PLACES = D.p || {};

  function s(key, fallback) {
    return typeof S[key] === 'string' && S[key] !== '' ? S[key] : fallback;
  }

  /** ':n' substitution, matching t() on the PHP side. */
  function fill(tpl, vars) {
    return String(tpl || '').replace(/:(\w+)/g, function (m, k) {
      return Object.prototype.hasOwnProperty.call(vars, k) ? String(vars[k]) : m;
    });
  }

  var el = function (tag, cls) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    return n;
  };
  var txt = function (tag, cls, text) {
    var n = el(tag, cls);
    n.textContent = text;
    return n;
  };

  // ------------------------------------------------------------ the pieces --

  /** The name and municipality in the current language, English as the fallback. */
  function name(row)      { return (row.n && (row.n[D.lang] || row.n.en)) || ''; }
  function municipality(row) { return (row.m && (row.m[D.lang] || row.m.en)) || ''; }

  /** One row of the list. */
  function row(place, slug, position, mins) {
    var li = el('li', 'trip-row');
    li.setAttribute('data-slug', slug);

    li.appendChild(txt('span', 'trip-n', T.num(position)));

    var ico = txt('span', 'trip-ico', place.i || '📍');
    ico.setAttribute('aria-hidden', 'true');
    if (place.c) { ico.style.setProperty('--c', place.c); }
    li.appendChild(ico);

    var main = el('span', 'trip-main');
    var link = txt('a', 'trip-name', name(place));
    link.href = D.base + '/place.php?slug=' + encodeURIComponent(slug);
    main.appendChild(link);

    var where = [municipality(place)];
    var time = T.time(mins);
    if (time) { where.push(time); }
    main.appendChild(txt('span', 'trip-where', where.join(' · ')));
    li.appendChild(main);

    var act = el('span', 'trip-row-act');

    var open = txt('a', 'btn sm ghost', s('open', 'Open'));
    open.href = link.href;
    act.appendChild(open);

    // The same [data-trip] contract the guide uses, so trip.js's delegated
    // handler does the removal and this page only has to re-render afterwards.
    var remove = txt('button', 'btn sm ghost danger', '✕');
    remove.type = 'button';
    remove.setAttribute('data-trip', slug);
    remove.setAttribute('data-trip-name', name(place));
    act.appendChild(remove);

    li.appendChild(act);

    return li;
  }

  /** A trip entry whose place is no longer published, or was never in the index. */
  function goneRow(slug) {
    var li = el('li', 'trip-gone-row');
    li.setAttribute('data-slug', slug);

    // Only the slug is known — the place is not in the index, so there is no
    // name to show. A slug reads as words once the dashes are spaces, which is
    // enough for somebody to recognise what they had put on their list.
    var label = slug.replace(/-/g, ' ');

    li.appendChild(txt('span', 'trip-name', label));
    li.appendChild(txt('span', 'trip-where', s('gone', 'No longer in the guide')));

    var remove = txt('button', 'btn sm ghost danger', s('remove', 'Remove'));
    remove.type = 'button';
    remove.setAttribute('data-trip', slug);
    // Without this the button's screen-reader label would name nothing.
    remove.setAttribute('data-trip-name', label);
    li.appendChild(remove);

    return li;
  }

  // ---------------------------------------------------------------- render --

  var listEl   = document.querySelector('[data-trip-list]');
  var emptyEl  = document.querySelector('[data-trip-empty]');
  var sumEl    = document.querySelector('[data-trip-summary]');
  var loadEl   = document.querySelector('[data-trip-loading]');
  var gonePan  = document.querySelector('[data-trip-gone-panel]');
  var goneNote = document.querySelector('[data-trip-gone-note]');
  var goneList = document.querySelector('[data-trip-gone-list]');
  var countEl  = document.querySelector('[data-trip-total-count]');
  var labelEl  = document.querySelector('[data-trip-total-label]');
  var timeEl   = document.querySelector('[data-trip-total-time]');
  var capEl    = document.querySelector('[data-trip-total-caption]');
  var unkEl    = document.querySelector('[data-trip-unknown]');
  var mapLink  = document.querySelector('[data-trip-map]');

  /**
   * Add up the visit times.
   *
   * Only places that are still in the index count, and only the ones that have a
   * recorded duration — which is why the result says how many were left out
   * rather than silently treating an unknown as zero. A trip total that reads
   * "4 hours" when half the places have no time recorded is a number somebody
   * would plan a day around.
   */
  function totalTime(found) {
    var mins = 0, unknown = 0;
    found.forEach(function (place) {
      if (place.d) { mins += place.d; } else { unknown++; }
    });
    return { mins: mins, unknown: unknown };
  }

  function render() {
    var all = T.all();

    var found = [], gone = [];
    all.forEach(function (slug) {
      var place = PLACES[slug];
      if (place) { found.push({ slug: slug, place: place }); } else { gone.push(slug); }
    });

    // --- the list ---
    if (listEl) {
      listEl.textContent = '';
      found.forEach(function (item, i) {
        listEl.appendChild(row(item.place, item.slug, i + 1, item.place.d));
      });
      listEl.hidden = found.length === 0;
    }

    // --- the empty state ---
    // Only when there is genuinely nothing, not when everything on the trip has
    // been unpublished: that case has its own panel below and saying "nothing
    // here" on top of it would be two answers to one question.
    if (emptyEl) { emptyEl.hidden = all.length !== 0; }

    // --- the totals ---
    var sum  = totalTime(found.map(function (i) { return i.place; }));
    // T.time() is the same formatter the individual rows use, so the total and
    // the parts can never disagree about what "2 h 30 min" looks like.
    var time = T.time(sum.mins);

    if (countEl) { countEl.textContent = T.num(found.length); }
    if (labelEl) {
      labelEl.textContent = found.length === 1
        ? s('count_one', '1 place')
        : fill(s('count', ':n places'), { n: T.num(found.length) });
    }
    if (timeEl) { timeEl.textContent = time !== '' ? time : '—'; }

    // With nothing to add up, the caption under the figure changes rather than
    // the figure alone going blank: a dash under "Time at these places" is a
    // label describing a number that is not there.
    var nothing = sum.mins === 0 && found.length > 0;
    if (capEl) {
      capEl.textContent = nothing
        ? s('total_none', 'No times recorded for these')
        : s('total', 'Time at these places');
    }

    // The unrecorded count is worth saying when there is a real total above it —
    // "2 h 30 min, and three of these have no time recorded". When every single
    // one is unrecorded the caption already says so, and the count is in the
    // figure next to it, so the line would only repeat both.
    if (unkEl) {
      unkEl.hidden = sum.unknown === 0 || nothing;
      unkEl.textContent = (sum.unknown > 0 && !nothing)
        ? fill(s('unknown', ':n with no time recorded'), { n: T.num(sum.unknown) })
        : '';
    }

    if (sumEl) { sumEl.hidden = all.length === 0; }

    // "That is time spent at the places themselves" is only a true sentence when
    // there is a total above it to refer to. With every duration missing, the
    // figure reads "—" and the note would be explaining a number nobody can see.
    var travel = document.querySelector('[data-trip-travel-note]');
    if (travel) { travel.hidden = sum.mins === 0; }

    // --- places that are no longer in the guide ---
    if (goneList) {
      goneList.textContent = '';
      gone.forEach(function (slug) { goneList.appendChild(goneRow(slug)); });
    }
    if (gonePan) { gonePan.hidden = gone.length === 0; }
    if (goneNote && gone.length > 0) {
      goneNote.textContent = fill(s('gone_note', ':n place(s) on your trip are not in the guide any more.'),
        { n: T.num(gone.length) });
    }

    // --- the funnel back into the map ---
    // The map marks localities, not places, so this is the set of localities the
    // trip touches — deduplicated, because several places usually share one.
    if (mapLink) {
      var locs = [], seen = {};
      found.forEach(function (item) {
        var loc = item.place.l;
        if (loc && !seen[loc]) { seen[loc] = 1; locs.push(loc); }
      });
      mapLink.href = locs.length > 0
        ? D.base + '/index.php?mark=' + locs.map(encodeURIComponent).join(',')
        : D.base + '/index.php';
    }

    if (loadEl) { loadEl.hidden = true; }
  }

  // ----------------------------------------------------------------- copy --

  /**
   * The list as plain text, for pasting into a message.
   *
   * Written for the person receiving it rather than as a dump of the page: a
   * heading that says how many, one numbered line per place, and the total at
   * the end. Two things it refuses to hide — places whose visit time nobody
   * recorded, and places that are no longer in the guide — because a message
   * that left either out would be describing a different trip from the one on
   * screen, and the person reading it is the one who would be misled.
   */
  function asText() {
    var all = T.all();
    var found = [], missing = 0;
    all.forEach(function (slug) {
      if (PLACES[slug]) { found.push(PLACES[slug]); } else { missing++; }
    });

    var sum = totalTime(found);
    var lines = [];

    lines.push(fill(s('copy_head', 'My Qatar trip — :n places'), { n: T.num(found.length) }));
    lines.push('');

    found.forEach(function (place, i) {
      var bits = [municipality(place)];
      var time = T.time(place.d);
      if (time) { bits.push(time); }
      lines.push(T.num(i + 1) + '. ' + name(place) + ' — ' + bits.join(' · '));
    });

    if (sum.unknown > 0) {
      lines.push('');
      lines.push(fill(s('unknown', ':n with no time recorded'), { n: T.num(sum.unknown) }));
    }

    if (missing > 0) {
      lines.push(fill(s('copy_gone', ':n no longer in the guide'), { n: T.num(missing) }));
    }

    var total = T.time(sum.mins);
    if (total !== '') {
      lines.push('');
      lines.push(fill(s('copy_total', 'About :time at the places themselves.'), { time: total }));
    }

    return lines.join('\n');
  }

  function copy(btn) {
    var text = asText();
    var was = btn.textContent;

    function done() {
      btn.textContent = s('copied', 'Copied');
      setTimeout(function () { btn.textContent = was; }, 1800);
    }
    function failed() {
      btn.textContent = s('copy_failed', 'Could not copy — select the text by hand.');
      setTimeout(function () { btn.textContent = was; }, 3200);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, function () {
        // The clipboard API needs a secure context. On plain http (a LAN address
        // rather than localhost) it is simply absent, so fall back to the old
        // selection trick before giving up.
        if (legacyCopy(text)) { done(); } else { failed(); }
      });
    } else if (legacyCopy(text)) {
      done();
    } else {
      failed();
    }
  }

  function legacyCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', 'readonly');
    ta.style.position = 'fixed';
    ta.style.top = '-1000px';
    document.body.appendChild(ta);
    ta.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(ta);
    return ok;
  }

  // ---------------------------------------------------------------- wiring --

  var copyBtn = document.querySelector('[data-trip-copy]');
  if (copyBtn) { copyBtn.addEventListener('click', function () { copy(copyBtn); }); }

  var clearBtn = document.querySelector('[data-trip-clear]');
  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      if (window.confirm(s('clear_confirm', 'Take every place off your trip?'))) {
        T.clear();
      }
    });
  }

  // Any change re-renders, including one made in another tab — trip.js forwards
  // that case — so the page never shows a list the store no longer agrees with.
  T.onChange(render);
  render();
})();
