<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/auth.php';
require_once CERT50_PRIVATE_ROOT . '/app/adif.php';

$admin = require_admin();
$eventStations = event_stations();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verify_csrf();

function imported_activity_date(array $qsos): ?string {
    $dates = [];
    foreach ($qsos as $qso) {
        $date = $qso['qso_date'] ?? null;
        if (!is_string($date) || !preg_match('/^\d{8}$/', $date)) continue;
        $parsed = DateTimeImmutable::createFromFormat('!Ymd', $date);
        if ($parsed && $parsed->format('Ymd') === $date) $dates[] = $parsed->format('Y-m-d');
    }
    if (!$dates) return null;
    sort($dates);
    return $dates[0];
}

try {
    if (empty($_FILES['adif']) || $_FILES['adif']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Escolha um arquivo ADIF para importar.');
    }

    $file = $_FILES['adif'];
    if ($file['size'] > (int) config('max_upload_bytes')) {
        throw new RuntimeException('O arquivo ADIF excede o tamanho permitido.');
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['adi', 'adif'], true)) {
        throw new RuntimeException('Envie um arquivo .adi ou .adif.');
    }

    $contents = file_get_contents($file['tmp_name']);
    if ($contents === false) throw new RuntimeException('Não foi possível ler o arquivo enviado.');
    $qsos = parse_adif($contents);
    if (!$qsos) throw new RuntimeException('Nenhum registro QSO foi encontrado no ADIF.');
    if (count($qsos) > 100000) throw new RuntimeException('Limite de 100.000 QSOs por arquivo.');

    $stationGroups = [];
    foreach ($qsos as $qso) {
        $station = $qso['station_callsign'] ?? null;
        if (!is_string($station) || $station === '') {
            throw new RuntimeException('O ADIF precisa informar STATION_CALLSIGN ou MY_CALL. Exportações do Club Log também são aceitas quando trazem “Log export of INDICATIVO” no cabeçalho. Nenhum dado foi importado.');
        }
        if (!in_array($station, $eventStations, true)) {
            throw new RuntimeException("A estação $station não pertence à lista habilitada: ZW5B, ZW50B, PY5GA e PQ5TA.");
        }
        $stationGroups[$station][] = $qso;
    }
    ksort($stationGroups);

    $storage = CERT50_PRIVATE_ROOT . '/storage/adif';
    if (!is_dir($storage) || !is_writable($storage)) {
        throw new RuntimeException('A pasta privada de ADIF não está disponível para gravação.');
    }

    $sourceFilename = mb_substr(basename((string) $file['name']), 0, 255);
    $sourceStem = pathinfo($sourceFilename, PATHINFO_FILENAME);
    $safeStem = strtolower((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $sourceStem));
    $safeStem = trim($safeStem, '-_');
    if ($safeStem === '') $safeStem = 'arquivo-adif';
    $safeStem = substr($safeStem, 0, 230);

    $pdo = db();
    $pdo->beginTransaction();

    // The database ID is the stable sequential index used in the stored filename.
    $insertUpload = $pdo->prepare('INSERT INTO uploads (label, activity_date, source_filename, stored_filename, is_satellite, is_wff, notes, created_by) VALUES (?, ?, ?, ?, 0, 0, NULL, ?)');
    $insertUpload->execute(['Importação em processamento', imported_activity_date($qsos), $sourceFilename, 'pendente', $admin['id']]);
    $uploadId = (int) $pdo->lastInsertId();
    $index = sprintf('Log50ano%03d', $uploadId);
    $stationLabel = implode('-', array_keys($stationGroups));
    $maxStemLength = 255 - strlen($index) - strlen($stationLabel) - strlen($extension) - 3;
    $safeStem = substr($safeStem, 0, max(1, $maxStemLength));
    $storedName = "$index-$stationLabel-$safeStem.$extension";
    $label = mb_substr("$index · $stationLabel — $sourceFilename", 0, 180);

    if (!move_uploaded_file($file['tmp_name'], $storage . '/' . $storedName)) {
        throw new RuntimeException('Não foi possível guardar o ADIF na pasta privada.');
    }

    $updateUpload = $pdo->prepare('UPDATE uploads SET label = ?, stored_filename = ? WHERE id = ?');
    $updateUpload->execute([$label, $storedName, $uploadId]);

    $qsoInsert = $pdo->prepare('INSERT INTO qsos (upload_id, station_callsign, callsign, qso_date, qso_time, band, mode, is_satellite, is_wff, is_pota) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($qsos as $qso) {
        $qsoInsert->execute([
            $uploadId,
            $qso['station_callsign'],
            $qso['callsign'],
            $qso['qso_date'],
            $qso['qso_time'],
            $qso['band'],
            $qso['mode'],
            $qso['is_satellite'] ? 1 : 0,
            $qso['is_wff'] ? 1 : 0,
            $qso['is_pota'] ? 1 : 0,
        ]);
    }
    $stationInsert = $pdo->prepare('INSERT INTO upload_stations (upload_id, station_callsign, qso_count) VALUES (?, ?, ?)');
    $setCurrent = $pdo->prepare('INSERT INTO station_current_uploads (station_callsign, upload_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE upload_id = VALUES(upload_id), updated_at = CURRENT_TIMESTAMP');
    foreach ($stationGroups as $station => $stationQsos) {
        $stationInsert->execute([$uploadId, $station, count($stationQsos)]);
        $setCurrent->execute([$station, $uploadId]);
    }

    $pdo->commit();
    flash('success', sprintf('%s sincronizado: %d QSOs, %d indicativos únicos e %d estação(ões): %s.', $storedName, count($qsos), count(array_unique(array_column($qsos, 'callsign'))), count($stationGroups), implode(', ', array_keys($stationGroups))));
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (isset($storedName)) @unlink((CERT50_PRIVATE_ROOT ?? '') . '/storage/adif/' . $storedName);
    error_log('ARDX50 import: ' . $error->getMessage());
    flash('error', $error->getMessage());
}

header('Location: index.php');
exit;
