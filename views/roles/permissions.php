<h2 style="margin-top:0;">Edit Permissions - <?= e($role['name']) ?></h2>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  Grouped by module — check "Full module access" to grant everything in that module at once, or pick individual
  permissions. Unchecking a permission takes it away from every user with this role immediately.
</p>

<?php if ($role['name'] === 'Admin'): ?>
  <div class="alert alert-error" style="background:#FFFBEB; color:#92400E; border-color:#FDE68A;">
    The <strong>role.manage</strong> permission is always kept on Admin so RBAC can still be configured afterwards.
  </div>
<?php endif; ?>

<form method="POST" action="/roles/<?= (int) $role['id'] ?>/permissions">
  <?= csrf_field() ?>

  <?php foreach ($groupedPermissions as $gi => $group): ?>
    <div class="card" style="margin-bottom:16px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
        <h3 style="margin:0;"><?= e($group['label']) ?></h3>
        <label style="display:flex; align-items:center; gap:6px; font-size:13px; font-weight:600; color: var(--color-slate); cursor:pointer;">
          <input type="checkbox" class="module-toggle" data-module-index="<?= (int) $gi ?>" style="width:16px; height:16px;">
          Full module access
        </label>
      </div>
      <table class="perm-matrix">
        <thead>
          <tr><th>Permission</th><th style="width:80px;">Granted</th></tr>
        </thead>
        <tbody>
          <?php foreach ($group['permissions'] as $p): ?>
            <tr>
              <td>
                <div style="font-weight:600;"><?= e($p['code']) ?></div>
                <div style="color: var(--color-text-muted); font-size:12px;"><?= e($p['description'] ?? '') ?></div>
              </td>
              <td>
                <input type="checkbox" name="permissions[]" value="<?= (int) $p['id'] ?>"
                       class="perm-checkbox" data-module-index="<?= (int) $gi ?>"
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
    </div>
  <?php endforeach; ?>

  <div style="display:flex; gap:12px; margin-top:16px;">
    <button type="submit" class="btn btn-primary">Save Permissions</button>
    <a href="/roles" class="btn btn-outline">Cancel</a>
  </div>
</form>

<script>
(function () {
  // Keeps each module's "Full module access" checkbox in sync with its
  // individual permission checkboxes in both directions: toggling it
  // checks/unchecks everything in that module, and it reflects
  // checked/indeterminate/unchecked based on what's actually granted.
  var moduleToggles = document.querySelectorAll('.module-toggle');

  function permsForModule(idx) {
    return Array.prototype.slice.call(document.querySelectorAll('.perm-checkbox[data-module-index="' + idx + '"]'));
  }

  function syncToggleState(toggle) {
    var idx = toggle.getAttribute('data-module-index');
    var perms = permsForModule(idx).filter(function (cb) { return !cb.disabled; });
    var checkedCount = perms.filter(function (cb) { return cb.checked; }).length;
    toggle.checked = perms.length > 0 && checkedCount === perms.length;
    toggle.indeterminate = checkedCount > 0 && checkedCount < perms.length;
  }

  moduleToggles.forEach(function (toggle) {
    syncToggleState(toggle);
    toggle.addEventListener('change', function () {
      var idx = toggle.getAttribute('data-module-index');
      permsForModule(idx).forEach(function (cb) {
        if (!cb.disabled) { cb.checked = toggle.checked; }
      });
      toggle.indeterminate = false;
    });
  });

  document.querySelectorAll('.perm-checkbox').forEach(function (cb) {
    cb.addEventListener('change', function () {
      var idx = cb.getAttribute('data-module-index');
      var toggle = document.querySelector('.module-toggle[data-module-index="' + idx + '"]');
      if (toggle) { syncToggleState(toggle); }
    });
  });
})();
</script>
