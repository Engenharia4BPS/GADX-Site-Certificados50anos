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
    $qsoSource = public_qso_source($pdo);
    $like = $search . '%';
    $count = $pdo->prepare("SELECT COUNT(DISTINCT callsign) FROM $qsoSource WHERE callsign LIKE ?");
    $count->execute([$like]);
    $participantCount = (int) $count->fetchColumn();
    $pageCount = max(1, (int) ceil($participantCount / $perPage));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $perPage;

    $summary = $pdo->prepare(
        "SELECT callsign,
                COUNT(DISTINCT CONCAT_WS('|', UPPER(TRIM(station_callsign)), COALESCE(NULLIF(UPPER(TRIM(band)), ''), 'SEM BANDA'), COALESCE(NULLIF(UPPER(TRIM(mode)), ''), 'N/I'))) AS total,
                COUNT(DISTINCT NULLIF(UPPER(TRIM(band)), '')) AS band_count
         FROM $qsoSource
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
            "SELECT q.callsign, q.station_callsign, q.band, q.mode,
                    MAX(q.is_wff) AS is_wff, MAX(q.is_pota) AS is_pota, MAX(q.is_satellite) AS is_satellite
             FROM $qsoSource q
             WHERE q.callsign IN ($marks)
             GROUP BY q.callsign, q.station_callsign, q.band, q.mode"
        );
        $details->execute($callsigns);
        $groups = $details->fetchAll();

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
            $station = strtoupper(trim((string) $group['station_callsign']));
            $band = strtoupper(trim((string) $group['band'])) ?: 'SEM BANDA';
            $mode = strtoupper(trim((string) $group['mode'])) ?: 'N/I';
            $record = &$byCallsign[$callsign];
            $contact = ['station' => $station, 'mode' => $mode];
            $contactKey = $station . '|' . $mode;
            $record['bands'][$band][$contactKey] = $contact;
            if ((int) $group['is_satellite'] === 1) {
                $record['sat'][$contactKey] = $contact;
                $record['achievements']['SAT'] = true;
            }
            if ((int) $group['is_wff'] === 1) $record['achievements']['WFF'] = true;
            if ($mode === 'CW') $record['achievements']['CW'] = true;
            if ((int) $group['is_pota'] === 1) $record['achievements']['POTA'] = true;
            unset($record);
        }

        foreach ($byCallsign as &$record) {
            $record['achievements'] = array_values(array_intersect(['WFF', 'POTA', 'SAT', 'CW'], array_keys($record['achievements'])));
            ksort($record['bands']);
            foreach ($record['bands'] as &$modes) {
                ksort($modes);
                $modes = array_values($modes);
            }
            unset($modes);
            ksort($record['sat']);
            $record['sat'] = array_values($record['sat']);
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
