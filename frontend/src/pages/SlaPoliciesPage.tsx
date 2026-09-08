import React, { useState, useEffect } from 'react';
import {
  Clock,
  Search,
  Plus,
  Filter,
  RefreshCw,
  Edit2,
  Trash2,
  Calendar,
  CheckCircle2,
  AlertCircle,
  ArrowLeft,
  X,
  Sliders,
  Shield,
  Layers,
  Zap,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useToastStore } from '@/shared/presentation/store/useToastStore';

interface SlaPolicyItem {
  id: number;
  code: string;
  name: string;
  applies_to_type?: string;
  is_active: boolean;
  effective_from: string;
  effective_to?: string;
  calendar_id?: number;
}

interface SlaTargetItem {
  id: number;
  sla_policy_id?: number;
  priority_id?: number;
  target_type?: string;
  target_minutes?: number;
  slaPolicy?: { name: string };
  priority?: { name: string };
}

interface BusinessCalendarItem {
  id: number;
  name: string;
  timezone?: string;
  work_start_time?: string;
  work_end_time?: string;
  work_days?: number[];
  is_default?: boolean;
}

export const SlaPoliciesPage: React.FC = () => {
  const t = useT();
  const { user } = useCan();
  const [activeTab, setActiveTab] = useState<'policies' | 'targets' | 'calendars'>('policies');

  // Policies
  const [policies, setPolicies] = useState<SlaPolicyItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');

  // Targets & Calendars
  const [targets, setTargets] = useState<SlaTargetItem[]>([]);
  const [calendars, setCalendars] = useState<BusinessCalendarItem[]>([]);

  // Modals
  const [isPolicyModalOpen, setIsPolicyModalOpen] = useState(false);
  const [editingPolicy, setEditingPolicy] = useState<SlaPolicyItem | null>(null);
  const [policyForm, setPolicyForm] = useState({
    code: '',
    name: '',
    applies_to_type: 'ALL',
    is_active: true,
    effective_from: new Date().toISOString().split('T')[0],
    effective_to: '',
  });

  // Calendar Modal
  const [isCalendarModalOpen, setIsCalendarModalOpen] = useState(false);
  const [calendarForm, setCalendarForm] = useState({
    name: '',
    timezone: 'Asia/Tashkent',
    work_start_time: '09:00',
    work_end_time: '18:00',
  });

  const fetchPolicies = () => {
    setLoading(true);
    axiosClient
      .get('/sla-policies', {
        params: { search: search || undefined, per_page: 50 },
      })
      .then((res) => {
        if (res.data?.data) setPolicies(res.data.data);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  const fetchTargets = () => {
    axiosClient.get('/sla-targets').then((res) => {
      if (res.data?.data) setTargets(res.data.data);
    }).catch(() => {});
  };

  const fetchCalendars = () => {
    axiosClient.get('/business-calendars').then((res) => {
      if (res.data?.data) setCalendars(res.data.data);
    }).catch(() => {});
  };

  useEffect(() => {
    if (activeTab === 'policies') fetchPolicies();
    else if (activeTab === 'targets') fetchTargets();
    else fetchCalendars();
  }, [activeTab, search]);

  const handleOpenCreatePolicy = () => {
    setEditingPolicy(null);
    setPolicyForm({
      code: `SLA-${Math.floor(100 + Math.random() * 900)}`,
      name: '',
      applies_to_type: 'ALL',
      is_active: true,
      effective_from: new Date().toISOString().split('T')[0],
      effective_to: '',
    });
    setIsPolicyModalOpen(true);
  };

  const handleOpenEditPolicy = (p: SlaPolicyItem) => {
    setEditingPolicy(p);
    setPolicyForm({
      code: p.code,
      name: p.name,
      applies_to_type: p.applies_to_type || 'ALL',
      is_active: Boolean(p.is_active),
      effective_from: p.effective_from || '',
      effective_to: p.effective_to || '',
    });
    setIsPolicyModalOpen(true);
  };

  const handleSavePolicy = async (e: React.FormEvent) => {
    e.preventDefault();
    const payload = {
      code: policyForm.code,
      name: policyForm.name,
      applies_to_type: policyForm.applies_to_type,
      is_active: Boolean(policyForm.is_active),
      effective_from: policyForm.effective_from,
      effective_to: policyForm.effective_to || null,
    };

    try {
      if (editingPolicy) {
        await axiosClient.put(`/sla-policies/${editingPolicy.id}`, payload);
      } else {
        await axiosClient.post('/sla-policies', payload);
      }
      setIsPolicyModalOpen(false);
      fetchPolicies();
      useToastStore.getState().success(editingPolicy ? 'SLA siyosati tahrirlandi' : 'Yangi SLA siyosati yaratildi');
    } catch (err: any) {
      useToastStore.getState().error(err.response?.data?.message || 'SLA siyosatini saqlashda xatolik');
    }
  };

  const handleDeletePolicy = async (id: number) => {
    if (!window.confirm('SLA siyosatini o\'chirishni tasdiqlaysizmi?')) return;
    try {
      await axiosClient.delete(`/sla-policies/${id}`);
      fetchPolicies();
      useToastStore.getState().success('SLA siyosati o\'chirildi');
    } catch (err) {
      useToastStore.getState().error('O\'chirishda xatolik yuz berdi');
    }
  };

  const handleCreateCalendar = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await axiosClient.post('/business-calendars', calendarForm);
      setIsCalendarModalOpen(false);
      fetchCalendars();
      useToastStore.getState().success('Ish taqvimi saqlandi');
    } catch (err) {
      useToastStore.getState().error('Kalendarni saqlashda xatolik');
    }
  };

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <Link
            to="/dashboard"
            className="inline-flex items-center text-xs font-bold text-slate-500 hover:text-cyan-500 transition-colors mb-2"
          >
            <ArrowLeft className="w-4 h-4 mr-1" /> {t('audit.backToDashboard')}
          </Link>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-3">
            <Clock className="w-8 h-8 text-cyan-500" />
            <span>{t('sla.title')}</span>
          </h1>
          <p className="text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">
            {t('sla.subtitle')}
          </p>
        </div>

        <div className="flex items-center space-x-3">
          <button
            onClick={() => {
              if (activeTab === 'policies') fetchPolicies();
              else if (activeTab === 'targets') fetchTargets();
              else fetchCalendars();
            }}
            className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all cursor-pointer"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
          {activeTab === 'policies' && (
            <button
              onClick={handleOpenCreatePolicy}
              className="px-4 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white font-bold text-xs flex items-center space-x-2 shadow-md shadow-cyan-600/20 transition-all cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>{t('sla.newPolicy')}</span>
            </button>
          )}
          {activeTab === 'calendars' && (
            <button
              onClick={() => setIsCalendarModalOpen(true)}
              className="px-4 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white font-bold text-xs flex items-center space-x-2 shadow-md shadow-cyan-600/20 transition-all cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>{t('sla.newCalendar')}</span>
            </button>
          )}
        </div>
      </div>

      {/* Tabs */}
      <div className="flex items-center space-x-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        {[
          { id: 'policies', label: t('sla.tabPolicies'), icon: Clock },
          { id: 'targets', label: t('sla.tabTargets'), icon: Sliders },
          { id: 'calendars', label: t('sla.tabCalendars'), icon: Calendar },
        ].map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`flex items-center space-x-2 px-4 py-2.5 rounded-xl text-xs font-extrabold transition-all cursor-pointer ${
                isActive
                  ? 'bg-cyan-600 text-white shadow-md shadow-cyan-600/20'
                  : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
              }`}
            >
              <Icon className="w-4 h-4" />
              <span>{tab.label}</span>
            </button>
          );
        })}
      </div>

      {/* TAB 1: POLICIES */}
      {activeTab === 'policies' && (
        <div className="space-y-4">
          <div className="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 text-[11px] font-black uppercase text-slate-500 tracking-wider">
                    <th className="py-4 px-6">{t('sla.policyCode')}</th>
                    <th className="py-4 px-6">{t('sla.policyName')}</th>
                    <th className="py-4 px-6">{t('sla.effectiveFrom')}</th>
                    <th className="py-4 px-6">Status</th>
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
                  ) : policies.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="py-12 text-center text-slate-400">
                        {t('sla.noPolicies')}
                      </td>
                    </tr>
                  ) : (
                    policies.map((p) => (
                      <tr key={p.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                        <td className="py-4 px-6 font-mono font-black text-cyan-600 dark:text-cyan-400">
                          {p.code}
                        </td>
                        <td className="py-4 px-6 font-extrabold text-slate-800 dark:text-slate-200">
                          {p.name}
                        </td>
                        <td className="py-4 px-6 text-slate-500">
                          {p.effective_from} {p.effective_to ? `— ${p.effective_to}` : '(Cheksiz)'}
                        </td>
                        <td className="py-4 px-6">
                          <span
                            className={`px-2.5 py-1 rounded-full text-[10px] font-black uppercase ${
                              p.is_active
                                ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800'
                            }`}
                          >
                            {p.is_active ? 'Faol' : 'Faol emas'}
                          </span>
                        </td>
                        <td className="py-4 px-6 text-right">
                          <div className="flex items-center justify-end space-x-1.5">
                            <button
                              onClick={() => handleOpenEditPolicy(p)}
                              className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 hover:text-cyan-500 transition-colors"
                              title="Tahrirlash"
                            >
                              <Edit2 className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => handleDeletePolicy(p.id)}
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
          </div>
        </div>
      )}

      {/* TAB 2: TARGETS */}
      {activeTab === 'targets' && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {targets.map((tItem) => (
            <div
              key={tItem.id}
              className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-2"
            >
              <div className="flex items-center justify-between">
                <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-cyan-100 dark:bg-cyan-950 text-cyan-700 dark:text-cyan-300">
                  {tItem.target_type || 'RESOLUTION'}
                </span>
                <Clock className="w-4 h-4 text-slate-400" />
              </div>
              <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">
                {tItem.slaPolicy?.name || `Policy #${tItem.sla_policy_id}`}
              </h3>
              <p className="text-xs text-slate-500">
                Muhimlik: <strong className="text-slate-800 dark:text-slate-200">{tItem.priority?.name || 'Barcha'}</strong>
              </p>
              <div className="text-xs font-bold text-cyan-600 dark:text-cyan-400 pt-2 border-t border-slate-100 dark:border-slate-800">
                Normativ: {tItem.target_minutes ? `${tItem.target_minutes} daqiqa (${(tItem.target_minutes / 60).toFixed(1)} soat)` : '—'}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* TAB 3: CALENDARS */}
      {activeTab === 'calendars' && (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {calendars.map((cal) => (
            <div
              key={cal.id}
              className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3"
            >
              <div className="flex items-center justify-between">
                <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300">
                  {cal.timezone || 'Asia/Tashkent'}
                </span>
                <Calendar className="w-4 h-4 text-slate-400" />
              </div>
              <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">{cal.name}</h3>
              <div className="text-xs text-slate-500 space-y-1 font-mono">
                <div>Ish vaqti: <strong>{cal.work_start_time || '09:00'} - {cal.work_end_time || '18:00'}</strong></div>
                <div>Ish kunlari: <strong>Dushanba - Juma</strong></div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* POLICY MODAL */}
      {isPolicyModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">
              {editingPolicy ? t('sla.editPolicy') : t('sla.newPolicy')}
            </h2>
            <form onSubmit={handleSavePolicy} className="space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('sla.policyCode')} *
                </label>
                <input
                  type="text"
                  required
                  value={policyForm.code}
                  onChange={(e) => setPolicyForm({ ...policyForm, code: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono font-semibold outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('sla.policyName')} *
                </label>
                <input
                  type="text"
                  required
                  placeholder="Masalan: Standard 8x5 IT Support"
                  value={policyForm.name}
                  onChange={(e) => setPolicyForm({ ...policyForm, name: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('sla.effectiveFrom')}
                  </label>
                  <input
                    type="date"
                    required
                    value={policyForm.effective_from}
                    onChange={(e) => setPolicyForm({ ...policyForm, effective_from: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('sla.effectiveTo')}
                  </label>
                  <input
                    type="date"
                    value={policyForm.effective_to}
                    onChange={(e) => setPolicyForm({ ...policyForm, effective_to: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                  />
                </div>
              </div>
              <div className="flex items-center space-x-2">
                <input
                  type="checkbox"
                  id="activePolicy"
                  checked={policyForm.is_active}
                  onChange={(e) => setPolicyForm({ ...policyForm, is_active: e.target.checked })}
                  className="w-4 h-4 rounded text-cyan-600"
                />
                <label htmlFor="activePolicy" className="text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                  Faol siyosat
                </label>
              </div>
              <div className="flex justify-end space-x-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button type="button" onClick={() => setIsPolicyModalOpen(false)} className="px-4 py-2 rounded-xl border text-xs font-bold">Bekor qilish</button>
                <button type="submit" className="px-4 py-2 rounded-xl bg-cyan-600 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* CALENDAR MODAL */}
      {isCalendarModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">{t('sla.newCalendar')}</h2>
            <form onSubmit={handleCreateCalendar} className="space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('sla.calendarName')} *
                </label>
                <input
                  type="text"
                  required
                  placeholder="Standart 5 kunlik ish grafigi"
                  value={calendarForm.name}
                  onChange={(e) => setCalendarForm({ ...calendarForm, name: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Boshlanish</label>
                  <input
                    type="time"
                    required
                    value={calendarForm.work_start_time}
                    onChange={(e) => setCalendarForm({ ...calendarForm, work_start_time: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none font-mono"
                  />
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Yakunlanish</label>
                  <input
                    type="time"
                    required
                    value={calendarForm.work_end_time}
                    onChange={(e) => setCalendarForm({ ...calendarForm, work_end_time: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none font-mono"
                  />
                </div>
              </div>
              <div className="flex justify-end space-x-2 pt-2">
                <button type="button" onClick={() => setIsCalendarModalOpen(false)} className="px-4 py-2 rounded-xl border text-xs font-bold">Bekor qilish</button>
                <button type="submit" className="px-4 py-2 rounded-xl bg-cyan-600 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
