import { IProfileRepository } from '../../domain/repositories/IProfileRepository';
import { UserProfile } from '../../domain/entities/Profile';
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
      company: data.department
        ? {
            name: data.department,
            title: data.position || 'Xodim',
          }
        : undefined,
    };
  }
}

export const httpProfileRepo = new HttpProfileRepo();
