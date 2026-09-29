<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/auth.php';
require_once CERT50_PRIVATE_ROOT . '/app/adif.php';
$admin = require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
verify_csrf();

try {
    $label = trim((string) ($_POST['label'] ?? ''));
    $activityDate = trim((string) ($_POST['activity_date'] ?? '')) ?: null;
    $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;
    if ($label === '') throw new RuntimeException('Informe o nome da operação.');
    if ($activityDate && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $activityDate)) throw new RuntimeException('Data da atividade inválida.');
    if (empty($_FILES['adif']) || $_FILES['adif']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Não foi possível receber o arquivo ADIF.');
    $file = $_FILES['adif'];
    if ($file['size'] > (int) config('max_upload_bytes')) throw new RuntimeException('O arquivo ADIF excede o tamanho permitido.');
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['adi', 'adif'], true)) throw new RuntimeException('Envie um arquivo .adi ou .adif.');
    $contents = file_get_contents($file['tmp_name']);
    if ($contents === false) throw new RuntimeException('Não foi possível ler o arquivo enviado.');
    $qsos = parse_adif($contents);
    if (!$qsos) throw new RuntimeException('Nenhum registro QSO foi encontrado no ADIF.');
    if (count($qsos) > 50000) throw new RuntimeException('Limite de 50.000 QSOs por arquivo.');
    $storage = CERT50_PRIVATE_ROOT . '/storage/adif';
    if (!is_dir($storage) || !is_writable($storage)) throw new RuntimeException('A pasta privada de ADIF não está disponível para gravação.');
    $storedName = date('Ymd-His') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], $storage . '/' . $storedName)) throw new RuntimeException('Não foi possível guardar o ADIF na pasta privada.');
    $pdo = db(); $pdo->beginTransaction();
    $upload = $pdo->prepare('INSERT INTO uploads (label, activity_date, source_filename, stored_filename, is_satellite, is_wff, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $upload->execute([$label, $activityDate, basename($file['name']), $storedName, isset($_POST['satellite']) ? 1 : 0, isset($_POST['wff']) ? 1 : 0, $notes, $admin['id']]);
    $uploadId = (int) $pdo->lastInsertId();
    $qsoInsert = $pdo->prepare('INSERT INTO qsos (upload_id, callsign, qso_date, qso_time, band, mode) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($qsos as $qso) $qsoInsert->execute([$uploadId, $qso['callsign'], $qso['qso_date'], $qso['qso_time'], $qso['band'], $qso['mode']]);
    $activationInsert = $pdo->prepare('INSERT INTO activations (upload_id, reference_code, name) VALUES (?, ?, ?)');
    foreach (preg_split('/[\\r\\n,]+/', (string) ($_POST['activations'] ?? '')) as $line) { $line = trim($line); if ($line === '') continue; [$reference, $name] = array_pad(explode('|', $line, 2), 2, null); $reference = strtoupper(trim($reference)); if ($reference !== '') $activationInsert->execute([$uploadId, $reference, trim((string) $name) ?: null]); }
    $endorsementInsert = $pdo->prepare('INSERT INTO endorsements (upload_id, code, label) VALUES (?, ?, ?)');
    foreach (preg_split('/[\\r\\n]+/', (string) ($_POST['endorsements'] ?? '')) as $line) { $line = trim($line); if ($line === '') continue; [$code, $name] = array_pad(explode('|', $line, 2), 2, null); $code = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', trim($code))); $name = trim((string) $name); if ($code !== '' && $name !== '') $endorsementInsert->execute([$uploadId, $code, mb_substr($name, 0, 120)]); }
    $pdo->commit();
    flash('success', sprintf('Importação concluída: %d QSOs e %d indicativos disponíveis para consulta.', count($qsos), count(array_unique(array_column($qsos, 'callsign')))));
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (isset($storedName)) @unlink((CERT50_PRIVATE_ROOT ?? '') . '/storage/adif/' . $storedName);
    error_log('ARDX50 import: ' . $error->getMessage());
    flash('error', $error->getMessage());
}
header('Location: index.php');
exit;
