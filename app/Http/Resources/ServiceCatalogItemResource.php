<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ServiceCatalogItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'organization_id' => $this->organization_id,
            'service_offering_id' => $this->service_offering_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'form_schema' => $this->form_schema,
            'fulfillment_workflow_id' => $this->fulfillment_workflow_id,
            'approval_workflow_id' => $this->approval_workflow_id,
            'estimated_minutes' => $this->estimated_minutes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
