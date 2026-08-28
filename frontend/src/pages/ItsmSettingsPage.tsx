import React, { useState, useEffect } from 'react';
import {
  Sliders,
  Search,
  Plus,
  RefreshCw,
  Edit2,
  Trash2,
  Users,
  MapPin,
  FileText,
  Tag as TagIcon,
  CheckCircle,
  ArrowLeft,
  X,
  Layers,
  FolderTree,
  FileCheck,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useToastStore } from '@/shared/presentation/store/useToastStore';

export const ItsmSettingsPage: React.FC = () => {
  const t = useT();
  const { user } = useCan();
  const [activeTab, setActiveTab] = useState<'services' | 'categories' | 'teams' | 'locations' | 'templates' | 'tags'>('services');

  const [loading, setLoading] = useState(false);
  const [services, setServices] = useState<any[]>([]);
  const [categories, setCategories] = useState<any[]>([]);
  const [resolutionCodes, setResolutionCodes] = useState<any[]>([]);
  const [teams, setTeams] = useState<any[]>([]);
  const [locations, setLocations] = useState<any[]>([]);
  const [templates, setTemplates] = useState<any[]>([]);
  const [tags, setTags] = useState<any[]>([]);

  // Modals
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalType, setModalType] = useState<'service' | 'category' | 'resolution' | 'team' | 'location' | 'template' | 'tag'>('service');
  const [formName, setFormName] = useState('');
  const [formCode, setFormCode] = useState('');
  const [formDescription, setFormDescription] = useState('');

  const fetchTabData = () => {
    setLoading(true);
    if (activeTab === 'services') {
      axiosClient.get('/services').then((res) => {
        if (res.data?.data) setServices(res.data.data);
      }).finally(() => setLoading(false));
    } else if (activeTab === 'categories') {
      axiosClient.get('/categories').then((res) => {
        if (res.data?.data) setCategories(res.data.data);
      });
      axiosClient.get('/resolution-codes').then((res) => {
        if (res.data?.data) setResolutionCodes(res.data.data);
      }).finally(() => setLoading(false));
    } else if (activeTab === 'teams') {
      axiosClient.get('/teams').then((res) => {
        if (res.data?.data) setTeams(res.data.data);
      }).finally(() => setLoading(false));
    } else if (activeTab === 'locations') {
      axiosClient.get('/locations').then((res) => {
        if (res.data?.data) setLocations(res.data.data);
      }).finally(() => setLoading(false));
    } else if (activeTab === 'templates') {
      axiosClient.get('/ticket-templates').then((res) => {
        if (res.data?.data) setTemplates(res.data.data);
        else if (Array.isArray(res.data)) setTemplates(res.data);
      }).finally(() => setLoading(false));
    } else if (activeTab === 'tags') {
      axiosClient.get('/tags').then((res) => {
        if (res.data?.data) setTags(res.data.data);
      }).finally(() => setLoading(false));
    }
  };

  useEffect(() => {
    fetchTabData();
  }, [activeTab]);

  const handleOpenAddModal = (type: 'service' | 'category' | 'resolution' | 'team' | 'location' | 'template' | 'tag') => {
    setModalType(type);
    setFormName('');
    setFormCode('');
    setFormDescription('');
    setIsModalOpen(true);
  };

  const handleSaveModal = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      if (modalType === 'service') {
        await axiosClient.post('/services', { name: formName, code: formCode || `SRV-${Date.now()}` });
      } else if (modalType === 'category') {
        await axiosClient.post('/categories', { name: formName, code: formCode || `CAT-${Date.now()}` });
      } else if (modalType === 'resolution') {
        await axiosClient.post('/resolution-codes', { name: formName, code: formCode || `RES-${Date.now()}` });
      } else if (modalType === 'team') {
        await axiosClient.post('/teams', { name: formName, code: formCode || `TEAM-${Date.now()}` });
      } else if (modalType === 'location') {
        await axiosClient.post('/locations', { name: formName, code: formCode || `LOC-${Date.now()}` });
      } else if (modalType === 'template') {
        await axiosClient.post('/ticket-templates', { name: formName, subject: formName, body: formDescription });
      } else if (modalType === 'tag') {
        await axiosClient.post('/tags', { name: formName, color: '#3B82F6' });
      }
      setIsModalOpen(false);
      fetchTabData();
      useToastStore.getState().success('Ma\'lumot muvaffaqiyatli saqlandi');
    } catch (err: any) {
      useToastStore.getState().error(err.response?.data?.message || 'Saqlashda xatolik yuz berdi');
    }
  };

  const handleDeleteItem = async (endpoint: string, id: number) => {
    if (!window.confirm('Haqiqatan ham ushbu ma\'lumotni o\'chirmoqchimisiz?')) return;
    try {
      await axiosClient.delete(`/${endpoint}/${id}`);
      fetchTabData();
      useToastStore.getState().success('Ma\'lumot o\'chirildi');
    } catch (err) {
      useToastStore.getState().error('O\'chirishda xatolik yuz berdi');
    }
  };

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <Link
            to="/dashboard"
            className="inline-flex items-center text-xs font-bold text-slate-500 hover:text-brand-500 transition-colors mb-2"
          >
            <ArrowLeft className="w-4 h-4 mr-1" /> {t('audit.backToDashboard')}
          </Link>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-3">
            <Sliders className="w-8 h-8 text-brand-500" />
            <span>{t('itsm.title')}</span>
          </h1>
          <p className="text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">
            {t('itsm.subtitle')}
          </p>
        </div>

        <button
          onClick={fetchTabData}
          className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all cursor-pointer"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {/* Tabs */}
      <div className="flex items-center space-x-2 border-b border-slate-200 dark:border-slate-800 pb-2 overflow-x-auto">
        {[
          { id: 'services', label: t('itsm.tabServices'), icon: Layers },
          { id: 'categories', label: t('itsm.tabCategories'), icon: FolderTree },
          { id: 'teams', label: t('itsm.tabTeams'), icon: Users },
          { id: 'locations', label: t('itsm.tabLocations'), icon: MapPin },
          { id: 'templates', label: t('itsm.tabTemplates'), icon: FileText },
          { id: 'tags', label: t('itsm.tabTags'), icon: TagIcon },
        ].map((tab) => {
          const Icon = tab.icon;
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              onClick={() => setActiveTab(tab.id as any)}
              className={`flex items-center space-x-2 px-4 py-2.5 rounded-xl text-xs font-extrabold whitespace-nowrap transition-all cursor-pointer ${
                isActive
                  ? 'bg-brand-500 text-white shadow-md shadow-brand-500/20'
                  : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
              }`}
            >
              <Icon className="w-4 h-4" />
              <span>{tab.label}</span>
            </button>
          );
        })}
      </div>

      {/* TAB CONTENT */}
      {/* 1. SERVICES */}
      {activeTab === 'services' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <button
              onClick={() => handleOpenAddModal('service')}
              className="px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs flex items-center space-x-2 shadow-md shadow-brand-500/20"
            >
              <Plus className="w-4 h-4" />
              <span>{t('itsm.addService')}</span>
            </button>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {services.map((s) => (
              <div key={s.id} className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                  <span className="text-[10px] font-black uppercase text-brand-500 font-mono">{s.code}</span>
                  <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">{s.name}</h3>
                </div>
                <button onClick={() => handleDeleteItem('services', s.id)} className="p-2 rounded-xl text-slate-400 hover:text-error-500">
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* 2. CATEGORIES */}
      {activeTab === 'categories' && (
        <div className="space-y-6">
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-black uppercase tracking-wider text-slate-500">Zayavka Kategoriyalari</h2>
              <button
                onClick={() => handleOpenAddModal('category')}
                className="px-3.5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs flex items-center space-x-1.5"
              >
                <Plus className="w-4 h-4" />
                <span>{t('itsm.addCategory')}</span>
              </button>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {categories.map((c) => (
                <div key={c.id} className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                  <div>
                    <span className="text-[10px] font-black uppercase text-purple-500 font-mono">{c.code}</span>
                    <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">{c.name}</h3>
                  </div>
                  <button onClick={() => handleDeleteItem('categories', c.id)} className="p-2 rounded-xl text-slate-400 hover:text-error-500">
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              ))}
            </div>
          </div>

          <div className="space-y-4 pt-4 border-t border-slate-200 dark:border-slate-800">
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-black uppercase tracking-wider text-slate-500">Standart Yechim Kodlari (Resolution Codes)</h2>
              <button
                onClick={() => handleOpenAddModal('resolution')}
                className="px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs flex items-center space-x-1.5"
              >
                <Plus className="w-4 h-4" />
                <span>{t('itsm.addResolutionCode')}</span>
              </button>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {resolutionCodes.map((r) => (
                <div key={r.id} className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                  <div>
                    <span className="text-[10px] font-black uppercase text-emerald-500 font-mono">{r.code}</span>
                    <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">{r.name}</h3>
                  </div>
                  <button onClick={() => handleDeleteItem('resolution-codes', r.id)} className="p-2 rounded-xl text-slate-400 hover:text-error-500">
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* 3. TEAMS */}
      {activeTab === 'teams' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <button
              onClick={() => handleOpenAddModal('team')}
              className="px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs flex items-center space-x-2"
            >
              <Plus className="w-4 h-4" />
              <span>{t('itsm.addTeam')}</span>
            </button>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {teams.map((team) => (
              <div key={team.id} className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-[10px] font-black uppercase text-brand-500 font-mono">{team.code}</span>
                  <Users className="w-4 h-4 text-slate-400" />
                </div>
                <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">{team.name}</h3>
                <p className="text-xs text-slate-500">{team.description || 'Texnik qo\'llab-quvvatlash guruhi'}</p>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* 4. LOCATIONS */}
      {activeTab === 'locations' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <button
              onClick={() => handleOpenAddModal('location')}
              className="px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs flex items-center space-x-2"
            >
              <Plus className="w-4 h-4" />
              <span>{t('itsm.addLocation')}</span>
            </button>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {locations.map((loc) => (
              <div key={loc.id} className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
                <div>
                  <span className="text-[10px] font-black uppercase text-purple-500 font-mono">{loc.code}</span>
                  <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">{loc.name}</h3>
                </div>
                <button onClick={() => handleDeleteItem('locations', loc.id)} className="p-2 rounded-xl text-slate-400 hover:text-error-500">
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* 5. TEMPLATES */}
      {activeTab === 'templates' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <button
              onClick={() => handleOpenAddModal('template')}
              className="px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs flex items-center space-x-2"
            >
              <Plus className="w-4 h-4" />
              <span>{t('itsm.addTemplate')}</span>
            </button>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {templates.map((tpl) => (
              <div key={tpl.id} className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-[10px] font-black uppercase text-amber-500 font-mono">SHABLON #{tpl.id}</span>
                  <FileText className="w-4 h-4 text-slate-400" />
                </div>
                <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">{tpl.name || tpl.subject}</h3>
                {tpl.body && <p className="text-xs text-slate-500 line-clamp-2">{tpl.body}</p>}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* 6. TAGS */}
      {activeTab === 'tags' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <button
              onClick={() => handleOpenAddModal('tag')}
              className="px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs flex items-center space-x-2"
            >
              <Plus className="w-4 h-4" />
              <span>{t('itsm.addTag')}</span>
            </button>
          </div>
          <div className="flex flex-wrap gap-3">
            {tags.map((tag) => (
              <div key={tag.id} className="px-4 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center space-x-2">
                <span className="w-2.5 h-2.5 rounded-full bg-brand-500" />
                <span className="text-xs font-extrabold text-slate-800 dark:text-slate-200">{tag.name}</span>
                <button onClick={() => handleDeleteItem('tags', tag.id)} className="text-slate-400 hover:text-error-500 ml-1">
                  <X className="w-3.5 h-3.5" />
                </button>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* REUSABLE ADD MODAL */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">
              Yangi element qo'shish ({modalType})
            </h2>
            <form onSubmit={handleSaveModal} className="space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Nomi *</label>
                <input
                  type="text"
                  required
                  value={formName}
                  onChange={(e) => setFormName(e.target.value)}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                />
              </div>
              {modalType !== 'tag' && (
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Kodi (Code)</label>
                  <input
                    type="text"
                    value={formCode}
                    onChange={(e) => setFormCode(e.target.value)}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono font-semibold outline-none"
                  />
                </div>
              )}
              {modalType === 'template' && (
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Matn shabloni</label>
                  <textarea
                    rows={3}
                    value={formDescription}
                    onChange={(e) => setFormDescription(e.target.value)}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                  />
                </div>
              )}
              <div className="flex justify-end space-x-2 pt-2">
                <button type="button" onClick={() => setIsModalOpen(false)} className="px-4 py-2 rounded-xl border text-xs font-bold">Bekor qilish</button>
                <button type="submit" className="px-4 py-2 rounded-xl bg-brand-500 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
