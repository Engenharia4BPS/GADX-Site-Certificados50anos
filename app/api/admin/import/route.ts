import { parseAdif } from "@/lib/adif";
import { getCurrentAdmin } from "@/lib/admin";
import { getDb } from "@/db";
import { activations, endorsements, qsos, uploads } from "@/db/schema";
import { NextRequest, NextResponse } from "next/server";

export const dynamic = "force-dynamic";

type ImportPayload = { label?: string; activityDate?: string; filename?: string; adif?: string; satellite?: boolean; wff?: boolean; notes?: string; activations?: { reference?: string; name?: string }[] };
const chunks = <T,>(items: T[], size: number) => Array.from({ length: Math.ceil(items.length / size) }, (_, index) => items.slice(index * size, index * size + size));

export async function POST(request: NextRequest) {
  const { admin, user } = await getCurrentAdmin();
  if (!admin || !user) return NextResponse.json({ error: "Sem permissão." }, { status: 403 });
  const body = await request.json().catch(() => null) as ImportPayload | null;
  const label = body?.label?.trim() ?? "";
  const rawAdif = body?.adif ?? "";
  if (!label || !rawAdif) return NextResponse.json({ error: "Informe o nome da operação e selecione um ADIF." }, { status: 400 });
  if (rawAdif.length > 12_000_000) return NextResponse.json({ error: "O ADIF é grande demais para esta importação." }, { status: 413 });
  const parsed = parseAdif(rawAdif);
  if (!parsed.length) return NextResponse.json({ error: "Não encontramos registros QSO no arquivo ADIF." }, { status: 422 });
  if (parsed.length > 50_000) return NextResponse.json({ error: "Limite de 50.000 QSOs por arquivo." }, { status: 413 });
  const db = getDb();
  const uploadId = crypto.randomUUID();
  await db.insert(uploads).values({ id: uploadId, label, activityDate: body?.activityDate || null, sourceFilename: body?.filename?.slice(0, 180) || "log.adi", satellite: Boolean(body?.satellite), notes: body?.notes?.trim().slice(0, 2000) || null, createdAt: new Date(), createdBy: user.userId });
  for (const batch of chunks(parsed, 100)) {
    await db.insert(qsos).values(batch.map((qso) => ({ id: crypto.randomUUID(), uploadId, ...qso })));
  }
  const parkRows = (body?.activations ?? []).map((park) => ({ reference: park.reference?.trim().toUpperCase() ?? "", name: park.name?.trim() || null })).filter((park) => park.reference);
  if (parkRows.length) await db.insert(activations).values(parkRows.map((park) => ({ id: crypto.randomUUID(), uploadId, ...park })));
  const endorsementRows = [
    ...(body?.wff ? [{ code: "WFF", label: "Ativação WFF" }] : []),
    ...(body?.satellite ? [{ code: "SAT", label: "Contato via satélite" }] : []),
  ];
  if (endorsementRows.length) await db.insert(endorsements).values(endorsementRows.map((item) => ({ id: crypto.randomUUID(), uploadId, ...item })));
  return NextResponse.json({ ok: true, imported: parsed.length, callsigns: new Set(parsed.map((qso) => qso.callsign)).size });
}
