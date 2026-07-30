import { UserProfile } from '../entities/Profile';

export interface IProfileRepository {
  getProfile(id: number): Promise<UserProfile>;
}
