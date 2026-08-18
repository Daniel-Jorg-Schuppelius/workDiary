<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CiBaseDesignTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\DocumentDesign;

use App\Enums\DocumentDesign\{RenderDocumentFamily, RenderDocumentKind};
use App\Models\DocumentDesign\DocumentRenderProfile;
use App\Models\{Organization, User};
use App\Services\DocumentDesign\{DocumentDesignRenderer, RenderProfileService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Issue #83: CI-Basisdesign mit Vererbung — Familien-Varianten, explizite
 * Sektions-Overrides, Fallback-Arten (Gutschrift → Rechnungsprofil),
 * Basisdesign-Preflight über alle brandfähigen Arten und die eingebettete
 * PDF-Vorschau.
 */
class CiBaseDesignTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array{0: Organization, 1: User} */
    private function makeOrgAdmin(): array {
        $org = Organization::factory()->create();
        $this->app->instance('currentOrganization', $org);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        return [$org, $admin];
    }

    private function service(): RenderProfileService {
        return app(RenderProfileService::class);
    }

    private function makeActiveBase(Organization $org, User $admin): DocumentRenderProfile {
        $base = $this->service()->createProfile($org, 'CI-Basisdesign', [], true, $admin);
        $this->assertTrue($this->service()->activate($base->versions()->firstOrFail(), $admin)->ok());

        return $base;
    }

    public function test_family_variant_wins_over_base_and_kind_wins_over_family(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = $this->service();
        $base = $this->makeActiveBase($org, $admin);

        $sales = $service->createProfile($org, 'Vertrieb', [], false, $admin, RenderDocumentFamily::Sales);
        $this->assertTrue($service->activate($sales->versions()->firstOrFail(), $admin)->ok());

        $invoice = $service->createProfile($org, 'Rechnungen', ['invoice'], false, $admin);
        $this->assertTrue($service->activate($invoice->versions()->firstOrFail(), $admin)->ok());

        // Dokumentart-Bindung ist spezifischer als die Familie.
        $this->assertSame($invoice->refresh()->active_version_id, $service->resolveFor($org, RenderDocumentKind::Invoice)?->id);
        // Vertriebsart ohne eigene Bindung → Familien-Variante.
        $this->assertSame($sales->refresh()->active_version_id, $service->resolveFor($org, RenderDocumentKind::Quote)?->id);
        // Fremde Familie → CI-Basisdesign.
        $this->assertSame($base->refresh()->active_version_id, $service->resolveFor($org, RenderDocumentKind::Protocol)?->id);
    }

    public function test_credit_note_falls_back_to_invoice_profile_for_compatibility(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = $this->service();

        // Bestandslage: nur ein Rechnungsprofil, keine Familie, kein Basisdesign.
        $invoice = $service->createProfile($org, 'Rechnungen', ['invoice'], false, $admin);
        $this->assertTrue($service->activate($invoice->versions()->firstOrFail(), $admin)->ok());

        $this->assertSame(
            $invoice->refresh()->active_version_id,
            $service->resolveFor($org, RenderDocumentKind::CreditNote)?->id,
            'Gutschriften rendern ohne eigene Variante weiter über das Rechnungsprofil.',
        );
        $this->assertSame(
            $invoice->refresh()->active_version_id,
            $service->resolveFor($org, RenderDocumentKind::Dunning)?->id,
        );
        $this->assertNull($service->resolveFor($org, RenderDocumentKind::Protocol));
    }

    public function test_inheriting_variant_merges_base_sections_and_marks_overrides(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = $this->service();

        $base = $this->makeActiveBase($org, $admin);
        $baseVersion = $base->versions()->firstOrFail()->refresh();
        // Basisdesign trägt ein markantes Layout (30-mm-Ränder) — per neuem
        // Entwurf, weil aktivierte Versionen unveränderlich sind.
        $draft = $service->newDraftFrom($baseVersion, $admin);
        $layout = $draft->layout;
        foreach (['content_first', 'content_following'] as $key) {
            $layout[$key] = ['top' => 30, 'right' => 30, 'bottom' => 30, 'left' => 30];
        }
        $service->updateDraft($draft, ['layout' => $layout], $admin);
        $this->assertTrue($service->activate($draft->refresh(), $admin)->ok());

        // Variante NACH dem Basisdesign angelegt → erbt automatisch ([]).
        $variant = $service->createProfile($org, 'Berichte', ['report'], false, $admin);
        $variantVersion = $variant->versions()->firstOrFail();
        $this->assertSame([], $variantVersion->override_sections);

        // Nur der Tabellenstil wird überschrieben.
        $service->updateDraft($variantVersion, [
            'table_style' => ['preset' => 'compact', 'overrides' => []],
            'override_sections' => ['table_style'],
        ], $admin);
        $this->assertTrue($service->activate($variantVersion->refresh(), $admin)->ok());

        $payload = app(DocumentDesignRenderer::class)->payloadFor($org, RenderDocumentKind::Report);
        $this->assertNotNull($payload);
        $this->assertSame($variant->refresh()->active_version_id, $payload['profile_version_id']);
        $this->assertSame(30.0, (float) $payload['layout']['content_first']['top'], 'Layout kommt aus dem Basisdesign.');
        $this->assertSame('compact', $payload['table_style']['preset'], 'Tabellenstil ist überschrieben.');
        $this->assertSame(['table_style'], $payload['inheritance']['override_sections']);
    }

    public function test_removing_override_resets_section_to_base(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = $this->service();
        $this->makeActiveBase($org, $admin);

        $variant = $service->createProfile($org, 'Berichte', ['report'], false, $admin);
        $version = $variant->versions()->firstOrFail();
        $service->updateDraft($version, [
            'table_style' => ['preset' => 'compact', 'overrides' => []],
            'override_sections' => ['table_style'],
        ], $admin);

        // „Auf Basisdesign zurücksetzen": Override der Sektion entfernen.
        $service->updateDraft($version->refresh(), ['override_sections' => []], $admin);
        $this->assertTrue($service->activate($version->refresh(), $admin)->ok());

        $payload = app(DocumentDesignRenderer::class)->payloadFor($org, RenderDocumentKind::Report);
        $this->assertNotNull($payload);
        $this->assertSame('clear', $payload['table_style']['preset'], 'Ohne Override gilt wieder das Basisdesign.');
    }

    public function test_base_design_preflight_covers_all_brandable_kinds(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = $this->service();

        // Basisdesign ohne Art-Bindung: Steueraufschlüsselung „nicht anwendbar"
        // verletzt die Rechnungs-Pflichtblöcke — die Aktivierung muss blocken,
        // obwohl document_kinds leer ist (früher: Prüfumfang leer).
        $base = $service->createProfile($org, 'CI-Basisdesign', [], true, $admin);
        $version = $base->versions()->firstOrFail();
        $rules = $version->block_rules;
        $rules['tax_breakdown'] = ['state' => 'not_applicable'];
        $service->updateDraft($version, ['block_rules' => $rules], $admin);

        $result = $service->activate($version->refresh(), $admin);
        $this->assertFalse($result->ok());
        $this->assertSame('mandatory_missing', $result->errors[0]['code']);
    }

    public function test_store_profile_with_family_persists_and_assign_updates_it(): void {
        [$org, $admin] = $this->makeOrgAdmin();

        $this->actingAs($admin)->post(route('admin.document-design.profiles.store'), [
            'name' => 'Vertriebsdesign',
            'document_family' => 'sales',
        ])->assertRedirect();

        $profile = DocumentRenderProfile::query()->firstOrFail();
        $this->assertSame(RenderDocumentFamily::Sales, $profile->document_family);

        $this->actingAs($admin)->post(route('admin.document-design.assign', $profile->sqid), [
            'document_family' => 'procurement',
        ])->assertRedirect();
        $this->assertSame(RenderDocumentFamily::Procurement, $profile->refresh()->document_family);

        $this->actingAs($admin)->post(route('admin.document-design.assign', $profile->sqid), [
            'document_family' => '',
        ])->assertRedirect();
        $this->assertNull($profile->refresh()->document_family);
    }

    public function test_preview_pdf_is_inline_and_supports_kind_and_scenario(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $profile = $this->service()->createProfile($org, 'P', ['invoice'], false, $admin);

        $response = $this->actingAs($admin)->get(route('admin.document-design.preview-pdf', [
            'profile' => $profile->sqid,
            'kind' => 'quote',
            'scenario' => 'many_rows',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_preview_pdf_denies_foreign_profile(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $profile = $this->service()->createProfile($org, 'P', ['invoice'], false, $admin);

        $otherOrg = Organization::factory()->create();
        $foreignAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        $this->app->instance('currentOrganization', $otherOrg);

        $this->actingAs($foreignAdmin)
            ->get(route('admin.document-design.preview-pdf', ['profile' => $profile->sqid, 'kind' => 'invoice']))
            ->assertNotFound();
    }

    public function test_typography_is_curated_and_rendered_into_the_document_css(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = $this->service();
        $profile = $service->createProfile($org, 'P', ['report'], false, $admin);
        $version = $profile->versions()->firstOrFail();

        $layout = $version->layout;
        $layout['typography'] = ['font_family' => 'dejavu-serif', 'base_size_pt' => 12];
        $service->updateDraft($version, ['layout' => $layout], $admin);
        // Nicht kuratierte Schriften werden verworfen (kein freies CSS).
        $layout['typography'] = ['font_family' => 'comic-sans', 'base_size_pt' => 99];
        $service->updateDraft($version->refresh(), ['layout' => $layout], $admin);
        $this->assertNull($version->refresh()->layout['typography']['font_family']);
        $this->assertSame(14.0, (float) $version->refresh()->layout['typography']['base_size_pt'], 'Grundgröße wird auf sichere Grenzen geklemmt.');

        $layout['typography'] = ['font_family' => 'dejavu-serif', 'base_size_pt' => 12];
        $service->updateDraft($version->refresh(), ['layout' => $layout], $admin);
        $this->assertTrue($service->activate($version->refresh(), $admin)->ok());

        $renderer = app(DocumentDesignRenderer::class);
        $composed = $renderer->compose(
            '<html><head></head><body></body></html>',
            $renderer->payloadFor($org, RenderDocumentKind::Report),
        );
        $this->assertStringContainsString("body { font-family: 'DejaVu Serif', sans-serif; }", $composed);
        $this->assertStringContainsString('body { font-size: 12.0pt; }', $composed);
    }

    public function test_brand_colors_are_referenced_from_organization_branding(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $org->forceFill(['settings' => array_merge((array) $org->settings, [
            'branding' => ['colors' => ['primary' => '#eef2f7', 'accent' => '#445566']],
        ])])->save();

        $service = $this->service();
        $profile = $service->createProfile($org, 'P', ['report'], false, $admin);
        $version = $profile->versions()->firstOrFail();
        $service->updateDraft($version, [
            'table_style' => ['preset' => 'clear', 'overrides' => [], 'use_brand_colors' => true],
        ], $admin);
        $this->assertTrue($service->activate($version->refresh(), $admin)->ok());

        $payload = app(DocumentDesignRenderer::class)->payloadFor($org, RenderDocumentKind::Report);
        $this->assertNotNull($payload);
        $this->assertSame('#445566', $payload['table_style']['overrides']['accent_color'], 'Akzentfarbe kommt aus dem Branding.');
        $this->assertSame('#eef2f7', $payload['table_style']['overrides']['header_fill'], 'Kopfzeilenfläche referenziert die Primärfarbe.');
        // Keine Farbkopie in der gespeicherten Version — nur die Referenz.
        $this->assertArrayNotHasKey('accent_color', $version->refresh()->table_style['overrides']);
    }

    public function test_credit_note_invoice_freezes_snapshot_under_its_own_kind(): void {
        [$org, $admin] = $this->makeOrgAdmin();
        $service = $this->service();
        $invoiceProfile = $service->createProfile($org, 'Rechnungen', ['invoice'], false, $admin);
        $this->assertTrue($service->activate($invoiceProfile->versions()->firstOrFail(), $admin)->ok());

        $customer = \App\Models\Customer::factory()->create(['organization_id' => $org->id]);
        $invoice = \App\Models\Invoice::create([
            'organization_id' => $org->id,
            'customer_id' => $customer->id,
            'number' => 'G-' . uniqid(),
            'status' => \App\Models\Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'type' => \App\Models\Invoice::TYPE_CREDIT_NOTE,
        ]);
        $invoice->freezeParties();
        $invoice->save();

        $this->assertDatabaseHas('document_render_snapshots', [
            'documentable_id' => $invoice->id,
            'document_kind' => RenderDocumentKind::CreditNote->value,
        ]);

        // Der PDF-Payload liest den Snapshot über die Gutschrifts-Art.
        $payload = app(DocumentDesignRenderer::class)->payloadFromSnapshot($invoice, RenderDocumentKind::CreditNote);
        $this->assertNotNull($payload);
        $this->assertSame($invoiceProfile->refresh()->active_version_id, $payload['profile_version_id']);
    }
}
