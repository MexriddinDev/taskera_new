import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Activity, AlertTriangle, ArrowLeft, Clock, Pencil, Plus, RefreshCw, Search, Trash2, X } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { EmptyState } from '@/shared/presentation/components/EmptyState';

interface Team {
  id: number;
  name: string;
  code: string;
  is_active: boolean;
}

interface Priority {
  id: number;
  code: string;
  name: string;
  color: string | null;
}

interface SlaRule {
  id: number;
  team_id: number;
  team?: Pick<Team, 'id' | 'name' | 'code'>;
  // null — guruhning umumiy qoidasi (barcha muhimliklar uchun).
  priority_id: number | null;
  priority?: Priority | null;
  name: string;
  description: string | null;
  accept_minutes: number;
  work_minutes: number;
  is_active: boolean;
}

interface FormState {
  team_id: number | '';
  priority_id: number | '';
  name: string;
  description: string;
  accept_minutes: number;
  work_minutes: number;
  is_active: boolean;
}

const emptyForm: FormState = {
  team_id: '',
  priority_id: '',
  name: '',
  description: '',
  accept_minutes: 15,
  work_minutes: 30,
  is_active: true,
};

const minutesLabel = (minutes: number) => `${minutes} minut`;

export const SlaPoliciesPage: React.FC = () => {
  const { can } = useCan();
  const manage = can('sla.manage');
  const toast = useToastStore();
  const [rules, setRules] = useState<SlaRule[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [priorities, setPriorities] = useState<Priority[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState<'all' | 'active' | 'passive'>('all');
  const [editing, setEditing] = useState<SlaRule | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [formOpen, setFormOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');

  const fetchData = useCallback(async () => {
    setLoading(true);
    setLoadError(false);
    try {
      const [slaResponse, teamResponse, priorityResponse] = await Promise.all([
        axiosClient.get('/sla-rules', { params: { per_page: 100 } }),
        // SLA uchun alohida qo'lda ro'yxat yuritilmaydi: Guruhlar bo'limining
        // o'z API manbasidan barcha faol guruhlar olinadi.
        axiosClient.get('/teams', { params: { per_page: 100, is_active: 1 } }),
        axiosClient.get('/sla-rules/priorities'),
      ]);
      setRules(slaResponse.data?.data ?? []);
      setTeams(teamResponse.data?.data ?? []);
      setPriorities(priorityResponse.data?.data ?? []);
    } catch {
      setLoadError(true);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const visibleRules = useMemo(() => {
    const needle = search.trim().toLocaleLowerCase();
    return rules.filter((rule) => {
      const matchesStatus = status === 'all' || (status === 'active' ? rule.is_active : !rule.is_active);
      const matchesSearch = !needle || [rule.name, rule.description, rule.team?.name, rule.team?.code, rule.priority?.name]
        .some((value) => value?.toLocaleLowerCase().includes(needle));
      return matchesStatus && matchesSearch;
    });
  }, [rules, search, status]);

  // Guruhlar bo'limidagi barcha faol guruhlar doim ko'rinadi. Avval SLA
  // biriktirilgan guruhlar butunlay yashirilgani uchun ro'yxat bo'sh tuyulardi.
  const availableTeams = teams.filter((team) => team.is_active);

  /**
   * Tanlangan guruh va muhimlik uchun allaqachon mavjud qoidalar.
   *
   * Cheklov yo'q — bir muhimlikka istagancha qoida qo'shsa bo'ladi. Ro'yxat
   * shunchaki ogohlantirish uchun: bir nechta qoida mos kelsa zayavkaga eng
   * qisqa muddatlisi qo'llanadi.
   */
  const siblingRules = useMemo(() => {
    const teamId = Number(form.team_id);
    if (!teamId) return [] as SlaRule[];

    const priorityId = form.priority_id === '' ? null : Number(form.priority_id);
    return rules.filter(
      (rule) => rule.team_id === teamId && (rule.priority_id ?? null) === priorityId && rule.id !== editing?.id
    );
  }, [rules, form.team_id, form.priority_id, editing]);

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm);
    setFormError('');
    setFormOpen(true);
    // Forma har ochilganda Guruhlar bo'limida yangi qo'shilganlarni ham oladi.
    void fetchData();
  };

  const openEdit = (rule: SlaRule) => {
    setEditing(rule);
    setForm({
      team_id: rule.team_id,
      priority_id: rule.priority_id ?? '',
      name: rule.name,
      description: rule.description ?? '',
      accept_minutes: rule.accept_minutes,
      work_minutes: rule.work_minutes,
      is_active: rule.is_active,
    });
    setFormError('');
    setFormOpen(true);
  };

  const save = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!form.team_id || !form.name.trim()) {
      setFormError('Guruh va SLA nomini kiriting.');
      return;
    }
    setSaving(true);
    setFormError('');
    const payload = {
      ...form,
      team_id: Number(form.team_id),
      // Bo'sh tanlov — guruhning umumiy qoidasi.
      priority_id: form.priority_id === '' ? null : Number(form.priority_id),
      name: form.name.trim(),
      description: form.description.trim() || null,
      accept_minutes: Number(form.accept_minutes),
      work_minutes: Number(form.work_minutes),
    };
    try {
      if (editing) await axiosClient.put(`/sla-rules/${editing.id}`, payload);
      else await axiosClient.post('/sla-rules', payload);
      toast.success(editing ? 'SLA muvaffaqiyatli o‘zgartirildi' : 'SLA muvaffaqiyatli yaratildi');
      setFormOpen(false);
      await fetchData();
    } catch (error: any) {
      const errors = error?.response?.data?.errors;
      setFormError(errors ? Object.values(errors).flat().join(' ') : error?.response?.data?.message || 'SLAni saqlashda xatolik yuz berdi.');
    } finally {
      setSaving(false);
    }
  };

  const toggleStatus = async (rule: SlaRule) => {
    try {
      await axiosClient.put(`/sla-rules/${rule.id}`, { is_active: !rule.is_active });
      setRules((current) => current.map((item) => item.id === rule.id ? { ...item, is_active: !item.is_active } : item));
      toast.success(!rule.is_active ? 'SLA aktiv qilindi' : 'SLA passiv qilindi');
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Holatni o‘zgartirib bo‘lmadi.');
    }
  };

  const remove = async (rule: SlaRule) => {
    if (!window.confirm(`“${rule.name}” SLA qoidasini o‘chirasizmi?`)) return;
    try {
      await axiosClient.delete(`/sla-rules/${rule.id}`);
      setRules((current) => current.filter((item) => item.id !== rule.id));
      toast.success('SLA o‘chirildi');
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'SLAni o‘chirib bo‘lmadi.');
    }
  };

  const inputClass = 'w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-sm font-semibold outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all';

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-5">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link to="/dashboard" className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400 hover:text-brand-500">
            <ArrowLeft className="w-3.5 h-3.5" /> Dashboardga qaytish
          </Link>
          <h1 className="mt-1 text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <Clock className="w-5 h-5 text-brand-500" /> SLA qoidalari
          </h1>
          <p className="text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400 mt-1">
            Xizmat guruhlari uchun qabul qilish va ishlash muddatlari. Bitta guruhga istagancha qoida biriktirish mumkin — ular muhimlik bo‘yicha ajraladi.
          </p>
        </div>
        <div className="flex gap-2">
          <button type="button" onClick={fetchData} disabled={loading} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-200">
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} /> Yangilash
          </button>
          {manage && <button type="button" onClick={openCreate} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-md">
            <Plus className="w-4 h-4" /> SLA yaratish
          </button>}
        </div>
      </header>

      <div className="grid sm:grid-cols-3 gap-3">
        <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
          <p className="text-xs text-slate-400">Jami SLA</p><p className="text-2xl font-black mt-1">{rules.length}</p>
        </div>
        <div className="rounded-2xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-950/30 p-4">
          <p className="text-xs text-emerald-600 dark:text-emerald-400">Aktiv</p><p className="text-2xl font-black mt-1 text-emerald-600 dark:text-emerald-400">{rules.filter((rule) => rule.is_active).length}</p>
        </div>
        <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4">
          <p className="text-xs text-slate-500">Passiv</p><p className="text-2xl font-black mt-1 text-slate-500">{rules.filter((rule) => !rule.is_active).length}</p>
        </div>
      </div>

      <div className="flex items-start gap-3 p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 text-amber-800 dark:text-amber-200">
        <Activity className="w-5 h-5 shrink-0 mt-0.5" />
        <div className="text-xs sm:text-sm"><strong>Kechikish vaqti avtomatik hisoblanadi.</strong> Xodim zayavkani qabul qilgandan keyin ishlash muddati boshlanadi. Shu muddat oshsa, o‘tgan vaqt minutlarda ko‘rsatiladi. Bir zayavkaga bir nechta qoida mos kelsa — eng qisqa muddatlisi qo‘llanadi.</div>
      </div>

      <div className="flex flex-wrap gap-3">
        <div className="relative flex-1 min-w-64 max-w-lg"><Search className="absolute left-3 top-3 w-4 h-4 text-slate-400"/><input className={`${inputClass} pl-9`} value={search} onChange={(event) => setSearch(event.target.value)} placeholder="SLA nomi yoki guruh bo‘yicha qidirish..." /></div>
        <select className={`${inputClass} w-auto`} value={status} onChange={(event) => setStatus(event.target.value as typeof status)}><option value="all">Barcha holatlar</option><option value="active">Aktiv</option><option value="passive">Passiv</option></select>
      </div>

      {loadError && <div className="flex gap-3 items-center p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/30 text-rose-600"><AlertTriangle className="w-5 h-5"/>SLA ma’lumotlarini yuklab bo‘lmadi.<button className="ml-auto underline" onClick={fetchData}>Qayta urinish</button></div>}
      {loading && <div className="min-h-52 flex items-center justify-center"><RefreshCw className="w-7 h-7 animate-spin text-brand-500"/></div>}
      {!loading && !loadError && !visibleRules.length && <EmptyState title="SLA topilmadi" description="Birinchi SLA qoidasini yarating va xizmat guruhiga biriktiring." />}
      {!loading && !loadError && visibleRules.length > 0 && <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/90 overflow-x-auto shadow-sm">
        <table className="w-full text-left text-xs">
          <thead><tr className="bg-slate-50 dark:bg-slate-900/40 text-slate-400 uppercase tracking-wider"><th className="p-4">SLA nomi va izoh</th><th className="p-4">Guruh</th><th className="p-4">Muhimlik</th><th className="p-4 text-center">Qabul qilish</th><th className="p-4 text-center">Ishlash</th><th className="p-4">Kechikish</th><th className="p-4 text-center">Holati</th>{manage && <th className="p-4 text-right">Amallar</th>}</tr></thead>
          <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60">{visibleRules.map((rule) => <tr key={rule.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30">
            <td className="p-4"><p className="font-black text-sm text-slate-900 dark:text-white">{rule.name}</p><p className="mt-1 text-slate-400 max-w-md whitespace-pre-wrap">{rule.description || 'Izoh kiritilmagan'}</p></td>
            <td className="p-4"><span className="font-bold">{rule.team?.name}</span><p className="font-mono text-slate-400 mt-1">{rule.team?.code}</p></td>
            <td className="p-4">{rule.priority ? <span className="rounded-full px-3 py-1 font-black text-white" style={{ backgroundColor: rule.priority.color || '#64748B' }}>{rule.priority.name}</span> : <span className="rounded-full px-3 py-1 bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 font-bold">Umumiy</span>}</td>
            <td className="p-4 text-center"><span className="rounded-full px-3 py-1 bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 font-black">{minutesLabel(rule.accept_minutes)}</span></td>
            <td className="p-4 text-center"><span className="rounded-full px-3 py-1 bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300 font-black">{minutesLabel(rule.work_minutes)}</span></td>
            <td className="p-4 text-slate-500 dark:text-slate-400"><span className="font-bold text-rose-500">Avtomatik</span><p className="mt-1">Ishlash muddati oshgandan boshlab</p></td>
            <td className="p-4 text-center">{manage ? <button type="button" role="switch" aria-checked={rule.is_active} onClick={() => toggleStatus(rule)} className={`inline-flex items-center gap-2 rounded-full px-3 py-1 font-black ${rule.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-200 text-slate-500 dark:bg-slate-700'}`}><span className="relative flex w-2 h-2">{rule.is_active && <span className="absolute inline-flex h-full w-full rounded-full bg-emerald-500 opacity-75 animate-ping"/>}<span className={`relative inline-flex w-2 h-2 rounded-full ${rule.is_active ? 'bg-emerald-500' : 'bg-slate-400'}`}/></span>{rule.is_active ? 'Aktiv' : 'Passiv'}</button> : <span>{rule.is_active ? 'Aktiv' : 'Passiv'}</span>}</td>
            {manage && <td className="p-4"><div className="flex justify-end gap-1"><button type="button" title="Tahrirlash" onClick={() => openEdit(rule)} className="p-2 rounded-lg text-slate-400 hover:text-brand-500 hover:bg-slate-100 dark:hover:bg-slate-700"><Pencil className="w-4 h-4"/></button><button type="button" title="O‘chirish" onClick={() => remove(rule)} className="p-2 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30"><Trash2 className="w-4 h-4"/></button></div></td>}
          </tr>)}</tbody>
        </table>
      </div>}

      {formOpen && <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm" onMouseDown={(event) => event.target === event.currentTarget && !saving && setFormOpen(false)}>
        <form onSubmit={save} className="w-full max-w-xl max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl">
          <header className="sticky top-0 bg-white dark:bg-slate-800 flex justify-between items-start gap-4 p-5 border-b border-slate-200 dark:border-slate-700"><div><h2 className="font-black text-slate-900 dark:text-white">{editing ? 'SLAni tahrirlash' : 'Yangi SLA yaratish'}</h2><p className="text-xs text-slate-400 mt-1">SLA qoidasini guruhga va (ixtiyoriy) muhimlikka biriktiring</p></div><button type="button" aria-label="Yopish" disabled={saving} onClick={() => setFormOpen(false)} className="p-1.5 text-slate-400"><X className="w-5 h-5"/></button></header>
          <div className="p-5 space-y-4">
            <label className="block space-y-1.5"><span className="text-xs font-black text-slate-500">Guruhga bog‘lash *</span><select required className={inputClass} value={form.team_id} onChange={(event) => setForm({ ...form, team_id: Number(event.target.value) || '' })}><option value="">Guruhni tanlang</option>{availableTeams.map((team) => { const teamRuleCount = rules.filter((rule) => rule.team_id === team.id).length; return <option key={team.id} value={team.id}>{team.name} ({team.code}){teamRuleCount ? ` — ${teamRuleCount} ta qoida` : ''}</option>; })}</select></label>
            {/* Bitta guruhda bir nechta qoida bo'ladi — ular muhimlik bo'yicha ajraladi. */}
            <label className="block space-y-1.5"><span className="text-xs font-black text-slate-500">Qaysi muhimlik uchun</span><select className={inputClass} value={form.priority_id} onChange={(event) => setForm({ ...form, priority_id: event.target.value === '' ? '' : Number(event.target.value) })}><option value="">Umumiy — barcha muhimliklar uchun</option>{priorities.map((priority) => <option key={priority.id} value={priority.id}>{priority.name}</option>)}</select><span className="block text-[11px] font-semibold text-slate-400">Muhimligi mos qoidasi bo‘lmagan zayavkalarga guruhning umumiy qoidalari qo‘llanadi.</span>{siblingRules.length > 0 && <span className="block text-[11px] font-semibold text-amber-600 dark:text-amber-400">Bu guruh va muhimlikda allaqachon {siblingRules.length} ta qoida bor ({siblingRules.map((rule) => rule.name).join(', ')}). Zayavkaga ular ichidan eng qisqa muddatlisi qo‘llanadi.</span>}</label>
            <label className="block space-y-1.5"><span className="text-xs font-black text-slate-500">SLA nomi *</span><input required maxLength={255} autoFocus className={inputClass} value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Masalan: Printer ishlamayapti"/></label>
            <label className="block space-y-1.5"><span className="text-xs font-black text-slate-500">Mazmuni (izoh)</span><textarea maxLength={5000} rows={3} className={inputClass} value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} placeholder="SLA qoidasi haqida izoh..."/></label>
            <div className="grid sm:grid-cols-2 gap-3">
              <label className="block space-y-1.5"><span className="text-xs font-black text-slate-500">Qabul qilish vaqti *</span><div className="relative"><input required min={1} max={100000} type="number" className={`${inputClass} pr-16`} value={form.accept_minutes} onChange={(event) => setForm({ ...form, accept_minutes: Number(event.target.value) })}/><span className="absolute right-3 top-3 text-xs text-slate-400">minut</span></div></label>
              <label className="block space-y-1.5"><span className="text-xs font-black text-slate-500">Ishlash vaqti *</span><div className="relative"><input required min={1} max={100000} type="number" className={`${inputClass} pr-16`} value={form.work_minutes} onChange={(event) => setForm({ ...form, work_minutes: Number(event.target.value) })}/><span className="absolute right-3 top-3 text-xs text-slate-400">minut</span></div></label>
            </div>
            <div className="rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 p-3 text-xs text-rose-700 dark:text-rose-300"><strong>Kechikish vaqti:</strong> ishlash vaqti tugagandan keyin tizim tomonidan avtomatik ravishda minutlarda hisoblanadi.</div>
            <label className="flex items-center justify-between gap-4 rounded-xl border border-slate-200 dark:border-slate-700 p-3"><span><strong className="block text-sm">Holati</strong><span className="text-xs text-slate-400">Passiv SLA zayavkalarga qo‘llanmaydi</span></span><button type="button" role="switch" aria-checked={form.is_active} onClick={() => setForm({ ...form, is_active: !form.is_active })} className={`relative w-11 h-6 rounded-full transition-colors ${form.is_active ? 'bg-emerald-500' : 'bg-slate-400'}`}><span className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-all ${form.is_active ? 'left-6' : 'left-1'}`}/></button></label>
            {formError && <p role="alert" className="text-xs font-bold text-rose-500">{formError}</p>}
          </div>
          <footer className="sticky bottom-0 flex justify-end gap-2 p-5 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"><button type="button" disabled={saving} onClick={() => setFormOpen(false)} className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold">Bekor qilish</button><button type="submit" disabled={saving} className="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold disabled:opacity-50">{saving ? 'Saqlanmoqda...' : 'Saqlash'}</button></footer>
        </form>
      </div>}
    </div>
  );
};
