<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';

start_app_session();

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function comment_row_to_api(array $row): array
{
    $name = trim((string) (($row['nombre'] ?? '') . ' ' . ($row['apellido'] ?? '')));
    if ($name === '') {
        $name = (string) ($row['email'] ?? 'Usuario TuyoMall');
    }

    return [
        'id' => (int) $row['id'],
        'product_id' => (int) $row['producto_id'],
        'user_id' => (int) $row['usuario_id'],
        'name' => $name,
        'role' => $row['rol'] ?? '',
        'initial' => strtoupper(substr($name, 0, 1)),
        'comment' => $row['comentario'],
        'created_at' => $row['creado_en'],
    ];
}

function product_exists(PDO $pdo, int $productId): bool
{
    $stmt = $pdo->prepare('SELECT id FROM productos WHERE id = :id AND estado = "activo" LIMIT 1');
    $stmt->execute(['id' => $productId]);
    return (bool) $stmt->fetch();
}

if ($method === 'GET') {
    $productId = (int) ($_GET['producto_id'] ?? 0);
    $limit = min(20, max(1, (int) ($_GET['limit'] ?? 20)));

    if ($productId <= 0) {
        json_response(['ok' => false, 'message' => 'Publicacion invalida.'], 422);
    }

    $stmt = $pdo->prepare(
        "SELECT
            c.id,
            c.producto_id,
            c.usuario_id,
            c.comentario,
            c.creado_en,
            u.nombre,
            u.apellido,
            u.email,
            u.rol
         FROM comentarios c
         INNER JOIN usuarios u ON u.id = c.usuario_id
         WHERE c.producto_id = :producto_id
           AND c.estado = 'activo'
         ORDER BY c.creado_en DESC
         LIMIT {$limit}"
    );
    $stmt->execute(['producto_id' => $productId]);
    $rows = array_reverse($stmt->fetchAll());

    json_response([
        'ok' => true,
        'comments' => array_map('comment_row_to_api', $rows),
    ]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
}

$role = (string) ($_SESSION['user_role'] ?? '');
$userId = (int) ($_SESSION['user_id'] ?? 0);

if (!in_array($role, ['cliente', 'emprendedor'], true) || $userId <= 0) {
    json_response(['ok' => false, 'message' => 'Inicia sesion como cliente o emprendedor para comentar.'], 401);
}

$productId = (int) post_value('producto_id');
$comment = trim(post_value('comentario'));

if ($productId <= 0 || !product_exists($pdo, $productId)) {
    json_response(['ok' => false, 'message' => 'La publicacion no existe.'], 404);
}

if ($comment === '') {
    json_response(['ok' => false, 'message' => 'Escribe un comentario antes de publicar.'], 422);
}

$commentLength = function_exists('mb_strlen') ? mb_strlen($comment) : strlen($comment);
if ($commentLength > 600) {
    json_response(['ok' => false, 'message' => 'El comentario puede tener maximo 600 caracteres.'], 422);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO comentarios (producto_id, usuario_id, comentario)
         VALUES (:producto_id, :usuario_id, :comentario)'
    );
    $stmt->execute([
        'producto_id' => $productId,
        'usuario_id' => $userId,
        'comentario' => $comment,
    ]);

    $stmt = $pdo->prepare(
        "SELECT
            c.id,
            c.producto_id,
            c.usuario_id,
            c.comentario,
            c.creado_en,
            u.nombre,
            u.apellido,
            u.email,
            u.rol
         FROM comentarios c
         INNER JOIN usuarios u ON u.id = c.usuario_id
         WHERE c.id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => (int) $pdo->lastInsertId()]);

    json_response([
        'ok' => true,
        'comment' => comment_row_to_api($stmt->fetch()),
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => database_public_message($e, 'comments')], 500);
}
