<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Botdan yozilgan rad etish izohlarini saytdagi ko'rinishga keltiradi.
 *
 * BotConversationService yechimni rad etganda izohni `kind` siz va inglizcha
 * "Solution rejected: " prefiksi bilan yozardi. Frontend esa dublikatni aynan
 * `kind = rejection` bo'yicha bosadi (TaskDetailPage), shuning uchun sabab
 * zayavka kartochkasida IKKI marta chiqardi: bir marta oddiy izoh sifatida,
 * yana bir marta `tickets.rejection_reason` ustunidan.
 *
 * Bot kodi tuzatildi, lekin eski yozuvlar bazada qolgan — shu yerda
 * to'g'rilanadi: prefiks olib tashlanadi, `metadata.kind` qo'yiladi.
 */
return new class extends Migration
{
    private const PREFIX = 'Solution rejected: ';

    public function up(): void
    {
        DB::table('comments')
            ->where('body', 'like', self::PREFIX.'%')
            ->orderBy('id')
            ->chunkById(200, function ($comments) {
                foreach ($comments as $comment) {
                    // Izohda allaqachon boshqa `kind` bo'lsa tegilmaydi.
                    $metadata = json_decode((string) $comment->metadata, true);
                    $metadata = is_array($metadata) ? $metadata : [];
                    if (! empty($metadata['kind'])) {
                        continue;
                    }

                    $metadata['kind'] = 'rejection';

                    DB::table('comments')->where('id', $comment->id)->update([
                        'body' => substr($comment->body, strlen(self::PREFIX)),
                        'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Orqaga qaytarilmaydi: eski ko'rinish (inglizcha prefiks + `kind` siz)
        // xatoning o'zi edi, uni tiklashning ma'nosi yo'q.
    }
};
