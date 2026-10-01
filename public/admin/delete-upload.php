<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#adifs-importados');
    exit;
}

verify_csrf();
$uploadId = filter_input(INPUT_POST, 'upload_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$stagedFile = null;
$originalFile = null;
$committed = false;

try {
    if (!$uploadId) throw new RuntimeException('Importação ADIF inválida.');
    if (($_POST['confirm_delete'] ?? null) !== '1') {
        throw new RuntimeException('Confirme a exclusão deste ADIF antes de continuar.');
    }

    $pdo = db();
    $pdo->beginTransaction();

    $find = $pdo->prepare('SELECT stored_filename FROM uploads WHERE id = ? FOR UPDATE');
    $find->execute([$uploadId]);
    $upload = $find->fetch();
    if (!$upload) throw new RuntimeException('Este ADIF não foi encontrado.');

    $filename = (string) $upload['stored_filename'];
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.(?:adi|adif)$/iD', $filename)) {
        throw new RuntimeException('O nome do arquivo ADIF guardado é inválido. Nenhum dado foi apagado.');
    }

    $shared = $pdo->prepare('SELECT COUNT(*) FROM uploads WHERE stored_filename = ? AND id <> ?');
    $shared->execute([$filename, $uploadId]);
    $isShared = (int) $shared->fetchColumn() > 0;

    $storage = CERT50_PRIVATE_ROOT . '/storage/adif';
    $originalFile = $storage . '/' . $filename;
    if (!$isShared && (is_file($originalFile) || is_link($originalFile))) {
        $stagedFile = $storage . '/.delete-' . bin2hex(random_bytes(16));
        if (!@rename($originalFile, $stagedFile)) {
            $stagedFile = null;
            throw new RuntimeException('Não foi possível preparar a remoção do arquivo ADIF. Nenhum dado foi apagado.');
        }
    }

    $delete = $pdo->prepare('DELETE FROM uploads WHERE id = ?');
    $delete->execute([$uploadId]);
    if ($delete->rowCount() !== 1) throw new RuntimeException('Este ADIF mudou durante a exclusão. Nenhum dado foi apagado.');
    $pdo->commit();
    $committed = true;

    if ($stagedFile !== null && !@unlink($stagedFile)) {
        error_log('ARDX50 delete upload: ADIF removido do banco, mas arquivo temporário não pôde ser apagado: ' . $stagedFile);
        flash('error', 'O log foi removido do site, mas o arquivo ADIF privado precisa ser apagado manualmente. Consulte o registro de erros do servidor.');
    } else {
        flash('success', 'Log ' . $filename . ' apagado. Seus QSOs, ativações e endossos saíram do site.');
    }
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (!$committed && $stagedFile !== null && $originalFile !== null && !@rename($stagedFile, $originalFile)) {
        error_log('ARDX50 delete upload: falha ao restaurar o ADIF após erro: ' . $stagedFile);
        flash('error', 'A exclusão não foi concluída e o ADIF precisa ser restaurado manualmente. Consulte o registro de erros do servidor.');
    } elseif ($error instanceof RuntimeException) {
        flash('error', $error->getMessage());
    } else {
        error_log('ARDX50 delete upload: ' . $error->getMessage());
        flash('error', 'Não foi possível apagar este log agora. Nenhum dado foi removido.');
    }
}

header('Location: index.php#adifs-importados');
exit;
