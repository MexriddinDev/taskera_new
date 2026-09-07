import type { Lang } from './translations';

interface PermissionText {
  label: string;
  desc: string;
}

interface PermissionMeta {
  icon: string;
  uz: PermissionText;
  ru: PermissionText;
  en: PermissionText;
}

/**
 * Har bir huquqning tushunarli nomi va "nima beradi" tavsifi.
 *
 * Ilgari bu ma'lumot RbacManagementPage ichida `rbac.perm.*` tarjima
 * kalitlariga havola qilingan edi, lekin translations.ts da bunday kalitlar
 * umuman yo'q edi — natijada ekranda "rbac.perm.ticketsView" kabi xom kalitlar
 * chiqardi. Shu sababli matnlar shu yerga, bitta joyga yig'ildi.
 *
 * Tavsif qisqa va aniq bo'lishi kerak: xodimga ayni shu huquq NIMA ochib
 * berishini bir qatorda aytadi.
 */
export const PERMISSION_META: Record<string, PermissionMeta> = {
  // ——— Navigatsiya ———
  'dashboard.view': {
    icon: '📌',
    uz: { label: 'Dashboard', desc: 'Bosh sahifadagi umumiy zayavkalar taxtasini ochadi.' },
    ru: { label: 'Дашборд', desc: 'Открывает главную доску со всеми заявками.' },
    en: { label: 'Dashboard', desc: 'Opens the main board with all tickets.' },
  },
  'tasks.view': {
    icon: '📋',
    uz: { label: 'Ochiq ishlar', desc: 'Hali hech kim olmagan zayavkalar ro\'yxatini ochadi.' },
    ru: { label: 'Открытые задачи', desc: 'Открывает список заявок, которые ещё никто не взял.' },
    en: { label: 'Open tasks', desc: 'Opens the list of tickets nobody has taken yet.' },
  },
  'my_tasks.view': {
    icon: '✍️',
    uz: { label: 'Mening ishlarim', desc: 'Xodimga biriktirilgan zayavkalar sahifasini ochadi.' },
    ru: { label: 'Мои задачи', desc: 'Открывает страницу заявок, назначенных сотруднику.' },
    en: { label: 'My tasks', desc: 'Opens the page of tickets assigned to the employee.' },
  },
  'monitoring.view': {
    icon: '📺',
    uz: { label: 'Monitoring', desc: 'Real vaqtdagi kuzatuv ekranini (Command Center) ochadi.' },
    ru: { label: 'Мониторинг', desc: 'Открывает экран наблюдения в реальном времени.' },
    en: { label: 'Monitoring', desc: 'Opens the real-time command center screen.' },
  },
  'team_workload.view': {
    icon: '👥',
    uz: { label: 'Jamoa yuklamasi', desc: 'Qaysi xodimda nechta zayavka borligini ko\'rsatadi.' },
    ru: { label: 'Загрузка команды', desc: 'Показывает, сколько заявок у каждого сотрудника.' },
    en: { label: 'Team workload', desc: 'Shows how many tickets each employee has.' },
  },
  'stats.view': {
    icon: '📊',
    uz: { label: 'Statistika', desc: 'Hisobotlar va diagrammalar sahifasini ochadi.' },
    ru: { label: 'Статистика', desc: 'Открывает страницу отчётов и диаграмм.' },
    en: { label: 'Statistics', desc: 'Opens the reports and charts page.' },
  },
  'roles.manage': {
    icon: '🛡️',
    uz: { label: 'Rollarni boshqarish', desc: 'Shu sahifani ochadi: rol, bo\'lim va huquq berish.' },
    ru: { label: 'Управление ролями', desc: 'Открывает эту страницу: роли, отделы и права.' },
    en: { label: 'Manage roles', desc: 'Opens this page: roles, departments and permissions.' },
  },

  // ——— Zayavkalar ———
  'tickets.view': {
    icon: '👁️',
    uz: { label: 'Barcha zayavkalar', desc: 'Butun tashkilotdagi zayavkalarni ko\'rsatadi. Xodimni "support" qiladi.' },
    ru: { label: 'Все заявки', desc: 'Показывает заявки всей организации. Делает сотрудника «support».' },
    en: { label: 'All tickets', desc: 'Shows tickets across the organisation. Makes the user support staff.' },
  },
  'tickets.create': {
    icon: '➕',
    uz: { label: 'Zayavka yuborish', desc: 'Yangi zayavka yaratish tugmasini ochadi.' },
    ru: { label: 'Создание заявки', desc: 'Открывает кнопку создания новой заявки.' },
    en: { label: 'Create ticket', desc: 'Enables the new-ticket button.' },
  },
  'tickets.assign': {
    icon: '👤',
    uz: { label: 'Biriktirish', desc: 'Zayavkani o\'ziga olish yoki boshqa xodimga o\'tkazish.' },
    ru: { label: 'Назначение', desc: 'Взять заявку себе или передать другому сотруднику.' },
    en: { label: 'Assign', desc: 'Take a ticket or hand it to another employee.' },
  },
  'tickets.transition': {
    icon: '🔄',
    uz: { label: 'Holatni o\'zgartirish', desc: 'Zayavkani jarayonga o\'tkazish, yopish yoki rad etish.' },
    ru: { label: 'Смена статуса', desc: 'Перевести заявку в работу, закрыть или отклонить.' },
    en: { label: 'Change status', desc: 'Move a ticket to in-progress, close or reject it.' },
  },
  'tickets.view_own': {
    icon: '🙋',
    uz: { label: 'Zayavkalarim', desc: 'Navbardagi "Zayavkalarim" bo\'limini ochadi — xodim faqat o\'zi yuborgan zayavkalarni ko\'radi.' },
    ru: { label: 'Мои заявки', desc: 'Открывает раздел «Мои заявки» — сотрудник видит только свои обращения.' },
    en: { label: 'My requests', desc: 'Opens the "My requests" section — the user sees only their own tickets.' },
  },
  'tickets.export': {
    icon: '📥',
    uz: { label: 'Eksport', desc: 'Zayavkalarni Excel yoki PDF ga yuklab olish.' },
    ru: { label: 'Экспорт', desc: 'Выгрузка заявок в Excel или PDF.' },
    en: { label: 'Export', desc: 'Download tickets as Excel or PDF.' },
  },
  'tickets.delete': {
    icon: '❌',
    uz: { label: 'O\'chirish', desc: 'Zayavkani butunlay o\'chirib yuborish. Ehtiyot bo\'ling.' },
    ru: { label: 'Удаление', desc: 'Полное удаление заявки. Будьте осторожны.' },
    en: { label: 'Delete', desc: 'Permanently delete a ticket. Use with care.' },
  },

  // ——— Foydalanuvchilar va tashkilot ———
  'users.manage': {
    icon: '👨‍💼',
    uz: { label: 'Foydalanuvchilar', desc: 'Xodim qo\'shish, tahrirlash va bloklash.' },
    ru: { label: 'Пользователи', desc: 'Добавление, редактирование и блокировка сотрудников.' },
    en: { label: 'Users', desc: 'Add, edit and block employees.' },
  },
  'departments.manage': {
    icon: '🏢',
    uz: { label: 'Bo\'limlar', desc: 'Bo\'lim, filial va xizmat guruhlarini yaratish va tahrirlash.' },
    ru: { label: 'Отделы', desc: 'Создание и редактирование отделов, филиалов и групп.' },
    en: { label: 'Departments', desc: 'Create and edit departments, branches and service groups.' },
  },

  // ——— Bilimlar bazasi va aktivlar ———
  'knowledge.view': {
    icon: '💡',
    uz: { label: 'Bilimlar bazasi', desc: 'Ko\'rsatma va maqolalarni o\'qish.' },
    ru: { label: 'База знаний', desc: 'Чтение инструкций и статей.' },
    en: { label: 'Knowledge base', desc: 'Read guides and articles.' },
  },
  'knowledge.manage': {
    icon: '✏️',
    uz: { label: 'Maqola yozish', desc: 'Bilimlar bazasiga maqola qo\'shish va nashr etish.' },
    ru: { label: 'Редактор статей', desc: 'Добавление и публикация статей в базе знаний.' },
    en: { label: 'Author articles', desc: 'Add and publish knowledge-base articles.' },
  },
  'assets.view': {
    icon: '💻',
    uz: { label: 'Aktivlar', desc: 'Kompyuter va uskunalar ro\'yxatini ko\'rish.' },
    ru: { label: 'Активы', desc: 'Просмотр списка компьютеров и оборудования.' },
    en: { label: 'Assets', desc: 'View the list of computers and equipment.' },
  },
  'assets.manage': {
    icon: '⚙️',
    uz: { label: 'Aktivlarni boshqarish', desc: 'Uskuna qo\'shish, biriktirish va inventarizatsiya.' },
    ru: { label: 'Управление активами', desc: 'Добавление, закрепление и инвентаризация оборудования.' },
    en: { label: 'Manage assets', desc: 'Add, assign and inventory equipment.' },
  },
  'sla.manage': {
    icon: '⏱️',
    uz: { label: 'SLA sozlash', desc: 'Muddat qoidalari va ish kalendarini o\'zgartirish.' },
    ru: { label: 'Настройка SLA', desc: 'Изменение правил сроков и рабочего календаря.' },
    en: { label: 'Configure SLA', desc: 'Change deadline rules and the working calendar.' },
  },
  'audit.view': {
    icon: '📜',
    uz: { label: 'Audit loglari', desc: 'Tizimda kim nima qilganini ko\'rish.' },
    ru: { label: 'Журнал аудита', desc: 'Просмотр действий пользователей в системе.' },
    en: { label: 'Audit log', desc: 'See who did what in the system.' },
  },

  // ——— ITSM jarayonlari ———
  'problems.view': {
    icon: '🧩',
    uz: { label: 'Muammolar', desc: 'Takrorlanuvchi muammolar ro\'yxatini ko\'rish.' },
    ru: { label: 'Проблемы', desc: 'Просмотр списка повторяющихся проблем.' },
    en: { label: 'Problems', desc: 'View the list of recurring problems.' },
  },
  'problems.manage': {
    icon: '🛠️',
    uz: { label: 'Muammolarni boshqarish', desc: 'Muammo yaratish, tahrirlash va yechim kiritish.' },
    ru: { label: 'Управление проблемами', desc: 'Создание, редактирование и решение проблем.' },
    en: { label: 'Manage problems', desc: 'Create, edit and resolve problems.' },
  },
  'changes.view': {
    icon: '📄',
    uz: { label: 'O\'zgarishlar', desc: 'Rejalashtirilgan o\'zgarishlar ro\'yxatini ko\'rish.' },
    ru: { label: 'Изменения', desc: 'Просмотр списка запланированных изменений.' },
    en: { label: 'Changes', desc: 'View the list of planned changes.' },
  },
  'changes.manage': {
    icon: '📝',
    uz: { label: 'O\'zgarish yaratish', desc: 'O\'zgarish so\'rovini yaratish va tahrirlash.' },
    ru: { label: 'Создание изменений', desc: 'Создание и редактирование запросов на изменение.' },
    en: { label: 'Author changes', desc: 'Create and edit change requests.' },
  },
  'changes.approve': {
    icon: '✅',
    uz: { label: 'O\'zgarishni tasdiqlash', desc: 'O\'zgarish so\'rovini tasdiqlash yoki rad etish (CAB).' },
    ru: { label: 'Утверждение изменений', desc: 'Утверждение или отклонение запроса на изменение (CAB).' },
    en: { label: 'Approve changes', desc: 'Approve or reject a change request (CAB).' },
  },
  'workflows.manage': {
    icon: '🔀',
    uz: { label: 'Jarayonlar', desc: 'Zayavka bosqichlari va biznes jarayonlarni sozlash.' },
    ru: { label: 'Процессы', desc: 'Настройка этапов заявок и бизнес-процессов.' },
    en: { label: 'Workflows', desc: 'Configure ticket stages and business processes.' },
  },
  'automation.manage': {
    icon: '🤖',
    uz: { label: 'Avtomatlashtirish', desc: 'Avtomatik qoidalar va triggerlarni sozlash.' },
    ru: { label: 'Автоматизация', desc: 'Настройка автоматических правил и триггеров.' },
    en: { label: 'Automation', desc: 'Configure automatic rules and triggers.' },
  },
  'integrations.manage': {
    icon: '🔌',
    uz: { label: 'Integratsiyalar', desc: 'Telegram, pochta va webhooklarni ulash.' },
    ru: { label: 'Интеграции', desc: 'Подключение Telegram, почты и вебхуков.' },
    en: { label: 'Integrations', desc: 'Connect Telegram, mail and webhooks.' },
  },
  'services.manage': {
    icon: '🗂️',
    uz: { label: 'Xizmatlar katalogi', desc: 'Xizmatlar ro\'yxati va shablonlarni boshqarish.' },
    ru: { label: 'Каталог услуг', desc: 'Управление списком услуг и шаблонами.' },
    en: { label: 'Service catalog', desc: 'Manage the service list and templates.' },
  },
};

/**
 * Huquq uchun ikonka, nom va tavsif.
 *
 * Ro'yxatda yo'q huquq uchun bazadagi tavsif ishlatiladi — yangi huquq
 * qo'shilganda ekranda xom kalit emas, hech bo'lmasa bazadagi izoh chiqadi.
 */
export const getPermissionMeta = (
  name: string,
  lang: Lang,
  fallbackDesc?: string | null
): { icon: string; label: string; desc: string } => {
  const meta = PERMISSION_META[name];
  if (!meta) {
    return { icon: '🔹', label: name, desc: fallbackDesc || '' };
  }

  const text = meta[lang] ?? meta.uz;

  return { icon: meta.icon, label: text.label, desc: text.desc };
};
