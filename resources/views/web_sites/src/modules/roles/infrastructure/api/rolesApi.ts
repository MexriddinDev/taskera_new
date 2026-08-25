import { axiosClient } from '@/shared/infrastructure/http/axiosClient';

/**
 * RBAC API qatlami — sahifa komponentlari to'g'ridan-to'g'ri axios chaqiruvi
 * o'rniga shu funksiyalardan foydalanadi (Clean Architecture: infrastructure).
 */

export interface Role {
  id: number;
  name: string;
  guard_name: string;
  description?: string;
  organization_id?: number;
  users_count?: number;
  permission_ids?: number[];
}

export interface Permission {
  id: number;
  name: string;
  guard_name: string;
  module: string;
  description?: string;
}

export const rolesApi = {
  fetchRoles: () => axiosClient.get<{ data: Role[] }>('/roles'),

  fetchPermissions: () => axiosClient.get<{ data: Permission[] }>('/permissions'),

  fetchUsersWithRoles: <T = unknown>() =>
    axiosClient.get<{ data: T[] }>('/users/roles'),

  createRole: (payload: { name: string; guard_name: string; description?: string; permissions?: number[] }) =>
    axiosClient.post('/roles', payload),

  updateRole: (id: number, payload: Record<string, unknown>) =>
    axiosClient.put(`/roles/${id}`, payload),

  deleteRole: (id: number) => axiosClient.delete(`/roles/${id}`),

  createPermission: (payload: { name: string; guard_name?: string; module?: string; description?: string }) =>
    axiosClient.post('/permissions', payload),

  updatePermission: (id: number, payload: Record<string, unknown>) =>
    axiosClient.put(`/permissions/${id}`, payload),

  deletePermission: (id: number) => axiosClient.delete(`/permissions/${id}`),

  assignUserRole: (
    userId: number,
    payload: {
      role_id: number;
      permissions?: number[];
      department_id?: number | null;
      branch_id?: number | null;
      position_id?: number | null;
      team_ids?: number[];
    }
  ) => axiosClient.post(`/users/${userId}/assign-role`, payload),
};
