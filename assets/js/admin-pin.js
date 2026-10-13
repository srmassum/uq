/**
 * Click-the-map pin picker for the place editor.
 *
 * Types a latitude and longitude into a box is how you end up with a place
 * pinned in the sea. Clicking the actual map is how you end up with it pinned
 * where you meant — and the inverse projection is already solved, because
 * geo.json ships the coefficients the map was drawn with.
 *
 * Forward:   x = ax·lng + bx        y = ay·mercY(lat) + by
 * Inverse:   lng = (x − bx) / ax    mercY(lat) = (y − by) / ay
 *            lat = atan(exp(mercY))·360/π − 90
 */
(function () {
  'use strict';

  var host = document.querySelector('[data-pin-map]');
  if (!host) return;

  var latEl = document.getElementById('f_lat');
  var lngEl = document.getElementById('f_lng');
  if (!latEl || !lngEl) return;

  var NS = 'http://www.w3.org/2000/svg';
  var statusEl = host.parentElement.querySelector('[data-pin-status]');

  function say(msg, bad) {
    if (!statusEl) return;
    statusEl.textContent = msg;
    statusEl.className = 'ad-help' + (bad ? ' ad-err' : '');
  }

  function mercY(lat) {
    return Math.log(Math.tan(Math.PI / 4 + (lat * Math.PI) / 360));
  }
  function invMercY(m) {
    return (Math.atan(Math.exp(m)) * 360) / Math.PI - 90;
  }

  var projection = null;

  function toLatLng(x, y) {
    var p = projection;
    return {
      lng: (x - p.bx) / p.ax,
      lat: invMercY((y - p.by) / p.ay),
    };
  }

  /** Where to draw the marker for a coordinate, or null if it cannot be read. */
  function toXY(lat, lng) {
    if (!projection || !isFinite(lat) || !isFinite(lng)) return null;
    return {
      x: projection.ax * lng + projection.bx,
      y: projection.ay * mercY(lat) + projection.by,
    };
  }

  // --------------------------------------------------------------- build map
  Promise.all([
    fetch('../assets/data/geo.json').then(function (r) { return r.json(); }),
    // The locality dots are the whole point: a place should sit on one of them,
    // and seeing them is what stops a pin landing in the middle of nowhere.
    fetch('../assets/data/localities.json')
      .then(function (r) { return r.json(); })
      .catch(function () { return null; }),
  ]).then(function (res) {
    var geo = res[0];
    var locs = res[1];
    projection = geo.projection;

    var svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', geo.viewBox || '0 0 1000 1000');
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'Map of Qatar. Click to place the pin.');

    var land = document.createElementNS(NS, 'g');
    land.setAttribute('fill', 'currentColor');
    land.setAttribute('opacity', '0.14');
    Object.keys(geo.municipalities).forEach(function (code) {
      var m = geo.municipalities[code];
      var path = document.createElementNS(NS, 'path');
      path.setAttribute('d', m.d);
      land.appendChild(path);
    });
    svg.appendChild(land);

    var outline = document.createElementNS(NS, 'g');
    outline.setAttribute('fill', 'none');
    outline.setAttribute('stroke', 'currentColor');
    outline.setAttribute('stroke-width', '1.5');
    outline.setAttribute('opacity', '0.5');
    Object.keys(geo.municipalities).forEach(function (code) {
      var path = document.createElementNS(NS, 'path');
      path.setAttribute('d', geo.municipalities[code].d);
      outline.appendChild(path);
    });
    svg.appendChild(outline);

    if (locs) {
      var dots = Array.isArray(locs) ? locs : (locs.localities || []);
      var g = document.createElementNS(NS, 'g');
      g.setAttribute('fill', 'currentColor');
      g.setAttribute('opacity', '0.42');
      dots.forEach(function (l) {
        var pt = toXY(parseFloat(l.lat), parseFloat(l.lng));
        if (!pt) return;
        var c = document.createElementNS(NS, 'circle');
        c.setAttribute('cx', pt.x);
        c.setAttribute('cy', pt.y);
        c.setAttribute('r', 3.2);
        var t = document.createElementNS(NS, 'title');
        t.textContent = l.name_en || l.slug || '';
        c.appendChild(t);
        g.appendChild(c);
      });
      svg.appendChild(g);
    }

    var marker = document.createElementNS(NS, 'g');
    marker.setAttribute('data-pin-marker', '');
    var ring = document.createElementNS(NS, 'circle');
    ring.setAttribute('r', '9');
    ring.setAttribute('fill', 'none');
    ring.setAttribute('stroke', 'currentColor');
    ring.setAttribute('stroke-width', '2');
    ring.setAttribute('opacity', '0.55');
    var dot = document.createElementNS(NS, 'circle');
    dot.setAttribute('r', '4');
    dot.setAttribute('class', 'ad-pin-dot');
    marker.appendChild(ring);
    marker.appendChild(dot);
    svg.appendChild(marker);

    host.appendChild(svg);

    // ------------------------------------------------------------- the click
    function place(x, y) {
      var ll = toLatLng(x, y);
      // Six decimals is what the column stores; showing more would suggest a
      // precision the database does not keep.
      latEl.value = ll.lat.toFixed(6);
      lngEl.value = ll.lng.toFixed(6);
      draw();
    }

    svg.addEventListener('click', function (ev) {
      var pt = svg.createSVGPoint();
      pt.x = ev.clientX;
      pt.y = ev.clientY;
      var ctm = svg.getScreenCTM();
      if (!ctm) return;
      var local = pt.matrixTransform(ctm.inverse());
      place(local.x, local.y);
    });

    // Typing coordinates by hand should move the pin too, so the two controls
    // never disagree about where the place is.
    function draw() {
      var pt = toXY(parseFloat(latEl.value), parseFloat(lngEl.value));
      if (!pt) {
        // `hidden` is an HTML attribute and does nothing on an SVG element, so
        // the marker is hidden with a style instead.
        marker.style.display = 'none';
        say('Enter coordinates, or click the map.', true);
        return;
      }
      marker.style.display = '';
      marker.setAttribute('transform', 'translate(' + pt.x + ' ' + pt.y + ')');
      say(
        'Pinned at ' + parseFloat(latEl.value).toFixed(6) + ', ' + parseFloat(lngEl.value).toFixed(6)
        + '. Click again to move it.'
      );
    }

    ['input', 'change'].forEach(function (evt) {
      latEl.addEventListener(evt, draw);
      lngEl.addEventListener(evt, draw);
    });

    draw();
  }).catch(function (err) {
    say('Could not load the map. You can still type the coordinates.', true);
    if (window.console) console.error('pin picker:', err);
  });
})();
