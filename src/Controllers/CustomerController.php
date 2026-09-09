<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;

final class CustomerController extends Controller
{
    public function index(array $params = []): void
    {
        $customers = Customer::all();
        foreach ($customers as &$c) {
            $c['rental_count'] = Customer::lprRentalCount((int) $c['id']);
        }
        unset($c);

        $this->view('customers/index', ['customers' => $customers]);
    }

    public function create(array $params = []): void
    {
        $this->view('customers/create');
    }

    public function store(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/customers/create');
        }

        $input = $_POST;
        $errors = $this->validate($input);

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect('/customers/create');
        }

        $id = Customer::create(trim($input['name']));

        ActivityLog::record(Auth::id(), 'customer.create', 'customer', $id);
        flash('success', 'Customer created successfully.');
        $this->redirect('/customers');
    }

    public function edit(array $params): void
    {
        $customer = Customer::findById((int) $params['id']);
        if (!$customer) {
            $this->notFound();
        }

        $this->view('customers/edit', ['customer' => $customer]);
    }

    public function update(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect("/customers/{$id}/edit");
        }

        $customer = Customer::findById($id);
        if (!$customer) {
            $this->notFound();
        }

        $input = $_POST;
        $errors = $this->validate($input, $id);

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect("/customers/{$id}/edit");
        }

        Customer::update($id, trim($input['name']));

        ActivityLog::record(Auth::id(), 'customer.update', 'customer', $id);
        flash('success', 'Customer updated successfully.');
        $this->redirect('/customers');
    }

    public function toggleActive(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/customers');
        }

        $customer = Customer::findById($id);
        if (!$customer) {
            $this->notFound();
        }

        Customer::setActive($id, !$customer['is_active']);
        ActivityLog::record(Auth::id(), $customer['is_active'] ? 'customer.deactivate' : 'customer.activate', 'customer', $id);
        flash('success', $customer['is_active'] ? 'Customer deactivated.' : 'Customer activated.');
        $this->redirect('/customers');
    }

    private function validate(array $input, ?int $excludeId = null): array
    {
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Customer name is required.';
        } elseif (Customer::nameExists($name, $excludeId)) {
            $errors['name'] = 'A customer with this name already exists.';
        }

        return $errors;
    }

    private function notFound(): never
    {
        http_response_code(404);
        require ROOT_PATH . '/views/errors/404.php';
        exit;
    }
}
