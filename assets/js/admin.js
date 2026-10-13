/**
 * Admin panel behaviour.
 *
 * Kept to one small deferred file: the panel is twenty server-rendered screens,
 * and none of them should need a bundler or a framework to toggle a checkbox.
 */
(function () {
  'use strict';

  // ---------------------------------------------------------------- theming
  //
  // Dark is the default. An explicit choice is remembered per browser — this is
  // a per-viewer convenience, so localStorage is the right home for it rather
  // than a database column nobody would ever look at.
  function applyTheme(name) {
    if (name === 'light') {
      document.documentElement.setAttribute('data-ad-theme', 'light');
    } else {
      document.documentElement.removeAttribute('data-ad-theme');
    }
  }

  try {
    var saved = localStorage.getItem('ad-theme');
    if (saved) applyTheme(saved);
  } catch (e) { /* private mode: the default is fine */ }

  document.addEventListener('click', function (ev) {
    var t = ev.target.closest('[data-ad-theme-toggle]');
    if (!t) return;
    var next = document.documentElement.getAttribute('data-ad-theme') === 'light' ? 'dark' : 'light';
    applyTheme(next);
    try { localStorage.setItem('ad-theme', next); } catch (e) {}
  });

  // ------------------------------------------------------------ mobile menu
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('[data-ad-side]')) return;
    var side = document.getElementById('ad-side');
    if (side) side.classList.toggle('open');
  });

  // ---------------------------------------------------------------- dismiss
  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('[data-ad-dismiss]')) return;
    var f = ev.target.closest('.ad-flash');
    if (f) f.remove();
  });

  // --------------------------------------------------------------- confirms
  //
  // One delegated handler rather than an inline onclick on every destructive
  // button, so a new screen gets the guard by adding an attribute.
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    if (!(f instanceof HTMLFormElement)) return;

    // Which button was actually pressed. `ev.submitter` is the reliable answer
    // and is asked first — Safari does not focus a button on click, so falling
    // back to document.activeElement there finds nothing and the confirmation
    // would be skipped on exactly the destructive button it exists for. The
    // :focus check and the activeElement check stay for older engines.
    var btn = ev.submitter
           || f.querySelector('[data-ad-confirm]:focus')
           || (document.activeElement && document.activeElement.form === f ? document.activeElement : null);

    var msg = (btn && btn.getAttribute('data-ad-confirm')) || f.getAttribute('data-ad-confirm');
    if (msg && !window.confirm(msg)) {
      ev.preventDefault();
      return;
    }

    // A second click on a slow save is how you get two records.
    var submit = btn || f.querySelector('button[type=submit]');
    if (submit && !submit.disabled) {
      setTimeout(function () {
        submit.disabled = true;
        var was = submit.textContent;
        submit.textContent = 'Working…';
        // If the response never navigates (a validation error re-renders in
        // place), put the button back so the form is usable again.
        setTimeout(function () { submit.disabled = false; submit.textContent = was; }, 6000);
      }, 0);
    }
  }, false);

  // ------------------------------------------------------------ select all
  document.addEventListener('change', function (ev) {
    var all = ev.target.closest('[data-ad-all]');
    if (!all) return;
    var form = all.closest('form');
    if (!form) return;
    form.querySelectorAll('input[name="ids[]"]').forEach(function (cb) { cb.checked = all.checked; });
  });

  // ------------------------------------------------------- colour text sync
  //
  // The colour input and the text box are two views of one value. Which one the
  // browser renders depends on the browser, so both are posted and the text box
  // is the one the server trusts.
  document.addEventListener('input', function (ev) {
    var el = ev.target;
    if (!(el instanceof HTMLInputElement)) return;

    if (el.type === 'color') {
      var txt = el.parentElement && el.parentElement.querySelector('input[type=text]');
      if (txt) txt.value = el.value;
    } else if (el.type === 'text' && el.parentElement && el.parentElement.classList.contains('ad-colorwrap')) {
      if (/^#[0-9a-f]{6}$/i.test(el.value)) {
        var pick = el.parentElement.querySelector('input[type=color]');
        if (pick) pick.value = el.value;
      }
    }
  });

  // ----------------------------------------------------------- image picker
  var picker = null;

  function buildPicker() {
    if (picker) return picker;
    picker = document.createElement('div');
    picker.className = 'ad-modal';
    picker.hidden = true;
    picker.innerHTML =
      '<div class="ad-modal-box" role="dialog" aria-modal="true" aria-label="Choose an image">' +
        '<h2>Choose an image</h2>' +
        '<p class="ad-help">Click one to use it. Upload new images from the ' +
          '<a href="media.php">Media</a> screen.</p>' +
        '<div class="ad-media" data-pick-grid></div>' +
        '<p style="margin-block-start:16px"><button class="ad-btn ghost" type="button" data-pick-close>Close</button></p>' +
      '</div>';
    document.body.appendChild(picker);

    picker.addEventListener('click', function (ev) {
      if (ev.target === picker || ev.target.closest('[data-pick-close]')) {
        picker.hidden = true;
        return;
      }
      var fig = ev.target.closest('[data-path]');
      if (!fig) return;
      var target = document.getElementById('f_' + picker.dataset.field);
      if (target) {
        target.value = fig.getAttribute('data-path');
        target.dispatchEvent(new Event('change', { bubbles: true }));
      }
      picker.hidden = true;
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && picker && !picker.hidden) picker.hidden = true;
    });

    return picker;
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-ad-pick]');
    if (!btn) return;

    var box = buildPicker();
    box.dataset.field = btn.getAttribute('data-ad-pick');
    var grid = box.querySelector('[data-pick-grid]');
    grid.innerHTML = '<p class="ad-muted">Loading…</p>';
    box.hidden = false;

    fetch('media.php?pick=1', { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok || !data.images.length) {
          grid.innerHTML = '<p class="ad-muted">No images uploaded yet.</p>';
          return;
        }
        grid.innerHTML = data.images.map(function (im) {
          return '<figure data-path="' + im.path.replace(/"/g, '&quot;') + '">' +
                   '<img src="' + im.url.replace(/"/g, '&quot;') + '" alt="" loading="lazy">' +
                   '<figcaption><b>' + im.name + '</b>' + im.used + '</figcaption>' +
                 '</figure>';
        }).join('');
      })
      .catch(function () {
        grid.innerHTML = '<p class="ad-muted">Could not load the library.</p>';
      });
  });

  // ------------------------------------------------------------- unsaved warn
  //
  // Only on editing forms: a long place description lost to a stray back button
  // is the single most annoying thing an admin panel can do.
  var dirty = false;
  document.addEventListener('input', function (ev) {
    if (ev.target.closest('form.ad-form')) dirty = true;
  });
  document.addEventListener('submit', function () { dirty = false; });
  window.addEventListener('beforeunload', function (ev) {
    if (!dirty) return;
    ev.preventDefault();
    ev.returnValue = '';
  });
})();
