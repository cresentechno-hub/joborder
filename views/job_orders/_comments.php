<?php

/**
 * Comment/update log section for the job order edit page.
 * Expects: $jobOrder, $stages, $users, $comments (all already in scope
 * from JobOrderController::edit()).
 */

$commentError = flash('comment_error');
$forceOpen = $commentError !== null;
?>

<div id="comments" class="card" style="margin-top:20px;">
  <div style="display:flex; justify-content:space-between; align-items:center;">
    <h3 style="margin:0;">Comments &amp; Updates</h3>
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
          <label class="form-label" for="comment_assigned_to">Assign To</label>
          <select class="form-control" id="comment_assigned_to" name="comment_assigned_to" required>
            <option value="">-- Select User --</option>
            <?php $selectedAssignee = old('comment_assigned_to', (string) $jobOrder['assigned_to']); ?>
            <?php foreach ($users as $u): ?>
              <option value="<?= (int) $u['id'] ?>" <?= $selectedAssignee === (string) $u['id'] ? 'selected' : '' ?>>
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

  <div style="margin-top:20px;">
    <?php if (empty($comments)): ?>
      <p style="color: var(--color-text-muted); font-size:13px;">No comments yet.</p>
    <?php else: ?>
      <?php foreach ($comments as $c): ?>
        <div style="border-top:1px solid var(--color-border); padding:14px 0;">
          <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; align-items:center;">
            <div>
              <span class="badge" style="background:<?= e($c['stage_color'] ?: '#64748B') ?>;">
                <?= e($c['stage_code']) ?> - <?= e($c['stage_name']) ?>
              </span>
              <span style="margin-left:8px; font-size:13px; color: var(--color-slate);">Assigned to <?= e($c['assigned_to_name']) ?></span>
            </div>
            <div style="font-size:12px; color: var(--color-text-muted); white-space:nowrap;">
              <?= e($c['created_at']) ?> by <?= e($c['created_by_name']) ?>
              &nbsp;|&nbsp;
              <a href="/job-orders/<?= (int) $jobOrder['id'] ?>/comments/<?= (int) $c['id'] ?>/edit">Edit</a>
              &nbsp;|&nbsp;
              <form method="POST" action="/job-orders/<?= (int) $jobOrder['id'] ?>/comments/<?= (int) $c['id'] ?>/delete" style="display:inline;"
                    onsubmit="return confirm('Delete this comment? This cannot be undone.');">
                <?= csrf_field() ?>
                <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:12px;">Delete</button>
              </form>
            </div>
          </div>

          <?php if (!empty($c['remark'])): ?>
            <p style="margin:8px 0 0; font-size:13px;"><?= nl2br(e($c['remark'])) ?></p>
          <?php endif; ?>

          <div style="margin-top:8px; font-size:12px; display:flex; flex-wrap:wrap; gap:16px;">
            <?php if (!empty($c['invoice_file_path'])): ?>
              <a href="/<?= e($c['invoice_file_path']) ?>" target="_blank" rel="noopener">
                Invoice<?= $c['invoice_no'] ? ' (' . e($c['invoice_no']) . ')' : '' ?>: <?= e($c['invoice_file_original_name']) ?>
              </a>
            <?php elseif (!empty($c['invoice_no'])): ?>
              <span style="color: var(--color-text-muted);">Invoice No: <?= e($c['invoice_no']) ?></span>
            <?php endif; ?>

            <?php if (!empty($c['do_file_path'])): ?>
              <a href="/<?= e($c['do_file_path']) ?>" target="_blank" rel="noopener">
                DO: <?= e($c['do_file_original_name']) ?>
              </a>
            <?php endif; ?>

            <?php if (!empty($c['cc_names'])): ?>
              <span style="color: var(--color-text-muted);">CC: <?= e(implode(', ', $c['cc_names'])) ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
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
