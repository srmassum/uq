/**
 * Qatar Unseen — badges, levels and travel personality.
 *
 * The definitions (which badges exist, what each one requires, how the
 * personalities score) are data, loaded from the database and handed to this
 * file as JSON. Adding a badge is a row insert, not a code change — so the
 * rule vocabulary below is the whole contract:
 *
 *   {"type":"count","n":N}                        N places marked
 *   {"type":"municipalities_all"}                 something marked in all eight
 *   {"type":"municipalities","n":N}               N municipalities covered
 *   {"type":"kind","kind":"natural","n":N}        by localities.kind
 *   {"type":"category","slug":"beach","n":N}      by places.category
 *   {"type":"municipality","code":"QAMS","all":1} everything in one municipality
 *
 * An unknown rule type fails closed (the badge stays locked) rather than
 * open, so a typo in a rule can never hand out a badge for nothing.
 *
 * Translations and markup live in PHP; this file only toggles classes and
 * fills in numbers, so there is never a second copy of a badge's name.
 */
(function () {
  'use strict';

  let LEVELS = [];
  let BADGES = [];
  let PERSONAS = [];
  let STRINGS = {};

  /** slug -> array of category slugs. Supplied once the places guide is built. */
  let PLACE_CATS = {};

  // ------------------------------------------------------------- context --

  function context(map) {
    const bySlug = {};
    map.localities.forEach(function (l) { bySlug[l.slug] = l; });

    const marked = [];
    map.localities.forEach(function (l) { if (map.state.places.has(l.slug)) marked.push(l); });

    const kinds = {}, muns = {}, cats = {};
    marked.forEach(function (l) {
      kinds[l.kind] = (kinds[l.kind] || 0) + 1;
      muns[l.mun]   = (muns[l.mun]   || 0) + 1;
      (PLACE_CATS[l.slug] || []).forEach(function (c) { cats[c] = (cats[c] || 0) + 1; });
    });

    return {
      n:        marked.length,
      marked:   marked,
      munCount: Object.keys(muns).length,
      kinds:    kinds,
      muns:     muns,
      cats:     cats,
    };
  }

  // -------------------------------------------------------------- badges --

  function badgeMet(rule, ctx, map) {
    if (!rule || typeof rule !== 'object') return false;

    switch (rule.type) {
      case 'count':
        return ctx.n >= (rule.n || 1);

      case 'municipalities_all': {
        const codes = Object.keys(map.geo.municipalities);
        return codes.length > 0 && codes.every(function (c) { return (ctx.muns[c] || 0) > 0; });
      }

      case 'municipalities':
        return ctx.munCount >= (rule.n || 1);

      case 'kind':
        return (ctx.kinds[rule.kind] || 0) >= (rule.n || 1);

      case 'category':
        return (ctx.cats[rule.slug] || 0) >= (rule.n || 1);

      case 'municipality': {
        const inMun = map.localities.filter(function (l) { return l.mun === rule.code; });
        if (!inMun.length) return false;
        const done = inMun.filter(function (l) { return map.state.places.has(l.slug); }).length;
        return rule.all ? done === inMun.length : done >= (rule.n || 1);
      }

      default:
        return false;   // unknown rule: stay locked rather than guess
    }
  }

  // -------------------------------------------------------- personality --

  function personaScore(weights, ctx) {
    let total = 0;
    for (const key in weights) {
      if (!Object.prototype.hasOwnProperty.call(weights, key)) continue;
      const w = weights[key];

      if (key === 'any') { total += w; continue; }

      const i = key.indexOf(':');
      if (i < 1) continue;
      const ns  = key.slice(0, i);
      const val = key.slice(i + 1);

      if (ns === 'kind')       total += w * (ctx.kinds[val] || 0);
      else if (ns === 'cat')   total += w * (ctx.cats[val]  || 0);
      else if (ns === 'mun')   total += w * (ctx.muns[val]  || 0);
      else if (ns === 'count') total += ctx.n >= Number(val) ? w : 0;
    }
    return total;
  }

  /** Highest score wins; ties break on the order the rows arrived (priority). */
  function pickPersona(ctx) {
    let best = null, bestScore = 0;
    PERSONAS.forEach(function (p) {
      const s = personaScore(p.rule, ctx);
      // A personality needs a positive score. Without this the least-negative
      // one would "win" for someone who has marked almost nothing.
      if (s > bestScore) { best = p; bestScore = s; }
    });
    return best;
  }

  // -------------------------------------------------------------- levels --

  function pickLevel(n) {
    let cur = null, next = null;
    for (const l of LEVELS) {
      if (n >= Number(l.min_places)) cur = l;
      else { next = l; break; }
    }
    return { level: cur, next: next };
  }

  // ------------------------------------------------------------ evaluate --

  function evaluate(map) {
    const ctx = context(map);

    const badgeState = BADGES.map(function (b) {
      return { slug: b.slug, earned: badgeMet(b.rule, ctx, map) };
    });

    const { level, next } = pickLevel(ctx.n);
    const earn = badgeState.filter(function (b) { return b.earned; }).length;

    let toNext = 0;
    if (next) toNext = Math.max(0, Number(next.min_places) - ctx.n);

    return {
      n:        ctx.n,
      total:    map.localities.length,
      regions:  map.state.regions.size,
      munCount: ctx.munCount,
      level:    level,
      next:     next,
      toNext:   toNext,
      badges:   badgeState,
      earned:   earn,
      personality: pickPersona(ctx),
    };
  }

  // -------------------------------------------------------------- render --

  function render(map) {
    const r = evaluate(map);

    document.querySelectorAll('[data-level]').forEach(function (el) {
      el.hidden = !r.level || el.getAttribute('data-level') !== r.level.slug;
    });

    document.querySelectorAll('.next-line').forEach(function (el) {
      el.hidden = el.getAttribute('data-next') !== (r.next ? r.next.slug : '');
    });
    const numEl = document.querySelector('.next-line:not([hidden]) [data-n]');
    if (numEl) numEl.textContent = String(r.toNext);

    document.querySelectorAll('[data-badge]').forEach(function (el) {
      const hit = r.badges.find(function (b) { return b.slug === el.getAttribute('data-badge'); });
      const on = !!(hit && hit.earned);
      el.classList.toggle('on', on);
      el.setAttribute('data-earned', on ? '1' : '0');
    });

    const counter = document.querySelector('[data-badge-count]');
    if (counter) counter.textContent = r.earned + ' / ' + BADGES.length;

    document.querySelectorAll('[data-persona]').forEach(function (el) {
      el.hidden = !r.personality || el.getAttribute('data-persona') !== r.personality.slug;
    });

    // The "mark a few more" nudge is only useful while there is nothing to show.
    document.querySelectorAll('[data-persona-hint]').forEach(function (el) {
      el.hidden = !!r.personality;
    });

    return r;
  }

  window.QGamify = {
    evaluate: evaluate,
    render: render,

    /** Called with {slug: ['beach','family']} once the places guide exists. */
    setPlaceCategories: function (map) {
      PLACE_CATS = map || {};
    },

    init: function (cfg) {
      LEVELS  = cfg.levels || [];
      BADGES  = cfg.badges || [];
      PERSONAS = cfg.personalities || [];
      STRINGS = cfg.strings || {};

      if (cfg.placeCategories) PLACE_CATS = cfg.placeCategories;
      return this;
    },

    /** Convenience: render now and again on every change. */
    bind: function (map) {
      render(map);
      map.onChange(function () { render(map); });
    },
  };
})();
