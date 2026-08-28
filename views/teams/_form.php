<?php

/**
 * Shared create/edit form. Expects: $mode ('create'|'edit'), and $team
 * (array, edit only).
 */

$errors = form_errors();
$team = $team ?? null;
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

<form method="POST" action="<?= $isEdit ? '/teams/' . (int) $team['id'] : '/teams' ?>">
  <?= csrf_field() ?>

  <div class="form-group">
    <label class="form-label" for="name">Team Name</label>
    <input class="form-control" type="text" id="name" name="name"
           value="<?= old('name', e($team['name'] ?? '')) ?>"
           placeholder="e.g. Team A" required>
  </div>

  <div class="form-group">
    <label class="form-label" for="description">Description (optional)</label>
    <input class="form-control" type="text" id="description" name="description"
           value="<?= old('description', e($team['description'] ?? '')) ?>"
           placeholder="e.g. Northern region sales team">
  </div>

  <div style="display:flex; gap:12px;">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Team' ?></button>
    <a href="/teams" class="btn btn-outline">Cancel</a>
  </div>
</form>
