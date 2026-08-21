<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Xodimni PINFL bo'yicha tekshirish (IABS / kadrlar tizimi).
 *
 * GET {url}?pnfl={14 xonali PINFL} → {"code":0,"state":"Success","employee":{...}}
 * Xodim topilmasa: {"code":100,"state":"Error","employee":null}
 */
class EmployeeCheckService
{
    /**
     * PINFL bo'yicha xodimni qidiradi.
     *
     * @return array|null Xodim topilsa normalize qilingan ma'lumotlar, topilmasa null
     */
    public function findByPinfl(string $pinfl): ?array
    {
        $url = (string) config('services.employee_check.url');

        if ($url === '') {
            return null;
        }

        try {
            $request = Http::timeout((int) config('services.employee_check.timeout'))
                ->acceptJson();

            if (config('services.employee_check.bypass_proxy')) {
                $request->withOptions(['proxy' => '']);
            }

            // PINFL yo'l parametri sifatida: {url}/{pinfl}
            $response = $request->get(rtrim($url, '/') . '/' . $pinfl);

            if (! $response->successful()) {
                Log::error('[EMPLOYEE_CHECK] API xatolik', [
                    'pnfl' => $pinfl,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            $employee = $data['employee'] ?? null;

            if (! is_array($employee) || empty($employee)) {
                Log::info('[EMPLOYEE_CHECK] Xodim topilmadi', [
                    'pnfl' => $pinfl,
                    'response' => $data,
                ]);

                return null;
            }

            Log::info('[EMPLOYEE_CHECK] Xodim topildi (raw)', [
                'pnfl' => $pinfl,
                'employee_id' => $employee['employee_id'] ?? null,
                'state' => $employee['state'] ?? null,
                'condition_name' => $employee['condition_name'] ?? null,
                'filial' => $employee['filial'] ?? null,
                'branch_id' => $employee['branch_id'] ?? null,
                'phone' => $employee['phone'] ?? null,
                'raw' => $employee,
            ]);

            return $this->normalize($employee);
        } catch (\Throwable $e) {
            Log::error('[EMPLOYEE_CHECK] So\'rovda xatolik', [
                'pnfl' => $pinfl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * API dan kelgan employee obyektini normalize qiladi.
     * Real javob formati:
     * {
     *   "employee_id": 225736,
     *   "employee_name": "Nuriddinov Mexriddin Muxiddinovich",
     *   "state": "A",
     *   "condition_name": "Рабочие",
     *   "filial": "09006",
     *   "branch_id": 9006,
     *   "local_code": "00000",
     *   "department_name": "...",
     *   "phone": "998944866308"
     * }
     */
    private function normalize(array $employee): array
    {
        // To'liq ism: "Nuriddinov Mexriddin Muxiddinovich"
        $fullName = $this->firstValue($employee, ['employee_name', 'full_name', 'fullName', 'fio', 'name']);
        [$lastName, $firstName, $middleName] = $this->parseName($fullName);

        $first = $firstName ?? $this->firstValue($employee, ['first_name', 'firstName', 'givenName', 'ism']);
        $last = $lastName ?? $this->firstValue($employee, ['last_name', 'lastName', 'surname', 'familiya', 'familyName']);
        $middle = $middleName ?? $this->firstValue($employee, ['middle_name', 'middleName', 'middlename', 'otchestvo', 'fatherName']);

        $phone = $this->firstValue($employee, ['phone', 'phoneNumber', 'mobile', 'tel', 'telephone']);
        // BXM kodi: filial ustuni bo'yicha (masalan "09006"), nol qoldiriladi.
        // branch_id (raqam) faqat filial bo'lmasagina ishlatiladi.
        $bxm = $this->firstValue($employee, ['filial', 'branch_id', 'bxmCode', 'bxm_code', 'bxm', 'branchCode']);
        $email = $this->firstValue($employee, ['email', 'mail', 'emailAddress', 'sAMAccountName']);
        $state = $this->firstValue($employee, ['state', 'status', 'employeeState']);
        $condition = $this->firstValue($employee, ['condition_name', 'condition', 'conditionName', 'workingState']);

        return [
            'first_name' => $first,
            'last_name' => $last,
            'middle_name' => $middle,
            'phone' => $this->normalizePhone($phone),
            'bxm_code' => $bxm !== null ? (string) $bxm : null,
            'email' => $email,
            'department' => $this->firstValue($employee, ['department_name', 'department', 'division', 'filial']),
            'position' => $this->firstValue($employee, ['condition_name', 'position', 'title', 'job', 'vazifasi']),
            'state' => $state,
            'condition_name' => $condition,
            'employee_id' => $employee['employee_id'] ?? null,
            'raw' => $employee,
        ];
    }

    /**
     * AD ochish uchun xodim faol ekanligini qat'iy tekshiradi.
     *
     * PINFL API'dan keladigan 2 ta maydon bo'yicha:
     *  - state = "A" (faol holat)
     *  - condition_name = "Рабочие" (ishlayotgan)
     *
     * Ikkalasi ham aynan mos bo'lmasa — AD ochilmaydi va keyingi bosqichga
     * o'tilmaydi.
     *
     * @return array<string> Muammolar ro'yxati — bo'sh bo'lsa xodim faol
     */
    public function eligibilityErrors(array $employee): array
    {
        $errors = [];

        $state = strtoupper(trim((string) ($employee['state'] ?? '')));
        if ($state !== 'A') {
            $errors[] = $state === ''
                ? 'xodim holati (state) tizimda ko\'rsatilmagan'
                : "xodim holati faol emas (state: {$employee['state']})";
        }

        $condition = mb_strtolower(trim((string) ($employee['condition_name'] ?? '')));
        if ($condition !== 'рабочие') {
            $errors[] = $condition === ''
                ? 'xodimning ish holati (condition) tizimda ko\'rsatilmagan'
                : "xodim \"Рабочие\" (ishlayotgan) holatida emas (condition: {$employee['condition_name']})";
        }

        return $errors;
    }

    /**
     * Xodim faol ishlayotgan ekanligini qat'iy tekshiradi.
     *
     * Ma'lumot yo'q bo'lsa ham ruxsat BERMAYDI (false qaytaradi) —
     * xodim faqat state=A va condition="Рабочие" bo'lgandagina o'tadi.
     */
    public function isActiveWorker(array $employee): bool
    {
        return $this->eligibilityErrors($employee) === [];
    }

    /**
     * "Familiya Ism Otasining ismi" formatidagi to'liq ismni qismlarga ajratadi.
     *
     * @return array{string|null, string|null, string|null}
     */
    private function parseName(?string $fullName): array
    {
        if ($fullName === null) {
            return [null, null, null];
        }

        $parts = array_values(array_filter(array_map('trim', explode(' ', $fullName)), fn ($p) => $p !== ''));

        return [
            $parts[0] ?? null,
            $parts[1] ?? null,
            $parts[2] ?? null,
        ];
    }

    private function firstValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && $data[$key] !== null && $data[$key] !== '') {
                return (string) $data[$key];
            }
        }

        return null;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $phone);

        return $digits === '' ? null : $digits;
    }
}
