<?php

/**
 * Shared create/edit form. Expects: $mode ('create'|'edit'), $roles,
 * $branches, and $targetUser (array, edit only).
 */

$errors = form_errors();
$targetUser = $targetUser ?? null;
$isEdit = $mode === 'edit';
?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <ul style="margin:0; padding-left:18px;">
      <?php foreach ($errors as $msg): ?>
        <li><?= e($msg) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form method="POST" action="<?= $isEdit ? '/users/' . (int) $targetUser['id'] : '/users' ?>">
  <?= csrf_field() ?>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="full_name">Full Name</label>
      <input class="form-control" type="text" id="full_name" name="full_name"
             value="<?= old('full_name', e($targetUser['full_name'] ?? '')) ?>"
             placeholder="e.g. Andy Yoon" required>
    </div>
    <div class="form-group">
      <label class="form-label" for="username">Username</label>
      <?php if ($isEdit): ?>
        <input class="form-control" type="text" value="<?= e($targetUser['username']) ?>" disabled>
        <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">Username cannot be changed.</div>
      <?php else: ?>
        <input class="form-control" type="text" id="username" name="username"
               value="<?= old('username') ?>" placeholder="e.g. andyyoon" required>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="email">Email</label>
      <input class="form-control" type="email" id="email" name="email"
             value="<?= old('email', e($targetUser['email'] ?? '')) ?>"
             placeholder="e.g. andyyoon@cresentech.com.my" required>
    </div>
    <div class="form-group">
      <label class="form-label" for="role_id">Role</label>
      <select class="form-control" id="role_id" name="role_id" required>
        <?php $selectedRole = old('role_id', (string) ($targetUser['role_id'] ?? '')); ?>
        <?php foreach ($roles as $r): ?>
          <option value="<?= (int) $r['id'] ?>" <?= $selectedRole === (string) $r['id'] ? 'selected' : '' ?>>
            <?= e($r['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-group">
    <label class="form-label" for="branch_id">Branch (optional)</label>
    <select class="form-control" id="branch_id" name="branch_id">
      <option value="">-- No Branch --</option>
      <?php $selectedBranch = old('branch_id', (string) ($targetUser['branch_id'] ?? '')); ?>
      <?php foreach ($branches as $b): ?>
        <option value="<?= (int) $b['id'] ?>" <?= $selectedBranch === (string) $b['id'] ? 'selected' : '' ?>>
          <?= e($b['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">
      Only affects Sales-role users: they only see job orders/LPR rentals/SMC contracts belonging to their own
      branch. Manage branches under <a href="/branches">Branches</a>.
    </div>
  </div>

  <div class="form-group">
    <label class="form-label" for="password">Password <?= $isEdit ? '(leave blank to keep current)' : '' ?></label>
    <input class="form-control" type="password" id="password" name="password"
           placeholder="<?= $isEdit ? 'New password (optional)' : 'Min. 8 characters' ?>" <?= $isEdit ? '' : 'required' ?>>
  </div>

  <div style="display:flex; gap:12px;">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create User' ?></button>
    <a href="/users" class="btn btn-outline">Cancel</a>
  </div>
</form>
