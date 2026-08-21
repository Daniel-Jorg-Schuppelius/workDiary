<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PhoneNumberMatcherTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Contacts;

use App\Models\{Customer, ForeignCustomer, Organization};
use App\Services\Contacts\PhoneNumberMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Gemeinsamer Rufnummern-Abgleich (Audit 2026-08, W2.4; Suchschlüssel seit
 * 2026-08-21): exakte Suche auf `phone_e164`/`mobile_e164`, vorher ein
 * tail-7-LIKE-Vorfilter mit bekannter Lücke. Die Klassen-Priorität
 * entscheidet über den Treffer.
 */
class PhoneNumberMatcherTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function matcher(): PhoneNumberMatcher {
        return app(PhoneNumberMatcher::class);
    }

    public function test_matches_across_different_notations(): void {
        // Gespeichert in nationaler Schreibweise mit Vorwahl-Trenner, gesucht
        // in E.164 — der häufige Fall aus Import und Anrufliste.
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '0511 / 12345678',
        ]);

        $match = $this->matcher()->match((int) $this->organization->id, '+4951112345678', [Customer::class]);

        $this->assertNotNull($match);
        $this->assertSame($customer->id, $match->id);
    }

    /**
     * Regression zur behobenen Grenze (Folgepunkt aus W2.4): Trennzeichen
     * INNERHALB der letzten sieben Ziffern hebelten den früheren LIKE-Vorfilter
     * aus — der Kunde wurde nicht gefunden, obwohl es dieselbe Nummer war. Mit
     * dem normalisierten Suchschlüssel trifft der Abgleich.
     */
    public function test_separators_inside_the_tail_no_longer_defeat_the_lookup(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '0511 / 123 456 78',
        ]);

        $match = $this->matcher()->match((int) $this->organization->id, '+4951112345678', [Customer::class]);

        $this->assertNotNull($match);
        $this->assertSame($customer->id, $match->id);
    }

    public function test_the_search_key_is_kept_current_on_save(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '0511 / 123 456 78',
        ]);
        $this->assertSame('+4951112345678', $customer->fresh()?->phone_e164);

        $customer->update(['phone' => '+49 30 111 222 333']);
        $this->assertSame('+4930111222333', $customer->fresh()?->phone_e164);

        // Unlesbares ergibt keinen Schlüssel — ein geratener fände den
        // falschen Kunden, und das fiele niemandem auf.
        $customer->update(['phone' => 'Durchwahl erfragen']);
        $this->assertNull($customer->fresh()?->phone_e164);
    }

    public function test_mobile_numbers_are_matched_too(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => null,
            'mobile' => '0171-2345678',
        ]);

        $match = $this->matcher()->match((int) $this->organization->id, '+491712345678', [Customer::class]);

        $this->assertSame($customer->id, $match?->id);
    }

    public function test_class_order_decides_the_winner(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '+4930111222333',
        ]);
        $endCustomer = ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '+4930111222333',
        ]);

        // Fritzbox-Reihenfolge: Endkunde vor Firma (präziseres Buchungsziel).
        $match = $this->matcher()->match(
            (int) $this->organization->id,
            '+4930111222333',
            [ForeignCustomer::class, Customer::class],
        );

        $this->assertInstanceOf(ForeignCustomer::class, $match);
        $this->assertSame($endCustomer->id, $match->id);
    }

    public function test_similar_tail_without_exact_match_is_rejected(): void {
        // Gleiche letzte 7 Ziffern, andere Vorwahl — der Schlüssel ist ein
        // anderer, also kein Treffer.
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '+49891234567',
        ]);

        $match = $this->matcher()->match((int) $this->organization->id, '+49301234567', [Customer::class]);

        $this->assertNull($match);
    }

    public function test_other_organizations_are_never_matched(): void {
        $foreignOrg = Organization::factory()->create();
        Customer::factory()->create([
            'organization_id' => $foreignOrg->id,
            'phone' => '+4940999888777',
        ]);

        $match = $this->matcher()->match((int) $this->organization->id, '+4940999888777', [Customer::class]);

        $this->assertNull($match);
    }
}
