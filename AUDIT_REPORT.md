# Taskera Enterprise ITSM — professional audit hisoboti

Audit sanasi: 2026-09-07  
Audit turi: statik kod auditi + framework/runtime diagnostikasi + test/build/dependency audit  
Tekshirilgan hajm: `backend/app` — 267 fayl / 18 445 qator; migrations — 36 fayl / 2 541 qator; frontend — 99 fayl / 20 898 qator; 303 route.

## 1. Executive Summary

Loyiha funksional prototip sifatida ishlaydi: barcha 345 PHP fayl syntax tekshiruvdan o'tdi, Laravel yuklandi, 36 migration joriy bazada `Ran`, backenddagi 28 test (74 assertion) muvaffaqiyatli o'tdi va frontend production build yaratildi. Shunga qaramay, loyiha hozir productionga tayyor emas.

Asosiy xavflar: korporativ AD'ni chetlab o'tuvchi ochiq local registration; MariaDB'da amalda ishlamaydigan tenant isolation; notification, approval, calendar, service request va SLA endpointlaridagi IDOR/broken access control; AD/Exchange credentiallarini sertifikatsiz LDAP orqali uzatish; private attachmentlarni `public` diskda saqlash; muhim event listenerlarning ishlamasligi; zaif dependencylar.

Umumiy baho: **38/100**. Release qarori: **NO-GO**. P0 va P1 bandlar tuzatilmasdan production deploy qilinmasin.

## 2. Critical Issues

### C-01 — Ochiq local registration korporativ autentifikatsiyani chetlab o'tadi

- Joylashuv: `backend/routes/web.php:30-31`, `backend/app/Http/Controllers/Auth/RegisterController.php:21-65`.
- Dalil: `/register` GET/POST public; controller istalgan username/email bilan `auth_source=LOCAL`, `status=ACTIVE` user yaratadi va darhol `Auth::login()` qiladi. Password minimumi atigi 6 belgi (`:25`).
- Qo'shimcha privilege xavfi: `backend/app/Models/User.php:133-136` username `superadmin` bo'lsa rolni tekshirmasdan Super Admin deb qabul qiladi. Bootstrap hisobi mavjud bo'lmagan/yumshoq o'chirilgan/yangi tenant holatida `superadmin` nomini ro'yxatdan o'tkazish to'liq admin beradi.
- Real xavf: tashqi shaxs korporativ AD, HR provisioning va account-status nazoratini chetlab o'tib tizimga kiradi; ayrim holatda to'liq privilege escalation.
- Yechim: self-registration route'larini productionda butunlay o'chirish; zarur bo'lsa invite-only, verified corporate identity va rate-limit bilan alohida flow. `isSuperAdmin()` faqat immutable role/permissionga tayansin, usernamega emas. Registrationni transaction ichida bajarish.

### C-02 — MariaDB/MySQL'da tenant isolation yo'q

- Joylashuv: `backend/app/Http/Middleware/SetOrganizationContextMiddleware.php:18-22`, `backend/database/migrations/0000_00_00_000160_add_rls_and_partitioning.php:10-25`, ko'plab API controllerlar.
- Dalil: middleware `SET LOCAL app.current_organization_id` ni faqat PostgreSQL uchun qo'llaydi; amaldagi runtime `DB_CONNECTION=mysql`, MariaDB 11.4. Eloquent modellarda global organization scope yo'q. Kamida 34 controller `organization_id` ni ixtiyoriy client query parametri sifatida qabul qiladi; parametr bo'lmasa barcha tenant qatorlari qaytadi, berilsa boshqa tenant tanlanadi.
- Misollar: `CalendarEventController.php:19-27`, `ApprovalRequestController.php:19-25`, `NotificationController.php:20-27`, `TeamController.php:20-29`, `AuditLogController.php:19-26`.
- Real xavf: bir tenant foydalanuvchisi boshqa tenantning ma'lumotini ko'rishi/o'zgartirishi/o'chirishi mumkin. RLS migration `Ran` ko'rinsa ham MariaDB'da hech narsa qilmagan.
- Yechim: `BelongsToOrganization` global scope + create hook joriy qilish; barcha `find/findOrFail/update/delete` so'rovlarini `organization_id = CurrentOrg::id()` bilan cheklash; FK/validationda bog'liq ID ayni orgga tegishli bo'lishini tekshirish. Client `organization_id` faqat alohida, auditlangan super-admin cross-tenant API'da qabul qilinsin. Ikki tenantli regressiya testlari yozilsin.

