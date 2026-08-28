import React, { useState, useEffect } from 'react';
import {
  Zap,
  Search,
  Plus,
  Filter,
  RefreshCw,
  Edit2,
  Trash2,
  ToggleLeft,
  ToggleRight,
  GitBranch,
  ArrowLeft,
  X,
  Play,
  CheckCircle2,
  Sliders,
  Layers,
  Sparkles,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useToastStore } from '@/shared/presentation/store/useToastStore';

interface AutomationRuleItem {
  id: number;
  public_id?: string;
  name: string;
  event_type: string;
  conditions?: any;
  actions: any;
  priority?: number;
  is_active: boolean;
  stop_processing?: boolean;
}

interface WorkflowItem {
  id: number;
  public_id?: string;
  code: string;
  name: string;
  entity_type: string;
  status: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED';
  version?: number;
  definition?: any;
}

export const AutomationPage: React.FC = () => {
  const t = useT();
  const { user } = useCan();
  const [activeTab, setActiveTab] = useState<'rules' | 'workflows'>('rules');

  // Rules
  const [rules, setRules] = useState<AutomationRuleItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');

  // Workflows
  const [workflows, setWorkflows] = useState<WorkflowItem[]>([]);

  // Rule Modal
  const [isRuleModalOpen, setIsRuleModalOpen] = useState(false);
  const [editingRule, setEditingRule] = useState<AutomationRuleItem | null>(null);
  const [ruleForm, setRuleForm] = useState({
    name: '',
    event_type: 'TICKET_CREATED',
    priority: 10,
    is_active: true,
    condition_field: 'category',
    condition_value: 'Hardware',
    action_type: 'ASSIGN_TEAM',
    action_target: 'IT Support',
  });

  // Workflow Modal
  const [isWorkflowModalOpen, setIsWorkflowModalOpen] = useState(false);
  const [workflowForm, setWorkflowForm] = useState({
    code: '',
    name: '',
    entity_type: 'TICKET',
    status: 'PUBLISHED',
  });

  const fetchRules = () => {
    setLoading(true);
    axiosClient
      .get('/automation-rules', {
        params: { search: search || undefined, per_page: 50 },
      })
      .then((res) => {
        if (res.data?.data) setRules(res.data.data);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  const fetchWorkflows = () => {
    axiosClient.get('/workflows').then((res) => {
      if (res.data?.data) setWorkflows(res.data.data);
    }).catch(() => {});
  };

  useEffect(() => {
    if (activeTab === 'rules') fetchRules();
    else fetchWorkflows();
  }, [activeTab, search]);

  const handleToggleRule = async (rule: AutomationRuleItem) => {
    try {
      await axiosClient.post(`/automation-rules/${rule.id}/toggle`);
      setRules((prev) =>
        prev.map((r) => (r.id === rule.id ? { ...r, is_active: !r.is_active } : r))
      );
    } catch (err) {
      alert('Qoidani o\'zgartirishda xatolik');
    }
  };

  const handleOpenCreateRule = () => {
    setEditingRule(null);
    setRuleForm({
      name: '',
      event_type: 'TICKET_CREATED',
      priority: 10,
      is_active: true,
      condition_field: 'category',
      condition_value: 'Hardware',
      action_type: 'ASSIGN_TEAM',
      action_target: 'IT Support',
    });
    setIsRuleModalOpen(true);
  };

  const handleOpenEditRule = (rule: AutomationRuleItem) => {
    setEditingRule(rule);
    setRuleForm({
      name: rule.name,
      event_type: rule.event_type,
      priority: rule.priority || 10,
      is_active: Boolean(rule.is_active),
      condition_field: rule.conditions?.field || 'category',
      condition_value: rule.conditions?.value || '',
      action_type: rule.actions?.type || 'ASSIGN_TEAM',
      action_target: rule.actions?.target || '',
    });
    setIsRuleModalOpen(true);
  };

  const handleSaveRule = async (e: React.FormEvent) => {
    e.preventDefault();
    const payload = {
      name: ruleForm.name,
      event_type: ruleForm.event_type,
      priority: Number(ruleForm.priority),
      is_active: Boolean(ruleForm.is_active),
      conditions: {
        field: ruleForm.condition_field,
        operator: 'EQUALS',
        value: ruleForm.condition_value,
      },
      actions: [
        {
          type: ruleForm.action_type,
          target: ruleForm.action_target,
        },
      ],
    };

    try {
      if (editingRule) {
        await axiosClient.put(`/automation-rules/${editingRule.id}`, payload);
      } else {
        await axiosClient.post('/automation-rules', payload);
      }
      setIsRuleModalOpen(false);
      fetchRules();
      useToastStore.getState().success(editingRule ? 'Avtomatizatsiya qoidasi tahrirlandi' : 'Yangi avtomatizatsiya qoidasi yaratildi');
    } catch (err: any) {
      useToastStore.getState().error(err.response?.data?.message || 'Qoidani saqlashda xatolik yuz berdi');
    }
  };

  const handleDeleteRule = async (id: number) => {
    if (!window.confirm('Qoidani o\'chirishni tasdiqlaysizmi?')) return;
    try {
      await axiosClient.delete(`/automation-rules/${id}`);
      fetchRules();
      useToastStore.getState().success('Avtomatizatsiya qoidasi o\'chirildi');
    } catch (err) {
      useToastStore.getState().error('O\'chirishda xatolik yuz berdi');
    }
  };

  const handleCreateWorkflow = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await axiosClient.post('/workflows', {
        code: workflowForm.code,
        name: workflowForm.name,
        entity_type: workflowForm.entity_type,
        definition: {
          steps: [
            { id: 1, name: 'Yaratildi', type: 'INITIAL' },
            { id: 2, name: 'Rahbar tasdiqlashi', type: 'APPROVAL' },
            { id: 3, name: 'IT Ijrosi', type: 'EXECUTION' },
            { id: 4, name: 'Yopildi', type: 'TERMINAL' },
          ],
        },
        status: workflowForm.status,
      });
      setIsWorkflowModalOpen(false);
      fetchWorkflows();
      useToastStore.getState().success('Yangi ish oqimi (Workflow) yaratildi');
    } catch (err) {
      useToastStore.getState().error('Ish oqimini saqlashda xatolik yuz berdi');
    }
  };

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <Link
            to="/dashboard"
            className="inline-flex items-center text-xs font-bold text-slate-500 hover:text-amber-500 transition-colors mb-2"
          >
            <ArrowLeft className="w-4 h-4 mr-1" /> {t('audit.backToDashboard')}
          </Link>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-3">
            <Zap className="w-8 h-8 text-amber-500" />
            <span>{t('auto.title')}</span>
          </h1>
          <p className="text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">
            {t('auto.subtitle')}
          </p>
        </div>

        <div className="flex items-center space-x-3">
          <button
            onClick={() => {
              if (activeTab === 'rules') fetchRules();
              else fetchWorkflows();
            }}
            className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all cursor-pointer"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
          {activeTab === 'rules' ? (
            <button
              onClick={handleOpenCreateRule}
              className="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs flex items-center space-x-2 shadow-md shadow-amber-500/20 transition-all cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>{t('auto.newRule')}</span>
            </button>
          ) : (
            <button
              onClick={() => setIsWorkflowModalOpen(true)}
              className="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs flex items-center space-x-2 shadow-md shadow-amber-500/20 transition-all cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>{t('auto.newWorkflow')}</span>
            </button>
          )}
        </div>
      </div>

      {/* Tabs */}
      <div className="flex items-center space-x-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        <button
          onClick={() => setActiveTab('rules')}
          className={`flex items-center space-x-2 px-4 py-2.5 rounded-xl text-xs font-extrabold transition-all cursor-pointer ${
            activeTab === 'rules'
              ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20'
              : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
          }`}
        >
          <Zap className="w-4 h-4" />
          <span>{t('auto.tabRules')}</span>
        </button>
        <button
          onClick={() => setActiveTab('workflows')}
          className={`flex items-center space-x-2 px-4 py-2.5 rounded-xl text-xs font-extrabold transition-all cursor-pointer ${
            activeTab === 'workflows'
              ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20'
              : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
          }`}
        >
          <GitBranch className="w-4 h-4" />
          <span>{t('auto.tabWorkflows')}</span>
        </button>
      </div>

      {/* TAB 1: AUTOMATION RULES */}
      {activeTab === 'rules' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {rules.map((rule) => (
              <div
                key={rule.id}
                className="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 flex flex-col justify-between"
              >
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <span className="px-2.5 py-0.5 rounded-md text-[10px] font-black uppercase bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                      Hodisa: {rule.event_type}
                    </span>
                    <button
                      onClick={() => handleToggleRule(rule)}
                      className={`text-2xl transition-transform ${
                        rule.is_active ? 'text-emerald-500' : 'text-slate-400'
                      }`}
                      title={rule.is_active ? 'O\'chirish' : 'Yoqish'}
                    >
                      {rule.is_active ? (
                        <ToggleRight className="w-8 h-8" />
                      ) : (
                        <ToggleLeft className="w-8 h-8" />
                      )}
                    </button>
                  </div>

                  <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">
                    {rule.name}
                  </h3>

                  <div className="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 font-mono text-xs space-y-1.5 text-slate-600 dark:text-slate-300">
                    <div className="text-[10px] font-black uppercase text-slate-400">Shart:</div>
                    <div className="font-bold text-brand-600 dark:text-brand-400">
                      {rule.conditions?.field || 'Kategoriya'} = {rule.conditions?.value || 'Hardware'}
                    </div>
                    <div className="text-[10px] font-black uppercase text-slate-400 pt-1">Harakat:</div>
                    <div className="font-bold text-purple-600 dark:text-purple-400">
                      {Array.isArray(rule.actions) ? rule.actions[0]?.type : rule.actions?.type || 'ASSIGN_TEAM'}
                    </div>
                  </div>
                </div>

                <div className="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-400">
                  <span>Prioritet: #{rule.priority || 10}</span>
                  <div className="flex items-center space-x-1">
                    <button
                      onClick={() => handleOpenEditRule(rule)}
                      className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-amber-500"
                    >
                      <Edit2 className="w-4 h-4" />
                    </button>
                    <button
                      onClick={() => handleDeleteRule(rule.id)}
                      className="p-1.5 rounded-lg hover:bg-error-50 dark:hover:bg-error-950/40 text-slate-400 hover:text-error-500"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 2: WORKFLOWS */}
      {activeTab === 'workflows' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {workflows.map((wf) => (
            <div
              key={wf.id}
              className="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4"
            >
              <div className="flex items-center justify-between">
                <span className="font-mono text-xs font-black text-amber-500">{wf.code}</span>
                <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300">
                  {wf.status}
                </span>
              </div>
              <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">{wf.name}</h3>
              <p className="text-xs text-slate-500">Obyekt: <strong className="text-slate-700 dark:text-slate-300">{wf.entity_type}</strong></p>

              {/* Step Nodes preview */}
              <div className="flex items-center space-x-2 pt-2 overflow-x-auto pb-1">
                {(wf.definition?.steps || [
                  { name: 'Yaratildi' },
                  { name: 'Ko\'rib chiqish' },
                  { name: 'Bajarildi' },
                ]).map((step: any, idx: number) => (
                  <React.Fragment key={idx}>
                    <span className="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-[11px] font-bold text-slate-700 dark:text-slate-300 whitespace-nowrap">
                      {step.name || `Bosqich ${idx + 1}`}
                    </span>
                    {idx < (wf.definition?.steps?.length || 3) - 1 && (
                      <span className="text-slate-400 text-xs">→</span>
                    )}
                  </React.Fragment>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* RULE MODAL */}
      {isRuleModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">
              {editingRule ? t('auto.editRule') : t('auto.newRule')}
            </h2>
            <form onSubmit={handleSaveRule} className="space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  Qoida nomi *
                </label>
                <input
                  type="text"
                  required
                  placeholder="Masalan: Hardware nosozliklarini avtomatik biriktirish"
                  value={ruleForm.name}
                  onChange={(e) => setRuleForm({ ...ruleForm, name: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none focus:ring-2 focus:ring-amber-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Trigger hodisasi</label>
                  <select
                    value={ruleForm.event_type}
                    onChange={(e) => setRuleForm({ ...ruleForm, event_type: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                  >
                    <option value="TICKET_CREATED">Ticket Yaratilganda</option>
                    <option value="STATUS_CHANGED">Status O'zgarganda</option>
                    <option value="SLA_BREACHED">SLA Buzilganda</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Prioritet tartibi</label>
                  <input
                    type="number"
                    value={ruleForm.priority}
                    onChange={(e) => setRuleForm({ ...ruleForm, priority: Number(e.target.value) })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                  />
                </div>
              </div>

              <div className="p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-3">
                <div className="text-[11px] font-black uppercase text-slate-500">Shart va Harakat</div>
                <div className="grid grid-cols-2 gap-2">
                  <input
                    type="text"
                    placeholder="Maydon (masalan: category)"
                    value={ruleForm.condition_field}
                    onChange={(e) => setRuleForm({ ...ruleForm, condition_field: e.target.value })}
                    className="w-full px-3 py-1.5 rounded-lg border text-xs font-semibold"
                  />
                  <input
                    type="text"
                    placeholder="Qiymat (masalan: Hardware)"
                    value={ruleForm.condition_value}
                    onChange={(e) => setRuleForm({ ...ruleForm, condition_value: e.target.value })}
                    className="w-full px-3 py-1.5 rounded-lg border text-xs font-semibold"
                  />
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <select
                    value={ruleForm.action_type}
                    onChange={(e) => setRuleForm({ ...ruleForm, action_type: e.target.value })}
                    className="w-full px-3 py-1.5 rounded-lg border text-xs font-semibold"
                  >
                    <option value="ASSIGN_TEAM">Jamoaga biriktirish</option>
                    <option value="SET_PRIORITY">Muhimlikni oshirish</option>
                    <option value="SEND_NOTIFICATION">Xabarnoma yuborish</option>
                  </select>
                  <input
                    type="text"
                    placeholder="Harakat nishoni"
                    value={ruleForm.action_target}
                    onChange={(e) => setRuleForm({ ...ruleForm, action_target: e.target.value })}
                    className="w-full px-3 py-1.5 rounded-lg border text-xs font-semibold"
                  />
                </div>
              </div>

              <div className="flex justify-end space-x-2 pt-2">
                <button type="button" onClick={() => setIsRuleModalOpen(false)} className="px-4 py-2 rounded-xl border text-xs font-bold">Bekor qilish</button>
                <button type="submit" className="px-4 py-2 rounded-xl bg-amber-500 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* WORKFLOW MODAL */}
      {isWorkflowModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">{t('auto.newWorkflow')}</h2>
            <form onSubmit={handleCreateWorkflow} className="space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Oqim kodi *</label>
                <input
                  type="text"
                  required
                  placeholder="WF-INCIDENT"
                  value={workflowForm.code}
                  onChange={(e) => setWorkflowForm({ ...workflowForm, code: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono font-semibold outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Oqim nomi *</label>
                <input
                  type="text"
                  required
                  placeholder="Standart Incident oqimi"
                  value={workflowForm.name}
                  onChange={(e) => setWorkflowForm({ ...workflowForm, name: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                />
              </div>
              <div className="flex justify-end space-x-2 pt-2">
                <button type="button" onClick={() => setIsWorkflowModalOpen(false)} className="px-4 py-2 rounded-xl border text-xs font-bold">Bekor qilish</button>
                <button type="submit" className="px-4 py-2 rounded-xl bg-amber-500 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
