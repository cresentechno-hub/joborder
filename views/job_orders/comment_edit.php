<?php $commentError = flash('comment_error'); ?>

<h2 style="margin-top:0;">Edit Comment</h2>
<p style="color: var(--color-text-muted); margin-top:-8px;">
  For job order <strong><?= e($jobOrder['quotation_no']) ?></strong> - <?= e($jobOrder['customer_name']) ?>
</p>

<?php if ($commentError): ?>
  <div class="alert alert-error"><?= e($commentError) ?></div>
<?php endif; ?>

<div class="card">
  <form method="POST" action="/job-orders/<?= (int) $jobOrder['id'] ?>/comments/<?= (int) $comment['id'] ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="comment_stage_id">Stage</label>
        <select class="form-control" id="comment_stage_id" name="comment_stage_id" required>
          <?php $selectedStage = old('comment_stage_id', (string) $comment['stage_id']); ?>
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
            $selectedAssigneeStrings = $oldAssignees ?: array_map('strval', $selectedAssignees);
          ?>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= in_array((string) $u['id'], $selectedAssigneeStrings, true) ? 'selected' : '' ?>>
              <?= e($u['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="comment_cc_users">CC To (optional - hold Ctrl/Cmd to select multiple)</label>
      <select class="form-control" id="comment_cc_users" name="comment_cc_users[]" multiple size="5">
        <?php $selectedCcRaw = $selectedCc; $oldCc = old_array('comment_cc_users'); $selectedCcStrings = $oldCc ?: array_map('strval', $selectedCcRaw); ?>
        <?php foreach ($users as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= in_array((string) $u['id'], $selectedCcStrings, true) ? 'selected' : '' ?>>
            <?= e($u['full_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label" for="comment_invoice_file">Upload Invoice</label>
        <?php if (!empty($comment['invoice_file_path'])): ?>
          <div style="margin-bottom:6px; font-size:12px;">
            Current: <a href="/<?= e($comment['invoice_file_path']) ?>" target="_blank" rel="noopener"><?= e($comment['invoice_file_original_name'] ?? 'view file') ?></a>
            <span style="color: var(--color-text-muted);">(upload a new file to replace)</span>
          </div>
        <?php endif; ?>
        <input class="form-control" type="file" id="comment_invoice_file" name="comment_invoice_file" accept="<?= e(upload_accept_attr()) ?>">
        <button type="button" id="read-invoice-btn" class="btn btn-outline" style="margin-top:8px; padding:6px 12px; font-size:13px;" disabled>
          Read Invoice &amp; Auto-Fill
        </button>
      </div>
      <div class="form-group">
        <label class="form-label" for="comment_invoice_no">Invoice No</label>
        <input class="form-control" type="text" id="comment_invoice_no" name="comment_invoice_no"
               value="<?= old('comment_invoice_no', e($comment['invoice_no'] ?? '')) ?>" placeholder="Auto-filled from the invoice file name">
        <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">
          The stored invoice file is renamed to <code>INV-{Invoice No}</code>.
        </div>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="comment_do_file">Upload DO (Delivery Order)</label>
      <?php if (!empty($comment['do_file_path'])): ?>
        <div style="margin-bottom:6px; font-size:12px;">
          Current: <a href="/<?= e($comment['do_file_path']) ?>" target="_blank" rel="noopener"><?= e($comment['do_file_original_name'] ?? 'view file') ?></a>
          <span style="color: var(--color-text-muted);">(upload a new file to replace)</span>
        </div>
      <?php endif; ?>
      <input class="form-control" type="file" id="comment_do_file" name="comment_do_file" accept="<?= e(upload_accept_attr()) ?>">
      <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">
        Requires Invoice No above (from this upload or typed in) - the stored DO file is renamed to <code>DO-{Invoice No}</code>.
      </div>
    </div>

    <div class="form-group">
      <label class="form-label" for="comment_remark">Remark</label>
      <textarea class="form-control" id="comment_remark" name="comment_remark" rows="3"
                placeholder="What changed / what's next"><?= old('comment_remark', e($comment['remark'] ?? '')) ?></textarea>
    </div>

    <div style="display:flex; gap:12px;">
      <button type="submit" class="btn btn-primary">Save Changes</button>
      <a href="/job-orders/<?= (int) $jobOrder['id'] ?>/edit#comments" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<form method="POST" action="/job-orders/<?= (int) $jobOrder['id'] ?>/comments/<?= (int) $comment['id'] ?>/delete"
      style="margin-top:16px;"
      onsubmit="return confirm('Delete this comment? This cannot be undone. The job order\'s current stage/assignee will NOT be reverted.');">
  <?= csrf_field() ?>
  <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:13px;">
    Delete this comment
  </button>
</form>

<script>
(function () {
  var invoiceFile = document.getElementById('comment_invoice_file');
  var invoiceNo = document.getElementById('comment_invoice_no');
  var readInvoiceBtn = document.getElementById('read-invoice-btn');

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
