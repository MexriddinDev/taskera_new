# 🚀 PROJECT 7.2 → 9.0+ TRANSFORMATION PROMPT

Sen **15+ yillik tajribaga ega Senior Software Architect, Senior Python Developer, Senior PHP/Laravel Developer, Senior Backend Developer, Senior React/TypeScript Frontend Developer, Database Engineer, Security Engineer va DevOps/Production Engineer** sifatida ishlaysan.

Men senga loyiha bo‘yicha oldindan o'tkazilgan audit natijasini taqdim qilaman.

Hozirgi holat:

```text
Architecture & Modularity     7.8 / 10
Python Backend                7.5 / 10
Laravel / PHP Backend         7.9 / 10
Frontend React + TypeScript    8.2 / 10
Database & Queries             7.0 / 10
Application Security           6.8 / 10
Performance & Scalability      7.2 / 10
Code Quality                   7.9 / 10
Automated Testing              4.5 / 10

OVERALL                        7.2 / 10
```

### MAQSAD

Loyihani bosqichma-bosqich takomillashtirib:

**7.2/10 → 8.0/10 → 8.5/10 → 9.0+/10**

darajaga olib chiq.

Maqsad shunchaki score'ni oshirish emas.

Maqsad:

* Security'ni kuchaytirish
* Architecture'ni yaxshilash
* Performance'ni optimallashtirish
* Database'ni professional darajaga olib chiqish
* Frontend architecture'ni bir xil standartga keltirish
* Automated testing'ni kuchaytirish
* Code quality'ni oshirish
* Production reliability'ni yaxshilash
* Scalability'ni ta'minlash
* Real production muhitida barqaror ishlaydigan tizim yaratish

---

# 1. BOSQICH — SECURITY HARDENING

Eng birinchi navbatda Security muammolarini tuzat.

## 1.1 RBAC / Permission

`api.php` dagi:

* problems
* changes
* workflows
* automation
* integrations
* boshqa administrativ endpointlar

uchun faqat:

```text
auth:sanctum
```

bilan cheklanib qolmasdan, tegishli:

```text
permission:*
```

yoki mavjud RBAC architecture'ga mos permission tekshiruvlarini qo‘sh.

### Talab:

Har bir endpoint uchun:

```text
Authentication
        ↓
Authorization
        ↓
Permission
        ↓
Resource ownership
        ↓
Business validation
```

ketma-ketligini ta'minla.

---

# 2. BOSQICH — IDOR / RESOURCE OWNERSHIP

Quyidagi controllerlarni alohida tekshir:

* CommentController.php
* TaskController.php
* boshqa resource controllerlar

Foydalanuvchi boshqa foydalanuvchining:

* task
* comment
* request
* asset
* problem
* boshqa resource

lariga noqonuniy murojaat qila olmasligini kafolatla.

Har bir resource uchun ownership yoki permission policy ishlat.

Laravel'da imkon qadar:

* Policies
* Gates
* Form Requests
* route model binding

dan foydalan.

---

# 3. BOSQICH — SMS VERIFICATION SECURITY

`AdAccountController.php` dagi SMS verification flow'ni qayta ko‘rib chiq.

Quyidagi prinsipni ta'minla:

```text
SMS verification
       ↓
One-time verification token
       ↓
Token validation
       ↓
Action execution
       ↓
Token invalidated
```

Token:

* bir martalik
* qisqa muddatli
* qayta ishlatilmaydigan
* xavfsiz saqlanadigan

bo‘lishi kerak.

Replay attack imkoniyatini yo‘qot.

---

# 4. BOSQICH — PYTHON DATABASE CONNECTION POOL

`mysql_database.py` ni tekshir.

Agar har bir query uchun yangi connection ochilayotgan bo‘lsa, buni:

```text
aiomysql.create_pool()
```

asosidagi connection pool architecture'ga o'tkaz.

Talablar:

* global/reusable pool
* connection reuse
* proper acquire/release
* timeout
* exception handling
* graceful shutdown
* pool size configuration
* environment orqali sozlash

Connection leak bo‘lmasligini ta'minla.

---

# 5. BOSQICH — N+1 QUERY MUAMMOLARINI YO‘QOTISH

`RoleController.php` va audit davomida topilgan barcha N+1 querylarni aniqlab chiq.

Har bir holatda:

```text
Before:
1 + N queries

After:
1–2 optimized queries
```

darajasiga olib kel.

Laravel'da kerak bo‘lsa:

* eager loading
* `with()`
* `withCount()`
* joins
* aggregation
* subquery
* caching

dan foydalan.

Lekin optimizatsiya natijasida business logic buzilmasin.

---

# 6. BOSQICH — DATABASE OPTIMIZATION

Database'ni to‘liq audit qil.

Tekshir:

* missing indexes
* unnecessary indexes
* composite indexes
* foreign keys
* unique constraints
* slow queries
* duplicate queries
* inefficient joins
* unnecessary columns
* pagination
* sorting
* filtering

Har bir katta table uchun query pattern'larni tahlil qil.

