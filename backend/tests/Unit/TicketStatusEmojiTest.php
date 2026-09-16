<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Telegram\Support\TicketStatusEmoji;
use PHPUnit\Framework\TestCase;

/**
 * Botdagi holat belgilari.
 *
 * Bazaga bog'liq emas — sof moslik jadvali, shuning uchun Unit to'plamida.
 */
final class TicketStatusEmojiTest extends TestCase
{
    /** Har bosqichning o'z belgisi: kulrang / sariq / yashil / ko'k / qizil. */
    public function test_each_stage_has_its_own_icon(): void
    {
        // Ochiq: Yangi, Ochiq, Biriktirilgan — kulrang doira.
        foreach ([1, 2, 3] as $statusId) {
            $this->assertSame('⚪', TicketStatusEmoji::for($statusId));
        }

        // Jarayonda va kutilmoqda — qum soati.
        foreach ([4, 5, 6] as $statusId) {
            $this->assertSame('⏳', TicketStatusEmoji::for($statusId));
        }

        // Bajarildi (baholanmagan) — ptichka. Aynan shu so'ralgan edi.
        $this->assertSame('✅', TicketStatusEmoji::for(7));
        $this->assertSame('✅', TicketStatusEmoji::for(8));

        // Baholandi — ko'k romb.
        $this->assertSame('🔷', TicketStatusEmoji::for(7, 5));
        $this->assertSame('🔷', TicketStatusEmoji::for(8, 1));

        // Rad etildi — krestik.
        $this->assertSame('❌', TicketStatusEmoji::for(9));
    }

    /** Pushti, binafsha va eski rangli kvadratlar qolmasligi kerak. */
    public function test_no_purple_pink_or_plain_square_is_left(): void
    {
        foreach (range(1, 10) as $statusId) {
            foreach ([null, 0, 5] as $rating) {
                $this->assertNotContains(
                    TicketStatusEmoji::for($statusId, $rating),
                    ['🟪', '🟣', '🩷', '💜', '⬜', '⬛', '🟨', '🟩', '🟥', '🟦'],
                    "status_id={$statusId}: eski rang yoki kvadrat qolgan.",
                );
            }
        }
    }

    /** Baho faqat bajarilgan zayavkaning belgisini almashtiradi. */
    public function test_a_rating_only_changes_a_finished_ticket(): void
    {
        $this->assertSame('⚪', TicketStatusEmoji::for(1, 5));
        $this->assertSame('⏳', TicketStatusEmoji::for(4, 5));
        // Rad etilgan zayavka baho bilan ham krestik bo'lib qoladi.
        $this->assertSame('❌', TicketStatusEmoji::for(9, 5));
        // Bahosiz (0 yoki null) — ptichka.
        $this->assertSame('✅', TicketStatusEmoji::for(7, 0));
        $this->assertSame('✅', TicketStatusEmoji::for(7, null));
    }

    /** Noma'lum holat dasturni yiqitmaydi. */
    public function test_an_unknown_status_falls_back(): void
    {
        $this->assertSame('▪️', TicketStatusEmoji::for(99));
        $this->assertSame('▪️', TicketStatusEmoji::for(null));
    }
}
