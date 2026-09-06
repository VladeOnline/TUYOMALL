-- TuyoMall - reserva previa por negocio
-- Ejecutar en phpMyAdmin si ya tienes una base existente.

SET NAMES utf8mb4;

ALTER TABLE negocios
  ADD COLUMN requiere_reserva TINYINT(1) NOT NULL DEFAULT 0 AFTER metodos_pago;
