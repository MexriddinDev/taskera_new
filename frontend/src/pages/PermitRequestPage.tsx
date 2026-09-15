import React, { useCallback, useEffect, useRef, useState } from 'react';
import { DoorOpen, Loader2, Send } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { useT } from '@/shared/presentation/i18n/i18n';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { RequiredMark } from '@/shared/presentation/components/RequiredMark';
import { PermitRequest, PermitStatusBadge } from '@/shared/presentation/components/PermitStatusBadge';

/**
 * Guvohnoma raqamini shaklga soladi: 2 ta katta harf, so'ng 7 ta raqam.
 *
 * Bir joyda saqlanadi, chunki backend ham aynan shu qoidani tekshiradi
 * (`PermitRequest::DOCUMENT_PATTERNS`).
 */
const formatDocumentNumber = (raw: string): string => {
  const letters = raw.slice(0, 2).replace(/[^a-zA-Z]/g, '').toUpperCase();
  const digits = raw.slice(2).replace(/\D/g, '').slice(0, 7);

  return letters + digits;
};

/**
 * Xodim uchun elektron ruxsatnoma so'rovi.
 *
 * Qaror Ichki xavfsizlik bo'limida qabul qilinadi — bu sahifada faqat so'rov
 * yuboriladi va o'z so'rovlarining holati kuzatiladi.
 */
