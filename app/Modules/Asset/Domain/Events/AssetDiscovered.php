<?php
namespace App\Modules\Asset\Domain\Events;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
class AssetDiscovered {
    use Dispatchable, SerializesModels;
    public function __construct(public int $assetId, public array $attributes) {}
}
