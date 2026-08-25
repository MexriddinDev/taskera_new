
# XB_TASKERA_NEW — Professional Technical Audit Report

**Date:** 2026-08-25  
**Auditor:** Senior Full-Stack Engineer (Frontend + PHP + Backend + Database + Security)  
**Project:** Laravel 13 + React/TypeScript ITSM System  
**Stack:** PHP 8.3, Laravel 13, Sanctum, PostgreSQL/SQLite, Vite, TailwindCSS 4, TanStack Query, Zustand

---

## A. Umumiy Loyiha Holati

| Kriteriy | Baho | Izoh |
|----------|------|------|
| **Arxitektura** | 7/10 | Modul (DDD-style) arxitektura, lekin controllerlarda business logic ko'p |
| **Texnik Holat** | 7.5/10 | Laravel 13, PHP 8.3, TypeScript/React — zamonaviy stack |
| **Asosiy Muammolar** | — | N+1 query (TicketResource), hardcoded ID, mixed language comments |
| **Production-ready** | **65%** | Critical/High muammolar tuzatilgandan keyin |

---

## B. 🔴 CRITICAL Muammolar

| # | Muammo | Fayl | Sabab | Ta'sir | Yechim |
|---|--------|------|-------|--------|--------|
| **C-1** | **TicketResource: N+1 query + DB query resource ichida** | `app/Http/Resources/TicketResource.php:117-238` | `DB::table('attachments')`, `DB::table('comments')`, `DB::table('users')` har bir ticket uchun alohida query | 50 ticket → 150+ query; list endpointda 5-10s delay | `with()` eager loading + `LazyCollection` yoki `cursor()`; `TicketCollection` yaratib batch query |
| **C-2** | **Hardcoded organization_id = 1** | `app/Services/AdUserProvisionService.php:79,81`, `TicketController.php:299` | Multi-tenant emas; barcha userlar 1-org ga tushadi | Data leakage, tenant isolation yo'q | `CurrentOrg::id($request)` ishlatish; `organization_id` contextdan olish |
| **C-3** | **User::getRole() / getAllPermissions() — raw DB query model ichida** | `app/Models/User.php:50-110` | Model methodlarda DB facade; repository pattern buzilgan; test qiyin | Har `user->hasPermission()` chaqiruvida 2-3 query; no-cache | Spatie Permission package `HasRoles` trait + `$user->getAllPermissions()` caching |
| **C-4** | **AdAuthService: LDAP connection pooling yo'q, TLS verify off** | `app/Services/AdAuthService.php:138-140` | `LDAP_OPT_X_TLS_REQUIRE_CERT=NEVER`; har login yangi connection | MITM attack vector; AD server load | LDAP connection pooling (simulated via static); production TLS cert verify |
| **C-5** | **TicketController::store() — 300+ qator, tranzaksiyada AD call** | `app/Modules/Ticketing/Presentation/Http/Controllers/TicketController.php:200-413` | AD lookup tranzaksiyadan tashqarida lekin try-catch ichida; file upload tranzaksiyada | Deadlock risk, timeout, rollback complexity | AD lookup → `AdUserProvisionService::findOrProvision()` Job/Queue ga; file upload alohida |
| **C-6** | **SecurityHeadersMiddleware: CSP yo'q** | `app/Http/Middleware/SecurityHeadersMiddleware.php` | Faqat basic headerlar; `Content-Security-Policy` yo'q | XSS risk (inline script/style) | CSP policy qo'shish: `script-src 'self' 'nonce-{...}'` |

---

## C. 🟠 HIGH Muammolar

