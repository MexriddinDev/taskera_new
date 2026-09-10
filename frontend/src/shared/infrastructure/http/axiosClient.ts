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

/** Fayl yuklash uchun alohida chegara — 10 daqiqa. */
const UPLOAD_TIMEOUT_MS = 10 * 60 * 1000;

// Request Interceptor: Inject Auth Token
axiosClient.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    const token = storage.get<string>('auth_token');
    if (token && config.headers) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    // FormData -> let axios set multipart/form-data with boundary (global JSON header would break uploads)
    if (config.data instanceof FormData && config.headers) {
      delete config.headers['Content-Type'];
      // Yuqoridagi 15 soniya oddiy JSON so'rovlar uchun. Fayl yuklashda esa u
      // kam: 60 MB biriktirma dev-serverga ~30 soniyada boradi, sekin tarmoqda
      // bundan ham ko'p. Taymer ishlab ketsa, foydalanuvchi hajm xatosi emas,
      // "Server unavailable" degan chalg'ituvchi xabarni ko'rardi.
      config.timeout = UPLOAD_TIMEOUT_MS;
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
        if (typeof window !== 'undefined') {
          window.dispatchEvent(new CustomEvent('auth:unauthorized'));
        }
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
