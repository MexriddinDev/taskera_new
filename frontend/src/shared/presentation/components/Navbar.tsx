import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import {
  BadgeCheck,
  CheckSquare,
  Moon,
  Sun,
  LogOut,
  LayoutDashboard,
  ClipboardList,
  Headphones,
  CheckSquare2,
  ShieldCheck,
  Users,
  UserCheck,
  Network,
  Monitor,
  BookOpen,
  Server,
  AlertTriangle,
  GitBranch,
  ShoppingBag,
  CheckCircle,
  Clock,
  Zap,
  Sliders,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  Menu,
  X,
  Layers,
  Phone,
} from 'lucide-react';
import { useAuthStore } from '../store/useAuthStore';
import { useThemeStore } from '../store/useThemeStore';
import { useAuth } from '@/modules/authentication/infrastructure/presentation/hooks/useAuth';
import { RoleManagementModal } from '@/modules/roles/infrastructure/presentation/components/RoleManagementModal';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';

import { useCan } from '../hooks/useCan';
import { homePathFor } from '../routing/homePath';
import { useT } from '../i18n/i18n';
import { LanguageSwitcher } from '../i18n/LanguageSwitcher';

/**
 * Rasm yo'q foydalanuvchi uchun bosh harflardan avatar.
 * `size` berilmasa ui-avatars 64px qaytaradi — Retina ekranda u xira ko'rinardi.
 */
const SIDEBAR_KEY = 'taskera_sidebar_collapsed';

const avatarFallback = (firstName?: string | null, lastName?: string | null): string =>
  `https://ui-avatars.com/api/?name=${encodeURIComponent(`${firstName ?? ''} ${lastName ?? ''}`.trim() || 'User')}&size=256&bold=true&background=0D8ABC&color=fff`;

