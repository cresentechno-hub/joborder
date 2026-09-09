<h2 style="margin-top:0;">Help &amp; Example Data</h2>
<p style="color: var(--color-text-muted);">
  A quick reference for filling in each module. Use this alongside the form when creating or updating a Job Order.
</p>

<div class="card" style="margin-bottom:20px;">
  <h3 style="margin-top:0;">Job Order Module</h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    Every job order starts from a quotation. Upload the quotation file, then click
    <strong>Read Quotation &amp; Auto-Fill</strong> - the system reads the PDF and fills in Quotation No,
    Customer Name, Subject, Total Cost, and (when creating a new job order) Job Start Date (today) for you.
    This only works for real PDF quotations (not scanned photos/images) that follow the standard Cresentech
    layout (<code>Our Ref</code>, <code>To</code>, <code>Subject</code>, <code>TOTAL</code>). Always
    double-check the auto-filled values before submitting - if a field couldn't be read, it's called out so
    you can type it in yourself. Then track the job through its stages as work progresses.
  </p>
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr><th>Field</th><th>What to enter</th><th>Example</th></tr>
      </thead>
      <tbody>
        <?php
        $rows = [
            ['Quotation File', "Upload the quotation PDF or image issued to the customer. Required when creating a new job order.", 'QT-2026-0142.pdf'],
            ['PO File', "Upload the customer's Purchase Order once received. Can be left blank at creation and added later via Edit.", 'PO-SUNRISE-8821.pdf'],
            ['Other Documents', 'Optional. Attach any other supporting files - select multiple at once. Each one can be removed individually from the Edit screen afterwards.', 'site_survey.pdf, signed_agreement.pdf'],
            ['Quotation No', 'The exact quotation number shown on the document. Must be unique in the system.', 'QT-2026-0142'],
            ['Customer Name', 'The company or individual named on the quotation.', 'Sunrise Trading Sdn Bhd'],
            ['Subject', 'A short description of what the quotation is for.', 'Supply of Office Furniture - HQ Level 3'],
            ['Total Cost (RM)', 'The total quotation amount. Numbers only, no commas.', '15800.00'],
            ['Job Start Date', 'The date work/order processing begins - usually the date the PO is received.', '20-08-2026'],
            ['Branch', 'Which branch this job order belongs to. Sales staff just see their own branch here; Admin/Manager get a dropdown that also narrows the Assign To list below to that branch\'s staff.', 'Penang Branch'],
            ['Assign To', 'The staff member(s) responsible for progressing this job order - hold Ctrl/Cmd to pick more than one. Sales staff can only pick colleagues at their own branch, or staff with no branch.', 'Andy Yoon, Sarah Lim'],
            ['Job Stage', 'Current stage of the job (see reference below). Update this as the job progresses.', '1 - Purchase Order Received'],
            ['Remarks', 'Optional notes - special instructions, delivery constraints, etc.', 'Customer requires delivery before 15 Sept.'],
        ];
        ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td style="font-weight:600; white-space:nowrap;"><?= e($r[0]) ?></td>
            <td><?= e($r[1]) ?></td>
            <td style="color: var(--color-text-muted); white-space:nowrap;"><?= e($r[2]) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="margin-bottom:20px;">
  <h3 style="margin-top:0;">Comments &amp; Updates</h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    On the Edit Job Order page, click <strong>+ Add Comment</strong> to log a progress update. Unlike the main
    form's fields, <strong>Stage</strong> and <strong>Assign To</strong> here immediately change the real job
    order - this is the normal way to move a job forward day-to-day, not just a note.
  </p>
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead>
        <tr><th>Field</th><th>What to enter</th><th>Example</th></tr>
      </thead>
      <tbody>
        <?php
        $commentRows = [
            ['Stage', "Moves the job order to this stage right away.", '5 - Issued DO Invoice For Full Payment'],
            ['Assign To', 'Reassigns the job order to this/these staff member(s) - hold Ctrl/Cmd to pick more than one.', 'Andy Yoon, Sarah Lim'],
            ['CC To', 'Optional. Select one or more staff who should be aware of this update - for record-keeping only, no email is sent.', 'Sarah Lim, Ben Tan'],
            ['Upload Invoice', "Optional. Invoice No auto-fills from this file's own name (e.g. INV20260829-0456.pdf -> INV20260829-0456) - check/correct it before submitting. The stored file is renamed to INV-{Invoice No}.", 'INV20260829-0456.pdf'],
            ['Upload DO', 'Optional. Requires an Invoice No (from the upload above, or typed in directly) - the stored file is renamed to DO-{Invoice No} using that same number.', 'delivery_order_scan.pdf'],
            ['Remark', 'Optional notes about this update.', 'Full payment received, ready for delivery.'],
        ];
        ?>
        <?php foreach ($commentRows as $r): ?>
          <tr>
            <td style="font-weight:600; white-space:nowrap;"><?= e($r[0]) ?></td>
            <td><?= e($r[1]) ?></td>
            <td style="color: var(--color-text-muted); white-space:nowrap;"><?= e($r[2]) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3 style="margin-top:0;">Job Stage Reference</h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    Move the job to the next stage as it progresses. The system automatically tracks how many days a job has
    spent in its current stage, and flags anything stuck longer than
    <?= (int) setting('stage_pending_alert_days', (string) STAGE_PENDING_ALERT_DAYS) ?> days on the dashboard.
  </p>
  <ol style="padding-left:20px; font-size:13px; line-height:1.9;">
    <li>Purchase Order Received</li>
    <li>Issued DO Invoice for Downpayment</li>
    <li>Deposit Receive and Start Order Stock
      <ul style="margin:4px 0;"><li>3.1 Pending Stock Information from PIC</li></ul>
    </li>
    <li>Stock Prepared
      <ul style="margin:4px 0;">
        <li>4.1 Goods Taken from PIC</li>
        <li>4.2 Pending PO / Pending Issue Invoice</li>
      </ul>
    </li>
    <li>Issued DO Invoice For Full Payment</li>
    <li>Cop Sign &amp; Delivery</li>
    <li>Full Payment Received &amp; Sales Completed</li>
    <li>Cancel PO <span style="color: var(--color-text-muted);">(use when the customer cancels the order)</span></li>
  </ol>
  <p style="color: var(--color-text-muted); font-size:13px; margin-bottom:0;">
    Once a job order reaches <strong>Full Payment Received &amp; Sales Completed</strong> or <strong>Cancel PO</strong>,
    it becomes Admin-only: it disappears from everyone else's list, dashboard, and stage counts (Sales/Manager/Viewer),
    even for the branch that handled it. Anyone with edit rights can still move a job order into one of these
    stages - they just won't be able to see it there afterwards.
  </p>
