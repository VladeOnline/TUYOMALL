<?php
declare(strict_types=1);

$paypalConfigPath = __DIR__ . '/../config/paypal.php';
$paypalConfig = is_file($paypalConfigPath) ? require $paypalConfigPath : [];

if (is_array($paypalConfig)) {
    $mode = strtolower((string) ($paypalConfig['mode'] ?? 'live'));
    $apiBase = $mode === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

    if (!defined('PAYPAL_CLIENT_ID')) {
        define('PAYPAL_CLIENT_ID', (string) ($paypalConfig['client_id'] ?? 'TU_PAYPAL_CLIENT_ID'));
    }

    if (!defined('PAYPAL_CLIENT_SECRET')) {
        define('PAYPAL_CLIENT_SECRET', (string) ($paypalConfig['client_secret'] ?? 'TU_PAYPAL_CLIENT_SECRET'));
    }

    if (!defined('PAYPAL_WEBHOOK_ID')) {
        define('PAYPAL_WEBHOOK_ID', (string) ($paypalConfig['webhook_id'] ?? 'TU_PAYPAL_WEBHOOK_ID'));
    }

    if (!defined('PAYPAL_PLAN_ID')) {
        define('PAYPAL_PLAN_ID', (string) ($paypalConfig['plan_id'] ?? ''));
    }

    if (!defined('PAYPAL_RETURN_URL')) {
        define('PAYPAL_RETURN_URL', (string) ($paypalConfig['return_url'] ?? ''));
    }

    if (!defined('PAYPAL_CANCEL_URL')) {
        define('PAYPAL_CANCEL_URL', (string) ($paypalConfig['cancel_url'] ?? ''));
    }

    if (!defined('PAYPAL_TAX_PERCENTAGE')) {
        define('PAYPAL_TAX_PERCENTAGE', (string) ($paypalConfig['tax_percentage'] ?? '0'));
    }

    if (!defined('PAYPAL_API_BASE')) {
        define('PAYPAL_API_BASE', $apiBase);
    }
}

if (!defined('PAYPAL_CLIENT_ID')) {
    define('PAYPAL_CLIENT_ID', 'TU_PAYPAL_CLIENT_ID');
}

if (!defined('PAYPAL_CLIENT_SECRET')) {
    define('PAYPAL_CLIENT_SECRET', 'TU_PAYPAL_CLIENT_SECRET');
}

if (!defined('PAYPAL_WEBHOOK_ID')) {
    define('PAYPAL_WEBHOOK_ID', 'TU_PAYPAL_WEBHOOK_ID');
}

if (!defined('PAYPAL_PLAN_ID')) {
    define('PAYPAL_PLAN_ID', '');
}

if (!defined('PAYPAL_RETURN_URL')) {
    define('PAYPAL_RETURN_URL', '');
}

if (!defined('PAYPAL_CANCEL_URL')) {
    define('PAYPAL_CANCEL_URL', '');
}

if (!defined('PAYPAL_TAX_PERCENTAGE')) {
    define('PAYPAL_TAX_PERCENTAGE', '0');
}

if (!defined('PAYPAL_API_BASE')) {
    define('PAYPAL_API_BASE', 'https://api-m.paypal.com');
}