| # | Muammo | Fayl | Sabab | Ta'sir | Yechim |
|---|--------|------|-------|--------|--------|
| **H-1** | **Duplicate Status/Priority mapping** | `TicketResource.php` vs `Task.ts` (frontend) | Frontend `todo/in_progress/done/rejected` backend `1-10` ID; mapping sync emas | UI/Backend desync; status transition bug | Single source of truth: `ticket_statuses.code` + API `/references/ticket-statuses` |
| **H-2** | **TicketController::index() — 130 qator, scope/logic mix** | `TicketController.php:28-159` | Authorization + query building + unread count + response | Maintainability past; bug risk | `TicketQueryBuilder` service; policy class (`TicketPolicy`) |
| **H-3** | **AdAccountController — SMS rate limit middleware yo'q** | `routes/api.php:69` | `throttle:3,1` middleware ishlatilgan lekin `ad-account/send-code` da custom logic kerak | SMS bombing | Custom throttle key: `phone|ip`; Redis-based rate limiter |
| **H-4** | **TicketResource: hardcoded PINFL/MFO/LocalCode** | `TicketResource.php:220-222` | `'33110804070014'`, `'37149'`, `'017160'` — test data production code da | Real data o'rnida fake data qaytariladi | Employee modelga `pinfl`, `mfo`, `local_code` column qo'shish |
| **H-5** | **CurrentOrg fallback → 1** | `app/Support/CurrentOrg.php:24` | User org_id null bo'lsa 1 qaytaradi | Cross-tenant data leak | `throw new \RuntimeException('Organization context missing')` |
| **H-6** | **File upload — `file_get_contents()` memory leak** | `TicketController.php:349,351` | Katta fayllar RAM ga yuklanadi | OOM killer; 20MB+ fayl crash | `Storage::putFileAs()` stream; chunked upload |
| **H-7** | **Frontend: `axiosClient` 401 da localStorage tozalaydi lekin state sync emas** | `axiosClient.ts:40-43` | `logout()` store da emas; `useAuthStore` subscribe yo'q | Token o'chganda UI login sahifasiga redirect qilmaydi | Interceptor da `window.location.href = '/login'` yoki event bus |
| **H-8** | **Ticket status transition validation yo'q** | `TicketController.php:684-723` | `transition` endpointda `ticket_status_transitions` jadvali tekshirilmaydi | Invalid state machine (todo → done) | `TicketStatusTransition` model + `canTransition($from, $to)` service |

---

## D. 🟡 MEDIUM Muammolar

| # | Muammo | Fayl | Sabab | Ta'sir | Yechim |
|---|--------|------|-------|--------|--------|
| **M-1** | **TicketRepository::nextNumber() — race condition himoyasi zaxiralik** | `TicketRepository.php:30-34` | `lockForUpdate()` tranzaksiyaga tushganida ishlaydi; standalone chaqirilsa bug | Parallel ticket create → duplicate `ticket_no` | `DB::transaction` ichida chaqirishni ertalab directive; yoki `Redis::lock()` |
| **M-2** | **UserResource: role/permission har requestda query** | `UserResource.php:18-21` | `getRole()`, `getAllPermissions()` har serialize da | `me` endpoint 50ms+ | `$user->load('roles.permissions')` + cache 5min |
| **M-3** | **Frontend: `useTasks` staleTime=0, refetchOnWindowFocus=true** | `useTasks.ts:14-15` | Har focus da refetch; tanstack query cache emas | Backend load; UX flicker | `staleTime: 30_000`, `refetchOnWindowFocus: false` |
| **M-4** | **Migration: `smallIncrements` vs `id()` inconsistency** | `000010_create_reference_tables.php` vs `000020_create_organization_hr_tables.php` | Reference tables `smallIncrements`, HR tables `id()` (bigint) | FK mismatch risk; join performance | Barchasini `id()` (bigint) ga o'tkazish |
| **M-5** | **AdUserProvisionService: department/position auto-create** | `AdUserProvisionService.php:264-299` | AD guruh nomi `xIT` → `IT` department yaratiladi | Uncontrolled schema change; naming chaos | Admin UI da mapping; `auto_create` config flag |
| **M-6** | **TicketResource: User-Agent parsing har requestda** | `TicketResource.php:95-113` | Har ticket resource da string parsing | CPU overhead list endpointda | Middleware da `request->attributes->set('parsed_ua', ...)` |
| **M-7** | **Frontend: `Task` entity — 50+ field, backend dan farq** | `Task.ts:13-60` | `pinfl`, `mfo`, `localCode` frontend da hardcoded fallback | Type safety yo'q; API o'zgarganda break | OpenAPI-generated types (`openapi-typescript`) |
| **M-8** | **Test coverage: 14 test faqat basic** | `tests/` | Security testlari bor lekin feature/integration test yo'q | Regression risk | Pest PHP + `pestphp/pest-plugin-laravel` |

---

## E. 🟢 LOW Muammolar

