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

function parse_adif(string $contents): array {
    $rows = [];
    foreach (preg_split('/<eor\\s*>/i', $contents) as $record) {
        $callsign = strtoupper((string) adif_field($record, 'CALL'));
        $callsign = preg_replace('/[^A-Z0-9\\/]/', '', $callsign);
        if (strlen($callsign) < 3) continue;
        $rows[] = [
            'callsign' => $callsign,
            'qso_date' => adif_field($record, 'QSO_DATE'),
            'qso_time' => adif_field($record, 'TIME_ON'),
            'band' => strtoupper((string) adif_field($record, 'BAND')) ?: null,
            'mode' => strtoupper((string) adif_field($record, 'MODE')) ?: null,
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
