-- TuyoMall database schema
-- Compatible with MySQL/MariaDB on cPanel/Namecheap.
--
-- Plan rule:
-- PHP must calculate the effective plan from an active subscription.
-- If a business has no active premium subscription, it uses the free plan.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rol ENUM('cliente', 'emprendedor', 'admin') NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  apellido VARCHAR(80) NULL,
  email VARCHAR(180) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  telefono VARCHAR(40) NULL,
  pais VARCHAR(80) NULL,
  provincia VARCHAR(100) NULL,
  direccion VARCHAR(220) NULL,
  estado ENUM('activo', 'pendiente', 'bloqueado') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_rol (rol),
  KEY idx_usuarios_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS planes (
  codigo VARCHAR(30) PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NULL,
  precio_mensual_usd DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  max_productos INT UNSIGNED NULL,
  max_imagenes_producto INT UNSIGNED NOT NULL DEFAULT 3,
  max_categorias INT UNSIGNED NOT NULL DEFAULT 5,
  max_etiquetas INT UNSIGNED NOT NULL DEFAULT 5,
  permite_cupones TINYINT(1) NOT NULL DEFAULT 0,
  permite_resenas TINYINT(1) NOT NULL DEFAULT 0,
  permite_precio_tachado TINYINT(1) NOT NULL DEFAULT 0,
  multiples_contactos TINYINT(1) NOT NULL DEFAULT 0,
  prioridad_feed TINYINT(1) NOT NULL DEFAULT 0,
  estadisticas_avanzadas TINYINT(1) NOT NULL DEFAULT 0,
  soporte_prioritario TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  soporte_prioritario
) VALUES
(
  'gratis',
  'Plan Gratis',
  'Perfil publico, hasta 10 productos y contacto directo.',
  0.00,
  10,
  3,
  5,
  5,
  0,
  0,
  0,
  0,
  0,
  0,
  0
),
(
  'premium',
  'Plan Premium',
  'Productos ilimitados, prioridad visual, resenas y estadisticas avanzadas.',
  5.00,
  NULL,
  10,
  20,
  20,
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
  soporte_prioritario = VALUES(soporte_prioritario);

CREATE TABLE IF NOT EXISTS categorias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  icono VARCHAR(60) NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_categorias_slug (slug),
  KEY idx_categorias_activa (activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categorias (nombre, slug, icono, orden) VALUES
  ('Alimentos', 'alimentos', 'utensils', 10),
  ('Arte', 'arte', 'palette', 20),
  ('Moda', 'moda', 'shirt', 30),
  ('Servicios', 'servicios', 'briefcase', 40),
  ('Tecnologia', 'tecnologia', 'cpu', 50),
  ('Hogar', 'hogar', 'home', 60),
  ('Belleza', 'belleza', 'sparkles', 70)
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  icono = VALUES(icono),
  orden = VALUES(orden);

CREATE TABLE IF NOT EXISTS negocios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  categoria_id INT UNSIGNED NULL,
  nombre_negocio VARCHAR(140) NOT NULL,
  slug VARCHAR(170) NOT NULL,
  tipo VARCHAR(80) NULL,
  descripcion TEXT NULL,
  historia TEXT NULL,
  whatsapp VARCHAR(40) NOT NULL,
  correo VARCHAR(180) NULL,
  pais VARCHAR(80) NOT NULL,
  provincia VARCHAR(100) NOT NULL,
  direccion VARCHAR(220) NULL,
  horario VARCHAR(120) NULL,
  avatar_url VARCHAR(255) NULL,
  portada_url VARCHAR(255) NULL,
  plan_codigo VARCHAR(30) NOT NULL DEFAULT 'gratis',
  estado ENUM('activo', 'pendiente', 'bloqueado') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_negocios_slug (slug),
  KEY idx_negocios_usuario (usuario_id),
  KEY idx_negocios_categoria (categoria_id),
  KEY idx_negocios_plan (plan_codigo),
  KEY idx_negocios_ubicacion (pais, provincia),
  KEY idx_negocios_estado (estado),
  CONSTRAINT fk_negocios_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_negocios_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_negocios_plan
    FOREIGN KEY (plan_codigo) REFERENCES planes(codigo)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suscripciones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT UNSIGNED NOT NULL,
  plan_codigo VARCHAR(30) NOT NULL,
  estado ENUM('activa', 'pendiente', 'vencida', 'cancelada') NOT NULL DEFAULT 'pendiente',
  renovacion_cancelada TINYINT(1) NOT NULL DEFAULT 0,
  proveedor ENUM('paypal', 'onvo', 'manual') NOT NULL DEFAULT 'paypal',
  proveedor_ref VARCHAR(190) NULL,
  inicia_en DATETIME NOT NULL,
  expira_en DATETIME NULL,
  cancelada_en DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_suscripciones_negocio (negocio_id),
  KEY idx_suscripciones_plan (plan_codigo),
  KEY idx_suscripciones_estado (estado, expira_en),
  KEY idx_suscripciones_proveedor_ref (proveedor, proveedor_ref),
  CONSTRAINT fk_suscripciones_negocio
    FOREIGN KEY (negocio_id) REFERENCES negocios(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_suscripciones_plan
    FOREIGN KEY (plan_codigo) REFERENCES planes(codigo)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pagos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT UNSIGNED NOT NULL,
  suscripcion_id INT UNSIGNED NULL,
  plan_codigo VARCHAR(30) NOT NULL,
  proveedor ENUM('paypal', 'onvo', 'manual') NOT NULL DEFAULT 'paypal',
  proveedor_pago_id VARCHAR(190) NULL,
  monto DECIMAL(10,2) NOT NULL,
  moneda CHAR(3) NOT NULL DEFAULT 'USD',
  estado ENUM('aprobado', 'pendiente', 'fallido', 'reembolsado') NOT NULL DEFAULT 'pendiente',
  payload TEXT NULL,
  pagado_en DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pagos_negocio (negocio_id),
  KEY idx_pagos_suscripcion (suscripcion_id),
  KEY idx_pagos_estado (estado),
  UNIQUE KEY uq_pagos_proveedor_pago (proveedor, proveedor_pago_id),
  CONSTRAINT fk_pagos_negocio
    FOREIGN KEY (negocio_id) REFERENCES negocios(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_pagos_suscripcion
    FOREIGN KEY (suscripcion_id) REFERENCES suscripciones(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_pagos_plan
    FOREIGN KEY (plan_codigo) REFERENCES planes(codigo)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS redes_negocio (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT UNSIGNED NOT NULL,
  tipo ENUM('whatsapp', 'correo', 'instagram', 'facebook', 'tiktok', 'pinterest', 'x', 'telegram', 'sitio', 'formulario') NOT NULL,
  valor VARCHAR(255) NOT NULL,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_redes_negocio (negocio_id),
  CONSTRAINT fk_redes_negocio
    FOREIGN KEY (negocio_id) REFERENCES negocios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT UNSIGNED NOT NULL,
  categoria_id INT UNSIGNED NULL,
  nombre VARCHAR(150) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  descripcion TEXT NULL,
  tipo ENUM('producto', 'servicio') NOT NULL DEFAULT 'producto',
  precio DECIMAL(10,2) NULL,
  precio_anterior DECIMAL(10,2) NULL,
  moneda CHAR(3) NOT NULL DEFAULT 'CRC',
  contacto_tipo ENUM('whatsapp', 'formulario', 'link') NOT NULL DEFAULT 'whatsapp',
  contacto_valor VARCHAR(255) NULL,
  estado ENUM('borrador', 'activo', 'pausado') NOT NULL DEFAULT 'activo',
  destacado TINYINT(1) NOT NULL DEFAULT 0,
  premium_boost TINYINT(1) NOT NULL DEFAULT 0,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_productos_negocio_slug (negocio_id, slug),
  KEY idx_productos_negocio (negocio_id),
  KEY idx_productos_categoria (categoria_id),
  KEY idx_productos_estado (estado),
  KEY idx_productos_feed (estado, premium_boost, destacado, creado_en),
  CONSTRAINT fk_productos_negocio
    FOREIGN KEY (negocio_id) REFERENCES negocios(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_productos_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS producto_imagenes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producto_id INT UNSIGNED NOT NULL,
  url VARCHAR(255) NOT NULL,
  alt VARCHAR(150) NULL,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_producto_imagenes_producto (producto_id),
  CONSTRAINT fk_producto_imagenes_producto
    FOREIGN KEY (producto_id) REFERENCES productos(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS producto_etiquetas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producto_id INT UNSIGNED NOT NULL,
  etiqueta VARCHAR(60) NOT NULL,
  UNIQUE KEY uq_producto_etiqueta (producto_id, etiqueta),
  CONSTRAINT fk_producto_etiquetas_producto
    FOREIGN KEY (producto_id) REFERENCES productos(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cupones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT UNSIGNED NOT NULL,
  producto_id INT UNSIGNED NULL,
  codigo VARCHAR(60) NOT NULL,
  descripcion VARCHAR(255) NULL,
  descuento_tipo ENUM('porcentaje', 'monto', 'texto') NOT NULL DEFAULT 'texto',
  valor DECIMAL(10,2) NULL,
  inicia_en DATETIME NULL,
  expira_en DATETIME NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cupones_negocio_codigo (negocio_id, codigo),
  KEY idx_cupones_producto (producto_id),
  CONSTRAINT fk_cupones_negocio
    FOREIGN KEY (negocio_id) REFERENCES negocios(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_cupones_producto
    FOREIGN KEY (producto_id) REFERENCES productos(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS likes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producto_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_likes_producto_usuario (producto_id, usuario_id),
  KEY idx_likes_usuario (usuario_id),
  CONSTRAINT fk_likes_producto
    FOREIGN KEY (producto_id) REFERENCES productos(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_likes_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS guardados (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producto_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_guardados_producto_usuario (producto_id, usuario_id),
  KEY idx_guardados_usuario (usuario_id),
  CONSTRAINT fk_guardados_producto
    FOREIGN KEY (producto_id) REFERENCES productos(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_guardados_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resenas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NULL,
  nombre_publico VARCHAR(100) NOT NULL,
  email VARCHAR(180) NULL,
  calificacion TINYINT UNSIGNED NOT NULL,
  comentario TEXT NOT NULL,
  estado ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_resenas_negocio (negocio_id, estado),
  KEY idx_resenas_usuario (usuario_id),
  CONSTRAINT fk_resenas_negocio
    FOREIGN KEY (negocio_id) REFERENCES negocios(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_resenas_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contactos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT UNSIGNED NULL,
  producto_id INT UNSIGNED NULL,
  tipo ENUM('whatsapp', 'formulario', 'link', 'soporte') NOT NULL DEFAULT 'formulario',
  nombre VARCHAR(100) NULL,
  email VARCHAR(180) NULL,
  telefono VARCHAR(40) NULL,
  mensaje TEXT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_contactos_negocio (negocio_id),
  KEY idx_contactos_producto (producto_id),
  KEY idx_contactos_tipo (tipo),
  CONSTRAINT fk_contactos_negocio
    FOREIGN KEY (negocio_id) REFERENCES negocios(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_contactos_producto
    FOREIGN KEY (producto_id) REFERENCES productos(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS metricas_eventos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  negocio_id INT UNSIGNED NULL,
  producto_id INT UNSIGNED NULL,
  usuario_id INT UNSIGNED NULL,
  evento ENUM('vista', 'click_whatsapp', 'guardar', 'like', 'compartir', 'perfil') NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_metricas_negocio (negocio_id, evento, creado_en),
  KEY idx_metricas_producto (producto_id, evento, creado_en),
  KEY idx_metricas_usuario (usuario_id),
  CONSTRAINT fk_metricas_negocio
    FOREIGN KEY (negocio_id) REFERENCES negocios(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_metricas_producto
    FOREIGN KEY (producto_id) REFERENCES productos(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_metricas_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_en DATETIME NOT NULL,
  usado_en DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_password_resets_token (token_hash),
  KEY idx_password_resets_usuario (usuario_id),
  CONSTRAINT fk_password_resets_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
