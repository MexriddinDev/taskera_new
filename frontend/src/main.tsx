import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import './index.css';

// Gremlins.js — "maymun testi" vositasi, faqat dev-rejimda.
//
// `import.meta.env.DEV` + dinamik `import()`: ekspluatatsiya build'ida bu
// shart doim `false` bo'lgani uchun Rollup butun shoxni (gremlins.js ham
// birga) bundle'dan chiqarib tashlaydi. O'z-o'zidan hech narsa ishga
// tushmaydi — konsolda `gremlins()` chaqirilishi kerak.
if (import.meta.env.DEV) {
  void import('./dev/gremlins').then(({ installGremlins }) => installGremlins());
}

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);
