<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_post();

$nombre = post_value('r-nombre');
$apellido = post_value('r-apellido');
$email = strtolower(post_value('r-email'));
$password = (string) ($_POST['r-password'] ?? '');
$password2 = (string) ($_POST['r-pass2'] ?? '');
$terms = isset($_POST['r-terms']);

if ($nombre === '' || $apellido === '') {
    json_response(['ok' => false, 'message' => 'Completa tu nombre y apellido.'], 422);
}

validate_email_or_fail($email);
validate_password_or_fail($password);

if ($password !== $password2) {
    json_response(['ok' => false, 'message' => 'Las contraseñas no coinciden.'], 422);
}

if (!$terms) {
    json_response(['ok' => false, 'message' => 'Debes aceptar los términos para continuar.'], 422);
}

try {
    $pdo = db();

    if (email_exists($pdo, $email)) {
        json_response(['ok' => false, 'message' => 'Ya existe una cuenta con ese correo.'], 409);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (rol, nombre, apellido, email, password_hash)
         VALUES (:rol, :nombre, :apellido, :email, :password_hash)'
    );
    $stmt->execute([
        'rol' => 'cliente',
        'nombre' => $nombre,
        'apellido' => $apellido,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    $user = [
        'id' => (int) $pdo->lastInsertId(),
        'rol' => 'cliente',
        'nombre' => $nombre,
        'email' => $email,
    ];
    login_user($user);

    json_response(['ok' => true, 'redirect' => 'https://tuyomall.com/principal.html']);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => database_public_message($e, 'register-client')], 500);
}
