import React, { useState, useRef, useEffect } from 'react';
import { UserProfile } from '../../../domain/entities/Profile';
import {
  Mail,
  Phone,
  User as UserIcon,
  Building2,
  CheckCircle2,
  Loader2,
  Camera,
  MapPin,
  Calendar as CalendarIcon,
  FileText,
  Save,
  KeyRound,
  Eye,
  EyeOff,
  ShieldCheck,
} from 'lucide-react';
import { AvatarCropperModal } from './AvatarCropperModal';
import { useUpdateProfile } from '../hooks/useUpdateProfile';
import { useChangePassword } from '../hooks/useChangePassword';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { useT } from '@/shared/presentation/i18n/i18n';
import { RequiredMark } from '@/shared/presentation/components/RequiredMark';

interface ProfileCardProps {
  profile: UserProfile;
}

const defaultAvatar = (name: string) =>
  `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'User')}&size=512&bold=true&background=0D8ABC&color=fff`;

const MIN_BIRTH_DATE = '1900-01-01';

const todayISO = () => {
  const now = new Date();
  const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
  return local.toISOString().slice(0, 10);
};

/**
 * Backenddan kelgan sanani `<input type="date">` kutadigan YYYY-MM-DD ko'rinishiga keltiradi.
 * Eski yozuvlar 12.05.1990 / 12/05/1990 / ISO datetime ko'rinishida bo'lishi mumkin.
 */
const toDateInputValue = (raw?: string | null): string => {
  const value = (raw || '').trim();
  if (!value) return '';

  const iso = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (iso) return `${iso[1]}-${iso[2]}-${iso[3]}`;

  const dotted = value.match(/^(\d{1,2})[./-](\d{1,2})[./-](\d{4})$/);
  if (dotted) {
    const [, day, month, year] = dotted;
    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
  }

  return '';
};