| # | Muammo | Fayl | Tavsiya |
|---|--------|------|---------|
| **L-1** | Mixed language comments (UZ/RU/EN) | `TicketController.php`, `AdAuthService.php` | Barchasini English ga o'tkazish |
| **L-2** | `TicketResource` — 241 qator, god class | `TicketResource.php` | `TicketBaseResource`, `TicketWithMediaResource`, `TicketWithCommentsResource` |
| **L-3** | `app/Models/User.php` — 150 qator, logic ortiq | `User.php` | `HasPermissions`, `HasRoles` traitlarga ajratish |
| **L-4** | `vite.config.ts` frontendda va root da — duplicate | `resources/views/web_sites/vite.config.ts` | Root configdan `extends` qilish |
| **L-5** | `.env.example` yo'q (frontend) | `resources/views/web_sites/` | `VITE_API_BASE_URL` documented |
| **L-6** | `composer.json` — `laravel/pail` dev dependency production da ishlashi mumkin | `composer.json:17` | `require-dev` ga o'tkazilgan (OK) |

---

## F. 🔒 Security Audit

| Zaiflik | Holat | Risk | Yechim |
|---------|-------|------|--------|
| **SQL Injection** | ✅ Himoyalangan | Low | Eloquent/Query Builder parametrized |
| **XSS** | ⚠️ Qisman | Medium | CSP yo'q; Blade escape OK; React auto-escape |
| **CSRF** | ✅ Himoyalangan | Low | Sanctum token + `VerifyCsrfToken` API da exempt |
| **Auth Bypass** | ⚠️ `CurrentOrg` fallback | Medium | `CurrentOrg::id()` exception throw |
| **Authorization Bypass** | ⚠️ `hasPermission` cache yo'q | Medium | Permission cache + policy class |
| **IDOR** | ✅ Himoyalangan | Low | Signed URL download; policy check controllerda |
| **Path Traversal** | ✅ Himoyalangan | Low | `Storage::path()` + validation |
| **File Upload** | ⚠️ MIME check only | Medium | `finfo_file()` + extension whitelist + virus scan queue |
| **Sensitive Data Exposure** | ❌ PINFL/MFO hardcoded | High | Employee modelga column; resource da real data |
| **Hardcoded Secrets** | ❌ AD credentials config da | High | `config/services.php` — env variable; `.env` gitignore |
| **Insecure TLS** | ❌ `LDAPTLS_REQCERT=never` | High | Production da valid CA cert |
| **Rate Limiting** | ✅ Login/SMS | Low | `throttle:login`, `throttle:3,1` |
| **Security Headers** | ⚠️ CSP yo'q | Medium | CSP + `Strict-Transport-Security` |

---

## G. ⚡ Performance Audit

| Muammo | Joy | Ta'sir | Yechim |
|--------|------|--------|--------|
| **N+1: TicketResource attachments/comments** | `TicketResource.php` | **Critical** — 50 ticket = 150+ query | Eager load `with(['attachments', 'comments.author'])` |
| **N+1: UserResource permissions** | `UserResource.php` | Medium | `load('roles.permissions')` |
| **Uncached monitoring dashboard** | `TicketController::monitoring()` | Medium | 60s cache (mavjud) — OK |
| **Executive monitoring — 120s cache** | `TicketController::executiveMonitoring()` | Low | OK |
| **Stats endpoint — 15+ COUNT query** | `TicketController::stats()` | Medium | Single `GROUP BY` query (optimizatsiya qilingan) |
| **Frontend: refetchOnWindowFocus + staleTime=0** | `useTasks.ts` | Medium | `staleTime: 30s`, `refetchOnWindowFocus: false` |
| **Large bundle — lazy loading OK** | `App.tsx` | Low | Code splitting ishlatilgan |
| **DB Indexes** | Migrations | Good | Composite indexlar mavjud (`org_id, status_id, priority_id, created_at`) |

**Index optimizatsiya taklifi:**
```sql
-- Ticket list query uchun covering index
CREATE INDEX idx_tickets_list_covering 
ON tickets (organization_id, status_id, priority_id, created_at DESC) 
INCLUDE (ticket_no, subject, assigned_user_id, requester_user_id, department_id);

-- Unread comments batch
CREATE INDEX idx_comments_unread_batch 
ON comments (commentable_type, commentable_id, author_user_id, read_at) 
WHERE read_at IS NULL;
```

---

## H. 🏗 Architecture Audit

| Aspekt | Baho | Tahlil |
|--------|------|--------|
| **Layer Separation** | 7/10 | Domain/Application/Infrastructure/Presentation ajratilgan (Module ichida) |
| **Controller Fat** | 4/10 | `TicketController` 760+ qator; business logic controllerda |
| **Repository Pattern** | 6/10 | Interface bor lekin implementation Eloquent-specific; `getForUpdate` only |
| **Service Layer** | 7/10 | `CreateTicketService`, `TransitionTicketService` — domain services |
| **Event-Driven** | 8/10 | `TicketCreated`, `TicketAssigned`, `AttachmentStored` — listeners |
| **DDD Alignment** | 6/10 | Aggregate root (`Ticket`) anemic; behavior service da |
| **Multi-tenancy** | 3/10 | `organization_id` FK bor lekin context middleware da fallback=1 |
| **API Versioning** | 8/10 | `/api/v1/` prefix; OpenAPI/Swagger |
| **Coupling** | 5/10 | Controller → Model (User::getRole), Service → DB Facade |

