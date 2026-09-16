import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { MapPin, Plus, RefreshCw } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useCan } from '@/shared/presentation/hooks/useCan';

interface Region { id: number; name: string }
interface Team { id: number; name: string; region_id: number | null; republic_only: boolean; is_active: boolean }
interface OfficeRoute { id: number; bxm_code: string; local_code: string; name: string; team_id: number; region_id: number | null }
interface Member { team_id: number; user_id: number; username: string; is_lead: boolean }
interface Unmapped { id: number; ticket_no: string; subject: string; bxm_code: string | null; local_code: string | null }
interface Config { regions: Region[]; teams: Team[]; routes: OfficeRoute[]; members: Member[]; users: Staff[]; unmapped_count: number; unmapped: Unmapped[] }
interface Stats { name: string; scope: string; region_id: number | null; total: number; open: number; completed: number; breached: number; sla_percent: number }
interface Staff { id: number; username: string; firstName?: string; lastName?: string; bxm_code?: string | null; local_code?: string | null }
const emptyOffice = { bxm_code: '', local_code: '', name: '', team_id: '' };
const control = 'w-full rounded-xl border border-slate-300 bg-white p-2.5 text-sm dark:border-slate-700 dark:bg-slate-900';
const card = 'rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900';
const button = 'rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50';

