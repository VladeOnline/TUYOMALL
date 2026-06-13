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
- `planes`
- `suscripciones`
- `pagos`
- `productos`
- `likes`
- `guardados`
- `resenas`
- `metricas_eventos`
- tablas de soporte para categorias, imagenes, redes, cupones y recuperacion

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

Dashboard y perfil:

- Panel emprendedor protegido: `dashboard-emprendedor.php`
- Perfil del negocio: `api/business.php`
- Publicaciones: `api/products.php`
- Interacciones cliente: `api/interactions.php`
- Resenas: `api/reviews.php`
- Cupones Premium: `api/coupons.php`
- Contactos/redes: `api/business-contacts.php`
- Metricas: `api/metrics.php`

## 5. Regla de planes

La base ya deja sembrados dos planes:

- `gratis`: hasta 10 productos activos, 3 imagenes por producto, 5 categorias, 5 etiquetas, contacto directo y estadisticas basicas.
- `premium`: $5 USD/mes, productos ilimitados, mas imagenes, cupones, precio tachado, resenas, multiples contactos, prioridad en feed, estadisticas avanzadas y soporte prioritario.

El plan real no debe depender solo del boton del front. En PHP la regla debe ser:

1. Si el negocio tiene una suscripcion `premium` con `estado = 'activa'` y no esta vencida, usa Premium.
2. Si no existe esa suscripcion, o esta vencida/cancelada/pendiente, usa Gratis.
3. Cuando PayPal confirme el pago, se crea un registro en `pagos`, se activa/renueva `suscripciones` y se actualiza `negocios.plan_codigo = 'premium'`.
4. Cuando venza o se cancele, PHP debe volver a dejar `negocios.plan_codigo = 'gratis'`.

Asi, aunque alguien intente saltarse el checkout desde el navegador, los limites se aplican desde la base y el servidor.

El archivo `includes/plan-rules.php` deja funciones listas para usar en los proximos endpoints:

- `get_business_plan($pdo, $businessId)`: devuelve Premium solo si hay suscripcion activa.
- `sync_business_plan($pdo, $businessId)`: actualiza `negocios.plan_codigo` segun la suscripcion real.
- `can_create_product($pdo, $businessId)`: bloquea nuevos productos si el plan Gratis ya llego a 10.
- `can_upload_product_image($pdo, $businessId, $productId)`: aplica el limite de imagenes por producto.
- `plan_allows($plan, 'cupones')`: valida funciones premium como cupones, resenas, precio tachado o estadisticas avanzadas.

Endpoints conectados a estas reglas:

- `api/products.php`: crea/lista publicaciones y aplica limite de productos, imagenes y precio tachado.
- `api/interactions.php`: guarda likes y guardados por cliente logueado.
- `api/reviews.php`: permite resenas solo si el negocio tiene Premium activo.
- `api/coupons.php`: permite cupones/descuentos solo con Premium activo.
- `api/business-contacts.php`: Gratis permite 1 contacto visible; Premium permite multiples contactos.
- `api/metrics.php`: registra eventos y solo muestra estadisticas avanzadas a Premium.

## 5.1. Subida de imagenes

Las imagenes se guardan en:

- `uploads/products`
- `uploads/business`

La carpeta `uploads` incluye un `.htaccess` para evitar ejecucion de PHP dentro de archivos subidos.

## 6. Pagos Premium con PayPal

Para activar Premium automaticamente, PayPal debe enviar un webhook al servidor:

`https://tudominio.com/billing/paypal-webhook.php`

Configura en `config/paypal.php`:

```php
const PAYPAL_CLIENT_ID = '...';
const PAYPAL_CLIENT_SECRET = '...';
const PAYPAL_WEBHOOK_ID = '...';
```

Cuando PayPal confirme un pago valido, `billing/paypal-webhook.php`:

1. Verifica la firma real de PayPal.
2. Busca el negocio relacionado con el pago.
3. Crea un registro en `pagos`.
4. Crea una suscripcion Premium activa por 1 mes.
5. Cambia `negocios.plan_codigo` a `premium`.

Si PayPal manda el mismo webhook dos veces, la tabla `pagos` evita duplicar el pago.

## 7. Cancelar renovacion mensual

El dashboard llama a:

`billing/cancel-premium.php`

Esa ruta marca `renovacion_cancelada = 1`, pero mantiene la suscripcion activa hasta `expira_en`.
Eso significa que si alguien pago el mes y cancela hoy, conserva Premium hasta terminar el periodo pagado.
Si PayPal entrega un ID de suscripcion recurrente, tambien se intenta cancelar la renovacion real en PayPal.
