const form = document.querySelector('#lookup-form');
const input = document.querySelector('#callsign');
const notice = document.querySelector('#notice');
const result = document.querySelector('#result');
let currentRecord = null;

const cleanCallsign = value => value.toUpperCase().replace(/[^A-Z0-9/]/g, '');
const dateBR = value => /^\d{8}$/.test(value) ? `${value.slice(6, 8)}/${value.slice(4, 6)}/${value.slice(0, 4)}` : value;
const text = (element, value) => { element.textContent = value; };

form.addEventListener('submit', async event => {
  event.preventDefault();
  const callsign = cleanCallsign(input.value);
  input.value = callsign;
  result.hidden = true;
  if (callsign.length < 3) { notice.textContent = 'Informe um indicativo válido.'; return; }
  notice.textContent = 'Consultando os logs…';
  try {
    const response = await fetch(`api/award.php?callsign=${encodeURIComponent(callsign)}`, { cache: 'no-store' });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Não foi possível consultar os logs.');
    if (!data.found) { notice.textContent = `Ainda não localizamos contatos para ${callsign}. O log da operação pode estar aguardando importação.`; return; }
    currentRecord = data;
    render(data);
    notice.textContent = '';
  } catch (error) { notice.textContent = error.message || 'Falha na consulta.'; }
});

function render(data, variant = 'participation') {
  const isHall = variant === 'hall';
  const certificate = document.querySelector('#certificate');
  certificate.classList.toggle('certificate--hall', isHall);
  certificate.classList.toggle('certificate--participation', !isHall);
  certificate.classList.toggle('certificate--long-callsign', data.callsign.length > 8);
  certificate.classList.toggle('certificate--very-long-callsign', data.callsign.length > 14);
  text(document.querySelector('#result-title'), window.CERT50_PREVIEW ? `Prévia: ${isHall ? 'Hall of Fame' : 'Participação'}` : `Diploma de ${data.callsign}`);
  text(document.querySelector('#certificate-variant-title'), isHall ? 'Homenagem · Hall of Fame' : 'Certificado de Participação');
  text(document.querySelector('#certificate-feature-title'), isHall ? 'HOMENAGEM — HALL OF FAME GADX' : 'Uma história feita de contatos');
  text(document.querySelector('#certificate-feature-description'), isHall ? 'Uma homenagem aos que construíram esta história.' : 'Cada QSO também faz parte destes 50 anos.');
  text(document.querySelector('#certificate-callsign'), data.callsign);
  text(document.querySelector('#metric-qsos'), Number(data.contacts).toLocaleString('pt-BR'));
  text(document.querySelector('#metric-bands'), String((data.bands || []).length));
  text(document.querySelector('#metric-modes'), String((data.modes || []).length));
  const dates = data.dates.map(dateBR);
  text(document.querySelector('#activity-dates'), `Operações registradas: ${dates.slice(0, 2).join(' · ') || '—'}${dates.length > 2 ? ` · +${dates.length - 2} datas` : ''}`);
  const endorsements = document.querySelector('#endorsements');
  const earnedEndorsements = data.endorsements.filter(item => item.code !== 'ARDX50');
  endorsements.replaceChildren(...earnedEndorsements.map(item => { const chip = document.createElement('span'); chip.textContent = item.label; return chip; }));
  if (!earnedEndorsements.length) text(endorsements, 'Selo comemorativo Araucária DX · 50 anos');
  const badgeDefinitions = [
    { code: 'WFF', symbol: '♣', label: 'WWFF' },
    { code: 'POTA', symbol: '⌖', label: 'POTA' },
    { code: 'SAT', symbol: '✦', label: 'SATÉLITE' },
    { code: 'CW', symbol: '· −', label: 'CW' },
  ];
  const earnedCodes = new Set(data.achievement_codes || earnedEndorsements.map(item => item.code));
  const badges = document.querySelector('#achievement-badges');
  badges.replaceChildren(...badgeDefinitions.filter(badge => earnedCodes.has(badge.code)).map(badge => {
    const element = document.createElement('div');
    element.className = 'cert-badge';
    element.setAttribute('aria-label', `Conquista ${badge.label}`);
    const emblem = document.createElement('img');
    emblem.className = 'cert-badge-emblem';
    emblem.src = 'assets/brand-mark.svg';
    emblem.alt = '';
    const label = document.createElement('strong'); label.textContent = badge.label;
    element.append(emblem, label);
    return element;
  }));
  if (!badges.childElementCount) { const empty = document.createElement('p'); empty.className = 'cert-no-badges'; empty.textContent = 'Novas conquistas poderão aparecer aqui.'; badges.append(empty); }
  const parks = document.querySelector('#parks');
  const parkNames = data.activations.slice(0, 3).map(item => `${item.reference}${item.name ? ` — ${item.name}` : ''}`);
  text(parks, data.activations.length ? `${parkNames.join(' · ')}${data.activations.length > 3 ? ` · e mais ${data.activations.length - 3}` : ''}` : 'Nenhuma referência vinculada aos contatos importados.');
  result.hidden = false;
  result.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

if (window.CERT50_PREVIEW) {
  currentRecord = window.CERT50_PREVIEW.data;
  render(currentRecord, window.CERT50_PREVIEW.variant);
}

document.querySelector('#print-certificate')?.addEventListener('click', async () => {
  await document.fonts.ready;
  window.print();
});
document.querySelector('#download-card')?.addEventListener('click', () => {
  if (!currentRecord) return;
  const canvas = document.createElement('canvas'); canvas.width = 1600; canvas.height = 900;
  const ctx = canvas.getContext('2d'); if (!ctx) return;
  const gradient = ctx.createLinearGradient(0, 0, 1600, 900); gradient.addColorStop(0, '#071d1a'); gradient.addColorStop(1, '#123c31');
  ctx.fillStyle = gradient; ctx.fillRect(0, 0, 1600, 900); ctx.strokeStyle = '#eab453'; ctx.lineWidth = 4; ctx.strokeRect(52, 52, 1496, 796); ctx.strokeStyle = 'rgba(234,180,83,.42)'; ctx.lineWidth = 1; ctx.strokeRect(74, 74, 1452, 752);
  ctx.fillStyle = '#eab453'; ctx.font = '700 42px Arial'; ctx.fillText('ARAUCÁRIA DX · 50 ANOS', 120, 180); ctx.fillStyle = '#fff'; ctx.font = '700 96px Arial'; ctx.fillText(currentRecord.callsign, 120, 340); ctx.fillStyle = '#f6e1ae'; ctx.font = '400 34px Arial'; ctx.fillText('Participante do diploma comemorativo', 120, 410); ctx.fillStyle = '#eab453'; ctx.font = '700 72px Arial'; ctx.fillText(`${currentRecord.contacts} QSOs`, 120, 600); ctx.fillStyle = '#dbece0'; ctx.font = '400 30px Arial'; ctx.fillText(`${currentRecord.activations.length} unidades de conservação ativadas`, 120, 665); ctx.fillText('araucariadx.com', 120, 760);
  const link = document.createElement('a'); link.download = `araucaria-dx-50-anos-${currentRecord.callsign.toLowerCase().replaceAll('/', '-')}.png`; link.href = canvas.toDataURL('image/png'); link.click();
});
