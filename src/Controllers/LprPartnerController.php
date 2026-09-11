<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\LprPartner;
use Throwable;

final class LprPartnerController extends Controller
{
    public function index(array $params = []): void
    {
        $partners = LprPartner::all();
        foreach ($partners as &$p) {
            $p['rental_count'] = LprPartner::rentalCount((int) $p['id']);
        }
        unset($p);

        $this->view('lpr_partners/index', ['partners' => $partners]);
    }

    public function create(array $params = []): void
    {
        $this->view('lpr_partners/create');
    }

    public function store(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/lpr-partners/create');
        }

        $input = $_POST;
        $errors = $this->validate($input);

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect('/lpr-partners/create');
        }

        $id = LprPartner::create(trim($input['name']));

        ActivityLog::record(Auth::id(), 'lpr_partner.create', 'lpr_partner', $id);
        flash('success', 'Partner created successfully.');
        $this->redirect('/lpr-partners');
    }

    public function edit(array $params): void
    {
        $partner = LprPartner::findById((int) $params['id']);
        if (!$partner) {
            $this->notFound();
        }

        $this->view('lpr_partners/edit', ['partner' => $partner]);
    }

    public function update(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect("/lpr-partners/{$id}/edit");
        }

        $partner = LprPartner::findById($id);
        if (!$partner) {
            $this->notFound();
        }

        $input = $_POST;
        $errors = $this->validate($input, $id);

        if (!empty($errors)) {
            flash_input($input);
            flash_errors($errors);
            $this->redirect("/lpr-partners/{$id}/edit");
        }

        LprPartner::update($id, trim($input['name']));

        ActivityLog::record(Auth::id(), 'lpr_partner.update', 'lpr_partner', $id);
        flash('success', 'Partner updated successfully.');
        $this->redirect('/lpr-partners');
    }

    public function toggleActive(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/lpr-partners');
        }

        $partner = LprPartner::findById($id);
        if (!$partner) {
            $this->notFound();
        }

        LprPartner::setActive($id, !$partner['is_active']);
        ActivityLog::record(Auth::id(), $partner['is_active'] ? 'lpr_partner.deactivate' : 'lpr_partner.activate', 'lpr_partner', $id);
        flash('success', $partner['is_active'] ? 'Partner deactivated.' : 'Partner activated.');
        $this->redirect('/lpr-partners');
    }

    public function destroy(array $params): void
    {
        $id = (int) $params['id'];

        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/lpr-partners');
        }

        $partner = LprPartner::findById($id);
        if (!$partner) {
            $this->notFound();
        }

        $rentals = LprPartner::rentalCount($id);
        if ($rentals > 0) {
            flash('error', "Cannot delete \"{$partner['name']}\" — still referenced by {$rentals} LPR rental(s). Deactivate it instead.");
            $this->redirect('/lpr-partners');
        }

        try {
            LprPartner::delete($id);
        } catch (Throwable $e) {
            flash('error', "Cannot delete \"{$partner['name']}\" — it's still referenced by other records. Deactivate it instead.");
            $this->redirect('/lpr-partners');
        }

        ActivityLog::record(Auth::id(), 'lpr_partner.delete', 'lpr_partner', $id);
        flash('success', 'Partner deleted.');
        $this->redirect('/lpr-partners');
    }

    public function exportCsv(array $params = []): void
    {
        $partners = LprPartner::all();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="lpr-partners-' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly

        fputcsv($out, ['Partner Name', 'Active']);
        foreach ($partners as $p) {
            fputcsv($out, [$p['name'], $p['is_active'] ? '1' : '0']);
        }
        fclose($out);
        exit;
    }

    public function importForm(array $params = []): void
    {
        $this->view('lpr_partners/import');
    }

    public function import(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/lpr-partners/import');
        }

        $file = $_FILES['import_file'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            flash('error', 'Please choose a CSV file to import.');
            $this->redirect('/lpr-partners/import');
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            flash('error', 'Upload failed, please try again.');
            $this->redirect('/lpr-partners/import');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            flash('error', 'Could not read the uploaded file.');
            $this->redirect('/lpr-partners/import');
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
            $this->redirect('/lpr-partners/import');
        }

        $added = 0;
        $updated = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $name = trim((string) ($row[0] ?? ''));
            if ($name === '') {
                continue;
            }
            $active = !in_array(strtolower(trim((string) ($row[1] ?? '1'))), ['0', 'no', 'false', 'inactive'], true);

            $existing = LprPartner::findByName($name);
            if ($existing) {
                LprPartner::setActive((int) $existing['id'], $active);
                $updated++;
            } else {
                LprPartner::setActive(LprPartner::create($name), $active);
                $added++;
            }
        }
        fclose($handle);

        ActivityLog::record(Auth::id(), 'lpr_partner.import');
        flash('success', "Import complete — {$added} partner(s) added, {$updated} updated.");
        $this->redirect('/lpr-partners');
    }

    private function validate(array $input, ?int $excludeId = null): array
    {
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Partner name is required.';
        } elseif (LprPartner::nameExists($name, $excludeId)) {
            $errors['name'] = 'A partner with this name already exists.';
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
