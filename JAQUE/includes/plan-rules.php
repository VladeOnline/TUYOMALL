<?php
declare(strict_types=1);

function get_business_plan(PDO $pdo, int $businessId): array
{
    $stmt = $pdo->prepare(
        "SELECT p.*
         FROM suscripciones s
         INNER JOIN planes p ON p.codigo = s.plan_codigo
         WHERE s.negocio_id = :negocio_id
           AND s.plan_codigo = 'premium'
           AND s.estado = 'activa'
           AND (s.expira_en IS NULL OR s.expira_en > NOW())
         ORDER BY s.inicia_en DESC
         LIMIT 1"
    );
    $stmt->execute(['negocio_id' => $businessId]);
    $premiumPlan = $stmt->fetch();

    if ($premiumPlan) {
        return $premiumPlan;
    }

    $stmt = $pdo->prepare('SELECT * FROM planes WHERE codigo = :codigo LIMIT 1');
    $stmt->execute(['codigo' => 'gratis']);

    return $stmt->fetch() ?: [
        'codigo' => 'gratis',
        'max_productos' => 10,
        'max_imagenes_producto' => 3,
        'max_categorias' => 5,
        'max_etiquetas' => 5,
        'permite_cupones' => 0,
        'permite_resenas' => 0,
        'permite_precio_tachado' => 0,
        'multiples_contactos' => 0,
        'prioridad_feed' => 0,
        'estadisticas_avanzadas' => 0,
        'soporte_prioritario' => 0,
    ];
}

function sync_business_plan(PDO $pdo, int $businessId): string
{
    $plan = get_business_plan($pdo, $businessId);
    $planCode = (string) ($plan['codigo'] ?? 'gratis');

    $stmt = $pdo->prepare(
        'UPDATE negocios SET plan_codigo = :plan_codigo WHERE id = :negocio_id'
    );
    $stmt->execute([
        'plan_codigo' => $planCode,
        'negocio_id' => $businessId,
    ]);

    return $planCode;
}

function get_business_for_user(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM negocios WHERE usuario_id = :usuario_id LIMIT 1'
    );
    $stmt->execute(['usuario_id' => $userId]);
    $business = $stmt->fetch();

    return $business ?: null;
}

function activate_premium_subscription(
    PDO $pdo,
    int $businessId,
    string $provider,
    ?string $providerPaymentId,
    float $amount,
    string $currency = 'USD',
    ?string $payload = null
): void {
    if ($providerPaymentId !== null && $providerPaymentId !== '') {
        $stmt = $pdo->prepare(
            "SELECT id
             FROM pagos
             WHERE proveedor = :proveedor
               AND proveedor_pago_id = :proveedor_pago_id
               AND estado = 'aprobado'
             LIMIT 1"
        );
        $stmt->execute([
            'proveedor' => $provider,
            'proveedor_pago_id' => $providerPaymentId,
        ]);

        if ($stmt->fetch()) {
            return;
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "UPDATE suscripciones
         SET estado = 'cancelada'
         WHERE negocio_id = :negocio_id
           AND plan_codigo = 'premium'
           AND estado IN ('pendiente', 'vencida')"
    );
    $stmt->execute(['negocio_id' => $businessId]);

    $stmt = $pdo->prepare(
        "INSERT INTO suscripciones (
            negocio_id,
            plan_codigo,
            estado,
            renovacion_cancelada,
            proveedor,
            proveedor_ref,
            inicia_en,
            expira_en
         )
         VALUES (
            :negocio_id,
            'premium',
            'activa',
            0,
            :proveedor,
            :proveedor_ref,
            NOW(),
            DATE_ADD(NOW(), INTERVAL 1 MONTH)
         )"
    );
    $stmt->execute([
        'negocio_id' => $businessId,
        'proveedor' => $provider,
        'proveedor_ref' => $providerPaymentId,
    ]);

    $subscriptionId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        "INSERT INTO pagos (
            negocio_id,
            suscripcion_id,
            plan_codigo,
            proveedor,
            proveedor_pago_id,
            monto,
            moneda,
            estado,
            payload,
            pagado_en
         )
         VALUES (
            :negocio_id,
            :suscripcion_id,
            'premium',
            :proveedor,
            :proveedor_pago_id,
            :monto,
            :moneda,
            'aprobado',
            :payload,
            NOW()
         )"
    );
    $stmt->execute([
        'negocio_id' => $businessId,
        'suscripcion_id' => $subscriptionId,
        'proveedor' => $provider,
        'proveedor_pago_id' => $providerPaymentId,
        'monto' => $amount,
        'moneda' => $currency,
        'payload' => $payload,
    ]);

    $stmt = $pdo->prepare(
        "UPDATE negocios SET plan_codigo = 'premium' WHERE id = :negocio_id"
    );
    $stmt->execute(['negocio_id' => $businessId]);

    $pdo->commit();
}

