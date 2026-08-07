import React, { useState, useRef } from 'react';
import { ProfileSummary, UserProfile } from '../../../domain/entities/Profile';
import {
  Mail,
  BarChart3,
  HelpCircle,
  ChevronRight,
  CheckCircle2,
  Camera,
  Loader2,
  Building2,
  Briefcase,
  Phone,
  User as UserIcon,
  Shield,
  CheckSquare,
  Clock,
  Inbox,
  ClipboardList,
  Star,
  CalendarDays,
} from 'lucide-react';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';

interface ProfileCardProps {
  profile: UserProfile;
  summary?: ProfileSummary;
}

const defaultAvatar = (name: string) =>
  `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'User')}&size=512&bold=true&background=0D8ABC&color=fff`;

const getStatusBadge = (status: string, clientRating?: number | null) => {
  if (status === 'done' && clientRating) {
    return { bg: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300', label: 'Yopildi (Baholangan)' };
  }
  switch (status) {
    case 'done':
      return { bg: 'bg-success-50 text-success-600 dark:bg-success-700/20 border border-success-500/20', label: 'Bajarildi' };
    case 'in_progress':
      return { bg: 'bg-warning-50 text-warning-600 dark:bg-warning-700/20 border border-warning-500/20', label: 'Jarayonda' };
    case 'rejected':
      return { bg: 'bg-error-50 text-error-600 dark:bg-error-700/20 border border-error-500/20', label: 'Reject bo\'lgan' };
    default:
      return { bg: 'bg-brand-50 text-brand-600 dark:bg-brand-950/40 border border-brand-500/20', label: 'Yangi / Ochiq' };
  }
};

export const ProfileCard: React.FC<ProfileCardProps> = ({ profile, summary }) => {
  const [userImage, setUserImage] = useState<string>(profile.image || defaultAvatar(profile.firstName));
  const [isUploading, setIsUploading] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const fullName = [profile.firstName, profile.lastName].filter(Boolean).join(' ') || profile.username;
  const department = profile.company?.name || '—';
  const position = profile.company?.title || '—';

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

  const infoRows = [
    { icon: UserIcon, label: "F.I.Sh.", value: fullName },
    { icon: Shield, label: 'Rol', value: profile.role ?? '—' },
    { icon: Mail, label: 'Email', value: profile.email },
    { icon: Phone, label: 'Telefon', value: profile.phone || '—' },
    { icon: Building2, label: 'Bo\'lim', value: department },
    { icon: Briefcase, label: 'Lavozim', value: position },
  ];

  const stats = [
    { icon: ClipboardList, label: 'Jami zayavka', value: summary?.total ?? 0, color: 'bg-brand-50 text-brand-500 dark:bg-brand-950/40 dark:text-brand-400' },
    { icon: Clock, label: 'Jarayonda', value: summary?.open ?? 0, color: 'bg-warning-50 text-warning-500 dark:bg-warning-700/20 dark:text-warning-400' },
    { icon: CheckCircle2, label: 'Bajarilgan', value: summary?.done ?? 0, color: 'bg-success-50 text-success-500 dark:bg-success-700/20 dark:text-success-400' },
    { icon: Star, label: 'Baholangan', value: summary?.rated ?? 0, color: 'bg-amber-50 text-amber-500 dark:bg-amber-700/20 dark:text-amber-400' },
  ];

  const recentTickets = summary?.recent ?? [];

  return (
    <div className="w-full flex flex-col space-y-6">
      {/* HERO: web-uslubdagi katta banner */}
      <div className="bg-white dark:bg-slate-800/90 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-700/80 overflow-hidden">
        <div className="relative h-44 sm:h-52 bg-gradient-to-r from-brand-700 via-brand-500 to-brand-400 overflow-hidden">
          <div className="absolute -top-10 -right-10 w-48 h-48 rounded-full bg-white/10 blur-2xl" />
          <div className="absolute -bottom-16 left-1/4 w-56 h-56 rounded-full bg-white/10 blur-3xl" />
          <div className="absolute top-6 right-8 hidden sm:flex items-center space-x-2 text-white/80">
            <CalendarDays className="w-4 h-4" />
            <span className="text-xs font-bold">
              {new Date().toLocaleDateString('uz-UZ', { day: 'numeric', month: 'long', year: 'numeric' })}
            </span>
          </div>
        </div>

        <div className="px-6 sm:px-10 pb-8">
          <div className="flex flex-col sm:flex-row items-start gap-6 -mt-16">
            {/* Avatar */}
            <div
              className="relative group cursor-pointer flex-shrink-0"
              onClick={() => fileInputRef.current?.click()}
              title="Rasmni yangilash"
            >
              <img
                src={userImage}
                alt={profile.username}
                className="w-32 h-32 rounded-full border-4 border-white dark:border-slate-800 bg-white object-cover shadow-xl transition-opacity group-hover:opacity-90"
              />
              <div className="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                {isUploading ? <Loader2 className="w-7 h-7 text-white animate-spin" /> : <Camera className="w-7 h-7 text-white" />}
              </div>
              <div className="absolute bottom-1.5 right-1.5 w-8 h-8 bg-brand-500 rounded-full border-2 border-white dark:border-slate-800 flex items-center justify-center shadow-md">
                <Camera className="w-4 h-4 text-white" />
              </div>
              <input
                type="file"
                ref={fileInputRef}
                onChange={handleImageChange}
                accept="image/*"
                className="hidden"
              />
            </div>

            {/* Name & meta */}
            <div className="min-w-0 pt-16">
              <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h2 className="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100">
                  {fullName}
                </h2>
                <CheckCircle2 className="w-6 h-6 text-success-500 flex-shrink-0" />
              </div>
              <p className="text-sm font-bold text-brand-500 mt-1">@{profile.username}</p>

              <div className="flex flex-wrap items-center gap-2 mt-4">
                <span className="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-full bg-brand-50 text-brand-600 dark:bg-brand-950/40 dark:text-brand-300 text-xs font-bold border border-brand-500/20">
                  <Building2 className="w-3.5 h-3.5" />
                  <span>{department}</span>
                </span>
                <span className="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-300 text-xs font-bold">
                  <Briefcase className="w-3.5 h-3.5" />
                  <span>{position}</span>
                </span>
                <span className="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-full bg-slate-100 text-slate-600 dark:bg-slate-700/40 dark:text-slate-300 text-xs font-bold">
                  <Mail className="w-3.5 h-3.5" />
                  <span className="truncate max-w-[240px]">{profile.email}</span>
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* KPI stat strip (web-uslub) */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        {stats.map((s) => (
          <div
            key={s.label}
            className="group bg-white dark:bg-slate-800/90 rounded-2xl p-5 shadow-sm border border-slate-200 dark:border-slate-700/80 flex items-center space-x-4 hover:border-brand-400 hover:shadow-md transition-all"
          >
            <div className={`p-3 rounded-2xl ${s.color} group-hover:scale-110 transition-transform`}>
              <s.icon className="w-6 h-6" />
            </div>
            <div>
              <p className="text-2xl font-extrabold text-slate-900 dark:text-slate-100 leading-none">{s.value}</p>
              <p className="text-[11px] font-bold text-slate-400 mt-1.5">{s.label}</p>
            </div>
          </div>
        ))}
      </div>

      {/* Main content grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {/* Personal Info (web) */}
        <div className="lg:col-span-2 bg-white dark:bg-slate-800/90 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-700/80 overflow-hidden">
          <div className="px-6 sm:px-8 py-5 border-b border-slate-100 dark:border-slate-700">
            <h4 className="text-base font-bold text-slate-900 dark:text-slate-100">Shaxsiy Ma'lumotlar</h4>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Akauntingiz bo'yicha asosiy ma'lumotlar
            </p>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 divide-y divide-slate-100 dark:divide-slate-700/60">
            {infoRows.map((row) => (
              <div key={row.label} className="flex items-center space-x-4 px-6 sm:px-8 py-4">
                <div className="p-2.5 rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/40 flex-shrink-0">
                  <row.icon className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">{row.label}</p>
                  <p className="text-sm font-bold text-slate-900 dark:text-slate-100 truncate">{row.value}</p>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Right sidebar: shortcuts */}
        <div className="space-y-6">
          <Link
            to="/stats"
            className="group bg-white dark:bg-slate-800/90 rounded-3xl p-6 shadow-sm border border-slate-200 dark:border-slate-700/80 flex items-center justify-between hover:border-brand-500 hover:shadow-md transition-all"
          >
            <div className="flex items-center space-x-4">
              <div className="p-3 rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-950/40 group-hover:scale-110 transition-transform">
                <BarChart3 className="w-6 h-6" />
              </div>
              <div>
                <h3 className="text-base font-bold text-slate-900 dark:text-slate-100">Ishlar Statistikasi</h3>
                <p className="text-xs text-slate-500 dark:text-slate-400">
                  {summary ? `${summary.total} zayavka · ${summary.done} bajarilgan` : 'Zayavkalar bo\'yicha tahliliy ma\'lumotlar'}
                </p>
              </div>
            </div>
            <ChevronRight className="w-5 h-5 text-slate-400 group-hover:translate-x-1 transition-transform" />
          </Link>

          <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 shadow-sm border border-slate-200 dark:border-slate-700/80">
            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400">Yordam va Qo'llab-quvvatlash</h4>
            <div className="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700/30 cursor-pointer transition-colors mt-2">
              <div className="flex items-center space-x-3">
                <HelpCircle className="w-5 h-5 text-slate-400" />
                <span className="text-sm font-bold text-slate-900 dark:text-slate-100">Help Center</span>
              </div>
              <ChevronRight className="w-5 h-5 text-slate-400" />
            </div>
          </div>
        </div>
      </div>

      {/* Recent Tickets (full width) */}
      <div className="bg-white dark:bg-slate-800/90 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-700/80 overflow-hidden">
        <div className="px-6 sm:px-8 py-5 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
          <div>
            <h4 className="text-base font-bold text-slate-900 dark:text-slate-100">So'nggi zayavkalar</h4>
            <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Yuborgan so'nggi zayavkalaringiz</p>
          </div>
          <Link
            to="/my-requests"
            className="inline-flex items-center space-x-1 text-xs font-bold text-brand-500 hover:text-brand-600 transition-colors"
          >
            <span>Barchasi</span>
            <ChevronRight className="w-4 h-4" />
          </Link>
        </div>

        {recentTickets.length === 0 ? (
          <div className="px-6 py-10 text-center">
            <Inbox className="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto" />
            <p className="mt-3 text-sm font-bold text-slate-500 dark:text-slate-400">Zayavkalar topilmadi</p>
            <p className="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Hali zayavka yubormagansiz</p>
          </div>
        ) : (
          <ul className="divide-y divide-slate-100 dark:divide-slate-700/60">
            {recentTickets.map((t) => {
              const statusInfo = getStatusBadge(t.status, t.clientRating);
              return (
                <li key={t.id}>
                  <Link
                    to={`/tickets/${t.id}`}
                    className="flex items-center gap-4 px-6 sm:px-8 py-4 hover:bg-slate-50 dark:hover:bg-slate-700/20 transition-colors group"
                  >
                    <div className="min-w-0 flex-1">
                      <p className="text-sm font-bold text-slate-900 dark:text-slate-100 truncate">
                        {t.ticketNo}
                        <span className="font-medium text-slate-500 dark:text-slate-400"> · {t.subject}</span>
                      </p>
                      <p className="text-[11px] font-bold text-slate-400 mt-0.5">{t.createdAt}</p>
                    </div>
                    {t.clientRating ? (
                      <div className="flex items-center space-x-1">
                        {Array.from({ length: 5 }).map((_, i) => (
                          <Star
                            key={i}
                            className={`w-4 h-4 ${i < (t.clientRating ?? 0) ? 'text-amber-400 fill-amber-400' : 'text-slate-300 dark:text-slate-600'}`}
                          />
                        ))}
                      </div>
                    ) : null}
                    <span className={`flex-shrink-0 px-3 py-1.5 rounded-lg text-[11px] font-bold ${statusInfo.bg}`}>
                      {statusInfo.label}
                    </span>
                    <ChevronRight className="w-4 h-4 text-slate-300 dark:text-slate-600 group-hover:translate-x-0.5 transition-transform flex-shrink-0" />
                  </Link>
                </li>
              );
            })}
          </ul>
        )}
      </div>

      {/* Footer */}
      <footer className="pt-6">
        <div className="relative flex items-center justify-center gap-6">
          <div className="h-px flex-1 max-w-[160px] bg-gradient-to-r from-transparent to-slate-200 dark:to-slate-700" />
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-600 to-brand-400 flex items-center justify-center text-white shadow-md shadow-brand-500/30 rotate-3">
            <CheckSquare className="w-5 h-5" />
          </div>
          <div className="h-px flex-1 max-w-[160px] bg-gradient-to-l from-transparent to-slate-200 dark:to-slate-700" />
        </div>
        <p className="mt-4 text-center text-xs font-bold text-slate-500 dark:text-slate-400">
          Bu tizim <span className="text-brand-500">Axborot Tizimlari boshqarmasi</span> tomonidan ishlab chiqildi
        </p>
        <p className="mt-1 text-center text-[10px] font-medium text-slate-400 dark:text-slate-600">
          © {new Date().getFullYear()} TaskFlow · Barcha huquqlar himoyalangan
        </p>
      </footer>
    </div>
  );
};
