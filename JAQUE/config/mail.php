<?php
declare(strict_types=1);

/*
 * Configuracion del correo de soporte.
 *
 * En produccion, edita solamente MAIL_SMTP_PASSWORD desde el administrador
 * de archivos de cPanel. La carpeta config esta protegida por .htaccess.
 */
const MAIL_SMTP_HOST = 'mail.privateemail.com';
const MAIL_SMTP_PORT = 465;
const MAIL_SMTP_ENCRYPTION = 'ssl';
const MAIL_SMTP_USERNAME = 'soporte@tuyomall.com';
const MAIL_SMTP_PASSWORD = '';
const MAIL_FROM_ADDRESS = 'soporte@tuyomall.com';
const MAIL_FROM_NAME = 'TuyoMall';
const MAIL_SUPPORT_ADDRESS = 'soporte@tuyomall.com';
