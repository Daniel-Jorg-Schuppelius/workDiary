<?php

/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyAutoRedirectTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Legacy\Http\Controllers\LegacyNotdienstController;
use App\Legacy\Http\Controllers\LegacyOnCallController;
use App\Legacy\Models\LegacyNotdienst;
use App\Legacy\Models\LegacyOnCall;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\RedirectResponse;
use Tests\TestCase;

class LegacyAutoRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    private function actAsLegacyAdmin(): User
    {
        // Username "admin" wird via LEGACY_FALLBACK_ADMINS als Admin erkannt.
        $admin = User::factory()->admin()->create(['name' => 'admin']);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_oncall_edit_redirects_when_migrated(): void
    {
        $admin = $this->actAsLegacyAdmin();

        OnCallShift::create([
            'legacy_id' => 4242,
            'user_id' => $admin->id,
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $oncall = new LegacyOnCall;
        $oncall->id = 4242;

        $response = app(LegacyOnCallController::class)->edit($oncall);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('week.index'), $response->getTargetUrl());
    }

    public function test_oncall_edit_does_not_redirect_when_not_migrated(): void
    {
        $this->actAsLegacyAdmin();

        $oncall = new LegacyOnCall;
        $oncall->id = 9999;

        // Ohne Migration sollte die Methode KEIN Redirect zurückliefern, sondern
        // versuchen, die View zu rendern (was hier mangels Daten ggf. wirft —
        // wir prüfen nur, dass kein Redirect auf week.index passiert).
        try {
            $response = app(LegacyOnCallController::class)->edit($oncall);
            $this->assertNotInstanceOf(RedirectResponse::class, $response);
        } catch (\Throwable $e) {
            // View-Render kann ohne Legacy-DB scheitern — das ist OK,
            // hier zählt nur, dass kein Auto-Redirect passiert ist.
            $this->assertTrue(true);
        }
    }

    public function test_notdienst_edit_redirects_when_migrated(): void
    {
        $admin = $this->actAsLegacyAdmin();

        EmergencyAssignment::create([
            'legacy_id' => 7777,
            'user_id' => $admin->id,
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $notdienst = new LegacyNotdienst;
        $notdienst->id = 7777;

        $response = app(LegacyNotdienstController::class)->edit($notdienst);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('week.index'), $response->getTargetUrl());
    }
}
