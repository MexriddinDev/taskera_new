<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cisco Finesse (UCCX) REST API bilan ishlash.
 *
 * Finesse XML qaytaradi (JSON emas) va HTTP Basic auth ishlatadi — agentning
 * O'Z login/paroli bilan, ya'ni har so'rov o'sha agent nomidan ketadi.
 *
 * Server o'z-o'zini imzolagan sertifikat bilan ishlaydi, shuning uchun TLS
 * tekshiruvi config orqali o'chiriladi (services.finesse.verify_tls).
 */
final class FinesseService
{
    /** Agent holatlari, `state` maydonidan keladi. `READY` — qo'ng'iroqqa tayyor. */
    public const STATE_READY = 'READY';

    private function client(string $login, string $password): PendingRequest
    {
        return Http::withBasicAuth($login, $password)
            ->withOptions(['verify' => (bool) config('services.finesse.verify_tls')])
            ->timeout((int) config('services.finesse.timeout'))
            ->accept('application/xml');
    }

    private function url(string $path): string
    {
        return config('services.finesse.url').'/'.ltrim($path, '/');
    }

    /**
     * Agent ma'lumoti: holat, extension, ism.
     *
     * @return array{ok:bool, status:int, state?:string, extension?:string, firstName?:string, lastName?:string, teamName?:string, roles?:array<int,string>, message?:string}
     */
    public function user(string $login, string $password): array
    {
        try {
            $response = $this->client($login, $password)->get($this->url('User/'.rawurlencode($login)));
        } catch (\Throwable $e) {
            Log::warning('Finesse: serverga ulanib bo\'lmadi', ['login' => $login, 'error' => $e->getMessage()]);

            return ['ok' => false, 'status' => 0, 'message' => 'Finesse serveriga ulanib bo\'lmadi.'];
        }

        if ($response->status() === 401) {
            return ['ok' => false, 'status' => 401, 'message' => 'Login yoki parol noto\'g\'ri.'];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'status' => $response->status(), 'message' => 'Finesse xatosi: HTTP '.$response->status()];
        }

        $xml = $this->parse($response->body());
        if ($xml === null) {
            return ['ok' => false, 'status' => $response->status(), 'message' => 'Finesse javobini o\'qib bo\'lmadi.'];
        }

        $roles = [];
        foreach ($xml->roles->role ?? [] as $role) {
            $roles[] = (string) $role;
        }

        return [
            'ok' => true,
            'status' => 200,
            'state' => (string) $xml->state,
            'extension' => (string) $xml->extension,
            'firstName' => (string) $xml->firstName,
            'lastName' => (string) $xml->lastName,
            'teamName' => (string) $xml->teamName,
            'roles' => $roles,
        ];
    }

    /**
     * Qo'ng'iroq boshlash. Agent Finesse'da extension bilan login qilgan va
     * qo'ng'iroqqa yaroqli holatda bo'lishi shart — aks holda Finesse xato
     * qaytaradi va uning matni foydalanuvchiga ko'rsatiladi.
     *
     * @return array{ok:bool, status:int, message?:string}
     */
    public function makeCall(string $login, string $password, string $fromExtension, string $toNumber): array
    {
        $body = '<Dialog>'
            .'<requestedAction>MAKE_CALL</requestedAction>'
            .'<fromAddress>'.htmlspecialchars($fromExtension, ENT_XML1).'</fromAddress>'
            .'<toAddress>'.htmlspecialchars($toNumber, ENT_XML1).'</toAddress>'
            .'</Dialog>';

        try {
            $response = $this->client($login, $password)
                ->withBody($body, 'application/xml')
                ->post($this->url('User/'.rawurlencode($login).'/Dialogs'));
        } catch (\Throwable $e) {
            Log::warning('Finesse: qo\'ng\'iroq yuborilmadi', ['login' => $login, 'error' => $e->getMessage()]);

            return ['ok' => false, 'status' => 0, 'message' => 'Finesse serveriga ulanib bo\'lmadi.'];
        }

        if ($response->successful()) {
            return ['ok' => true, 'status' => $response->status()];
        }

        return [
            'ok' => false,
            'status' => $response->status(),
            'message' => $this->apiErrorMessage($response->body()) ?? ('Qo\'ng\'iroq amalga oshmadi: HTTP '.$response->status()),
        ];
    }

    /** Finesse xato javobidagi tushuntirish matni (ApiErrors bloki). */
    private function apiErrorMessage(string $body): ?string
    {
        $xml = $this->parse($body);
        if ($xml === null) {
            return null;
        }

        $error = $xml->ApiError ?? $xml->apiErrors->apiError ?? null;
        if ($error === null) {
            return null;
        }

        $text = trim((string) ($error->ErrorMessage ?? $error->errorMessage ?? ''));
        $type = trim((string) ($error->ErrorType ?? $error->errorType ?? ''));

        return $text !== '' ? $text : ($type !== '' ? $type : null);
    }

    private function parse(string $body): ?\SimpleXMLElement
    {
        if (trim($body) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $xml === false ? null : $xml;
    }
}
