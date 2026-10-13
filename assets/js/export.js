/**
 * Qatar Unseen — share card renderer.
 *
 * Cards are drawn straight onto a canvas with Path2D rather than by turning the
 * on-page SVG into an <img> and rasterising that. The reason is Arabic: an SVG
 * loaded through <img> cannot pull in a web font, so every Arabic label on the
 * card would fall back to whatever the OS has and the layout would fall apart.
 * Drawing by hand keeps the fonts, the RTL ordering and the spacing under our
 * control.
 *
 * Depends on window.QMap for the selection and geometry.
 */
(function () {
  'use strict';

  const SIZES = {
    post:  { w: 1080, h: 1080, ratio: 1 },
    story: { w: 1080, h: 1920, ratio: 9 / 16 },
    wide:  { w: 1200, h: 628,  ratio: 1.91 },
  };

  const JSPDF_URL = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.2/jspdf.umd.min.js';

  let CFG = { rtl: false, lang: 'en', strings: {} };
  let fontPromise = null;

  // ------------------------------------------------------------------ fonts

  /**
   * Canvas silently substitutes a fallback if the webfont is not in the font
   * cache yet, and the first paint is far too early for that. Loading the
   * exact family+weight pairs we draw with, then awaiting document.fonts.ready,
   * is what makes the difference between crisp Arabic and tofu.
   */
  function ensureFonts() {
    if (fontPromise) return fontPromise;
    if (!document.fonts || !document.fonts.load) return (fontPromise = Promise.resolve());

    const jobs = [];
    ['400', '500', '600', '700', '800'].forEach(function (w) {
      jobs.push(document.fonts.load(w + ' 100px "Inter"'));
      jobs.push(document.fonts.load(w + ' 100px "Noto Kufi Arabic"'));
    });

    fontPromise = Promise.all(jobs)
      .catch(function () { /* a missing weight is not worth failing the card for */ })
      .then(function () { return document.fonts.ready; })
      .catch(function () {});

    return fontPromise;
  }

  function fontStack() {
    return CFG.rtl
      ? '"Noto Kufi Arabic", "Inter", system-ui, sans-serif'
      : '"Inter", system-ui, -apple-system, "Segoe UI", sans-serif';
  }

  /** Arabic-Indic digits for the Arabic card, Western ones otherwise. */
  function digits(n) {
    const s = String(n);
    if (!CFG.rtl) return s;
    const map = { '0': '٠', '1': '١', '2': '٢', '3': '٣', '4': '٤',
                  '5': '٥', '6': '٦', '7': '٧', '8': '٨', '9': '٩' };
    return s.replace(/[0-9]/g, function (d) { return map[d]; });
  }

  /** Substitute :name placeholders — the same convention app.js and PHP use. */
  function fill(tpl, vars) {
    return String(tpl || '').replace(/:(\w+)/g, function (m, k) {
      return Object.prototype.hasOwnProperty.call(vars, k) ? String(vars[k]) : m;
    });
  }

  /** Width of a string in the card's own font. Measuring beats guessing: it is
   *  what lets the name be laid out against the avatar instead of a fixed nudge
   *  that long names run straight through. */
  function measureText(ctx, str, size, weight) {
    ctx.font = (weight || 500) + ' ' + Math.round(size) + 'px ' + fontStack();
    return ctx.measureText(str).width;
  }

  // -------------------------------------------------------------- geometry

  function mapBounds(geo) {
    let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    for (const code in geo.municipalities) {
      const b = geo.municipalities[code].bbox;
      if (!b || b.length !== 4) continue;
      x0 = Math.min(x0, b[0]); y0 = Math.min(y0, b[1]);
      x1 = Math.max(x1, b[2]); y1 = Math.max(y1, b[3]);
    }
    return isFinite(x0) ? [x0, y0, x1, y1] : [0, 0, 1000, 1000];
  }

  function drawMap(ctx, box, geo, locs, state, theme) {
    const b = mapBounds(geo);
    const bw = Math.max(1, b[2] - b[0]);
    const bh = Math.max(1, b[3] - b[1]);
    const k  = Math.min(box.w / bw, box.h / bh);
    const tx = box.x + (box.w - bw * k) / 2 - b[0] * k;
    const ty = box.y + (box.h - bh * k) / 2 - b[1] * k;

    const dotR  = Math.max(4, Math.min(box.w, box.h) * 0.019);
    const scale = Math.max(1e-6, k);

    ctx.save();
    ctx.translate(tx, ty);
    ctx.scale(scale, scale);

    for (const code in geo.municipalities) {
      const d = geo.municipalities[code].d;
      if (!d) continue;
      const path = new Path2D(d);
      ctx.fillStyle = state.regions.has(code) ? theme.visited : theme.land;
      ctx.fill(path);
      ctx.strokeStyle = theme.bg;
      ctx.lineWidth = 1.8 / scale;
      ctx.stroke(path);
    }

    for (const l of locs) {
      const on = state.places.has(l.slug);
      const r  = (on ? dotR * 1.05 : dotR) / scale;

      ctx.beginPath();
      ctx.arc(l.x, l.y, r, 0, Math.PI * 2);
      ctx.fillStyle   = on ? theme.visited : theme.bg;
      ctx.strokeStyle = on ? theme.bg : theme.visited;
      ctx.lineWidth   = Math.max(1, dotR * 0.34) / scale;
      ctx.fill();
      ctx.stroke();
    }

    ctx.restore();
  }

  // ------------------------------------------------------------------ text

  function makeText(ctx, rtl) {
    return function txt(str, x, y, o) {
      o = o || {};
      ctx.font = (o.weight || 500) + ' ' + Math.round(o.size) + 'px ' + fontStack();
      ctx.fillStyle = o.color;
      ctx.textAlign = o.align || 'center';
      ctx.textBaseline = o.baseline || 'alphabetic';
      if (o.alpha !== undefined) { ctx.save(); ctx.globalAlpha = o.alpha; }
      // maxWidth condenses a long string instead of letting it run off the
      // card — the safety net for names and translations of unknown length.
      if (o.maxWidth) ctx.fillText(str, x, y, o.maxWidth);
      else            ctx.fillText(str, x, y);
      if (o.alpha !== undefined) ctx.restore();
    };
  }

  // ------------------------------------------------------------------ card

  function draw(canvas, sizeKey, map, opts) {
    const S = SIZES[sizeKey] || SIZES.post;
    const scale = opts.scale || 1;
    const W = Math.round(S.w * scale);
    const H = Math.round(S.h * scale);

    canvas.width = W;
    canvas.height = H;

    const ctx = canvas.getContext('2d');
    const rtl = CFG.rtl;
    const th  = opts.theme;
    const str = CFG.strings;

    ctx.direction = rtl ? 'rtl' : 'ltr';

    const startX = rtl ? W * 0.93 : W * 0.07;
    const startA = rtl ? 'right' : 'left';

    // Background and the flag-coloured rule along the top.
    ctx.fillStyle = th.bg;
    ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = th.accent;
    ctx.fillRect(0, 0, W, Math.max(4, Math.round(H * 0.011)));

    const txt  = makeText(ctx, rtl);
    const soft = th.ink_soft || th.ink;

    // --- brand ------------------------------------------------------------
    const brandSize = Math.round((sizeKey === 'wide' ? H : W) * 0.026);
    const markSize  = Math.round(brandSize * 1.5);

    const markX = rtl ? W - W * 0.07 - markSize : W * 0.07;
    ctx.fillStyle = th.visited;
    roundRect(ctx, markX, H * 0.055 - markSize * 0.72, markSize, markSize, markSize * 0.26);
    ctx.fill();
    ctx.fillStyle = th.bg;
    ctx.beginPath();
    ctx.moveTo(markX + markSize * 0.3, H * 0.055 + markSize * 0.28);
    ctx.lineTo(markX + markSize * 0.7, H * 0.055 - markSize * 0.28);
    ctx.lineTo(markX + markSize * 0.7, H * 0.055 + markSize * 0.28);
    ctx.closePath();
    ctx.fill();

    txt(str.brand || 'Qatar Unseen', rtl ? W - W * 0.07 - markSize * 1.4 : W * 0.07 + markSize * 1.4,
        H * 0.055 + brandSize * 0.36,
        { size: brandSize, weight: 700, color: th.ink, align: startA });

    const n = map.state.places.size;
    const total = map.localities.length;
    // Rounded, so the card agrees with the count on the shared page (s.php).
    const pct = total > 0 ? Math.round((n / total) * 100) : 0;
    const name = opts.name || str.anon_name || '';

    if (sizeKey === 'wide') {
      const mapBox = { x: W * 0.05, y: H * 0.15, w: H * 0.72, h: H * 0.72 };
      drawMap(ctx, mapBox, map.geo, map.localities, map.state, th);

      // The text column sits to the right of the map in LTR, left of it in RTL.
      const align = rtl ? 'right' : 'left';
      const tx0 = rtl ? W * 0.95 : W * 0.55;
      // The column's far edge, used as the maxWidth guard on every line.
      const colW = Math.abs((rtl ? W * 0.55 : W * 0.95) - tx0);

      txt(digits(n), tx0, H * 0.52, { size: H * 0.28, weight: 800, color: th.visited, align: align });
      txt(fill(str.card_pct, { pct: digits(pct) }), tx0, H * 0.64,
          { size: H * 0.075, weight: 500, color: soft, align: align, maxWidth: colW });

      // The name and the avatar are one unit: the avatar takes the leading edge
      // of the column and the name is pushed clear of it, so a long name cannot
      // run back over the photo. This branch drew no photo at all before.
      const nameSz = H * 0.095;
      // Raised from 0.82H to make room for the larger avatar below it: at 0.82H
      // a 78px circle cleared the subtitle by 3px, which is not clearance.
      const nameY  = H * 0.78;
      let nameX   = tx0;
      let nameMax = colW;
      if (name && opts.photo) {
        const r   = H * 0.062;
        const gap = H * 0.020;
        drawAvatar(ctx, opts.photo, rtl ? tx0 - r : tx0 + r, nameY - nameSz * 0.34, r, th.visited);
        nameX   = rtl ? tx0 - (r * 2 + gap) : tx0 + (r * 2 + gap);
        nameMax = Math.abs((rtl ? W * 0.55 : W * 0.95) - nameX);
      }
      if (name) {
        txt(name, nameX, nameY,
            { size: nameSz, weight: 700, color: th.ink, align: align, maxWidth: nameMax });
      }
      if (opts.subtitle) {
        txt(opts.subtitle, tx0, H * 0.915,
            { size: H * 0.06, weight: 500, color: soft, align: align, maxWidth: colW });
      }
      txt(str.card_credit || '', W * 0.5, H * 0.965,
          { size: Math.round(H * 0.032), weight: 400, color: soft, align: 'center', alpha: 0.75 });
      return canvas;
    }

    // --- stacked (post / story) -------------------------------------------
    // Six things have to fit under the map, so their stops are derived from the
    // type sizes instead of being picked by eye. The old fixed stops put the
    // big number on top of the map, ran the hairline through the avatar's face
    // and printed the credit across the photo.
    const tall = sizeKey === 'story';

    const numSz  = tall ? H * 0.100 : H * 0.104;
    const subSz  = tall ? H * 0.022 : H * 0.027;
    const nameSz = tall ? H * 0.034 : H * 0.042;
    const sub2Sz = tall ? H * 0.020 : H * 0.025;
    const credSz = Math.round((tall ? H : W) * 0.017);
    // The photo is a byline element, not an icon. At 0.042H it was 90px on a
    // 1080 card — 8% of the width, and a smudge in the on-page preview — which
    // is what "the photo does not show properly" turned out to mean. At 0.065H
    // it is 140px (13% of the width), which reads as a face at preview scale.
    // The map gives up about a tenth of its height to pay for it.
    const photoR = tall ? H * 0.046 : H * 0.065;
    const gapY   = H * 0.024;

    // Anchored to the credit and built upward, with every byline stop measured
    // off the avatar rather than off a fixed fraction of the canvas. That is
    // what keeps the layout honest: making the photo bigger pushes the lines
    // above it up instead of dropping it onto the line below.
    const creditY  = H * 0.962;
    const sub2Y    = creditY - credSz * 0.72 - gapY * 1.4;
    const avatarCy = sub2Y - sub2Sz * 0.72 - gapY - photoR;
    const nameY    = avatarCy + nameSz * 0.34;   // the name centres on the photo
    const lineY    = avatarCy - photoR - gapY;

    // The number and its percentage are one band, centred in the gap between
    // the map and the hairline.
    const band = tall ? H * 0.145 : H * 0.175;
    const groupH = numSz * 0.72 + H * 0.014 + subSz * 0.72;

    const mapTop = tall ? H * 0.115 : H * 0.125;
    const box = { x: W * 0.10, y: mapTop, w: W * 0.80,
                  h: Math.max(H * 0.10, lineY - band - mapTop) };
    drawMap(ctx, box, map.geo, map.localities, map.state, th);

    const groupTop = box.y + box.h + Math.max(0, (band - groupH) / 2);
    const numY = groupTop + numSz * 0.72;
    const subY = numY + H * 0.014 + subSz * 0.72;

    txt(digits(n) + ' / ' + digits(total), W / 2, numY,
        { size: numSz, weight: 800, color: th.visited });

    txt(fill(str.card_pct, { pct: digits(pct) }), W / 2, subY,
        { size: subSz, weight: 500, color: soft, maxWidth: W * 0.86 });

    // A hairline between the number and the name reads as a signature line.
    ctx.strokeStyle = soft;
    ctx.globalAlpha = 0.32;
    ctx.lineWidth = Math.max(1, W * 0.0015);
    ctx.beginPath();
    ctx.moveTo(W * 0.40, lineY);
    ctx.lineTo(W * 0.60, lineY);
    ctx.stroke();
    ctx.globalAlpha = 1;

    // The avatar and the name are measured and centred as one group, so the
    // name sits beside the photo rather than across it — and mirrors in Arabic.
    if (name) {
      let nameX = W / 2;
      let nameAlign = 'center';
      let nameMax = W * 0.86;

      if (opts.photo) {
        const gap = nameSz * 0.42;
        const nw  = Math.min(measureText(ctx, name, nameSz, 700), W * 0.72);
        const gw  = nw + gap + photoR * 2;
        const x0  = (W - gw) / 2;

        drawAvatar(ctx, opts.photo,
                   rtl ? W - x0 - photoR : x0 + photoR, avatarCy, photoR, th.visited);

        nameX     = rtl ? W - x0 - photoR * 2 - gap : x0 + photoR * 2 + gap;
        nameAlign = rtl ? 'right' : 'left';
        nameMax   = nw;
      }

      txt(name, nameX, nameY,
          { size: nameSz, weight: 700, color: th.ink, align: nameAlign, maxWidth: nameMax });
    }

    if (opts.subtitle) {
      txt(opts.subtitle, W / 2, sub2Y,
          { size: sub2Sz, weight: 500, color: soft, maxWidth: W * 0.90 });
    }

    txt(str.card_credit || '', W / 2, creditY,
        { size: credSz, weight: 400, color: soft, align: 'center', alpha: 0.7 });

    return canvas;
  }

  function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  /** Draw an image filling a box, cropping the overflow — object-fit: cover. */
  function drawCover(ctx, img, x, y, w, h) {
    // An image that has not decoded yet reports 0×0, which would put NaN into
    // every coordinate below and quietly draw nothing at all.
    if (!img || !img.width || !img.height || !isFinite(w) || !isFinite(h)) return;

    const ir = img.width / img.height;
    const br = w / h;
    let sw = img.width, sh = img.height, sx = 0, sy = 0;
    if (ir > br) { sw = img.height * br; sx = (img.width - sw) / 2; }
    else         { sh = img.width / br;  sy = (img.height - sh) / 2; }
    ctx.drawImage(img, sx, sy, sw, sh, x, y, w, h);
  }

  /** The round photo, cropped to a circle. Every card size uses this one, so
   *  the avatar cannot drift out of step between them. */
  function drawAvatar(ctx, img, cx, cy, r, ringColor) {
    ctx.save();
    ctx.beginPath();
    ctx.arc(cx, cy, r, 0, Math.PI * 2);
    ctx.closePath();
    ctx.clip();
    drawCover(ctx, img, cx - r, cy - r, r * 2, r * 2);
    ctx.restore();

    // A ring in the card's accent, drawn just OUTSIDE the photo so it never
    // eats into the image. It separates the circle from a flat background and
    // is what makes the photo read as deliberate rather than as a stray blob.
    if (ringColor) {
      const w = Math.max(2, r * 0.055);
      ctx.save();
      ctx.beginPath();
      ctx.arc(cx, cy, r + w * 0.5, 0, Math.PI * 2);
      ctx.strokeStyle = ringColor;
      ctx.lineWidth   = w;
      ctx.globalAlpha = 0.9;
      ctx.stroke();
      ctx.restore();
    }
  }

  // --------------------------------------------------------------- outputs

  function toBlob(canvas) {
    return new Promise(function (resolve, reject) {
      canvas.toBlob(function (b) {
        b ? resolve(b) : reject(new Error('Canvas produced no image data.'));
      }, 'image/png');
    });
  }

  /** A fresh offscreen canvas — never the on-screen preview, which may be scaled. */
  function renderOffscreen(sizeKey, map, opts) {
    const c = document.createElement('canvas');
    return draw(c, sizeKey, map, opts);
  }

  function download(sizeKey, map, opts) {
    const canvas = renderOffscreen(sizeKey, map, { ...opts, scale: 1 });
    return toBlob(canvas).then(function (blob) {
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'qatar-map-' + sizeKey + '.png';
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
      return blob;
    });
  }

  /**
   * jsPDF is ~350 KB and most visitors only ever download a PNG, so it is
   * fetched on first use rather than blocking the page.
   */
  function loadJsPDF() {
    if (window.jspdf && window.jspdf.jsPDF) return Promise.resolve(window.jspdf.jsPDF);
    return new Promise(function (resolve, reject) {
      const s = document.createElement('script');
      s.src = JSPDF_URL;
      s.async = true;
      s.onload = function () {
        window.jspdf && window.jspdf.jsPDF
          ? resolve(window.jspdf.jsPDF)
          : reject(new Error('jsPDF loaded but did not register.'));
      };
      s.onerror = function () { reject(new Error('Could not load jsPDF.')); };
      document.head.appendChild(s);
    });
  }

  function downloadPdf(sizeKey, map, opts) {
    return Promise.all([
      Promise.resolve(renderOffscreen(sizeKey, map, { ...opts, scale: 1 })),
      loadJsPDF(),
    ]).then(function (out) {
      const canvas = out[0];
      const JsPDF  = out[1];
      const landscape = sizeKey === 'wide';

      const pdf = new JsPDF({ unit: 'mm', format: 'a4', orientation: landscape ? 'landscape' : 'portrait' });
      const pw = pdf.internal.pageSize.getWidth();
      const ph = pdf.internal.pageSize.getHeight();
      const margin = 12;

      const availW = pw - margin * 2;
      const availH = ph - margin * 2;
      const imgW = canvas.width, imgH = canvas.height;
      const r = Math.min(availW / imgW, availH / imgH);
      const w = imgW * r, h = imgH * r;

      pdf.addImage(canvas.toDataURL('image/png'), 'PNG',
                   (pw - w) / 2, (ph - h) / 2, w, h, undefined, 'FAST');
      pdf.save('qatar-map-' + sizeKey + '.pdf');
      return true;
    });
  }

  window.QCard = {
    SIZES: SIZES,
    ensureFonts: ensureFonts,

    init: function (cfg) {
      CFG = Object.assign(CFG, cfg || {});
      // Warm the fonts up front so the first preview is not drawn in a fallback.
      ensureFonts();
      return this;
    },

    draw: function (canvas, sizeKey, map, opts) {
      return draw(canvas, sizeKey, map, opts || {});
    },

    download: download,
    downloadPdf: downloadPdf,
    toBlob: function (sizeKey, map, opts) {
      return toBlob(renderOffscreen(sizeKey, map, opts || {}));
    },
    dataUrl: function (sizeKey, map, opts) {
      return renderOffscreen(sizeKey, map, opts || {}).toDataURL('image/png');
    },
  };
})();
