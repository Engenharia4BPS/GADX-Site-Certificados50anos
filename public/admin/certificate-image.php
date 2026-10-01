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
$kindValue = $_POST['image_kind'] ?? 'background';
$isLogo = $kindValue === 'logo';
$anchor = $isLogo ? 'logo-50anos' : 'imagem-diploma';

try {
    if (!is_string($kindValue) || !in_array($kindValue, ['background', 'logo'], true)) {
        throw new RuntimeException('Tipo de imagem inválido.');
    }
    if (empty($_FILES['certificate_image']) || $_FILES['certificate_image']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException($isLogo ? 'Escolha a logo dos 50 anos.' : 'Escolha uma imagem para o diploma.');
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
        throw new RuntimeException('Não foi possível preparar a pasta das imagens do certificado.');
    }
    if (!is_writable($directory)) {
        throw new RuntimeException('A pasta das imagens do certificado não tem permissão de escrita.');
    }
    if (((fileperms($directory) ?: 0) & 0005) !== 0005 && !@chmod($directory, 0755)) {
        throw new RuntimeException('A pasta das imagens do certificado precisa permitir leitura pelo servidor web.');
    }

    $extension = $allowed[$mime];
    $baseName = $isLogo ? 'anniversary-logo' : 'certificate-background';
    $target = $directory . "/$baseName.$extension";
    $temporary = tempnam($directory, '.certificate-');
    if ($temporary === false || !move_uploaded_file($image['tmp_name'], $temporary)) {
        throw new RuntimeException('Não foi possível guardar a imagem enviada.');
    }
    if (!@chmod($temporary, 0644)) {
        throw new RuntimeException('Não foi possível permitir a leitura da imagem pelo servidor web.');
    }
    if (!rename($temporary, $target)) {
        throw new RuntimeException('Não foi possível publicar a imagem do certificado.');
    }
    $temporary = null;

    foreach (['jpg', 'png', 'webp'] as $oldExtension) {
        $oldFile = $directory . "/$baseName.$oldExtension";
        if ($oldFile !== $target && is_file($oldFile)) @unlink($oldFile);
    }

    flash('success', $isLogo ? 'Logo dos 50 anos atualizada.' : 'Imagem de fundo do diploma atualizada.');
} catch (RuntimeException $error) {
    if (isset($temporary) && is_string($temporary) && is_file($temporary)) @unlink($temporary);
    flash('error', $error->getMessage());
} catch (Throwable $error) {
    if (isset($temporary) && is_string($temporary) && is_file($temporary)) @unlink($temporary);
    error_log('ARDX50 certificate image: ' . $error->getMessage());
    flash('error', 'Não foi possível atualizar a imagem do certificado agora.');
}

header('Location: index.php#' . $anchor);
exit;
