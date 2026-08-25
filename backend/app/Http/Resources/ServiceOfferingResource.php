<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ServiceOfferingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'service' => $this->whenLoaded('service', fn() => new ServiceResource($this->service)),
            'category_id' => $this->category_id,
            'is_requestable' => $this->is_requestable,
            'is_active' => $this->is_active,
        ];
    }
}
