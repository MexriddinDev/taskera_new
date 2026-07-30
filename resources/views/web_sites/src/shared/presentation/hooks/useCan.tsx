import React from 'react';
import { useAuthStore } from '../store/useAuthStore';

export const useCan = () => {
  const user = useAuthStore((state) => state.user);

  const can = (permission: string | string[]): boolean => {
    if (!user) return false;
    if (user.role === 'Super Admin' || user.username === 'admin' || user.username === 'superadmin') {
      return true;
    }

    const userPermissions = user.permissions || [];

    if (Array.isArray(permission)) {
      return permission.some((p) => userPermissions.includes(p));
    }

    return userPermissions.includes(permission);
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
