<?php
/*
 * Created on   : Sat Sep 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KnowledgeEntryPointTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Knowledge;

use App\Enums\User\Permission;
use App\Models\{SafetyEvent, User};
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Ein Einstieg statt fünf Menüpunkten (MVP-820, Zuschnitt A).
 *
 * Wissenszentrale, Wissensbasis, Sammlungen, Ideenkarten und Dokumente standen
 * gleichrangig in der Sidebar und zeigten teils denselben Bestand — die
 * Beobachtung „doppelte Mappen", die diese Phase ausgelöst hat. Die Fachlisten
 * hängen jetzt als Typansichten an einer gemeinsamen Leiste; die Sidebar führt
 * nur noch den Einstieg und die eigenständigen Formulare.
 *
 * Der Golden-Snapshot {@see \Tests\Feature\Navigation\NavigationGoldenTest}
 * friert die Sichtbarkeit ein; hier steht, was fachlich gelten soll.
 */
final class KnowledgeEntryPointTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    public function test_the_type_bar_connects_the_views_of_the_knowledge_area(): void {
        $seiten = [
            route('knowledge-hub.index'),
            route('knowledge.index'),
            route('ideas.index'),
            route('documents.index'),
            route('collections.index'),
            route('communication-notes.index'),
        ];

        foreach ($seiten as $seite) {
            $html = (string) $this->actingAs($this->admin)->get($seite)->assertOk()->getContent();
            $this->assertStringContainsString('data-knowledge-tabs', $html, "Die Typleiste fehlt auf {$seite}.");

            foreach ([
                route('knowledge-hub.index'),
                route('knowledge.index'),
                route('ideas.index'),
                route('documents.index'),
                route('communication-notes.index'),
            ] as $ziel) {
                $this->assertStringContainsString(
                    'href="' . $ziel . '"',
                    $html,
                    "Die Typleiste ist unvollständig auf {$seite}.",
                );
            }
        }
    }

    public function test_a_type_without_module_leaves_the_bar(): void {
        // Ohne das Modul Ideenlandkarten verschwindet der Reiter, der Rest bleibt.
        config(['license.feature_overrides' => ['module.ideas' => false]]);
        app(FeatureFlagResolver::class)->flush();

        $html = (string) $this->actingAs($this->admin)->get(route('knowledge-hub.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('href="' . route('ideas.index') . '"', $html);
        $this->assertStringContainsString('href="' . route('knowledge.index') . '"', $html);
    }

    public function test_safety_events_moved_to_the_occupational_safety_section(): void {
        // Melder ohne Register-Zugriff: die Sektion öffnet sich trotzdem für ihn.
        $melder = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->grantPermissions($melder, [Permission::SafetyReport]);

        $html = (string) $this->actingAs($melder)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString(route('safety-events.index'), $html);
        // Der Meldeweg hängt nicht mehr an der Wissensordnung.
        $this->assertTrue(
            $melder->can('create', SafetyEvent::class),
            'Das Melderecht trägt den Eintrag, nicht das Register-Recht.',
        );
    }

    public function test_forms_stand_on_their_own(): void {
        // Der zweideutige Eintrag „Dokumente & Formulare" ist aufgelöst: beide
        // Ziele sind getrennt erreichbar, das Formular trägt keine Wissensleiste.
        $html = (string) $this->actingAs($this->admin)->get(route('form-submissions.index'))->assertOk()->getContent();

        // Der Einstieg bleibt über die Sidebar erreichbar; die Seite selbst
        // trägt keine Wissensleiste, weil Formulare kein Inhaltstyp sind.
        $this->assertStringNotContainsString('data-knowledge-tabs', $html);
        $this->assertStringNotContainsString('href="' . route('documents.index') . '"', $html);
    }
}
