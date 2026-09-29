<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

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
