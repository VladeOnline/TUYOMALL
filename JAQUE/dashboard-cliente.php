<?php
declare(strict_types=1);

require_once __DIR__ . '/config/session.php';

require_role('cliente', 'acceso-cliente.html');

$html = __DIR__ . '/dashboard-cliente.html';

if (!is_file($html)) {
    http_response_code(500);
    echo 'Dashboard de cliente no disponible.';
    exit;
}

readfile($html);
