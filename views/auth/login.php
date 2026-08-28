<?php $error = flash('error'); ?>

<?php if ($error): ?>
  <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<form method="POST" action="/login">
  <?= csrf_field() ?>

  <div class="form-group">
    <label class="form-label" for="username">Username</label>
    <input class="form-control" type="text" id="username" name="username" value="<?= old('username') ?>" required autofocus>
  </div>

  <div class="form-group">
    <label class="form-label" for="password">Password</label>
    <input class="form-control" type="password" id="password" name="password" required>
  </div>

  <button type="submit" class="btn btn-primary" style="width:100%;">Sign In</button>
</form>
