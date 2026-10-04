(() => {
const form = document.querySelector('#lookup-form');
const input = document.querySelector('#callsign');
const notice = document.querySelector('#notice');
const result = document.querySelector('#result');
let currentRecord = null;
const i18n = window.CERT50_I18N;
const t = (key, values = {}) => i18n?.t(key, values) ?? key;
const localNumber = value => i18n?.number(value) ?? Number(value).toLocaleString('pt-BR');

const cleanCallsign = value => value.toUpperCase().replace(/[^A-Z0-9/]/g, '');
const text = (element, value) => { element.textContent = value; };

const fetchAward = async callsign => {
  const parameters = new URLSearchParams({ callsign, refresh: String(Date.now()) });
  const response = await fetch(`api/award.php?${parameters}`, { cache: 'no-store' });
  const data = await response.json();
  if (!response.ok) throw new Error(t('award.error'));
  return data;
};

const eventNotice = document.querySelector('#event-notice');
if (eventNotice) {
  const closeEventNotice = () => {
    eventNotice.hidden = true;
    document.body.classList.remove('modal-open');
  };
  document.body.classList.add('modal-open');
  document.querySelector('#event-notice-close')?.addEventListener('click', closeEventNotice);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !eventNotice.hidden) closeEventNotice();
  });
  document.querySelector('#event-notice-close')?.focus();
}

form.addEventListener('submit', async event => {
  event.preventDefault();
  const callsign = cleanCallsign(input.value);
  input.value = callsign;
  result.hidden = true;
  if (callsign.length < 3) { notice.textContent = t('award.invalid_callsign'); return; }
  notice.textContent = t('award.loading');
  try {
    const data = await fetchAward(callsign);
    if (!data.found) { notice.textContent = t('award.not_found', { callsign }); return; }
    currentRecord = data;
    render(data);
    notice.textContent = '';
  } catch (error) { notice.textContent = error.message || t('award.error'); }
});

