<?php

/**
 * Shared create/edit form. Expects: $mode ('create'|'edit'), and $customer
 * (array, edit only).
 */

$errors = form_errors();
$customer = $customer ?? null;
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

<form method="POST" action="<?= $isEdit ? '/customers/' . (int) $customer['id'] : '/customers' ?>">
  <?= csrf_field() ?>

  <div class="form-group">
    <label class="form-label" for="name">Customer Name</label>
    <input class="form-control" type="text" id="name" name="name"
           value="<?= old('name', e($customer['name'] ?? '')) ?>"
           placeholder="e.g. Sunrise Trading Sdn Bhd" required>
  </div>

  <div style="display:flex; gap:12px;">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Customer' ?></button>
    <a href="/customers" class="btn btn-outline">Cancel</a>
  </div>
</form>
