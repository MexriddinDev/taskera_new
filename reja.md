# Loyiha Audit va Texnik Tahlil Prompti

Loyihani to‘liq o‘rganib chiq va professional **Senior Frontend Developer, Senior PHP Developer va Senior Backend Developer** sifatida texnik audit qil.

Loyihaning barcha fayllari, papkalari, kod strukturasi, frontend, backend, PHP kodlari, API'lar, database bilan ishlash qismi, authentication, authorization, validation, error handling va boshqa barcha muhim qismlarini ketma-ket tahlil qil.

## Asosiy maqsad

Loyihadagi barcha:

* xatolar;
* buglar;
* mantiqiy xatolar;
* ishlamayotgan funksiyalar;
* noto‘g‘ri yoki ortiqcha kodlar;
* frontend muammolari;
* PHP/backend muammolari;
* API muammolari;
* database bilan bog‘liq muammolar;
* security zaifliklari;
* performance muammolari;
* scalability muammolari;
* UX/UI muammolari;
* kod strukturasi va arxitektura kamchiliklari;
* duplicate/redundant kodlar;
* noto‘g‘ri exception/error handling;
* validation kamchiliklari;
* authentication va authorization xatolari;
* edge-case muammolari;
* deployment/production muammolarini

aniqlab ber.

## 1. Frontend audit

Frontend qismini Senior Frontend Developer sifatida tekshir:

* UI/UX;
* responsive design;
* desktop/tablet/mobile moslashuvchanligi;
* JavaScript logikasi;
* API bilan integratsiya;
* form validation;
* loading/error/empty state;
* state management;
* komponentlar strukturasi;
* performance;
* browser compatibility;
* accessibility;
* security;
* duplicate code;
* noto‘g‘ri event handling;
* memory leak;
* console error/warning;
* foydalanuvchi tajribasiga ta’sir qiluvchi muammolar.

## 2. PHP audit

PHP kodini Senior PHP Developer sifatida chuqur tekshir:

* PHP architecture;
* OOP;
* SOLID;
* clean code;
* dependency management;
* database queries;
* SQL injection;
* XSS;
* CSRF;
* authentication;
* authorization;
* session management;
* input validation;
* sanitization;
* exception handling;
* logging;
* file handling;
* API implementation;
* performance;
* security;
* maintainability.

## 3. Backend audit

Backendni Senior Backend Developer sifatida tekshir:

* architecture;
* API structure;
* endpointlar;
* request/response formatlari;
* HTTP status code'lar;
* authentication;
* authorization;
* business logic;
* validation;
* database interaction;
* transactionlar;
* concurrency;
* caching;
* performance;
* error handling;
* logging;
* security;
* scalability;
* reliability.

## 4. Database audit

Database qismini ham tekshir:

* jadvallar strukturasi;
* relationships;
* primary/foreign key;
* indexes;
* constraints;
* normalization;
* duplicate data;
* SQL query optimizatsiyasi;
* N+1 query muammolari;
* transactionlar;
* data integrity;
* migrationlar;
* backup/recovery bilan bog‘liq muammolar.

## 5. Security audit

Loyihani security nuqtayi nazaridan alohida tekshir.

Quyidagilarni aniqlashga harakat qil:

* SQL Injection;
* XSS;
* CSRF;
* authentication bypass;
* authorization bypass;
* insecure session;
* sensitive data exposure;
* hardcoded secret/API key;
* noto‘g‘ri password handling;
* insecure file upload;
* path traversal;
* IDOR;
* privilege escalation;
* noto‘g‘ri CORS;
* security headerlar;
* boshqa keng tarqalgan web security zaifliklari.

**Zaiflikni aniqlaganda uni xavfsiz tarzda tushuntir va qanday qilib tuzatish kerakligini ko‘rsat.**

## 6. Performance audit

Loyihaning tezligi va resurslardan foydalanishini tekshir:

* sekin ishlaydigan kodlar;
* ortiqcha database querylar;
* og‘ir API requestlar;
* frontend rendering muammolari;
* katta fayllar;
* caching imkoniyatlari;
* database indexlari;
* unnecessary requestlar;
* memory/CPU bilan bog‘liq muammolar.

## 7. Arxitektura audit

Loyihaning umumiy architecture'sini bahola.

Quyidagilarni aniqlab ber:

* arxitektura to‘g‘ri tanlanganmi;
* frontend/backend separation;
* layerlar to‘g‘ri ajratilganmi;
* business logic qayerda joylashgan;
* code coupling;
* dependency muammolari;
* scalability;
* maintainability;
* future development uchun qanchalik qulayligi.

Agar architecture noto‘g‘ri bo‘lsa, **qanday architecture'ga o‘tkazish kerakligini aniq taklif qil.**

## 8. Har bir muammoni prioritet bilan chiqar

Topilgan muammolarni quyidagi kategoriyalarga ajrat:

🔴 **CRITICAL** — loyiha ishlashiga yoki security'ga jiddiy xavf
🟠 **HIGH** — muhim funksiyaga ta’sir qiluvchi muammo
🟡 **MEDIUM** — optimizatsiya yoki sifat muammosi
🟢 **LOW** — kichik texnik kamchilik

Har bir muammo uchun:

1. Muammo nomi
2. Qaysi faylda
3. Qaysi kod qismida
4. Muammo sababi
5. Loyihaga ta’siri
6. Prioriteti
7. Tavsiya etiladigan yechim
8. Zarur bo‘lsa, qanday kod o‘zgarishi kerakligi

ni ko‘rsat.

## 9. Ishlashini tekshir

Faqat kodni o‘qib xulosa qilma.

Imkoniyat mavjud bo‘lsa:

* loyihani ishga tushir;
* build qil;
* frontendni tekshir;
* backendni ishga tushir;
* API endpointlarni tekshir;
* database connectionni tekshir;
* mavjud funksiyalarni test qil;
* console/log xatolarini tekshir;
* mavjud testlarni ishga tushir.

Topilgan real xatolarni alohida ko‘rsat.

## 10. Audit natijasi

Audit yakunida quyidagi formatda hisobot ber:

### A. Umumiy loyiha holati

* loyiha arxitekturasi;
* texnik holati;
* asosiy muammolar;
* production-ready yoki yo‘qligi.

### B. Critical muammolar

### C. High muammolar

### D. Medium muammolar

### E. Low muammolar

### F. Security audit

### G. Performance audit

### H. Architecture audit

### I. Frontend audit

### J. PHP audit

### K. Backend audit

### L. Database audit

### M. Yakuniy baho

Loyihani **0–100 ball** oralig‘ida bahola va har bir yo‘nalish bo‘yicha alohida ball ber:

* Frontend
* PHP
* Backend
* Database
* Security
* Performance
* Architecture
* Code Quality
* UX/UI
* Scalability

## Muhim qoidalar

* Hech narsani taxmin qilib yozma.
* Kodda mavjud bo‘lmagan muammoni o‘ylab topma.
* Har bir xulosani real kod yoki loyiha strukturasiga asosla.
* Bir xil muammoni qayta-qayta yozma.
* Avval muammolarni to‘liq aniqlab chiq, keyin yechimlarni taklif qil.
* Muhim muammolarni yashirma.
* "Hammasi yaxshi" degan umumiy xulosa bilan cheklanma.
* Loyiha katta bo‘lsa, barcha qismlarni bosqichma-bosqich audit qil.
* Yakunda loyihada aniqlangan muammolarning **to‘liq checklistini** ber.

**Hozircha kodni o‘zgartirma. Avval loyihani to‘liq audit qil, barcha kamchiliklarni aniqlab, batafsil texnik hisobot ber.**
