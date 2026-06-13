<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../includes/plan-rules.php';

function contacts_business_or_fail(PDO $pdo): array
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
$business = contacts_business_or_fail($pdo);
$businessId = (int) $business['id'];

if ($method === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT id, tipo, valor, visible, orden
         FROM redes_negocio
         WHERE negocio_id = :negocio_id
         ORDER BY orden ASC, id ASC'
    );
    $stmt->execute(['negocio_id' => $businessId]);

    json_response(['ok' => true, 'contacts' => $stmt->fetchAll()]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
}

$plan = get_business_plan($pdo, $businessId);
$type = post_value('tipo');
$value = post_value('valor');

if (!in_array($type, ['whatsapp', 'correo', 'instagram', 'facebook', 'tiktok', 'pinterest', 'x', 'telegram', 'sitio', 'formulario'], true)) {
    json_response(['ok' => false, 'message' => 'Tipo de contacto invalido.'], 422);
}

if ($value === '') {
    json_response(['ok' => false, 'message' => 'Ingresa el contacto o enlace.'], 422);
}

if (!plan_allows($plan, 'multiples_contactos')) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM redes_negocio WHERE negocio_id = :negocio_id AND visible = 1'
    );
    $stmt->execute(['negocio_id' => $businessId]);

    if ((int) $stmt->fetchColumn() >= 1) {
        json_response([
            'ok' => false,
            'message' => 'El plan Gratis permite 1 contacto visible. Premium permite multiples botones de contacto.',
            'upgrade_required' => true,
        ], 403);
    }
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO redes_negocio (negocio_id, tipo, valor, visible, orden)
         VALUES (:negocio_id, :tipo, :valor, 1, 0)'
    );
    $stmt->execute([
        'negocio_id' => $businessId,
        'tipo' => $type,
        'valor' => $value,
    ]);

    json_response(['ok' => true, 'message' => 'Contacto guardado correctamente.']);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'No pudimos guardar el contacto.'], 500);
}
