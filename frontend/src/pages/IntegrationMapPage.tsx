import React, { useMemo, useState } from 'react';
import { Network, Monitor, Server, Database, RefreshCw } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';

/**
 * Integratsiyalar xaritasi — qaysi ekran qaysi API orqali qayerdan ma'lumot
 * olishini graf ko'rinishida ko'rsatadi.
 *
 * Ma'lumot QO'LDA yozilgan: u kod tuzilishidan avtomatik chiqarilmaydi.
 * Yangi ekran yoki tashqi tizim qo'shilsa, quyidagi ro'yxatlarni ham
 * yangilash kerak — manbalar `backend/config/services.php` va
 * `backend/routes/api.php` dan olingan.
 */

type NodeKind = 'screen' | 'api' | 'source';

interface GraphNode {
  id: string;
  label: string;
  /** Qo'shimcha izoh — tugma ostida kichik shrift bilan. */
  detail?: string;
  kind: NodeKind;
}

const SCREENS: GraphNode[] = [
  { id: 's-login', label: 'Kirish (Login)', kind: 'screen' },
  { id: 's-dashboard', label: 'Boshqaruv paneli', kind: 'screen' },
  { id: 's-mytasks', label: 'Mening vazifalarim', kind: 'screen' },
  { id: 's-queue', label: 'Ochiq ishlar', kind: 'screen' },
  { id: 's-monitoring', label: 'Monitoring', kind: 'screen' },
  { id: 's-workload', label: 'Xodimlar zayavkalari', kind: 'screen' },
  { id: 's-users', label: 'Foydalanuvchilar', kind: 'screen' },
  { id: 's-stats', label: 'Statistika', kind: 'screen' },
  { id: 's-requests', label: 'Zayavkalarim', kind: 'screen' },
  { id: 's-detail', label: 'Zayavka tafsiloti', kind: 'screen' },
  { id: 's-profile', label: 'Profil', kind: 'screen' },
  { id: 's-knowledge', label: 'Bilimlar bazasi', kind: 'screen' },
  { id: 's-catalog', label: 'Xizmatlar katalogi', kind: 'screen' },
  { id: 's-approvals', label: 'Tasdiqlashlar', kind: 'screen' },
  { id: 's-assets', label: 'Aktivlar (CMDB)', kind: 'screen' },
  { id: 's-problems', label: 'Muammolar', kind: 'screen' },
  { id: 's-changes', label: "O'zgarishlar", kind: 'screen' },
  { id: 's-sla', label: 'SLA siyosatlari', kind: 'screen' },
  { id: 's-automation', label: 'Avtomatlashtirish', kind: 'screen' },
  { id: 's-itsm', label: 'ITSM sozlamalari', kind: 'screen' },
  { id: 's-rbac', label: "Rollar va bo'limlar", kind: 'screen' },
  { id: 's-audit', label: 'Audit loglar', kind: 'screen' },
  { id: 's-adaccount', label: 'Pochta (AD) ochish', kind: 'screen' },
];

