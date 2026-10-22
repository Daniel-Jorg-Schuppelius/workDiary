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

use App\Models\Platform\Organization;
use App\Plugins\Lexoffice\Exceptions\LexofficeVoucherFileMissingException;
use App\Plugins\Lexoffice\Models\LexofficeVoucher;
use App\Plugins\Lexoffice\Services\{LexofficeVoucherFileService, LexofficeVoucherSync};
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithPluginSecrets;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Buchhaltungswechsel-Folgeschnitte (Feature 110, MVP-690 — Vollscan G3):
 * Belegbilder lokal sichern (GoBD nach Vertragsende), leere Sync-Antwort
 * archiviert den Spiegel nicht, Abschluss blockiert ohne Materialisierung,
 * abgeschlossener Wechsel friert den Sync ein.
 */
final class LexofficeVoucherMaterializationTest extends TestCase {
    use RefreshDatabase;
    use WithPluginSecrets;

    private Organization $org;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->pluginSecret('lexoffice', ['api_key' => 'test-key']);
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

    private FakePluginHttp $http;

    /** Antworten in Aufrufreihenfolge; die letzte wiederholt sich. */
    private function service(Response ...$responses): LexofficeVoucherFileService {
        $this->http = FakePluginHttp::fake(['https://api.lexware.io/*' => $responses]);

        return new LexofficeVoucherFileService($this->org->id);
    }

    /** @return list<string> */
    private function sent(): array {
        return array_map(static fn (array $entry): string => $entry['request']->getMethod() . ' ' . $entry['request']->getUri() . ' ' . $entry['request']->getHeaderLine('Accept'), $this->http->recorded());
    }

