<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#foto-hall');
    exit;
}

verify_csrf();

try {
    if (empty($_FILES['hall_photo']) || $_FILES['hall_photo']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Escolha uma foto para o Hall of Fame.');
    }

    $image = $_FILES['hall_photo'];
    if ($image['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('A foto pode ter no máximo 8 MB.');
    }

    $details = @getimagesize($image['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = $details['mime'] ?? '';
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Envie uma foto JPG, PNG ou WebP válida.');
    }

    $directory = CERT50_PUBLIC_ROOT . '/assets/certificate';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível preparar a pasta da foto.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('A pasta da foto não tem permissão de escrita.');
    }
    if (((fileperms($directory) ?: 0) & 0005) !== 0005 && !@chmod($directory, 0755)) {
        throw new RuntimeException('A pasta da foto precisa permitir leitura pelo servidor web.');
    }

    $extension = $allowed[$mime];
    $target = $directory . "/hall-of-fame-photo.$extension";
    $temporary = tempnam($directory, '.hall-photo-');
    if ($temporary === false || !move_uploaded_file($image['tmp_name'], $temporary)) {
        throw new RuntimeException('Não foi possível guardar a foto enviada.');
    }
    if (!@chmod($temporary, 0644)) {
        throw new RuntimeException('Não foi possível permitir a leitura da foto pelo servidor web.');
    }
    if (!rename($temporary, $target)) {
        throw new RuntimeException('Não foi possível publicar a foto do Hall of Fame.');
    }
    $temporary = null;

    foreach (['jpg', 'png', 'webp'] as $oldExtension) {
        $oldFile = $directory . "/hall-of-fame-photo.$oldExtension";
        if ($oldFile !== $target && is_file($oldFile)) @unlink($oldFile);
    }

    flash('success', 'Foto do Hall of Fame atualizada.');
} catch (RuntimeException $error) {
    if (isset($temporary) && is_string($temporary) && is_file($temporary)) @unlink($temporary);
    flash('error', $error->getMessage());
} catch (Throwable $error) {
    if (isset($temporary) && is_string($temporary) && is_file($temporary)) @unlink($temporary);
    error_log('ARDX50 hall photo: ' . $error->getMessage());
    flash('error', 'Não foi possível atualizar a foto do Hall of Fame agora.');
}

header('Location: index.php#foto-hall');
exit;
