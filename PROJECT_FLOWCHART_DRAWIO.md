# XB Taskera — draw.io CSV diagrammalari

Har bir blok app.diagrams.net uchun tayyor: **Extras → Insert → Advanced → CSV...** →
blokdagi matnni qo'ying → **Import**.

Ustunlar: `oldingi` — oldingi tugun id'si, `ha` / `yoq` — qaror tugunidan chiqadigan tarmoq.

---

## 1. Tizim konteksti

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n1-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,Xodim / murojaatchi,tashqi,,,
2,Qo'llab-quvvatlash xodimi,tashqi,,,
3,Boshqaruv / Admin,tashqi,,,
4,Telegram foydalanuvchi,tashqi,,,
5,React SPA (Vite + TypeScript),jarayon,"1,2,3",,
6,Telegram bot,jarayon,4,,
7,REST API /api/v1,jarayon,"5,6",,
8,Modullar (DDD),jarayon,7,,
9,Navbat (Jobs),jarayon,8,,
10,PostgreSQL,tashqi,8,,
11,Storage disk (public / local),tashqi,8,,
12,"Cache (monitoring.version, SMS kod)",tashqi,8,,
13,Active Directory (LDAP),tashqi,8,,
14,Exchange (pochta qutisi),tashqi,8,,
15,HR API (hr_emps),tashqi,8,,
16,SMS gateway,tashqi,8,,
17,Telegram Bot API,tashqi,9,,
18,Cisco Finesse,tashqi,8,,
```

---

## 2. Backend qatlamlari

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n2-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,Presentation: Http Requests,jarayon,,,
2,Presentation: Http Controllers,jarayon,1,,
3,Presentation: Console commands,jarayon,,,
4,Application: use-case servislari,jarayon,"2,3",,
5,Domain: Services,jarayon,"2,4",,
6,Domain: Repository interfeyslari,jarayon,5,,
7,Domain: Events,jarayon,5,,
8,Infrastructure: Repository implementatsiyasi,tashqi,6,,
9,Infrastructure: Eloquent modellar,tashqi,8,,
10,Infrastructure: Listeners,tashqi,7,,
11,Infrastructure: Jobs,tashqi,10,,
12,Infrastructure: tashqi API klientlar,tashqi,11,,
13,Modullar,jarayon,,,
14,Ticketing,natija,13,,
15,Identity,natija,13,,
16,Organization,natija,13,,
17,Asset,natija,13,,
18,Knowledge,natija,13,,
19,Problem,natija,13,,
20,Change,natija,13,,
21,ServiceCatalog,natija,13,,
22,Workflow,natija,13,,
23,Automation,natija,13,,
24,Notification,natija,13,,
25,Integration,natija,13,,
26,Telegram,natija,13,,
27,Audit,natija,13,,
```

---

## 3. HTTP so'rov konveyeri

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n3-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,HTTP so'rov,jarayon,,,
2,Marshrut topildimi?,qaror,1,,
3,404 endpoint topilmadi,xato,,,2
4,SetOrganizationContextMiddleware,jarayon,,2,
5,SET LOCAL app.current_organization_id,jarayon,4,,
6,SecurityHeadersMiddleware,jarayon,5,,
7,"Rate limiter chegarasi oshdimi? (login 5/daq, sms 3/daq)",qaror,6,,
8,429 Too Many Requests,xato,,7,
9,auth:sanctum talab qilinadimi?,qaror,,,7
10,Bearer token yaroqlimi?,qaror,,9,
11,401 Tizimga kirilmagan,xato,,,10
12,permission middleware bormi?,qaror,,10,
13,CheckPermissionMiddleware,jarayon,,12,
14,Huquq bormi yoki Super Admin?,qaror,13,,
15,403 huquq yetarli emas,xato,,,14
16,Controller,jarayon,,14,"9,12"
17,Domain servis,jarayon,16,,
18,DB tranzaksiya,jarayon,17,,
19,Domen hodisasi,jarayon,18,,
20,JSON Resource javob,natija,18,,
```

---

## 4. Frontend yuklanish va marshrutlash

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n4-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,main.tsx,jarayon,,,
2,App.tsx,jarayon,1,,
3,I18nProvider + ErrorBoundary + QueryClientProvider,jarayon,2,,
4,BrowserRouter,jarayon,3,,
5,Marshrut turi?,qaror,4,,
6,/login - LoginPage,natija,5,,
7,/ad-account - AdAccountCreatePage,natija,5,,
8,Boshqa yo'llar - ProtectedRoute,jarayon,5,,
9,Token va user bormi?,qaror,8,,
10,Navigate /login,xato,,,9
11,MainLayout: Navbar + Outlet + ToastContainer,jarayon,,9,
12,Yo'l turi?,qaror,11,,
13,/ - RootRedirect homePathFor,natija,12,,
14,"Guardsiz: /profile, /task/:id, /knowledge, /catalog, /approvals, /cisco-call",natija,12,,
15,/requests - OwnRequestsRouteGuard,jarayon,12,,
16,Qolganlari - PermissionRouteGuard,jarayon,12,,
17,Super Admin yoki tickets.view_own?,qaror,15,,
18,MyRequestsPage,natija,,17,
19,Navigate staffHomePath,xato,,,17
20,Super Admin?,qaror,16,,
21,requireStaff va isStaff emasmi?,qaror,,,20
22,Navigate nonStaffHomePath,xato,,21,
23,can(permission)?,qaror,,,21
24,Navigate bosh sahifaga,xato,,,23
25,So'ralgan sahifa,jarayon,,"20,23",
26,React.lazy chunk yuklanadi,jarayon,25,,
27,useQuery -> axiosClient -> /api/v1,natija,26,,
```

