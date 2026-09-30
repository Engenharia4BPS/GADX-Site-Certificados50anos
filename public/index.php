<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once CERT50_PRIVATE_ROOT . '/app/certificate.php';
$certificateBackground = certificate_background();
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
    <a class="brand" href="./" aria-label="Araucária DX — 50 anos"><span class="brand-mark">⌁</span><span><strong>ARAUCÁRIA DX</strong><small>50 ANOS</small></span></a>
    <a class="admin-link" href="admin/">Área da organização</a>
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
        <article><b>03</b><h2>Endossos</h2><p>Selos por satélite, WFF e operações especiais.</p></article>
      </aside>
    </section>

    <section id="result" class="result" hidden>
      <div class="result-head print-hidden"><div><p class="eyebrow">RESULTADO ENCONTRADO</p><h2 id="result-title">Diploma</h2></div><div class="actions"><button id="print-certificate" class="secondary" type="button">Imprimir / salvar PDF</button><button id="download-card" type="button">Baixar figurinha</button></div></div>
      <article id="certificate" class="certificate<?= $certificateBackground ? ' certificate--with-background' : '' ?>">
        <div class="certificate-inner">
          <div class="certificate-top"><div><p>ARAUCÁRIA DX · 50 ANOS</p><h2>Certificado comemorativo</h2></div><span class="seal">50<br>ANOS</span></div>
          <div class="certificate-name"><p>A Araucária DX reconhece a participação de</p><strong id="certificate-callsign"></strong><p>nas atividades comemorativas do cinquentenário, registrando contatos, ativações e conquistas que fortalecem o radioamadorismo.</p></div>
          <div class="metrics"><div><span>QSOs registrados</span><strong id="metric-qsos">0</strong></div><div><span>Unidades ativadas</span><strong id="metric-parks">0</strong></div><div><span>Bandas</span><strong id="metric-bands">—</strong></div></div>
          <div class="certificate-details"><div><p>ENDOSSOS CONQUISTADOS</p><div id="endorsements" class="chips"></div></div><div><p>UNIDADES DE CONSERVAÇÃO</p><div id="parks" class="park-list"></div></div></div>
          <div class="certificate-bottom"><span id="activity-dates">Operações registradas: —</span><strong>Araucária DX</strong></div>
        </div>
      </article>
    </section>
  </main>
  <footer>Araucária DX · Diploma comemorativo de 50 anos</footer>
  <script src="assets/award.js" defer></script>
</body>
</html>
