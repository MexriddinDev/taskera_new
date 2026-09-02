<?php

namespace App\Modules\Ticketing\Infrastructure\Repositories;

use App\Modules\Ticketing\Domain\Repositories\TicketRepositoryInterface;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;

class TicketRepository implements TicketRepositoryInterface
{
    public function findById(int $id): ?Ticket
    {
        return Ticket::find($id);
    }

    public function findByPublicId(string $publicId): ?Ticket
    {
        return Ticket::where('public_id', $publicId)->first();
    }

    public function getForUpdate(int $id): ?Ticket
    {
        return Ticket::where('id', $id)->lockForUpdate()->first();
    }

    /** Web/bot orqali yaratilgan zayavkalar raqami shu prefiks bilan boradi. */
    private const NUMBER_PREFIX = 'INC-';

    public function nextNumber(int $organizationId): string
    {
        // Raqam FAQAT 'INC-' prefiksli zayavkalar orasidan, eng katta tartib
        // raqami bo'yicha olinadi.
        //
        // Ilgari "eng oxirgi qo'shilgan zayavka" (latest('id')) olinar edi va
        // undan trailing raqamlar ajratilardi. Bazada boshqa formatdagi raqamlar
        // ham bor (Telegram bot 'TG-260828-0EC6F0' kabi yozadi), shuning uchun:
        //   TG-260828-0EC6F0 -> trailing '0'      -> INC-000001 (DUBLIKAT!)
        //   TG-260901-ABC123 -> trailing '123'    -> INC-000124 (tasodifiy sakrash)
        // Endi format aralashib ketsa ham hisob buzilmaydi.
        //
        // RACE CONDITION himoyasi: qator lockForUpdate() bilan qulflanadi —
        // bu metod tranzaksiya ichida chaqiriladi (store() dagidek).
        $last = Ticket::withTrashed()
            ->where('organization_id', $organizationId)
            ->where('ticket_no', 'LIKE', self::NUMBER_PREFIX.'%')
            ->orderByRaw('CAST(SUBSTRING(ticket_no, ?) AS UNSIGNED) DESC', [strlen(self::NUMBER_PREFIX) + 1])
            ->lockForUpdate()
            ->value('ticket_no');

        $lastNumber = 0;
        if ($last !== null) {
            $lastNumber = (int) substr((string) $last, strlen(self::NUMBER_PREFIX));
        }

        return sprintf(self::NUMBER_PREFIX.'%06d', $lastNumber + 1);
    }

    public function save(Ticket $ticket): bool
    {
        return $ticket->save();
    }
}
