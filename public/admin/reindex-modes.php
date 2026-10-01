<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/auth.php';
require_once CERT50_PRIVATE_ROOT . '/app/adif.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#adifs-importados');
    exit;
}

verify_csrf();
$uploadId = filter_input(INPUT_POST, 'upload_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

try {
    if (!$uploadId) throw new RuntimeException('Importação ADIF inválida.');
    $pdo = db();
    $find = $pdo->prepare('SELECT stored_filename FROM uploads WHERE id = ?');
    $find->execute([$uploadId]);
    $filename = $find->fetchColumn();
    if (!is_string($filename) || $filename === '' || basename($filename) !== $filename || str_contains($filename, '\\')) {
        throw new RuntimeException('O arquivo original deste ADIF não foi encontrado.');
    }
    $file = CERT50_PRIVATE_ROOT . '/storage/adif/' . $filename;
    if (!is_file($file) || !is_readable($file)) {
        throw new RuntimeException('O arquivo original deste ADIF não está disponível para leitura.');
    }
    $contents = file_get_contents($file);
    if ($contents === false) throw new RuntimeException('Não foi possível ler o ADIF original.');
    $parsed = parse_adif($contents);

    $storedQuery = $pdo->prepare('SELECT id, callsign, qso_date, qso_time, band, mode FROM qsos WHERE upload_id = ? ORDER BY id');
    $storedQuery->execute([$uploadId]);
    $stored = $storedQuery->fetchAll();
    if (count($parsed) !== count($stored)) {
        throw new RuntimeException('Os QSOs guardados não correspondem ao ADIF original; nenhum dado foi alterado.');
    }

    $updates = [];
    foreach ($parsed as $index => $qso) {
        $current = $stored[$index];
        foreach (['callsign', 'qso_date', 'qso_time', 'band'] as $field) {
            if ((string) ($current[$field] ?? '') !== (string) ($qso[$field] ?? '')) {
                throw new RuntimeException('A ordem dos QSOs mudou; nenhum modo foi alterado.');
            }
        }
        $newMode = $qso['mode'];
        if (strtoupper(trim((string) $current['mode'])) !== 'MFSK' || $newMode === null || $newMode === 'MFSK') continue;
        if (strlen($newMode) > 24) throw new RuntimeException('Um submodo excede o tamanho permitido no banco; nenhum dado foi alterado.');
        $updates[] = [$newMode, (int) $current['id']];
    }

    if ($updates) {
        $pdo->beginTransaction();
        $update = $pdo->prepare("UPDATE qsos SET mode = ? WHERE id = ? AND upload_id = ? AND mode = 'MFSK'");
        foreach ($updates as [$mode, $qsoId]) {
            $update->execute([$mode, $qsoId, $uploadId]);
            if ($update->rowCount() !== 1) throw new RuntimeException('Um QSO mudou durante a atualização; nenhuma alteração foi mantida.');
        }
        $pdo->commit();
    }
    flash('success', count($updates) . ' submodos identificados neste ADIF. Nenhum QSO foi duplicado.');
} catch (RuntimeException $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    flash('error', $error->getMessage());
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('ARDX50 reindex modes: ' . $error->getMessage());
    flash('error', 'Não foi possível atualizar os modos deste ADIF.');
}

header('Location: index.php#adif-' . ($uploadId ?: 's-importados'));
exit;
