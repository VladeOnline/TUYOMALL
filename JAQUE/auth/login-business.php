<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../includes/plan-rules.php';

require_post();

$email = strtolower(post_value('email'));
$password = (string) ($_POST['password'] ?? '');

validate_email_or_fail($email);

if ($password === '') {
    json_response(['ok' => false, 'message' => 'Ingresa tu contraseña.'], 422);
}

try {
    $pdo = db();
    $user = find_user_by_email_and_role($pdo, $email, 'emprendedor');

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_response(['ok' => false, 'message' => 'Correo o contraseña incorrectos.'], 401);
    }

    if ($user['estado'] !== 'activo') {
        json_response(['ok' => false, 'message' => 'Esta cuenta no está activa.'], 403);
    }

    $business = get_business_for_user($pdo, (int) $user['id']);

    if (!$business) {
        json_response(['ok' => false, 'message' => 'No encontramos el negocio asociado a esta cuenta.'], 404);
    }

    sync_business_plan($pdo, (int) $business['id']);

    $user['business_id'] = (int) $business['id'];
    login_user($user);
    json_response(['ok' => true, 'redirect' => 'dashboard-emprendedor.php']);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'No pudimos iniciar sesión. Revisa la base de datos.'], 500);
}
