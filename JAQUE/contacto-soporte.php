<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/smtp-mailer.php';

function json_result(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_result(['ok' => false, 'message' => 'Método no permitido.'], 405);
}

function clean_value(string $value): string
{
    $value = trim($value);
    $value = str_replace(["\r", "\n"], ' ', $value);
    return filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
}

$name = clean_value($_POST['nombre'] ?? '');
$email = trim((string) ($_POST['correo'] ?? ''));
$type = clean_value($_POST['tipo'] ?? 'Consulta general');
$whatsapp = clean_value($_POST['whatsapp'] ?? '');
$message = trim((string) ($_POST['mensaje'] ?? ''));

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
    json_result(['ok' => false, 'message' => 'Revisá el nombre, correo y mensaje.'], 422);
}

$safeEmail = filter_var($email, FILTER_SANITIZE_EMAIL);
$safeMessage = trim(filter_var($message, FILTER_SANITIZE_FULL_SPECIAL_CHARS));

$subject = 'Nueva consulta desde TuyoMall';
$body = "Nueva consulta recibida desde TuyoMall:\n\n";
$body .= "Nombre: {$name}\n";
$body .= "Correo: {$safeEmail}\n";
$body .= "WhatsApp: " . ($whatsapp !== '' ? $whatsapp : 'No indicado') . "\n";
$body .= "Tipo de consulta: {$type}\n\n";
$body .= "Mensaje:\n{$safeMessage}\n";

try {
    $sent = send_support_email($safeEmail, $subject, $body);
} catch (Throwable $error) {
    error_log('TuyoMall SMTP: ' . $error->getMessage());
    $sent = false;
}

if (!$sent) {
    json_result([
        'ok' => false,
        'message' => 'No pudimos enviar la consulta. Escribinos a soporte@tuyomall.com.',
    ], 500);
}

json_result([
    'ok' => true,
    'message' => 'Gracias por escribirnos. Te responderemos pronto.',
]);
