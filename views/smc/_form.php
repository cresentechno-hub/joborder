<?php

/**
 * Shared create/edit form. Expects: $mode ('create'|'edit'), $customers,
 * $users, $coverageOptions, $canSelectBranch, $branches, $myBranchId,
 * $myBranchName, $contract (array, edit only), and $selectedCc (int[],
 * edit only).
 */

$errors = form_errors();
$contract = $contract ?? null;
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

<form method="POST" action="<?= $isEdit ? '/smc/' . (int) $contract['id'] : '/smc' ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <div class="form-group">
    <label class="form-label" for="customer_id">Customer</label>
    <select class="form-control" id="customer_id" name="customer_id" required>
      <option value="">-- Select Customer --</option>
      <?php $selectedCustomer = old('customer_id', (string) ($contract['customer_id'] ?? '')); ?>
      <?php foreach ($customers as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $selectedCustomer === (string) $c['id'] ? 'selected' : '' ?>>
          <?= e($c['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">
      Not listed? <a href="/customers/create" target="_blank" rel="noopener">Add a new customer</a> then refresh this page.
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="start_date">Contract Start Date</label>
      <input class="form-control" type="date" id="start_date" name="start_date"
             value="<?= old('start_date', e($contract['start_date'] ?? '')) ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label" for="coverage_months">Month Coverage</label>
      <select class="form-control" id="coverage_months" name="coverage_months" required>
        <option value="">-- Select Coverage --</option>
        <?php $selectedCoverage = old('coverage_months', (string) ($contract['coverage_months'] ?? '')); ?>
        <?php foreach ($coverageOptions as $months): ?>
          <option value="<?= (int) $months ?>" <?= $selectedCoverage === (string) $months ? 'selected' : '' ?>>
            <?= (int) $months ?> months
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="customer_email">Customer Email (optional)</label>
      <input class="form-control" type="email" id="customer_email" name="customer_email"
             value="<?= old('customer_email', e($contract['customer_email'] ?? '')) ?>"
             placeholder="e.g. accounts@customer.com">
    </div>
    <div class="form-group">
      <label class="form-label" for="payment_term">Payment Term (optional)</label>
      <input class="form-control" type="text" id="payment_term" name="payment_term"
             value="<?= old('payment_term', e($contract['payment_term'] ?? '')) ?>"
             placeholder="e.g. CASH">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="quotation_no">Quotation No (optional)</label>
      <input class="form-control" type="text" id="quotation_no" name="quotation_no"
             value="<?= old('quotation_no', e($contract['quotation_no'] ?? '')) ?>">
    </div>
    <div class="form-group">
      <label class="form-label" for="short_name">Short Name (optional)</label>
      <input class="form-control" type="text" id="short_name" name="short_name"
             value="<?= old('short_name', e($contract['short_name'] ?? '')) ?>">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="site">Site (optional)</label>
      <input class="form-control" type="text" id="site" name="site"
             value="<?= old('site', e($contract['site'] ?? '')) ?>">
    </div>
    <div class="form-group">
      <label class="form-label" for="service_frequency">Service Frequency (optional)</label>
      <input class="form-control" type="text" id="service_frequency" name="service_frequency"
             value="<?= old('service_frequency', e($contract['service_frequency'] ?? '')) ?>"
             placeholder="e.g. Quarterly">
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="service_date">Service Date (optional)</label>
      <input class="form-control" type="date" id="service_date" name="service_date"
             value="<?= old('service_date', e($contract['service_date'] ?? '')) ?>">
    </div>
    <div class="form-group">
      <label class="form-label" for="contract_status">Contract Status (optional)</label>
      <input class="form-control" type="text" id="contract_status" name="contract_status"
             value="<?= old('contract_status', e($contract['contract_status'] ?? '')) ?>">
    </div>
  </div>

  <div class="form-group">
    <label class="form-label" for="description">Description (optional)</label>
    <textarea class="form-control" id="description" name="description" rows="3"><?= old('description', e($contract['description'] ?? '')) ?></textarea>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="assigned_to">Assign To (optional)</label>
      <select class="form-control" id="assigned_to" name="assigned_to">
        <option value="">-- Unassigned --</option>
        <?php $selectedAssignee = old('assigned_to', (string) ($contract['assigned_to'] ?? '')); ?>
        <?php foreach ($users as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= $selectedAssignee === (string) $u['id'] ? 'selected' : '' ?>>
            <?= e($u['full_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label" for="cc_users">CC To (optional - hold Ctrl/Cmd to select multiple)</label>
      <select class="form-control" id="cc_users" name="cc_users[]" multiple size="5">
        <?php $selectedCcRaw = $selectedCc ?? []; $oldCc = old_array('cc_users'); $selectedCcStrings = $oldCc ?: array_map('strval', $selectedCcRaw); ?>
        <?php foreach ($users as $u): ?>
          <option value="<?= (int) $u['id'] ?>" <?= in_array((string) $u['id'], $selectedCcStrings, true) ? 'selected' : '' ?>>
            <?= e($u['full_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-group">
    <label class="form-label" for="contract_file">Contract File (optional)</label>
    <?php if ($isEdit && !empty($contract['contract_file_path'])): ?>
      <div style="margin-bottom:6px; font-size:12px;">
        Current: <a href="/<?= e($contract['contract_file_path']) ?>" target="_blank" rel="noopener"><?= e($contract['contract_file_original_name'] ?? 'view file') ?></a>
        <span style="color:var(--color-text-muted);">(upload a new file to replace)</span>
      </div>
    <?php endif; ?>
    <input class="form-control" type="file" id="contract_file" name="contract_file" accept="<?= e(upload_accept_attr()) ?>">
  </div>

  <div class="form-group">
    <label class="form-label" for="branch_id">Branch</label>
    <?php if ($canSelectBranch): ?>
      <select class="form-control" id="branch_id" name="branch_id" required>
        <option value="">-- Select Branch --</option>
        <?php $selectedBranch = old('branch_id', (string) ($contract['branch_id'] ?? $myBranchId ?? '')); ?>
        <?php foreach ($branches as $b): ?>
          <option value="<?= (int) $b['id'] ?>" <?= $selectedBranch === (string) $b['id'] ? 'selected' : '' ?>>
            <?= e($b['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    <?php else: ?>
      <input class="form-control" type="text" value="<?= e($myBranchName ?? 'No branch assigned') ?>" disabled>
      <input type="hidden" name="branch_id" value="<?= (int) ($myBranchId ?? 0) ?>">
    <?php endif; ?>
  </div>

  <div style="display:flex; gap:12px;">
    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create Contract' ?></button>
    <a href="/smc" class="btn btn-outline">Cancel</a>
  </div>
</form>
