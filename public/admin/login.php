<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/auth.php';

if (current_admin()) { header('Location: index.php'); exit; }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $rateLimit = login_rate_limit_status($email);
    if ($rateLimit['blocked']) {
        http_response_code(429);
        header('Retry-After: ' . $rateLimit['retry_after']);
        $minutes = max(1, (int) ceil($rateLimit['retry_after'] / 60));
        $error = "Muitas tentativas de acesso. Aguarde $minutes minuto(s) e tente novamente.";
    } else {
        $query = db()->prepare('SELECT id, password_hash FROM admins WHERE email = ? LIMIT 1');
        $query->execute([$email]);
        $admin = $query->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            login_rate_limit_clear_credential($email);
            start_secure_session();
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            header('Location: index.php'); exit;
        }
        login_rate_limit_record_failure($email);
        $rateLimit = login_rate_limit_status($email);
        if ($rateLimit['blocked']) {
            http_response_code(429);
            header('Retry-After: ' . $rateLimit['retry_after']);
            $minutes = max(1, (int) ceil($rateLimit['retry_after'] / 60));
            $error = "Muitas tentativas de acesso. Aguarde $minutes minuto(s) e tente novamente.";
        } else {
            $error = 'E-mail ou senha incorretos.';
        }
    }
}
$csrfToken = csrf_token();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Entrar — Araucária DX 50 anos</title><link rel="icon" href="../assets/favicon.svg?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/favicon.svg') ?>" type="image/svg+xml" sizes="any"><link rel="stylesheet" href="../assets/site.css"></head><body>
<header class="site-header"><a class="brand" href="../"><img class="brand-mark" src="../assets/brand-mark.svg" alt=""><span><strong>ARAUCÁRIA DX</strong><small>GESTÃO · 50 ANOS</small></span></a></header>
<main class="admin-shell"><section class="admin-card" style="max-width:520px;margin:auto"><p class="eyebrow">ORGANIZAÇÃO</p><h1>Entrar na administração</h1><p>Use a conta criada pelo administrador proprietário.</p><?php if ($error): ?><p class="flash danger"><?= h($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= h($csrfToken) ?>"><label class="field">E-mail<input type="email" name="email" autocomplete="email" required></label><label class="field">Senha<input type="password" name="password" autocomplete="current-password" required></label><div class="form-actions"><button type="submit">Entrar</button><a class="admin-link" href="../">Voltar ao site</a></div></form></section></main></body></html>
