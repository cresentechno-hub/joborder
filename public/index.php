<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HelpController;
use App\Controllers\JobOrderController;
use App\Controllers\RoleController;
use App\Controllers\SettingsController;
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
$router->get('/job-orders/{id}/edit', [JobOrderController::class, 'edit'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.edit'],
]);
$router->post('/job-orders/{id}', [JobOrderController::class, 'update'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.edit'],
]);
$router->post('/job-orders/{id}/delete', [JobOrderController::class, 'destroy'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.delete'],
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

// Help
$router->get('/help', [HelpController::class, 'index'], [[AuthMiddleware::class, null]]);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
