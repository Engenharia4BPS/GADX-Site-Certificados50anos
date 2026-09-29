import { getCurrentAdmin } from "@/lib/admin";
import { getDb } from "@/db";
import { admins } from "@/db/schema";
import { NextRequest, NextResponse } from "next/server";

export const dynamic = "force-dynamic";

export async function POST(request: NextRequest) {
  const { admin } = await getCurrentAdmin();
  if (!admin) return NextResponse.json({ error: "Sem permissão." }, { status: 403 });
  const body = await request.json().catch(() => null) as { email?: string } | null;
  const email = body?.email?.trim().toLowerCase() ?? "";
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return NextResponse.json({ error: "Informe um e-mail válido." }, { status: 400 });
  try {
    await getDb().insert(admins).values({ id: crypto.randomUUID(), email, userId: null, role: "manager", createdAt: new Date() });
    return NextResponse.json({ ok: true });
  } catch {
    return NextResponse.json({ error: "Esse e-mail já possui acesso." }, { status: 409 });
  }
}
