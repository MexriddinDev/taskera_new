import { IAuthRepository } from '../../domain/repositories/IAuthRepository';
import { AuthSession, LoginCredentials, User } from '../../domain/entities/User';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';

interface LoginResponse {
  token: string;
  user: User;
}

export class HttpAuthRepo implements IAuthRepository {
  async login(credentials: LoginCredentials): Promise<AuthSession> {
    const response = await axiosClient.post<LoginResponse>('/auth/login', {
      username: credentials.username,
      password: credentials.password,
    });

    return {
      user: response.data.user,
      token: response.data.token,
    };
  }

  async getCurrentUser(_id: number): Promise<User> {
    const response = await axiosClient.get<{ user: User }>('/auth/me');
    return response.data.user;
  }

  async logout(): Promise<void> {
    await axiosClient.post('/auth/logout');
  }
}

export const httpAuthRepo = new HttpAuthRepo();
