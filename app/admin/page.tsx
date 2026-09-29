import { requireChatGPTUser } from "@/app/chatgpt-auth";
import { AdminConsole } from "@/components/admin-console";
import { Radio } from "lucide-react";

export const dynamic = "force-dynamic";

export default async function AdminPage() {
  const user = await requireChatGPTUser("/admin");
  return (
    <main className="min-h-screen bg-[#071d1a] text-stone-100">
      <header className="border-b border-white/10 bg-[#061714] px-5 py-4 sm:px-8"><div className="mx-auto flex max-w-5xl items-center justify-between gap-4"><a href="/" className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-xl bg-[#eab453] text-[#08211c]"><Radio className="size-5" /></span><span><span className="block text-sm font-extrabold tracking-[0.08em] text-white">ARAUCÁRIA DX</span><span className="block text-xs font-medium tracking-[0.18em] text-[#d7ad5d]">GESTÃO · 50 ANOS</span></span></a><a href="/" className="text-sm font-semibold text-[#f7d78d] hover:text-white">Ver consulta pública</a></div></header>
      <AdminConsole email={user.email} />
    </main>
  );
}
