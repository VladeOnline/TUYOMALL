-- TuyoMall - metodos de pago aceptados por negocio
-- Ejecutar en phpMyAdmin si ya tienes una base existente.

SET NAMES utf8mb4;

ALTER TABLE negocios
  ADD COLUMN metodos_pago TEXT NULL AFTER horario;
