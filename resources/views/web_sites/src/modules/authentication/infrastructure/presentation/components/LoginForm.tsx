import React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Lock, User as UserIcon, AlertCircle } from 'lucide-react';
import { Input } from '@/shared/presentation/components/Input';
import { Button } from '@/shared/presentation/components/Button';
import { useLogin } from '../hooks/useLogin';

const loginSchema = z.object({
  username: z.string().min(1, 'Username is required'),
  password: z.string().min(4, 'Password must be at least 4 characters'),
});

type LoginSchema = z.infer<typeof loginSchema>;

export const LoginForm: React.FC = () => {
  const { mutate: login, isPending, error } = useLogin();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginSchema>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      username: '',
      password: '',
    },
  });

  const onSubmit = (data: LoginSchema) => {
    login(data);
  };



  return (
    <div className="w-full max-w-md p-8 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 transition-all">
      <div className="mb-8 text-center">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Welcome back</h1>
        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
          Sign in to your TaskFlow account to manage your workspace.
        </p>
      </div>

      {error && (
        <div className="mb-6 p-4 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 flex items-start space-x-3">
          <AlertCircle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
          <div className="text-sm text-red-700 dark:text-red-300">
            {error.message || 'Authentication failed. Please check your credentials.'}
          </div>
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-5">
        <Input
          label="Username"
          placeholder="username"
          icon={<UserIcon className="w-4 h-4" />}
          error={errors.username?.message}
          {...register('username')}
        />

        <Input
          label="Password"
          type="password"
          placeholder="••••••••"
          icon={<Lock className="w-4 h-4" />}
          error={errors.password?.message}
          {...register('password')}
        />

        <Button type="submit" className="w-full py-3 mt-2" isLoading={isPending}>
          Sign In
        </Button>
      </form>
    </div>
  );
};
