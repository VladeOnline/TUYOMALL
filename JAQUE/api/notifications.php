<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../includes/plan-rules.php';

function display_user_name(array $row): string
{
    $name = trim((string) ($row['nombre'] ?? '') . ' ' . (string) ($row['apellido'] ?? ''));

    return $name !== '' ? $name : (string) ($row['email'] ?? 'Alguien');
}

function short_text(string $value, int $limit = 92): string
{
    $value = trim($value);

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $limit, '...', 'UTF-8');
    }

    return strlen($value) > $limit ? substr($value, 0, $limit - 3) . '...' : $value;
}

start_app_session();

if (($_SESSION['user_role'] ?? null) !== 'emprendedor') {
    json_response(['ok' => false, 'message' => 'Debes iniciar sesion como emprendedor.'], 401);
}

try {
    $pdo = db();
    $business = get_business_for_user($pdo, (int) ($_SESSION['user_id'] ?? 0));

    if (!$business) {
        json_response(['ok' => false, 'message' => 'No encontramos tu negocio.'], 404);
    }

    $_SESSION['business_id'] = (int) $business['id'];
    $businessId = (int) $business['id'];
    $ownerUserId = (int) $business['usuario_id'];

    $stmt = $pdo->prepare(
        "SELECT
            'comment' AS tipo,
            c.id AS evento_id,
            c.creado_en,
            c.comentario,
            p.id AS producto_id,
            p.nombre AS producto,
            u.nombre,
            u.apellido,
            u.email
         FROM comentarios c
         INNER JOIN productos p ON p.id = c.producto_id
         INNER JOIN usuarios u ON u.id = c.usuario_id
         WHERE p.negocio_id = :negocio_id_comments
           AND c.estado = 'activo'
           AND c.usuario_id <> :owner_user_id_comments

         UNION ALL

         SELECT
            'like' AS tipo,
            l.id AS evento_id,
            l.creado_en,
            NULL AS comentario,
            p.id AS producto_id,
            p.nombre AS producto,
            u.nombre,
            u.apellido,
            u.email
         FROM likes l
         INNER JOIN productos p ON p.id = l.producto_id
         INNER JOIN usuarios u ON u.id = l.usuario_id
         WHERE p.negocio_id = :negocio_id_likes
           AND l.usuario_id <> :owner_user_id_likes

         ORDER BY creado_en DESC
         LIMIT 15"
    );
    $stmt->execute([
        'negocio_id_comments' => $businessId,
        'owner_user_id_comments' => $ownerUserId,
        'negocio_id_likes' => $businessId,
        'owner_user_id_likes' => $ownerUserId,
    ]);

    $notifications = array_map(static function (array $row): array {
        $actor = display_user_name($row);
        $product = (string) ($row['producto'] ?? 'tu publicacion');
        $type = (string) $row['tipo'];

        if ($type === 'comment') {
            $text = trim((string) ($row['comentario'] ?? ''));
            return [
                'id' => 'comment-' . (int) $row['evento_id'],
                'type' => 'comment',
                'icon' => 'ti-message-circle',
                'title' => $product,
                'text' => $actor . ' comento' . ($text !== '' ? ': "' . short_text($text) . '"' : '.'),
                'time' => (string) $row['creado_en'],
                'productId' => (int) $row['producto_id'],
                'actor' => $actor,
            ];
        }

        return [
            'id' => 'like-' . (int) $row['evento_id'],
            'type' => 'like',
            'icon' => 'ti-heart',
            'title' => $product,
            'text' => $actor . ' le dio me gusta a tu publicacion.',
            'time' => (string) $row['creado_en'],
            'productId' => (int) $row['producto_id'],
            'actor' => $actor,
        ];
    }, $stmt->fetchAll());

    json_response([
        'ok' => true,
        'notifications' => $notifications,
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => database_public_message($e, 'notifications')], 500);
}
