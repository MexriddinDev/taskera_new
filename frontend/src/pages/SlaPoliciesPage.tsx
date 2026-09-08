import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft, Clock, Plus, RefreshCw, Search, Trash2, Pencil, X, AlertTriangle } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useT } from '@/shared/presentation/i18n/i18n';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { EmptyState } from '@/shared/presentation/components/EmptyState';

/**
 * SLA — zayavka kategoriyasining uch muddati.
 *
 * Ilgari bu sahifa alohida "SLA siyosatlari" quyi tizimi edi: qoidalar,
 * versiyalar, maqsad metrikalari, ish kalendarlari, bayramlar, eskalatsiya
 * bosqichlari. Amalda ulardan foydalanilmadi. Endi SLA kategoriya bilan
 * BIRGA yaratiladi va bor-yo'g'i uchta sondan iborat:
 *
 *   Qabul qilish → Ishlash → Yopish
 *
 * Zayavka tizimga tushganda muddatlar shu kategoriyadan olinadi.
 */

interface Category {
  id: number;
  code: string;
  name: string;
  description: string | null;
  sla_accept_minutes: number;
  sla_work_minutes: number;
  sla_close_minutes: number;
  is_active: boolean;
}

/** Standart muddatlar — backenddagi TicketSlaService::DEFAULTS bilan bir xil. */
const DEFAULTS = { accept: 30, work: 240, close: 120 };

const emptyForm = {
  name: '',
  description: '',
  sla_accept_minutes: DEFAULTS.accept,
  sla_work_minutes: DEFAULTS.work,
  sla_close_minutes: DEFAULTS.close,
};

type FormState = typeof emptyForm;

/** Daqiqani odam o'qiydigan ko'rinishga keltiradi: 240 → "4 soat". */
const humanMinutes = (minutes: number, t: (k: string, p?: any) => string): string => {
  if (!minutes || minutes < 1) return '—';
  if (minutes < 60) return t('slaSimple.minutesShort', { count: minutes });
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  const hoursText = t('slaSimple.hoursShort', { count: hours });
  return rest > 0 ? `${hoursText} ${t('slaSimple.minutesShort', { count: rest })}` : hoursText;
};

