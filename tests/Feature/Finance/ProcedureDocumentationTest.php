<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureDocumentationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\ProcedureDocumentationStatus;
use App\Enums\User\Permission;
use App\Models\Backup\BackupTargetConnection;
use App\Models\Finance\ProcedureDocumentation;
use App\Models\Integration\WebhookEndpoint;
use App\Models\{Organization, PluginSetting, User};
use App\Services\Finance\ProcedureDocumentation\{ProcedureDocumentationBuilder, ProcedureDocumentationService};
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 134, MVP-699: GoBD-Verfahrensdokumentation. Builder liefert alle
 * Abschnitte ohne Secrets, Publish friert Snapshot + PDF mit SHA-256 ein,
 * Statusmaschine/Unveränderlichkeit, Tenancy, Recht `finance.gobd.export`.
 */
final class ProcedureDocumentationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const SECTION_KEYS = ['system', 'accounting', 'numbering', 'roles', 'immutability', 'backup', 'interfaces', 'retention'];

    private ProcedureDocumentationService $service;

    private ProcedureDocumentationBuilder $builder;

    private User $accountant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        Storage::fake('local');
        $this->service = app(ProcedureDocumentationService::class);
        $this->builder = app(ProcedureDocumentationBuilder::class);
        $this->accountant = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->accountant->givePermissionTo(Permission::FinanceGobdExport->value);
    }

    /** @return array<string, mixed> */
    private function seedSecrets(): array {
        $secrets = [
            'access' => 'SECRET-ACCESS-TOKEN-9f1e',
            'refresh' => 'SECRET-REFRESH-TOKEN-7c2d',
            'api_key' => 'PLUGIN-API-KEY-31337',
            'url_token' => 'URL-TOKEN-4242',
            'webhook' => 'WEBHOOK-SECRET-5555',
        ];
        BackupTargetConnection::factory()->create([
            'name' => 'Dropbox Firmenkonto',
            'access_token' => $secrets['access'],
            'refresh_token' => $secrets['refresh'],
        ]);
        PluginSetting::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'lexoffice',
            'enabled' => true,
            'settings' => ['api_key' => $secrets['api_key']],
        ]);
        WebhookEndpoint::factory()->create([
            'organization_id' => $this->organization->id,
            'label' => 'Buchhaltungs-Hook',
            'url' => 'https://hooks.example.test/wd?token=' . $secrets['url_token'],
            'secret' => $secrets['webhook'],
        ]);

        return $secrets;
    }

    /** @param array<array-key, mixed> $node */
    private function assertNoSecretKeys(array $node, string $path = ''): void {
        foreach ($node as $key => $value) {
            $current = $path === '' ? (string) $key : $path . '.' . $key;
            if (is_string($key)) {
                $this->assertDoesNotMatchRegularExpression('/secret|token|passw|api[_-]?key|private[_-]?key|credential|envelope/i', $key, 'Secret-Schlüssel in ' . $current);
            }
            if (is_array($value)) {
                $this->assertNoSecretKeys($value, $current);
            }
        }
    }

    // ── Builder ──────────────────────────────────────────────────────────

    public function test_builder_delivers_all_sections_without_secrets(): void {
        $secrets = $this->seedSecrets();

        $payload = $this->builder->build($this->organization);
        $json = $this->builder->toJson($payload);

        $this->assertSame(ProcedureDocumentationBuilder::SCHEMA, $payload['schema']);
        $this->assertFalse($payload['chains_verified']);
        $this->assertSame(self::SECTION_KEYS, array_column($payload['sections'], 'key'));
        foreach ($payload['sections'] as $section) {
            $this->assertNotSame('', $section['title']);
        }
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
        $this->assertNoSecretKeys($payload);

        // Nicht-geheime Anzeigewerte sind da: Backup-Ziel und Webhook ohne Query-String.
        $this->assertStringContainsString('Dropbox Firmenkonto', $json);
        $this->assertStringContainsString('https://hooks.example.test/wd', $json);
        $this->assertStringNotContainsString('?token', $json);
        // Ketten in der Vorschau nicht nachgerechnet.
        $this->assertStringContainsString(__('procedure-documentation.immutability.not_verified'), $json);
    }

    // ── Lebenszyklus ─────────────────────────────────────────────────────

    public function test_draft_gets_running_version_and_only_one_draft_exists(): void {
        $draft = $this->service->createDraft($this->organization, $this->accountant, ['general_description' => 'Handwerksbetrieb, 12 Mitarbeiter.']);

        $this->assertSame(1, $draft->version);
        $this->assertSame(ProcedureDocumentationStatus::Draft, $draft->status);
        $this->assertSame('Handwerksbetrieb, 12 Mitarbeiter.', $draft->general_description);

        $this->expectException(ValidationException::class);
        $this->service->createDraft($this->organization, $this->accountant);
    }

    public function test_publish_freezes_snapshot_and_pdf_with_hashes(): void {
        $draft = $this->service->createDraft($this->organization, $this->accountant, [
            'general_description' => 'Allgemein',
            'operational_documentation' => 'Buchhaltung bucht, GF gibt frei.',
        ]);

        $published = $this->service->publish($draft, $this->accountant);

        $this->assertTrue($published->isPublished());
        $this->assertNotNull($published->published_at);
        $this->assertSame($this->accountant->id, $published->published_by);

        $snapshot = (array) $published->snapshot;
        $this->assertTrue($snapshot['chains_verified']);
        $this->assertSame(self::SECTION_KEYS, array_column($snapshot['sections'], 'key'));
        $this->assertSame(CryptoHelper::hash($this->builder->toJson($snapshot)), $published->snapshot_sha256);
        $json = $this->builder->toJson($snapshot);
        $this->assertStringContainsString('audit_logs', $json);
        $okPattern = '/' . str_replace('\\:count', '\\d+', preg_quote((string) __('procedure-documentation.immutability.verified_ok', ['count' => ':count']), '/')) . '/u';
        $this->assertMatchesRegularExpression($okPattern, $json, 'Nachgerechnete Ketten stehen im Snapshot.');
        $this->assertStringNotContainsString(__('procedure-documentation.immutability.not_verified'), $json);

        $pdf = $this->service->pdfBytes($published);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(CryptoHelper::hash($pdf), $published->pdf_sha256);
        Storage::disk('local')->assertExists((string) $published->pdf_path);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => ProcedureDocumentation::class,
            'auditable_id' => $published->id,
            'event' => 'procedure_documentation.published',
        ]);
        $this->artisan('audit:verify', ['--chain' => 'audit_logs'])->assertExitCode(0);
    }

    public function test_published_version_is_immutable_and_status_machine_holds(): void {
        $published = $this->service->publish($this->service->createDraft($this->organization, $this->accountant), $this->accountant);

        try {
            $published->forceFill(['general_description' => 'nachträglich'])->save();
            $this->fail('Update einer veröffentlichten Version muss werfen.');
        } catch (RuntimeException) {
            $this->assertSame(null, $published->refresh()->general_description);
        }

        try {
            $published->delete();
            $this->fail('Löschen einer veröffentlichten Version muss werfen.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('procedure_documentations', ['id' => $published->id]);
        }

        try {
            $this->service->update($published, ['general_description' => 'x']);
            $this->fail('Service-Update einer veröffentlichten Version muss werfen.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(RuntimeException::class);
        $this->service->publish($published, $this->accountant);
    }

    public function test_next_draft_is_prefilled_from_last_published_version(): void {
        $first = $this->service->createDraft($this->organization, $this->accountant, ['general_description' => 'Stand 2026', 'change_history' => 'v1']);
        $this->service->publish($first, $this->accountant);

        $second = $this->service->createDraft($this->organization, $this->accountant, ['change_history' => 'v2: Lexoffice abgelöst']);

        $this->assertSame(2, $second->version);
        $this->assertSame('Stand 2026', $second->general_description);
        $this->assertSame('v2: Lexoffice abgelöst', $second->change_history);
        $this->assertTrue($second->isEditable());
    }

    // ── Web / Recht / Tenancy ────────────────────────────────────────────

    public function test_pages_require_gobd_export_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('finance.procedure-documentation.index'))->assertForbidden();
        $this->actingAs($stranger)->post(route('finance.procedure-documentation.store'))->assertForbidden();
        $this->actingAs($this->accountant)->get(route('finance.procedure-documentation.index'))->assertOk()->assertViewIs('finance.procedure-documentation.index');
    }

    public function test_web_flow_create_edit_publish_download(): void {
        $this->actingAs($this->accountant)->post(route('finance.procedure-documentation.store'))->assertRedirect();
        /** @var ProcedureDocumentation $draft */
        $draft = ProcedureDocumentation::query()->where('organization_id', $this->organization->id)->firstOrFail();

        $this->actingAs($this->accountant)->get(route('finance.procedure-documentation.show', $draft))
            ->assertOk()
            ->assertSee(__('procedure-documentation.generated.preview_hint'));
        $this->actingAs($this->accountant)->get(route('finance.procedure-documentation.edit', $draft))
            ->assertOk()->assertViewIs('finance.procedure-documentation._form_dialog');

        $this->actingAs($this->accountant)->put(route('finance.procedure-documentation.update', $draft), [
            'general_description' => 'Aus dem Dialog',
            'user_documentation' => 'Siehe Hilfe-Themen.',
        ])->assertRedirect(route('finance.procedure-documentation.show', $draft));
        $this->assertSame('Aus dem Dialog', $draft->refresh()->general_description);

        $this->actingAs($this->accountant)->get(route('finance.procedure-documentation.download', $draft))->assertNotFound();

        $this->actingAs($this->accountant)->post(route('finance.procedure-documentation.publish', $draft))
            ->assertRedirect(route('finance.procedure-documentation.show', $draft));
        $this->assertTrue($draft->refresh()->isPublished());

        $response = $this->actingAs($this->accountant)->get(route('finance.procedure-documentation.download', $draft));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertSame(CryptoHelper::hash((string) $response->getContent()), $draft->pdf_sha256);

        $this->actingAs($this->accountant)->get(route('finance.procedure-documentation.index'))
            ->assertOk()->assertSee($draft->displayVersion());
    }

    public function test_download_detects_tampered_pdf_file(): void {
        $published = $this->service->publish($this->service->createDraft($this->organization, $this->accountant), $this->accountant);
        Storage::disk('local')->put((string) $published->pdf_path, '%PDF-manipuliert');

        $this->expectException(RuntimeException::class);
        $this->service->pdfBytes($published);
    }

    public function test_documents_respect_tenant_boundary(): void {
        $otherOrg = Organization::factory()->create();
        $foreign = $this->service->createDraft($otherOrg, null);

        $this->actingAs($this->accountant)->get(route('finance.procedure-documentation.show', $foreign))->assertNotFound();
        $this->actingAs($this->accountant)->put(route('finance.procedure-documentation.update', $foreign), ['general_description' => 'x'])->assertNotFound();
        $this->actingAs($this->accountant)->post(route('finance.procedure-documentation.publish', $foreign))->assertNotFound();

        $own = $this->service->createDraft($this->organization, $this->accountant);
        $this->assertSame(1, $own->version, 'Versionen laufen je Organisation.');
        $this->assertSame(1, $foreign->version);
    }
}
