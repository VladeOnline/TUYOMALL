<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_post();

$nombre = post_value('r-nombre');
$apellido = post_value('r-apellido');
$negocio = post_value('r-negocio');
$email = strtolower(post_value('r-email'));
$whatsapp = post_value('r-wa');
$pais = post_value('r-pais');
$provincia = post_value('r-provincia');
$direccion = post_value('r-direccion');
$password = (string) ($_POST['r-password'] ?? '');
$requestedPlan = post_value('plan') === 'premium' ? 'premium' : 'gratis';
$terms = isset($_POST['r-terms']);

if ($nombre === '' || $apellido === '' || $negocio === '' || $whatsapp === '' || $pais === '' || $provincia === '') {
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
    $slug = unique_business_slug($pdo, $negocio);

    $stmt = $pdo->prepare(
        'INSERT INTO negocios (
            usuario_id,
            nombre_negocio,
            slug,
            whatsapp,
            correo,
            pais,
            provincia,
            direccion,
            plan_codigo
         )
         VALUES (
            :usuario_id,
            :nombre_negocio,
            :slug,
            :whatsapp,
            :correo,
            :pais,
            :provincia,
            :direccion,
            :plan_codigo
         )'
    );
    $stmt->execute([
        'usuario_id' => $userId,
        'nombre_negocio' => $negocio,
        'slug' => $slug,
        'whatsapp' => $whatsapp,
        'correo' => $email,
        'pais' => $pais,
        'provincia' => $provincia,
        'direccion' => $direccion !== '' ? $direccion : null,
        'plan_codigo' => 'gratis',
    ]);

    $businessId = (int) $pdo->lastInsertId();

    if ($requestedPlan === 'premium') {
        $stmt = $pdo->prepare(
            'INSERT INTO suscripciones (negocio_id, plan_codigo, estado, proveedor, inicia_en)
             VALUES (:negocio_id, :plan_codigo, :estado, :proveedor, NOW())'
        );
        $stmt->execute([
            'negocio_id' => $businessId,
            'plan_codigo' => 'premium',
            'estado' => 'pendiente',
            'proveedor' => 'paypal',
        ]);
    }

    $pdo->commit();

    login_user([
        'id' => $userId,
        'rol' => 'emprendedor',
        'nombre' => $nombre,
        'email' => $email,
        'business_id' => $businessId,
    ]);

    json_response([
        'ok' => true,
        'redirect' => $requestedPlan === 'premium' ? 'premium-checkout.html' : 'dashboard-emprendedor.php',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['ok' => false, 'message' => 'No pudimos crear el negocio. Revisa la conexión a la base de datos.'], 500);
}
