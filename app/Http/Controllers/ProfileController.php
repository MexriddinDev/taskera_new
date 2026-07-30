<?php

namespace App\Http\Controllers;

use App\Modules\Organization\Infrastructure\Eloquent\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $employee = null;
        if ($user->employee_id) {
            $employee = Employee::with(['department', 'branch', 'position'])->find($user->employee_id);
        }

        $role = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $user->id)
            ->select('roles.name')
            ->first();

        return view('profile', compact('user', 'employee', 'role'));
    }
}
