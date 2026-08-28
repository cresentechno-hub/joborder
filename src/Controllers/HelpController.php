<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class HelpController extends Controller
{
    public function index(array $params = []): void
    {
        $this->view('help/index');
    }
}
