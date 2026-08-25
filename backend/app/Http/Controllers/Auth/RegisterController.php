<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:128|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Create Employee record for new user
        $empId = DB::table('employees')->insertGetId([
            'public_id' => Str::uuid(),
            'organization_id' => 1,
            'employee_no' => 'EMP-' . rand(1000, 9999),
            'first_name' => $validated['username'],
            'last_name' => 'Xodim',
            'email' => $validated['email'],
            'department_id' => 1,
            'branch_id' => 1,
            'position_id' => 1,
            'employment_status_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create User record
        $user = User::create([
            'public_id' => Str::uuid(),
            'organization_id' => 1,
            'employee_id' => $empId,
            'username' => $validated['username'],
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'auth_source' => 'LOCAL',
            'status' => 'ACTIVE',
            'locale_id' => 1,
            'timezone_id' => 1,
        ]);

        // Assign standard user role (or no privileged admin role)
        $standardRole = DB::table('roles')->where('name', 'Standard User')->first();
        if ($standardRole) {
            DB::table('model_has_roles')->insertOrIgnore([
                'role_id' => $standardRole->id,
                'model_type' => 'App\\Models\\User',
                'model_id' => $user->id,
                'organization_id' => 1,
            ]);
        }

        Auth::login($user);

        // Standard user / new user gets minimum access (Employee Service Portal)
        return redirect('/portal')->with('success', 'Muvaffaqiyatli ro\'yxatdan o\'tdingiz!');
    }
}
