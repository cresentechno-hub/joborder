<h2 style="margin-top:0;">Edit Permissions - <?= e($role['name']) ?></h2>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  Check the permissions this role should have. Unchecking a permission takes it away from every user with this role immediately.
</p>

<?php if ($role['name'] === 'Admin'): ?>
  <div class="alert alert-error" style="background:#FFFBEB; color:#92400E; border-color:#FDE68A;">
    The <strong>role.manage</strong> permission is always kept on Admin so RBAC can still be configured afterwards.
  </div>
<?php endif; ?>

<div class="card">
  <form method="POST" action="/roles/<?= (int) $role['id'] ?>/permissions">
    <?= csrf_field() ?>
    <table class="perm-matrix">
      <thead>
        <tr><th>Permission</th><th style="width:80px;">Granted</th></tr>
      </thead>
      <tbody>
        <?php foreach ($permissions as $p): ?>
          <tr>
            <td>
              <div style="font-weight:600;"><?= e($p['code']) ?></div>
              <div style="color: var(--color-text-muted); font-size:12px;"><?= e($p['description'] ?? '') ?></div>
            </td>
            <td>
              <input type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>"
                     <?= in_array((int) $p['id'], $grantedPermIds, true) ? 'checked' : '' ?>
                     <?= ($role['name'] === 'Admin' && $p['code'] === 'role.manage') ? 'disabled' : '' ?>
                     style="width:16px; height:16px;">
              <?php if ($role['name'] === 'Admin' && $p['code'] === 'role.manage'): ?>
                <input type="hidden" name="permissions[]" value="<?= (int) $p['id'] ?>">
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div style="display:flex; gap:12px; margin-top:16px;">
      <button type="submit" class="btn btn-primary">Save Permissions</button>
      <a href="/roles" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
