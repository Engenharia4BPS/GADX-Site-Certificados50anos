<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/auth.php';
require_once CERT50_PRIVATE_ROOT . '/app/certificate.php';

$admin = require_admin();
$success = flash('success');
$error = flash('error');
$certificateBackground = certificate_background();
$hallPhoto = certificate_hall_photo();
$certificateLogo = certificate_logo();

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
    <p class="lead">Cada ADIF importado alimenta a consulta pública. Revise as estatísticas de cada operação e escolha os endossos aplicados a todos os seus participantes.</p>
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

    <div id="nova-importacao" class="admin-grid">
      <section class="admin-card">
        <h2>Nova importação ADIF</h2>
        <p>Escolha o ADIF. A data será lida dos QSOs e os detalhes da operação poderão ser ajustados na lista de ADIFs.</p>
        <form action="import.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
          <label class="field">Arquivo ADIF (.adi ou .adif)<input type="file" name="adif" accept=".adi,.adif,text/plain" required></label>
          <div class="form-actions"><button type="submit">Importar ADIF</button></div>
        </form>
      </section>
      <aside class="admin-card">
        <h2>Como funciona</h2>
        <ul>
          <li>Cada arquivo recebe um identificador como <code>Log50ano001-wsjtx_log.adi</code>.</li>
          <li>A primeira data válida do ADIF é usada como data da operação.</li>
          <li>Endossos, ativações e observações são configurados na lista de ADIFs.</li>
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
              $hasSatellite = (int) $upload['is_satellite'] === 1;
              $hasWff = (int) $upload['is_wff'] === 1;
              $uploadEndorsements = $endorsementsByUpload[$uploadId] ?? [];
              $hasPota = (bool) array_filter($uploadEndorsements, static fn(array $endorsement): bool => $endorsement['code'] === 'POTA');
              $customEndorsements = array_values(array_filter($uploadEndorsements, static fn(array $endorsement): bool => $endorsement['code'] !== 'POTA'));
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
                  <p class="upload-meta"><strong>Nome enviado:</strong> <?= h($upload['source_filename']) ?> · Importado por <?= h($upload['created_by_email']) ?></p>
                </div>
                <div class="current-endorsements" aria-label="Endossos atuais">
                  <span class="tag">Araucária DX · 50 anos</span>
                  <?php if ($hasSatellite): ?><span class="tag">Via satélite</span><?php endif; ?>
                  <?php if ($hasWff): ?><span class="tag">Ativação WWFF</span><?php endif; ?>
                  <?php if ($hasPota): ?><span class="tag">Ativação POTA</span><?php endif; ?>
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
                  <p class="editor-help">O selo <strong>Araucária DX · 50 anos</strong> é aplicado automaticamente. As escolhas abaixo valem para todos os participantes deste arquivo; o selo CW é reconhecido pelo modo de cada QSO.</p>
                  <div class="two-fields">
                    <label class="field">Nome da operação<input name="label" maxlength="180" value="<?= h($upload['label']) ?>" required></label>
                    <label class="field">Data da atividade<input type="date" name="activity_date" value="<?= h($upload['activity_date']) ?>"></label>
                  </div>
                  <div class="checkbox-row">
                    <label class="checkbox"><input type="checkbox" name="satellite" value="1"<?= $hasSatellite ? ' checked' : '' ?>>Contato via satélite</label>
                    <label class="checkbox"><input type="checkbox" name="wff" value="1"<?= $hasWff ? ' checked' : '' ?>>Ativação WWFF</label>
                    <label class="checkbox"><input type="checkbox" name="pota" value="1"<?= $hasPota ? ' checked' : '' ?>>Ativação POTA</label>
                  </div>
                  <p class="small">Marque POTA apenas quando os contatos deste ADIF participarem da ativação POTA.</p>
                  <label class="field">Unidades de conservação<textarea name="activations" placeholder="Uma por linha: PR-0001 | Nome da unidade&#10;Se não houver, deixe em branco."><?= h($activationText) ?></textarea></label>
                  <label class="field">Endossos adicionais<textarea name="endorsements" placeholder="Um por linha: CODIGO | Nome do endosso&#10;Ex.: SAT-QO100 | QO-100"><?= h($customText) ?></textarea></label>
                  <label class="field">Observações internas<textarea name="notes" placeholder="Opcional — não aparece no diploma."><?= h($upload['notes']) ?></textarea></label>
                  <div class="form-actions"><button type="submit">Salvar alterações</button></div>
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
                  <p>Isso apagará <strong><?= h($upload['stored_filename']) ?></strong>, seus <?= h((string) $upload['qso_count']) ?> QSOs, ativações e endossos. O ranking e os certificados serão recalculados sem esses contatos. Esta ação não pode ser desfeita.</p>
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
