"use client";

import { Award, BadgeCheck, Download, FileDown, LoaderCircle, MapPin, RadioTower, Satellite, Search, ShieldCheck } from "lucide-react";
import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";

type AwardRecord = {
  found: boolean; callsign: string; contacts?: number; bands?: string[]; dates?: string[];
  activations?: { reference: string; name: string }[]; endorsements?: { code: string; label: string }[]; satellite?: boolean;
};

const demo: AwardRecord = { found: true, callsign: "SEU INDICATIVO", contacts: 0, bands: [], dates: [], activations: [], endorsements: [{ code: "50", label: "Araucária DX · 50 anos" }] };

function formatDate(value: string) { return /^\d{8}$/.test(value) ? `${value.slice(6, 8)}/${value.slice(4, 6)}/${value.slice(0, 4)}` : value; }

function makeShareCard(record: AwardRecord) {
  const canvas = document.createElement("canvas"); canvas.width = 1600; canvas.height = 900;
  const context = canvas.getContext("2d"); if (!context) return;
  const gradient = context.createLinearGradient(0, 0, 1600, 900); gradient.addColorStop(0, "#071d1a"); gradient.addColorStop(1, "#123c31");
  context.fillStyle = gradient; context.fillRect(0, 0, canvas.width, canvas.height);
  context.strokeStyle = "#eab453"; context.lineWidth = 4; context.strokeRect(52, 52, 1496, 796);
  context.strokeStyle = "rgba(234,180,83,.42)"; context.lineWidth = 1; context.strokeRect(74, 74, 1452, 752);
  context.fillStyle = "#eab453"; context.font = "700 42px Arial"; context.fillText("ARAUCÁRIA DX · 50 ANOS", 120, 180);
  context.fillStyle = "#ffffff"; context.font = "700 96px Arial"; context.fillText(record.callsign, 120, 340);
  context.fillStyle = "#f6e1ae"; context.font = "400 34px Arial"; context.fillText("Participante do diploma comemorativo", 120, 410);
  context.fillStyle = "#eab453"; context.font = "700 72px Arial"; context.fillText(`${record.contacts ?? 0} QSOs`, 120, 600);
  context.fillStyle = "#dbece0"; context.font = "400 30px Arial"; context.fillText(`${record.activations?.length ?? 0} unidades de conservação ativadas`, 120, 665); context.fillText("araucariadx.com", 120, 760);
  const link = document.createElement("a"); link.download = `araucaria-dx-50-anos-${record.callsign.toLowerCase().replace(/\//g, "-")}.png`; link.href = canvas.toDataURL("image/png"); link.click();
}

