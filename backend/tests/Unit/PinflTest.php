<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Pinfl;
use PHPUnit\Framework\TestCase;

/**
 * PINFL dan tug'ilgan sanani ajratish.
 *
 * Bazaga bog'liq emas — sof mantiq, shuning uchun Unit to'plamida.
 */
final class PinflTest extends TestCase
{
    public function test_it_reads_the_birth_date_from_each_century_marker(): void
    {
        // 1-xona: 3,4 -> 19xx
        $this->assertSame('1989-03-25', Pinfl::birthDate('32503890123456'));
        $this->assertSame('1989-03-25', Pinfl::birthDate('42503890123456'));

        // 5,6 -> 20xx
        $this->assertSame('2005-12-01', Pinfl::birthDate('50112050123456'));

        // 1,2 -> 18xx
        $this->assertSame('1899-01-31', Pinfl::birthDate('13101990123456'));
    }

    public function test_it_returns_null_for_an_impossible_date(): void
    {
        // 31-fevral mavjud emas.
        $this->assertNull(Pinfl::birthDate('33102890123456'));
        // 13-oy.
        $this->assertNull(Pinfl::birthDate('30113890123456'));
    }

    public function test_it_returns_null_for_a_malformed_number(): void
    {
        $this->assertNull(Pinfl::birthDate(''));
        $this->assertNull(Pinfl::birthDate('123'));
        // 14 xona, lekin raqam emas.
        $this->assertNull(Pinfl::birthDate('3250389012345a'));
        // Tanilmagan asr belgisi (0, 7, 8, 9).
        $this->assertNull(Pinfl::birthDate('72503890123456'));
        $this->assertNull(Pinfl::birthDate('02503890123456'));
    }
}
