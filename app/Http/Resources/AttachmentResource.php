<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'original_name' => $this->original_name,
            'size_bytes' => $this->size_bytes,
            'mime_type' => $this->mime_type,
            'url' => $this->storage_path ? url('api/v1/attachments/' . $this->id . '/download') : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
