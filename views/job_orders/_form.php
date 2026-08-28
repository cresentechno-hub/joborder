<?php

/**
 * Shared create/edit form. Expects: $mode ('create'|'edit'), $stages,
 * $users, and $jobOrder (array, edit only).
 */

$errors = form_errors();
$jobOrder = $jobOrder ?? null;
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

<form method="POST" action="<?= $isEdit ? '/job-orders/' . (int) $jobOrder['id'] : '/job-orders' ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label">Quotation File <?= $isEdit ? '' : '<span style="color:var(--color-danger)">*</span>' ?></label>
      <input class="form-control" type="file" name="quotation_file" accept="<?= e(upload_accept_attr()) ?>">
      <?php if ($isEdit && !empty($jobOrder['quotation_file_path'])): ?>
        <div style="margin-top:6px; font-size:12px;">
          Current: <a href="/<?= e($jobOrder['quotation_file_path']) ?>" target="_blank" rel="noopener"><?= e($jobOrder['quotation_file_original_name'] ?? 'view file') ?></a>
          <span style="color:var(--color-text-muted);">(upload a new file to replace)</span>
        </div>
      <?php endif; ?>
    </div>

    <div class="form-group">
      <label class="form-label">PO File</label>
      <input class="form-control" type="file" name="po_file" accept="<?= e(upload_accept_attr()) ?>">
      <?php if ($isEdit && !empty($jobOrder['po_file_path'])): ?>
        <div style="margin-top:6px; font-size:12px;">
          Current: <a href="/<?= e($jobOrder['po_file_path']) ?>" target="_blank" rel="noopener"><?= e($jobOrder['po_file_original_name'] ?? 'view file') ?></a>
          <span style="color:var(--color-text-muted);">(upload a new file to replace)</span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="quotation_no">Quotation No</label>
      <input class="form-control" type="text" id="quotation_no" name="quotation_no"
             value="<?= old('quotation_no', e($jobOrder['quotation_no'] ?? '')) ?>"
             placeholder="e.g. QT-2026-0142" required>
    </div>
    <div class="form-group">
      <label class="form-label" for="customer_name">Customer Name</label>
      <input class="form-control" type="text" id="customer_name" name="customer_name"
             value="<?= old('customer_name', e($jobOrder['customer_name'] ?? '')) ?>"
             placeholder="e.g. Sunrise Trading Sdn Bhd" required>
    </div>
  </div>

  <div class="form-group">
    <label class="form-label" for="subject">Subject</label>
    <input class="form-control" type="text" id="subject" name="subject"
           value="<?= old('subject', e($jobOrder['subject'] ?? '')) ?>"
           placeholder="e.g. Supply of Office Furniture - HQ Level 3" required>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="total_cost">Total Cost (RM)</label>
      <input class="form-control" type="number" step="0.01" min="0" id="total_cost" name="total_cost"
             value="<?= old('total_cost', e((string) ($jobOrder['total_cost'] ?? ''))) ?>"
             placeholder="e.g. 15800.00" required>
    </div>
    <div class="form-group">
      <label class="form-label" for="job_start_date">Job Start Date</label>
      <input class="form-control" type="date" id="job_start_date" name="job_start_date"
             value="<?= old('job_start_date', e($jobOrder['job_start_date'] ?? '')) ?>" required>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="assigned_to">Assign To</label>
      <select class="form-control" id="assigned_to" name="assigned_to" required>
        <option value="">-- Select User --</option>
        <?php $selectedUser = old('assigned_to', (string) ($jobOrder['assigned_to'] ?? '')); ?>
        <?php foreach ($users as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= $selectedUser === (string) $u['id'] ? 'selected' : '' ?>>
            <?= e($u['full_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label" for="stage_id">Job Stage</label>
      <select class="form-control" id="stage_id" name="stage_id" required>
        <?php $selectedStage = old('stage_id', (string) ($jobOrder['stage_id'] ?? '')); ?>
        <?php foreach ($stages as $s): ?>
          <option value="<?= (int) $s['id'] ?>" <?= $selectedStage === (string) $s['id'] ? 'selected' : '' ?>>
            <?= e($s['stage_code']) ?> - <?= e($s['stage_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-group">
    <label class="form-label" for="remarks">Remarks (optional)</label>
    <textarea class="form-control" id="remarks" name="remarks" rows="3"
              placeholder="Any additional notes for this job order"><?= old('remarks', e($jobOrder['remarks'] ?? '')) ?></textarea>
  </div>

  <div style="display:flex; gap:12px;">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Job Order' ?></button>
    <a href="/job-orders" class="btn btn-outline">Cancel</a>
  </div>
</form>
