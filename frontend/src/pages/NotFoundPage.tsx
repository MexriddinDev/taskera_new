import React from 'react';
import { Link } from 'react-router-dom';
import { Button } from '@/shared/presentation/components/Button';
import { Home } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';

export const NotFoundPage: React.FC = () => {
  const t = useT();
  return (
    <div className="min-h-[80vh] flex items-center justify-center p-4">
      <div className="text-center max-w-md">
        <h1 className="text-8xl font-extrabold text-brand-600 dark:text-brand-400">404</h1>
        <h2 className="mt-4 text-2xl font-bold text-gray-900 dark:text-gray-100">{t('notFound.title')}</h2>
        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400 mb-6">
          {t('notFound.subtitle')}
        </p>
        <Link to="/dashboard">
          <Button leftIcon={<Home className="w-4 h-4" />}>{t('notFound.backHome')}</Button>
        </Link>
      </div>
    </div>
  );
};
