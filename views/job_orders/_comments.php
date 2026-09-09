<?php

/**
 * Comment/update history log for the job order edit page. The "+ Add
 * Comment" trigger + form live separately at the top of the page (see
 * views/job_orders/_add_comment.php) so they're reachable without
 * scrolling past the whole job order form — this section is just the
 * read-mostly log of past comments.
 * Expects: $jobOrder, $comments (already in scope from
 * JobOrderController::edit()).
 */
?>

<div id="comments" class="card" style="margin-top:20px;">
  <h3 style="margin:0;">Comments &amp; Updates</h3>

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
              <span style="margin-left:8px; font-size:13px; color: var(--color-slate);">Assigned to <?= e(implode(', ', $c['assignee_names'])) ?></span>
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
