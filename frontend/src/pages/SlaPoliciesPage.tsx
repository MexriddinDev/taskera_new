import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { Activity, AlertTriangle, ArrowLeft, ChevronDown, ChevronRight, Clock, Copy, Pencil, Plus, RefreshCw, Search, Trash2, X } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { useT } from '@/shared/presentation/i18n/i18n';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { RequiredMark } from '@/shared/presentation/components/RequiredMark';

interface Team {
  id: number;
  name: string;
  code: string;
  is_active: boolean;
  /** null — respublika guruhi, ya'ni hududga biriktirilmagan. */
  region_id: number | null;
  /** BI kabi guruhlar: zayavkasi doim respublikaga boradi. */
  republic_only: boolean;
}

interface Region {
  id: number;
  name: string;
}

interface Priority {
  id: number;
  code: string;
  name: string;
  color: string | null;
}

/**
 * Muhimlik nomi bazada faqat bitta tilda saqlanadi (`ticket_priorities.name`),
 * shuning uchun ekranda KOD bo'yicha tarjima qilinadi. Kod notanish bo'lsa
 * bazadagi nom ko'rsatiladi — yangi muhimlik qo'shilsa ham bo'sh joy qolmasin.
 */
const PRIORITY_LABEL_KEY: Record<string, string> = {
  CRITICAL: 'priority.critical',
  HIGH: 'priority.high',
  MEDIUM: 'priority.medium',
  LOW: 'priority.low',
};

const priorityName = (priority: Pick<Priority, 'code' | 'name'>, t: (key: string) => string) => {
  const key = PRIORITY_LABEL_KEY[priority.code];

  return key ? t(key) : priority.name;
};

