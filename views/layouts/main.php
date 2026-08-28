<?php

use App\Core\Auth;

$currentUser = Auth::user();
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
        <li><a href="/" class="<?= $isActive('/') ?>">Dashboard</a></li>
        <li><a href="/job-orders" class="<?= $isActive('/job-orders') ?>">Job Orders</a></li>
        <?php if (Auth::can('user.manage')): ?>
          <li><a href="/users" class="<?= $isActive('/users') ?>">Users</a></li>
          <li><a href="/teams" class="<?= $isActive('/teams') ?>">Teams</a></li>
        <?php endif; ?>
        <?php if (Auth::can('role.manage')): ?>
          <li><a href="/roles" class="<?= $isActive('/roles') ?>">Roles</a></li>
        <?php endif; ?>
        <?php if (Auth::can('settings.manage')): ?>
          <li><a href="/settings" class="<?= $isActive('/settings') ?>">Settings</a></li>
        <?php endif; ?>
        <li><a href="/help" class="<?= $isActive('/help') ?>">Help</a></li>
      </ul>
    </aside>
    <div class="sidebar-overlay"></div>
    <div class="main-area">
      <header class="topbar">
        <button type="button" class="sidebar-toggle" aria-label="Toggle menu">
          <span></span><span></span><span></span>
        </button>
        <span><?= e($currentUser['full_name'] ?? '') ?> &middot; <?= e($currentUser['role_name'] ?? '') ?></span>
        <form method="POST" action="/logout" style="margin:0;">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline">Logout</button>
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
