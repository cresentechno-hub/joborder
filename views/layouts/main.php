<?php

use App\Core\Auth;
use App\Models\Notification;

$currentUser = Auth::user();
$unreadCount = Auth::check() ? Notification::unreadCountForUser((int) Auth::id()) : 0;
$recentNotifications = Auth::check() ? Notification::recentForUser((int) Auth::id()) : [];
$currentPath = rtrim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
$isActive = static fn (string $prefix): string =>
    ($prefix === '/' ? $currentPath === '/' : str_starts_with($currentPath, $prefix)) ? 'active' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= e(setting('app_name', APP_NAME)) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <div class="sidebar-logo">
          <img src="<?= asset('images/Letterheadlogo.jpg') ?>" alt="<?= e(setting('app_name', APP_NAME)) ?>">
        </div>
      </div>
      <ul class="sidebar-nav">
        <li><a href="/" class="<?= $isActive('/') ?>"><?= icon('layout-dashboard') ?> Dashboard</a></li>
        <li><a href="/job-orders" class="<?= $isActive('/job-orders') ?>"><?= icon('briefcase') ?> Job Orders</a></li>
        <?php if (Auth::can('lpr_rental.view')): ?>
          <li><a href="/lpr-rentals" class="<?= $isActive('/lpr-rentals') ?>"><?= icon('car') ?> LPR Rental</a></li>
        <?php endif; ?>
        <?php if (Auth::can('smc.view')): ?>
          <li><a href="/smc" class="<?= $isActive('/smc') ?>"><?= icon('building') ?> SMC</a></li>
        <?php endif; ?>
        <?php if (Auth::can('user.manage')): ?>
          <li><a href="/users" class="<?= $isActive('/users') ?>"><?= icon('users') ?> Users</a></li>
          <li><a href="/branches" class="<?= $isActive('/branches') ?>"><?= icon('map-pin') ?> Branches</a></li>
        <?php endif; ?>
        <?php if (Auth::can('customer.manage')): ?>
          <li><a href="/customers" class="<?= $isActive('/customers') ?>"><?= icon('user') ?> Customers</a></li>
        <?php endif; ?>
        <?php if (Auth::can('lpr_partner.manage')): ?>
          <li><a href="/lpr-partners" class="<?= $isActive('/lpr-partners') ?>"><?= icon('handshake') ?> LPR Partners</a></li>
        <?php endif; ?>
        <?php if (Auth::can('role.manage')): ?>
          <li><a href="/roles" class="<?= $isActive('/roles') ?>"><?= icon('shield') ?> Roles</a></li>
        <?php endif; ?>
        <?php if (Auth::can('settings.manage')): ?>
          <li><a href="/settings" class="<?= $isActive('/settings') ?>"><?= icon('settings') ?> Settings</a></li>
        <?php endif; ?>
        <?php if (Auth::can('activity_log.view')): ?>
          <li><a href="/activity-log" class="<?= $isActive('/activity-log') ?>"><?= icon('clock') ?> Activity Log</a></li>
        <?php endif; ?>
        <li><a href="/help" class="<?= $isActive('/help') ?>"><?= icon('help-circle') ?> Help</a></li>
      </ul>
    </aside>
    <div class="sidebar-overlay"></div>
    <div class="main-area">
      <header class="topbar">
        <button type="button" class="sidebar-toggle" aria-label="Toggle menu">
          <span></span><span></span><span></span>
        </button>

        <div class="notif-bell-wrap">
          <button type="button" id="notif-bell-btn" class="notif-bell-btn" aria-label="Notifications">
            <?= icon('bell') ?>
            <?php if ($unreadCount > 0): ?>
              <span class="notif-bell-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
            <?php endif; ?>
          </button>
          <div id="notif-dropdown" class="notif-dropdown" hidden>
            <div class="notif-dropdown-head">
              <span>Notifications</span>
              <?php if ($unreadCount > 0): ?>
                <form method="POST" action="/notifications/mark-all-read" style="margin:0;">
                  <?= csrf_field() ?>
                  <button type="submit" class="notif-mark-all">Mark all read</button>
                </form>
              <?php endif; ?>
            </div>
            <div class="notif-dropdown-list">
              <?php if (empty($recentNotifications)): ?>
                <div class="notif-empty">No notifications yet.</div>
              <?php else: ?>
                <?php foreach ($recentNotifications as $n): ?>
                  <a href="/notifications/<?= (int) $n['id'] ?>/open" class="notif-item <?= (int) $n['is_read'] === 0 ? 'notif-item-unread' : '' ?>">
                    <div class="notif-item-title"><?= e($n['title']) ?></div>
                    <?php if (!empty($n['message'])): ?><div class="notif-item-msg"><?= e($n['message']) ?></div><?php endif; ?>
                    <div class="notif-item-time"><?= e($n['created_at']) ?></div>
                  </a>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <span><?= e($currentUser['full_name'] ?? '') ?> &middot; <?= e($currentUser['role_name'] ?? '') ?></span>
        <form method="POST" action="/logout" style="margin:0;">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline btn-sm"><?= icon('log-out') ?> Logout</button>
        </form>
      </header>
      <main class="content">
        <?= $content ?>
      </main>
    </div>
  </div>
  <script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
