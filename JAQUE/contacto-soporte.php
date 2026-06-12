<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
    exit;
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
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Revisá nombre, correo y mensaje.']);
    exit;
}

$safeEmail = filter_var($email, FILTER_SANITIZE_EMAIL);
$safeMessage = trim(filter_var($message, FILTER_SANITIZE_FULL_SPECIAL_CHARS));

$to = 'soporte@tuyomall.com';
$subject = 'Nueva consulta desde TuyoMall';
$body = "Nueva consulta recibida desde TuyoMall:\n\n";
$body .= "Nombre: {$name}\n";
$body .= "Correo: {$safeEmail}\n";
$body .= "WhatsApp: " . ($whatsapp !== '' ? $whatsapp : 'No indicado') . "\n";
$body .= "Tipo de consulta: {$type}\n\n";
$body .= "Mensaje:\n{$safeMessage}\n";

$headers = [
    'From: TuyoMall <soporte@tuyomall.com>',
    'Reply-To: ' . $safeEmail,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
];

$sent = mail($to, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No pudimos enviar la consulta. Probá escribir a soporte@tuyomall.com.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Gracias por escribirnos. Te responderemos pronto.']);
