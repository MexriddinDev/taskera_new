<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SlaPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'calendar_id' => $this->calendar_id,
            'applies_to_type' => $this->applies_to_type,
            'conditions' => $this->conditions,
            'is_active' => $this->is_active,
            'version' => $this->version,
            'effective_from' => $this->effective_from,
            'effective_to' => $this->effective_to,
        ];
    }
}
