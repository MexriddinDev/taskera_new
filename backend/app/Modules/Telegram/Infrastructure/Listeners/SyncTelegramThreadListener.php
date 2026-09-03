<?php

namespace App\Modules\Telegram\Infrastructure\Listeners;

use App\Modules\Telegram\Infrastructure\Services\TelegramNotifierService;
use App\Modules\Ticketing\Domain\Events\CommentAdded;
use App\Modules\Ticketing\Domain\Events\TicketAssigned;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use App\Modules\Ticketing\Domain\Events\TicketCreated;
use App\Modules\Ticketing\Domain\Events\TicketStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Zayavka hodisalarini Telegram orqali tegishli foydalanuvchilarga yetkazadi:
 * - status o'zgarganda — so'rovchiga
 * - biriktirilganda — ijrochiga va so'rovchiga
 * - yangi zayavkada — barcha xodimlarga
 */
class SyncTelegramThreadListener implements ShouldQueue
{
    use InteractsWithQueue;

    /** comment_types: 1 = PUBLIC (ommaviy), 2 = INTERNAL (ichki izoh). */
    private const COMMENT_TYPE_PUBLIC = 1;

    /** ticket_statuses: 7 = Bajarildi, 8 = Yopildi. */
    private const RESOLVED_STATUSES = [7, 8];

    private const STATUS_EMOJI = [
        '1' => '🟦', '2' => '🟦', '3' => '🟦',
        '4' => '🟪', '5' => '🟪', '6' => '🟪',
        '7' => '🟩', '8' => '🟩',
        '9' => '🟥',
        '10' => '⬜',
    ];

    public function __construct(private readonly TelegramNotifierService $notifier) {}

    public function handle(object $event): void
    {
        if ($event instanceof TicketStatusChanged) {
            $this->onStatusChanged($event);
        } elseif ($event instanceof TicketAssigned) {
            $this->onAssigned($event);
        } elseif ($event instanceof TicketCreated) {
            $this->onCreated($event);
        } elseif ($event instanceof CommentAdded) {
            $this->onCommentAdded($event);
        }
    }

    private function onStatusChanged(TicketStatusChanged $event): void
    {
        $ticket = $event->ticket;
        $organizationId = (int) $ticket->organization_id;
        $statusName = DB::table('ticket_statuses')->where('id', $event->toStatusId)->value('name') ?? "Noma'lum";
        $emoji = self::STATUS_EMOJI[(string) $event->toStatusId] ?? '▪️';
        $assigneeName = $ticket->assigned_user_id
            ? (DB::table('users')->where('id', $ticket->assigned_user_id)->value('username') ?? '-')
            : '-';

        $text =
            "📣 <b>Zayavka holati o'zgardi</b>\n\n".
            '🎫 <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b>\n".
            '📝 '.htmlspecialchars(mb_substr((string) $ticket->subject, 0, 120))."\n".
            '📊 Holat: '.$emoji.' '.htmlspecialchars($statusName)."\n".
            '🔧 Ijrochi: '.htmlspecialchars($assigneeName);

        $markup = null;

        // Zayavka yopilganda so'rovchi darrov baholay olsin yoki qaytara olsin —
        // botga kirib ro'yxatdan qidirish shart bo'lmasin. Tugmalar botdagi
        // mavjud callback'larni ishlatadi.
        if (in_array((int) $event->toStatusId, self::RESOLVED_STATUSES, true) && empty($ticket->client_rating)) {
            $text .= "\n\n<i>Ish bajarildi. Iltimos, baholang — shundan keyin yangi zayavka yubora olasiz.</i>";

            $markup = [
                'inline_keyboard' => [
                    [['text' => '⭐ Baholash', 'callback_data' => 'ticket:rate:'.$ticket->id]],
                    [['text' => '↩️ Qaytarib yuborish', 'callback_data' => 'ticket:return:'.$ticket->id]],
                ],
            ];
        }

        // So'rovchiga xabar (holatni o'zi o'zgartirmagan bo'lsa)
        if ($ticket->requester_user_id && (int) $ticket->requester_user_id !== (int) $event->changedByUserId) {
            $this->notifier->sendToUser($organizationId, (int) $ticket->requester_user_id, $text, $markup);
        }
    }

