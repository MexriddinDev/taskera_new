<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Itms\FinesseAccount;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use App\Services\FinesseService;
use App\Modules\Ticketing\Domain\Services\StoreAttachmentService;
use App\Support\CurrentOrg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Cisco Finesse hisobi — har foydalanuvchi o'z login/parolini saqlaydi,
 * holati esa Finesse API'dan jonli o'qiladi.
 */
final class FinesseController extends Controller
{
    /** `attachment_types` dagi AUDIO turi. */
    private const AUDIO_ATTACHMENT_TYPE_ID = 3;

    public function __construct(private readonly FinesseService $finesse) {}

    /** Saqlangan hisob va uning JONLI holati. */
    public function show(Request $request): JsonResponse
    {
        $account = $this->accountFor($request);

        if (! $account) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->withLiveState($account)]);
    }

    /**
     * Login/parolni saqlaydi. Saqlashdan OLDIN Finesse'da tekshiriladi —
     * noto'g'ri parol saqlanib qolsa, foydalanuvchi buni faqat qo'ng'iroq
     * paytida bilardi.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login_id' => 'required|string|max:128',
            'password' => 'required|string|max:255',
        ]);

        $login = trim($validated['login_id']);
        $check = $this->finesse->user($login, $validated['password']);

        if (! $check['ok']) {
            return response()->json(['message' => $check['message'] ?? 'Finesse tekshiruvi muvaffaqiyatsiz.'], 422);
        }

        $account = FinesseAccount::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'organization_id' => CurrentOrg::id($request),
                'login_id' => $login,
                'password_encrypted' => Crypt::encryptString($validated['password']),
                'extension' => $check['extension'] ?? null,
                'last_state' => $check['state'] ?? null,
                'last_checked_at' => now(),
            ]
        );

        return response()->json(['data' => $this->present($account, $check)], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        FinesseAccount::where('user_id', $request->user()->id)->delete();

        return response()->json(['message' => 'Cisco hisobi o‘chirildi.']);
    }

    /** Holatni qayta so'raydi — sahifa vaqti-vaqti bilan shu manzilga murojaat qiladi. */
    public function status(Request $request): JsonResponse
    {
        $account = $this->accountFor($request);

        if (! $account) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->withLiveState($account)]);
    }

    /**
     * Qo'ng'iroq hali davom etyaptimi — frontend shu bilan o'z holatini
     * tiklaydi. Go'shak Jabber'da qo'yilganda sayt boshqa yo'l bilan buni
     * bilmaydi va mikrofon yozishda qolib ketardi.
     *
     * `active: null` — Finesse javob bermadi; frontend yozuvni to'xtatmaydi.
     */
    public function callActive(Request $request): JsonResponse
    {
        $account = $this->accountFor($request);

        if (! $account) {
            return response()->json(['data' => null]);
        }

        $result = $this->finesse->hasActiveCall($account->login_id, $account->password());

        return response()->json(['data' => [
            'active' => $result['active'],
            'message' => $result['message'] ?? null,
        ]]);
    }

    /**
     * Zayavka murojaatchisiga qo'ng'iroq. Raqam so'rovdan EMAS, zayavkadan
     * olinadi — client yuborgan raqamga ishonib bo'lmaydi.
     */
    public function call(Request $request, int $ticketId): JsonResponse
    {
        $account = $this->accountFor($request);

        if (! $account) {
            return response()->json(['message' => 'Avval "Cisco Call" bo‘limida login va parolni saqlang.'], 422);
        }

        $ticket = Ticket::whereNull('deleted_at')
            ->where('organization_id', CurrentOrg::id($request))
            ->find($ticketId);

        if (! $ticket) {
            return response()->json(['message' => 'Zayavka topilmadi'], 404);
        }

        // Raqam avval murojaatchi PROFILIDAN olinadi — zayavkadagi nusxa
        // eskirgan bo'lishi mumkin (TicketResource ham shu tartibda ko'rsatadi,
        // ya'ni ekranda ko'ringan raqam aynan teriladi).
        $ticket->loadMissing('requesterEmployee');
        $profilePhone = (string) ($ticket->requesterEmployee?->phone ?? '');
        $number = $this->dialNumber($profilePhone !== '' ? $profilePhone : (string) $ticket->initiator_phone);

        if ($number === null) {
            return response()->json(['message' => 'Zayavkada murojaatchi telefon raqami yo‘q.'], 422);
        }

        // Extension Finesse javobidan keladi; keshdagi qiymat eskirgan bo'lishi
        // mumkin, shuning uchun qo'ng'iroqdan oldin yangilanadi.
        $live = $this->finesse->user($account->login_id, $account->password());

        if (! $live['ok']) {
            return response()->json(['message' => $live['message'] ?? 'Finesse bilan bog‘lanib bo‘lmadi.'], 422);
        }

        $extension = $live['extension'] ?? '';
        $state = $live['state'] ?? '';

        // Finesse'dan chiqib ketilgan bo'lsa MAKE_CALL "Invalid State" bilan
        // rad etiladi va sabab tushunarsiz bo'ladi. DIQQAT: `/User` javobi
        // chiqib ketilganda ham sozlangan extension'ni qaytaradi, shuning
        // uchun holatni ALOHIDA tekshirish shart — extension bo'sh emasligi
        // agent tizimda turibdi degani emas.
        if ($extension === '' || $state === '' || $state === 'LOGOUT') {
            return response()->json([
                'message' => 'Finesse\'ga operator sifatida kirmagansiz'
                    .($state !== '' ? ' (holat: '.$state.')' : '')
                    .'. Cisco Finesse\'da login qiling va READY holatiga o\'ting.',
            ], 422);
        }

        $account->update([
            'extension' => $extension,
            'last_state' => $live['state'] ?? null,
            'last_checked_at' => now(),
        ]);

        $result = $this->finesse->makeCall($account->login_id, $account->password(), $extension, $number);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message'] ?? 'Qo‘ng‘iroq amalga oshmadi.'], 422);
        }

        return response()->json([
            'message' => 'Qo‘ng‘iroq boshlandi.',
            'data' => ['from' => $extension, 'to' => $number],
        ]);
    }

    /**
     * Suhbat yozuvini zayavkaga biriktiradi.
     *
     * Yozuv `metadata.kind = call_recording` bilan belgilanadi — TicketResource
     * shu belgiga qarab uni MUROJAATCHIDAN yashiradi. Umumiy
     * `/attachments/upload` ishlatilmadi: u belgini qo'ya olmaydi va unga
     * "ichki fayl" imkoniyatini qo'shish har qanday chaqiruvchiga
     * biriktirmani yashirish yo'lini ochib bergan bo'lardi.
     */
    public function storeRecording(Request $request, int $ticketId, StoreAttachmentService $service): JsonResponse
    {
        $user = $request->user();

        // Yozuvni faqat zayavka ustida ishlaydigan xodim yuklaydi — bu ayni
        // uni ko'ra oladigan doira (TicketResource dagi filtr bilan bir xil).
        if (! $user->canTakeTickets()) {
            return response()->json(['message' => 'Sizda suhbat yozuvini biriktirish huquqi yo‘q'], 403);
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:webm,ogg,mp3,wav|max:61440',
        ]);

        $ticket = Ticket::whereNull('deleted_at')
            ->where('organization_id', CurrentOrg::id($request))
            ->find($ticketId);

        if (! $ticket) {
            return response()->json(['message' => 'Zayavka topilmadi'], 404);
        }

        $file = $validated['file'];
        $safeName = Str::uuid().'.'.($file->getClientOriginalExtension() ?: 'webm');
        $dir = 'attachments/'.date('Y/m/d');

        // Attachment'lar bilan bir xil tartib: avval 'public', bo'lmasa 'local'.
        $disk = 'public';
        try {
            Storage::disk($disk)->putFileAs($dir, $file, $safeName);
        } catch (\Throwable $e) {
            $disk = 'local';
            Storage::disk($disk)->putFileAs($dir, $file, $safeName);
        }

        $attachment = $service->execute([
            'organization_id' => CurrentOrg::id($request),
            'attachable_type' => Ticket::class,
            'attachable_id' => $ticket->id,
            'attachment_type_id' => self::AUDIO_ATTACHMENT_TYPE_ID,
            'uploaded_by' => $user->id,
            'source_id' => 1,
            'storage_disk' => $disk,
            'storage_path' => $dir.'/'.$safeName,
            'original_name' => 'Suhbat yozuvi '.now()->format('d-m-Y H:i').'.webm',
            'safe_name' => $safeName,
            // Tur qat'iy: webm konteyneri mazmun bo'yicha `video/webm` deb
            // aniqlanadi, TicketResource esa turni MIME bo'yicha ajratadi —
            // ishonmasak yozuv zayavkada video bo'lib chiqardi.
            'mime_type' => 'audio/webm',
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
        ]);

        // Belgini servisdan keyin qo'yamiz: StoreAttachmentService umumiy va
        // uni shu bitta chaqiruvchi uchun o'zgartirmaymiz.
        $attachment->metadata = json_encode(['kind' => 'call_recording'], JSON_UNESCAPED_UNICODE);
        $attachment->save();

        return response()->json(['message' => 'Suhbat yozuvi biriktirildi.'], 201);
    }

    /** Faol qo'ng'iroqni tugatish. */
    public function drop(Request $request): JsonResponse
    {
        $account = $this->accountFor($request);

        if (! $account) {
            return response()->json(['message' => 'Avval "Cisco Call" bo‘limida login va parolni saqlang.'], 422);
        }

        $result = $this->finesse->dropActiveCall($account->login_id, $account->password());

        if (! $result['ok']) {
            return response()->json(
                ['message' => $result['message'] ?? 'Qo‘ng‘iroqni tugatib bo‘lmadi.'],
                $result['status'] === 404 ? 404 : 422
            );
        }

        return response()->json(['message' => 'Qo‘ng‘iroq tugatildi.']);
    }

    private function accountFor(Request $request): ?FinesseAccount
    {
        return FinesseAccount::where('user_id', $request->user()->id)->first();
    }

    /** @return array<string, mixed> */
    private function withLiveState(FinesseAccount $account): array
    {
        $live = $this->finesse->user($account->login_id, $account->password());

        if ($live['ok']) {
            $account->update([
                'extension' => $live['extension'] ?? $account->extension,
                'last_state' => $live['state'] ?? null,
                'last_checked_at' => now(),
            ]);
        }

        return $this->present($account, $live);
    }

    /**
     * @param  array<string, mixed>  $live
     * @return array<string, mixed>
     */
    private function present(FinesseAccount $account, array $live): array
    {
        return [
            'login_id' => $account->login_id,
            'extension' => $account->extension,
            // Aloqa yo'q bo'lsa oxirgi ma'lum holat ko'rsatiladi, `online` esa false.
            'state' => $live['ok'] ? ($live['state'] ?? null) : $account->last_state,
            'online' => (bool) ($live['ok'] ?? false),
            'ready' => ($live['ok'] ?? false) && ($live['state'] ?? null) === FinesseService::STATE_READY,
            'full_name' => trim(($live['firstName'] ?? '').' '.($live['lastName'] ?? '')) ?: null,
            'team_name' => $live['teamName'] ?? null,
            'error' => $live['ok'] ? null : ($live['message'] ?? null),
            'checked_at' => $account->last_checked_at?->toIso8601String(),
        ];
    }

    /**
     * Raqamni UCCX dial-plan kutadigan ko'rinishga keltiradi.
     *
     * Format tajribada aniqlangan: UCCX'ga yuborilgan va HTTP 202 qaytargan
     * haqiqiy chaqiruvlarda mobil raqam MILLIY ko'rinishda bo'lgan
     * (`0952404142`), ichki raqam esa o'z holicha (`10500`). Bazada esa
     * raqamlar xalqaro ko'rinishda saqlanadi (`998952404142`), shuning uchun
     * `998` prefiksi `0` ga almashtiriladi.
     */
    private function dialNumber(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        // 998XXXXXXXXX (12 xona) -> 0XXXXXXXXX. Ichki raqamlar (4-5 xona)
        // va allaqachon milliy ko'rinishdagilar o'zgarishsiz qoladi.
        if (strlen($digits) === 12 && str_starts_with($digits, '998')) {
            $digits = '0'.substr($digits, 3);
        }

        return (string) config('services.finesse.dial_prefix', '').$digits;
    }
}
