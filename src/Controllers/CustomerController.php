<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\Customer;
use Throwable;

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

    public function destroy(array $params): void
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

        $rentals = Customer::lprRentalCount($id);
        $contracts = Customer::smcContractCount($id);
        $jobOrders = Customer::jobOrderCount($customer['name']);
        if ($rentals > 0 || $contracts > 0 || $jobOrders > 0) {
            flash('error', "Cannot delete \"{$customer['name']}\" — still referenced by {$jobOrders} job order(s), {$rentals} LPR rental(s), and {$contracts} SMC contract(s). Deactivate it instead.");
            $this->redirect('/customers');
        }

        try {
            Customer::delete($id);
        } catch (Throwable $e) {
            flash('error', "Cannot delete \"{$customer['name']}\" — it's still referenced by other records. Deactivate it instead.");
            $this->redirect('/customers');
        }

        ActivityLog::record(Auth::id(), 'customer.delete', 'customer', $id);
        flash('success', 'Customer deleted.');
        $this->redirect('/customers');
    }

    public function exportCsv(array $params = []): void
    {
        $customers = Customer::all();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="customers-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly

        fputcsv($out, ['Customer Name', 'Active']);
        foreach ($customers as $c) {
            fputcsv($out, [$c['name'], $c['is_active'] ? '1' : '0']);
        }
        fclose($out);
        exit;
    }

    public function importForm(array $params = []): void
    {
        $this->view('customers/import');
    }

    public function import(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/customers/import');
        }

        $file = $_FILES['import_file'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            flash('error', 'Please choose a CSV file to import.');
            $this->redirect('/customers/import');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            flash('error', 'Upload failed, please try again.');
            $this->redirect('/customers/import');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            flash('error', 'Could not read the uploaded file.');
            $this->redirect('/customers/import');
        }

        // Strip a UTF-8 BOM if present (Excel adds one on export/save).
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            flash('error', 'The file appears to be empty.');
            $this->redirect('/customers/import');
        }

        $added = 0;
        $updated = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $name = trim((string) ($row[0] ?? ''));
            if ($name === '') {
                continue;
            }
            $active = !in_array(strtolower(trim((string) ($row[1] ?? '1'))), ['0', 'no', 'false', 'inactive'], true);

            $existing = Customer::findByName($name);
            if ($existing) {
                Customer::setActive((int) $existing['id'], $active);
                $updated++;
            } else {
                Customer::setActive(Customer::create($name), $active);
                $added++;
            }
        }
        fclose($handle);

        ActivityLog::record(Auth::id(), 'customer.import');
        flash('success', "Import complete — {$added} customer(s) added, {$updated} updated.");
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
