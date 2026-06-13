<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../includes/plan-rules.php';

function coupon_business_or_fail(PDO $pdo): array
{
    start_app_session();

    if (($_SESSION['user_role'] ?? null) !== 'emprendedor') {
        json_response(['ok' => false, 'message' => 'Debes iniciar sesion como emprendedor.'], 401);
    }

    $business = get_business_for_user($pdo, (int) ($_SESSION['user_id'] ?? 0));

    if (!$business) {
        json_response(['ok' => false, 'message' => 'No encontramos tu negocio.'], 404);
    }

    return $business;
}

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$business = coupon_business_or_fail($pdo);
$businessId = (int) $business['id'];
$plan = get_business_plan($pdo, $businessId);

if (!plan_allows($plan, 'cupones')) {
    json_response([
        'ok' => false,
        'message' => 'Los cupones, descuentos y precio tachado son funciones Premium.',
        'upgrade_required' => true,
    ], 403);
}

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT * FROM cupones WHERE negocio_id = :negocio_id ORDER BY creado_en DESC'
    );
    $stmt->execute(['negocio_id' => $businessId]);
    json_response(['ok' => true, 'coupons' => $stmt->fetchAll()]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
}

$code = strtoupper(post_value('codigo'));
$description = post_value('descripcion');
$type = post_value('descuento_tipo');
$value = post_value('valor');

if ($code === '' || $description === '') {
    json_response(['ok' => false, 'message' => 'Completa el codigo y la descripcion del cupon.'], 422);
}

if (!in_array($type, ['porcentaje', 'monto', 'texto'], true)) {
    $type = 'texto';
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO cupones (
            negocio_id,
            codigo,
            descripcion,
            descuento_tipo,
            valor,
            activo
         )
         VALUES (
            :negocio_id,
            :codigo,
            :descripcion,
            :descuento_tipo,
            :valor,
            1
         )"
    );
    $stmt->execute([
        'negocio_id' => $businessId,
        'codigo' => $code,
        'descripcion' => $description,
        'descuento_tipo' => $type,
        'valor' => is_numeric($value) ? $value : null,
    ]);

    json_response(['ok' => true, 'message' => 'Cupon creado correctamente.']);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'No pudimos guardar el cupon.'], 500);
}
