/**
 * Drag-to-reorder for the itinerary stop list.
 *
 * Optional on purpose. Every row also carries ▲ ▼ buttons that submit on their
 * own, so the screen works with JavaScript off, on a touch screen, and from a
 * keyboard — which is the half that matters. What drag adds is speed.
 *
 * The order is never sent as a field of its own. Rows are moved in the DOM, and
 * the form posts its inputs in DOM order, so the sequence the browser sends IS
 * the new order and the server has nothing to reconcile.
 *
 * Drops are confined to the row's own day. A stop dragged out of Day 1 and into
 * Day 3 would save back into Day 1 — because day_number is a value on the row,
 * not a consequence of position — so allowing the drop would look exactly like
 * a drag that silently failed. Changing the day is what the day box is for.
 */
(function () {
  'use strict';

  var list = document.querySelector('[data-stops]');
  if (!list) return;

  var drag = null;

  list.addEventListener('dragstart', function (ev) {
    var row = ev.target.closest('tr[data-stop]');
    if (!row) return;

    drag = row;
    row.classList.add('dragging');

    if (ev.dataTransfer) {
      ev.dataTransfer.effectAllowed = 'move';
      // Firefox will not start a drag unless some data is set.
      ev.dataTransfer.setData('text/plain', row.getAttribute('data-stop') || '');
    }
  });

  list.addEventListener('dragover', function (ev) {
    if (!drag) return;

    var row = ev.target.closest('tr[data-stop]');
    if (!row || row === drag) return;
    if (row.parentNode !== drag.parentNode) return;   // same day only

    ev.preventDefault();
    if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'move';

    // Which half of the row the pointer is over decides which side it lands on,
    // so dragging down past the middle of a row means "after it".
    var box   = row.getBoundingClientRect();
    var after = (ev.clientY - box.top) > box.height / 2;

    if (after) {
      if (row.nextSibling !== drag) row.after(drag);
    } else if (row.previousSibling !== drag) {
      row.before(drag);
    }
  });

  list.addEventListener('drop', function (ev) {
    if (drag) ev.preventDefault();
  });

  list.addEventListener('dragend', function () {
    if (!drag) return;
    drag.classList.remove('dragging');
    drag = null;

    // A drag fires no input or change event, so the panel's "you have unsaved
    // changes" warning would otherwise never arm for a reorder. Bubbling an
    // input event from inside the form is what admin.js is listening for.
    list.dispatchEvent(new Event('input', { bubbles: true }));
  });
})();