interface SlaRule {
  id: number;
  team_id: number;
  /** null — respublika qoidasi. */
  region_id: number | null;
  team?: Pick<Team, 'id' | 'name' | 'code'>;
  // null — guruhning umumiy qoidasi (barcha muhimliklar uchun).
  priority_id: number | null;
  priority?: Priority | null;
  name: string;
  description: string | null;
  accept_minutes: number;
  work_minutes: number;
  // SLA bahosi jarimalari. `accept_*` guruh bahosiga, `work_*` xodim bahosiga
  // ta'sir qiladi; `reject_penalty` ikkalasidan ham ayiriladi.
  accept_grace_minutes: number;
  accept_penalty: number;
  work_grace_minutes: number;
  work_penalty: number;
  reject_penalty: number;
  is_active: boolean;
  // Guruhning "Default holat" qoidasi — shablon tanlanmagan zayavka shu
  // muddatni oladi. Jadvalda ko'rinmaydi: u blok tepasidagi kartada
  // tahrirlanadi va faqat ikkita vaqti o'zgaradi.
  is_default: boolean;
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

type SlaTab = 'rules' | 'scoring';

export const SlaPoliciesPage: React.FC = () => {
  const t = useT();
  const [tab, setTab] = useState<SlaTab>('rules');
  const { can } = useCan();
  const manage = can('sla.manage');
  const toast = useToastStore();
  const [rules, setRules] = useState<SlaRule[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [regions, setRegions] = useState<Region[]>([]);
  const [priorities, setPriorities] = useState<Priority[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState<'all' | 'active' | 'passive'>('all');
  const [teamFilter, setTeamFilter] = useState<number | 'all'>('all');
  // Hudud har doim tanlangan: 'republic' — hududsiz qoidalar, raqam — viloyat.
  // "Barcha hududlar" ko'rinishi yo'q — unda har shablon har viloyat nusxasi
  // bilan takrorlanib, 12 ta qoida 180 ta bo'lib ko'rinardi.
  const [regionFilter, setRegionFilter] = useState<number | 'republic'>('republic');
  const [copying, setCopying] = useState(false);
  const [editing, setEditing] = useState<SlaRule | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [formOpen, setFormOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState('');

  const fetchData = useCallback(async () => {
    setLoading(true);
    setLoadError(false);
    try {
      const [slaResponse, teamResponse, priorityResponse, regionResponse] = await Promise.all([
        axiosClient.get('/sla-rules', { params: { per_page: 500 } }),
        // SLA uchun alohida qo'lda ro'yxat yuritilmaydi: Guruhlar bo'limining
        // o'z API manbasidan barcha faol guruhlar olinadi.
        axiosClient.get('/teams', { params: { per_page: 100, is_active: 1 } }),
        axiosClient.get('/sla-rules/priorities'),
        axiosClient.get('/regions', { params: { per_page: 100, is_active: 1 } }),
      ]);
      setRules(slaResponse.data?.data ?? []);
      setTeams(teamResponse.data?.data ?? []);
      setPriorities(priorityResponse.data?.data ?? []);
      setRegions(regionResponse.data?.data ?? []);
    } catch {
      setLoadError(true);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  /** Zayavkasi doim respublikaga boradigan guruhlar (BI). */
  const republicOnlyTeams = useMemo(
    () => new Set(teams.filter((team) => team.republic_only).map((team) => team.id)),
    [teams],
  );

  /**
   * Qoida tanlangan hudud filtriga tushadimi.
   *
   * Tekshiruv QOIDANING hududi bo'yicha boradi. Ilgari bu yerda GURUHNING
   * hududi qaralardi — xizmat guruhlarining hammasida u `null` bo'lgani uchun
   * viloyat tanlanganda sahifa butunlay bo'sh qolardi.
   *
   * BI guruhi istisno: uning qoidalari faqat respublikada turadi va viloyat
   * ko'rinishida ham o'shalar ko'rsatiladi — nomiga "(RES)" qo'shib.
   */
  const inRegion = useCallback((rule: SlaRule) => {
    if (regionFilter === 'republic') return rule.region_id === null;
    if (republicOnlyTeams.has(rule.team_id)) return rule.region_id === null;

    return rule.region_id === regionFilter;
  }, [regionFilter, republicOnlyTeams]);

  /** Tanlangan hududning barcha qoidalari ("Default holat" bilan). */
  const regionRules = useMemo(() => rules.filter(inRegion), [rules, inRegion]);

  const visibleRules = useMemo(() => {
    const needle = search.trim().toLocaleLowerCase();
    return rules.filter((rule) => {
      if (rule.is_default) return false;
      if (!inRegion(rule)) return false;
      const matchesStatus = status === 'all' || (status === 'active' ? rule.is_active : !rule.is_active);
      const matchesSearch = !needle || [rule.name, rule.description, rule.team?.name, rule.team?.code, rule.priority?.name]
        .some((value) => value?.toLocaleLowerCase().includes(needle));
      return matchesStatus && matchesSearch;
    });
  }, [rules, search, status, inRegion]);

  /**
   * Guruh bo'yicha bloklar: har birida "Default holat" kartasi va guruhning
   * qolgan qoidalari.
   *
   * Qidiruv yoki holat filtri ishlaganda faqat mos qoidasi bor guruhlar
   * qoladi — aks holda filtr natijasi o'nlab bo'sh blok orasida yo'qolardi.
   */

  const teamGroups = useMemo(() => {
    const filtering = search.trim() !== '' || status !== 'all';
    const groups = new Map<number, { teamId: number; name: string; code: string; defaultRule: SlaRule | null; rules: SlaRule[] }>();

    const ensure = (rule: SlaRule) => {
      let group = groups.get(rule.team_id);
      if (!group) {
        group = { teamId: rule.team_id, name: rule.team?.name ?? '—', code: rule.team?.code ?? '', defaultRule: null, rules: [] };
        groups.set(rule.team_id, group);
      }
      return group;
    };

    // "Default holat" ham tanlangan hududniki bo'lishi kerak: aks holda
    // viloyat ko'rinishida respublika muddati ko'rsatilardi.
    rules.filter((rule) => rule.is_default && inRegion(rule)).forEach((rule) => { ensure(rule).defaultRule = rule; });
    visibleRules.forEach((rule) => { ensure(rule).rules.push(rule); });

    return [...groups.values()]
      .filter((group) => teamFilter === 'all' || group.teamId === teamFilter)
      .filter((group) => group.rules.length > 0 || !filtering)
      .sort((a, b) => a.name.localeCompare(b.name));
  }, [rules, visibleRules, search, status, teamFilter, inRegion]);

  /**
   * Viloyat ko'rinishida BI guruhining nomiga "(RES)" qo'shiladi: uning
   * qoidalari viloyatniki emas, respublikaniki ekani ko'rinib tursin.
   */
  const groupLabel = useCallback((teamId: number, name: string) => (
    regionFilter !== 'republic' && republicOnlyTeams.has(teamId)
      ? `${name} (${t('slaPolicies.republicMark')})`
      : name
  ), [regionFilter, republicOnlyTeams, t]);

  const [collapsed, setCollapsed] = useState<number[]>([]);
  const toggleTeam = (teamId: number) => setCollapsed((current) => current.includes(teamId)
    ? current.filter((id) => id !== teamId)
    : [...current, teamId]);

  // Default kartadagi tahrirlanayotgan qiymatlar (guruh bo'yicha).
  const [defaultDraft, setDefaultDraft] = useState<Record<number, { accept: number; work: number }>>({});
  const [savingDefault, setSavingDefault] = useState<number | null>(null);

  const saveDefault = async (rule: SlaRule, draft: { accept: number; work: number }) => {
    setSavingDefault(rule.team_id);
    try {
      // Defaultda faqat ikkita muddat o'zgaradi — server ham shuni qabul qiladi.
      await axiosClient.put(`/sla-rules/${rule.id}`, { accept_minutes: draft.accept, work_minutes: draft.work });
      setRules((current) => current.map((item) => item.id === rule.id
        ? { ...item, accept_minutes: draft.accept, work_minutes: draft.work }
        : item));
      toast.success(t('slaPolicies.defaultSaved'));
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('slaPolicies.defaultSaveFailed'));
    } finally {
      setSavingDefault(null);
    }
  };

  // Guruhlar bo'limidagi barcha faol guruhlar doim ko'rinadi. Avval SLA
  // biriktirilgan guruhlar butunlay yashirilgani uchun ro'yxat bo'sh tuyulardi.
  const availableTeams = teams.filter((team) => team.is_active);

  /**
   * Filtr qatoridagi "Guruh" ro'yxati tanlangan hududga qisqaradi.
   *
   * Formadagi ro'yxat (`availableTeams`) qisqarMAYDI: u yerda hudud filtri
   * emas, guruhning o'zi tanlanadi — aks holda filtr qo'yilgan holda boshqa
   * hududga qoida yozib bo'lmasdi.
   */
  const filterTeams = useMemo(() => {
    const withRules = new Set(regionRules.map((rule) => rule.team_id));

    return availableTeams.filter((team) => withRules.has(team.id));
  }, [availableTeams, regionRules]);

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
    return regionRules.filter(
      (rule) => rule.team_id === teamId && (rule.priority_id ?? null) === priorityId && rule.id !== editing?.id
    );
  }, [regionRules, form.team_id, form.priority_id, editing]);

  const regionName = regionFilter === 'republic'
    ? t('slaPolicies.republicTeams')
    : regions.find((region) => region.id === regionFilter)?.name ?? '—';

  /**
   * Yangi qoida hozir ko'rilayotgan hududga yoziladi. BI kabi guruhlar
   * istisno: ularning qoidasi faqat respublikada ma'noga ega.
   */
  const targetRegionId = (teamId: number) => (
    regionFilter === 'republic' || republicOnlyTeams.has(teamId) ? null : regionFilter
  );

  /** Viloyatda yo'q respublika shablonlarini qo'shadi, borlariga tegmaydi. */
  const copyFromRepublic = async () => {
    if (regionFilter === 'republic') return;
    setCopying(true);
    try {
      const response = await axiosClient.post('/sla-rules/copy-from-republic', { region_id: regionFilter });
      const copied = Number(response.data?.data?.copied ?? 0);
      if (copied > 0) toast.success(t('slaPolicies.copied', { count: copied }));
      else toast.success(t('slaPolicies.nothingToCopy'));
      await fetchData();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('slaPolicies.copyFailed'));
    } finally {
      setCopying(false);
    }
  };

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
      setFormError(t('slaPolicies.formRequired'));
      return;
    }
    setSaving(true);
    setFormError('');
    const payload = {
      ...form,
      team_id: Number(form.team_id),
      // Hudud faqat yaratishda beriladi: mavjud qoida boshqa hududga ko'chmaydi.
      ...(editing ? {} : { region_id: targetRegionId(Number(form.team_id)) }),
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
      toast.success(t(editing ? 'slaPolicies.updated' : 'slaPolicies.created'));
      setFormOpen(false);
      await fetchData();
    } catch (error: any) {
      const errors = error?.response?.data?.errors;
      setFormError(errors ? Object.values(errors).flat().join(' ') : error?.response?.data?.message || t('slaPolicies.saveFailed'));
    } finally {
      setSaving(false);
    }
  };

  const toggleStatus = async (rule: SlaRule) => {
    try {
      await axiosClient.put(`/sla-rules/${rule.id}`, { is_active: !rule.is_active });
      setRules((current) => current.map((item) => item.id === rule.id ? { ...item, is_active: !item.is_active } : item));
      toast.success(t(!rule.is_active ? 'slaPolicies.activated' : 'slaPolicies.deactivated'));
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('slaPolicies.statusFailed'));
    }
  };

