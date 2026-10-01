<?php
declare(strict_types=1);

function certificate_background(): ?array {
    if (!defined('CERT50_PUBLIC_ROOT')) return null;

    $directory = CERT50_PUBLIC_ROOT . '/assets/certificate';
    foreach (['webp', 'jpg', 'png'] as $extension) {
        $filename = "certificate-background.$extension";
        $path = $directory . '/' . $filename;
        if (is_file($path)) {
            return ['filename' => $filename, 'path' => $path];
        }
    }
    return null;
}

function certificate_hall_photo(): ?array {
    if (!defined('CERT50_PUBLIC_ROOT')) return null;

    $directory = CERT50_PUBLIC_ROOT . '/assets/certificate';
    foreach (['webp', 'jpg', 'png'] as $extension) {
        $filename = "hall-of-fame-photo.$extension";
        $path = $directory . '/' . $filename;
        if (is_file($path)) {
            return ['filename' => $filename, 'path' => $path];
        }
    }
    return null;
}

function certificate_logo(): ?array {
    if (!defined('CERT50_PUBLIC_ROOT')) return null;

    $directory = CERT50_PUBLIC_ROOT . '/assets/certificate';
    foreach (['webp', 'jpg', 'png'] as $extension) {
        $filename = "anniversary-logo.$extension";
        $path = $directory . '/' . $filename;
        if (is_file($path)) {
            return ['filename' => $filename, 'path' => $path];
        }
    }
    return null;
}
