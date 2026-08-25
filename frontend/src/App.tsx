import React, { Suspense, lazy } from 'react';
import { BrowserRouter, Routes, Route, Navigate, Outlet } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProtectedRoute } from './modules/authentication/infrastructure/presentation/components/ProtectedRoute';
import { Navbar } from './shared/presentation/components/Navbar';
import { ErrorBoundary } from './shared/presentation/components/ErrorBoundary';
import { I18nProvider } from './shared/presentation/i18n/i18n';
import { useAuthStore } from './shared/presentation/store/useAuthStore';

// PERFORMANCE: sahifalar lazy yuklanadi — bitta 1.2MB bundle o'rniga
// har sahifa o'z chunk'ini faqat kerak bo'lganda oladi.
const LoginPage = lazy(() => import('./pages/LoginPage').then((m) => ({ default: m.LoginPage })));
const AdAccountCreatePage = lazy(() => import('./pages/AdAccountCreatePage').then((m) => ({ default: m.AdAccountCreatePage })));
const DashboardPage = lazy(() => import('./pages/DashboardPage').then((m) => ({ default: m.DashboardPage })));
const OpenTasksPage = lazy(() => import('./pages/OpenTasksPage').then((m) => ({ default: m.OpenTasksPage })));
const MyTasksPage = lazy(() => import('./pages/MyTasksPage').then((m) => ({ default: m.MyTasksPage })));
const TaskDetailPage = lazy(() => import('./pages/TaskDetailPage').then((m) => ({ default: m.TaskDetailPage })));
const ProfilePage = lazy(() => import('./pages/ProfilePage').then((m) => ({ default: m.ProfilePage })));
const MyRequestsPage = lazy(() => import('./pages/MyRequestsPage').then((m) => ({ default: m.MyRequestsPage })));
const StatsPage = lazy(() => import('./pages/StatsPage').then((m) => ({ default: m.StatsPage })));
const RbacManagementPage = lazy(() => import('./pages/RbacManagementPage').then((m) => ({ default: m.RbacManagementPage })));
const TeamWorkloadPage = lazy(() => import('./pages/TeamWorkloadPage').then((m) => ({ default: m.TeamWorkloadPage })));
const MonitoringPage = lazy(() => import('./pages/MonitoringPage').then((m) => ({ default: m.MonitoringPage })));
const AuditLogsPage = lazy(() => import('./pages/AuditLogsPage').then((m) => ({ default: m.AuditLogsPage })));
const NotFoundPage = lazy(() => import('./pages/NotFoundPage').then((m) => ({ default: m.NotFoundPage })));

import { useCan } from './shared/presentation/hooks/useCan';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

const PageFallback: React.FC = () => (
  <div className="flex items-center justify-center min-h-[50vh]">
    <div className="w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin" aria-label="Loading" />
  </div>
);

const MainLayout: React.FC = () => {
  return (
    <div className="min-h-screen flex flex-col bg-gray-50 dark:bg-gray-900 transition-colors">
      <Navbar />
      <main className="flex-1">
        <Outlet />
      </main>
    </div>
  );
};

const PermissionRouteGuard: React.FC<{ permission?: string | string[]; requireStaff?: boolean }> = ({ permission, requireStaff }) => {
  const { can, user } = useCan();

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  const isSuperAdmin = user?.role === 'Super Admin' || user?.username === 'superadmin';

  if (isSuperAdmin) {
    return <Outlet />;
  }

  if (requireStaff && !user.isStaff && !can(['tickets.view', 'tickets.assign', 'stats.view', 'roles.manage'])) {
    return <Navigate to="/requests" replace />;
  }

  if (permission && !can(permission)) {
    return <Navigate to={user.isStaff ? "/dashboard" : "/requests"} replace />;
  }

  return <Outlet />;
};

const RootRedirect: React.FC = () => {
  const { can, user } = useCan();
  
  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (can(['roles.manage', 'tickets.view', 'stats.view']) || user.isStaff) {
    return <Navigate to="/dashboard" replace />;
  }

  return <Navigate to="/requests" replace />;
};

export const App: React.FC = () => {
  return (
    <I18nProvider>
      <ErrorBoundary>
        <QueryClientProvider client={queryClient}>
          <BrowserRouter basename="/web_sites">
            <Suspense fallback={<PageFallback />}>
              <Routes>
                {/* Public Routes */}
                <Route path="/login" element={<LoginPage />} />
                <Route path="/ad-account" element={<AdAccountCreatePage />} />

              {/* Protected Routes */}
              <Route element={<ProtectedRoute />}>
                {/* Profil — to'liq sahifa (navbar'siz) */}
                <Route path="/profile" element={<ProfilePage />} />

                <Route element={<MainLayout />}>
                  <Route path="/" element={<RootRedirect />} />
                  <Route path="/requests" element={<MyRequestsPage />} />
                  <Route path="/task/:id" element={<TaskDetailPage />} />

                  {/* Staff / Permission Protected Routes */}
                  <Route element={<PermissionRouteGuard requireStaff />}>
                    <Route path="/dashboard" element={<DashboardPage />} />
                    <Route path="/tasks" element={<OpenTasksPage />} />
                    <Route path="/my-tasks" element={<MyTasksPage />} />
                  </Route>

                  <Route element={<PermissionRouteGuard permission={['team_workload.view', 'tickets.view']} />}>
                    <Route path="/team-workload" element={<TeamWorkloadPage />} />
                  </Route>

                  <Route element={<PermissionRouteGuard permission="monitoring.view" />}>
                    <Route path="/monitoring" element={<MonitoringPage />} />
                  </Route>

                  <Route element={<PermissionRouteGuard permission="stats.view" />}>
                    <Route path="/stats" element={<StatsPage />} />
                  </Route>

                  <Route element={<PermissionRouteGuard permission="roles.manage" />}>
                    <Route path="/rbac" element={<RbacManagementPage />} />
                  </Route>

                  <Route element={<PermissionRouteGuard permission="audit.view" />}>
                    <Route path="/audit" element={<AuditLogsPage />} />
                  </Route>
                </Route>
              </Route>

              {/* 404 Route */}
              <Route path="*" element={<NotFoundPage />} />
              </Routes>
            </Suspense>
          </BrowserRouter>
      </QueryClientProvider>
      </ErrorBoundary>
    </I18nProvider>
  );
};

export default App;