Kerakli indexlarni migration orqali qo‘sh.

### Muhim:

Indexni shunchaki ko‘paytirib yuborma.

Har bir index uchun:

```text
Query pattern
+
Expected benefit
+
Write overhead
```

ni hisobga ol.

---

# 7. BOSQICH — REDIS / CACHING

Takroran chaqiriladigan va kam o‘zgaradigan ma'lumotlarni aniqlab:

Redis caching architecture taklif qil yoki mavjud Redis'ni optimallashtir.

Masalan:

* permissions
* roles
* system settings
* frequently accessed reference data
* dashboard statistics
* expensive aggregation results

Cache uchun:

```text
Cache key
TTL
Invalidation strategy
Fallback
```

aniq belgilansin.

Stale data muammosini oldini ol.

---

# 8. BOSQICH — API PERFORMANCE

Barcha API endpointlarni tekshir.

Quyidagilarni optimallashtir:

* response payload
* pagination
* filtering
* sorting
* eager loading
* serialization
* duplicate requests
* unnecessary API calls
* database query count

Frontendga kerak bo‘lmagan ma'lumotlarni yuborma.

---

# 9. BOSQICH — FRONTEND ARCHITECTURE

Hozir:

```text
Authentication → yaxshi
Tasks → yaxshi
```

Clean Architecture asosida ishlayotgan bo‘lsa, shu standartni:

* ProblemsPage
* AssetsPage
* RbacManagementPage
* boshqa yangi sahifalar

ga ham tatbiq qil.

Bir xil architecture:

```text
Presentation
    ↓
Application
    ↓
Domain
    ↓
Infrastructure
```

yoki loyihadagi mavjud Clean Architecture standardiga mos bo‘lsin.

Har bir yangi modul mavjud pattern bilan bir xil ishlasin.

---

# 10. BOSQICH — FRONTEND CODE QUALITY

React + TypeScript kodlarini tekshir:

* unnecessary re-renders
* duplicated components
* duplicated API logic
* incorrect state management
* unnecessary `useEffect`
* missing memoization where genuinely useful
* type safety
* `any` usage
* error handling
* loading state
* empty state
* optimistic updates
* API caching

TypeScript'da imkon qadar:

```text
any
```

dan qoch.

---

# 11. BOSQICH — ERROR HANDLING

Backend va frontend bo‘yicha yagona error handling strategy yarat.

Backend:

```text
Exception
    ↓
Logging
    ↓
Normalized API response
```

Frontend:

```text
API error
    ↓
Error normalization
    ↓
User-friendly message
    ↓
Logging/monitoring
```

Sensitive technical information foydalanuvchiga chiqarilmasin.

---

# 12. BOSQICH — LOGGING & MONITORING

Production uchun:

* structured logging
* error tracking
* request logging
* authentication events
* security events
* failed jobs
* database errors
* critical business errors

uchun monitoring strategy yarat.

Keraksiz yoki sensitive ma'lumotlarni log qilma.

---

# 13. BOSQICH — AUTOMATED TESTING

Hozirgi:

**4.5/10 Testing**

ko‘rsatkichini kamida:

**8.5–9.0/10**

darajaga olib chiq.

Test pyramid:

```text
        E2E
       /   \
 Integration
    /       \
   Unit Tests
```

## Laravel

Qo‘sh:

* Feature tests
* API tests
* Authorization tests
* Policy tests
* Validation tests
* Database tests

## Python

Qo‘sh:

* unit tests
* integration tests
* database tests
* async tests
* error handling tests

## React

Qo‘sh:

* component tests
* hook tests
* API integration tests
* critical user-flow tests

---

# 14. BOSQICH — SECURITY TESTS

Alohida security testlar yoz.

Majburiy testlar:

```text
Unauthorized user
Authenticated user
Wrong role
Wrong permission
Wrong resource owner
Expired token
Reused token
Invalid input
Malformed request
Rate limit
```

Quyidagi prinsipni kafolatla:

**User faqat o‘ziga ruxsat berilgan resource va actionlarga kira oladi.**

---

# 15. BOSQICH — TRANSACTION & DATA INTEGRITY

Critical business operations uchun database transactionlardan foydalanish kerakligini tekshir.

Masalan:

```text
Operation A
Operation B
Operation C
```

agar ulardan biri muvaffaqiyatsiz bo‘lsa:

```text
ROLLBACK
```

bo‘lishi kerak.

Database consistency buziladigan barcha holatlarni aniqlash.

---

# 16. BOSQICH — CONCURRENCY & RACE CONDITIONS

Quyidagilarni tekshir:

* simultaneous requests
* duplicate actions
* double submission
* concurrent updates
* stock/quantity changes
* financial/business critical operations
* queue jobs

Kerak bo‘lsa:

* database locking
* unique constraints
* idempotency
* transactions

dan foydalan.

---

# 17. BOSQICH — DEPENDENCIES

Python:

* `requirements.txt`
* `pyproject.toml`

Laravel:

* `composer.json`

Frontend:

* `package.json`

ni tekshir.

Aniqla:

