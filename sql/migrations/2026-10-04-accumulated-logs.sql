-- Migra o ranking de snapshots por estacao para logs acumulados.
--
-- Execute uma unica vez, depois de fazer backup do banco e depois da migracao
-- 2026-10-03-station-snapshots.sql. O INSERT inicial inclui somente os dados
-- que ja estavam publicos em current_qsos, portanto o resultado do ranking e
-- dos diplomas nao muda no momento da migracao.

CREATE TABLE aggregate_upload_stations (
  upload_id BIGINT UNSIGNED NOT NULL,
  station_callsign VARCHAR(32) NOT NULL,
  PRIMARY KEY (upload_id, station_callsign),
  KEY idx_aggregate_station (station_callsign),
  CONSTRAINT fk_aggregate_upload FOREIGN KEY (upload_id) REFERENCES uploads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO aggregate_upload_stations (upload_id, station_callsign)
SELECT DISTINCT upload_id, station_callsign
FROM current_qsos;

CREATE OR REPLACE VIEW aggregate_qsos AS
SELECT q.*
FROM qsos q
INNER JOIN aggregate_upload_stations included
  ON included.upload_id = q.upload_id
 AND included.station_callsign = q.station_callsign;

