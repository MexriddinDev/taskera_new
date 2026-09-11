<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * O'z-o'zini ro'yxatdan o'tkazish yopilgani [C-01].
 *
 * `/register` tashqi shaxsga `auth_source=LOCAL`, `status=ACTIVE` hisob ochib,
 * darhol sessiya berardi — ya'ni AD, HR provisioning va hisob holati nazorati
 * butunlay chetlab o'tilardi. Hisob faqat AD orqali yoki admin tomonidan
 * yaratiladi, shuning uchun marshrut umuman bo'lmasligi kerak.
 */
final class SelfRegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_registration_route_is_registered(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_contains($uri, 'register'))
            ->values()
            ->all();

        $this->assertSame([], $uris, 'Ro\'yxatdan o\'tish marshruti qaytib kelgan: '.implode(', ', $uris));
    }

    public function test_register_endpoints_are_not_reachable(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'username' => 'intruder',
            'email' => 'intruder@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['username' => 'intruder']);
    }
}
