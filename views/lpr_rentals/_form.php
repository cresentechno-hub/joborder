<?php

/**
 * Shared create/edit form. Expects: $mode ('create'|'edit'), $customers,
 * $partners, $coverageOptions, $canSelectBranch, $branches, $myBranchId,
 * and $rental (array, edit only).
 */

$errors = form_errors();
$rental = $rental ?? null;
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

<form method="POST" action="<?= $isEdit ? '/lpr-rentals/' . (int) $rental['id'] : '/lpr-rentals' ?>">
  <?= csrf_field() ?>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="customer_id">Customer</label>
      <select class="form-control" id="customer_id" name="customer_id" required>
        <option value="">-- Select Customer --</option>
        <?php $selectedCustomer = old('customer_id', (string) ($rental['customer_id'] ?? '')); ?>
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
    <div class="form-group">
      <label class="form-label" for="partner_id">Partner</label>
      <select class="form-control" id="partner_id" name="partner_id" required>
        <option value="">-- Select Partner --</option>
        <?php $selectedPartner = old('partner_id', (string) ($rental['partner_id'] ?? '')); ?>
        <?php foreach ($partners as $p): ?>
          <option value="<?= (int) $p['id'] ?>" <?= $selectedPartner === (string) $p['id'] ? 'selected' : '' ?>>
            <?= e($p['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <div style="margin-top:4px; font-size:12px; color: var(--color-text-muted);">
        Not listed? <a href="/lpr-partners/create" target="_blank" rel="noopener">Add a new partner</a> then refresh this page.
      </div>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="start_date">Contract Start Date</label>
      <input class="form-control" type="date" id="start_date" name="start_date"
             value="<?= old('start_date', e($rental['start_date'] ?? '')) ?>" required>
    </div>
    <div class="form-group">
      <label class="form-label" for="coverage_months">Month Coverage</label>
      <select class="form-control" id="coverage_months" name="coverage_months" required>
        <option value="">-- Select Coverage --</option>
        <?php $selectedCoverage = old('coverage_months', (string) ($rental['coverage_months'] ?? '')); ?>
        <?php foreach ($coverageOptions as $months): ?>
          <option value="<?= (int) $months ?>" <?= $selectedCoverage === (string) $months ? 'selected' : '' ?>>
            <?= (int) $months ?> months
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-group">
    <label class="form-label" for="customer_email">Customer Email (optional)</label>
    <input class="form-control" type="email" id="customer_email" name="customer_email"
           value="<?= old('customer_email', e($rental['customer_email'] ?? '')) ?>"
           placeholder="e.g. accounts@customer.com">
  </div>

  <div class="form-group">
    <label class="form-label" for="branch_id">Branch</label>
    <?php if ($canSelectBranch): ?>
      <select class="form-control" id="branch_id" name="branch_id" required>
        <option value="">-- Select Branch --</option>
        <?php $selectedBranch = old('branch_id', (string) ($rental['branch_id'] ?? $myBranchId ?? '')); ?>
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
    <a href="/lpr-rentals" class="btn btn-outline">Cancel</a>
  </div>
</form>
