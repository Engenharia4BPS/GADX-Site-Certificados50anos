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
  <?php if ($preview === null): ?>
    <div id="event-notice" class="event-notice" role="dialog" aria-modal="true" aria-labelledby="event-notice-title" aria-describedby="event-notice-message">
      <div class="event-notice__dialog">
        <button id="event-notice-close" class="event-notice__close" type="button" aria-label="Fechar aviso" data-i18n-aria-label="modal.close">×</button>
        <p class="eyebrow" data-i18n="modal.eyebrow">AVISO AOS PARTICIPANTES</p>
        <h2 id="event-notice-title" data-i18n="modal.title">Atualização dos logs e ranking</h2>
        <div id="event-notice-message">
          <p data-i18n="modal.logs">Os logs serão atualizados diariamente.</p>
          <p data-i18n="modal.result">O resultado final será apresentado em 01 de novembro, após o encerramento do evento comemorativo dos 50 anos.</p>
          <p data-i18n="modal.dynamic">Até lá, o log e o ranking permanecem dinâmicos e podem receber atualizações.</p>
        </div>
        <p class="event-notice__signature" data-i18n="modal.signature">Forte 73 de PY5EG.</p>
      </div>
    </div>
    <noscript><style>#event-notice { display: none; }</style></noscript>
  <?php endif; ?>
  <header class="site-header">
    <a class="brand" href="./" aria-label="Araucária DX — 50 anos" data-i18n-aria-label="brand.label"><img class="brand-mark" src="assets/brand-mark.svg" alt=""><span><strong>ARAUCÁRIA DX</strong><small data-i18n="brand.anniversary">50 ANOS</small></span></a>
    <nav class="header-nav" aria-label="Navegação" data-i18n-aria-label="nav.label"><a href="#ranking" data-i18n="nav.ranking">Ranking</a><a class="admin-link" href="admin/" data-i18n="nav.admin">Área da organização</a><div class="language-switcher" role="group" aria-label="Idioma" data-i18n-aria-label="language.label"><button class="language-button" type="button" data-language="pt-BR" aria-pressed="true">PT</button><button class="language-button" type="button" data-language="en-US" aria-pressed="false">EN</button></div></nav>
  </header>

  <main class="shell">
    <section class="intro">
      <div>
        <p class="eyebrow" data-i18n="intro.eyebrow">DIPLOMA COMEMORATIVO</p>
        <h1 data-i18n="intro.title">Seu sinal fez parte desta história.</h1>
        <p class="lead" data-i18n="intro.lead">Consulte seu indicativo para emitir o diploma dos 50 anos da Araucária DX, com os QSOs, ativações e endossos registrados pela organização.</p>
        <form id="lookup-form" class="lookup-form" novalidate>
          <label class="sr-only" for="callsign" data-i18n="lookup.label">Seu indicativo</label>
          <input id="callsign" name="callsign" autocomplete="off" autocapitalize="characters" placeholder="Ex.: PY5XT" data-i18n-placeholder="lookup.placeholder" maxlength="32" required>
          <button type="submit" data-i18n="lookup.button">Consultar diploma</button>
        </form>
        <p id="notice" class="notice" aria-live="polite"></p>
      </div>
      <aside class="feature-list" aria-label="O que aparece no diploma" data-i18n-aria-label="features.label">
        <article><b>01</b><h2 data-i18n="features.one.title">Diploma único</h2><p data-i18n="features.one.text">Seu indicativo, pronto para imprimir ou salvar em PDF.</p></article>
        <article><b>02</b><h2 data-i18n="features.two.title">Ativações</h2><p data-i18n="features.two.text">Unidades de conservação e referências trabalhadas.</p></article>
        <article><b>03</b><h2 data-i18n="features.three.title">Conquistas</h2><p data-i18n="features.three.text">Selos por WFF, POTA, satélite e CW, além dos endossos da operação.</p></article>
      </aside>
    </section>

    <section id="ranking" class="ranking-section print-hidden" aria-labelledby="ranking-title">
      <div class="ranking-heading">
        <div><p class="eyebrow" data-i18n="ranking.eyebrow">LOGS DA ZW50B</p><h2 id="ranking-title" data-i18n="ranking.title">Ranking de participantes</h2><p data-i18n="ranking.description">Ordenado por bandas distintas; em caso de empate, pelo total de QSOs. SAT é uma categoria adicional e não aumenta o total de bandas.</p></div>
        <form id="ranking-search" class="ranking-search" role="search">
          <label for="ranking-callsign" data-i18n="ranking.search_label">Buscar indicativo</label>
          <div><input id="ranking-callsign" name="q" maxlength="32" autocomplete="off" autocapitalize="characters" placeholder="Ex.: PY5XT" data-i18n-placeholder="lookup.placeholder"><button type="submit" data-i18n="ranking.search_button">Buscar</button></div>
        </form>
      </div>
      <p id="ranking-status" class="ranking-status" aria-live="polite" data-i18n="ranking.loading">Carregando participantes…</p>
      <p class="ranking-scroll-hint" data-i18n="ranking.scroll_hint">Deslize a tabela para ver BANDS, TOTAL e ACHIEVEMENTS →</p>
      <div class="ranking-table-scroll">
        <table class="ranking-table">
          <caption class="sr-only" data-i18n="ranking.caption">Participantes, bandas trabalhadas, contatos totais e conquistas</caption>
          <thead><tr><th scope="col" data-i18n="ranking.header_call">CALL</th><th scope="col" data-i18n="ranking.header_bands">BANDS</th><th scope="col" data-i18n="ranking.header_total">TOTAL</th><th scope="col" data-i18n="ranking.header_achievements">ACHIEVEMENTS</th></tr></thead>
          <tbody id="ranking-rows"></tbody>
        </table>
      </div>
      <div class="ranking-pagination"><button id="ranking-prev" class="secondary" type="button" disabled data-i18n="ranking.previous">Anterior</button><span id="ranking-page">Página 1</span><button id="ranking-next" class="secondary" type="button" disabled data-i18n="ranking.next">Próxima</button></div>
      <noscript><p data-i18n="ranking.noscript">Ative o JavaScript para consultar o ranking.</p></noscript>
    </section>

    <section id="result" class="result"<?= $preview === null ? ' hidden' : '' ?>>
      <div class="result-head print-hidden"><div><p id="result-eyebrow" class="eyebrow" data-i18n="<?= $preview ? 'result.preview' : 'result.found' ?>"><?= $preview ? 'PRÉVIA VISUAL · DADOS ILUSTRATIVOS' : 'RESULTADO ENCONTRADO' ?></p><h2 id="result-title">Certificado</h2></div><div class="actions"><?php if (!$preview): ?><button id="print-certificate" class="secondary" type="button" data-i18n="result.print">Imprimir / salvar PDF</button><button id="download-card" type="button" data-i18n="result.download">Baixar figurinha</button><?php else: ?><a class="admin-link" href="admin/" data-i18n="result.back">Voltar ao painel</a><?php endif; ?></div></div>
      <article id="certificate" class="certificate certificate--v2 certificate--participation<?= $certificateBackground ? ' certificate--with-background' : '' ?>">
        <div class="cert-frame">
          <span class="cert-corner cert-corner--tl" aria-hidden="true"></span><span class="cert-corner cert-corner--tr" aria-hidden="true"></span><span class="cert-corner cert-corner--bl" aria-hidden="true"></span><span class="cert-corner cert-corner--br" aria-hidden="true"></span>
          <div class="cert-layout">
            <div class="cert-main">
              <div class="cert-brand">
                <?php if ($certificateLogo): ?>
                  <img class="cert-brand-logo" src="assets/certificate/<?= rawurlencode($certificateLogo['filename']) ?>?v=<?= (int) filemtime($certificateLogo['path']) ?>" alt="Logo dos 50 anos da Araucária DX" data-i18n-alt="certificate.logo_alt">
                <?php else: ?>
                  <span class="cert-brand-medal">50<small data-i18n="certificate.anniversary">ANOS</small></span>
                <?php endif; ?>
                <div><p data-i18n="certificate.celebrating">CELEBRANDO UMA HISTÓRIA NO RADIOAMADORISMO</p><h2>ARAUCÁRIA DX</h2><strong data-i18n="certificate.anniversary_long">50 ANOS</strong></div>
              </div>
              <div class="cert-heading"><span id="certificate-variant-title" data-i18n="certificate.participation">Certificado de Participação</span></div>
              <div class="cert-recipient"><p data-i18n="certificate.granted">Concedido a</p><strong id="certificate-callsign"></strong></div>
              <div class="cert-metrics"><div><strong id="metric-qsos">0</strong><span data-i18n="certificate.contacts">contatos</span></div><div><strong id="metric-bands">0</strong><span data-i18n="certificate.bands">bandas trabalhadas</span></div><div><strong id="metric-modes">0</strong><span data-i18n="certificate.modes">modos trabalhados</span></div></div>
              <p class="cert-recognition"><span data-i18n="certificate.recognition.before">Este certificado é concedido a</span> <strong id="recognition-callsign"></strong>, <span data-i18n="certificate.recognition.after">em reconhecimento à realização de contato com uma das estações comemorativas dos</span> <strong data-i18n="certificate.recognition.anniversary">50 anos do Araucária DX Group</strong>.<br><span data-i18n="certificate.recognition.participation">Sua participação integra esta celebração de amizade, radioamadorismo e história.</span><br><strong data-i18n="certificate.recognition.thanks">Agradecemos por fazer parte desta homenagem.</strong></p>
              <div class="cert-achievements"><p class="cert-small-title" data-i18n="certificate.achievements">CONQUISTAS ESPECIAIS</p><div id="achievement-badges" class="cert-badges"></div></div>
            </div>
            <aside class="cert-feature">
              <div class="cert-feature-art<?= $hallPhoto ? ' cert-feature-art--photo' : '' ?>">
                <?php if ($certificateLogo): ?>
                  <img class="cert-feature-logo" src="assets/certificate/<?= rawurlencode($certificateLogo['filename']) ?>?v=<?= (int) filemtime($certificateLogo['path']) ?>" alt="Logo dos 50 anos da Araucária DX" data-i18n-alt="certificate.logo_alt">
                <?php else: ?>
                  <div class="cert-commemorative-seal" aria-hidden="true"><span>ARAUCÁRIA DX</span><strong>50</strong><small data-i18n="certificate.anniversary">ANOS</small></div>
                <?php endif; ?>
                <?php if ($hallPhoto): ?><img class="cert-hall-photo" src="assets/certificate/<?= rawurlencode($hallPhoto['filename']) ?>?v=<?= (int) filemtime($hallPhoto['path']) ?>" alt="Integrantes homenageados no Hall of Fame" data-i18n-alt="certificate.hall_photo_alt"><?php endif; ?>
              </div>
              <div class="cert-feature-caption"><strong id="certificate-feature-title" data-i18n="certificate.feature.participation">Uma história feita de contatos</strong><p id="certificate-feature-description" data-i18n="certificate.feature.participation_text">Cada QSO também faz parte destes 50 anos.</p></div>
            </aside>
          </div>
          <div class="cert-extras"><div><p class="cert-small-title" data-i18n="certificate.endorsements">ENDOSSOS CONQUISTADOS</p><div id="endorsements" class="cert-endorsements"></div></div><div><p class="cert-small-title" data-i18n="certificate.references">REFERÊNCIAS TRABALHADAS</p><div id="parks" class="cert-parks"></div></div></div>
            <div class="cert-footer"><span id="activity-dates" data-i18n="certificate.period">Operações registradas entre 01/outubro/2026 e 31/outubro/2026</span><strong data-i18n="certificate.footer_brand">Araucária DX · 50 anos</strong></div>
        </div>
      </article>
    </section>
  </main>
  <footer data-i18n="footer">Araucária DX · Diploma comemorativo de 50 anos</footer>
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
  <script src="assets/i18n.js" defer></script>
  <script src="assets/award.js" defer></script>
  <script src="assets/ranking.js" defer></script>
</body>
</html>
