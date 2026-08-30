<?php
/*
 * Created on   : Sun Aug 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HighFindingsWave2Test.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\{Organization, User, WorkSchedule};
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\UserSpec;
use App\Services\Org\UserOffboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Sicherheitsscan 2026-08-23, zweite Welle der HOCH-Befunde (S-05 bis S-08).
 *
 * Vier verschiedene Wege, auf denen die Mandantengrenze bzw. eine
 * Aufbewahrungspflicht unterlaufen wurde — hier je ein Nachweis, dass der Weg
 * zu ist, plus die Gegenprobe, dass der erlaubte Fall weiter funktioniert.
 */
class HighFindingsWave2Test extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    // ── S-05 · CSP-Injektion über die Kachel-URL ─────────────────────────

    public function test_kachel_url_kann_die_csp_nicht_erweitern(): void {
        $this->organization->forceFill([
            'settings' => ['routing' => ['tiles' => ['url' => "https://x;script-src-attr 'unsafe-inline';y/{z}/{x}/{y}.png"]]],
        ])->save();

        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $csp = (string) $this->actingAs($user)->get(route('dashboard'))
            ->headers->get('Content-Security-Policy');

        // Der Angriff hängt an einer zusätzlichen Direktive und am
        // Fremd-Origin in img-src — `style-src 'unsafe-inline'` steht dort
        // legitim und ist nicht gemeint.
        $this->assertStringNotContainsString('script-src-attr', $csp);
        $this->assertStringNotContainsString('https://x;', $csp);

        preg_match('/img-src[^;]*/', $csp, $imgSrc);
        $this->assertSame("img-src 'self' data: blob:", trim($imgSrc[0] ?? ''));
    }

    public function test_eine_echte_kachel_url_landet_weiter_in_der_csp(): void {
        // Gegenprobe: die Sperre darf den Regelfall nicht treffen.
        $this->organization->forceFill([
            'settings' => ['routing' => ['tiles' => ['url' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png']]],
        ])->save();

        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        $csp = (string) $this->actingAs($user)->get(route('dashboard'))
            ->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('https://tile.openstreetmap.org', $csp);
    }

    // ── S-06 · Arbeitszeit-Modell fremder Mitarbeiter ────────────────────

    public function test_arbeitszeitmodell_fremder_mitarbeiter_bleibt_zu(): void {
        $fremdeOrg = Organization::factory()->create();
        $opfer = User::factory()->create(['organization_id' => $fremdeOrg->id]);

        $angreifer = User::factory()->personalverwaltung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($angreifer)
            ->get(route('users.work-schedule.edit', $opfer->sqid))
            ->assertStatus(403);

        $this->actingAs($angreifer)
            ->put(route('users.work-schedule.update', $opfer->sqid), [
                'valid_from' => now()->startOfMonth()->toDateString(),
                'weekly_minutes' => 60,
            ])
            ->assertForbidden();

        $this->assertSame(0, WorkSchedule::query()->withoutGlobalScopes()->where('user_id', $opfer->id)->count());
    }

    // ── S-07 · CSV-Import über die Mandantengrenze ───────────────────────

    public function test_import_fasst_keine_fremden_konten_an(): void {
        $fremdeOrg = Organization::factory()->create();
        $opfer = User::factory()->create([
            'organization_id' => $fremdeOrg->id,
            'email' => 'opfer@fremde-firma.test',
            'name' => 'Echter Name',
            'hourly_rate' => 42.00,
        ]);

        [$outcome, $issue] = app(UserSpec::class)->upsert([
            'name' => 'Überschrieben',
            'email' => 'opfer@fremde-firma.test',
            'hourly_rate' => '0',
        ], $this->organization);

        $this->assertSame(ImportOutcome::Failed, $outcome, 'Die Zeile hätte abgelehnt werden müssen.');
        $this->assertNotNull($issue);
        $this->assertSame('email', $issue->field);

        $opfer->refresh();
        $this->assertSame('Echter Name', $opfer->name);
        $this->assertStringStartsWith('42.00', (string) $opfer->hourly_rate);

        // Kein Konto in der eigenen Organisation angelegt: sonst wäre die
        // Ablehnung nur die halbe Miete.
        $this->assertSame(
            0,
            User::query()->withoutGlobalScopes()
                ->where('email', 'opfer@fremde-firma.test')
                ->where('organization_id', $this->organization->id)
                ->count()
        );
    }

    public function test_import_legt_in_der_eigenen_organisation_weiter_an(): void {
        [$outcome] = app(UserSpec::class)->upsert([
            'name' => 'Neue Kollegin',
            'email' => 'neu@eigene-firma.test',
        ], $this->organization);

        $this->assertSame(ImportOutcome::Created, $outcome);
    }

    // ── S-08 · Nachweise am Konto ───────────────────────────────────────

    public function test_nachweise_mit_urheberschaft_zaehlen_als_aufbewahrungspflichtig(): void {
        // Die Oberfläche weist das Löschen ab, bevor die Kaskade greift —
        // Protokolle samt Kundenunterschrift hingen vorher am Konto.
        $service = app(UserOffboardingService::class);

        $reflection = new \ReflectionClass($service);
        $tables = $reflection->getConstant('EVIDENCE_TABLES');

        foreach (['protocols', 'documents', 'disposal_jobs', 'disposal_handovers', 'diary_entries', 'form_submissions', 'safety_events', 'tours'] as $table) {
            $this->assertArrayHasKey($table, $tables, $table . ' fehlt in den Nachweistabellen.');
        }
    }

    // ── S-11 · Reset-Link aus dem Host-Header ───────────────────────────

    public function test_reset_link_folgt_nicht_dem_host_header(): void {
        \Illuminate\Support\Facades\Notification::fake();
        config(['app.url' => 'https://work.example.com']);

        $opfer = User::factory()->create(['organization_id' => $this->organization->id, 'email' => 'opfer@example.test']);

        // Der Aufruf ist unauthentifiziert; ein Angreifer bestimmt den Host.
        $this->withServerVariables(['HTTP_HOST' => 'angreifer.example'])
            ->post(route('password.email'), ['email' => 'opfer@example.test']);

        \Illuminate\Support\Facades\Notification::assertSentTo(
            $opfer,
            \App\Notifications\PasswordResetLink::class,
            function (\App\Notifications\PasswordResetLink $notification): bool {
                $this->assertStringStartsWith('https://work.example.com/', $notification->url);
                $this->assertStringNotContainsString('angreifer.example', $notification->url);

                return true;
            }
        );
    }

    // ── S-15 · Instanz-Lizenz überschreiben ─────────────────────────────

    public function test_lizenz_darf_nur_der_betreiber_ersetzen(): void {
        // Ist eine nutzbare Lizenz installiert, ist das Aufspielen einer
        // anderen eine Betreiber-Handlung — vorher genügte ein beliebiger
        // signierter Schlüssel plus passender Host-Header.
        $service = $this->partialMock(\App\Services\Licensing\LicenseService::class, function ($mock): void {
            $mock->shouldReceive('current')->andReturn(
                new \App\Services\Licensing\LicenseResult(\App\Services\Licensing\LicenseStatus::Valid, null, null)
            );
            $mock->shouldNotReceive('install');
        });

        $this->post(route('license.store'), ['license_key' => 'irgendein-signierter-schluessel'])
            ->assertForbidden();
    }

    // ── S-16 · Installer nach dem Wiederherstellen ──────────────────────

    public function test_installer_bleibt_zu_wenn_es_schon_einen_betreiber_gibt(): void {
        // Der Marker `storage/installed` wird von keinem Backup gesichert.
        // Nach einem Restore stand der Wizard sonst gegen die volle
        // Mandanten-Datenbank offen.
        User::factory()->platformAdmin()->create();

        $this->get('/install')->assertNotFound();
        $this->post('/install/admin', [
            'org_name' => 'Übernahme GmbH',
            'name' => 'Angreifer',
            'email' => 'angreifer@example.test',
            'password' => 'ein-langes-geheimnis-1234',
            'password_confirmation' => 'ein-langes-geheimnis-1234',
        ])->assertNotFound();

        $this->assertSame(0, User::query()->withoutGlobalScopes()->where('email', 'angreifer@example.test')->count());
    }

    // ── S-17 · 2FA-Selbstverwaltung ─────────────────────────────────────

    public function test_aktiver_authenticator_wird_nicht_stillschweigend_ersetzt(): void {
        // Der Aufruf setzte bedingungslos ein neues Secret und
        // `two_factor_confirmed_at = null`: war TOTP der einzige Faktor, war
        // die Zwei-Faktor-Pflicht danach ohne einen einzigen Code aufgehoben.
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'two_factor_secret' => 'ALTESGEHEIMNIS234567',
            'two_factor_confirmed_at' => now(),
        ]);

        $this->actingAs($user)->post(route('account.2fa.enable'))->assertSessionHasErrors();

        $user->refresh();
        $this->assertSame('ALTESGEHEIMNIS234567', $user->two_factor_secret);
        $this->assertNotNull($user->two_factor_confirmed_at);
    }

    public function test_bestaetigten_faktor_entfernt_nur_wer_sich_ausweist(): void {
        $user = User::factory()->create([
            'organization_id' => $this->organization->id,
            'is_new_system' => true,
            'password' => bcrypt('mein-langes-passwort-1'),
        ]);

        $credential = $user->twoFactorCredentials()->create([
            'type' => \App\Enums\Auth\TwoFactorType::Email->value,
            'label' => $user->email,
            'confirmed_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('account.2fa.credential.destroy', $credential))
            ->assertSessionHasErrors('credential');

        $this->assertSame(1, $user->twoFactorCredentials()->count());

        // Mit Passwort geht es — sonst wäre die Verwaltung unbenutzbar.
        $this->actingAs($user)
            ->delete(route('account.2fa.credential.destroy', $credential), ['current_password' => 'mein-langes-passwort-1'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $user->twoFactorCredentials()->count());
    }

    // ── S-19 · Log-Auszug im Problem-Melde-Dialog ───────────────────────

    public function test_log_auszug_liefert_nur_den_eigenen_request(): void {
        // `rid=2026-08-23` und `rid=production.ERROR` passen zum
        // Request-ID-Format und trafen per str_contains praktisch jede Zeile
        // des mandantenübergreifenden Logs.
        $logPath = storage_path('logs/laravel.log');
        $vorher = is_file($logPath) ? (string) file_get_contents($logPath) : null;

        file_put_contents($logPath, implode("\n", [
            '[2026-08-23 10:00:00] production.ERROR: Fremdfehler {"request_id":"AAAAAAAA1111"}',
            '[2026-08-23 10:00:01] production.ERROR: Eigener Fehler {"request_id":"BBBBBBBB2222"}',
        ]) . "\n");

        try {
            $user = User::factory()->create(['organization_id' => $this->organization->id]);

            $antwort = $this->actingAs($user)->get(route('problem-reports.create', ['rid' => '2026-08-23']));
            $antwort->assertOk();
            $this->assertStringNotContainsString('Fremdfehler', (string) $antwort->getContent());

            $treffer = $this->actingAs($user)->get(route('problem-reports.create', ['rid' => 'BBBBBBBB2222']));
            $treffer->assertOk();
            $this->assertStringContainsString('Eigener Fehler', (string) $treffer->getContent());
        } finally {
            if ($vorher === null) {
                @unlink($logPath);
            } else {
                file_put_contents($logPath, $vorher);
            }
        }
    }

}
