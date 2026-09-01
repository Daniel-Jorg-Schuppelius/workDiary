<?php
/*
 * Created on   : Tue Sep 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpCenterTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Help;

use App\Models\{HelpTopic, HelpView, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hilfecenter-Vollseite (Feature 039, MVP-752): Übersicht, Suche,
 * Bereichsansicht und Themenseite — derselbe Inhaltsbestand und dieselbe
 * Sichtbarkeitslogik wie der Drawer (HelpTopicResolver).
 */
class HelpCenterTest extends TestCase {
    use RefreshDatabase;

    private function makeTopic(string $topic, array $overrides = []): HelpTopic {
        return HelpTopic::query()->create(array_merge([
            'topic' => $topic,
            'locale' => 'de',
            'title' => 'Titel ' . $topic,
            'audience' => [],
            'modules' => [],
            'version' => 1,
            'body_md' => 'Inhalt von ' . $topic,
            'body_html' => '<p>Inhalt von ' . $topic . '</p>',
            'related' => [],
            'headings' => [],
        ], $overrides));
    }

    public function test_index_requires_authentication(): void {
        $this->get(route('help.center.index'))->assertRedirect();
    }

    public function test_overview_shows_sections_with_visible_counts_only(): void {
        $this->makeTopic('dashboard.overview');
        $this->makeTopic('customers.overview');
        // Nur für Admins sichtbar — darf für normale Nutzer weder gelistet
        // noch mitgezählt werden.
        $this->makeTopic('admin.backups', ['audience' => ['admin']]);

        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->get(route('help.center.index'));

        $response->assertOk()
            ->assertSee(__('help.sections.erste-schritte.title'))
            ->assertSee(__('help.sections.kunden-vertrieb.title'))
            ->assertDontSee(__('help.sections.administration.title'));
    }

    public function test_section_view_lists_only_visible_topics_of_that_section(): void {
        $this->makeTopic('dashboard.overview');
        $this->makeTopic('onboarding.index', ['title' => 'Onboarding-Checkliste']);
        $this->makeTopic('customers.overview', ['title' => 'Kundenstamm']);

        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->get(route('help.center.index', ['bereich' => 'erste-schritte']));

        $response->assertOk()
            ->assertSee('Titel dashboard.overview')
            ->assertSee('Onboarding-Checkliste')
            ->assertDontSee('Kundenstamm');
    }

