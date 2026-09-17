import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { MapPin, Plus, RefreshCw } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useT } from '@/shared/presentation/i18n/i18n';

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
  const t = useT();
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
  const reportError = (e: unknown) => setError(e instanceof Error ? e.message : t('regional.loadFailed'));
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
  const teams = config?.teams.filter(team => team.is_active && (!regionId || String(team.region_id) === regionId)) ?? [];
  const routes = config?.routes.filter(r => !regionId || String(r.region_id) === regionId) ?? [];
  const saveOffice = (e: React.FormEvent) => {
    e.preventDefault();
    void mutate(async () => {
      const payload = { ...office, team_id: Number(office.team_id) };
      if (editId) await axiosClient.put(`/regional-support/routes/${editId}`, payload);
      else await axiosClient.post('/regional-support/routes', payload);
      setOffice(emptyOffice); setEditId(null);
    }, t('regional.officeSaved'));
  };
  return (
    <main className="space-y-6 p-4 text-slate-900 dark:text-slate-100 md:p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div><h1 className="flex items-center gap-2 text-2xl font-bold"><MapPin /> {t('regional.title')}</h1>
          <p className="mt-1 text-sm text-slate-500">{t('regional.subtitle')}</p></div>
        <button className={button} disabled={busy} onClick={() => void mutate(load, t('regional.refreshed'))}><RefreshCw className="inline h-4 w-4" /> {t('regional.refresh')}</button>
      </div>
      {error && <p role="alert" className="rounded-xl bg-red-50 p-3 text-red-700 dark:bg-red-950 dark:text-red-200">{error}</p>}
      {message && <p role="status" className="rounded-xl bg-green-50 p-3 text-green-700 dark:bg-green-950 dark:text-green-200">{message}</p>}
      {user?.bxmCode && <p className="text-sm">{t('regional.yourCodes', { bxm: user.bxmCode, local: user.localCode || '—' })}</p>}
      <section className={card}>
        <h2 className="mb-3 font-bold">{t('regional.statsTitle')}</h2>
        <div className="overflow-x-auto"><table className="w-full text-left text-sm">
          <thead><tr className="border-b"><th className="p-2">{t('regional.colRegion')}</th><th>{t('regional.colTotal')}</th><th>{t('regional.colOpen')}</th><th>{t('regional.colCompleted')}</th><th>{t('regional.colBreached')}</th><th>{t('regional.colSla')}</th></tr></thead>
          <tbody>{stats.map(row => <tr key={`${row.scope}-${row.region_id}`} className="border-b border-slate-100 dark:border-slate-800"><td className="p-2 font-semibold">{row.name}</td><td>{row.total}</td><td>{row.open}</td><td>{row.completed}</td><td>{row.breached}</td><td>{row.sla_percent}%</td></tr>)}</tbody>
        </table></div>
        {!stats.length && <p className="mt-3 text-sm text-slate-500">{t('regional.noTickets')}</p>}
      </section>
      {superadmin && <>
        <div className="flex flex-wrap items-center gap-3">
          <button className={button} disabled={busy} onClick={() => void mutate(() => axiosClient.post('/regional-support/initialize'), t('regional.initialized'))}><Plus className="inline h-4 w-4" /> {t('regional.initialize')}</button>
          <Link className="text-sm font-semibold text-brand-600" to="/sla-policies">{t('regional.manageSla')}</Link>
        </div>
        <label className="block max-w-md space-y-1 text-sm font-semibold">{t('regional.regionFilter')}
          <select className={control} value={regionId} onChange={e => { setRegionId(e.target.value); setMemberTeam(''); setOffice(emptyOffice); setEditId(null); }}>
            <option value="">{t('regional.allRegions')}</option>{config?.regions.map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
          </select>
        </label>
        <div className="grid gap-5 xl:grid-cols-2">
          <section className={card}>
            <h2 className="mb-3 font-bold">{t(editId ? 'regional.officeEditTitle' : 'regional.officeCreateTitle')}</h2>
            <form onSubmit={saveOffice} className="grid gap-3 sm:grid-cols-2">
              <label className="space-y-1 text-sm">{t('regional.bxmCode')}<input required maxLength={32} className={control} value={office.bxm_code} onChange={e => setOffice({ ...office, bxm_code: e.target.value })} /></label>
              <label className="space-y-1 text-sm">{t('regional.localCode')}<input maxLength={32} className={control} value={office.local_code} onChange={e => setOffice({ ...office, local_code: e.target.value })} /></label>
              <p className="text-xs text-slate-500 sm:col-span-2">{t('regional.officeHint')}</p>
              <label className="space-y-1 text-sm">{t('regional.officeName')}<input required maxLength={255} className={control} value={office.name} onChange={e => setOffice({ ...office, name: e.target.value })} /></label>
              <label className="space-y-1 text-sm">{t('regional.itTeam')}<select required className={control} value={office.team_id} onChange={e => setOffice({ ...office, team_id: e.target.value })}><option value="">{t('regional.select')}</option>{teams.filter(team => !team.republic_only).map(team => <option key={team.id} value={team.id}>{team.name}</option>)}</select></label>
              <button disabled={busy} className={button}>{t('regional.save')}</button>
              {editId && <button type="button" onClick={() => { setEditId(null); setOffice(emptyOffice); }}>{t('regional.cancel')}</button>}
            </form>
          </section>
          <section className={card}>
            <h2 className="mb-3 font-bold">{t('regional.teamTitle')}</h2>
            <p className="mb-3 text-sm text-slate-500">{t('regional.teamHint')}</p>
            <form className="space-y-3" onSubmit={e => { e.preventDefault(); void mutate(async () => { await axiosClient.post('/teams', { name: teamName, region_id: Number(regionId), is_active: true }); setTeamName(''); }, t('regional.teamCreated')); }}>
              <label className="block space-y-1 text-sm">{t('regional.teamName')}<input required className={control} value={teamName} onChange={e => setTeamName(e.target.value)} /></label>
              {!regionId && <p className="text-sm text-amber-600">{t('regional.pickRegionFirst')}</p>}
              <button disabled={busy || !regionId} className={button}>{t('regional.createTeam')}</button>
            </form>
          </section>
        </div>
        <section className={card}>
          <h2 className="mb-3 font-bold">{t('regional.routesTitle')}</h2>
          <div className="overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr className="border-b"><th className="p-2">{t('regional.bxmCode')}</th><th>{t('regional.localCode')}</th><th>{t('regional.colOffice')}</th><th>{t('regional.itTeam')}</th><th /></tr></thead><tbody>{routes.map(r => <tr key={r.id} className="border-b border-slate-100 dark:border-slate-800"><td className="p-2 font-mono">{r.bxm_code}</td><td className="font-mono">{r.local_code || t('regional.common')}</td><td>{r.name}</td><td>{config?.teams.find(team => team.id === r.team_id)?.name}</td><td><button className="p-2 text-brand-600" onClick={() => { setEditId(r.id); setOffice({ bxm_code: r.bxm_code, local_code: r.local_code, name: r.name, team_id: String(r.team_id) }); }}>{t('regional.edit')}</button></td></tr>)}</tbody></table></div>
          {!routes.length && <p className="mt-3 text-sm text-slate-500">{t('regional.noRoutes')}</p>}
        </section>
        <section className={card}>
          <h2 className="mb-3 font-bold">{t('regional.membersTitle')}</h2>
          <p className="mb-3 text-sm text-slate-500">{t('regional.membersHint')}</p>
          <form className="grid gap-3 md:grid-cols-4" onSubmit={e => { e.preventDefault(); void mutate(() => axiosClient.post('/regional-support/members', { team_id: Number(memberTeam), user_id: Number(memberUser), role: memberRole }), t('regional.memberSaved')); }}>
            <label className="space-y-1 text-sm">{t('regional.staffSearch')}<input className={control} value={staffSearch} onChange={e => setStaffSearch(e.target.value)} placeholder={t('regional.staffSearchPlaceholder')} /></label>
            <label className="space-y-1 text-sm">{t('regional.staff')}<select required className={control} value={memberUser} onChange={e => setMemberUser(e.target.value)}><option value="">{t('regional.select')}</option>{staff.filter(s => `${s.username} ${s.firstName || ''} ${s.lastName || ''}`.toLowerCase().includes(staffSearch.toLowerCase())).map(s => <option key={s.id} value={s.id}>{s.username}</option>)}</select></label>
            <label className="space-y-1 text-sm">{t('regional.itTeam')}<select required className={control} value={memberTeam} onChange={e => setMemberTeam(e.target.value)}><option value="">{t('regional.select')}</option>{teams.filter(team => team.region_id).map(team => <option key={team.id} value={team.id}>{team.name}</option>)}</select></label>
            <label className="space-y-1 text-sm">{t('regional.role')}<select className={control} value={memberRole} onChange={e => setMemberRole(e.target.value)}><option value="Regional Support">{t('regional.roleSupport')}</option><option value="Regional Admin">{t('regional.roleAdmin')}</option></select></label>
            <button disabled={busy} className={button}>{t('regional.assign')}</button>
          </form>
          <ul className="mt-4 space-y-2 text-sm">{config?.members.filter(m => teams.some(team => team.id === m.team_id)).map(m => <li className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-slate-50 p-3 dark:bg-slate-800" key={`${m.team_id}-${m.user_id}`}><span>{m.username} · {config.teams.find(team => team.id === m.team_id)?.name} · {t(m.is_lead ? 'regional.lead' : 'regional.member')}</span><button disabled={busy} className="text-red-600" onClick={() => void mutate(() => axiosClient.delete(`/regional-support/teams/${m.team_id}/members/${m.user_id}`), t('regional.memberRemoved'))}>{t('regional.removeMember')}</button></li>)}</ul>
          {memberUser && <form className="mt-4 flex flex-wrap items-end gap-3 border-t pt-4" onSubmit={e => { e.preventDefault(); void mutate(() => axiosClient.put(`/regional-support/users/${memberUser}/identity`, { bxm_code: staffBxm, local_code: staffLocal }), t('regional.identitySaved')); }}>
            <label className="space-y-1 text-sm">{t('regional.staffBxm')}<input required maxLength={32} className={control} value={staffBxm} onChange={e => setStaffBxm(e.target.value)} /></label>
            <label className="space-y-1 text-sm">{t('regional.staffLocal')}<input maxLength={32} className={control} value={staffLocal} onChange={e => setStaffLocal(e.target.value)} /></label>
            <button disabled={busy} className={button}>{t('regional.saveIdentity')}</button>
            <p className="w-full text-xs text-slate-500">{t('regional.identityHint')}</p>
          </form>}
        </section>
        <section className={card}>
          <h2 className="mb-3 font-bold">{t('regional.biTitle')}</h2>
          <p className="mb-3 text-sm text-slate-500">{t('regional.biHint')}</p>
          <div className="grid gap-2 md:grid-cols-3">{config?.teams.filter(team => !team.region_id).map(team => <label key={team.id} className="flex items-center gap-2 text-sm"><input type="checkbox" disabled={busy} checked={Boolean(team.republic_only)} onChange={e => void mutate(() => axiosClient.put(`/teams/${team.id}`, { republic_only: e.target.checked }), t('regional.biSaved'))} />{team.name}</label>)}</div>
        </section>
        {!!config?.unmapped_count && <section className={card}>
          <h2 className="mb-3 font-bold text-amber-600">{t('regional.unmappedTitle', { count: config.unmapped_count })}</h2>
          <p className="mb-3 text-sm text-slate-500">{t('regional.unmappedHint')}</p>
          {config.unmapped.map(row => <div className="flex flex-wrap items-center justify-between gap-2 border-b py-3 text-sm" key={row.id}><Link className="text-brand-600" to={`/task/${row.id}`}>{row.ticket_no} · {row.subject} · BXM: {row.bxm_code || '—'} / {row.local_code || '—'}</Link><button className={button} disabled={busy} onClick={() => void mutate(() => axiosClient.post(`/regional-support/tickets/${row.id}/reroute`), t('regional.rerouted'))}>{t('regional.reroute')}</button></div>)}
        </section>}
      </>}
    </main>
  );
};
