<?php $errors = form_errors(); ?>

<h2 style="margin-top:0;">New Role</h2>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  After creating the role, you'll be taken straight to its Permissions page to decide what it can do.
</p>

<?php if (!empty($errors)): ?>
  <div class="alert alert-error">
    <ul style="margin:0; padding-left:18px;">
      <?php foreach ($errors as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="card">
  <form method="POST" action="/roles">
    <?= csrf_field() ?>

    <div class="form-group">
      <label class="form-label" for="name">Role Name</label>
      <input class="form-control" type="text" id="name" name="name"
             value="<?= old('name') ?>" placeholder="e.g. Finance" required>
    </div>

    <div class="form-group">
      <label class="form-label" for="description">Description (optional)</label>
      <input class="form-control" type="text" id="description" name="description"
             value="<?= old('description') ?>" placeholder="e.g. Issues invoices and tracks payments">
    </div>

    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn btn-primary">Create Role</button>
      <a href="/roles" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>
