import { ChangePasswordPayload, ProfileSummary, UpdateProfilePayload, UserProfile } from '../entities/Profile';

export interface IProfileRepository {
  getProfile(id: number): Promise<UserProfile>;
  getSummary(id: number): Promise<ProfileSummary>;
  updateProfile(data: UpdateProfilePayload): Promise<{ message: string; user: UserProfile }>;
  changePassword(data: ChangePasswordPayload): Promise<{ message: string }>;
}
