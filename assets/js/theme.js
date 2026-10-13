/**
 * Qatar Unseen — the light/dark switch.
 *
 * Small on purpose. The theme itself is already decided by the time this file
 * runs: theme_boot() in <head> has set data-theme from the stored choice, or
 * from the device's own preference when there is no choice to read. All that is
 * left is the button, which is why the button ships hidden — it is revealed here
 * and nowhere else, so it only ever exists once it can actually do something.
 *
 * Not to be confused with the map's own theme, which is a row in share_themes
 * deciding what colour the exported card is drawn in. That one lives in map.js
 * and the two never read each other.
 */
(function () {
  'use strict';

  var S  = window.QTHEME_STRINGS || {};
  var KEY = S.key || 'qatar-theme-v1';
  var DARK = S.dark || 'dark';
  var LIGHT = S.light || 'light';

  var root = document.documentElement;
  var btn = document.querySelector('[data-theme-toggle]');
  if (!btn) { return; }

  /** The colours the <meta name="theme-color"> pair is set from. */
  var CHROME = { dark: '#0c0a09', light: '#fbfaf9' };

  function current() {
    return root.getAttribute('data-theme') === DARK ? DARK : LIGHT;
  }

  function store(v) {
    try { localStorage.setItem(KEY, v); } catch (e) { /* private mode, quota — the theme still changes */ }
  }

  /**
   * Repaint the button for the theme that is now showing.
   *
   * Both the icon and the label describe what pressing the button will do, not
   * what the theme currently is, so they always say the same thing — an icon
   * showing a moon next to a label reading "switch to the light theme" is the
   * kind of detail that makes a control feel broken.
   */
  function paint() {
    var now = current();
    var next = now === DARK ? LIGHT : DARK;

    btn.setAttribute('data-mode', now);
    btn.hidden = false;

    var label = next === DARK ? (S.to_dark || 'Switch to the dark theme')
                              : (S.to_light || 'Switch to the light theme');
    btn.setAttribute('aria-label', label);
    btn.setAttribute('title', label);
  }

  function apply(v) {
    root.setAttribute('data-theme', v);

    var metas = document.querySelectorAll('meta[name=theme-color]');
    for (var i = 0; i < metas.length; i++) {
      metas[i].setAttribute('content', CHROME[v] || CHROME.light);
    }
    paint();
  }

  btn.addEventListener('click', function () {
    var v = current() === DARK ? LIGHT : DARK;
    store(v);
    apply(v);
  });

  /**
   * A device that switches itself at sunset should take the page with it — but
   * only while the visitor has not chosen. Once they have, their choice is the
   * answer and the device does not overrule it.
   */
  if (window.matchMedia) {
    var mq = window.matchMedia('(prefers-color-scheme: dark)');
    var onSystemChange = function (e) {
      var stored = null;
      try { stored = localStorage.getItem(KEY); } catch (err) { /* unreadable — treat as unchosen */ }
      if (stored === DARK || stored === LIGHT) { return; }
      apply(e.matches ? DARK : LIGHT);
    };
    if (mq.addEventListener) { mq.addEventListener('change', onSystemChange); }
    else if (mq.addListener) { mq.addListener(onSystemChange); }   // Safari before 14
  }

  /**
   * Another tab of this site changed the theme. The stored value is the thing
   * that changed, so re-read it rather than trusting the event's payload.
   */
  window.addEventListener('storage', function (ev) {
    if (ev.key !== null && ev.key !== KEY) { return; }
    var v = null;
    try { v = localStorage.getItem(KEY); } catch (e) { return; }
    apply(v === DARK ? DARK : LIGHT);
  });

  paint();
})();
