<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/auth.php';
require_once CERT50_PRIVATE_ROOT . '/app/adif.php';

$admin = require_admin();
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
    if (count($qsos) > 50000) throw new RuntimeException('Limite de 50.000 QSOs por arquivo.');

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
    $storedName = "$index-$safeStem.$extension";
    $label = mb_substr("$index — $sourceFilename", 0, 180);

    if (!move_uploaded_file($file['tmp_name'], $storage . '/' . $storedName)) {
        throw new RuntimeException('Não foi possível guardar o ADIF na pasta privada.');
    }

    $updateUpload = $pdo->prepare('UPDATE uploads SET label = ?, stored_filename = ? WHERE id = ?');
    $updateUpload->execute([$label, $storedName, $uploadId]);

    $qsoInsert = $pdo->prepare('INSERT INTO qsos (upload_id, callsign, qso_date, qso_time, band, mode) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($qsos as $qso) {
        $qsoInsert->execute([$uploadId, $qso['callsign'], $qso['qso_date'], $qso['qso_time'], $qso['band'], $qso['mode']]);
    }

    $pdo->commit();
    flash('success', sprintf('%s importado: %d QSOs e %d indicativos únicos.', $storedName, count($qsos), count(array_unique(array_column($qsos, 'callsign')))));
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (isset($storedName)) @unlink((CERT50_PRIVATE_ROOT ?? '') . '/storage/adif/' . $storedName);
    error_log('ARDX50 import: ' . $error->getMessage());
    flash('error', $error->getMessage());
}

header('Location: index.php');
exit;
