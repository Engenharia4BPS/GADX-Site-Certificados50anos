<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/auth.php';
require_once CERT50_PRIVATE_ROOT . '/app/certificate.php';
require_once CERT50_PRIVATE_ROOT . '/app/adif.php';

$admin = require_admin();
$success = flash('success');
$error = flash('error');
$certificateBackground = certificate_background();
$hallPhoto = certificate_hall_photo();
$certificateLogo = certificate_logo();
$eventStations = event_stations();

$uploads = db()->query(
    'SELECT u.id, u.label, u.activity_date, u.source_filename, u.stored_filename, u.is_satellite, u.is_wff, u.notes, u.created_at,
            a.email AS created_by_email, COUNT(q.id) AS qso_count, COUNT(DISTINCT q.callsign) AS callsign_count
     FROM uploads u
     INNER JOIN admins a ON a.id = u.created_by
     LEFT JOIN qsos q ON q.upload_id = u.id
     GROUP BY u.id, u.label, u.activity_date, u.source_filename, u.stored_filename, u.is_satellite, u.is_wff, u.notes, u.created_at, a.email
     ORDER BY COALESCE(u.activity_date, DATE(u.created_at)) DESC, u.id DESC'
)->fetchAll();

$breakdowns = [];
foreach (['bands' => 'band', 'modes' => 'mode'] as $group => $column) {
    $stats = db()->query(
        "SELECT upload_id, COALESCE(NULLIF(TRIM($column), ''), 'Não informado') AS label, COUNT(*) AS total
         FROM qsos
         GROUP BY upload_id, COALESCE(NULLIF(TRIM($column), ''), 'Não informado')
         ORDER BY upload_id, total DESC, label ASC"
    )->fetchAll();
    foreach ($stats as $stat) {
        $breakdowns[(int) $stat['upload_id']][$group][] = $stat;
    }
}

$stationsByUpload = [];
$stationRows = db()->query('SELECT upload_id, station_callsign, qso_count FROM upload_stations ORDER BY upload_id, station_callsign')->fetchAll();
foreach ($stationRows as $stationRow) {
    $stationsByUpload[(int) $stationRow['upload_id']][] = $stationRow;
}

$currentSnapshots = [];
$currentRows = db()->query(
    'SELECT current_upload.station_callsign, current_upload.upload_id, current_upload.updated_at, u.stored_filename, u.source_filename, upload_stations.qso_count
     FROM station_current_uploads current_upload
     INNER JOIN uploads u ON u.id = current_upload.upload_id
     INNER JOIN upload_stations ON upload_stations.upload_id = current_upload.upload_id AND upload_stations.station_callsign = current_upload.station_callsign
     ORDER BY current_upload.station_callsign'
)->fetchAll();
foreach ($currentRows as $currentRow) {
    $currentSnapshots[$currentRow['station_callsign']] = $currentRow;
}

$endorsementsByUpload = [];
$endorsements = db()->query('SELECT upload_id, code, label FROM endorsements ORDER BY upload_id, label, code')->fetchAll();
foreach ($endorsements as $endorsement) {
    $endorsementsByUpload[(int) $endorsement['upload_id']][] = $endorsement;
}

$activationsByUpload = [];
$activations = db()->query('SELECT upload_id, reference_code, name FROM activations ORDER BY upload_id, reference_code')->fetchAll();
foreach ($activations as $activation) {
    $activationsByUpload[(int) $activation['upload_id']][] = $activation;
}

