<?php
/*
 * Created on   : Wed Aug 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DesignRefinementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\DocumentDesign;

use App\Enums\DocumentDesign\RenderDocumentKind;
use App\Models\{Organization, User};
use App\Services\DocumentDesign\{DocumentDesignRenderer, RenderProfileService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MVP-650-Feinschliff: feingranulare Vererbung (Einstellungsgruppen statt
 * Layout en bloc), bewusste Warnbestätigung bei der Aktivierung,
 * Kopf-/Fußzeilen je Seite (carryover_note entfernt) und die restlichen
 * Tabellenstil-Schalter.
 */
class DesignRefinementTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
    }

    private function service(): RenderProfileService {
        return app(RenderProfileService::class);
    }

    /** Aktives Basisdesign mit markanten Rändern (30 mm) und Serif-Typografie. */
    private function makeBase(): void {
        $service = $this->service();
        $base = $service->createProfile($this->org, 'CI-Basisdesign', [], true, $this->admin);
        $version = $base->versions()->firstOrFail();
        $layout = $version->layout;
        foreach (['content_first', 'content_following'] as $key) {
            $layout[$key] = ['top' => 30, 'right' => 30, 'bottom' => 30, 'left' => 30];
        }
        $layout['typography'] = ['font_family' => 'dejavu-serif', 'base_size_pt' => 12];
        $service->updateDraft($version, ['layout' => $layout], $this->admin);
        $this->assertTrue($service->activate($version->refresh(), $this->admin)->ok());
    }

    public function test_fine_grained_sections_inherit_independently(): void {
        $this->makeBase();
        $service = $this->service();

        // Variante überschreibt NUR die Ränder — Typografie bleibt geerbt.
        $variant = $service->createProfile($this->org, 'Berichte', ['report'], false, $this->admin);
        $version = $variant->versions()->firstOrFail();
        $layout = $version->layout;
        foreach (['content_first', 'content_following'] as $key) {
            $layout[$key] = ['top' => 25, 'right' => 25, 'bottom' => 25, 'left' => 25];
        }
        $service->updateDraft($version, ['layout' => $layout, 'override_sections' => ['margins']], $this->admin);
        $this->assertTrue($service->activate($version->refresh(), $this->admin)->ok());

        $payload = app(DocumentDesignRenderer::class)->payloadFor($this->org, RenderDocumentKind::Report);
        $this->assertNotNull($payload);
        $this->assertSame(25.0, (float) $payload['layout']['content_first']['top'], 'Ränder kommen aus der Variante.');
        $this->assertSame('dejavu-serif', $payload['layout']['typography']['font_family'], 'Typografie bleibt vom Basisdesign geerbt.');
        $this->assertSame(['margins'], $payload['inheritance']['override_sections']);
    }

    public function test_legacy_layout_override_expands_to_all_layout_groups(): void {
        $this->makeBase();
        $service = $this->service();

        $variant = $service->createProfile($this->org, 'Berichte', ['report'], false, $this->admin);
        $version = $variant->versions()->firstOrFail();
        $layout = $version->layout;
        $layout['typography'] = ['font_family' => 'dejavu-mono', 'base_size_pt' => null];
        $service->updateDraft($version, ['layout' => $layout], $this->admin);
        // Bestandsdaten: gespeicherter Sammel-Override 'layout' (vor dem Feinschliff).
        $version->refresh()->forceFill(['override_sections' => ['layout']])->save();
        $this->assertTrue($service->activate($version->refresh(), $this->admin)->ok());

        $payload = app(DocumentDesignRenderer::class)->payloadFor($this->org, RenderDocumentKind::Report);
        $this->assertNotNull($payload);
        $this->assertSame(20.0, (float) $payload['layout']['content_first']['top'], 'Legacy-layout-Override: Ränder aus der Variante (Standard 20 mm).');
        $this->assertSame('dejavu-mono', $payload['layout']['typography']['font_family'], 'Legacy-layout-Override deckt auch die Typografie ab.');
    }

    public function test_activation_with_warnings_requires_explicit_confirmation(): void {
        $service = $this->service();
        $profile = $service->createProfile($this->org, 'P', ['report'], false, $this->admin);
        $version = $profile->versions()->firstOrFail();

        // Unterschiedliche untere Ränder → Preflight-WARNUNG (kein Fehler).
        $layout = $version->layout;
        $layout['content_first'] = ['top' => 20, 'right' => 20, 'bottom' => 25, 'left' => 20];
        $service->updateDraft($version, ['layout' => $layout], $this->admin);

        $result = $service->activate($version->refresh(), $this->admin);
        $this->assertTrue($result->ok());
        $this->assertNotSame([], $result->warnings);
        $this->assertTrue($version->refresh()->isDraft(), 'Ohne Bestätigung wird trotz grünem Preflight nicht aktiviert.');

        // HTTP-Pfad: ohne Bestätigung Fehler-Flash, mit Bestätigung aktiv.
        $this->actingAs($this->admin)
            ->post(route('admin.document-design.activate', $profile->sqid))
            ->assertSessionHas('error');
        $this->assertTrue($version->refresh()->isDraft());

        $this->actingAs($this->admin)
            ->post(route('admin.document-design.activate', $profile->sqid), ['confirm_warnings' => 1])
            ->assertSessionHas('success');
        $this->assertFalse($version->refresh()->isDraft());
    }

    public function test_per_page_header_and_footer_notes_render_and_carryover_flag_is_gone(): void {
        $service = $this->service();
        $profile = $service->createProfile($this->org, 'P', ['report'], true, $this->admin);
        $version = $profile->versions()->firstOrFail();

        $layout = $version->layout;
        $layout['header'] = ['note' => '<b>Vertraulich</b> — nur intern'];
        $layout['footer'] = ['page_numbers' => true, 'note' => 'Es gelten unsere AGB.', 'carryover_note' => true];
        $service->updateDraft($version, ['layout' => $layout], $this->admin);

        $stored = $version->refresh()->layout;
        $this->assertSame('Vertraulich — nur intern', $stored['header']['note'], 'HTML wird entfernt.');
        $this->assertSame('Es gelten unsere AGB.', $stored['footer']['note']);
        $this->assertArrayNotHasKey('carryover_note', $stored['footer'], 'Der tote Schalter ist entfernt.');

        $this->assertTrue($service->activate($version->refresh(), $this->admin)->ok());
        $renderer = app(DocumentDesignRenderer::class);
        $composed = $renderer->compose('<html><head></head><body></body></html>', $renderer->payloadFor($this->org, RenderDocumentKind::Report));
        $this->assertStringContainsString('dd-headnote', $composed);
        $this->assertStringContainsString('Vertraulich — nur intern', $composed);
        $this->assertStringContainsString('dd-footnote', $composed);
        $this->assertStringContainsString('Es gelten unsere AGB.', $composed);
    }

    public function test_extended_table_style_switches_flow_into_rendered_css(): void {
        $service = $this->service();
        $profile = $service->createProfile($this->org, 'P', ['report'], true, $this->admin);
        $version = $profile->versions()->firstOrFail();

        $service->updateDraft($version, ['table_style' => [
            'preset' => 'clear',
            'overrides' => [
                'grid' => 'full',
                'repeat_header' => false,
                'highlight_totals' => false,
                'line_height' => 1.5,
                'cell_padding_v' => 8,
            ],
        ]], $this->admin);
        $this->assertTrue($service->activate($version->refresh(), $this->admin)->ok());

        $renderer = app(DocumentDesignRenderer::class);
        $composed = $renderer->compose('<html><head></head><body></body></html>', $renderer->payloadFor($this->org, RenderDocumentKind::Report));
        $this->assertStringContainsString('border: 0.5px solid', $composed, 'Volles Raster.');
        $this->assertStringContainsString('line-height: 1.50', $composed);
        $this->assertStringContainsString('padding: 8px', $composed);
        $this->assertStringNotContainsString('table-header-group', $composed, 'Kopfzeilen-Wiederholung abgeschaltet.');
        $this->assertStringNotContainsString('tfoot td { border-top', $composed, 'Summenbetonung abgeschaltet.');
    }
}
