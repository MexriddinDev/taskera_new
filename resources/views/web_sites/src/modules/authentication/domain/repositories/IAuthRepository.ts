import { AuthSession, LoginCredentials, User } from '../entities/User';

export interface IAuthRepository {
  login(credentials: LoginCredentials): Promise<AuthSession>;
  getCurrentUser(id: number): Promise<User>;
  logout(): Promise<void>;
}
