<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$searchValue = $_GET['q'] ?? '';
$search = is_string($searchValue) ? preg_replace('/[^A-Z0-9\/]/', '', strtoupper($searchValue)) : '';
$search = substr($search, 0, 32);
$pageValue = $_GET['page'] ?? '1';
$page = is_string($pageValue) && ctype_digit($pageValue) ? max(1, min(100000, (int) $pageValue)) : 1;
$perPage = 30;

try {
    $pdo = db();
    $like = $search . '%';
    $count = $pdo->prepare('SELECT COUNT(DISTINCT callsign) FROM qsos WHERE callsign LIKE ?');
    $count->execute([$like]);
    $participantCount = (int) $count->fetchColumn();
    $pageCount = max(1, (int) ceil($participantCount / $perPage));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $perPage;

    $summary = $pdo->prepare(
        "SELECT callsign, COUNT(*) AS total, COUNT(DISTINCT NULLIF(UPPER(TRIM(band)), '')) AS band_count
         FROM qsos
         WHERE callsign LIKE ?
         GROUP BY callsign
         ORDER BY band_count DESC, total DESC, callsign ASC
         LIMIT $perPage OFFSET $offset"
    );
    $summary->execute([$like]);
    $rows = $summary->fetchAll();

    if ($rows) {
        $callsigns = array_column($rows, 'callsign');
        $marks = implode(',', array_fill(0, count($callsigns), '?'));
        $details = $pdo->prepare(
            "SELECT q.callsign, q.upload_id, q.band, q.mode, u.is_wff, u.is_satellite, COUNT(*) AS total
             FROM qsos q
             INNER JOIN uploads u ON u.id = q.upload_id
             WHERE q.callsign IN ($marks)
             GROUP BY q.callsign, q.upload_id, q.band, q.mode, u.is_wff, u.is_satellite"
        );
        $details->execute($callsigns);
        $groups = $details->fetchAll();

        $uploadIds = array_values(array_unique(array_map('intval', array_column($groups, 'upload_id'))));
        $potaUploads = [];
        if ($uploadIds) {
            $uploadMarks = implode(',', array_fill(0, count($uploadIds), '?'));
            $pota = $pdo->prepare("SELECT DISTINCT upload_id FROM endorsements WHERE code = 'POTA' AND upload_id IN ($uploadMarks)");
            $pota->execute($uploadIds);
            $potaUploads = array_fill_keys(array_map('intval', $pota->fetchAll(PDO::FETCH_COLUMN)), true);
        }

        $byCallsign = [];
        foreach ($rows as $index => $row) {
            $callsign = $row['callsign'];
            $byCallsign[$callsign] = [
                'rank' => $offset + $index + 1,
                'callsign' => $callsign,
                'band_count' => (int) $row['band_count'],
                'total' => (int) $row['total'],
                'achievements' => [],
                'bands' => [],
                'sat' => [],
            ];
        }

        foreach ($groups as $group) {
            $callsign = $group['callsign'];
            $band = strtoupper(trim((string) $group['band'])) ?: 'SEM BANDA';
            $mode = strtoupper(trim((string) $group['mode'])) ?: 'N/I';
            $total = (int) $group['total'];
            $record = &$byCallsign[$callsign];
            $record['bands'][$band][$mode] = ($record['bands'][$band][$mode] ?? 0) + $total;
            if ((int) $group['is_satellite'] === 1) {
                $record['sat'][$mode] = ($record['sat'][$mode] ?? 0) + $total;
                $record['achievements']['SAT'] = true;
            }
            if ((int) $group['is_wff'] === 1) $record['achievements']['WFF'] = true;
            if ($mode === 'CW') $record['achievements']['CW'] = true;
            if (isset($potaUploads[(int) $group['upload_id']])) $record['achievements']['POTA'] = true;
            unset($record);
        }

        foreach ($byCallsign as &$record) {
            $record['achievements'] = array_values(array_intersect(['WFF', 'POTA', 'SAT', 'CW'], array_keys($record['achievements'])));
            ksort($record['bands']);
            foreach ($record['bands'] as &$modes) ksort($modes);
            unset($modes);
            ksort($record['sat']);
        }
        unset($record);
        $rows = array_values($byCallsign);
    }

    echo json_encode([
        'rows' => $rows,
        'total_participants' => $participantCount,
        'page' => $page,
        'page_count' => $pageCount,
        'per_page' => $perPage,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('ARDX50 ranking: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'O ranking está temporariamente indisponível.']);
}