export function AwardLookup() {
  const [callsign, setCallsign] = useState("");
  const [record, setRecord] = useState<AwardRecord | null>(null);
  const [status, setStatus] = useState<"idle" | "loading" | "not-found" | "error">("idle");
  const lookup = useCallback(async (value: string) => {
    const normalized = value.toUpperCase().replace(/[^A-Z0-9/]/g, "");
    if (normalized.length < 3) { setStatus("error"); setRecord(null); return { error: "Informe um indicativo válido." }; }
    setCallsign(normalized); setStatus("loading"); setRecord(null);
    try {
      const response = await fetch(`/api/award?callsign=${encodeURIComponent(normalized)}`, { cache: "no-store" });
      const payload = (await response.json()) as AwardRecord & { error?: string };
      if (!response.ok) throw new Error(payload.error ?? "Não foi possível consultar os logs.");
      if (!payload.found) { setStatus("not-found"); return { found: false, callsign: normalized }; }
      setRecord(payload); setStatus("idle"); return payload;
    } catch (error) { setStatus("error"); return { error: error instanceof Error ? error.message : "Falha na consulta." }; }
  }, []);

  useEffect(() => {
    const context = document.modelContext; if (!context?.registerTool) return;
    const lifecycle = new AbortController();
    void Promise.resolve(context.registerTool({
      name: "look_up_araucaria_dx_certificate", title: "Consultar diploma Araucária DX",
      description: "Consulta um indicativo e mostra seu diploma comemorativo, quando houver contatos importados.",
      inputSchema: { type: "object", properties: { callsign: { type: "string", description: "Indicativo de radioamador" } }, required: ["callsign"], additionalProperties: false },
      annotations: { readOnlyHint: true, untrustedContentHint: false },
      execute: async (input) => lookup(String((input as { callsign?: unknown }).callsign ?? "")),
    }, { signal: lifecycle.signal }));
    return () => lifecycle.abort();
  }, [lookup]);

  const visibleRecord = useMemo(() => record ?? demo, [record]);
  function handleSubmit(event: FormEvent<HTMLFormElement>) { event.preventDefault(); void lookup(callsign); }

  return (
    <div className="mx-auto max-w-6xl px-5 pb-16 pt-12 sm:px-8 sm:pt-16">
      <section className="grid gap-10 lg:grid-cols-[1.12fr_.88fr] lg:items-center">
        <div>
          <p className="mb-4 text-sm font-bold uppercase tracking-[0.2em] text-[#eab453]">Diploma comemorativo</p>
          <h1 className="max-w-3xl text-4xl font-black leading-[1.05] tracking-tight text-white sm:text-6xl">Seu sinal fez parte desta história.</h1>
          <p className="mt-5 max-w-2xl text-lg leading-8 text-stone-300">Consulte seu indicativo para emitir o diploma dos 50 anos da Araucária DX, com seus QSOs, ativações e endossos registrados pela organização.</p>
          <form onSubmit={handleSubmit} className="mt-8 flex max-w-xl flex-col gap-3 sm:flex-row">
            <label className="sr-only" htmlFor="callsign">Seu indicativo</label>
            <input id="callsign" value={callsign} onChange={(event) => setCallsign(event.target.value.toUpperCase())} placeholder="Ex.: PY5XT" className="min-h-14 flex-1 rounded-xl border border-white/15 bg-white px-5 text-lg font-bold tracking-[0.06em] text-[#092019] outline-none placeholder:text-stone-400 focus:border-[#eab453] focus:ring-4 focus:ring-[#eab453]/20" autoCapitalize="characters" autoCorrect="off" spellCheck={false} />
            <button type="submit" disabled={status === "loading"} className="inline-flex min-h-14 items-center justify-center gap-2 rounded-xl bg-[#eab453] px-6 text-base font-extrabold text-[#08211c] transition hover:bg-[#f5c86d] disabled:cursor-wait disabled:opacity-70">{status === "loading" ? <LoaderCircle className="size-5 animate-spin" /> : <Search className="size-5" />}Consultar</button>
          </form>
          <p className="mt-3 text-sm text-stone-400">Use o mesmo indicativo presente no log do contato.</p>
          {status === "not-found" && <p role="status" className="mt-5 rounded-lg border border-[#eab453]/30 bg-[#eab453]/10 px-4 py-3 text-sm text-[#f6d895]">Ainda não localizamos contatos para <strong>{callsign}</strong>. O log da operação pode estar aguardando importação.</p>}
          {status === "error" && <p role="alert" className="mt-5 rounded-lg border border-red-300/30 bg-red-400/10 px-4 py-3 text-sm text-red-100">Informe um indicativo com ao menos três caracteres ou tente novamente em instantes.</p>}
        </div>
        <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
          <StatCard icon={<Award className="size-5" />} title="Diploma único" text="Emitido com o seu indicativo e pronto para imprimir." />
          <StatCard icon={<MapPin className="size-5" />} title="Ativações" text="Unidades de conservação e referências trabalhadas." />
          <StatCard icon={<Satellite className="size-5" />} title="Endossos" text="Selos por satélite, WFF e operações especiais." />
        </div>
      </section>

      {record?.found ? <section className="mt-14" aria-live="polite">
        <div className="mb-5 flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-bold uppercase tracking-[0.16em] text-[#eab453]">Resultado encontrado</p><h2 className="mt-1 text-2xl font-bold text-white">Diploma de {record.callsign}</h2></div><div className="flex flex-wrap gap-2 print:hidden"><button onClick={() => window.print()} className="inline-flex items-center gap-2 rounded-lg border border-white/20 px-4 py-2.5 text-sm font-bold text-white hover:bg-white/10"><FileDown className="size-4" /> Imprimir / salvar PDF</button><button onClick={() => makeShareCard(record)} className="inline-flex items-center gap-2 rounded-lg bg-[#eab453] px-4 py-2.5 text-sm font-extrabold text-[#08211c] hover:bg-[#f5c86d]"><Download className="size-4" /> Baixar figurinha</button></div></div>
        <article className="certificate relative overflow-hidden rounded-2xl border border-[#eab453]/70 bg-[#f6f0df] p-5 text-[#173a30] shadow-2xl sm:p-9"><div className="absolute inset-3 border border-[#bc8b31]/40" aria-hidden="true" /><div className="relative grid min-h-[580px] content-between gap-8 border border-[#bc8b31]/30 p-5 sm:p-10">
          <div className="flex items-start justify-between gap-5"><div><p className="text-xs font-extrabold tracking-[0.22em] text-[#a4741c]">ARAUCÁRIA DX · 50 ANOS</p><h3 className="mt-2 text-2xl font-black tracking-tight sm:text-4xl">Certificado comemorativo</h3></div><div className="grid size-16 shrink-0 place-items-center rounded-full border-4 border-[#a4741c] text-center text-[10px] font-black leading-3 text-[#775112]">50<br />ANOS</div></div>
          <div className="max-w-3xl"><p className="text-lg italic text-[#49685d]">A Araucária DX reconhece a participação de</p><p className="mt-3 break-words text-4xl font-black tracking-[0.05em] text-[#0d382c] sm:text-6xl">{visibleRecord.callsign}</p><p className="mt-5 max-w-2xl text-base leading-7 text-[#35594c] sm:text-lg">nas atividades comemorativas do cinquentenário, registrando contatos, ativações e conquistas que fortalecem o radioamadorismo.</p></div>
          <div className="grid gap-4 sm:grid-cols-3"><Metric label="QSOs registrados" value={String(visibleRecord.contacts ?? 0)} icon={<RadioTower className="size-5" />} /><Metric label="Unidades ativadas" value={String(visibleRecord.activations?.length ?? 0)} icon={<MapPin className="size-5" />} /><Metric label="Bandas" value={visibleRecord.bands?.length ? visibleRecord.bands.join(" · ") : "—"} icon={<ShieldCheck className="size-5" />} /></div>
          <div className="grid gap-6 border-t border-[#bc8b31]/30 pt-6 md:grid-cols-2"><div><p className="text-xs font-bold uppercase tracking-[0.15em] text-[#896021]">Endossos conquistados</p><div className="mt-3 flex flex-wrap gap-2">{visibleRecord.endorsements?.map((endorsement) => <span key={endorsement.code} className="inline-flex items-center gap-1.5 rounded-full border border-[#a4741c]/45 bg-[#eab453]/15 px-3 py-1.5 text-sm font-bold text-[#66470f]"><BadgeCheck className="size-4" /> {endorsement.label}</span>)}</div></div><div><p className="text-xs font-bold uppercase tracking-[0.15em] text-[#896021]">Unidades de conservação</p><p className="mt-3 text-sm leading-6 text-[#35594c]">{visibleRecord.activations?.length ? visibleRecord.activations.map((item) => `${item.reference}${item.name ? ` — ${item.name}` : ""}`).join(" · ") : "Nenhuma unidade de conservação vinculada aos contatos importados."}</p></div></div>
          <div className="flex flex-wrap items-end justify-between gap-4 border-t border-[#bc8b31]/30 pt-5 text-sm text-[#49685d]"><span>Operações registradas: {visibleRecord.dates?.map(formatDate).join(" · ") || "—"}</span><span className="font-bold text-[#6f4d10]">Araucária DX</span></div>
        </div></article>
      </section> : <section className="mt-14 rounded-2xl border border-dashed border-white/20 bg-white/[.035] px-6 py-8 text-center"><p className="font-semibold text-white">O seu certificado aparecerá aqui após a consulta.</p><p className="mt-2 text-sm text-stone-400">A organização importa os logs e atribui os endossos de cada operação.</p></section>}
    </div>
  );
}

function StatCard({ icon, title, text }: { icon: React.ReactNode; title: string; text: string }) { return <div className="rounded-xl border border-white/10 bg-white/[.055] p-5"><span className="mb-4 grid size-9 place-items-center rounded-lg bg-[#eab453]/15 text-[#f2c76f]">{icon}</span><h2 className="font-bold text-white">{title}</h2><p className="mt-1.5 text-sm leading-6 text-stone-400">{text}</p></div>; }
function Metric({ label, value, icon }: { label: string; value: string; icon: React.ReactNode }) { return <div className="rounded-xl border border-[#bc8b31]/25 bg-white/45 p-4"><span className="flex items-center gap-2 text-sm text-[#775112]">{icon} {label}</span><p className="mt-2 break-words text-2xl font-black text-[#0d382c]">{value}</p></div>; }
