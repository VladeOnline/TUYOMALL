<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../includes/plan-rules.php';

function public_business_payload(PDO $pdo, array $business): array
{
    $businessId = (int) $business['id'];
    $plan = get_business_plan($pdo, $businessId);

    $stmt = $pdo->prepare(
        "SELECT tipo, valor, visible, orden
         FROM redes_negocio
         WHERE negocio_id = :negocio_id
           AND visible = 1
         ORDER BY orden ASC, id ASC"
    );
    $stmt->execute(['negocio_id' => $businessId]);
    $contacts = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS publicaciones,
            COALESCE((SELECT COUNT(*) FROM guardados g INNER JOIN productos p2 ON p2.id = g.producto_id WHERE p2.negocio_id = :negocio_id), 0) AS guardados,
            COALESCE((SELECT COUNT(*) FROM metricas_eventos m WHERE m.negocio_id = :negocio_id AND m.evento = 'vista'), 0) AS vistas,
            COALESCE((SELECT ROUND(AVG(calificacion), 1) FROM resenas r WHERE r.negocio_id = :negocio_id AND r.estado = 'aprobada'), 0) AS calificacion
         FROM productos p
         WHERE p.negocio_id = :negocio_id
           AND p.estado = 'activo'"
    );
    $stmt->execute(['negocio_id' => $businessId]);
    $stats = $stmt->fetch() ?: [];

    return [
        'id' => $businessId,
        'name' => $business['nombre_negocio'],
        'slug' => $business['slug'],
        'type' => $business['tipo'],
        'description' => $business['descripcion'],
        'story' => $business['historia'],
        'whatsapp' => $business['whatsapp'],
        'email' => $business['correo'],
        'country' => $business['pais'],
        'province' => $business['provincia'],
        'address' => $business['direccion'],
        'schedule' => $business['horario'],
        'avatar' => $business['avatar_url'] ?: 'assets/img/logo-tuyomall-nav.png',
        'cover' => $business['portada_url'],
        'plan' => $plan['codigo'] ?? 'gratis',
        'isPremium' => ($plan['codigo'] ?? 'gratis') === 'premium',
        'contacts' => $contacts,
        'stats' => [
            'posts' => (int) ($stats['publicaciones'] ?? 0),
            'views' => (int) ($stats['vistas'] ?? 0),
            'saves' => (int) ($stats['guardados'] ?? 0),
            'rating' => (float) ($stats['calificacion'] ?? 0),
        ],
    ];
}

