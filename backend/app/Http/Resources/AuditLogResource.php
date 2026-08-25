<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actor = $this->relationLoaded('actorUser') ? $this->actorUser : null;

        $actorName = null;
        if ($actor) {
            $employee = $actor->relationLoaded('employee') ? $actor->employee : null;
            $actorName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? '')) ?: ($actor->username ?? "user #{$actor->id}");
        }

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'actor_user_id' => $this->actor_user_id,
            'actor_employee_id' => $this->actor_employee_id,
            'actorName' => $actorName,
            'action' => $this->action,
            'description' => $this->description ?? $this->reason,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'auditable_public_id' => $this->auditable_public_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'changed_fields' => $this->changed_fields,
            'source' => $this->source,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'session_id' => $this->session_id,
            'correlation_id' => $this->correlation_id,
            'request_id' => $this->request_id,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
            'createdAt' => $this->created_at ? \Carbon\Carbon::parse($this->created_at)->timezone('Asia/Tashkent')->format('d-M Y, H:i') : null,
        ];
    }
}