export const Navbar: React.FC = () => {
  const t = useT();
  const { user, isAuthenticated } = useAuthStore();
  const { logout } = useAuth();
  const { theme, toggleTheme } = useThemeStore();
  const { can } = useCan();
  const navigate = useNavigate();
  const location = useLocation();

  const [isRoleModalOpen, setIsRoleModalOpen] = useState(false);
  const [activeDropdown, setActiveDropdown] = useState<'ops' | 'itsm' | 'admin' | null>(null);
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  // Yon panel yig'ilgan holati brauzerda saqlanadi — sahifa yangilanganda
  // foydalanuvchi tanlovi qaytadi.
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState<boolean>(() => {
    try {
      return localStorage.getItem(SIDEBAR_KEY) === '1';
    } catch {
      return false;
    }
  });

  // Kontent kengligi shu o'zgaruvchiga bog'langan (App.tsx dagi `lg:pl-[var(--sidebar-w)]`).
  useEffect(() => {
    document.documentElement.style.setProperty('--sidebar-w', isSidebarCollapsed ? '4.5rem' : '18rem');
    try {
      localStorage.setItem(SIDEBAR_KEY, isSidebarCollapsed ? '1' : '0');
    } catch {
      // localStorage mavjud emas — faqat joriy sessiya uchun ishlaydi
    }
  }, [isSidebarCollapsed]);

  const setUser = useAuthStore((state) => state.setUser);

  useEffect(() => {
    if (!isAuthenticated) return;

    const syncProfile = () => {
      axiosClient.get('/auth/me').catch(() => axiosClient.get('/me')).then((res) => {
        const userData = res?.data?.user?.data || res?.data?.user;
        if (userData) {
          setUser(userData);
        }
      }).catch(() => {});
    };

    syncProfile();
    const interval = setInterval(() => {
      if (document.visibilityState === 'visible') {
        syncProfile();
      }
    }, 60_000);
    return () => clearInterval(interval);
  }, [isAuthenticated, setUser]);

  useEffect(() => {
    setIsMobileMenuOpen(false);
  }, [location.pathname]);

  useEffect(() => {
    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setActiveDropdown(null);
        setIsMobileMenuOpen(false);
      }
    };
    window.addEventListener('keydown', handleEscape);
    return () => window.removeEventListener('keydown', handleEscape);
  }, []);

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const isSuperAdmin = user?.role === 'Super Admin' || user?.username === 'superadmin';
  const isStaff = Boolean(user?.isStaff) || isSuperAdmin;

  // Boshqaruv paneli — barcha zayavkalarning umumiy ko'rinishi. Faqat
  // `dashboard.view` huquqi bo'lganlarga: support xodim uni ko'rmaydi.
  // Havola /dashboard route qorovuli bilan bir xil shartga tayanadi.
  const canViewDashboard = isSuperAdmin || can('dashboard.view');
  const canViewOwnRequests = isSuperAdmin || can('tickets.view_own');
  const canViewMyTasks = isSuperAdmin || can('my_tasks.view');
  const canViewMonitoring = isSuperAdmin || can('monitoring.view');
  // Support paneli navbatni ko'rish huquqidan ALOHIDA — RBAC dan biriktiriladi.
  const canViewSupportPanel = isSuperAdmin || can('support_panel.view');
  const canViewTeamWorkload = isSuperAdmin || can('team_workload.view');
  const canViewUsers = isSuperAdmin || can(['users.view', 'users.manage', 'stats.view']);
  const canViewStats = isSuperAdmin || can('stats.view');
  const canManageRoles = isSuperAdmin || can('roles.manage');
  const canViewAudit = isSuperAdmin || can('audit.view');
  const canManagePermits = isSuperAdmin || can('permits.manage');
  const canViewKnowledge = isSuperAdmin || can(['knowledge.view', 'knowledge.manage']);
  const canViewCatalog = isSuperAdmin || can('catalog.view');
  const canViewApprovals = isSuperAdmin || can(['approvals.view', 'changes.approve']);
  const canViewAssets = isSuperAdmin || can(['assets.view', 'assets.manage']);
  const canManageSla = isSuperAdmin || can('sla.manage');
  const canViewProblems = isSuperAdmin || can(['problems.view', 'problems.manage']);
  const canViewChanges = isSuperAdmin || can(['changes.view', 'changes.manage']);
  const canManageAutomation = isSuperAdmin || can('automation.manage');
  const canManageItsmSettings = isSuperAdmin || can(['services.manage', 'workflows.manage', 'integrations.manage']);
  const canViewIntegrationMap = isSuperAdmin || can('integrations.manage');

  // Operations / Tickets group
  const opsLinks = [
    ...(canViewDashboard ? [{ label: t('nav.dashboard'), path: '/dashboard', icon: LayoutDashboard }] : []),
    ...(canViewMyTasks ? [{ label: t('nav.myTasks'), path: '/my-tasks', icon: CheckSquare2 }] : []),
    ...(canViewMonitoring ? [{ label: t('nav.monitoring'), path: '/monitoring', icon: Monitor }] : []),
    ...(canViewTeamWorkload ? [{ label: t('nav.teamWorkload'), path: '/team-workload', icon: Users }] : []),
    ...(canViewSupportPanel ? [{ label: t('nav.supportPanel'), path: '/support-panel', icon: Headphones }] : []),
    ...(canViewUsers ? [{ label: t('nav.users'), path: '/users', icon: UserCheck }] : []),
    ...(canViewStats ? [{ label: t('nav.stats'), path: '/stats', icon: CheckSquare2 }] : []),
  ];

  // ITSM Services group.
  // Har bir havola o'z huquqiga bog'langan — bo'limni RBAC dan boshqarish
  // mumkin. Guruh bo'shab qolsa sarlavhasi ham chiqmaydi (render joylarida
  // `itsmLinks.length > 0` qorovuli bor).
  const itsmLinks = [
    ...(canViewKnowledge ? [{ label: t('nav.knowledge'), path: '/knowledge', icon: BookOpen }] : []),
    ...(canViewCatalog ? [{ label: t('nav.catalog'), path: '/catalog', icon: ShoppingBag }] : []),
    ...(canViewApprovals ? [{ label: t('nav.approvals'), path: '/approvals', icon: CheckCircle }] : []),
    ...(canViewAssets ? [{ label: t('nav.assets'), path: '/assets', icon: Server }] : []),
    ...(canViewProblems ? [{ label: t('nav.problems'), path: '/problems', icon: AlertTriangle }] : []),
    ...(canViewChanges ? [{ label: t('nav.changes'), path: '/changes', icon: GitBranch }] : []),
  ];

  // Administration / Settings group
  const adminLinks = [
    ...(canManageSla ? [{ label: t('nav.sla'), path: '/sla-policies', icon: Clock }] : []),
    ...(canManageAutomation ? [{ label: t('nav.automation'), path: '/automation', icon: Zap }] : []),
    ...(canManageItsmSettings ? [{ label: t('nav.itsmSettings'), path: '/itsm-settings', icon: Sliders }] : []),
    ...(canViewIntegrationMap ? [{ label: t('nav.integrationMap'), path: '/integrations-map', icon: Network }] : []),
    ...(canManageRoles ? [{ label: t('nav.rbac'), path: '/rbac', icon: ShieldCheck }] : []),
    { label: t('nav.ciscoCall'), path: '/cisco-call', icon: Phone },
    ...(canManagePermits ? [{ label: t('nav.permits'), path: '/permits', icon: BadgeCheck }] : []),
    ...(canViewAudit ? [{ label: t('nav.audit'), path: '/audit', icon: ShieldCheck }] : []),
  ];

  // Yon panel yig'ilganda guruhlar ochilmaydi — barcha havolalar bitta
  // ustunda faqat ikonka sifatida turadi, nomi tooltipda ko'rinadi.
  const railLinks = [
    ...(canViewOwnRequests ? [{ label: t('nav.myRequests'), path: '/requests', icon: ClipboardList, tone: 'text-brand-500' }] : []),
    ...opsLinks.map((link) => ({ ...link, tone: 'text-brand-500' })),
    ...itsmLinks.map((link) => ({ ...link, tone: 'text-purple-500' })),
    ...adminLinks.map((link) => ({ ...link, tone: 'text-emerald-500' })),
  ];

  const isPathActive = (path: string) => location.pathname === path || location.pathname.startsWith(`${path}/`);
  const isOpsActive = opsLinks.some((l) => isPathActive(l.path));
  const isItsmActive = itsmLinks.some((l) => isPathActive(l.path));
  const isAdminActive = adminLinks.some((l) => isPathActive(l.path));
  const homePath = homePathFor(can, isStaff);

  return (
    <header className="sticky top-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 transition-colors">
      <div className="w-full px-4 sm:px-8 lg:px-12 h-16 flex items-center justify-between">
        {/* Brand */}
        <div className="flex items-center space-x-6">
          <Link to={homePath} className="flex items-center space-x-2.5 rounded-xl focus-visible:ring-offset-4" aria-label="TaskFlow bosh sahifasi">
            <div className="w-9 h-9 rounded-xl bg-brand-500 flex items-center justify-center text-white shadow-md">
              <CheckSquare className="w-5 h-5" />
            </div>
            <span className="text-xl font-extrabold bg-gradient-to-r from-brand-500 to-brand-700 bg-clip-text text-transparent">
              TaskFlow
            </span>
          </Link>

          {/* Desktop navigatsiya chap sidebarda ko'rsatiladi. */}
          {isAuthenticated && (
            <nav className="hidden" aria-label="Asosiy navigatsiya">
              {/* 1. Requests — "tickets.view_own" huquqi bo'lganlarda.
                  Ilgari bu havola hamma uchun ochiq edi; endi RBAC dan
                  boshqariladi, ya'ni support xodimdan olib qo'yish mumkin. */}
              {canViewOwnRequests && (
              <Link
                to="/requests"
                className={`flex items-center space-x-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all ${
                  location.pathname === '/requests'
                    ? 'bg-brand-50 text-brand-500 dark:bg-brand-950/50 dark:text-brand-300'
                    : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                }`}
              >
                <ClipboardList className="w-4 h-4" />
                <span>{t('nav.myRequests')}</span>
              </Link>
              )}

              {/* 2. Operations / Tasks Dropdown (for Staff) */}
              {opsLinks.length > 0 && (
                <div className="relative">
                  <button
                    onClick={() => setActiveDropdown(activeDropdown === 'ops' ? null : 'ops')}
                    aria-expanded={activeDropdown === 'ops'}
                    aria-haspopup="menu"
                    className={`flex items-center space-x-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all ${
                      isOpsActive
                        ? 'bg-brand-50 text-brand-500 dark:bg-brand-950/50 dark:text-brand-300'
                        : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                    }`}
                  >
                    <LayoutDashboard className="w-4 h-4" />
                    <span>{t('nav.operationsGroup')}</span>
                    <ChevronDown className={`w-3.5 h-3.5 transition-transform ${activeDropdown === 'ops' ? 'rotate-180' : ''}`} />
                  </button>

                  {activeDropdown === 'ops' && (
                    <div className="absolute left-0 mt-2 w-56 p-2 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl z-50 animate-in fade-in-50 zoom-in-95">
                      {opsLinks.map((link) => {
                        const Icon = link.icon;
                        const isActive = location.pathname === link.path;
                        return (
                          <Link
                            key={link.path}
                            to={link.path}
                            className={`flex items-center space-x-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all ${
                              isActive
                                ? 'bg-brand-50 text-brand-600 dark:bg-brand-950/60 dark:text-brand-300 font-bold'
                                : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
                            }`}
                          >
                            <Icon className="w-4 h-4 text-brand-500" />
                            <span>{link.label}</span>
                          </Link>
                        );
                      })}
                    </div>
                  )}
                </div>
              )}

              {/* 3. ITSM Services Dropdown */}
              {itsmLinks.length > 0 && (
              <div className="relative">
                  <button
                  onClick={() => setActiveDropdown(activeDropdown === 'itsm' ? null : 'itsm')}
                  aria-expanded={activeDropdown === 'itsm'}
                  aria-haspopup="menu"
                  className={`flex items-center space-x-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all ${
                    isItsmActive
                      ? 'bg-brand-50 text-brand-500 dark:bg-brand-950/50 dark:text-brand-300'
                      : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                  }`}
                >
                  <Layers className="w-4 h-4" />
                  <span>{t('nav.itsmGroup')}</span>
                  <ChevronDown className={`w-3.5 h-3.5 transition-transform ${activeDropdown === 'itsm' ? 'rotate-180' : ''}`} />
                </button>

                {activeDropdown === 'itsm' && (
                  <div className="absolute left-0 mt-2 w-64 p-2 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl z-50 animate-in fade-in-50 zoom-in-95">
                    {itsmLinks.map((link) => {
                      const Icon = link.icon;
                      const isActive = location.pathname === link.path;
                      return (
                        <Link
                          key={link.path}
                          to={link.path}
                          className={`flex items-center space-x-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all ${
                            isActive
                              ? 'bg-brand-50 text-brand-600 dark:bg-brand-950/60 dark:text-brand-300 font-bold'
                              : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
                          }`}
                        >
                          <Icon className="w-4 h-4 text-purple-500" />
                          <span>{link.label}</span>
                        </Link>
                      );
                    })}
                  </div>
                )}
              </div>
              )}

              {/* 4. Administration & Settings Dropdown (Staff / Super Admin) */}
              {adminLinks.length > 0 && (
                <div className="relative">
                  <button
                    onClick={() => setActiveDropdown(activeDropdown === 'admin' ? null : 'admin')}
                    aria-expanded={activeDropdown === 'admin'}
                    aria-haspopup="menu"
                    className={`flex items-center space-x-1.5 px-3 py-2 rounded-xl text-xs font-bold transition-all ${
                      isAdminActive
                        ? 'bg-brand-50 text-brand-500 dark:bg-brand-950/50 dark:text-brand-300'
                        : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                    }`}
                  >
                    <Sliders className="w-4 h-4" />
                    <span>{t('nav.adminGroup')}</span>
                    <ChevronDown className={`w-3.5 h-3.5 transition-transform ${activeDropdown === 'admin' ? 'rotate-180' : ''}`} />
                  </button>

                  {activeDropdown === 'admin' && (
                    <div className="absolute left-0 mt-2 w-64 p-2 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl z-50 animate-in fade-in-50 zoom-in-95">
                      {adminLinks.map((link) => {
                        const Icon = link.icon;
                        const isActive = location.pathname === link.path;
                        return (
                          <Link
                            key={link.path}
                            to={link.path}
                            className={`flex items-center space-x-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all ${
                              isActive
                                ? 'bg-brand-50 text-brand-600 dark:bg-brand-950/60 dark:text-brand-300 font-bold'
                                : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
                            }`}
                          >
                            <Icon className="w-4 h-4 text-emerald-500" />
                            <span>{link.label}</span>
                          </Link>
                        );
                      })}
                    </div>
                  )}
                </div>
              )}
            </nav>
          )}
        </div>

        {/* Global Header Actions */}
        <div className="flex items-center space-x-3">
          <LanguageSwitcher />

          {/* Theme Toggle */}
          <button
            onClick={toggleTheme}
            className="p-2 rounded-xl text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            title={t('nav.toggleTheme')}
            aria-label={t('nav.toggleTheme')}
          >
            {theme === 'dark' ? <Sun className="w-5 h-5 text-amber-400" /> : <Moon className="w-5 h-5" />}
          </button>

          {/* Mobile menu trigger */}
          {isAuthenticated && (
            <button
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className="lg:hidden p-2 rounded-xl text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800"
              aria-label={isMobileMenuOpen ? 'Menyuni yopish' : 'Menyuni ochish'}
              aria-expanded={isMobileMenuOpen}
              aria-controls="mobile-navigation"
            >
              {isMobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
            </button>
          )}

          {/* User Profile and Logout */}
          {isAuthenticated && user && (
            <div className="hidden">
              <Link
                to="/profile"
                className="flex items-center space-x-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:text-brand-500 transition-colors"
              >
                <img
                  src={user.image || avatarFallback(user.firstName, user.lastName)}
                  alt={user.username}
                  className="w-8 h-8 rounded-full border-2 border-brand-500 object-cover"
                />
                <span className="hidden md:inline-block font-bold">{user.firstName}</span>
              </Link>
              <button
                onClick={handleLogout}
                className="p-2 rounded-xl text-error-500 hover:bg-error-50 dark:hover:bg-error-700/20 transition-colors cursor-pointer"
                title={t('nav.logout')}
                aria-label={t('nav.logout')}
              >
                <LogOut className="w-5 h-5" />
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Desktop Sidebar Navigation */}
      {isAuthenticated && createPortal(
        <aside className={`fixed bottom-0 left-0 top-16 z-30 hidden flex-col border-r border-slate-200 bg-white/95 shadow-sm backdrop-blur-md transition-[width] duration-200 dark:border-slate-800 dark:bg-slate-900/95 lg:flex ${isSidebarCollapsed ? 'w-[4.5rem]' : 'w-72'}`}>
          {/* Yig'ish tugmasi panel chekkasida turadi — bosilganda faqat ikonkalar qoladi. */}
          <button
            type="button"
            onClick={() => setIsSidebarCollapsed((open) => !open)}
            aria-expanded={!isSidebarCollapsed}
            aria-controls="sidebar-navigation"
            title={t(isSidebarCollapsed ? 'nav.expandSidebar' : 'nav.collapseSidebar')}
            aria-label={t(isSidebarCollapsed ? 'nav.expandSidebar' : 'nav.collapseSidebar')}
            className="absolute -right-3 top-4 z-10 flex h-6 w-6 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-md transition-colors hover:text-brand-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:text-brand-400"
          >
            {isSidebarCollapsed ? <ChevronRight className="h-4 w-4" /> : <ChevronLeft className="h-4 w-4" />}
          </button>

          {isSidebarCollapsed ? (
            <nav id="sidebar-navigation" className="flex-1 space-y-1 overflow-y-auto overscroll-contain scrollbar-none px-2 py-5" aria-label={t('nav.mainNavigation')}>
              {railLinks.map((link) => {
                const Icon = link.icon;
                const active = isPathActive(link.path);
                return (
                  <Link
                    key={link.path}
                    to={link.path}
                    title={link.label}
                    aria-label={link.label}
                    aria-current={active ? 'page' : undefined}
                    className={`flex items-center justify-center rounded-xl p-3 transition-colors ${
                      active
                        ? 'bg-brand-50 text-brand-600 dark:bg-brand-950/60 dark:text-brand-300'
                        : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'
                    }`}
                  >
                    <Icon className={`h-7 w-7 shrink-0 ${active ? '' : link.tone}`} />
                  </Link>
                );
              })}
            </nav>
          ) : (
          <nav id="sidebar-navigation" className="flex-1 space-y-2 overflow-y-auto overscroll-contain scrollbar-none px-4 py-5" aria-label={t('nav.mainNavigation')}>
            {canViewOwnRequests && (
              <section aria-label={t('nav.myRequests')}>
                <Link
                  to="/requests"
                  aria-current={isPathActive('/requests') ? 'page' : undefined}
                  className={`flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-black transition-colors ${
                    isPathActive('/requests')
                      ? 'bg-brand-50 text-brand-600 dark:bg-brand-950/60 dark:text-brand-300'
                      : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'
                  }`}
                >
                  <ClipboardList className="h-6 w-6 shrink-0 text-brand-500" />
                  <span>{t('nav.myRequests')}</span>
                </Link>
              </section>
            )}

            {opsLinks.length > 0 && (
              <section aria-labelledby="operations-navigation">
                <button
                  id="operations-navigation"
                  type="button"
                  onClick={() => setActiveDropdown(activeDropdown === 'ops' ? null : 'ops')}
                  aria-expanded={activeDropdown === 'ops'}
                  aria-controls="operations-navigation-links"
                  className={`flex w-full items-center gap-2 rounded-xl px-3 py-3 text-left text-xs font-black uppercase tracking-[0.08em] transition-colors ${
                    isOpsActive ? 'text-brand-600 dark:text-brand-300' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                  }`}
                >
                  <LayoutDashboard className="h-6 w-6 shrink-0 text-brand-500" />
                  <span className="flex-1">{t('nav.operationsGroup')}</span>
                  <ChevronDown className={`h-4 w-4 transition-transform ${activeDropdown === 'ops' ? 'rotate-180' : ''}`} />
                </button>
                {activeDropdown === 'ops' && <div id="operations-navigation-links" className="mt-1 space-y-1 pl-2">
                  {opsLinks.map((link) => {
                    const Icon = link.icon;
                    const active = isPathActive(link.path);
                    return (
                      <Link
                        key={link.path}
                        to={link.path}
                        aria-current={active ? 'page' : undefined}
                        className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors ${
                          active
                            ? 'bg-brand-50 text-brand-600 dark:bg-brand-950/60 dark:text-brand-300'
                            : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'
                        }`}
                      >
                        <Icon className="h-6 w-6 shrink-0 text-brand-500" />
                        <span>{link.label}</span>
                      </Link>
                    );
                  })}
                </div>}
              </section>
            )}

            {itsmLinks.length > 0 && (
            <section aria-labelledby="itsm-navigation">
              <button
                id="itsm-navigation"
                type="button"
                onClick={() => setActiveDropdown(activeDropdown === 'itsm' ? null : 'itsm')}
                aria-expanded={activeDropdown === 'itsm'}
                aria-controls="itsm-navigation-links"
                className={`flex w-full items-center gap-2 rounded-xl px-3 py-3 text-left text-xs font-black uppercase tracking-[0.08em] transition-colors ${
                  isItsmActive ? 'text-purple-700 dark:text-purple-300' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                }`}
              >
                <Layers className="h-6 w-6 shrink-0 text-purple-500" />
                <span className="flex-1">{t('nav.itsmGroup')}</span>
                <ChevronDown className={`h-4 w-4 transition-transform ${activeDropdown === 'itsm' ? 'rotate-180' : ''}`} />
              </button>
              {activeDropdown === 'itsm' && <div id="itsm-navigation-links" className="mt-1 space-y-1 pl-2">
                {itsmLinks.map((link) => {
                  const Icon = link.icon;
                  const active = isPathActive(link.path);
                  return (
                    <Link
                      key={link.path}
                      to={link.path}
                      aria-current={active ? 'page' : undefined}
                      className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors ${
                        active
                          ? 'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300'
                          : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'
                      }`}
                    >
                      <Icon className="h-6 w-6 shrink-0 text-purple-500" />
                      <span>{link.label}</span>
                    </Link>
                  );
                })}
              </div>}
            </section>
            )}

            {adminLinks.length > 0 && (
              <section aria-labelledby="admin-navigation">
                <button
                  id="admin-navigation"
                  type="button"
                  onClick={() => setActiveDropdown(activeDropdown === 'admin' ? null : 'admin')}
                  aria-expanded={activeDropdown === 'admin'}
                  aria-controls="admin-navigation-links"
                  className={`flex w-full items-center gap-2 rounded-xl px-3 py-3 text-left text-xs font-black uppercase tracking-[0.08em] transition-colors ${
                    isAdminActive ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                  }`}
                >
                  <Sliders className="h-6 w-6 shrink-0 text-emerald-500" />
                  <span className="flex-1">{t('nav.adminGroup')}</span>
                  <ChevronDown className={`h-4 w-4 transition-transform ${activeDropdown === 'admin' ? 'rotate-180' : ''}`} />
                </button>
                {activeDropdown === 'admin' && <div id="admin-navigation-links" className="mt-1 space-y-1 pl-2">
                  {adminLinks.map((link) => {
                    const Icon = link.icon;
                    const active = isPathActive(link.path);
                    return (
                      <Link
                        key={link.path}
                        to={link.path}
                        aria-current={active ? 'page' : undefined}
                        className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors ${
                          active
                            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                            : 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800'
                        }`}
                      >
                        <Icon className="h-6 w-6 shrink-0 text-emerald-500" />
                        <span>{link.label}</span>
                      </Link>
                    );
                  })}
                </div>}
              </section>
            )}
          </nav>
          )}

          {/* Locked bottom Profile & Logout section */}
          {user && isSidebarCollapsed && (
            <div className="border-t border-slate-200 bg-slate-50/60 p-2 dark:border-slate-800 dark:bg-slate-900/60">
              <Link
                to="/profile"
                title={[user.firstName, user.lastName].filter(Boolean).join(' ') || user.username}
                aria-label={t('profileCard.personalInfo')}
                aria-current={isPathActive('/profile') ? 'page' : undefined}
                className="flex justify-center rounded-xl p-2 hover:bg-slate-100 dark:hover:bg-slate-800"
              >
                <img
                  src={user.image || avatarFallback(user.firstName, user.lastName)}
                  alt={user.username}
                  className="h-9 w-9 rounded-full border-2 border-brand-500 object-cover"
                />
              </Link>
              <button
                type="button"
                onClick={handleLogout}
                className="mt-1 flex w-full justify-center rounded-xl p-2 text-error-500 transition-colors hover:bg-error-50 dark:hover:bg-error-950/40 cursor-pointer"
                title={t('nav.logout')}
                aria-label={t('nav.logout')}
              >
                <LogOut className="h-5 w-5" />
              </button>
            </div>
          )}

          {user && !isSidebarCollapsed && (
            <div className="border-t border-slate-200 bg-slate-50/60 p-3.5 dark:border-slate-800 dark:bg-slate-900/60" aria-label={t('profilePage.title')}>
              <div className={`flex items-center gap-2 rounded-2xl border p-2 transition-colors ${
                isPathActive('/profile')
                  ? 'border-brand-300 bg-brand-50 dark:border-brand-800 dark:bg-brand-950/50 shadow-sm'
                  : 'border-transparent bg-white hover:border-slate-200 dark:bg-slate-800/80 dark:hover:border-slate-700 shadow-xs'
              }`}>
                <Link
                  to="/profile"
                  aria-current={isPathActive('/profile') ? 'page' : undefined}
                  className="flex min-w-0 flex-1 items-center gap-3 rounded-xl p-1 text-left group"
                  aria-label={t('profileCard.personalInfo')}
                >
                  <img
                    src={user.image || avatarFallback(user.firstName, user.lastName)}
                    alt={user.username}
                    className="h-9 w-9 shrink-0 rounded-full border-2 border-brand-500 object-cover group-hover:ring-2 group-hover:ring-brand-500/30 transition-all"
                  />
                  <span className="min-w-0">
                    <span className="block truncate text-xs font-extrabold text-slate-800 dark:text-slate-100 group-hover:text-brand-600 dark:group-hover:text-brand-400 transition-colors">
                      {[user.firstName, user.lastName].filter(Boolean).join(' ') || user.username}
                    </span>
                    <span className="block truncate text-[11px] font-semibold text-slate-500 dark:text-slate-400">@{user.username}</span>
                  </span>
                </Link>
                <button
                  type="button"
                  onClick={handleLogout}
                  className="shrink-0 rounded-xl p-2 text-error-500 transition-colors hover:bg-error-50 dark:hover:bg-error-950/40 cursor-pointer"
                  title={t('nav.logout')}
                  aria-label={t('nav.logout')}
                >
                  <LogOut className="h-5 w-5" />
                </button>
              </div>
            </div>
          )}
        </aside>,
        document.body,
      )}

      {/* Mobile Drawer Navigation */}
      {isAuthenticated && isMobileMenuOpen && (
        <div id="mobile-navigation" className="lg:hidden max-h-[calc(100dvh-4rem)] overflow-y-auto overscroll-contain border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-4 shadow-2xl">
          <div className="space-y-1">
            {canViewOwnRequests && (
              <Link
                to="/requests"
                className="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
              >
                <ClipboardList className="w-5 h-5 text-brand-500" />
                <span>{t('nav.myRequests')}</span>
              </Link>
            )}

            {/* Operations links */}
            {opsLinks.length > 0 && (
              <div className="pt-2">
                <div className="px-3 py-1 text-[10px] font-black uppercase text-slate-400 tracking-wider">
                  {t('nav.operationsGroup')}
                </div>
                {opsLinks.map((link) => {
                  const Icon = link.icon;
                  return (
                    <Link
                      key={link.path}
                      to={link.path}
                      className="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
                    >
                      <Icon className="w-4 h-4 text-brand-500" />
                      <span>{link.label}</span>
                    </Link>
                  );
                })}
              </div>
            )}

            {/* ITSM Services */}
            {itsmLinks.length > 0 && (
            <div className="pt-2">
              <div className="px-3 py-1 text-[10px] font-black uppercase text-slate-400 tracking-wider">
                {t('nav.itsmGroup')}
              </div>
              {itsmLinks.map((link) => {
                const Icon = link.icon;
                return (
                  <Link
                    key={link.path}
                    to={link.path}
                    className="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
                  >
                    <Icon className="w-4 h-4 text-purple-500" />
                    <span>{link.label}</span>
                  </Link>
                );
              })}
            </div>
            )}

            {/* Administration & Settings */}
            {adminLinks.length > 0 && (
              <div className="pt-2">
                <div className="px-3 py-1 text-[10px] font-black uppercase text-slate-400 tracking-wider">
                  {t('nav.adminGroup')}
                </div>
                {adminLinks.map((link) => {
                  const Icon = link.icon;
                  return (
                    <Link
                      key={link.path}
                      to={link.path}
                      className="flex items-center space-x-3 px-3 py-2 rounded-xl text-sm font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
                    >
                      <Icon className="w-4 h-4 text-emerald-500" />
                      <span>{link.label}</span>
                    </Link>
                  );
                })}
              </div>
            )}

            {/* Mobile Profile & Logout */}
            <div className="pt-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
              <Link to="/profile" className="flex items-center space-x-2 text-left text-xs font-bold text-slate-700 dark:text-slate-200">
                <img
                  src={user?.image || avatarFallback(user?.firstName, user?.lastName)}
                  alt="Avatar"
                  className="w-7 h-7 rounded-full border border-brand-500 object-cover"
                />
                <span>{user?.firstName} {user?.lastName}</span>
              </Link>
              <button
                onClick={handleLogout}
                className="flex items-center space-x-1 text-xs font-bold text-error-500 px-3 py-1.5 rounded-lg bg-error-50 dark:bg-error-950/40"
              >
                <LogOut className="w-4 h-4" />
                <span>{t('nav.logout')}</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Role Management Modal for Super Admin */}
      <RoleManagementModal
        isOpen={isRoleModalOpen}
        onClose={() => setIsRoleModalOpen(false)}
      />
    </header>
  );
};


