<?php
/*
 * Created on   : Tue Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceTemplateConsolidationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\DocumentDesign;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\{Customer, Invoice, Organization, User};
use App\Models\DocumentDesign\DocumentRenderProfile;
use App\Services\DocumentDesign\{DocumentDesignRenderer, RenderProfileService};
use App\Services\Invoicing\InvoicePdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

/**
 * MVP-651 (Issue #84): `invoice_templates` gehen im Profilmodell auf —
 * Kopf-/Fußtexte als versionierte, einfrierbare Sektion, Kunden-Sonderdesigns
 * als Profile mit Kundenzeiger, Akzentfarbe aus dem Tabellenstil.
 */
class InvoiceTemplateConsolidationTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id]);
    }

    private function service(): RenderProfileService {
        return app(RenderProfileService::class);
    }

    /**
     * @param  array<int, string>  $kinds
     * @param  array<string, mixed>  $draft
     * @return array{0: DocumentRenderProfile, 1: int} Profil + aktive Versions-ID
     */
    private function activeProfile(string $name, array $kinds = [], bool $default = false, array $draft = []): array {
        $profile = $this->service()->createProfile($this->org, $name, $kinds, $default, $this->admin);
        $version = $profile->versions()->firstOrFail();
        if ($draft !== []) {
            $this->service()->updateDraft($version, $draft, $this->admin);
        }
        $this->assertTrue($this->service()->activate($version->refresh(), $this->admin)->ok());

        return [$profile->refresh(), (int) $profile->active_version_id];
    }

    public function test_migration_converts_templates_into_profiles_and_repoints_customers(): void {
        // Basisdesign vorhanden → migrierte Profile erben (nur Text-/Farb-Override).
        $this->activeProfile('CI-Basisdesign', [], default: true);

        $defaultTemplateId = DB::table('invoice_templates')->insertGetId([
            'organization_id' => $this->org->id,
            'name' => 'Standard-Vorlage',
            'slug' => 'standard',
            'header_text' => 'Vielen Dank für Ihren Auftrag!',
            'footer_text' => 'Zahlbar innerhalb von 14 Tagen.',
            'accent_color' => '1d4ed8',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $customerTemplateId = DB::table('invoice_templates')->insertGetId([
            'organization_id' => $this->org->id,
            'name' => 'Sondervorlage Beispiel GmbH',
            'slug' => 'beispiel',
            'header_text' => null,
            'footer_text' => 'Rahmenvertrag 42 gilt.',
            'accent_color' => null,
            'is_default' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('customers')->where('id', $this->customer->id)->update(['invoice_template_id' => $customerTemplateId]);

        $migration = require database_path('migrations/2027_01_12_100000_consolidate_invoice_templates_into_render_profiles.php');
        $migration->migrateTemplates();

        // Default-Template → org-weite Vertriebs-Familienvariante mit Texten.
        $defaultProfile = DocumentRenderProfile::query()->where('name', 'Standard-Vorlage')->firstOrFail();
        $this->assertFalse((bool) $defaultProfile->is_customer_specific);
        $this->assertSame('sales', $defaultProfile->document_family?->value);
        $payload = app(DocumentDesignRenderer::class)->payloadFor($this->org, RenderDocumentKind::Invoice);
        $this->assertNotNull($payload);
        $this->assertSame('Vielen Dank für Ihren Auftrag!', $payload['content_texts']['header_text'] ?? null);
        $this->assertSame('#1d4ed8', $payload['table_style']['overrides']['accent_color'] ?? null, 'Template-Akzentfarbe wandert in den Tabellenstil.');
        $this->assertSame(20.0, (float) $payload['layout']['content_first']['top'], 'Layout kommt aus dem geerbten Basisdesign.');

        // Kunden-Template → Sonderprofil + Kundenzeiger; gewinnt für den Kunden.
        $customerProfile = DocumentRenderProfile::query()->where('name', 'Sondervorlage Beispiel GmbH')->firstOrFail();
        $this->assertTrue((bool) $customerProfile->is_customer_specific);
        $this->assertSame((int) $customerProfile->id, (int) $this->customer->refresh()->document_render_profile_id);

        $customerPayload = app(DocumentDesignRenderer::class)->payloadFor($this->org, RenderDocumentKind::Invoice, (int) $this->customer->id);
        $this->assertSame('Rahmenvertrag 42 gilt.', $customerPayload['content_texts']['footer_text'] ?? null);

        // Ohne Kundenzeiger bleibt die org-weite Auflösung unberührt vom Sonderprofil.
        $other = Customer::factory()->create(['organization_id' => $this->org->id]);
        $otherPayload = app(DocumentDesignRenderer::class)->payloadFor($this->org, RenderDocumentKind::Invoice, (int) $other->id);
        $this->assertSame('Vielen Dank für Ihren Auftrag!', $otherPayload['content_texts']['header_text'] ?? null);
    }

    public function test_content_texts_render_in_invoice_view_and_freeze_with_snapshot(): void {
        [$profile] = $this->activeProfile('CI-Basisdesign', [], default: true, draft: [
            'content_texts' => ['header_text' => 'Kopfzeile V1', 'footer_text' => 'Fußzeile V1'],
        ]);

        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-0200',
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
        ]);

        $html = view('invoices.pdf', app(InvoicePdfRenderer::class)->viewData($invoice))->render();
        $this->assertStringContainsString('Kopfzeile V1', $html);
        $this->assertStringContainsString('Fußzeile V1', $html);

        // Finalisieren friert die Texte ein …
        $invoice->freezeParties();
        $invoice->save();

        // … eine neue Profilversion mit anderen Texten wirkt nicht zurück.
        $draft = $this->service()->newDraftFrom($profile->versions()->firstOrFail()->refresh(), $this->admin);
        $this->service()->updateDraft($draft, ['content_texts' => ['header_text' => 'Kopfzeile V2', 'footer_text' => null]], $this->admin);
        $this->assertTrue($this->service()->activate($draft->refresh(), $this->admin)->ok());

        $frozenHtml = view('invoices.pdf', app(InvoicePdfRenderer::class)->viewData($invoice->refresh()))->render();
        $this->assertStringContainsString('Kopfzeile V1', $frozenHtml, 'Finalisierte Belege behalten ihre Texte.');
        $this->assertStringNotContainsString('Kopfzeile V2', $frozenHtml);
    }

    public function test_customer_design_profile_endpoint_assigns_and_clears(): void {
        [$profile] = $this->activeProfile('Sonderdesign', []);

        $this->actingAs($this->admin)
            ->post(route('customers.design-profile', $this->customer), ['profile' => $profile->sqid])
            ->assertRedirect();
        $this->assertSame((int) $profile->id, (int) $this->customer->refresh()->document_render_profile_id);

        $this->actingAs($this->admin)
            ->post(route('customers.design-profile', $this->customer), ['profile' => ''])
            ->assertRedirect();
        $this->assertNull($this->customer->refresh()->document_render_profile_id);
    }

    public function test_customer_design_profile_endpoint_denies_foreign_profile_and_unprivileged_user(): void {
        $otherOrg = Organization::factory()->create();
        $foreignProfile = DocumentRenderProfile::query()->create([
            'organization_id' => $otherOrg->id,
            'name' => 'Fremd',
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin)
            ->post(route('customers.design-profile', $this->customer), ['profile' => $foreignProfile->sqid])
            ->assertNotFound();

        $regular = User::factory()->user()->create(['organization_id' => $this->org->id]);
        $this->actingAs($regular)
            ->post(route('customers.design-profile', $this->customer), ['profile' => ''])
            ->assertForbidden();
    }
}