* outdated dependencies
* deprecated packages
* security vulnerabilities
* unused dependencies
* duplicate dependencies
* incompatible versions

Keraksiz dependencylarni olib tashlashni taklif qil.

---

# 18. BOSQICH — CODE QUALITY

Barcha backend va frontend kodlarida:

* SOLID
* DRY
* KISS
* YAGNI
* Clean Code
* Separation of Concerns
* Dependency Injection

prinsiplarini qo‘llash.

Lekin:

**Over-engineering qilma.**

Oddiy muammo uchun keraksiz murakkab architecture yaratma.

---

# 19. BOSQICH — PRODUCTION HARDENING

Production uchun quyidagilarni tekshir:

* environment configuration
* secrets
* debug mode
* CORS
* rate limiting
* queue workers
* cron/scheduler
* database backup
* logging
* health checks
* graceful shutdown
* timeout
* retry
* monitoring
* deployment configuration

Production'da:

```text
DEBUG = false
```

va sensitive credentials source code ichida bo‘lmasligini tekshir.

---

# 20. REGRESSION PROTECTION

Har bir muhim bug fix uchun regression test yoz.

Prinsip:

```text
Bug found
   ↓
Bug fixed
   ↓
Test added
   ↓
Future regression prevented
```

---

# 21. O‘ZGARISHLARNI XAVFSIZ BAJAR

Kodga o‘zgartirish kiritishdan oldin:

1. Muammoni aniqlash.
2. Sababini tushunish.
3. Business logic'ni tekshirish.
4. Minimal va xavfsiz fix rejasini tuzish.
5. Kodni o‘zgartirish.
6. Test qilish.
7. Regression tekshirish.
8. Natijani hujjatlashtirish.

**Keraksiz katta refactoring qilma.**

Working functionality'ni sababsiz o‘zgartirma.

---

# 22. HAR BIR FIX UCHUN HISOBOT

Har bir o‘zgartirishdan keyin:

```text
FILE:
CHANGE:
WHY:
SECURITY IMPACT:
PERFORMANCE IMPACT:
BREAKING CHANGE:
TEST ADDED:
RESULT:
```

formatida hisobot ber.

---

# 23. FINAL AUDIT

Barcha fixlar tugagach loyihani boshidan qayta audit qil.

Quyidagi score'larni qayta hisobla:

```text
Architecture & Modularity     XX/10
Python Backend                XX/10
Laravel / PHP Backend         XX/10
Frontend React + TypeScript   XX/10
Database & Queries            XX/10
Application Security          XX/10
Performance & Scalability     XX/10
Code Quality                  XX/10
Automated Testing             XX/10

OVERALL                       XX/10
```

### MAQSAD:

```text
Architecture       ≥ 9.0
Python Backend     ≥ 9.0
Laravel Backend    ≥ 9.0
Frontend           ≥ 9.0
Database           ≥ 9.0
Security           ≥ 9.0
Performance        ≥ 9.0
Code Quality       ≥ 9.0
Testing            ≥ 9.0

OVERALL            ≥ 9.0
```

Agar biror kategoriya 9.0 dan past bo‘lsa:

1. sababini aniqlash;
2. qolgan muammolarni topish;
3. ustuvorlik berish;
4. fix qilish;
5. qayta test qilish;
6. score'ni qayta baholash.

---

# 24. YAKUNIY NATIJA

Oxirida quyidagi hisobotni ber:

## FINAL PROJECT HEALTH SCORE

```text
BEFORE: 7.2 / 10
AFTER:  X.X / 10
TARGET: 9.0+ / 10
```

## FIXED ISSUES

```text
Critical: XX
High:     XX
Medium:   XX
Low:      XX
```

## SECURITY

```text
Before: 6.8/10
After:  X.X/10
```

## PERFORMANCE

```text
Before: 7.2/10
After:  X.X/10
```

## TESTING

```text
Before: 4.5/10
After:  X.X/10
```

## REMAINING ISSUES

9/10 ga chiqishga hali to‘sqinlik qilayotgan barcha muammolarni ko‘rsat.

## PRODUCTION READINESS

Quyidagilardan har biriga:

```text
READY / NOT READY / NEEDS IMPROVEMENT
```

bahosini ber:

* Security
* Stability
* Performance
* Scalability
* Testing
* Monitoring
* Backup
* Deployment
* Error handling
* Data integrity

---

# ASOSIY QOIDA

**Maqsad — shunchaki kodni o‘zgartirish emas.**

Maqsad:

> **Security, reliability, performance, scalability, maintainability va testability jihatidan professional production-ready tizim yaratish.**

Har bir qarorni mavjud loyiha architecture'ini hisobga olgan holda qabul qil.

**Avval audit → keyin reja → keyin fix → keyin test → keyin regression audit → keyin final score.**

Hech qachon muammoni yashirma.

Agar 9/10 ga chiqishga to‘sqinlik qilayotgan muammo mavjud bo‘lsa, uni aniq ko‘rsat.

**9/10 score'ni sun'iy ravishda bermagin. Faqat real texnik holat asosida bahola.**
