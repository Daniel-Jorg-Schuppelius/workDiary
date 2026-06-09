<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HardeningTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Whistleblowing;

use App\Models\Organization;
use App\Models\Whistleblowing\{Portal, WhistleblowingCase};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Härtung (Phase 6): Mass-Assignment-Schutz sensibler Felder und Rate-Limiting
 * des Postfach-Logins.
 */
class HardeningTest extends TestCase {
    use RefreshDatabase;

    public function test_sensitive_case_fields_are_not_mass_assignable(): void {
        $fillable = (new WhistleblowingCase)->getFillable();

        foreach (['status', 'dek_wrapped', 'access_code_hash', 'access_code_lookup', 'public_id', 'case_number', 'retention_due_at', 'legal_hold_at'] as $protected) {
            $this->assertNotContains($protected, $fillable, "{$protected} darf nicht massenzuweisbar sein.");
        }
    }

    public function test_mailbox_login_is_rate_limited(): void {
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));

        $org = Organization::factory()->create();
        Portal::create(['organization_id' => $org->id, 'public_slug' => 'acme', 'is_enabled' => true,
            'allow_anonymous' => true, 'allow_confidential' => true]);

        $status = null;
        for ($i = 0; $i < 6; $i++) {
            $status = $this->post('/melden/postfach', ['secret' => 'FALSCH-' . $i])->getStatusCode();
        }

        $this->assertSame(429, $status, 'Postfach-Login muss nach mehreren Versuchen rate-limitiert werden.');
    }
}
