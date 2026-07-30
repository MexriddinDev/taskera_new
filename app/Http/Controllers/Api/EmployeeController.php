<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Modules\Organization\Infrastructure\Eloquent\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 15), 100);

        $employees = Employee::query()
            ->with(['department', 'branch', 'position', 'manager', 'employmentStatus'])
            ->when($request->filled('organization_id'), fn($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->filled('department_id'), fn($q) => $q->where('department_id', $request->department_id))
            ->when($request->filled('branch_id'), fn($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->filled('position_id'), fn($q) => $q->where('position_id', $request->position_id))
            ->when($request->filled('search'), fn($q) => $q->where(function ($q) use ($request) {
                $search = '%' . $request->search . '%';
                $q->where('first_name', 'like', $search)
                  ->orWhere('last_name', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('employee_no', 'like', $search);
            }))
            ->when($request->filled('employment_status_id'), fn($q) => $q->where('employment_status_id', $request->employment_status_id))
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'data' => EmployeeResource::collection($employees),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
            ],
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_no' => 'required|string|max:64',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'department_id' => 'required|integer|exists:departments,id',
            'branch_id' => 'required|integer|exists:branches,id',
            'position_id' => 'required|integer|exists:positions,id',
            'manager_id' => 'nullable|integer|exists:employees,id',
            'employment_status_id' => 'required|integer|exists:employment_statuses,id',
            'hired_at' => 'nullable|date',
        ]);

        $employee = Employee::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'employee_no' => $validated['employee_no'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'department_id' => $validated['department_id'],
            'branch_id' => $validated['branch_id'],
            'position_id' => $validated['position_id'],
            'manager_id' => $validated['manager_id'] ?? null,
            'employment_status_id' => $validated['employment_status_id'],
            'hired_at' => $validated['hired_at'] ?? null,
        ]);

        return response()->json([
            'data' => new EmployeeResource($employee->load(['department', 'branch', 'position', 'manager', 'employmentStatus'])),
        ], 201)->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function show(Request $request, $id): JsonResponse
    {
        $employee = Employee::with(['department', 'branch', 'position', 'manager', 'employmentStatus'])->findOrFail($id);

        return response()->json([
            'data' => new EmployeeResource($employee),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'employee_no' => 'sometimes|required|string|max:64',
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:32',
            'department_id' => 'sometimes|required|integer|exists:departments,id',
            'branch_id' => 'sometimes|required|integer|exists:branches,id',
            'position_id' => 'sometimes|required|integer|exists:positions,id',
            'manager_id' => 'nullable|integer|exists:employees,id',
            'employment_status_id' => 'sometimes|required|integer|exists:employment_statuses,id',
            'hired_at' => 'nullable|date',
        ]);

        $employee->update($validated);

        return response()->json([
            'data' => new EmployeeResource($employee->load(['department', 'branch', 'position', 'manager', 'employmentStatus'])),
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        return response()->json([
            'message' => 'Deleted',
        ])->header('X-Organization-Id', $request->header('X-Organization-Id', '1'));
    }
}
