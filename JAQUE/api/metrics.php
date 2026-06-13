<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../includes/plan-rules.php';

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $productId = (int) post_value('producto_id');
    $event = post_value('evento');

    if (!in_array($event, ['vista', 'click_whatsapp', 'guardar', 'like', 'compartir', 'perfil'], true)) {
        json_response(['ok' => false, 'message' => 'Evento invalido.'], 422);
    }

    $businessId = null;

    if ($productId > 0) {
        $stmt = $pdo->prepare('SELECT negocio_id FROM productos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $productId]);
        $businessId = $stmt->fetchColumn();
    } else {
        $businessId = (int) post_value('negocio_id') ?: null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO metricas_eventos (negocio_id, producto_id, usuario_id, evento, ip, user_agent)
         VALUES (:negocio_id, :producto_id, :usuario_id, :evento, :ip, :user_agent)'
    );
    start_app_session();
    $stmt->execute([
        'negocio_id' => $businessId ?: null,
        'producto_id' => $productId > 0 ? $productId : null,
        'usuario_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'evento' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    json_response(['ok' => true]);
}

if ($method !== 'GET') {
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
$plan = get_business_plan($pdo, $businessId);

if (!plan_allows($plan, 'estadisticas_avanzadas')) {
    json_response([
        'ok' => false,
        'message' => 'Las estadisticas avanzadas son una funcion Premium.',
        'upgrade_required' => true,
    ], 403);
}

$stmt = $pdo->prepare(
    "SELECT evento, COUNT(*) AS total
     FROM metricas_eventos
     WHERE negocio_id = :negocio_id
       AND creado_en >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY evento"
);
$stmt->execute(['negocio_id' => $businessId]);

json_response(['ok' => true, 'metrics' => $stmt->fetchAll()]);
