# Base de datos TuyoMall en cPanel

## 1. Crear base en Namecheap/cPanel

En cPanel abre **MySQL Databases**:

1. Crea una base de datos, por ejemplo `tuyomall`.
2. Crea un usuario MySQL.
3. Asigna el usuario a la base de datos con permisos.

cPanel normalmente agrega el prefijo de tu cuenta. Por ejemplo:

- Base: `usuario_tuyomall`
- Usuario: `usuario_tuyouser`

## 2. Importar tablas

En **phpMyAdmin**, selecciona la base de datos e importa:

`database/schema.sql`

Eso crea:

- `usuarios`
- `negocios`

## 3. Configurar conexión

Edita:

`config/database.php`

Y reemplaza:

```php
const DB_NAME = 'TU_USUARIO_CPANEL_tuyomall';
const DB_USER = 'TU_USUARIO_CPANEL_user';
const DB_PASS = 'TU_PASSWORD';
```

Normalmente `DB_HOST` queda como `localhost`.

## 4. Rutas ya conectadas

Cliente:

- Registro: `auth/register-client.php`
- Login: `auth/login-client.php`

Emprendedor:

- Registro: `auth/register-business.php`
- Login: `auth/login-business.php`

Los formularios de `acceso-cliente.html` y `acceso-emprendedor.html` ya envían datos a esos endpoints.
