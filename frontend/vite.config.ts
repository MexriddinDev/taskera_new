import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

const getApiProxyTarget = (mode: string) =>
  loadEnv(mode, __dirname, '').VITE_API_PROXY_TARGET || 'http://127.0.0.1:5170';

export default defineConfig(({ mode }) => ({
  base: '/',
  plugins: [react()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      output: {
        // PERFORMANCE: vendor kutubxonalarni alohida chunk'larga ajratish —
        // kichik sahifa o'zgarishlarida butun bundle qayta yuklanmaydi.
        manualChunks: {
          'vendor-react': ['react', 'react-dom', 'react-router-dom'],
          'vendor-query': ['@tanstack/react-query'],
          'vendor-charts': ['recharts'],
        },
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: getApiProxyTarget(mode),
        changeOrigin: true,
      },
    },
  },
}));
