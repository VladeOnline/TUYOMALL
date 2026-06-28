<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';

start_app_session();

$role = $_SESSION['user_role'] ?? null;

if (!$role) {
    json_response([
        'ok' => true,
        'authenticated' => false,
    ]);
}

$payload = [
    'ok' => true,
    'authenticated' => true,
    'user' => [
        'id' => (int) ($_SESSION['user_id'] ?? 0),
        'role' => (string) $role,
        'name' => (string) ($_SESSION['user_name'] ?? ''),
        'email' => (string) ($_SESSION['user_email'] ?? ''),
        'business_id' => isset($_SESSION['business_id']) ? (int) $_SESSION['business_id'] : null,
    ],
];

json_response($payload);
