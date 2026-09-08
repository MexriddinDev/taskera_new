<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request, $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Foydalanuvchi topilmadi'], 404);
        }

        // IDOR himoyasi: profilni faqat o'zi yoki staff ko'radi
        $viewer = $request->user() ?? auth()->user();
        if (! $viewer) {
            return response()->json(['message' => 'Tizimga kiring'], 401);
        }
        $isSelf = (int) $viewer->id === (int) $id;
        $isStaff = $viewer->isSupportStaff();
        if (! $isSelf && ! $isStaff) {
            return response()->json(['message' => 'Sizda bu profilni ko\'rish huquqi yo\'q'], 403);
        }

        $user = User::with(['employee.department', 'employee.position'])->find((int) $id);

        if (!$user) {
            return response()->json(['message' => 'Foydalanuvchi topilmadi'], 404);
        }

        // Bo'lim AD dagi guruh a'zoligi bo'yicha jonli aniqlanadi
        // (masalan "xGitlab" guruhi → "Gitlab" bo'limi).
        try {
            $adData = app(\App\Services\AdAuthService::class)->lookupByUsername($user->username);
            if ($adData) {
                $adDepartment = app(\App\Services\AdUserProvisionService::class)
                    ->resolveDepartmentNameFromAdGroups($adData['member_of'] ?? []);
                if ($adDepartment) {
                    $user->ad_department = $adDepartment;
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Profile AD lookup muvaffaqiyatsiz: '.$e->getMessage());
        }

        return response()->json(new UserResource($user));
    }

    /**
     * Foydalanuvchining yuborgan zayavkalari bo'yicha qisqa statistika
     * va so'nggi 5 ta zayavkasi (profil sahifasi uchun).
     */
    public function summary(Request $request, $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Foydalanuvchi topilmadi'], 404);
        }

        $user = User::find((int) $id);

        if (!$user) {
            return response()->json(['message' => 'Foydalanuvchi topilmadi'], 404);
        }

        $base = Ticket::query()
            ->where('requester_user_id', $user->id)
            ->whereNull('deleted_at');

        $total = (clone $base)->count();
        $open = (clone $base)->whereIn('status_id', [1, 2, 3, 4, 5, 6])->count();
        $done = (clone $base)->whereIn('status_id', [7, 8])->count();
        $rejected = (clone $base)->whereIn('status_id', [9, 10])->count();
        $rated = (clone $base)->whereNotNull('client_rating')->count();
        $unrated = (clone $base)->whereIn('status_id', [7, 8])->whereNull('client_rating')->count();

        $recent = (clone $base)
            ->orderByDesc('created_at')
            ->limit(3)
            ->get()
            ->map(fn (Ticket $t) => [
                'id' => $t->id,
                'ticketNo' => $t->ticket_no,
                'subject' => $t->subject ?? $t->description,
                'status' => TicketResource::mapStatusFromId((int) $t->status_id),
                'clientRating' => $t->client_rating,
                'createdAt' => TicketResource::formatDate($t->created_at),
            ]);

        return response()->json([
            'total' => $total,
            'open' => $open,
            'done' => $done,
            'rejected' => $rejected,
            'rated' => $rated,
            'unrated' => $unrated,
            'recent' => $recent,
        ]);
    }

    /**
     * Foydalanuvchining shaxsiy ma'lumotlarini yangilash (telefon, telegram, manzil, tug'ilgan sana, bio).
     */
    public function update(Request $request): JsonResponse
    {
        $viewer = $request->user() ?? auth()->user();
        if (! $viewer) {
            return response()->json(['message' => 'Tizimga kiring'], 401);
        }

        $validated = $request->validate([
            'phone' => 'nullable|string|max:32',
            'telegram_username' => 'nullable|string|max:64',
            'address' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date|after_or_equal:1900-01-01|before_or_equal:today',
            'bio' => 'nullable|string|max:1000',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'image' => 'nullable|string',
        ]);

        $user = User::with(['employee.department', 'employee.position'])->find((int) $viewer->id);
        if (! $user) {
            return response()->json(['message' => 'Foydalanuvchi topilmadi'], 404);
        }

        if (! empty($validated['image'])) {
            $user->image = $validated['image'];
        }

        $employee = $user->employee;
        if ($employee) {
            if (isset($validated['phone'])) {
                $employee->phone = $validated['phone'];
            }
            if (! empty($validated['first_name'])) {
                $employee->first_name = $validated['first_name'];
            }
            if (isset($validated['last_name'])) {
                $employee->last_name = $validated['last_name'];
            }
            if (isset($validated['middle_name'])) {
                $employee->middle_name = $validated['middle_name'];
            }

            $attrs = is_array($employee->attributes)
                ? $employee->attributes
                : (is_string($employee->attributes) ? json_decode($employee->attributes, true) ?? [] : []);

            if (isset($validated['telegram_username'])) {
                $attrs['telegram_username'] = $validated['telegram_username'];
            }
            if (isset($validated['address'])) {
                $attrs['address'] = $validated['address'];
            }
            if ($request->has('birth_date')) {
                $attrs['birth_date'] = ! empty($validated['birth_date'])
                    ? Carbon::parse($validated['birth_date'])->toDateString()
                    : null;
            }
            if (isset($validated['bio'])) {
                $attrs['bio'] = $validated['bio'];
            }

            $employee->attributes = $attrs;
            $employee->save();
        }

        $user->save();

        // Agar telegram_username kiritilgan bo'lsa telegram_accounts jadvaliga ham bog'laymiz
        if (! empty($validated['telegram_username'])) {
            $cleanTg = ltrim(trim($validated['telegram_username']), '@');
            $existingTg = \Illuminate\Support\Facades\DB::table('telegram_accounts')
                ->where('user_id', $user->id)
                ->first();

            if ($existingTg) {
                \Illuminate\Support\Facades\DB::table('telegram_accounts')
                    ->where('id', $existingTg->id)
                    ->update([
                        'telegram_username' => $cleanTg,
                        'updated_at' => now(),
                    ]);
            }
        }

        \App\Modules\Audit\Domain\Services\AuditLogger::log($request, 'USER_PROFILE_UPDATED', "Profil ma'lumotlari yangilandi: {$user->username}", [
            'actor_user_id' => $user->id,
            'actor_employee_id' => $user->employee_id,
            'auditable_type' => 'App\Models\User',
            'auditable_id' => $user->id,
            'auditable_public_id' => $user->public_id,
        ]);

        $freshUser = User::with(['employee.department', 'employee.position'])->find((int) $user->id);

        return response()->json([
            'message' => "Profil ma'lumotlari muvaffaqiyatli saqlandi",
            'user' => (new UserResource($freshUser))->resolve(),
        ]);
    }
}
