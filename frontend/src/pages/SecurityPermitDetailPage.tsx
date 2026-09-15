import React, { useCallback, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Check, Loader2, LogIn, LogOut, X } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { useT } from '@/shared/presentation/i18n/i18n';
import { PermitRequest, PermitStatusBadge } from '@/shared/presentation/components/PermitStatusBadge';

/**
 * Ichki xavfsizlik → Elektron ruxsatnoma tafsiloti.
 *
 * Alohida sahifa (modal emas): so'rovning o'z manzili bor, yangilanganda ham
 * ochiq qoladi va havolasini yuborish mumkin.
 */
export const SecurityPermitDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const t = useT();
  const toast = useToastStore();

  const [row, setRow] = useState<PermitRequest | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isBusy, setIsBusy] = useState(false);
  const [isRejecting, setIsRejecting] = useState(false);
  const [reason, setReason] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await axiosClient.get<{ data: PermitRequest }>(`/permit-requests/${id}`);
      setRow(res.data.data);
    } catch {
      setRow(null);
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  const act = async (path: string, body?: Record<string, unknown>) => {
    setIsBusy(true);
    try {
      const res = await axiosClient.post<{ data: PermitRequest }>(`/permit-requests/${id}/${path}`, body);
      setRow(res.data.data);
      setIsRejecting(false);
      setReason('');
      toast.success(t('permitReq.decided'));
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('permitReq.decideFailed'));
    } finally {
      setIsBusy(false);
    }
  };

  const field = 'w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-sm font-semibold outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500';

  const docLabel = (type: string | null) => {
    if (type === 'DRIVER_LICENSE') return t('permitReq.docDriverLicense');
    if (type === 'PASSPORT') return t('permitReq.docPassport');

    return t('permitReq.docIdCard');
  };

  const when = (value: string | null) => (value ? new Date(value).toLocaleString() : null);

  if (isLoading) {
    return (
      <div className="flex min-h-[50vh] items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-rose-500" />
      </div>
    );
  }

  if (!row) {
    return (
      <div className="p-4 sm:p-6 lg:p-8">
        <button
          type="button"
          onClick={() => navigate('/security-permits')}
          className="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-rose-500"
        >
          <ArrowLeft className="h-4 w-4" />
          {t('permitReq.queue')}
        </button>
        <p className="mt-6 text-sm font-semibold text-slate-500">{t('permitReq.empty')}</p>
      </div>
    );
  }

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-5">
      <button
        type="button"
        onClick={() => navigate('/security-permits')}
        className="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-rose-500"
      >
        <ArrowLeft className="h-4 w-4" />
        {t('permitReq.queue')}
      </button>

      <header className="flex flex-wrap items-center justify-between gap-3">
        <div className="min-w-0">
          <h1 className="text-2xl font-extrabold text-slate-900 dark:text-slate-100">{row.id}</h1>
          <p className="truncate text-xs font-semibold text-slate-500 dark:text-slate-400">{row.full_name}</p>
        </div>

        {/* Kirdi / Chiqdi — qorovul posti shu tugmalarni bosadi. */}
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => act('enter')}
            disabled={isBusy || row.status !== 'APPROVED' || row.entered_at !== null}
            className="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-600 disabled:opacity-40"
          >
            <LogIn className="h-4 w-4" />
            {t('permitReq.markEntered')}
          </button>
          <button
            type="button"
            onClick={() => act('exit')}
            disabled={isBusy || row.entered_at === null || row.exited_at !== null}
            className="inline-flex items-center gap-2 rounded-xl bg-slate-600 px-4 py-2 text-xs font-bold text-white hover:bg-slate-700 disabled:opacity-40"
          >
            <LogOut className="h-4 w-4" />
            {t('permitReq.markExited')}
          </button>
        </div>
      </header>

      <section className="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900/40">
        <dl className="divide-y divide-slate-100 text-sm dark:divide-slate-800">
          <Row label="ID" value={String(row.id)} />
          <Row label={t('permitReq.createdAt')} value={when(row.created_at)} />
          <Row label="FIO" value={row.full_name} />
          <Row label={t('permitReq.documentType')} value={docLabel(row.document_type)} />
          <Row label={t('permitReq.documentNumber')} value={row.document_number} />
          <Row label={t('permitReq.visitorOrg')} value={row.visitor_organization} />
          <Row label={t('permitReq.hostDepartment')} value={row.host_department} />
          <Row label={t('permitReq.purpose')} value={row.visit_purpose} />
          <Row label={t('permitReq.visitAt')} value={when(row.visit_at)} />

          <div className="flex gap-4 px-5 py-3">
            <dt className="w-56 shrink-0 text-xs font-bold text-slate-500 dark:text-slate-400">Status</dt>
            <dd><PermitStatusBadge status={row.status} /></dd>
          </div>

          <Row label={t('permitReq.enteredAt')} value={when(row.entered_at)} />
          <Row label={t('permitReq.exitedAt')} value={when(row.exited_at)} />

          {/* So'rovni kim yuborgan — xodim kartochkasi. */}
          <Row label={t('permitReq.employee')} value={row.requester_card.name} />
          <Row label={t('permitReq.employeeDepartment')} value={row.requester_card.department} />
          <Row label={t('permitReq.employeePosition')} value={row.requester_card.position} />

          {row.decision_reason && <Row label={t('permitReq.rejectReason')} value={row.decision_reason} />}
        </dl>
      </section>

      {row.status === 'PENDING' && (
        <div className="flex flex-wrap justify-end gap-2">
          <button
            type="button"
            onClick={() => { setIsRejecting(true); setReason(''); }}
            disabled={isBusy}
            className="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 px-5 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50 disabled:opacity-50 dark:border-rose-900 dark:hover:bg-rose-950/40"
          >
            <X className="h-3.5 w-3.5" />
            {t('permitReq.reject')}
          </button>
          <button
            type="button"
            onClick={() => act('decide', { status: 'APPROVED' })}
            disabled={isBusy}
            className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-500 px-5 py-2.5 text-xs font-bold text-white hover:bg-emerald-600 disabled:opacity-50"
          >
            {isBusy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Check className="h-3.5 w-3.5" />}
            {t('permitReq.approve')}
          </button>
        </div>
      )}

      {/* Rad etish — sabab majburiy, backend ham buni talab qiladi. */}
      {isRejecting && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
          <div className="w-full max-w-md rounded-2xl bg-white p-5 dark:bg-slate-900">
            <h3 className="mb-3 text-sm font-extrabold text-slate-800 dark:text-slate-100">
              {t('permitReq.reject')}: {row.full_name}
            </h3>
            <textarea
              rows={3}
              maxLength={2000}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder={t('permitReq.rejectReason')}
              className={field}
            />
            <div className="mt-4 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => setIsRejecting(false)}
                className="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 dark:border-slate-700 dark:text-slate-300"
              >
                {t('common.cancel')}
              </button>
              <button
                type="button"
                onClick={() => act('decide', { status: 'REJECTED', decision_reason: reason })}
                disabled={reason.trim() === '' || isBusy}
                className="rounded-xl bg-rose-500 px-4 py-2 text-xs font-bold text-white hover:bg-rose-600 disabled:opacity-50"
              >
                {t('permitReq.reject')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

/** Tafsilotdagi bitta qator. Bo'sh qiymat "belgilanmagan" bo'lib ko'rinadi. */
const Row: React.FC<{ label: string; value: string | null }> = ({ label, value }) => {
  const t = useT();

  return (
    <div className="flex flex-wrap gap-4 px-5 py-3">
      <dt className="w-56 shrink-0 text-xs font-bold text-slate-500 dark:text-slate-400">{label}</dt>
      <dd className={`min-w-0 break-words ${value ? 'text-slate-800 dark:text-slate-100' : 'text-slate-400'}`}>
        {value || t('permitReq.notSet')}
      </dd>
    </div>
  );
};
