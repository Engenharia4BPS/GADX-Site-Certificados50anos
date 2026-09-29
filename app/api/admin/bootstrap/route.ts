import { getChatGPTUser } from "@/app/chatgpt-auth";
import { getDb } from "@/db";
import { admins } from "@/db/schema";
import { count } from "drizzle-orm";
import { env } from "cloudflare:workers";
import { NextRequest, NextResponse } from "next/server";

export const dynamic = "force-dynamic";

export async function POST(request: NextRequest) {
  const user = await getChatGPTUser();
  if (!user) return NextResponse.json({ error: "Faça login para continuar." }, { status: 401 });
  const body = await request.json().catch(() => null) as { setupKey?: string } | null;
  const setupKey = (env as unknown as { ADMIN_SETUP_KEY?: string }).ADMIN_SETUP_KEY;
  if (!body?.setupKey || !setupKey || body.setupKey !== setupKey) return NextResponse.json({ error: "Chave de ativação inválida." }, { status: 403 });
  const db = getDb();
  const [{ total }] = await db.select({ total: count() }).from(admins);
  if (Number(total) > 0) return NextResponse.json({ error: "A área administrativa já foi ativada." }, { status: 409 });
  await db.insert(admins).values({ id: crypto.randomUUID(), email: user.email.toLowerCase(), userId: user.userId, role: "owner", createdAt: new Date() });
  return NextResponse.json({ ok: true });
}