### C-03 — Notification API barcha foydalanuvchilar xabarlarini va delivery ma'lumotlarini ochadi

- Joylashuv: `backend/routes/api.php:160-162`, `backend/app/Modules/Notification/Presentation/Http/Controllers/NotificationController.php:17-58`.
- Dalil: authenticated istalgan user `Notification::query()->with('deliveries')` orqali barcha notificationlarni oladi; `show` va `markAsRead` oddiy `findOrFail(id)`. `notifiable_id` joriy userga tengligi tekshirilmaydi. Delivery resource recipient/provider response kabi sezgir ma'lumotlarni ham olib kelishi mumkin.
- Real xavf: boshqa xodimlarning xabarlari, recipientlari va biznes hodisalari oshkor bo'ladi; istalgan notification ID bilan amal bajariladi.
- Yechim: queryni `notifiable_type=User::class`, `notifiable_id=$request->user()->id`, current org bilan majburiy scope qilish; admin ko'rinishi alohida permission bilan; delivery payloadlarini minimallashtirish.

### C-04 — Approval'ni istalgan authenticated user tasdiqlashi/rad etishi mumkin

- Joylashuv: `backend/routes/api.php:383-388`, `backend/app/Http/Controllers/Api/ApprovalRequestController.php:48-85`.
- Dalil: approve/reject route'larida permission yo'q. Controller approver/step/organization/statusni tekshirmay, istalgan ID holatini bevosita `APPROVED` yoki `REJECTED` qiladi. `decision_by` va `comment` validatsiya qilinadi, lekin ishlatilmaydi.
- Real xavf: moliyaviy yoki operatsion approval workflow to'liq buziladi; soxta qaror, qayta qaror va audit izi yo'qoladi.
- Yechim: policy (`canDecide`) + active approval step + current org + `PENDING` transition guard; actor har doim authenticated userdan; transaction va pessimistic lock; decision/audit yozuvi; idempotency.

### C-05 — LDAP/AD credentiallari uchun sertifikat tekshiruvi majburan o'chirilgan

- Joylashuv: `backend/app/Services/AdAuthService.php:104-110,128-136`, `backend/app/Services/ExchangeMailService.php:818-825`, `backend/config/services.php:42-48`.
- Dalil: kod global `LDAP_OPT_X_TLS_REQUIRE_CERT=LDAP_OPT_X_TLS_NEVER`, `LDAPTLS_REQCERT=never` va channel bindingni `none` qiladi. Port 389 bo'lsa `ldap://` ishlatiladi; real envda LDAP port 389. Exchange LDAPS ham server sertifikatini tekshirmaydi.
- Real xavf: MITM hujumchi service-account va user parollarini o'g'irlashi, LDAP javoblarini almashtirishi yoki account provisioningni boshqarishi mumkin.
- Yechim: LDAPS 636 yoki StartTLS; korporativ CA trust store; `DEMAND/HARD` certificate validation; channel bindingni o'chirmaslik; fail-closed. Service credentialni rotate qilish va ulanish testini CI/deploy gate qilish.

### C-06 — Attachment signed URL himoyasi `public` disk sabab chetlab o'tilishi mumkin

