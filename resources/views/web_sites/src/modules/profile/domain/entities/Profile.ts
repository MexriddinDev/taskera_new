export interface UserProfile {
  id: number;
  username: string;
  email: string;
  firstName: string;
  lastName: string;
  gender: string;
  image: string;
  phone?: string;
  role?: string;
  company?: {
    name: string;
    title: string;
  };
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
