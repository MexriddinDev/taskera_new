<?php

namespace App\Modules\Ticketing\Domain\Services;

use App\Modules\Ticketing\Domain\Events\TicketAssigned;
use App\Modules\Ticketing\Domain\Events\TicketStatusChanged;
use App\Modules\Ticketing\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AssignTicketService
{
    /** Hali qabul qilinmagan zayavka holatlari. */
    private const OPEN_STATUS_IDS = [1, 2, 3];

    /** "Jarayonda". */
    private const IN_PROGRESS_STATUS_ID = 4;

    public function __construct(private TicketRepositoryInterface $ticketRepository) {}

    /**
     * @param  int|null  $teamId  `null` — guruh o'zgarmaydi (faqat xodim almashadi).
     */
    public function execute(int $ticketId, ?int $teamId, ?int $assigneeUserId, int $assignedByUserId, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($ticketId, $teamId, $assigneeUserId, $assignedByUserId, $reason) {
            $ticket = $this->ticketRepository->getForUpdate($ticketId);
            $actor = \App\Models\User::findOrFail($assignedByUserId);
            abort_unless(\App\Support\RegionalRouting::canWork($actor, $ticket), 403);
            if ($teamId !== null && $teamId !== (int) $ticket->assigned_team_id) {
                $team = DB::table('teams')->where('organization_id', $ticket->organization_id)->where('id', $teamId)->whereNull('deleted_at')->where('is_active', true)->first();
                abort_unless($team && ($ticket->support_scope === 'regional'
                    ? (int) $team->region_id === (int) $ticket->region_id
                    : $team->region_id === null), 422, 'Guruh zayavka hududiga mos emas.');
            }
            if ($assigneeUserId !== null) {
                $assignee = \App\Models\User::where('organization_id', $ticket->organization_id)->where('status', 'ACTIVE')->find($assigneeUserId);
                abort_unless($assignee && \App\Support\RegionalRouting::canWork($assignee, $ticket), 422, 'Xodim zayavka hududiga biriktirilmagan.');
                if ($ticket->support_scope === 'regional' && ! $assignee->isSuperAdmin()) {
                    abort_unless(in_array($teamId ?? (int) $ticket->assigned_team_id, \App\Support\RegionalRouting::regionalTeamIds($assignee)), 422);
                }
            }
            $fromTeamId = $ticket->assigned_team_id;
            $fromUserId = $ticket->assigned_user_id;

            // HECH NARSA O'ZGARMASA — hech narsa yozilmaydi.
            //
            // Ilgari o'ziga biriktirilgan zayavkani boshqaruv panelidan qayta
            // qayta "biriktirish" mumkin edi: har bosishda `ticket_assignment_history`
            // ga `from_user_id === to_user_id` bo'lgan bo'sh yozuv tushar va
            // `TicketAssigned` hodisasi ishga tushib, xodimga takroriy
            // bildirishnoma (Telegram + ichki) yuborilardi.
            //
            // Amal quyidagi uchtasidan birortasi ham o'zgarmasa — bo'sh amal:
            //   ijrochi, guruh (ataylab berilgan bo'lsa), holat.
            // `assigned_user_id` da `int` cast yo'q — drayverga qarab satr
            // ham qaytishi mumkin, shuning uchun solishtirishdan oldin
            // ikkalasi ham `?int` ga keltiriladi.
            $normalize = static fn ($value): ?int => $value === null ? null : (int) $value;
            $isSameUser = $normalize($fromUserId) === $normalize($assigneeUserId);
            $isSameTeam = $teamId === null || (int) $teamId === (int) $fromTeamId;
            $needsStatusFix = $assigneeUserId && in_array((int) $ticket->status_id, self::OPEN_STATUS_IDS, true);

            if ($isSameUser && $isSameTeam && ! $needsStatusFix) {
                return $ticket;
            }

            if ($fromUserId && $fromUserId !== $assigneeUserId && empty(trim((string) $reason))) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'reason' => "Boshqa xodimga biriktirilgan zayavkani o'ziga olishda sabab kiritish majburiy.",
                ]);
            }

            // Guruh faqat ATAYLAB berilganda o'zgaradi.
            //
            // Ilgari bu satr shartsiz edi: zayavkani xodimga biriktirish
            // (so'rovda `team_id` yo'q) uning guruhini NULL qilib qo'yardi —
            // shu bilan SLA muddatlari ham yo'qolardi, chunki qoida aynan
            // guruhga bog'langan.
            if ($teamId !== null) {
                $ticket->assigned_team_id = $teamId;
            }

            $ticket->assigned_user_id = $assigneeUserId;

            // Ijrochi almashdi — taymer noldan boshlanadi.
            //
            // Yangi xodim o'zidan oldingi kutish vaqti uchun javob bermaydi,
            // shuning uchun `started_at` qayta yoziladi. Oldingi ijrochining
            // vaqti yo'qolmaydi: u tarix yozuviga (`spent_minutes`) tushadi va
            // kartochkada ko'rinadi.
            $previousSpentMinutes = null;
            $userChanged = $fromUserId !== $assigneeUserId;

            if ($userChanged && $ticket->started_at) {
                $previousSpentMinutes = max(1, (int) abs(now()->diffInMinutes(Carbon::parse($ticket->started_at))));
                $ticket->started_at = $assigneeUserId ? now() : null;
            }

            // Birinchi marta biriktirilyapti — taymer shu yerdan boshlanadi.
            // Saytdagi "Qabul qilish" tugmasi ham aynan shunday ishlaydi, shu
            // sabab SLA ning "qabul qilish" bosqichi Telegram bot orqali
            // olingan zayavkada ham to'g'ri yopiladi (ilgari u yopilmay,
            // muddat buzildi bo'lib turaverardi).
            if ($assigneeUserId && is_null($ticket->started_at)) {
                $ticket->started_at = now();
            }

            // Ijrochisi bor zayavka darrov "Jarayonda" bo'ladi.
            //
            // Ilgari oraliq "Qabul qilingan" holati bor edi: zayavka biriktirilgan,
            // lekin holati hamon "Ochiq". "Ochiq" ustuni taxtalardan olib
            // tashlangach bunday zayavkalar hech qayerda ko'rinmay qolardi.
            $fromStatusId = (int) $ticket->status_id;
            $statusChanged = $assigneeUserId && in_array($fromStatusId, self::OPEN_STATUS_IDS, true);

            if ($statusChanged) {
                $ticket->status_id = self::IN_PROGRESS_STATUS_ID;
            }

            $this->ticketRepository->save($ticket);

            if ($statusChanged) {
                DB::table('ticket_status_history')->insert([
                    'ticket_id' => $ticket->id,
                    'from_status_id' => $fromStatusId,
                    'to_status_id' => self::IN_PROGRESS_STATUS_ID,
                    'changed_by' => $assignedByUserId,
                    'source_id' => 1,
                    'action' => 'STATUS_TRANSITION',
                    'reason' => $reason,
                    'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
                    'created_at' => now(),
                ]);
            }

            DB::table('ticket_assignment_history')->insert([
                'ticket_id' => $ticket->id,
                'from_team_id' => $fromTeamId,
                'to_team_id' => $ticket->assigned_team_id,
                'from_user_id' => $fromUserId,
                'to_user_id' => $assigneeUserId,
                'changed_by' => $assignedByUserId,
                'reason' => $reason,
                'spent_minutes' => $previousSpentMinutes,
                'source_id' => 1,
                'created_at' => now(),
            ]);

            event(new TicketAssigned($ticket, $fromUserId, $assigneeUserId, $assignedByUserId));

            if ($statusChanged) {
                event(new TicketStatusChanged($ticket, $fromStatusId, self::IN_PROGRESS_STATUS_ID, $assignedByUserId));
            }

            return $ticket;
        });
    }
}