---

## 5. Himoyalangan sahifalar va huquqlar

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n5-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 30
# levelspacing: 90
# edgespacing: 40
# layout: horizontaltree
id,name,tur,oldingi
1,PermissionRouteGuard,jarayon,
2,/dashboard : dashboard.view + staff,natija,1
3,/support-panel : support_panel.view + staff,natija,1
4,"/tasks, /my-tasks, /problems, /changes, /automation, /itsm-settings : staff",natija,1
5,/assets : assets.view yoki assets.manage,natija,1
6,/sla-policies : sla.manage,natija,1
7,/team-workload : team_workload.view yoki tickets.view,natija,1
8,"/users : users.view, users.manage yoki stats.view",natija,1
9,/monitoring : monitoring.view,natija,1
10,/stats : stats.view,natija,1
11,/permits : permits.manage,natija,1
12,/rbac : roles.manage,natija,1
13,/integrations-map : integrations.manage,natija,1
14,/audit : audit.view,natija,1
```

---

## 6. Frontend API qatlami

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n6-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,Repo metodi: HttpTaskRepo / HttpAuthRepo,jarayon,,,
2,Request interceptor,jarayon,1,,
3,localStorage auth_token bormi?,qaror,2,,
4,Authorization: Bearer token,jarayon,,3,
5,data FormData mi?,qaror,4,,3
6,Content-Type o'chiriladi; timeout 10 daqiqa,jarayon,,5,
7,JSON; timeout 15 soniya,jarayon,,,5
8,So'rov yuboriladi,jarayon,"6,7",,
9,Javob turi?,qaror,8,,
10,2xx - React Query keshga tushadi,natija,9,,
11,401 - token va user o'chiriladi,xato,9,,
12,window event: auth:unauthorized,jarayon,11,,
13,useAuthStore.logout -> /login,xato,12,,
14,404 - AppError.notFound,xato,9,,
15,429 - Retry-After asosida kutish xabari,xato,9,,
16,"Boshqa xato - AppError(message, status)",xato,9,,
17,Javob kelmadi - tarmoq xatosi 503,xato,9,,
18,Sahifa render,natija,10,,
19,Toast xabari,natija,"14,15,16,17",,
```

---

