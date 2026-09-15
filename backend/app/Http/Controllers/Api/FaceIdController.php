<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Itms\FaceIdRecord;
use App\Services\EmployeeCheckService;
use App\Support\CurrentOrg;
use App\Support\Pinfl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ichki xavfsizlik → FaceID.
 *
 * Oqim: PINFL kiritiladi -> `lookup` ism/familiyani HR API'dan, tug'ilgan
 * sanani PINFL raqamining o'zidan oladi -> foydalanuvchi rasm va PDF
 * biriktiradi -> `store` yozuvni saqlaydi.
 */
final class FaceIdController extends Controller
{
    /** Saqlangan yozuvlar ro'yxati. */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $records = FaceIdRecord::query()
            ->where('organization_id', CurrentOrg::id($request))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('pinfl', 'like', $search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('first_name', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => $records->map(fn (FaceIdRecord $r) => $this->present($r))]);
    }

    /**
     * PINFL bo'yicha ma'lumot: ism/familiya HR API'dan, sana raqamdan.
     *
     * Xodim topilmasa ham 404 QAYTARILMAYDI: tug'ilgan sana baribir PINFL dan
     * hisoblanadi va foydalanuvchi ism/familiyani qo'lda yozib davom eta
     * oladi — bu yerda maqsad to'sish emas, to'ldirishga yordam berish.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pinfl' => ['required', 'string', 'size:14', 'regex:/^[0-9]{14}$/'],
        ]);

        $pinfl = (string) $validated['pinfl'];
        $employee = app(EmployeeCheckService::class)->findByPinfl($pinfl);

        return response()->json(['data' => [
            'pinfl' => $pinfl,
            'birth_date' => Pinfl::birthDate($pinfl),
            'first_name' => $employee['first_name'] ?? null,
            'last_name' => $employee['last_name'] ?? null,
            'middle_name' => $employee['middle_name'] ?? null,
            'found' => $employee !== null,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pinfl' => ['required', 'string', 'size:14', 'regex:/^[0-9]{14}$/'],
            'last_name' => ['required', 'string', 'max:128'],
            'first_name' => ['required', 'string', 'max:128'],
            'middle_name' => ['nullable', 'string', 'max:128'],
            'birth_date' => ['nullable', 'date'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'document' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $record = FaceIdRecord::create([
            'organization_id' => CurrentOrg::id($request),
            'pinfl' => $validated['pinfl'],
            'last_name' => $validated['last_name'],
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            // Sana yuborilmasa PINFL dan hisoblanadi — forma chetlab o'tilsa ham
            // yozuv to'liq bo'lib qoladi.
            'birth_date' => $validated['birth_date'] ?? Pinfl::birthDate($validated['pinfl']),
            'photo_path' => $this->storeUpload($request, 'photo', 'jpg'),
            'document_path' => $this->storeUpload($request, 'document', 'pdf'),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json(['data' => $this->present($record)], 201);
    }

    /** @return array<string, mixed> */
    private function present(FaceIdRecord $record): array
    {
        return [
            'id' => $record->id,
            'pinfl' => $record->pinfl,
            'last_name' => $record->last_name,
            'first_name' => $record->first_name,
            'middle_name' => $record->middle_name,
            'full_name' => $record->fullName(),
            'birth_date' => $record->birth_date?->format('Y-m-d'),
            'has_photo' => $record->photo_path !== null,
            'has_document' => $record->document_path !== null,
            'created_at' => $record->created_at?->toIso8601String(),
        ];
    }

    /**
     * Yuklangan faylni saqlaydi va yo'lini qaytaradi; fayl kelmasa — null.
     *
     * Ruxsatnomalar bo'limi bilan bir xil tartib: avval 'public' diski,
     * u ishlamasa 'local'.
     */
    private function storeUpload(Request $request, string $field, string $fallbackExtension): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        $safeName = Str::uuid().'.'.($file->getClientOriginalExtension() ?: $fallbackExtension);
        $dir = 'face-id/'.date('Y/m');

        try {
            Storage::disk('public')->putFileAs($dir, $file, $safeName);
        } catch (\Throwable) {
            Storage::disk('local')->putFileAs($dir, $file, $safeName);
        }

        return $dir.'/'.$safeName;
    }
}
