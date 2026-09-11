import js from '@eslint/js';
import globals from 'globals';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import tseslint from 'typescript-eslint';

/**
 * ESLint sozlamasi.
 *
 * Ilgari `npm run lint` umuman ishlamasdi: ESLint `devDependencies` da yo'q
 * edi va `eslint.config.*` fayli ham mavjud emasdi. Ya'ni "unused import",
 * "unused variable", "console.log" kabi tekshiruvlar hech qachon
 * bajarilmagan.
 */
export default tseslint.config(
  { ignores: ['dist', 'node_modules', '*.config.js'] },
  {
    files: ['**/*.{ts,tsx}'],
    extends: [js.configs.recommended, ...tseslint.configs.recommended],
    languageOptions: {
      ecmaVersion: 2022,
      globals: globals.browser,
    },
    plugins: {
      'react-hooks': reactHooks,
      'react-refresh': reactRefresh,
    },
    rules: {
      ...reactHooks.configs.recommended.rules,
      'react-refresh/only-export-components': ['warn', { allowConstantExport: true }],

      // `console.error` ataylab ishlatiladi (storage xatolari), qolganlari yo'q.
      'no-console': ['warn', { allow: ['error'] }],
      'no-debugger': 'error',

      // Ishlatilmayotgan o'zgaruvchilar — ogohlantirish. `_` bilan boshlangani
      // ataylab tashlab yuborilgan deb hisoblanadi.
      '@typescript-eslint/no-unused-vars': ['warn', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],

      // Loyihada mavjud kod `any` ni bir necha joyda ishlatadi (axios xatolari).
      // Uni xatoga aylantirish hozircha 100+ joyni buzardi — ogohlantirish.
      '@typescript-eslint/no-explicit-any': 'warn',

      // react-hooks v7 ning React Compiler qoidalari. Mavjud kodda 47 ta
      // joyda ishlaydi va ularni tuzatish alohida refaktoring ishi —
      // shuning uchun hozircha OGOHLANTIRISH. Tuzatilgani sari bu qatorlarni
      // birma-bir o'chirib, qoidani xatoga qaytarish mumkin.
      'react-hooks/set-state-in-effect': 'warn',
      'react-hooks/refs': 'warn',
      'react-hooks/purity': 'warn',
      'react-hooks/static-components': 'warn',
      'react-hooks/immutability': 'warn',
    },
  },
);
