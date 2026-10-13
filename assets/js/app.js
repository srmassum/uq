/**
 * Qatar Unseen — page wiring.
 *
 * Everything here is glue: it connects the modules (QMap for the selection,
 * QGamify for badges and levels, QCard for the exported image) to the DOM that
 * index.php rendered. No state lives in this file, so a re-render is always
 * driven by a change event rather than by someone remembering to call a
 * function in the right order.
 */
(function () {
  'use strict';

  const P = window.QATAR;
  if (!P || !window.QMap || !window.QGamify) {
    console.error('Qatar Unseen: payload or modules missing.');
    return;
  }

  const UI   = P.ui;
  const META = P.meta;
  const map  = window.QMap;
  const $    = function (sel) { return document.querySelector(sel); };

  // ------------------------------------------------------------- helpers --

  /** Translated column for the current language, falling back to English. */
  function pick(row, base) {
    if (!row) return '';
    return row[base + '_' + META.lang] || row[base + '_en'] || '';
  }

  function fill(tpl, vars) {
    return String(tpl || '').replace(/:(\w+)/g, function (m, k) {
      return Object.prototype.hasOwnProperty.call(vars, k) ? String(vars[k]) : m;
    });
  }

  let toastTimer = null;
  function toast(msg, isError) {
    let el = $('.toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'toast';
      el.setAttribute('role', 'status');
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.toggle('err', !!isError);
    el.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.classList.remove('show'); }, 3200);
  }

  // -------------------------------------------------------------- modules --

  map.init({
    geo:        P.geo,
    localities: P.localities,
    themes:     P.themes,
    lang:       META.lang,
    mount:      'map-frame',
  });

  window.QGamify.init(P.game).bind(map);

  window.QCard.init({
    rtl:  !!META.rtl,
    lang: META.lang,
    strings: {
      brand:         UI.brand,
      card_pct:      UI.cardPct,
      card_credit:   UI.cardCredit,
      anon_name:     UI.anonName,
    },
  });

  // ------------------------------------------------------------- progress --

  const bar = $('[data-bar]');
  const summary = $('[data-summary]');
  const munPill = $('[data-mun-pill]');
  const pctEl = $('[data-pct]');

  function updateProgress() {
    const c = map.counts();

    document.querySelectorAll('[data-count]').forEach(function (el) {
      el.textContent = String(c.places);
    });

    if (bar) {
      bar.style.inlineSize = (c.total ? (c.places / c.total) * 100 : 0).toFixed(1) + '%';
    }
    if (pctEl) {
      pctEl.textContent = (c.total ? Math.round((c.places / c.total) * 100) : 0) + '%';
    }
    if (munPill) {
      munPill.textContent = c.regions + ' / ' + c.regionsTotal;
    }

    if (summary) {
      let line;
      if (c.places === 0)      line = UI.empty;
      else if (c.places >= c.total) line = fill(UI.all, { total: c.total });
      else {
        line = fill(UI.progress, { done: c.places, total: c.total });
        if (c.regions > 0) {
          line += ' · ' + fill(UI.muns, { done: c.regions, total: c.regionsTotal });
        }
      }
      summary.textContent = line;
    }
  }

  // --------------------------------------------------------------- search --

  const search = $('#map-search');
  const noResults = $('[data-no-results]');

  if (search) {
    search.addEventListener('input', function () {
      const q = search.value.trim();
      const shown = map.filter(q);

      if (noResults) {
        noResults.hidden = !(q !== '' && shown === 0);
        if (!noResults.hidden) noResults.textContent = fill(UI.noMatch, { q: q });
      }
    });
  }

  // ----------------------------------------------------------- map tools --

  document.querySelectorAll('[data-act]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const act = btn.getAttribute('data-act');
      if (act === 'select-all') map.selectAll();
      else if (act === 'clear-all') map.clearAll();
      else if (act === 'reset-view') map.resetView();
    });
  });

  // ---------------------------------------------------------- share panel --

  const canvas   = $('#card-preview');
  const nameIn   = $('#card-name');
  const photoIn  = $('#photo-file');
  const avatar   = $('#avatar');
  const photoClr = $('#photo-clear');
  const linkBox  = $('#link-box');
  const linkUrl  = $('#link-url');
  const linkBtn  = $('#make-link');

  let sizeKey = 'post';
  let photoImg = null;
  let linkBusy = false;

  /** Level and personality make the card worth reading back. */
  function subtitle() {
    const r = window.QGamify.evaluate(map);
    const bits = [];
    if (r.level) bits.push(pick(r.level, 'name'));
    if (r.personality) bits.push(pick(r.personality, 'name'));
    return bits.join(' · ');
  }

  function cardOpts() {
    return {
      theme:    map.theme(),
      name:     map.displayName() || '',
      photo:    photoImg,
      subtitle: subtitle(),
    };
  }

  let rafId = null;
  function renderPreview() {
    if (!canvas || !window.QCard) return;
    if (rafId) cancelAnimationFrame(rafId);
    rafId = requestAnimationFrame(function () {
      rafId = null;
      // Fonts first: drawing before they land bakes a fallback into the pixels.
      window.QCard.ensureFonts().then(function () {
        window.QCard.draw(canvas, sizeKey, map, cardOpts());
      });
    });
  }

  // --- themes ---
  document.querySelectorAll('#theme-chips [data-theme]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      map.setTheme(chip.getAttribute('data-theme'));
    });
  });

  function syncThemeChips() {
    document.querySelectorAll('#theme-chips [data-theme]').forEach(function (chip) {
      chip.setAttribute('aria-pressed',
        chip.getAttribute('data-theme') === map.state.theme ? 'true' : 'false');
    });
  }

  // --- sizes ---
  document.querySelectorAll('#size-chips [data-size]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      sizeKey = chip.getAttribute('data-size');
      document.querySelectorAll('#size-chips [data-size]').forEach(function (c) {
        c.setAttribute('aria-pressed', c === chip ? 'true' : 'false');
      });
      renderPreview();
    });
  });

  // --- name ---
  if (nameIn) {
    nameIn.addEventListener('input', function () { map.setName(nameIn.value); });
  }

  // --- photo ---
  if (photoIn) {
    photoIn.addEventListener('change', function () {
      const file = photoIn.files && photoIn.files[0];
      photoIn.value = '';   // so picking the same file twice still fires
      if (file) readPhoto(file);
    });
  }
  if ($('#photo-pick')) {
    $('#photo-pick').addEventListener('click', function () { photoIn && photoIn.click(); });
  }
  if (photoClr) {
    photoClr.addEventListener('click', function () { applyPhoto(null); });
  }

  /**
   * Shrunk to 512px before it is stored. The selection lives in localStorage
   * and ships to the server inside a POST, so a 4 MB phone photo would blow
   * the storage quota and the request limit at the same time.
   */
  function readPhoto(file) {
    if (!/^image\//.test(file.type)) { toast(UI.error, true); return; }

    const url = URL.createObjectURL(file);
    const img = new Image();

    img.onload = function () {
      const max = 512;
      const r = Math.min(1, max / Math.max(img.width, img.height));
      const c = document.createElement('canvas');
      c.width  = Math.max(1, Math.round(img.width * r));
      c.height = Math.max(1, Math.round(img.height * r));
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      URL.revokeObjectURL(url);
      applyPhoto(c.toDataURL('image/jpeg', 0.86));
    };
    img.onerror = function () {
      URL.revokeObjectURL(url);
      toast(UI.error, true);
    };
    img.src = url;
  }

  function applyPhoto(dataUrl) {
    if (!dataUrl) {
      photoImg = null;
      map.setPhoto(null);
      if (avatar) { avatar.textContent = '＋'; avatar.innerHTML = '＋'; }
      if (photoClr) photoClr.hidden = true;
      return;
    }

    const img = new Image();
    img.onload = function () {
      photoImg = img;
      map.setPhoto(dataUrl);
      if (avatar) avatar.innerHTML = '';
      if (avatar) avatar.appendChild(img);
      if (photoClr) photoClr.hidden = false;
    };
    img.src = dataUrl;
  }

  // --- downloads ---
  const pngBtn = $('#dl-png');
  if (pngBtn) {
    pngBtn.addEventListener('click', function () {
      pngBtn.disabled = true;
      window.QCard.ensureFonts()
        .then(function () { return window.QCard.download(sizeKey, map, cardOpts()); })
        .catch(function () { toast(UI.error, true); })
        .finally(function () { pngBtn.disabled = false; });
    });
  }

  const pdfBtn = $('#dl-pdf');
  if (pdfBtn) {
    pdfBtn.addEventListener('click', function () {
      pdfBtn.disabled = true;
      const was = pdfBtn.textContent;
      pdfBtn.textContent = UI.working;
      window.QCard.ensureFonts()
        .then(function () { return window.QCard.downloadPdf(sizeKey, map, cardOpts()); })
        .catch(function () { toast(UI.error, true); })
        .finally(function () { pdfBtn.disabled = false; pdfBtn.textContent = was; });
    });
  }

  // --- share link ---
  if (linkBtn) {
    linkBtn.addEventListener('click', function () {
      if (linkBusy) return;
      linkBusy = true;
      linkBtn.disabled = true;
      linkBtn.textContent = UI.creating;

      window.QCard.ensureFonts()
        .then(function () { return window.QCard.toBlob('post', map, cardOpts()); })
        .then(function (blob) {
          const r = window.QGamify.evaluate(map);
          const fd = new FormData();
          fd.append('csrf',    META.csrf);
          fd.append('places',  JSON.stringify(map.marked()));
          fd.append('regions', JSON.stringify(map.markedRegions()));
          fd.append('name',    map.displayName() || '');
          fd.append('theme',   map.state.theme);
          if (r.personality) fd.append('personality', r.personality.slug);
          if (r.level)       fd.append('level', r.level.slug);
          fd.append('snapshot', blob, 'card.png');

          return fetch(META.base + '/api/save.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
          });
        })
        .then(function (res) {
          return res.json().catch(function () { throw new Error('bad_response'); })
            .then(function (data) {
              if (!res.ok || !data.ok) throw new Error(data.error || 'failed');
              return data;
            });
        })
        .then(function (data) {
          if (!linkBox || !linkUrl) { toast(UI.linkReady, false); return; }
          linkUrl.value = data.url;
          linkBox.hidden = false;
          linkUrl.focus();
          linkUrl.select();
          toast(UI.linkReady, false);
        })
        .catch(function (err) {
          toast(err && err.message === 'rate_limited' ? UI.rateLimited : UI.error, true);
        })
        .finally(function () {
          linkBusy = false;
          linkBtn.disabled = false;
          linkBtn.textContent = UI.makeLink;
        });
    });
  }

  const copyBtn = $('#copy-link');
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      if (!linkUrl) return;
      const done = function () { toast(UI.copied, false); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(linkUrl.value).then(done, function () {
          linkUrl.select();
          document.execCommand('copy');
          done();
        });
      } else {
        linkUrl.select();
        document.execCommand('copy');
        done();
      }
    });
  }

  // ----------------------------------------------------------- change feed --

  map.onChange(function () {
    updateProgress();
    syncThemeChips();
    renderPreview();
  });

  // ------------------------------------------------------------ first paint --

  // Restore the saved name and photo before the first card is drawn, otherwise
  // a returning visitor briefly sees their own card without their face on it.
  if (nameIn) nameIn.value = map.state.name || '';
  if (map.state.photo) applyPhoto(map.state.photo);

  syncThemeChips();
  updateProgress();
  renderPreview();

  // ------------------------------------------------------- open a shared map --

  /**
   * index.php?c=CODE — arrive from someone else's share link with their
   * selection already loaded. This is the other half of the viral loop: the
   * link is not just something to look at, it is a starting point.
   *
   * Their map replaces ours only if we have nothing marked, so a link can
   * never silently wipe work in progress.
   */
  const params = new URLSearchParams(location.search);
  const shared = params.get('c');

  /**
   * index.php?mark=SLUG — arrive from a place page in the guide.
   * index.php?mark=SLUG,SLUG,… — arrive from the trip page, which touches many
   * localities at once.
   *
   * Marking is additive, never destructive, and only ever for a slug that is
   * really in the payload, so a hand-edited URL cannot mark something that is
   * not on the map. Every slug is checked individually, so one bad value in a
   * list costs only itself rather than the whole link.
   *
   * Capped, because the URL is one somebody can paste: a link carrying ten
   * thousand slugs should not become ten thousand DOM writes.
   */
  const MARK_MAX = 64;

  function applyMark() {
    const raw = params.get('mark');
    if (!raw) return;

    const slugs = raw.split(',').slice(0, MARK_MAX);
    const bySlug = {};
    (P.localities || []).forEach(function (l) { bySlug[l.slug] = l; });

    let n = 0;
    let lastName = '';

    slugs.forEach(function (slug) {
      if (!/^[a-z0-9-]{1,64}$/.test(slug)) return;
      const row = bySlug[slug];
      if (!row) return;
      map.togglePlace(slug, true);
      n++;
      lastName = pick(row, 'name');
    });

    if (n === 1) {
      toast(fill(UI.markedOne, { name: lastName }), false);
    } else if (n > 1) {
      // One line for the whole set rather than one per place: arriving from the
      // trip page with twenty places would otherwise stack twenty toasts.
      toast(fill(UI.markedMany, { n: n }), false);
    }
  }

  if (shared && /^[0-9A-Z]{4,12}$/.test(shared)) {
    if (map.counts().places > 0) {
      toast(UI.keepYours, false);
      applyMark();
    } else {
      fetch(META.base + '/api/load.php?c=' + encodeURIComponent(shared), {
        headers: { 'Accept': 'application/json' },
      })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d || !d.ok) throw new Error('not_found');
          d.regions.forEach(function (code) { map.toggleRegion(code, true); });
          d.places.forEach(function (slug) { map.togglePlace(slug, true); });
          if (d.theme) map.setTheme(d.theme);
          if (d.name && nameIn && nameIn.value === '') {
            nameIn.value = d.name;
            map.setName(d.name);
          }
          toast(UI.loaded, false);
        })
        .catch(function () { /* a dead code just means an empty map */ })
        // After the shared map has settled, so a mark never counts as "you
        // already have a map" and blocks the load.
        .finally(applyMark);
    }
  } else {
    applyMark();
  }
})();
