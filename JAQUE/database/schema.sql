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
  max_imagenes_producto INT UNSIGNED NOT NULL DEFAULT 1,
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
  1,
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
  1,
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
  ('Productos', 'productos', 'package', 10),
  ('Servicios', 'servicios', 'briefcase', 20)
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  icono = VALUES(icono),
  orden = VALUES(orden);

CREATE TABLE IF NOT EXISTS subcategorias (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoria_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  descripcion VARCHAR(255) NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  orden INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_subcategorias_slug (slug),
  KEY idx_subcategorias_categoria (categoria_id, activa, orden),
  CONSTRAINT fk_subcategorias_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO subcategorias (categoria_id, nombre, slug, descripcion, orden)
SELECT c.id, x.nombre, x.slug, x.descripcion, x.orden
FROM categorias c
INNER JOIN (
  SELECT 'productos' categoria_slug, 'Moda y ropa' nombre, 'moda-y-ropa' slug, 'Ropa de mujer, hombre, ninos, ropa deportiva, pijamas, uniformes' descripcion, 10 orden
  UNION ALL SELECT 'productos', 'Calzado', 'calzado', 'Tenis, sandalias, zapatos, calzado infantil, calzado artesanal', 20
  UNION ALL SELECT 'productos', 'Accesorios y joyeria', 'accesorios-y-joyeria', 'Bisuteria, joyeria, bolsos, carteras, lentes, relojes, accesorios para cabello', 30
  UNION ALL SELECT 'productos', 'Belleza y cuidado personal', 'belleza-y-cuidado-personal', 'Maquillaje, skincare, unas, perfumes, productos capilares', 40
  UNION ALL SELECT 'productos', 'Salud y bienestar', 'salud-y-bienestar', 'Productos de autocuidado, articulos de relajacion, bienestar fisico', 50
  UNION ALL SELECT 'productos', 'Hogar y decoracion', 'hogar-y-decoracion', 'Decoracion, organizadores, textiles, velas, cuadros, articulos para cocina', 60
  UNION ALL SELECT 'productos', 'Tecnologia y accesorios', 'tecnologia-y-accesorios', 'Accesorios para celular, audifonos, gadgets, soportes, cables, perifericos', 70
  UNION ALL SELECT 'productos', 'Alimentos y bebidas', 'alimentos-y-bebidas', 'Reposteria, snacks, cafe, salsas, productos artesanales, comidas empacadas', 80
  UNION ALL SELECT 'productos', 'Productos artesanales', 'productos-artesanales', 'Manualidades, arte, ceramica, bordados, productos hechos a mano', 90
  UNION ALL SELECT 'productos', 'Bebes y ninos', 'bebes-y-ninos', 'Ropa infantil, juguetes, accesorios, productos para maternidad', 100
  UNION ALL SELECT 'productos', 'Mascotas', 'mascotas', 'Alimentos, accesorios, camas, juguetes, higiene para mascotas', 110
  UNION ALL SELECT 'productos', 'Papeleria y oficina', 'papeleria-y-oficina', 'Agendas, stickers, cuadernos, planners, material de oficina', 120
  UNION ALL SELECT 'productos', 'Libros e infoproductos fisicos', 'libros-e-infoproductos-fisicos', 'Libros, guias impresas, material educativo fisico', 130
  UNION ALL SELECT 'productos', 'Repuestos y accesorios', 'repuestos-y-accesorios', 'Accesorios para autos, motos, bicicletas, herramientas pequenas', 140
  UNION ALL SELECT 'servicios', 'Belleza y estetica', 'belleza-y-estetica', 'Maquillaje, unas, barberia, spa, tratamientos y cuidado personal', 10
  UNION ALL SELECT 'servicios', 'Reparaciones y mantenimiento', 'reparaciones-y-mantenimiento', 'Reparacion de electrodomesticos, hogar, vehiculos y equipos', 20
  UNION ALL SELECT 'servicios', 'Diseno y marketing', 'diseno-y-marketing', 'Diseno grafico, branding, redes sociales, publicidad y contenido', 30
  UNION ALL SELECT 'servicios', 'Educacion y clases', 'educacion-y-clases', 'Tutorias, cursos, clases privadas, talleres y capacitaciones', 40
  UNION ALL SELECT 'servicios', 'Transporte y entregas', 'transporte-y-entregas', 'Mensajeria, mudanzas, entregas locales y transporte privado', 50
  UNION ALL SELECT 'servicios', 'Eventos y fotografia', 'eventos-y-fotografia', 'Fotografia, video, decoracion, musica, catering y organizacion', 60
  UNION ALL SELECT 'servicios', 'Salud y bienestar', 'servicios-salud-y-bienestar', 'Terapias, entrenamiento, nutricion, bienestar fisico y mental', 70
  UNION ALL SELECT 'servicios', 'Servicios profesionales', 'servicios-profesionales', 'Contabilidad, asesoria legal, consultoria, tramites y administracion', 80
  UNION ALL SELECT 'servicios', 'Tecnologia y soporte', 'tecnologia-y-soporte', 'Soporte tecnico, sitios web, software, instalacion y mantenimiento digital', 90
  UNION ALL SELECT 'servicios', 'Limpieza y hogar', 'limpieza-y-hogar', 'Limpieza, jardineria, cocina, cuido y apoyo domestico', 100
) x ON x.categoria_slug = c.slug
ON DUPLICATE KEY UPDATE
  categoria_id = VALUES(categoria_id),
  nombre = VALUES(nombre),
  descripcion = VALUES(descripcion),
  orden = VALUES(orden),
  activa = 1;

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
  subcategoria_id INT UNSIGNED NULL,
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
  KEY idx_productos_subcategoria (subcategoria_id),
  KEY idx_productos_estado (estado),
  KEY idx_productos_creado (creado_en),
  KEY idx_productos_negocio_creado (negocio_id, creado_en),
  KEY idx_productos_feed (estado, premium_boost, destacado, creado_en),
  CONSTRAINT fk_productos_negocio
    FOREIGN KEY (negocio_id) REFERENCES negocios(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_productos_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias(id)
    ON DELETE SET NULL,
  CONSTRAINT fk_productos_subcategoria
    FOREIGN KEY (subcategoria_id) REFERENCES subcategorias(id)
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
  KEY idx_likes_producto_creado (producto_id, creado_en),
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
  KEY idx_guardados_producto_creado (producto_id, creado_en),
  CONSTRAINT fk_guardados_producto
    FOREIGN KEY (producto_id) REFERENCES productos(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_guardados_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comentarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producto_id INT UNSIGNED NOT NULL,
  usuario_id INT UNSIGNED NOT NULL,
  comentario TEXT NOT NULL,
  estado ENUM('activo', 'oculto') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_comentarios_producto_creado (producto_id, creado_en),
  KEY idx_comentarios_usuario (usuario_id),
  CONSTRAINT fk_comentarios_producto
    FOREIGN KEY (producto_id) REFERENCES productos(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_comentarios_usuario
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