  const remove = async (rule: SlaRule) => {
    if (!window.confirm(t('slaPolicies.deleteConfirm', { name: rule.name }))) return;
    try {
      await axiosClient.delete(`/sla-rules/${rule.id}`);
      setRules((current) => current.filter((item) => item.id !== rule.id));
      toast.success(t('slaPolicies.deleted'));
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('slaPolicies.deleteFailed'));
    }
  };

  const minutesLabel = (minutes: number) => t('slaPolicies.minutesValue', { minutes });

  const inputClass = 'w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-sm font-semibold outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all';
  // `inputClass` dagi `w-full` tanlovda ham ishlab ketadi va filtr qatorini
  // butunlay egallab oladi — shuning uchun unga qat'iy kenglik beriladi.
  const selectClass = inputClass.replace('w-full', 'w-full sm:w-52');

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-5">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link to="/dashboard" className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400 hover:text-brand-500">
            <ArrowLeft className="w-3.5 h-3.5" /> {t('slaPolicies.back')}
          </Link>
          <h1 className="mt-1 text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <Clock className="w-5 h-5 text-brand-500" /> {t('slaPolicies.title')}
          </h1>
          <p className="text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400 mt-1">
            {t('slaPolicies.subtitle')}
          </p>
        </div>
        <div className="flex gap-2">
          <button type="button" onClick={fetchData} disabled={loading} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-200">
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} /> {t('slaPolicies.refresh')}
          </button>
          {manage && <button type="button" onClick={openCreate} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-md">
            <Plus className="w-4 h-4" /> {t('slaPolicies.create')}
          </button>}
        </div>
      </header>

      {/* Tablar — UsersPage dagi bilan bir xil ko'rinish. */}
      <div className="flex items-center gap-2 border-b border-slate-200 dark:border-slate-700">
        {(['rules', 'scoring'] as SlaTab[]).map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => setTab(key)}
            className={`px-4 py-2.5 -mb-px text-xs sm:text-sm font-black border-b-2 transition-colors cursor-pointer ${
              tab === key
                ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
            }`}
          >
            {key === 'rules' ? t('slaPolicies.tabRules') : t('slaPolicies.tabScoring')}
          </button>
        ))}
      </div>

      {tab === 'scoring' && (
        <ScoringTab
          rules={regionRules}
          manage={manage}
          loading={loading}
          onSaved={(saved) => setRules((current) => current.map((item) => item.id === saved.id ? saved : item))}
        />
      )}

      {tab === 'rules' && <>
      <div className="grid sm:grid-cols-3 gap-3">
        <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4">
          <p className="text-xs text-slate-400">{t('slaPolicies.total')}</p><p className="text-2xl font-black mt-1">{regionRules.length}</p>
        </div>
        <div className="rounded-2xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50 dark:bg-emerald-950/30 p-4">
          <p className="text-xs text-emerald-600 dark:text-emerald-400">{t('slaPolicies.active')}</p><p className="text-2xl font-black mt-1 text-emerald-600 dark:text-emerald-400">{regionRules.filter((rule) => rule.is_active).length}</p>
        </div>
        <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-4">
          <p className="text-xs text-slate-500">{t('slaPolicies.passive')}</p><p className="text-2xl font-black mt-1 text-slate-500">{regionRules.filter((rule) => !rule.is_active).length}</p>
        </div>
      </div>

      <div className="flex items-start gap-3 p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 text-amber-800 dark:text-amber-200">
        <Activity className="w-5 h-5 shrink-0 mt-0.5" />
        <div className="text-xs sm:text-sm"><strong>{t('slaPolicies.autoNoticeTitle')}</strong> {t('slaPolicies.autoNoticeBody')}</div>
      </div>

      {/* Uchala filtr bir qatorda: qidiruv siqiladi, tanlovlar yonma-yon turadi. */}
      <div className="flex flex-wrap sm:flex-nowrap items-center gap-3">
        <div className="relative flex-1 min-w-0 max-w-lg"><Search className="absolute left-3 top-3 w-4 h-4 text-slate-400"/><input className={`${inputClass} pl-9`} value={search} onChange={(event) => setSearch(event.target.value)} placeholder={t('slaPolicies.searchPlaceholder')} /></div>
        {/* Hudud filtri guruh filtridan OLDIN turadi: avval hudud, keyin
            o'sha hududning IT bo'limi tanlanadi. Hudud almashtirilganda
            guruh tanlovi tozalanadi — aks holda eski guruh yangi hududga
            tushmay, ro'yxat bo'sh ko'rinardi. */}
        <select className={`${selectClass} shrink-0`} value={regionFilter} onChange={(event) => { const value = event.target.value; setRegionFilter(value === 'republic' ? value : Number(value)); setTeamFilter('all'); }}><option value="republic">{t('slaPolicies.republicTeams')}</option>{regions.map((region) => <option key={region.id} value={region.id}>{region.name}</option>)}</select>
        <select className={`${selectClass} shrink-0`} value={teamFilter} onChange={(event) => setTeamFilter(event.target.value === 'all' ? 'all' : Number(event.target.value))}><option value="all">{t('slaPolicies.allTeams')}</option>{filterTeams.map((team) => <option key={team.id} value={team.id}>{team.name}</option>)}</select>
        {manage && regionFilter !== 'republic' && <button type="button" onClick={copyFromRepublic} disabled={copying || loading} title={t('slaPolicies.copyFromRepublicHint')} className="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-brand-200 dark:border-brand-900 text-xs font-bold text-brand-600 dark:text-brand-300 hover:bg-brand-50 dark:hover:bg-brand-950/30 disabled:opacity-50">
          <Copy className={`w-4 h-4 ${copying ? 'animate-pulse' : ''}`} /> {t('slaPolicies.copyFromRepublic')}
        </button>}
        <select className={`${selectClass} shrink-0`} value={status} onChange={(event) => setStatus(event.target.value as typeof status)}><option value="all">{t('slaPolicies.allStatuses')}</option><option value="active">{t('slaPolicies.active')}</option><option value="passive">{t('slaPolicies.passive')}</option></select>
      </div>

      {loadError && <div className="flex gap-3 items-center p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/30 text-rose-600"><AlertTriangle className="w-5 h-5"/>{t('slaPolicies.loadFailed')}<button className="ml-auto underline" onClick={fetchData}>{t('slaPolicies.retry')}</button></div>}
      {loading && <div className="min-h-52 flex items-center justify-center"><RefreshCw className="w-7 h-7 animate-spin text-brand-500"/></div>}
      {!loading && !loadError && !teamGroups.length && <EmptyState title={t('slaPolicies.emptyTitle')} description={t('slaPolicies.emptyDesc')} />}

      {!loading && !loadError && teamGroups.map((group) => {
        const isCollapsed = collapsed.includes(group.teamId);
        const defaultRule = group.defaultRule;
        const draft = defaultRule
          ? defaultDraft[group.teamId] ?? { accept: defaultRule.accept_minutes, work: defaultRule.work_minutes }
          : null;
        const setDraft = (patch: Partial<{ accept: number; work: number }>) =>
          setDefaultDraft((current) => ({ ...current, [group.teamId]: { ...(draft as { accept: number; work: number }), ...patch } }));

        return (
          <div key={group.teamId} className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/90 shadow-sm overflow-hidden">
            <button type="button" onClick={() => toggleTeam(group.teamId)} className="w-full flex items-center gap-2 p-4 text-left hover:bg-slate-50 dark:hover:bg-slate-700/30">
              {isCollapsed ? <ChevronRight className="w-4 h-4 text-slate-400"/> : <ChevronDown className="w-4 h-4 text-slate-400"/>}
              <span className="font-black text-sm text-slate-900 dark:text-white">{groupLabel(group.teamId, group.name)}</span>
              <span className="font-mono text-[11px] text-slate-400">{group.code}</span>
              <span className="ml-auto text-[11px] font-bold text-slate-400">{t('slaPolicies.ruleCount', { count: group.rules.length })}</span>
            </button>

            {!isCollapsed && <div className="px-4 pb-4 space-y-3">
              {/* Shablon tanlanmagan zayavka shu muddat bo'yicha o'lchanadi.
                  Qoidalar jadvalida ko'rinmaydi: unda faqat ikkita vaqt bor. */}
              {defaultRule && draft && <div className="flex flex-wrap items-end gap-3 rounded-xl border border-brand-200 dark:border-brand-900 bg-brand-50/60 dark:bg-brand-950/20 p-3">
                <div className="mr-auto">
                  <p className="text-xs font-black text-slate-700 dark:text-slate-200">{t('slaPolicies.defaultTitle')}</p>
                  <p className="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{t('slaPolicies.defaultHint')}</p>
                </div>
                <label className="space-y-1"><span className="block text-[11px] font-black text-slate-500">{t('slaPolicies.accept')}</span><div className="relative"><input type="number" min={1} max={100000} disabled={!manage} className={`${inputClass} w-36 pr-14`} value={draft.accept} onChange={(event) => setDraft({ accept: Number(event.target.value) })}/><span className="absolute right-3 top-3 text-[11px] text-slate-400">{t('slaPolicies.minutes')}</span></div></label>
                <label className="space-y-1"><span className="block text-[11px] font-black text-slate-500">{t('slaPolicies.work')}</span><div className="relative"><input type="number" min={1} max={100000} disabled={!manage} className={`${inputClass} w-36 pr-14`} value={draft.work} onChange={(event) => setDraft({ work: Number(event.target.value) })}/><span className="absolute right-3 top-3 text-[11px] text-slate-400">{t('slaPolicies.minutes')}</span></div></label>
                {manage && <button type="button" disabled={savingDefault === group.teamId} onClick={() => saveDefault(defaultRule, draft)} className="px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold disabled:opacity-50">{t(savingDefault === group.teamId ? 'slaPolicies.saving' : 'slaPolicies.save')}</button>}
              </div>}

              {!defaultRule && <div className="rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/30 p-3 text-xs font-semibold text-amber-800 dark:text-amber-200">{t('slaPolicies.noDefault')}</div>}

              {group.rules.length === 0
                ? <p className="text-xs font-semibold text-slate-400">{t('slaPolicies.noExtraRules')}</p>
                : <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                <table className="w-full text-left text-xs">
                  <thead><tr className="bg-slate-50 dark:bg-slate-900/40 text-slate-400 uppercase tracking-wider"><th className="p-4">{t('slaPolicies.colName')}</th><th className="p-4">{t('slaPolicies.colPriority')}</th><th className="p-4 text-center">{t('slaPolicies.accept')}</th><th className="p-4 text-center">{t('slaPolicies.work')}</th><th className="p-4">{t('slaPolicies.colOverdue')}</th><th className="p-4 text-center">{t('slaPolicies.colStatus')}</th>{manage && <th className="p-4 text-right">{t('slaPolicies.colActions')}</th>}</tr></thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60">{group.rules.map((rule) => <tr key={rule.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                    <td className="p-4"><p className="font-black text-sm text-slate-900 dark:text-white">{rule.name}</p><p className="mt-1 text-slate-400 max-w-md whitespace-pre-wrap">{rule.description || t('slaPolicies.noDescription')}</p></td>
                    <td className="p-4">{rule.priority ? <span className="rounded-full px-3 py-1 font-black text-white" style={{ backgroundColor: rule.priority.color || '#64748B' }}>{priorityName(rule.priority, t)}</span> : <span className="rounded-full px-3 py-1 bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-300 font-bold">{t('slaPolicies.anyPriority')}</span>}</td>
                    <td className="p-4 text-center"><span className="rounded-full px-3 py-1 bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 font-black">{minutesLabel(rule.accept_minutes)}</span></td>
                    <td className="p-4 text-center"><span className="rounded-full px-3 py-1 bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300 font-black">{minutesLabel(rule.work_minutes)}</span></td>
                    <td className="p-4 text-slate-500 dark:text-slate-400"><span className="font-bold text-rose-500">{t('slaPolicies.overdueAuto')}</span><p className="mt-1">{t('slaPolicies.overdueHint')}</p></td>
                    <td className="p-4 text-center">{manage ? <button type="button" role="switch" aria-checked={rule.is_active} onClick={() => toggleStatus(rule)} className={`inline-flex items-center gap-2 rounded-full px-3 py-1 font-black ${rule.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-200 text-slate-500 dark:bg-slate-700'}`}><span className={`inline-flex w-2 h-2 rounded-full ${rule.is_active ? 'bg-emerald-500' : 'bg-slate-400'}`}/>{t(rule.is_active ? 'slaPolicies.active' : 'slaPolicies.passive')}</button> : <span>{t(rule.is_active ? 'slaPolicies.active' : 'slaPolicies.passive')}</span>}</td>
                    {manage && <td className="p-4"><div className="flex justify-end gap-1"><button type="button" title={t('slaPolicies.edit')} onClick={() => openEdit(rule)} className="p-2 rounded-lg text-slate-400 hover:text-brand-500 hover:bg-slate-100 dark:hover:bg-slate-700"><Pencil className="w-4 h-4"/></button><button type="button" title={t('slaPolicies.delete')} onClick={() => remove(rule)} className="p-2 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30"><Trash2 className="w-4 h-4"/></button></div></td>}
                  </tr>)}</tbody>
                </table>
              </div>}
            </div>}
          </div>
        );
      })}
      </>}

      {formOpen && <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm" onMouseDown={(event) => event.target === event.currentTarget && !saving && setFormOpen(false)}>
        <form onSubmit={save} className="w-full max-w-xl max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl">
          <header className="sticky top-0 bg-white dark:bg-slate-800 flex justify-between items-start gap-4 p-5 border-b border-slate-200 dark:border-slate-700"><div><h2 className="font-black text-slate-900 dark:text-white">{t(editing ? 'slaPolicies.formEditTitle' : 'slaPolicies.formCreateTitle')}</h2><p className="text-xs text-slate-400 mt-1">{t('slaPolicies.formSubtitle')}</p><p className="text-xs font-bold text-brand-600 dark:text-brand-300 mt-1">{t('slaPolicies.formRegion', { region: editing ? (editing.region_id === null ? t('slaPolicies.republicTeams') : regions.find((region) => region.id === editing.region_id)?.name ?? '—') : (form.team_id && targetRegionId(Number(form.team_id)) === null ? t('slaPolicies.republicTeams') : regionName) })}</p></div><button type="button" aria-label={t('slaPolicies.close')} disabled={saving} onClick={() => setFormOpen(false)} className="p-1.5 text-slate-400"><X className="w-5 h-5"/></button></header>
          <div className="p-5 space-y-4">
            <label className="block space-y-1.5"><span className="text-xs font-black text-slate-500">{t('slaPolicies.teamLabel')}<RequiredMark /></span><select required className={inputClass} value={form.team_id} onChange={(event) => { const teamId = Number(event.target.value) || ''; setForm({ ...form, team_id: teamId, description: teamId === form.team_id ? form.description : '' }); }}><option value="">{t('slaPolicies.teamPlaceholder')}</option>{availableTeams.map((team) => { const teamRuleCount = regionRules.filter((rule) => rule.team_id === team.id).length; return <option key={team.id} value={team.id}>{team.name} ({team.code}){teamRuleCount ? ` — ${t('slaPolicies.ruleCount', { count: teamRuleCount })}` : ''}</option>; })}</select></label>
            {/* Bitta guruhda bir nechta qoida bo'ladi — ular muhimlik bo'yicha ajraladi. */}
            <label className="block space-y-1.5"><span className="text-xs font-black text-slate-500">{t('slaPolicies.priorityLabel')}</span><select className={inputClass} value={form.priority_id} onChange={(event) => setForm({ ...form, priority_id: event.target.value === '' ? '' : Number(event.target.value) })}><option value="">{t('slaPolicies.priorityAny')}</option>{priorities.map((priority) => <option key={priority.id} value={priority.id}>{priorityName(priority, t)}</option>)}</select><span className="block text-[11px] font-semibold text-slate-400">{t('slaPolicies.priorityHint')}</span>{siblingRules.length > 0 && <span className="block text-[11px] font-semibold text-amber-600 dark:text-amber-400">{t('slaPolicies.siblingWarning', { count: siblingRules.length })}</span>}</label>
            <label className="block space-y-1.5"><span className="text-xs font-black text-slate-500">{t('slaPolicies.nameLabel')}<RequiredMark /></span><input required maxLength={255} autoFocus className={inputClass} value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder={t('slaPolicies.namePlaceholder')}/></label>
            <label className="block space-y-1.5"><span className="text-xs font-black text-slate-500">{t('slaPolicies.descriptionLabel')}</span><textarea maxLength={5000} rows={3} className={inputClass} value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} placeholder={t('slaPolicies.descriptionPlaceholder')}/></label>
            <div className="grid sm:grid-cols-2 gap-3">
              <label className="block space-y-1.5"><span className="text-xs font-black text-slate-500">{t('slaPolicies.acceptLabel')}<RequiredMark /></span><div className="relative"><input required min={1} max={100000} type="number" className={`${inputClass} pr-16`} value={form.accept_minutes} onChange={(event) => setForm({ ...form, accept_minutes: Number(event.target.value) })}/><span className="absolute right-3 top-3 text-xs text-slate-400">{t('slaPolicies.minutes')}</span></div></label>
              <label className="block space-y-1.5"><span className="text-xs font-black text-slate-500">{t('slaPolicies.workLabel')}<RequiredMark /></span><div className="relative"><input required min={1} max={100000} type="number" className={`${inputClass} pr-16`} value={form.work_minutes} onChange={(event) => setForm({ ...form, work_minutes: Number(event.target.value) })}/><span className="absolute right-3 top-3 text-xs text-slate-400">{t('slaPolicies.minutes')}</span></div></label>
            </div>
            <div className="rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900 p-3 text-xs text-rose-700 dark:text-rose-300"><strong>{t('slaPolicies.overdueNoticeTitle')}</strong> {t('slaPolicies.overdueNoticeBody')}</div>
            <label className="flex items-center justify-between gap-4 rounded-xl border border-slate-200 dark:border-slate-700 p-3"><span><strong className="block text-sm">{t('slaPolicies.statusLabel')}</strong><span className="text-xs text-slate-400">{t('slaPolicies.statusHint')}</span></span><button type="button" role="switch" aria-checked={form.is_active} onClick={() => setForm({ ...form, is_active: !form.is_active })} className={`relative w-11 h-6 rounded-full transition-colors ${form.is_active ? 'bg-emerald-500' : 'bg-slate-400'}`}><span className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-all ${form.is_active ? 'left-6' : 'left-1'}`}/></button></label>
            {formError && <p role="alert" className="text-xs font-bold text-rose-500">{formError}</p>}
          </div>
          <footer className="sticky bottom-0 flex justify-end gap-2 p-5 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800"><button type="button" disabled={saving} onClick={() => setFormOpen(false)} className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold">{t('slaPolicies.cancel')}</button><button type="submit" disabled={saving} className="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold disabled:opacity-50">{t(saving ? 'slaPolicies.saving' : 'slaPolicies.save')}</button></footer>
        </form>
      </div>}
    </div>
  );
};

/**
 * "Baholash sozlamalari" tabi.
 *
 * Zayavkaning bahosi ikki tomonlama o'qiladi:
 *   - GURUH bahosi — zayavka qancha tez QABUL QILINGANI bo'yicha;
 *   - XODIM bahosi — zayavka ustida ISHLASH muddati bo'yicha.
 *
 * Murojaatchi qo'ygan baho (`client_rating`) o'zgartirilmaydi — jarimalar
 * undan ayirilib, monitoringda yonma-yon ko'rsatiladi.
 */
const ScoringTab: React.FC<{
  rules: SlaRule[];
  manage: boolean;
  loading: boolean;
  onSaved: (rule: SlaRule) => void;
}> = ({ rules, manage, loading, onSaved }) => {
  const t = useT();
  const toast = useToastStore();
  const [draft, setDraft] = useState<Record<number, Partial<SlaRule>>>({});
  const [savingId, setSavingId] = useState<number | null>(null);

  // Guruh → muhimlik tartibida: bir guruhning qoidalari yonma-yon tursin.
  const ordered = useMemo(
    () => [...rules].sort((a, b) =>
      (a.team?.name ?? '').localeCompare(b.team?.name ?? '') || a.name.localeCompare(b.name)),
    [rules]
  );

  const valueOf = (rule: SlaRule, key: keyof SlaRule) => {
    const patched = draft[rule.id]?.[key];
    return (patched === undefined ? rule[key] : patched) as number;
  };

  const patch = (rule: SlaRule, key: keyof SlaRule, value: number) =>
    setDraft((current) => ({ ...current, [rule.id]: { ...current[rule.id], [key]: value } }));

  const save = async (rule: SlaRule) => {
    setSavingId(rule.id);
    try {
      // Muddatlar ham yuboriladi: "Default holat" qoidasini serverning
      // alohida validatsiyasi ularsiz qabul qilmaydi.
      const payload = {
        accept_minutes: rule.accept_minutes,
        work_minutes: rule.work_minutes,
        accept_grace_minutes: valueOf(rule, 'accept_grace_minutes'),
        accept_penalty: valueOf(rule, 'accept_penalty'),
        work_grace_minutes: valueOf(rule, 'work_grace_minutes'),
        work_penalty: valueOf(rule, 'work_penalty'),
        reject_penalty: valueOf(rule, 'reject_penalty'),
      };
      await axiosClient.put(`/sla-rules/${rule.id}`, payload);
      onSaved({ ...rule, ...payload });
      setDraft((current) => { const next = { ...current }; delete next[rule.id]; return next; });
      toast.success(t('slaPolicies.scoringSaved'));
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('slaPolicies.scoringSaveFailed'));
    } finally {
      setSavingId(null);
    }
  };

  const numberInput = 'w-20 px-2 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-xs font-bold text-center outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 disabled:opacity-50';

  if (loading) {
    return <div className="min-h-52 flex items-center justify-center"><RefreshCw className="w-7 h-7 animate-spin text-brand-500" /></div>;
  }

  if (!ordered.length) {
    return <EmptyState title={t('slaPolicies.emptyTitle')} description={t('slaPolicies.emptyDesc')} />;
  }

  return (
    <div className="space-y-4">
      <div className="flex items-start gap-3 p-4 rounded-2xl bg-brand-50 dark:bg-brand-950/30 border border-brand-200 dark:border-brand-900 text-brand-800 dark:text-brand-200">
        <Activity className="w-5 h-5 shrink-0 mt-0.5" />
        <div className="text-xs sm:text-sm">
          <strong>{t('slaPolicies.scoringNoticeTitle')}</strong> {t('slaPolicies.scoringNoticeBody')}
        </div>
      </div>

      <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/90 overflow-x-auto">
        <table className="w-full text-left text-xs">
          <thead className="bg-slate-50 dark:bg-slate-900/40 text-slate-500 dark:text-slate-400">
            <tr>
              <th className="px-4 py-3 font-black">{t('slaPolicies.scoringRule')}</th>
              <th className="px-4 py-3 font-black text-center" colSpan={2}>{t('slaPolicies.scoringTeamScore')}</th>
              <th className="px-4 py-3 font-black text-center" colSpan={2}>{t('slaPolicies.scoringUserScore')}</th>
              <th className="px-4 py-3 font-black text-center">{t('slaPolicies.scoringReject')}</th>
              <th className="px-4 py-3" />
            </tr>
            <tr className="text-[10px] uppercase tracking-wider">
              <th className="px-4 pb-2" />
              <th className="px-4 pb-2 font-bold text-center">{t('slaPolicies.scoringGrace')}</th>
              <th className="px-4 pb-2 font-bold text-center">{t('slaPolicies.scoringPenalty')}</th>
              <th className="px-4 pb-2 font-bold text-center">{t('slaPolicies.scoringGrace')}</th>
              <th className="px-4 pb-2 font-bold text-center">{t('slaPolicies.scoringPenalty')}</th>
              <th className="px-4 pb-2 font-bold text-center">{t('slaPolicies.scoringPenalty')}</th>
              <th className="px-4 pb-2" />
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100 dark:divide-slate-700/70">
            {ordered.map((rule) => {
              const dirty = draft[rule.id] !== undefined;

              return (
                <tr key={rule.id} className="text-slate-800 dark:text-slate-100">
                  <td className="px-4 py-3 min-w-56">
                    <span className="block font-bold break-words">{rule.name}</span>
                    <span className="block text-[11px] font-semibold text-slate-400">
                      {rule.team?.name ?? '—'} · {rule.priority ? priorityName(rule.priority, t) : t('slaPolicies.priorityAny')}
                    </span>
                  </td>
                  {([
                    ['accept_grace_minutes', 1, 100000, 1],
                    ['accept_penalty', 0, 5, 0.25],
                    ['work_grace_minutes', 1, 100000, 1],
                    ['work_penalty', 0, 5, 0.25],
                    ['reject_penalty', 0, 5, 0.25],
                  ] as Array<[keyof SlaRule, number, number, number]>).map(([key, min, max, step]) => (
                    <td key={key} className="px-4 py-3 text-center">
                      <input
                        type="number"
                        min={min}
                        max={max}
                        step={step}
                        disabled={!manage || savingId === rule.id}
                        className={numberInput}
                        value={valueOf(rule, key)}
                        onChange={(event) => patch(rule, key, Number(event.target.value))}
                        onBlur={(event) => {
                          // Ikki narsa shu yerda to'g'rilanadi:
                          // 1) `min`/`max` — `type="number"` da ular faqat forma
                          //    yuborilganda tekshiriladi, bu yerda forma yo'q,
                          //    ya'ni 9 ham yozilib ketaverardi;
                          // 2) boshidagi nol ("02") — React "02" ni 2 ga TENG deb
                          //    hisoblab DOM qiymatini qayta yozmaydi, shuning uchun
                          //    uni qo'lda normallashtiramiz.
                          const next = Math.min(max, Math.max(min, Number(event.currentTarget.value) || 0));
                          if (next !== valueOf(rule, key)) patch(rule, key, next);
                          event.currentTarget.value = String(next);
                        }}
                      />
                    </td>
                  ))}
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      disabled={!manage || !dirty || savingId === rule.id}
                      onClick={() => save(rule)}
                      className="px-3 py-1.5 rounded-lg bg-brand-600 hover:bg-brand-500 text-white text-[11px] font-bold disabled:opacity-40"
                    >
                      {t(savingId === rule.id ? 'slaPolicies.saving' : 'slaPolicies.save')}
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
};
