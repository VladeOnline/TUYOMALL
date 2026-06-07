<?php
declare(strict_types=1);

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);

    session_start();
}

function login_user(array $user): void
{
    start_app_session();
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['rol'];
    $_SESSION['user_name'] = $user['nombre'];
    $_SESSION['user_email'] = $user['email'];
}

function require_role(string $role, string $redirect = 'acceso-cliente.html'): void
{
    start_app_session();

    if (($_SESSION['user_role'] ?? null) !== $role) {
        header('Location: ' . $redirect);
        exit;
    }
}