function upload_business_asset(string $field, int $businessId): ?string
{
    if (empty($_FILES[$field]) || (int) $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $original = (string) $_FILES[$field]['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        json_response(['ok' => false, 'message' => 'Solo se permiten imagenes JPG, PNG o WEBP.'], 422);
    }

    if ((int) $_FILES[$field]['size'] > 4 * 1024 * 1024) {
        json_response(['ok' => false, 'message' => 'La imagen no puede pesar mas de 4MB.'], 422);
    }

    $dir = dirname(__DIR__) . '/uploads/business';

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fileName = $businessId . '-' . $field . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $dir . '/' . $fileName;

    if (!move_uploaded_file((string) $_FILES[$field]['tmp_name'], $target)) {
        json_response(['ok' => false, 'message' => 'No pudimos subir la imagen.'], 500);
    }

    return 'uploads/business/' . $fileName;
}

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $slug = post_value('slug') ?: trim((string) ($_GET['slug'] ?? ''));
    $id = (int) ($_GET['id'] ?? 0);
    $productId = (int) ($_GET['producto_id'] ?? $_GET['producto'] ?? 0);

    if ($slug !== '' || $id > 0 || $productId > 0) {
        if ($productId > 0) {
            $stmt = $pdo->prepare(
                "SELECT n.*
                 FROM negocios n
                 INNER JOIN productos p ON p.negocio_id = n.id
                 WHERE p.id = :producto_id
                   AND n.estado = 'activo'
                 LIMIT 1"
            );
            $stmt->execute(['producto_id' => $productId]);
        } else {
            $stmt = $slug !== ''
                ? $pdo->prepare("SELECT * FROM negocios WHERE slug = :slug AND estado = 'activo' LIMIT 1")
                : $pdo->prepare("SELECT * FROM negocios WHERE id = :id AND estado = 'activo' LIMIT 1");
            $stmt->execute($slug !== '' ? ['slug' => $slug] : ['id' => $id]);
        }
        $business = $stmt->fetch();

        if (!$business) {
            json_response(['ok' => false, 'message' => 'No encontramos el negocio.'], 404);
        }

        json_response(['ok' => true, 'business' => public_business_payload($pdo, $business)]);
    }

    start_app_session();

    if (($_SESSION['user_role'] ?? null) !== 'emprendedor') {
        json_response(['ok' => false, 'message' => 'Debes iniciar sesion como emprendedor.'], 401);
    }

    $business = get_business_for_user($pdo, (int) ($_SESSION['user_id'] ?? 0));

    if (!$business) {
        json_response(['ok' => false, 'message' => 'No encontramos tu negocio.'], 404);
    }

    json_response(['ok' => true, 'business' => public_business_payload($pdo, $business)]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
}

start_app_session();

if (($_SESSION['user_role'] ?? null) !== 'emprendedor') {
    json_response(['ok' => false, 'message' => 'Debes iniciar sesion como emprendedor.'], 401);
}

$business = get_business_for_user($pdo, (int) ($_SESSION['user_id'] ?? 0));

if (!$business) {
    json_response(['ok' => false, 'message' => 'No encontramos tu negocio.'], 404);
}

$businessId = (int) $business['id'];
$name = post_value('nombre_negocio') ?: (string) $business['nombre_negocio'];
$type = post_value('tipo') ?: null;
$description = post_value('descripcion') ?: null;
$story = post_value('historia') ?: null;
$whatsapp = post_value('whatsapp') ?: (string) $business['whatsapp'];
$email = strtolower(post_value('correo')) ?: null;
$country = post_value('pais') ?: (string) $business['pais'];
$province = post_value('provincia') ?: (string) $business['provincia'];
$address = post_value('direccion') ?: null;
$schedule = post_value('horario') ?: null;
$avatar = upload_business_asset('avatar', $businessId) ?: $business['avatar_url'];
$cover = upload_business_asset('portada', $businessId) ?: $business['portada_url'];

if ($name === '' || $whatsapp === '' || $country === '' || $province === '') {
    json_response(['ok' => false, 'message' => 'Nombre, WhatsApp, pais y provincia son obligatorios.'], 422);
}

if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'message' => 'Ingresa un correo valido.'], 422);
}

try {
    $stmt = $pdo->prepare(
        "UPDATE negocios
         SET nombre_negocio = :nombre_negocio,
             tipo = :tipo,
             descripcion = :descripcion,
             historia = :historia,
             whatsapp = :whatsapp,
             correo = :correo,
             pais = :pais,
             provincia = :provincia,
             direccion = :direccion,
             horario = :horario,
             avatar_url = :avatar_url,
             portada_url = :portada_url
         WHERE id = :id"
    );
    $stmt->execute([
        'nombre_negocio' => $name,
        'tipo' => $type,
        'descripcion' => $description,
        'historia' => $story,
        'whatsapp' => $whatsapp,
        'correo' => $email,
        'pais' => $country,
        'provincia' => $province,
        'direccion' => $address,
        'horario' => $schedule,
        'avatar_url' => $avatar,
        'portada_url' => $cover,
        'id' => $businessId,
    ]);

    $stmt = $pdo->prepare('SELECT * FROM negocios WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $businessId]);

    json_response([
        'ok' => true,
        'message' => 'Perfil actualizado correctamente.',
        'business' => public_business_payload($pdo, $stmt->fetch()),
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'No pudimos actualizar el negocio.'], 500);
}
