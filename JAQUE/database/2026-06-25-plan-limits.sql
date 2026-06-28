-- TuyoMall - limites definitivos de planes
-- Ejecutar en phpMyAdmin si necesitas reparar o actualizar los limites.

SET NAMES utf8mb4;

UPDATE planes
SET
  max_productos = 10,
  max_imagenes_producto = 1,
  permite_precio_tachado = 0,
  multiples_contactos = 0,
  prioridad_feed = 0,
  estadisticas_avanzadas = 0,
  soporte_prioritario = 0
WHERE codigo = 'gratis';

UPDATE planes
SET
  max_productos = NULL,
  max_imagenes_producto = 1,
  permite_precio_tachado = 1,
  multiples_contactos = 1,
  prioridad_feed = 1,
  estadisticas_avanzadas = 1,
  soporte_prioritario = 1
WHERE codigo = 'premium';
