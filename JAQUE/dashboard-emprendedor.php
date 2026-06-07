<?php
declare(strict_types=1);

require_once __DIR__ . '/config/session.php';

require_role('emprendedor', 'acceso-emprendedor.html');
require __DIR__ . '/dashboard-emprendedor.html';
