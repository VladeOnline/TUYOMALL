-- TuyoMall - reparacion rapida de planes base
-- Ejecutar en phpMyAdmin dentro de la base de datos de TuyoMall.

SET NAMES utf8mb4;

INSERT INTO planes (
  codigo,
  nombre,
  descripcion,
  precio_mensual_usd,
  max_productos,
  max_imagenes_producto,
  max_categorias,
  max_etiquetas,
  permite_cupones,
  permite_resenas,
  permite_precio_tachado,
  multiples_contactos,
  prioridad_feed,
  estadisticas_avanzadas,
  soporte_prioritario,
  activo
) VALUES
(
  'gratis',
  'Plan Gratis',
  'Perfil publico, hasta 10 productos y contacto directo.',
  0.00,
  10,
  1,
  5,
  5,
  0,
  0,
  0,
  0,
  0,
  0,
  0,
  1
),
(
  'premium',
  'Plan Premium',
  'Productos ilimitados, prioridad visual, resenas y estadisticas avanzadas.',
  10.00,
  NULL,
  1,
  20,
  20,
  1,
  1,
  1,
  1,
  1,
  1,
  1,
  1
)
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  descripcion = VALUES(descripcion),
  precio_mensual_usd = VALUES(precio_mensual_usd),
  max_productos = VALUES(max_productos),
  max_imagenes_producto = VALUES(max_imagenes_producto),
  max_categorias = VALUES(max_categorias),
  max_etiquetas = VALUES(max_etiquetas),
  permite_cupones = VALUES(permite_cupones),
  permite_resenas = VALUES(permite_resenas),
  permite_precio_tachado = VALUES(permite_precio_tachado),
  multiples_contactos = VALUES(multiples_contactos),
  prioridad_feed = VALUES(prioridad_feed),
  estadisticas_avanzadas = VALUES(estadisticas_avanzadas),
  soporte_prioritario = VALUES(soporte_prioritario),
  activo = VALUES(activo);
