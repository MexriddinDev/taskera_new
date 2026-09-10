<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Itms\FinesseAccount;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use App\Services\FinesseService;
use App\Support\CurrentOrg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Cisco Finesse hisobi — har foydalanuvchi o'z login/parolini saqlaydi,
 * holati esa Finesse API'dan jonli o'qiladi.
 */
final class FinesseController extends Controller
{
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

        $number = $this->dialNumber((string) $ticket->initiator_phone);

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
        if ($extension === '') {
            return response()->json(['message' => 'Finesse hisobingizda extension yo‘q — operator sifatida login qiling.'], 422);
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

    /** Raqamlardan boshqasi olib tashlanadi; dial-plan prefiksi configdan. */
    private function dialNumber(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        return (string) config('services.finesse.dial_prefix', '').$digits;
    }
}
