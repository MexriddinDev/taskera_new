<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        $employee = $this->relationLoaded('employee') ? $this->employee : null;

        // Rolsiz foydalanuvchi tushunchasi olib tashlandi: har kimda rol bo'ladi,
        // eng kamida 'user'. Bu yerdagi qiymat faqat himoya uchun — rol biror
        // sabab bilan yo'q bo'lsa ham "Standard User" degan eski nom chiqmasin.
        $roleObj = method_exists($this->resource, 'getRole') ? $this->resource->getRole() : null;
        $roleName = $roleObj ? $roleObj->name : 'user';
        
        $permissions = method_exists($this->resource, 'getAllPermissions') ? $this->resource->getAllPermissions() : [];

        // Huquqlari umuman yo'q foydalanuvchi uchun User::hasPermission()
        // ichida yashirin standart to'plam bor. Frontend `can()` esa faqat shu
        // ro'yxatga qaraydi — ikkalasi bir xil bo'lishi uchun standart to'plam
        // shu yerda ham qaytariladi. Aks holda rolsiz xodim "Zayavkalarim"
        // bo'limini yo'qotib qo'yardi.
        if (empty($permissions)) {
            $permissions = ['tickets.view_own', 'tickets.create'];
        }

        // Xodimlik yagona manbadan olinadi — User::isSupportStaff().
        //
        // Ilgari bu yerda alohida qoida turardi: "roli 'standard user' bo'lmasa
        // xodim" va "birorta huquqi bo'lsa xodim". Ikkalasi ham yangi rollarda
        // noto'g'ri ishlardi:
        //   spectator — roli ro'yxatda yo'q, demak xodim bo'lib qolardi va
        //               navbarda Muammolar/O'zgarishlar/SLA ochilib ketardi;
        //   user      — tickets.create huquqi borligi uchun xodim bo'lib qolardi.
        $isStaff = method_exists($this->resource, 'isSupportStaff')
            && $this->resource->isSupportStaff();

        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'firstName' => $employee?->first_name ?? $this->username,
            'lastName' => $employee?->last_name ?? 'User',
            'image' => $employee?->photo_url ?? $this->image ?? null,
            'phone' => $employee?->phone ?? null,
            'department' => $this->ad_department ?? $employee?->department?->name ?? null,
            'position' => $employee?->position?->name ?? null,
            'role' => $roleName,
            'permissions' => $permissions,
            'isStaff' => $isStaff,
        ];
    }
}