## 7. Login oqimi

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n7-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,POST /api/v1/auth/login,jarayon,,,
2,throttle:login chegarasi oshdimi? (5/daqiqa),qaror,1,,
3,429 juda ko'p urinish,xato,,2,
4,"Validatsiya: username, password",jarayon,,,2
5,username kichik harfga; UPN domeni olib tashlanadi,jarayon,4,,
6,User::where(username),jarayon,5,,
7,auth_source = LOCAL?,qaror,6,,
8,Hash::check mos keldimi?,qaror,,7,
9,422 parol noto'g'ri,xato,,,8
10,AdAuthService::authenticate,jarayon,,,7
11,LDAP ulanish xatosimi?,qaror,10,,
12,503 AD server bilan bog'lanib bo'lmadi,xato,,11,
13,Autentifikatsiya muvaffaqiyatlimi?,qaror,,,11
14,lookupByUsername - AD da bormi?,jarayon,,,13
15,AD da ham DB da ham yo'qmi?,qaror,14,,
16,422 user_not_found - pochta ochish taklifi,xato,,15,
17,401 parol noto'g'ri,xato,,,15
18,AD akkaunt faolmi?,qaror,,13,
19,403 akkaunt bloklangan,xato,,,18
20,AdUserProvisionService::findOrProvision,jarayon,,18,
21,Employee va User yaratiladi yoki yangilanadi,jarayon,20,,
22,Sanctum token: createToken web-sites,jarayon,21,8,
23,AuditLogger: USER_LOGIN,jarayon,22,,
24,200 token + UserResource,natija,23,,
25,Frontend: useAuthStore.setAuth + localStorage,natija,24,,
```

---

## 8. Pochta (AD) hisobi ochish oqimi

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n8-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,/ad-account sahifasi yoki botda ro'yxatdan o'tish,jarayon,,,
2,1-bosqich: PINFL kiritiladi,jarayon,1,,
3,POST /ad-account/check-employee (10/daqiqa),jarayon,2,,
4,EmployeeCheckService::findByPinfl - hr_emps API,jarayon,3,,
5,Xodim topildimi?,qaror,4,,
6,Xato: xodim topilmadi,xato,,,5
7,eligibilityErrors bo'shmi?,qaror,,5,
8,Xato: holat yoki ma'lumot to'liq emas,xato,,,7
9,2-bosqich: BXM kodi,jarayon,,7,
10,POST /ad-account/check-bxm,jarayon,9,,
11,API dagi bxm_code mos keldimi?,qaror,10,,
12,Xato: BXM kod noto'g'ri,xato,,,11
13,Rotatsiya: API BXM va Exchange BXM farq qiladimi?,qaror,,11,
14,3-bosqich: telefon raqami,jarayon,13,,
15,POST /ad-account/send-code (sms 3/daqiqa),jarayon,14,,
16,Telefon HR dagi bilan mos keldimi?,qaror,15,,
17,Xato: raqam mos emas,xato,,,16
18,Kod generatsiya; sms_codes ga hash bilan yoziladi (TTL 3 daqiqa),jarayon,,16,
19,SmsGatewayService::send,jarayon,18,,
20,4-bosqich: SMS kod tasdiqlash,jarayon,19,,
21,POST /ad-account/verify-code,jarayon,20,,
22,Kod to'g'ri va muddati o'tmaganmi?,qaror,21,,
23,Xato: kod noto'g'ri yoki eskirgan,xato,,,22
24,verification_token beriladi,jarayon,,22,
25,5-bosqich: POST /ad-account/exchange,jarayon,24,,
26,ExchangeMailService::create - AD user + pochta qutisi,jarayon,25,,
27,Yaratildimi?,qaror,26,,
28,Xato: Exchange xatosi,xato,,,27
29,ad_accounts: login va shifrlangan parol,jarayon,,27,
30,"Ekranga login, email va parol chiqariladi",natija,29,,
31,POST /ad-account/link-bxm - boshqa BXM OU ga ko'chirish,natija,,13,
32,POST /ad-account/reset-password - parolni tiklash,natija,25,,
```

---

