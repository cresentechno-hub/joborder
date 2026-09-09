<?php $success = flash('success'); $error = flash('error'); ?>

<h2 style="margin-top:0;">Manage LPR Renewal Recipients</h2>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  Exactly who receives the LPR renewal reminder email/notification, regardless of their role's permissions.
  Each person selected still only sees renewals for their own branch (or every branch, if their role grants that) —
  this list only controls who's considered at all. SMC renewal reminders are unaffected by this page; those still
  go to everyone with SMC management permission.
</p>

<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <form method="POST" action="/settings/lpr-renewal-recipients">
    <?= csrf_field() ?>

    <?php if (empty($users)): ?>
      <p style="color: var(--color-text-muted);">No active users to choose from.</p>
    <?php else: ?>
      <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:10px; margin-bottom:20px;">
        <?php foreach ($users as $u): ?>
          <label style="display:flex; align-items:center; gap:8px; font-size:14px; padding:8px 10px; border:1px solid var(--color-border); border-radius:var(--radius); cursor:pointer;">
            <input type="checkbox" name="user_ids[]" value="<?= (int) $u['id'] ?>"
                   <?= in_array((int) $u['id'], $selectedUserIds, true) ? 'checked' : '' ?>>
            <?= e($u['full_name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary">Save Recipients</button>
    <a href="/settings" class="btn btn-outline">Back to Settings</a>
  </form>
</div>
