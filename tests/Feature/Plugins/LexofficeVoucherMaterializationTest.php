<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeVoucherMaterializationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins;

use App\Models\{LexofficeVoucher, Organization};
use App\Plugins\Lexoffice\{LexofficeVoucherFileMissingException, LexofficeVoucherFileService, LexofficeVoucherSync};
use App\Plugins\Support\PluginApiClient;
use GuzzleHttp\{Client, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Buchhaltungswechsel-Folgeschnitte (Feature 110, MVP-690 — Vollscan G3):
 * Belegbilder lokal sichern (GoBD nach Vertragsende), leere Sync-Antwort
 * archiviert den Spiegel nicht, Abschluss blockiert ohne Materialisierung,
 * abgeschlossener Wechsel friert den Sync ein.
 */
final class LexofficeVoucherMaterializationTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
    }

    /** @param array<string, mixed> $attributes */
    private function voucher(array $attributes = []): LexofficeVoucher {
        return LexofficeVoucher::query()->create(array_merge([
            'organization_id' => $this->org->id,
            'external_id' => 'lx-' . fake()->unique()->uuid(),
            'voucher_type' => 'purchaseinvoice',
            'voucher_number' => 'LX-1',
            'currency' => 'EUR',
            'archived' => false,
            'payload' => ['files' => ['file-1']],
        ], $attributes));
    }

    private function service(MockHandler $mock): LexofficeVoucherFileService {
        $service = new LexofficeVoucherFileService('key', 'https://api.lexoffice.test/v1');
        // Mock-Transport über die private PluginApiClient-Naht (C10-Muster).
        $client = new PluginApiClient('lexoffice', 'https://api.lexoffice.test/v1', new Client(['handler' => HandlerStack::create($mock)]));
        \Closure::bind(function () use ($client): void {
            $this->api = $client;
        }, $service, LexofficeVoucherFileService::class)();

        return $service;
    }

    public function test_materialize_stores_the_file_locally_and_serves_it_without_api(): void {
        $voucher = $this->voucher();
        // Generischer Beleg: (1) /vouchers/{id} liefert das files-Array,
        // (2) /files/{id} das Belegbild.
        $service = $this->service(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['files' => ['file-1']])),
            new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-fake'),
        ]));

        $this->assertTrue($service->materialize($voucher));
        $voucher->refresh();
        $this->assertNotNull($voucher->file_path);
        Storage::disk('local')->assertExists((string) $voucher->file_path);

        // Idempotent: zweiter Aufruf braucht keinen weiteren API-Call
        // (der MockHandler hätte keine Antwort mehr).
        $this->assertTrue($service->materialize($voucher));

        $local = $service->localFile($voucher);
        $this->assertSame('%PDF-fake', $local['body'] ?? null);
        $this->assertSame('application/pdf', $local['content_type'] ?? null);
    }

    public function test_missing_file_marks_the_voucher_checked_without_local_file(): void {
        $voucher = $this->voucher();
        $service = $this->service(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['files' => ['file-1']])),
            new Response(404, ['Content-Type' => 'application/json'], '{"message":"not found"}'),
        ]));

        $this->assertFalse($service->materialize($voucher));
        $voucher->refresh();
        $this->assertNotNull($voucher->file_materialized_at);
        $this->assertNull($voucher->file_path);
    }

    /**
     * Vorher entschied ein Textvergleich („404"/„file" in der Meldung) — und
     * „Lexoffice file fetch failed: 401" enthielt „file": Auth- und Serverfehler
     * wurden dauerhaft als „geprüft, kein Belegbild" verbucht.
     */
    public function test_auth_or_server_errors_are_not_mistaken_for_a_missing_file(): void {
        $voucher = $this->voucher();
        $service = $this->service(new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['files' => ['file-1']])),
            new Response(401, ['Content-Type' => 'application/json'], '{"message":"unauthorized"}'),
        ]));

        try {
            $service->materialize($voucher);
            $this->fail('401 muss nach oben, nicht als „kein Belegbild" enden');
        } catch (\RuntimeException $e) {
            $this->assertNotInstanceOf(LexofficeVoucherFileMissingException::class, $e);
        }
        $this->assertNull($voucher->fresh()?->file_materialized_at);
    }

    public function test_failed_file_reference_lookup_is_not_a_missing_file(): void {
        // 401 bereits beim Nachschlagen der Datei-Referenz (/vouchers/{id}):
        // vorher lief das als leere Referenz in „kein Belegbild".
        $voucher = $this->voucher();
        $service = $this->service(new MockHandler([
            new Response(401, ['Content-Type' => 'application/json'], '{"message":"unauthorized"}'),
        ]));

        try {
            $service->materialize($voucher);
            $this->fail('401 beim Nachschlagen muss nach oben');
        } catch (\RuntimeException $e) {
            $this->assertNotInstanceOf(LexofficeVoucherFileMissingException::class, $e);
        }
        $this->assertNull($voucher->fresh()?->file_materialized_at);
    }

    public function test_empty_seen_list_does_not_archive_the_mirror(): void {
        $voucher = $this->voucher();
        $sync = new LexofficeVoucherSync('key', 'https://api.lexoffice.test/v1');

        $this->assertSame(0, $sync->archiveMissing($this->org, []));
        $this->assertFalse((bool) $voucher->fresh()?->archived);

        // Mit nicht-leerer seen-Menge greift die Archivierung normal.
        $this->assertSame(1, $sync->archiveMissing($this->org, ['andere-id']));
        $this->assertTrue((bool) $voucher->fresh()?->archived);
    }

    public function test_completed_migration_freezes_the_sync(): void {
        \App\Models\Migration\AccountingMigrationRun::factory()->create([
            'organization_id' => $this->org->id,
            'source_plugin' => 'lexoffice',
            'status' => \App\Enums\Migration\AccountingMigrationStatus::Completed->value,
        ]);

        $result = (new LexofficeVoucherSync('key', 'https://api.lexoffice.test/v1'))->sync($this->org);

        $this->assertTrue((bool) ($result['frozen'] ?? false));
        $this->assertSame(0, $result['created']);
    }

    /**
     * Gate-Abdeckung fuer den Plugin-Befehl (Vollscan 2026-09-15, `C1-10`):
     * `lexoffice:materialize-voucher-files` ist Pflichtschritt vor dem Abschluss
     * eines Buchhaltungswechsels und war bis dahin von keinem Test beruehrt.
     * Ohne hinterlegten Zugang fasst der Lauf keinen Beleg an und endet sauber.
     */
    public function test_materialize_command_skips_organizations_without_configuration(): void {
        $voucher = $this->voucher();

        $this->artisan('lexoffice:materialize-voucher-files')
            ->expectsOutputToContain('nicht konfiguriert')
            ->assertExitCode(0);

        $this->assertNull($voucher->refresh()->file_materialized_at);
    }

    public function test_completion_is_blocked_until_files_are_materialized(): void {
        $this->voucher();
        $run = \App\Models\Migration\AccountingMigrationRun::factory()->create([
            'organization_id' => $this->org->id,
            'source_plugin' => 'lexoffice',
        ]);

        $blockers = app(\App\Services\AccountingMigration\AccountingMigrationService::class)->completionBlockers($run);

        $this->assertNotEmpty(array_filter($blockers, fn (string $b): bool => str_contains($b, 'materialize-voucher-files')));
    }
}
