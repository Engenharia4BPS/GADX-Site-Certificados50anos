<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/certificate.php';
$certificateBackground = certificate_background();
$hallPhoto = certificate_hall_photo();
$certificateLogo = certificate_logo();
$previewValue = $_GET['preview'] ?? null;
$preview = is_string($previewValue) && in_array($previewValue, ['participation', 'hall'], true) ? $previewValue : null;
if ($preview !== null) {
    require_once CERT50_PRIVATE_ROOT . '/app/auth.php';
    if (!current_admin()) {
        http_response_code(403);
        exit('A prévia visual é reservada aos administradores.');
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Diploma comemorativo dos 50 anos da Araucária DX.">
  <title>Araucária DX — 50 anos</title>
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/site.css">
  <?php if ($certificateBackground): ?>
    <style>.certificate--with-background { background-image: url("assets/certificate/<?= rawurlencode($certificateBackground['filename']) ?>?v=<?= (int) filemtime($certificateBackground['path']) ?>"); background-position: center; background-repeat: no-repeat; background-size: cover; }</style>
  <?php endif; ?>
</head>
<body>
  <header class="site-header">
    <a class="brand" href="./" aria-label="Araucária DX — 50 anos"><img class="brand-mark" src="assets/brand-mark.svg" alt=""><span><strong>ARAUCÁRIA DX</strong><small>50 ANOS</small></span></a>
    <nav class="header-nav" aria-label="Navegação"><a href="#ranking">Ranking</a><a class="admin-link" href="admin/">Área da organização</a></nav>
  </header>

  <main class="shell">
    <section class="intro">
      <div>
        <p class="eyebrow">DIPLOMA COMEMORATIVO</p>
        <h1>Seu sinal fez parte desta história.</h1>
        <p class="lead">Consulte seu indicativo para emitir o diploma dos 50 anos da Araucária DX, com os QSOs, ativações e endossos registrados pela organização.</p>
        <form id="lookup-form" class="lookup-form" novalidate>
          <label class="sr-only" for="callsign">Seu indicativo</label>
          <input id="callsign" name="callsign" autocomplete="off" autocapitalize="characters" placeholder="Ex.: PY5XT" maxlength="32" required>
          <button type="submit">Consultar diploma</button>
        </form>
        <p id="notice" class="notice" aria-live="polite"></p>
      </div>
      <aside class="feature-list" aria-label="O que aparece no diploma">
        <article><b>01</b><h2>Diploma único</h2><p>Seu indicativo, pronto para imprimir ou salvar em PDF.</p></article>
        <article><b>02</b><h2>Ativações</h2><p>Unidades de conservação e referências trabalhadas.</p></article>
        <article><b>03</b><h2>Conquistas</h2><p>Selos por WFF, POTA, satélite e CW, além dos endossos da operação.</p></article>
      </aside>
    </section>

    <section id="ranking" class="ranking-section print-hidden" aria-labelledby="ranking-title">
      <div class="ranking-heading">
        <div><p class="eyebrow">LOGS DA ZW50B</p><h2 id="ranking-title">Ranking de participantes</h2><p>Ordenado por bandas distintas; em caso de empate, pelo total de QSOs. SAT é uma categoria adicional e não aumenta o total de bandas.</p></div>
        <form id="ranking-search" class="ranking-search" role="search">
          <label for="ranking-callsign">Buscar indicativo</label>
          <div><input id="ranking-callsign" name="q" maxlength="32" autocomplete="off" autocapitalize="characters" placeholder="Ex.: PY5XT"><button type="submit">Buscar</button></div>
        </form>
      </div>
      <p id="ranking-status" class="ranking-status" aria-live="polite">Carregando participantes…</p>
      <p class="ranking-scroll-hint">Deslize a tabela para ver BANDS, TOTAL e ACHIEVEMENTS →</p>
      <div class="ranking-table-scroll">
        <table class="ranking-table">
          <caption class="sr-only">Participantes, bandas trabalhadas, contatos totais e conquistas</caption>
          <thead><tr><th scope="col">CALL</th><th scope="col">BANDS</th><th scope="col">TOTAL</th><th scope="col">ACHIEVEMENTS</th></tr></thead>
          <tbody id="ranking-rows"></tbody>
        </table>
      </div>
      <div class="ranking-pagination"><button id="ranking-prev" class="secondary" type="button" disabled>Anterior</button><span id="ranking-page">Página 1</span><button id="ranking-next" class="secondary" type="button" disabled>Próxima</button></div>
      <noscript><p>Ative o JavaScript para consultar o ranking.</p></noscript>
    </section>

    <section id="result" class="result"<?= $preview === null ? ' hidden' : '' ?>>
      <div class="result-head print-hidden"><div><p class="eyebrow"><?= $preview ? 'PRÉVIA VISUAL · DADOS ILUSTRATIVOS' : 'RESULTADO ENCONTRADO' ?></p><h2 id="result-title">Certificado</h2></div><div class="actions"><?php if (!$preview): ?><button id="print-certificate" class="secondary" type="button">Imprimir / salvar PDF</button><button id="download-card" type="button">Baixar figurinha</button><?php else: ?><a class="admin-link" href="admin/">Voltar ao painel</a><?php endif; ?></div></div>
      <article id="certificate" class="certificate certificate--v2 certificate--participation<?= $certificateBackground ? ' certificate--with-background' : '' ?>">
        <div class="cert-frame">
          <span class="cert-corner cert-corner--tl" aria-hidden="true"></span><span class="cert-corner cert-corner--tr" aria-hidden="true"></span><span class="cert-corner cert-corner--bl" aria-hidden="true"></span><span class="cert-corner cert-corner--br" aria-hidden="true"></span>
          <div class="cert-layout">
            <div class="cert-main">
              <div class="cert-brand">
                <?php if ($certificateLogo): ?>
                  <img class="cert-brand-logo" src="assets/certificate/<?= rawurlencode($certificateLogo['filename']) ?>?v=<?= (int) filemtime($certificateLogo['path']) ?>" alt="Logo dos 50 anos da Araucária DX">
                <?php else: ?>
                  <span class="cert-brand-medal">50<small>ANOS</small></span>
                <?php endif; ?>
                <div><p>CELEBRANDO UMA HISTÓRIA NO RADIOAMADORISMO</p><h2>ARAUCÁRIA DX</h2><strong>50 ANOS</strong></div>
              </div>
              <div class="cert-heading"><span id="certificate-variant-title">Certificado de Participação</span></div>
              <div class="cert-recipient"><p>Concedido a</p><strong id="certificate-callsign"></strong></div>
              <div class="cert-metrics"><div><strong id="metric-qsos">0</strong><span>contatos</span></div><div><strong id="metric-bands">0</strong><span>bandas trabalhadas</span></div><div><strong id="metric-modes">0</strong><span>modos trabalhados</span></div></div>
              <p class="cert-recognition">Este certificado é concedido em reconhecimento por ter trabalhado uma estação comemorativa dos 50 anos.<br><strong>Agradecemos sua participação — Araucária DX Group.</strong></p>
              <div class="cert-achievements"><p class="cert-small-title">CONQUISTAS ESPECIAIS</p><div id="achievement-badges" class="cert-badges"></div></div>
            </div>
            <aside class="cert-feature">
              <div class="cert-feature-art<?= $hallPhoto ? ' cert-feature-art--photo' : '' ?>">
                <?php if ($certificateLogo): ?>
                  <img class="cert-feature-logo" src="assets/certificate/<?= rawurlencode($certificateLogo['filename']) ?>?v=<?= (int) filemtime($certificateLogo['path']) ?>" alt="Logo dos 50 anos da Araucária DX">
                <?php else: ?>
                  <div class="cert-commemorative-seal" aria-hidden="true"><span>ARAUCÁRIA DX</span><strong>50</strong><small>ANOS</small></div>
                <?php endif; ?>
                <?php if ($hallPhoto): ?><img class="cert-hall-photo" src="assets/certificate/<?= rawurlencode($hallPhoto['filename']) ?>?v=<?= (int) filemtime($hallPhoto['path']) ?>" alt="Integrantes homenageados no Hall of Fame"><?php endif; ?>
              </div>
              <div class="cert-feature-caption"><strong id="certificate-feature-title">Uma história feita de contatos</strong><p id="certificate-feature-description">Cada QSO também faz parte destes 50 anos.</p></div>
            </aside>
          </div>
          <div class="cert-extras"><div><p class="cert-small-title">ENDOSSOS CONQUISTADOS</p><div id="endorsements" class="cert-endorsements"></div></div><div><p class="cert-small-title">REFERÊNCIAS TRABALHADAS</p><div id="parks" class="cert-parks"></div></div></div>
          <div class="cert-footer"><span id="activity-dates">Operações registradas: —</span><strong>Araucária DX · 50 anos</strong></div>
        </div>
      </article>
    </section>
  </main>
  <footer>Araucária DX · Diploma comemorativo de 50 anos</footer>
  <?php if ($preview): ?>
    <?php
      $sampleEndorsements = [
          ['code' => 'ARDX50', 'label' => 'Araucária DX · 50 anos'],
          ['code' => 'WFF', 'label' => 'Ativação WWFF'],
          ['code' => 'POTA', 'label' => 'Ativação POTA'],
          ['code' => 'SAT', 'label' => 'Contato via satélite'],
          ['code' => 'CW', 'label' => 'Contato em CW'],
      ];
      $sample = [
          'found' => true,
          'callsign' => 'EXEMPLO',
          'contacts' => $preview === 'hall' ? 126 : 28,
          'bands' => ['10M', '20M', '40M'],
          'modes' => ['CW', 'FT8', 'SSB'],
          'dates' => ['20260929'],
          'activations' => [['reference' => 'BR-0001', 'name' => 'Parque de exemplo'], ['reference' => 'BR-0002', 'name' => 'Reserva de exemplo']],
          'endorsements' => $preview === 'hall' ? $sampleEndorsements : array_slice($sampleEndorsements, 0, 3),
          'achievement_codes' => $preview === 'hall' ? ['WFF', 'POTA', 'SAT', 'CW'] : ['WFF', 'POTA'],
          'endorsement_count' => $preview === 'hall' ? 4 : 2,
      ];
    ?>
    <script>window.CERT50_PREVIEW = <?= json_encode(['variant' => $preview, 'data' => $sample], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
  <?php endif; ?>
  <script src="assets/award.js" defer></script>
  <script src="assets/ranking.js" defer></script>
</body>
</html>
