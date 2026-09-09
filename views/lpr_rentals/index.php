<?php $success = flash('success'); $error = flash('error'); ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
  <h2 style="margin:0;">LPR Rental</h2>
  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a href="/lpr-rentals/export" class="btn btn-outline">Export CSV</a>
    <?php if ($canManage): ?>
      <a href="/lpr-rentals/import" class="btn btn-outline">Import</a>
      <a href="/lpr-rentals/create" class="btn btn-primary">+ New Contract</a>
    <?php endif; ?>
  </div>
</div>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  Tracks which months' invoices have been printed for each LPR rental contract. Grouped by partner — the checkbox
  columns scroll independently; Customer, Start Date, Coverage, Email and Actions stay fixed on the left.
</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="GET" action="/lpr-rentals" style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
  <select class="form-control" name="customer_id" style="max-width:280px;">
    <option value="">All Customers</option>
    <?php foreach ($customers as $cust): ?>
      <option value="<?= (int) $cust['id'] ?>" <?= $selectedCustomerId === (int) $cust['id'] ? 'selected' : '' ?>><?= e($cust['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($canSelectBranch): ?>
    <select class="form-control" name="branch_id" style="max-width:280px;">
      <option value="">All Branches</option>
      <?php foreach ($branches as $br): ?>
        <option value="<?= (int) $br['id'] ?>" <?= $selectedBranchId === (int) $br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
      <?php endforeach; ?>
    </select>
  <?php endif; ?>
  <button type="submit" class="btn btn-outline">Filter</button>
</form>

<?php if (empty($groups)): ?>
  <div class="card" style="text-align:center; padding:32px; color:var(--color-text-muted);">
    No LPR rental contracts yet.
    <?php if ($canManage): ?><br><a href="/lpr-rentals/create">Create the first one</a>.<?php endif; ?>
  </div>
<?php else: ?>
  <div class="card" style="padding:0;">
    <div class="lpr-grid-wrap" id="lpr-grid-wrap">
      <table class="lpr-grid <?= $showBranchColumn ? 'has-branch-col' : '' ?>">
        <thead>
          <tr>
            <th class="sticky-col col-actions"></th>
            <th class="sticky-col col-customer">Customer</th>
            <th class="sticky-col col-start">Start Date</th>
            <th class="sticky-col col-coverage">Coverage</th>
            <th class="sticky-col col-email">Customer Email</th>
            <?php if ($showBranchColumn): ?><th class="sticky-col col-branch">Branch</th><?php endif; ?>
            <?php foreach ($months as $ym): ?>
              <th class="month-col <?= $ym === $currentYm ? 'current-month' : '' ?>"><?= e(lpr_month_label($ym)) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groups as $partnerName => $rentalsInGroup): ?>
            <tr class="group-header-row">
              <td class="sticky-col col-actions" colspan="<?= $showBranchColumn ? 6 : 5 ?>">Partner: <?= e($partnerName) ?></td>
              <?php foreach ($months as $ym): ?><td class="<?= $ym === $currentYm ? 'current-month' : '' ?>"></td><?php endforeach; ?>
            </tr>
            <?php foreach ($rentalsInGroup as $r): ?>
              <?php $rid = (int) $r['id']; ?>
              <tr>
                <td class="sticky-col col-actions">
                  <?php if ($canManage): ?>
                    <a href="/lpr-rentals/<?= $rid ?>/edit">Edit</a>
                    &nbsp;|&nbsp;
                    <form method="POST" action="/lpr-rentals/<?= $rid ?>/delete" style="display:inline;"
                          onsubmit="return confirm('Delete this LPR rental contract? This cannot be undone.');">
                      <?= csrf_field() ?>
                      <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:13px;">Delete</button>
                    </form>
                  <?php endif; ?>
                </td>
                <td class="sticky-col col-customer"><?= e($r['customer_name']) ?></td>
                <td class="sticky-col col-start"><?= e(format_date($r['start_date'])) ?></td>
                <td class="sticky-col col-coverage"><?= (int) $r['coverage_months'] ?>mo</td>
                <td class="sticky-col col-email"><?= e($r['customer_email'] ?? '') ?></td>
                <?php if ($showBranchColumn): ?><td class="sticky-col col-branch"><?= e($r['branch_name']) ?></td><?php endif; ?>
                <?php foreach ($months as $ym): ?>
                  <td class="month-col <?= $ym === $currentYm ? 'current-month' : '' ?>">
                    <?php if (in_array($ym, $r['covered_months'], true)): ?>
                      <input type="checkbox" class="lpr-check" data-rental-id="<?= $rid ?>" data-ym="<?= e($ym) ?>"
                             <?= ($checks[$rid][$ym] ?? false) ? 'checked' : '' ?>
                             <?= $canManage ? '' : 'disabled' ?>>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<input type="hidden" id="lpr-csrf-token" value="<?= e(csrf_token()) ?>">

<script>
(function () {
  // Scrolls the grid so the current-month column is the first one visible
  // past the fixed columns. Reading offsetWidth before the browser has
  // actually laid out this table returns 0 for every column and silently
  // no-ops, so this polls briefly until the wrapper reports a real width
  // rather than trusting any single "ready" signal (load/rAF/etc.) to
  // always fire before that's true.
  var wrap = document.getElementById('lpr-grid-wrap');
  if (wrap) {
    var attempts = 0;
    var tryPosition = function () {
      attempts++;
      // scrollWidth > clientWidth is the actual "ready to scroll" condition
      // — clientWidth alone can be non-zero even before layout/CSS has
      // finished settling, giving a false "ready" reading too early.
      if (wrap.scrollWidth <= wrap.clientWidth) {
        if (attempts < 40) { setTimeout(tryPosition, 100); }
        return;
      }

      var ths = Array.prototype.slice.call(document.querySelectorAll('.lpr-grid thead th'));
      var currentIdx = -1;
      var stickyWidth = 0;
      ths.forEach(function (th, i) {
        if (th.classList.contains('sticky-col')) { stickyWidth += th.offsetWidth; }
        if (th.classList.contains('current-month')) { currentIdx = i; }
      });
      if (currentIdx > -1) {
        var offset = 0;
        for (var i = 0; i < currentIdx; i++) { offset += ths[i].offsetWidth; }
        wrap.scrollLeft = Math.max(0, offset - stickyWidth);
      }
    };
    tryPosition();
  }

  var csrfToken = document.getElementById('lpr-csrf-token').value;
  document.querySelectorAll('.lpr-check').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var rentalId = cb.getAttribute('data-rental-id');
      var ym = cb.getAttribute('data-ym');
      var wasChecked = cb.checked;
      cb.disabled = true;

      fetch('/lpr-rentals/' + rentalId + '/checks/' + ym + '/toggle', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_csrf=' + encodeURIComponent(csrfToken),
        credentials: 'same-origin'
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok || result.data.error) {
            cb.checked = !wasChecked;
            alert(result.data.error || 'Could not update. Please try again.');
          } else {
            cb.checked = result.data.checked;
          }
        })
        .catch(function () {
          cb.checked = !wasChecked;
          alert('Could not reach the server. Please try again.');
        })
        .finally(function () {
          cb.disabled = false;
        });
    });
  });
})();
</script>
