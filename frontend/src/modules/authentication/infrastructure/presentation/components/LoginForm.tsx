import React, { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Lock, Mail, AlertCircle, ArrowRight } from 'lucide-react';
import { Input } from '@/shared/presentation/components/Input';
import { Button } from '@/shared/presentation/components/Button';
import { useLogin } from '../hooks/useLogin';
import { useT } from '@/shared/presentation/i18n/i18n';

type LoginSchema = z.infer<typeof loginSchema>;

// Xato matnlari sxemaga TARJIMA QILINGAN holda emas, KALIT sifatida yoziladi
// va render paytida tarjima qilinadi. Ilgari sxema `t` bilan qurilardi:
// react-hook-form resolverni birinchi renderda eslab qolgani uchun til
// almashtirilsa ham xato matni eski tilda qolib ketardi.
const loginSchema = z.object({
  username: z.string().min(1, 'login.usernameRequired'),
  password: z.string().min(1, 'login.passwordRequired'),
});

export const LoginForm: React.FC = () => {
  const t = useT();
  const { mutate: login, isPending, error } = useLogin();

  const {
    register,
    handleSubmit,
    resetField,
    formState: { errors },
  } = useForm<LoginSchema>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      username: '',
      password: '',
    },
  });

  // Login xato bo'lsa parol maydonini tozalaymiz — qaytadan yozadi
  useEffect(() => {
    if (error) {
      resetField('password');
    }
  }, [error, resetField]);

  const onSubmit = (data: LoginSchema) => {
    login(data);
  };

  return (
    <div className="w-full p-5 bg-white/95 dark:bg-slate-900/70 rounded-3xl shadow-2xl shadow-slate-300/40 dark:shadow-black/40 border border-slate-200 dark:border-brand-500/25 backdrop-blur-sm transition-all">
      <div className="mb-3.5">
        <h1 className="flex items-center gap-2.5 text-xl sm:text-2xl font-bold text-slate-900 dark:text-white">
          <span aria-hidden="true">👋</span>
          <span>{t('login.welcomeBack')}</span>
        </h1>
        <p className="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
          {t('login.subtitle')}
        </p>
      </div>

      {error && (
        <div className="mb-4 p-3.5 rounded-2xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 flex items-start space-x-3">
          <AlertCircle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
          <div className="text-sm text-red-700 dark:text-red-300">
            {error.message || t('login.authFailed')}
          </div>
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-2.5">
        <Input
          label={t('login.username')}
          placeholder="ism.familiya@xb.uz"
          icon={<Mail className="w-4 h-4" />}
          compact
          error={errors.username?.message && t(errors.username.message)}
          {...register('username')}
        />

        <Input
          label={t('login.password')}
          type="password"
          placeholder="••••••••"
          icon={<Lock className="w-4 h-4" />}
          compact
          error={errors.password?.message && t(errors.password.message)}
          {...register('password')}
        />

        {/* Parolni unutgan xodim AD parolini shu yerdan yangilaydi.
            mode=reset — /ad-account sahifasi sarlavhasini "yaratish" emas,
            "parolni almashtirish" ko'rinishida ochadi. */}
        <div className="flex justify-end -mt-1.5">
          <Link
            to="/ad-account?mode=reset"
            className="text-xs font-semibold text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 hover:underline transition-colors"
          >
            {t('login.forgotPassword')}
          </Link>
        </div>

        {/* Strelka mutlaq joylashuvda — matn tugma markazida qoladi va
            yuklanish spinneri chiqqanda ham joyi siljimaydi. */}
        <Button
          type="submit"
          className="relative w-full py-2.5 rounded-2xl text-sm bg-gradient-to-r from-brand-600 to-blue-500 hover:from-brand-500 hover:to-blue-400 shadow-lg shadow-brand-500/30"
          isLoading={isPending}
        >
          {t('login.signIn')}
          <ArrowRight className="w-4 h-4 absolute right-5 top-1/2 -translate-y-1/2" />
        </Button>
      </form>
    </div>
  );
};
