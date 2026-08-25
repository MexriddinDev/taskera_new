<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

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
            // Vaqtinchalik imzolangan havola (30 daqiqa) — auth'siz <img>/<video>
            // uchun ishlaydi, lekin sanab bo'lmas (IDOR himoyasi).
            'url' => $this->storage_path
                ? URL::temporarySignedRoute('attachments.download', now()->addMinutes(30), ['id' => $this->id])
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
