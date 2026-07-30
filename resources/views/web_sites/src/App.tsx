import React from 'react';
import { BrowserRouter, Routes, Route, Navigate, Outlet } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProtectedRoute } from './modules/authentication/infrastructure/presentation/components/ProtectedRoute';
import { Navbar } from './shared/presentation/components/Navbar';
import { ErrorBoundary } from './shared/presentation/components/ErrorBoundary';
import { useAuthStore } from './shared/presentation/store/useAuthStore';

import { LoginPage } from './pages/LoginPage';
import { DashboardPage } from './pages/DashboardPage';
import { OpenTasksPage } from './pages/OpenTasksPage';
import { MyTasksPage } from './pages/MyTasksPage';
import { TaskDetailPage } from './pages/TaskDetailPage';
import { ProfilePage } from './pages/ProfilePage';
import { MyRequestsPage } from './pages/MyRequestsPage';
import { StatsPage } from './pages/StatsPage';
import { RbacManagementPage } from './pages/RbacManagementPage';
import { NotFoundPage } from './pages/NotFoundPage';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      refetchOnWindowFocus: false,
    },
  },
});

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

const StaffRouteGuard: React.FC = () => {
  const user = useAuthStore((state) => state.user);
  if (!user?.isStaff) {
    return <Navigate to="/requests" replace />;
  }
  return <Outlet />;
};

const RootRedirect: React.FC = () => {
  const user = useAuthStore((state) => state.user);
  return <Navigate to={user?.isStaff ? "/dashboard" : "/requests"} replace />;
};

export const App: React.FC = () => {
  return (
    <ErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <BrowserRouter basename="/web_sites">
          <Routes>
            {/* Public Routes */}
            <Route path="/login" element={<LoginPage />} />

            {/* Protected Routes */}
            <Route element={<ProtectedRoute />}>
              <Route element={<MainLayout />}>
                <Route path="/" element={<RootRedirect />} />
                <Route path="/requests" element={<MyRequestsPage />} />
                <Route path="/task/:id" element={<TaskDetailPage />} />
                <Route path="/profile" element={<ProfilePage />} />

                {/* Staff Only Routes */}
                <Route element={<StaffRouteGuard />}>
                  <Route path="/dashboard" element={<DashboardPage />} />
                  <Route path="/tasks" element={<OpenTasksPage />} />
                  <Route path="/my-tasks" element={<MyTasksPage />} />
                  <Route path="/stats" element={<StatsPage />} />
                  <Route path="/rbac" element={<RbacManagementPage />} />
                </Route>
              </Route>
            </Route>

            {/* 404 Route */}
            <Route path="*" element={<NotFoundPage />} />
          </Routes>
        </BrowserRouter>
      </QueryClientProvider>
    </ErrorBoundary>
  );
};

export default App;
