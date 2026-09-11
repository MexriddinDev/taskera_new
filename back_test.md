# SENIOR PHP BACKEND TESTING — LOCAL IP + PORT MUHITI

## Muhit

Ushbu loyiha internetga ochiq public sayt emas.

Tizim lokal tarmoq orqali ishlaydi.

Masalan:

```text
http://127.0.0.1:8000
http://localhost:8000
http://192.168.1.20:8080
http://10.10.10.15:8000
```

Shuning uchun backend testing aynan:

```text
Client
   ↓
LOCAL NETWORK
   ↓
IP : PORT
   ↓
Apache / Nginx / PHP Server
   ↓
PHP Backend
   ↓
Database
```

zanjiri bo‘yicha bajariladi.

Asosiy maqsad:

> Lokal IP va port orqali kirayotgan foydalanuvchilar uchun backend barqaror, xavfsiz va xatosiz ishlashini tekshirish.

---

# 1. LOCAL SERVER VA PORT TEST

Eng birinchi tekshiriladigan qism — PHP backend umuman kerakli portda ishlayaptimi.

Masalan:

```text
http://192.168.1.20:8000
```

Tekshiriladi:

```text
IP ishlayaptimi?
Port ochiqmi?
Backend request qabul qilyaptimi?
Apache/Nginx ishlayaptimi?
PHP-FPM ishlayaptimi?
Database bilan aloqa bormi?
Boshqa kompyuterdan sayt ochiladimi?
```

Masalan Windows/Linux orqali:

```bash
ping 192.168.1.20
```

Portni tekshirish:

```bash
curl http://192.168.1.20:8000
```

yoki:

```bash
curl -I http://192.168.1.20:8000
```

Linux serverda:

```bash
ss -tulpn
```

yoki:

```bash
netstat -tulpn
```

Tekshiriladi:

```text
8000 port listening holatidami?
8080 port ishlayaptimi?
Keraksiz portlar ochiq emasmi?
```

---

# 2. STATIC ANALYSIS VA CODE QUALITY

Kod ishga tushmasdan PHP kodning o‘zi tekshiriladi.

Asboblar:

```text
PHPStan
Psalm
Laravel Pint
PHP-CS-Fixer
```

Tekshiriladi:

```text
type error
nullable qiymatlar
noto‘g‘ri method
noto‘g‘ri property
dead code
duplicate code
unused variable
unreachable code
```

Masalan:

```bash
./vendor/bin/phpstan analyse
```

Laravel:

```bash
./vendor/bin/pint --test
```

Maqsad:

> Runtime paytida chiqishi mumkin bo‘lgan PHP xatolarini oldindan aniqlash.

---

# 3. PHP CONFIGURATION TEST

Lokal serverdagi PHP konfiguratsiyasi ham tekshiriladi.

```bash
php -v
```

Tekshiriladi:

```text
PHP versiyasi
required extensions
memory_limit
max_execution_time
upload_max_filesize
post_max_size
timezone
session sozlamalari
```

Masalan:

```bash
php -m
```

Required extensionlar:

```text
pdo
pdo_mysql yoki pdo_pgsql
mbstring
openssl
json
fileinfo
curl — agar lokal integratsiyalar bo‘lsa
```

---

# 4. ENVIRONMENT CONFIGURATION

`.env` konfiguratsiyasi tekshiriladi.

Masalan Laravel:

```env
APP_ENV=local
APP_DEBUG=true

APP_URL=http://192.168.1.20:8000

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=taskflow
DB_USERNAME=...
DB_PASSWORD=...
```

Tekshiriladi:

```text
APP_URL to‘g‘rimi?
DB_HOST to‘g‘rimi?
DB_PORT to‘g‘rimi?
Database login ishlayaptimi?
Session konfiguratsiyasi ishlayaptimi?
Cache driver ishlayaptimi?
Queue driver ishlayaptimi?
```

Muhim:

Test vaqtida `APP_DEBUG=true` bo‘lishi mumkin.

Lekin real lokal ekspluatatsiyada:

```env
APP_DEBUG=false
```

bo‘lishi tavsiya qilinadi.

