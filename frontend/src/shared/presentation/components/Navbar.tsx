import React, { useState, useEffect, useRef } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import {
  CheckSquare,
  Moon,
  Sun,
  LogOut,
  LayoutDashboard,
  ClipboardList,
  CheckSquare2,
  ShieldCheck,
  Users,
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
  Menu,
  X,
  Layers,
} from 'lucide-react';
import { useAuthStore } from '../store/useAuthStore';
import { useThemeStore } from '../store/useThemeStore';
import { useAuth } from '@/modules/authentication/infrastructure/presentation/hooks/useAuth';
import { RoleManagementModal } from '@/modules/roles/infrastructure/presentation/components/RoleManagementModal';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';

import { useCan } from '../hooks/useCan';
import { useT } from '../i18n/i18n';
import { LanguageSwitcher } from '../i18n/LanguageSwitcher';

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

  const dropdownRef = useRef<HTMLDivElement>(null);
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
  }, [isAuthenticated, location.pathname]);

  // Close dropdown on outside click or route change
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setActiveDropdown(null);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    setActiveDropdown(null);
    setIsMobileMenuOpen(false);
  }, [location.pathname]);

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const isSuperAdmin = user?.role === 'Super Admin' || user?.username === 'superadmin';
  const isStaff = Boolean(user?.isStaff) || isSuperAdmin;

  const canViewDashboard = isSuperAdmin || can('dashboard.view') || (isStaff && !user?.permissions?.length);
  const canViewMyTasks = isSuperAdmin || can('my_tasks.view');
  const canViewMonitoring = isSuperAdmin || can('monitoring.view');
  const canViewTeamWorkload = isSuperAdmin || can('team_workload.view');
  const canViewStats = isSuperAdmin || can('stats.view');
  const canManageRoles = isSuperAdmin || can('roles.manage');
  const canViewAudit = isSuperAdmin || can('audit.view');
  const canViewAssets = isSuperAdmin || can(['assets.view', 'assets.manage']);
  const canManageSla = isSuperAdmin || can('sla.manage');

  // Operations / Tickets group
  const opsLinks = [
    ...(canViewDashboard ? [{ label: t('nav.dashboard'), path: '/dashboard', icon: LayoutDashboard }] : []),
    ...(canViewMyTasks ? [{ label: t('nav.myTasks'), path: '/my-tasks', icon: CheckSquare2 }] : []),
    ...(canViewMonitoring ? [{ label: t('nav.monitoring'), path: '/monitoring', icon: Monitor }] : []),
    ...(canViewTeamWorkload ? [{ label: t('nav.teamWorkload'), path: '/team-workload', icon: Users }] : []),
    ...(canViewStats ? [{ label: t('nav.stats'), path: '/stats', icon: CheckSquare2 }] : []),
  ];

  // ITSM Services group
  const itsmLinks = [
    { label: t('nav.knowledge'), path: '/knowledge', icon: BookOpen },
    { label: t('nav.catalog'), path: '/catalog', icon: ShoppingBag },
    { label: t('nav.approvals'), path: '/approvals', icon: CheckCircle },
    ...(isStaff || canViewAssets ? [{ label: t('nav.assets'), path: '/assets', icon: Server }] : []),
    ...(isStaff ? [{ label: t('nav.problems'), path: '/problems', icon: AlertTriangle }] : []),
    ...(isStaff ? [{ label: t('nav.changes'), path: '/changes', icon: GitBranch }] : []),
  ];

  // Administration / Settings group
  const adminLinks = [
    ...(isStaff || canManageSla ? [{ label: t('nav.sla'), path: '/sla-policies', icon: Clock }] : []),
    ...(isStaff || isSuperAdmin ? [{ label: t('nav.automation'), path: '/automation', icon: Zap }] : []),
    ...(isStaff || isSuperAdmin ? [{ label: t('nav.itsmSettings'), path: '/itsm-settings', icon: Sliders }] : []),
    ...(canManageRoles ? [{ label: t('nav.rbac'), path: '/rbac', icon: ShieldCheck }] : []),
    ...(canViewAudit ? [{ label: t('nav.audit'), path: '/audit', icon: ShieldCheck }] : []),
  ];

  const isOpsActive = opsLinks.some((l) => location.pathname === l.path);
  const isItsmActive = itsmLinks.some((l) => location.pathname === l.path);
  const isAdminActive = adminLinks.some((l) => location.pathname === l.path);

  return (
    <header className="sticky top-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 transition-colors">
      <div className="w-full px-4 sm:px-8 lg:px-12 h-16 flex items-center justify-between" ref={dropdownRef}>
        {/* Brand */}
        <div className="flex items-center space-x-6">
          <Link to={isStaff ? '/dashboard' : '/requests'} className="flex items-center space-x-2.5">
            <div className="w-9 h-9 rounded-xl bg-brand-500 flex items-center justify-center text-white shadow-md">
              <CheckSquare className="w-5 h-5" />
            </div>
            <span className="text-xl font-extrabold bg-gradient-to-r from-brand-500 to-brand-700 bg-clip-text text-transparent">
              TaskFlow
            </span>
          </Link>

          {/* Desktop Navigation Links */}
          {isAuthenticated && (
            <nav className="hidden lg:flex items-center space-x-1.5">
              {/* 1. Requests (for everyone) */}
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

              {/* 2. Operations / Tasks Dropdown (for Staff) */}
              {isStaff && opsLinks.length > 0 && (
                <div className="relative">
                  <button
                    onClick={() => setActiveDropdown(activeDropdown === 'ops' ? null : 'ops')}
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
              <div className="relative">
                <button
                  onClick={() => setActiveDropdown(activeDropdown === 'itsm' ? null : 'itsm')}
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

              {/* 4. Administration & Settings Dropdown (Staff / Super Admin) */}
              {(isStaff || isSuperAdmin) && adminLinks.length > 0 && (
                <div className="relative">
                  <button
                    onClick={() => setActiveDropdown(activeDropdown === 'admin' ? null : 'admin')}
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
              aria-label="Open mobile menu"
            >
              {isMobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
            </button>
          )}

          {/* User Profile and Logout */}
          {isAuthenticated && user && (
            <div className="hidden sm:flex items-center space-x-3 pl-3 border-l border-slate-200 dark:border-slate-800">
              <Link
                to="/profile"
                className="flex items-center space-x-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:text-brand-500 transition-colors"
              >
                <img
                  src={user.image || `https://ui-avatars.com/api/?name=${user.firstName}+${user.lastName}`}
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

      {/* Mobile Drawer Navigation */}
      {isAuthenticated && isMobileMenuOpen && (
        <div className="lg:hidden border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 space-y-4 shadow-2xl">
          <div className="space-y-1">
            <Link
              to="/requests"
              className="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800"
            >
              <ClipboardList className="w-5 h-5 text-brand-500" />
              <span>{t('nav.myRequests')}</span>
            </Link>

            {/* Operations links */}
            {isStaff && (
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

            {/* Administration & Settings */}
            {(isStaff || isSuperAdmin) && adminLinks.length > 0 && (
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
              <Link to="/profile" className="flex items-center space-x-2 text-xs font-bold text-slate-700 dark:text-slate-200">
                <img
                  src={user?.image || `https://ui-avatars.com/api/?name=${user?.firstName}+${user?.lastName}`}
                  alt="Avatar"
                  className="w-7 h-7 rounded-full border border-brand-500"
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


