import React, { useState, useRef } from 'react';
import { UserProfile } from '../../../domain/entities/Profile';
import {
  Mail,
  BarChart3,
  Bell,
  Globe,
  HelpCircle,
  Info,
  ChevronRight,
  LogOut,
  Check,
  CheckCircle2,
  Camera,
  Loader2,
} from 'lucide-react';
import { Modal } from '@/shared/presentation/components/Modal';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';

interface ProfileCardProps {
  profile: UserProfile;
  onLogout: () => void;
}

export const ProfileCard: React.FC<ProfileCardProps> = ({ profile, onLogout }) => {
  const [userImage, setUserImage] = useState<string>(profile.image);
  const [isUploading, setIsUploading] = useState(false);
  const [notificationsEnabled, setNotificationsEnabled] = useState(true);
  const [currentLang, setCurrentLang] = useState<'uz' | 'ru' | 'en'>('uz');
  const [isLangModalOpen, setIsLangModalOpen] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const langLabels = {
    uz: "O'zbekcha",
    ru: 'Русский',
    en: 'English',
  };

  const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setIsUploading(true);
    const reader = new FileReader();
    reader.onload = (event) => {
      const base64 = event.target?.result as string;
      if (!base64) return;

      axiosClient.post('/auth/avatar', { image: base64 })
        .then((res) => {
          if (res.data?.user) {
            setUserImage(base64);
            const currentSession = useAuthStore.getState();
            if (currentSession.user) {
              currentSession.setSession({
                token: currentSession.token || '',
                user: { ...currentSession.user, image: base64 },
              });
            }
          }
        })
        .catch(() => {})
        .finally(() => setIsUploading(false));
    };
    reader.readAsDataURL(file);
  };

  return (
    <div className="w-full max-w-5xl mx-auto space-y-6">
      {/* 1. Profile Header */}
      <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200 dark:border-slate-700/80">
        <div className="flex flex-col sm:flex-row items-start sm:items-center space-y-4 sm:space-y-0 sm:space-x-6">
          <div className="relative group cursor-pointer" onClick={() => fileInputRef.current?.click()}>
            <img
              src={userImage}
              alt={profile.username}
              className="w-20 h-20 sm:w-24 sm:h-24 rounded-full border-4 border-brand-500/20 bg-white object-cover shadow-md transition-opacity group-hover:opacity-80"
            />
            <div className="absolute inset-0 rounded-full bg-black/30 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
              {isUploading ? <Loader2 className="w-6 h-6 text-white animate-spin" /> : <Camera className="w-6 h-6 text-white" />}
            </div>
            <div className="absolute bottom-0 right-0 w-6 h-6 bg-brand-500 rounded-full border-2 border-white dark:border-slate-800 flex items-center justify-center shadow">
              <Camera className="w-3 h-3 text-white" />
            </div>
            <input
              type="file"
              ref={fileInputRef}
              onChange={handleImageChange}
              accept="image/*"
              className="hidden"
            />
          </div>

          <div className="space-y-1">
            <div className="flex items-center space-x-2">
              <h2 className="text-2xl font-extrabold text-slate-900 dark:text-slate-100">
                {profile.firstName} {profile.lastName}
              </h2>
              <CheckCircle2 className="w-5 h-5 text-success-500" />
            </div>
            <p className="text-xs font-semibold text-slate-500 dark:text-slate-400">
              Hardware & Software bo'limi · NOK Xizmati
            </p>
            <p className="text-xs font-bold text-brand-500">@{profile.username}</p>

            <div className="flex items-center space-x-2 pt-2 text-xs text-slate-500 dark:text-slate-400">
              <Mail className="w-3.5 h-3.5" />
              <span>{profile.email}</span>
            </div>
          </div>
        </div>
      </div>

      {/* 2. Statistics Card Tile */}
      <Link
        to="/stats"
        className="group bg-white dark:bg-slate-800/90 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-700/80 flex items-center justify-between hover:border-brand-500 transition-all"
      >
        <div className="flex items-center space-x-4">
          <div className="p-3 rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-950/40">
            <BarChart3 className="w-6 h-6" />
          </div>
          <div>
            <h3 className="text-base font-bold text-slate-900 dark:text-slate-100">Ishlar Statistikasi</h3>
            <p className="text-xs text-slate-500 dark:text-slate-400">Barcha zayavkalaringiz bo'yicha tahliliy statistika</p>
          </div>
        </div>
        <ChevronRight className="w-5 h-5 text-slate-400 group-hover:translate-x-1 transition-transform" />
      </Link>

      {/* 3. Settings Section (No Duplicates) */}
      <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-700/80 space-y-4">
        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">Sozlamalar</h4>

        {/* Notifications Tile */}
        <div className="flex items-center justify-between p-3.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
          <div className="flex items-center space-x-3.5">
            <div className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-500">
              <Bell className="w-5 h-5" />
            </div>
            <div>
              <p className="text-sm font-bold text-slate-900 dark:text-slate-100">Notifications</p>
              <p className="text-xs text-slate-400">Push and in-app notifications</p>
            </div>
          </div>
          <button
            onClick={() => setNotificationsEnabled(!notificationsEnabled)}
            className={`w-11 h-6 rounded-full transition-colors relative p-0.5 ${
              notificationsEnabled ? 'bg-brand-500' : 'bg-slate-300 dark:bg-slate-600'
            }`}
          >
            <div
              className={`w-5 h-5 rounded-full bg-white transition-transform ${
                notificationsEnabled ? 'translate-x-5' : 'translate-x-0'
              }`}
            />
          </button>
        </div>

        <div className="h-px bg-slate-100 dark:bg-slate-700 ml-12" />

        {/* Language Tile */}
        <div
          onClick={() => setIsLangModalOpen(true)}
          className="flex items-center justify-between p-3.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/30 cursor-pointer transition-colors"
        >
          <div className="flex items-center space-x-3.5">
            <div className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-500">
              <Globe className="w-5 h-5" />
            </div>
            <div>
              <p className="text-sm font-bold text-slate-900 dark:text-slate-100">Til (Language)</p>
              <p className="text-xs text-slate-400">{langLabels[currentLang]}</p>
            </div>
          </div>
          <ChevronRight className="w-5 h-5 text-slate-400" />
        </div>
      </div>

      {/* 4. Support Section */}
      <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-6 shadow-sm border border-slate-200 dark:border-slate-700/80 space-y-4">
        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">Yordam va Qo'llab-quvvatlash</h4>

        <div className="flex items-center justify-between p-3.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/30 cursor-pointer transition-colors">
          <div className="flex items-center space-x-3.5">
            <div className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-500">
              <HelpCircle className="w-5 h-5" />
            </div>
            <div>
              <p className="text-sm font-bold text-slate-900 dark:text-slate-100">Help Center</p>
              <p className="text-xs text-slate-400">FAQ and support request</p>
            </div>
          </div>
          <ChevronRight className="w-5 h-5 text-slate-400" />
        </div>

        <div className="h-px bg-slate-100 dark:bg-slate-700 ml-12" />

        <div className="flex items-center justify-between p-3.5 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/30 cursor-pointer transition-colors">
          <div className="flex items-center space-x-3.5">
            <div className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-500">
              <Info className="w-5 h-5" />
            </div>
            <div>
              <p className="text-sm font-bold text-slate-900 dark:text-slate-100">About App</p>
              <p className="text-xs text-slate-400">Version 1.0.0 (Production Build)</p>
            </div>
          </div>
          <ChevronRight className="w-5 h-5 text-slate-400" />
        </div>

        {/* 5. Prominent Full-Width Red Logout Button */}
        <div className="pt-4 border-t border-slate-100 dark:border-slate-700">
          <button
            onClick={onLogout}
            className="w-full py-3.5 rounded-xl bg-error-50 dark:bg-error-700/20 text-error-500 font-bold text-sm hover:bg-error-500 hover:text-white transition-all flex items-center justify-center space-x-2 border border-error-500/20"
          >
            <LogOut className="w-5 h-5" />
            <span>Tizimdan chiqish (Log Out)</span>
          </button>
        </div>
      </div>

      {/* Language Selector Modal */}
      <Modal isOpen={isLangModalOpen} onClose={() => setIsLangModalOpen(false)} title="Tilni Tanlang">
        <div className="space-y-2 py-2">
          {(['uz', 'ru', 'en'] as const).map((lang) => (
            <button
              key={lang}
              onClick={() => {
                setCurrentLang(lang);
                setIsLangModalOpen(false);
              }}
              className="w-full p-3.5 rounded-xl flex items-center justify-between hover:bg-slate-100 dark:hover:bg-slate-700 text-sm font-bold text-slate-900 dark:text-slate-100 transition-colors"
            >
              <span>{langLabels[lang]}</span>
              {currentLang === lang && <Check className="w-5 h-5 text-brand-500" />}
            </button>
          ))}
        </div>
      </Modal>
    </div>
  );
};
