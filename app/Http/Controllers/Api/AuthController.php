<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Modules\Audit\Domain\Services\AuditLogger;
use App\Services\AdAuthService;
use App\Services\AdUserProvisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Foydalanuvchi login qiladi.
     *
     * Login logikasi auth_source ga asoslangan (bitta forma — hammaga bir xil):
     *
     *   1. DB dan username bo'yicha foydalanuvchini izlaymiz.
     *   2. Agar topilsa va auth_source = 'LOCAL' bo'lsa  → DB hash bilan solishtirish
     *      (Faqat bootstrap superadmin bunday bo'ladi)
     *   3. Boshqa barcha holatlarda (auth_source='AD' yoki DB da yo'q) → AD orqali
     *   4. AD muvaffaqiyatli bo'lsa → foydalanuvchi avtomatik yaratiladi/yangilanadi
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|max:128',
            'password' => 'required|string',
        ]);

        $username = strtolower(trim((string) $request->input('username')));
        $password = (string) $request->input('password');

        // ── 1. DB dan foydalanuvchini izlash ─────────────────────────────────
        $user = User::where('username', $username)->first();

        // ── 2. auth_source ga qarab autentifikatsiya yo'li ───────────────────
        if ($user && $user->auth_source === 'LOCAL') {
            // LOCAL: DB dagi hashed parol bilan solishtirish
            // (Faqat bootstrap superadmin — auth_source='LOCAL' bo'ladi)
            if (! Hash::check($password, (string) $user->password)) {
                return response()->json(
                    ['message' => 'Login yoki parol noto\'g\'ri'],
                    422
                );
            }
            // $user tayyor — quyidagi token yaratish qismiga o'tamiz

        } else {
            // AD: Foydalanuvchi DB da bo'lsa (auth_source='AD') yoki bo'lmasa —
            // ikkalasida ham AD orqali autentifikatsiya qilinadi.

            try {
                /** @var AdAuthService $adService */
                $adService    = app(AdAuthService::class);
                $adAttributes = $adService->authenticate($username, $password);
            } catch (\RuntimeException $e) {
                Log::error('AD login xatosi', [
                    'username' => $username,
                    'error'    => $e->getMessage(),
                ]);
                return response()->json([
                    'message' => 'AD server bilan bog\'lanib bo\'lmadi. IT administratoriga murojaat qiling.',
                ], 503);
            }

            // Noto'g'ri login yoki parol
            if (! $adAttributes) {
                return response()->json(
                    ['message' => 'Login yoki parol noto\'g\'ri'],
                    422
                );
            }

            // AD da akkaunt bloklangan
            if (! $adAttributes['enabled']) {
                return response()->json([
                    'message' => 'Akkauntingiz bloklangan. IT bo\'limiga murojaat qiling.',
                ], 403);
            }

            // Employee + User yaratish yoki yangilash
            /** @var AdUserProvisionService $provision */
            $provision = app(AdUserProvisionService::class);
            $user      = $provision->findOrProvision($adAttributes);
        }

        // ── 3. Sanctum token yaratish ─────────────────────────────────────────
        $token = $user->createToken('web-sites')->plainTextToken;
        $user->load('employee.department');

        // ── 4. Audit log ──────────────────────────────────────────────────────
        AuditLogger::log($request, 'USER_LOGIN', "Foydalanuvchi tizimga kirdi: {$user->username}", [
            'actor_user_id'      => $user->id,
            'actor_employee_id'  => $user->employee_id,
            'auditable_type'     => 'App\Models\User',
            'auditable_id'       => $user->id,
            'auditable_public_id'=> $user->public_id,
        ]);

        return response()->json([
            'token' => $token,
            'user'  => (new UserResource($user))->resolve(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('employee.department');

        return response()->json([
            'user' => (new UserResource($user))->resolve(),
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|string',
        ]);

        $user = $request->user() ?? auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Tizimga kiring'], 401);
        }

        $user->image = $request->input('image');
        $user->save();

        return response()->json([
            'user'    => new UserResource($user->load('employee.department')),
            'message' => 'Profil rasmi yangilandi',
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