const APIS: GraphNode[] = [
  { id: 'a-auth', label: 'Auth', detail: '/auth/login · /auth/me', kind: 'api' },
  { id: 'a-tickets', label: 'Ticketing', detail: '/tickets · /tickets/{id}/comments', kind: 'api' },
  { id: 'a-ticket-stats', label: 'Zayavka statistikasi', detail: '/tickets/stats · /tickets/monitoring', kind: 'api' },
  { id: 'a-user-stats', label: 'Foydalanuvchi statistikasi', detail: '/users/department-stats · /users/requester-stats', kind: 'api' },
  { id: 'a-profile', label: 'Profil', detail: '/profile · /users/{id}', kind: 'api' },
  { id: 'a-knowledge', label: 'Bilimlar bazasi', detail: '/knowledge/articles', kind: 'api' },
  { id: 'a-catalog', label: 'Xizmatlar katalogi', detail: '/catalog/items · /service-offerings', kind: 'api' },
  { id: 'a-approvals', label: 'Tasdiqlashlar', detail: '/approval-requests', kind: 'api' },
  { id: 'a-assets', label: 'CMDB', detail: '/assets · /vendors · /software-licenses', kind: 'api' },
  { id: 'a-problems', label: 'Problem Mgmt', detail: '/problems', kind: 'api' },
  { id: 'a-changes', label: 'Change Mgmt', detail: '/changes · /maintenance-windows', kind: 'api' },
  { id: 'a-sla', label: 'SLA', detail: '/categories (SLA muddatlari)', kind: 'api' },
  { id: 'a-automation', label: 'Avtomatlashtirish', detail: '/automation-rules · /workflows', kind: 'api' },
  { id: 'a-master', label: 'ITSM master data', detail: '/services · /categories · /teams · /tags', kind: 'api' },
  { id: 'a-rbac', label: 'RBAC va tashkilot', detail: '/roles · /permissions · /departments · /branches', kind: 'api' },
  { id: 'a-audit', label: 'Audit', detail: '/audit-logs', kind: 'api' },
  { id: 'a-adaccount', label: 'AD hisob ochish', detail: '/ad-account/*', kind: 'api' },
  { id: 'a-telegram', label: 'Telegram webhook', detail: '/telegram/webhook/{bot}', kind: 'api' },
];

const SOURCES: GraphNode[] = [
  { id: 'x-mysql', label: 'MySQL', detail: 'asosiy baza · queue · cache · session', kind: 'source' },
  { id: 'x-ad', label: 'Active Directory (LDAP)', detail: 'LDAP_HOST · 389-port', kind: 'source' },
  { id: 'x-exchange', label: 'Exchange (LDAPS)', detail: 'EXCHANGE_LDAP_HOST · 636-port', kind: 'source' },
  { id: 'x-sso', label: 'SSO', detail: 'sso.xb.uz · client_credentials', kind: 'source' },
  { id: 'x-sms', label: 'SMS Gateway', detail: 'sms.xb.uz · tasdiqlash kodi', kind: 'source' },
  { id: 'x-bxm', label: 'BXM xodim tekshiruvi', detail: 'check-employee xizmati', kind: 'source' },
  { id: 'x-telegram', label: 'Telegram Bot API', detail: 'api.telegram.org', kind: 'source' },
];

/** [ekran/API, API/manba] juftliklari. */
const EDGES: [string, string][] = [
  // Ekran → API
  ['s-login', 'a-auth'],
  ['s-dashboard', 'a-tickets'],
  ['s-dashboard', 'a-ticket-stats'],
  ['s-mytasks', 'a-tickets'],
  ['s-queue', 'a-ticket-stats'],
  ['s-monitoring', 'a-ticket-stats'],
  ['s-workload', 'a-tickets'],
  ['s-workload', 'a-ticket-stats'],
  ['s-users', 'a-user-stats'],
  ['s-users', 'a-rbac'],
  ['s-stats', 'a-ticket-stats'],
  ['s-requests', 'a-tickets'],
  ['s-detail', 'a-tickets'],
  ['s-profile', 'a-profile'],
  ['s-knowledge', 'a-knowledge'],
  ['s-catalog', 'a-catalog'],
  ['s-catalog', 'a-tickets'],
  ['s-approvals', 'a-approvals'],
  ['s-assets', 'a-assets'],
  ['s-assets', 'a-rbac'],
  ['s-problems', 'a-problems'],
  ['s-changes', 'a-changes'],
  ['s-sla', 'a-sla'],
  ['s-automation', 'a-automation'],
  ['s-itsm', 'a-master'],
  ['s-rbac', 'a-rbac'],
  ['s-audit', 'a-audit'],
  ['s-adaccount', 'a-adaccount'],

  // API → manba
  ['a-auth', 'x-mysql'],
  ['a-auth', 'x-ad'],
  ['a-tickets', 'x-mysql'],
  ['a-ticket-stats', 'x-mysql'],
  ['a-user-stats', 'x-mysql'],
  ['a-profile', 'x-mysql'],
  ['a-knowledge', 'x-mysql'],
  ['a-catalog', 'x-mysql'],
  ['a-approvals', 'x-mysql'],
  ['a-assets', 'x-mysql'],
  ['a-problems', 'x-mysql'],
  ['a-changes', 'x-mysql'],
  ['a-sla', 'x-mysql'],
  ['a-automation', 'x-mysql'],
  ['a-master', 'x-mysql'],
  ['a-rbac', 'x-mysql'],
  ['a-audit', 'x-mysql'],
  ['a-adaccount', 'x-mysql'],
  ['a-adaccount', 'x-bxm'],
  ['a-adaccount', 'x-sms'],
  ['a-adaccount', 'x-sso'],
  ['a-adaccount', 'x-exchange'],
  ['a-adaccount', 'x-ad'],
  ['a-telegram', 'x-mysql'],
  ['a-telegram', 'x-telegram'],
  ['a-tickets', 'x-telegram'],
];

