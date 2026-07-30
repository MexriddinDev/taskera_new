import React from 'react';
import { Navigate } from 'react-router-dom';
import { LoginForm } from '@/modules/authentication/infrastructure/presentation/components/LoginForm';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';
import { CheckSquare } from 'lucide-react';

export const LoginPage: React.FC = () => {
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }

  return (
    <div className="min-h-screen flex flex-col justify-center items-center p-4 bg-gradient-to-br from-gray-50 via-brand-50/20 to-gray-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-950">
      <div className="flex items-center space-x-3 mb-8">
        <div className="w-12 h-12 rounded-xl bg-brand-600 flex items-center justify-center text-white shadow-lg">
          <CheckSquare className="w-7 h-7" />
        </div>
        <span className="text-3xl font-extrabold bg-gradient-to-r from-brand-600 to-brand-400 bg-clip-text text-transparent">
          TaskFlow
        </span>
      </div>

      <LoginForm />
    </div>
  );
};
