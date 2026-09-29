"use client";

import { AwardLookup } from "@/components/award-lookup";
import { Radio } from "lucide-react";

export default function Home() {
  return (
    <main className="min-h-screen bg-[#071d1a] text-stone-100 selection:bg-[#eab453] selection:text-[#071d1a]">
      <header className="border-b border-white/10 bg-[#061714]/90 px-5 py-4 backdrop-blur sm:px-8">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4">
          <a href="/" className="flex items-center gap-3" aria-label="Araucária DX — 50 anos">
            <span className="grid size-10 place-items-center rounded-xl bg-[#eab453] text-[#08211c] shadow-[0_0_28px_rgba(234,180,83,.2)]"><Radio className="size-5" strokeWidth={2.4} /></span>
            <span><span className="block text-sm font-extrabold tracking-[0.08em] text-white">ARAUCÁRIA DX</span><span className="block text-xs font-medium tracking-[0.18em] text-[#d7ad5d]">50 ANOS</span></span>
          </a>
          <a href="/admin" className="rounded-lg border border-[#d7ad5d]/45 px-3.5 py-2 text-sm font-semibold text-[#f7d78d] transition hover:border-[#f7d78d] hover:bg-[#eab453]/10">Área da organização</a>
        </div>
      </header>
      <AwardLookup />
      <footer className="border-t border-white/10 px-5 py-7 text-center text-sm text-stone-400">Araucária DX · Diploma comemorativo de 50 anos</footer>
    </main>
  );
}
