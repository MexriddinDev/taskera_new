<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\RequesterPrefill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SlaRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'team_id' => $this->team_id,
            'team' => $this->whenLoaded('team', fn () => [
                'id' => $this->team->id,
                'name' => $this->team->name,
                'code' => $this->team->code,
            ]),
            'priority_id' => $this->priority_id,
            'priority' => $this->whenLoaded('priority', fn () => $this->priority ? [
                'id' => $this->priority->id,
                'name' => $this->priority->name,
                'code' => $this->priority->code,
                'color' => $this->priority->color,
            ] : null),
            'name' => $this->name,
            // Izoh zayavka matniga qo'yiladi, shuning uchun `prefill=1` bilan
            // so'ralganda bo'sh qatorlari to'ldiriladi. Sozlamalar sahifasi
            // parametrsiz so'raydi va xom matnni oladi.
            'description' => $request->boolean('prefill')
                ? RequesterPrefill::apply($this->description, $request->user())
                : $this->description,
            'accept_minutes' => $this->accept_minutes,
            'work_minutes' => $this->work_minutes,
            'is_active' => $this->is_active,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
