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

ALTER TABLE productos
  ADD KEY idx_productos_creado (creado_en),
  ADD KEY idx_productos_negocio_creado (negocio_id, creado_en);

ALTER TABLE likes
  ADD KEY idx_likes_producto_creado (producto_id, creado_en);

ALTER TABLE guardados
  ADD KEY idx_guardados_producto_creado (producto_id, creado_en);
