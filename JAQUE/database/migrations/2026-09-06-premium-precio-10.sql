-- TuyoMall - precio correcto del plan Premium
-- Ejecutar en phpMyAdmin si ya tienes una base existente.

SET NAMES utf8mb4;

UPDATE planes
SET precio_mensual_usd = 10.00
WHERE codigo = 'premium';
