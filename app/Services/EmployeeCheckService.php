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
                return null;
            }

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
        // BXM kodi: branch_id (9006) yoki filial ("09006")
        $bxm = $this->firstValue($employee, ['branch_id', 'filial', 'bxmCode', 'bxm_code', 'bxm', 'branchCode']);
        $email = $this->firstValue($employee, ['email', 'mail', 'emailAddress', 'sAMAccountName']);

        return [
            'first_name' => $first,
            'last_name' => $last,
            'middle_name' => $middle,
            'phone' => $this->normalizePhone($phone),
            'bxm_code' => $bxm !== null ? ltrim($bxm, '0') : null,
            'email' => $email,
            'department' => $this->firstValue($employee, ['department_name', 'department', 'division', 'filial']),
            'position' => $this->firstValue($employee, ['condition_name', 'position', 'title', 'job', 'vazifasi']),
            'employee_id' => $employee['employee_id'] ?? null,
            'raw' => $employee,
        ];
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
