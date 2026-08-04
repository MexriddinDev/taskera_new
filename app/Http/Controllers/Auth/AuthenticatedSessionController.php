<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AdAuthService;
use App\Services\AdUserProvisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Foydalanuvchi login qiladi (Blade/Web uchun).
     *
     * Bitta forma — hammaga bir xil.
     * auth_source ga qarab ichida LOCAL yoki AD autentifikatsiya tanlanadi.
     */
    public function store(Request $request)
    {
        $request->validate([
            'login'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginInput = strtolower(trim((string) $request->input('login')));
        // UPN ko'rinishida yozilsa ("yusuf.rahimboyev@xb.uz") — domain qismi olib
        // tashlanadi, chunki AD da sAMAccountName domensiz saqlanadi.
        $loginInput = preg_replace('/@.*$/', '', $loginInput);
        $password   = (string) $request->input('password');

        // ── 1. DB dan foydalanuvchini izlash ─────────────────────────────────
        $user = \App\Models\User::where('username', $loginInput)->first();

        // ── 2. auth_source ga qarab autentifikatsiya ──────────────────────────
        if ($user && $user->auth_source === 'LOCAL') {
            // LOCAL: DB hash bilan solishtirish (faqat bootstrap superadmin)
            if (! Hash::check($password, (string) $user->password)) {
                return back()
                    ->withErrors(['login' => 'Kiritilgan login yoki parol noto\'g\'ri.'])
                    ->onlyInput('login');
            }
            Auth::login($user, $request->boolean('remember'));

        } else {
            // AD: DB da yo'q yoki auth_source='AD' bo'lsa — AD orqali
            try {
                $adService    = app(AdAuthService::class);
                $adAttributes = $adService->authenticate($loginInput, $password);
            } catch (\RuntimeException $e) {
                return back()
                    ->withErrors(['login' => 'AD server mavjud emas. IT bo\'limiga murojaat qiling.'])
                    ->onlyInput('login');
            }

            if (! $adAttributes) {
                return back()
                    ->withErrors(['login' => 'Kiritilgan login yoki parol noto\'g\'ri.'])
                    ->onlyInput('login');
            }

            if (! $adAttributes['enabled']) {
                return back()
                    ->withErrors(['login' => 'Akkauntingiz bloklangan. IT bo\'limiga murojaat qiling.'])
                    ->onlyInput('login');
            }

            $provision = app(AdUserProvisionService::class);
            $user = $provision->findOrProvision($adAttributes);
            Auth::login($user, $request->boolean('remember'));
        }

        $request->session()->regenerate();

        // Rol asosida yo'naltirish
        if ($user->isSuperAdmin() || $user->isDepartmentAdmin()) {
            return redirect()->intended('/dashboard');
        }

        return redirect()->intended('/portal');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
