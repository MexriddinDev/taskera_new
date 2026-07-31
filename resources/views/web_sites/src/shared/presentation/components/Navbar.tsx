import React, { useState, useEffect } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { CheckSquare, Moon, Sun, LogOut, LayoutDashboard, ClipboardList, CheckSquare2, User, ShieldCheck, Users, Monitor } from 'lucide-react';
import { useAuthStore } from '../store/useAuthStore';
import { useThemeStore } from '../store/useThemeStore';
import { useAuth } from '@/modules/authentication/infrastructure/presentation/hooks/useAuth';
import { RoleManagementModal } from '@/modules/roles/infrastructure/presentation/components/RoleManagementModal';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';

import { useCan } from '../hooks/useCan';

export const Navbar: React.FC = () => {
  const { user, isAuthenticated } = useAuthStore();
  const { logout } = useAuth();
  const { theme, toggleTheme } = useThemeStore();
  const { can } = useCan();
  const navigate = useNavigate();
  const location = useLocation();

  const [isRoleModalOpen, setIsRoleModalOpen] = useState(false);

  const setUser = useAuthStore((state) => state.setUser);

  useEffect(() => {
    if (!isAuthenticated) return;

    const syncProfile = () => {
      axiosClient.get('/me').then((res) => {
        if (res.data?.user) {
          setUser(res.data.user);
        }
      }).catch(() => {});
    };

    syncProfile();
    const interval = setInterval(syncProfile, 5000);
    return () => clearInterval(interval);
  }, [isAuthenticated, location.pathname]);

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const isSuperAdmin = user?.role === 'Super Admin' || user?.username === 'superadmin';
  const isStaff = Boolean(user?.isStaff) || isSuperAdmin;

  const canViewDashboard = isSuperAdmin || can('dashboard.view') || (isStaff && !user?.permissions?.length);
  const canViewTasks = isSuperAdmin || can('tasks.view');
  const canViewMyTasks = isSuperAdmin || can('my_tasks.view');
  const canViewExecutiveMonitoring = isSuperAdmin || can('monitoring.view');
  const canViewTeamWorkload = isSuperAdmin || can('team_workload.view');
  const canViewStats = isSuperAdmin || can('stats.view');
  const canManageRoles = isSuperAdmin || can('roles.manage');
  const canViewAudit = isSuperAdmin || can('audit.view');

  const navLinks = [
    ...(canViewDashboard ? [{ label: 'Dashboard', path: '/dashboard', icon: LayoutDashboard }] : []),
    ...(canViewTasks ? [{ label: 'Tasks', path: '/tasks', icon: ClipboardList }] : []),
    ...(canViewMyTasks ? [{ label: 'My Tasks', path: '/my-tasks', icon: CheckSquare2 }] : []),
    ...(canViewExecutiveMonitoring ? [{ label: 'Monitoring', path: '/monitoring', icon: Monitor }] : []),
    ...(canViewTeamWorkload ? [{ label: 'Xodimlar Zayavkalari', path: '/team-workload', icon: Users }] : []),
    ...(canViewStats ? [{ label: 'Statistika', path: '/stats', icon: CheckSquare2 }] : []),
    ...(canManageRoles ? [{ label: 'Rollar & Bo\'limlar', path: '/rbac', icon: ShieldCheck }] : []),
    ...(canViewAudit ? [{ label: 'Audit Loglar', path: '/audit', icon: ShieldCheck }] : []),
    { label: 'Zayavkalarim', path: '/requests', icon: ClipboardList },
  ];

  return (
    <header className="sticky top-0 z-40 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 transition-colors">
      <div className="w-full px-4 sm:px-8 lg:px-12 h-16 flex items-center justify-between">
        {/* Brand */}
        <div className="flex items-center space-x-8">
          <Link to={isStaff ? '/dashboard' : '/requests'} className="flex items-center space-x-2.5">
            <div className="w-9 h-9 rounded-xl bg-brand-500 flex items-center justify-center text-white shadow-md">
              <CheckSquare className="w-5 h-5" />
            </div>
            <span className="text-xl font-extrabold bg-gradient-to-r from-brand-500 to-brand-700 bg-clip-text text-transparent">
              TaskFlow
            </span>
          </Link>

          {/* Clean Primary Navigation Links */}
          {isAuthenticated && (
            <nav className="hidden md:flex items-center space-x-1">
              {navLinks.map((link) => {
                const Icon = link.icon;
                const isActive = location.pathname === link.path;
                return (
                  <Link
                    key={link.path}
                    to={link.path}
                    className={`flex items-center space-x-2 px-3.5 py-2 rounded-xl text-xs font-bold transition-all ${
                      isActive
                        ? 'bg-brand-50 text-brand-500 dark:bg-brand-950/50 dark:text-brand-300'
                        : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800'
                    }`}
                  >
                    <Icon className="w-4 h-4" />
                    <span>{link.label}</span>
                  </Link>
                );
              })}
            </nav>
          )}
        </div>

        {/* Global Header Actions */}
        <div className="flex items-center space-x-3">
          {/* Single Primary Theme Toggle */}
          <button
            onClick={toggleTheme}
            className="p-2 rounded-xl text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            title="Mavzuni almashtirish"
          >
            {theme === 'dark' ? <Sun className="w-5 h-5 text-amber-400" /> : <Moon className="w-5 h-5" />}
          </button>

          {isAuthenticated && user && (
            <div className="flex items-center space-x-3 pl-3 border-l border-slate-200 dark:border-slate-800">
              <Link
                to="/profile"
                className="flex items-center space-x-2 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:text-brand-500 transition-colors"
              >
                <img
                  src={user.image || `https://ui-avatars.com/api/?name=${user.firstName}+${user.lastName}`}
                  alt={user.username}
                  className="w-8 h-8 rounded-full border-2 border-brand-500 object-cover"
                />
                <span className="hidden sm:inline-block font-bold">{user.firstName}</span>
              </Link>
              <button
                onClick={handleLogout}
                className="p-2 rounded-xl text-error-500 hover:bg-error-50 dark:hover:bg-error-700/20 transition-colors"
                title="Tizimdan chiqish"
              >
                <LogOut className="w-5 h-5" />
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Role Management Modal for Super Admin */}
      <RoleManagementModal
        isOpen={isRoleModalOpen}
        onClose={() => setIsRoleModalOpen(false)}
      />
    </header>
  );
};

