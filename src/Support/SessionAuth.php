<?php

declare(strict_types=1);

namespace App\Support;

final class SessionAuth
{
    private const USER_KEY = 'admin_logged_in';
    private const CSRF_KEY = 'csrf_token';

    public function __construct(
        private readonly string $sessionName,
        private readonly string $username,
        private readonly string $passwordHash,
        private readonly bool $httpsOnly = true,
    ) {
        $this->start();
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($this->sessionName);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $this->httpsOnly,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public function attempt(string $username, string $password): bool
    {
        if ($username !== $this->username) {
            return false;
        }

        if (!password_verify($password, $this->passwordHash)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION[self::USER_KEY] = true;
        $this->csrfToken();
        return true;
    }

    public function check(): bool
    {
        return (bool) ($_SESSION[self::USER_KEY] ?? false);
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    public function csrfToken(): string
    {
        if (!isset($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(16));
        }
        return (string) $_SESSION[self::CSRF_KEY];
    }

    public function validateCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals($this->csrfToken(), $token);
    }
}
