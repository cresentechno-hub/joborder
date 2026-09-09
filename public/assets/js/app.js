(function () {
  var shell = document.querySelector('.app-shell');
  var toggle = document.querySelector('.sidebar-toggle');
  var overlay = document.querySelector('.sidebar-overlay');

  if (!shell || !toggle || !overlay) {
    return;
  }

  function closeSidebar() {
    shell.classList.remove('sidebar-open');
  }

  toggle.addEventListener('click', function () {
    shell.classList.toggle('sidebar-open');
  });

  overlay.addEventListener('click', closeSidebar);

  document.querySelectorAll('.sidebar-nav a').forEach(function (link) {
    link.addEventListener('click', closeSidebar);
  });
})();

/**
 * Client-side click-to-sort for tables that hold their whole result set in
 * the DOM already (table.client-sortable — Users/Branches/Customers/LPR
 * Partners) and for the LPR/SMC grids (table.lpr-grid), which additionally
 * need to keep each partner's rows sorted within their own group rather
 * than shuffled across group boundaries. Paginated lists (Job Orders,
 * Activity Log) are sorted server-side instead — see sortable_th() in
 * Helpers/helpers.php — since only one page of rows ever reaches the DOM.
 */
(function () {
  function cellText(row, colIndex) {
    var cell = row.children[colIndex];
    return cell ? cell.textContent.replace(/\s+/g, ' ').trim() : '';
  }

  function compare(a, b) {
    return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
  }

  function makeSortable(table) {
    var thead = table.tHead;
    var tbody = table.tBodies[0];
    if (!thead || !tbody || !thead.rows.length) {
      return;
    }
    var headerRow = thead.rows[0];

    Array.prototype.forEach.call(headerRow.cells, function (th, colIndex) {
      var label = th.textContent.trim();
      if (!label || th.classList.contains('actions-cell') || th.classList.contains('month-col')) {
        return;
      }

      th.classList.add('sortable-col');
      var indicator = document.createElement('span');
      indicator.className = 'sort-indicator';
      th.appendChild(indicator);

      var applySort = function () {
        var newDir = th.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
        Array.prototype.forEach.call(headerRow.cells, function (h) {
          if (h === th) { return; }
          h.removeAttribute('data-sort-dir');
          var ind = h.querySelector('.sort-indicator');
          if (ind) { ind.textContent = ''; }
        });
        th.setAttribute('data-sort-dir', newDir);
        indicator.textContent = newDir === 'asc' ? '▲' : '▼';

        // Split the body into groups delimited by .group-header-row (LPR's
        // partner grouping) so sorting never moves a row into a different
        // partner's section. Tables without any such rows end up as one
        // single implicit group, which just sorts the whole body normally.
        var rows = Array.prototype.slice.call(tbody.rows);
        var groups = [{ header: null, rows: [] }];
        rows.forEach(function (row) {
          if (row.classList.contains('group-header-row')) {
            groups.push({ header: row, rows: [] });
          } else {
            groups[groups.length - 1].rows.push(row);
          }
        });

        groups.forEach(function (g) {
          g.rows.sort(function (r1, r2) {
            var cmp = compare(cellText(r1, colIndex), cellText(r2, colIndex));
            return newDir === 'asc' ? cmp : -cmp;
          });
        });

        var frag = document.createDocumentFragment();
        groups.forEach(function (g) {
          if (g.header) { frag.appendChild(g.header); }
          g.rows.forEach(function (r) { frag.appendChild(r); });
        });
        tbody.appendChild(frag);
      };

      th.addEventListener('click', applySort);
    });
  }

  document.querySelectorAll('table.client-sortable, table.lpr-grid').forEach(makeSortable);
})();

/** Notification bell dropdown — toggle on click, close on an outside click or Escape. */
(function () {
  var btn = document.getElementById('notif-bell-btn');
  var dropdown = document.getElementById('notif-dropdown');
  if (!btn || !dropdown) {
    return;
  }

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    dropdown.hidden = !dropdown.hidden;
  });

  document.addEventListener('click', function (e) {
    if (!dropdown.hidden && !dropdown.contains(e.target) && e.target !== btn) {
      dropdown.hidden = true;
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      dropdown.hidden = true;
    }
  });
})();
