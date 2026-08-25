import { User } from '../domain/entities/User';
import { IAuthRepository } from '../domain/repositories/IAuthRepository';

export class GetCurrentUserUseCase {
  constructor(private readonly authRepository: IAuthRepository) {}

  async execute(userId: number): Promise<User> {
    return await this.authRepository.getCurrentUser(userId);
  }
}