- Joylashuv: `backend/routes/api.php:50-53`, `backend/app/Http/Controllers/Api/AttachmentController.php:38-51,84-96`, `TicketController.php:428-452`.
- Dalil: download route signed bo'lsa-da upload default `public` diskka yozadi. Productionda `storage:link` ishlatilsa `/storage/attachments/...` to'g'ridan-to'g'ri web-server orqali route signature/authsiz ochiladi. Runtime hozir `public/storage NOT LINKED`, ammo bu deploy nuqsoni emas, access-control tasodifiy ishlayotganini bildiradi.
- Qo'shimcha IDOR: upload `attachable_id` mavjudligini va user shu ticket/userga kira olishini tekshirmaydi (`AttachmentController.php:29,51`).
- Real xavf: screenshot, audio, video va hujjatlar URL sizishi orqali public bo'ladi; begona obyektga fayl biriktirish mumkin.
- Yechim: barcha private fayllarni `local/private` yoki private S3 bucketda saqlash; faqat policy tekshiruvidan so'ng stream/temporary URL; signed URLga qo'shimcha object authorization; attachable uchun morph-map va policy; virus scan fail-closed.

## 3. High Priority Issues

### H-01 — Calendar eventlarda to'liq IDOR

`CalendarEventController.php:19-28,76-110`: barcha eventlar ko'rinadi; `show/update/destroy`da creator, participant, permission yoki org tekshiruvi yo'q. Istalgan user boshqa eventni o'zgartiradi/o'chiradi. Policy va current-org scope shart.

### H-02 — Service request va Ticket SLA ma'lumotlari ticket accessini tekshirmaydi

`ServiceRequestController.php:16-42`, `TicketSlaController.php:16-55`, route'lar `api.php:147-149,381-382`. Oddiy authenticated user boshqa user ticketiga bog'langan request/SLA'ni ID orqali ko'ra oladi. Har bir query accessible ticket subquery/policy bilan cheklansin.

### H-03 — Profile summary IDOR

`ProfileController::show()` `:22-34`da self/staff tekshiradi, ammo `summary()` `:62-101` xuddi shu tekshiruvsiz ishlaydi. Istalgan authenticated user boshqa userning ticket statistikasi va oxirgi ticket mavzularini oladi. Bir xil policy ikkala methodga qo'llansin.

### H-04 — Web comment route API'dagi tuzatilgan IDOR'ni qayta ochadi

`backend/routes/web.php:181-193`: faqat `auth` bor; ticket mavjudligi/accessi tekshirilmaydi, `organization_id` hardcoded `1`, bodyda max limit yo'q. API `CommentController`dagi authorization bu routega tatbiq etilmagan. Closure olib tashlanib, yagona authorized controller/service ishlatilsin.

### H-05 — Notification `markAsRead` umuman ishlamaydi

`NotificationController.php:50-58` faqat obyektni qaytarib “Marked as read” deydi, hech qanday update yo'q. `notifications` migrationida `read_at` ham mavjud emas (`0000_00_00_000090...:33-49`). Funksiya yolg'on muvaffaqiyat qaytaradi. Per-user read/delivery jadvali yoki `read_at` modeli joriy qilinsin.

### H-06 — Business calendar update nested payload bilan 500 beradi va atomik emas

`BusinessCalendarController.php:117-132`: `business_hours` va `holidays` validation natijasiga kiradi, keyin `$calendar->update($validated)` chaqiriladi. `business_calendars` jadvalida bunday columnlar yo'q; nested payload unknown-column SQL xatosiga olib keladi. Store/update child amallari transactionda emas, yarim yozuv qolishi mumkin. Parent fieldlarni `Arr::only`, childlarni alohida transactionda sync qilish kerak.

### H-07 — Muhim event-driven funksiyalar ishlamaydi yoki placeholder

