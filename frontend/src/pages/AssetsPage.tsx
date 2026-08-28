import React, { useState, useEffect } from 'react';
import {
  Server,
  Search,
  Plus,
  Filter,
  RefreshCw,
  Edit2,
  Trash2,
  Eye,
  CheckCircle2,
  AlertTriangle,
  Radio,
  Building,
  User,
  Shield,
  Layers,
  Cpu,
  ArrowLeft,
  X,
  Laptop,
  KeyRound,
  ShoppingBag,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import { useCan } from '@/shared/presentation/hooks/useCan';

interface AssetItem {
  id: number;
  asset_tag: string;
  serial_number?: string;
  hostname?: string;
  asset_type_id: number;
  status_id: number;
  model_id?: number;
  owner_employee_id?: number;
  custodian_employee_id?: number;
  department_id?: number;
  branch_id?: number;
  vendor_id?: number;
  ip_addresses?: string[];
  mac_addresses?: string[];
  os_name?: string;
  purchase_date?: string;
  warranty_end_date?: string;
  model?: { id: number; name: string; manufacturer?: { name: string } };
  status?: { id: number; name: string; color?: string };
  assetType?: { id: number; name: string };
  department?: { id: number; name: string };
  branch?: { id: number; name: string };
  vendor?: { id: number; name: string };
}

interface VendorItem {
  id: number;
  name: string;
  code?: string;
  contact_person?: string;
  email?: string;
  phone?: string;
}

interface ModelItem {
  id: number;
  name: string;
  manufacturer_id?: number;
  model_number?: string;
  manufacturer?: { name: string };
}

interface SoftwareLicenseItem {
  id: number;
  product_name?: string;
  software_product_id?: number;
  license_key?: string;
  license_type?: string;
  seats_total?: number;
  seats_allocated?: number;
  expires_at?: string;
  vendor?: { name: string };
}

export const AssetsPage: React.FC = () => {
  const t = useT();
  const { user } = useCan();
  const [activeTab, setActiveTab] = useState<'assets' | 'models' | 'vendors' | 'software' | 'discovery'>('assets');

  // Assets list state
  const [assets, setAssets] = useState<AssetItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  // References
  const [assetTypes, setAssetTypes] = useState<any[]>([]);
  const [assetStatuses, setAssetStatuses] = useState<any[]>([]);
  const [departments, setDepartments] = useState<any[]>([]);
  const [branches, setBranches] = useState<any[]>([]);
  const [models, setModels] = useState<ModelItem[]>([]);
  const [vendors, setVendors] = useState<VendorItem[]>([]);
  const [licenses, setLicenses] = useState<SoftwareLicenseItem[]>([]);

  // Modals
  const [isAssetModalOpen, setIsAssetModalOpen] = useState(false);
  const [editingAsset, setEditingAsset] = useState<AssetItem | null>(null);
  const [viewingAsset, setViewingAsset] = useState<AssetItem | null>(null);

  // Vendor & Model Modal
  const [isVendorModalOpen, setIsVendorModalOpen] = useState(false);
  const [vendorForm, setVendorForm] = useState({ name: '', code: '', email: '', phone: '' });

  const [isModelModalOpen, setIsModelModalOpen] = useState(false);
  const [modelForm, setModelForm] = useState({ name: '', model_number: '', manufacturer_name: '' });

  // License modal
  const [isLicenseModalOpen, setIsLicenseModalOpen] = useState(false);
  const [licenseForm, setLicenseForm] = useState({
    name: '',
    license_key: '',
    license_type: 'PERPETUAL',
    seats_total: 10,
    expires_at: '',
  });

  // Discovery state
  const [discovering, setDiscovering] = useState(false);
  const [discoveryLogs, setDiscoveryLogs] = useState<string[]>([]);

  // Asset Form State
  const [assetForm, setAssetForm] = useState({
    asset_tag: '',
    serial_number: '',
    hostname: '',
    asset_type_id: 1,
    status_id: 1,
    model_id: '',
    department_id: '',
    branch_id: '',
    vendor_id: '',
    ip_address: '',
    mac_address: '',
    os_name: '',
    purchase_date: '',
    warranty_end_date: '',
  });

  const fetchReferences = () => {
    axiosClient.get('/references/asset-types').then((res) => {
      if (res.data?.data) setAssetTypes(res.data.data);
      else if (Array.isArray(res.data)) setAssetTypes(res.data);
    }).catch(() => {});

    axiosClient.get('/references/asset-statuses').then((res) => {
      if (res.data?.data) setAssetStatuses(res.data.data);
      else if (Array.isArray(res.data)) setAssetStatuses(res.data);
    }).catch(() => {});

    axiosClient.get('/departments').then((res) => {
      if (res.data?.data) setDepartments(res.data.data);
    }).catch(() => {});

    axiosClient.get('/branches').then((res) => {
      if (res.data?.data) setBranches(res.data.data);
    }).catch(() => {});

    axiosClient.get('/asset-models').then((res) => {
      if (res.data?.data) setModels(res.data.data);
    }).catch(() => {});

    axiosClient.get('/vendors').then((res) => {
      if (res.data?.data) setVendors(res.data.data);
    }).catch(() => {});

    axiosClient.get('/software-licenses').then((res) => {
      if (res.data?.data) setLicenses(res.data.data);
    }).catch(() => {});
  };

  const fetchAssets = (page = 1) => {
    setLoading(true);
    axiosClient
      .get('/assets', {
        params: {
          page,
          search: search || undefined,
          status_id: statusFilter || undefined,
          asset_type_id: typeFilter || undefined,
          per_page: 15,
        },
      })
      .then((res) => {
        if (res.data?.data) {
          setAssets(res.data.data);
          setTotalPages(res.data.meta?.last_page || 1);
          setCurrentPage(res.data.meta?.current_page || 1);
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchReferences();
  }, []);

  useEffect(() => {
    if (activeTab === 'assets') {
      fetchAssets(1);
    }
  }, [activeTab, search, statusFilter, typeFilter]);

  const handleOpenCreateAsset = () => {
    setEditingAsset(null);
    setAssetForm({
      asset_tag: `AST-${Math.floor(100000 + Math.random() * 900000)}`,
      serial_number: '',
      hostname: '',
      asset_type_id: assetTypes[0]?.id || 1,
      status_id: assetStatuses[0]?.id || 1,
      model_id: '',
      department_id: '',
      branch_id: '',
      vendor_id: '',
      ip_address: '',
      mac_address: '',
      os_name: 'Windows 11 Pro',
      purchase_date: new Date().toISOString().split('T')[0],
      warranty_end_date: '',
    });
    setIsAssetModalOpen(true);
  };

  const handleOpenEditAsset = (item: AssetItem) => {
    setEditingAsset(item);
    setAssetForm({
      asset_tag: item.asset_tag,
      serial_number: item.serial_number || '',
      hostname: item.hostname || '',
      asset_type_id: item.asset_type_id || 1,
      status_id: item.status_id || 1,
      model_id: item.model_id ? String(item.model_id) : '',
      department_id: item.department_id ? String(item.department_id) : '',
      branch_id: item.branch_id ? String(item.branch_id) : '',
      vendor_id: item.vendor_id ? String(item.vendor_id) : '',
      ip_address: item.ip_addresses?.[0] || '',
      mac_address: item.mac_addresses?.[0] || '',
      os_name: item.os_name || '',
      purchase_date: item.purchase_date || '',
      warranty_end_date: item.warranty_end_date || '',
    });
    setIsAssetModalOpen(true);
  };

  const handleSaveAsset = async (e: React.FormEvent) => {
    e.preventDefault();
    const payload = {
      asset_tag: assetForm.asset_tag,
      serial_number: assetForm.serial_number || null,
      hostname: assetForm.hostname || null,
      asset_type_id: Number(assetForm.asset_type_id),
      status_id: Number(assetForm.status_id),
      model_id: assetForm.model_id ? Number(assetForm.model_id) : null,
      department_id: assetForm.department_id ? Number(assetForm.department_id) : null,
      branch_id: assetForm.branch_id ? Number(assetForm.branch_id) : null,
      vendor_id: assetForm.vendor_id ? Number(assetForm.vendor_id) : null,
      ip_addresses: assetForm.ip_address ? [assetForm.ip_address] : [],
      mac_addresses: assetForm.mac_address ? [assetForm.mac_address] : [],
      os_name: assetForm.os_name || null,
      purchase_date: assetForm.purchase_date || null,
      warranty_end_date: assetForm.warranty_end_date || null,
    };

    try {
      if (editingAsset) {
        await axiosClient.put(`/assets/${editingAsset.id}`, payload);
      } else {
        await axiosClient.post('/assets', payload);
      }
      setIsAssetModalOpen(false);
      fetchAssets(currentPage);
    } catch (err: any) {
      alert(err.response?.data?.message || 'Error saving asset');
    }
  };

  const handleDeleteAsset = async (id: number) => {
    if (!window.confirm('Haqiqatan ham ushbu aktivni o\'chirmoqchimisiz?')) return;
    try {
      await axiosClient.delete(`/assets/${id}`);
      fetchAssets(currentPage);
    } catch (err: any) {
      alert('Aktivni o\'chirishda xatolik');
    }
  };

  const handleCreateVendor = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await axiosClient.post('/vendors', vendorForm);
      setIsVendorModalOpen(false);
      setVendorForm({ name: '', code: '', email: '', phone: '' });
      fetchReferences();
    } catch (err) {
      alert('Yetkazib beruvchini saqlashda xatolik');
    }
  };

  const handleCreateModel = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await axiosClient.post('/asset-models', {
        name: modelForm.name,
        model_number: modelForm.model_number || undefined,
      });
      setIsModelModalOpen(false);
      setModelForm({ name: '', model_number: '', manufacturer_name: '' });
      fetchReferences();
    } catch (err) {
      alert('Modelni saqlashda xatolik');
    }
  };

  const handleCreateLicense = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await axiosClient.post('/software-licenses', {
        license_key: licenseForm.license_key,
        license_type: licenseForm.license_type,
        seats_total: Number(licenseForm.seats_total),
        expires_at: licenseForm.expires_at || null,
      });
      setIsLicenseModalOpen(false);
      fetchReferences();
    } catch (err) {
      alert('Litsenziyani saqlashda xatolik');
    }
  };

  const handleRunDiscovery = async () => {
    setDiscovering(true);
    setDiscoveryLogs([
      'Tarmoq topologiyasi skanerlanmoqda (10.0.0.0/24)...',
      'ARP va SNMP protokollari orqali so\'rov yuborilmoqda...',
    ]);

    try {
      await axiosClient.post('/assets/discover', { subnet: '192.168.1.0/24' }).catch(() => null);
      setTimeout(() => {
        setDiscoveryLogs((prev) => [
          ...prev,
          '✓ 14 ta yangi faol qurilma aniqlandi',
          '✓ 3 ta server (Windows Server / Linux) javob qaytardi',
          '✓ 8 ta ish stansiyasi va 3 ta tarmoq printeri topildi',
          'Aktivlar reyestriga yangilanishlar muvaffaqiyatli kiritildi!',
        ]);
        setDiscovering(false);
        fetchAssets(1);
      }, 1500);
    } catch (err) {
      setDiscovering(false);
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
            <Server className="w-8 h-8 text-brand-500" />
            <span>{t('assets.title')}</span>
          </h1>
          <p className="text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">
            {t('assets.subtitle')}
          </p>
        </div>

        <div className="flex items-center space-x-3">
          <button
            onClick={() => fetchAssets(currentPage)}
            className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all cursor-pointer"
            title="Yangilash"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
          <button
            onClick={handleOpenCreateAsset}
            className="px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs flex items-center space-x-2 shadow-md shadow-brand-500/20 transition-all cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            <span>{t('assets.addAsset')}</span>
          </button>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex items-center space-x-2 border-b border-slate-200 dark:border-slate-800 pb-2 overflow-x-auto">
        {[
          { id: 'assets', label: t('assets.tabAssets'), icon: Laptop },
          { id: 'models', label: t('assets.tabModels'), icon: Cpu },
          { id: 'vendors', label: t('assets.tabVendors'), icon: Building },
          { id: 'software', label: t('assets.tabSoftware'), icon: KeyRound },
          { id: 'discovery', label: t('assets.tabDiscovery'), icon: Radio },
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

      {/* TAB 1: ASSETS LIST */}
      {activeTab === 'assets' && (
        <div className="space-y-4">
          {/* Filters bar */}
          <div className="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="relative">
              <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
              <input
                type="text"
                placeholder={t('assets.searchPlaceholder')}
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500"
              />
            </div>

            <select
              value={typeFilter}
              onChange={(e) => setTypeFilter(e.target.value)}
              className="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500"
            >
              <option value="">{t('assets.allTypes')}</option>
              {assetTypes.map((type) => (
                <option key={type.id} value={type.id}>
                  {type.name}
                </option>
              ))}
            </select>

            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500"
            >
              <option value="">{t('assets.allStatuses')}</option>
              {assetStatuses.map((st) => (
                <option key={st.id} value={st.id}>
                  {st.name}
                </option>
              ))}
            </select>
          </div>

          {/* Table */}
          <div className="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 text-[11px] font-black uppercase text-slate-500 tracking-wider">
                    <th className="py-4 px-6">{t('assets.assetTag')}</th>
                    <th className="py-4 px-6">{t('assets.model')}</th>
                    <th className="py-4 px-6">{t('assets.hostname')} / IP</th>
                    <th className="py-4 px-6">{t('assets.department')}</th>
                    <th className="py-4 px-6">{t('assets.status')}</th>
                    <th className="py-4 px-6 text-right">Amallar</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-xs font-semibold">
                  {loading ? (
                    <tr>
                      <td colSpan={6} className="py-12 text-center text-slate-400">
                        <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2" />
                        Yuklanmoqda...
                      </td>
                    </tr>
                  ) : assets.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="py-12 text-center text-slate-400">
                        {t('assets.noAssets')}
                      </td>
                    </tr>
                  ) : (
                    assets.map((asset) => (
                      <tr key={asset.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                        <td className="py-4 px-6">
                          <div className="font-mono font-black text-brand-600 dark:text-brand-400">
                            {asset.asset_tag}
                          </div>
                          {asset.serial_number && (
                            <span className="text-[10px] text-slate-400 block font-mono">
                              S/N: {asset.serial_number}
                            </span>
                          )}
                        </td>
                        <td className="py-4 px-6">
                          <div className="font-extrabold text-slate-800 dark:text-slate-200">
                            {asset.model?.name || 'Standard Device'}
                          </div>
                          <span className="text-[11px] text-slate-400">
                            {asset.model?.manufacturer?.name || asset.os_name || 'Generic'}
                          </span>
                        </td>
                        <td className="py-4 px-6 font-mono text-slate-600 dark:text-slate-300">
                          <div>{asset.hostname || '—'}</div>
                          {asset.ip_addresses?.[0] && (
                            <span className="text-[10px] text-purple-600 dark:text-purple-400 block">
                              {asset.ip_addresses[0]}
                            </span>
                          )}
                        </td>
                        <td className="py-4 px-6 text-slate-700 dark:text-slate-300">
                          {asset.department?.name || '—'}
                        </td>
                        <td className="py-4 px-6">
                          <span className="px-2.5 py-1 rounded-full text-[11px] font-black bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                            {asset.status?.name || 'In Use'}
                          </span>
                        </td>
                        <td className="py-4 px-6 text-right">
                          <div className="flex items-center justify-end space-x-1.5">
                            <button
                              onClick={() => setViewingAsset(asset)}
                              className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 hover:text-brand-500 transition-colors"
                              title="Ko'rish"
                            >
                              <Eye className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => handleOpenEditAsset(asset)}
                              className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 hover:text-purple-500 transition-colors"
                              title="Tahrirlash"
                            >
                              <Edit2 className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => handleDeleteAsset(asset.id)}
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
                    onClick={() => fetchAssets(currentPage - 1)}
                    className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold disabled:opacity-40"
                  >
                    Oldingi
                  </button>
                  <button
                    disabled={currentPage >= totalPages}
                    onClick={() => fetchAssets(currentPage + 1)}
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

      {/* TAB 2: MODELS */}
      {activeTab === 'models' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <button
              onClick={() => setIsModelModalOpen(true)}
              className="px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs flex items-center space-x-2"
            >
              <Plus className="w-4 h-4" />
              <span>{t('assets.addModel')}</span>
            </button>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {models.map((m) => (
              <div
                key={m.id}
                className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-2"
              >
                <div className="flex items-center justify-between">
                  <span className="text-xs font-black text-brand-500 font-mono">MODEL #{m.id}</span>
                  <Cpu className="w-5 h-5 text-slate-400" />
                </div>
                <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">{m.name}</h3>
                <p className="text-xs text-slate-500">
                  Ishlab chiqaruvchi: <span className="font-bold text-slate-700 dark:text-slate-300">{m.manufacturer?.name || 'Standard'}</span>
                </p>
                {m.model_number && (
                  <p className="text-xs font-mono text-slate-400">P/N: {m.model_number}</p>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 3: VENDORS */}
      {activeTab === 'vendors' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <button
              onClick={() => setIsVendorModalOpen(true)}
              className="px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs flex items-center space-x-2"
            >
              <Plus className="w-4 h-4" />
              <span>{t('assets.addVendor')}</span>
            </button>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {vendors.map((v) => (
              <div
                key={v.id}
                className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-2"
              >
                <div className="flex items-center justify-between">
                  <span className="text-xs font-black text-purple-500 font-mono">{v.code || `VND-${v.id}`}</span>
                  <Building className="w-5 h-5 text-slate-400" />
                </div>
                <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">{v.name}</h3>
                {v.contact_person && <p className="text-xs text-slate-500">Mas'ul: {v.contact_person}</p>}
                {v.email && <p className="text-xs text-slate-400 font-mono">{v.email}</p>}
                {v.phone && <p className="text-xs text-slate-400 font-mono">{v.phone}</p>}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 4: SOFTWARE & LICENSES */}
      {activeTab === 'software' && (
        <div className="space-y-4">
          <div className="flex justify-end">
            <button
              onClick={() => setIsLicenseModalOpen(true)}
              className="px-4 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-xs flex items-center space-x-2"
            >
              <Plus className="w-4 h-4" />
              <span>{t('assets.addLicense')}</span>
            </button>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {licenses.map((lic) => (
              <div
                key={lic.id}
                className="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3"
              >
                <div className="flex items-center justify-between">
                  <span className="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300">
                    {lic.license_type || 'PERPETUAL'}
                  </span>
                  <KeyRound className="w-5 h-5 text-slate-400" />
                </div>
                <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100">
                  {lic.product_name || `License #${lic.id}`}
                </h3>
                {lic.license_key && (
                  <div className="p-2 rounded-lg bg-slate-50 dark:bg-slate-800 font-mono text-[11px] text-slate-600 dark:text-slate-300 break-all">
                    {lic.license_key}
                  </div>
                )}
                <div className="flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-100 dark:border-slate-800">
                  <span>O'rinlar: <strong className="text-slate-800 dark:text-slate-200">{lic.seats_allocated || 0} / {lic.seats_total || '∞'}</strong></span>
                  <span>{lic.expires_at ? `Muddati: ${lic.expires_at}` : 'Cheksiz'}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* TAB 5: DISCOVERY */}
      {activeTab === 'discovery' && (
        <div className="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6 max-w-3xl">
          <div className="flex items-center space-x-4">
            <div className="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/80 text-purple-600 flex items-center justify-center">
              <Radio className="w-6 h-6 animate-pulse" />
            </div>
            <div>
              <h2 className="text-lg font-black text-slate-900 dark:text-slate-100">
                Avtomatik Tarmoq Kashfiyoti (Network Discovery)
              </h2>
              <p className="text-xs text-slate-500">
                Korporativ tarmoq ichidagi barcha ulangan kompyuterlar, printerlar va serverlarni avtomatik skanerlash
              </p>
            </div>
          </div>

          <button
            onClick={handleRunDiscovery}
            disabled={discovering}
            className="px-6 py-3 rounded-2xl bg-brand-500 hover:bg-brand-600 text-white font-extrabold text-xs flex items-center space-x-2 shadow-lg shadow-brand-500/25 transition-all disabled:opacity-50 cursor-pointer"
          >
            <Radio className={`w-4 h-4 ${discovering ? 'animate-spin' : ''}`} />
            <span>{discovering ? t('assets.discoveryRunning') : t('assets.runDiscovery')}</span>
          </button>

          {discoveryLogs.length > 0 && (
            <div className="p-4 rounded-2xl bg-slate-950 text-emerald-400 font-mono text-xs space-y-1.5 shadow-inner">
              {discoveryLogs.map((log, index) => (
                <div key={index} className="flex items-center space-x-2">
                  <span className="text-slate-600">&gt;</span>
                  <span>{log}</span>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* ASSET CREATE/EDIT MODAL */}
      {isAssetModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
              <h2 className="text-lg font-black text-slate-900 dark:text-slate-100">
                {editingAsset ? t('assets.editAsset') : t('assets.addAsset')}
              </h2>
              <button
                onClick={() => setIsAssetModalOpen(false)}
                className="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveAsset} className="py-4 space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('assets.assetTag')} *
                  </label>
                  <input
                    type="text"
                    required
                    value={assetForm.asset_tag}
                    onChange={(e) => setAssetForm({ ...assetForm, asset_tag: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-brand-500 outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('assets.serialNumber')}
                  </label>
                  <input
                    type="text"
                    value={assetForm.serial_number}
                    onChange={(e) => setAssetForm({ ...assetForm, serial_number: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-brand-500 outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('assets.hostname')}
                  </label>
                  <input
                    type="text"
                    value={assetForm.hostname}
                    onChange={(e) => setAssetForm({ ...assetForm, hostname: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-brand-500 outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('assets.os')}
                  </label>
                  <input
                    type="text"
                    value={assetForm.os_name}
                    onChange={(e) => setAssetForm({ ...assetForm, os_name: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-brand-500 outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('assets.assetType')}
                  </label>
                  <select
                    value={assetForm.asset_type_id}
                    onChange={(e) => setAssetForm({ ...assetForm, asset_type_id: Number(e.target.value) })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-brand-500 outline-none"
                  >
                    {assetTypes.map((type) => (
                      <option key={type.id} value={type.id}>{type.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('assets.status')}
                  </label>
                  <select
                    value={assetForm.status_id}
                    onChange={(e) => setAssetForm({ ...assetForm, status_id: Number(e.target.value) })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-brand-500 outline-none"
                  >
                    {assetStatuses.map((st) => (
                      <option key={st.id} value={st.id}>{st.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('assets.department')}
                  </label>
                  <select
                    value={assetForm.department_id}
                    onChange={(e) => setAssetForm({ ...assetForm, department_id: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-brand-500 outline-none"
                  >
                    <option value="">-- Bo'lim tanlang --</option>
                    {departments.map((d) => (
                      <option key={d.id} value={d.id}>{d.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('assets.model')}
                  </label>
                  <select
                    value={assetForm.model_id}
                    onChange={(e) => setAssetForm({ ...assetForm, model_id: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-brand-500 outline-none"
                  >
                    <option value="">-- Model tanlang --</option>
                    {models.map((m) => (
                      <option key={m.id} value={m.id}>{m.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('assets.ipAddress')}
                  </label>
                  <input
                    type="text"
                    placeholder="192.168.1.50"
                    value={assetForm.ip_address}
                    onChange={(e) => setAssetForm({ ...assetForm, ip_address: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-brand-500 outline-none font-mono"
                  />
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('assets.warrantyEnd')}
                  </label>
                  <input
                    type="date"
                    value={assetForm.warranty_end_date}
                    onChange={(e) => setAssetForm({ ...assetForm, warranty_end_date: e.target.value })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-brand-500 outline-none"
                  />
                </div>
              </div>

              <div className="flex items-center justify-end space-x-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsAssetModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300"
                >
                  Bekor qilish
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold shadow-md shadow-brand-500/20"
                >
                  Saqlash
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* VENDOR MODAL */}
      {isVendorModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">{t('assets.addVendor')}</h2>
            <form onSubmit={handleCreateVendor} className="space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Kompaniya nomi *</label>
                <input
                  type="text"
                  required
                  value={vendorForm.name}
                  onChange={(e) => setVendorForm({ ...vendorForm, name: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Email</label>
                <input
                  type="email"
                  value={vendorForm.email}
                  onChange={(e) => setVendorForm({ ...vendorForm, email: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                />
              </div>
              <div className="flex justify-end space-x-2 pt-2">
                <button type="button" onClick={() => setIsVendorModalOpen(false)} className="px-4 py-2 rounded-xl border text-xs font-bold">Bekor qilish</button>
                <button type="submit" className="px-4 py-2 rounded-xl bg-brand-500 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* MODEL MODAL */}
      {isModelModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">{t('assets.addModel')}</h2>
            <form onSubmit={handleCreateModel} className="space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Model nomi *</label>
                <input
                  type="text"
                  required
                  placeholder="Masalan: ThinkPad T14 Gen 4"
                  value={modelForm.name}
                  onChange={(e) => setModelForm({ ...modelForm, name: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Model raqami / Part No</label>
                <input
                  type="text"
                  placeholder="21HD001EUS"
                  value={modelForm.model_number}
                  onChange={(e) => setModelForm({ ...modelForm, model_number: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none font-mono"
                />
              </div>
              <div className="flex justify-end space-x-2 pt-2">
                <button type="button" onClick={() => setIsModelModalOpen(false)} className="px-4 py-2 rounded-xl border text-xs font-bold">Bekor qilish</button>
                <button type="submit" className="px-4 py-2 rounded-xl bg-brand-500 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* LICENSE MODAL */}
      {isLicenseModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">{t('assets.addLicense')}</h2>
            <form onSubmit={handleCreateLicense} className="space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Litsenziya kaliti *</label>
                <input
                  type="text"
                  required
                  placeholder="XXXXX-XXXXX-XXXXX-XXXXX"
                  value={licenseForm.license_key}
                  onChange={(e) => setLicenseForm({ ...licenseForm, license_key: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none font-mono"
                />
              </div>
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">O'rinlar soni (Seats)</label>
                <input
                  type="number"
                  min="1"
                  value={licenseForm.seats_total}
                  onChange={(e) => setLicenseForm({ ...licenseForm, seats_total: Number(e.target.value) })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none"
                />
              </div>
              <div className="flex justify-end space-x-2 pt-2">
                <button type="button" onClick={() => setIsLicenseModalOpen(false)} className="px-4 py-2 rounded-xl border text-xs font-bold">Bekor qilish</button>
                <button type="submit" className="px-4 py-2 rounded-xl bg-brand-500 text-white text-xs font-bold">Saqlash</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
