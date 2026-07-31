import axios, { AxiosError, InternalAxiosRequestConfig } from 'axios';
import { storage } from '../storage/localStorage';
import { AppError } from '../../domain/errors/AppError';

const BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api/v1';

export const axiosClient = axios.create({
  baseURL: BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 15000,
});

// Request Interceptor: Inject Auth Token
axiosClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = storage.get<string>('auth_token');
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Response Interceptor: Format errors into domain AppError
axiosClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string }>) => {
    if (error.response) {
      const status = error.response.status;
      const message = error.response.data?.message || error.message || 'An error occurred';

      if (status === 401) {
        storage.remove('auth_token');
        storage.remove('auth_user');
        return Promise.reject(AppError.unauthorized(message));
      }
      if (status === 404) {
        return Promise.reject(AppError.notFound(message));
      }
      return Promise.reject(new AppError(message, status));
    }

    if (error.request) {
      return Promise.reject(new AppError('Server unavailable or network connection lost', 503));
    }

    return Promise.reject(new AppError(error.message || 'Unexpected HTTP error'));
  }
);
