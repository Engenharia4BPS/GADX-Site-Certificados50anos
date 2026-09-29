export type ParsedQso = {
  callsign: string;
  qsoDate: string | null;
  qsoTime: string | null;
  band: string | null;
  mode: string | null;
};

function readField(record: string, field: string): string | null {
  const marker = new RegExp(`<${field}(?::(\\d+)(?::[^>]*)?)?\\s*>`, "i");
  const match = marker.exec(record);
  if (!match || match.index === undefined) return null;
  const start = match.index + match[0].length;
  const length = Number(match[1]);
  const value = Number.isFinite(length) && length > 0 ? record.slice(start, start + length) : record.slice(start).split("<", 1)[0];
  return value.trim() || null;
}

export function parseAdif(input: string): ParsedQso[] {
  const qsos: ParsedQso[] = [];
  for (const record of input.split(/<eor\s*>/i)) {
    const callsign = readField(record, "CALL")?.toUpperCase().replace(/[^A-Z0-9/]/g, "");
    if (!callsign || callsign.length < 3) continue;
    qsos.push({ callsign, qsoDate: readField(record, "QSO_DATE"), qsoTime: readField(record, "TIME_ON"), band: readField(record, "BAND")?.toUpperCase() ?? null, mode: readField(record, "MODE")?.toUpperCase() ?? null });
  }
  return qsos;
}
