<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class RegionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            // Bankning viloyat kodi (AV020, XM000...). Bo'sh yoki "00000" —
            // bosh boshqarma (Respublika), ya'ni viloyat emas.
            'local_code' => $this->local_code,
            'manager' => $this->whenLoaded('manager', fn() => new EmployeeResource($this->manager)),
            'is_active' => $this->is_active,
        ];
    }
}