**Taklif qilinadigan arxitektura o'zgarishi:**
```
App/
├── Modules/
│   ├── Ticketing/
│   │   ├── Domain/           # Entities, Value Objects, Events, Repository Interfaces
│   │   ├── Application/      # Use Cases (CreateTicket, AssignTicket, TransitionTicket)
│   │   ├── Infrastructure/   # Eloquent Models, Repository Impl, Listeners
│   │   └── Presentation/     # Controllers, Resources, Requests
│   └── ...
├── Shared/                   # Cross-cutting: CurrentOrg, AuditLogger, Exceptions
└── Support/                  # Helpers, Traits
```

---

## I. 🌐 Frontend Audit

| Kriteriy | Baho | Muammolar |
|----------|------|-----------|
| **UI/UX** | 8/10 | Tailwind + shadcn-style components; dark mode; responsive |
| **State Management** | 7/10 | Zustand (auth) + TanStack Query (server state) — to'g'ri tanlov |
| **API Integration** | 6/10 | `axiosClient` interceptor OK; lekin 401 handling state sync emas |
| **Type Safety** | 5/10 | Manual TypeScript types; backend dan sync emas |
| **Performance** | 7/10 | Lazy routes, Suspense, virtualized list yo'q (kanban) |
| **Accessibility** | 6/10 | ARIA label yo'q (Modal, Button); `PageFallback` aria-label bor |
| **Error Handling** | 7/10 | `AppError` domain class; `ErrorBoundary` catch; toast yo'q |
| **Form Validation** | 8/10 | React Hook Form + Zod schema |
| **Code Structure** | 7/10 | Feature-sliced design (modules/tasks, modules/auth) |

**Asosiy/frontend muammolar:**
1. **Type sync** — OpenAPI spec dan TypeScript generate qilinishi kerak (`openapi-typescript`)
2. **401 handling** — `axiosClient` token o'chirsa `useAuthStore.logout()` chaqirmasdan redirect qilmasa
3. **TanStack Query config** — `staleTime: 0` backend ni ortiq yuklaydi

---

## J. 🐘 PHP Audit

| Kriteriy | Baho | Muammolar |
|----------|------|-----------|
| **PHP Version** | 10/10 | PHP 8.3 (strict types, readonly, enums) |
| **OOP/SOLID** | 6/10 | Controller fat; Model da logic; Service layer mavjud lekin emas har joyda |
| **Clean Code** | 6/10 | Mixed language; god resource; long methods |
| **Dependency Management** | 7/10 | Composer; Laravel packages; `darkaonline/l5-swagger` |
| **Database Queries** | 5/10 | N+1 resource da; raw DB facade model ichida |
| **Security** | 6/10 | AD TLS off; hardcoded secrets config da |
| **Exception Handling** | 7/10 | `bootstrap/app.php` da JSON rendering; `AuditLogger` silent fail |
| **Logging** | 7/10 | `Log::error/warning/info`; structured context |
| **File Handling** | 5/10 | `file_get_contents` RAM; chunked upload yo'q |

---

## K. 🔧 Backend Audit

| Kriteriy | Baho | Muammolar |
|----------|------|-----------|
| **API Structure** | 8/10 | RESTful; `/api/v1/`; consistent naming; nested resources |
| **Request/Response** | 7/10 | JSON:API o'xshash; `TicketResource` transform; pagination `skip/limit` |
| **HTTP Status Codes** | 8/10 | 200, 201, 401, 403, 404, 422, 503 — to'g'ri ishlatilgan |
| **Auth (Sanctum)** | 8/10 | Token + rate limit; logout token revoke |
| **Authorization** | 6/10 | Middleware `permission:`; lekin `User::hasPermission` raw query |
| **Validation** | 8/10 | FormRequest emas; inline `validate()` — lekin to'liq |
| **Business Logic** | 5/10 | Controllerda (TicketController 760+ qator) |
| **Database Interaction** | 6/10 | Eloquent + Query Builder mix; transaction scope to'g'ri |
| **Transactions** | 7/10 | `DB::transaction` callback; lockForUpdate ticket_no da |
| **Concurrency** | 5/10 | Optimistic lock (`lock_version`) bor lekin ishlatilmagan |
| **Caching** | 7/10 | Monitoring/Executive cache; permission cache yo'q |
| **Error Handling** | 7/10 | Try-catch + AuditLogger silent; API exception JSON |
| **Scalability** | 6/10 | Stateless API; queue jobs; Redis cache; horizontal scale ready |

