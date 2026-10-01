const rankingForm = document.querySelector('#ranking-search');
const rankingInput = document.querySelector('#ranking-callsign');
const rankingStatus = document.querySelector('#ranking-status');
const rankingRows = document.querySelector('#ranking-rows');
const rankingPageLabel = document.querySelector('#ranking-page');
const rankingPrev = document.querySelector('#ranking-prev');
const rankingNext = document.querySelector('#ranking-next');
const physicalBands = ['160M', '80M', '60M', '40M', '30M', '20M', '17M', '15M', '12M', '10M', '6M', '2M'];
const preferredModes = ['FT8', 'FT4', 'CW', 'SSB'];
let rankingPage = 1;
let rankingSearch = '';
let rankingPageCount = 1;
let rankingRequest = null;

const rankingNumber = value => Number(value).toLocaleString('pt-BR');

function rankedModes(modes) {
  return Object.entries(modes || {}).sort(([first], [second]) => {
    const firstIndex = preferredModes.indexOf(first);
    const secondIndex = preferredModes.indexOf(second);
    if (firstIndex !== -1 || secondIndex !== -1) {
      if (firstIndex === -1) return 1;
      if (secondIndex === -1) return -1;
      return firstIndex - secondIndex;
    }
    return first.localeCompare(second, 'pt-BR');
  });
}

function bandColumns(record) {
  const extras = Object.keys(record.bands || {})
    .filter(band => !physicalBands.includes(band) && band !== 'SEM BANDA')
    .sort((first, second) => first.localeCompare(second, 'pt-BR', { numeric: true }));
  const columns = [...physicalBands, ...extras];
  if (Object.hasOwn(record.bands || {}, 'SEM BANDA')) columns.push('SEM BANDA');
  columns.push('SAT');
  return columns;
}

function bandDetails(record, id) {
  const detailRow = document.createElement('tr');
  detailRow.className = 'ranking-detail-row';
  detailRow.id = id;
  detailRow.hidden = true;
  const cell = document.createElement('td');
  cell.colSpan = 4;
  const hint = document.createElement('p');
  hint.className = 'band-grid-hint';
  hint.textContent = 'Deslize para ver todas as bandas e a coluna SAT →';
  const scroll = document.createElement('div');
  scroll.className = 'band-grid-scroll';
  scroll.tabIndex = 0;
  scroll.setAttribute('aria-label', 'Bandas e modos de ' + record.callsign + '; role horizontalmente para ver todas');
  const table = document.createElement('table');
  table.className = 'band-grid';
  const caption = document.createElement('caption');
  caption.className = 'sr-only';
  caption.textContent = 'Contatos de ' + record.callsign + ' por banda e modo';
  table.append(caption);
  const head = document.createElement('thead');
  const headRow = document.createElement('tr');
  const body = document.createElement('tbody');
  const bodyRow = document.createElement('tr');
  for (const band of bandColumns(record)) {
    const heading = document.createElement('th');
    heading.scope = 'col';
    heading.textContent = band === 'SEM BANDA' ? 'N/I' : band;
    headRow.append(heading);
    const value = document.createElement('td');
    const modes = band === 'SAT' ? record.sat : record.bands[band];
    const entries = rankedModes(modes);
    if (!entries.length) {
      value.textContent = '—';
      value.className = 'band-grid-empty';
    } else {
      const pills = document.createElement('div');
      pills.className = 'mode-pills';
      for (const [mode, count] of entries) {
        const pill = document.createElement('span');
        pill.className = 'mode-pill';
        pill.textContent = mode + ' · ' + rankingNumber(count);
        pills.append(pill);
      }
      value.append(pills);
    }
    bodyRow.append(value);
  }
  head.append(headRow);
  body.append(bodyRow);
  table.append(head, body);
  scroll.append(table);
  cell.append(hint, scroll);
  detailRow.append(cell);
  return detailRow;
}

