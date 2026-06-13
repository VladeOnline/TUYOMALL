<?php
declare(strict_types=1);

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/plan-rules.php';

require_role('emprendedor', 'acceso-emprendedor.html');

$planData = [
    'codigo' => 'gratis',
    'nombre' => 'Plan Gratis',
    'isPremium' => false,
    'renovacionCancelada' => false,
    'expiraEn' => null,
    'renovacionTexto' => 'Premium no activo',
];

try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id FROM negocios WHERE usuario_id = :usuario_id LIMIT 1'
    );
    $stmt->execute(['usuario_id' => (int) ($_SESSION['user_id'] ?? 0)]);
    $business = $stmt->fetch();

    if ($business) {
        $businessId = (int) $business['id'];
        $plan = get_business_plan($pdo, $businessId);
        sync_business_plan($pdo, $businessId);

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
        $stmt->execute(['negocio_id' => $businessId]);
        $subscription = $stmt->fetch() ?: [];

        $expiraEn = $subscription['expira_en'] ?? null;
        $isPremium = ($plan['codigo'] ?? 'gratis') === 'premium';
        $renovacionCancelada = (bool) ($subscription['renovacion_cancelada'] ?? false);

        $planData = [
            'codigo' => (string) ($plan['codigo'] ?? 'gratis'),
            'nombre' => (string) ($plan['nombre'] ?? 'Plan Gratis'),
            'isPremium' => $isPremium,
            'renovacionCancelada' => $renovacionCancelada,
            'expiraEn' => $expiraEn,
            'renovacionTexto' => $isPremium
                ? ($renovacionCancelada ? 'Activo hasta: ' . ($expiraEn ?: 'fin del periodo') : 'Renovacion: ' . ($expiraEn ?: 'mensual activa'))
                : 'Activa Premium para destacar en el feed',
        ];
    }
} catch (Throwable $e) {
    $planData['dbUnavailable'] = true;
}

$html = file_get_contents(__DIR__ . '/dashboard-emprendedor.html');
$planJson = json_encode($planData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$html = str_replace(
    '</head>',
    "<script>window.TUYOMALL_PLAN = {$planJson};</script>\n</head>",
    $html
);

echo $html;