`php artisan event:list` faqat Ticket eventlari uchun `SyncTelegramThreadListener`ni ko'rsatdi. SLA initialize/pause, status/assignment history, attachment scan, automation, audit, notification, preview va metrics listenerlari ro'yxatdan o'tmagan. `WriteAuditLogListener`, `InitializeSlaListener`, `PauseOrResumeSlaListener`, `RecordStatusHistoryListener`, `RecordAssignmentHistoryListener`, `RecalculateQueueMetricsListener`, `GeneratePreviewListener` faqat `logger()->info(...)` placeholder. Bu kataloglar production capability sifatida ko'rsatilmasin; real handler + event mapping + integration test yozilsin.

### H-08 — Locked PHP dependencyda 10 advisory

`composer audit --locked` `league/commonmark 2.8.3` uchun 10 advisory topdi: 8 high (jumladan XSS va bir nechta DoS) va 2 medium. Paket Laravel 13.23 orqali kelgan. Kamida patched `league/commonmark >=2.10.0`/Laravel-compatible releasega yangilash va auditni CI gate qilish kerak.

### H-09 — Frontend dependencylarda 5 advisory

Frontend `npm audit --package-lock-only`: Vite (high, Windows path traversal/source disclosure), nanoid (high DoS), React Router/DOM va esbuild (3 moderate) — jami 5. Vite dev serverini tarmoqqa ochmaslik; lockfile'ni compatible patched versiyalarga yangilash; React Router migration/regression test.

### H-10 — Sanctum tokenlari muddatsiz

`backend/config/sanctum.php:53` — `expiration => null`. O'g'irlangan localStorage tokeni revoke qilinmaguncha amal qiladi. Frontend tokenni `localStorage`da saqlaydi (`frontend/src/shared/infrastructure/http/axiosClient.ts:18-22`), XSS ta'sirini kuchaytiradi. Qisqa token TTL, rotation, device/session inventory, “logout all”, inactivity/revocation siyosati va imkon bo'lsa HttpOnly same-site cookie flow kerak.

## 4. Medium Issues

### M-01 — Production konfiguratsiya xavfli

Runtime: `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost`, session secure cookie unset, `SSO_VERIFY_SSL=false`, `SMS_VERIFY_SSL=false`, proxy bypass yoqilgan. Bu holatda deploy qilinsa exception/debug ma'lumotlari va transport xavfi paydo bo'ladi. Environment-specific deploy validation skripti majburiy bo'lsin.

### M-02 — `.env.example` haqiqiy ko'rinishdagi test credential va ichki topologiyani beradi

`backend/.env.example`da `SSO_CLIENT_ID=taskflow_test`, `SSO_CLIENT_SECRET=taskflow_test`, real ichki IP/hostlar va SMS username bor. Credential test bo'lsa ham secret placeholderga almashtirilsin va amaldagi credential rotate qilinsin; ichki manzillar deployment secret/configga ko'chirilsin.

### M-03 — Registration atomik emas va employee number collisionga moyil

`RegisterController.php:29-62`: employee va user yaratish transactionda emas; `EMP-` + `rand(1000,9999)` kichik fazo va retry yo'q. User insert xato bersa orphan employee qoladi. UUID/sequence va `DB::transaction` ishlatilsin.

### M-04 — Avatar API cheklanmagan base64 stringni DBga yozadi

`AuthController.php:185-205`: faqat `required|string`; MIME, decoded size, dimension va maksimum uzunlik yo'q. Katta request DB/log/memoryni to'ldirishi mumkin. Multipart image, 2–5 MB limit, real MIME/decode va resize, private object storage ishlatilsin.

### M-05 — Pagination inputlarida pastki chegara/validation yo'q

Ko'p controller `min((int)$request->query('per_page',15),100)` ishlatadi; `0` yoki manfiy qiymat qabul qilinadi. `integer|min:1|max:100` FormRequest validation joriy qilinsin.

### M-06 — Queued classlarda retry/backoff/idempotency siyosati yo'q

