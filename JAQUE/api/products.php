<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../includes/plan-rules.php';

function current_business_or_fail(PDO $pdo): array
{
    start_app_session();

    if (($_SESSION['user_role'] ?? null) !== 'emprendedor') {
        json_response(['ok' => false, 'message' => 'Debes iniciar sesion como emprendedor.'], 401);
    }

    $businessId = (int) ($_SESSION['business_id'] ?? 0);

    if ($businessId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM negocios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $businessId]);
        $business = $stmt->fetch();

        if ($business) {
            return $business;
        }
    }

    $business = get_business_for_user($pdo, (int) ($_SESSION['user_id'] ?? 0));

    if (!$business) {
        json_response(['ok' => false, 'message' => 'No encontramos tu negocio.'], 404);
    }

    $_SESSION['business_id'] = (int) $business['id'];
    return $business;
}

function money_to_decimal(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    $normalized = preg_replace('/[^0-9.,]/', '', $value) ?? '';
    $normalized = str_replace('.', '', $normalized);
    $normalized = str_replace(',', '.', $normalized);

    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    return number_format((float) $normalized, 2, '.', '');
}

function category_id_by_slug(PDO $pdo, string $slug): ?int
{
    if ($slug === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM categorias WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $id = $stmt->fetchColumn();

    return $id ? (int) $id : null;
}

function unique_product_slug(PDO $pdo, int $businessId, string $name): string
{
    $baseSlug = slugify($name);
    $slug = $baseSlug;
    $suffix = 2;
    $stmt = $pdo->prepare(
        'SELECT id FROM productos WHERE negocio_id = :negocio_id AND slug = :slug LIMIT 1'
    );

    while (true) {
        $stmt->execute([
            'negocio_id' => $businessId,
            'slug' => $slug,
        ]);

        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function product_belongs_to_business(PDO $pdo, int $productId, int $businessId): bool
{
    $stmt = $pdo->prepare(
        'SELECT id FROM productos WHERE id = :id AND negocio_id = :negocio_id LIMIT 1'
    );
    $stmt->execute([
        'id' => $productId,
        'negocio_id' => $businessId,
    ]);

    return (bool) $stmt->fetch();
}

function upload_product_images(int $productId, int $maxImages): array
{
    if (empty($_FILES['imagenes']) || !is_array($_FILES['imagenes']['name'])) {
        return [];
    }

    $baseDir = dirname(__DIR__) . '/uploads/products';

    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }

    $saved = [];
    $count = count($_FILES['imagenes']['name']);
    $limit = min($count, $maxImages);

    for ($i = 0; $i < $limit; $i++) {
        if ((int) $_FILES['imagenes']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmp = (string) $_FILES['imagenes']['tmp_name'][$i];
        $original = (string) $_FILES['imagenes']['name'][$i];
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            continue;
        }

        $fileName = $productId . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $target = $baseDir . '/' . $fileName;

        if (!move_uploaded_file($tmp, $target)) {
            continue;
        }

        $saved[] = [
            'url' => 'uploads/products/' . $fileName,
            'alt' => pathinfo($original, PATHINFO_FILENAME),
            'orden' => $i,
        ];
    }

    return $saved;
}

function product_row_to_api(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['nombre'],
        'description' => $row['descripcion'],
        'type' => $row['tipo'] === 'servicio' ? 'Servicio' : 'Producto',
        'price' => $row['precio'] !== null ? '₡' . number_format((float) $row['precio'], 0, ',', '.') : '',
        'price_raw' => $row['precio'],
        'old_price_raw' => $row['precio_anterior'],
        'img' => $row['imagen'] ?: 'assets/img/logo-tuyomall-nav.png',
        'status' => $row['estado'],
        'likes' => (int) $row['likes'],
        'saves' => (int) $row['guardados'],
        'premium' => (bool) $row['premium_boost'],
        'seller' => $row['nombre_negocio'] ?? null,
        'sellerInit' => strtoupper(substr((string) ($row['nombre_negocio'] ?? 'T'), 0, 1)),
        'sellerColor' => '#FF6B47',
        'cat' => $row['categoria'] ?? 'Servicios',
        'prov' => $row['provincia'] ?? 'Toda LATAM',
        'business_slug' => $row['negocio_slug'] ?? null,
    ];
}

function list_products(PDO $pdo, ?int $businessId = null, bool $includeInactive = false): void
{
    if ($businessId) {
        $where = $includeInactive
            ? 'WHERE p.negocio_id = :negocio_id'
            : "WHERE p.negocio_id = :negocio_id AND p.estado = 'activo'";
    } else {
        $where = "WHERE p.estado = 'activo'";
    }
    $params = $businessId ? ['negocio_id' => $businessId] : [];

    $stmt = $pdo->prepare(
        "SELECT
            p.*,
            n.nombre_negocio,
            n.provincia,
            n.slug AS negocio_slug,
            c.nombre AS categoria,
            (SELECT pi.url FROM producto_imagenes pi WHERE pi.producto_id = p.id ORDER BY pi.orden ASC, pi.id ASC LIMIT 1) AS imagen,
            (SELECT COUNT(*) FROM likes l WHERE l.producto_id = p.id) AS likes,
            (SELECT COUNT(*) FROM guardados g WHERE g.producto_id = p.id) AS guardados
         FROM productos p
         INNER JOIN negocios n ON n.id = p.negocio_id
         LEFT JOIN categorias c ON c.id = p.categoria_id
         {$where}
         ORDER BY p.premium_boost DESC, p.destacado DESC, p.creado_en DESC
         LIMIT 80"
    );
    $stmt->execute($params);

    json_response([
        'ok' => true,
        'products' => array_map('product_row_to_api', $stmt->fetchAll()),
    ]);
}

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    start_app_session();
    $mine = ($_GET['mine'] ?? '') === '1';
    $businessId = (int) ($_GET['business_id'] ?? 0);
    $businessSlug = trim((string) ($_GET['business_slug'] ?? ''));

    if ($mine) {
        $business = current_business_or_fail($pdo);
        list_products($pdo, (int) $business['id'], true);
    }

    if ($businessSlug !== '') {
        $stmt = $pdo->prepare('SELECT id FROM negocios WHERE slug = :slug AND estado = "activo" LIMIT 1');
        $stmt->execute(['slug' => $businessSlug]);
        $businessId = (int) $stmt->fetchColumn();
    }

    if ($businessId > 0) {
        list_products($pdo, $businessId, false);
    }

    list_products($pdo, null, false);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
}

