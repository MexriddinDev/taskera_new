# 🚀 TaskFlow — Clean Architecture & Feature-Driven Modular Architecture Guide

Ushbu loyiha **Senior Frontend Engineer** yondashuvi asosida **Clean Architecture (Toza Arxitektura)** va **Feature-Driven Modular Architecture** tamoyillari asosida qurilgan. Loyiha React, TypeScript, Tailwind CSS, TanStack Query (React Query v5), Axios va Zustand texnologiyalaridan foydalanadi.

---

## 📐 Arxitektura Konsepsiyasi (Clean Architecture in Frontend)

Loyihaning asosiy maqsadi: **UI, Biznes Mantiq (Application Logic), Domen Modellari (Domain Entities) va Data Manbalarini (Infrastructure)** bir-biridan to'liq mustaqil qilish (**Separation of Concerns**).

Qaramlik yo'nalishi (**Dependency Rule**) har doim tashqi qatlamdan ichki qatlamga qarab yo'naladi:
`Presentation / Infrastructure` ➔ `Application` ➔ `Domain`

```
  ┌─────────────────────────────────────────────────────────────┐
  │ 🎨 PRESENTATION LAYER (React Components, Custom Hooks)      │
  └──────────────────────────────┬──────────────────────────────┘
                                 │ calls
                                 ▼
  ┌─────────────────────────────────────────────────────────────┐
  │ ⚙️ APPLICATION LAYER (Use Cases: LoginUseCase, GetTasks)   │
  └──────────────────────────────┬──────────────────────────────┘
                                 │ depends on interfaces
                                 ▼
  ┌─────────────────────────────────────────────────────────────┐
  │ 💎 DOMAIN LAYER (Entities & Repository Interfaces)          │
  └──────────────────────────────▲──────────────────────────────┘
                                 │ implements interface
  ┌──────────────────────────────┴──────────────────────────────┐
  │ 🔌 INFRASTRUCTURE LAYER (HttpAuthRepo, Axios, DummyJSON API)│
  └─────────────────────────────────────────────────────────────┘
```

---

## 📁 Papkalar Strukturasi (Folder Structure)

Loyihadagi kodlar funksional modullar bo'yicha ajratilgan (`src/modules/`):

