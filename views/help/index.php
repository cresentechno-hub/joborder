<?php
/**
 * This page doubles as the reference material for the Help chat
 * assistant (src/Services/HelpChatService.php) — its system prompt is
 * grounded directly in the same facts documented below. Keep both in
 * sync: if a module's behavior changes here, update the assistant's
 * SYSTEM_PROMPT to match, and vice versa.
 */
?>
<h2 style="margin-top:0;">Help &amp; Example Data</h2>
<p style="color: var(--color-text-muted);">
  A quick reference for filling in each module. Use this alongside the form when creating or updating a Job Order.
</p>

<div class="card" style="margin-bottom:20px;">
  <h3 style="margin-top:0;">Ask the Help Assistant</h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    Ask a question about how to use the system - it only knows what's documented on this page, so it won't make up
    behavior it isn't sure about.
  </p>
  <div id="help-chat-log" style="max-height:340px; overflow-y:auto; border:1px solid var(--color-border); border-radius:8px; padding:12px; margin-bottom:10px; background: var(--color-bg);">
    <?php if (empty($chatHistory)): ?>
      <p id="help-chat-empty" style="color: var(--color-text-muted); font-size:13px; margin:0;">No messages yet - ask something like "how do I fill in the Job Start Date?"</p>
    <?php else: ?>
      <?php foreach ($chatHistory as $m): ?>
        <div style="margin-bottom:10px;">
          <div style="font-weight:600; font-size:12px; color: var(--color-slate);"><?= $m['role'] === 'user' ? 'You' : 'Assistant' ?></div>
          <div style="font-size:13px; white-space:pre-wrap;"><?= e($m['text']) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <div id="help-chat-status" style="font-size:12px; color: var(--color-danger); margin-bottom:6px;"></div>
  <form id="help-chat-form" style="display:flex; gap:8px;">
    <?= csrf_field() ?>
    <input class="form-control" type="text" id="help-chat-input" name="message" placeholder="Ask a question..." style="flex:1;" autocomplete="off" required>
    <button type="submit" class="btn btn-primary" id="help-chat-send">Send</button>
    <button type="button" class="btn btn-outline" id="help-chat-clear">Clear</button>
  </form>
</div>

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
            ['Customer Name', "The company or individual named on the quotation. Start typing to see matching existing customers, or type a brand-new name - it's added to the Customers master list automatically when you save.", 'Sunrise Trading Sdn Bhd'],
            ['Subject', 'A short description of what the quotation is for.', 'Supply of Office Furniture - HQ Level 3'],
            ['Total Cost (RM)', 'The total quotation amount. Numbers only, no commas.', '15800.00'],
            ['Job Start Date', 'The date work/order processing begins - usually the date the PO is received.', '20-08-2026'],
            ['Branch', 'Which branch this job order belongs to. Sales staff just see their own branch here; Admin/Manager get a dropdown that also narrows the Assign To list below to that branch\'s staff.', 'Penang Branch'],
            ['Assign To', 'The staff member(s) responsible for progressing this job order - tick a checkbox for each person. Sales staff can only pick colleagues at their own branch, or staff with no branch.', 'Andy Yoon, Sarah Lim'],
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
    checked months. The <strong>Customer</strong> field is a text box with autocomplete - start typing to see
    matching existing customers, or type a brand-new name and it's added to the <strong>Customers</strong> master
    list automatically when you save. Each
    contract belongs to one <strong>Branch</strong> (visible to Admin only) - Sales staff only see contracts at
    their own branch, and new contracts are locked to it automatically. The Create/Edit form also has optional
    fields not shown on the listing or the CSV template below: <strong>Quotation No</strong>,
    <strong>Rental Amount</strong>, <strong>Detail</strong>, <strong>E-Invoice</strong>, and a
    <strong>Contract File</strong> attachment (upload the signed contract PDF/image directly on the contract).
  </p>
  <p style="font-size:13px; font-weight:600; margin-bottom:6px;">Import/Export CSV template</p>
  <pre style="background: var(--color-bg); border:1px solid var(--color-border); border-radius:6px; padding:10px 12px; font-size:12.5px; overflow-x:auto;">Partner,Customer,Start Date,Coverage Months,Customer Email,2026-09,2026-10,...
