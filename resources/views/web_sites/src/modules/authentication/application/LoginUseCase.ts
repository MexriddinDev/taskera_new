import { AuthSession, LoginCredentials } from '../domain/entities/User';
import { IAuthRepository } from '../domain/repositories/IAuthRepository';
import { AppError } from '@/shared/domain/errors/AppError';

export class LoginUseCase {
  constructor(private readonly authRepository: IAuthRepository) {}

  async execute(credentials: LoginCredentials): Promise<AuthSession> {
    if (!credentials.username || !credentials.username.trim()) {
      throw AppError.badRequest('Username is required');
    }
    if (!credentials.password || credentials.password.length < 4) {
      throw AppError.badRequest('Password must be at least 4 characters');
    }

    return await this.authRepository.login({
      username: credentials.username.trim(),
      password: credentials.password,
    });
  }
}
