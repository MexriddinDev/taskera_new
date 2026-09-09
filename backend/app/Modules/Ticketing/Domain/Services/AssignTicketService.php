<?php

namespace App\Modules\Ticketing\Domain\Services;

use App\Modules\Ticketing\Domain\Events\TicketAssigned;
use App\Modules\Ticketing\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AssignTicketService
{
    public function __construct(private TicketRepositoryInterface $ticketRepository) {}

    /**
     * @param  int|null  $teamId  `null` — guruh o'zgarmaydi (faqat xodim almashadi).
     */
    public function execute(int $ticketId, ?int $teamId, ?int $assigneeUserId, int $assignedByUserId, ?string $reason = null): Ticket
    {
        return DB::transaction(function () use ($ticketId, $teamId, $assigneeUserId, $assignedByUserId, $reason) {
            $ticket = $this->ticketRepository->getForUpdate($ticketId);
            $fromTeamId = $ticket->assigned_team_id;
            $fromUserId = $ticket->assigned_user_id;

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

            $this->ticketRepository->save($ticket);

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

            return $ticket;
        });
    }
}
