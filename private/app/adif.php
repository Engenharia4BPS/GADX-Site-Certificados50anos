<?php
declare(strict_types=1);

function adif_field(string $record, string $field): ?string {
    $pattern = '/<' . preg_quote($field, '/') . '(?::(\\d+)(?::[^>]*)?)?\\s*>/i';
    if (!preg_match($pattern, $record, $match, PREG_OFFSET_CAPTURE)) return null;
    $start = $match[0][1] + strlen($match[0][0]);
    $length = isset($match[1][0]) && $match[1][0] !== '' ? (int) $match[1][0] : null;
    $value = $length ? substr($record, $start, $length) : explode('<', substr($record, $start), 2)[0];
    $value = trim($value);
    return $value === '' ? null : $value;
}

function adif_header_field(string $contents, string $field): ?string {
    $header = preg_split('/<eoh\\s*>/i', $contents, 2)[0] ?? '';
    return adif_field($header, $field);
}

function adif_callsign(?string $value): ?string {
    $callsign = strtoupper((string) $value);
    $callsign = preg_replace('#[^A-Z0-9/]#', '', $callsign);
    return strlen($callsign) >= 3 ? $callsign : null;
}

function adif_clublog_station(string $contents): ?string {
    $programId = adif_header_field($contents, 'PROGRAMID');
    if (!is_string($programId) || strcasecmp(trim($programId), 'Club Log') !== 0) return null;
    if (!preg_match('#(?:^|\R)\s*Log export of\s+([A-Z0-9/]+)\s+at\b#im', $contents, $match)) return null;
    return adif_callsign($match[1]);
}

function adif_flag(string $record, string $field): bool {
    return adif_field($record, $field) !== null;
}

function event_stations(): array {
    return ['ZW5B', 'ZW50B', 'PY5GA', 'PQ5TA'];
}

function parse_adif(string $contents): array {
    $rows = [];
    $headerStation = adif_callsign(adif_header_field($contents, 'STATION_CALLSIGN'))
        ?? adif_callsign(adif_header_field($contents, 'MY_CALL'))
        ?? adif_clublog_station($contents);
    foreach (preg_split('/<eor\\s*>/i', $contents) as $record) {
        $callsign = adif_callsign(adif_field($record, 'CALL'));
        if ($callsign === null) continue;
        $stationCallsign = adif_callsign(adif_field($record, 'STATION_CALLSIGN'))
            ?? adif_callsign(adif_field($record, 'MY_CALL'))
            ?? $headerStation;
        $mode = strtoupper((string) adif_field($record, 'MODE')) ?: null;
        $submode = strtoupper((string) adif_field($record, 'SUBMODE')) ?: null;
        if ($mode === 'MFSK' && $submode !== null) $mode = $submode;
        $propagation = strtoupper((string) adif_field($record, 'PROP_MODE'));
        $rows[] = [
            'callsign' => $callsign,
            'station_callsign' => $stationCallsign,
            'qso_date' => adif_field($record, 'QSO_DATE'),
            'qso_time' => adif_field($record, 'TIME_ON'),
            'band' => strtoupper((string) adif_field($record, 'BAND')) ?: null,
            'mode' => $mode,
            'is_satellite' => $propagation === 'SAT' || adif_flag($record, 'SAT_NAME'),
            'is_wff' => adif_flag($record, 'MY_WWFF_REF'),
            'is_pota' => adif_flag($record, 'MY_POTA_REF'),
        ];
    }
    return $rows;
}

function adif_activity_date(array $qsos): ?string {
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