21 `ShouldQueue` classdan 20 tasida `$tries` yoki `backoff` yo'q; deyarli barchasida timeout/uniqueness/after-commit yo'q. External Telegram/email/scan ishlarida duplicate va transactiondan oldin dispatch xavfi bor. Har job uchun tries, timeout, exponential backoff, `failed()`, unique/idempotency key va `afterCommit` belgilang.

### M-07 — Frontend lint pipeline ishlamaydi

`npm.cmd run lint` `frontend/node_modules/eslint/bin/eslint.js` topilmagani uchun exit 1 berdi; `eslint` package `frontend/package.json`da yo'q. ESLint va configni devDependency sifatida pin qilish, CI'da lint + `tsc --noEmit` ishlatish kerak.

### M-08 — Testlar xavfsizlik regressiyalarining muhim qismini qamramaydi

28 test bor, ammo notification, approval, calendar, profile summary, service request, SLA, multi-tenant MariaDB, public registration, token expiry va attachment direct-public URL flowlari yo'q. Mavjud security testlar asosan bir organizationli SQLite muhitida, shu sababli C-02 aniqlanmagan.

## 5. Low Priority / Code Quality

- `TicketController.php` 1 592 qator, `AdAccountController.php` 890 qator, `RoleController.php` 589 qator: controllerlar query, authorization, mapping, storage va business logicni aralashtirgan. Kichik use-case/service va FormRequest/Policylarga bo'lish testabilityni oshiradi.
- `routes/web.php` ichida katta closure query/controllerlar bor; API va web flowlar duplicate bo'lib, authorization drift (H-04) yuz bergan.
- Ko'p controller client yuborgan `X-Organization-Id`ni response headerda qaytaradi; bu real resolved tenantni bildirmaydi va audit/debugni chalg'itadi.
- README hali default Laravel matni; deployment, queue worker, scheduler, integrations, backup/restore va security configuration hujjati yo'q.
- Status ID va business mappinglar bir necha joyda magic integerlar (`1..10`) bilan tarqalgan; enum/reference service ishlatilsa drift kamayadi.

## 6. Security Audit yakuni

- SQL injection bo'yicha ko'rilgan raw querylar asosan binding yoki constant expression ishlatadi; tasdiqlangan injectable raw input topilmadi.
- LDAP filter va DNlarda `ldap_escape` ko'p joyda to'g'ri qo'llangan.
- Login va SMS send rate limiterlari mavjud; lekin public registration va ayrim AD provisioning endpointlari uchun alohida abuse/rate limit siyosati yetarli emas.
- Comment va chat uchun ayrim IDOR regressiya testlari bor, ammo parallel legacy/web va boshqa modullar bir xil himoyani olmagan.
- CORS allowlist ko'rinishida va wildcard origin yo'q; production `FRONTEND_URL` majburiy validatsiya qilinsin.
- API bearer tokenlari localStorageda va muddatsiz; CSP/security headers XSS ta'sirini kamaytirsa ham token theft riski qoladi.

## 7. Database Audit yakuni

- Amaldagi DB: MariaDB 11.4, 121 table, 36/36 migration qo'llangan. Migration status toza.
- Asosiy DB nuqsoni tenant scope/RLS nomuvofiqligi (C-02).
- Bir nechta multi-step write transactiondan tashqarida (registration, business calendar); partial data xavfi bor.
- `organization_id`li FKlar bog'liq recordning ayni organizationga tegishliligini kafolatlamaydi; application validation ham ko'p joyda faqat global `exists`.
- `user_notification_preferences`da timestamps/read tracking yo'q; notification read modeli talabga mos emas.
- Ticket listinglarida composite index migrationlari bor; hozirgi kichik datasetda ishlaydi. Katta dataset uchun monitoring/report querylarini real production volume va `EXPLAIN` bilan qayta benchmark qilish kerak.

## 8. Performance Audit yakuni

