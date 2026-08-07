import React from 'react';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';
import { useProfile } from '@/modules/profile/infrastructure/presentation/hooks/useProfile';
import { useProfileSummary } from '@/modules/profile/infrastructure/presentation/hooks/useProfileSummary';
import { ProfileCard } from '@/modules/profile/infrastructure/presentation/components/ProfileCard';
import { useNavigate, Link } from 'react-router-dom';
import { LogOut, CheckSquare, ArrowLeft } from 'lucide-react';

export const ProfilePage: React.FC = () => {
  const user = useAuthStore((state) => state.user);
  const logout = useAuthStore((state) => state.logout);
  const navigate = useNavigate();

  const userId = user?.id || 1;
  const { data: profile, isLoading } = useProfile(userId);
  const { data: summary } = useProfileSummary(userId);

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 via-brand-50/20 to-gray-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-950 p-4">
        <div className="w-full max-w-6xl bg-white dark:bg-slate-800 rounded-3xl p-8 shadow-sm border border-slate-200 dark:border-slate-700 animate-pulse space-y-4">
          <div className="h-52 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
          <div className="h-8 bg-slate-200 dark:bg-slate-700 rounded w-1/3" />
          <div className="grid grid-cols-4 gap-4">
            {[1, 2, 3, 4].map((n) => (
              <div key={n} className="h-20 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
            ))}
          </div>
        </div>
      </div>
    );
  }

  const userProfile = profile || {
    id: userId,
    username: user?.username || 'user',
    email: user?.email || 'user@taskflow.local',
    firstName: user?.firstName || 'Foydalanuvchi',
    lastName: user?.lastName || '',
    gender: 'male',
    image: user?.image || `https://ui-avatars.com/api/?name=${encodeURIComponent(user?.firstName || 'User')}&size=512&bold=true&background=0D8ABC&color=fff`,
    phone: user?.phone || '+998 90 000-00-00',
    role: user?.role || 'Foydalanuvchi',
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 via-brand-50/20 to-gray-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-950 transition-colors">
      <div className="w-full max-w-6xl mx-auto px-4 sm:px-8 py-6 flex flex-col min-h-screen">
        {/* Full-page header */}
        <header className="flex items-center justify-between mb-8">
          <div className="flex items-center space-x-3">
            <div className="w-11 h-11 rounded-xl bg-gradient-to-br from-brand-600 to-brand-400 flex items-center justify-center text-white shadow-md shadow-brand-500/30">
              <CheckSquare className="w-6 h-6" />
            </div>
            <div>
              <p className="text-lg font-extrabold bg-gradient-to-r from-brand-600 to-brand-400 bg-clip-text text-transparent leading-none">
                TaskFlow
              </p>
              <p className="text-[10px] font-bold text-slate-400 mt-1">Profil</p>
            </div>
          </div>

          <div className="flex items-center space-x-3">
            <Link
              to="/"
              className="inline-flex items-center space-x-2 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold text-xs hover:border-brand-400 transition-all"
            >
              <ArrowLeft className="w-4 h-4" />
              <span>Bosh sahifa</span>
            </Link>
            <button
              onClick={handleLogout}
              className="inline-flex items-center space-x-2 px-5 py-2.5 rounded-xl border border-error-500/30 bg-error-50 dark:bg-error-700/20 text-error-500 font-bold text-xs hover:bg-error-500 hover:text-white transition-all cursor-pointer"
            >
              <LogOut className="w-4 h-4" />
              <span>Tizimdan chiqish</span>
            </button>
          </div>
        </header>

        <ProfileCard profile={userProfile} summary={summary} />
      </div>
    </div>
  );
};
