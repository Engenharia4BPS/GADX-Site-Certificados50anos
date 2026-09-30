<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#imagem-diploma');
    exit;
}

verify_csrf();

try {
    if (empty($_FILES['certificate_image']) || $_FILES['certificate_image']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Escolha uma imagem para o diploma.');
    }

    $image = $_FILES['certificate_image'];
    if ($image['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('A imagem pode ter no máximo 8 MB.');
    }

    $details = @getimagesize($image['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = $details['mime'] ?? '';
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Envie uma imagem JPG, PNG ou WebP válida.');
    }

    $directory = CERT50_PUBLIC_ROOT . '/assets/certificate';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Não foi possível preparar a pasta da imagem do diploma.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('A pasta da imagem do diploma não tem permissão de escrita.');
    }

    $extension = $allowed[$mime];
    $target = $directory . "/certificate-background.$extension";
    $temporary = tempnam($directory, '.certificate-');
    if ($temporary === false || !move_uploaded_file($image['tmp_name'], $temporary)) {
        throw new RuntimeException('Não foi possível guardar a imagem enviada.');
    }
    if (!rename($temporary, $target)) {
        @unlink($temporary);
        throw new RuntimeException('Não foi possível publicar a imagem do diploma.');
    }

    foreach (['jpg', 'png', 'webp'] as $oldExtension) {
        $oldFile = $directory . "/certificate-background.$oldExtension";
        if ($oldFile !== $target && is_file($oldFile)) @unlink($oldFile);
    }

    flash('success', 'Imagem de fundo do diploma atualizada.');
} catch (RuntimeException $error) {
    flash('error', $error->getMessage());
} catch (Throwable $error) {
    error_log('ARDX50 certificate image: ' . $error->getMessage());
    flash('error', 'Não foi possível atualizar a imagem do diploma agora.');
}

header('Location: index.php#imagem-diploma');
exit;
