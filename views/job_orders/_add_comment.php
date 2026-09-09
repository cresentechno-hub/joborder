<?php

/**
 * Quick-access "Add Comment" trigger + form, shown at the top of the job
 * order edit page so it doesn't require scrolling past the whole form to
 * reach it. The comment history log itself stays in views/job_orders/_comments.php,
 * further down the page.
 * Expects: $jobOrder, $stages, $users (already in scope from
 * JobOrderController::edit()).
 */

$commentError = flash('comment_error');
$forceOpen = $commentError !== null;
?>

<div id="add-comment" class="card" style="margin-bottom:20px;">
  <div style="display:flex; justify-content:space-between; align-items:center;">
    <h3 style="margin:0;">Add Comment</h3>
    <button type="button" id="add-comment-btn" class="btn btn-primary" style="padding:6px 14px; font-size:13px;">
      + Add Comment
    </button>
  </div>

  <div id="add-comment-form" style="margin-top:16px; <?= $forceOpen ? '' : 'display:none;' ?>">
    <?php if ($commentError): ?>
      <div class="alert alert-error"><?= e($commentError) ?></div>
    <?php endif; ?>

    <form method="POST" action="/job-orders/<?= (int) $jobOrder['id'] ?>/comments" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="comment_stage_id">Stage</label>
          <select class="form-control" id="comment_stage_id" name="comment_stage_id" required>
            <?php $selectedStage = old('comment_stage_id', (string) $jobOrder['stage_id']); ?>
            <?php foreach ($stages as $s): ?>
              <option value="<?= (int) $s['id'] ?>" <?= $selectedStage === (string) $s['id'] ? 'selected' : '' ?>>
                <?= e($s['stage_code']) ?> - <?= e($s['stage_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="comment_assigned_to">Assign To (hold Ctrl/Cmd to select multiple)</label>
          <select class="form-control" id="comment_assigned_to" name="comment_assigned_to[]" multiple size="5" required>
            <?php
              $oldAssignees = old_array('comment_assigned_to');
              $selectedAssignees = $oldAssignees ?: array_map('strval', \App\Models\JobOrder::getAssigneeIds((int) $jobOrder['id']));
            ?>
            <?php foreach ($users as $u): ?>
              <option value="<?= (int) $u['id'] ?>" <?= in_array((string) $u['id'], $selectedAssignees, true) ? 'selected' : '' ?>>
                <?= e($u['full_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="comment_cc_users">CC To (optional - hold Ctrl/Cmd to select multiple)</label>
        <select class="form-control" id="comment_cc_users" name="comment_cc_users[]" multiple size="5">
          <?php $selectedCc = old_array('comment_cc_users'); ?>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= in_array((string) $u['id'], $selectedCc, true) ? 'selected' : '' ?>>
              <?= e($u['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="comment_invoice_file">Upload Invoice</label>
          <input class="form-control" type="file" id="comment_invoice_file" name="comment_invoice_file" accept="<?= e(upload_accept_attr()) ?>">
          <button type="button" id="read-invoice-btn" class="btn btn-outline" style="margin-top:8px; padding:6px 12px; font-size:13px;" disabled>
            Read Invoice &amp; Auto-Fill
          </button>
        </div>
        <div class="form-group">
          <label class="form-label" for="comment_invoice_no">Invoice No</label>
          <input class="form-control" type="text" id="comment_invoice_no" name="comment_invoice_no"
                 value="<?= old('comment_invoice_no') ?>" placeholder="Auto-filled from the invoice file name">
          <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">
            The stored invoice file is renamed to <code>INV-{Invoice No}</code>.
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="comment_do_file">Upload DO (Delivery Order)</label>
        <input class="form-control" type="file" id="comment_do_file" name="comment_do_file" accept="<?= e(upload_accept_attr()) ?>">
        <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">
          Requires Invoice No above (from this upload or typed in) - the stored DO file is renamed to <code>DO-{Invoice No}</code>.
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="comment_remark">Remark</label>
        <textarea class="form-control" id="comment_remark" name="comment_remark" rows="3"
                  placeholder="What changed / what's next"><?= old('comment_remark') ?></textarea>
      </div>

      <div style="display:flex; gap:12px;">
        <button type="submit" class="btn btn-primary">Add Comment</button>
        <button type="button" id="cancel-comment-btn" class="btn btn-outline">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var toggleBtn = document.getElementById('add-comment-btn');
  var cancelBtn = document.getElementById('cancel-comment-btn');
  var form = document.getElementById('add-comment-form');
  var invoiceFile = document.getElementById('comment_invoice_file');
  var invoiceNo = document.getElementById('comment_invoice_no');
  var readInvoiceBtn = document.getElementById('read-invoice-btn');

  toggleBtn.addEventListener('click', function () {
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
  });

  cancelBtn.addEventListener('click', function () {
    form.style.display = 'none';
  });

  invoiceFile.addEventListener('change', function () {
    readInvoiceBtn.disabled = !invoiceFile.files.length;
  });

  readInvoiceBtn.addEventListener('click', function () {
    if (invoiceFile.files.length) {
      invoiceNo.value = invoiceFile.files[0].name.replace(/\.[^/.]+$/, '');
    }
  });
})();
</script>
