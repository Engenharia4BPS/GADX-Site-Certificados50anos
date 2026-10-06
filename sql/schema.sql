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
  station_callsign VARCHAR(32) NOT NULL,
  callsign VARCHAR(32) NOT NULL,
  qso_date CHAR(8) NULL,
  qso_time CHAR(6) NULL,
  band VARCHAR(16) NULL,
  mode VARCHAR(24) NULL,
  is_satellite TINYINT(1) NOT NULL DEFAULT 0,
  is_wff TINYINT(1) NOT NULL DEFAULT 0,
  is_pota TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_qsos_callsign (callsign),
  KEY idx_qsos_upload (upload_id),
  KEY idx_qsos_station_upload (station_callsign, upload_id),
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

CREATE TABLE upload_stations (
  upload_id BIGINT UNSIGNED NOT NULL,
  station_callsign VARCHAR(32) NOT NULL,
  qso_count INT UNSIGNED NOT NULL,
  PRIMARY KEY (upload_id, station_callsign),
  KEY idx_upload_stations_station (station_callsign),
  CONSTRAINT fk_upload_stations_upload FOREIGN KEY (upload_id) REFERENCES uploads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE station_current_uploads (
  station_callsign VARCHAR(32) NOT NULL,
  upload_id BIGINT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (station_callsign),
  KEY idx_station_current_upload (upload_id),
  CONSTRAINT fk_station_current_upload FOREIGN KEY (upload_id) REFERENCES uploads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE aggregate_upload_stations (
  upload_id BIGINT UNSIGNED NOT NULL,
  station_callsign VARCHAR(32) NOT NULL,
  PRIMARY KEY (upload_id, station_callsign),
  KEY idx_aggregate_station (station_callsign),
  CONSTRAINT fk_aggregate_upload FOREIGN KEY (upload_id) REFERENCES uploads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE VIEW current_qsos AS
SELECT q.*
FROM qsos q
LEFT JOIN station_current_uploads current_upload
  ON current_upload.station_callsign = q.station_callsign
WHERE current_upload.station_callsign IS NULL OR current_upload.upload_id = q.upload_id;

CREATE VIEW aggregate_qsos AS
SELECT q.*
FROM qsos q
INNER JOIN aggregate_upload_stations included
  ON included.upload_id = q.upload_id
 AND included.station_callsign = q.station_callsign;
