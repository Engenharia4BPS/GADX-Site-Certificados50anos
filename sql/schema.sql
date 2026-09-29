CREATE TABLE admins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','admin') NOT NULL DEFAULT 'admin',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE uploads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  label VARCHAR(180) NOT NULL,
  activity_date DATE NULL,
  source_filename VARCHAR(255) NOT NULL,
  stored_filename VARCHAR(255) NOT NULL,
  is_satellite TINYINT(1) NOT NULL DEFAULT 0,
  is_wff TINYINT(1) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_uploads_activity_date (activity_date),
  CONSTRAINT fk_uploads_admin FOREIGN KEY (created_by) REFERENCES admins(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE qsos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  upload_id BIGINT UNSIGNED NOT NULL,
  callsign VARCHAR(32) NOT NULL,
  qso_date CHAR(8) NULL,
  qso_time CHAR(6) NULL,
  band VARCHAR(16) NULL,
  mode VARCHAR(24) NULL,
  PRIMARY KEY (id),
  KEY idx_qsos_callsign (callsign),
  KEY idx_qsos_upload (upload_id),
  CONSTRAINT fk_qsos_upload FOREIGN KEY (upload_id) REFERENCES uploads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  upload_id BIGINT UNSIGNED NOT NULL,
  reference_code VARCHAR(80) NOT NULL,
  name VARCHAR(180) NULL,
  PRIMARY KEY (id),
  KEY idx_activations_upload (upload_id),
  CONSTRAINT fk_activations_upload FOREIGN KEY (upload_id) REFERENCES uploads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE endorsements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  upload_id BIGINT UNSIGNED NOT NULL,
  code VARCHAR(40) NOT NULL,
  label VARCHAR(120) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_endorsements_upload (upload_id),
  CONSTRAINT fk_endorsements_upload FOREIGN KEY (upload_id) REFERENCES uploads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
