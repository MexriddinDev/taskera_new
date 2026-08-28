import React, { useState, useEffect } from 'react';
import {
  CheckCircle,
  XCircle,
  Clock,
  Search,
  Filter,
  RefreshCw,
  User,
  ArrowLeft,
  Calendar,
  AlertCircle,
  FileCheck,
  MessageSquare,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useToastStore } from '@/shared/presentation/store/useToastStore';

interface ApprovalItem {
  id: number;
  public_id?: string;
  approvable_type: string;
  approvable_id: number;
  status: 'PENDING' | 'APPROVED' | 'REJECTED';
  completed_at?: string;
  created_at?: string;
  steps?: Array<{ id: number; approver_user_id?: number; status: string; comment?: string }>;
}

export const ApprovalsPage: React.FC = () => {
  const t = useT();
  const { user } = useCan();

  const [approvals, setApprovals] = useState<ApprovalItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState<'pending' | 'history'>('pending');
  const [decisionModal, setDecisionModal] = useState<{ id: number; action: 'approve' | 'reject' } | null>(null);
  const [comment, setComment] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchApprovals = () => {
    setLoading(true);
    axiosClient
      .get('/approval-requests', {
        params: {
          status: activeTab === 'pending' ? 'PENDING' : undefined,
          per_page: 20,
        },
      })
      .then((res) => {
        if (res.data?.data) {
          const list: ApprovalItem[] = res.data.data;
          if (activeTab === 'history') {
            setApprovals(list.filter((a) => a.status !== 'PENDING'));
          } else {
            setApprovals(list.filter((a) => a.status === 'PENDING'));
          }
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchApprovals();
  }, [activeTab]);

  const handleDecision = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!decisionModal) return;

    setSubmitting(true);
    try {
      if (decisionModal.action === 'approve') {
        await axiosClient.post(`/approval-requests/${decisionModal.id}/approve`, {
          comment,
        });
        useToastStore.getState().success('Tasdiqlash so\'rovi ma\'qullandi');
      } else {
        await axiosClient.post(`/approval-requests/${decisionModal.id}/reject`, {
          comment,
        });
        useToastStore.getState().warning('Tasdiqlash so\'rovi rad etildi');
      }
      setSubmitting(false);
      setDecisionModal(null);
      setComment('');
      fetchApprovals();
    } catch (err: any) {
      setSubmitting(false);
      useToastStore.getState().error(err.response?.data?.message || 'Qarorni saqlashda xatolik yuz berdi');
    }
  };

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <Link
            to="/requests"
            className="inline-flex items-center text-xs font-bold text-slate-500 hover:text-brand-500 transition-colors mb-2"
          >
            <ArrowLeft className="w-4 h-4 mr-1" /> Orqaga
          </Link>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-3">
            <CheckCircle className="w-8 h-8 text-emerald-500" />
            <span>{t('approvals.title')}</span>
          </h1>
          <p className="text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">
            {t('approvals.subtitle')}
          </p>
        </div>

        <button
          onClick={fetchApprovals}
          className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all cursor-pointer"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {/* Tabs */}
      <div className="flex items-center space-x-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        <button
          onClick={() => setActiveTab('pending')}
          className={`flex items-center space-x-2 px-4 py-2.5 rounded-xl text-xs font-extrabold transition-all cursor-pointer ${
            activeTab === 'pending'
              ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/20'
              : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
          }`}
        >
          <Clock className="w-4 h-4" />
          <span>{t('approvals.pending')}</span>
        </button>
        <button
          onClick={() => setActiveTab('history')}
          className={`flex items-center space-x-2 px-4 py-2.5 rounded-xl text-xs font-extrabold transition-all cursor-pointer ${
            activeTab === 'history'
              ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/20'
              : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
          }`}
        >
          <FileCheck className="w-4 h-4" />
          <span>{t('approvals.history')}</span>
        </button>
      </div>

      {/* Approvals list */}
      <div className="space-y-4">
        {loading ? (
          <div className="py-20 text-center text-slate-400">
            <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2" />
            Yuklanmoqda...
          </div>
        ) : approvals.length === 0 ? (
          <div className="py-16 text-center bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-8">
            <CheckCircle className="w-12 h-12 text-slate-300 mx-auto mb-3" />
            <h3 className="text-base font-bold text-slate-700 dark:text-slate-300">{t('approvals.noApprovals')}</h3>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {approvals.map((item) => (
              <div
                key={item.id}
                className="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 flex flex-col justify-between"
              >
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="px-2.5 py-0.5 rounded-md text-[10px] font-black uppercase bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                      {item.approvable_type.replace(/App\\Models\\/g, '')} #{item.approvable_id}
                    </span>
                    <span className="text-[11px] text-slate-400 font-mono">
                      {item.created_at || 'Yaqinda'}
                    </span>
                  </div>

                  <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">
                    Tasdiqlash so'rovi #{item.id}
                  </h3>
                  <p className="text-xs text-slate-500">
                    Obyekt: <strong className="text-slate-700 dark:text-slate-300">{item.approvable_type} (ID: {item.approvable_id})</strong>
                  </p>
                </div>

                {item.status === 'PENDING' ? (
                  <div className="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <button
                      onClick={() => setDecisionModal({ id: item.id, action: 'reject' })}
                      className="px-4 py-2 rounded-xl bg-red-50 hover:bg-red-100 dark:bg-red-950/60 dark:hover:bg-red-900/60 text-red-600 text-xs font-bold transition-colors cursor-pointer"
                    >
                      Rad etish
                    </button>
                    <button
                      onClick={() => setDecisionModal({ id: item.id, action: 'approve' })}
                      className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-md shadow-emerald-600/20 transition-colors cursor-pointer"
                    >
                      Tasdiqlash
                    </button>
                  </div>
                ) : (
                  <div className="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                    <span className="text-slate-400">Holat:</span>
                    <span
                      className={`font-black ${
                        item.status === 'APPROVED' ? 'text-emerald-600' : 'text-red-600'
                      }`}
                    >
                      {item.status === 'APPROVED' ? 'Tasdiqlangan' : 'Rad etilgan'}
                    </span>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* DECISION MODAL */}
      {decisionModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-slate-100 mb-2">
              {decisionModal.action === 'approve' ? 'Tasdiqlash' : 'Rad etish'}
            </h2>
            <form onSubmit={handleDecision} className="space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  Izoh yoki sabab
                </label>
                <textarea
                  rows={3}
                  placeholder="Qaroringiz bo'yicha izoh..."
                  value={comment}
                  onChange={(e) => setComment(e.target.value)}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                />
              </div>
              <div className="flex justify-end space-x-2 pt-2">
                <button
                  type="button"
                  onClick={() => setDecisionModal(null)}
                  className="px-4 py-2 rounded-xl border text-xs font-bold"
                >
                  Bekor qilish
                </button>
                <button
                  type="submit"
                  disabled={submitting}
                  className={`px-4 py-2 rounded-xl text-white text-xs font-bold ${
                    decisionModal.action === 'approve' ? 'bg-emerald-600' : 'bg-red-600'
                  }`}
                >
                  {submitting ? 'Saqlanmoqda...' : 'Tasdiqlash'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
