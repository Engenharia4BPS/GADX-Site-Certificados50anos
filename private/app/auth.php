<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function login_rate_limit_settings(): array {
    $custom = config('login_rate_limit');
    if (!is_array($custom)) $custom = [];

    return [
        'window_seconds' => max(60, min(86400, (int) ($custom['window_seconds'] ?? 900))),
        'credential_attempts' => max(3, min(100, (int) ($custom['credential_attempts'] ?? 5))),
        'ip_attempts' => max(5, min(500, (int) ($custom['ip_attempts'] ?? 20))),
    ];
}

function login_rate_limit_directory(): ?string {
    static $resolved = false;
    static $directory = null;
    if ($resolved) return $directory;
    $resolved = true;

    $sessionRoot = config('session_save_path');
    $root = is_string($sessionRoot) && $sessionRoot !== '' && is_dir($sessionRoot) && is_writable($sessionRoot)
        ? $sessionRoot
        : CERT50_PRIVATE_ROOT . '/storage';
    $candidate = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'login-rate-limit';
    if (!is_dir($candidate) && !@mkdir($candidate, 0700, true) && !is_dir($candidate)) {
        error_log('ARDX50 login rate limit: não foi possível criar a pasta privada.');
        return null;
    }
    if (!is_writable($candidate)) {
        error_log('ARDX50 login rate limit: a pasta privada não permite gravação.');
        return null;
    }
    @chmod($candidate, 0700);
    $directory = $candidate;
    return $directory;
}

function login_rate_limit_file(string $bucket, string $identity): ?string {
    $directory = login_rate_limit_directory();
    if ($directory === null) return null;
    $key = (string) config('app_key');
    if ($key === '') $key = CERT50_PRIVATE_ROOT;
    $digest = hash_hmac('sha256', $bucket . "\n" . $identity, $key);
    return $directory . DIRECTORY_SEPARATOR . $bucket . '-' . $digest . '.json';
}

function login_rate_limit_bucket(string $bucket, string $identity, bool $recordFailure = false): array {
    $file = login_rate_limit_file($bucket, $identity);
    if ($file === null || (!$recordFailure && !is_file($file))) return [];

    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        error_log('ARDX50 login rate limit: não foi possível abrir o contador privado.');
        return [];
    }
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        error_log('ARDX50 login rate limit: não foi possível bloquear o contador privado.');
        return [];
    }

    $settings = login_rate_limit_settings();
    $now = time();
    rewind($handle);
    $decoded = json_decode((string) stream_get_contents($handle), true);
    $timestamps = is_array($decoded) ? $decoded : [];
    $timestamps = array_values(array_filter($timestamps, static fn($value): bool => is_int($value) && $value > $now - $settings['window_seconds'] && $value <= $now));
    if ($recordFailure) $timestamps[] = $now;

    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($timestamps, JSON_THROW_ON_ERROR));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    @chmod($file, 0600);
    return $timestamps;
}

function login_rate_limit_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return is_string($ip) && $ip !== '' ? $ip : 'unknown';
}

function login_rate_limit_status(string $email): array {
    $settings = login_rate_limit_settings();
    $ip = login_rate_limit_client_ip();
    $credentialIdentity = $ip . "\n" . strtolower(trim($email));
    $buckets = [
        [login_rate_limit_bucket('credential', $credentialIdentity), $settings['credential_attempts']],
        [login_rate_limit_bucket('ip', $ip), $settings['ip_attempts']],
    ];
    $retryAfter = 0;
    $now = time();
    foreach ($buckets as [$timestamps, $limit]) {
        if (count($timestamps) < $limit) continue;
        sort($timestamps, SORT_NUMERIC);
        $threshold = $timestamps[count($timestamps) - $limit];
        $retryAfter = max($retryAfter, $threshold + $settings['window_seconds'] - $now);
    }
    return ['blocked' => $retryAfter > 0, 'retry_after' => max(0, $retryAfter)];
}

function login_rate_limit_record_failure(string $email): void {
    $ip = login_rate_limit_client_ip();
    login_rate_limit_bucket('credential', $ip . "\n" . strtolower(trim($email)), true);
    login_rate_limit_bucket('ip', $ip, true);
}

function login_rate_limit_clear_credential(string $email): void {
    $identity = login_rate_limit_client_ip() . "\n" . strtolower(trim($email));
    $file = login_rate_limit_file('credential', $identity);
    if ($file !== null && is_file($file)) @unlink($file);
}

function current_admin(): ?array {
    start_secure_session();
    $id = $_SESSION['admin_id'] ?? null;
    if (!is_int($id) && !ctype_digit((string) $id)) return null;
    $query = db()->prepare('SELECT id, email, role FROM admins WHERE id = ? LIMIT 1');
    $query->execute([(int) $id]);
    return $query->fetch() ?: null;
}

function require_admin(): array {
    $admin = current_admin();
    if ($admin) return $admin;
    header('Location: login.php');
    exit;
}

function require_owner(): array {
    $admin = require_admin();
    if ($admin['role'] === 'owner') return $admin;
    http_response_code(403);
    exit('Apenas o administrador proprietário pode acessar esta página.');
}
