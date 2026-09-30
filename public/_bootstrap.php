<?php
declare(strict_types=1);

if (!defined('CERT50_PUBLIC_ROOT')) {
    define('CERT50_PUBLIC_ROOT', __DIR__);
}

$privatePathFile = __DIR__ . '/private-path.php';
if (!is_file($privatePathFile)) {
    http_response_code(500);
    exit('Configuração do aplicativo ausente.');
}
require_once $privatePathFile;
require_once CERT50_PRIVATE_ROOT . '/app/bootstrap.php';