    private function onAssigned(TicketAssigned $event): void
    {
        $ticket = $event->ticket;
        $organizationId = (int) $ticket->organization_id;

        if ($event->toUserId && (int) $event->toUserId !== (int) $event->assignedByUserId) {
            $this->notifier->sendToUser($organizationId, (int) $event->toUserId,
                "🔧 <b>Sizga zayavka biriktirildi</b>\n\n".
                '🎫 <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b>\n".
                '📝 '.htmlspecialchars(mb_substr((string) $ticket->subject, 0, 120))."\n".
                "Qabul qilish va bajarish uchun botda «🛠 Mening vazifalarim» bo'limiga o'ting."
            );
        }

        if ($ticket->requester_user_id) {
            $this->notifier->sendToUser($organizationId, (int) $ticket->requester_user_id,
                "✅ <b>Zayavkangiz qabul qilindi</b>\n\n".
                '🎫 <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b>\n".
                'Zayavkangiz mutaxassis tomonidan qabul qilindi.'
            );
        }
    }

    private function onCreated(TicketCreated $event): void
    {
        $ticket = $event->ticket;
        $organizationId = (int) $ticket->organization_id;
        $requesterName = $ticket->requester_user_id
            ? (DB::table('users')->where('id', $ticket->requester_user_id)->value('username') ?? '-')
            : '-';

        $text =
            "🆕 <b>Yangi zayavka</b>\n\n".
            '🎫 <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b>\n".
            '📝 '.htmlspecialchars(mb_substr((string) $ticket->subject, 0, 120))."\n".
            '👤 So\'rovchi: '.htmlspecialchars($requesterName)."\n\n".
            "📥 Qabul qilish uchun botda «📥 Ochiq zayavkalar» bo'limiga o'ting.";

        $this->notifier->sendToStaff($organizationId, $text, $ticket->requester_user_id ? (int) $ticket->requester_user_id : null);
    }

    /**
     * Zayavkaga yozilgan izohni ikkinchi tomonga Telegram orqali yetkazadi.
     *
     * So'rovchi yozsa — ijrochiga, ijrochi yozsa — so'rovchiga. Uchinchi shaxs
     * (masalan admin) yozsa — ikkalasiga ham. Muallifning o'ziga qaytarilmaydi.
     *
     * Ichki izohlar (INTERNAL) yuborilmaydi — ular xodimlar orasidagi yozishma.
     */
    private function onCommentAdded(CommentAdded $event): void
    {
        $comment = $event->comment;

        if ((int) ($comment->type_id ?? 1) !== self::COMMENT_TYPE_PUBLIC) {
            return;
        }

        if ($comment->commentable_type !== Ticket::class) {
            return;
        }

        $ticket = DB::table('tickets')->where('id', $comment->commentable_id)->first();
        if (! $ticket) {
            return;
        }

        $authorId = (int) ($comment->author_user_id ?? 0);
        $authorName = $authorId
            ? (DB::table('users')->where('id', $authorId)->value('username') ?? 'Foydalanuvchi')
            : 'Tizim';

        $text =
            "💬 <b>Zayavkaga yangi xabar</b>\n\n".
            '🎫 <b>'.htmlspecialchars((string) $ticket->ticket_no)."</b>\n".
            '👤 '.htmlspecialchars($authorName)."\n\n".
            htmlspecialchars(mb_substr((string) $comment->body, 0, 500));

        $recipients = array_unique(array_filter([
            (int) ($ticket->requester_user_id ?? 0),
            (int) ($ticket->assigned_user_id ?? 0),
        ]));

        foreach ($recipients as $userId) {
            if ($userId === $authorId) {
                continue;
            }

            $this->notifier->sendToUser((int) $ticket->organization_id, $userId, $text);
        }
    }
}
