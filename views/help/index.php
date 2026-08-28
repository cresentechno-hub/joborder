<h2 style="margin-top:0;">Help &amp; Example Data</h2>
<p style="color: var(--color-text-muted);">
  A quick reference for filling in each module. Use this alongside the form when creating or updating a Job Order.
</p>

<div class="card" style="margin-bottom:20px;">
  <h3 style="margin-top:0;">Job Order Module</h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    Every job order starts from a quotation. Upload the quotation file, fill in what it says, then track the
    job through its stages as work progresses.
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
            ['Quotation No', 'The exact quotation number shown on the document. Must be unique in the system.', 'QT-2026-0142'],
            ['Customer Name', 'The company or individual named on the quotation.', 'Sunrise Trading Sdn Bhd'],
            ['Subject', 'A short description of what the quotation is for.', 'Supply of Office Furniture - HQ Level 3'],
            ['Total Cost (RM)', 'The total quotation amount. Numbers only, no commas.', '15800.00'],
            ['Job Start Date', 'The date work/order processing begins - usually the date the PO is received.', '20-08-2026'],
            ['Assign To', 'The staff member responsible for progressing this job order.', 'Andy Yoon'],
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
    even for the sales team that handled it. Anyone with edit rights can still move a job order into one of these
    stages - they just won't be able to see it there afterwards.
  </p>
</div>

<div class="card" style="margin-top:20px;">
  <h3 style="margin-top:0;">Users &amp; Roles Module <span style="color: var(--color-text-muted); font-weight:400; font-size:13px;">(Admin only)</span></h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    Create a login for each staff member under <strong>Users</strong> and assign them a role. Roles control what
    a user can see and do - manage what each role is allowed to do under <strong>Roles &amp; Permissions</strong>.
    Deactivating a user (instead of deleting) keeps their history on past job orders intact.
  </p>
  <div style="overflow-x:auto;">
    <table class="data-table">
      <thead><tr><th>Field</th><th>What to enter</th><th>Example</th></tr></thead>
      <tbody>
        <?php
        $userRows = [
            ['Full Name', 'The staff member\'s display name.', 'Andy Yoon'],
            ['Username', 'Used to log in. Cannot be changed after the account is created.', 'andyyoon'],
            ['Email', 'Must be unique. Used for identification only (login is by username).', 'andyyoon@cresentech.com.my'],
            ['Role', 'Determines what this user can access - see the four default roles below.', 'Sales'],
            ['Password', 'Minimum 8 characters. Leave blank on Edit to keep the current password.', '(set by the user)'],
        ];
        ?>
        <?php foreach ($userRows as $r): ?>
          <tr>
            <td style="font-weight:600; white-space:nowrap;"><?= e($r[0]) ?></td>
            <td><?= e($r[1]) ?></td>
            <td style="color: var(--color-text-muted); white-space:nowrap;"><?= e($r[2]) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p style="color: var(--color-text-muted); font-size:13px; margin-top:12px; margin-bottom:0;">
    Default roles: <strong>Admin</strong> (full access incl. Users/Roles/Settings), <strong>Manager</strong>
    (all job order actions + reports), <strong>Sales</strong> (create/edit own job orders), <strong>Viewer</strong>
    (read-only).
  </p>
</div>

<div class="card" style="margin-top:20px;">
  <h3 style="margin-top:0;">Sales Teams Module <span style="color: var(--color-text-muted); font-weight:400; font-size:13px;">(Admin only)</span></h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    Sales teams control who can see which job orders. Create teams under <strong>Teams</strong>, then assign each
    Sales-role user to a team from their entry in <strong>Users</strong>. A Sales user only sees job orders
    assigned to a member of their own team - Team A cannot see Team B's job orders, and vice versa. Admin,
    Manager, and Viewer are unaffected and always see every job order regardless of team.
  </p>
  <p style="color: var(--color-text-muted); font-size:13px; margin-bottom:0;">
    A Sales user with no team assigned sees no job orders until an Admin assigns them one.
  </p>
</div>

<div class="card" style="margin-top:20px;">
  <h3 style="margin-top:0;">Settings Module <span style="color: var(--color-text-muted); font-weight:400; font-size:13px;">(Admin only)</span></h3>
  <p style="color: var(--color-text-muted); font-size:13px;">
    System-wide options that apply immediately after saving - no redeploy needed. This includes the application
    name shown in the sidebar, the timezone used for all dates, the pending-stage alert threshold used on the
    Dashboard, and the upload size/type restrictions used by the Quotation and PO uploads.
  </p>
</div>

<div class="card" style="margin-top:20px;">
  <h3 style="margin-top:0;">Activity Log <span style="color: var(--color-text-muted); font-weight:400; font-size:13px;">(Admin only)</span></h3>
  <p style="color: var(--color-text-muted); font-size:13px; margin-bottom:0;">
    A trace of who did what and when - logins/logouts and every create, update, delete, and permission change
    across the system. Filter by action type or by user under <strong>Activity Log</strong>.
  </p>
</div>