## 9. Zayavka yaratish oqimi

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n9-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,CreateTaskModal yoki Telegram bot,jarayon,,,
2,POST /api/v1/tickets,jarayon,1,,
3,Foydalanuvchi autentifikatsiyalanganmi?,qaror,2,,
4,401,xato,,,3
5,Bahosiz bajarilgan zayavka bormi? (status 7 yoki 8),qaror,,3,
6,422 avval eski zayavkani baholang,xato,,5,
7,StoreTicketRequest validatsiyasi,jarayon,,,5
8,AD lookup - tranzaksiyadan TASHQARIDA,jarayon,7,,
9,AD javob berdimi?,qaror,8,,
10,AdUserProvisionService - employee yangilanadi,jarayon,,9,
11,Log warning - DB dagi oxirgi ma'lumot ishlatiladi,jarayon,,,9
12,DB::transaction boshlanadi,jarayon,"10,11",,
13,ticketRepository->nextNumber,jarayon,12,,
14,"employees, departments, positions dan prefill",jarayon,13,,
15,priorityFromSlaRule - muhimlik SLA qoidasidan,jarayon,14,,
16,Kategoriya target_department dan aniqlanadi,jarayon,15,,
17,Ticket::create - status 1 NEW; source 1 WEB,jarayon,16,,
18,"metadata: device, audio_url, screenshot_url, video_url",jarayon,17,,
19,Fayl yuborilganmi?,qaror,18,,
20,Storage public; xato bo'lsa local,jarayon,,19,
21,"attachments qatori: sha256, mime_type, size_bytes",jarayon,20,,
22,ticket_status_history: TICKET_CREATED,jarayon,21,,19
23,event(TicketCreated),jarayon,22,,
24,AuditLogger: TICKET_CREATED,jarayon,23,,
25,Tranzaksiya yopiladi,jarayon,24,,
26,Monitoring kesh versiyasi oshiriladi,jarayon,25,,
27,201 TicketResource,natija,26,,
```

---

## 10. Zayavka hayot sikli

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n10-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 80
# edgespacing: 40
# layout: horizontalflow
id,name,tur,oldingi
1,1 NEW - Yangi,jarayon,
2,2 OPEN - Ochiq,jarayon,1
3,3 ASSIGNED - Biriktirilgan,jarayon,"1,2"
4,4 IN_PROGRESS - Jarayonda,jarayon,"1,2,3"
5,5 WAITING_USER - SLA to'xtaydi,qaror,4
6,6 WAITING_VENDOR - SLA to'xtaydi,qaror,4
7,7 RESOLVED - Hal qilindi,natija,4
8,8 CLOSED - Yopildi,natija,7
9,9 REJECTED - Rad etildi,xato,4
10,10 CANCELLED - Bekor qilindi,xato,"1,2,4"
11,Terminal holat,tashqi,"8,9,10"
```

---

## 11. Zayavkani biriktirish oqimi

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n11-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,POST /tickets/{id}/assign,jarayon,,,
2,AssignTicketService::execute,jarayon,1,,
3,DB::transaction + getForUpdate qulfi,jarayon,2,,
4,"Ijrochi, guruh va holat o'zgarmadimi?",qaror,3,,
5,Hech narsa yozilmaydi - takroriy bildirishnoma oldi olinadi,natija,,4,
6,Boshqa xodimdan olinyaptimi va sabab bo'shmi?,qaror,,,4
7,ValidationException: sabab majburiy,xato,,6,
8,team_id ataylab berilganmi?,qaror,,,6
9,assigned_team_id yangilanadi,jarayon,,8,
10,Guruh o'zgarmaydi - SLA qoidasi saqlanadi,jarayon,,,8
11,assigned_user_id yangilanadi,jarayon,"9,10",,
12,Ijrochi almashdimi va started_at bormi?,qaror,11,,
13,spent_minutes hisoblanadi; started_at qayta boshlanadi,jarayon,,12,
14,started_at bo'shmi va ijrochi bormi?,qaror,13,,12
15,started_at = now - SLA qabul bosqichi yopiladi,jarayon,,14,
16,Holat 1 / 2 / 3 mi?,qaror,15,,14
17,status_id = 4 IN_PROGRESS,jarayon,,16,
18,ticket_status_history yoziladi,jarayon,17,,
19,Ticket saqlanadi,jarayon,18,,16
20,ticket_assignment_history yoziladi,jarayon,19,,
21,event(TicketAssigned),jarayon,20,,
22,Holat o'zgardimi?,qaror,21,,
23,event(TicketStatusChanged),jarayon,,22,
24,Javob,natija,23,,22
```

---

## 12. Holatni o'zgartirish oqimi

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n12-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,POST /tickets/{id}/transition,jarayon,,,
2,TransitionTicketService::execute,jarayon,1,,
3,DB::transaction + getForUpdate,jarayon,2,,
4,from_status va to_status farq qiladimi?,qaror,3,,
5,ticket_status_transitions dan qoida izlanadi,jarayon,,4,
6,Qoida bor va is_active = false?,qaror,5,,
7,DomainException: o'tish taqiqlangan,xato,,6,
8,requires_comment va sabab bo'shmi?,qaror,,,6
9,InvalidArgumentException: izoh shart,xato,,8,
10,status_id yangilanadi va saqlanadi,jarayon,,,"4,8"
11,ticket_status_history: STATUS_TRANSITION + correlation_id,jarayon,10,,
12,event(TicketStatusChanged),jarayon,11,,
13,SyncTelegramThreadListener -> Telegram xabari,jarayon,12,,
14,Javob,natija,13,,
```