</div>

<div class="card" style="margin-top:20px;">
  <h3 style="margin-top:0;">LPR Rental Module</h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    Tracks invoice printing for LPR rental contracts. Create a contract under <strong>LPR Rental</strong> with its
    Customer, Partner, Contract Start Date and Month Coverage (12/24/36/48 months) - the system automatically builds
    one checkbox column per covered month, labelled by year and month. Tick a box once that month's invoice has been
    printed; it saves immediately. The listing is grouped by partner, opens scrolled to the current month, and keeps
    Customer/Start Date/Coverage/Email/Actions fixed on the left while the month columns scroll. Use
    <strong>Export CSV</strong> / <strong>Import</strong> at the top to back up or bulk-load contracts and their
    checked months. Customer and Partner names come from the <strong>Customers</strong> and
    <strong>LPR Partners</strong> maintenance lists - add a new one there if it's not in the dropdown yet. Each
    contract belongs to one <strong>Branch</strong> (visible to Admin only) - Sales staff only see contracts at
    their own branch, and new contracts are locked to it automatically.
  </p>
</div>

<div class="card" style="margin-top:20px;">
  <h3 style="margin-top:0;">SMC Module</h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    Tracks the monthly schedule status for SMC contracts. Create a contract under <strong>SMC</strong> with its
    Customer, Contract Start Date and Month Coverage (12/24/36/48 months) - the system automatically builds one
    status column per covered month, labelled by year and month. Set each month to <strong>Blank</strong>,
    <strong>SCH</strong> (scheduled) or <strong>DONE</strong> from the dropdown; it saves immediately. The listing
    opens scrolled to the current month and keeps Customer/Start Date/Coverage/Email/Actions fixed on the left
    while the month columns scroll. Use <strong>Export CSV</strong> / <strong>Import</strong> at the top to back up
    or bulk-load contracts and their monthly statuses. Customer names come from the <strong>Customers</strong>
    maintenance list - add a new one there if it's not in the dropdown yet. Each contract belongs to one
    <strong>Branch</strong> (visible to Admin only) - Sales staff only see contracts at their own branch, and new
    contracts are locked to it automatically.
  </p>
</div>
