<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bazada saqlangan profil rasmlarini bir xil o'lchamga keltiradi.
 *
 * Sabab: rasm ilgari qanday yuklangan bo'lsa, shundayligicha (masalan
 * 3000x4000) base64 holida saqlanardi. Brauzer bunday rasmni 24-32px doiraga
 * bir bosqichda sig'dirganda natija xira chiqadi. Yangi yuklashlar frontendda
 * allaqachon 512x512 ga keltiriladi; bu buyruq esa eskilarini bir marta
 * tuzatish uchun.
 *
 * Ishlatilishi:
 *   php artisan avatars:normalize --dry-run   — nima o'zgarishini ko'rish
 *   php artisan avatars:normalize             — haqiqiy o'zgartirish
 */
class NormalizeAvatars extends Command
{
    protected $signature = 'avatars:normalize
                            {--dry-run : Hech narsa saqlanmaydi, faqat hisobot chiqadi}
                            {--size=512 : Natijaviy tomon (px)}';

    protected $description = "Foydalanuvchilarning profil rasmlarini kvadrat va bir xil o'lchamga keltiradi";

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->error('GD kengaytmasi yoqilmagan — rasmlarni qayta ishlab bo\'lmaydi.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $size = max(64, (int) $this->option('size'));

        $users = DB::table('users')
            ->whereNotNull('image')
            ->where('image', 'like', 'data:image/%')
            ->select(['id', 'username', 'image'])
            ->get();

        if ($users->isEmpty()) {
            $this->info('Qayta ishlanadigan rasm topilmadi.');

            return self::SUCCESS;
        }

        $changed = 0;
        $skipped = 0;
        $savedBytes = 0;

        foreach ($users as $user) {
            $original = (string) $user->image;
            $resized = $this->resizeDataUrl($original, $size);

            if ($resized === null) {
                $this->warn("#{$user->id} {$user->username}: rasm o'qilmadi, o'tkazib yuborildi.");
                $skipped++;
                continue;
            }

            // Natija kattaroq bo'lib qolsa — tegmaymiz (rasm allaqachon kichik).
            if (strlen($resized) >= strlen($original)) {
                $skipped++;
                continue;
            }

            $delta = strlen($original) - strlen($resized);
            $savedBytes += $delta;
            $changed++;

            $this->line(sprintf(
                '#%d %s: %s → %s',
                $user->id,
                $user->username,
                $this->humanBytes(strlen($original)),
                $this->humanBytes(strlen($resized))
            ));

            if (! $dryRun) {
                DB::table('users')->where('id', $user->id)->update(['image' => $resized]);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s%d ta rasm yangilandi, %d ta o\'tkazib yuborildi, %s tejaldi.',
            $dryRun ? '[DRY RUN] ' : '',
            $changed,
            $skipped,
            $this->humanBytes($savedBytes)
        ));

        return self::SUCCESS;
    }

    /**
     * base64 data URL ni markazdan kvadrat kesib, berilgan tomonga keltiradi.
     * Rasm allaqachon kichik bo'lsa kattalashtirilmaydi.
     */
    private function resizeDataUrl(string $dataUrl, int $size): ?string
    {
        $commaAt = strpos($dataUrl, ',');
        if ($commaAt === false) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, $commaAt + 1), true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        if ($side < 1) {
            imagedestroy($source);

            return null;
        }

        $target = min($side, $size);
        $canvas = imagecreatetruecolor($target, $target);

        // Shaffof PNG ni JPEG ga o'tkazganda fon qora bo'lib qolmasin.
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $target, $target, $white);

        imagecopyresampled(
            $canvas,
            $source,
            0, 0,
            (int) (($width - $side) / 2),
            (int) (($height - $side) / 2),
            $target, $target,
            $side, $side
        );

        ob_start();
        imagejpeg($canvas, null, 92);
        $encoded = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        if ($encoded === '') {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($encoded);
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 1).' MB';
    }
}
