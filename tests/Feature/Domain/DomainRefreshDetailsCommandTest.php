<?php
/*
 * Created on   : Mon Oct 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainRefreshDetailsCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\Reselling\SubscriptionProvider;
use App\Models\Domain\{DomainProjection, DomainProviderConnection};
use App\Models\Reselling\ResaleSubscription;
use App\Services\Reselling\Register\DomainSubscriptionSync;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeDomainResellingTransport;
use Tests\TestCase;

/**
 * Domains ins Register (Feature 185, MVP-1407): der Detailabgleich mit Budget
 * holt Registrierungsdatum und Preis nach, fehlende Felder zuerst; bis dahin
 * leitet der Domain-Sync den Beginn aus dem Ablaufdatum ab.
 */
class DomainRefreshDetailsCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-26');
    }

    /** @param  array<string, mixed>  $attributes */
    private function projection(DomainProviderConnection $connection, string $domain, array $attributes = []): DomainProjection {
        return DomainProjection::factory()->create(array_merge([
            'organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_domain' => $domain,
            'domain_hash' => DomainProjection::hashFor($domain), 'status' => 'ACTIVE', 'renewal_mode' => 'AUTORENEW',
            'registration_at' => null, 'expiration_at' => '2027-03-15', 'renewal_price' => null, 'synced_at' => now()->subDay(),
        ], $attributes));
    }

    public function test_budgeted_refresh_fills_missing_details_first(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $missing = $this->projection($connection, 'ohne-details.de');
        $complete = $this->projection($connection, 'komplett.de', ['registration_at' => '2020-03-15', 'renewal_price' => '8.87', 'synced_at' => now()->subDays(30)]);
        $second = $this->projection($connection, 'zweite.de', ['synced_at' => now()->subDays(2)]);

        FakeDomainResellingTransport::fake([
            'StatusDomain' => static fn(array $params): string => FakeDomainResellingTransport::properties([[
                'registrar' => 'DENIC', 'registrationdate' => '2019-03-15 00:00:00', 'registrationexpirationdate' => '2027-03-15 00:00:00',
                'renewalprice' => '9.50', 'currency' => 'EUR', 'user' => 'reseller1',
            ]]),
        ]);

        $this->assertSame(0, Artisan::call('domain:refresh-details', ['--limit' => 2]));

        $this->assertStringContainsString('2 Domains nachgeladen', Artisan::output());
        $this->assertSame('2019-03-15', $missing->refresh()->registration_at?->toDateString(), 'fehlende Felder zuerst');
        $this->assertSame('2019-03-15', $second->refresh()->registration_at?->toDateString());
        $this->assertSame('2020-03-15', $complete->refresh()->registration_at?->toDateString(), 'vollständige Domain kommt erst nach den unvollständigen dran');
        $this->assertSame('9.50', (string) $missing->renewal_price);
    }

    public function test_domain_sync_derives_the_start_from_the_expiration_until_details_arrive(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $projection = $this->projection($connection, 'abgeleitet.de', ['expiration_at' => '2027-03-15']);

        $reference = CarbonImmutable::parse('2026-10-26');
        $this->assertSame('2025-03-15', DomainSubscriptionSync::startOf($projection, null, $reference)?->toDateString(), 'Jahrestag vor dem laufenden Lizenzjahr');
        $this->assertSame('2025-01-01', DomainSubscriptionSync::startOf($this->projection($connection, 'frisch.de', ['expiration_at' => '2027-01-01']), null, $reference)?->toDateString(), 'laufendes Lizenzjahr ab 01.01.2026, Beginn ein Jahr davor');

        app(DomainSubscriptionSync::class)->sync($this->organization, $reference);
        $subscription = ResaleSubscription::query()->where('provider', SubscriptionProvider::DomainReselling->value)->where('external_id', 'abgeleitet.de')->firstOrFail();
        $this->assertSame('2025-03-15', $subscription->starts_on->toDateString());
        $this->assertSame(['2025-03-15', '2026-03-15'], $subscription->periods()->pluck('starts_on')->map(static fn($d) => $d->toDateString())->all());

        // Verlängerung beim Anbieter: der Ablauf wandert, der abgeleitete Beginn bleibt (keine wandernden Perioden).
        $projection->forceFill(['expiration_at' => '2028-03-15'])->save();
        app(DomainSubscriptionSync::class)->sync($this->organization, $reference);
        $this->assertSame('2025-03-15', $subscription->refresh()->starts_on->toDateString());

        // Echtes Registrierungsdatum gewinnt.
        $projection->forceFill(['registration_at' => '2019-03-15'])->save();
        app(DomainSubscriptionSync::class)->sync($this->organization, $reference);
        $this->assertSame('2019-03-15', $subscription->refresh()->starts_on->toDateString());
    }
}
