<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\ActivityLog;

final class AuthController extends Controller
{
    public function showLogin(array $params = []): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $this->view('auth/login', [], 'layouts/guest');
    }

    public function login(array $params = []): void
    {
        if (!verify_csrf()) {
            flash('error', 'Your session expired, please try again.');
            $this->redirect('/login');
        }

        $identifier = trim((string) $this->input('username', ''));
        $password   = (string) $this->input('password', '');

        if ($identifier === '' || $password === '') {
            flash('error', 'Username and password are required.');
            $this->redirect('/login');
        }

        $result = Auth::attempt($identifier, $password);

        switch ($result) {
            case Auth::OK:
                ActivityLog::record(Auth::id(), 'auth.login');
                $this->redirect('/');
                // no break, redirect() exits

            case Auth::LOCKED:
                flash('error', 'Too many failed attempts. This account is locked for 15 minutes.');
                break;

            case Auth::INACTIVE:
                flash('error', 'This account has been deactivated. Contact your administrator.');
                break;

            default:
                flash('error', 'Invalid username or password.');
                break;
        }

        $this->redirect('/login');
    }

    public function logout(array $params = []): void
    {
        ActivityLog::record(Auth::id(), 'auth.logout');
        Auth::logout();
        $this->redirect('/login');
    }
}
