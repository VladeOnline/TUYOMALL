CREATE TABLE usuarios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rol ENUM('cliente', 'emprendedor') NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  apellido VARCHAR(80) NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  telefono VARCHAR(40) NULL,
  estado ENUM('activo', 'bloqueado') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE negocios (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  nombre_negocio VARCHAR(140) NOT NULL,
  whatsapp VARCHAR(40) NOT NULL,
  provincia VARCHAR(80) NOT NULL,
  canton VARCHAR(80) NULL,
  plan ENUM('gratis', 'premium') NOT NULL DEFAULT 'gratis',
  estado ENUM('activo', 'pendiente', 'bloqueado') NOT NULL DEFAULT 'activo',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_negocios_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_en DATETIME NOT NULL,
  usado_en DATETIME NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_password_resets_token (token_hash),
  INDEX idx_password_resets_usuario (usuario_id),
  CONSTRAINT fk_password_resets_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
