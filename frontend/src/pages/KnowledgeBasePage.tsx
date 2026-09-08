import React, { useState, useEffect } from 'react';
import {
  BookOpen,
  Search,
  Plus,
  Filter,
  RefreshCw,
  Eye,
  ThumbsUp,
  ThumbsDown,
  Edit2,
  Trash2,
  Share2,
  FileText,
  CheckCircle,
  Archive,
  ArrowLeft,
  X,
  Tag,
  Clock,
  User,
  ExternalLink,
  Lock,
  Globe,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { homePathFor } from '@/shared/presentation/routing/homePath';
import { useToastStore } from '@/shared/presentation/store/useToastStore';

interface ArticleItem {
  id: number;
  article_no?: string;
  title: string;
  summary?: string;
  content: string;
  content_format?: 'MARKDOWN' | 'HTML';
  status: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED';
  visibility: 'INTERNAL' | 'PUBLIC' | 'CUSTOMER';
  view_count?: number;
  helpful_count?: number;
  not_helpful_count?: number;
  category_id?: number;
  article_type_id?: number;
  author?: { id: number; username: string; firstName?: string; lastName?: string };
  category?: { id: number; name: string };
  articleType?: { id: number; name: string };
  created_at?: string;
}

export const KnowledgeBasePage: React.FC = () => {
  const t = useT();
  const { can, user } = useCan();
  const isStaff = Boolean(user?.isStaff) || user?.role === 'Super Admin' || user?.username === 'superadmin';

  const [articles, setArticles] = useState<ArticleItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState(isStaff ? '' : 'PUBLISHED');
  const [categories, setCategories] = useState<any[]>([]);
  const [articleTypes, setArticleTypes] = useState<any[]>([]);

  // Reader Modal
  const [readingArticle, setReadingArticle] = useState<ArticleItem | null>(null);
  const [feedbackSent, setFeedbackSent] = useState(false);

  // Editor Modal
  const [isEditorOpen, setIsEditorOpen] = useState(false);
  const [editingArticle, setEditingArticle] = useState<ArticleItem | null>(null);
  const [articleForm, setArticleForm] = useState({
    title: '',
    summary: '',
    content: '',
    category_id: '',
    article_type_id: 1,
    visibility: 'INTERNAL',
    status: 'PUBLISHED',
  });

  const fetchCategories = () => {
    axiosClient.get('/categories').then((res) => {
      if (res.data?.data) setCategories(res.data.data);
    }).catch(() => {});

    axiosClient.get('/references/article-types').then((res) => {
      if (res.data?.data) setArticleTypes(res.data.data);
      else if (Array.isArray(res.data)) setArticleTypes(res.data);
    }).catch(() => {});
  };

  const fetchArticles = () => {
    setLoading(true);
    axiosClient
      .get('/knowledge/articles', {
        params: {
          search: search || undefined,
          category_id: categoryFilter || undefined,
          status: statusFilter || undefined,
          per_page: 30,
        },
      })
      .then((res) => {
        if (res.data?.data) {
          setArticles(res.data.data);
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchCategories();
  }, []);

  useEffect(() => {
    fetchArticles();
  }, [search, categoryFilter, statusFilter]);

  const handleOpenReader = (article: ArticleItem) => {
    setFeedbackSent(false);
    setReadingArticle(article);
    // Fetch full article (increments view count)
    axiosClient.get(`/knowledge/articles/${article.id}`).then((res) => {
      if (res.data?.data) {
        setReadingArticle(res.data.data);
      }
    }).catch(() => {});
  };

  const handleSendFeedback = async (wasHelpful: boolean) => {
    if (!readingArticle) return;
    try {
      await axiosClient.post(`/knowledge/articles/${readingArticle.id}/feedback`, {
        was_helpful: wasHelpful,
      });
      setFeedbackSent(true);
    } catch (err) {
      setFeedbackSent(true);
    }
  };

  const handleOpenCreate = () => {
    setEditingArticle(null);
    setArticleForm({
      title: '',
      summary: '',
      content: '## Kirish\n\nUshbu qo\'llanma foydalanuvchilar uchun...\n\n### Bosqichlar:\n1. Tizimga kiring\n2. Sozlamalar bo\'limini oching\n3. Saqlash tugmasini bosing',
      category_id: categories[0]?.id ? String(categories[0].id) : '',
      article_type_id: articleTypes[0]?.id || 1,
      visibility: 'PUBLIC',
      status: 'PUBLISHED',
    });
    setIsEditorOpen(true);
  };

  const handleOpenEdit = (article: ArticleItem) => {
    setEditingArticle(article);
    setArticleForm({
      title: article.title,
      summary: article.summary || '',
      content: article.content || '',
      category_id: article.category_id ? String(article.category_id) : '',
      article_type_id: article.article_type_id || 1,
      visibility: article.visibility || 'INTERNAL',
      status: article.status || 'PUBLISHED',
    });
    setIsEditorOpen(true);
  };

  const handleSaveArticle = async (e: React.FormEvent) => {
    e.preventDefault();
    const payload = {
      title: articleForm.title,
      summary: articleForm.summary || null,
      content: articleForm.content,
      content_format: 'MARKDOWN',
      category_id: articleForm.category_id ? Number(articleForm.category_id) : null,
      article_type_id: Number(articleForm.article_type_id),
      visibility: articleForm.visibility,
      status: articleForm.status,
    };

    try {
      if (editingArticle) {
        await axiosClient.put(`/knowledge/articles/${editingArticle.id}`, payload);
      } else {
        await axiosClient.post('/knowledge/articles', payload);
      }
      setIsEditorOpen(false);
      fetchArticles();
      useToastStore.getState().success(editingArticle ? 'Maqola muvaffaqiyatli yangilandi' : 'Yangi bilimlar bazasi maqolasi yaratildi');
    } catch (err: any) {
      useToastStore.getState().error(err.response?.data?.message || 'Maqolani saqlashda xatolik yuz berdi');
    }
  };

  const handleDeleteArticle = async (id: number) => {
    if (!window.confirm('Maqolani o\'chirishni tasdiqlaysizmi?')) return;
    try {
      await axiosClient.delete(`/knowledge/articles/${id}`);
      fetchArticles();
      useToastStore.getState().success('Maqola o\'chirildi');
    } catch (err) {
      useToastStore.getState().error('O\'chirishda xatolik yuz berdi');
    }
  };

  const handlePublishToggle = async (article: ArticleItem) => {
    try {
      if (article.status === 'PUBLISHED') {
        await axiosClient.post(`/knowledge/articles/${article.id}/archive`);
        useToastStore.getState().info('Maqola arxivlandi');
      } else {
        await axiosClient.post(`/knowledge/articles/${article.id}/publish`);
        useToastStore.getState().success('Maqola e\'lon qilindi (Nashr etildi)');
      }
      fetchArticles();
    } catch (err) {
      useToastStore.getState().error('Amalni bajarishda xatolik yuz berdi');
    }
  };

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-8">
      {/* Hero Banner */}
      <div className="relative overflow-hidden rounded-3xl bg-gradient-to-r from-purple-600 via-indigo-600 to-brand-600 p-8 sm:p-12 text-white shadow-xl">
        <div className="relative z-10 max-w-2xl space-y-4">
          <Link
            to={homePathFor(can, isStaff)}
            className="inline-flex items-center text-xs font-bold text-purple-200 hover:text-white transition-colors"
          >
            <ArrowLeft className="w-4 h-4 mr-1" /> {t('audit.backToDashboard')}
          </Link>
          <div className="flex items-center space-x-3">
            <div className="p-3 rounded-2xl bg-white/20 backdrop-blur-md">
              <BookOpen className="w-8 h-8 text-white" />
            </div>
            <div>
              <h1 className="text-2xl sm:text-4xl font-black">{t('kb.title')}</h1>
              <p className="text-xs sm:text-sm text-purple-100 font-medium">{t('kb.subtitle')}</p>
            </div>
          </div>

          {/* Big Search Bar */}
          <div className="relative pt-2">
            <Search className="w-5 h-5 absolute left-4 top-5 text-slate-400" />
            <input
              type="text"
              placeholder={t('kb.searchPlaceholder')}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full pl-12 pr-4 py-3.5 rounded-2xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm font-semibold shadow-lg focus:outline-none focus:ring-4 focus:ring-purple-400/30"
            />
          </div>
        </div>

        {/* Decorative background shapes */}
        <div className="absolute right-0 top-0 -mt-12 -mr-12 w-96 h-96 rounded-full bg-white/10 blur-3xl pointer-events-none" />
      </div>

      {/* Controls Bar */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div className="flex items-center space-x-3 w-full sm:w-auto">
          {/* Category Filter */}
          <select
            value={categoryFilter}
            onChange={(e) => setCategoryFilter(e.target.value)}
            className="px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none"
          >
            <option value="">Barcha kategoriyalar</option>
            {categories.map((c) => (
              <option key={c.id} value={c.id}>
                {c.name}
              </option>
            ))}
          </select>

          {isStaff && (
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-xs font-bold focus:outline-none"
            >
              <option value="">Barcha statuslar</option>
              <option value="PUBLISHED">{t('kb.published')}</option>
              <option value="DRAFT">{t('kb.draft')}</option>
              <option value="ARCHIVED">{t('kb.archived')}</option>
            </select>
          )}
        </div>

        <div className="flex items-center space-x-3">
          <button
            onClick={fetchArticles}
            className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition-all cursor-pointer"
            title="Yangilash"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>
          {isStaff && (
            <button
              onClick={handleOpenCreate}
              className="px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs flex items-center space-x-2 shadow-md shadow-purple-600/20 transition-all cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>{t('kb.newArticle')}</span>
            </button>
          )}
        </div>
      </div>

      {/* Articles Grid */}
      {loading ? (
        <div className="py-20 text-center text-slate-400">
          <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-3" />
          <p className="text-xs font-bold">Maqolalar yuklanmoqda...</p>
        </div>
      ) : articles.length === 0 ? (
        <div className="py-16 text-center bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 p-8">
          <BookOpen className="w-12 h-12 text-slate-300 mx-auto mb-3" />
          <h3 className="text-base font-bold text-slate-700 dark:text-slate-300">{t('kb.noArticles')}</h3>
          <p className="text-xs text-slate-400 mt-1">Boshqa kalit so'z yoki kategoriya bo'yicha qidirib ko'ring.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {articles.map((article) => (
            <div
              key={article.id}
              onClick={() => handleOpenReader(article)}
              className="group p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-purple-300 dark:hover:border-purple-700 shadow-sm hover:shadow-xl transition-all cursor-pointer flex flex-col justify-between"
            >
              <div className="space-y-3">
                {/* Header badges */}
                <div className="flex items-center justify-between">
                  <span className="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase bg-purple-50 dark:bg-purple-950/80 text-purple-600 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                    {article.category?.name || 'Umumiy'}
                  </span>
                  <div className="flex items-center space-x-1.5 text-xs text-slate-400 font-semibold">
                    <Eye className="w-3.5 h-3.5" />
                    <span>{article.view_count || 0}</span>
                  </div>
                </div>

                {/* Title */}
                <h3 className="font-extrabold text-base text-slate-900 dark:text-slate-100 group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors line-clamp-2">
                  {article.title}
                </h3>

                {/* Summary */}
                <p className="text-xs text-slate-500 dark:text-slate-400 line-clamp-3 leading-relaxed">
                  {article.summary || article.content.substring(0, 150) + '...'}
                </p>
              </div>

              {/* Footer */}
              <div className="pt-4 mt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-400 font-medium">
                <div className="flex items-center space-x-1.5">
                  <User className="w-3.5 h-3.5" />
                  <span>{article.author?.firstName || article.author?.username || 'IT Support'}</span>
                </div>

                {isStaff && (
                  <div
                    onClick={(e) => e.stopPropagation()}
                    className="flex items-center space-x-1"
                  >
                    <button
                      onClick={() => handleOpenEdit(article)}
                      className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-400 hover:text-purple-600"
                      title="Tahrirlash"
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                    </button>
                    <button
                      onClick={() => handleDeleteArticle(article.id)}
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

      {/* ARTICLE READER MODAL */}
      {readingArticle && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm">
          <div className="w-full max-w-3xl bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-800 max-h-[90vh] overflow-y-auto space-y-6">
            <div className="flex items-start justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
              <div className="space-y-2">
                <div className="flex items-center space-x-2">
                  <span className="px-2.5 py-0.5 rounded-md text-[11px] font-black uppercase bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300">
                    {readingArticle.category?.name || 'Umumiy'}
                  </span>
                  <span className="text-xs text-slate-400 font-mono">#{readingArticle.article_no || readingArticle.id}</span>
                </div>
                <h2 className="text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100">
                  {readingArticle.title}
                </h2>
              </div>
              <button
                onClick={() => setReadingArticle(null)}
                className="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Article Content */}
            <div className="prose dark:prose-invert max-w-none text-slate-800 dark:text-slate-200 text-sm leading-relaxed whitespace-pre-wrap font-sans">
              {readingArticle.content}
            </div>

            {/* Feedback footer */}
            <div className="pt-6 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
              <div className="flex items-center space-x-3 text-xs font-bold text-slate-600 dark:text-slate-400">
                <span>{t('kb.helpfulQuestion')}</span>
                {feedbackSent ? (
                  <span className="text-emerald-500 font-extrabold flex items-center space-x-1">
                    <CheckCircle className="w-4 h-4" />
                    <span>{t('kb.feedbackThanks')}</span>
                  </span>
                ) : (
                  <div className="flex space-x-2">
                    <button
                      onClick={() => handleSendFeedback(true)}
                      className="px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 font-extrabold text-xs flex items-center space-x-1 hover:bg-emerald-100 transition-colors"
                    >
                      <ThumbsUp className="w-3.5 h-3.5" />
                      <span>{t('kb.helpfulYes')}</span>
                    </button>
                    <button
                      onClick={() => handleSendFeedback(false)}
                      className="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-extrabold text-xs flex items-center space-x-1 hover:bg-slate-200 transition-colors"
                    >
                      <ThumbsDown className="w-3.5 h-3.5" />
                      <span>{t('kb.helpfulNo')}</span>
                    </button>
                  </div>
                )}
              </div>

              <button
                onClick={() => setReadingArticle(null)}
                className="px-5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 font-bold text-xs text-slate-700 dark:text-slate-300"
              >
                Yopish
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ARTICLE EDITOR MODAL (STAFF) */}
      {isEditorOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm">
          <div className="w-full max-w-3xl bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-800 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
              <h2 className="text-lg font-black text-slate-900 dark:text-slate-100">
                {editingArticle ? t('kb.editArticle') : t('kb.newArticle')}
              </h2>
              <button
                onClick={() => setIsEditorOpen(false)}
                className="p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveArticle} className="py-4 space-y-4">
              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('kb.articleTitle')} *
                </label>
                <input
                  type="text"
                  required
                  placeholder="Masalan: Korporativ VPN ga ulanish bo'yicha yo'riqnoma"
                  value={articleForm.title}
                  onChange={(e) => setArticleForm({ ...articleForm, title: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-purple-500 outline-none"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('kb.category')}
                  </label>
                  <select
                    value={articleForm.category_id}
                    onChange={(e) => setArticleForm({ ...articleForm, category_id: e.target.value })}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-purple-500 outline-none"
                  >
                    <option value="">-- Kategoriya --</option>
                    {categories.map((c) => (
                      <option key={c.id} value={c.id}>{c.name}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('kb.visibility')}
                  </label>
                  <select
                    value={articleForm.visibility}
                    onChange={(e) => setArticleForm({ ...articleForm, visibility: e.target.value as any })}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-purple-500 outline-none"
                  >
                    <option value="PUBLIC">{t('kb.public')}</option>
                    <option value="INTERNAL">{t('kb.internal')}</option>
                    <option value="CUSTOMER">{t('kb.customer')}</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                    {t('kb.status')}
                  </label>
                  <select
                    value={articleForm.status}
                    onChange={(e) => setArticleForm({ ...articleForm, status: e.target.value as any })}
                    className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-purple-500 outline-none"
                  >
                    <option value="PUBLISHED">{t('kb.published')}</option>
                    <option value="DRAFT">{t('kb.draft')}</option>
                    <option value="ARCHIVED">{t('kb.archived')}</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('kb.summary')}
                </label>
                <input
                  type="text"
                  placeholder="Maqola haqida qisqacha 1-2 jumlali tavsif"
                  value={articleForm.summary}
                  onChange={(e) => setArticleForm({ ...articleForm, summary: e.target.value })}
                  className="w-full px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:ring-2 focus:ring-purple-500 outline-none"
                />
              </div>

              <div>
                <label className="block text-xs font-black text-slate-700 dark:text-slate-300 mb-1">
                  {t('kb.content')} *
                </label>
                <textarea
                  required
                  rows={10}
                  value={articleForm.content}
                  onChange={(e) => setArticleForm({ ...articleForm, content: e.target.value })}
                  className="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-mono font-medium focus:ring-2 focus:ring-purple-500 outline-none"
                />
              </div>

              <div className="flex items-center justify-end space-x-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setIsEditorOpen(false)}
                  className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300"
                >
                  Bekor qilish
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow-md shadow-purple-600/20"
                >
                  Saqlash va Nashr qilish
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
