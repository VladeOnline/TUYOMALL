-- Migration: categorias principales + subcategorias para productos/servicios.
-- Ejecutar en phpMyAdmin sobre la base existente de TuyoMall.

SET NAMES utf8mb4;

INSERT INTO categorias (nombre, slug, icono, orden) VALUES
  ('Productos', 'productos', 'package', 10),
  ('Servicios', 'servicios', 'briefcase', 20)
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  icono = VALUES(icono),
  orden = VALUES(orden),
  activa = 1;

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

ALTER TABLE productos
  ADD COLUMN subcategoria_id INT UNSIGNED NULL AFTER categoria_id;

ALTER TABLE productos
  ADD KEY idx_productos_subcategoria (subcategoria_id);

ALTER TABLE productos
  ADD CONSTRAINT fk_productos_subcategoria
    FOREIGN KEY (subcategoria_id) REFERENCES subcategorias(id)
    ON DELETE SET NULL;
