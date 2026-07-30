<?php

namespace App\Http\Controllers;

use App\Modules\Organization\Infrastructure\Eloquent\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $departments = Department::with('branch')
            ->orderBy('id')
            ->get();

        if ($request->expectsJson()) {
            return response()->json(['data' => $departments]);
        }

        return view('organization.departments', compact('departments'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:32',
            'name' => 'required|string|max:255',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'parent_id' => 'nullable|integer|exists:departments,id',
            'manager_employee_id' => 'nullable|integer|exists:employees,id',
            'cost_center' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
        ]);

        $code = !empty($validated['code'])
            ? strtoupper($validated['code'])
            : strtoupper(\Illuminate\Support\Str::slug($validated['name'], '_'));

        $department = Department::create([
            'organization_id' => $request->header('X-Organization-Id', 1),
            'code' => substr($code, 0, 32),
            'name' => $validated['name'],
            'branch_id' => $validated['branch_id'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'manager_employee_id' => $validated['manager_employee_id'] ?? null,
            'cost_center' => $validated['cost_center'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $department->load('branch')], 201);
        }

        return redirect()->back()->with('success', 'Department created successfully');
    }

    public function update(Request $request, $id): JsonResponse|RedirectResponse
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'code' => 'nullable|string|max:32',
            'name' => 'sometimes|string|max:255',
            'branch_id' => 'nullable|integer|exists:branches,id',
            'parent_id' => 'nullable|integer|exists:departments,id',
            'manager_employee_id' => 'nullable|integer|exists:employees,id',
            'cost_center' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
        ]);

        $department->update($validated);

        if ($request->expectsJson()) {
            return response()->json(['data' => $department->load('branch')]);
        }

        return redirect()->back()->with('success', 'Department updated successfully');
    }

    public function destroy(Request $request, $id): JsonResponse|RedirectResponse
    {
        $department = Department::findOrFail($id);
        $department->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Deleted']);
        }

        return redirect()->back()->with('success', 'Department deleted successfully');
    }
}
