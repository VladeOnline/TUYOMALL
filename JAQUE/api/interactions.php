<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';

require_post();
start_app_session();

$role = (string) ($_SESSION['user_role'] ?? '');

if (!in_array($role, ['cliente', 'emprendedor'], true)) {
    json_response(['ok' => false, 'message' => 'Inicia sesion como cliente o emprendedor para interactuar.'], 401);
}

$productId = (int) post_value('producto_id');
$action = post_value('accion');
$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($productId <= 0 || !in_array($action, ['like', 'guardar'], true)) {
    json_response(['ok' => false, 'message' => 'Solicitud invalida.'], 422);
}

$table = $action === 'like' ? 'likes' : 'guardados';

try {
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, negocio_id FROM productos WHERE id = :id AND estado = "activo" LIMIT 1');
    $stmt->execute(['id' => $productId]);
    $product = $stmt->fetch();

    if (!$product) {
        json_response(['ok' => false, 'message' => 'La publicacion no existe.'], 404);
    }

    if ($role === 'emprendedor') {
        $stmt = $pdo->prepare('SELECT id FROM negocios WHERE usuario_id = :usuario_id AND id = :negocio_id LIMIT 1');
        $stmt->execute([
            'usuario_id' => $userId,
            'negocio_id' => (int) $product['negocio_id'],
        ]);

        if ($stmt->fetch()) {
            json_response(['ok' => false, 'message' => 'Puedes apoyar publicaciones de otros negocios, pero no las tuyas.'], 403);
        }
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM {$table} WHERE producto_id = :producto_id AND usuario_id = :usuario_id LIMIT 1"
    );
    $stmt->execute([
        'producto_id' => $productId,
        'usuario_id' => $userId,
    ]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = :id");
        $stmt->execute(['id' => (int) $existing['id']]);
        $active = false;
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO {$table} (producto_id, usuario_id) VALUES (:producto_id, :usuario_id)"
        );
        $stmt->execute([
            'producto_id' => $productId,
            'usuario_id' => $userId,
        ]);
        $active = true;

        $stmt = $pdo->prepare(
            'INSERT INTO metricas_eventos (negocio_id, producto_id, usuario_id, evento, ip, user_agent)
             VALUES (:negocio_id, :producto_id, :usuario_id, :evento, :ip, :user_agent)'
        );
        $stmt->execute([
            'negocio_id' => (int) $product['negocio_id'],
            'producto_id' => $productId,
            'usuario_id' => $userId,
            'evento' => $action === 'like' ? 'like' : 'guardar',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE producto_id = :producto_id");
    $stmt->execute(['producto_id' => $productId]);

    json_response([
        'ok' => true,
        'active' => $active,
        'count' => (int) $stmt->fetchColumn(),
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'No pudimos guardar la interaccion.'], 500);
}