function cancel_premium_renewal(PDO $pdo, int $businessId): ?string
{
    $stmt = $pdo->prepare(
        "SELECT id, expira_en
         FROM suscripciones
         WHERE negocio_id = :negocio_id
           AND plan_codigo = 'premium'
           AND estado = 'activa'
           AND (expira_en IS NULL OR expira_en > NOW())
         ORDER BY inicia_en DESC
         LIMIT 1"
    );
    $stmt->execute(['negocio_id' => $businessId]);
    $subscription = $stmt->fetch();

    if (!$subscription) {
        return null;
    }

    $expiresAt = $subscription['expira_en'] ?: null;

    if ($expiresAt === null) {
        $stmt = $pdo->prepare(
            "UPDATE suscripciones
             SET renovacion_cancelada = 1,
                 cancelada_en = NOW(),
                 expira_en = DATE_ADD(NOW(), INTERVAL 1 MONTH)
             WHERE id = :id"
        );
        $stmt->execute(['id' => (int) $subscription['id']]);

        $stmt = $pdo->prepare('SELECT expira_en FROM suscripciones WHERE id = :id');
        $stmt->execute(['id' => (int) $subscription['id']]);
        return (string) $stmt->fetchColumn();
    }

    $stmt = $pdo->prepare(
        "UPDATE suscripciones
         SET renovacion_cancelada = 1,
             cancelada_en = NOW()
         WHERE id = :id"
    );
    $stmt->execute(['id' => (int) $subscription['id']]);

    return (string) $expiresAt;
}

function can_create_product(PDO $pdo, int $businessId): bool
{
    $plan = get_business_plan($pdo, $businessId);
    $maxProducts = $plan['max_productos'];

    if ($maxProducts === null) {
        return true;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM productos
         WHERE negocio_id = :negocio_id
           AND estado IN ('activo', 'borrador')"
    );
    $stmt->execute(['negocio_id' => $businessId]);

    return (int) $stmt->fetchColumn() < (int) $maxProducts;
}

function can_upload_product_image(PDO $pdo, int $businessId, int $productId): bool
{
    $plan = get_business_plan($pdo, $businessId);
    $maxImages = (int) ($plan['max_imagenes_producto'] ?? 3);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM producto_imagenes
         WHERE producto_id = :producto_id"
    );
    $stmt->execute(['producto_id' => $productId]);

    return (int) $stmt->fetchColumn() < $maxImages;
}

function plan_allows(array $plan, string $feature): bool
{
    $allowedFeatures = [
        'cupones' => 'permite_cupones',
        'resenas' => 'permite_resenas',
        'precio_tachado' => 'permite_precio_tachado',
        'multiples_contactos' => 'multiples_contactos',
        'prioridad_feed' => 'prioridad_feed',
        'estadisticas_avanzadas' => 'estadisticas_avanzadas',
        'soporte_prioritario' => 'soporte_prioritario',
    ];

    if (!isset($allowedFeatures[$feature])) {
        return false;
    }

    return (int) ($plan[$allowedFeatures[$feature]] ?? 0) === 1;
}
