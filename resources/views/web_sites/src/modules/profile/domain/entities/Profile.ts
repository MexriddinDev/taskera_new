export interface UserProfile {
  id: number;
  username: string;
  email: string;
  firstName: string;
  lastName: string;
  gender: string;
  image: string;
  phone?: string;
  company?: {
    name: string;
    title: string;
  };
}