Chunki PHP exception ichidan:

```text
database password
server path
SQL query
environment ma'lumotlari
```

chiqib ketishi mumkin.

---

# 5. UNIT TEST

Bitta class yoki method alohida test qilinadi.

Masalan SLA hisoblash:

```php
public function test_sla_deadline_is_calculated_correctly(): void
{
    $service = new SlaService();

    $deadline = $service->calculateDeadline(
        start: '2026-09-11 10:00:00',
        minutes: 120
    );

    $this->assertEquals(
        '2026-09-11 12:00:00',
        $deadline
    );
}
```

Bu testda:

```text
browser kerak emas
port kerak emas
database shart emas
```

Faqat biznes logika tekshiriladi.

---

# 6. INTEGRATION TEST

Bir nechta backend komponent birgalikda tekshiriladi.

Masalan:

```text
Controller
    ↓
Service
    ↓
Repository
    ↓
Database
```

Tekshiriladi:

```text
request controllerga tushdimi?
service ishladimi?
repository to‘g‘ri query yubordimi?
databasega yozildimi?
transaction ishladimi?
```

Masalan:

```text
Ticket yaratish
       ↓
ticket jadvaliga yozish
       ↓
SLA biriktirish
       ↓
history yozish
       ↓
audit yozish
```

Bularning hammasi bir request ichida to‘g‘ri ishlashi kerak.

---

# 7. API / FEATURE TEST

Lokal loyiha bo‘lsa ham API test juda muhim.

Masalan sayt:

```text
http://192.168.1.20:8000
```

API:

```http
POST http://192.168.1.20:8000/api/tickets
```

Tekshiriladi:

```text
200 OK
201 Created
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Validation Error
500 Internal Server Error
```

Laravel misoli:

```php
public function test_user_can_create_ticket(): void
{
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->postJson('/api/tickets', [
            'category_id' => 1,
            'title' => 'Printer ishlamayapti',
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('tickets', [
        'title' => 'Printer ishlamayapti',
    ]);
}
```

---

# 8. PORT ORQALI REAL API TEST

Backend test faqat PHPUnit ichida qolmasligi kerak.

Real port orqali ham tekshiriladi.

Masalan:

```bash
curl http://192.168.1.20:8000/api/tickets
```

POST:

```bash
curl -X POST \
http://192.168.1.20:8000/api/tickets \
-H "Content-Type: application/json" \
-d '{"category_id":1,"title":"Test ticket"}'
```

Tekshiriladi:

```text
request servergacha yetib bordimi?
server to‘g‘ri javob berdimi?
JSON to‘g‘rimi?
HTTP status to‘g‘rimi?
response juda sekin emasmi?
```

---

# 9. VALIDATION TEST

Har bir input alohida tekshiriladi.

Masalan ticket yaratish:

```text
category_id yo‘q
category_id null
category mavjud emas
title bo‘sh
title juda uzun
description juda uzun
status noto‘g‘ri
sana noto‘g‘ri
noto‘g‘ri enum
```

Shuningdek:

```text
' OR 1=1 --
<script>alert(1)</script>
../../../../etc/passwd
```

kabi zararli inputlar ham tekshiriladi.

Backend hech qachon frontend validationga to‘liq ishonmasligi kerak.

---

# 10. AUTHENTICATION TEST

Lokal tarmoq bo‘lsa ham authentication majburiy tekshiriladi.

```text
login/parol to‘g‘rimi?
noto‘g‘ri parol bloklanadimi?
logout ishlaydimi?
session yaratiladimi?
session tugaydimi?
bloklangan user kira olmaydimi?
```

Misol:

```text
Login qilmagan user
        ↓
/admin/users
        ↓
403 yoki login page
```

Login qilmagan odam URLni qo‘lda yozib kirib ketmasligi kerak.

---

# 11. AUTHORIZATION / ROLE / PERMISSION

Bu juda muhim.

Frontendda buttonni yashirish xavfsizlik emas.

Backendning o‘zi bloklashi kerak.

Masalan:

```text
Oddiy user
    ↓
DELETE /api/users/10
    ↓
403 Forbidden
```

Tekshiriladi:

