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
 * Gemeinsamer Rufnummern-Abgleich (Audit 2026-08, W2.4): tail-7-Vorfilter +
 * exakter E.164-Vergleich, vorher wortgleich in CtiCallService und
 * FritzboxImportService. Die Klassen-Priorität entscheidet über den Treffer.
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
     * Dokumentierte Grenze des Bestandsverfahrens (bei der Zusammenführung in
     * W2.4 belegt, galt vorher genauso für CTI und Fritzbox): Trennzeichen
     * INNERHALB der letzten sieben Ziffern hebeln den LIKE-Vorfilter aus.
     * Behebung bräuchte einen normalisierten Suchschlüssel an den Stammdaten —
     * eigener Schnitt. Der Test hält den Ist-Zustand sichtbar.
     */
    public function test_known_limit_separators_inside_the_tail_defeat_the_prefilter(): void {
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'phone' => '0511 / 123 456 78',
        ]);

        $match = $this->matcher()->match((int) $this->organization->id, '+4951112345678', [Customer::class]);

        $this->assertNull($match, 'Verhalten geändert — Grenze prüfen und Doku/Folgepunkt anpassen.');
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
        // Gleiche letzte 7 Ziffern, andere Vorwahl → Vorfilter trifft, der
        // exakte E.164-Vergleich verwirft.
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
