<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_post();

$email = strtolower(post_value('email'));
$role = post_value('role');

validate_email_or_fail($email);

if (!in_array($role, ['cliente', 'emprendedor'], true)) {
    json_response(['ok' => false, 'message' => 'Tipo de cuenta inválido.'], 422);
}

$genericMessage = 'Si el correo está registrado, te enviaremos un enlace para recuperar tu contraseña.';

try {
    $pdo = db();
    $user = find_user_by_email_and_role($pdo, $email, $role);

    if (!$user || $user['estado'] !== 'activo') {
        json_response(['ok' => true, 'message' => $genericMessage]);
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('+45 minutes'))->format('Y-m-d H:i:s');

    $pdo->beginTransaction();

    $delete = $pdo->prepare(
        'DELETE FROM password_resets
         WHERE usuario_id = :usuario_id OR expira_en < NOW() OR usado_en IS NOT NULL'
    );
    $delete->execute(['usuario_id' => $user['id']]);

    $insert = $pdo->prepare(
        'INSERT INTO password_resets (usuario_id, token_hash, expira_en)
         VALUES (:usuario_id, :token_hash, :expira_en)'
    );
    $insert->execute([
        'usuario_id' => $user['id'],
        'token_hash' => $tokenHash,
        'expira_en' => $expiresAt,
    ]);

    $pdo->commit();

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'tuyomall.com';
    $basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/\\');
    $resetUrl = $scheme . '://' . $host . $basePath . '/reset-password.html?token=' . urlencode($token) . '&role=' . urlencode($role);

    $accountLabel = $role === 'emprendedor' ? 'emprendedor' : 'cliente';
    $subject = 'Recupera tu contraseña de TuyoMall';
    $body = "Hola {$user['nombre']},\n\n";
    $body .= "Recibimos una solicitud para recuperar la contraseña de tu cuenta de {$accountLabel} en TuyoMall.\n\n";
    $body .= "Abrí este enlace para crear una nueva contraseña:\n{$resetUrl}\n\n";
    $body .= "El enlace vence en 45 minutos. Si no solicitaste este cambio, podés ignorar este correo.\n\n";
    $body .= "Equipo TuyoMall";

    $headers = [
        'From: TuyoMall <soporte@tuyomall.com>',
        'Reply-To: soporte@tuyomall.com',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
    ];

    $sent = mail($email, $subject, $body, implode("\r\n", $headers));

    if (!$sent) {
        $cleanup = $pdo->prepare('DELETE FROM password_resets WHERE token_hash = :token_hash');
        $cleanup->execute(['token_hash' => $tokenHash]);
        json_response(['ok' => false, 'message' => 'No pudimos enviar el correo. Probá de nuevo o escribí a soporte@tuyomall.com.'], 500);
    }

    json_response(['ok' => true, 'message' => $genericMessage]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['ok' => false, 'message' => 'No pudimos procesar la recuperación. Intentá de nuevo.'], 500);
}
