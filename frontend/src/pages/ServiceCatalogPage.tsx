import React, { useState, useEffect } from 'react';
import {
  ShoppingBag,
  Search,
  Plus,
  Clock,
  CheckCircle,
  ArrowRight,
  ArrowLeft,
  X,
  Edit2,
  Trash2,
  Layers,
  Sparkles,
  RefreshCw,
  Send,
} from 'lucide-react';
import { Link, useNavigate } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useToastStore } from '@/shared/presentation/store/useToastStore';

interface CatalogItem {
  id: number;
  public_id?: string;
  code: string;
  name: string;
  description?: string;
  service_offering_id?: number;
  estimated_minutes?: number;
  is_active: boolean;
  serviceOffering?: { id: number; name: string };
}

export const ServiceCatalogPage: React.FC = () => {
  const t = useT();
  const navigate = useNavigate();
  const { user } = useCan();
  const isStaff = Boolean(user?.isStaff) || user?.role === 'Super Admin' || user?.username === 'superadmin';

  const [items, setItems] = useState<CatalogItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [offerings, setOfferings] = useState<any[]>([]);

  // Modals
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<CatalogItem | null>(null);
  const [requestItem, setRequestItem] = useState<CatalogItem | null>(null);
  const [requestComment, setRequestComment] = useState('');
  const [isSubmittingRequest, setIsSubmittingRequest] = useState(false);

  // Form State
  const [itemForm, setItemForm] = useState({
    code: '',
    name: '',
    description: '',
    service_offering_id: '',
    estimated_minutes: 60,
    is_active: true,
  });

  const fetchOfferings = () => {
    axiosClient.get('/service-offerings').then((res) => {
      if (res.data?.data) setOfferings(res.data.data);
    }).catch(() => {});
  };

  const fetchItems = () => {
    setLoading(true);
    axiosClient
      .get('/catalog/items', {
        params: {
          search: search || undefined,
          per_page: 50,
        },
      })
      .then((res) => {
        if (res.data?.data) {
          setItems(res.data.data);
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchOfferings();
  }, []);

  useEffect(() => {
    fetchItems();
  }, [search]);

  const handleOpenCreate = () => {
    setEditingItem(null);
    setItemForm({
      code: `SRV-${Math.floor(100 + Math.random() * 900)}`,
      name: '',
      description: '',
      service_offering_id: offerings[0]?.id ? String(offerings[0].id) : '',
      estimated_minutes: 60,
      is_active: true,
    });
    setIsModalOpen(true);
  };

  const handleOpenEdit = (item: CatalogItem) => {
    setEditingItem(item);
    setItemForm({
      code: item.code,
      name: item.name,
      description: item.description || '',
      service_offering_id: item.service_offering_id ? String(item.service_offering_id) : '',
      estimated_minutes: item.estimated_minutes || 60,
      is_active: Boolean(item.is_active),
    });
    setIsModalOpen(true);
  };

  const handleSaveItem = async (e: React.FormEvent) => {
    e.preventDefault();
    const payload = {
      code: itemForm.code,
      name: itemForm.name,
      description: itemForm.description || null,
      service_offering_id: itemForm.service_offering_id ? Number(itemForm.service_offering_id) : null,
      estimated_minutes: Number(itemForm.estimated_minutes),
      is_active: Boolean(itemForm.is_active),
    };

    try {
      if (editingItem) {
        await axiosClient.put(`/catalog/items/${editingItem.id}`, payload);
      } else {
        await axiosClient.post('/catalog/items', payload);
      }
      setIsModalOpen(false);
      fetchItems();
      useToastStore.getState().success(editingItem ? 'Xizmat katalogi yangilandi' : 'Yangi IT xizmati katalogga qo\'shildi');
    } catch (err: any) {
      useToastStore.getState().error(err.response?.data?.message || 'Xizmatni saqlashda xatolik yuz berdi');
    }
  };

  const handleDeleteItem = async (id: number) => {
    if (!window.confirm('Ushbu xizmatni o\'chirishni tasdiqlaysizmi?')) return;
    try {
      await axiosClient.delete(`/catalog/items/${id}`);
      fetchItems();
      useToastStore.getState().success('Xizmat o\'chirildi');
    } catch (err) {
      useToastStore.getState().error('O\'chirishda xatolik yuz berdi');
    }
  };

  const handleSendServiceRequest = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!requestItem) return;

    setIsSubmittingRequest(true);
    try {
      // Creates ticket linked to catalog service request
      await axiosClient.post('/tickets', {
        todo: `${requestItem.name}: ${requestComment || 'Xizmat so\'rovi'}`,
        category: requestItem.name,
        targetDepartment: 'software',
      });
      setIsSubmittingRequest(false);
      setRequestItem(null);
      setRequestComment('');
      useToastStore.getState().success('Xizmat so\'rovingiz qabul qilindi va yangi zayavka sifatida ro\'yxatga olindi!');
      navigate('/requests');
    } catch (err: any) {
      setIsSubmittingRequest(false);
      useToastStore.getState().error(err.response?.data?.message || 'So\'rov yuborishda xatolik yuz berdi');
    }
  };

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-8">
      {/* Header Banner */}
      <div className="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 p-8 sm:p-12 text-white shadow-xl">
        <div className="relative z-10 max-w-2xl space-y-4">
          <Link
            to={isStaff ? '/dashboard' : '/requests'}
            className="inline-flex items-center text-xs font-bold text-emerald-200 hover:text-white transition-colors"
          >
            <ArrowLeft className="w-4 h-4 mr-1" /> {t('audit.backToDashboard')}
          </Link>
          <div className="flex items-center space-x-3">
            <div className="p-3 rounded-2xl bg-white/20 backdrop-blur-md">
              <ShoppingBag className="w-8 h-8 text-white" />
            </div>
            <div>
              <h1 className="text-2xl sm:text-4xl font-black">{t('catalog.title')}</h1>
              <p className="text-xs sm:text-sm text-emerald-100 font-medium">{t('catalog.subtitle')}</p>
            </div>
          </div>

          <div className="relative pt-2">
            <Search className="w-5 h-5 absolute left-4 top-5 text-slate-400" />
            <input
              type="text"
              placeholder="Xizmat nomi yoki toifasi bo'yicha qidiring (masalan: Yangi noutbuk, VPN ruxsati)..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-12 pr-4 py-3.5 rounded-2xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm font-semibold shadow-lg focus:outline-none focus:ring-4 focus:ring-emerald-400/30"
            />
          </div>
        </div>

        <div className="absolute right-0 top-0 -mt-12 -mr-12 w-96 h-96 rounded-full bg-white/10 blur-3xl pointer-events-none" />
      </div>

      {/* Top action bar */}
      <div className="flex items-center justify-between">
        <div className="text-xs font-black uppercase tracking-wider text-slate-400">
          Mavjud IT Xizmatlari ({items.length})
        </div>

        <div className="flex items-center space-x-3">
          <button
            onClick={fetchItems}
            className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all cursor-pointer"
            title="Yangilash"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
          {isStaff && (
            <button
              onClick={handleOpenCreate}
              className="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center space-x-2 shadow-md shadow-emerald-600/20 transition-all cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>{t('catalog.newItem')}</span>
            </button>
          )}
        </div>
      </div>

      {/* Grid of Catalog Items */}
      {loading ? (
        <div className="py-20 text-center text-slate-400">
          <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-3" />
          <p className="text-xs font-bold">Xizmatlar katalogi yuklanmoqda...</p>
        </div>
      ) : items.length === 0 ? (
        <div className="py-16 text-center bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-8">
          <ShoppingBag className="w-12 h-12 text-slate-300 mx-auto mb-3" />
          <h3 className="text-base font-bold text-slate-700 dark:text-slate-300">{t('catalog.noItems')}</h3>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {items.map((item) => (
            <div
              key={item.id}
              className="group p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-emerald-300 dark:hover:border-emerald-700 shadow-sm hover:shadow-xl transition-all flex flex-col justify-between"
            >
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span className="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase bg-emerald-50 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                    {item.serviceOffering?.name || item.code}
                  </span>
                  <div className="flex items-center space-x-1 text-xs text-slate-500 font-semibold">
                    <Clock className="w-3.5 h-3.5 text-amber-500" />
                    <span>~{item.estimated_minutes || 60} daq</span>
                  </div>
                </div>

                <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors">
                  {item.name}
                </h3>

                <p className="text-xs text-slate-500 dark:text-slate-400 line-clamp-3 leading-relaxed">
                  {item.description || 'Ushbu IT xizmati uchun so\'rov yuborishingiz mumkin.'}
                </p>
              </div>

              <div className="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <button
                  onClick={() => setRequestItem(item)}
                  className="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center space-x-1.5 shadow-md shadow-emerald-600/20 transition-all cursor-pointer"
                >
                  <span>{t('catalog.requestService')}</span>
                  <ArrowRight className="w-3.5 h-3.5" />
                </button>

                {isStaff && (
                  <div className="flex items-center space-x-1">
                    <button
                      onClick={() => handleOpenEdit(item)}
                      className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-emerald-600"
                      title="Tahrirlash"
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                    </button>
                    <button
                      onClick={() => handleDeleteItem(item.id)}
                      className="p-1.5 rounded-lg hover:bg-error-50 dark:hover:bg-error-950/40 text-slate-400 hover:text-error-500"
                      title="O'chirish"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* REQUEST SERVICE MODAL (USER) */}
      {requestItem && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div>
                <span className="text-[10px] font-black uppercase text-emerald-600">Xizmat buyurtmasi</span>
                <h2 className="text-lg font-black text-slate-900 dark:text-slate-100">{requestItem.name}</h2>
              </div>
              <button onClick={() => setRequestItem(null)} className="p-2 rounded-xl text-slate-400">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSendServiceRequest} className="space-y-4">
              <div className="p-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-300">
                <Clock className="w-4 h-4 inline mr-1 text-emerald-600" />
                Taxminiy bajarilish muddati: <strong>{requestItem.estimated_minutes || 60} daqiqa</strong>
              </div>

              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  Qo'shimcha izoh va talablaringiz
                </label>
                <textarea
                  rows={4}
                  required
                  placeholder="Masalan: 3-qavat 305-xona uchun, shoshilinch..."
                  value={requestComment}
                  onChange={(e) => setRequestComment(e.target.value)}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium focus:ring-2 focus:ring-emerald-500 outline-none"
                />
              </div>

              <div className="flex justify-end space-x-2 pt-2">
                <button
                  type="button"
                  onClick={() => setRequestItem(null)}
                  className="px-4 py-2 rounded-xl border text-xs font-bold"
                >
                  Bekor qilish
                </button>
                <button
                  type="submit"
                  disabled={isSubmittingRequest}
                  className="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center space-x-1.5"
                >
                  <Send className="w-3.5 h-3.5" />
                  <span>{isSubmittingRequest ? 'Yuborilmoqda...' : 'Yuborish'}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* CREATE / EDIT CATALOG ITEM (ADMIN) */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-lg bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <h2 className="text-lg font-black text-slate-900 dark:text-slate-100">
                {editingItem ? t('catalog.editItem') : t('catalog.newItem')}
              </h2>
              <button onClick={() => setIsModalOpen(false)} className="p-2 rounded-xl text-slate-400">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveItem} className="py-4 space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('catalog.itemName')} *
                </label>
                <input
                  type="text"
                  required
                  placeholder="Masalan: VPN hisobi ochish"
                  value={itemForm.name}
                  onChange={(e) => setItemForm({ ...itemForm, name: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold focus:ring-2 focus:ring-emerald-500 outline-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('catalog.itemCode')} *
                  </label>
                  <input
                    type="text"
                    required
                    value={itemForm.code}
                    onChange={(e) => setItemForm({ ...itemForm, code: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-mono font-semibold outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('catalog.estimatedMinutes')}
                  </label>
                  <input
                    type="number"
                    min="5"
                    value={itemForm.estimated_minutes}
                    onChange={(e) => setItemForm({ ...itemForm, estimated_minutes: Number(e.target.value) })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  Tavsif
                </label>
                <textarea
                  rows={3}
                  value={itemForm.description}
                  onChange={(e) => setItemForm({ ...itemForm, description: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium outline-none"
                />
              </div>

              <div className="flex items-center space-x-2">
                <input
                  type="checkbox"
                  id="activeCheck"
                  checked={itemForm.is_active}
                  onChange={(e) => setItemForm({ ...itemForm, is_active: e.target.checked })}
                  className="w-4 h-4 rounded text-emerald-600"
                />
                <label htmlFor="activeCheck" className="text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                  {t('catalog.active')} (Katalogda ko'rinsin)
                </label>
              </div>

              <div className="flex justify-end space-x-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button type="button" onClick={() => setIsModalOpen(false)} className="px-4 py-2 rounded-xl border text-xs font-bold">Bekor qilish</button>
                <button type="submit" className="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
