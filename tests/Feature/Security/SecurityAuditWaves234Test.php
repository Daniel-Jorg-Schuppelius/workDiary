<?php
/*
 * Created on   : Tue Sep 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityAuditWaves234Test.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Models\{Attachment, DiaryEntry, OperationsTask, User};
use App\Support\Crypto\EnvelopeCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Regressionsnetz für die zweite bis vierte Welle des Sicherheitsaudits
 * 2026-09-13 (Vollscan 2026-09-15, Befund `C4-12` / MVP-795).
 *
 * Die Korrekturen lagen im Code, ohne dass ein Test sie hielt — die erste
 * Welle war vollständig getestet, die Wellen 2–4 gar nicht. Jeder Test hier
 * schlägt fehl, wenn man die zugehörige Korrektur zurücknimmt.
 */
class SecurityAuditWaves234Test extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    // ── api-3 · Zwei-Faktor-Pflicht galt nur der Weboberfläche ───────────

    /**
     * Die Pflicht hing allein am Web-Stack. Über ein persönliches Token ging
     * derselbe Zugriff an ihr vorbei — und eine Umleitung auf ein Formular
     * wäre dort auch keine brauchbare Antwort gewesen.
     */
    public function test_the_api_refuses_a_token_while_two_factor_setup_is_pending(): void {
        $this->organization->forceFill(['two_factor_required' => true])->save();
        $admin = $this->orgAdmin();
        $this->assertFalse($admin->hasTwoFactorEnabled(), 'Der Testnutzer trägt bereits einen Zweitfaktor.');

        Sanctum::actingAs($admin, ['articles:read']);

        $this->getJson(route('api.articles.index'))
            ->assertForbidden()
            ->assertJsonPath('reason', 'two_factor_setup_required');
    }

    /** Gegenprobe: ohne die Pflicht der Organisation bleibt der Weg offen. */
    public function test_the_api_stays_open_when_two_factor_is_not_required(): void {
        $this->organization->forceFill(['two_factor_required' => false])->save();
        Sanctum::actingAs($this->orgAdmin(), ['articles:read']);

        $this->getJson(route('api.articles.index'))->assertOk();
    }

    // ── api-4 / surface-2 / config-2 · SCIM ohne Drossel ─────────────────

    /**
     * Der Verzeichnis-Stack lief ohne jede Drossel. Jeder fehlgeschlagene
     * Versuch schreibt eine Zeile ins Sicherheitsprotokoll — unbegrenzte
     * Versuche sind damit zugleich Token-Raten und ein Weg, die Datenbank
     * vollzuschreiben.
     */
    public function test_scim_routes_run_behind_a_rate_limiter(): void {
        foreach (['scim.users.index', 'scim.users.store', 'scim.groups.index'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Route '$name' nicht gefunden.");

            $this->assertContains(
                'throttle:scim',
                $route->gatherMiddleware(),
                "Die SCIM-Route '$name' läuft ohne die Drossel 'scim'.",
            );
        }
    }

    /** Die Drossel muss auch registriert sein, sonst greift der Name ins Leere. */
    public function test_the_scim_rate_limiter_is_registered(): void {
        $this->assertNotNull(
            app(\Illuminate\Cache\RateLimiter::class)->limiter('scim'),
            "Der Limiter 'scim' ist nicht registriert — 'throttle:scim' liefe ins Leere.",
        );
    }

    // ── authz-4 · Anhang-Download ohne Blick auf das Trägerobjekt ────────

    /**
     * Der Download prüfte nur das Token-Recht `attachments:read`, nicht die
     * Policy des Trägerobjekts. Wer irgendein Lese-Token besass, holte damit
     * jeden Anhang der Organisation — auch den am fremden Auftrag.
     */
    public function test_the_api_attachment_download_honours_the_carrier_policy(): void {
        $uploader = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        // Dieselbe Organisation: sonst greift der Mandanten-Scope und wir
        // prüften die Mandantentrennung statt der Rechteprüfung.
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $entry = DiaryEntry::factory()->for($uploader)->create(['organization_id' => $this->organization->id]);
        $attachment = Attachment::factory()->for($uploader, 'uploader')->create([
            'attachable_type' => DiaryEntry::class,
            'attachable_id' => $entry->id,
        ]);

        Sanctum::actingAs($other, ['attachments:read']);

        $this->getJson(route('api.attachments.download', $attachment))->assertForbidden();
    }

    // ── tenant-4 · Nav-Badge-Cacheschlüssel ohne Mandantenbindung ────────

    /**
     * Der Zähler der Betriebsaufgaben wird zwischengespeichert. Ohne die
     * Organisation im Schlüssel sähe der nächste Mandant die Zahl des
     * vorigen.
     */
    public function test_the_operations_nav_badge_cache_key_is_bound_to_the_organization(): void {
        $first = OperationsTask::navBadgeCacheKey(1);
        $second = OperationsTask::navBadgeCacheKey(2);

        $this->assertNotSame($first, $second, 'Zwei Mandanten teilen sich denselben Cache-Schlüssel.');
        $this->assertStringContainsString('1', $first);
        $this->assertStringContainsString('2', $second);
    }

    // ── crypto-7 · zu kurzer Modulschlüssel ──────────────────────────────

    /**
     * Ein zu kurzer Schlüssel ist kein Schlüssel. Vor der Korrektur nahm die
     * Umschlagverschlüsselung ihn an und arbeitete mit verringerter Stärke
     * weiter.
     */
    public function test_a_short_module_key_is_rejected(): void {
        $this->assertFalse(EnvelopeCrypto::isUsableKey(''), 'Der leere Schlüssel gilt als verwendbar.');
        $this->assertFalse(EnvelopeCrypto::isUsableKey('viel-zu-kurz'), 'Ein kurzer Rohschlüssel gilt als verwendbar.');
        $this->assertFalse(
            EnvelopeCrypto::isUsableKey(base64_encode(random_bytes(16))),
            'Ein base64-kodierter 16-Byte-Schlüssel gilt als verwendbar.',
        );
    }

    /** Gegenprobe: die Sperre darf den gültigen Schlüssel nicht treffen. */
    public function test_a_full_length_module_key_is_accepted(): void {
        $this->assertTrue(EnvelopeCrypto::isUsableKey(base64_encode(random_bytes(32))));
    }

    // ── crypto-4 · FTP-Katalogabruf ohne TLS ─────────────────────────────

    /**
     * Ohne TLS gehen die hinterlegten Lieferanten-Zugangsdaten im Klartext
     * über die Leitung. Klartext nur noch, wenn der Betreiber ihn bewusst
     * freischaltet — geprüft an den Verbindungsvorgaben, ohne einen
     * FTP-Server zu betreiben.
     */
    public function test_the_catalog_ftp_connection_requires_tls_by_default(): void {
        $this->assertTrue($this->ftpOptions()->ssl(), 'Der FTP-Abruf liefe ohne TLS.');
    }

    /** Sicherheitsaudit 2026-09-17 (ssrf-3): der Datenkanal folgt nicht der PASV-Adresse des Servers. */
    public function test_the_catalog_ftp_connection_ignores_the_passive_address(): void {
        $this->assertTrue($this->ftpOptions()->ignorePassiveAddress());
    }

    /** Gegenprobe: der bewusste Schalter des Betreibers wirkt. */
    public function test_the_operator_can_still_allow_plaintext_ftp_on_purpose(): void {
        config(['procurement.ftp_allow_plaintext' => true]);

        $this->assertFalse($this->ftpOptions()->ssl());
    }

    private function ftpOptions(): \League\Flysystem\Ftp\FtpConnectionOptions {
        $source = new \App\Models\SupplierCatalogSource;
        $source->forceFill([
            'remote_host' => 'ftp.lieferant.example',
            'remote_username' => 'katalog',
            'remote_password' => 'geheim',
            'remote_path' => '/katalog.csv',
        ]);

        $method = new \ReflectionMethod(\App\Services\Procurement\CatalogFetchService::class, 'ftpOptions');

        return $method->invoke(app(\App\Services\Procurement\CatalogFetchService::class), $source);
    }

    // ── api-6 / tenant-5 · Wächter-Eingang ohne Mandantensperre ──────────

    /**
     * `EnforceTenantStatus` greift nur bei angemeldeten Zugriffen — ein
     * Wächter-Gerät hat keine Sitzung. Ohne diese Prüfung scannte ein
     * gesperrter Mandant unbegrenzt weiter.
     */
    public function test_the_patrol_scan_refuses_a_blocked_tenant(): void {
        $token = $this->patrolToken();
        $this->organization->forceFill(['is_active' => false])->save();

        $this->postJson("/api/patrol/scan/{$token}", ['checkpoint' => 'irgendwas'])
            ->assertStatus(423)
            ->assertJsonPath('error', 'tenant_blocked');
    }

    /** Im Wartungsfenster mit gesperrtem Eingang antwortet der Scan mit 503. */
    public function test_the_patrol_scan_refuses_during_maintenance(): void {
        $token = $this->patrolToken();
        $settings = (array) ($this->organization->settings ?? []);
        $settings['maintenance'] = ['enabled' => true, 'block_ingest' => true, 'until' => null];
        $this->organization->forceFill(['settings' => $settings])->save();

        $this->postJson("/api/patrol/scan/{$token}", ['checkpoint' => 'irgendwas'])
            ->assertStatus(503)
            ->assertJsonPath('error', 'maintenance');
    }

    private function patrolToken(): string {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        [, $plain] = \App\Models\Location\LocationDeviceToken::issue($user, 'Wächtergerät');

        return $plain;
    }

    // ── config-4 · CORS für jede Herkunft offen ──────────────────────────

    /**
     * Die Freigabe stand auf `*`: jede fremde Seite durfte die Schnittstelle
     * im Browser des angemeldeten Nutzers ansprechen.
     */
    public function test_cors_does_not_allow_every_origin(): void {
        $origins = (array) config('cors.allowed_origins');

        $this->assertNotContains('*', $origins, 'CORS erlaubt jede Herkunft.');
        $this->assertNotSame([], $origins, 'CORS-Herkünfte sind leer — die Konfiguration greift nicht.');
    }
}