- Ticket API pagination/limit ishlatadi; lekin monitoring controllerida katta aggregate va ko'p alohida querylar mavjud.
- `TeamController::formatTeam()` har team uchun alohida `COUNT` qiladi (`TeamController.php:198-202`) — N+1; `withCount` ishlatilsin.
- Profile summary bir bazaviy querydan 6 alohida `COUNT` qiladi; conditional aggregate bilan bitta queryga tushirish mumkin.
- Web ticket sahifalari hard limit 200, cursor/pagination yo'q; foydalanuvchi keyingi recordlarni ko'ra olmaydi.
- DB cache jadvali 16 MB bo'lib, bazaning asosiy hajmini egallagan; TTL cleanup va Redis production cache ko'rib chiqilsin.
- External LDAP/HTTP amallari request threadida bajariladi; Exchange yaratish/reset 60 soniyagacha bloklashi mumkin. Idempotent queued workflow + progress endpoint ma'qul.

## 9. Architecture Audit yakuni

Codebase modul kataloglari va repository interfeyslariga ega, ammo arxitektura izchil emas: ko'p API controller to'g'ridan-to'g'ri Eloquent/DB bilan ishlaydi, web route closurelari business logic saqlaydi, muhim listenerlar placeholder yoki ro'yxatdan o'tmagan. Hozirgi “event-driven” ko'rinishga ishonib bo'lmaydi. Bitta policy/tenant boundary va use-case qatlamini cross-cutting invariant sifatida majburiy qilish kerak.

## 10. Frontend ↔ Backend Audit

- Frontend API base `/api/v1`, mavjud literal chaqiriqlar route ro'yxati bilan asosan mos; production build muvaffaqiyatli.
- `Notification markAsRead` backendda no-op — UI qo'shilganda noto'g'ri success ko'rsatadi.
- Frontend authorization buttonlarni yashiradi, ammo Calendar/Approval kabi backend endpointlar real permission bermaydi; UI himoyasi security emas.
- `HttpProfileRepo` boshqa user summary endpointini to'g'ridan-to'g'ri chaqira oladi; backend policy yetishmaydi.
- Lint command mavjud, lekin dependency/config yo'qligi sabab ishlamaydi.

## 11. Ishlamaydigan funksiyalar

- Notification “mark as read” — DB update/schema yo'q.
- SLA initialize/pause-resume listenerlari — placeholder va eventga ulanmagan.
- Status/assignment history listenerlari — placeholder va eventga ulanmagan (ayrim controllerlar historyni qo'lda yozadi, izchillik yo'q).
- Automation, preview, queue metrics, generic audit va stakeholder/assignee notification listenerlari — ro'yxatdan o'tmagan yoki bo'sh skeleton.
- Business calendar nested update — payloadga `business_hours`/`holidays` kelsa SQL 500 ehtimoli yuqori.
- Frontend lint — ESLint topilmaydi.

## 12. Yarim ishlaydigan funksiyalar

- Attachment download signed route bilan himoyalangan, lekin public storage deployda uni chetlab o'tadi.
- Tenant context PostgreSQL'da qisman RLS beradi, amaldagi MariaDB'da bermaydi; RLS ro'yxati ham barcha org tablelarni qamramaydi.
- Queue/SLA/notification arxitekturasi classlarga ega, ammo retry/idempotency/event registration tugallanmagan.
- Registration user yaratadi, lekin transaction, corporate verification va collision handling yo'q.
- Business calendar create parent/children yozadi, ammo transaction yo'q.

## 13. Duplicate / Dead Code

- Web va API ticket/comment flowlari duplicate; web closure xavfsizlik tuzatishlarini meros olmaydi.
- Ko'plab `*Listener.php` skeletonlar runtime event graphda yo'q; implement qilinmasa o'chirish, aks holda aniq map/test bilan ulash kerak.
- Laravel default README va backend Vite welcome assetlari alohida React frontend ishlatilayotgan loyihada legacy/scaffold bo'lishi mumkin; deploy entrypoint aniq belgilanib keraksiz scaffold chiqarilsin.
- `App\Models\User` va `App\Modules\Identity\Infrastructure\Eloquent\User` kabi parallel model namespace'lari identity mapping driftiga sabab bo'lishi mumkin; yagona canonical model tanlansin.

