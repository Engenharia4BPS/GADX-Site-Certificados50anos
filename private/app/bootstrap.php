<?php
declare(strict_types=1);

if (!defined('CERT50_PRIVATE_ROOT')) {
    throw new RuntimeException('Caminho privado do aplicativo não foi configurado.');
}

$configFile = CERT50_PRIVATE_ROOT . '/app/config.php';
if (!is_file($configFile)) {
    throw new RuntimeException('Arquivo private/app/config.php não encontrado.');
}

$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'America/Sao_Paulo');

function config(string $key = null): mixed {
    global $config;
    return $key === null ? $config : ($config[$key] ?? null);
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $db = config('db');
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $db['host'], $db['name'], $db['charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function public_qso_source(?PDO $pdo = null): string {
    static $source = null;
    if (is_string($source)) return $source;

    $pdo ??= db();
    try {
        $pdo->query('SELECT 1 FROM aggregate_qsos LIMIT 0');
        $source = 'aggregate_qsos';
    } catch (Throwable) {
        // Mantém o comportamento anterior até a migração acumulativa ser executada.
        $source = 'current_qsos';
    }
    return $source;
}

function cumulative_imports_enabled(?PDO $pdo = null): bool {
    return public_qso_source($pdo) === 'aggregate_qsos';
}

function h(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $sessionPath = config('session_save_path');
    if (is_string($sessionPath) && $sessionPath !== '' && is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }

    session_name('ardx50_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function csrf_token(): string {
    start_secure_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verify_csrf(): void {
    start_secure_session();
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        exit('A sessão expirou. Volte e tente novamente.');
    }
}

function flash(string $key, ?string $value = null): ?string {
    start_secure_session();
    if ($value !== null) { $_SESSION['flash'][$key] = $value; return null; }
    $message = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $message;
}
