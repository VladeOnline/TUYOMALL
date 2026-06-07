<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_post();

$nombre = post_value('r-nombre');
$apellido = post_value('r-apellido');
$negocio = post_value('r-negocio');
$email = strtolower(post_value('r-email'));
$whatsapp = post_value('r-wa');
$provincia = post_value('r-provincia');
$canton = post_value('r-canton');
$password = (string) ($_POST['r-password'] ?? '');
$plan = post_value('plan') === 'premium' ? 'premium' : 'gratis';
$terms = isset($_POST['r-terms']);

if ($nombre === '' || $apellido === '' || $negocio === '' || $whatsapp === '' || $provincia === '') {
    json_response(['ok' => false, 'message' => 'Completa los datos obligatorios del negocio.'], 422);
}

validate_email_or_fail($email);
validate_password_or_fail($password);

if (!$terms) {
    json_response(['ok' => false, 'message' => 'Debes aceptar los términos para continuar.'], 422);
}

try {
    $pdo = db();

    if (email_exists($pdo, $email)) {
        json_response(['ok' => false, 'message' => 'Ya existe una cuenta con ese correo.'], 409);
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (rol, nombre, apellido, email, password_hash, telefono)
         VALUES (:rol, :nombre, :apellido, :email, :password_hash, :telefono)'
    );
    $stmt->execute([
        'rol' => 'emprendedor',
        'nombre' => $nombre,
        'apellido' => $apellido,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'telefono' => $whatsapp,
    ]);

    $userId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'INSERT INTO negocios (usuario_id, nombre_negocio, whatsapp, provincia, canton, plan)
         VALUES (:usuario_id, :nombre_negocio, :whatsapp, :provincia, :canton, :plan)'
    );
    $stmt->execute([
        'usuario_id' => $userId,
        'nombre_negocio' => $negocio,
        'whatsapp' => $whatsapp,
        'provincia' => $provincia,
        'canton' => $canton !== '' ? $canton : null,
        'plan' => $plan,
    ]);

    $pdo->commit();

    login_user([
        'id' => $userId,
        'rol' => 'emprendedor',
        'nombre' => $nombre,
        'email' => $email,
    ]);

    json_response([
        'ok' => true,
        'redirect' => $plan === 'premium' ? 'premium-checkout.html' : 'dashboard-emprendedor.php',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['ok' => false, 'message' => 'No pudimos crear el negocio. Revisa la conexión a la base de datos.'], 500);
}
