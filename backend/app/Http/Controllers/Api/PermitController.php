<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermitResource;
use App\Models\Itms\Permit;
use App\Support\CurrentOrg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Elektron ruxsatnoma qaydlari — CRUD.
 *
 * Kirish `permits.manage` huquqi bilan cheklangan (marshrutda), ya'ni
 * amalda admin va superadmin.
 */
final class PermitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', '20'), 1), 100);

        $permits = Permit::query()
            ->where('organization_id', CurrentOrg::id($request))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->query('search'));
                $query->where(function ($query) use ($search) {
                    $query->where('last_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('middle_name', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%")
                        ->orWhere('visit_purpose', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('document_type'), fn ($query) => $query->where('document_type', $request->query('document_type')))
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => PermitResource::collection($permits),
            'meta' => [
                'current_page' => $permits->currentPage(),
                'last_page' => $permits->lastPage(),
                'per_page' => $permits->perPage(),
                'total' => $permits->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        unset($validated['photo']);

        $permit = Permit::create($validated + [
            'organization_id' => CurrentOrg::id($request),
            'photo_path' => $this->storePhoto($request),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['data' => new PermitResource($permit)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $permit = Permit::where('organization_id', CurrentOrg::id($request))->findOrFail($id);

        $validated = $this->validated($request, true);
        unset($validated['photo']);

        // Yangi rasm kelgandagina almashtiriladi — aks holda eskisi joyida qoladi.
        if ($newPath = $this->storePhoto($request)) {
            $this->deletePhoto($permit->photo_path);
            $validated['photo_path'] = $newPath;
        }

        $permit->update($validated + ['updated_by' => $request->user()->id]);

        return response()->json(['data' => new PermitResource($permit->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $permit = Permit::where('organization_id', CurrentOrg::id($request))->findOrFail($id);
        $this->deletePhoto($permit->photo_path);
        $permit->delete();

        return response()->json(['message' => 'Ruxsatnoma o‘chirildi.']);
    }

    /**
     * Tashrifchi rasmini beradi. `attachments.download` bilan bir xil naqsh:
     * marshrut imzolangan havola bilan himoyalangan (routes/api.php), shuning
     * uchun bu yerda qo'shimcha organizatsiya filtri kerak emas — imzo aynan
     * shu id'ni avtorizatsiya qiladi.
     */
    public function photo(int $id)
    {
        $permit = Permit::findOrFail($id);

        if (! $permit->photo_path) {
            abort(404, 'Ruxsatnomada rasm yo‘q.');
        }

        foreach (['public', 'local'] as $disk) {
            if (Storage::disk($disk)->exists($permit->photo_path)) {
                return response()->file(Storage::disk($disk)->path($permit->photo_path));
            }
        }

        abort(404, 'Rasm fayli topilmadi.');
    }

    /** Yuklangan rasmni saqlaydi va yo'lini qaytaradi; rasm kelmasa — null. */
    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        $file = $request->file('photo');
        $safeName = Str::uuid().'.'.($file->getClientOriginalExtension() ?: 'jpg');
        $dir = 'permits/'.date('Y/m');

        // Attachment'lar bilan bir xil tartib: avval 'public', bo'lmasa 'local'.
        try {
            Storage::disk('public')->putFileAs($dir, $file, $safeName);
        } catch (\Throwable $e) {
            Storage::disk('local')->putFileAs($dir, $file, $safeName);
        }

        return $dir.'/'.$safeName;
    }

    private function deletePhoto(?string $path): void
    {
        if (! $path) {
            return;
        }

        foreach (['public', 'local'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'last_name' => [$required, 'string', 'max:100'],
            'first_name' => [$required, 'string', 'max:100'],
            'document_type' => [$required, Rule::in(Permit::DOCUMENT_TYPES)],
            'visit_purpose' => [$required, 'string', 'max:2000'],
            // Qolganlari ixtiyoriy: qorovul postida asosiylari yetadi,
            // qolgani ma'lum bo'lsa to'ldiriladi.
            'middle_name' => ['nullable', 'string', 'max:100'],
            'document_number' => ['nullable', 'string', 'max:64'],
            'visit_at' => ['nullable', 'date'],
            // Faqat rasm: jpg/png. 'image' qoidasi kengaytmadan tashqari
            // faylning haqiqiy mazmunini ham tekshiradi.
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'host_department' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