export const ProfileCard: React.FC<ProfileCardProps> = ({ profile }) => {
  const t = useT();
  const toast = useToastStore();
  const updateProfileMutation = useUpdateProfile();
  const changePasswordMutation = useChangePassword();

  // Avatar state
  const [userImage, setUserImage] = useState<string>(profile.image || defaultAvatar(profile.firstName));
  const [cropSource, setCropSource] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Parolni qo'lda o'rnatish (avatar yonidagi bo'sh joy).
  // Parol saytda va AD (pochta) da bir vaqtda o'zgaradi — backend shu tartibda
  // ishlaydi, shuning uchun talablar ham AD siyosatiga tenglashtirilgan.
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showPasswords, setShowPasswords] = useState(false);
  const [passwordError, setPasswordError] = useState('');

  // Personal Information form state
  const [departmentName, setDepartmentName] = useState<string>(
    profile.department || profile.company?.name || "Biznes dasturlarni qo`llab-quvvatlash bo`limi"
  );
  const [fullName, setFullName] = useState<string>(
    profile.fullName || [profile.firstName, profile.lastName, profile.middleName].filter(Boolean).join(' ') || profile.username
  );
  const [phone, setPhone] = useState<string>(profile.phone || '(93) 212-99-05');
  const [email, setEmail] = useState<string>(profile.email || 'yusuf.rahimboyev@xb.uz');
  const [address, setAddress] = useState<string>(profile.address || '');
  const [birthDate, setBirthDate] = useState<string>(toDateInputValue(profile.birth_date));
  const [bio, setBio] = useState<string>(profile.bio || '');

  useEffect(() => {
    if (profile.image) setUserImage(profile.image);
    if (profile.department || profile.company?.name) {
      setDepartmentName(profile.department || profile.company?.name || '');
    }
    const derivedFullName =
      profile.fullName ||
      [profile.firstName, profile.lastName, profile.middleName].filter(Boolean).join(' ') ||
      profile.username;
    if (derivedFullName) setFullName(derivedFullName);
    if (profile.phone) setPhone(profile.phone);
    if (profile.email) setEmail(profile.email);
    if (profile.address) setAddress(profile.address);
    if (profile.birth_date) setBirthDate(toDateInputValue(profile.birth_date));
    if (profile.bio) setBio(profile.bio);
  }, [profile]);

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Rasm avval qirqish oynasida ochiladi — avatarga aynan tanlangan
    // dumaloq qism tushadi. Bir xil faylni qayta tanlash ham ishlashi uchun
    // input qiymati tozalanadi.
    setCropSource(URL.createObjectURL(file));
    e.target.value = '';
  };

  const handleSavePersonalInfo = async (e: React.FormEvent) => {
    e.preventDefault();

    // F.I.Sh va bo'lim kadrlar tizimidan keladi va har AD login'da ustidan
    // qayta yoziladi (AdUserProvisionService), shuning uchun yuborilmaydi —
    // backend ham ularni qabul qilmaydi.
    updateProfileMutation.mutate({
      phone,
      address,
      birth_date: birthDate || null,
      bio,
      image: userImage !== profile.image ? userImage : undefined,
    });
  };

  // Takror parol yozilayotgan paytda tekshiriladi: tugmani bosgunga qadar
  // xato ko'rinib turadi va tugma bloklanadi.
  const passwordMismatch = confirmPassword.length > 0 && newPassword !== confirmPassword;

  /** Parol AD siyosatiga mos keladimi: 8+ belgi, katta/kichik harf va raqam. */
  const passwordRuleError = (value: string): string => {
    if (value.length < 8) return t('profile.passwordTooShort');
    if (!/[A-Z]/.test(value) || !/[a-z]/.test(value) || !/[0-9]/.test(value)) {
      return t('profile.passwordTooWeak');
    }
    return '';
  };

  const handleChangePassword = (e: React.FormEvent) => {
    e.preventDefault();

    if (!newPassword) {
      setPasswordError(t('profile.passwordFillAll'));
      return;
    }

    const ruleError = passwordRuleError(newPassword);
    if (ruleError) {
      setPasswordError(ruleError);
      return;
    }

    if (newPassword !== confirmPassword) {
      setPasswordError(t('profile.passwordMismatch'));
      return;
    }

    setPasswordError('');
    changePasswordMutation.mutate(
      { password: newPassword, password_confirmation: confirmPassword },
      {
        onSuccess: () => {
          setNewPassword('');
          setConfirmPassword('');
        },
      }
    );
  };

  const roleTitle = profile.position || profile.role || 'Developers';

  // Sarlavha yonidagi "to'ldirilganlik" ko'rsatkichi: qaysi maydonlar bo'sh
  // qolganini xodim bir qarashda ko'rsin (avval bu joy bo'sh turardi).
  const trackedFields = [departmentName, fullName, phone, email, address, birthDate, bio];
  const filledCount = trackedFields.filter((value) => (value || '').trim().length > 0).length;
  const completion = Math.round((filledCount / trackedFields.length) * 100);

  return (
    <div className="w-full flex flex-col gap-4 min-h-0 lg:h-full">
      {cropSource && (
        <AvatarCropperModal
          src={cropSource}
          onCancel={() => {
            URL.revokeObjectURL(cropSource);
            setCropSource(null);
          }}
          onConfirm={(dataUrl) => {
            setUserImage(dataUrl);
            URL.revokeObjectURL(cropSource);
            setCropSource(null);
            toast.success(t('profile.cropDone'));
          }}
        />
      )}

      {/* Hidden file input for single avatar upload */}
      <input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        onChange={handleFileSelect}
        className="hidden"
      />

      {/* Top Header Section */}
      <div className="flex-shrink-0 bg-white dark:bg-slate-800/90 rounded-2xl p-5 sm:p-6 shadow-sm border border-slate-200 dark:border-slate-700/80 transition-colors">
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div className="flex items-end gap-6 sm:gap-9 lg:gap-10 min-w-0">
            <div
              className="relative group cursor-pointer flex-shrink-0"
              onClick={() => fileInputRef.current?.click()}
              title={t('profile.chooseFile') || 'Rasmni o\'zgartirish'}
            >
              {/* Dumaloq avatar: rasm doirani to'liq to'ldiradi (object-cover).
                  object-contain bilan rasm karta ichida kichkina bo'lib qolar,
                  atrofida bo'sh joy ko'rinardi. */}
              <img
                src={userImage}
                alt={fullName}
                className="w-28 h-28 sm:w-40 sm:h-40 lg:w-44 lg:h-44 rounded-full border-2 border-brand-500/30 object-cover object-center shadow-sm bg-slate-100 dark:bg-slate-700"
              />
              <div className="absolute inset-0 rounded-full bg-black/45 opacity-0 group-hover:opacity-100 flex flex-col items-center justify-center transition-opacity text-white text-xs font-semibold">
                <Camera className="w-7 h-7 mb-1" />
                <span>{t('profile.image')}</span>
              </div>
            </div>

            <div className="min-w-0 pb-1">
              <div className="flex items-center gap-2 flex-wrap">
                <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 truncate">
                  {fullName}
                </h1>
                <CheckCircle2 className="w-5 h-5 text-emerald-500 flex-shrink-0" />
              </div>
              <p className="text-sm sm:text-base font-bold text-brand-600 dark:text-brand-400 mt-1">
                {roleTitle}
              </p>
              <div className="flex flex-wrap items-center gap-2 mt-2 text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400">
                <span className="inline-flex items-center gap-1">
                  <Building2 className="w-3.5 h-3.5 text-slate-400" />
                  <span>{departmentName}</span>
                </span>
                <span>&bull;</span>
                <span className="inline-flex items-center gap-1">
                  <Mail className="w-3.5 h-3.5 text-slate-400" />
                  <span>{email}</span>
                </span>
              </div>
            </div>
          </div>

          {/* Avatarning o'ng tomonidagi bo'sh joy: rol belgisi va parolni
              qo'lda o'rnatish formasi. Parol saytda ham, AD (pochta) da ham
              bir vaqtda yangilanadi — tasodifiy parol kutish shart emas. */}
          <div className="flex flex-col items-stretch gap-3 w-full sm:w-80 lg:w-96 flex-shrink-0">
            <div className="flex items-center justify-end">
              <span className="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-brand-50 text-brand-700 dark:bg-brand-950/60 dark:text-brand-300 border border-brand-500/20">
                {profile.role || 'Developer'}
              </span>
            </div>

            <form
              onSubmit={handleChangePassword}
              className="rounded-2xl border border-slate-200 dark:border-slate-700/80 bg-slate-50/70 dark:bg-slate-900/40 p-3.5 space-y-2.5"
            >
              <div className="flex items-center gap-2">
                <div className="p-1.5 rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                  <KeyRound className="w-4 h-4" />
                </div>
                <div className="min-w-0 flex-1">
                  <p className="text-xs font-extrabold text-slate-900 dark:text-slate-100 truncate">
                    {t('profile.passwordTitle')}
                  </p>
                  <p className="text-[10px] font-semibold text-slate-500 dark:text-slate-400 truncate">
                    {t('profile.passwordSubtitle')}
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => setShowPasswords((value) => !value)}
                  className="p-1.5 rounded-lg text-slate-400 hover:text-brand-500 hover:bg-white dark:hover:bg-slate-800 transition-colors"
                  title={showPasswords ? t('profile.passwordHide') : t('profile.passwordShow')}
                  aria-label={showPasswords ? t('profile.passwordHide') : t('profile.passwordShow')}
                >
                  {showPasswords ? <Eye className="w-4 h-4" /> : <EyeOff className="w-4 h-4" />}
                </button>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <input
                  type={showPasswords ? 'text' : 'password'}
                  autoComplete="new-password"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  placeholder={t('profile.passwordNew')}
                  className="w-full px-3 py-2 text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all"
                />
                <input
                  type={showPasswords ? 'text' : 'password'}
                  autoComplete="new-password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  placeholder={t('profile.passwordConfirm')}
                  aria-invalid={passwordMismatch}
                  className={`w-full px-3 py-2 text-xs font-semibold rounded-xl border bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 outline-none focus:ring-2 transition-all ${
                    passwordMismatch
                      ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500/20'
                      : 'border-slate-200 dark:border-slate-700 focus:border-brand-500 focus:ring-brand-500/20'
                  }`}
                />
              </div>

              {passwordMismatch || passwordError ? (
                <p role="alert" className="text-[11px] font-bold text-rose-500">
                  {passwordMismatch ? t('profile.passwordMismatch') : passwordError}
                </p>
              ) : (
                <p className="flex items-start gap-1.5 text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                  <ShieldCheck className="w-3.5 h-3.5 flex-shrink-0 mt-px text-emerald-500" />
                  {t('profile.passwordHint')}
                </p>
              )}

              <button
                type="submit"
                disabled={changePasswordMutation.isPending || !newPassword || passwordMismatch}
                className="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-extrabold transition-colors"
              >
                {changePasswordMutation.isPending ? (
                  <Loader2 className="w-4 h-4 animate-spin" />
                ) : (
                  <KeyRound className="w-4 h-4" />
                )}
                {t('profile.passwordSubmit')}
              </button>
            </form>
          </div>
        </div>
      </div>

      {/* Personal Information Form Card — qolgan bo'sh balandlikni egallaydi */}
      <div className="flex-1 min-h-0 flex flex-col bg-white dark:bg-slate-800/90 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700/80 overflow-hidden">
        {/* Sarlavha: chapda ikonka + nom (avatar bilan bir chiziqda), o'ngda
            profil to'ldirilganlik indikatori — ilgari bu joy bo'sh edi. */}
        <div className="px-5 sm:px-6 py-4 border-b border-slate-100 dark:border-slate-700/80 flex flex-col sm:flex-row sm:items-center gap-4">
          <div className="flex items-center gap-3 min-w-0">
            <div className="p-2.5 rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-950/50 dark:text-brand-400 flex-shrink-0">
              <UserIcon className="w-5 h-5" />
            </div>
            <div className="min-w-0">
              <h2 className="text-sm sm:text-base font-extrabold text-slate-900 dark:text-slate-100">
                {t('profile.personalInfo')}
              </h2>
              <p className="text-[11px] text-slate-500 dark:text-slate-400">
                {t('profile.personalInfoSub')}
              </p>
            </div>
          </div>

          <div className="w-full sm:w-64 sm:ml-auto sm:flex-shrink-0">
            <div className="flex items-center justify-between gap-3">
              <span className="text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {t('profile.completeness')}
              </span>
              <span className="text-xs font-extrabold text-brand-600 dark:text-brand-400">
                {completion}%
              </span>
            </div>
            <div className="mt-1.5 h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-700/70 overflow-hidden">
              <div
                className="h-full rounded-full bg-brand-500 transition-all duration-500"
                style={{ width: `${completion}%` }}
              />
            </div>
            <p className="mt-1.5 text-[11px] font-semibold text-slate-400 dark:text-slate-500">
              {t('profile.completenessHint', { filled: filledCount, total: trackedFields.length })}
            </p>
          </div>
        </div>

        <form onSubmit={handleSavePersonalInfo} className="flex flex-1 min-h-0 flex-col gap-4 sm:gap-5 overflow-hidden p-5 sm:p-6">
          {/* Maydonlar o'z ichida skroll qiladi — "Saqlash" tugmasi kartaning
              pastida doim ko'rinib turadi. Ilgari uzun forma tugmani kartadan
              chiqarib yuborardi va u ko'rinmay qolardi. */}
          <div className="flex flex-1 min-h-0 flex-col gap-4 sm:gap-5 overflow-y-auto pr-1">
          {/* Form Fields Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
            {/* Department name * */}
            <div>
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                {t('profile.departmentName')}
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                  <Building2 className="w-4 h-4" />
                </div>
                <input
                  type="text"
                  value={departmentName}
                  readOnly
                  aria-readonly="true"
                  placeholder={t('profile.departmentName')}
                  className="w-full pl-10 pr-3.5 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900/70 text-slate-500 dark:text-slate-400 text-sm font-semibold outline-none cursor-not-allowed"
                />
              </div>
                <p className="mt-1 text-[11px] font-semibold text-slate-400">{t('profile.fromHrHint')}</p>
            </div>

            {/* Full name of the employee * */}
            <div>
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                {t('profile.fullNameEmployee')}
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                  <UserIcon className="w-4 h-4" />
                </div>
                <input
                  type="text"
                  value={fullName}
                  readOnly
                  aria-readonly="true"
                  placeholder={t('profile.fullNameEmployee')}
                  className="w-full pl-10 pr-3.5 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900/70 text-slate-500 dark:text-slate-400 text-sm font-semibold outline-none cursor-not-allowed"
                />
              </div>
                <p className="mt-1 text-[11px] font-semibold text-slate-400">{t('profile.fromHrHint')}</p>
            </div>

            {/* Phone number * */}
            <div>
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                {t('profile.phoneNumber')}<RequiredMark />
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                  <Phone className="w-4 h-4" />
                </div>
                <input
                  type="text"
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder={t('profile.phoneNumber')}
                  required
                  className="w-full pl-10 pr-3.5 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 text-slate-900 dark:text-slate-100 text-sm font-semibold focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all outline-none"
                />
              </div>
            </div>

            {/* Mail information * */}
            <div>
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                {t('profile.mailInfo')}<RequiredMark />
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                  <Mail className="w-4 h-4" />
                </div>
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder={t('profile.mailInfo')}
                  required
                  className="w-full pl-10 pr-3.5 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 text-slate-900 dark:text-slate-100 text-sm font-semibold focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all outline-none"
                />
              </div>
            </div>

            {/* Address * */}
            <div>
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                {t('profile.address')}
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                  <MapPin className="w-4 h-4" />
                </div>
                <input
                  type="text"
                  value={address}
                  onChange={(e) => setAddress(e.target.value)}
                  placeholder={t('profile.address')}
                  className="w-full pl-10 pr-3.5 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 text-slate-900 dark:text-slate-100 text-sm font-semibold focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all outline-none"
                />
              </div>
            </div>

            {/* Birth date * */}
            <div>
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
                {t('profile.birthDate')}
              </label>
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                  <CalendarIcon className="w-4 h-4" />
                </div>
                <input
                  type="date"
                  value={birthDate}
                  onChange={(e) => setBirthDate(e.target.value)}
                  min={MIN_BIRTH_DATE}
                  max={todayISO()}
                  placeholder={t('profile.birthDate')}
                  className="w-full pl-10 pr-3.5 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 text-slate-900 dark:text-slate-100 text-sm font-semibold focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all outline-none [&::-webkit-calendar-picker-indicator]:cursor-pointer dark:[&::-webkit-calendar-picker-indicator]:invert"
                />
              </div>
            </div>
          </div>

          {/* Bio — gridan tashqarida: kartaning qolgan balandligini to'ldiradi,
              shunda sahifa ostida bo'sh joy qolmaydi. */}
          <div className="flex flex-1 min-h-0 flex-col">
            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              {t('profile.bio')}
            </label>
            <div className="relative flex-1 min-h-0">
              <div className="absolute top-3 left-0 pl-3.5 flex items-start pointer-events-none text-slate-400">
                <FileText className="w-4 h-4" />
              </div>
              <textarea
                value={bio}
                onChange={(e) => setBio(e.target.value)}
                placeholder={t('profile.bio')}
                className="w-full h-full min-h-[64px] pl-10 pr-3.5 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 text-slate-900 dark:text-slate-100 text-sm font-semibold focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all outline-none resize-none"
              />
            </div>
          </div>

          </div>

          {/* Save Button */}
          <div className="flex flex-shrink-0 justify-end border-t border-slate-100 dark:border-slate-700/80 pt-4">
            <button
              type="submit"
              disabled={updateProfileMutation.isPending}
              className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-bold shadow-md shadow-brand-500/20 disabled:opacity-50 transition-all cursor-pointer"
            >
              {updateProfileMutation.isPending ? (
                <Loader2 className="w-4 h-4 animate-spin" />
              ) : (
                <Save className="w-4 h-4" />
              )}
              <span>{updateProfileMutation.isPending ? t('profile.saving') : t('profile.save')}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
