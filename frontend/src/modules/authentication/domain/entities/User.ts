export interface User {
  id: number;
  username: string;
  email: string;
  firstName: string;
  lastName?: string;
  gender?: string;
  image?: string;
  phone?: string;
  role?: string;
  permissions?: string[];
  isStaff?: boolean;
}

export type AuthToken = string;

export interface AuthSession {
  user: User;
  token: AuthToken;
}

export interface LoginCredentials {
  username: string;
  password: string;
}
