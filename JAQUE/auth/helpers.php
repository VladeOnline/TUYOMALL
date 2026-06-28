<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function post_value(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_response(['ok' => false, 'message' => 'Método no permitido.'], 405);
    }
}

function validate_email_or_fail(string $email): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => false, 'message' => 'Ingresa un correo válido.'], 422);
    }
}

function validate_password_or_fail(string $password): void
{
    if (strlen($password) < 8) {
        json_response(['ok' => false, 'message' => 'La contraseña debe tener mínimo 8 caracteres.'], 422);
    }
}

function database_public_message(Throwable $error, string $context = 'database'): string
{
    $driverCode = $error instanceof PDOException && isset($error->errorInfo[1])
        ? (int) $error->errorInfo[1]
        : (int) $error->getCode();

    $messages = [
        1044 => 'El usuario de la base de datos no tiene permisos suficientes. Revisá los privilegios en cPanel.',
        1045 => 'No pudimos conectar con MySQL. Revisá usuario y contraseña de la base de datos.',
        1049 => 'La base de datos configurada no existe o tiene otro nombre.',
        1054 => 'La base de datos está desactualizada: falta una columna. Importá nuevamente database/schema.sql.',
        1062 => 'Ya existe una cuenta con ese correo.',
        1146 => 'La base de datos está incompleta: falta una tabla. Importá nuevamente database/schema.sql.',
        1216 => 'Falta información relacionada en la base de datos. Revisá que las tablas principales estén importadas.',
        1217 => 'Falta información relacionada en la base de datos. Revisá que las tablas principales estén importadas.',
        1451 => 'Falta información relacionada en la base de datos. Revisá que las tablas principales estén importadas.',
        1452 => 'Falta información relacionada en la base de datos. Revisá que la tabla planes tenga gratis y premium.',
    ];

    error_log(sprintf('[TuyoMall:%s] %s', $context, $error->getMessage()));

    return $messages[$driverCode] ?? 'No pudimos guardar la información. Revisá que la base de datos esté importada y conectada correctamente.';
}

function email_exists(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return (bool) $stmt->fetch();
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'negocio';
}

function unique_business_slug(PDO $pdo, string $businessName): string
{
    $baseSlug = slugify($businessName);
    $slug = $baseSlug;
    $suffix = 2;

    $stmt = $pdo->prepare('SELECT id FROM negocios WHERE slug = :slug LIMIT 1');

    while (true) {
        $stmt->execute(['slug' => $slug]);

        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function find_user_by_email_and_role(PDO $pdo, string $email, string $role): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, rol, nombre, email, password_hash, estado
         FROM usuarios
         WHERE email = :email AND rol = :rol
         LIMIT 1'
    );
    $stmt->execute(['email' => $email, 'rol' => $role]);
    $user = $stmt->fetch();

    return $user ?: null;
}
