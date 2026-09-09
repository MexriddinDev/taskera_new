import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, BadgeCheck, Building2, CalendarClock, IdCard, Plus, RefreshCw, Search, Trash2, X } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { useT } from '@/shared/presentation/i18n/i18n';

type DocumentType = 'PASSPORT' | 'DRIVER_LICENSE';

interface Permit {
  id: number;
  full_name: string;
  document_type: DocumentType;
  document_number: string | null;
  visit_purpose: string;
  visit_at: string | null;
  visitor_organization: string | null;
  host_department: string | null;
  created_at: string | null;
}

interface FormState {
  full_name: string;
  document_type: DocumentType;
  document_number: string;
  visit_purpose: string;
  visit_at: string;
  visitor_organization: string;
  host_department: string;
}

const emptyForm: FormState = {
  full_name: '',
  document_type: 'PASSPORT',
  document_number: '',
  visit_purpose: '',
  visit_at: '',
  visitor_organization: '',
  host_department: '',
};

/** ISO sanani `datetime-local` kutadigan ko'rinishga keltiradi. */
const toLocalInput = (iso: string | null): string => (iso ? iso.slice(0, 16) : '');

const formatVisitAt = (iso: string | null): string => {
  if (!iso) return '—';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '—';

  const day = date.toLocaleDateString('uz-UZ', { day: '2-digit', month: '2-digit', year: 'numeric' });
  const time = date.toLocaleTimeString('uz-UZ', { hour: '2-digit', minute: '2-digit' });
  return day + ' ' + time;
};

