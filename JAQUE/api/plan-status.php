<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../includes/plan-rules.php';

start_app_session();

if (($_SESSION['user_role'] ?? null) !== 'emprendedor') {
    json_response([
        'ok' => true,
        'authenticated' => isset($_SESSION['user_id']),
        'is_business' => false,
        'is_premium' => false,
    ]);
}

$pdo = db();
$business = get_business_for_user($pdo, (int) ($_SESSION['user_id'] ?? 0));

if (!$business) {
    json_response([
        'ok' => true,
        'authenticated' => true,
        'is_business' => true,
        'has_business' => false,
        'is_premium' => false,
    ]);
}

$plan = get_business_plan($pdo, (int) $business['id']);

$stmt = $pdo->prepare(
    "SELECT expira_en, renovacion_cancelada
     FROM suscripciones
     WHERE negocio_id = :negocio_id
       AND plan_codigo = 'premium'
       AND estado = 'activa'
       AND (expira_en IS NULL OR expira_en > NOW())
     ORDER BY inicia_en DESC
     LIMIT 1"
);
$stmt->execute(['negocio_id' => (int) $business['id']]);
$subscription = $stmt->fetch() ?: [];

json_response([
    'ok' => true,
    'authenticated' => true,
    'is_business' => true,
    'has_business' => true,
    'business_id' => (int) $business['id'],
    'plan_codigo' => (string) ($plan['codigo'] ?? 'gratis'),
    'is_premium' => (($plan['codigo'] ?? 'gratis') === 'premium'),
    'expires_at' => $subscription['expira_en'] ?? null,
    'renewal_cancelled' => (bool) ($subscription['renovacion_cancelada'] ?? false),
]);
