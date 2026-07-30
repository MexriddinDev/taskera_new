import React from 'react';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';
import { useProfile } from '@/modules/profile/infrastructure/presentation/hooks/useProfile';
import { ProfileCard } from '@/modules/profile/infrastructure/presentation/components/ProfileCard';
import { useNavigate } from 'react-router-dom';

export const ProfilePage: React.FC = () => {
  const user = useAuthStore((state) => state.user);
  const logout = useAuthStore((state) => state.logout);
  const navigate = useNavigate();

  const userId = user?.id || 1;
  const { data: profile, isLoading } = useProfile(userId);

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  if (isLoading) {
    return (
      <div className="w-full max-w-5xl mx-auto px-4 sm:px-8 lg:px-12 py-12">
        <div className="bg-white dark:bg-slate-800 rounded-3xl p-8 shadow-sm border border-slate-200 dark:border-slate-700 animate-pulse space-y-4">
          <div className="h-28 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
          <div className="h-6 bg-slate-200 dark:bg-slate-700 rounded w-1/3" />
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
    image: user?.image || `https://ui-avatars.com/api/?name=${user?.firstName || 'User'}`,
    phone: user?.phone || '+998 90 000-00-00',
  };

  return (
    <div className="w-full max-w-5xl mx-auto px-4 sm:px-8 lg:px-12 py-8">
      <h1 className="text-2xl font-extrabold text-slate-900 dark:text-slate-100 mb-6">Akaunt Ma'lumotlari</h1>
      <ProfileCard profile={userProfile} onLogout={handleLogout} />
    </div>
  );
};
