import { getDb } from "@/db";
import { activations, endorsements, qsos, uploads } from "@/db/schema";
import { eq, inArray } from "drizzle-orm";
import { NextRequest, NextResponse } from "next/server";

export const dynamic = "force-dynamic";

export async function GET(request: NextRequest) {
  const callsign = (request.nextUrl.searchParams.get("callsign") ?? "").toUpperCase().replace(/[^A-Z0-9/]/g, "");
  if (callsign.length < 3) return NextResponse.json({ error: "Indicativo inválido." }, { status: 400 });
  try {
    const db = getDb();
    const rows = await db.select({ uploadId: qsos.uploadId, qsoDate: qsos.qsoDate, band: qsos.band, satellite: uploads.satellite }).from(qsos).innerJoin(uploads, eq(qsos.uploadId, uploads.id)).where(eq(qsos.callsign, callsign));
    if (!rows.length) return NextResponse.json({ found: false, callsign });
    const uploadIds = [...new Set(rows.map((row) => row.uploadId))];
    const [parkRows, endorsementRows] = await Promise.all([
      db.select({ reference: activations.reference, name: activations.name }).from(activations).where(inArray(activations.uploadId, uploadIds)),
      db.select({ code: endorsements.code, label: endorsements.label }).from(endorsements).where(inArray(endorsements.uploadId, uploadIds)),
    ]);
    const uniqueParks = [...new Map(parkRows.map((park) => [`${park.reference}|${park.name ?? ""}`, { reference: park.reference, name: park.name ?? "" }])).values()];
    const uniqueEndorsements = [...new Map(endorsementRows.map((item) => [item.code, item])).values()];
    return NextResponse.json({
      found: true, callsign, contacts: rows.length,
      bands: [...new Set(rows.map((row) => row.band).filter((value): value is string => Boolean(value)))].sort(),
      dates: [...new Set(rows.map((row) => row.qsoDate).filter((value): value is string => Boolean(value)))].sort(),
      activations: uniqueParks,
      endorsements: [{ code: "ARDX50", label: "Araucária DX · 50 anos" }, ...uniqueEndorsements],
      satellite: rows.some((row) => row.satellite),
    });
  } catch (error) {
    console.error("Award lookup failed", error);
    return NextResponse.json({ error: "Os dados estão temporariamente indisponíveis." }, { status: 503 });
  }
}
