<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';

$alreadyInstalled = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
if ($alreadyInstalled) { header('Location: login.php'); exit; }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $installKey = (string) ($_POST['install_key'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Informe um e-mail válido.';
    elseif (strlen($password) < 12) $error = 'Use uma senha de ao menos 12 caracteres.';
    elseif (!hash_equals((string) config('install_key'), $installKey)) $error = 'Chave de instalação inválida.';
    else {
        $insert = db()->prepare("INSERT INTO admins (email, password_hash, role) VALUES (?, ?, 'owner')");
        $insert->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
        flash('success', 'Administrador proprietário criado. Faça login para continuar.');
        header('Location: login.php'); exit;
    }
}
$csrfToken = csrf_token();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ativação — Araucária DX 50 anos</title><link rel="icon" href="../assets/favicon.svg?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/favicon.svg') ?>" type="image/svg+xml" sizes="any"><link rel="stylesheet" href="../assets/site.css"></head><body>
<header class="site-header"><a class="brand" href="../"><img class="brand-mark" src="../assets/brand-mark.svg" alt=""><span><strong>ARAUCÁRIA DX</strong><small>GESTÃO · 50 ANOS</small></span></a></header>
<main class="admin-shell"><section class="admin-card" style="max-width:600px;margin:auto"><p class="eyebrow">PRIMEIRA INSTALAÇÃO</p><h1>Criar administrador proprietário</h1><p>Use a chave definida em <code>private/app/config.php</code>. Depois da ativação, apague ou renomeie este arquivo <code>setup.php</code>.</p><?php if ($error): ?><p class="flash danger"><?= h($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= h($csrfToken) ?>"><label class="field">E-mail<input type="email" name="email" required></label><label class="field">Senha (mínimo de 12 caracteres)<input type="password" name="password" minlength="12" required></label><label class="field">Chave de instalação<input type="password" name="install_key" required></label><div class="form-actions"><button type="submit">Ativar administração</button></div></form></section></main></body></html>