export const RegionalSupportPage: React.FC = () => {
  const { user } = useCan();
  const superadmin = Boolean(user?.isSuperAdmin || user?.username === 'superadmin' || user?.role === 'Super Admin');
  const [config, setConfig] = useState<Config | null>(null);
  const [stats, setStats] = useState<Stats[]>([]);
  const [staff, setStaff] = useState<Staff[]>([]);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [busy, setBusy] = useState(false);
  const inFlight = useRef(false);
  const [regionId, setRegionId] = useState('');
  const [office, setOffice] = useState(emptyOffice);
  const [editId, setEditId] = useState<number | null>(null);
  const [teamName, setTeamName] = useState('');
  const [memberTeam, setMemberTeam] = useState('');
  const [memberUser, setMemberUser] = useState('');
  const [memberRole, setMemberRole] = useState('Regional Support');
  const [staffSearch, setStaffSearch] = useState('');
  const [staffBxm, setStaffBxm] = useState('');
  const [staffLocal, setStaffLocal] = useState('');
  const load = useCallback(async () => {
    const statRes = await axiosClient.get<{ data: Stats[] }>('/regional-support/stats');
    setStats(statRes.data.data);
    if (superadmin) {
      const settings = await axiosClient.get<Config>('/regional-support');
      setConfig(settings.data);
      setStaff(settings.data.users);
    }
  }, [superadmin]);
  const reportError = (e: unknown) => setError(e instanceof Error ? e.message : 'Ma’lumotni yuklash yoki saqlashda xatolik.');
  useEffect(() => { load().catch(reportError); }, [load]);
  const mutate = async (action: () => Promise<unknown>, success: string) => {
    if (inFlight.current) return;
    inFlight.current = true;
    setBusy(true); setError(''); setMessage('');
    try { await action(); await load(); setMessage(success); }
    catch (e) { reportError(e); }
    finally { setBusy(false); inFlight.current = false; }
  };
  useEffect(() => {
    const selected = staff.find(s => String(s.id) === memberUser);
    setStaffBxm(selected?.bxm_code || '');
    setStaffLocal(selected?.local_code || '');
  }, [memberUser, staff]);
  const teams = config?.teams.filter(t => t.is_active && (!regionId || String(t.region_id) === regionId)) ?? [];
  const routes = config?.routes.filter(r => !regionId || String(r.region_id) === regionId) ?? [];
  const saveOffice = (e: React.FormEvent) => {
    e.preventDefault();
    void mutate(async () => {
      const payload = { ...office, team_id: Number(office.team_id) };
      if (editId) await axiosClient.put(`/regional-support/routes/${editId}`, payload);
      else await axiosClient.post('/regional-support/routes', payload);
      setOffice(emptyOffice); setEditId(null);
    }, 'Ofis IT bo‘limga biriktirildi.');
  };
  return (
    <main className="space-y-6 p-4 text-slate-900 dark:text-slate-100 md:p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div><h1 className="flex items-center gap-2 text-2xl font-bold"><MapPin /> Hududiy support</h1>
          <p className="mt-1 text-sm text-slate-500">Respublika va viloyatlar bo‘yicha zayavkalar, IT bo‘limlar va SLA.</p></div>
        <button className={button} disabled={busy} onClick={() => void mutate(load, 'Yangilandi.')}><RefreshCw className="inline h-4 w-4" /> Yangilash</button>
      </div>
      {error && <p role="alert" className="rounded-xl bg-red-50 p-3 text-red-700 dark:bg-red-950 dark:text-red-200">{error}</p>}
      {message && <p role="status" className="rounded-xl bg-green-50 p-3 text-green-700 dark:bg-green-950 dark:text-green-200">{message}</p>}
      {user?.bxmCode && <p className="text-sm">Sizning BXM kodingiz: <b>{user.bxmCode}</b> · Local kod: <b>{user.localCode || '—'}</b></p>}
      <section className={card}>
        <h2 className="mb-3 font-bold">Hududlar kesimida ko‘rsatkichlar</h2>
        <div className="overflow-x-auto"><table className="w-full text-left text-sm">
          <thead><tr className="border-b"><th className="p-2">Hudud</th><th>Jami</th><th>Navbatda</th><th>Bajarildi</th><th>SLA buzildi</th><th>SLA</th></tr></thead>
          <tbody>{stats.map(row => <tr key={`${row.scope}-${row.region_id}`} className="border-b border-slate-100 dark:border-slate-800"><td className="p-2 font-semibold">{row.name}</td><td>{row.total}</td><td>{row.open}</td><td>{row.completed}</td><td>{row.breached}</td><td>{row.sla_percent}%</td></tr>)}</tbody>
        </table></div>
        {!stats.length && <p className="mt-3 text-sm text-slate-500">Hozircha zayavkalar yo‘q.</p>}
      </section>
      {superadmin && <>
        <div className="flex flex-wrap items-center gap-3">
          <button className={button} disabled={busy} onClick={() => void mutate(() => axiosClient.post('/regional-support/initialize'), '14 ta hudud, IT guruhlar va hududiy rollar tayyor.')}><Plus className="inline h-4 w-4" /> 14 ta hududni tayyorlash</button>
          <Link className="text-sm font-semibold text-brand-600" to="/sla-policies">Hududiy guruhlarning SLA qoidalarini boshqarish →</Link>
        </div>
        <label className="block max-w-md space-y-1 text-sm font-semibold">Hudud bo‘yicha filtr
          <select className={control} value={regionId} onChange={e => { setRegionId(e.target.value); setMemberTeam(''); setOffice(emptyOffice); setEditId(null); }}>
            <option value="">Barcha hududlar va respublika</option>{config?.regions.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
          </select>
        </label>
        <div className="grid gap-5 xl:grid-cols-2">
          <section className={card}>
            <h2 className="mb-3 font-bold">{editId ? 'Ofis biriktirishini tahrirlash' : 'BXM / local kodni IT bo‘limga biriktirish'}</h2>
            <form onSubmit={saveOffice} className="grid gap-3 sm:grid-cols-2">
              <label className="space-y-1 text-sm">BXM kodi<input required maxLength={32} className={control} value={office.bxm_code} onChange={e => setOffice({ ...office, bxm_code: e.target.value })} /></label>
              <label className="space-y-1 text-sm">Local kod<input maxLength={32} className={control} value={office.local_code} onChange={e => setOffice({ ...office, local_code: e.target.value })} /></label>
              <p className="text-xs text-slate-500 sm:col-span-2">Local kod bo‘sh bo‘lsa, shu BXM uchun umumiy qoida. Aniq local kod biriktirishi ustun turadi. Kod boshidagi nollar saqlanadi.</p>
              <label className="space-y-1 text-sm">Ofis nomi<input required maxLength={255} className={control} value={office.name} onChange={e => setOffice({ ...office, name: e.target.value })} /></label>
              <label className="space-y-1 text-sm">IT bo‘lim<select required className={control} value={office.team_id} onChange={e => setOffice({ ...office, team_id: e.target.value })}><option value="">Tanlang</option>{teams.filter(t => !t.republic_only).map(t => <option key={t.id} value={t.id}>{t.name}</option>)}</select></label>
              <button disabled={busy} className={button}>Saqlash</button>
              {editId && <button type="button" onClick={() => { setEditId(null); setOffice(emptyOffice); }}>Bekor qilish</button>}
            </form>
          </section>
          <section className={card}>
            <h2 className="mb-3 font-bold">Hududiy IT bo‘lim qo‘shish</h2>
            <p className="mb-3 text-sm text-slate-500">Har hududga bir yoki bir nechta IT bo‘lim ochib, ofislarini alohida taqsimlang.</p>
            <form className="space-y-3" onSubmit={e => { e.preventDefault(); void mutate(async () => { await axiosClient.post('/teams', { name: teamName, region_id: Number(regionId), is_active: true }); setTeamName(''); }, 'IT bo‘lim va alohida default SLA yaratildi.'); }}>
              <label className="block space-y-1 text-sm">Bo‘lim nomi<input required className={control} value={teamName} onChange={e => setTeamName(e.target.value)} /></label>
              {!regionId && <p className="text-sm text-amber-600">Avval yuqoridagi filtrdan hududni tanlang.</p>}
              <button disabled={busy || !regionId} className={button}>IT bo‘lim yaratish</button>
            </form>
          </section>
        </div>
        <section className={card}>
          <h2 className="mb-3 font-bold">Ofislar taqsimoti</h2>
          <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="p-2">BXM</th><th>Local kod</th><th>Ofis</th><th>IT bo‘lim</th><th /></tr></thead><tbody>{routes.map(r => <tr key={r.id} className="border-b border-slate-100 dark:border-slate-800"><td className="p-2 font-mono">{r.bxm_code}</td><td className="font-mono">{r.local_code || 'Umumiy'}</td><td>{r.name}</td><td>{config?.teams.find(t => t.id === r.team_id)?.name}</td><td><button className="p-2 text-brand-600" onClick={() => { setEditId(r.id); setOffice({ bxm_code: r.bxm_code, local_code: r.local_code, name: r.name, team_id: String(r.team_id) }); }}>Tahrirlash</button></td></tr>)}</tbody></table></div>
          {!routes.length && <p className="mt-3 text-sm text-slate-500">Bu hudud uchun ofislar hali biriktirilmagan.</p>}
        </section>
        <section className={card}>
          <h2 className="mb-3 font-bold">Hududiy support va administratorlar</h2>
          <p className="mb-3 text-sm text-slate-500">Xodimning BXM/local kodi tanlangan hududga mos bo‘lishi kerak. Rol va guruhga a’zolik birga beriladi.</p>
          <form className="grid gap-3 md:grid-cols-4" onSubmit={e => { e.preventDefault(); void mutate(() => axiosClient.post('/regional-support/members', { team_id: Number(memberTeam), user_id: Number(memberUser), role: memberRole }), 'Xodim va rol biriktirildi.'); }}>
            <label className="space-y-1 text-sm">Xodim qidirish<input className={control} value={staffSearch} onChange={e => setStaffSearch(e.target.value)} placeholder="Login yoki ism" /></label>
            <label className="space-y-1 text-sm">Xodim<select required className={control} value={memberUser} onChange={e => setMemberUser(e.target.value)}><option value="">Tanlang</option>{staff.filter(s => `${s.username} ${s.firstName || ''} ${s.lastName || ''}`.toLowerCase().includes(staffSearch.toLowerCase())).map(s => <option key={s.id} value={s.id}>{s.username}</option>)}</select></label>
            <label className="space-y-1 text-sm">IT bo‘lim<select required className={control} value={memberTeam} onChange={e => setMemberTeam(e.target.value)}><option value="">Tanlang</option>{teams.filter(t => t.region_id).map(t => <option key={t.id} value={t.id}>{t.name}</option>)}</select></label>
            <label className="space-y-1 text-sm">Rol<select className={control} value={memberRole} onChange={e => setMemberRole(e.target.value)}><option value="Regional Support">Viloyat support</option><option value="Regional Admin">Viloyat administratori</option></select></label>
            <button disabled={busy} className={button}>Biriktirish</button>
          </form>
          <ul className="mt-4 space-y-2 text-sm">{config?.members.filter(m => teams.some(t => t.id === m.team_id)).map(m => <li className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 p-3 dark:bg-slate-800" key={`${m.team_id}-${m.user_id}`}><span>{m.username} · {config.teams.find(t => t.id === m.team_id)?.name} · {m.is_lead ? 'Administrator' : 'Support'}</span><button disabled={busy} className="text-red-600" onClick={() => void mutate(() => axiosClient.delete(`/regional-support/teams/${m.team_id}/members/${m.user_id}`), 'Biriktirish bekor qilindi.')}>Guruhdan chiqarish</button></li>)}</ul>
          {memberUser && <form className="mt-4 flex flex-wrap items-end gap-3 border-t pt-4" onSubmit={e => { e.preventDefault(); void mutate(() => axiosClient.put(`/regional-support/users/${memberUser}/identity`, { bxm_code: staffBxm, local_code: staffLocal }), 'Xodim kodlari saqlandi.'); }}>
            <label className="space-y-1 text-sm">Xodimning BXM kodi<input required maxLength={32} className={control} value={staffBxm} onChange={e => setStaffBxm(e.target.value)} /></label>
            <label className="space-y-1 text-sm">Xodimning local kodi<input maxLength={32} className={control} value={staffLocal} onChange={e => setStaffLocal(e.target.value)} /></label>
            <button disabled={busy} className={button}>Xodim kodlarini saqlash</button>
            <p className="w-full text-xs text-slate-500">AD orqali kelgan BXM kodi kirishda yangilanadi. Local kod HR sinxronizatsiyasidan yoki shu maydondan olinadi.</p>
          </form>}
        </section>
        <section className={card}>
          <h2 className="mb-3 font-bold">BI xizmatlari — faqat respublika</h2>
          <p className="mb-3 text-sm text-slate-500">BI hisobotlari bilan ishlaydigan respublika guruhini belgilang. Unga murojaatlar barcha hududlardan tushadi.</p>
          <div className="grid gap-2 md:grid-cols-3">{config?.teams.filter(t => !t.region_id).map(t => <label key={t.id} className="flex items-center gap-2 text-sm"><input type="checkbox" disabled={busy} checked={Boolean(t.republic_only)} onChange={e => void mutate(() => axiosClient.put(`/teams/${t.id}`, { republic_only: e.target.checked }), 'Respublika yo‘naltirish qoidasi saqlandi.')} />{t.name}</label>)}</div>
        </section>
        {!!config?.unmapped_count && <section className={card}>
          <h2 className="mb-3 font-bold text-amber-600">Biriktirish kutilayotgan zayavkalar: {config.unmapped_count}</h2>
          <p className="mb-3 text-sm text-slate-500">Avval BXM/local kodni sozlang, so‘ng zayavkani yo‘naltiring. Oxirgi 100 ta ko‘rsatilgan.</p>
          {config.unmapped.map(t => <div className="flex flex-wrap items-center justify-between gap-2 border-b py-3 text-sm" key={t.id}><Link className="text-brand-600" to={`/task/${t.id}`}>{t.ticket_no} · {t.subject} · BXM: {t.bxm_code || '—'} / {t.local_code || '—'}</Link><button className={button} disabled={busy} onClick={() => void mutate(() => axiosClient.post(`/regional-support/tickets/${t.id}/reroute`), 'Zayavka IT bo‘limga yo‘naltirildi.')}>Yo‘naltirish</button></div>)}
        </section>}
      </>}
    </main>
  );
};
