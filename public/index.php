<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Controllers\ActivityLogController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HelpController;
use App\Controllers\JobOrderCommentController;
use App\Controllers\JobOrderController;
use App\Controllers\RoleController;
use App\Controllers\SettingsController;
use App\Controllers\TeamController;
use App\Controllers\UserController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;

$router = new Router();

// Auth
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], [[AuthMiddleware::class, null]]);

// Dashboard
$router->get('/', [DashboardController::class, 'index'], [[AuthMiddleware::class, null]]);

// Job Orders
$router->get('/job-orders', [JobOrderController::class, 'index'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.view'],
]);
$router->get('/job-orders/create', [JobOrderController::class, 'create'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.create'],
]);
$router->post('/job-orders', [JobOrderController::class, 'store'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.create'],
]);
$router->post('/job-orders/extract-quotation', [JobOrderController::class, 'extractQuotation'], [
    // job_order.view (not .create) — this button is used from both the
    // create AND edit forms; every role with create or edit also has view.
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.view'],
]);
$router->get('/job-orders/{id}/edit', [JobOrderController::class, 'edit'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.edit'],
]);
$router->post('/job-orders/{id}', [JobOrderController::class, 'update'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.edit'],
]);
$router->post('/job-orders/{id}/delete', [JobOrderController::class, 'destroy'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.delete'],
]);
$router->post('/job-orders/{id}/comments', [JobOrderCommentController::class, 'store'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.edit'],
]);

// Users
$router->get('/users', [UserController::class, 'index'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->get('/users/create', [UserController::class, 'create'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->post('/users', [UserController::class, 'store'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->get('/users/{id}/edit', [UserController::class, 'edit'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->post('/users/{id}', [UserController::class, 'update'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->post('/users/{id}/toggle-active', [UserController::class, 'toggleActive'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);

// Sales Teams
$router->get('/teams', [TeamController::class, 'index'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->get('/teams/create', [TeamController::class, 'create'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->post('/teams', [TeamController::class, 'store'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->get('/teams/{id}/edit', [TeamController::class, 'edit'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->post('/teams/{id}', [TeamController::class, 'update'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->post('/teams/{id}/toggle-active', [TeamController::class, 'toggleActive'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);

// Roles (RBAC)
$router->get('/roles', [RoleController::class, 'index'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'role.manage'],
]);
$router->get('/roles/{id}/permissions', [RoleController::class, 'editPermissions'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'role.manage'],
]);
$router->post('/roles/{id}/permissions', [RoleController::class, 'updatePermissions'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'role.manage'],
]);

// Settings
$router->get('/settings', [SettingsController::class, 'edit'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'settings.manage'],
]);
$router->post('/settings', [SettingsController::class, 'update'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'settings.manage'],
]);

// Activity Log
$router->get('/activity-log', [ActivityLogController::class, 'index'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'activity_log.view'],
]);

// Help
$router->get('/help', [HelpController::class, 'index'], [[AuthMiddleware::class, null]]);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