Weilong,Eastern Oriental Hotel,2026-03-01,36,contact@eoh.com,1,0,...</pre>
  <p style="color: var(--color-text-muted); font-size:13px; margin-bottom:0;">
    The first five columns are fixed (Start Date as <code>YYYY-MM-DD</code>, Coverage Months is 12/24/36/48),
    followed by one column per covered month (header <code>YYYY-MM</code>) with <code>1</code>/<code>0</code>
    (or Yes/No) marking whether that month's invoice was printed. A row is matched to an existing contract by
    Partner + Customer + Start Date; unmatched combinations create a new contract, and Partner/Customer names not
    already in the system are added automatically.
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
    or bulk-load contracts and their monthly statuses. The <strong>Customer</strong> field is a text box with
    autocomplete - start typing to see matching existing customers, or type a brand-new name and it's added to the
    <strong>Customers</strong> master list automatically when you save. Each contract belongs to one
    <strong>Branch</strong> (visible to Admin only) - Sales staff only see contracts at their own branch, and new
    contracts are locked to it automatically. The Create/Edit form also has optional fields not shown on the
    listing or the CSV template below: <strong>Assign To</strong> (one person responsible for servicing the
    contract), <strong>CC To</strong> (multiple people to keep in the loop), <strong>Quotation No</strong>,
    <strong>Short Name</strong>, <strong>Site</strong>, <strong>Service Frequency</strong>,
    <strong>Service Date</strong>, <strong>Payment Term</strong>, <strong>Contract Status</strong> (the contract's
    own status - separate from each month's Blank/SCH/DONE), <strong>Description</strong>, and a
    <strong>Contract File</strong> attachment.
  </p>
  <p style="font-size:13px; font-weight:600; margin-bottom:6px;">Import/Export CSV template</p>
  <pre style="background: var(--color-bg); border:1px solid var(--color-border); border-radius:6px; padding:10px 12px; font-size:12.5px; overflow-x:auto;">Customer,Start Date,Coverage Months,Customer Email,2026-09,2026-10,...
Badan Pengurusan Bersama Wellesly Residences,2026-08-02,24,contact@wellesly.com,SCH,DONE,...</pre>
  <p style="color: var(--color-text-muted); font-size:13px; margin-bottom:0;">
    The first four columns are fixed (Start Date as <code>YYYY-MM-DD</code>, Coverage Months is 12/24/36/48),
    followed by one column per covered month (header <code>YYYY-MM</code>) with <code>Blank</code>/<code>SCH</code>/<code>DONE</code>.
    A row is matched to an existing contract by Customer + Start Date; unmatched combinations create a new
    contract, and Customer names not already in the system are added automatically.
  </p>
</div>

<div class="card" style="margin-top:20px;">
  <h3 style="margin-top:0;">Customers Module</h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    Shared customer master list, kept in sync automatically by the Customer field on Job Orders, LPR Rental and
    SMC - typing a name that doesn't exist yet on any of those three adds it here the moment you save. Add, edit,
    deactivate or delete individual customers from the <strong>Customers</strong> page, or bulk-manage the whole
    list with <strong>Export CSV</strong> / <strong>Import</strong> at the top. <strong>Delete</strong> is
    permanent and only works if the customer has no job orders, LPR rentals or SMC contracts (even old/removed
    ones still count) - use <strong>Deactivate</strong> instead to just hide it from autocomplete while keeping
    its history.
  </p>
  <p style="font-size:13px; font-weight:600; margin-bottom:6px;">Import/Export CSV template</p>
  <pre style="background: var(--color-bg); border:1px solid var(--color-border); border-radius:6px; padding:10px 12px; font-size:12.5px; overflow-x:auto;">Customer Name,Active
Sunrise Trading Sdn Bhd,1
Old Client Sdn Bhd,0</pre>
  <p style="color: var(--color-text-muted); font-size:13px; margin-bottom:0;">
    <code>Active</code> accepts <code>1</code>/<code>0</code> or Yes/No (blank = active). A row is matched to an
    existing customer by exact name - unmatched names are added as new customers; matched names have their Active
    status updated. Re-importing an exported file is safe and won't create duplicates.
  </p>
</div>

<div class="card" style="margin-top:20px;">
  <h3 style="margin-top:0;">LPR Partners Module</h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    Partner maintenance list used by the LPR Rental module's Partner dropdown (the rental listing is grouped by
    partner). Add, edit, deactivate or delete individual partners from the <strong>LPR Partners</strong> page, or
    bulk-manage the whole list with <strong>Export CSV</strong> / <strong>Import</strong> at the top.
    <strong>Delete</strong> is permanent and only works if the partner has no LPR rentals (even old/removed ones
    still count) - use <strong>Deactivate</strong> instead to just hide it from the dropdown while keeping its
    history.
  </p>
  <p style="font-size:13px; font-weight:600; margin-bottom:6px;">Import/Export CSV template</p>
  <pre style="background: var(--color-bg); border:1px solid var(--color-border); border-radius:6px; padding:10px 12px; font-size:12.5px; overflow-x:auto;">Partner Name,Active
