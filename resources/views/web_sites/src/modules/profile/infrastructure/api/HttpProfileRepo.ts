import { IProfileRepository } from '../../domain/repositories/IProfileRepository';
import { ProfileSummary, UserProfile } from '../../domain/entities/Profile';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';

interface BackendUserResponse {
  id: number;
  username: string;
  email: string;
  firstName: string;
  lastName: string;
  image: string | null;
  phone?: string;
  department?: string;
  position?: string;
  role?: string;
}

export class HttpProfileRepo implements IProfileRepository {
  async getProfile(id: number): Promise<UserProfile> {
    const response = await axiosClient.get<BackendUserResponse>(`/users/${id}`);

    const data = response.data;

    return {
      id: data.id,
      username: data.username,
      email: data.email,
      firstName: data.firstName,
      lastName: data.lastName,
      gender: 'unknown',
      image: data.image || '',
      phone: data.phone,
      role: data.role,
      company: data.department
        ? {
            name: data.department,
            title: data.position || 'Xodim',
          }
        : undefined,
    };
  }

  async getSummary(id: number): Promise<ProfileSummary> {
    const response = await axiosClient.get<ProfileSummary>(`/users/${id}/summary`);
    return response.data;
  }
}

export const httpProfileRepo = new HttpProfileRepo();
