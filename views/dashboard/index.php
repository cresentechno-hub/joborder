<?php
$stageTotals = array_map(static fn ($s) => (int) $s['total'], $stageCounts);
$maxStageCount = $stageTotals ? max(1, ...$stageTotals) : 1;
?>

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

<div class="form-row">
  <div class="card">
    <h3 style="margin-top:0;">Jobs by Stage</h3>
    <?php foreach ($stageCounts as $sc): ?>
      <div class="stage-bar-row">
        <div class="stage-bar-label"><?= e($sc['stage_code']) ?> - <?= e($sc['stage_name']) ?></div>
        <div class="stage-bar-track">
          <div class="stage-bar-fill" style="width: <?= (int) round(((int) $sc['total'] / $maxStageCount) * 100) ?>%; background: <?= e($sc['stage_color'] ?: '#64748B') ?>;"></div>
        </div>
        <div class="stage-bar-count"><?= (int) $sc['total'] ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
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
</div>
