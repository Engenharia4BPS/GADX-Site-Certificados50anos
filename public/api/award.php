<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$callsign = strtoupper((string) ($_GET['callsign'] ?? ''));
$callsign = preg_replace('/[^A-Z0-9\\/]/', '', $callsign);
if (strlen($callsign) < 3) {
    http_response_code(400);
    echo json_encode(['error' => 'Indicativo inválido.']);
    exit;
}

try {
    $pdo = db();
    $query = $pdo->prepare('SELECT q.upload_id, q.qso_date, q.band, u.is_satellite, u.is_wff FROM qsos q INNER JOIN uploads u ON u.id = q.upload_id WHERE q.callsign = ?');
    $query->execute([$callsign]);
    $contacts = $query->fetchAll();
    if (!$contacts) {
        echo json_encode(['found' => false, 'callsign' => $callsign]);
        exit;
    }
    $uploadIds = array_values(array_unique(array_column($contacts, 'upload_id')));
    $marks = implode(',', array_fill(0, count($uploadIds), '?'));
    $parks = $pdo->prepare("SELECT DISTINCT reference_code AS reference, COALESCE(name, '') AS name FROM activations WHERE upload_id IN ($marks) ORDER BY reference_code");
    $parks->execute($uploadIds);
    $custom = $pdo->prepare("SELECT DISTINCT code, label FROM endorsements WHERE upload_id IN ($marks) ORDER BY label");
    $custom->execute($uploadIds);
    $endorsements = [['code' => 'ARDX50', 'label' => 'Araucária DX · 50 anos']];
    if (array_filter($contacts, fn($row) => (bool) $row['is_wff'])) $endorsements[] = ['code' => 'WFF', 'label' => 'Ativação WFF'];
    if (array_filter($contacts, fn($row) => (bool) $row['is_satellite'])) $endorsements[] = ['code' => 'SAT', 'label' => 'Contato via satélite'];
    foreach ($custom->fetchAll() as $item) $endorsements[$item['code']] = $item;
    $bands = array_values(array_unique(array_filter(array_column($contacts, 'band'))));
    sort($bands);
    $dates = array_values(array_unique(array_filter(array_column($contacts, 'qso_date'))));
    sort($dates);
    echo json_encode(['found' => true, 'callsign' => $callsign, 'contacts' => count($contacts), 'bands' => $bands, 'dates' => $dates, 'activations' => $parks->fetchAll(), 'endorsements' => array_values($endorsements)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('ARDX50 award lookup: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'Os dados estão temporariamente indisponíveis.']);
}
