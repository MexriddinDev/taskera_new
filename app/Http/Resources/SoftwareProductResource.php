<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SoftwareProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'publisher' => $this->publisher,
            'name' => $this->name,
            'version_pattern' => $this->version_pattern,
            'is_licensed' => $this->is_licensed,
        ];
    }
}
