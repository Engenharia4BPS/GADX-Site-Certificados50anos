<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/_bootstrap.php';
start_secure_session();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