function render(data, variant = 'participation', shouldScroll = true) {
  const isHall = variant === 'hall';
  const certificate = document.querySelector('#certificate');
  certificate.classList.toggle('certificate--hall', isHall);
  certificate.classList.toggle('certificate--participation', !isHall);
  certificate.classList.toggle('certificate--long-callsign', data.callsign.length > 8);
  certificate.classList.toggle('certificate--very-long-callsign', data.callsign.length > 14);
  text(document.querySelector('#result-eyebrow'), t(window.CERT50_PREVIEW ? 'result.preview' : 'result.found'));
  text(document.querySelector('#result-title'), window.CERT50_PREVIEW ? t(isHall ? 'award.preview_hall' : 'award.preview_participation') : t('award.result_title', { callsign: data.callsign }));
  text(document.querySelector('#certificate-variant-title'), t(isHall ? 'certificate.hall' : 'certificate.participation'));
  text(document.querySelector('#certificate-feature-title'), t(isHall ? 'certificate.feature.hall' : 'certificate.feature.participation'));
  text(document.querySelector('#certificate-feature-description'), t(isHall ? 'certificate.feature.hall_text' : 'certificate.feature.participation_text'));
  text(document.querySelector('#certificate-callsign'), data.callsign);
  text(document.querySelector('#recognition-callsign'), data.callsign);
  text(document.querySelector('#metric-qsos'), localNumber(data.contacts));
  text(document.querySelector('#metric-bands'), String((data.bands || []).length));
  text(document.querySelector('#metric-modes'), String((data.modes || []).length));
  text(document.querySelector('#activity-dates'), t('certificate.period'));
  const endorsements = document.querySelector('#endorsements');
  const earnedEndorsements = data.endorsements.filter(item => item.code !== 'ARDX50');
  const endorsementLabels = { WFF: 'award.endorsement_wff', POTA: 'award.endorsement_pota', SAT: 'award.endorsement_sat', CW: 'award.endorsement_cw' };
  endorsements.replaceChildren(...earnedEndorsements.map(item => { const chip = document.createElement('span'); chip.textContent = endorsementLabels[item.code] ? t(endorsementLabels[item.code]) : item.label; return chip; }));
  if (!earnedEndorsements.length) text(endorsements, t('award.no_endorsements'));
  const badgeDefinitions = [
    { code: 'WFF', label: t('award.badge_wff') },
    { code: 'POTA', label: t('award.badge_pota') },
    { code: 'SAT', label: t('award.badge_sat') },
    { code: 'CW', label: t('award.badge_cw') },
  ];
  const earnedCodes = new Set(data.achievement_codes || earnedEndorsements.map(item => item.code));
  const badges = document.querySelector('#achievement-badges');
  badges.replaceChildren(...badgeDefinitions.filter(badge => earnedCodes.has(badge.code)).map(badge => {
    const element = document.createElement('div');
    element.className = 'cert-badge';
    element.setAttribute('aria-label', t('award.achievement_aria', { label: badge.label }));
    const emblem = document.createElement('img');
    emblem.className = 'cert-badge-emblem';
    emblem.src = 'assets/brand-mark.svg';
    emblem.alt = '';
    const label = document.createElement('strong'); label.textContent = badge.label;
    element.append(emblem, label);
    return element;
  }));
  if (!badges.childElementCount) { const empty = document.createElement('p'); empty.className = 'cert-no-badges'; empty.textContent = t('award.no_achievements'); badges.append(empty); }
  const parks = document.querySelector('#parks');
  const parkNames = data.activations.slice(0, 3).map(item => `${item.reference}${item.name ? ` — ${item.name}` : ''}`);
  text(parks, data.activations.length ? `${parkNames.join(' · ')}${data.activations.length > 3 ? ` ${t('award.more_references', { count: data.activations.length - 3 })}` : ''}` : t('award.no_references'));
  result.hidden = false;
  if (shouldScroll) result.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

if (window.CERT50_PREVIEW) {
  currentRecord = window.CERT50_PREVIEW.data;
  render(currentRecord, window.CERT50_PREVIEW.variant);
}

document.addEventListener('cert50:languagechange', () => {
  if (!currentRecord) return;
  render(currentRecord, document.querySelector('#certificate').classList.contains('certificate--hall') ? 'hall' : 'participation', false);
});

document.addEventListener('visibilitychange', async () => {
  if (document.visibilityState !== 'visible' || !currentRecord || window.CERT50_PREVIEW) return;
  try {
    const updatedRecord = await fetchAward(currentRecord.callsign);
    if (!updatedRecord.found) return;
    currentRecord = updatedRecord;
    render(updatedRecord, 'participation', false);
  } catch (error) {
    // Mantém o diploma já exibido se a atualização silenciosa falhar.
  }
});

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
  ctx.fillStyle = '#eab453'; ctx.font = '700 42px Arial'; ctx.fillText(t('certificate.footer_brand'), 120, 180); ctx.fillStyle = '#fff'; ctx.font = '700 96px Arial'; ctx.fillText(currentRecord.callsign, 120, 340); ctx.fillStyle = '#f6e1ae'; ctx.font = '400 34px Arial'; ctx.fillText(t('award.sticker_participant'), 120, 410); ctx.fillStyle = '#eab453'; ctx.font = '700 72px Arial'; ctx.fillText(`${localNumber(currentRecord.contacts)} QSOs`, 120, 600); ctx.fillStyle = '#dbece0'; ctx.font = '400 30px Arial'; ctx.fillText(t('award.sticker_activations', { count: localNumber(currentRecord.activations.length) }), 120, 665); ctx.fillText('araucariadx.com', 120, 760);
  const link = document.createElement('a'); link.download = `araucaria-dx-50-anos-${currentRecord.callsign.toLowerCase().replaceAll('/', '-')}.png`; link.href = canvas.toDataURL('image/png'); link.click();
});
})();
