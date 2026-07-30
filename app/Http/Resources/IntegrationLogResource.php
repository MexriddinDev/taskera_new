<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class IntegrationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'integration_id' => $this->integration_id,
            'direction' => $this->direction,
            'operation' => $this->operation,
            'correlation_id' => $this->correlation_id,
            'request_meta' => $this->request_meta,
            'response_meta' => $this->response_meta,
            'status' => $this->status,
            'duration_ms' => $this->duration_ms,
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
