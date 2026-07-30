import React from 'react';
import { StatsOverview } from '@/modules/profile/infrastructure/presentation/components/StatsOverview';
import { ArrowLeft } from 'lucide-react';
import { Link } from 'react-router-dom';

export const StatsPage: React.FC = () => {
  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      <div className="flex items-center justify-between mb-6">
        <div>
          <Link
            to="/profile"
            className="inline-flex items-center text-sm font-medium text-gray-500 hover:text-brand-500 transition-colors mb-2"
          >
            <ArrowLeft className="w-4 h-4 mr-1" /> Profilga qaytish
          </Link>
          <h1 className="text-3xl font-extrabold text-gray-900 dark:text-gray-100">Bajarilgan Ishlar Statistikasi</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Zayavkalar ijrosi, tahlil va kategoriyalar bo'yicha ko'rsatkichlar.
          </p>
        </div>
      </div>

      <StatsOverview />
    </div>
  );
};
