<?php
namespace App\Modules\Ticketing\Domain\Events;
use App\Modules\Ticketing\Infrastructure\Eloquent\Attachment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class AttachmentStored {
    use Dispatchable, SerializesModels;
    public function __construct(public Attachment $attachment) {}
}