```text
Admin
Operator
Rahbar
Oddiy xodim
Read-only
```

Har biri faqat o‘z vakolatiga tegishli funksiyani bajara olishi kerak.

Masalan:

```text
Oddiy xodim → ticket yaratadi
Operator → ticketni qabul qiladi
Rahbar → monitoring qiladi
Admin → userlarni boshqaradi
```

---

# 12. SESSION TEST

Lokal web tizimlarda session juda muhim.

Tekshiriladi:

```text
login qilganda session yaratiladimi?
logoutdan keyin session bekor bo‘ladimi?
session timeout ishlaydimi?
browser yopilib ochilganda nima bo‘ladi?
bir user bir nechta kompyuterdan kirsa nima bo‘ladi?
session hijackingdan himoya bormi?
```

Masalan:

```text
User login qildi
    ↓
session_id yaratildi
    ↓
logout qildi
    ↓
oldingi session ishlamasligi kerak
```

---

# 13. DATABASE TEST

Database backendning eng muhim qismlaridan biri.

Tekshiriladi:

```text
migration ishlaydimi?
foreign key bormi?
unique constraint bormi?
nullable to‘g‘rimi?
indexlar bormi?
transaction ishlaydimi?
rollback ishlaydimi?
N+1 query bormi?
duplicate ma'lumot yoziladimi?
```

Masalan:

```php
DB::transaction(function () {

    Ticket::create(...);

    TicketHistory::create(...);

    AuditLog::create(...);

});
```

Agar `AuditLog` yozishda xato chiqsa:

```text
Ticket yozilib qolib ketmasligi kerak.
```

Transaction rollback bo‘lishi kerak.

---

# 14. DATABASE CONNECTION FAILURE TEST

Database vaqtincha to‘xtatiladi.

Keyin backend qanday ishlashi tekshiriladi.

Masalan:

```text
Database DOWN
      ↓
User request
      ↓
Backend
      ↓
500
```

Lekin userga:

```text
SQLSTATE[HY000] ...
root@localhost...
password...
```

ko‘rinmasligi kerak.

User uchun normal xabar:

```text
Serverda vaqtinchalik xatolik yuz berdi.
```

Server logida esa batafsil xato saqlanishi mumkin.

---

# 15. BUSINESS LOGIC TEST

Backendning eng asosiy qismi.

TaskFlow/SLA misolida:

```text
Zayavka yaratildi
        ↓
Kategoriya aniqlandi
        ↓
SLA qoidasi topildi
        ↓
Qabul qilish vaqti boshlandi
        ↓
Xodim qabul qildi
        ↓
Ijro vaqti boshlandi
        ↓
Pause
        ↓
SLA to‘xtadimi?
        ↓
Resume
        ↓
SLA davom etdimi?
        ↓
Deadline tugadi
        ↓
Overdue
        ↓
Rahbar notification
```

Har bir qadam alohida test qilinadi.

---

# 16. EDGE CASE TEST

Senior dasturchi normal holat bilan cheklanmaydi.

Quyidagilar tekshiriladi:

```text
bitta tugma 2 marta bosilsa nima bo‘ladi?
bir request 2 marta yuborilsa nima bo‘ladi?
bir vaqtda 2 operator ticketni olsa nima bo‘ladi?
ticket o‘chirilayotgan paytda boshqa user tahrirlasa nima bo‘ladi?
database uzilib qolsa nima bo‘ladi?
server restart bo‘lsa nima bo‘ladi?
queue ishlamay qolsa nima bo‘ladi?
disk to‘lib qolsa nima bo‘ladi?
```

Masalan ikki operator:

```text
Operator A ──┐
             ├── Ticket #100 ni olish
Operator B ──┘
```

Natija:

```text
faqat bitta operator ticket egasi bo‘lishi kerak.
```

---

# 17. DUPLICATE REQUEST TEST

User tugmani ikki marta bosishi mumkin.

Masalan:

```text
[Saqlash]
[Saqlash]
```

Backend:

```text
2 ta bir xil ticket
```

yaratib yubormasligi kerak.

Kerak bo‘lsa:

```text
uniqu
```
