<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class LookupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code ?? null,
            'name' => $this->name,
            'color' => $this->color ?? null,
            'is_active' => $this->is_active,
        ];
    }
}
