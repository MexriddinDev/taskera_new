import React from 'react';
import { useAuthStore } from '../store/useAuthStore';

export const useCan = () => {
  const user = useAuthStore((state) => state.user);

  const can = (permission: string | string[]): boolean => {
    if (!user) return false;
    if (user.role === 'Super Admin' || user.username === 'superadmin') {
      return true;
    }

    const rawPerms = user.permissions || (user as any)?.data?.permissions || [];
    const userPermissions = (Array.isArray(rawPerms) ? rawPerms : []).map((p: any) => String(p).toLowerCase());

    if (Array.isArray(permission)) {
      return permission.some((p) => userPermissions.includes(p.toLowerCase()));
    }

    return userPermissions.includes(permission.toLowerCase());
  };

  return { can, user };
};

interface CanProps {
  permission: string | string[];
  fallback?: React.ReactNode;
  children: React.ReactNode;
}

export const Can: React.FC<CanProps> = ({ permission, fallback = null, children }) => {
  const { can } = useCan();

  if (can(permission)) {
    return <>{children}</>;
  }

  return <>{fallback}</>;
};