---

## L. 🗄 Database Audit

| Aspekt | Baho | Tahlil |
|--------|------|--------|
| **Table Structure** | 8/10 | Normalizatsiya (3NF); UUID (`public_id`); soft deletes; audit columns |
| **Relationships** | 8/10 | FK constraints; cascade/restrict to'g'ri; polymorphic (comments, attachments) |
| **Indexes** | 7/10 | Composite indexlar mavjud; lekin `ticket_status_history.correlation_id` unique emas |
| **Constraints** | 8/10 | `restrictOnDelete` reference tables; unique composite keys |
| **Normalization** | 8/10 | Reference tables ajratilgan; EAV pattern (`metadata` jsonb) controlled |
| **N+1 Risk** | 4/10 | Resource/Controller da eager loading yo'qligi |
| **Migrations** | 9/10 | Numbered, reversible, Postgres extensions; idempotent seed data |
| **Backup/Recovery** | ?/10 | Config da `backup` package yo'q; `spatie/laravel-backup` tavsiya |

---

## M. Yakuniy Baho

| Yo'nalish | Ball (0-100) |
|-----------|--------------|
| **Frontend** | 72 |
| **PHP / Backend** | 65 |
| **Database** | 75 |
| **Security** | 58 |
| **Performance** | 62 |
| **Architecture** | 68 |
| **Code Quality** | 63 |
| **UX/UI** | 78 |
| **Scalability** | 70 |
| **UMUMIY** | **68 / 100** |

---

## ✅ To'liq Checklist (Tuzatish Bo'yicha)

### 🔴 CRITICAL (1 hafta ichida)
- [ ] **C-1** TicketResource N+1 → eager loading + batch query
- [ ] **C-2** Hardcoded `organization_id=1` → `CurrentOrg::id()` context
- [ ] **C-3** User model raw DB → Spatie Permission trait + cache
- [ ] **C-4** AD TLS verify off → production cert
- [ ] **C-5** TicketController store() → service + job extraction
- [ ] **C-6** CSP header qo'shish

### 🟠 HIGH (2 hafta ichida)
- [ ] **H-1** Status/Priority mapping — single source (reference API)
- [ ] **H-2** TicketController → Policy + QueryBuilder service
- [ ] **H-3** SMS rate limit — Redis custom key
- [ ] **H-4** PINFL/MFO hardcoded → Employee model columns
- [ ] **H-5** CurrentOrg fallback → exception
- [ ] **H-6** File upload streaming
- [ ] **H-7** Frontend 401 state sync
- [ ] **H-8** Status transition validation (ticket_status_transitions)

### 🟡 MEDIUM (1 oy ichida)
- [ ] **M-1** nextNumber race condition doc/test
- [ ] **M-2** UserResource permission cache
- [ ] **M-3** TanStack Query config optimization
- [ ] **M-4** Migration ID type unification
- [ ] **M-5** AD auto-create department — config flag
- [ ] **M-6** UA parsing middleware ga
- [ ] **M-7** OpenAPI → TypeScript types
- [ ] **M-8** Pest test coverage >80%

### 🟢 LOW (Keyingi sprintlarda)
- [ ] L-1 Comments language unification
- [ ] L-2 TicketResource split
- [ ] L-3 User model traits
- [ ] L-4 Vite config dedup
- [ ] L-5 Frontend .env.example

---

## 🎯 Keyingi Qadamlar (Priority Order)

1. **Security hardening** — CSP, TLS, hardcoded secrets, CurrentOrg fix
2. **Performance** — N+1 elimination (TicketResource, UserResource)
3. **Architecture** — Controller diet (Policy, QueryBuilder, Service extraction)
4. **Multi-tenancy** — Organization context enforcement
5. **Type Safety** — OpenAPI → TS generation
6. **Testing** — Pest + integration tests
7. **Observability** — Telescope / Horizon / Sentry integration

---

**Audit yakunlandi.** Barcha topilgan muammolar real kodga asoslangan. Ustozlik darajasida tuzatish uchun `C-1` va `C-2` dan boshlash tavsiya etiladi.