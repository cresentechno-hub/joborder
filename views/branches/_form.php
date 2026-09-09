<?php

/**
 * Shared create/edit form. Expects: $mode ('create'|'edit'), and $branch
 * (array, edit only).
 */

$errors = form_errors();
$branch = $branch ?? null;
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

<form method="POST" action="<?= $isEdit ? '/branches/' . (int) $branch['id'] : '/branches' ?>">
  <?= csrf_field() ?>

  <div class="form-group">
    <label class="form-label" for="name">Branch Name</label>
    <input class="form-control" type="text" id="name" name="name"
           value="<?= old('name', e($branch['name'] ?? '')) ?>"
           placeholder="e.g. Penang Branch" required>
  </div>

  <div style="display:flex; gap:12px;">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Branch' ?></button>
    <a href="/branches" class="btn btn-outline">Cancel</a>
  </div>
</form>
