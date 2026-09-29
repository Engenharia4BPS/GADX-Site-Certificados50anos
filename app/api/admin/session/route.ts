import { getChatGPTUser } from "@/app/chatgpt-auth";
import { getCurrentAdmin } from "@/lib/admin";
import { getDb } from "@/db";
import { admins } from "@/db/schema";
import { count } from "drizzle-orm";
import { NextResponse } from "next/server";

export const dynamic = "force-dynamic";

export async function GET() {
  const user = await getChatGPTUser();
  if (!user) return NextResponse.json({ signedIn: false, isAdmin: false, bootstrapAllowed: false });
  const db = getDb();
  const [{ total }] = await db.select({ total: count() }).from(admins);
  const { admin } = await getCurrentAdmin();
  return NextResponse.json({ signedIn: true, email: user.email, isAdmin: Boolean(admin), role: admin?.role ?? null, bootstrapAllowed: Number(total) === 0 });
}
