<?php $success = flash('success'); ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
  <h2 style="margin:0;">Job Orders</h2>
  <a href="/job-orders/create" class="btn btn-primary">+ New Job Order</a>
</div>

<?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<form method="GET" action="/job-orders" style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
  <input class="form-control" style="flex:1; min-width:220px;" type="text" name="q"
         value="<?= e($filters['q']) ?>" placeholder="Search quotation no, customer, subject...">
  <select class="form-control" name="stage_id" style="max-width:280px;">
    <option value="">All Stages</option>
    <?php foreach ($stages as $s): ?>
      <option value="<?= (int) $s['id'] ?>" <?= ((int) ($filters['stage_id'] ?? 0) === (int) $s['id']) ? 'selected' : '' ?>>
        <?= e($s['stage_code']) ?> - <?= e($s['stage_name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-outline">Filter</button>
</form>

<div class="card" style="padding:0; overflow-x:auto;">
  <table class="data-table">
    <thead>
      <tr>
        <th>Quotation No</th>
        <th>Customer</th>
        <th>Subject</th>
        <th style="text-align:right;">Total Cost (RM)</th>
        <th>Start Date</th>
        <th style="text-align:center;">Days</th>
        <th>Assigned To</th>
        <th>Stage</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($jobOrders)): ?>
        <tr><td colspan="9" style="padding:24px; text-align:center; color:var(--color-text-muted);">No job orders found.</td></tr>
      <?php endif; ?>
      <?php foreach ($jobOrders as $jo): ?>
        <tr>
          <td><?= e($jo['quotation_no']) ?></td>
          <td><?= e($jo['customer_name']) ?></td>
          <td class="wrap" style="min-width:200px;"><?= e($jo['subject']) ?></td>
          <td style="text-align:right;"><?= format_money((float) $jo['total_cost']) ?></td>
          <td><?= e(format_date($jo['job_start_date'])) ?></td>
          <td style="text-align:center;"><?= (int) $jo['days_elapsed'] ?></td>
          <td><?= e($jo['assigned_to_name']) ?></td>
          <td>
            <span class="badge" style="background:<?= e($jo['stage_color'] ?: '#64748B') ?>;">
              <?= e($jo['stage_code']) ?> - <?= e($jo['stage_name']) ?>
            </span>
          </td>
          <td style="white-space:nowrap;">
            <a href="/job-orders/<?= (int) $jo['id'] ?>/edit">Edit</a>
            <?php if (\App\Core\Auth::can('job_order.delete')): ?>
              &nbsp;|&nbsp;
              <form method="POST" action="/job-orders/<?= (int) $jo['id'] ?>/delete" style="display:inline;"
                    onsubmit="return confirm('Delete job order <?= e($jo['quotation_no']) ?>? This cannot be undone from the UI.');">
                <?= csrf_field() ?>
                <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:13px;">Delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php
$totalPages = (int) ceil($total / max(1, $perPage));
if ($totalPages > 1):
?>
  <div class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <?php $qs = http_build_query(array_merge($filters, ['page' => $p])); ?>
      <a href="/job-orders?<?= e($qs) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>
