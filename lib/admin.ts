import { getChatGPTUser } from "@/app/chatgpt-auth";
import { getDb } from "@/db";
import { admins } from "@/db/schema";
import { eq } from "drizzle-orm";

export async function getCurrentAdmin() {
  const user = await getChatGPTUser();
  if (!user) return { user: null, admin: null };
  const db = getDb();
  const [admin] = await db.select().from(admins).where(eq(admins.email, user.email.toLowerCase())).limit(1);
  if (admin && !admin.userId) {
    await db.update(admins).set({ userId: user.userId }).where(eq(admins.id, admin.id));
    admin.userId = user.userId;
  }
  return { user, admin: admin ?? null };
}
