import React from 'react';
import { useT } from '@/shared/presentation/i18n/i18n';

export type PermitStatus = 'PENDING' | 'APPROVED' | 'REJECTED';

/** Elektron ruxsatnoma so'rovi — ikki sahifada ham shu shakl ishlatiladi. */
export interface PermitRequest {
  id: number;
  last_name: string | null;
  first_name: string | null;
  middle_name: string | null;
  full_name: string;
  document_type: string | null;
  visitor_organization: string | null;
  document_number: string | null;
  host_department: string | null;
  has_photo: boolean;
  visit_purpose: string;
  visit_at: string | null;
  status: PermitStatus;
  decision_reason: string | null;
  requester: string | null;
  decided_by: string | null;
  created_at: string | null;
  /** So'rov yuborgan xodim kartochkasi — qorovul postida "kim chaqirgan". */
  requester_card: {
    name: string | null;
    department: string | null;
    position: string | null;
  };
  entered_at: string | null;
  exited_at: string | null;
}

const TONE: Record<PermitStatus, string> = {
  PENDING: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
  APPROVED: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
  REJECTED: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
};

export const PermitStatusBadge: React.FC<{ status: PermitStatus }> = ({ status }) => {
  const t = useT();

  return (
    <span className={`inline-flex items-center rounded-lg px-2 py-1 text-[11px] font-bold ${TONE[status]}`}>
      {t(`permitReq.status.${status}`)}
    </span>
  );
};