$business = current_business_or_fail($pdo);
$businessId = (int) $business['id'];
$plan = get_business_plan($pdo, $businessId);
sync_business_plan($pdo, $businessId);
$action = post_value('action');

if ($action === 'delete' || $action === 'pause' || $action === 'activate') {
    $productId = (int) post_value('producto_id');

    if ($productId <= 0 || !product_belongs_to_business($pdo, $productId, $businessId)) {
        json_response(['ok' => false, 'message' => 'Publicacion no encontrada.'], 404);
    }

    $newState = $action === 'activate' ? 'activo' : 'pausado';

    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM productos WHERE id = :id AND negocio_id = :negocio_id');
        $stmt->execute(['id' => $productId, 'negocio_id' => $businessId]);
        json_response(['ok' => true, 'message' => 'Publicacion eliminada correctamente.']);
    }

    $stmt = $pdo->prepare(
        'UPDATE productos SET estado = :estado WHERE id = :id AND negocio_id = :negocio_id'
    );
    $stmt->execute([
        'estado' => $newState,
        'id' => $productId,
        'negocio_id' => $businessId,
    ]);

    json_response(['ok' => true, 'message' => 'Publicacion actualizada correctamente.']);
}

$productId = (int) post_value('producto_id');
$isUpdate = $action === 'update' && $productId > 0;

