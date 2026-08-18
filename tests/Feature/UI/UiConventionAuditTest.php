<?php
/*
 * Created on   : Mon Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UiConventionAuditTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Models\User;
use App\Support\EntityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Gates für die im UI-Unification-Audit (B1/MVP-007, Features 064–077)
 * direkt behobenen Konventionsverstöße — jede Regression fällt hier auf:
 *
 * - Rechnungsvorlagen sind Modal-first (kein Vollseiten-Formular mehr),
 * - Dokumentdesign-Index rendert die Anlege-Aktionen (der actions-Slot
 *   wurde zuvor von <x-page-shell> verschluckt → Buttons unsichtbar),
 * - Morph-Typ-Badges nutzen den Label-Helfer (entity-types.* ×5 Sprachen),
 * - Von-Bis-Zeiträume nutzen <x-date-range> statt zweier date-Inputs.
 */
class UiConventionAuditTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create([
            'organization_id' => $this->organization->id,
        ]);
    }

    /**
     * Vollaudit 2026-07 (N3): <x-page-shell> hat keinen actions-Slot — ein
     * direkt darunter platzierter <x-slot:actions> wird verschluckt und die
     * Aktionen sind unsichtbar. Der Fehler ist zweimal aufgetreten (Dokument-
     * design Index + Editor); dieses Gate hält ihn app-weit fest.
     */
    public function test_no_view_feeds_actions_slot_directly_into_page_shell(): void {
        $offenders = [];
        foreach (\Illuminate\Support\Facades\File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $content = (string) file_get_contents($file->getPathname());
            if (preg_match('/<x-page-shell[^>]*>\s*<x-slot:actions/', $content) === 1) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'x-page-shell verschluckt <x-slot:actions> — Aktionen gehören in die Toolbar (<x-slot:toolbar>) oder die Kopf-Karte: ' . implode(', ', $offenders));
    }

    public function test_document_design_index_shows_create_actions_for_admin(): void {
        $this->actingAs($this->admin)
            ->get(route('admin.document-design.index'))
            ->assertOk()
            ->assertSee(route('admin.document-design.profiles.create'))
            ->assertSee(route('admin.document-design.assets.create'))
            ->assertSee('data-entry-modal-trigger', false);
    }

    public function test_link_badge_entity_types_are_translated_in_all_locales(): void {
        $keys = [
            'Document', 'IncomingEInvoice', 'IsmsIncident', 'PrivacyIncident',
            'PurchaseOrder', 'SafetyEvent', 'ServiceTicket',
        ];

        foreach (['de', 'en', 'fr', 'it', 'es'] as $locale) {
            foreach ($keys as $key) {
                $this->assertTrue(
                    Lang::has("entity-types.$key", $locale),
                    "entity-types.$key fehlt in $locale",
                );
            }
        }

        app()->setLocale('de');
        $this->assertSame('Bestellung', EntityType::label(\App\Models\PurchaseOrder::class));
        $this->assertSame('Service-Ticket', EntityType::label(\App\Models\ServiceTicket::class));
    }

    public function test_sustainability_activity_form_uses_date_range_component(): void {
        $this->actingAs($this->admin)
            ->get(route('sustainability.index'))
            ->assertOk()
            ->assertSee('name="period_start"', false)
            ->assertSee('name="period_end"', false)
            // Marker der <x-date-range>-Komponente (gekoppeltes Von/Bis):
            ->assertSee('data-range-from', false)
            ->assertSee('data-range-to', false);
    }
}