function renderRanking(data) {
  rankingRows.replaceChildren();
  rankingPage = Number(data.page);
  rankingPageCount = Number(data.page_count);
  const total = Number(data.total_participants);
  rankingStatus.textContent = total
    ? rankingNumber(total) + ' ' + (total === 1 ? 'participante' : 'participantes') + (rankingSearch ? (total === 1 ? ' encontrado para ' : ' encontrados para ') + rankingSearch : '') + '.'
    : rankingSearch ? 'Nenhum indicativo encontrado para ' + rankingSearch + '.' : 'Nenhum ADIF importado ainda.';
  rankingPageLabel.textContent = 'Página ' + rankingPage + ' de ' + rankingPageCount;
  rankingPrev.disabled = rankingPage <= 1;
  rankingNext.disabled = rankingPage >= rankingPageCount;

  for (const record of data.rows) {
    const mainRow = document.createElement('tr');
    mainRow.className = 'ranking-main-row';
    const callCell = document.createElement('th');
    callCell.scope = 'row';
    const position = document.createElement('span');
    position.className = 'ranking-position';
    position.textContent = '#' + record.rank;
    const callButton = document.createElement('button');
    callButton.className = 'ranking-call';
    callButton.type = 'button';
    callButton.textContent = record.callsign;
    callButton.setAttribute('aria-label', 'Consultar diploma de ' + record.callsign);
    callButton.addEventListener('click', () => {
      document.querySelector('#callsign').value = record.callsign;
      document.querySelector('#lookup-form').requestSubmit();
    });
    callCell.append(position, callButton);
    const bandsCell = document.createElement('td');
    const detailId = 'ranking-detail-' + record.rank;
    const bandsButton = document.createElement('button');
    bandsButton.className = 'ranking-expand';
    bandsButton.type = 'button';
    bandsButton.textContent = record.band_count + ' ' + (record.band_count === 1 ? 'banda' : 'bandas') + '  ▾';
    bandsButton.setAttribute('aria-controls', detailId);
    bandsButton.setAttribute('aria-expanded', 'false');
    bandsCell.append(bandsButton);
    const totalCell = document.createElement('td');
    totalCell.className = 'ranking-total';
    totalCell.textContent = rankingNumber(record.total);
    const achievementCell = document.createElement('td');
    achievementCell.className = 'ranking-achievements';
    const labels = { WFF: 'WWFF', POTA: 'POTA', SAT: 'SAT', CW: 'CW' };
    for (const code of record.achievements) {
      const pill = document.createElement('span');
      pill.className = 'achievement-pill';
      pill.textContent = labels[code] || code;
      achievementCell.append(pill);
    }
    if (!record.achievements.length) achievementCell.textContent = '—';
    mainRow.append(callCell, bandsCell, totalCell, achievementCell);
    const detailRow = bandDetails(record, detailId);
    bandsButton.addEventListener('click', () => {
      detailRow.hidden = !detailRow.hidden;
      bandsButton.setAttribute('aria-expanded', String(!detailRow.hidden));
      bandsButton.textContent = record.band_count + ' ' + (record.band_count === 1 ? 'banda' : 'bandas') + '  ' + (detailRow.hidden ? '▾' : '▴');
    });
    rankingRows.append(mainRow, detailRow);
  }
}

async function loadRanking(page = 1) {
  if (rankingRequest) rankingRequest.abort();
  const controller = new AbortController();
  rankingRequest = controller;
  rankingStatus.textContent = 'Carregando participantes…';
  rankingPrev.disabled = true;
  rankingNext.disabled = true;
  try {
    const parameters = new URLSearchParams({ page: String(page) });
    if (rankingSearch) parameters.set('q', rankingSearch);
    const response = await fetch('api/ranking.php?' + parameters, { cache: 'no-store', signal: controller.signal });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Não foi possível carregar o ranking.');
    renderRanking(data);
  } catch (error) {
    if (error.name === 'AbortError') return;
    rankingRows.replaceChildren();
    rankingStatus.textContent = error.message || 'Não foi possível carregar o ranking.';
    rankingPageLabel.textContent = '—';
  } finally {
    if (rankingRequest === controller) rankingRequest = null;
  }
}

rankingForm.addEventListener('submit', event => {
  event.preventDefault();
  rankingSearch = rankingInput.value.toUpperCase().replace(/[^A-Z0-9/]/g, '').slice(0, 32);
  rankingInput.value = rankingSearch;
  loadRanking(1);
});
rankingPrev.addEventListener('click', () => loadRanking(rankingPage - 1));
rankingNext.addEventListener('click', () => loadRanking(rankingPage + 1));
loadRanking();
