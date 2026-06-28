<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/mail.php';

function smtp_read_response($socket, array $validCodes): string
{
    $response = '';

    do {
        $line = fgets($socket, 515);
        if ($line === false) {
            throw new RuntimeException('El servidor SMTP cerro la conexion.');
        }
        $response .= $line;
    } while (isset($line[3]) && $line[3] === '-');

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $validCodes, true)) {
        throw new RuntimeException('El servidor SMTP rechazo la solicitud.');
    }

    return $response;
}

function smtp_command($socket, string $command, array $validCodes): string
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('No se pudo escribir en la conexion SMTP.');
    }

    return smtp_read_response($socket, $validCodes);
}

function smtp_header_value(string $value): string
{
    $value = str_replace(["\r", "\n"], '', trim($value));

    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }

    return $value;
}

function send_support_email(
    string $replyTo,
    string $subject,
    string $body
): bool {
    if (MAIL_SMTP_PASSWORD === '') {
        $headers = [
            'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
            'Reply-To: ' . $replyTo,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: PHP/' . phpversion(),
        ];

        return mail(
            MAIL_SUPPORT_ADDRESS,
            smtp_header_value($subject),
            $body,
            implode("\r\n", $headers)
        );
    }

    $transport = MAIL_SMTP_ENCRYPTION === 'ssl' ? 'ssl://' : '';
    $socket = stream_socket_client(
        $transport . MAIL_SMTP_HOST . ':' . MAIL_SMTP_PORT,
        $errorNumber,
        $errorMessage,
        15,
        STREAM_CLIENT_CONNECT
    );

    if ($socket === false) {
        throw new RuntimeException('No se pudo conectar con el servidor SMTP.');
    }

    stream_set_timeout($socket, 15);

    try {
        smtp_read_response($socket, [220]);
        smtp_command($socket, 'EHLO tuyomall.com', [250]);
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode(MAIL_SMTP_USERNAME), [334]);
        smtp_command($socket, base64_encode(MAIL_SMTP_PASSWORD), [235]);
        smtp_command($socket, 'MAIL FROM:<' . MAIL_FROM_ADDRESS . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . MAIL_SUPPORT_ADDRESS . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . smtp_header_value(MAIL_FROM_NAME) . ' <' . MAIL_FROM_ADDRESS . '>',
            'To: <' . MAIL_SUPPORT_ADDRESS . '>',
            'Reply-To: <' . $replyTo . '>',
            'Subject: ' . smtp_header_value($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@tuyomall.com>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $safeBody = preg_replace('/^\./m', '..', $body) ?? $body;
        fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $safeBody . "\r\n.\r\n");
        smtp_read_response($socket, [250]);
        smtp_command($socket, 'QUIT', [221]);

        return true;
    } finally {
        fclose($socket);
    }
}
