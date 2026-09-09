<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;
use App\Models\LprPartner;

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
