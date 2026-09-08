import { useEffect, useState } from 'react';
import { Clock, History, Pause, Play, Plus, RefreshCw } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useT } from '@/shared/presentation/i18n/i18n';
import { SlaDialog } from './SlaDialog';
import { Field, Select } from './SlaPolicyWizard';
import { api, buttonClass, dateTime, fieldClass, metrics, minutesText, panelClass, primaryClass, statusNames, type Config, type Instance, type Option } from './types';
type Extension = { id: number; instance_id: number; minutes: number; reason: string; status: string; approver_id: number; requested_by: number };
type TicketData = { runs: { id: number; number: number; cycle: number; config: Config; ended_at: string | null }[]; instances: Instance[];
  events: { id: number; instance_id: number; event_type: string; created_at: string; data: string | object }[]; extensions: Extension[]; can_operate: boolean; approvers: Option[] };
export function TicketSlaPanel({ ticketId, revision }: { ticketId: string | number; revision?: string }) {
  const t = useT();
  const { user } = useCan();
  const [data, setData] = useState<TicketData | null>(null);
  const [error, setError] = useState('');
  const [refresh, setRefresh] = useState(0);
  const [dialog, setDialog] = useState<'history' | 'pause' | 'extension' | null>(null);
  const [busy, setBusy] = useState(false);
  const [instanceId, setInstanceId] = useState<number | null>(null);
  const [minutes, setMinutes] = useState(60);
  const [reason, setReason] = useState('');
  const [reasonCode, setReasonCode] = useState('COMPLEXITY');
  const [approver, setApprover] = useState<number | null>(null);
  const [pauseStatus, setPauseStatus] = useState('WAITING_USER');
  useEffect(() => {
    const controller = new AbortController();
    const load = () => axiosClient.get(`${api}/tickets/${ticketId}`, { signal: controller.signal }).then(r => { setData(r.data.data); setError(''); }).catch(e => { if (!controller.signal.aborted) setError(e.response?.data?.message || t('sla.panelError')); });
    load(); const timer = window.setInterval(load, 30000);
    return () => { controller.abort(); clearInterval(timer); };
  }, [ticketId, refresh, revision]);
  const submit = async (url: string, body = {}) => {
    setBusy(true); setError('');
    try { await axiosClient.post(url, body); setDialog(null); setReason(''); setRefresh(n => n + 1); }
    catch (e: any) { setError(Object.values(e.response?.data?.errors || {}).flat().join('\n') || e.response?.data?.message || t('sla.actionFailed')); }
    finally { setBusy(false); }
  };
  const run = data?.runs[0];
  const current = data?.instances.filter(i => i.run_id === run?.id) || [];
  const active = current.filter(i => ['RUNNING', 'BREACHED', 'PAUSED'].includes(i.status) && i.metric !== 'UPDATE');
  const paused = current.some(i => i.status === 'PAUSED');
  return <section className={`${panelClass} p-5 space-y-4`}><header className="flex justify-between items-center gap-3"><h2 className="font-bold flex items-center gap-2"><Clock className="text-cyan-500" size={20}/>{t('sla.panelTitle')}</h2><div className="flex gap-3"><button aria-label={t('sla.panelRefresh')} onClick={() => setRefresh(n => n + 1)}><RefreshCw size={16}/></button>{run && <button className="flex gap-1 items-center text-sm text-cyan-500" onClick={() => setDialog('history')}><History size={16}/>{t('sla.panelHistory')}</button>}</div></header>
    {error && <p role="alert" className="text-sm text-red-500 whitespace-pre-line">{error}</p>}
    {!run ? <p className="text-sm text-slate-500">{t(data ? 'sla.panelNoRun' : 'sla.panelLoading')}</p> : <><p className="text-xs text-slate-500">{run.config.name} · v{run.number} · {t('sla.cycle', { cycle: run.cycle })} · {run.config.calendar?.name}</p><div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-3">{current.map(i => <div key={i.id} className="border border-slate-200 dark:border-slate-700 rounded-xl p-3"><div className="flex justify-between gap-2 text-xs"><strong>{t(metrics[i.metric])}</strong><span className={i.breached_at ? 'text-red-500' : 'text-cyan-500'}>{t(statusNames[i.status])}</span></div><p className="text-sm mt-3 font-mono">{minutesText(t, i.target_minutes)}{i.extension_minutes > 0 && ` + ${minutesText(t, i.extension_minutes)}`}</p><p className="text-xs text-slate-500 mt-2">{t('sla.deadlineLabel', { value: dateTime(i.due_at) })}</p>{i.breached_at && <p className="text-xs text-red-500 mt-1">{t('sla.breachedAtLabel', { value: dateTime(i.breached_at) })}</p>}{i.status === 'PAUSED' && <p className="text-xs text-amber-500 mt-1">{t('sla.remainingWork', { minutes: Math.round((i.remaining_seconds || 0) / 60) })}</p>}</div>)}</div>
      {data?.can_operate && active.length > 0 && <div className="flex flex-wrap gap-2">{paused ? <button disabled={busy} className={buttonClass} onClick={() => submit(`${api}/tickets/${ticketId}/resume`)}><Play size={16}/>{t('sla.resume')}</button> : run.config.pause_statuses.length > 0 && <button className={buttonClass} onClick={() => { setPauseStatus(run.config.pause_statuses[0]); setDialog('pause'); }}><Pause size={16}/>{t('sla.pause')}</button>}{run.config.extension.enabled && <button className={buttonClass} onClick={() => { setInstanceId(active[0].id); setDialog('extension'); }}><Plus size={16}/>{t('sla.requestExtension')}</button>}</div>}
      {data?.extensions.filter(e => e.status === 'PENDING').map(e => <div key={e.id} className="p-3 rounded-lg bg-amber-500/10 text-sm"><p>{t('sla.extensionRequest', { minutes: minutesText(t, e.minutes), reason: e.reason })}</p>{Number(e.approver_id) === Number(user?.id) && <div className="flex gap-2 mt-2"><button disabled={busy} className={buttonClass} onClick={() => { const answer = window.prompt(t('sla.approvePrompt')); if (answer) submit(`${api}/extensions/${e.id}/decision`, { approve: true, reason: answer }); }}>{t('sla.approve')}</button><button disabled={busy} className={buttonClass} onClick={() => { const answer = window.prompt(t('sla.rejectPrompt')); if (answer) submit(`${api}/extensions/${e.id}/decision`, { approve: false, reason: answer }); }}>{t('sla.reject')}</button></div>}</div>)}
    </>}
    {dialog && <SlaDialog title={t(dialog === 'history' ? 'sla.dialogHistory' : dialog === 'pause' ? 'sla.dialogPause' : 'sla.dialogExtension')} onClose={() => !busy && setDialog(null)}><div className="p-5 overflow-y-auto flex-1 space-y-4">{error && <p role="alert" className="text-red-500">{error}</p>}{dialog === 'history' ? data?.events.map(e => <div key={e.id} className="border-b border-slate-200 dark:border-slate-700 pb-3 text-sm"><div className="flex justify-between gap-3"><strong>{e.event_type}</strong><span className="text-slate-500 text-xs">{dateTime(e.created_at)}</span></div><p className="text-xs text-slate-500 mt-1">{t(metrics[data.instances.find(i => i.id === e.instance_id)?.metric || 'RESOLUTION'])}</p><pre className="whitespace-pre-wrap break-words text-xs mt-2">{JSON.stringify(typeof e.data === 'string' ? JSON.parse(e.data) : e.data, null, 2)}</pre></div>) : <><Field label={t('sla.reasonRequired')}><textarea className={fieldClass} rows={3} value={reason} onChange={e => setReason(e.target.value)}/></Field>{dialog === 'pause' ? <Field label={t('sla.waitingStatus')}><select className={fieldClass} value={pauseStatus} onChange={e => setPauseStatus(e.target.value)}>{run?.config.pause_statuses.map(s => <option key={s} value={s}>{t(s === 'WAITING_USER' ? 'sla.waitingUser' : 'sla.waitingVendor')}</option>)}</select></Field> : <><Field label={t('sla.timerField')}><Select empty={t('sla.selectTimer')} value={instanceId} options={active.map(i => ({ id: i.id, name: t(metrics[i.metric]) }))} onChange={setInstanceId}/></Field><Field label={t('sla.extraMinutes')}><input type="number" min={1} max={run?.config.extension.max_minutes} className={fieldClass} value={minutes} onChange={e => setMinutes(Number(e.target.value))}/></Field><Field label={t('sla.reasonType')}><select className={fieldClass} value={reasonCode} onChange={e => setReasonCode(e.target.value)}><option value="COMPLEXITY">{t('sla.reasonComplexity')}</option><option value="VENDOR">{t('sla.reasonVendor')}</option><option value="PARTS">{t('sla.reasonParts')}</option><option value="OTHER">{t('sla.reasonOther')}</option></select></Field><Field label={t('sla.approver')}><Select empty={t('sla.selectApprover')} options={(data?.approvers || []).filter(u => Number(u.id) !== Number(user?.id))} value={approver} onChange={setApprover}/></Field></>}</>}</div>{dialog !== 'history' && <footer className="p-4 flex justify-end border-t border-slate-200 dark:border-slate-700"><button disabled={busy || reason.trim().length < 5 || (dialog === 'extension' && (!approver || !instanceId))} className={primaryClass} onClick={() => dialog === 'pause' ? submit(`${api}/tickets/${ticketId}/pause`, { reason, status: pauseStatus }) : submit(`${api}/instances/${instanceId}/extensions`, { reason, reason_code: reasonCode, minutes, approver_id: approver })}>{busy ? t('sla.saving') : t('sla.send')}</button></footer>}</SlaDialog>}
  </section>;
}
