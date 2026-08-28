<h2 style="margin-top:0;">Activity Log</h2>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  A trace of who did what: logins/logouts, and every create/update/delete/permission change across the system.
</p>

<form method="GET" action="/activity-log" style="display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
  <select class="form-control" name="action" style="max-width:280px;">
    <option value="">All Actions</option>
    <?php foreach ($actions as $a): ?>
      <option value="<?= e($a) ?>" <?= ($filters['action'] ?? '') === $a ? 'selected' : '' ?>><?= e($a) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-control" name="user_id" style="max-width:240px;">
    <option value="">All Users</option>
    <?php foreach ($users as $u): ?>
      <option value="<?= (int) $u['id'] ?>" <?= (int) ($filters['user_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
        <?= e($u['full_name']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-outline">Filter</button>
</form>

<div class="card" style="padding:0; overflow-x:auto;">
  <table class="data-table">
    <thead>
      <tr>
        <th>Timestamp</th>
        <th>User</th>
        <th>Action</th>
        <th>Entity</th>
        <th>IP Address</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="5" style="padding:24px; text-align:center; color:var(--color-text-muted);">No activity recorded yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($logs as $log): ?>
        <tr>
          <td><?= e($log['created_at']) ?></td>
          <td><?= $log['user_name'] ? e($log['user_name']) : '<span style="color:var(--color-text-muted);">System</span>' ?></td>
          <td><span class="badge-outline"><?= e($log['action']) ?></span></td>
          <td>
            <?php if ($log['entity_type']): ?>
              <?= e($log['entity_type']) ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?>
            <?php else: ?>
              <span style="color:var(--color-text-muted);">—</span>
            <?php endif; ?>
          </td>
          <td><?= $log['ip_address'] ? e($log['ip_address']) : '-' ?></td>
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
      <?php $qs = http_build_query(array_merge(array_filter($filters), ['page' => $p])); ?>
      <a href="/activity-log?<?= e($qs) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>
