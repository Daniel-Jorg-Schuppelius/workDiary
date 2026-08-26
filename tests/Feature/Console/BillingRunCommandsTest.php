<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillingRunCommandsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Http, Queue};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Vollscan 2026-08-23, D7 (MVP-725): die beiden Monatsläufe der
 * Kunden-Sonderkonditionen (Feature 098). Beide arbeiten unter einem
 * Cache-Lease — der Lease-Guard ist der eigentliche Schutz gegen
 * Doppelfakturierung und wird hier explizit geprüft.
 */
class BillingRunCommandsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Http::preventStrayRequests();
        Http::fake();
        Queue::fake();
    }

    // ── customer-billing:generate-invoices ───────────────────────────────

    public function test_account_invoices_report_an_empty_run(): void {
        $this->artisan('customer-billing:generate-invoices')
            ->expectsOutputToContain('Rechnungen: 0 erzeugt')
            ->assertExitCode(0);
    }

    public function test_account_invoices_abort_while_a_lease_is_active(): void {
        $lock = Cache::lock('customer-billing:generate-invoices', 600);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('customer-billing:generate-invoices')
                ->expectsOutputToContain('Läuft bereits (Lease aktiv)')
                ->assertExitCode(0);
        } finally {
            $lock->release();
        }
    }

    // ── customer-billing:push-retainers ──────────────────────────────────

    public function test_retainer_push_skips_organizations_without_lexoffice(): void {
        $this->artisan('customer-billing:push-retainers')
            ->expectsOutputToContain('Lexoffice nicht konfiguriert — übersprungen.')
            ->assertExitCode(0);
    }

    public function test_retainer_push_aborts_while_a_lease_is_active(): void {
        $lock = Cache::lock('customer-billing:push-retainers', 900);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('customer-billing:push-retainers')
                ->expectsOutputToContain('Läuft bereits (Lease aktiv)')
                ->assertExitCode(0);
        } finally {
            $lock->release();
        }
    }
}
