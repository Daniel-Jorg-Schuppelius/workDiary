<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpContextTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Help;

use App\Models\{HelpTopic, User};
use App\Services\Help\HelpContextResolver;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * Feature 039 Inkrement 1: Route→Topic-Registry (config/help-topics.php),
 * automatischer Seitenkontext im Layout (data-help-context + Auto-Button)
 * und definierter Fallback bei fehlendem Topic.
 *
 * Das Sidebar-JS (Öffnen/Schließen, ?-Shortcut, localStorage) wird hier
 * bewusst NICHT getestet — es gibt kein JS-Test-Setup im Projekt.
 */
class HelpContextTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    private function namedRoute(string $name): Route {
        return (new Route(['GET'], '/_help-context-test', []))->name($name);
    }

    private function createTopic(string $topic, array $audience = []): HelpTopic {
        return HelpTopic::query()->create([
            'topic' => $topic,
            'locale' => 'de',
            'title' => 'Titel ' . $topic,
            'audience' => $audience,
            'version' => 1,
            'body_md' => 'Body',
            'body_html' => '<p>Body</p>',
            'related' => [],
        ]);
    }

    // ----------------------------------------------------------------
    // Registry-Matching (HelpContextResolver::currentTopicFor)
    // ----------------------------------------------------------------

    public function test_exact_route_name_matches_registry_entry(): void {
        config(['help-topics.routes' => [
            'diary.index' => 'diary-entries.create',
        ]]);

        $resolver = app(HelpContextResolver::class);

        $this->assertSame(
            'diary-entries.create',
            $resolver->currentTopicFor($this->namedRoute('diary.index'))
        );
    }

    public function test_wildcard_pattern_matches_registry_entry(): void {
        config(['help-topics.routes' => [
            'onboarding.*' => 'onboarding.checklist',
            'reports.*.drilldown.*' => 'reports.drilldown',
        ]]);

        $resolver = app(HelpContextResolver::class);

        $this->assertSame(
            'onboarding.checklist',
            $resolver->currentTopicFor($this->namedRoute('onboarding.index'))
        );
        $this->assertSame(
            'reports.drilldown',
            $resolver->currentTopicFor($this->namedRoute('reports.customers.drilldown.protocols'))
        );
    }

    public function test_exact_match_wins_over_wildcard(): void {
        config(['help-topics.routes' => [
            'reports.*' => 'reports.drilldown',
            'reports.customers' => 'reports.customer-analysis',
        ]]);

        $resolver = app(HelpContextResolver::class);

        $this->assertSame(
            'reports.customer-analysis',
            $resolver->currentTopicFor($this->namedRoute('reports.customers'))
        );
    }

    public function test_unmatched_route_yields_null(): void {
        config(['help-topics.routes' => [
            'diary.*' => 'diary-entries.create',
        ]]);

        $resolver = app(HelpContextResolver::class);

        $this->assertNull($resolver->currentTopicFor($this->namedRoute('dashboard')));
        $this->assertNull($resolver->currentTopicFor(new Route(['GET'], '/unnamed', [])));
    }

    public function test_default_registry_maps_existing_topics(): void {
        $resolver = app(HelpContextResolver::class);

        $this->assertSame('account.two-factor', $resolver->currentTopicFor($this->namedRoute('account.2fa.show')));
        $this->assertSame('duties.overview', $resolver->currentTopicFor($this->namedRoute('duties.index')));
        $this->assertSame('diary-entries.create', $resolver->currentTopicFor($this->namedRoute('diary.index')));
        $this->assertSame('week.overview', $resolver->currentTopicFor($this->namedRoute('week.index')));
        $this->assertSame('onboarding.checklist', $resolver->currentTopicFor($this->namedRoute('onboarding.index')));
        $this->assertSame('protocols.sign', $resolver->currentTopicFor($this->namedRoute('protocols.public-sign')));
        $this->assertSame('protocols.create', $resolver->currentTopicFor($this->namedRoute('protocols.store')));
    }

    // ----------------------------------------------------------------
    // Sichtbarkeit (visibleTopicFor): Existenz + audience-Filter
    // ----------------------------------------------------------------

    public function test_visible_topic_requires_existing_help_topic(): void {
        config(['help-topics.routes' => ['week.index' => 'time-entries.start']]);

        $user = User::factory()->user()->create();
        $resolver = app(HelpContextResolver::class);

        $this->assertNull($resolver->visibleTopicFor($this->namedRoute('week.index'), $user));

        $this->createTopic('time-entries.start');

        $this->assertSame(
            'time-entries.start',
            $resolver->visibleTopicFor($this->namedRoute('week.index'), $user)
        );
    }

    public function test_visible_topic_respects_audience_filter(): void {
        config(['help-topics.routes' => ['week.index' => 'time-entries.start']]);
        $this->createTopic('time-entries.start', ['admin']);

        $resolver = app(HelpContextResolver::class);

        $user = User::factory()->user()->create();
        $this->assertNull($resolver->visibleTopicFor($this->namedRoute('week.index'), $user));

        $admin = User::factory()->admin()->create();
        $this->assertSame(
            'time-entries.start',
            $resolver->visibleTopicFor($this->namedRoute('week.index'), $admin)
        );
    }

    // ----------------------------------------------------------------
    // Layout: data-help-context + Auto-Button im Seitenkopf
    // ----------------------------------------------------------------

    public function test_layout_renders_context_and_auto_button_for_mapped_route(): void {
        $this->createTopic('week.overview');

        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('week.index'))
            ->assertOk()
            ->assertSee('data-help-context="week.overview"', false)
            ->assertSee(__('Hilfe zu dieser Seite'));
    }

    public function test_layout_renders_no_context_for_unmapped_route(): void {
        $this->createTopic('time-entries.start');

        $user = User::factory()->user()->create();

        // Der Hilfe-Button ist IMMER da (konsistente Stelle laut
        // Bedienkonzept) — ohne Mapping aber ohne Kontext-Attribut/-Topic;
        // er öffnet dann das Fallback-Panel mit Suche.
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-help-context=', false)
            ->assertDontSee(__('Hilfe zu dieser Seite'))
            ->assertSee('data-help-trigger', false);
    }

    public function test_layout_renders_no_context_when_topic_is_admin_only(): void {
        $this->createTopic('week.overview', ['admin']);

        $user = User::factory()->user()->create();

        // audience-fremdes Topic ⇒ kein Kontext-Attribut und kein
        // data-help-topic am Header-Button (Fallback statt 404 im Drawer).
        $this->actingAs($user)
            ->get(route('week.index'))
            ->assertOk()
            ->assertDontSee('data-help-context=', false)
            ->assertDontSee(__('Hilfe zu dieser Seite'))
            ->assertSee('data-help-trigger', false);
    }

    public function test_layout_renders_no_context_when_topic_file_is_missing(): void {
        // Registry kennt die Route, aber das Topic existiert (noch) nicht in
        // der Datenbank → kein Kontext-Attribut, Button öffnet Fallback.
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('week.index'))
            ->assertOk()
            ->assertDontSee('data-help-context=', false)
            ->assertDontSee(__('Hilfe zu dieser Seite'))
            ->assertSee('data-help-trigger', false);
    }

    // ----------------------------------------------------------------
    // Fallback auf HTTP-Ebene: unbekanntes Topic → sauberes 404-JSON
    // ----------------------------------------------------------------

    public function test_unknown_topic_returns_clean_404_payload_for_drawer_fallback(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->getJson(route('help.topics.show', ['topic' => 'unknown.topic']))
            ->assertNotFound()
            ->assertJsonPath('found', false)
            ->assertJsonStructure(['found', 'topic', 'message']);
    }
}
