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

    if (!preg_match('/^\d+$/', $value)) {
        return null;
    }

    return number_format((float) $value, 2, '.', '');
}

function currency_code_for_country(?string $country): string
{
    $normalized = strtolower(trim((string) $country));
    $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized) ?: $normalized;

    $currencies = [
        'costa rica' => 'CRC',
        'mexico' => 'MXN',
        'guatemala' => 'GTQ',
        'el salvador' => 'USD',
        'honduras' => 'HNL',
        'panama' => 'PAB',
        'colombia' => 'COP',
        'ecuador' => 'USD',
        'peru' => 'PEN',
        'bolivia' => 'BOB',
        'chile' => 'CLP',
        'argentina' => 'ARS',
        'uruguay' => 'UYU',
        'paraguay' => 'PYG',
        'republica dominicana' => 'DOP',
    ];

    return $currencies[$normalized] ?? 'USD';
}

function format_product_price(?string $price, ?string $country): string
{
    if ($price === null || $price === '') {
        return '';
    }

    return currency_code_for_country($country) . ' ' . number_format((float) $price, 0, ',', '.');
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

function subcategory_by_slug(PDO $pdo, string $slug, string $type): ?array
{
    if ($slug === '') {
        return null;
    }

    $categorySlug = $type === 'servicio' ? 'servicios' : 'productos';
    $stmt = $pdo->prepare(
        "SELECT sc.id, sc.categoria_id, sc.nombre, sc.slug
         FROM subcategorias sc
         INNER JOIN categorias c ON c.id = sc.categoria_id
         WHERE sc.slug = :slug
           AND c.slug = :categoria_slug
           AND sc.activa = 1
         LIMIT 1"
    );
    $stmt->execute([
        'slug' => $slug,
        'categoria_slug' => $categorySlug,
    ]);
    $subcategory = $stmt->fetch();

    return $subcategory ?: null;
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

        if (!compress_uploaded_image($tmp, $target, $ext)) {
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

function compress_uploaded_image(string $tmp, string $target, string $ext): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return move_uploaded_file($tmp, $target);
    }

    $source = null;
    if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('imagecreatefromjpeg')) {
        $source = @imagecreatefromjpeg($tmp);
    } elseif ($ext === 'png' && function_exists('imagecreatefrompng')) {
        $source = @imagecreatefrompng($tmp);
    } elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
        $source = @imagecreatefromwebp($tmp);
    }

    if (!$source) {
        return move_uploaded_file($tmp, $target);
    }

    $width = imagesx($source);
    $height = imagesy($source);
    $maxSide = 1600;
    $scale = min(1, $maxSide / max($width, $height));
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));

    $image = imagecreatetruecolor($newWidth, $newHeight);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);
    imagecopyresampled($image, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    if (in_array($ext, ['jpg', 'jpeg'], true)) {
        $ok = imagejpeg($image, $target, 82);
    } elseif ($ext === 'png') {
        $ok = imagepng($image, $target, 7);
    } elseif ($ext === 'webp' && function_exists('imagewebp')) {
        $ok = imagewebp($image, $target, 82);
    } else {
        $ok = imagejpeg($image, $target, 82);
    }

    imagedestroy($source);
    imagedestroy($image);

    if (!$ok) {
        return move_uploaded_file($tmp, $target);
    }

    return true;
}

function product_row_to_api(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['nombre'],
        'description' => $row['descripcion'],
        'type' => $row['tipo'] === 'servicio' ? 'Servicio' : 'Producto',
        'price' => format_product_price($row['precio'], $row['pais'] ?? null),
        'price_raw' => $row['precio'],
        'old_price_raw' => $row['precio_anterior'],
        'img' => $row['imagen'] ?: 'assets/img/logo-tuyomall-nav.png',
        'status' => $row['estado'],
        'likes' => (int) $row['likes'],
        'saves' => (int) $row['guardados'],
        'comments' => (int) ($row['comentarios'] ?? 0),
        'premium' => (bool) $row['premium_boost'],
        'featured' => (bool) $row['destacado'],
        'locked_for_plan' => (bool) ($row['locked_for_plan'] ?? false),
        'liked_by_me' => (bool) ($row['liked_by_me'] ?? false),
        'saved_by_me' => (bool) ($row['saved_by_me'] ?? false),
        'seller' => $row['nombre_negocio'] ?? null,
        'sellerInit' => strtoupper(substr((string) ($row['nombre_negocio'] ?? 'T'), 0, 1)),
        'sellerAvatar' => $row['avatar_url'] ?? null,
        'sellerWhatsapp' => $row['whatsapp'] ?? null,
        'sellerColor' => '#FF6B47',
        'cat' => $row['subcategoria'] ?? $row['categoria'] ?? 'Sin subcategoria',
        'catSlug' => $row['subcategoria_slug'] ?? null,
        'mainCat' => $row['categoria'] ?? ($row['tipo'] === 'servicio' ? 'Servicios' : 'Productos'),
        'mainCatSlug' => $row['categoria_slug'] ?? ($row['tipo'] === 'servicio' ? 'servicios' : 'productos'),
        'prov' => $row['provincia'] ?? 'Toda LATAM',
        'business_id' => (int) $row['negocio_id'],
        'business_slug' => $row['negocio_slug'] ?? null,
    ];
}

