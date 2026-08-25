<?php
namespace App\Modules\SLA\Domain\Events;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class SlaWarningReached {
    use Dispatchable, SerializesModels;
    public function __construct(public int $ticketSlaId, public int $ticketId) {}
}
