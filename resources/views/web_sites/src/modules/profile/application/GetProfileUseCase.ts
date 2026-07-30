import { UserProfile } from '../domain/entities/Profile';
import { IProfileRepository } from '../domain/repositories/IProfileRepository';
import { AppError } from '@/shared/domain/errors/AppError';

export class GetProfileUseCase {
  constructor(private readonly profileRepository: IProfileRepository) {}

  async execute(userId: number): Promise<UserProfile> {
    if (!userId || userId <= 0) {
      throw AppError.badRequest('Invalid user ID');
    }
    return await this.profileRepository.getProfile(userId);
  }
}
