(() => {
const rankingForm = document.querySelector('#ranking-search');
const rankingInput = document.querySelector('#ranking-callsign');
const rankingStatus = document.querySelector('#ranking-status');
const rankingRows = document.querySelector('#ranking-rows');
const rankingPageLabel = document.querySelector('#ranking-page');
const rankingPrev = document.querySelector('#ranking-prev');
const rankingNext = document.querySelector('#ranking-next');
const physicalBands = ['160M', '80M', '60M', '40M', '30M', '20M', '17M', '15M', '12M', '10M', '6M', '2M'];
const preferredModes = ['FT8', 'FT4', 'CW', 'SSB'];
const preferredStations = ['ZW5B', 'ZW50B', 'PY5GA', 'PQ5TA'];
let rankingPage = 1;
let rankingSearch = '';
let rankingPageCount = 1;
let rankingRequest = null;
let rankingData = null;
const i18n = window.CERT50_I18N;
const t = (key, values = {}) => i18n?.t(key, values) ?? key;
const rankingLocale = () => i18n?.getLocale() ?? 'pt-BR';

const rankingNumber = value => i18n?.number(value) ?? Number(value).toLocaleString(rankingLocale());

function rankedModes(contacts) {
  const normalized = Array.isArray(contacts)
    ? contacts
    : Object.entries(contacts || {}).map(([mode, count]) => ({ station: '', mode, count }));
  return normalized.sort((first, second) => {
    const firstStation = preferredStations.indexOf(first.station);
    const secondStation = preferredStations.indexOf(second.station);
    if (firstStation !== secondStation) {
      if (firstStation === -1) return 1;
      if (secondStation === -1) return -1;
      return firstStation - secondStation;
    }
    const firstIndex = preferredModes.indexOf(first.mode);
    const secondIndex = preferredModes.indexOf(second.mode);
    if (firstIndex !== -1 || secondIndex !== -1) {
      if (firstIndex === -1) return 1;
      if (secondIndex === -1) return -1;
      return firstIndex - secondIndex;
    }
    return first.mode.localeCompare(second.mode, rankingLocale());
  });
}

function bandColumns(record) {
  const extras = Object.keys(record.bands || {})
    .filter(band => !physicalBands.includes(band) && band !== 'SEM BANDA')
    .sort((first, second) => first.localeCompare(second, rankingLocale(), { numeric: true }));
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
  hint.textContent = t('ranking.detail_hint');
  const scroll = document.createElement('div');
  scroll.className = 'band-grid-scroll';
  scroll.tabIndex = 0;
  scroll.setAttribute('aria-label', t('ranking.detail_aria', { callsign: record.callsign }));
  const table = document.createElement('table');
  table.className = 'band-grid';
  const caption = document.createElement('caption');
  caption.className = 'sr-only';
  caption.textContent = t('ranking.detail_caption', { callsign: record.callsign });
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
      for (const contact of entries) {
        const pill = document.createElement('span');
        pill.className = 'mode-pill';
        pill.textContent = contact.station
          ? contact.station + ' · ' + contact.mode
          : contact.mode + ' · ' + rankingNumber(contact.count);
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
  rankingData = data;
  rankingRows.replaceChildren();
  rankingPage = Number(data.page);
  rankingPageCount = Number(data.page_count);
  const total = Number(data.total_participants);
  const searchText = rankingSearch ? t(total === 1 ? 'ranking.search_one' : 'ranking.search_many', { callsign: rankingSearch }) : '';
  rankingStatus.textContent = total
    ? t(total === 1 ? 'ranking.status_one' : 'ranking.status_many', { count: rankingNumber(total), search: searchText })
    : rankingSearch ? t('ranking.empty_search', { callsign: rankingSearch }) : t('ranking.empty');
  rankingPageLabel.textContent = t('ranking.page', { page: rankingPage, pages: rankingPageCount });
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
    callButton.setAttribute('aria-label', t('ranking.callsign_aria', { callsign: record.callsign }));
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
    bandsButton.textContent = t(record.band_count === 1 ? 'ranking.band_one' : 'ranking.band_many', { count: rankingNumber(record.band_count) }) + '  ' + t('ranking.expand');
    bandsButton.setAttribute('aria-controls', detailId);
    bandsButton.setAttribute('aria-expanded', 'false');
    bandsCell.append(bandsButton);
    const totalCell = document.createElement('td');
    totalCell.className = 'ranking-total';
    const totalValue = Number(record.total);
    const totalNumber = document.createElement('strong');
    totalNumber.textContent = rankingNumber(totalValue);
    const totalLabel = document.createElement('span');
    totalLabel.textContent = t(totalValue === 1 ? 'ranking.valid_qso_one' : 'ranking.valid_qso_many');
    totalCell.append(totalNumber, totalLabel);
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
      bandsButton.textContent = t(record.band_count === 1 ? 'ranking.band_one' : 'ranking.band_many', { count: rankingNumber(record.band_count) }) + '  ' + t(detailRow.hidden ? 'ranking.expand' : 'ranking.collapse');
    });
    rankingRows.append(mainRow, detailRow);
  }
}

async function loadRanking(page = 1) {
  if (rankingRequest) rankingRequest.abort();
  const controller = new AbortController();
  rankingRequest = controller;
  rankingStatus.textContent = t('ranking.loading');
  rankingPrev.disabled = true;
  rankingNext.disabled = true;
  try {
    const parameters = new URLSearchParams({ page: String(page) });
    if (rankingSearch) parameters.set('q', rankingSearch);
    const response = await fetch('api/ranking.php?' + parameters, { cache: 'no-store', signal: controller.signal });
    const data = await response.json();
    if (!response.ok) throw new Error(t('ranking.error'));
    renderRanking(data);
  } catch (error) {
    if (error.name === 'AbortError') return;
    rankingRows.replaceChildren();
    rankingStatus.textContent = error.message || t('ranking.error');
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
document.addEventListener('cert50:languagechange', () => {
  if (rankingData) renderRanking(rankingData);
});
loadRanking();
})();
