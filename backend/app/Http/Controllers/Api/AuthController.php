<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Modules\Audit\Domain\Services\AuditLogger;
use App\Services\AdAuthService;
use App\Services\AdUserProvisionService;
use App\Services\ExchangeMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Auth', description: 'Authentication Endpoints')]
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
    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'User Login',
        description: 'Login with AD or Local credentials and receive Sanctum Bearer Token',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'superadmin'),
                    new OA\Property(property: 'password', type: 'string', example: 'Admin@2024!'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful login',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: '1|xyz...'),
                        new OA\Property(property: 'user', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error or Invalid credentials'),
            new OA\Response(response: 403, description: 'Account disabled in AD'),
            new OA\Response(response: 503, description: 'AD Server unavailable'),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string|max:128',
            'password' => 'required|string',
        ]);

        $username = strtolower(trim((string) $request->input('username')));
        // UPN ko'rinishida yozilsa ("yusuf.rahimboyev@xb.uz") — domain qismi olib
        // tashlanadi, chunki AD da sAMAccountName domensiz saqlanadi.
        $username = preg_replace('/@.*$/', '', $username);
        $password = (string) $request->input('password');

        // ── 1. DB dan foydalanuvchini izlash ─────────────────────────────────
        $user = User::where('username', $username)->first();

        // ── 2. auth_source ga qarab autentifikatsiya yo'li ───────────────────
        if ($user && $user->auth_source === 'LOCAL') {
            // LOCAL: DB dagi hashed parol bilan solishtirish
            // (Faqat bootstrap superadmin — auth_source='LOCAL' bo'ladi)
            if (! Hash::check($password, (string) $user->password)) {
                return response()->json(
                    ['message' => "Parol noto'g'ri"],
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

            // Noto'g'ri login yoki parol (standart: 401 Unauthorized)
            if (! $adAttributes) {
                // Parol xatomi yoki bunday hisob umuman yo'qmi — ajratamiz.
                // Yangi xodim hali pochta (AD) ochmagan bo'lsa, "parol
                // noto'g'ri" deyish chalg'ituvchi: u nima qilishni bilmaydi.
                $existsInAd = false;
                try {
                    $existsInAd = app(AdAuthService::class)->lookupByUsername($username) !== null;
                } catch (\Throwable $lookupError) {
                    // AD qidiruvi ishlamasa umumiy xabarga qaytamiz —
                    // login jarayonining o'zi buzilmasligi kerak.
                    Log::warning('AD qidiruvi muvaffaqiyatsiz', [
                        'username' => $username,
                        'error' => $lookupError->getMessage(),
                    ]);
                }

                if (! $existsInAd && ! $user) {
                    return response()->json([
                        'message' => "Bunday foydalanuvchi topilmadi. Pochta (AD) hisobingiz bo'lmasa, uni shu yerdan yarating.",
                        'user_not_found' => true,
                    ], 422);
                }

                return response()->json(
                    ['message' => "Parol noto'g'ri"],
                    401
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

    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Get current user profile',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'User profile details'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
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

        // Xodimlar avatarlari monitoring javobida 60 soniya keshlanadi.
        // Profil rasmi yangilanganda Kanban filtri eski rasmni qaytarmasligi uchun
        // barcha monitoring keshlari foydalanadigan versiyani oshiramiz.
        Cache::add('monitoring.version', 1, now()->addYears(10));
        Cache::increment('monitoring.version');

        return response()->json([
            'user'    => new UserResource($user->load('employee.department')),
            'message' => 'Profil rasmi yangilandi',
        ]);
    }

    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Logout user and revoke token',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Tizimdan chiqildi',
        ]);
    }

    /**
     * Foydalanuvchi o'z parolini QO'LDA o'rnatadi (profil sahifasidagi forma).
     *
     * Parol ikki joyda birdek o'zgaradi: saytdagi hash va AD (Exchange) dagi
     * domen paroli. Ilgari AD paroli faqat SMS tasdiqli "reset" oqimidan
     * tasodifiy qiymat bilan almashardi — xodim o'zi tanlagan parolni qo'ya
     * olmasdi.
     *
     * Tartib muhim: avval AD, keyin sayt. AD xato bersa saytdagi parol
     * o'zgarmaydi va ikkalasi bir-biriga mos qolaveradi.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'old_password' => 'required|string',
            // AD parol siyosati: kamida 8 belgi, katta va kichik harf hamda raqam.
            'password' => [
                'required', 'string', 'min:8', 'confirmed',
                'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/',
            ],
        ], [], [
            'old_password' => 'Eski parol',
            'password' => 'Yangi parol',
        ]);

        $user = $request->user() ?? auth()->user();
        if (! $user) {
            return response()->json(['message' => 'Tizimga kiring'], 401);
        }

        $oldPassword = (string) $request->input('old_password');
        $newPassword = (string) $request->input('password');

        if ($user->password && ! Hash::check($oldPassword, (string) $user->password)) {
            $validInAd = false;
            if ($user->auth_source === 'AD') {
                try {
                    $adAuth = app(AdAuthService::class)->authenticate($user->username, $oldPassword);
                    if ($adAuth) {
                        $validInAd = true;
                    }
                } catch (\Throwable $e) {
                    Log::warning('AD change-password verify failed: '.$e->getMessage());
                }
            }

            if (! $validInAd) {
                return response()->json([
                    'message' => "Eski parol noto'g'ri",
                    'errors' => [
                        'old_password' => ["Eski parol noto'g'ri kiritildi"],
                    ],
                ], 422);
            }
        }

        // ── AD (Exchange) dagi domen paroli ──────────────────────────────────
        $pinfl = $this->pinflFor($user);
        $adUpdated = false;

        if ($pinfl !== null) {
            try {
                app(ExchangeMailService::class)->resetPassword($pinfl, $newPassword);
                $adUpdated = true;
            } catch (\Throwable $e) {
                Log::error('[PROFILE] AD parolini almashtirishda xatolik', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Parolni AD (pochta) da almashtirib bo\'lmadi, shu sabab sayt paroli ham o\'zgartirilmadi. Parol AD talablariga javob berishi kerak yoki IT administratoriga murojaat qiling.',
                ], 502);
            }

            // Login sahifasi ko'rsatadigan nusxa ham yangilanadi.
            DB::table('ad_accounts')
                ->where('pinfl', $pinfl)
                ->update([
                    'password_encrypted' => Crypt::encryptString($newPassword),
                    'updated_at' => now(),
                ]);
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        AuditLogger::log($request, 'USER_PASSWORD_CHANGED', "Foydalanuvchi paroli o'zgartirildi: {$user->username}".($adUpdated ? ' (sayt + AD)' : ' (sayt)'), [
            'actor_user_id' => $user->id,
            'actor_employee_id' => $user->employee_id,
            'auditable_type' => 'App\Models\User',
            'auditable_id' => $user->id,
            'auditable_public_id' => $user->public_id,
        ]);

        return response()->json([
            'message' => $adUpdated
                ? "Parol saytda va pochtada (AD) muvaffaqiyatli o'zgartirildi"
                : "Parol muvaffaqiyatli o'zgartirildi",
            'ad_updated' => $adUpdated,
        ]);
    }

    /**
     * Foydalanuvchining PINFL (JShShIR) raqami — AD dagi akkaunt aynan shu
     * bo'yicha topiladi. Topilmasa AD ga umuman murojaat qilinmaydi.
     */
    private function pinflFor(User $user): ?string
    {
        $employee = $user->employee_id
            ? DB::table('employees')->where('id', $user->employee_id)->first(['attributes'])
            : null;

        $attributes = is_string($employee?->attributes)
            ? json_decode($employee->attributes, true)
            : (array) ($employee->attributes ?? []);

        $pinfl = is_array($attributes) ? ($attributes['pinfl'] ?? null) : null;

        if (! $pinfl) {
            $pinfl = DB::table('ad_accounts')
                ->where('username', $user->username)
                ->orderByDesc('id')
                ->value('pinfl');
        }

        $pinfl = preg_replace('/[^0-9]/', '', (string) $pinfl);

        return strlen((string) $pinfl) === 14 ? $pinfl : null;
    }
}
