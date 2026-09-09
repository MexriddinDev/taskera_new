<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PermitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'full_name' => $this->full_name,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'visit_purpose' => $this->visit_purpose,
            'visit_at' => $this->visit_at?->toIso8601String(),
            'visitor_organization' => $this->visitor_organization,
            'host_department' => $this->host_department,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