    public function test_section_view_for_unknown_or_empty_section_is_404(): void {
        $this->makeTopic('dashboard.overview');
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('help.center.index', ['bereich' => 'gibt-es-nicht']))->assertNotFound();
        // Bereich existiert, hat aber keine sichtbaren Artikel.
        $this->actingAs($user)->get(route('help.center.index', ['bereich' => 'buchhaltung']))->assertNotFound();
    }

    public function test_search_finds_visible_topics_and_hides_restricted_titles(): void {
        $this->makeTopic('time-entries.start', ['title' => 'Zeiterfassung starten', 'body_md' => 'Stempeluhr und Buchungen']);
        $this->makeTopic('admin.backups', ['title' => 'Sicherung geheim', 'audience' => ['admin'], 'body_md' => 'Stempeluhr im Admin']);

        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->get(route('help.center.index', ['q' => 'Stempeluhr']));

        $response->assertOk()
            ->assertSee('Zeiterfassung starten')
            ->assertDontSee('Sicherung geheim');
    }

    public function test_search_escapes_like_wildcards(): void {
        $this->makeTopic('sample.percent', ['title' => 'Enthält 100% Prozent', 'body_md' => 'Enthält 100% Prozent']);
        $this->makeTopic('sample.other', ['title' => 'Ganz anderer Titel', 'body_md' => 'Ganz anderer Inhalt']);

        $user = User::factory()->user()->create();

        // "%" ist literal zu suchen (whereLikeEscaped) — kein Match-All.
        $response = $this->actingAs($user)->get(route('help.center.index', ['q' => '100%']));

        $response->assertOk()
            ->assertSee('Enthält 100% Prozent')
            ->assertDontSee('Ganz anderer Titel');
    }

    public function test_search_paginates_and_keeps_query(): void {
        for ($i = 1; $i <= 25; $i++) {
            $this->makeTopic(sprintf('customers.thema-%02d', $i), [
                'title' => sprintf('Suchwort Artikel %02d', $i),
                'body_md' => 'Suchwort',
            ]);
        }

        $user = User::factory()->user()->create();

        $first = $this->actingAs($user)->get(route('help.center.index', ['q' => 'Suchwort']));
        $first->assertOk()->assertSee('Suchwort Artikel 01')->assertDontSee('Suchwort Artikel 25');

        $second = $this->actingAs($user)->get(route('help.center.index', ['q' => 'Suchwort', 'page' => 2]));
        $second->assertOk()->assertSee('Suchwort Artikel 25')->assertDontSee('Suchwort Artikel 01');
    }

    public function test_empty_search_shows_defined_empty_state(): void {
        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->get(route('help.center.index', ['q' => 'xyzzy-nichts']));

        $response->assertOk()
            ->assertSee(__('Keine passenden Hilfethemen gefunden.'))
            ->assertSee(__('Suche zurücksetzen'));
    }

    public function test_show_renders_article_with_toc_related_and_logs_view(): void {
        $this->makeTopic('time-entries.start', [
            'title' => 'Zeiterfassung starten',
            'body_html' => '<h2 id="sec-zweck">Zweck</h2><p>Inhalt der Seite.</p>',
            'related' => ['time-entries.edit', 'time-entries.missing', 'admin.backups'],
            'headings' => [
                ['level' => 2, 'text' => 'Zweck', 'anchor' => 'sec-zweck'],
                ['level' => 2, 'text' => 'Ablauf', 'anchor' => 'sec-ablauf'],
                ['level' => 2, 'text' => 'Fehler', 'anchor' => 'sec-fehler'],
            ],
        ]);
        $this->makeTopic('time-entries.edit', ['title' => 'Zeitbuchungen bearbeiten']);
        // Verwandtes Topic mit Admin-Audience: für normale Nutzer unsichtbar.
        $this->makeTopic('admin.backups', ['title' => 'Sicherung geheim', 'audience' => ['admin']]);

        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->get(route('help.center.show', ['topic' => 'time-entries.start']));

        $response->assertOk()
            ->assertSee('Zeiterfassung starten')
            ->assertSee('Inhalt der Seite.')
            ->assertSee('sec-zweck')
            ->assertSee(__('Auf dieser Seite'))
            ->assertSee('Zeitbuchungen bearbeiten')
            ->assertDontSee('Sicherung geheim')
            ->assertSee(__('help.sections.zeit-personal.title'));

        $view = HelpView::query()->where('topic', 'time-entries.start')->first();
        $this->assertNotNull($view);
        $this->assertSame($user->organization_id, $view->organization_id);
        $this->assertNull($view->was_helpful);
    }

    public function test_show_unknown_and_restricted_topics_are_identical_404(): void {
        $this->makeTopic('admin.backups', ['audience' => ['admin']]);

        $user = User::factory()->user()->create();

        $unknown = $this->actingAs($user)->get(route('help.center.show', ['topic' => 'gibt.es-nicht']));
        $restricted = $this->actingAs($user)->get(route('help.center.show', ['topic' => 'admin.backups']));

        $unknown->assertNotFound();
        $restricted->assertNotFound();
        // Kein Berechtigungs-Orakel: keine HelpView-Zeile für den Versuch.
        $this->assertSame(0, HelpView::query()->count());
    }

    public function test_show_falls_back_along_locale_chain(): void {
        // Nur de-Zeile vorhanden — ein fr-Nutzer bekommt den de-Inhalt.
        $this->makeTopic('dashboard.overview', ['title' => 'Dashboard-Hilfe']);

        $user = User::factory()->user()->create(['preferences' => ['locale' => 'fr']]);

        $response = $this->actingAs($user)->get(route('help.center.show', ['topic' => 'dashboard.overview']));

        $response->assertOk()->assertSee('Dashboard-Hilfe');
    }

    public function test_admin_sees_admin_topics_in_overview_counts(): void {
        $this->makeTopic('admin.backups', ['audience' => ['admin']]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('help.center.index'));

        $response->assertOk()->assertSee(__('help.sections.administration.title'));
    }

    public function test_module_gated_topic_is_hidden_when_module_is_disabled(): void {
        $this->makeTopic('helpdesk.overview', ['title' => 'Helpdesk-Grundlagen', 'modules' => ['module.helpdesk'], 'body_md' => 'Ticketbearbeitung']);

        $user = User::factory()->user()->create();

        // Modul lizenziert (Factory-Default): sichtbar.
        $this->actingAs($user)->get(route('help.center.show', ['topic' => 'helpdesk.overview']))->assertOk();

        // Modul abgeschaltet: Übersicht zählt nicht, Suche schweigt, show ist 404.
        config()->set('license.feature_overrides', ['module.helpdesk' => false]);
        app(\App\Services\Licensing\FeatureFlagResolver::class)->flush();

        $this->actingAs($user)->get(route('help.center.show', ['topic' => 'helpdesk.overview']))->assertNotFound();
        $this->actingAs($user)->get(route('help.center.index', ['q' => 'Ticketbearbeitung']))
            ->assertOk()
            ->assertDontSee('Helpdesk-Grundlagen');
        // Drawer-Endpunkt folgt derselben Sichtbarkeit (ein Resolver).
        $this->actingAs($user)->getJson(route('help.topics.show', ['topic' => 'helpdesk.overview']))->assertNotFound();
    }

    public function test_module_gating_combines_with_audience(): void {
        $this->makeTopic('finance.dunning', ['title' => 'Mahnlauf-Hilfe', 'modules' => ['module.finance'], 'audience' => ['admin']]);

        $user = User::factory()->user()->create();
        $admin = User::factory()->admin()->create();

        // Modul an, aber Rolle fehlt → unsichtbar; Admin sieht es.
        $this->actingAs($user)->get(route('help.center.show', ['topic' => 'finance.dunning']))->assertNotFound();
        $this->actingAs($admin)->get(route('help.center.show', ['topic' => 'finance.dunning']))->assertOk();

        // Modul aus → auch der Admin sieht nichts mehr.
        config()->set('license.feature_overrides', ['module.finance' => false]);
        app(\App\Services\Licensing\FeatureFlagResolver::class)->flush();
        $this->actingAs($admin)->get(route('help.center.show', ['topic' => 'finance.dunning']))->assertNotFound();
    }

    public function test_search_snippet_highlights_match_and_escapes_html(): void {
        $this->makeTopic('sample.snippet', [
            'title' => 'Snippet-Beispiel',
            'body_md' => 'Vor dem Treffer <script>alert(1)</script> steht Suchwortxyz und danach folgt weiterer Text.',
        ]);

        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->get(route('help.center.index', ['q' => 'Suchwortxyz']));

        $response->assertOk()
            ->assertSee('Suchwortxyz</mark>', false)
            // body_md-HTML bleibt Daten: escaped, nie roh in der Seite.
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_overview_lists_popular_topics_of_own_organization(): void {
        $this->makeTopic('dashboard.overview', ['title' => 'Dashboard-Grundlagen']);
        $this->makeTopic('admin.backups', ['title' => 'Sicherung geheim', 'audience' => ['admin']]);

        $user = User::factory()->user()->create();
        foreach (['dashboard.overview', 'admin.backups'] as $topic) {
            for ($i = 0; $i < 3; $i++) {
                HelpView::query()->create([
                    'organization_id' => $user->organization_id,
                    'topic' => $topic,
                    'locale' => 'de',
                    'was_helpful' => null,
                    'created_at' => now(),
                ]);
            }
        }

        $response = $this->actingAs($user)->get(route('help.center.index'));

        $response->assertOk()
            ->assertSee(__('Beliebte Themen'))
            ->assertSee('Dashboard-Grundlagen')
            // Viel gelesen, aber für die Rolle unsichtbar: taucht nie auf.
            ->assertDontSee('Sicherung geheim');
    }
}
