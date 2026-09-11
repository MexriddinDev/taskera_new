<?php

declare(strict_types=1);

namespace Tests\Feature\ITSM;

use App\Models\Itms\FinesseAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Qo'ng'iroq Finesse tomonida hali davom etyaptimi.
 *
 * Qo'ng'iroq jabber (softfon) go'shagidan tugatilsa sayt bu haqda hech narsa
 * bilmasdi: tugma "Tugatish" holatida qolib, brauzer mikrofoni yozishda
 * davom etardi. Frontend shu endpointni so'rab holatni o'zi tiklaydi.
 */
final class FinesseCallActiveTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('organizations')->insert([
            'id' => 1, 'public_id' => (string) Str::uuid(), 'name' => 'Finesse test', 'code' => 'FIN',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $id = DB::table('users')->insertGetId([
            'public_id' => (string) Str::uuid(), 'organization_id' => 1, 'username' => 'agent',
            'email' => 'agent@example.com', 'password' => bcrypt('Secret123!'), 'auth_source' => 'LOCAL',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->user = User::findOrFail($id);
    }

    public function test_it_reports_an_active_call_while_a_dialog_exists(): void
    {
        $this->account();
        Http::fake([
            '*/User/*/Dialogs' => Http::response(
                '<Dialogs><Dialog><id>d-1</id></Dialog></Dialogs>', 200, ['Content-Type' => 'application/xml']
            ),
        ]);

        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/finesse/call-active')
            ->assertOk()
            ->assertJsonPath('data.active', true);
    }

    public function test_it_reports_no_call_once_the_dialog_list_is_empty(): void
    {
        $this->account();
        Http::fake([
            '*/User/*/Dialogs' => Http::response('<Dialogs/>', 200, ['Content-Type' => 'application/xml']),
        ]);

        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/finesse/call-active')
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }

    /**
     * Finesse javob bermasa "qo'ng'iroq tugagan" deb xulosa chiqarilmaydi —
     * aks holda tarmoq uzilishi yozuvni yarmida to'xtatib qo'yardi.
     */
    public function test_it_stays_silent_when_finesse_is_unreachable(): void
    {
        $this->account();
        Http::fake(['*/User/*/Dialogs' => Http::response('', 500)]);

        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/finesse/call-active')
            ->assertOk()
            ->assertJsonPath('data.active', null);
    }

    public function test_it_returns_null_when_no_account_is_connected(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/finesse/call-active')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    private function account(): void
    {
        FinesseAccount::create([
            'organization_id' => 1,
            'user_id' => $this->user->id,
            'login_id' => 'agent',
            'password_encrypted' => Crypt::encryptString('secret'),
        ]);
    }
}
