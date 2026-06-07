<?php
declare(strict_types=1);

/*
 * Configuracion para MySQL/MariaDB en cPanel.
 *
 * En Namecheap normalmente debes crear:
 * 1. Una base de datos desde "MySQL Databases".
 * 2. Un usuario de base de datos.
 * 3. Asignar ese usuario a la base con permisos.
 *
 * Luego reemplaza estos valores por los reales de cPanel.
 * Ejemplo comun: usuarioCpanel_tuyomall
 */
const DB_HOST = 'localhost';
const DB_NAME = 'TU_USUARIO_CPANEL_tuyomall';
const DB_USER = 'TU_USUARIO_CPANEL_user';
const DB_PASS = 'TU_PASSWORD';
const DB_CHARSET = 'utf8mb4';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
