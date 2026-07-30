<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request, $id): JsonResponse
    {
        if (!is_numeric($id)) {
            return response()->json(['message' => 'Foydalanuvchi topilmadi'], 404);
        }

        $user = User::with('employee.department')->find((int) $id);

        if (!$user) {
            return response()->json(['message' => 'Foydalanuvchi topilmadi'], 404);
        }

        return response()->json(new UserResource($user));
    }
}
