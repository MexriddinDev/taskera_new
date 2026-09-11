import { defineConfig, loadEnv } from 'vite';
import react from '@vitejs/plugin-react';
import basicSsl from '@vitejs/plugin-basic-ssl';
import path from 'path';

const getApiProxyTarget = (mode: string) =>
  loadEnv(mode, __dirname, '').VITE_API_PROXY_TARGET || 'http://127.0.0.1:5170';

// Mikrofon, kamera, geolokatsiya kabi API'lar brauzerda faqat "secure context"
// da ishlaydi: https:// yoki localhost. LAN IP orqali (http://172.28.x.x:5173)
// ochilganda navigator.mediaDevices umuman mavjud bo'lmaydi va ovoz yozish
// ishlamaydi. Shuning uchun dev-server o'z-o'zi imzolagan sertifikat bilan
// HTTPS'da ko'tariladi.
//
// O'chirish kerak bo'lsa: .env da VITE_DEV_HTTPS=false
const isDevHttpsEnabled = (mode: string) =>
  loadEnv(mode, __dirname, '').VITE_DEV_HTTPS !== 'false';

export default defineConfig(({ mode }) => ({
  base: '/',
  // Ekspluatatsiya build'ida konsolga chiqarish olib tashlanadi: ichki xato
  // tafsilotlari brauzer konsolida qolmasligi kerak. Dev rejimida tegilmaydi.
  esbuild: { drop: mode === 'production' ? ['console', 'debugger'] : [] },
  plugins: [react(), ...(isDevHttpsEnabled(mode) ? [basicSsl()] : [])],
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
    // LAN'dagi boshqa qurilmalar ham kira olishi uchun barcha interfeyslarda tinglaydi
    host: true,
    proxy: {
      '/api': {
        // Frontend HTTPS'da, backend HTTP'da — proxy vite ichida (server tomonda)
        // ishlagani uchun bu aralash holat muammo tug'dirmaydi.
        target: getApiProxyTarget(mode),
        changeOrigin: true,
      },
    },
  },
}));
