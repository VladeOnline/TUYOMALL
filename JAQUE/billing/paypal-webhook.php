<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paypal.php';
require_once __DIR__ . '/../includes/plan-rules.php';

function paypal_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function paypal_access_token(): string
{
    $ch = curl_init(PAYPAL_API_BASE . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: en_US'],
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status >= 400) {
        throw new RuntimeException('No se pudo autenticar con PayPal.');
    }

    $data = json_decode($response, true);
    return (string) ($data['access_token'] ?? '');
}

function verify_paypal_webhook(string $rawBody, array $event): bool
{
    if (
        PAYPAL_CLIENT_ID === 'TU_PAYPAL_CLIENT_ID'
        || PAYPAL_CLIENT_SECRET === 'TU_PAYPAL_CLIENT_SECRET'
        || PAYPAL_WEBHOOK_ID === 'TU_PAYPAL_WEBHOOK_ID'
    ) {
        return false;
    }

    $headers = array_change_key_case(getallheaders(), CASE_UPPER);
    $payload = [
        'auth_algo' => $headers['PAYPAL-AUTH-ALGO'] ?? '',
        'cert_url' => $headers['PAYPAL-CERT-URL'] ?? '',
        'transmission_id' => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
        'transmission_sig' => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
        'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
        'webhook_id' => PAYPAL_WEBHOOK_ID,
        'webhook_event' => $event,
    ];

    $token = paypal_access_token();
    $ch = curl_init(PAYPAL_API_BASE . '/v1/notifications/verify-webhook-signature');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status >= 400) {
        return false;
    }

    $verification = json_decode($response, true);
    return ($verification['verification_status'] ?? '') === 'SUCCESS';
}

function paypal_resource_value(array $resource, array $keys): ?string
{
    foreach ($keys as $key) {
        if (isset($resource[$key]) && is_scalar($resource[$key])) {
            return (string) $resource[$key];
        }
    }

    return null;
}

function find_business_id_from_paypal(PDO $pdo, array $event): ?int
{
    $resource = $event['resource'] ?? [];
    $customValue = paypal_resource_value($resource, ['custom_id', 'invoice_id', 'custom']);

    if ($customValue && preg_match('/(?:negocio|business|tm)[-_:]?(\d+)/i', $customValue, $match)) {
        return (int) $match[1];
    }

    if ($customValue && ctype_digit($customValue)) {
        return (int) $customValue;
    }

    $payerEmail = $resource['payer']['email_address']
        ?? $resource['subscriber']['email_address']
        ?? $resource['payer_email']
        ?? null;

    if (!$payerEmail) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT n.id
         FROM negocios n
         INNER JOIN usuarios u ON u.id = n.usuario_id
         WHERE u.email = :email OR n.correo = :email
         ORDER BY n.id DESC
         LIMIT 1'
    );
    $stmt->execute(['email' => strtolower((string) $payerEmail)]);
    $businessId = $stmt->fetchColumn();

    return $businessId ? (int) $businessId : null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    paypal_json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
}

$rawBody = file_get_contents('php://input') ?: '';
$event = json_decode($rawBody, true);

if (!is_array($event)) {
    paypal_json_response(['ok' => false, 'message' => 'Payload invalido.'], 400);
}

try {
    if (!verify_paypal_webhook($rawBody, $event)) {
        paypal_json_response(['ok' => false, 'message' => 'Firma PayPal no verificada.'], 401);
    }

    $eventType = (string) ($event['event_type'] ?? '');
    $acceptedEvents = [
        'PAYMENT.CAPTURE.COMPLETED',
        'CHECKOUT.ORDER.APPROVED',
        'BILLING.SUBSCRIPTION.ACTIVATED',
        'PAYMENT.SALE.COMPLETED',
    ];

    if (!in_array($eventType, $acceptedEvents, true)) {
        paypal_json_response(['ok' => true, 'message' => 'Evento ignorado.']);
    }

    $pdo = db();
    $businessId = find_business_id_from_paypal($pdo, $event);

    if (!$businessId) {
        paypal_json_response(['ok' => false, 'message' => 'No se encontro negocio para este pago.'], 422);
    }

    $resource = $event['resource'] ?? [];
    $paymentId = paypal_resource_value($resource, ['id', 'capture_id', 'billing_agreement_id']);
    $amount = (float) (
        $resource['amount']['value']
        ?? $resource['purchase_units'][0]['amount']['value']
        ?? 5.00
    );
    $currency = (string) (
        $resource['amount']['currency_code']
        ?? $resource['purchase_units'][0]['amount']['currency_code']
        ?? 'USD'
    );

    activate_premium_subscription($pdo, $businessId, 'paypal', $paymentId, $amount, $currency, $rawBody);

    paypal_json_response(['ok' => true, 'message' => 'Premium activado.']);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    paypal_json_response(['ok' => false, 'message' => 'No se pudo procesar el webhook.'], 500);
}
