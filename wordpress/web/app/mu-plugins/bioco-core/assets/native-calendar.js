/* Native headings own month order; align repeated cells and accessible headers. */
(function () {
  'use strict';
  var instance = 0;
  function month(column) {
    var match = column.className.match(/(?:^|\s)bioco-calendar-month-(1[0-2]|[1-9])(?:\s|$)/);
    return match ? match[1] : '';
  }
  function init() {
    document.querySelectorAll('.bioco-native-calendar').forEach(function (root) {
      if (root.dataset.biocoCalendarReady) return;
      var head = root.querySelector('.bioco-calendar-head');
      if (!head) return;
      root.dataset.biocoCalendarReady = '1'; instance += 1;
      root.setAttribute('role', 'table');
      head.setAttribute('role', 'row');
      var nameHeader = head.querySelector('.bioco-calendar-name');
      if (nameHeader) {
        nameHeader.setAttribute('role', 'columnheader');
        nameHeader.setAttribute('aria-colindex', '1');
      }
      var headers = Array.from(head.children).filter(function (el) { return month(el); });
      headers.forEach(function (el, index) {
        if (!el.id) el.id = 'bioco-calendar-' + instance + '-month-' + month(el);
        el.setAttribute('role', 'columnheader'); el.setAttribute('aria-colindex', String(index + 2));
      });
      root.querySelectorAll('.bioco-calendar-data').forEach(function (row) {
        row.setAttribute('role', 'row');
        var name = row.querySelector('.bioco-calendar-name');
        if (name) {
          name.setAttribute('role', 'rowheader');
          name.setAttribute('aria-colindex', '1');
        }
        var cells = Array.from(row.children).filter(function (el) { return month(el); });
        headers.forEach(function (header, index) {
          var cell = cells.find(function (el) { return month(el) === month(header); });
          if (!cell) return;
          row.appendChild(cell); cell.setAttribute('role', 'cell');
          cell.setAttribute('aria-colindex', String(index + 2)); cell.setAttribute('aria-describedby', header.id);
        });
        // A removed displayed month must not leave an unlabeled data column.
        cells.forEach(function (cell) {
          if (!headers.some(function (header) { return month(header) === month(cell); })) { cell.hidden = true; cell.style.display = 'none'; }
        });
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
