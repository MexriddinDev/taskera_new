import type { TFunction } from '@/shared/presentation/i18n/i18n';

/** Qiymatlar — tarjima kalitlari; ko‘rsatishdan oldin `t(...)` orqali o‘tkaziladi. */
export const metrics = {
  ASSIGNMENT: 'sla.metric.ASSIGNMENT', ACCEPTANCE: 'sla.metric.ACCEPTANCE', FIRST_RESPONSE: 'sla.metric.FIRST_RESPONSE',
  WORK_START: 'sla.metric.WORK_START', RESOLUTION: 'sla.metric.RESOLUTION', CLOSURE: 'sla.metric.CLOSURE', UPDATE: 'sla.metric.UPDATE',
} as const;
export type Metric = keyof typeof metrics;
export type Option = { id: number; name: string; parent_id?: number | null; timezone_id?: number; is_24x7?: boolean };
export type Options = Record<'categories' | 'services' | 'departments' | 'users' | 'priorities' | 'sources' | 'timezones' | 'calendars', Option[]>;
export type Target = { metric: Metric; minutes: number; start: string; calendar_mode: 'BUSINESS' | '24X7' };
export type Escalation = { threshold: number; assignee: boolean; user_ids: number[]; channels: string[] };
export type Config = {
  code: string; name: string; description: string; effective_from: string; effective_to: string | null;
  calendar_id: number | null; policy_priority: number;
  scope: { category_id?: number | null; subcategory_id?: number | null; priority_id?: number | null; service_id?: number | null; department_id?: number | null; source_id?: number | null; requester_type?: string | null };
  targets: Target[]; pause_statuses: string[];
  extension: { enabled: boolean; max_minutes: number; approver_ids: number[] };
  escalations: Escalation[];
  calendar?: { name: string; timezone: string };
};
export type Policy = { id: number; code: string; name: string; publication_status: string; version: number; effective_from: string; draft_config: Config | null };
export type Instance = { id: number; run_id: number; ticket_id: number; metric: Metric; status: string; target_minutes: number; extension_minutes: number;
  started_at: string | null; due_at: string | null; completed_at: string | null; breached_at: string | null; paused_at: string | null;
  remaining_seconds: number | null; paused_seconds: number; escalation_index: number; ticket_no?: string; subject?: string; assignee?: string; category?: string;
  /** Monitoring javobida: ish kalendari bo‘yicha qolgan vaqt, sarflangan foiz va risk darajasi. */
  percent?: number | null; level?: RiskLevel | string };
export type RiskLevel = 'OK' | 'WARNING' | 'HIGH' | 'BREACHED';
export type MonitoringSummary = { breached: number; high: number; warning: number; total: number };
export const riskNames: Record<RiskLevel, string> = { OK: 'sla.risk.OK', WARNING: 'sla.risk.WARNING', HIGH: 'sla.risk.HIGH', BREACHED: 'sla.risk.BREACHED' };
export const riskClass: Record<RiskLevel, string> = {
  OK: 'text-emerald-500 bg-emerald-500/10 border-emerald-500/30',
  WARNING: 'text-amber-500 bg-amber-500/10 border-amber-500/30',
  HIGH: 'text-orange-500 bg-orange-500/10 border-orange-500/30',
  BREACHED: 'text-red-500 bg-red-500/10 border-red-500/30',
};
export type Stats = { total: number; completed: number; breached: number; compliance: number | null; average_minutes: number | null; median_minutes: number | null; p90_minutes: number | null; p95_minutes: number | null };
export type Reports = Stats & { active_policies: number; running: number; paused: number; metrics: Record<string, Stats>; categories: Record<string, Stats>; employees: Record<string, Stats>; departments: Record<string, Stats> };
export const emptyOptions: Options = { categories: [], services: [], departments: [], users: [], priorities: [], sources: [], timezones: [], calendars: [] };
const starts: Record<Metric, string> = { ASSIGNMENT: 'CREATED', ACCEPTANCE: 'ASSIGNED', FIRST_RESPONSE: 'CREATED', WORK_START: 'ACCEPTED', RESOLUTION: 'CREATED', CLOSURE: 'RESOLVED', UPDATE: 'ACCEPTED' };
export const initialConfig = (): Config => ({
  code: '', name: '', description: '', effective_from: new Date().toLocaleDateString('en-CA'), effective_to: null,
  calendar_id: null, policy_priority: 0, scope: {},
  targets: (Object.keys(metrics) as Metric[]).map((metric, index) => ({ metric, minutes: [15, 30, 60, 60, 480, 120, 240][index], start: starts[metric], calendar_mode: 'BUSINESS' })),
  pause_statuses: ['WAITING_USER', 'WAITING_VENDOR'], extension: { enabled: false, max_minutes: 240, approver_ids: [] },
  escalations: [75, 90, 100, 120].map(threshold => ({ threshold, assignee: true, user_ids: [], channels: ['IN_APP'] })),
});
export const api = '/sla-management';
export const fieldClass = 'w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/70 px-3 py-2.5 text-sm text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-cyan-500';
export const panelClass = 'rounded-xl border border-slate-200 dark:border-slate-700/70 bg-white dark:bg-slate-900/80';
export const buttonClass = 'inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed';
export const primaryClass = 'inline-flex items-center justify-center gap-2 rounded-lg bg-cyan-600 hover:bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-cyan-500/10 disabled:opacity-50 disabled:cursor-not-allowed';
export const statusNames: Record<string, string> = { ACTIVE: 'sla.state.ACTIVE', DRAFT: 'sla.state.DRAFT', ARCHIVED: 'sla.state.ARCHIVED', RUNNING: 'sla.state.RUNNING', BREACHED: 'sla.state.BREACHED', PAUSED: 'sla.state.PAUSED', COMPLETED: 'sla.state.COMPLETED', PENDING: 'sla.state.PENDING', EXCLUDED: 'sla.state.EXCLUDED' };
export const dateTime = (value?: string | null) => value ? new Date(value.includes('T') ? value : value.replace(' ', 'T') + 'Z').toLocaleString() : '—';
export const minutesText = (t: TFunction, value: number) => value >= 60 && value % 60 === 0 ? t('sla.unitHours', { count: value / 60 }) : t('sla.unitMinutes', { count: value });
/** Qolgan (yoki kechikkan) ish vaqti: 2 soat 15 daq. / -14 daq. kechikdi. */
export const remainingText = (t: TFunction, seconds?: number | null) => {
  if (seconds == null) return '—';
  const total = Math.round(Math.abs(seconds) / 60);
  const text = total >= 60 ? t('sla.unitHoursMinutes', { hours: Math.floor(total / 60), minutes: total % 60 }) : t('sla.unitMinutesShort', { minutes: total });
  return seconds < 0 ? t('sla.overdueBy', { time: text }) : text;
};
