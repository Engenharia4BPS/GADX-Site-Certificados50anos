"use client";

import { FileUp, KeyRound, LoaderCircle, Plus, ShieldCheck, Upload, UserPlus } from "lucide-react";
import { ChangeEvent, FormEvent, useEffect, useState } from "react";

type Session = { signedIn: boolean; email?: string; isAdmin: boolean; role?: string | null; bootstrapAllowed: boolean };
const today = new Date().toISOString().slice(0, 10);

export function AdminConsole({ email }: { email: string }) {
  const [session, setSession] = useState<Session | null>(null);
  const [setupKey, setSetupKey] = useState("");
  const [bootstrapMessage, setBootstrapMessage] = useState("");
  const [file, setFile] = useState<File | null>(null);
  const [label, setLabel] = useState("");
  const [activityDate, setActivityDate] = useState(today);
  const [satellite, setSatellite] = useState(false);
  const [wff, setWff] = useState(false);
  const [activations, setActivations] = useState("");
  const [notes, setNotes] = useState("");
  const [importMessage, setImportMessage] = useState("");
  const [importing, setImporting] = useState(false);
  const [inviteEmail, setInviteEmail] = useState("");
  const [inviteMessage, setInviteMessage] = useState("");

  async function refreshSession() {
    const response = await fetch("/api/admin/session", { cache: "no-store" });
    setSession(await response.json() as Session);
  }
  useEffect(() => { void refreshSession(); }, []);

  async function activate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBootstrapMessage("");
    const response = await fetch("/api/admin/bootstrap", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ setupKey }) });
    const result = await response.json() as { error?: string };
    if (!response.ok) { setBootstrapMessage(result.error ?? "Não foi possível ativar a área."); return; }
    setSetupKey(""); setBootstrapMessage("Área ativada. Você já pode importar o primeiro ADIF."); await refreshSession();
  }

  async function importLog(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setImportMessage("");
    if (!file) { setImportMessage("Selecione um arquivo ADIF (.adi ou .adif)."); return; }
    setImporting(true);
    try {
      const adif = await file.text();
      const parkRows = activations.split(/\n|,/).map((line) => line.trim()).filter(Boolean).map((line) => { const [reference, ...rest] = line.split("|"); return { reference, name: rest.join("|").trim() }; });
      const response = await fetch("/api/admin/import", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ label, activityDate, filename: file.name, adif, satellite, wff, notes, activations: parkRows }) });
      const result = await response.json() as { error?: string; imported?: number; callsigns?: number };
      if (!response.ok) throw new Error(result.error ?? "Falha ao importar.");
      setImportMessage(`Importação concluída: ${result.imported} QSOs e ${result.callsigns} indicativos disponíveis para consulta.`);
      setFile(null); setLabel(""); setActivations(""); setNotes(""); setSatellite(false); setWff(false);
    } catch (error) { setImportMessage(error instanceof Error ? error.message : "Falha ao importar o arquivo."); }
    finally { setImporting(false); }
  }

  async function invite(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setInviteMessage("");
    const response = await fetch("/api/admin/invite", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ email: inviteEmail }) });
    const result = await response.json() as { error?: string };
    if (!response.ok) { setInviteMessage(result.error ?? "Não foi possível liberar o acesso."); return; }
    setInviteMessage("Acesso liberado. A pessoa deverá entrar com esse e-mail na área da organização."); setInviteEmail("");
  }

  if (!session) return <div className="mx-auto flex max-w-5xl items-center gap-3 px-5 py-16 text-stone-300"><LoaderCircle className="size-5 animate-spin" /> Verificando acesso…</div>;
  if (!session.isAdmin && session.bootstrapAllowed) return <section className="mx-auto max-w-xl px-5 py-14"><div className="rounded-2xl border border-[#eab453]/35 bg-white/[.045] p-7"><span className="grid size-11 place-items-center rounded-xl bg-[#eab453]/15 text-[#f0c46c]"><KeyRound className="size-5" /></span><h1 className="mt-5 text-2xl font-black text-white">Ativar a área da organização</h1><p className="mt-2 leading-7 text-stone-300">Você entrou como <strong>{email}</strong>. Informe a chave inicial recebida para tornar esta conta a administradora do evento.</p><form onSubmit={activate} className="mt-6"><label className="block text-sm font-bold text-stone-200" htmlFor="setup-key">Chave de ativação</label><input id="setup-key" type="password" value={setupKey} onChange={(event) => setSetupKey(event.target.value)} className="mt-2 min-h-12 w-full rounded-lg border border-white/15 bg-white px-3 text-[#092019] outline-none focus:border-[#eab453]" required /><button className="mt-4 inline-flex min-h-11 items-center gap-2 rounded-lg bg-[#eab453] px-4 text-sm font-extrabold text-[#08211c]"><ShieldCheck className="size-4" /> Ativar administração</button>{bootstrapMessage && <p className="mt-4 text-sm text-[#f6d895]">{bootstrapMessage}</p>}</form></div></section>;
  if (!session.isAdmin) return <section className="mx-auto max-w-xl px-5 py-14"><div className="rounded-2xl border border-white/10 bg-white/[.045] p-7"><h1 className="text-2xl font-black text-white">Acesso ainda não liberado</h1><p className="mt-3 leading-7 text-stone-300">Sua conta <strong>{email}</strong> não está na lista de administradores. Peça a um organizador já habilitado para incluí-la.</p></div></section>;

  return <div className="mx-auto max-w-5xl px-5 py-10 sm:py-14"><div className="mb-9"><p className="text-sm font-bold uppercase tracking-[0.17em] text-[#eab453]">Organização</p><h1 className="mt-2 text-3xl font-black text-white sm:text-4xl">Importar logs e reconhecer participantes</h1><p className="mt-3 max-w-3xl leading-7 text-stone-300">Cada ADIF importado passa a alimentar a consulta pública. Classifique a operação uma vez e os participantes recebem seus endossos automaticamente.</p></div><div className="grid gap-6 lg:grid-cols-[1.35fr_.65fr]"><section className="rounded-2xl border border-white/10 bg-white/[.045] p-5 sm:p-7"><div className="flex items-center gap-3"><span className="grid size-10 place-items-center rounded-lg bg-[#eab453]/15 text-[#f0c46c]"><FileUp className="size-5" /></span><div><h2 className="font-bold text-white">Nova importação ADIF</h2><p className="text-sm text-stone-400">Os QSOs serão associados aos endossos abaixo.</p></div></div><form onSubmit={importLog} className="mt-7 grid gap-5"><div className="grid gap-5 sm:grid-cols-2"><Field label="Nome da operação"><input value={label} onChange={(event) => setLabel(event.target.value)} placeholder="Ex.: 50 anos — etapa 1" required className="field" /></Field><Field label="Data da atividade"><input type="date" value={activityDate} onChange={(event) => setActivityDate(event.target.value)} className="field" /></Field></div><Field label="Arquivo ADIF"><input type="file" accept=".adi,.adif,text/plain" onChange={(event: ChangeEvent<HTMLInputElement>) => setFile(event.target.files?.[0] ?? null)} required className="block w-full rounded-lg border border-dashed border-white/25 bg-white/[.04] px-3 py-3 text-sm text-stone-300 file:mr-4 file:rounded-md file:border-0 file:bg-[#eab453] file:px-3 file:py-1.5 file:text-sm file:font-bold file:text-[#08211c]" />{file && <span className="mt-1 block text-sm text-[#f6d895]">Selecionado: {file.name}</span>}</Field><div className="grid gap-3 sm:grid-cols-2"><Check label="Contato via satélite" checked={satellite} onChange={setSatellite} /><Check label="Ativação WFF" checked={wff} onChange={setWff} /></div><Field label="Unidades de conservação"><textarea value={activations} onChange={(event) => setActivations(event.target.value)} placeholder={"Uma por linha: PR-0001 | Nome da unidade\nSe não houver, deixe em branco."} className="field min-h-24" /></Field><Field label="Observações internas"><textarea value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Opcional — não aparece no diploma." className="field min-h-20" /></Field><div className="flex flex-wrap items-center gap-4"><button disabled={importing} className="inline-flex min-h-12 items-center gap-2 rounded-lg bg-[#eab453] px-5 text-sm font-extrabold text-[#08211c] disabled:opacity-60">{importing ? <LoaderCircle className="size-4 animate-spin" /> : <Upload className="size-4" />}{importing ? "Importando…" : "Importar e publicar nos diplomas"}</button>{importMessage && <p role="status" className="text-sm text-[#f6d895]">{importMessage}</p>}</div></form></section><aside className="space-y-6"><section className="rounded-2xl border border-white/10 bg-white/[.045] p-5"><span className="grid size-10 place-items-center rounded-lg bg-[#eab453]/15 text-[#f0c46c]"><UserPlus className="size-5" /></span><h2 className="mt-4 font-bold text-white">Adicionar administrador</h2><p className="mt-1 text-sm leading-6 text-stone-400">Liberte outra pessoa da equipe. Ela entra com o próprio e-mail nesta área.</p><form onSubmit={invite} className="mt-4"><label className="sr-only" htmlFor="invite-email">E-mail</label><input id="invite-email" type="email" value={inviteEmail} onChange={(event) => setInviteEmail(event.target.value)} placeholder="equipe@exemplo.com" required className="field" /><button className="mt-3 inline-flex min-h-10 items-center gap-2 rounded-lg border border-[#eab453]/60 px-4 text-sm font-bold text-[#f6d895] hover:bg-[#eab453]/10"><Plus className="size-4" /> Liberar acesso</button>{inviteMessage && <p className="mt-3 text-sm leading-6 text-[#f6d895]">{inviteMessage}</p>}</form></section><section className="rounded-2xl border border-white/10 bg-[#123c31]/45 p-5"><h2 className="font-bold text-white">Como os endossos funcionam</h2><ul className="mt-3 space-y-2 text-sm leading-6 text-stone-300"><li>• Todo QSO recebe o selo “Araucária DX · 50 anos”.</li><li>• Marque satélite quando aquela operação tiver contatos por satélite.</li><li>• Informe a unidade de conservação para exibi-la no diploma.</li></ul></section></aside></div></div>;
}

function Field({ label, children }: { label: string; children: React.ReactNode }) { return <label className="block text-sm font-bold text-stone-200"><span className="mb-2 block">{label}</span>{children}</label>; }
function Check({ label, checked, onChange }: { label: string; checked: boolean; onChange: (value: boolean) => void }) { return <label className="flex cursor-pointer items-center gap-3 rounded-lg border border-white/10 bg-white/[.035] px-4 py-3 text-sm font-semibold text-stone-200"><input type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="size-4 accent-[#eab453]" />{label}</label>; }
