<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    /** Ruxsat route middleware'ida (auth:sanctum) tekshiriladi. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Frontend zayavkani multipart/form-data bilan yuboradi — u yerda BARCHA
     * qiymat string bo'lib keladi, 'integer' qoidasi esa faqat tekshiradi,
     * cast QILMAYDI. strict_types yoqilgani uchun "3" kabi qiymat int tipli
     * metodga uzatilganda TypeError beradi — shuning uchun id'lar
     * validatsiyadan oldin shu yerda int'ga o'giriladi.
     */
    protected function prepareForValidation(): void
    {
        $casted = [];

        foreach (['teamId', 'slaRuleId', 'assigned_team_id'] as $key) {
            $value = $this->input($key);

            if (is_numeric($value)) {
                $casted[$key] = (int) $value;
            }
        }

        $this->merge($casted);
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'todo' => 'required|string|min:3|max:2000',
            'category' => 'nullable|string|max:255',
            'targetDepartment' => 'nullable|in:hardware,software',
            'teamId' => 'nullable|integer|exists:teams,id',
            // Zayavka yaratishda tanlangan shablon (SLA qoidasi). Berilmasa —
            // "default holat": muddat guruhning umumiy qoidasidan olinadi.
            'slaRuleId' => 'nullable|integer|exists:sla_rules,id',
            'assigned_team_id' => 'nullable|integer|exists:teams,id',
            'originDepartment' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:128',
            'initiatorName' => 'nullable|string|max:255',
            'initiatorPhone' => 'nullable|string|max:32',
            'deviceName' => 'nullable|string|max:255',
            'brokenUrl' => 'nullable|url|max:2048',
            'status' => 'nullable|in:todo,in_progress,done,rejected',
            // Chegara — 60 MB (61440 KB) va u FAQAT foydalanuvchi tanlagan
            // faylga tegishli. Ovoz/skrinshot avtomatik olinadi va bu chegaraga
            // qo'shilmaydi — ular o'z chegaralari bilan quyida tekshiriladi.
            'file' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,bmp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z|max:61440',
            'screenshot' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,bmp|max:51200',
            'audio' => 'nullable|file|mimes:mp3,ogg,wav,webm|max:51200',
            'video' => 'nullable|file|mimes:mp4,webm,mov|max:51200',
        ];
    }
}
