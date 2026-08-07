<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Modules\Ticketing\Infrastructure\Eloquent\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request, $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Foydalanuvchi topilmadi'], 404);
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
}
