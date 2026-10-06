<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/auth.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

verify_csrf();
$uploadId = filter_input(INPUT_POST, 'upload_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

try {
    if (!$uploadId) throw new RuntimeException('Importação ADIF inválida.');

    $label = trim((string) ($_POST['label'] ?? ''));
    if ($label === '') throw new RuntimeException('Informe o nome da operação.');
    $label = mb_substr($label, 0, 180);

    $activityDate = trim((string) ($_POST['activity_date'] ?? '')) ?: null;
    if ($activityDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $activityDate)) {
        throw new RuntimeException('Data da atividade inválida.');
    }
    $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;

    $customEndorsements = [];
    $reservedCodes = ['ARDX50', 'WFF', 'POTA', 'SAT', 'CW'];
    foreach (preg_split('/\R/', (string) ($_POST['endorsements'] ?? '')) as $line) {
        $line = trim($line);
        if ($line === '') continue;

        [$code, $endorsementLabel] = array_pad(explode('|', $line, 2), 2, '');
        $code = strtoupper(preg_replace('/[^A-Z0-9_-]/', '', trim($code)));
        $endorsementLabel = trim($endorsementLabel);
        if ($code === '' || $endorsementLabel === '') {
            throw new RuntimeException('Use o formato CÓDIGO | Nome do endosso em cada linha.');
        }
        if (in_array($code, $reservedCodes, true)) {
            throw new RuntimeException("O código $code já é reservado por um endosso do sistema.");
        }
        if (strlen($code) > 40) {
            throw new RuntimeException('O código de um endosso pode ter no máximo 40 caracteres.');
        }
        $customEndorsements[$code] = ['code' => $code, 'label' => mb_substr($endorsementLabel, 0, 120)];
    }

    $activations = [];
    foreach (preg_split('/[\r\n,]+/', (string) ($_POST['activations'] ?? '')) as $line) {
        $line = trim($line);
        if ($line === '') continue;

        [$reference, $name] = array_pad(explode('|', $line, 2), 2, '');
        $reference = strtoupper(trim($reference));
        $name = trim($name);
        if ($reference === '') throw new RuntimeException('Informe a referência de cada unidade de conservação.');
        $activations[$reference] = [
            'reference' => mb_substr($reference, 0, 80),
            'name' => $name === '' ? null : mb_substr($name, 0, 180),
        ];
    }

    $pdo = db();
    $cumulativeMode = cumulative_imports_enabled($pdo);
    $exists = $pdo->prepare('SELECT id FROM uploads WHERE id = ?');
    $exists->execute([$uploadId]);
    if (!$exists->fetchColumn()) throw new RuntimeException('Esta importação ADIF não foi encontrada.');

    $pdo->beginTransaction();
    $update = $pdo->prepare('UPDATE uploads SET label = ?, activity_date = ?, notes = ? WHERE id = ?');
    $update->execute([$label, $activityDate, $notes, $uploadId]);

    $removeActivations = $pdo->prepare('DELETE FROM activations WHERE upload_id = ?');
    $removeActivations->execute([$uploadId]);
    if ($activations) {
        $insertActivation = $pdo->prepare('INSERT INTO activations (upload_id, reference_code, name) VALUES (?, ?, ?)');
        foreach ($activations as $activation) {
            $insertActivation->execute([$uploadId, $activation['reference'], $activation['name']]);
        }
    }

    $removeCustom = $pdo->prepare('DELETE FROM endorsements WHERE upload_id = ?');
    $removeCustom->execute([$uploadId]);
    if ($customEndorsements) {
        $insertCustom = $pdo->prepare('INSERT INTO endorsements (upload_id, code, label) VALUES (?, ?, ?)');
        foreach ($customEndorsements as $endorsement) {
            $insertCustom->execute([$uploadId, $endorsement['code'], $endorsement['label']]);
        }
    }
    $pdo->commit();

    flash('success', $cumulativeMode
        ? 'Dados atualizados. As referências já estão disponíveis nos diplomas dos contatos incorporados por este arquivo.'
        : 'Dados atualizados. As referências já estão disponíveis nos diplomas dos contatos deste snapshot vigente.');
} catch (RuntimeException $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    flash('error', $error->getMessage());
} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('ARDX50 update upload: ' . $error->getMessage());
    flash('error', 'Não foi possível atualizar este ADIF agora. Tente novamente.');
}

header('Location: index.php#adif-' . ($uploadId ?: 's-importados'));
exit;