export const SlaPoliciesPage: React.FC = () => {
  const t = useT();
  const { can } = useCan();
  const toast = useToastStore();
  const manage = can('sla.manage');

  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [isError, setIsError] = useState(false);
  const [search, setSearch] = useState('');

  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editing, setEditing] = useState<Category | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);

  const fetchCategories = useCallback(async () => {
    setLoading(true);
    setIsError(false);
    try {
      const res = await axiosClient.get('/categories', { params: { per_page: 100 } });
      setCategories(res.data?.data ?? []);
    } catch {
      setIsError(true);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchCategories();
  }, [fetchCategories]);

  const visible = useMemo(() => {
    const needle = search.trim().toLowerCase();
    if (!needle) return categories;
    return categories.filter(
      (c) => c.name.toLowerCase().includes(needle) || (c.code || '').toLowerCase().includes(needle)
    );
  }, [categories, search]);

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm);
    setFormError(null);
    setIsFormOpen(true);
  };

  const openEdit = (category: Category) => {
    setEditing(category);
    setForm({
      name: category.name,
      description: category.description ?? '',
      sla_accept_minutes: category.sla_accept_minutes,
      sla_work_minutes: category.sla_work_minutes,
      sla_close_minutes: category.sla_close_minutes,
    });
    setFormError(null);
    setIsFormOpen(true);
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!form.name.trim()) {
      setFormError(t('slaSimple.nameRequired'));
      return;
    }

    setSaving(true);
    setFormError(null);
    try {
      const payload = {
        name: form.name.trim(),
        description: form.description.trim() || null,
        sla_accept_minutes: Number(form.sla_accept_minutes) || DEFAULTS.accept,
        sla_work_minutes: Number(form.sla_work_minutes) || DEFAULTS.work,
        sla_close_minutes: Number(form.sla_close_minutes) || DEFAULTS.close,
      };

      if (editing) {
        await axiosClient.put(`/categories/${editing.id}`, payload);
      } else {
        await axiosClient.post('/categories', payload);
      }

      toast.success(t('slaSimple.saved'));
      setIsFormOpen(false);
      fetchCategories();
    } catch (err: any) {
      setFormError(err?.response?.data?.message || t('common.errorGeneric'));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (category: Category) => {
    try {
      await axiosClient.delete(`/categories/${category.id}`);
      toast.success(t('slaSimple.deleted'));
      fetchCategories();
    } catch (err: any) {
      toast.error(err?.response?.data?.message || t('common.errorGeneric'));
    }
  };

  const inputClass =
    'w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-sm font-semibold outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all';

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-5">
      {/* Sarlavha */}
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <Link
            to="/dashboard"
            className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400 hover:text-brand-600 dark:hover:text-brand-400 transition-colors"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            {t('audit.backToDashboard')}
          </Link>
          <h1 className="mt-1 text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <Clock className="w-5 h-5 text-brand-500" />
            {t('slaSimple.title')}
          </h1>
          <p className="text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400 mt-0.5">
            {t('slaSimple.subtitle')}
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={fetchCategories}
            disabled={loading}
            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-700/50 disabled:opacity-50 transition-all cursor-pointer"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
            {t('usersPage.refresh')}
          </button>

          {manage && (
            <button
              type="button"
              onClick={openCreate}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-md transition-all cursor-pointer border-none"
            >
              <Plus className="w-4 h-4" />
              {t('slaSimple.newCategory')}
            </button>
          )}
        </div>
      </div>

      {/* Muddatlar qanday ishlashi — bir qatorli tushuntirish */}
      <div className="flex flex-wrap items-center gap-2 p-4 rounded-2xl bg-brand-50 dark:bg-brand-950/40 border border-brand-200 dark:border-brand-900 text-xs sm:text-sm font-bold text-brand-800 dark:text-brand-200">
        <span>{t('slaSimple.flowAccept')}</span>
        <span className="text-brand-400">→</span>
        <span>{t('slaSimple.flowWork')}</span>
        <span className="text-brand-400">→</span>
        <span>{t('slaSimple.flowClose')}</span>
      </div>

      {/* Qidiruv */}
      <div className="relative max-w-md">
        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
          <Search className="w-4 h-4" />
        </div>
        <input
          type="text"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder={t('slaSimple.searchPlaceholder')}
          className={`${inputClass} pl-9`}
        />
      </div>

      {isError && (
        <div className="flex items-center gap-3 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300">
          <AlertTriangle className="w-5 h-5 shrink-0" />
          <span className="text-xs sm:text-sm font-bold">{t('common.errorGeneric')}</span>
          <button type="button" onClick={fetchCategories} className="ml-auto text-xs font-black underline cursor-pointer">
            {t('common.retry')}
          </button>
        </div>
      )}

      {loading && (
        <div className="flex items-center justify-center min-h-[30vh]" role="status" aria-live="polite">
          <div className="w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin" aria-hidden="true" />
        </div>
      )}

      {!loading && !isError && visible.length === 0 && (
        <EmptyState title={t('slaSimple.emptyTitle')} description={t('slaSimple.emptyDesc')} />
      )}

      {!loading && !isError && visible.length > 0 && (
        <div className="bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="bg-slate-50 dark:bg-slate-900/40 text-slate-400 font-bold uppercase tracking-wider">
                  <th className="py-3 px-4">{t('slaSimple.categoryColumn')}</th>
                  <th className="py-3 px-4 text-center">{t('slaSimple.acceptColumn')}</th>
                  <th className="py-3 px-4 text-center">{t('slaSimple.workColumn')}</th>
                  <th className="py-3 px-4 text-center">{t('slaSimple.closeColumn')}</th>
                  {manage && <th className="py-3 px-4 text-right">{t('slaSimple.actionsColumn')}</th>}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium text-slate-700 dark:text-slate-200">
                {visible.map((category) => (
                  <tr key={category.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td className="py-3 px-4">
                      <div className="font-extrabold text-slate-900 dark:text-slate-100 truncate max-w-[320px]">{category.name}</div>
                      <div className="text-[11px] text-slate-400 font-mono">{category.code}</div>
                    </td>
                    <td className="py-3 px-4 text-center">
                      <span className="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 font-extrabold">
                        {humanMinutes(category.sla_accept_minutes, t)}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-center">
                      <span className="px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300 font-extrabold">
                        {humanMinutes(category.sla_work_minutes, t)}
                      </span>
                    </td>
                    <td className="py-3 px-4 text-center">
                      <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-extrabold">
                        {humanMinutes(category.sla_close_minutes, t)}
                      </span>
                    </td>
                    {manage && (
                      <td className="py-3 px-4">
                        <div className="flex items-center justify-end gap-1.5">
                          <button
                            type="button"
                            onClick={() => openEdit(category)}
                            className="p-1.5 rounded-lg text-slate-400 hover:text-brand-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                            title={t('slaSimple.edit')}
                          >
                            <Pencil className="w-4 h-4" />
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDelete(category)}
                            className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                            title={t('slaSimple.delete')}
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </td>
                    )}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Yaratish / tahrirlash oynasi */}
      {isFormOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm" onClick={() => setIsFormOpen(false)}>
          <form
            onSubmit={handleSave}
            onClick={(e) => e.stopPropagation()}
            className="w-full max-w-lg bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700"
          >
            <div className="flex items-start justify-between gap-3 p-5 border-b border-slate-200 dark:border-slate-700">
              <div>
                <h3 className="text-sm font-black text-slate-900 dark:text-slate-100">
                  {editing ? t('slaSimple.editTitle') : t('slaSimple.newCategory')}
                </h3>
                <p className="text-xs font-semibold text-slate-400 mt-0.5">{t('slaSimple.formHint')}</p>
              </div>
              <button
                type="button"
                onClick={() => setIsFormOpen(false)}
                className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                aria-label={t('usersPage.close')}
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-5 space-y-4">
              <div className="space-y-1.5">
                <label className="text-[11px] font-black uppercase tracking-wider text-slate-400">{t('slaSimple.nameLabel')}</label>
                <input
                  type="text"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  placeholder={t('slaSimple.namePlaceholder')}
                  className={inputClass}
                  autoFocus
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                {([
                  ['sla_accept_minutes', 'slaSimple.acceptColumn'],
                  ['sla_work_minutes', 'slaSimple.workColumn'],
                  ['sla_close_minutes', 'slaSimple.closeColumn'],
                ] as const).map(([field, label]) => (
                  <div key={field} className="space-y-1.5">
                    <label className="text-[11px] font-black uppercase tracking-wider text-slate-400">{t(label)}</label>
                    <div className="relative">
                      <input
                        type="number"
                        min={1}
                        value={form[field]}
                        onChange={(e) => setForm({ ...form, [field]: Number(e.target.value) })}
                        className={`${inputClass} pr-12`}
                      />
                      <span className="absolute inset-y-0 right-3 flex items-center text-[11px] font-bold text-slate-400 pointer-events-none">
                        {t('slaSimple.minutesUnit')}
                      </span>
                    </div>
                    <p className="text-[11px] font-semibold text-slate-400">{humanMinutes(Number(form[field]), t)}</p>
                  </div>
                ))}
              </div>

              <div className="space-y-1.5">
                <label className="text-[11px] font-black uppercase tracking-wider text-slate-400">{t('slaSimple.descriptionLabel')}</label>
                <textarea
                  value={form.description}
                  onChange={(e) => setForm({ ...form, description: e.target.value })}
                  rows={2}
                  className={inputClass}
                />
              </div>

              {formError && (
                <p className="text-xs font-bold text-rose-600 dark:text-rose-400">{formError}</p>
              )}
            </div>

            <div className="flex items-center justify-end gap-2 p-5 border-t border-slate-200 dark:border-slate-700">
              <button
                type="button"
                onClick={() => setIsFormOpen(false)}
                className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer"
              >
                {t('usersPage.close')}
              </button>
              <button
                type="submit"
                disabled={saving}
                className="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-md disabled:opacity-60 transition-all cursor-pointer border-none"
              >
                {saving ? t('slaSimple.saving') : t('slaSimple.save')}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
};
