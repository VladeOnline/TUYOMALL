<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../includes/paypal-config.php';
require_once __DIR__ . '/../includes/plan-rules.php';

require_post();
require_role('emprendedor', '../acceso-emprendedor.html');

function paypal_configured(): bool
{
    return PAYPAL_CLIENT_ID !== 'TU_PAYPAL_CLIENT_ID'
        && PAYPAL_CLIENT_SECRET !== 'TU_PAYPAL_CLIENT_SECRET';
}

function paypal_cancel_remote_subscription(string $subscriptionId): void
{
    if (!paypal_configured()) {
        return;
    }

    $tokenRequest = curl_init(PAYPAL_API_BASE . '/v1/oauth2/token');
    curl_setopt_array($tokenRequest, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: en_US'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $tokenResponse = curl_exec($tokenRequest);
    $tokenStatus = (int) curl_getinfo($tokenRequest, CURLINFO_HTTP_CODE);
    curl_close($tokenRequest);

    if ($tokenResponse === false || $tokenStatus >= 400) {
        throw new RuntimeException('No se pudo autenticar con PayPal.');
    }

    $tokenData = json_decode($tokenResponse, true);
    $token = (string) ($tokenData['access_token'] ?? '');

    if ($token === '') {
        throw new RuntimeException('PayPal no devolvio token.');
    }

    $cancelRequest = curl_init(PAYPAL_API_BASE . '/v1/billing/subscriptions/' . rawurlencode($subscriptionId) . '/cancel');
    curl_setopt_array($cancelRequest, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['reason' => 'El emprendedor cancelo la renovacion desde TuyoMall.']),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($cancelRequest);
    $cancelStatus = (int) curl_getinfo($cancelRequest, CURLINFO_HTTP_CODE);
    curl_close($cancelRequest);

    if ($cancelStatus >= 400) {
        throw new RuntimeException('PayPal no pudo cancelar la suscripcion.');
    }
}

try {
    $pdo = db();
    $business = get_business_for_user($pdo, (int) ($_SESSION['user_id'] ?? 0));

    if (!$business) {
        json_response(['ok' => false, 'message' => 'No encontramos tu negocio.'], 404);
    }

    $stmt = $pdo->prepare(
        "SELECT proveedor, proveedor_ref
         FROM suscripciones
         WHERE negocio_id = :negocio_id
           AND plan_codigo = 'premium'
           AND estado = 'activa'
           AND renovacion_cancelada = 0
           AND (expira_en IS NULL OR expira_en > NOW())
         ORDER BY inicia_en DESC
         LIMIT 1"
    );
    $stmt->execute(['negocio_id' => (int) $business['id']]);
    $subscription = $stmt->fetch();

    if (
        $subscription
        && ($subscription['proveedor'] ?? '') === 'paypal'
        && !empty($subscription['proveedor_ref'])
        && substr((string) $subscription['proveedor_ref'], 0, 2) === 'I-'
    ) {
        paypal_cancel_remote_subscription((string) $subscription['proveedor_ref']);
    }

    $expiresAt = cancel_premium_renewal($pdo, (int) $business['id']);

    if ($expiresAt === null) {
        json_response(['ok' => false, 'message' => 'No hay una suscripcion Premium activa para cancelar.'], 422);
    }

    json_response([
        'ok' => true,
        'expira_en' => $expiresAt,
        'message' => 'Renovacion cancelada. Premium sigue activo hasta terminar el periodo pagado.',
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'No pudimos cancelar la renovacion.'], 500);
}
