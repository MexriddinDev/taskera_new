import React, { useState, useEffect } from 'react';
import {
  GitBranch,
  Search,
  Plus,
  Filter,
  RefreshCw,
  Edit2,
  Trash2,
  CheckCircle2,
  XCircle,
  Clock,
  Calendar,
  AlertTriangle,
  ArrowLeft,
  X,
  FileText,
  ShieldAlert,
  Layers,
  User,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useToastStore } from '@/shared/presentation/store/useToastStore';

interface ChangeItem {
  id: number;
  change_no?: string;
  title: string;
  description?: string;
  change_type: 'STANDARD' | 'NORMAL' | 'EMERGENCY';
  risk_level?: string;
  impact?: string;
  status?: string;
  approval_status: 'PENDING' | 'APPROVED' | 'REJECTED';
  requester_user_id?: number;
  owner_user_id?: number;
  planned_start_at?: string;
  planned_end_at?: string;
  implementation_plan?: string;
  test_plan?: string;
  backout_plan?: string;
  requesterUser?: { id: number; username: string; firstName?: string; lastName?: string };
  ownerUser?: { id: number; username: string; firstName?: string; lastName?: string };
  created_at?: string;
}

interface MaintenanceWindowItem {
  id: number;
  title: string;
  start_at: string;
  end_at: string;
  impact_level?: string;
  service_id?: number;
  description?: string;
}

