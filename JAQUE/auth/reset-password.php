<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_post();

$token = post_value('token');
$password = (string) ($_POST['password'] ?? '');
$password2 = (string) ($_POST['password_confirm'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    json_response(['ok' => false, 'message' => 'El enlace de recuperación no es válido.'], 422);
}

validate_password_or_fail($password);

if ($password !== $password2) {
    json_response(['ok' => false, 'message' => 'Las contraseñas no coinciden.'], 422);
}

try {
    $pdo = db();
    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        'SELECT pr.id, pr.usuario_id, u.estado
         FROM password_resets pr
         INNER JOIN usuarios u ON u.id = pr.usuario_id
         WHERE pr.token_hash = :token_hash
           AND pr.usado_en IS NULL
           AND pr.expira_en >= NOW()
         LIMIT 1'
    );
    $stmt->execute(['token_hash' => $tokenHash]);
    $reset = $stmt->fetch();

    if (!$reset || $reset['estado'] !== 'activo') {
        json_response(['ok' => false, 'message' => 'El enlace expiró o ya fue usado. Solicitá uno nuevo.'], 422);
    }

    $pdo->beginTransaction();

    $updateUser = $pdo->prepare(
        'UPDATE usuarios
         SET password_hash = :password_hash
         WHERE id = :usuario_id'
    );
    $updateUser->execute([
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'usuario_id' => $reset['usuario_id'],
    ]);

    $markUsed = $pdo->prepare(
        'UPDATE password_resets
         SET usado_en = NOW()
         WHERE id = :id'
    );
    $markUsed->execute(['id' => $reset['id']]);

    $deleteOld = $pdo->prepare(
        'DELETE FROM password_resets
         WHERE usuario_id = :usuario_id AND id <> :id'
    );
    $deleteOld->execute([
        'usuario_id' => $reset['usuario_id'],
        'id' => $reset['id'],
    ]);

    $pdo->commit();

    json_response(['ok' => true, 'message' => 'Contraseña actualizada. Ya podés iniciar sesión.']);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['ok' => false, 'message' => 'No pudimos actualizar la contraseña. Intentá de nuevo.'], 500);
}