Weilong,1
Whizcity,1</pre>
  <p style="color: var(--color-text-muted); font-size:13px; margin-bottom:0;">
    Same rules as the Customers template above: <code>Active</code> is <code>1</code>/<code>0</code> or Yes/No
    (blank = active), matched by exact Partner Name, safe to re-import.
  </p>
</div>

<div class="card" style="margin-top:20px;">
  <h3 style="margin-top:0;">Branches Module</h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    Branch maintenance list. Sales-role users only see job orders/LPR rentals/SMC contracts belonging to their own
    branch; other roles see every branch. Add, edit, deactivate or delete individual branches from the
    <strong>Branches</strong> page, or bulk-manage the whole list with <strong>Export CSV</strong> /
    <strong>Import</strong> at the top. <strong>Delete</strong> is permanent and only works if the branch has no
    users, job orders, LPR rentals, or SMC contracts assigned to it - use <strong>Deactivate</strong> instead to
    keep it (and its history) while hiding it from new assignments.
  </p>
  <p style="font-size:13px; font-weight:600; margin-bottom:6px;">Import/Export CSV template</p>
  <pre style="background: var(--color-bg); border:1px solid var(--color-border); border-radius:6px; padding:10px 12px; font-size:12.5px; overflow-x:auto;">Branch Name,Active
Main Branch,1
Penang Branch,1</pre>
  <p style="color: var(--color-text-muted); font-size:13px; margin-bottom:0;">
    Same rules as the Customers template above: <code>Active</code> is <code>1</code>/<code>0</code> or Yes/No
    (blank = active), matched by exact Branch Name, safe to re-import.
  </p>
</div>

<script>
(function () {
  var log = document.getElementById('help-chat-log');
  var emptyMsg = document.getElementById('help-chat-empty');
  var status = document.getElementById('help-chat-status');
  var form = document.getElementById('help-chat-form');
  var input = document.getElementById('help-chat-input');
  var sendBtn = document.getElementById('help-chat-send');
  var clearBtn = document.getElementById('help-chat-clear');
  var csrfToken = form.querySelector('input[name="_csrf"]').value;

  function appendMessage(role, text) {
    if (emptyMsg) { emptyMsg.remove(); emptyMsg = null; }
    var wrap = document.createElement('div');
    wrap.style.marginBottom = '10px';
    var label = document.createElement('div');
    label.style.fontWeight = '600';
    label.style.fontSize = '12px';
    label.style.color = 'var(--color-slate)';
    label.textContent = role === 'user' ? 'You' : 'Assistant';
    var body = document.createElement('div');
    body.style.fontSize = '13px';
    body.style.whiteSpace = 'pre-wrap';
    body.textContent = text;
    wrap.appendChild(label);
    wrap.appendChild(body);
    log.appendChild(wrap);
    log.scrollTop = log.scrollHeight;
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var message = input.value.trim();
    if (!message) { return; }

    status.textContent = '';
    appendMessage('user', message);
    input.value = '';
    input.disabled = true;
    sendBtn.disabled = true;
    sendBtn.textContent = 'Thinking...';

    fetch('/help/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: '_csrf=' + encodeURIComponent(csrfToken) + '&message=' + encodeURIComponent(message),
      credentials: 'same-origin'
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        if (!result.ok || result.data.error) {
          status.textContent = result.data.error || 'Could not get a reply. Please try again.';
          return;
        }
        appendMessage('model', result.data.reply);
      })
      .catch(function () {
        status.textContent = 'Could not reach the server. Please try again.';
      })
      .finally(function () {
        input.disabled = false;
        sendBtn.disabled = false;
        sendBtn.textContent = 'Send';
        input.focus();
      });
  });

  clearBtn.addEventListener('click', function () {
    fetch('/help/chat/clear', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: '_csrf=' + encodeURIComponent(csrfToken),
      credentials: 'same-origin'
    }).finally(function () {
      log.innerHTML = '<p id="help-chat-empty" style="color: var(--color-text-muted); font-size:13px; margin:0;">No messages yet - ask something like "how do I fill in the Job Start Date?"</p>';
      emptyMsg = document.getElementById('help-chat-empty');
      status.textContent = '';
    });
  });
})();
</script>
