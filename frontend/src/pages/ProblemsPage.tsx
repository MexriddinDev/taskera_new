import React, { useState, useEffect } from 'react';
import {
  AlertTriangle,
  Search,
  Plus,
  Filter,
  RefreshCw,
  Edit2,
  Trash2,
  Eye,
  Link as LinkIcon,
  CheckCircle2,
  ArrowLeft,
  X,
  BookOpen,
  FileCode,
  User,
  Shield,
  Activity,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useToastStore } from '@/shared/presentation/store/useToastStore';

interface ProblemItem {
  id: number;
  problem_no?: string;
  title: string;
  description?: string;
  status: number;
  priority_id?: number;
  owner_user_id?: number;
  known_error: boolean;
  root_cause?: string;
  workaround?: string;
  resolved_at?: string;
  ownerUser?: { id: number; username: string; firstName?: string; lastName?: string };
  tickets?: Array<{ id: number; ticket_number: string; subject: string; status: string }>;
  created_at?: string;
}

export const ProblemsPage: React.FC = () => {
  const t = useT();
  const { user } = useCan();

  const [problems, setProblems] = useState<ProblemItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [knownErrorFilter, setKnownErrorFilter] = useState<string>('all');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  // Modals
  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [editingProblem, setEditingProblem] = useState<ProblemItem | null>(null);
  const [viewingProblem, setViewingProblem] = useState<ProblemItem | null>(null);

  // Link Ticket Modal
  const [isLinkModalOpen, setIsLinkModalOpen] = useState(false);
  const [linkProblemId, setLinkProblemId] = useState<number | null>(null);
  const [ticketIdToLink, setTicketIdToLink] = useState('');

  // Form State
  const [problemForm, setProblemForm] = useState({
    title: '',
    description: '',
    priority_id: 2,
    status: 1,
    known_error: false,
    root_cause: '',
    workaround: '',
  });

  const fetchProblems = (page = 1) => {
    setLoading(true);
    axiosClient
      .get('/problems', {
        params: {
          page,
          search: search || undefined,
          known_error: knownErrorFilter === 'known_only' ? true : undefined,
          per_page: 15,
        },
      })
      .then((res) => {
        if (res.data?.data) {
          setProblems(res.data.data);
          setTotalPages(res.data.meta?.last_page || 1);
          setCurrentPage(res.data.meta?.current_page || 1);
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchProblems(1);
  }, [search, knownErrorFilter]);

  const handleOpenCreate = () => {
    setEditingProblem(null);
    setProblemForm({
      title: '',
      description: '',
      priority_id: 2,
      status: 1,
      known_error: false,
      root_cause: '',
      workaround: '',
    });
    setIsFormModalOpen(true);
  };

  const handleOpenEdit = (problem: ProblemItem) => {
    setEditingProblem(problem);
    setProblemForm({
      title: problem.title,
      description: problem.description || '',
      priority_id: problem.priority_id || 2,
      status: problem.status || 1,
      known_error: Boolean(problem.known_error),
      root_cause: problem.root_cause || '',
      workaround: problem.workaround || '',
    });
    setIsFormModalOpen(true);
  };

  const handleOpenLinkModal = (problemId: number) => {
    setLinkProblemId(problemId);
    setTicketIdToLink('');
    setIsLinkModalOpen(true);
  };

  const handleSaveProblem = async (e: React.FormEvent) => {
    e.preventDefault();
    const payload = {
      title: problemForm.title,
      description: problemForm.description || null,
      priority_id: Number(problemForm.priority_id),
      status: Number(problemForm.status),
      known_error: Boolean(problemForm.known_error),
      root_cause: problemForm.root_cause || null,
      workaround: problemForm.workaround || null,
    };

    try {
      if (editingProblem) {
        await axiosClient.put(`/problems/${editingProblem.id}`, payload);
      } else {
        await axiosClient.post('/problems', payload);
      }
      setIsFormModalOpen(false);
      fetchProblems(currentPage);
      useToastStore.getState().success(editingProblem ? 'Muammo muvaffaqiyatli tahrirlandi' : 'Yangi muammo yaratildi');
    } catch (err: any) {
      useToastStore.getState().error(err.response?.data?.message || 'Muammoni saqlashda xatolik yuz berdi');
    }
  };

  const handleLinkTicket = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!linkProblemId || !ticketIdToLink.trim()) return;

    try {
      await axiosClient.post(`/problems/${linkProblemId}/link-ticket`, {
        ticket_id: Number(ticketIdToLink),
      });
      setIsLinkModalOpen(false);
      fetchProblems(currentPage);
      useToastStore.getState().success('Zayavka muammoga muvaffaqiyatli biriktirildi!');
    } catch (err: any) {
      useToastStore.getState().error('Zayavkani biriktirishda xatolik. Zayavka raqamini tekshiring.');
    }
  };

  const handleDeleteProblem = async (id: number) => {
    if (!window.confirm('Ushbu muammoni o\'chirishni tasdiqlaysizmi?')) return;
    try {
      await axiosClient.delete(`/problems/${id}`);
      fetchProblems(currentPage);
      useToastStore.getState().success('Muammo o\'chirildi');
    } catch (err: any) {
      useToastStore.getState().error(err.response?.data?.message || 'Muammoni o\'chirishda xatolik');
    }
  };

  const getStatusBadge = (status: number) => {
    switch (status) {
      case 1:
        return <span className="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300">Tekshirilmoqda</span>;
      case 2:
        return <span className="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300">Vaqtinchalik yechim bor</span>;
      case 3:
        return <span className="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Hal qilindi</span>;
      default:
        return <span className="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">Yopildi</span>;
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
            <AlertTriangle className="w-8 h-8 text-amber-500" />
            <span>{t('problems.title')}</span>
          </h1>
          <p className="text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">
            {t('problems.subtitle')}
          </p>
        </div>

        <div className="flex items-center space-x-3">
          <button
            onClick={() => fetchProblems(currentPage)}
            className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all cursor-pointer"
            title="Yangilash"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
          <button
            onClick={handleOpenCreate}
            className="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white font-bold text-xs flex items-center space-x-2 shadow-md shadow-amber-500/20 transition-all cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            <span>{t('problems.newProblem')}</span>
          </button>
        </div>
      </div>

      {/* Filter bar */}
      <div className="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
        <div className="relative w-full sm:w-80">
          <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
          <input
            type="text"
            placeholder="Muammo raqami, sarlavha, sabab..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-amber-500 outline-none"
          />
        </div>

        <div className="flex items-center space-x-2 w-full sm:w-auto">
          <button
            onClick={() => setKnownErrorFilter('all')}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${
              knownErrorFilter === 'all'
                ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20'
                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
            }`}
          >
            {t('problems.allProblems')}
          </button>
          <button
            onClick={() => setKnownErrorFilter('known_only')}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${
              knownErrorFilter === 'known_only'
                ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20'
                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'
            }`}
          >
            {t('problems.knownErrorsOnly')}
          </button>
        </div>
      </div>

      {/* Problems Table */}
      <div className="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 text-[11px] font-black uppercase text-slate-500 tracking-wider">
                <th className="py-4 px-6">{t('problems.problemNo')}</th>
                <th className="py-4 px-6">Sarlavha & Tavsif</th>
                <th className="py-4 px-6">KEDB Holati</th>
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
              ) : problems.length === 0 ? (
                <tr>
                  <td colSpan={5} className="py-12 text-center text-slate-400">
                    {t('problems.noProblems')}
                  </td>
                </tr>
              ) : (
                problems.map((prb) => (
                  <tr key={prb.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                    <td className="py-4 px-6 font-mono font-black text-amber-600 dark:text-amber-400">
                      {prb.problem_no || `PRB-${prb.id}`}
                    </td>
                    <td className="py-4 px-6 max-w-md">
                      <div className="font-extrabold text-slate-800 dark:text-slate-200">
                        {prb.title}
                      </div>
                      {prb.description && (
                        <p className="text-[11px] text-slate-400 line-clamp-1 mt-0.5">{prb.description}</p>
                      )}
                    </td>
                    <td className="py-4 px-6">
                      {prb.known_error ? (
                        <span className="px-2.5 py-1 rounded-lg text-[10px] font-black bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                          {t('problems.knownErrorBadge')}
                        </span>
                      ) : (
                        <span className="text-[11px] text-slate-400 font-medium">—</span>
                      )}
                    </td>
                    <td className="py-4 px-6">
                      {getStatusBadge(prb.status)}
                    </td>
                    <td className="py-4 px-6 text-right">
                      <div className="flex items-center justify-end space-x-1.5">
                        <button
                          onClick={() => handleOpenLinkModal(prb.id)}
                          className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 hover:text-blue-500 transition-colors"
                          title={t('problems.linkTickets')}
                        >
                          <LinkIcon className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleOpenEdit(prb)}
                          className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 hover:text-amber-500 transition-colors"
                          title="Tahrirlash"
                        >
                          <Edit2 className="w-4 h-4" />
                        </button>
                        <button
                          onClick={() => handleDeleteProblem(prb.id)}
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

      {/* CREATE / EDIT PROBLEM MODAL */}
      {isFormModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-2xl bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
              <h2 className="text-lg font-black text-slate-900 dark:text-slate-100">
                {editingProblem ? t('problems.editProblem') : t('problems.newProblem')}
              </h2>
              <button
                onClick={() => setIsFormModalOpen(false)}
                className="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveProblem} className="py-4 space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  Muammo sarlavhasi *
                </label>
                <input
                  type="text"
                  required
                  placeholder="Masalan: Markaziy switch portida yuqori paket yo'qolishi"
                  value={problemForm.title}
                  onChange={(e) => setProblemForm({ ...problemForm, title: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-amber-500 outline-none"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    Holati (Status)
                  </label>
                  <select
                    value={problemForm.status}
                    onChange={(e) => setProblemForm({ ...problemForm, status: Number(e.target.value) })}
                    className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold focus:ring-2 focus:ring-amber-500 outline-none"
                  >
                    <option value={1}>{t('problems.statusInvestigating')}</option>
                    <option value={2}>{t('problems.statusWorkaround')}</option>
                    <option value={3}>{t('problems.statusResolved')}</option>
                    <option value={4}>{t('problems.statusClosed')}</option>
                  </select>
                </div>
                <div className="flex items-center space-x-3 pt-6">
                  <input
                    type="checkbox"
                    id="knownErrorCb"
                    checked={problemForm.known_error}
                    onChange={(e) => setProblemForm({ ...problemForm, known_error: e.target.checked })}
                    className="w-4 h-4 rounded text-amber-500 focus:ring-amber-400"
                  />
                  <label htmlFor="knownErrorCb" className="text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer">
                    {t('problems.knownError')} (KEDB ga kiritish)
                  </label>
                </div>
              </div>

              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  Batafsil tavsif
                </label>
                <textarea
                  rows={3}
                  value={problemForm.description}
                  onChange={(e) => setProblemForm({ ...problemForm, description: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium focus:ring-2 focus:ring-amber-500 outline-none"
                />
              </div>

              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('problems.rootCause')}
                </label>
                <textarea
                  rows={3}
                  placeholder="Nosozlikka nima sabab bo'lgani tahlili..."
                  value={problemForm.root_cause}
                  onChange={(e) => setProblemForm({ ...problemForm, root_cause: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium focus:ring-2 focus:ring-amber-500 outline-none"
                />
              </div>

              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('problems.workaround')}
                </label>
                <textarea
                  rows={3}
                  placeholder="Asosiy yechim chiqquncha vaqtinchalik aylanma yo'l..."
                  value={problemForm.workaround}
                  onChange={(e) => setProblemForm({ ...problemForm, workaround: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-medium focus:ring-2 focus:ring-amber-500 outline-none"
                />
              </div>

              <div className="flex items-center justify-end space-x-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsFormModalOpen(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300"
                >
                  Bekor qilish
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-md shadow-amber-500/20"
                >
                  Saqlash
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* LINK TICKET MODAL */}
      {isLinkModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-6 shadow-2xl border border-slate-200 dark:border-slate-800">
            <h2 className="text-lg font-black text-slate-900 dark:text-slate-100 mb-2">{t('problems.linkTickets')}</h2>
            <p className="text-xs text-slate-500 mb-4">Muammoga bog'lash uchun tegishli zayavka (Ticket) raqami yoki ID sini kiriting.</p>
            <form onSubmit={handleLinkTicket} className="space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">Ticket ID *</label>
                <input
                  type="number"
                  required
                  placeholder="Masalan: 42"
                  value={ticketIdToLink}
                  onChange={(e) => setTicketIdToLink(e.target.value)}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs font-semibold outline-none font-mono"
                />
              </div>
              <div className="flex justify-end space-x-2 pt-2">
                <button type="button" onClick={() => setIsLinkModalOpen(false)} className="px-4 py-2 rounded-xl border text-xs font-bold">Bekor qilish</button>
                <button type="submit" className="px-4 py-2 rounded-xl bg-amber-500 text-white text-xs font-bold">Biriktirish</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