if ($isUpdate && !product_belongs_to_business($pdo, $productId, $businessId)) {
    json_response(['ok' => false, 'message' => 'Publicacion no encontrada.'], 404);
}

if (!$isUpdate && !can_create_product($pdo, $businessId)) {
    json_response([
        'ok' => false,
        'message' => 'Tu plan Gratis permite hasta 10 productos activos. Activa Premium para publicar sin limite.',
        'upgrade_required' => true,
    ], 403);
}

$name = post_value('nombre');
$description = post_value('descripcion');
$type = post_value('tipo') === 'servicio' ? 'servicio' : 'producto';
$categoryId = category_id_by_slug($pdo, post_value('categoria'));
$price = money_to_decimal(post_value('precio'));
$oldPrice = money_to_decimal(post_value('precio_anterior'));

if ($name === '') {
    json_response(['ok' => false, 'message' => 'El nombre de la publicacion es obligatorio.'], 422);
}

if ($oldPrice !== null && !plan_allows($plan, 'precio_tachado')) {
    json_response([
        'ok' => false,
        'message' => 'El precio tachado es una funcion Premium.',
        'upgrade_required' => true,
    ], 403);
}

$slug = $isUpdate ? null : unique_product_slug($pdo, $businessId, $name);
$isPremium = ($plan['codigo'] ?? 'gratis') === 'premium';

try {
    $pdo->beginTransaction();

    if ($isUpdate) {
        $stmt = $pdo->prepare(
            "UPDATE productos
             SET categoria_id = :categoria_id,
                 nombre = :nombre,
                 descripcion = :descripcion,
                 tipo = :tipo,
                 precio = :precio,
                 precio_anterior = :precio_anterior,
                 premium_boost = :premium_boost
             WHERE id = :id
               AND negocio_id = :negocio_id"
        );
        $stmt->execute([
            'categoria_id' => $categoryId,
            'nombre' => $name,
            'descripcion' => $description !== '' ? $description : null,
            'tipo' => $type,
            'precio' => $price,
            'precio_anterior' => $oldPrice,
            'premium_boost' => $isPremium ? 1 : 0,
            'id' => $productId,
            'negocio_id' => $businessId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO productos (
                negocio_id,
                categoria_id,
                nombre,
                slug,
                descripcion,
                tipo,
                precio,
                precio_anterior,
                moneda,
                premium_boost
             )
             VALUES (
                :negocio_id,
                :categoria_id,
                :nombre,
                :slug,
                :descripcion,
                :tipo,
                :precio,
                :precio_anterior,
                'CRC',
                :premium_boost
             )"
        );
        $stmt->execute([
            'negocio_id' => $businessId,
            'categoria_id' => $categoryId,
            'nombre' => $name,
            'slug' => $slug,
            'descripcion' => $description !== '' ? $description : null,
            'tipo' => $type,
            'precio' => $price,
            'precio_anterior' => $oldPrice,
            'premium_boost' => $isPremium ? 1 : 0,
        ]);

        $productId = (int) $pdo->lastInsertId();
    }

    $images = upload_product_images($productId, (int) ($plan['max_imagenes_producto'] ?? 3));

    foreach ($images as $image) {
        if (!can_upload_product_image($pdo, $businessId, $productId)) {
            break;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO producto_imagenes (producto_id, url, alt, orden)
             VALUES (:producto_id, :url, :alt, :orden)'
        );
        $stmt->execute([
            'producto_id' => $productId,
            'url' => $image['url'],
            'alt' => $image['alt'],
            'orden' => $image['orden'],
        ]);
    }

    $pdo->commit();

    json_response([
        'ok' => true,
        'message' => $isUpdate ? 'Publicacion actualizada correctamente.' : 'Publicacion creada correctamente.',
        'product_id' => $productId,
        'plan' => $plan['codigo'] ?? 'gratis',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    json_response(['ok' => false, 'message' => 'No pudimos guardar la publicacion.'], 500);
}
