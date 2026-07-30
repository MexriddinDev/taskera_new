<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required_without:email|string',
            'email' => 'required_without:username|email',
            'password' => 'required|string',
        ]);

        $username = $request->input('username') ?? (string) Str::before($request->input('email', ''), '@');
        $email = $request->input('email') ?? ($username . '@company.uz');

        $user = User::where('username', $username)->orWhere('email', $email)->first();

        if (!$user) {
            // Vaqtinchalik login rejimi (AD ulanmagunicha):
            // Istalgan login/parol bilan kirgan yangi foydalanuvchi uchun
            // avtomatik user yaratamiz. Employee (xodim kartochkasi) YARATILMAYDI —
            // u AD sinxronizatsiyasi orqali keladi. Shu sabab employee_id = null.
            // Rol biriktirilmagan bo'lgani uchun bu foydalanuvchi faqat zayavka
            // yuborishi va o'z zayavkalarini ko'rishi mumkin (UserResource->isStaff = false).
            $user = User::create([
                'public_id' => (string) Str::uuid(),
                'organization_id' => 1,
                'employee_id' => null,
                'username' => $username,
                'email' => $email,
                'password' => \Illuminate\Support\Facades\Hash::make($request->input('password')),
                'auth_source' => 'LOCAL',
                'status' => 'ACTIVE',
            ]);
        } else {
            // Mavjud foydalanuvchi — parolni tekshiramiz.
            if (! \Illuminate\Support\Facades\Hash::check($request->input('password'), (string) $user->password)) {
                return response()->json([
                    'message' => 'Login yoki parol noto\'g\'ri',
                ], 422);
            }
        }

        $token = $user->createToken('web-sites')->plainTextToken;
        $user->load('employee.department');

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('employee.department');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Tizimdan chiqildi',
        ]);
    }
}
