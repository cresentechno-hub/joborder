<h2 style="margin-top:0;">Welcome, <?= e($user['full_name'] ?? '') ?></h2>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-value"><?= (int) $quotationsThisYear ?></div>
    <div class="stat-label">Quotations This Year</div>
  </div>
  <div class="stat-card">
    <div class="stat-value"><?= array_sum(array_column($stageCounts, 'total')) ?></div>
    <div class="stat-label">Active Job Orders</div>
  </div>
  <div class="stat-card <?= count($stuckJobs) > 0 ? 'stat-alert' : '' ?>">
    <div class="stat-value"><?= count($stuckJobs) ?></div>
    <div class="stat-label">Pending &gt; <?= (int) $alertDays ?> Days in Stage</div>
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <h3 style="margin-top:0;">Pending &gt; <?= (int) $alertDays ?> Days in Current Stage</h3>
  <?php if (empty($stuckJobs)): ?>
    <p style="color: var(--color-text-muted); font-size:13px;">Nothing is stuck right now.</p>
  <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr><th>Quotation No</th><th>Customer</th><th>Stage</th><th style="text-align:center;">Days</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($stuckJobs as $job): ?>
            <tr>
              <td><?= e($job['quotation_no']) ?></td>
              <td><?= e($job['customer_name']) ?></td>
              <td>
                <span class="badge" style="background:<?= e($job['stage_color'] ?: '#64748B') ?>;">
                  <?= e($job['stage_code']) ?> - <?= e($job['stage_name']) ?>
                </span>
              </td>
              <td style="text-align:center; color: var(--color-danger); font-weight:600;"><?= (int) $job['days_in_current_stage'] ?></td>
              <td><a href="/job-orders/<?= (int) $job['id'] ?>/edit">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($renewals) || \App\Core\Auth::can('lpr_rental.view') || \App\Core\Auth::can('smc.view')): ?>
<div class="card" style="margin-top:20px;">
  <h3 style="margin-top:0;">Contract Renewals <span style="color: var(--color-text-muted); font-weight:400; font-size:13px;">(LPR &amp; SMC, within <?= (int) $renewalMonths ?> months)</span></h3>
  <?php if (empty($renewals)): ?>
    <p style="color: var(--color-text-muted); font-size:13px;">Nothing coming up for renewal.</p>
  <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="data-table">
        <thead>
          <tr><th>Module</th><th>Customer</th><th>Partner</th><th>End Date</th><th>Days Left</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($renewals as $r): ?>
            <?php
              $daysLeft = (int) floor((strtotime($r['end_date']) - strtotime(date('Y-m-d'))) / 86400);
              $daysColor = $daysLeft < 0 ? 'var(--color-danger)' : ($daysLeft <= 30 ? 'var(--color-amber, #D97706)' : 'var(--color-text)');
              $daysLabel = $daysLeft < 0 ? abs($daysLeft) . ' days overdue' : ($daysLeft === 0 ? 'Today' : $daysLeft . ' days');
            ?>
            <tr>
              <td><span class="badge-outline"><?= e($r['module']) ?></span></td>
              <td><?= e($r['customer']) ?></td>
              <td><?= $r['partner'] ? e($r['partner']) : '<span style="color:var(--color-text-muted);">—</span>' ?></td>
              <td><?= e(format_date($r['end_date'])) ?></td>
              <td style="color:<?= $daysColor ?>; font-weight:600;"><?= e($daysLabel) ?></td>
              <td><a href="<?= e($r['url']) ?>">View</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>
