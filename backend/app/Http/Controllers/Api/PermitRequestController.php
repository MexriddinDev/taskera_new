<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Itms\PermitRequest;
use App\Modules\Audit\Domain\Services\AuditLogger;
use App\Support\CurrentOrg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Elektron ruxsatnoma so'rovlari.
 *
 * Ikki tomoni bor:
 *   - xodim o'z so'rovini yuboradi va o'zinikini ko'radi (`mine`, `store`);
 *   - Ichki xavfsizlik barcha so'rovlarni ko'radi va qaror qabul qiladi
 *     (`index`, `decide`) — bunga `security.manage` huquqi kerak.
 */
final class PermitRequestController extends Controller
{
    /** Ichki xavfsizlik uchun: barcha so'rovlar, holat bo'yicha filtr bilan. */
    public function index(Request $request): JsonResponse
    {
        $status = strtoupper(trim((string) $request->query('status', '')));
        $statuses = [PermitRequest::STATUS_PENDING, PermitRequest::STATUS_APPROVED, PermitRequest::STATUS_REJECTED];

        // Noto'g'ri filtr jimgina e'tiborsiz qolsa, foydalanuvchi filtr
        // ishlayapti deb o'ylab BUTUN ro'yxatni ko'rardi.
        if ($status !== '' && ! in_array($status, $statuses, true)) {
            return response()->json([
                'message' => "Holat faqat quyidagilardan biri bo'lishi mumkin: ".implode(', ', $statuses),
            ], 422);
        }

        $search = trim((string) $request->query('search', ''));

        $rows = PermitRequest::query()
            ->where('organization_id', CurrentOrg::id($request))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            // F.I.Sh yoki guvohnoma raqami bo'yicha qidiruv.
            ->when($search !== '', function ($q) use ($search) {
                $needle = '%'.$search.'%';
                // PostgreSQL da `LIKE` registrga sezgir: `toshmatov` yozilsa
                // `Toshmatov` topilmasdi. Sinovlar sqlite da ishlagani uchun
                // bu farq testda ko'rinmaydi.
                $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $q->where(function ($inner) use ($needle, $operator) {
                    $inner->where('last_name', $operator, $needle)
                        ->orWhere('first_name', $operator, $needle)
                        ->orWhere('document_number', $operator, $needle);
                });
            })
            // Qaror kutayotganlar tepada, ular ichida ENG ESKISI birinchi:
            // navbat shu tartibda ishlanadi va 200 ta chegarasi eng uzoq
            // kutganlarni ro'yxatdan tushirib qoldirmaydi.
            ->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'PENDING' THEN id END ASC")
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => $this->presentMany($rows)]);
    }

    /** Xodim uchun: faqat o'z so'rovlari. */
    public function mine(Request $request): JsonResponse
    {
        $rows = PermitRequest::query()
            ->where('organization_id', CurrentOrg::id($request))
            ->where('requester_user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $this->presentMany($rows)]);
    }

    public function store(Request $request): JsonResponse
    {
        // Maydonlar eski tashrifchilar qaydi shakli bilan bir xil: qorovul
        // postiga kerakli ma'lumot to'liq yig'iladi.
        $validated = $request->validate([
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'document_type' => ['required', Rule::in(PermitRequest::DOCUMENT_TYPES)],
            // Raqam shakli tanlangan hujjat turiga bog'liq — qoida modelda.
            'document_number' => [
                'nullable',
                'string',
                'max:64',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    $type = (string) $request->input('document_type');
                    $pattern = PermitRequest::DOCUMENT_PATTERNS[$type] ?? null;

                    if ($pattern !== null && preg_match($pattern, (string) $value) !== 1) {
                        $fail("Guvohnoma raqami noto'g'ri. Namuna: AA1234567 (2 ta katta harf va 7 ta raqam).");
                    }
                },
            ],
            'visitor_organization' => ['nullable', 'string', 'max:255'],
            'visit_purpose' => ['required', 'string', 'max:2000'],
            'visit_at' => ['nullable', 'date'],
            'host_department' => ['nullable', 'string', 'max:255'],
            // 'image' qoidasi kengaytmadan tashqari faylning haqiqiy mazmunini
            // ham tekshiradi.
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        // Forma maydonlari `$fillable` orqali, qolgani — aniq yoziladi:
        // tegishlilik, holat va rasm so'rovchining ixtiyorida emas.
        $permitRequest = new PermitRequest($validated);
        $permitRequest->organization_id = CurrentOrg::id($request);
        $permitRequest->requester_user_id = $request->user()->id;
        $permitRequest->status = PermitRequest::STATUS_PENDING;
        $permitRequest->photo_path = $this->storePhoto($request);
        $permitRequest->save();

        return response()->json(['data' => $this->present($permitRequest)], 201);
    }

    /** Ichki xavfsizlik qarori: tasdiqlash yoki rad etish. */
    public function decide(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:APPROVED,REJECTED'],
            'decision_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        // Rad etishda sabab majburiy — saytdagi zayavka rad etish qoidasi bilan
        // bir xil: qaror izohsiz qolmasin.
        if ($validated['status'] === PermitRequest::STATUS_REJECTED && trim((string) ($validated['decision_reason'] ?? '')) === '') {
            return response()->json(['message' => 'Rad etish sababini yozing.'], 422);
        }

        // Qulf tranzaksiya ichida: ikki xodim bir vaqtda qaror bosganda
        // ikkinchisi birinchisining qarorini ustiga yozib yubormasin.
        return DB::transaction(function () use ($request, $id, $validated) {
            $permitRequest = $this->findForUpdate($request, $id);

            if (! $permitRequest) {
                return response()->json(['message' => "So'rov topilmadi."], 404);
            }

            // Qaror bir marta qabul qilinadi — qayta yozilsa tarix chalkashardi.
            if ($permitRequest->status !== PermitRequest::STATUS_PENDING) {
                return response()->json([
                    'message' => "Bu so'rov bo'yicha qaror allaqachon qabul qilingan.",
                ], 422);
            }

            $permitRequest->status = $validated['status'];
            $permitRequest->decision_reason = $validated['decision_reason'] ?? null;
            $permitRequest->decided_by = $request->user()->id;
            $permitRequest->decided_at = now();
            $permitRequest->save();

            $this->audit($request, $permitRequest, 'PERMIT_REQUEST_DECIDED', sprintf(
                'Ruxsatnoma so\'rovi #%d — %s: %s',
                $permitRequest->id,
                $validated['status'],
                $permitRequest->fullName(),
            ));

            return response()->json(['data' => $this->present($permitRequest)]);
        });
    }

    /**
     * Bitta so'rov — alohida sahifa uchun.
     *
     * Ro'yxatdagi qatordan nusxa olish yetarli emas: sahifa o'z manziliga ega
     * va to'g'ridan-to'g'ri ochilganda (yoki yangilanganda) ro'yxat bo'lmaydi.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $permitRequest = $this->findForOrg($request, $id);

        if (! $permitRequest) {
            return response()->json(['message' => "So'rov topilmadi."], 404);
        }

        return response()->json(['data' => $this->present($permitRequest)]);
    }

    /**
     * Tashrifchi kirdi.
     *
     * Faqat RUXSAT BERILGAN so'rov uchun: tasdiqlanmagan mehmonni ichkariga
     * kiritib bo'lmaydi. Ikkinchi marta bosilsa ham vaqt qayta yozilmaydi.
     */
    public function enter(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $permitRequest = $this->findForUpdate($request, $id);

            if (! $permitRequest) {
                return response()->json(['message' => "So'rov topilmadi."], 404);
            }

            if ($permitRequest->status !== PermitRequest::STATUS_APPROVED) {
                return response()->json(['message' => "Faqat ruxsat berilgan so'rov bo'yicha kirish qayd etiladi."], 422);
            }

            if ($permitRequest->entered_at !== null) {
                return response()->json(['message' => 'Kirish allaqachon qayd etilgan.'], 422);
            }

            $permitRequest->entered_at = now();
            $permitRequest->entered_by = $request->user()->id;
            $permitRequest->save();

            $this->audit($request, $permitRequest, 'PERMIT_REQUEST_ENTERED', sprintf(
                'Tashrifchi kirdi: %s (so\'rov #%d)',
                $permitRequest->fullName(),
                $permitRequest->id,
            ));

            return response()->json(['data' => $this->present($permitRequest)]);
        });
    }

    /** Tashrifchi chiqdi — kirish qayd etilgandan keyingina. */
    public function exit(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $permitRequest = $this->findForUpdate($request, $id);

            if (! $permitRequest) {
                return response()->json(['message' => "So'rov topilmadi."], 404);
            }

            if ($permitRequest->entered_at === null) {
                return response()->json(['message' => 'Avval kirish qayd etilishi kerak.'], 422);
            }

            if ($permitRequest->exited_at !== null) {
                return response()->json(['message' => 'Chiqish allaqachon qayd etilgan.'], 422);
            }

            $permitRequest->exited_at = now();
            $permitRequest->exited_by = $request->user()->id;
            $permitRequest->save();

            $this->audit($request, $permitRequest, 'PERMIT_REQUEST_EXITED', sprintf(
                'Tashrifchi chiqdi: %s (so\'rov #%d)',
                $permitRequest->fullName(),
                $permitRequest->id,
            ));

            return response()->json(['data' => $this->present($permitRequest)]);
        });
    }

    /**
     * Qatorni tranzaksiya ichida QULFLAB o'qiydi.
     *
     * Holat tekshiruvi bilan yozuv orasida boshqa so'rov oraliqqa kirib
     * qolmasligi uchun — loyihadagi `AssignTicketService` bilan bir xil naqsh.
     */
    private function findForUpdate(Request $request, int $id): ?PermitRequest
    {
        return PermitRequest::query()
            ->where('organization_id', CurrentOrg::id($request))
            ->lockForUpdate()
            ->find($id);
    }

    /** Qaror va kirish/chiqish — binoga kirish bilan bog'liq, jurnalga tushadi. */
    private function audit(Request $request, PermitRequest $r, string $action, string $description): void
    {
        AuditLogger::log($request, $action, $description, [
            'organization_id' => (int) $r->organization_id,
            'auditable_type' => PermitRequest::class,
            'auditable_id' => $r->id,
            'auditable_public_id' => $r->public_id,
        ]);
    }

    /**
     * So'rov yuborgan xodimlarning kartochkalari: F.I.Sh, departament, lavozim.
     *
     * Qorovul postida "kim chaqirgan" ko'rinib turishi kerak. Ma'lumot
     * so'rovda saqlanmaydi — har safar xodim kartochkasidan o'qiladi, chunki
     * lavozim va bo'lim vaqt o'tib o'zgaradi.
     *
     * BITTA so'rov bilan hammasi olinadi. Ilgari bu metod har bir qator uchun
     * alohida chaqirilardi: 200 ta so'rovli navbat 400+ SQL tug'dirardi.
     * `Model::preventLazyLoading()` qo'riqchisi buni ushlamaydi, chunki bu
     * Eloquent munosabati emas, balki to'g'ridan-to'g'ri `DB::table()`.
     *
     * @param  iterable<int|string|null>  $userIds
     * @return array<int, array{username: ?string, name: ?string, department: ?string, position: ?string}>
     */
    private function userCards(iterable $userIds): array
    {
        $ids = collect($userIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('users as u')
            ->leftJoin('employees as e', 'e.id', '=', 'u.employee_id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('positions as p', 'p.id', '=', 'e.position_id')
            ->whereIn('u.id', $ids)
            ->select('u.id', 'u.username', 'e.first_name', 'e.last_name', 'e.middle_name', 'd.name as department', 'p.name as position')
            ->get()
            ->keyBy('id')
            ->map(function ($row) {
                $fio = trim(implode(' ', array_filter([$row->last_name, $row->first_name, $row->middle_name])));

                return [
                    'username' => $row->username,
                    'name' => $fio !== '' ? $fio : $row->username,
                    'department' => $row->department,
                    'position' => $row->position,
                ];
            })
            ->all();
    }

    /**
     * Ro'yxatni tayyorlaydi: xodim kartochkalari bir marta yig'iladi.
     *
     * @param  Collection<int, PermitRequest>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function presentMany($rows): array
    {
        $cards = $this->userCards(
            $rows->pluck('requester_user_id')->merge($rows->pluck('decided_by'))
        );

        return $rows->map(fn (PermitRequest $r) => $this->present($r, $cards))->values()->all();
    }

    /**
     * Tashrifchi rasmini saqlaydi va yo'lini qaytaradi; rasm kelmasa — null.
     *
     * Tartib boshqa bo'limlar bilan bir xil: avval 'public' diski, u ishlamasa
     * 'local'.
     */
    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        $file = $request->file('photo');
        $safeName = Str::uuid().'.'.($file->getClientOriginalExtension() ?: 'jpg');
        $dir = 'permit-requests/'.date('Y/m');

        // DIQQAT: `putFileAs` muvaffaqiyatsizlikda odatda xato TASHLAMAYDI,
        // `false` qaytaradi. Ilgari faqat `catch` bor edi, ya'ni yozilmagan
        // fayl uchun ham yo'l saqlanardi va `has_photo` yolg'on gapirardi.
        try {
            $stored = Storage::disk('public')->putFileAs($dir, $file, $safeName);
        } catch (\Throwable) {
            $stored = false;
        }

        if ($stored === false) {
            try {
                $stored = Storage::disk('local')->putFileAs($dir, $file, $safeName);
            } catch (\Throwable) {
                $stored = false;
            }
        }

        return $stored === false ? null : $dir.'/'.$safeName;
    }

    /**
     * @param  array<int, array{username: ?string, name: ?string, department: ?string, position: ?string}>|null  $cards
     *                                                                                                                   Ro'yxat uchun oldindan yig'ilgan kartochkalar; bitta yozuv uchun null.
     * @return array<string, mixed>
     */
    private function present(PermitRequest $r, ?array $cards = null): array
    {
        $cards ??= $this->userCards([$r->requester_user_id, $r->decided_by]);
        $requester = $cards[(int) $r->requester_user_id] ?? null;
        $decider = $r->decided_by !== null ? ($cards[(int) $r->decided_by] ?? null) : null;

        return [
            'id' => $r->id,
            'last_name' => $r->last_name,
            'first_name' => $r->first_name,
            'middle_name' => $r->middle_name,
            'full_name' => $r->fullName(),
            'document_type' => $r->document_type,
            'visitor_organization' => $r->visitor_organization,
            'document_number' => $r->document_number,
            'host_department' => $r->host_department,
            'has_photo' => $r->photo_path !== null,
            'visit_purpose' => $r->visit_purpose,
            'visit_at' => $r->visit_at?->toIso8601String(),
            'status' => $r->status,
            'decision_reason' => $r->decision_reason,
            'requester' => $requester['username'] ?? null,
            'decided_by' => $decider['username'] ?? null,
            'decided_at' => $r->decided_at?->toIso8601String(),
            'requester_card' => [
                'name' => $requester['name'] ?? null,
                'department' => $requester['department'] ?? null,
                'position' => $requester['position'] ?? null,
            ],
            'entered_at' => $r->entered_at?->toIso8601String(),
            'exited_at' => $r->exited_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }
}
