<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\HelpChatService;

final class HelpController extends Controller
{
    private const SESSION_KEY = 'help_chat_history';
    private const MAX_MESSAGE_LENGTH = 2000;

    public function index(array $params = []): void
    {
        $this->view('help/index', [
            'chatHistory' => $_SESSION[self::SESSION_KEY] ?? [],
        ]);
    }

    public function chat(array $params = []): void
    {
        if (!verify_csrf()) {
            $this->json(['error' => 'Your session expired, please refresh and try again.'], 419);
        }

        $message = trim((string) $this->input('message', ''));
        if ($message === '') {
            $this->json(['error' => 'Type a question first.'], 422);
        }
        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $this->json(['error' => 'That message is too long — please shorten it.'], 422);
        }

        $history = $_SESSION[self::SESSION_KEY] ?? [];
        $history[] = ['role' => 'user', 'text' => $message];

        $result = HelpChatService::answer($history);

        if ($result['reply'] === null) {
            // Don't keep the unanswered question in history — let them retry
            // the same message cleanly rather than it permanently occupying
            // a slot in the resent conversation.
            $this->json(['error' => $result['error']], 502);
        }

        $history[] = ['role' => 'model', 'text' => $result['reply']];
        $_SESSION[self::SESSION_KEY] = $history;

        $this->json(['reply' => $result['reply']]);
    }

    public function clearChat(array $params = []): void
    {
        if (!verify_csrf()) {
            $this->json(['error' => 'Your session expired, please refresh and try again.'], 419);
        }

        unset($_SESSION[self::SESSION_KEY]);
        $this->json(['ok' => true]);
    }
}