// ——— Chizma o'lchamlari ———
const NODE_W = 250;
const NODE_H = 46;
const GAP_Y = 12;
const STEP = NODE_H + GAP_Y;
const COL_X = [24, 380, 736];
const CANVAS_W = COL_X[2] + NODE_W + 24;

const COLUMNS: { kind: NodeKind; nodes: GraphNode[] }[] = [
  { kind: 'screen', nodes: SCREENS },
  { kind: 'api', nodes: APIS },
  { kind: 'source', nodes: SOURCES },
];

const CANVAS_H = Math.max(...COLUMNS.map((c) => c.nodes.length)) * STEP + 24;

/** Har bir tugunning chizmadagi o'rni — bir marta hisoblanadi. */
const POSITIONS: Record<string, { x: number; y: number; kind: NodeKind }> = {};
COLUMNS.forEach((col, colIndex) => {
  const columnH = col.nodes.length * STEP;
  const offsetY = (CANVAS_H - columnH) / 2;
  col.nodes.forEach((node, i) => {
    POSITIONS[node.id] = { x: COL_X[colIndex], y: offsetY + i * STEP, kind: col.kind };
  });
});

const KIND_STYLE: Record<NodeKind, { border: string; bg: string; text: string; stroke: string }> = {
  screen: {
    border: 'border-brand-400 dark:border-brand-600',
    bg: 'bg-brand-50 dark:bg-brand-950/50',
    text: 'text-brand-900 dark:text-brand-100',
    stroke: '#6366F1',
  },
  api: {
    border: 'border-warning-400 dark:border-warning-600',
    bg: 'bg-warning-50 dark:bg-warning-950/50',
    text: 'text-amber-900 dark:text-amber-100',
    stroke: '#F59E0B',
  },
  source: {
    border: 'border-success-400 dark:border-success-600',
    bg: 'bg-success-50 dark:bg-success-950/50',
    text: 'text-emerald-900 dark:text-emerald-100',
    stroke: '#22C55E',
  },
};

/** Tanlangan tugun bilan bog'liq hamma tugunlarni topadi (ikki tomonlama). */
const relatedTo = (startId: string): Set<string> => {
  const found = new Set<string>([startId]);
  const queue = [startId];

  while (queue.length > 0) {
    const current = queue.shift() as string;
    EDGES.forEach(([from, to]) => {
      if (from === current && !found.has(to)) {
        found.add(to);
        queue.push(to);
      }
      if (to === current && !found.has(from)) {
        found.add(from);
        queue.push(from);
      }
    });
  }

  return found;
};

