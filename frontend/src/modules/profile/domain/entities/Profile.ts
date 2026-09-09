export interface UserProfile {
  id: number;
  username: string;
  email: string;
  firstName: string;
  lastName: string;
  middleName?: string;
  fullName?: string;
  gender?: string;
  image: string;
  phone?: string;
  role?: string;
  department?: string;
  position?: string;
  telegram_username?: string;
  address?: string;
  birth_date?: string;
  bio?: string;
  company?: {
    name: string;
    title: string;
  };
}

export interface UpdateProfilePayload {
  first_name?: string;
  last_name?: string;
  middle_name?: string;
  phone?: string;
  telegram_username?: string;
  address?: string;
  /** ISO sana: YYYY-MM-DD, tozalash uchun null */
  birth_date?: string | null;
  bio?: string;
  image?: string;
}

export interface ChangePasswordPayload {
  password: string;
  password_confirmation: string;
}

export interface ProfileRecentTicket {
  id: number;
  ticketNo: string;
  subject: string;
  status: string;
  clientRating: number | null;
  createdAt: string;
}

export interface ProfileSummary {
  total: number;
  open: number;
  done: number;
  rejected: number;
  rated: number;
  unrated: number;
  recent: ProfileRecentTicket[];
}