    public function test_materialize_stores_the_file_locally_and_serves_it_without_api(): void {
        $voucher = $this->voucher();
        // Generischer Beleg: (1) /vouchers/{id} liefert das files-Array,
        // (2) /files/{id} das Belegbild.
        $service = $this->service(
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['files' => ['0b6f1c2e-4a5d-4e8f-9a1b-2c3d4e5f6a7b']])),
            new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-fake'),
        );

        $this->assertTrue($service->materialize($voucher));
        $voucher->refresh();
        $this->assertNotNull($voucher->file_path);
        Storage::disk('local')->assertExists((string) $voucher->file_path);
        // VouchersEndpoint::get(), dann FilesEndpoint::downloadContent() mit der Accept-Liste der Belegbilder.
        $this->assertSame([
            'GET https://api.lexware.io/v1/vouchers/' . $voucher->external_id . ' application/json',
            'GET https://api.lexware.io/v1/files/0b6f1c2e-4a5d-4e8f-9a1b-2c3d4e5f6a7b application/pdf, image/png, image/jpeg, image/gif, image/tiff, application/xml, */*',
        ], $this->sent());

        // Idempotent: zweiter Aufruf braucht keinen weiteren API-Call.
        $this->assertTrue($service->materialize($voucher));
        $this->http->assertSentCount(2);

        $local = $service->localFile($voucher);
        $this->assertSame('%PDF-fake', $local['body'] ?? null);
        $this->assertSame('application/pdf', $local['content_type'] ?? null);
    }

    public function test_missing_file_marks_the_voucher_checked_without_local_file(): void {
        $voucher = $this->voucher();
        $service = $this->service(
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['files' => ['0b6f1c2e-4a5d-4e8f-9a1b-2c3d4e5f6a7b']])),
            new Response(404, ['Content-Type' => 'application/json'], '{"message":"not found"}'),
        );

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
        $service = $this->service(
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['files' => ['0b6f1c2e-4a5d-4e8f-9a1b-2c3d4e5f6a7b']])),
            new Response(401, ['Content-Type' => 'application/json'], '{"message":"unauthorized"}'),
        );

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
        $service = $this->service(
            new Response(401, ['Content-Type' => 'application/json'], '{"message":"unauthorized"}'),
        );

        try {
            $service->materialize($voucher);
            $this->fail('401 beim Nachschlagen muss nach oben');
        } catch (\RuntimeException $e) {
            $this->assertNotInstanceOf(LexofficeVoucherFileMissingException::class, $e);
        }
        $this->assertNull($voucher->fresh()?->file_materialized_at);
    }

    /**
     * Verkaufsbeleg: PDF über `/{beleg}/{id}/file` (`/document` ist laut Referenz veraltet); ein Entwurf
     * (409) fällt auf `/vouchers/{id}` zurück, ohne Belegbild dort gibt es keine Datei.
     */
    public function test_sales_document_is_fetched_through_the_file_endpoint(): void {
        $invoice = $this->voucher(['voucher_type' => 'salesinvoice']);
        $draft = $this->voucher(['voucher_type' => 'salescreditnote']);
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/invoices/' . $invoice->external_id . '/file' => new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1'),
            'https://api.lexware.io/v1/credit-notes/' . $draft->external_id . '/file' => FakePluginHttp::response(['message' => 'draft'], 409),
            'https://api.lexware.io/v1/vouchers/' . $draft->external_id => FakePluginHttp::response(['files' => []]),
        ]);
        $service = new LexofficeVoucherFileService($this->org->id);

        $file = $service->download($invoice);
        $this->assertSame(['body' => '%PDF-1', 'content_type' => 'application/pdf', 'extension' => 'pdf'], $file);
        $fake->assertSent(static fn (RequestInterface $request): bool => str_ends_with((string) $request->getUri(), '/invoices/' . $invoice->external_id . '/file')
            && $request->getHeaderLine('Accept') === 'application/pdf');
        $fake->assertNotSent(static fn (RequestInterface $request): bool => str_contains((string) $request->getUri(), '/document'));

        $this->expectException(LexofficeVoucherFileMissingException::class);
        $service->download($draft);
    }

    /**
     * Der Antwort-Body geht an den Browser (S-10): einer Weiterleitung folgt der
     * Dateiabruf nicht, auch nicht über den injizierten Transport des SDK-Clients.
     */
    public function test_file_download_does_not_follow_redirects(): void {
        $voucher = $this->voucher();
        $service = $this->service(
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['files' => ['0b6f1c2e-4a5d-4e8f-9a1b-2c3d4e5f6a7b']])),
            new Response(302, ['Location' => 'https://elsewhere.example/file.pdf']),
        );

        try {
            $service->download($voucher);
            $this->fail('Eine Weiterleitung muss als Fehler enden.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Lexoffice file fetch failed: 302', $e->getMessage());
        }
        $this->http->assertSentCount(2);
        $this->http->assertNotSent(static fn (RequestInterface $request): bool => $request->getUri()->getHost() === 'elsewhere.example');
        foreach ($this->http->recorded() as $entry) {
            $this->assertFalse($entry['options']['allow_redirects']);
        }
    }

    public function test_empty_seen_list_does_not_archive_the_mirror(): void {
        $voucher = $this->voucher(['contact_external_id' => 'kontakt-1']);
        $sync = new LexofficeVoucherSync($this->org->id);

        $this->assertSame(0, $sync->archiveMissing($this->org, [], ['kontakt-1']));
        $this->assertFalse((bool) $voucher->fresh()?->archived);

        // Mit nicht-leerer seen-Menge greift die Archivierung normal.
        $this->assertSame(1, $sync->archiveMissing($this->org, ['andere-id'], ['kontakt-1']));
        $this->assertTrue((bool) $voucher->fresh()?->archived);
    }

    /** MVP-1112: Belege anderer Kontakte und des Sammelkontakts kamen in der Abfrage nicht vor. */
    public function test_only_vouchers_of_the_queried_contacts_are_archived(): void {
        $other = $this->voucher(['contact_external_id' => 'kontakt-2']);
        $collective = $this->voucher(['contact_external_id' => null]);
        $sync = new LexofficeVoucherSync($this->org->id);

        $this->assertSame(0, $sync->archiveMissing($this->org, ['gesehen-1'], ['kontakt-1']));
        $this->assertFalse((bool) $other->fresh()?->archived);
        $this->assertFalse((bool) $collective->fresh()?->archived);
    }

    public function test_completed_migration_freezes_the_sync(): void {
        \App\Models\Migration\AccountingMigrationRun::factory()->create([
            'organization_id' => $this->org->id,
            'source_plugin' => 'lexoffice',
            'status' => \App\Enums\Migration\AccountingMigrationStatus::Completed->value,
        ]);

        $result = (new LexofficeVoucherSync($this->org->id))->sync($this->org);

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
        \App\Models\Platform\PluginSetting::query()->withoutGlobalScopes()->where('organization_id', $this->org->id)->delete();

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
