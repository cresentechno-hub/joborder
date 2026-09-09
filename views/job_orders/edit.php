<?php $success = flash('success'); ?>

<h2 style="margin-top:0;">Edit Job Order</h2>

<?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<?php include ROOT_PATH . '/views/job_orders/_add_comment.php'; ?>

<div class="card">
  <?php $mode = 'edit'; include ROOT_PATH . '/views/job_orders/_form.php'; ?>
</div>

<?php include ROOT_PATH . '/views/job_orders/_comments.php'; ?>
