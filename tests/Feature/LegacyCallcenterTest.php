<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UsesLegacySqlite;
use Tests\TestCase;

class LegacyCallcenterTest extends TestCase
{
    use RefreshDatabase;
    use UsesLegacySqlite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useLegacySqlite();
    }

    public function test_callcenter_login_succeeds_and_stores_session(): void
    {
        DB::connection('legacy')->table('calluser')->insert([
            'uname' => 'agent',
            'userpw' => 'secret',
        ]);

        $this->post(route('legacy.callcenter.login.submit'), [
            'username' => 'agent',
            'password' => 'secret',
        ])->assertRedirect(route('legacy.callcenter.notdienst'));

        $this->assertSame('agent', session('legacy_callcenter_user'));
    }

    public function test_callcenter_login_is_rate_limited_after_repeated_failures(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from(route('legacy.callcenter.login'))->post(route('legacy.callcenter.login.submit'), [
                'username' => 'agent',
                'password' => 'wrong',
            ])->assertRedirect(route('legacy.callcenter.login'));
        }

        $this->from(route('legacy.callcenter.login'))->post(route('legacy.callcenter.login.submit'), [
            'username' => 'agent',
            'password' => 'wrong',
        ])->assertRedirect(route('legacy.callcenter.login'))
            ->assertSessionHasErrors('username');
    }
}
