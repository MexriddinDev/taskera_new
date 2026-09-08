import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { Clock, Plus, Search, Download, RefreshCw, Shield, Calendar, Activity, BarChart3, Layers, Pencil, Archive, ArrowRight, CheckCircle2, AlertTriangle, ChevronLeft, ChevronRight } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useT, type TFunction } from '@/shared/presentation/i18n/i18n';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { SlaPolicyWizard } from '@/modules/sla/SlaPolicyWizard';
import { SlaCalendarEditor, type CalendarData } from '@/modules/sla/SlaCalendarEditor';
import { api, buttonClass, dateTime, emptyOptions, fieldClass, metrics, minutesText, panelClass, primaryClass, remainingText, riskClass, riskNames, statusNames, type Instance, type MonitoringSummary, type Options, type Policy, type Reports, type RiskLevel } from '@/modules/sla/types';

const tabs = [{ id: 'policies', name: 'sla.tabRules', icon: Shield }, { id: 'targets', name: 'sla.tabTargets', icon: Clock }, { id: 'escalations', name: 'sla.tabEscalations', icon: Layers }, { id: 'calendars', name: 'sla.tabCalendars', icon: Calendar }, { id: 'monitoring', name: 'sla.tabMonitoring', icon: Activity }, { id: 'reports', name: 'sla.tabReports', icon: BarChart3 }];
export function SlaPoliciesPage() {
  const t = useT();
  const { can } = useCan();
  const manage = can('sla.manage');
  const [tab, setTab] = useState('policies');
  const [policies, setPolicies] = useState<Policy[]>([]);
  const [options, setOptions] = useState<Options>(emptyOptions);
  const [calendars, setCalendars] = useState<CalendarData[]>([]);
  const [instances, setInstances] = useState<Instance[]>([]);
  const [summary, setSummary] = useState<MonitoringSummary | null>(null);
  const [level, setLevel] = useState('');
  const [priority, setPriority] = useState('');
  const [assignee, setAssignee] = useState('');
  const [reports, setReports] = useState<Reports | null>(null);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [category, setCategory] = useState('');
  const [metric, setMetric] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [wizard, setWizard] = useState<{ policy?: Policy } | null>(null);
  const [calendarEditor, setCalendarEditor] = useState<{ calendar?: CalendarData } | null>(null);
  const [refresh, setRefresh] = useState(0);
  const [exporting, setExporting] = useState(false);
  const filters = { category_id: category || undefined, metric: metric || undefined, from: from || undefined, to: to || undefined };
  const reload = useCallback(() => setRefresh(n => n + 1), []);
  useEffect(() => {
    let cancelled = false;
    Promise.all([axiosClient.get(`${api}/options`), manage ? axiosClient.get(`${api}/reports`, { params: filters }) : Promise.resolve(null)]).then(([o, r]) => {
      if (!cancelled) { setOptions(o.data.data); setReports(r?.data.data || null); }
    }).catch(() => { if (!cancelled) setError(t('sla.optionsError')); });
    return () => { cancelled = true; };
  }, [refresh, manage, category, metric, from, to]);
  useEffect(() => {
    const controller = new AbortController();
    const timer = window.setTimeout(async () => {
      setLoading(true); setError('');
      try {
        if (tab === 'calendars') {
          const { data } = await axiosClient.get('/business-calendars', { params: { page, per_page: 20 }, signal: controller.signal });
          setCalendars(data.data); setTotal(data.meta.total); setLastPage(data.meta.last_page);
        } else if (tab === 'monitoring') {
          const { data } = await axiosClient.get(`${api}/monitoring`, { params: { ...filters, page, status: status || undefined, level: level || undefined, priority_id: priority || undefined, assigned_user_id: assignee || undefined }, signal: controller.signal });
          setInstances(data.data); setTotal(data.total); setLastPage(data.last_page); setSummary(data.summary);
        } else if (tab !== 'reports') {
          const { data } = await axiosClient.get(`${api}/policies`, { params: { page, search: search || undefined, status: status || undefined }, signal: controller.signal });
          setPolicies(data.data); setTotal(data.total); setLastPage(data.last_page);
        }
      } catch (e: any) { if (!controller.signal.aborted) setError(e.response?.data?.message || t('sla.loadError')); }
      finally { if (!controller.signal.aborted) setLoading(false); }
    }, 250);
    return () => { clearTimeout(timer); controller.abort(); };
  }, [tab, page, search, status, category, metric, from, to, level, priority, assignee, refresh]);
  useEffect(() => {
    if (tab !== 'monitoring') return;
    const timer = window.setInterval(reload, 30000);
    return () => window.clearInterval(timer);
  }, [tab, reload]);
  const exportCsv = async () => {
    setExporting(true);
    try {
      const response = await axiosClient.get(`${api}/export`, { params: filters, responseType: 'blob' });
      const url = URL.createObjectURL(response.data); const a = document.createElement('a'); a.href = url; a.download = 'sla-report.csv'; a.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch { useToastStore.getState().error(t('sla.exportError')); } finally { setExporting(false); }
  };
  const archive = async (policy: Policy) => {
    if (!window.confirm(t('sla.archiveConfirm', { name: policy.name }))) return;
    try { await axiosClient.post(`${api}/policies/${policy.id}/archive`); reload(); } catch { useToastStore.getState().error(t('sla.archiveError')); }
  };
  const changeTab = (id: string) => { setTab(id); setPage(1); setSearch(''); setStatus(''); setLevel(''); };
  const statCards = [
    { title: t('sla.cardActive'), value: reports?.active_policies, icon: Shield, color: 'text-emerald-500 bg-emerald-500/10' },
    { title: t('sla.cardRunning'), value: reports?.running, icon: Clock, color: 'text-blue-500 bg-blue-500/10' },
    { title: t('sla.cardBreached'), value: reports?.breached, icon: AlertTriangle, color: 'text-amber-500 bg-amber-500/10' },
    { title: t('sla.cardPaused'), value: reports?.paused, icon: Activity, color: 'text-purple-500 bg-purple-500/10' },
  ];
  return <div className="w-full px-4 sm:px-7 py-6 space-y-5 text-slate-900 dark:text-slate-100">
    <header className="flex flex-wrap justify-between items-center gap-4"><div><p className="text-xs text-slate-500 mb-2">{t('sla.breadcrumb')} <span className="mx-2">›</span> {t('sla.pageTitle')}</p><h1 className="flex items-center gap-3 text-3xl font-bold"><Clock className="text-cyan-500" size={30}/>{t('sla.pageTitle')}</h1><p className="text-sm text-slate-500 dark:text-slate-400 mt-2">{t('sla.pageSubtitle')}</p></div><div className="flex gap-2"><button aria-label={t('sla.refresh')} className={buttonClass} onClick={reload}><RefreshCw size={17} className={loading ? 'animate-spin' : ''}/></button>{manage && <><button className={buttonClass} disabled={exporting} onClick={exportCsv}><Download size={17}/>{exporting ? t('sla.exporting') : t('sla.export')}</button><button className={primaryClass} onClick={() => tab === 'calendars' ? setCalendarEditor({}) : setWizard({})}><Plus size={18}/>{tab === 'calendars' ? t('sla.newCalendarButton') : t('sla.newRule')}</button></>}</div></header>
    {manage && <div className="grid sm:grid-cols-2 xl:grid-cols-5 gap-3">{statCards.map(card => <div key={card.title} className={`${panelClass} flex gap-3 items-center p-4`}><span className={`p-3 rounded-xl ${card.color}`}><card.icon size={22}/></span><div><p className="text-2xl font-bold">{card.value ?? '—'}</p><p className="text-xs text-slate-500 dark:text-slate-400">{card.title}</p></div></div>)}<div className={`${panelClass} p-4`}><p className="text-xs text-slate-500 dark:text-slate-400">{t('sla.compliance')}</p><p className="text-2xl font-bold mt-1">{reports?.compliance == null ? '—' : `${reports.compliance}%`}</p><div className="h-1.5 bg-slate-200 dark:bg-slate-800 rounded-full mt-3 overflow-hidden"><div className="h-full rounded-full bg-emerald-400" style={{ width: `${reports?.compliance || 0}%` }}/></div></div></div>}
    <nav className="flex overflow-x-auto gap-1 border-b border-slate-200 dark:border-slate-700">{tabs.filter(item => manage || !['monitoring', 'reports'].includes(item.id)).map(item => <button key={item.id} onClick={() => changeTab(item.id)} className={`shrink-0 flex gap-2 items-center px-4 py-3 text-sm border-b-2 transition-colors ${tab === item.id ? 'border-cyan-400 bg-cyan-500/10 text-cyan-600 dark:text-cyan-400' : 'border-transparent text-slate-500 hover:text-cyan-500'}`}><item.icon size={17}/>{t(item.name)}</button>)}</nav>
    {error && <div role="alert" className="rounded-xl p-4 border border-red-500/30 bg-red-500/10 text-red-500">{error}<button className="ml-4 underline" onClick={reload}>{t('sla.retry')}</button></div>}
    <section className={`${panelClass} overflow-hidden`}>
      <div className="flex flex-wrap gap-3 p-3 border-b border-slate-200 dark:border-slate-800">
        {['policies', 'targets', 'escalations'].includes(tab) && <div className="relative flex-1 min-w-56"><Search size={17} className="absolute top-3 left-3 text-slate-400"/><input aria-label={t('sla.searchAria')} className={`${fieldClass} !pl-10`} placeholder={t('sla.searchPlaceholder')} value={search} onChange={e => { setSearch(e.target.value); setPage(1); }}/></div>}
        {['policies', 'monitoring'].includes(tab) && <select aria-label={t('sla.filterStatus')} className={`${fieldClass} !w-auto`} value={status} onChange={e => { setStatus(e.target.value); setPage(1); }}><option value="">{t('sla.allStatuses')}</option>{(tab === 'policies' ? ['ACTIVE', 'DRAFT', 'ARCHIVED'] : ['RUNNING', 'BREACHED', 'PAUSED', 'PENDING', 'COMPLETED', 'EXCLUDED']).map(s => <option key={s} value={s}>{t(statusNames[s])}</option>)}</select>}
        {['monitoring', 'reports'].includes(tab) && <><select aria-label={t('sla.filterCategory')} className={`${fieldClass} !w-auto`} value={category} onChange={e => { setCategory(e.target.value); setPage(1); }}><option value="">{t('sla.allCategories')}</option>{options.categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}</select><select aria-label={t('sla.filterMetric')} className={`${fieldClass} !w-auto`} value={metric} onChange={e => { setMetric(e.target.value); setPage(1); }}><option value="">{t('sla.allMetrics')}</option>{Object.entries(metrics).map(([key, label]) => <option key={key} value={key}>{t(label)}</option>)}</select><input aria-label={t('sla.dateFrom')} type="date" className={`${fieldClass} !w-auto`} value={from} onChange={e => { setFrom(e.target.value); setPage(1); }}/><input aria-label={t('sla.dateTo')} type="date" className={`${fieldClass} !w-auto`} value={to} onChange={e => { setTo(e.target.value); setPage(1); }}/></>}
        {tab === 'monitoring' && <><select aria-label={t('sla.filterPriority')} className={`${fieldClass} !w-auto`} value={priority} onChange={e => { setPriority(e.target.value); setPage(1); }}><option value="">{t('sla.allPriorities')}</option>{options.priorities.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}</select><select aria-label={t('sla.filterAssignee')} className={`${fieldClass} !w-auto`} value={assignee} onChange={e => { setAssignee(e.target.value); setPage(1); }}><option value="">{t('sla.allAssignees')}</option>{options.users.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}</select></>}
        <button className={buttonClass} onClick={() => { setSearch(''); setStatus(''); setCategory(''); setMetric(''); setFrom(''); setTo(''); setLevel(''); setPriority(''); setAssignee(''); setPage(1); }}>{t('sla.clear')}</button>
      </div>
      {loading ? <div className="p-12 text-center text-slate-500">{t('sla.loading')}</div> : <>
        {tab === 'policies' && <div className="overflow-x-auto"><table className="w-full text-sm text-left"><thead className="bg-slate-100 dark:bg-slate-800/70 text-xs text-slate-500 dark:text-slate-400"><tr>{['sla.thCode', 'sla.thName', 'sla.thCategory', 'sla.thAcceptance', 'sla.thResolution', 'sla.thVersion', 'sla.thStatus', 'sla.thActions'].map(h => <th key={h} className="px-4 py-3 font-medium whitespace-nowrap">{t(h)}</th>)}</tr></thead><tbody className="divide-y divide-slate-200 dark:divide-slate-800">{policies.map(p => <tr key={p.id} className="hover:bg-slate-100 dark:hover:bg-slate-800/40"><td className="p-4 font-mono text-cyan-600 dark:text-cyan-400">{p.code}</td><td className="p-4 font-medium">{p.name}</td><td className="p-4 text-xs">{options.categories.find(c => c.id === p.draft_config?.scope.category_id)?.name || t('sla.all')}</td>{['ACCEPTANCE', 'RESOLUTION'].map(m => <td key={m} className="p-4 whitespace-nowrap">{p.draft_config?.targets.find(x => x.metric === m) ? minutesText(t, p.draft_config.targets.find(x => x.metric === m)!.minutes) : t('sla.notConfigured')}</td>)}<td className="p-4">v{p.version}</td><td className="p-4"><span className={`rounded-full px-2 py-1 text-xs ${p.publication_status === 'ACTIVE' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-amber-500/10 text-amber-500'}`}>{t(statusNames[p.publication_status])}</span></td><td className="p-4">{manage && <div className="flex gap-3"><button title={t('sla.edit')} aria-label={t('sla.editAria', { name: p.name })} onClick={() => setWizard({ policy: p })}><Pencil size={16}/></button>{p.publication_status !== 'ARCHIVED' && <button title={t('sla.archive')} aria-label={t('sla.archiveAria', { name: p.name })} onClick={() => archive(p)}><Archive size={16}/></button>}</div>}</td></tr>)}</tbody></table>{!policies.length && <Empty text={t('sla.emptyPolicies')}/>}</div>}
        {['targets', 'escalations'].includes(tab) && <div className="grid md:grid-cols-2 xl:grid-cols-3 gap-4 p-4">{policies.map(p => <article key={p.id} className={`${panelClass} p-4 space-y-3`}><div className="flex justify-between gap-3"><h3 className="font-semibold">{p.name}</h3>{manage && <button aria-label={t('sla.edit')} onClick={() => setWizard({ policy: p })}><Pencil size={16}/></button>}</div><p className="font-mono text-xs text-cyan-500">{p.code}</p>{tab === 'targets' ? p.draft_config?.targets.map(target => <div key={target.metric} className="flex justify-between gap-3 text-sm"><span className="text-slate-500">{t(metrics[target.metric])}</span><span>{minutesText(t, target.minutes)}</span></div>) : p.draft_config?.escalations.map((rule, index) => <div key={index} className="p-2 rounded bg-amber-500/10 text-sm"><strong>{rule.threshold}%</strong>{rule.assignee ? ` · ${t('sla.toAssignee')}` : ''}{rule.user_ids.length ? ` · +${rule.user_ids.length}` : ''}<p className="text-xs text-slate-500 mt-1">{rule.channels.map(c => t(c === 'EMAIL' ? 'sla.channelEmail' : c === 'TELEGRAM' ? 'sla.channelTelegram' : 'sla.channelInApp')).join(', ')}</p></div>)}{!p.draft_config && <p className="text-sm text-slate-500">{t('sla.ruleNotConfigured')}</p>}</article>)}{!policies.length && <Empty text={t('sla.emptyRules')}/>}</div>}
        {tab === 'calendars' && <div className="grid md:grid-cols-2 xl:grid-cols-3 gap-4 p-4">{calendars.map(c => <article key={c.id} className={`${panelClass} p-5 space-y-3`}><div className="flex items-center justify-between"><Calendar className="text-cyan-500"/>{manage && <button aria-label={t('sla.editCalendarAria')} onClick={() => setCalendarEditor({ calendar: c })}><Pencil size={17}/></button>}</div><h3 className="font-bold">{c.name}</h3><p className="text-xs text-cyan-500">{options.timezones.find(t => t.id === c.timezone_id)?.name}</p>{c.is_24x7 ? <p>{t('sla.round247')}</p> : c.business_hours.filter(h => h.is_working).map((h, index) => <p key={index} className="flex justify-between text-sm text-slate-500 dark:text-slate-400"><span>{t(`sla.dayShort.${h.weekday}`)}</span>{h.start_time.slice(0, 5)} – {h.end_time.slice(0, 5)}</p>)}<p className="text-xs text-slate-500">{t('sla.holidaysCount', { count: c.holidays.length })}</p></article>)}{!calendars.length && <Empty text={t('sla.emptyCalendars')}/>}</div>}
        {tab === 'monitoring' && <>
          <div className="p-4 border-b border-slate-200 dark:border-slate-800">
            <div className="flex flex-wrap items-center gap-3">
              <p className="text-sm font-bold uppercase tracking-wide">{t('sla.checkNow', { count: summary?.total ?? 0 })}</p>
              {level && <button className="text-xs text-cyan-500 underline" onClick={() => { setLevel(''); setPage(1); }}>{t('sla.removeFilter')}</button>}
            </div>
            <div className="grid sm:grid-cols-3 gap-3 mt-3">
              {([['BREACHED', summary?.breached], ['HIGH', summary?.high], ['WARNING', summary?.warning]] as const).map(([key, value]) =>
                <button key={key} aria-pressed={level === key} onClick={() => { setLevel(level === key ? '' : key); setPage(1); }}
                  className={`flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-left transition-all ${riskClass[key as RiskLevel]} ${level === key ? 'ring-2 ring-offset-2 ring-offset-transparent ring-current' : ''}`}>
                  <span className="text-sm font-semibold">{t(riskNames[key as RiskLevel])}</span>
                  <span className="text-2xl font-bold">{value ?? 0}</span>
                </button>)}
            </div>
          </div>
          <div className="overflow-x-auto"><table className="w-full text-sm text-left"><thead className="bg-slate-100 dark:bg-slate-800/70"><tr>{['sla.thTicket', 'sla.thCategoryAssignee', 'sla.thMetric', 'sla.thDeadline', 'sla.thRemaining', 'sla.thStatus', 'sla.thEscalation'].map(h => <th key={h} className="p-4 text-xs font-medium text-slate-500">{t(h)}</th>)}</tr></thead><tbody className="divide-y divide-slate-200 dark:divide-slate-800">{instances.map(i => <tr key={i.id}><td className="p-4"><Link to={`/task/${i.ticket_id}`} className="text-cyan-500 font-semibold">{i.ticket_no}</Link><p className="text-xs text-slate-500 max-w-56 truncate">{i.subject}</p></td><td className="p-4 text-xs">{i.category || '—'}<p className="text-slate-500 mt-1">{i.assignee || t('sla.unassigned')}</p></td><td className="p-4">{t(metrics[i.metric])}</td><td className="p-4 text-xs font-mono whitespace-nowrap">{dateTime(i.due_at)}</td><td className="p-4 text-xs whitespace-nowrap"><span className={(i.remaining_seconds ?? 0) < 0 ? 'text-red-500 font-semibold' : ''}>{remainingText(t, i.remaining_seconds)}</span>{i.percent != null && <div className="mt-1.5 h-1.5 w-24 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden"><div className={`h-full rounded-full ${i.level === 'BREACHED' ? 'bg-red-500' : i.level === 'HIGH' ? 'bg-orange-500' : i.level === 'WARNING' ? 'bg-amber-400' : 'bg-emerald-400'}`} style={{ width: `${Math.min(100, i.percent)}%` }}/></div>}</td><td className="p-4 text-xs"><RiskBadge instance={i} t={t}/><p className="text-slate-500 mt-1">{t(statusNames[i.status])}</p></td><td className="p-4">{i.escalation_index}</td></tr>)}</tbody></table>{!instances.length && <Empty text={t('sla.emptyMonitoring')}/>}</div>
        </>}
        {tab === 'reports' && reports && <div className="p-5 space-y-5"><p className="text-sm text-slate-500">{t('sla.reportsNote')}</p><div className="grid sm:grid-cols-4 gap-3">{[[t('sla.avg'), reports.average_minutes], [t('sla.median'), reports.median_minutes], [t('sla.p90'), reports.p90_minutes], [t('sla.p95'), reports.p95_minutes]].map(([label, value]) => <div key={label} className={`${panelClass} p-4`}><p className="text-xs text-slate-500">{label}</p><p className="text-xl font-semibold mt-1">{value ?? '—'} <span className="text-xs">{t('sla.minutesShort')}</span></p></div>)}</div>{([[t('sla.groupMetrics'), reports.metrics, null], [t('sla.groupCategories'), reports.categories, options.categories], [t('sla.groupEmployees'), reports.employees, options.users], [t('sla.groupDepartments'), reports.departments, options.departments]] as const).map(([label, groups, list]) => <div key={label}><h3 className="font-semibold mb-3">{label}</h3><div className="overflow-x-auto"><table className="w-full text-sm text-left"><thead><tr>{['sla.thTitle', 'sla.thTotal', 'sla.thCompleted', 'sla.thBreached', 'sla.thCompliance'].map(h => <th className="p-2 text-xs text-slate-500" key={h}>{t(h)}</th>)}</tr></thead><tbody>{Object.entries(groups).map(([key, s]) => <tr key={key} className="border-t border-slate-200 dark:border-slate-800"><td className="p-2">{list ? list.find(o => String(o.id) === key)?.name || t('sla.undefined') : t(metrics[key as keyof typeof metrics])}</td><td className="p-2">{s.total}</td><td className="p-2">{s.completed}</td><td className="p-2 text-red-500">{s.breached}</td><td className="p-2">{s.compliance == null ? '—' : `${s.compliance}%`}</td></tr>)}</tbody></table></div></div>)}</div>}
      </>}
      {tab !== 'reports' && <footer className="flex justify-between items-center gap-3 px-4 py-3 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500"><span>{t('sla.totalPage', { total, page })}</span><div className="flex items-center gap-2"><button aria-label={t('sla.prevPage')} disabled={page <= 1 || loading} className={buttonClass} onClick={() => setPage(p => p - 1)}><ChevronLeft size={16}/></button><span className="px-3 py-2 rounded-lg bg-cyan-500 text-white">{page}</span><button aria-label={t('sla.nextPage')} disabled={page >= lastPage || loading} className={buttonClass} onClick={() => setPage(p => p + 1)}><ChevronRight size={16}/></button></div></footer>}
    </section>
    <div className="grid lg:grid-cols-[3fr_2fr] gap-5"><section className={`${panelClass} p-5`}><h2 className="font-semibold mb-6">{t('sla.lifecycleTitle')}</h2><div className="flex items-start justify-between gap-2">{[{ label: t('sla.lifecycle1'), icon: Plus, color: 'bg-blue-500/15 text-blue-500' }, { label: t('sla.lifecycle2'), icon: Shield, color: 'bg-amber-500/15 text-amber-500' }, { label: t('sla.lifecycle3'), icon: Activity, color: 'bg-purple-500/15 text-purple-500' }, { label: t('sla.lifecycle4'), icon: CheckCircle2, color: 'bg-emerald-500/15 text-emerald-500' }].map((s, i) => <div key={s.label} className="flex flex-1 items-center gap-1"><div className="flex-1 text-center"><span className={`mx-auto flex w-12 h-12 rounded-full items-center justify-center ${s.color}`}><s.icon size={24}/></span><p className="text-xs font-semibold mt-3">{i + 1}. {s.label}</p></div>{i < 3 && <ArrowRight className="text-slate-400 shrink-0" size={18}/>}</div>)}</div></section><section className={`${panelClass} p-5 space-y-3`}><h2 className="font-semibold">{t('sla.flowTitle')}</h2><p className="text-sm text-slate-500 dark:text-slate-400">{t('sla.flowText')}</p><p className="text-sm text-cyan-600 dark:text-cyan-400">{t('sla.flowChain')}</p><p className="text-xs text-slate-500">{t('sla.flowNote')}</p></section></div>
    {wizard && <SlaPolicyWizard policy={wizard.policy} options={options} onClose={() => setWizard(null)} onSaved={() => { setWizard(null); reload(); }}/>} {calendarEditor && <SlaCalendarEditor calendar={calendarEditor.calendar} timezones={options.timezones} onClose={() => setCalendarEditor(null)} onSaved={() => { setCalendarEditor(null); reload(); }}/>} 
  </div>;
}
function Empty({ text }: { text: string }) { return <div className="p-12 text-center text-slate-500 text-sm">{text}</div>; }
/**
 * Risk nishoni. Yakunlangan yoki hisobdan chiqarilgan taymerda risk darajasi
 * bo‘lmaydi — o‘shanda holatning o‘zi (buzilgan bo‘lsa qizil) ko‘rsatiladi.
 */
function RiskBadge({ instance, t }: { instance: Instance; t: TFunction }) {
  const risk = (['OK', 'WARNING', 'HIGH', 'BREACHED'] as RiskLevel[]).find(l => l === instance.level);
  if (risk) return <span className={`rounded-full border px-2 py-1 ${riskClass[risk]}`}>{t(riskNames[risk])}</span>;
  const breached = Boolean(instance.breached_at);
  return <span className={`rounded-full border px-2 py-1 ${breached ? riskClass.BREACHED : 'text-slate-500 bg-slate-500/10 border-slate-500/30'}`}>{t(breached ? 'sla.risk.BREACHED' : statusNames[instance.status])}</span>;
}
