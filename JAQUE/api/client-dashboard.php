<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';

start_app_session();

if (($_SESSION['user_role'] ?? null) !== 'cliente') {
    json_response(['ok' => false, 'message' => 'Debes iniciar sesion como cliente.'], 401);
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $phone = post_value('telefono');
    $country = post_value('pais');
    $province = post_value('provincia');
    $address = post_value('direccion');

    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'UPDATE usuarios
             SET telefono = :telefono,
                 pais = :pais,
                 provincia = :provincia,
                 direccion = :direccion
             WHERE id = :id
               AND rol = "cliente"'
        );
        $stmt->execute([
            'telefono' => $phone !== '' ? $phone : null,
            'pais' => $country !== '' ? $country : null,
            'provincia' => $province !== '' ? $province : null,
            'direccion' => $address !== '' ? $address : null,
            'id' => $userId,
        ]);

        json_response(['ok' => true, 'message' => 'Datos actualizados correctamente.']);
    } catch (Throwable $e) {
        json_response(['ok' => false, 'message' => database_public_message($e, 'client-dashboard-update')], 500);
    }
}

function client_product_select_sql(string $sourceTable): string
{
    return "
        SELECT
            p.id,
            p.nombre,
            p.descripcion,
            p.tipo,
            p.precio,
            p.premium_boost,
            n.id AS negocio_id,
            n.nombre_negocio,
            n.slug AS negocio_slug,
            n.avatar_url,
            n.whatsapp,
            n.provincia,
            sc.nombre AS subcategoria,
            (SELECT pi.url FROM producto_imagenes pi WHERE pi.producto_id = p.id ORDER BY pi.orden ASC, pi.id ASC LIMIT 1) AS imagen,
            (SELECT COUNT(*) FROM likes l2 WHERE l2.producto_id = p.id) AS likes,
            (SELECT COUNT(*) FROM guardados g2 WHERE g2.producto_id = p.id) AS guardados,
            (SELECT COUNT(*) FROM comentarios c2 WHERE c2.producto_id = p.id AND c2.estado = 'activo') AS comentarios,
            src.creado_en AS interaccion_en
        FROM {$sourceTable} src
        INNER JOIN productos p ON p.id = src.producto_id
        INNER JOIN negocios n ON n.id = p.negocio_id
        LEFT JOIN subcategorias sc ON sc.id = p.subcategoria_id
        WHERE src.usuario_id = :usuario_id
          AND p.estado = 'activo'
        ORDER BY src.creado_en DESC
        LIMIT 8
    ";
}

function product_card(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['nombre'],
        'description' => (string) ($row['descripcion'] ?? ''),
        'type' => $row['tipo'] === 'servicio' ? 'Servicio' : 'Producto',
        'price' => $row['precio'] !== null ? 'CRC ' . number_format((float) $row['precio'], 0, ',', '.') : '',
        'img' => $row['imagen'] ?: 'assets/img/logo-tuyomall-nav.png',
        'premium' => (bool) $row['premium_boost'],
        'likes' => (int) $row['likes'],
        'saves' => (int) $row['guardados'],
        'comments' => (int) $row['comentarios'],
        'seller' => (string) ($row['nombre_negocio'] ?? 'Negocio TuyoMall'),
        'sellerAvatar' => $row['avatar_url'] ?? null,
        'sellerInit' => strtoupper(substr((string) ($row['nombre_negocio'] ?? 'T'), 0, 1)),
        'sellerWhatsapp' => $row['whatsapp'] ?? null,
        'subcategory' => (string) ($row['subcategoria'] ?? 'Sin subcategoria'),
        'province' => (string) ($row['provincia'] ?? 'Toda LATAM'),
        'businessSlug' => $row['negocio_slug'] ?? null,
        'businessId' => (int) $row['negocio_id'],
        'createdAt' => (string) $row['interaccion_en'],
    ];
}

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT id, nombre, apellido, email, telefono, pais, provincia, direccion, creado_en
         FROM usuarios
         WHERE id = :id AND rol = "cliente"
         LIMIT 1'
    );
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(['ok' => false, 'message' => 'No encontramos tu cuenta de cliente.'], 404);
    }

    $stats = [];
    foreach (['guardados' => 'guardados', 'likes' => 'likes', 'comentarios' => 'comentarios'] as $key => $table) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE usuario_id = :usuario_id");
        $stmt->execute(['usuario_id' => $userId]);
        $stats[$key] = (int) $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare(client_product_select_sql('guardados'));
    $stmt->execute(['usuario_id' => $userId]);
    $saved = array_map('product_card', $stmt->fetchAll());

    $stmt = $pdo->prepare(client_product_select_sql('likes'));
    $stmt->execute(['usuario_id' => $userId]);
    $liked = array_map('product_card', $stmt->fetchAll());

    $stmt = $pdo->prepare(
        "SELECT
            c.id,
            c.comentario,
            c.creado_en,
            p.id AS producto_id,
            p.nombre AS producto,
            n.nombre_negocio,
            n.slug AS negocio_slug
         FROM comentarios c
         INNER JOIN productos p ON p.id = c.producto_id
         INNER JOIN negocios n ON n.id = p.negocio_id
         WHERE c.usuario_id = :usuario_id
           AND c.estado = 'activo'
         ORDER BY c.creado_en DESC
         LIMIT 8"
    );
    $stmt->execute(['usuario_id' => $userId]);
    $comments = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'comment' => (string) $row['comentario'],
            'createdAt' => (string) $row['creado_en'],
            'productId' => (int) $row['producto_id'],
            'product' => (string) $row['producto'],
            'business' => (string) $row['nombre_negocio'],
            'businessSlug' => $row['negocio_slug'] ?? null,
        ];
    }, $stmt->fetchAll());

    json_response([
        'ok' => true,
        'user' => [
            'id' => (int) $user['id'],
            'name' => trim((string) $user['nombre'] . ' ' . (string) ($user['apellido'] ?? '')),
            'email' => (string) $user['email'],
            'phone' => (string) ($user['telefono'] ?? ''),
            'country' => (string) ($user['pais'] ?? ''),
            'province' => (string) ($user['provincia'] ?? ''),
            'address' => (string) ($user['direccion'] ?? ''),
            'createdAt' => (string) $user['creado_en'],
        ],
        'stats' => $stats,
        'saved' => $saved,
        'liked' => $liked,
        'comments' => $comments,
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => database_public_message($e, 'client-dashboard')], 500);
}
