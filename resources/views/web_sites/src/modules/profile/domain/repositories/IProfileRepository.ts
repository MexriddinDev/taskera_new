import { ProfileSummary, UserProfile } from '../entities/Profile';

export interface IProfileRepository {
  getProfile(id: number): Promise<UserProfile>;
  getSummary(id: number): Promise<ProfileSummary>;
}