---

## 13. SLA hisoblash oqimi

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n13-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,TicketSlaService::forTicket,jarayon,,,
2,tickets.sla_rule_id bormi? (shablon),qaror,1,,
3,Aynan o'sha qoida ishlatiladi,jarayon,,2,
4,Guruhda umumiy qoida bormi? (priority_id = null),qaror,,,2
5,Guruhning umumiy qoidasi,jarayon,,4,
6,Tizim standarti: qabul 15 daq / ishlash 30 daq,jarayon,,,4
7,Bir nechta qoida mos keldimi?,qaror,"3,5,6",,
8,Eng qattig'i - eng qisqa muddatlisi tanlanadi,jarayon,,7,
9,Muddatlar hisoblanadi,jarayon,8,,7
10,Qabul muddati: created_at dan started_at gacha,jarayon,9,,
11,Ish vaqti oynasi: 09:00-18:00 Asia/Tashkent,jarayon,10,,
12,Ish vaqtidan tashqaridagi vaqt hisobga olinmaydi,jarayon,11,,
13,Ishlash muddati: started_at dan resolved_at gacha,jarayon,12,,
14,Deadline o'tdimi?,qaror,13,,
15,Buzilgan - kechikish har safar qayta hisoblanadi,xato,,14,
16,Muddat ichida,natija,,,14
17,breachStatsByTeam - panel ko'rsatkichlari,natija,"15,16",,
```

---

## 14. Izoh va biriktirma oqimi

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n14-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,POST /tickets/{id}/comments,jarayon,,,
2,AddCommentService,jarayon,1,,
3,comments: 1 PUBLIC yoki 2 INTERNAL,jarayon,2,,
4,event(CommentAdded),jarayon,3,,
5,SyncTelegramThreadListener -> murojaatchiga xabar,natija,4,,
6,POST /attachments/upload,jarayon,,,
7,StoreAttachmentService,jarayon,6,,
8,public diskka yozildimi?,qaror,7,,
9,storage_disk = public,jarayon,,8,
10,local diskka yoziladi,jarayon,,,8
11,"attachments qatori: sha256, mime_type, size_bytes",jarayon,"9,10",,
12,event(AttachmentStored),natija,11,,
13,AttachmentResource 30 daqiqalik imzolangan havola beradi,jarayon,,,
14,GET /attachments/{id}/download,jarayon,13,,
15,signed:relative imzo to'g'rimi?,qaror,14,,
16,403,xato,,,15
17,Fayl oqimi qaytariladi,natija,,15,
```

---

## 15. Domen hodisalari va tinglovchilar

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n15-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 30
# levelspacing: 80
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi
1,TicketCreated,jarayon,
2,TicketStatusChanged,jarayon,
3,TicketAssigned,jarayon,
4,CommentAdded,jarayon,
5,TicketPriorityChanged,jarayon,
6,AttachmentStored,jarayon,
7,SyncTelegramThreadListener - ro'yxatdan o'tgan,natija,"1,2,3,4"
8,ShouldQueue - navbatga tushadi,jarayon,7
9,TelegramNotifierService -> Telegram Bot API,natija,8
10,Hozircha hech kim tinglamaydi,xato,"5,6"
11,Ulanmagan: AutoClassifyTicketListener,tashqi,10
12,Ulanmagan: BroadcastTicketCreatedListener,tashqi,10
13,Ulanmagan: RecalculateQueueMetricsListener,tashqi,10
14,Ulanmagan: RecordStatusHistoryListener,tashqi,10
15,Ulanmagan: RecordAssignmentHistoryListener,tashqi,10
16,Ulanmagan: ScanAttachmentListener,tashqi,10
17,Ulanmagan: GeneratePreviewListener,tashqi,10
18,Ulanmagan: NotifyAssigneeListener,tashqi,10
19,Ulanmagan: NotifyDispatcherListener,tashqi,10
20,Ulanmagan: NotifyStakeholdersListener,tashqi,10
21,Ulanmagan: RunAutomationRulesListener,tashqi,10
22,Ulanmagan: BlockTelegramAccountListener,tashqi,10
```

---

## 16. Telegram webhook oqimi

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n16-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,Telegram Bot API,tashqi,,,
2,POST /api/v1/telegram/webhook/{botUsername},jarayon,1,,
3,update_id bormi?,qaror,2,,
4,400 invalid,xato,,,3
5,telegram_bots dan bot izlanadi,jarayon,,3,
6,Bot topildimi va o'chirilmaganmi?,qaror,5,,
7,404 bot_not_found,xato,,,6
8,Secret token hash mos keldimi?,qaror,,6,
9,401 unauthorized,xato,,,8
10,telegram_updates ga insertOrIgnore - idempotent,jarayon,,8,
11,status = PROCESSED mi?,qaror,10,,
12,duplicate - qayta ishlanmaydi,natija,,11,
13,ProcessTelegramUpdateJob navbatga tushadi,jarayon,,,11
14,BotConversationService::handle,jarayon,13,,
15,Javob TelegramApiClient orqali yuboriladi,natija,14,,
16,PollTelegramUpdatesCommand - long polling,tashqi,,,
17,(webhook o'rniga) Job ga uzatadi,jarayon,16,,
```

