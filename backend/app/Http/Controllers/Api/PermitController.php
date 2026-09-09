<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermitResource;
use App\Models\Itms\Permit;
use App\Support\CurrentOrg;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%")
                        ->orWhere('visitor_organization', 'like', "%{$search}%")
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

        $permit = Permit::create($validated + [
            'organization_id' => CurrentOrg::id($request),
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json(['data' => new PermitResource($permit)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $permit = Permit::where('organization_id', CurrentOrg::id($request))->findOrFail($id);
        $permit->update($this->validated($request, true) + ['updated_by' => $request->user()->id]);

        return response()->json(['data' => new PermitResource($permit->fresh())]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $permit = Permit::where('organization_id', CurrentOrg::id($request))->findOrFail($id);
        $permit->delete();

        return response()->json(['message' => 'Ruxsatnoma o‘chirildi.']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        return $request->validate([
            'full_name' => [$required, 'string', 'max:255'],
            'document_type' => [$required, Rule::in(Permit::DOCUMENT_TYPES)],
            'visit_purpose' => [$required, 'string', 'max:2000'],
            // Qolganlari ixtiyoriy: qorovul postida asosiy uchtasi yetadi,
            // qolgani ma'lum bo'lsa to'ldiriladi.
            'document_number' => ['nullable', 'string', 'max:64'],
            'visit_at' => ['nullable', 'date'],
            'visitor_organization' => ['nullable', 'string', 'max:255'],
            'host_department' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
