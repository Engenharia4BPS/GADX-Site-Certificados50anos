-- Execute once, after deploying the application files for station snapshots.
-- Existing records are preserved and treated as historical ZW50B records until
-- the first complete ZW50B snapshot is synchronized.

ALTER TABLE qsos
  ADD COLUMN station_callsign VARCHAR(32) NULL AFTER upload_id,
  ADD COLUMN is_satellite TINYINT(1) NOT NULL DEFAULT 0 AFTER mode,
  ADD COLUMN is_wff TINYINT(1) NOT NULL DEFAULT 0 AFTER is_satellite,
  ADD COLUMN is_pota TINYINT(1) NOT NULL DEFAULT 0 AFTER is_wff,
  ADD KEY idx_qsos_station_upload (station_callsign, upload_id);

UPDATE qsos
SET station_callsign = 'ZW50B'
WHERE station_callsign IS NULL OR station_callsign = '';

UPDATE qsos q
INNER JOIN uploads u ON u.id = q.upload_id
SET q.is_satellite = u.is_satellite,
    q.is_wff = u.is_wff;

UPDATE qsos q
INNER JOIN (
  SELECT DISTINCT upload_id
  FROM endorsements
  WHERE code = 'POTA'
) pota ON pota.upload_id = q.upload_id
SET q.is_pota = 1;

ALTER TABLE qsos
  MODIFY station_callsign VARCHAR(32) NOT NULL;

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

CREATE OR REPLACE VIEW current_qsos AS
SELECT q.*
FROM qsos q
LEFT JOIN station_current_uploads current_upload
  ON current_upload.station_callsign = q.station_callsign
WHERE current_upload.station_callsign IS NULL OR current_upload.upload_id = q.upload_id;