export const PermitRequestPage: React.FC = () => {
  const t = useT();
  const toast = useToastStore();

  const [lastName, setLastName] = useState('');
  const [firstName, setFirstName] = useState('');
  const [middleName, setMiddleName] = useState('');
  const [documentType, setDocumentType] = useState('ID_CARD');
  const [documentNumber, setDocumentNumber] = useState('');
  const [visitorOrg, setVisitorOrg] = useState('');
  const [hostDepartment, setHostDepartment] = useState('');
  const [purpose, setPurpose] = useState('');
  const [visitAt, setVisitAt] = useState('');
  const [photo, setPhoto] = useState<File | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [rows, setRows] = useState<PermitRequest[]>([]);
  const photoInputRef = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    try {
      const res = await axiosClient.get<{ data: PermitRequest[] }>('/permit-requests/mine');
      setRows(res.data?.data ?? []);
    } catch {
      setRows([]);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const submit = async () => {
    setIsSaving(true);
    try {
      // Rasm bor, shuning uchun JSON emas — FormData.
      const form = new FormData();
      form.append('last_name', lastName);
      form.append('first_name', firstName);
      if (middleName) form.append('middle_name', middleName);
      form.append('document_type', documentType);
      if (documentNumber) form.append('document_number', documentNumber);
      if (visitorOrg) form.append('visitor_organization', visitorOrg);
      if (hostDepartment) form.append('host_department', hostDepartment);
      form.append('visit_purpose', purpose);
      if (visitAt) form.append('visit_at', visitAt);
      if (photo) form.append('photo', photo);

      await axiosClient.post('/permit-requests', form);
      toast.success(t('permitReq.sent'));
      setLastName('');
      setFirstName('');
      setMiddleName('');
      setDocumentType('ID_CARD');
      setDocumentNumber('');
      setVisitorOrg('');
      setHostDepartment('');
      setPurpose('');
      setVisitAt('');
      setPhoto(null);
      if (photoInputRef.current) photoInputRef.current.value = '';
      await load();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('permitReq.failed'));
    } finally {
      setIsSaving(false);
    }
  };

  const canSubmit = lastName.trim() !== '' && firstName.trim() !== '' && purpose.trim() !== '' && !isSaving;

  const field = 'w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-sm font-semibold outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500';
  const label = 'block text-xs font-bold text-slate-500 dark:text-slate-400 mb-1.5';

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      <header className="flex items-center gap-3">
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 dark:bg-brand-950/40 dark:text-brand-300">
          <DoorOpen className="h-6 w-6" />
        </span>
        <div className="min-w-0">
          <h1 className="truncate text-xl font-extrabold text-slate-900 dark:text-slate-100">{t('permitReq.title')}</h1>
          <p className="truncate text-xs font-semibold text-slate-500 dark:text-slate-400">{t('permitReq.subtitle')}</p>
        </div>
      </header>

      <section className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 p-4 sm:p-6 space-y-4">
        <p className="text-xs font-semibold text-slate-400">{t('permitReq.requiredHint')}</p>

        <div className="grid gap-4 sm:grid-cols-3">
          <div>
            <label className={label} htmlFor="pr-last">
              {t('permitReq.lastName')} <RequiredMark />
            </label>
            <input id="pr-last" value={lastName} onChange={(e) => setLastName(e.target.value)} className={field} />
          </div>
          <div>
            <label className={label} htmlFor="pr-first">
              {t('permitReq.firstName')} <RequiredMark />
            </label>
            <input id="pr-first" value={firstName} onChange={(e) => setFirstName(e.target.value)} className={field} />
          </div>
          <div>
            <label className={label} htmlFor="pr-middle">{t('permitReq.middleName')}</label>
            <input id="pr-middle" value={middleName} onChange={(e) => setMiddleName(e.target.value)} className={field} />
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className={label} htmlFor="pr-doctype">
              {t('permitReq.documentType')} <RequiredMark />
            </label>
            <select id="pr-doctype" value={documentType} onChange={(e) => setDocumentType(e.target.value)} className={field}>
              <option value="ID_CARD">{t('permitReq.docIdCard')}</option>
              <option value="PASSPORT">{t('permitReq.docPassport')}</option>
              <option value="DRIVER_LICENSE">{t('permitReq.docDriverLicense')}</option>
            </select>
          </div>
          <div>
            <label className={label} htmlFor="pr-doc">{t('permitReq.documentNumber')}</label>
            <input
              id="pr-doc"
              value={documentNumber}
              /* Shakl: 2 ta katta harf + 7 ta raqam. Kiritish paytida katta
                 harfga o'giriladi va ortiqcha belgi umuman yozilmaydi —
                 backend ham shu qoidani tekshiradi. */
              onChange={(e) => setDocumentNumber(formatDocumentNumber(e.target.value))}
              placeholder="AA1234567"
              maxLength={9}
              className={field}
            />
            <p className="mt-1 text-[11px] font-semibold text-slate-400">{t('permitReq.docNumberHint')}</p>
          </div>
          <div>
            <label className={label} htmlFor="pr-org">{t('permitReq.visitorOrg')}</label>
            <input id="pr-org" value={visitorOrg} onChange={(e) => setVisitorOrg(e.target.value)} className={field} />
          </div>
          <div>
            <label className={label} htmlFor="pr-dept">{t('permitReq.hostDepartment')}</label>
            <input id="pr-dept" value={hostDepartment} onChange={(e) => setHostDepartment(e.target.value)} className={field} />
          </div>
        </div>

        <div>
          <label className={label} htmlFor="pr-purpose">
            {t('permitReq.purpose')} <RequiredMark />
          </label>
          <textarea id="pr-purpose" rows={3} maxLength={2000} value={purpose} onChange={(e) => setPurpose(e.target.value)} className={field} />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <label className={label} htmlFor="pr-date">{t('permitReq.visitAt')}</label>
            <input id="pr-date" type="datetime-local" value={visitAt} onChange={(e) => setVisitAt(e.target.value)} className={field} />
          </div>
          <div>
            <label className={label} htmlFor="pr-photo">{t('permitReq.photo')}</label>
            <input
              id="pr-photo"
              ref={photoInputRef}
              type="file"
              accept="image/jpeg,image/png"
              onChange={(e) => setPhoto(e.target.files?.[0] ?? null)}
              className="w-full text-xs font-semibold text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-bold file:text-slate-700 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200"
            />
            <p className="mt-1 text-[11px] font-semibold text-slate-400">{t('permitReq.photoHint')}</p>
          </div>
        </div>

        <div className="flex justify-end">
          <button
            type="button"
            onClick={submit}
            disabled={!canSubmit}
            className="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-6 py-2.5 text-xs font-bold text-white hover:bg-brand-600 disabled:opacity-50"
          >
            {isSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
            {t('permitReq.submit')}
          </button>
        </div>
      </section>

      <section className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 p-4 sm:p-6">
        <h2 className="mb-4 text-sm font-extrabold text-slate-800 dark:text-slate-100">{t('permitReq.mine')}</h2>

        {rows.length === 0 ? (
          <EmptyState title={t('permitReq.empty')} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[600px] text-left text-sm">
              <thead>
                <tr className="text-xs font-bold uppercase tracking-wide text-slate-400">
                  <th className="pb-2">{t('permitReq.lastName')}</th>
                  <th className="pb-2">{t('permitReq.purpose')}</th>
                  <th className="pb-2">{t('permitReq.visitAt')}</th>
                  <th className="pb-2">{t('permitReq.status.PENDING')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {rows.map((row) => (
                  <tr key={row.id} className="text-slate-700 dark:text-slate-200">
                    <td className="py-2.5 font-semibold">{row.full_name}</td>
                    <td className="py-2.5 max-w-[280px] truncate">{row.visit_purpose}</td>
                    <td className="py-2.5">{row.visit_at ? new Date(row.visit_at).toLocaleString() : '—'}</td>
                    <td className="py-2.5">
                      <PermitStatusBadge status={row.status} />
                      {row.status === 'REJECTED' && row.decision_reason && (
                        <span className="block text-[11px] font-semibold text-slate-400">{row.decision_reason}</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </div>
  );
};