## 14. Missing Features / Missing Validation

- Markaziy tenant global scope va tenant-aware `exists` rule.
- Approval policy, step transition, actor/audit va optimistic/pessimistic locking.
- Calendar ownership/sharing policy.
- Notification read-state va per-user scope.
- Attachment object policy, private storage va real malware scanning.
- Production config validator, secret scanner, dependency audit CI.
- Token expiration/rotation va session management.
- FormRequestlar: pagination, enum/status, morph type/id pair va foreign-ID organization consistency.
- Multi-tenant, concurrency, queue retry/idempotency, external failure va large-file tests.

## 15. Production Risks

Release blockerlar: C-01 dan C-06 gacha; H-01 dan H-09 gacha; production envdagi debug va TLS-off holati. Queue worker/scheduler/process supervisor, backup/restore drill, log redaction/retention, HTTPS/HSTS va private storage konfiguratsiyasi deploy checklistda tasdiqlanmaguncha release qilinmasin.

## 16. Tuzatish tartibi

### P0 — darhol, deployni bloklaydi

1. Public local registrationni o'chirish; username-based superadmin bypassni olib tashlash.
2. LDAP/Exchange TLS certificate validationni yoqish va credentiallarni rotate qilish.
3. MariaDB tenant scope'ni barcha org modellari/querylariga qo'llash; cross-tenant testlar.
4. Notification, approval, calendar, service request, ticket SLA, profile summary va web comment IDORlarini policy bilan yopish.
5. Attachmentlarni private storagega ko'chirish va direct public accessni yo'q qilish.

### P1 — release oldidan

1. Dependencylarni patched versiyalarga yangilash; Composer/npm audit zero-high gate.
2. Business calendar write flowini transaction va DTO bilan tuzatish.
3. Event/listener graphni aniq ro'yxatdan o'tkazish; placeholder capabilitylarni implement qilish yoki o'chirish.
4. Production env hardening: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS URL, secure cookies, TLS verify, config/route cache.
5. Sanctum TTL/rotation va logout-all/session inventory.

### P2 — barqarorlik va performance

1. Queue retry/backoff/timeout/idempotency/afterCommit.
2. N+1 va aggregate query optimizatsiyasi; real hajmda `EXPLAIN`/load test.
3. FormRequest va tenant-aware validation; avatar/upload limitlari.
4. Critical flow feature/integration testsini kengaytirish.

### P3 — maintainability

1. God controller va route closurelarni use-case/service/policylarga ajratish.
2. Canonical model namespace, enum/status mapping va response contractlarni standartlashtirish.
3. README/deployment/runbook/incident recovery hujjatlari; dead scaffold/skeletonlarni tozalash.

## Tekshiruv natijalari

- `php -l`: 345/345 PHP fayl o'tdi.
- `php artisan test`: 28 passed, 74 assertions.
- `php artisan route:list --except-vendor`: 303 route.
- `php artisan migrate:status`: 36/36 `Ran`.
- `npm run build`: muvaffaqiyatli (`tsc -b && vite build`).
- `npm run lint`: muvaffaqiyatsiz — local ESLint module yo'q.
- `composer validate --strict`: valid.
- `composer audit --locked`: 10 advisory (`league/commonmark 2.8.3`).
- frontend `npm audit --package-lock-only`: 5 advisory (2 high, 3 moderate).
- `php artisan event:list`: faqat Telegram sync listenerlari custom Ticket eventlariga ulangan.

Audit davomida application source code o'zgartirilmadi. Tavsiya etilgan patchlar avval P0 testlari bilan kichik, alohida commitlarda bajarilishi kerak.