function list_products(PDO $pdo, ?int $businessId = null, bool $includeInactive = false): void
{
    $premiumExistsSql = "EXISTS (
        SELECT 1
        FROM suscripciones sp
        WHERE sp.negocio_id = p.negocio_id
          AND sp.plan_codigo = 'premium'
          AND sp.estado = 'activa'
          AND (sp.expira_en IS NULL OR sp.expira_en > NOW())
    )";
    $activeRankSql = "(SELECT COUNT(*)
        FROM productos p2
        WHERE p2.negocio_id = p.negocio_id
          AND p2.estado = 'activo'
          AND (p2.creado_en > p.creado_en OR (p2.creado_en = p.creado_en AND p2.id >= p.id))
    )";
    $dashboardRankSql = "(SELECT COUNT(*)
        FROM productos p3
        WHERE p3.negocio_id = p.negocio_id
          AND p3.estado IN ('activo', 'borrador', 'pausado')
          AND (p3.creado_en > p.creado_en OR (p3.creado_en = p.creado_en AND p3.id >= p.id))
    )";
    $publicVisibilitySql = "({$premiumExistsSql} OR {$activeRankSql} <= 10)";

    if ($businessId) {
        $where = $includeInactive
            ? 'WHERE p.negocio_id = :negocio_id'
            : "WHERE p.negocio_id = :negocio_id AND p.estado = 'activo' AND {$publicVisibilitySql}";
    } else {
        $where = "WHERE p.estado = 'activo' AND {$publicVisibilitySql}";
    }
    $params = $businessId ? ['negocio_id' => $businessId] : [];
    $viewerUserId = (int) ($_SESSION['user_id'] ?? 0);
    $params['viewer_like_user_id'] = $viewerUserId;
    $params['viewer_save_user_id'] = $viewerUserId;
    $limit = min(24, max(1, (int) ($_GET['limit'] ?? 12)));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));

    $stmt = $pdo->prepare(
        "SELECT
            p.*,
            n.nombre_negocio,
            n.avatar_url,
            n.whatsapp,
            n.pais,
            n.provincia,
            n.slug AS negocio_slug,
            c.nombre AS categoria,
            c.slug AS categoria_slug,
            sc.nombre AS subcategoria,
            sc.slug AS subcategoria_slug,
            (SELECT pi.url FROM producto_imagenes pi WHERE pi.producto_id = p.id ORDER BY pi.orden ASC, pi.id ASC LIMIT 1) AS imagen,
            (SELECT COUNT(*) FROM likes l WHERE l.producto_id = p.id) AS likes,
            (SELECT COUNT(*) FROM guardados g WHERE g.producto_id = p.id) AS guardados,
            (SELECT COUNT(*) FROM comentarios cm WHERE cm.producto_id = p.id AND cm.estado = 'activo') AS comentarios,
            CASE
              WHEN {$premiumExistsSql} THEN 0
              WHEN {$dashboardRankSql} > 10 THEN 1
              ELSE 0
            END AS locked_for_plan,
            EXISTS (
                SELECT 1
                FROM likes lm
                WHERE lm.producto_id = p.id
                  AND lm.usuario_id = :viewer_like_user_id
            ) AS liked_by_me,
            EXISTS (
                SELECT 1
                FROM guardados gm
                WHERE gm.producto_id = p.id
                  AND gm.usuario_id = :viewer_save_user_id
            ) AS saved_by_me
         FROM productos p
         INNER JOIN negocios n ON n.id = p.negocio_id
         LEFT JOIN categorias c ON c.id = p.categoria_id
         LEFT JOIN subcategorias sc ON sc.id = p.subcategoria_id
         {$where}
         ORDER BY p.premium_boost DESC, p.destacado DESC, p.creado_en DESC
         LIMIT {$limit}
         OFFSET {$offset}"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    json_response([
        'ok' => true,
        'products' => array_map('product_row_to_api', $rows),
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($rows),
            'has_more' => count($rows) === $limit,
        ],
    ]);
}

