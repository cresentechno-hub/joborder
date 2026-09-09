<?php

/**
 * Shared create/edit form. Expects: $mode ('create'|'edit'), and $partner
 * (array, edit only).
 */

$errors = form_errors();
$partner = $partner ?? null;
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

<form method="POST" action="<?= $isEdit ? '/lpr-partners/' . (int) $partner['id'] : '/lpr-partners' ?>">
  <?= csrf_field() ?>

  <div class="form-group">
    <label class="form-label" for="name">Partner Name</label>
    <input class="form-control" type="text" id="name" name="name"
           value="<?= old('name', e($partner['name'] ?? '')) ?>"
           placeholder="e.g. ABC LPR Systems Sdn Bhd" required>
  </div>

  <div style="display:flex; gap:12px;">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Partner' ?></button>
    <a href="/lpr-partners" class="btn btn-outline">Cancel</a>
  </div>
</form>