```text
src/
├── main.tsx                         # Ilova kirish nuqtasi
├── App.tsx                          # Marshrutizator (React Router), Context providerlar
├── index.css                        # Tailwind CSS va global stillar
│
├── modules/                         # Funksional modullar (Feature Modules)
│   ├── authentication/              # Autentifikatsiya moduli
│   │   ├── domain/                  # 1. Domen qatlami (Business rules & contracts)
│   │   │   ├── entities/            # Foydalanuvchi va token tiplari (User, AuthToken, Session)
│   │   │   │   └── User.ts
│   │   │   └── repositories/        # Repozitoriy interfeysi (Shartnoma)
│   │   │       └── IAuthRepository.ts
│   │   ├── application/             # 2. App qatlami (Use Cases / Biznes ssenariylar)
│   │   │   ├── LoginUseCase.ts      # Login mantiqi va validatsiyasi
│   │   │   ├── LogoutUseCase.ts     # Tizimdan chiqish mantiqi
│   │   │   └── GetCurrentUserUseCase.ts
│   │   └── infrastructure/          # 3. Infrastruktura qatlami (Realizatsiyalar va UI)
│   │       ├── api/                 # Axios orqali API chaqiruvlari (Concrete Repository)
│   │       │   └── HttpAuthRepo.ts
│   │       └── presentation/        # React vizual qismi va UI ulanmalari
│   │           ├── components/      # Form va UI komponentlari (LoginForm, ProtectedRoute)
│   │           │   ├── LoginForm.tsx
│   │           │   └── ProtectedRoute.tsx
│   │           └── hooks/           # Use Caselarni React UI bilan bog'lovchi React Query hooklari
│   │               ├── useAuth.ts
│   │               └── useLogin.ts
│   │
│   ├── tasks/                       # Task (Vazifalar) moduli
│   │   ├── domain/
│   │   │   ├── entities/            # Task, TaskStatus, TaskPriority modellari
│   │   │   │   └── Task.ts
│   │   │   └── repositories/        # ITaskRepository interfeysi
│   │   │       └── ITaskRepository.ts
│   │   ├── application/
│   │   │   ├── GetTasksUseCase.ts   # Tasklar ro'yxatini olish mantiqi
│   │   │   ├── GetTaskByIdUseCase.ts# Bitta taskni olish
│   │   │   ├── CreateTaskUseCase.ts # Yangi task yaratish va validatsiyasi
│   │   │   ├── UpdateTaskUseCase.ts # Taskni tahrirlash
│   │   │   └── DeleteTaskUseCase.ts # Taskni o'chirish
│   │   └── infrastructure/
│   │       ├── api/
│   │       │   └── HttpTaskRepo.ts  # Axios va DummyJSON API integratsiyasi
│   │       └── presentation/
│   │           ├── components/      # Karta, List, Modal, Skeleton komponentlari
│   │           │   ├── TaskCard.tsx
│   │           │   ├── TaskFilter.tsx
│   │           │   ├── TaskFormModal.tsx
│   │           │   ├── TaskSkeleton.tsx
│   │           │   └── TaskDeleteDialog.tsx
│   │           └── hooks/           # TanStack Query custom hooklari
│   │               ├── useTasks.ts
│   │               ├── useTaskDetail.ts
│   │               ├── useCreateTask.ts
│   │               ├── useUpdateTask.ts
│   │               └── useDeleteTask.ts
│   │
│   └── profile/                     # Profile moduli
│       ├── domain/
│       │   ├── entities/            # UserProfile modeli
│       │   │   └── Profile.ts
│       │   └── repositories/
│       │       └── IProfileRepository.ts
│       ├── application/
│       │   └── GetProfileUseCase.ts
│       └── infrastructure/
│           ├── api/
│           │   └── HttpProfileRepo.ts
│           └── presentation/
│               ├── components/
│               │   └── ProfileCard.tsx
│               └── hooks/
│                   └── useProfile.ts
│
├── pages/                           # Sahifalar (Page Containers)
│   ├── LoginPage.tsx
│   ├── DashboardPage.tsx
│   ├── TaskDetailPage.tsx
│   ├── ProfilePage.tsx
│   └── NotFoundPage.tsx
│
└── shared/                          # Umumiy ishlatiladigan modul (Cross-cutting Concerns)
    ├── domain/
    │   └── errors/
    │       └── AppError.ts          # Domen darajasidagi maxsus xatoliklar
    ├── infrastructure/
    │   ├── http/
    │   │   └── axiosClient.ts       # Axios misoli va Interceptorlar (Token inject, error format)
    │   └── storage/
    │       └── localStorage.ts      # Type-safe LocalStorage o'ramasi
    └── presentation/
        ├── components/              # Qayta ishlatiluvchi atomic UI komponentlar
        │   ├── Button.tsx
        │   ├── Input.tsx
        │   ├── Modal.tsx
        │   ├── Navbar.tsx
        │   ├── EmptyState.tsx
        │   └── ErrorBoundary.tsx
        ├── store/                   # Global holatlar (Zustand: Auth & Theme)
        │   ├── useAuthStore.ts
        │   └── useThemeStore.ts
        └── hooks/
            └── useDebounce.ts       # Qidiruv debounce uchun maxsus hook
```

---

## 🏛️ Qatlamlar Mas'uliyati (Layer Responsibilities)

### 1. 💎 Domain Layer (`/domain`)
- **Vazifasi**: Loyihaning eng toza biznes yadrosi.
- **Qoidalari**: Hech qanday tashqi kutubxona (React, Axios, TanStack Query) yoki freymvorkka qaram emas.
- **Tarkibi**:
  - `entities/`: TypeScript interfeyslari va tiplari (`User`, `Task`, `AuthSession`).
  - `repositories/`: Ma'lumot olish shartnomalari (`IAuthRepository`, `ITaskRepository`). Bu interfeyslar backend yoki saqlash usulidan qat'i nazar ma'lumotlar strukturasini belgilaydi.

### 2. ⚙️ Application Layer (`/application`)
- **Vazifasi**: Muayyan foydalanuvchi harakati (Use Case) bo'yicha biznes mantiqni bajaradi.
- **Tarkibi**:
  - `LoginUseCase`: Login ma'lumotlarini tekshirish, bo'sh joylarni tozalash va repozitoriyga yuborish.
  - `CreateTaskUseCase`: Sarlavha minimal 3 ta belgidan iboratligini tekshirish.
