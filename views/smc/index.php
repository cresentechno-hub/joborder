<?php $success = flash('success'); $error = flash('error'); ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
  <h2 style="margin:0;">SMC</h2>
  <div style="display:flex; gap:10px; flex-wrap:wrap;">
    <a href="/smc/export" class="btn btn-outline">Export CSV</a>
    <?php if ($canManage): ?>
      <a href="/smc/import" class="btn btn-outline">Import</a>
      <a href="/smc/create" class="btn btn-primary">+ New Contract</a>
    <?php endif; ?>
  </div>
</div>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  Tracks the monthly schedule status for each SMC contract - Blank / SCH / DONE. The status columns scroll
  independently; Customer, Start Date, Coverage, Email and Actions stay fixed on the left.
</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<form method="GET" action="/smc" style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
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

<?php if (empty($contracts)): ?>
  <div class="card" style="text-align:center; padding:32px; color:var(--color-text-muted);">
    No SMC contracts yet.
    <?php if ($canManage): ?><br><a href="/smc/create">Create the first one</a>.<?php endif; ?>
  </div>
<?php else: ?>
  <div class="card" style="padding:0;">
    <div class="lpr-grid-wrap" id="smc-grid-wrap">
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
          <?php foreach ($contracts as $c): ?>
            <?php
              $cid = (int) $c['id'];
              $currentMonthStatus = $statuses[$cid][$currentYm] ?? '';
              $isAlertRow = $currentMonthStatus === 'SCH';
            ?>
            <tr class="<?= $isAlertRow ? 'row-alert' : '' ?>">
              <td class="sticky-col col-actions">
                <?php if ($canManage): ?>
                  <a href="/smc/<?= $cid ?>/edit">Edit</a>
                  &nbsp;|&nbsp;
                  <form method="POST" action="/smc/<?= $cid ?>/delete" style="display:inline;"
                        onsubmit="return confirm('Delete this SMC contract? This cannot be undone.');">
                    <?= csrf_field() ?>
                    <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:13px;">Delete</button>
                  </form>
                <?php endif; ?>
              </td>
              <td class="sticky-col col-customer"><?= e($c['customer_name']) ?></td>
              <td class="sticky-col col-start"><?= e(format_date($c['start_date'])) ?></td>
              <td class="sticky-col col-coverage"><?= (int) $c['coverage_months'] ?>mo</td>
              <td class="sticky-col col-email"><?= e($c['customer_email'] ?? '') ?></td>
              <?php if ($showBranchColumn): ?><td class="sticky-col col-branch"><?= e($c['branch_name']) ?></td><?php endif; ?>
              <?php foreach ($months as $ym): ?>
                <td class="month-col <?= $ym === $currentYm ? 'current-month' : '' ?>">
                  <?php if (in_array($ym, $c['covered_months'], true)): ?>
                    <?php $status = $statuses[$cid][$ym] ?? ''; ?>
                    <select class="smc-status-select status-<?= e(strtolower($status ?: 'blank')) ?>"
                            data-contract-id="<?= $cid ?>" data-ym="<?= e($ym) ?>"
                            <?= $canManage ? '' : 'disabled' ?>>
                      <option value="" <?= $status === '' ? 'selected' : '' ?>></option>
                      <option value="SCH" <?= $status === 'SCH' ? 'selected' : '' ?>>SCH</option>
                      <option value="DONE" <?= $status === 'DONE' ? 'selected' : '' ?>>DONE</option>
                    </select>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<input type="hidden" id="smc-csrf-token" value="<?= e(csrf_token()) ?>">

<script>
(function () {
  var wrap = document.getElementById('smc-grid-wrap');
  if (wrap) {
    var attempts = 0;
    var tryPosition = function () {
      attempts++;
      if (wrap.scrollWidth <= wrap.clientWidth) {
        if (attempts < 40) { setTimeout(tryPosition, 100); }
        return;
      }

      var ths = Array.prototype.slice.call(wrap.querySelectorAll('thead th'));
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

  var csrfToken = document.getElementById('smc-csrf-token').value;
  document.querySelectorAll('.smc-status-select').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var contractId = sel.getAttribute('data-contract-id');
      var ym = sel.getAttribute('data-ym');
      var newStatus = sel.value;
      var prevValue = sel.getAttribute('data-prev-value') || '';
      sel.disabled = true;

      fetch('/smc/' + contractId + '/status/' + ym, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_csrf=' + encodeURIComponent(csrfToken) + '&status=' + encodeURIComponent(newStatus),
        credentials: 'same-origin'
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok || result.data.error) {
            sel.value = prevValue;
            alert(result.data.error || 'Could not update. Please try again.');
          } else {
            sel.setAttribute('data-prev-value', result.data.status);
          }
          sel.className = 'smc-status-select status-' + (sel.value.toLowerCase() || 'blank');
        })
        .catch(function () {
          sel.value = prevValue;
          sel.className = 'smc-status-select status-' + (prevValue.toLowerCase() || 'blank');
          alert('Could not reach the server. Please try again.');
        })
        .finally(function () {
          sel.disabled = false;
        });
    });
    sel.setAttribute('data-prev-value', sel.value);
  });
})();
</script>
