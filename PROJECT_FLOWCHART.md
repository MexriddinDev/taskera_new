# XB Taskera — loyihaning to'liq flowchart kodi

Barcha diagrammalar **Lucidchart Mermaid** dialektida yozilgan:
`graph TD` / `graph LR`, tirnoqsiz label, `-- matn -->` ko'rinishidagi bog'lanish.

**Qanday ishlatiladi:** Lucidchart → Insert → Diagram as Code → Mermaid →
kerakli blokdagi kodni ```` ```mermaid ```` va ```` ``` ```` belgilarisiz nusxalab qo'ying.
Har bir diagramma mustaqil, bittalab import qilinadi.

Diagrammalar kod bazasidan o'qib chiqilgan haqiqiy oqimni aks ettiradi:
`backend/routes/api.php`, `backend/app/Modules/**`, `backend/app/Services/**`,
`frontend/src/App.tsx`, `frontend/src/shared/**`.

## Mundarija

1. [Tizim konteksti](#1-tizim-konteksti)
2. [Backend qatlamlari](#2-backend-qatlamlari)
3. [HTTP so'rov konveyeri](#3-http-sorov-konveyeri)
4. [Frontend yuklanish va marshrutlash](#4-frontend-yuklanish-va-marshrutlash)
5. [Frontend API qatlami](#5-frontend-api-qatlami)
6. [Login oqimi](#6-login-oqimi)
7. [Pochta AD hisobi ochish oqimi](#7-pochta-ad-hisobi-ochish-oqimi)
8. [Zayavka yaratish oqimi](#8-zayavka-yaratish-oqimi)
9. [Zayavka hayot sikli](#9-zayavka-hayot-sikli)
10. [Zayavkani biriktirish oqimi](#10-zayavkani-biriktirish-oqimi)
11. [Holatni o'zgartirish oqimi](#11-holatni-ozgartirish-oqimi)
12. [SLA hisoblash oqimi](#12-sla-hisoblash-oqimi)
13. [Izoh va biriktirma oqimi](#13-izoh-va-biriktirma-oqimi)
14. [Domen hodisalari va tinglovchilar](#14-domen-hodisalari-va-tinglovchilar)
15. [Telegram webhook oqimi](#15-telegram-webhook-oqimi)
16. [Telegram bot suhbat mashinasi](#16-telegram-bot-suhbat-mashinasi)
17. [Bildirishnoma oqimi](#17-bildirishnoma-oqimi)
18. [Cisco Finesse qo'ng'iroq oqimi](#18-cisco-finesse-qongiroq-oqimi)
19. [RBAC huquq tekshiruvi](#19-rbac-huquq-tekshiruvi)
20. [Ma'lumotlar bazasi bloklari](#20-malumotlar-bazasi-bloklari)
21. [Umumiy end-to-end oqim](#21-umumiy-end-to-end-oqim)

---

## 1. Tizim konteksti

```mermaid
graph TD
  U1[Xodim / murojaatchi] --> FE
  U2[Qollab-quvvatlash xodimi] --> FE
  U3[Boshqaruv / Admin] --> FE
  U4[Telegram foydalanuvchi] --> TG

  subgraph Mijoz qatlami
    FE[React SPA / Vite / TypeScript]
    TG[Telegram bot]
  end

  subgraph Laravel backend
    API[REST API /api/v1]
    MOD[Modullar DDD]
    QUEUE[Navbat Jobs]
    API --> MOD
    MOD --> QUEUE
  end

  subgraph Malumotlar
    PG[PostgreSQL]
    FILES[Storage disk public yoki local]
    CACHE[Cache monitoring.version va SMS kod]
  end

  subgraph Tashqi tizimlar
    AD[Active Directory LDAP]
    EXCH[Exchange pochta qutisi]
    HR[HR API hr_emps]
    SMS[SMS gateway]
    TGAPI[Telegram Bot API]
    FIN[Cisco Finesse]
  end

  FE -- Bearer token --> API
  TG -- webhook --> API
  MOD --> PG
  MOD --> FILES
  MOD --> CACHE
  MOD --> AD
  MOD --> EXCH
  MOD --> HR
  MOD --> SMS
  QUEUE --> TGAPI
  MOD --> FIN
```

---

## 2. Backend qatlamlari

```mermaid
graph TD
  subgraph Presentation
    C1[Http Controllers]
    C2[Http Requests]
    C3[Console commands]
  end

  subgraph Application
    A1[Use-case servislari]
  end

  subgraph Domain
    D1[Domain Services]
    D2[Domain Events]
    D3[Repository interfeyslari]
  end

  subgraph Infrastructure
    I1[Eloquent modellar]
    I2[Repository implementatsiyasi]
    I3[Listeners]
    I4[Jobs]
    I5[Integrations tashqi API klientlar]
  end

  C2 --> C1
  C1 --> A1
  C3 --> A1
  A1 --> D1
  C1 --> D1
  D1 --> D3
  D1 --> D2
  D3 -- AppServiceProvider bind --> I2
  I2 --> I1
  D2 --> I3
  I3 --> I4
  I4 --> I5

  subgraph Mavjud modullar
    M1[Ticketing]
    M2[Identity]
    M3[Organization]
    M4[Asset]
    M5[Knowledge]
    M6[Problem]
    M7[Change]
    M8[ServiceCatalog]
    M9[Workflow]
    M10[Automation]
    M11[Notification]
    M12[Integration]
    M13[Telegram]
    M14[Audit]
  end
```

---

## 3. HTTP so'rov konveyeri

```mermaid
graph TD
  REQ[HTTP sorov] --> ROUTE{Marshrut topildimi?}
  ROUTE -- yoq --> E404[404 JSON endpoint topilmadi]
  ROUTE -- ha --> ORG[SetOrganizationContextMiddleware]
  ORG --> ORGSET[pgsql SET LOCAL app.current_organization_id]
  ORGSET --> SEC[SecurityHeadersMiddleware]
  SEC --> THR{Rate limiter bormi?}
  THR -- login 5 daqiqada --> T1
  THR -- sms 3 daqiqada --> T1
  THR -- yoq --> AUTHQ
  T1{Limit oshdimi?} -- ha --> E429[429 JSON]
  T1 -- yoq --> AUTHQ
  AUTHQ{auth sanctum kerakmi?} -- ha --> TOK{Bearer token yaroqlimi?}
  AUTHQ -- yoq --> CTRL
  TOK -- yoq --> E401[401 JSON tizimga kirilmagan]
  TOK -- ha --> PERM
  PERM{permission middleware bormi?} -- ha --> PCHK[CheckPermissionMiddleware]
  PERM -- yoq --> CTRL
  PCHK --> PRES{Huquq bormi yoki Super Admin?}
  PRES -- yoq --> E403[403 JSON huquq yetarli emas]
  PRES -- ha --> CTRL[Controller]
  CTRL --> SRV[Domain servis]
  SRV --> DBTX[DB tranzaksiya]
  DBTX --> EVT[Domen hodisasi]
  DBTX --> RESP[JSON Resource javob]
```

---

## 4. Frontend yuklanish va marshrutlash

```mermaid
graph TD
  BOOT[main.tsx] --> APP[App.tsx]
  APP --> PROV[I18nProvider va ErrorBoundary va QueryClientProvider]
  PROV --> BR[BrowserRouter]
  BR --> R{Marshrut turi}

  R -- /login --> LP[LoginPage]
  R -- /ad-account --> ADP[AdAccountCreatePage]
  R -- boshqa --> PR[ProtectedRoute]

  PR --> AUTH{Token va user bormi?}
  AUTH -- yoq --> TOLOGIN[Navigate /login]
  AUTH -- ha --> LAYOUT[MainLayout Navbar Outlet ToastContainer]

  LAYOUT --> ROOT{Yol}
  ROOT -- root / --> RR[RootRedirect homePathFor]
  ROOT -- profile, task detali, knowledge, catalog, approvals, cisco-call --> FREE[Qoshimcha guardsiz sahifalar]
  ROOT -- /requests --> OWN[OwnRequestsRouteGuard]
  ROOT -- qolganlari --> GUARD[PermissionRouteGuard]

  OWN --> OWNQ{Super Admin yoki tickets.view_own?}
  OWNQ -- ha --> MYREQ[MyRequestsPage]
  OWNQ -- yoq --> STAFFHOME[Navigate staffHomePath]

  GUARD --> SU{Super Admin?}
  SU -- ha --> PAGE
  SU -- yoq --> STAFFQ{requireStaff va isStaff emasmi?}
  STAFFQ -- ha --> NONSTAFF[Navigate nonStaffHomePath]
  STAFFQ -- yoq --> PERMQ{can permission?}
  PERMQ -- yoq --> HOME[Navigate bosh sahifaga]
  PERMQ -- ha --> PAGE[Soralgan sahifa]

  PAGE --> LAZY[React.lazy chunk yuklanadi]
  LAZY --> QUERY[useQuery va axiosClient va api v1]
```

### Himoyalangan sahifalar va huquqlar

```mermaid
graph LR
  G[PermissionRouteGuard] --> P1[dashboard : dashboard.view va staff]
  G --> P2[support-panel : support_panel.view va staff]
  G --> P3[tasks, my-tasks, problems, changes, automation, itsm-settings : staff]
  G --> P4[assets : assets.view yoki assets.manage]
  G --> P5[sla-policies : sla.manage]
  G --> P6[team-workload : team_workload.view yoki tickets.view]
  G --> P7[users : users.view, users.manage yoki stats.view]
  G --> P8[monitoring : monitoring.view]
  G --> P9[stats : stats.view]
  G --> P10[permits : permits.manage]
  G --> P11[rbac : roles.manage]
  G --> P12[integrations-map : integrations.manage]
  G --> P13[audit : audit.view]
```

---

## 5. Frontend API qatlami

```mermaid
graph TD
  CALL[Repo metodi HttpTaskRepo yoki HttpAuthRepo] --> REQI[Request interceptor]
  REQI --> TOKQ{localStorage auth_token bormi?}
  TOKQ -- ha --> SETH[Authorization Bearer token]
  TOKQ -- yoq --> FD
  SETH --> FD{data FormData mi?}
  FD -- ha --> MP[Content-Type ochiriladi va timeout 10 daqiqa]
  FD -- yoq --> JSONH[JSON va timeout 15 soniya]
  MP --> SEND
  JSONH --> SEND[Sorov yuboriladi]

  SEND --> RESP{Javob}
  RESP -- 2xx --> OK[Malumot qaytadi va React Query keshga tushadi]
  RESP -- 401 --> L401[Token va user ochiriladi]
  L401 --> EVT[window event auth unauthorized]
  EVT --> LOGOUT[useAuthStore logout va login sahifasi]
  RESP -- 404 --> E404[AppError notFound]
  RESP -- 429 --> E429[Retry-After asosida kutish xabari]
  RESP -- boshqa xato --> EGEN[AppError message va status]
  RESP -- javob kelmadi --> ENET[AppError tarmoq xatosi 503]

  OK --> UI[Sahifa render]
  E404 --> TOAST[Toast xabari]
  E429 --> TOAST
  EGEN --> TOAST
  ENET --> TOAST
```

---

## 6. Login oqimi

```mermaid
graph TD
  S[POST api v1 auth login] --> RL{throttle login 5 daqiqada}
  RL -- oshdi --> R429[429 juda kop urinish]
  RL -- ok --> V[Validatsiya username va password]
  V --> NORM[username kichik harfga va UPN domeni olib tashlanadi]
  NORM --> FIND[User where username]
  FIND --> SRC{auth_source LOCAL mi?}

  SRC -- ha --> HASH{Hash check mos keldimi?}
  HASH -- yoq --> E422[422 parol notogri]
  HASH -- ha --> TOKEN

  SRC -- yoq yoki user topilmadi --> ADC[AdAuthService authenticate]
  ADC --> ADERR{LDAP ulanish xatosimi?}
  ADERR -- ha --> E503[503 AD server bilan boglanib bolmadi]
  ADERR -- yoq --> ADOK{Autentifikatsiya muvaffaqiyatlimi?}

  ADOK -- yoq --> LOOK[lookupByUsername AD da bormi]
  LOOK --> EXQ{AD da ham DB da ham yoqmi?}
  EXQ -- ha --> ENF[422 user_not_found va pochta ochish taklifi]
  EXQ -- yoq --> E401[401 parol notogri]

  ADOK -- ha --> ENB{AD akkaunt faolmi?}
  ENB -- yoq --> E403[403 akkaunt bloklangan]
  ENB -- ha --> PROV[AdUserProvisionService findOrProvision]
  PROV --> PROVD[Employee va User yaratiladi yoki yangilanadi]
  PROVD --> TOKEN[Sanctum token createToken web-sites]

  TOKEN --> AUDIT[AuditLogger USER_LOGIN]
  AUDIT --> OUT[200 token va UserResource]
  OUT --> FEST[Frontend useAuthStore setAuth va localStorage]
```

---

## 7. Pochta AD hisobi ochish oqimi

```mermaid
graph TD
  ST[Sahifa ad-account yoki botda royxatdan otish] --> S1[1-bosqich PINFL kiritiladi]
  S1 --> C1[POST ad-account check-employee throttle 10 daqiqada]
  C1 --> HR[EmployeeCheckService findByPinfl va hr_emps API]
  HR --> F1{Xodim topildimi?}
  F1 -- yoq --> X1[Xato xodim topilmadi]
  F1 -- ha --> EL{eligibilityErrors boshmi?}
  EL -- yoq --> X2[Xato holat yoki malumot toliq emas]
  EL -- ha --> S2[2-bosqich BXM kodi]

  S2 --> C2[POST ad-account check-bxm]
  C2 --> BXMQ{API dagi bxm_code mos keldimi?}
  BXMQ -- yoq --> X3[Xato BXM kod notogri]
  BXMQ -- ha --> ROT{Rotatsiya API BXM va Exchange BXM farq qiladimi?}
  ROT -- ha --> S3
  ROT -- yoq --> S3[3-bosqich telefon raqami]

  S3 --> C3[POST ad-account send-code throttle sms 3 daqiqada]
  C3 --> PHQ{Telefon HR dagi bilan mos keldimi?}
  PHQ -- yoq --> X4[Xato raqam mos emas]
  PHQ -- ha --> GEN[Kod generatsiya va sms_codes ga hash bilan yoziladi TTL 3 daqiqa]
  GEN --> SMS[SmsGatewayService send]
  SMS --> S4[4-bosqich SMS kod tasdiqlash]

  S4 --> C4[POST ad-account verify-code]
  C4 --> CQ{Kod togri va muddati otmaganmi?}
  CQ -- yoq --> X5[Xato kod notogri yoki eskirgan]
  CQ -- ha --> TKN[verification_token beriladi]
  TKN --> S5[5-bosqich Exchange]

  S5 --> C5[POST ad-account exchange]
  C5 --> EXC[ExchangeMailService create AD user va pochta qutisi]
  EXC --> EXQ{Yaratildimi?}
  EXQ -- yoq --> X6[Xato Exchange xatosi]
  EXQ -- ha --> SAVE[ad_accounts login va shifrlangan parol]
  SAVE --> SHOW[Ekranga login email va parol chiqariladi]

  ROT -- ha --> LINK[POST ad-account link-bxm boshqa BXM OU ga kochirish]
  S5 --> RESETP[POST ad-account reset-password parolni tiklash]
```

---

## 8. Zayavka yaratish oqimi

```mermaid
graph TD
  UI[CreateTaskModal yoki Telegram bot] --> POST[POST api v1 tickets]
  POST --> AUTH{Foydalanuvchi autentifikatsiyalanganmi?}
  AUTH -- yoq --> E401[401]
  AUTH -- ha --> RATE{Bahosiz bajarilgan zayavka bormi status 7 yoki 8}
  RATE -- ha --> E422[422 avval eski zayavkani baholang]
  RATE -- yoq --> VAL[StoreTicketRequest validatsiyasi]

  VAL --> ADL[AD lookup tranzaksiyadan TASHQARIDA]
  ADL --> ADQ{AD javob berdimi?}
  ADQ -- ha --> SYNC[AdUserProvisionService employee yangilanadi]
  ADQ -- yoq --> WARN[Log warning va DB dagi oxirgi malumot ishlatiladi]
  SYNC --> TX
  WARN --> TX[DB transaction boshlanadi]

  TX --> NUM[ticketRepository nextNumber]
  NUM --> PREFILL[employees departments positions dan avtomatik toldirish]
  PREFILL --> PRIO[priorityFromSlaRule muhimlik SLA qoidasidan olinadi]
  PRIO --> CAT[Kategoriya nomi target_department dan aniqlanadi]
  CAT --> CREATE[Ticket create status 1 NEW va source 1 WEB]
  CREATE --> META[metadata device audio_url screenshot_url video_url]
  META --> FILES{Fayl yuborilganmi?}
  FILES -- ha --> STORE[Storage public va xato bolsa local]
  FILES -- yoq --> HIST
  STORE --> ATT[attachments qatori sha256 mime_type size_bytes]
  ATT --> HIST[ticket_status_history TICKET_CREATED]
  HIST --> EV[event TicketCreated]
  EV --> AUD[AuditLogger TICKET_CREATED]
  AUD --> COMMIT[Tranzaksiya yopiladi]
  COMMIT --> INV[Monitoring kesh versiyasi oshiriladi]
  INV --> OUT[201 TicketResource]
```

---

## 9. Zayavka hayot sikli

```mermaid
graph LR
  N[1 NEW Yangi] --> O[2 OPEN Ochiq]
  N --> A[3 ASSIGNED Biriktirilgan]
  O --> A
  N -- biriktirish --> P[4 IN_PROGRESS Jarayonda]
  O -- biriktirish --> P
  A -- biriktirish --> P
  P --> WU[5 WAITING_USER SLA toxtaydi]
  P --> WV[6 WAITING_VENDOR SLA toxtaydi]
  WU --> P
  WV --> P
  P --> R[7 RESOLVED Hal qilindi]
  R --> CL[8 CLOSED Yopildi]
  P --> RJ[9 REJECTED Rad etildi]
  N --> CN[10 CANCELLED Bekor qilindi]
  O --> CN
  P --> CN
  R -- qaytarish --> P

  CL --> T1[Terminal holat]
  RJ --> T1
  CN --> T1
```

> `status_group`: OPEN — 1, 2, 3 · IN_PROGRESS — 4 · PENDING — 5, 6 va `pauses_sla` ·
> RESOLVED — 7 · CLOSED — 8, 9, 10 va `is_terminal`.
> O'tishlar `ticket_status_transitions` jadvalidan boshqariladi: qator mavjud bo'lsa va
> `is_active = false` bo'lsa o'tish taqiqlanadi; `requires_comment = true` bo'lsa izoh majburiy.

---

## 10. Zayavkani biriktirish oqimi

```mermaid
graph TD
  S[POST tickets ID assign] --> SRV[AssignTicketService execute]
  SRV --> TX[DB transaction va getForUpdate qulfi]
  TX --> SAME{Ijrochi guruh va holat ozgarmadimi?}
  SAME -- ha --> NOOP[Hech narsa yozilmaydi va takroriy bildirishnoma oldi olinadi]
  SAME -- yoq --> REASON{Boshqa xodimdan olinyaptimi va sabab boshmi?}
  REASON -- ha --> VEX[ValidationException sabab majburiy]
  REASON -- yoq --> TEAM{team_id ataylab berilganmi?}
  TEAM -- ha --> SETTEAM[assigned_team_id yangilanadi]
  TEAM -- yoq --> KEEP[Guruh ozgarmaydi va SLA qoidasi saqlanadi]
  SETTEAM --> USER
  KEEP --> USER[assigned_user_id yangilanadi]

  USER --> CHG{Ijrochi almashdimi va started_at bormi?}
  CHG -- ha --> SPENT[spent_minutes hisoblanadi va started_at qayta boshlanadi]
  CHG -- yoq --> FIRST
  SPENT --> FIRST{started_at boshmi va ijrochi bormi?}
  FIRST -- ha --> START[started_at now va SLA qabul bosqichi yopiladi]
  FIRST -- yoq --> STAT
  START --> STAT{Holat 1 yoki 2 yoki 3 mi?}
  STAT -- ha --> INPROG[status_id 4 IN_PROGRESS]
  STAT -- yoq --> SAVE
  INPROG --> SH[ticket_status_history yoziladi]
  SH --> SAVE[Ticket saqlanadi]
  SAVE --> AH[ticket_assignment_history yoziladi]
  AH --> E1[event TicketAssigned]
  E1 --> E2{Holat ozgardimi?}
  E2 -- ha --> E3[event TicketStatusChanged]
  E2 -- yoq --> DONE[Javob]
  E3 --> DONE
```

---

## 11. Holatni o'zgartirish oqimi

```mermaid
graph TD
  S[POST tickets ID transition] --> SRV[TransitionTicketService execute]
  SRV --> TX[DB transaction va getForUpdate]
  TX --> DIFF{from_status va to_status farq qiladimi?}
  DIFF -- yoq --> SAVE
  DIFF -- ha --> RULE[ticket_status_transitions dan qoida izlanadi]
  RULE --> ACT{Qoida bor va is_active false mi?}
  ACT -- ha --> DEX[DomainException otish taqiqlangan]
  ACT -- yoq --> CMT{requires_comment va sabab boshmi?}
  CMT -- ha --> IEX[InvalidArgumentException izoh shart]
  CMT -- yoq --> SAVE[status_id yangilanadi va saqlanadi]
  SAVE --> HIST[ticket_status_history STATUS_TRANSITION va correlation_id]
  HIST --> EV[event TicketStatusChanged]
  EV --> LIS[SyncTelegramThreadListener va Telegram xabari]
  LIS --> OUT[Javob]
```

---

## 12. SLA hisoblash oqimi

```mermaid
graph TD
  T[TicketSlaService forTicket] --> R1{tickets.sla_rule_id bormi shablon tanlanganmi?}
  R1 -- ha --> USE1[Aynan osha qoida ishlatiladi]
  R1 -- yoq --> R2{Guruhda umumiy qoida bormi priority_id null}
  R2 -- ha --> USE2[Guruhning umumiy qoidasi]
  R2 -- yoq --> USE3[Tizim standarti qabul 15 daqiqa ishlash 30 daqiqa]

  USE1 --> MULTI
  USE2 --> MULTI
  USE3 --> MULTI{Bir nechta qoida mos keldimi?}
  MULTI -- ha --> STRICT[Eng qattigi va eng qisqa muddatlisi tanlanadi]
  MULTI -- yoq --> CALC
  STRICT --> CALC[Muddatlar hisoblanadi]

  CALC --> ACC[Qabul muddati created_at dan started_at gacha]
  ACC --> WH[Ish vaqti oynasi 09:00 dan 18:00 gacha Asia Tashkent]
  WH --> ACCD[Ish vaqtidan tashqaridagi vaqt hisobga olinmaydi]
  ACCD --> WORK[Ishlash muddati started_at dan resolved_at gacha kalendar vaqt]
  WORK --> BREACH{Deadline otdimi?}
  BREACH -- ha --> B1[Buzilgan va kechikish har safar qayta hisoblanadi]
  BREACH -- yoq --> B2[Muddat ichida]
  B1 --> STATS[breachStatsByTeam panel korsatkichlari]
  B2 --> STATS
```

---

## 13. Izoh va biriktirma oqimi

```mermaid
graph TD
  subgraph Izohlar
    A1[POST tickets ID comments] --> A2[AddCommentService]
    A2 --> A3[comments comment_type 1 PUBLIC yoki 2 INTERNAL]
    A3 --> A4[event CommentAdded]
    A4 --> A5[SyncTelegramThreadListener murojaatchiga xabar]
  end

  subgraph Biriktirmalar
    B1[POST attachments upload] --> B2[StoreAttachmentService]
    B2 --> B3{public diskka yozildimi?}
    B3 -- yoq --> B4[local diskka yoziladi]
    B3 -- ha --> B5[storage_disk public]
    B4 --> B6[attachments qatori sha256 mime_type size_bytes]
    B5 --> B6
    B6 --> B7[event AttachmentStored]
  end

  subgraph Yuklab olish IDOR himoyasi
    C1[AttachmentResource 30 daqiqalik imzolangan havola beradi]
    C1 --> C2[GET attachments ID download]
    C2 --> C3{signed relative imzo togrimi?}
    C3 -- yoq --> C4[403]
    C3 -- ha --> C5[Fayl oqimi qaytariladi]
  end
```

---

## 14. Domen hodisalari va tinglovchilar

```mermaid
graph TD
  E1[TicketCreated] --> L0
  E2[TicketStatusChanged] --> L0
  E3[TicketAssigned] --> L0
  E5[CommentAdded] --> L0[SyncTelegramThreadListener royxatdan otgan]
  E4[TicketPriorityChanged] --> NOREG
  E6[AttachmentStored] --> NOREG[Hozircha hech kim tinglamaydi]

  L0 --> Q[ShouldQueue navbatga tushadi]
  Q --> TN[TelegramNotifierService va Telegram Bot API]

  subgraph Kodda mavjud lekin ulanmagan tinglovchilar
    R1[AutoClassifyTicketListener]
    R2[BroadcastTicketCreatedListener]
    R3[RecalculateQueueMetricsListener]
    R4[RecordStatusHistoryListener]
    R5[RecordAssignmentHistoryListener]
    R6[ScanAttachmentListener]
    R7[GeneratePreviewListener]
    R8[NotifyAssigneeListener]
    R9[NotifyDispatcherListener]
    R10[NotifyStakeholdersListener]
    R11[RunAutomationRulesListener]
    R12[BlockTelegramAccountListener]
  end

  NOREG --> R1
```

> Eslatma: hozirda faqat `SyncTelegramThreadListener` `AppServiceProvider::boot()` ichida
> `Event::listen` orqali ulangan. Qolgan tinglovchilar fayl sifatida mavjud, lekin
> ro'yxatdan o'tmagan — `bootstrap/app.php` da `withEvents()` yo'q, shuning uchun avtomatik
> topilmaydi. Status va biriktirish tarixi hozircha to'g'ridan-to'g'ri servislar ichida yoziladi.

---

## 15. Telegram webhook oqimi

```mermaid
graph TD
  TG[Telegram Bot API] --> WH[POST api v1 telegram webhook botUsername]
  WH --> UID{update_id bormi?}
  UID -- yoq --> B400[400 invalid]
  UID -- ha --> BOT[telegram_bots dan bot izlanadi]
  BOT --> BQ{Bot topildimi va ochirilmaganmi?}
  BQ -- yoq --> B404[404 bot_not_found]
  BQ -- ha --> SEC{Secret token hash mos keldimi?}
  SEC -- yoq --> B401[401 unauthorized]
  SEC -- ha --> INS[telegram_updates ga insertOrIgnore idempotent]
  INS --> DUP{status PROCESSED mi?}
  DUP -- ha --> BDUP[duplicate qayta ishlanmaydi]
  DUP -- yoq --> JOB[ProcessTelegramUpdateJob navbatga tushadi]
  JOB --> CONV[BotConversationService handle]
  CONV --> ACT[Javob TelegramApiClient orqali yuboriladi]

  POLL[PollTelegramUpdatesCommand webhook ornida long polling] --> JOB
```

---

## 16. Telegram bot suhbat mashinasi

```mermaid
graph TD
  IDLE[IDLE asosiy menyu] --> MENU{Tanlov}

  MENU -- Royxatdan otish --> REG1[REG_AWAIT_PINFL]
  REG1 --> REG2[REG_AWAIT_BXM]
  REG2 --> REG3[REG_AWAIT_PHONE]
  REG3 --> REG4[REG_AWAIT_CODE]
  REG4 --> REGOK[Exchange login va parol yuboriladi]
  REGOK --> IDLE

  MENU -- Kirish --> AC[AWAIT_CONTACT telefon soraladi]
  AC --> AU[AWAIT_USERNAME]
  AU --> AP[AWAIT_PASSWORD]
  AP --> VER{VerifyBotLoginService AD tekshiruvi}
  VER -- xato --> AU
  VER -- ok --> LINKED[Telegram akkaunt userga boglanadi]
  LINKED --> IDLE

  MENU -- Yangi zayavka --> T1[AWAIT_TICKET_TEXT]
  T1 --> T2[AWAIT_TICKET_TEAM sahifalangan royxat 8 tadan]
  T2 --> T3[AWAIT_TICKET_TEMPLATE shablon va SLA qoidasi]
  T3 --> T4[AWAIT_TICKET_CONFIRM]
  T4 -- tasdiq --> TCREATE[TicketController store chaqiriladi]
  TCREATE --> IDLE

  MENU -- Mening zayavkalarim --> MY[Royxat va inline tugmalar]
  MENU -- Ochiq zayavkalar --> OPEN[Ochiq navbat xodimlar uchun]
  MENU -- Mening vazifalarim --> TASKS[Biriktirilgan zayavkalar]
  MENU -- Statistika --> STATS[Korsatkichlar]

  OPEN -- Qabul qilish --> ASSIGN[AssignTicketService]
  TASKS -- Yopish --> SOL[AWAIT_SOLUTION_COMMENT]
  SOL --> TRANS[TransitionTicketService 7 RESOLVED]
  TASKS -- Rad etish --> REJ[AWAIT_REJECT_REASON]
  REJ --> TRANS9[TransitionTicketService 9 REJECTED]
  TASKS -- Qaytarish --> RET[AWAIT_TICKET_RETURN_REASON]
  MY -- Baholash 1 dan 5 gacha --> RATE[AWAIT_RATING_FEEDBACK]
  RATE --> SAVER[client_rating saqlanadi]
  SAVER --> IDLE
  ASSIGN --> IDLE
  TRANS --> IDLE
  TRANS9 --> IDLE
  RET --> IDLE
```

---

## 17. Bildirishnoma oqimi

```mermaid
graph TD
  SRC[Hodisa yoki servis chaqiruvi] --> ORCH[NotificationOrchestrator send]
  ORCH --> DBI[notifications qatori event_type template_code correlation_id]
  DBI --> CH{Kanal}
  CH -- TELEGRAM --> J1[SendTelegramNotificationJob]
  CH -- EMAIL --> J2[SendEmailNotificationJob]
  J1 --> Q[Navbat ishchisi]
  J2 --> Q
  Q --> DEL[notification_deliveries yetkazish holati]
  DEL --> EXT[Telegram Bot API yoki SMTP]

  subgraph Bildirishnoma API
    A1[GET notifications]
    A2[GET notifications ID]
    A3[POST notifications ID read]
    A4[GET va PUT notification-preferences]
    A5[notification-templates CRUD roles.manage yoki users.manage]
  end
```

---

## 18. Cisco Finesse qo'ng'iroq oqimi

```mermaid
graph TD
  SET[POST finesse account login va parol saqlanadi] --> ACC[finesse_accounts shifrlangan parol]
  CALL[POST tickets ID call] --> ACCQ{Hisob saqlanganmi?}
  ACCQ -- yoq --> E1[422 avval Cisco Call bolimida saqlang]
  ACCQ -- ha --> TQ{Zayavka topildimi organization boyicha?}
  TQ -- yoq --> E2[404]
  TQ -- ha --> NUM[Raqam avval requesterEmployee phone keyin initiator_phone]
  NUM --> NQ{Raqam bormi?}
  NQ -- yoq --> E3[422 telefon raqami yoq]
  NQ -- ha --> USER[FinesseService user jonli extension va state]
  USER --> UQ{Finesse javob berdimi?}
  UQ -- yoq --> E4[422 Finesse bilan boglanib bolmadi]
  UQ -- ha --> SQ{extension bosh yoki state LOGOUT mi?}
  SQ -- ha --> E5[422 Finesse ga operator sifatida kirmagansiz]
  SQ -- yoq --> UPD[Hisobda extension va last_state yangilanadi]
  UPD --> MC[FinesseService makeCall]
  MC --> MQ{Qongiroq boshlandimi?}
  MQ -- yoq --> E6[422 qongiroq amalga oshmadi]
  MQ -- ha --> OK[200 qongiroq ketmoqda]

  OK --> POLL[GET finesse call-active holat tekshiruvi]
  POLL --> DROP[POST finesse drop dialog yopiladi]
  OK --> REC[POST tickets ID call-recording suhbat yozuvi]
  STATUS[GET finesse status agent holati] --> ACC
```

---

## 19. RBAC huquq tekshiruvi

```mermaid
graph TD
  subgraph Malumot modeli
    U[users] --> UR[model_has_roles]
    UR --> RO[roles]
    RO --> RHP[role_has_permissions]
    RHP --> PE[permissions]
    ADG[ad_group_roles AD guruhi boyicha rol] --> RO
  end

  REQ[Himoyalangan endpoint] --> MW[CheckPermissionMiddleware]
  MW --> AUTHQ{Foydalanuvchi bormi?}
  AUTHQ -- yoq --> E401[401]
  AUTHQ -- ha --> SA{isSuperAdmin?}
  SA -- ha --> PASS[Otkaziladi]
  SA -- yoq --> LOOP{Korsatilgan huquqlardan BIRORTASI bormi?}
  LOOP -- ha --> PASS
  LOOP -- yoq --> E403[403 huquq yetarli emas]

  subgraph Huquq guruhlari
    G1[tickets.view tickets.create tickets.assign tickets.view_own]
    G2[dashboard.view support_panel.view monitoring.view stats.view]
    G3[roles.manage users.manage departments.manage]
    G4[services.manage sla.manage assets.manage knowledge.manage]
    G5[problems.manage changes.manage changes.approve]
    G6[workflows.manage automation.manage integrations.manage]
    G7[audit.view permits.manage]
  end
```

---

## 20. Ma'lumotlar bazasi bloklari

```mermaid
graph TD
  subgraph Reference migratsiya 000010
    R1[ticket_statuses ticket_status_transitions ticket_priorities ticket_sources]
    R2[comment_types comment_sources attachment_types notification_channels]
    R3[asset_types asset_statuses relationship_types article_types]
    R4[locales timezones employment_statuses workflow_entity_types integration_types]
  end

  subgraph Organization va HR 000020
    O1[organizations regions branches departments positions employees]
  end

  subgraph Identity va RBAC 000030 va 000040
    I1[users personal_access_tokens]
    I2[roles permissions role_has_permissions model_has_roles ad_group_roles]
  end

  subgraph ITSM master 000050
    M1[categories services service_offerings locations resolution_codes teams]
  end

  subgraph Ticketing 000060 va 000070
    T1[tickets]
    T2[ticket_status_history ticket_assignment_history]
    T3[comments attachments ticket_templates tags]
  end

  subgraph SLA 000080 va keyingi migratsiyalar
    S1[sla_rules team_id priority_id is_default]
  end

  subgraph Notification 000090
    N1[notifications notification_deliveries notification_templates user_notification_preferences]
  end

  subgraph Asset va CMDB 000100
    C1[assets asset_models manufacturers vendors software_products software_licenses]
  end

  subgraph Knowledge 000110
    K1[knowledge_articles article_feedback]
  end

  subgraph Problem Change Catalog 000120
    P1[problems changes maintenance_windows]
    P2[service_catalog_items service_requests approval_requests approval_steps]
  end

  subgraph Workflow va Automation 000130
    W1[workflows workflow_runs workflow_step_runs]
    W2[automation_rules automation_executions]
  end

  subgraph Integration 000140
    GG1[integrations integration_logs webhook_endpoints outbox]
    GG2[telegram_bots telegram_updates telegram akkauntlari]
  end

  subgraph Audit va Security 000150 va 000160 va 000170
    AA1[audit_logs]
    AA2[RLS siyosatlari va partitsiyalash]
    AA3[Full-text indekslar]
  end

  subgraph Qoshimcha migratsiyalar
    X1[sms_codes hashlangan kod va verification_token]
    X2[ad_accounts login va shifrlangan parol]
    X3[finesse_accounts va permits]
  end

  O1 --> I1
  I1 --> T1
  M1 --> T1
  S1 --> T1
  T1 --> N1
  T1 --> AA1
```

---

## 21. Umumiy end-to-end oqim

```mermaid
graph TD
  START[Xodimda muammo paydo boldi] --> CH{Qaysi kanal?}

  CH -- Veb --> W1[Login AD yoki LOCAL]
  CH -- Telegram --> B1[Botga start va telefon tasdiqlash]

  W1 --> W2{AD hisobi bormi?}
  W2 -- yoq --> W3[ad-account PINFL BXM SMS Exchange]
  W3 --> W1
  W2 -- ha --> W4[Token olinadi va bosh sahifaga yonaltiriladi]

  B1 --> B2{Hisob boglanganmi?}
  B2 -- yoq --> B3[Botdan royxatdan otish yoki login]
  B3 --> B4
  B2 -- ha --> B4[Bot menyusi]

  W4 --> CREATE[Zayavka yaratish]
  B4 --> CREATE

  CREATE --> GUARD{Bahosiz eski zayavka bormi?}
  GUARD -- ha --> RATEOLD[Avval eski zayavkaga baho beriladi]
  RATEOLD --> CREATE
  GUARD -- yoq --> NEW[Ticket status 1 NEW va SLA qabul taymeri boshlanadi]

  NEW --> EVC[event TicketCreated va Telegram xabari]
  EVC --> QUEUE[Navbat tasks sahifasi yoki botdagi ochiq zayavkalar]
  QUEUE --> TAKE[Xodim qabul qiladi]
  TAKE --> ASG[AssignTicketService started_at va status 4]
  ASG --> EVA[event TicketAssigned va event TicketStatusChanged]

  EVA --> WORK[Ish jarayoni izohlar biriktirmalar Finesse qongirogi]
  WORK --> PAUSE{Kutish kerakmi?}
  PAUSE -- ha --> WAIT[5 WAITING_USER yoki 6 WAITING_VENDOR SLA toxtaydi]
  WAIT --> WORK
  PAUSE -- yoq --> RESULT{Natija}

  RESULT -- hal qilindi --> SOLVE[Yechim matni va status 7 RESOLVED]
  RESULT -- rad etildi --> REJECT[Sabab va status 9 REJECTED]
  RESULT -- bekor qilindi --> CANCEL[status 10 CANCELLED]

  SOLVE --> NOTIFY[Murojaatchiga Telegram xabari]
  REJECT --> NOTIFY
  NOTIFY --> RATE[Baholash 1 dan 5 gacha va izoh]
  RATE --> CLOSE[status 8 CLOSED]
  CANCEL --> ARCH

  CLOSE --> ARCH[Arxiv]
  ARCH --> METRICS[Korsatkichlar SLA buzilishi xodim yuklamasi monitoring]
  METRICS --> PANELS[Dashboard Support panel Stats Team workload Audit]
```

---

## Manbalar

| Diagramma | Kod bazasidagi manba |
|---|---|
| Marshrutlar va huquqlar | `backend/routes/api.php`, `backend/app/Http/Middleware/CheckPermissionMiddleware.php` |
| Middleware konveyeri | `backend/bootstrap/app.php` |
| Login | `backend/app/Http/Controllers/Api/AuthController.php`, `backend/app/Services/AdAuthService.php` |
| AD pochta ochish | `backend/app/Http/Controllers/Api/AdAccountController.php`, `backend/app/Services/EmployeeCheckService.php`, `SmsGatewayService.php`, `ExchangeMailService.php` |
| Zayavka yaratish | `backend/app/Modules/Ticketing/Presentation/Http/Controllers/TicketController.php` |
| Biriktirish va holat o'tishi | `backend/app/Modules/Ticketing/Domain/Services/AssignTicketService.php`, `TransitionTicketService.php` |
| SLA | `backend/app/Modules/Ticketing/Domain/Services/TicketSlaService.php` |
| Holatlar ro'yxati | `backend/database/seeders/ReferenceDataSeeder.php` |
| Hodisalar va tinglovchilar | `backend/app/Providers/AppServiceProvider.php`, `backend/app/Modules/Ticketing/Domain/Events/` |
| Telegram | `backend/app/Modules/Telegram/**` |
| Bildirishnoma | `backend/app/Modules/Notification/**` |
| Finesse | `backend/app/Http/Controllers/Api/FinesseController.php`, `backend/app/Services/FinesseService.php` |
| Frontend marshrutlar | `frontend/src/App.tsx` |
| Frontend API qatlami | `frontend/src/shared/infrastructure/http/axiosClient.ts` |
| DB bloklari | `backend/database/migrations/` |