- **Afzalligi**: Framework o'zgarsa ham (masalan Vue.js yoki React Native'ga o'tilsa), Use Case mantiqiga umuman tegish shart emas.

### 3. 🔌 Infrastructure Layer (`/infrastructure`)
- **Vazifasi**: Tashqi dunyo (API, LocalStorage, HTTP Client) va UI vizualizatsiyasi bilan ishlash.
- **Tarkibi**:
  - `api/HttpAuthRepo.ts`, `api/HttpTaskRepo.ts`: `IAuthRepository` va `ITaskRepository` interfeyslarining Axios orqali amalga oshirilgan realizatsiyalari.
  - `presentation/components/`: Modulga tegishli React komponentlari (`LoginForm`, `TaskCard`, `TaskFormModal`).
  - `presentation/hooks/`: React Query (`useMutation`, `useQuery`) orqali Use Caselarni chaqiradigan va UI holatini boshqaradigan custom hooklar.

### 4. 🌐 Shared Layer (`/shared`)
- Loyihaning barcha qismlari uchun umumiy bo'lgan yordamchi modullar:
  - `axiosClient`: Authorization token interseptori va avtomatik 401/404 xatolarini `AppError` ko'rinishiga o'tkazish.
  - `useAuthStore`: Foydalanuvchi sessiyasini (Global Auth State) Zustand'da saqlash.
  - `useThemeStore`: Dark/Light Theme rejimlarini boshqarish.
  - Atomic UI: `Button`, `Input`, `Modal`, `EmptyState`, `ErrorBoundary`.

---

## 🔄 Ma'lumotlar Oqimi (Data Flow Pattern)

Foydalanuvchi UI'da tugmani bosganda ma'lumot qanday harakatlanadi:

1. **User Action**: Foydalanuvchi `LoginForm` sahifasida "Sign In" tugmasini bosadi.
2. **React Hook (`useLogin`)**: Formadagi ma'lumotlarni qabul qiladi va `LoginUseCase.execute(credentials)`ni chaqiradi.
3. **Use Case (`LoginUseCase`)**: Kirish ma'lumotlarini validatsiya qiladi va `IAuthRepository.login()` shartnomasini chaqiradi.
4. **Concrete Repo (`HttpAuthRepo`)**: Axios client orqali `POST /auth/login` so'rovini yuboradi.
5. **Axios Client**: Tokenni `localStorage`ga saqlaydi va javobni qaytaradi.
6. **State & UI Update**: `useAuthStore` foydalanuvchi sessiyasini yangilaydi, React Query UI'ni yangilaydi va foydalanuvchi `/dashboard` sahifasiga yo'naltiriladi.

---

## 🛠️ O'rnatish va Ishga Tushirish (Quick Start)

### 1. Bog'liqliklarni o'rnatish:
```bash
npm install
```

### 2. Ishchi (Development) rejimda ishga tushirish:
```bash
npm run dev
```
Brauzerda `http://localhost:5173` manzilini oching.

### 3. Production uchun Build qilish va TypeScript tekshiruvi:
```bash
npm run build
```

---

## 🔑 Sinov Uchun Akkount (Test Credentials)

Loyihada **DummyJSON** mock API ishlatilgan:
- **Username:** `emilys`
- **Password:** `emilyspass`

*(Login formada test akkount ma'lumotlarini avtomatik to'ldirish tugmasi ham mavjud).*

---

## 🌟 Senior Muhandislik Qarorlari (Senior Architectural Highlights)

1. **Strict Type Safety**: `any` tipidan foydalanilmagan. Barcha entity va DTOlar aniq tipizatsiya qilingan.
2. **Layer Isolation**: UI komponentlari to'g'ridan-to meksikan Axios chaqirmaydi — faqat presentation hooklar va Use Caselar orqali ishlaydi.
3. **Decoupled HTTP Client**: `axiosClient` interceptorlari token injection va error parsing ishlarini markazlashtirilgan holda bajaradi.
4. **Optimistic & Cache Strategy**: TanStack Query orqali server state keshlangan, refetch va mutation jarayonlari optimallashtirilgan.
5. **UX Details**: Skeleton loaderlar, debounced search (400ms), dark/light theme, confirmation dialoglar va Error Boundary to'liq integratsiya qilingan.