export const PermitsPage: React.FC = () => {
  const t = useT();
  const { can } = useCan();
  const toast = useToastStore();

  const [permits, setPermits] = useState<Permit[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState<'' | DocumentType>('');
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<Permit | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [formError, setFormError] = useState('');
  const [saving, setSaving] = useState(false);

  const manage = can('permits.manage');

  const documentLabel = useCallback(
    (type: DocumentType) => t(type === 'PASSPORT' ? 'permits.docPassport' : 'permits.docLicense'),
    [t]
  );

  const fetchData = useCallback(async () => {
    setLoading(true);
    setLoadError(false);
    try {
      const response = await axiosClient.get('/permits', { params: { per_page: 100 } });
      setPermits(response.data?.data ?? []);
    } catch {
      setLoadError(true);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Qidiruv va filtr mahalliy: ro'yxat bir sahifaga sig'adigan hajmda.
  const visiblePermits = useMemo(() => {
    const needle = search.trim().toLocaleLowerCase();
    return permits.filter((permit) => {
      const matchesType = !typeFilter || permit.document_type === typeFilter;
      const matchesSearch =
        !needle ||
        [permit.full_name, permit.document_number, permit.visitor_organization, permit.visit_purpose, permit.host_department]
          .some((value) => value?.toLocaleLowerCase().includes(needle));
      return matchesType && matchesSearch;
    });
  }, [permits, search, typeFilter]);

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm);
    setFormError('');
    setFormOpen(true);
  };

  const openEdit = (permit: Permit) => {
    setEditing(permit);
    setForm({
      full_name: permit.full_name,
      document_type: permit.document_type,
      document_number: permit.document_number ?? '',
      visit_purpose: permit.visit_purpose,
      visit_at: toLocalInput(permit.visit_at),
      visitor_organization: permit.visitor_organization ?? '',
      host_department: permit.host_department ?? '',
    });
    setFormError('');
    setFormOpen(true);
  };

  const save = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!form.full_name.trim() || !form.visit_purpose.trim()) {
      setFormError(t('permits.requiredError'));
      return;
    }

    setSaving(true);
    setFormError('');
    const payload = {
      full_name: form.full_name.trim(),
      document_type: form.document_type,
      document_number: form.document_number.trim() || null,
      visit_purpose: form.visit_purpose.trim(),
      visit_at: form.visit_at || null,
      visitor_organization: form.visitor_organization.trim() || null,
      host_department: form.host_department.trim() || null,
    };

    try {
      if (editing) await axiosClient.put('/permits/' + editing.id, payload);
      else await axiosClient.post('/permits', payload);
      toast.success(editing ? t('permits.updated') : t('permits.created'));
      setFormOpen(false);
      await fetchData();
    } catch (error: any) {
      const errors = error?.response?.data?.errors;
      setFormError(errors ? Object.values(errors).flat().join(' ') : error?.response?.data?.message || t('permits.saveError'));
    } finally {
      setSaving(false);
    }
  };

  const remove = async (permit: Permit) => {
    if (!window.confirm(t('permits.deleteConfirm', { name: permit.full_name }))) return;
    try {
      await axiosClient.delete('/permits/' + permit.id);
      setPermits((current) => current.filter((item) => item.id !== permit.id));
      toast.success(t('permits.deleted'));
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('permits.deleteError'));
    }
  };

  const inputClass =
    'w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-sm font-semibold outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all';

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-5">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
            <BadgeCheck className="w-5 h-5 text-brand-500" /> {t('permits.title')}
          </h1>
          <p className="text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400 mt-1">
            {t('permits.subtitle')}
          </p>
        </div>
        <div className="flex gap-2">
          <button
            type="button"
            onClick={fetchData}
            disabled={loading}
            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-200"
          >
            <RefreshCw className={'w-4 h-4 ' + (loading ? 'animate-spin' : '')} /> {t('permits.refresh')}
          </button>
          {manage && (
            <button
              type="button"
              onClick={openCreate}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold shadow-md"
            >
              <Plus className="w-4 h-4" /> {t('permits.create')}
            </button>
          )}
        </div>
      </header>

      <div className="flex flex-wrap gap-3">
        <div className="relative flex-1 min-w-64 max-w-lg">
          <Search className="absolute left-3 top-3 w-4 h-4 text-slate-400" />
          <input
            className={inputClass + ' pl-9'}
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder={t('permits.searchPlaceholder')}
          />
        </div>
        <select
          className={inputClass + ' w-auto'}
          value={typeFilter}
          onChange={(event) => setTypeFilter(event.target.value as '' | DocumentType)}
        >
          <option value="">{t('permits.allTypes')}</option>
          <option value="PASSPORT">{t('permits.docPassport')}</option>
          <option value="DRIVER_LICENSE">{t('permits.docLicense')}</option>
        </select>
      </div>

      {loadError && (
        <div className="flex gap-3 items-center p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/30 text-rose-600">
          <AlertTriangle className="w-5 h-5" />
          {t('permits.loadError')}
          <button className="ml-auto underline" onClick={fetchData}>{t('common.retry')}</button>
        </div>
      )}

      {loading && (
        <div className="min-h-52 flex items-center justify-center">
          <RefreshCw className="w-7 h-7 animate-spin text-brand-500" />
        </div>
      )}

      {!loading && !loadError && visiblePermits.length === 0 && (
        <EmptyState title={t('permits.emptyTitle')} description={t('permits.emptyDesc')} />
      )}

      {!loading && !loadError && visiblePermits.length > 0 && (
        <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/90 overflow-x-auto shadow-sm">
          <table className="w-full text-left text-xs">
            <thead>
              <tr className="bg-slate-50 dark:bg-slate-900/40 text-slate-400 uppercase tracking-wider">
                <th className="p-4">{t('permits.colVisitor')}</th>
                <th className="p-4">{t('permits.colDocument')}</th>
                <th className="p-4">{t('permits.colPurpose')}</th>
                <th className="p-4">{t('permits.colVisitAt')}</th>
                {manage && <th className="p-4 text-right">{t('permits.colActions')}</th>}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60">
              {visiblePermits.map((permit) => (
                <tr key={permit.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                  <td className="p-4">
                    <p className="font-black text-sm text-slate-900 dark:text-white">{permit.full_name}</p>
                    {permit.visitor_organization && (
                      <p className="mt-1 inline-flex items-center gap-1 text-slate-400">
                        <Building2 className="w-3 h-3" /> {permit.visitor_organization}
                      </p>
                    )}
                  </td>
                  <td className="p-4">
                    <span className="inline-flex items-center gap-1.5 rounded-full px-3 py-1 bg-slate-100 dark:bg-slate-700 font-bold">
                      <IdCard className="w-3.5 h-3.5" /> {documentLabel(permit.document_type)}
                    </span>
                    {permit.document_number && (
                      <p className="mt-1 font-mono text-slate-400">{permit.document_number}</p>
                    )}
                  </td>
                  <td className="p-4 max-w-md whitespace-pre-wrap text-slate-600 dark:text-slate-300">
                    {permit.visit_purpose}
                    {permit.host_department && (
                      <p className="mt-1 text-slate-400">{t('permits.hostLabel')}: {permit.host_department}</p>
                    )}
                  </td>
                  <td className="p-4">
                    <span className="inline-flex items-center gap-1.5 font-bold text-slate-700 dark:text-slate-200">
                      <CalendarClock className="w-3.5 h-3.5 text-slate-400" /> {formatVisitAt(permit.visit_at)}
                    </span>
                  </td>
                  {manage && (
                    <td className="p-4">
                      <div className="flex justify-end gap-1">
                        <button
                          type="button"
                          onClick={() => openEdit(permit)}
                          className="px-2.5 py-1.5 rounded-lg text-slate-500 hover:text-brand-500 hover:bg-slate-100 dark:hover:bg-slate-700 font-bold"
                        >
                          {t('permits.edit')}
                        </button>
                        <button
                          type="button"
                          onClick={() => remove(permit)}
                          className="p-2 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30"
                          title={t('permits.delete')}
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
      )}

      {formOpen && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm"
          onMouseDown={(event) => event.target === event.currentTarget && !saving && setFormOpen(false)}
        >
          <form onSubmit={save} className="w-full max-w-xl max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl">
            <header className="sticky top-0 bg-white dark:bg-slate-800 flex justify-between items-start gap-4 p-5 border-b border-slate-200 dark:border-slate-700">
              <div>
                <h2 className="font-black text-slate-900 dark:text-white">
                  {editing ? t('permits.editTitle') : t('permits.createTitle')}
                </h2>
                <p className="text-xs text-slate-400 mt-1">{t('permits.formHint')}</p>
              </div>
              <button type="button" aria-label={t('common.cancel')} disabled={saving} onClick={() => setFormOpen(false)} className="p-1.5 text-slate-400">
                <X className="w-5 h-5" />
              </button>
            </header>

            <div className="p-5 space-y-4">
              <label className="block space-y-1.5">
                <span className="text-xs font-black text-slate-500">{t('permits.fullName')} *</span>
                <input
                  required
                  maxLength={255}
                  autoFocus
                  className={inputClass}
                  value={form.full_name}
                  onChange={(event) => setForm({ ...form, full_name: event.target.value })}
                />
              </label>

              <div className="grid sm:grid-cols-2 gap-3">
                <label className="block space-y-1.5">
                  <span className="text-xs font-black text-slate-500">{t('permits.documentType')} *</span>
                  <select
                    required
                    className={inputClass}
                    value={form.document_type}
                    onChange={(event) => setForm({ ...form, document_type: event.target.value as DocumentType })}
                  >
                    <option value="PASSPORT">{t('permits.docPassport')}</option>
                    <option value="DRIVER_LICENSE">{t('permits.docLicense')}</option>
                  </select>
                </label>
                <label className="block space-y-1.5">
                  <span className="text-xs font-black text-slate-500">{t('permits.documentNumber')}</span>
                  <input
                    maxLength={64}
                    className={inputClass}
                    value={form.document_number}
                    onChange={(event) => setForm({ ...form, document_number: event.target.value })}
                    placeholder="AA1234567"
                  />
                </label>
              </div>

              <label className="block space-y-1.5">
                <span className="text-xs font-black text-slate-500">{t('permits.purpose')} *</span>
                <textarea
                  required
                  maxLength={2000}
                  rows={3}
                  className={inputClass}
                  value={form.visit_purpose}
                  onChange={(event) => setForm({ ...form, visit_purpose: event.target.value })}
                />
              </label>

              <div className="grid sm:grid-cols-2 gap-3">
                <label className="block space-y-1.5">
                  <span className="text-xs font-black text-slate-500">{t('permits.visitAt')}</span>
                  <input
                    type="datetime-local"
                    className={inputClass}
                    value={form.visit_at}
                    onChange={(event) => setForm({ ...form, visit_at: event.target.value })}
                  />
                </label>
                <label className="block space-y-1.5">
                  <span className="text-xs font-black text-slate-500">{t('permits.visitorOrganization')}</span>
                  <input
                    maxLength={255}
                    className={inputClass}
                    value={form.visitor_organization}
                    onChange={(event) => setForm({ ...form, visitor_organization: event.target.value })}
                  />
                </label>
              </div>

              <label className="block space-y-1.5">
                <span className="text-xs font-black text-slate-500">{t('permits.hostDepartment')}</span>
                <input
                  maxLength={255}
                  className={inputClass}
                  value={form.host_department}
                  onChange={(event) => setForm({ ...form, host_department: event.target.value })}
                />
              </label>

              {formError && <p role="alert" className="text-xs font-bold text-rose-500">{formError}</p>}
            </div>

            <footer className="sticky bottom-0 flex justify-end gap-2 p-5 border-t border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
              <button type="button" disabled={saving} onClick={() => setFormOpen(false)} className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold">
                {t('common.cancel')}
              </button>
              <button type="submit" disabled={saving} className="px-4 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold disabled:opacity-50">
                {saving ? t('permits.saving') : t('permits.save')}
              </button>
            </footer>
          </form>
        </div>
      )}
    </div>
  );
};