export const ChangesPage: React.FC = () => {
  const t = useT();
  const { user } = useCan();
  const [activeTab, setActiveTab] = useState<'changes' | 'maintenance'>('changes');

  // Changes list state
  const [changes, setChanges] = useState<ChangeItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [approvalFilter, setApprovalFilter] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  // Maintenance Windows
  const [windows, setWindows] = useState<MaintenanceWindowItem[]>([]);

  // Modals
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [editingChange, setEditingChange] = useState<ChangeItem | null>(null);
  const [viewingChange, setViewingChange] = useState<ChangeItem | null>(null);

  // Approve / Reject modal
  const [decisionModal, setDecisionModal] = useState<{ id: number; action: 'approve' | 'reject' } | null>(null);
  const [decisionComment, setDecisionComment] = useState('');

  // Maintenance Modal
  const [isWindowModalOpen, setIsWindowModalOpen] = useState(false);
  const [windowForm, setWindowForm] = useState({
    title: '',
    start_at: '',
    end_at: '',
    description: '',
  });

  // Change Form State
  const [changeForm, setChangeForm] = useState({
    title: '',
    description: '',
    change_type: 'NORMAL' as 'STANDARD' | 'NORMAL' | 'EMERGENCY',
    risk_level: 'MEDIUM',
    impact: 'MEDIUM',
    status: 'DRAFT',
    planned_start_at: '',
    planned_end_at: '',
    implementation_plan: '',
    test_plan: '',
    backout_plan: '',
  });

  const fetchChanges = (page = 1) => {
    setLoading(true);
    axiosClient
      .get('/changes', {
        params: {
          page,
          search: search || undefined,
          change_type: typeFilter || undefined,
          approval_status: approvalFilter || undefined,
          per_page: 15,
        },
      })
      .then((res) => {
        if (res.data?.data) {
          setChanges(res.data.data);
          setTotalPages(res.data.meta?.last_page || 1);
          setCurrentPage(res.data.meta?.current_page || 1);
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  const fetchWindows = () => {
    axiosClient.get('/maintenance-windows').then((res) => {
      if (res.data?.data) setWindows(res.data.data);
    }).catch(() => {});
  };

  useEffect(() => {
    if (activeTab === 'changes') {
      fetchChanges(1);
    } else {
      fetchWindows();
    }
  }, [activeTab, search, typeFilter, approvalFilter]);

  const handleOpenCreate = () => {
    setEditingChange(null);
    setChangeForm({
      title: '',
      description: '',
      change_type: 'NORMAL',
      risk_level: 'MEDIUM',
      impact: 'MEDIUM',
      status: 'DRAFT',
      planned_start_at: '',
      planned_end_at: '',
      implementation_plan: '',
      test_plan: '',
      backout_plan: '',
    });
    setIsFormModalOpen(true);
  };

  const handleOpenEdit = (c: ChangeItem) => {
    setEditingChange(c);
    setChangeForm({
      title: c.title,
      description: c.description || '',
      change_type: c.change_type,
      risk_level: c.risk_level || 'MEDIUM',
      impact: c.impact || 'MEDIUM',
      status: c.status || 'DRAFT',
      planned_start_at: c.planned_start_at || '',
      planned_end_at: c.planned_end_at || '',
      implementation_plan: c.implementation_plan || '',
      test_plan: c.test_plan || '',
      backout_plan: c.backout_plan || '',
    });
    setIsFormModalOpen(true);
  };

  const handleSaveChange = async (e: React.FormEvent) => {
    e.preventDefault();
    const payload = {
      ...changeForm,
      planned_start_at: changeForm.planned_start_at || null,
      planned_end_at: changeForm.planned_end_at || null,
    };

    try {
      if (editingChange) {
        await axiosClient.put(`/changes/${editingChange.id}`, payload);
      } else {
        await axiosClient.post('/changes', payload);
      }
      setIsFormModalOpen(false);
      fetchChanges(currentPage);
      useToastStore.getState().success(editingChange ? 'O\'zgarish so\'rovi tahrirlandi' : 'Yangi o\'zgarish so\'rovi (RFC) yaratildi');
    } catch (err: any) {
      useToastStore.getState().error(err.response?.data?.message || 'RFC saqlashda xatolik yuz berdi');
    }
  };

  const handleDecisionSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!decisionModal) return;

    try {
      if (decisionModal.action === 'approve') {
        await axiosClient.post(`/changes/${decisionModal.id}/approve`, {
          comment: decisionComment,
        });
        useToastStore.getState().success('O\'zgarish so\'rovi (RFC) ma\'qullandi');
      } else {
        await axiosClient.post(`/changes/${decisionModal.id}/reject`, {
          comment: decisionComment,
        });
        useToastStore.getState().warning('O\'zgarish so\'rovi (RFC) rad etildi');
      }
      setDecisionModal(null);
      setDecisionComment('');
      fetchChanges(currentPage);
    } catch (err: any) {
      useToastStore.getState().error(err.response?.data?.message || 'Qarorni saqlashda xatolik');
    }
  };

  const handleCreateWindow = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await axiosClient.post('/maintenance-windows', windowForm);
      setIsWindowModalOpen(false);
      setWindowForm({ title: '', start_at: '', end_at: '', description: '' });
      fetchWindows();
      useToastStore.getState().success('Texnik tanaffus oynasi yaratildi');
    } catch (err: any) {
      useToastStore.getState().error(err.response?.data?.message || 'Texnik tanaffusni saqlashda xatolik');
    }
  };

  const handleDeleteChange = async (id: number) => {
    if (!window.confirm('O\'zgarish so\'rovini o\'chirishni tasdiqlaysizmi?')) return;
    try {
      await axiosClient.delete(`/changes/${id}`);
      fetchChanges(currentPage);
      useToastStore.getState().success('O\'zgarish so\'rovi o\'chirildi');
    } catch (err: any) {
      useToastStore.getState().error(err.response?.data?.message || 'O\'chirishda xatolik yuz berdi');
    }
  };

  const getRiskBadge = (risk?: string) => {
    switch (risk?.toUpperCase()) {
      case 'CRITICAL':
        return <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">Kritik</span>;
      case 'HIGH':
        return <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300">Yuqori</span>;
      case 'MEDIUM':
        return <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">O'rta</span>;
      default:
        return <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Past</span>;
    }
  };

  const getApprovalBadge = (status?: string) => {
    switch (status) {
      case 'APPROVED':
        return <span className="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-600 border border-emerald-200 dark:bg-emerald-950 dark:border-emerald-800">Tasdiqlangan</span>;
      case 'REJECTED':
        return <span className="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-red-50 text-red-600 border border-red-200 dark:bg-red-950 dark:border-red-800">Rad etilgan</span>;
      default:
        return <span className="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-amber-50 text-amber-600 border border-amber-200 dark:bg-amber-950 dark:border-amber-800">Kutilmoqda</span>;
    }
  };

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <Link
            to="/dashboard"
            className="inline-flex items-center text-xs font-bold text-slate-500 hover:text-blue-500 transition-colors mb-2"
          >
            <ArrowLeft className="w-4 h-4 mr-1" /> {t('audit.backToDashboard')}
          </Link>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-3">
            <GitBranch className="w-8 h-8 text-blue-500" />
            <span>{t('changes.title')}</span>
          </h1>
          <p className="text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">
            {t('changes.subtitle')}
          </p>
        </div>

        <div className="flex items-center space-x-3">
          <button
            onClick={() => fetchChanges(currentPage)}
            className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all cursor-pointer"
            title="Yangilash"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
          <button
            onClick={handleOpenCreate}
            className="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center space-x-2 shadow-md shadow-blue-600/20 transition-all cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            <span>{t('changes.newChange')}</span>
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex items-center space-x-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        <button
          onClick={() => setActiveTab('changes')}
          className={`flex items-center space-x-2 px-4 py-2.5 rounded-xl text-xs font-extrabold transition-all cursor-pointer ${
            activeTab === 'changes'
              ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20'
              : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
          }`}
        >
          <GitBranch className="w-4 h-4" />
          <span>{t('changes.tabChanges')}</span>
        </button>
        <button
          onClick={() => setActiveTab('maintenance')}
          className={`flex items-center space-x-2 px-4 py-2.5 rounded-xl text-xs font-extrabold transition-all cursor-pointer ${
            activeTab === 'maintenance'
              ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20'
              : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
          }`}
        >
          <Clock className="w-4 h-4" />
          <span>{t('changes.tabMaintenance')}</span>
        </button>
      </div>

      {/* TAB 1: CHANGES */}
      {activeTab === 'changes' && (
        <div className="space-y-4">
          {/* Filters Bar */}
          <div className="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="relative">
              <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
              <input
                type="text"
                placeholder="RFC raqami, sarlavha..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold focus:ring-2 focus:ring-blue-500 outline-none"
              />
            </div>

            <select
              value={typeFilter}
              onChange={(e) => setTypeFilter(e.target.value)}
              className="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold focus:ring-2 focus:ring-blue-500 outline-none"
            >
              <option value="">Barcha turlar</option>
              <option value="STANDARD">{t('changes.typeStandard')}</option>
              <option value="NORMAL">{t('changes.typeNormal')}</option>
              <option value="EMERGENCY">{t('changes.typeEmergency')}</option>
            </select>

            <select
              value={approvalFilter}
              onChange={(e) => setApprovalFilter(e.target.value)}
              className="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold focus:ring-2 focus:ring-blue-500 outline-none"
            >
              <option value="">Barcha tasdiq holatlari</option>
              <option value="PENDING">{t('changes.pendingApproval')}</option>
              <option value="APPROVED">{t('changes.approved')}</option>
              <option value="REJECTED">{t('changes.rejected')}</option>
            </select>
          </div>

          {/* Table */}
          <div className="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 text-[11px] font-black uppercase text-slate-500 tracking-wider">
                    <th className="py-4 px-6">{t('changes.changeNo')}</th>
                    <th className="py-4 px-6">Sarlavha & Turi</th>
                    <th className="py-4 px-6">Xavf / Risk</th>
                    <th className="py-4 px-6">Tasdiq Holati</th>
                    <th className="py-4 px-6 text-right">Amallar</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-xs font-semibold">
                  {loading ? (
                    <tr>
                      <td colSpan={5} className="py-12 text-center text-slate-400">
                        <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2" />
                        Yuklanmoqda...
                      </td>
                    </tr>
                  ) : changes.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="py-12 text-center text-slate-400">
                        {t('changes.noChanges')}
                      </td>
                    </tr>
                  ) : (
                    changes.map((change) => (
                      <tr key={change.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                        <td className="py-4 px-6 font-mono font-black text-blue-600 dark:text-blue-400">
                          {change.change_no || `CHG-${change.id}`}
                        </td>
                        <td className="py-4 px-6 max-w-md">
                          <div className="font-extrabold text-slate-800 dark:text-slate-200">
                            {change.title}
                          </div>
                          <span className="text-[10px] font-black uppercase text-slate-400">
                            {change.change_type}
                          </span>
                        </td>
                        <td className="py-4 px-6">
                          {getRiskBadge(change.risk_level)}
                        </td>
                        <td className="py-4 px-6">
                          {getApprovalBadge(change.approval_status)}
                        </td>
                        <td className="py-4 px-6 text-right">
                          <div className="flex items-center justify-end space-x-1.5">
                            {change.approval_status === 'PENDING' && (
                              <>
                                <button
                                  onClick={() => setDecisionModal({ id: change.id, action: 'approve' })}
                                  className="p-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:hover:bg-emerald-900/60 transition-colors"
                                  title="Tasdiqlash"
                                >
                                  <CheckCircle2 className="w-4 h-4" />
                                </button>
                                <button
                                  onClick={() => setDecisionModal({ id: change.id, action: 'reject' })}
                                  className="p-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-950/60 dark:hover:bg-red-900/60 transition-colors"
                                  title="Rad etish"
                                >
                                  <XCircle className="w-4 h-4" />
                                </button>
                              </>
                            )}
                            <button
                              onClick={() => handleOpenEdit(change)}
                              className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 hover:text-blue-500 transition-colors"
                              title="Tahrirlash"
                            >
                              <Edit2 className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => handleDeleteChange(change.id)}
                              className="p-1.5 rounded-lg hover:bg-error-50 dark:hover:bg-error-950/40 text-slate-500 hover:text-error-500 transition-colors"
                              title="O'chirish"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {totalPages > 1 && (
              <div className="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <span className="text-xs text-slate-500">
                  Sahifa {currentPage} / {totalPages}
                </span>
                <div className="flex space-x-2">
                  <button
                    disabled={currentPage <= 1}
                    onClick={() => fetchChanges(currentPage - 1)}
                    className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold disabled:opacity-40"
                  >
                    Oldingi
                  </button>
                  <button
                    disabled={currentPage >= totalPages}
                    onClick={() => fetchChanges(currentPage + 1)}
                    className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold disabled:opacity-40"
                  >
                    Keyingi
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* TAB 2: MAINTENANCE WINDOWS */}
      {activeTab === 'maintenance' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <button
              onClick={() => setIsWindowModalOpen(true)}
              className="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs flex items-center space-x-2"
            >
              <Plus className="w-4 h-4" />
              <span>{t('changes.newWindow')}</span>
            </button>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {windows.map((w) => (
              <div
                key={w.id}
                className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3"
              >
                <div className="flex items-center justify-between">
                  <span className="px-2.5 py-0.5 rounded-md text-[10px] font-black uppercase bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300">
                    TEXNIK ISHLAR
                  </span>
                  <Clock className="w-5 h-5 text-slate-400" />
                </div>
                <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">{w.title}</h3>
                {w.description && <p className="text-xs text-slate-500">{w.description}</p>}
                <div className="pt-2 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400 font-mono space-y-1">
                  <div>Boshlanish: <strong className="text-slate-700 dark:text-slate-300">{w.start_at}</strong></div>
                  <div>Yakunlanish: <strong className="text-slate-700 dark:text-slate-300">{w.end_at}</strong></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* CREATE / EDIT CHANGE MODAL */}
      {isFormModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
              <h2 className="text-lg font-black text-slate-900 dark:text-slate-100">
                {editingChange ? t('changes.editChange') : t('changes.newChange')}
              </h2>
              <button
                onClick={() => setIsFormModalOpen(false)}
                className="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveChange} className="py-4 space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  O'zgarish nomi (RFC Title) *
                </label>
                <input
                  type="text"
                  required
                  placeholder="Masalan: Core Router dasturiy ta'minotini yangilash (v4.2)"
                  value={changeForm.title}
                  onChange={(e) => setChangeForm({ ...changeForm, title: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('changes.changeType')}
                  </label>
                  <select
                    value={changeForm.change_type}
                    onChange={(e) => setChangeForm({ ...changeForm, change_type: e.target.value as any })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold focus:ring-2 focus:ring-blue-500 outline-none"
                  >
                    <option value="STANDARD">{t('changes.typeStandard')}</option>
                    <option value="NORMAL">{t('changes.typeNormal')}</option>
                    <option value="EMERGENCY">{t('changes.typeEmergency')}</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('changes.riskLevel')}
                  </label>
                  <select
                    value={changeForm.risk_level}
                    onChange={(e) => setChangeForm({ ...changeForm, risk_level: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold focus:ring-2 focus:ring-blue-500 outline-none"
                  >
                    <option value="LOW">{t('changes.riskLow')}</option>
                    <option value="MEDIUM">{t('changes.riskMedium')}</option>
                    <option value="HIGH">{t('changes.riskHigh')}</option>
                    <option value="CRITICAL">{t('changes.riskCritical')}</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('changes.impact')}
                  </label>
                  <select
                    value={changeForm.impact}
                    onChange={(e) => setChangeForm({ ...changeForm, impact: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold focus:ring-2 focus:ring-blue-500 outline-none"
                  >
                    <option value="LOW">{t('changes.riskLow')}</option>
                    <option value="MEDIUM">{t('changes.riskMedium')}</option>
                    <option value="HIGH">{t('changes.riskHigh')}</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('changes.implementationPlan')}
                </label>
                <textarea
                  rows={2}
                  placeholder="Bosqichma-bosqich bajarish tartibi..."
                  value={changeForm.implementation_plan}
                  onChange={(e) => setChangeForm({ ...changeForm, implementation_plan: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>

              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('changes.testPlan')}
                </label>
                <textarea
                  rows={2}
                  placeholder="O'zgarish muvaffaqiyatini tekshirish usuli..."
                  value={changeForm.test_plan}
                  onChange={(e) => setChangeForm({ ...changeForm, test_plan: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>

              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('changes.backoutPlan')}
                </label>
                <textarea
                  rows={2}
                  placeholder="Muammo chiqsa tizimni oldingi holatga qaytarish rejasi..."
                  value={changeForm.backout_plan}
                  onChange={(e) => setChangeForm({ ...changeForm, backout_plan: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium focus:ring-2 focus:ring-blue-500 outline-none"
                />
              </div>

              <div className="flex items-center justify-end space-x-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsFormModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold"
                >
                  Bekor qilish
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-600/20"
                >
                  Saqlash
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* APPROVAL / REJECTION DECISION MODAL */}
      {decisionModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-slate-100 mb-2">
              {decisionModal.action === 'approve' ? t('changes.approve') : t('changes.reject')}
            </h2>
            <form onSubmit={handleDecisionSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('changes.approvalComment')}
                </label>
                <textarea
                  rows={3}
                  placeholder="Qaroringiz asosnomasi..."
                  value={decisionComment}
                  onChange={(e) => setDecisionComment(e.target.value)}
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
                  className={`px-4 py-2 rounded-xl text-white text-xs font-bold ${
                    decisionModal.action === 'approve' ? 'bg-emerald-600' : 'bg-red-600'
                  }`}
                >
                  Tasdiqlash
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MAINTENANCE WINDOW MODAL */}
      {isWindowModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">{t('changes.newWindow')}</h2>
            <form onSubmit={handleCreateWindow} className="space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Tanaffus sababi *</label>
                <input
                  type="text"
                  required
                  placeholder="Serverlar profilaktikasi"
                  value={windowForm.title}
                  onChange={(e) => setWindowForm({ ...windowForm, title: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Boshlanish</label>
                  <input
                    type="datetime-local"
                    required
                    value={windowForm.start_at}
                    onChange={(e) => setWindowForm({ ...windowForm, start_at: e.target.value })}
                    className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Yakunlanish</label>
                  <input
                    type="datetime-local"
                    required
                    value={windowForm.end_at}
                    onChange={(e) => setWindowForm({ ...windowForm, end_at: e.target.value })}
                    className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                  />
                </div>
              </div>
              <div className="flex justify-end space-x-2 pt-2">
                <button type="button" onClick={() => setIsWindowModalOpen(false)} className="px-4 py-2 rounded-xl border text-xs font-bold">Bekor qilish</button>
                <button type="submit" className="px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