---

## 17. Telegram bot suhbat mashinasi

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n17-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 30
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi
1,IDLE - asosiy menyu,jarayon,
2,Tanlov?,qaror,1
3,REG_AWAIT_PINFL,jarayon,2
4,REG_AWAIT_BXM,jarayon,3
5,REG_AWAIT_PHONE,jarayon,4
6,REG_AWAIT_CODE,jarayon,5
7,Exchange: login va parol yuboriladi,natija,6
8,AWAIT_CONTACT - telefon so'raladi,jarayon,2
9,AWAIT_USERNAME,jarayon,8
10,AWAIT_PASSWORD,jarayon,9
11,VerifyBotLoginService - AD tekshiruvi,qaror,10
12,Telegram akkaunt userga bog'lanadi,natija,11
13,AWAIT_TICKET_TEXT,jarayon,2
14,AWAIT_TICKET_TEAM - 8 tadan sahifalangan,jarayon,13
15,AWAIT_TICKET_TEMPLATE - shablon va SLA qoidasi,jarayon,14
16,AWAIT_TICKET_CONFIRM,qaror,15
17,TicketController::store chaqiriladi,natija,16
18,Mening zayavkalarim - inline tugmalar,jarayon,2
19,Ochiq zayavkalar - xodimlar uchun,jarayon,2
20,Mening vazifalarim,jarayon,2
21,Statistika,natija,2
22,AssignTicketService - qabul qilish,natija,19
23,AWAIT_SOLUTION_COMMENT,jarayon,20
24,TransitionTicketService -> 7 RESOLVED,natija,23
25,AWAIT_REJECT_REASON,jarayon,20
26,TransitionTicketService -> 9 REJECTED,natija,25
27,AWAIT_TICKET_RETURN_REASON,jarayon,20
28,AWAIT_RATING_FEEDBACK - baho 1..5,jarayon,18
29,client_rating saqlanadi,natija,28
```

---

## 18. Bildirishnoma oqimi

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n18-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi
1,Hodisa yoki servis chaqiruvi,jarayon,
2,NotificationOrchestrator::send,jarayon,1
3,"notifications qatori: event_type, template_code, correlation_id",jarayon,2
4,Kanal?,qaror,3
5,SendTelegramNotificationJob,jarayon,4
6,SendEmailNotificationJob,jarayon,4
7,Navbat ishchisi,jarayon,"5,6"
8,notification_deliveries - yetkazish holati,jarayon,7
9,Telegram Bot API yoki SMTP,tashqi,8
10,GET /notifications,natija,
11,GET /notifications/{id},natija,
12,POST /notifications/{id}/read,natija,
13,GET va PUT /notification-preferences,natija,
14,notification-templates CRUD (roles.manage / users.manage),natija,
```

---

