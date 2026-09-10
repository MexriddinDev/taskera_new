<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

final class PermitResource extends JsonResource
{
    /** Havola chelagi — TicketResource bilan bir xil, brauzer keshi buzilmasligi uchun. */
    private const URL_BUCKET_SECONDS = 600;

    /**
     * Rasm uchun imzolangan havola. Chelakka yaxlitlab ustiga to'liq oyna
     * qo'shiladi — havola har doim kamida 30 daqiqa amal qiladi.
     */
    private static function photoUrl(int $permitId): string
    {
        $bucket = (int) ceil(time() / self::URL_BUCKET_SECONDS) * self::URL_BUCKET_SECONDS;

        return URL::temporarySignedRoute(
            'permits.photo',
            Carbon::createFromTimestamp($bucket + 1800),
            ['id' => $permitId],
            absolute: false
        );
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            // Ro'yxat, qidiruv va o'chirish tasdig'i to'liq nomni ishlatadi —
            // uni har joyda qayta yig'masdan shu yerda bir marta beramiz.
            'full_name' => $this->resource->fullName(),
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'visit_purpose' => $this->visit_purpose,
            'visit_at' => $this->visit_at?->toIso8601String(),
            // Rasm imzolangan vaqtinchalik havola orqali beriladi — TicketResource
            // biriktirmalarda ishlatadigan naqshning aynan o'zi.
            'photo_url' => $this->photo_path ? self::photoUrl((int) $this->id) : null,
            'host_department' => $this->host_department,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
