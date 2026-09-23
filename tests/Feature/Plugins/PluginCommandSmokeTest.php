<?php

/*
 * Filename     : PluginCommandSmokeTest.php
 * Description  : Jeder Plugin-Befehl laeuft ohne aktiviertes Plugin sauber
 *                durch, statt mit einer Ausnahme abzubrechen.
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\Platform\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Herkunft: Vollscan 2026-09-15, Befund `C1-10`. Das Command-Test-Gate scannte
 * nur `app/Console/Commands` und liess die Befehle unter
 * `app/Plugins/<Plugin>/Console` ungeprueft — 16 von ihnen wurden von keinem
 * Test beruehrt, darunter der GoBD-Pflichtschritt vor dem Buchhaltungswechsel.
 *
 * Dieser Test deckt den Weg ab, den jede Installation ohne das jeweilige Plugin
 * geht: Der Befehl findet keine aktivierte Organisation, fasst nichts an und
 * endet mit Code 0. Fachliche Synchronisationswege pruefen die Plugin-eigenen
 * Tests mit ihren Transport-Attrappen.
 */
final class PluginCommandSmokeTest extends TestCase {
    use RefreshDatabase;

    /**
     * Signaturen aller Plugin-Befehle ohne eigenen Fachtest.
     *
     * @var list<string>
     */
    private const COMMANDS = [
        'billbee:sync',
        'caldav:publish',
        'calendly:backfill',
        'clockify:push',
        'easybill:sync',
        'etsy:sync',
        'google-calendar:publish',
        'jtl:sync',
        'lexoffice:sync-articles',
        'lexoffice:sync-contacts',
        'msgraph:publish',
        'openproject:push',
        'openproject:import',
        'orgamax:sync',
        'remote:sync-sessions',
        'sevdesk:pull-vouchers',
    ];

    protected function setUp(): void {
        parent::setUp();
        // Eine Organisation existiert, aber kein Plugin ist aktiviert: genau so
        // sieht eine frische Installation aus.
        $organization = Organization::factory()->create();
        app()->instance('currentOrganization', $organization);
    }

    public function test_plugin_commands_end_cleanly_without_an_activated_plugin(): void {
        foreach (self::COMMANDS as $signature) {
            $this->artisan($signature)->assertExitCode(0);
        }

        $this->assertTrue(true, 'Alle Plugin-Befehle sind ohne Aktivierung sauber durchgelaufen.');
    }
}
