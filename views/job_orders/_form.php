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
      <input class="form-control" type="file" id="quotation_file" name="quotation_file" accept="<?= e(upload_accept_attr()) ?>">
      <?php if ($isEdit && !empty($jobOrder['quotation_file_path'])): ?>
        <div style="margin-top:6px; font-size:12px;">
          Current: <a href="/<?= e($jobOrder['quotation_file_path']) ?>" target="_blank" rel="noopener"><?= e($jobOrder['quotation_file_original_name'] ?? 'view file') ?></a>
          <span style="color:var(--color-text-muted);">(upload a new file to replace)</span>
        </div>
      <?php endif; ?>
      <button type="button" id="read-quotation-btn" class="btn btn-outline" style="margin-top:8px; padding:6px 12px; font-size:13px;" disabled>
        Read Quotation &amp; Auto-Fill
      </button>
      <div id="read-quotation-status" style="margin-top:6px; font-size:12px;"></div>
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

  <div class="form-group">
    <label class="form-label">Other Documents (optional)</label>
    <input class="form-control" type="file" name="other_documents[]" accept="<?= e(upload_accept_attr()) ?>" multiple>
    <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">
      Attach any other supporting files for this job order - you can select multiple at once. Files upload alongside the rest of this form when you submit.
    </div>
    <?php if ($isEdit && !empty($documents)): ?>
      <ul style="margin:10px 0 0; padding-left:18px; font-size:13px;">
        <?php foreach ($documents as $doc): ?>
          <li style="margin-bottom:4px;">
            <a href="/<?= e($doc['file_path']) ?>" target="_blank" rel="noopener"><?= e($doc['original_name']) ?></a>
            <span style="color:var(--color-text-muted);">- <?= e($doc['uploaded_by_name']) ?>, <?= e(format_date(substr($doc['created_at'], 0, 10))) ?></span>
            &nbsp;|&nbsp;
            <form method="POST" action="/job-orders/<?= (int) $jobOrder['id'] ?>/documents/<?= (int) $doc['id'] ?>/delete" style="display:inline;"
                  onsubmit="return confirm('Delete this document? This cannot be undone.');">
              <?= csrf_field() ?>
              <button type="submit" style="background:none; border:none; padding:0; color: var(--color-danger); cursor:pointer; font-size:12px;">Delete</button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
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

  <?php
    // Resolve which assignees are actually pre-selected (old() values on a
    // validation-failure redisplay, else the job order's current assignee
    // list).
    $oldAssignees = old_array('assigned_to');
    $selectedUsers = $oldAssignees ?: array_map('strval', $assigneeIds ?? (isset($jobOrder['assigned_to']) ? [(string) $jobOrder['assigned_to']] : []));
  ?>
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="branch_id">Branch</label>
      <?php if (!empty($canSelectBranch)): ?>
        <select class="form-control" id="branch_id" name="branch_id" required>
          <option value="">-- Select Branch --</option>
          <?php $selectedBranch = old('branch_id', (string) ($jobOrder['branch_id'] ?? $myBranchId ?? '')); ?>
          <?php foreach ($branches as $b): ?>
            <option value="<?= (int) $b['id'] ?>" <?= $selectedBranch === (string) $b['id'] ? 'selected' : '' ?>>
              <?= e($b['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">
          Which branch this job order belongs to. Changing this narrows Assign To below to that branch's staff.
        </div>
      <?php else: ?>
        <input class="form-control" type="text" value="<?= e($myBranchName ?? 'No branch assigned') ?>" disabled>
        <input type="hidden" name="branch_id" value="<?= (int) ($myBranchId ?? 0) ?>">
      <?php endif; ?>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="assigned_to">Assign To (hold Ctrl/Cmd to select multiple)</label>
      <select class="form-control" id="assigned_to" name="assigned_to[]" multiple size="6" required>
        <?php foreach ($users as $u): ?>
          <option value="<?= (int) $u['id'] ?>" data-branch-id="<?= e((string) ($u['branch_id'] ?? '')) ?>" <?= in_array((string) $u['id'], $selectedUsers, true) ? 'selected' : '' ?>>
            <?= e($u['full_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">
        Select one or more people responsible for this job order.
      </div>
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

<script>
(function () {
  var isEditMode = <?= $isEdit ? 'true' : 'false' ?>;
  var fileInput = document.getElementById('quotation_file');
  var readBtn = document.getElementById('read-quotation-btn');
  var status = document.getElementById('read-quotation-status');
  var csrfToken = document.querySelector('input[name="_csrf"]').value;

  fileInput.addEventListener('change', function () {
    readBtn.disabled = !fileInput.files.length;
    status.textContent = '';
  });

  readBtn.addEventListener('click', function () {
    if (!fileInput.files.length) {
      return;
    }

    readBtn.disabled = true;
    readBtn.textContent = 'Reading...';
    status.textContent = '';

    var formData = new FormData();
    formData.append('_csrf', csrfToken);
    formData.append('quotation_file', fileInput.files[0]);

    fetch('/job-orders/extract-quotation', { method: 'POST', body: formData, credentials: 'same-origin' })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        var data = result.data;

        if (!result.ok || data.error) {
          status.style.color = 'var(--color-danger)';
          status.textContent = data.error || 'Could not read the file. Please fill in the fields manually.';
          return;
        }

        var filled = [];
        var missed = [];

        if (data.quotation_no) {
          document.getElementById('quotation_no').value = data.quotation_no;
          filled.push('Quotation No');
        } else {
          missed.push('Quotation No');
        }

        if (data.customer_name) {
          document.getElementById('customer_name').value = data.customer_name;
          filled.push('Customer Name');
        } else {
          missed.push('Customer Name');
        }

        if (data.subject) {
          document.getElementById('subject').value = data.subject;
          filled.push('Subject');
        } else {
          missed.push('Subject');
        }

        if (data.total_cost) {
          document.getElementById('total_cost').value = data.total_cost;
          filled.push('Total Cost');
        } else {
          missed.push('Total Cost');
        }

        // Job Start Date only auto-fills to today on a brand-new job order —
        // on Edit it would silently overwrite the real (possibly past)
        // start date just because someone re-uploaded a corrected file.
        if (!isEditMode) {
          var today = new Date();
          var iso = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
          document.getElementById('job_start_date').value = iso;
          filled.push('Job Start Date');
        }

        if (data.note) {
          status.style.color = 'var(--color-text-muted)';
          status.textContent = data.note;
        } else if (missed.length) {
          status.style.color = 'var(--color-amber, #D97706)';
          status.textContent = (filled.length ? 'Filled: ' + filled.join(', ') + '. ' : '')
            + 'Please check/enter manually: ' + missed.join(', ') + '.';
        } else {
          status.style.color = 'var(--color-success)';
          status.textContent = 'Auto-filled from the quotation - please double-check before submitting.';
        }
      })
      .catch(function () {
        status.style.color = 'var(--color-danger)';
        status.textContent = 'Could not reach the server. Please fill in the fields manually.';
      })
      .finally(function () {
        readBtn.disabled = false;
        readBtn.textContent = 'Read Quotation & Auto-Fill';
      });
  });

  // Branch dropdown (Admin/Manager) narrows the Assign To list. Assign To
  // is a multi-select, so narrowing must not blindly clear every pick —
  // it only hides non-matching options, and deselects any option that
  // becomes hidden (a hidden-but-still-selected option would silently
  // submit a person outside the branch Branch now says this is scoped to).
  var branchSelect = document.getElementById('branch_id');
  var assignSelect = document.getElementById('assigned_to');
  if (branchSelect && assignSelect) {
    var applyBranchFilter = function () {
      var selectedBranch = branchSelect.value;
      Array.prototype.forEach.call(assignSelect.options, function (opt) {
        if (!opt.value) { return; }
        var branchId = opt.getAttribute('data-branch-id') || '';
        var shouldHide = selectedBranch !== '' && branchId !== '' && branchId !== selectedBranch;
        opt.hidden = shouldHide;
        if (shouldHide) { opt.selected = false; }
      });
    };
    // Deliberately NOT run on page load: an existing job order's assignees
    // were picked under whatever branch applied at the time, so
    // auto-filtering on load could silently deselect/hide real existing
    // assignees the moment the page opens.
    branchSelect.addEventListener('change', applyBranchFilter);
  }
})();
</script>
