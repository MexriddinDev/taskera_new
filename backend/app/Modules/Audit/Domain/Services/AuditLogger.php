<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Central helper for writing rows into the audit_logs table.
 * Silently ignores failures (audit must never break the main flow).
 */
final class AuditLogger
{
    /**
     * @param  array{
     *     actor_user_id?: int|null,
     *     auditable_type?: string|null,
     *     auditable_id?: int|null,
     *     auditable_public_id?: string|null,
     *     old_values?: array|null,
     *     new_values?: array|null,
     *     changed_fields?: array|null,
     *     source?: string|null,
     *     reason?: string|null,
     *     correlation_id?: string|null,
     * }  $attributes
     */
    public static function log(Request $request, string $action, string $description, array $attributes = []): void
    {
        try {
            $actor = $request->user() ?? auth()->user();

            DB::table('audit_logs')->insert(array_filter([
                'organization_id' => $attributes['organization_id'] ?? 1,
                'actor_user_id' => $attributes['actor_user_id'] ?? ($actor?->id),
                'actor_employee_id' => $attributes['actor_employee_id'] ?? ($actor?->employee_id),
                'action' => $action,
                'description' => $description,
                'auditable_type' => $attributes['auditable_type'] ?? null,
                'auditable_id' => $attributes['auditable_id'] ?? null,
                'auditable_public_id' => $attributes['auditable_public_id'] ?? null,
                'old_values' => isset($attributes['old_values']) ? json_encode($attributes['old_values']) : null,
                'new_values' => isset($attributes['new_values']) ? json_encode($attributes['new_values']) : null,
                'changed_fields' => isset($attributes['changed_fields']) ? json_encode($attributes['changed_fields']) : null,
                'source' => $attributes['source'] ?? 'WEB_API',
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->header('User-Agent'), 0, 255),
                'session_id' => session()->getId() ?: null,
                'correlation_id' => $attributes['correlation_id'] ?? (string) Str::uuid(),
                'request_id' => request()->header('X-Request-Id'),
                'reason' => $attributes['reason'] ?? null,
                'created_at' => now(),
            ], fn ($value) => $value !== null));
        } catch (\Throwable $e) {
            // Audit never breaks the main flow
        }
    }
}