function list_saved_products(PDO $pdo): void
{
    start_app_session();

    $role = (string) ($_SESSION['user_role'] ?? '');
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userId <= 0 || !in_array($role, ['cliente', 'emprendedor'], true)) {
        json_response(['ok' => false, 'message' => 'Debes iniciar sesion para ver tus guardados.'], 401);
    }

    $limit = min(24, max(1, (int) ($_GET['limit'] ?? 12)));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));

    $stmt = $pdo->prepare(
        "SELECT
            p.*,
            n.nombre_negocio,
            n.avatar_url,
            n.whatsapp,
            n.pais,
            n.provincia,
            n.slug AS negocio_slug,
            c.nombre AS categoria,
            c.slug AS categoria_slug,
            sc.nombre AS subcategoria,
            sc.slug AS subcategoria_slug,
            (SELECT pi.url FROM producto_imagenes pi WHERE pi.producto_id = p.id ORDER BY pi.orden ASC, pi.id ASC LIMIT 1) AS imagen,
            (SELECT COUNT(*) FROM likes l WHERE l.producto_id = p.id) AS likes,
            (SELECT COUNT(*) FROM guardados g2 WHERE g2.producto_id = p.id) AS guardados,
            (SELECT COUNT(*) FROM comentarios cm WHERE cm.producto_id = p.id AND cm.estado = 'activo') AS comentarios,
            EXISTS (
                SELECT 1
                FROM likes lm
                WHERE lm.producto_id = p.id
                  AND lm.usuario_id = :viewer_like_user_id
            ) AS liked_by_me,
            1 AS saved_by_me
         FROM guardados g
         INNER JOIN productos p ON p.id = g.producto_id
         INNER JOIN negocios n ON n.id = p.negocio_id
         LEFT JOIN categorias c ON c.id = p.categoria_id
         LEFT JOIN subcategorias sc ON sc.id = p.subcategoria_id
         WHERE g.usuario_id = :usuario_id
           AND p.estado = 'activo'
         ORDER BY g.creado_en DESC
         LIMIT {$limit}
         OFFSET {$offset}"
    );
    $stmt->execute([
        'usuario_id' => $userId,
        'viewer_like_user_id' => $userId,
    ]);
    $rows = $stmt->fetchAll();

    json_response([
        'ok' => true,
        'products' => array_map('product_row_to_api', $rows),
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($rows),
            'has_more' => count($rows) === $limit,
        ],
    ]);
}

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    start_app_session();
    $saved = ($_GET['saved'] ?? '') === '1';
    $mine = ($_GET['mine'] ?? '') === '1';
    $businessId = (int) ($_GET['business_id'] ?? 0);
    $businessSlug = trim((string) ($_GET['business_slug'] ?? ''));

    if ($saved) {
        list_saved_products($pdo);
    }

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
        'message' => 'Tu plan Gratis permite hasta 10 publicaciones. Activa Premium para publicar sin limite.',
        'upgrade_required' => true,
    ], 403);
}

$name = post_value('nombre');
$description = post_value('descripcion');
$type = post_value('tipo') === 'servicio' ? 'servicio' : 'producto';
$categoryId = category_id_by_slug($pdo, $type === 'servicio' ? 'servicios' : 'productos');
$subcategory = subcategory_by_slug($pdo, post_value('subcategoria'), $type);
$subcategoryId = $subcategory ? (int) $subcategory['id'] : null;
$price = money_to_decimal(post_value('precio'));
$oldPrice = money_to_decimal(post_value('precio_anterior'));

if ($name === '') {
    json_response(['ok' => false, 'message' => 'El nombre de la publicacion es obligatorio.'], 422);
}

if ($price === null) {
    json_response(['ok' => false, 'message' => 'El precio de la publicacion es obligatorio.'], 422);
}

if ($description === '') {
    json_response(['ok' => false, 'message' => 'La descripcion de la publicacion es obligatoria.'], 422);
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
                 subcategoria_id = :subcategoria_id,
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
            'subcategoria_id' => $subcategoryId,
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
                subcategoria_id,
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
                :subcategoria_id,
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
            'subcategoria_id' => $subcategoryId,
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

    $images = upload_product_images($productId, 1);

    if ($images) {
        $stmt = $pdo->prepare('DELETE FROM producto_imagenes WHERE producto_id = :producto_id');
        $stmt->execute(['producto_id' => $productId]);
    }

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
