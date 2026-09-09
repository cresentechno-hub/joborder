<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';

use App\Controllers\ActivityLogController;
use App\Controllers\AuthController;
use App\Controllers\BranchController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\HelpController;
use App\Controllers\JobOrderCommentController;
use App\Controllers\JobOrderController;
use App\Controllers\LprPartnerController;
use App\Controllers\LprRentalController;
use App\Controllers\LprRenewalRecipientsController;
use App\Controllers\NotificationController;
use App\Controllers\RoleController;
use App\Controllers\SettingsController;
use App\Controllers\SmcController;
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
// Must be registered before POST /job-orders/{id} below, or the router's
// {id} segment would greedily match the literal "extract-invoice" first
// and route here into JobOrderController::update() instead — 404ing on a
// job order id that doesn't exist.
$router->post('/job-orders/extract-invoice', [JobOrderCommentController::class, 'extractInvoice'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.edit'],
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
$router->get('/job-orders/{id}/comments/{commentId}/edit', [JobOrderCommentController::class, 'edit'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.edit'],
]);
$router->post('/job-orders/{id}/comments/{commentId}', [JobOrderCommentController::class, 'update'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.edit'],
]);
$router->post('/job-orders/{id}/comments/{commentId}/delete', [JobOrderCommentController::class, 'destroy'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'job_order.edit'],
]);
$router->post('/job-orders/{id}/documents/{documentId}/delete', [JobOrderController::class, 'destroyDocument'], [
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

// Branches
$router->get('/branches', [BranchController::class, 'index'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->get('/branches/create', [BranchController::class, 'create'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->post('/branches', [BranchController::class, 'store'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->get('/branches/{id}/edit', [BranchController::class, 'edit'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->post('/branches/{id}', [BranchController::class, 'update'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);
$router->post('/branches/{id}/toggle-active', [BranchController::class, 'toggleActive'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'user.manage'],
]);

// Customers (shared master list)
$router->get('/customers', [CustomerController::class, 'index'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'customer.manage'],
]);
$router->get('/customers/create', [CustomerController::class, 'create'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'customer.manage'],
]);
$router->post('/customers', [CustomerController::class, 'store'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'customer.manage'],
]);
$router->get('/customers/{id}/edit', [CustomerController::class, 'edit'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'customer.manage'],
]);
$router->post('/customers/{id}', [CustomerController::class, 'update'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'customer.manage'],
]);
$router->post('/customers/{id}/toggle-active', [CustomerController::class, 'toggleActive'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'customer.manage'],
]);

// LPR Partners
$router->get('/lpr-partners', [LprPartnerController::class, 'index'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_partner.manage'],
]);
$router->get('/lpr-partners/create', [LprPartnerController::class, 'create'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_partner.manage'],
]);
$router->post('/lpr-partners', [LprPartnerController::class, 'store'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_partner.manage'],
]);
$router->get('/lpr-partners/{id}/edit', [LprPartnerController::class, 'edit'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_partner.manage'],
]);
$router->post('/lpr-partners/{id}', [LprPartnerController::class, 'update'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_partner.manage'],
]);
$router->post('/lpr-partners/{id}/toggle-active', [LprPartnerController::class, 'toggleActive'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_partner.manage'],
]);

// LPR Rental (invoice-printing tracker) — literal routes (create/export/import)
// registered before the {id} patterns, same reasoning as Job Orders above.
$router->get('/lpr-rentals', [LprRentalController::class, 'index'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_rental.view'],
]);
$router->get('/lpr-rentals/create', [LprRentalController::class, 'create'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_rental.manage'],
]);
$router->get('/lpr-rentals/export', [LprRentalController::class, 'exportCsv'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_rental.view'],
]);
$router->get('/lpr-rentals/import', [LprRentalController::class, 'importForm'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_rental.manage'],
]);
$router->post('/lpr-rentals/import', [LprRentalController::class, 'import'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_rental.manage'],
]);
$router->post('/lpr-rentals', [LprRentalController::class, 'store'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_rental.manage'],
]);
$router->get('/lpr-rentals/{id}/edit', [LprRentalController::class, 'edit'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_rental.manage'],
]);
$router->post('/lpr-rentals/{id}', [LprRentalController::class, 'update'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_rental.manage'],
]);
$router->post('/lpr-rentals/{id}/delete', [LprRentalController::class, 'destroy'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_rental.manage'],
]);
$router->post('/lpr-rentals/{id}/checks/{yearMonth}/toggle', [LprRentalController::class, 'toggleCheck'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'lpr_rental.manage'],
]);

// SMC — literal routes (create/export/import) registered before the
// {id} patterns, same reasoning as Job Orders / LPR Rental above.
$router->get('/smc', [SmcController::class, 'index'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'smc.view'],
]);
$router->get('/smc/create', [SmcController::class, 'create'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'smc.manage'],
]);
$router->get('/smc/export', [SmcController::class, 'exportCsv'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'smc.view'],
]);
$router->get('/smc/import', [SmcController::class, 'importForm'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'smc.manage'],
]);
$router->post('/smc/import', [SmcController::class, 'import'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'smc.manage'],
]);
$router->post('/smc', [SmcController::class, 'store'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'smc.manage'],
]);
$router->get('/smc/{id}/edit', [SmcController::class, 'edit'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'smc.manage'],
]);
$router->post('/smc/{id}', [SmcController::class, 'update'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'smc.manage'],
]);
$router->post('/smc/{id}/delete', [SmcController::class, 'destroy'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'smc.manage'],
]);
$router->post('/smc/{id}/status/{yearMonth}', [SmcController::class, 'updateStatus'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'smc.manage'],
]);

// Roles (RBAC)
$router->get('/roles', [RoleController::class, 'index'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'role.manage'],
]);
$router->get('/roles/create', [RoleController::class, 'create'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'role.manage'],
]);
$router->post('/roles', [RoleController::class, 'store'], [
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
$router->get('/settings/lpr-renewal-recipients', [LprRenewalRecipientsController::class, 'edit'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'settings.manage'],
]);
$router->post('/settings/lpr-renewal-recipients', [LprRenewalRecipientsController::class, 'update'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'settings.manage'],
]);

// Notifications (any logged-in user manages their own)
$router->get('/notifications/{id}/open', [NotificationController::class, 'open'], [
    [AuthMiddleware::class, null],
]);
$router->post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'], [
    [AuthMiddleware::class, null],
]);

// Activity Log
$router->get('/activity-log', [ActivityLogController::class, 'index'], [
    [AuthMiddleware::class, null], [PermissionMiddleware::class, 'activity_log.view'],
]);

// Help
$router->get('/help', [HelpController::class, 'index'], [[AuthMiddleware::class, null]]);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
