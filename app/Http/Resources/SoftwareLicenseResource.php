<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SoftwareLicenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'software_product' => $this->whenLoaded('softwareProduct', fn() => new SoftwareProductResource($this->softwareProduct)),
            'vendor' => $this->whenLoaded('vendor', fn() => new VendorResource($this->vendor)),
            'license_type' => $this->license_type,
            'quantity' => $this->quantity,
            'valid_from' => $this->valid_from,
            'valid_to' => $this->valid_to,
            'cost' => $this->cost,
            'currency' => $this->currency,
        ];
    }
}