## 19. Cisco Finesse qo'ng'iroq oqimi

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n19-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,POST /finesse/account - login va parol saqlanadi,jarayon,,,
2,finesse_accounts - shifrlangan parol,jarayon,1,,
3,POST /tickets/{id}/call,jarayon,,,
4,Hisob saqlanganmi?,qaror,3,,
5,422 avval Cisco Call bo'limida saqlang,xato,,,4
6,Zayavka topildimi? (organization bo'yicha),qaror,,4,
7,404,xato,,,6
8,"Raqam: avval requesterEmployee.phone, keyin initiator_phone",jarayon,,6,
9,Raqam bormi?,qaror,8,,
10,422 telefon raqami yo'q,xato,,,9
11,FinesseService::user - jonli extension va state,jarayon,,9,
12,Finesse javob berdimi?,qaror,11,,
13,422 Finesse bilan bog'lanib bo'lmadi,xato,,,12
14,extension bo'sh yoki state = LOGOUT mi?,qaror,,12,
15,422 Finesse'ga operator sifatida kirmagansiz,xato,,14,
16,Hisobda extension va last_state yangilanadi,jarayon,,,14
17,FinesseService::makeCall,jarayon,16,,
18,Qo'ng'iroq boshlandimi?,qaror,17,,
19,422 qo'ng'iroq amalga oshmadi,xato,,,18
20,200 qo'ng'iroq ketmoqda,natija,,18,
21,GET /finesse/call-active - holat tekshiruvi,jarayon,20,,
22,POST /finesse/drop - dialog yopiladi,natija,21,,
23,POST /tickets/{id}/call-recording - suhbat yozuvi,natija,20,,
24,GET /finesse/status - agent holati,natija,2,,
```

---

## 20. RBAC huquq tekshiruvi

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n20-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,users,tashqi,,,
2,model_has_roles,tashqi,1,,
3,roles,tashqi,2,,
4,role_has_permissions,tashqi,3,,
5,permissions,tashqi,4,,
6,ad_group_roles - AD guruhi bo'yicha rol,tashqi,,,
7,Himoyalangan endpoint,jarayon,,,
8,CheckPermissionMiddleware,jarayon,7,,
9,Foydalanuvchi bormi?,qaror,8,,
10,401,xato,,,9
11,isSuperAdmin?,qaror,,9,
12,Ko'rsatilgan huquqlardan BIRORTASI bormi?,qaror,,,11
13,O'tkaziladi,natija,,"11,12",
14,403 huquq yetarli emas,xato,,,12
15,"tickets.view, tickets.create, tickets.assign, tickets.view_own",tashqi,5,,
16,"dashboard.view, support_panel.view, monitoring.view, stats.view",tashqi,5,,
17,"roles.manage, users.manage, departments.manage",tashqi,5,,
18,"services.manage, sla.manage, assets.manage, knowledge.manage",tashqi,5,,
19,"problems.manage, changes.manage, changes.approve",tashqi,5,,
20,"workflows.manage, automation.manage, integrations.manage",tashqi,5,,
21,"audit.view, permits.manage",tashqi,5,,
```

---

## 21. Ma'lumotlar bazasi bloklari

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n21-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 80
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi
1,"000010 Reference: ticket_statuses, ticket_status_transitions, ticket_priorities, ticket_sources",tashqi,
2,"000010 Reference: comment_types, comment_sources, attachment_types, notification_channels",tashqi,
3,"000010 Reference: asset_types, asset_statuses, relationship_types, article_types",tashqi,
4,"000010 Reference: locales, timezones, employment_statuses, workflow_entity_types, integration_types",tashqi,
5,"000020 Organization: organizations, regions, branches, departments, positions, employees",jarayon,
6,"000030 Identity: users, personal_access_tokens",jarayon,5
7,"000040 RBAC: roles, permissions, role_has_permissions, model_has_roles, ad_group_roles",jarayon,6
8,"000050 ITSM master: categories, services, service_offerings, locations, resolution_codes, teams",jarayon,
9,000060 Ticketing: tickets,natija,"6,8,12"
10,"000060 Tarix: ticket_status_history, ticket_assignment_history",natija,9
11,"000070: comments, attachments, ticket_templates, tags",natija,9
12,"000080 SLA: sla_rules (team_id, priority_id, is_default)",jarayon,
13,"000090 Notification: notifications, notification_deliveries, notification_templates, user_notification_preferences",natija,9
14,"000100 CMDB: assets, asset_models, manufacturers, vendors, software_products, software_licenses",tashqi,
15,"000110 Knowledge: knowledge_articles, article_feedback",tashqi,
16,"000120: problems, changes, maintenance_windows",tashqi,
17,"000120: service_catalog_items, service_requests, approval_requests, approval_steps",tashqi,
18,"000130 Workflow: workflows, workflow_runs, workflow_step_runs",tashqi,
19,"000130 Automation: automation_rules, automation_executions",tashqi,
20,"000140 Integration: integrations, integration_logs, webhook_endpoints, outbox",tashqi,
21,"000140 Telegram: telegram_bots, telegram_updates, telegram akkauntlari",tashqi,
22,000150 Audit: audit_logs,natija,9
23,000160: RLS siyosatlari va partitsiyalash,tashqi,
24,000170: Full-text indekslar,tashqi,
25,"Qo'shimcha: sms_codes (hashlangan kod, verification_token)",tashqi,
26,Qo'shimcha: ad_accounts (login va shifrlangan parol),tashqi,
27,"Qo'shimcha: finesse_accounts, permits",tashqi,
```

---

## 22. Umumiy end-to-end oqim

```
# label: %name%
# stylename: tur
# styles: {"jarayon":"rounded=1;whiteSpace=wrap;html=1;fillColor=#dae8fc;strokeColor=#6c8ebf;","qaror":"rhombus;whiteSpace=wrap;html=1;fillColor=#fff2cc;strokeColor=#d6b656;","xato":"rounded=1;whiteSpace=wrap;html=1;fillColor=#f8cecc;strokeColor=#b85450;","natija":"rounded=1;whiteSpace=wrap;html=1;fillColor=#d5e8d4;strokeColor=#82b366;","tashqi":"rounded=0;whiteSpace=wrap;html=1;fillColor=#e1d5e7;strokeColor=#9673a6;"}
# namespace: n22-
# connect: {"from":"oldingi","to":"id","invert":true,"style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;"}
# connect: {"from":"ha","to":"id","invert":true,"label":"ha","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#82b366;"}
# connect: {"from":"yoq","to":"id","invert":true,"label":"yo'q","style":"edgeStyle=orthogonalEdgeStyle;rounded=1;html=1;strokeColor=#b85450;"}
# width: auto
# height: auto
# padding: 12
# nodespacing: 40
# levelspacing: 70
# edgespacing: 40
# layout: verticalflow
id,name,tur,oldingi,ha,yoq
1,Xodimda muammo paydo bo'ldi,jarayon,,,
2,Qaysi kanal?,qaror,1,,
3,Veb - login (AD yoki LOCAL),jarayon,2,,
4,Telegram - /start va telefon tasdiqlash,jarayon,2,,
5,AD hisobi bormi?,qaror,3,,
6,"/ad-account: PINFL, BXM, SMS, Exchange",jarayon,,,5
7,Token olinadi; bosh sahifaga yo'naltiriladi,jarayon,,5,
8,Hisob bog'langanmi?,qaror,4,,
9,Botdan ro'yxatdan o'tish yoki login,jarayon,,,8
10,Bot menyusi,jarayon,"9",8,
11,Zayavka yaratish,jarayon,"7,10",,
12,Bahosiz eski zayavka bormi?,qaror,11,,
13,Avval eski zayavkaga baho beriladi,xato,,12,
14,Ticket: status 1 NEW; SLA qabul taymeri boshlanadi,jarayon,,,12
15,event(TicketCreated) -> Telegram xabari,jarayon,14,,
16,Navbat: /tasks yoki botdagi ochiq zayavkalar,jarayon,15,,
17,Xodim qabul qiladi,jarayon,16,,
18,AssignTicketService: started_at va status 4,jarayon,17,,
19,event(TicketAssigned) + event(TicketStatusChanged),jarayon,18,,
20,"Ish jarayoni: izohlar, biriktirmalar, Finesse qo'ng'irog'i",jarayon,19,,
21,Kutish kerakmi?,qaror,20,,
22,5 WAITING_USER yoki 6 WAITING_VENDOR - SLA to'xtaydi,jarayon,,21,
23,Natija?,qaror,,,21
24,Yechim matni va status 7 RESOLVED,natija,23,,
25,Sabab va status 9 REJECTED,xato,23,,
26,status 10 CANCELLED,xato,23,,
27,Murojaatchiga Telegram xabari,jarayon,"24,25",,
28,Baholash 1..5 va izoh,jarayon,27,,
29,status 8 CLOSED,natija,28,,
30,Arxiv,natija,"26,29",,
31,"Ko'rsatkichlar: SLA buzilishi, xodim yuklamasi, monitoring",natija,30,,
32,"Dashboard, Support panel, Stats, Team workload, Audit",natija,31,,
```