$formatDate = static function (?string $date): string {
    if (!$date) return 'Data não informada';
    $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $value ? $value->format('d/m/Y') : $date;
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Organização — Araucária DX 50 anos</title>
  <link rel="icon" href="../assets/favicon.svg?v=<?= (int) filemtime(dirname(__DIR__) . '/assets/favicon.svg') ?>" type="image/svg+xml" sizes="any">
  <link rel="stylesheet" href="../assets/site.css">
</head>
<body>
  <header class="site-header">
    <a class="brand" href="../"><img class="brand-mark" src="../assets/brand-mark.svg" alt=""><span><strong>ARAUCÁRIA DX</strong><small>GESTÃO · 50 ANOS</small></span></a>
    <a class="admin-link" href="../">Ver consulta pública</a>
  </header>

  <main class="admin-shell">
    <p class="eyebrow">ORGANIZAÇÃO</p>
    <h1>Importar logs e reconhecer participantes</h1>
    <p class="lead">Cada ADIF é tratado como um snapshot completo das estações que ele contém. Você pode enviar um log de uma estação ou um ADIF misturado; o ranking sempre usa a versão mais recente de cada uma.</p>
    <nav class="admin-nav">
      <a href="#nova-importacao">Nova importação</a>
      <a href="#adifs-importados">ADIFs importados</a>
      <a href="#imagem-diploma">Imagem do diploma</a>
      <a href="#logo-50anos">Logo 50 anos</a>
      <a href="#foto-hall">Foto Hall of Fame</a>
      <?php if ($admin['role'] === 'owner'): ?><a href="users.php">Administradores</a><?php endif; ?>
      <a href="logout.php">Sair (<?= h($admin['email']) ?>)</a>
    </nav>

    <?php if ($success): ?><p class="flash"><?= h($success) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="flash danger"><?= h($error) ?></p><?php endif; ?>

    <section class="admin-card" aria-labelledby="station-sync-title">
      <p class="eyebrow">SINCRONIZAÇÃO ATUAL</p>
      <h2 id="station-sync-title">Logs vigentes por estação</h2>
      <div class="upload-stat-grid station-sync-grid">
        <?php foreach ($eventStations as $station): ?>
          <?php $snapshot = $currentSnapshots[$station] ?? null; ?>
          <div><span><?= h($station) ?></span><?php if ($snapshot): ?><strong><?= h((string) $snapshot['qso_count']) ?> QSOs</strong><p class="small"><?= h($snapshot['stored_filename']) ?><br>Atualizado em <?= h($snapshot['updated_at']) ?></p><?php else: ?><strong>—</strong><p class="small">Aguardando primeiro snapshot.</p><?php endif; ?></div>
        <?php endforeach; ?>
      </div>
    </section>

    <div id="nova-importacao" class="admin-grid">
      <section class="admin-card">
        <h2>Nova importação ADIF</h2>
        <p>Envie um ADIF completo. Ele pode conter uma ou mais estações habilitadas; use <code>STATION_CALLSIGN</code>, <code>MY_CALL</code> ou o cabeçalho padrão de exportação do Club Log.</p>
        <form action="import.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <label class="field">Arquivo ADIF (.adi ou .adif)<input type="file" name="adif" accept=".adi,.adif,text/plain" required></label>
          <div class="form-actions"><button type="submit">Importar ADIF</button></div>
        </form>
      </section>
      <aside class="admin-card">
        <h2>Como funciona</h2>
        <ul>
          <li>Cada arquivo recebe um identificador como <code>Log50ano001-ZW50B-wsjtx_log.adi</code>.</li>
          <li>São aceitas apenas ZW5B, ZW50B, PY5GA e PQ5TA.</li>
          <li>O novo arquivo passa a ser o snapshot vigente somente das estações encontradas nele.</li>
          <li>WWFF, POTA e satélite são lidos por QSO nos campos do próprio ADIF.</li>
          <li>O ADIF original fica protegido fora de <code>public_html</code>.</li>
        </ul>
      </aside>
    </div>

    <section id="imagem-diploma" class="certificate-image-settings admin-card" aria-labelledby="certificate-image-title">
      <div>
        <p class="eyebrow">VISUAL DO DIPLOMA</p>
        <h2 id="certificate-image-title">Imagem de fundo</h2>
        <p>Envie a arte horizontal do certificado. Ela aparecerá atrás dos dados dinâmicos no diploma público e na impressão. Sem imagem, o fundo bege atual continua em uso.</p>
        <form action="certificate-image.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <label class="field">Imagem do diploma (JPG, PNG ou WebP, até 8 MB)<input type="file" name="certificate_image" accept="image/jpeg,image/png,image/webp" required></label>
          <div class="form-actions"><button type="submit">Enviar imagem</button></div>
        </form>
      </div>
      <div class="certificate-image-preview">
        <?php if ($certificateBackground): ?>
          <img src="../assets/certificate/<?= rawurlencode($certificateBackground['filename']) ?>?v=<?= (int) filemtime($certificateBackground['path']) ?>" alt="Imagem atual do diploma">
          <p class="small">Imagem atual: <?= h($certificateBackground['filename']) ?></p>
        <?php else: ?>
          <div class="certificate-image-placeholder">Fundo bege padrão</div>
          <p class="small">Nenhuma imagem foi enviada.</p>
        <?php endif; ?>
      </div>
    </section>

    <section id="logo-50anos" class="certificate-image-settings admin-card" aria-labelledby="certificate-logo-title">
      <div>
        <p class="eyebrow">MARCA COMEMORATIVA</p>
        <h2 id="certificate-logo-title">Logo dos 50 anos</h2>
        <p>Esta logo substitui o pequeno círculo “50 anos” ao lado do título nas versões Participação e Hall of Fame. Prefira PNG ou WebP com fundo transparente e sem margens grandes; a imagem será exibida inteira, sem cortes.</p>
        <form action="certificate-image.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="image_kind" value="logo">
          <label class="field">Logo (JPG, PNG ou WebP, até 8 MB)<input type="file" name="certificate_image" accept="image/jpeg,image/png,image/webp" required></label>
          <div class="form-actions"><button type="submit">Enviar logo</button></div>
        </form>
      </div>
      <div class="certificate-image-preview certificate-logo-preview">
        <?php if ($certificateLogo): ?>
          <img src="../assets/certificate/<?= rawurlencode($certificateLogo['filename']) ?>?v=<?= (int) filemtime($certificateLogo['path']) ?>" alt="Logo atual dos 50 anos">
          <p class="small">Logo atual: <?= h($certificateLogo['filename']) ?></p>
        <?php else: ?>
          <div class="certificate-image-placeholder">Círculo “50 anos” em uso</div>
          <p class="small">Nenhuma logo foi enviada.</p>
        <?php endif; ?>
      </div>
    </section>

    <section id="foto-hall" class="certificate-image-settings admin-card" aria-labelledby="hall-photo-title">
      <div>
        <p class="eyebrow">VERSÃO HALL OF FAME</p>
        <h2 id="hall-photo-title">Foto dos homenageados</h2>
        <p>Envie a foto do grupo para a composição Hall of Fame, de preferência PNG com fundo transparente. Ela só aparecerá nessa versão; Participação continua sem a foto.</p>
        <form action="hall-photo.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <label class="field">Foto do Hall of Fame (JPG, PNG ou WebP, até 8 MB)<input type="file" name="hall_photo" accept="image/jpeg,image/png,image/webp" required></label>
          <div class="form-actions"><button type="submit">Enviar foto</button></div>
        </form>
      </div>
      <div class="certificate-image-preview">
        <?php if ($hallPhoto): ?>
          <img src="../assets/certificate/<?= rawurlencode($hallPhoto['filename']) ?>?v=<?= (int) filemtime($hallPhoto['path']) ?>" alt="Foto atual do Hall of Fame">
          <p class="small">Foto atual: <?= h($hallPhoto['filename']) ?></p>
        <?php else: ?>
          <div class="certificate-image-placeholder">Foto ainda não enviada</div>
          <p class="small">A composição usa um espaço decorativo até você enviar a foto.</p>
        <?php endif; ?>
      </div>
    </section>

    <section class="admin-card visual-preview-links" aria-labelledby="visual-preview-title">
      <div><p class="eyebrow">PRÉVIAS · DADOS ILUSTRATIVOS</p><h2 id="visual-preview-title">Duas versões visuais</h2><p>Compare a versão de Participação com a composição Hall of Fame. A categoria Hall of Fame ainda não será emitida automaticamente enquanto definimos o critério.</p></div>
      <div class="form-actions"><a class="admin-link" href="../?preview=participation#result">Ver Participação</a><a class="admin-link" href="../?preview=hall#result">Ver Hall of Fame</a></div>
    </section>

    <section id="adifs-importados" class="uploads-section" aria-labelledby="uploads-title">
      <div class="section-heading">
        <div>
          <p class="eyebrow">ACERVO</p>
          <h2 id="uploads-title">ADIFs importados</h2>
        </div>
        <span class="record-count"><?= count($uploads) ?> <?= count($uploads) === 1 ? 'arquivo' : 'arquivos' ?></span>
      </div>

      <?php if (!$uploads): ?>
        <section class="admin-card empty-state"><h3>Nenhum ADIF importado</h3><p>Quando uma operação for importada, ela aparecerá aqui com os contatos e endossos correspondentes.</p></section>
      <?php else: ?>
        <div class="upload-list">
          <?php foreach ($uploads as $upload): ?>
            <?php
              $uploadId = (int) $upload['id'];
              $uploadEndorsements = $endorsementsByUpload[$uploadId] ?? [];
              $customEndorsements = array_values(array_filter($uploadEndorsements, static fn(array $endorsement): bool => !in_array($endorsement['code'], ['WFF', 'POTA', 'SAT', 'CW'], true)));
              $uploadStations = $stationsByUpload[$uploadId] ?? [];
              $hasMfsk = (bool) array_filter($breakdowns[$uploadId]['modes'] ?? [], static fn(array $stat): bool => strtoupper((string) $stat['label']) === 'MFSK');
              $activationText = implode(PHP_EOL, array_map(
                  static fn(array $activation): string => $activation['reference_code'] . ($activation['name'] ? ' | ' . $activation['name'] : ''),
                  $activationsByUpload[$uploadId] ?? []
              ));
              $customText = implode(PHP_EOL, array_map(
                  static fn(array $endorsement): string => $endorsement['code'] . ' | ' . $endorsement['label'],
                  $customEndorsements
              ));
            ?>
            <article id="adif-<?= $uploadId ?>" class="upload-card">
              <div class="upload-card-head">
                <div>
                  <p class="eyebrow">ADIF <?= h($upload['stored_filename']) ?> · <?= h($formatDate($upload['activity_date'])) ?></p>
                  <h3><?= h($upload['label']) ?></h3>
                  <p class="upload-meta"><strong>Nome enviado:</strong> <?= h($upload['source_filename']) ?> · Importado por <?= h($upload['created_by_email']) ?><?php if ($uploadStations): ?> · <strong>Estações:</strong> <?= h(implode(', ', array_column($uploadStations, 'station_callsign'))) ?><?php endif; ?></p>
                </div>
                <div class="current-endorsements" aria-label="Endossos atuais">
                  <span class="tag">Araucária DX · 50 anos</span>
                  <?php foreach ($uploadStations as $station): ?><span class="tag"><?= h($station['station_callsign']) ?> · <?= h((string) $station['qso_count']) ?></span><?php endforeach; ?>
                  <?php foreach ($customEndorsements as $endorsement): ?><span class="tag"><?= h($endorsement['label']) ?></span><?php endforeach; ?>
                </div>
              </div>

              <div class="upload-stat-grid">
                <div><span>QSOs importados</span><strong><?= h((string) $upload['qso_count']) ?></strong></div>
                <div><span>Indicativos únicos</span><strong><?= h((string) $upload['callsign_count']) ?></strong></div>
                <div class="stat-breakdown"><span>QSOs por banda</span><p><?php foreach (($breakdowns[$uploadId]['bands'] ?? []) as $stat): ?><b><?= h($stat['label']) ?> <em><?= h((string) $stat['total']) ?></em></b><?php endforeach; ?></p></div>
                <div class="stat-breakdown"><span>QSOs por modo</span><p><?php foreach (($breakdowns[$uploadId]['modes'] ?? []) as $stat): ?><b><?= h($stat['label']) ?> <em><?= h((string) $stat['total']) ?></em></b><?php endforeach; ?></p></div>
              </div>

              <details class="endorsement-editor">
                <summary>Editar esta operação</summary>
                <form action="update-upload.php" method="post">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="upload_id" value="<?= $uploadId ?>">
                  <p class="editor-help">O selo <strong>Araucária DX · 50 anos</strong> é aplicado automaticamente. WWFF, POTA, satélite e CW são identificados por QSO no ADIF; edite abaixo apenas o título, referências, endossos extras e observações.</p>
                  <div class="two-fields">
                    <label class="field">Nome da operação<input name="label" maxlength="180" value="<?= h($upload['label']) ?>" required></label>
                    <label class="field">Data da atividade<input type="date" name="activity_date" value="<?= h($upload['activity_date']) ?>"></label>
                  </div>
                  <label class="field">Unidades de conservação<textarea name="activations" placeholder="Uma por linha: PR-0001 | Nome da unidade&#10;Se não houver, deixe em branco."><?= h($activationText) ?></textarea><span class="small">Ao salvar, estas referências passam a aparecer imediatamente nos diplomas de todos os contatos deste snapshot vigente.</span></label>
                  <label class="field">Endossos adicionais<textarea name="endorsements" placeholder="Um por linha: CODIGO | Nome do endosso&#10;Ex.: SAT-QO100 | QO-100"><?= h($customText) ?></textarea></label>
                  <label class="field">Observações internas<textarea name="notes" placeholder="Opcional — não aparece no diploma."><?= h($upload['notes']) ?></textarea></label>
                  <div class="form-actions"><button type="submit">Salvar e atualizar diplomas</button></div>
                </form>
                <?php if ($hasMfsk): ?>
                  <form action="reindex-modes.php" method="post" class="reindex-form">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="upload_id" value="<?= $uploadId ?>">
                    <button class="secondary" type="submit">Identificar submodos (FT4 e outros)</button>
                    <p class="small">Relê o ADIF original e só corrige modos MFSK quando os QSOs corresponderem. Não cria novos contatos.</p>
                  </form>
                <?php endif; ?>
              </details>
              <details class="upload-delete">
                <summary>Apagar este log</summary>
                <form action="delete-upload.php" method="post">
                  <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="upload_id" value="<?= $uploadId ?>">
                  <p>Isso apagará o snapshot <strong><?= h($upload['stored_filename']) ?></strong>, seus <?= h((string) $upload['qso_count']) ?> QSOs e seus metadados. Se ele for o snapshot vigente de uma estação, o sistema volta ao snapshot anterior disponível para ela. Esta ação não pode ser desfeita.</p>
                  <label class="upload-delete-confirm"><input type="checkbox" name="confirm_delete" value="1" required> Confirmo que quero apagar este log e seus dados.</label>
                  <button type="submit" class="delete-button">Apagar log definitivamente</button>
                </form>
              </details>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
