import { IProfileRepository } from '../../domain/repositories/IProfileRepository';
import { ChangePasswordPayload, ProfileSummary, UpdateProfilePayload, UserProfile } from '../../domain/entities/Profile';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';

interface BackendUserResponse {
  id: number;
  username: string;
  email: string;
  firstName: string;
  lastName: string;
  middleName?: string;
  fullName?: string;
  image: string | null;
  phone?: string;
  department?: string;
  position?: string;
  role?: string;
  telegram_username?: string;
  address?: string;
  birth_date?: string;
  bio?: string;
}

const mapBackendToUserProfile = (data: BackendUserResponse): UserProfile => ({
  id: data.id,
  username: data.username,
  email: data.email,
  firstName: data.firstName,
  lastName: data.lastName,
  middleName: data.middleName,
  fullName: data.fullName || trimName(`${data.firstName} ${data.lastName} ${data.middleName || ''}`) || data.username,
  gender: 'unknown',
  image: data.image || '',
  phone: data.phone,
  role: data.role,
  department: data.department,
  position: data.position,
  telegram_username: data.telegram_username,
  address: data.address,
  birth_date: data.birth_date,
  bio: data.bio,
  company: data.department
    ? {
        name: data.department,
        title: data.position || 'Xodim',
      }
    : undefined,
});

const trimName = (str: string) => str.replace(/\s+/g, ' ').trim();

export class HttpProfileRepo implements IProfileRepository {
  async getProfile(id: number): Promise<UserProfile> {
    const response = await axiosClient.get<any>(`/users/${id}`);
    const data = response.data?.data || response.data;
    return mapBackendToUserProfile(data);
  }

  async getSummary(id: number): Promise<ProfileSummary> {
    const response = await axiosClient.get<ProfileSummary>(`/users/${id}/summary`);
    return response.data;
  }

  async updateProfile(payload: UpdateProfilePayload): Promise<{ message: string; user: UserProfile }> {
    const response = await axiosClient.put<any>('/profile', payload);
    const rawUser = response.data?.user?.data || response.data?.user || response.data;
    return {
      message: response.data?.message || 'Profil saqlandi',
      user: mapBackendToUserProfile(rawUser),
    };
  }

  async changePassword(payload: ChangePasswordPayload): Promise<{ message: string }> {
    const response = await axiosClient.post<{ message: string }>('/auth/change-password', payload);
    return response.data;
  }
}

export const httpProfileRepo = new HttpProfileRepo();
