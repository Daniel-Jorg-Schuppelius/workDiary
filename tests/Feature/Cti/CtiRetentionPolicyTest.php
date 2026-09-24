<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CtiRetentionPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Cti;

use App\Models\Communication\CommunicationNote;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\{Organization, User};
use App\Services\Cti\CtiCallService;
use App\Services\Retention\RetentionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Löschbereich `cti_calls`: die Rufnummer verschwindet aus der Referenz,
 * der Betreff der verknüpften Anrufnotiz wird anonymisiert. Nach dem
 * Herauslösen in das CTI-Manifest (MVP-863) fehlte der Import der Notiz-
 * klasse — die Notiz blieb unverändert (behoben mit MVP-873).
 */
final class CtiRetentionPolicyTest extends TestCase {
    use RefreshDatabase;

    public function test_purge_anonymizes_number_and_call_note(): void {
        $organization = Organization::factory()->create();
        $actor = User::factory()->admin()->create(['organization_id' => $organization->id]);
        $note = CommunicationNote::factory()->create(['organization_id' => $organization->id, 'subject' => 'Anruf von +49 30 1234567']);
        $reference = ExternalReference::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => CtiCallService::PLUGIN_ID,
            'external_type' => CtiCallService::EXTERNAL_TYPE,
            'referenceable_type' => $note->getMorphClass(),
            'referenceable_id' => $note->id,
            'external_id' => 'call-1',
            'payload' => ['number' => '+49301234567', 'direction' => 'in'],
            'synced_at' => now()->subYears(5),
        ]);

        $purge = app(RetentionRegistry::class)->policy('cti_calls')?->purge;
        $this->assertNotNull($purge);
        $purge($reference->refresh(), $actor);

        $payload = (array) $reference->refresh()->payload;
        $this->assertArrayNotHasKey('number', $payload);
        $this->assertTrue($payload['anonymized']);
        $this->assertSame((string) __('Anruf (anonymisiert)'), $note->refresh()->subject);
    }
}