export const IntegrationMapPage: React.FC = () => {
  const t = useT();
  const [selected, setSelected] = useState<string | null>(null);

  const active = useMemo(() => (selected ? relatedTo(selected) : null), [selected]);
  const isDimmed = (id: string) => active !== null && !active.has(id);

  const legend = [
    { kind: 'screen' as NodeKind, icon: Monitor, label: t('integrationMap.legendScreens'), count: SCREENS.length },
    { kind: 'api' as NodeKind, icon: Server, label: t('integrationMap.legendApis'), count: APIS.length },
    { kind: 'source' as NodeKind, icon: Database, label: t('integrationMap.legendSources'), count: SOURCES.length },
  ];

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-6 space-y-5">
      {/* Sarlavha */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="w-11 h-11 rounded-2xl bg-brand-50 dark:bg-brand-950/50 flex items-center justify-center text-brand-600 dark:text-brand-400">
            <Network className="w-6 h-6" />
          </div>
          <div>
            <h1 className="text-lg sm:text-xl font-black text-slate-900 dark:text-slate-100">{t('integrationMap.title')}</h1>
            <p className="text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400">{t('integrationMap.subtitle')}</p>
          </div>
        </div>

        {selected && (
          <button
            type="button"
            onClick={() => setSelected(null)}
            className="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-all cursor-pointer"
          >
            <RefreshCw className="w-4 h-4" />
            {t('integrationMap.resetFilter')}
          </button>
        )}
      </div>

      {/* Izoh (legenda) */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
        {legend.map((item) => (
          <div
            key={item.kind}
            className={`p-4 rounded-2xl border-2 shadow-sm flex items-center justify-between ${KIND_STYLE[item.kind].border} ${KIND_STYLE[item.kind].bg}`}
          >
            <div>
              <p className={`text-xl font-black ${KIND_STYLE[item.kind].text}`}>{item.count}</p>
              <p className="text-xs font-bold text-slate-500 dark:text-slate-400">{item.label}</p>
            </div>
            <item.icon className={`w-5 h-5 ${KIND_STYLE[item.kind].text}`} />
          </div>
        ))}
      </div>

      <p className="text-xs font-semibold text-slate-500 dark:text-slate-400">{t('integrationMap.hint')}</p>

      {/* Graf */}
      <div className="bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-x-auto">
        <div className="relative mx-auto" style={{ width: CANVAS_W, height: CANVAS_H }}>
          {/* Bog'lanish chiziqlari */}
          <svg
            className="absolute inset-0 pointer-events-none"
            width={CANVAS_W}
            height={CANVAS_H}
            aria-hidden="true"
          >
            {EDGES.map(([from, to]) => {
              const a = POSITIONS[from];
              const b = POSITIONS[to];
              if (!a || !b) return null;

              const x1 = a.x + NODE_W;
              const y1 = a.y + NODE_H / 2;
              const x2 = b.x;
              const y2 = b.y + NODE_H / 2;
              const midX = (x1 + x2) / 2;
              const dimmed = active !== null && !(active.has(from) && active.has(to));

              return (
                <path
                  key={`${from}-${to}`}
                  d={`M ${x1} ${y1} C ${midX} ${y1}, ${midX} ${y2}, ${x2} ${y2}`}
                  fill="none"
                  stroke={KIND_STYLE[a.kind].stroke}
                  strokeWidth={dimmed ? 1 : 2}
                  strokeOpacity={dimmed ? 0.08 : 0.55}
                />
              );
            })}
          </svg>

          {/* Tugunlar */}
          {COLUMNS.flatMap((col) => col.nodes).map((node) => {
            const pos = POSITIONS[node.id];
            const style = KIND_STYLE[node.kind];
            const dimmed = isDimmed(node.id);

            return (
              <button
                key={node.id}
                type="button"
                onClick={() => setSelected((prev) => (prev === node.id ? null : node.id))}
                aria-pressed={selected === node.id}
                className={`absolute text-left px-3 py-1.5 rounded-xl border-2 shadow-sm transition-all cursor-pointer overflow-hidden ${style.border} ${style.bg} ${
                  selected === node.id ? 'ring-2 ring-offset-1 ring-slate-900/40 dark:ring-white/40 dark:ring-offset-slate-800' : ''
                } ${dimmed ? 'opacity-20' : 'hover:shadow-md'}`}
                style={{ left: pos.x, top: pos.y, width: NODE_W, height: NODE_H }}
              >
                <span className={`block text-xs font-black truncate ${style.text}`}>{node.label}</span>
                {node.detail && (
                  <span className="block text-[10px] font-semibold text-slate-500 dark:text-slate-400 truncate font-mono">
                    {node.detail}
                  </span>
                )}
              </button>
            );
          })}
        </div>
      </div>

      <p className="text-[11px] font-semibold text-slate-400 dark:text-slate-500">{t('integrationMap.footnote')}</p>
    </div>
  );
};
