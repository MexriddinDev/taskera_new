import React from 'react';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';
import { useProfile } from '@/modules/profile/infrastructure/presentation/hooks/useProfile';
import { ProfileCard } from '@/modules/profile/infrastructure/presentation/components/ProfileCard';

export const ProfilePage: React.FC = () => {
  const user = useAuthStore((state) => state.user);

  const userId = user?.id || 1;
  const { data: profile, isLoading } = useProfile(userId);

  if (isLoading && !profile && !user) {
    return (
      <div className="flex min-h-[60vh] items-center justify-center px-4 py-8 sm:px-8 lg:px-12">
        <div className="w-full bg-white dark:bg-slate-800 rounded-3xl p-8 shadow-sm border border-slate-200 dark:border-slate-700 animate-pulse space-y-6">
          <div className="h-28 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div className="h-96 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
            <div className="h-96 bg-slate-200 dark:bg-slate-700 rounded-2xl" />
          </div>
        </div>
      </div>
    );
  }

  const userProfile = profile || {
    id: userId,
    username: user?.username || 'user',
    email: user?.email || 'yusuf.rahimboyev@xb.uz',
    firstName: user?.firstName || 'Rahimboyev',
    lastName: user?.lastName || 'Yusuf',
    middleName: (user as any)?.middleName || "Jahongir o'g'li",
    fullName: (user as any)?.fullName || [user?.firstName, user?.lastName].filter(Boolean).join(' ') || "Rahimboyev Yusuf Jahongir o`g`li",
    gender: 'male',
    image: user?.image || `https://ui-avatars.com/api/?name=${encodeURIComponent(user?.firstName || 'User')}&size=512&bold=true&background=0D8ABC&color=fff`,
    phone: user?.phone || '(93) 212-99-05',
    role: user?.role || 'Developers',
    department: (user as any)?.department || "Biznes dasturlarni qo`llab-quvvatlash bo`limi",
    position: (user as any)?.position || 'Developers',
    telegram_username: (user as any)?.telegram_username || '',
    address: (user as any)?.address || '',
    birth_date: (user as any)?.birth_date || '',
    bio: (user as any)?.bio || '',
  };

  return (
    <div className="w-full px-4 py-4 sm:px-6 lg:px-8 max-w-6xl mx-auto lg:h-[calc(100dvh-4rem)] lg:overflow-hidden">
      <ProfileCard profile={userProfile} />
    </div>
  );
};
