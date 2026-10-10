<?php
/*
 * Created on   : Thu Oct 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FunctionFinderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Help;

use App\Models\Platform\{HelpTopic, User};
use App\Services\Help\{FunctionFinder, HelpSearch};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Wegweiser zur Funktion (MVP-1082): Seiten der Navigation, gefunden über
 * Bezeichnung und Suchbegriffe ihres Hilfethemas, nur mit Zugriff; dazu
 * Bedienaktionen der Befehlspalette und die Gruppen der Palette.
 */
class FunctionFinderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app()->setLocale('de');
    }

    /** @param array<string, mixed> $overrides */
    private function makeTopic(string $topic, array $overrides = []): HelpTopic {
        return HelpTopic::query()->create(array_merge([
            'topic' => $topic,
            'locale' => 'de',
            'title' => 'Titel ' . $topic,
            'keywords' => [],
            'audience' => [],
            'modules' => [],
            'version' => 1,
            'body_md' => 'Inhalt',
            'body_html' => '<p>Inhalt</p>',
            'related' => [],
            'headings' => [],
        ], $overrides));
    }

    /** @return list<string> */
    private function pageRoutes(string $query): array {
        return array_column(app(FunctionFinder::class)->pages(app(HelpSearch::class)->prepare($query, 'de'), 10), 'route');
    }

    public function test_page_is_found_by_its_label(): void {
        $this->actingAs(User::factory()->user()->create(['organization_id' => $this->organization->id]));

        $this->assertContains('attendance.index', $this->pageRoutes('Stempeluhr'));
    }

    public function test_page_is_found_by_keywords_of_its_help_topic(): void {
        $this->makeTopic('attendance.manage', ['title' => 'Anwesenheit erfassen', 'keywords' => ['Stechuhr', 'Kommen und Gehen']]);
        $this->actingAs(User::factory()->user()->create(['organization_id' => $this->organization->id]));

        $pages = app(FunctionFinder::class)->pages(app(HelpSearch::class)->prepare('Stechuhr', 'de'), 10);

        $this->assertSame('attendance.index', $pages[0]['route'] ?? null);
        $this->assertSame('Stechuhr', $pages[0]['keyword']);
        $this->assertSame(route('attendance.index'), app(FunctionFinder::class)->pagesByTopic()['attendance.manage']['url'] ?? null);
    }

    public function test_pages_without_access_stay_out(): void {
        $this->actingAs(User::factory()->user()->create(['organization_id' => $this->organization->id]));
        $this->assertNotContains('holidays.index', $this->pageRoutes('Feiertage'));

        $this->actingAs(User::factory()->admin()->create(['organization_id' => $this->organization->id]));
        $this->assertContains('holidays.index', $this->pageRoutes('Feiertage'));
    }

    public function test_theme_action_is_found_by_everyday_words(): void {
        $this->actingAs(User::factory()->user()->create(['organization_id' => $this->organization->id]));
        $search = app(HelpSearch::class);
        $finder = app(FunctionFinder::class);

        foreach (['Darkmode', 'dark mode', 'Nachtmodus', 'Farbschema'] as $query) {
            $this->assertSame(['theme'], array_column($finder->actions($search->prepare($query, 'de')), 'key'), $query);
        }
        $this->assertSame([], $finder->actions($search->prepare('Rechnung', 'de')));
    }

    public function test_palette_puts_actions_pages_and_help_before_records(): void {
        $this->makeTopic('navigation.interface', ['title' => 'Bedienoberfläche und Darstellung', 'keywords' => ['Darkmode']]);
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($user)->getJson(route('api.internal.search', ['q' => 'Darkmode']))->assertOk();

        $groups = collect($response->json('groups'));
        $this->assertSame('actions', $groups->first()['key']);
        $this->assertSame('theme', $groups->first()['items'][0]['action']);
        $help = $groups->firstWhere('key', 'help');
        $this->assertSame(route('help.center.show', ['topic' => 'navigation.interface']), $help['items'][0]['url']);

        $pages = collect($this->actingAs($user)->getJson(route('api.internal.search', ['q' => 'Stempeluhr']))->json('groups'))->firstWhere('key', 'pages');
        $this->assertSame(route('attendance.index'), $pages['items'][0]['url']);
    }

    public function test_help_center_search_shows_matching_pages_and_jump_targets(): void {
        $this->makeTopic('attendance.manage', ['title' => 'Anwesenheit erfassen', 'keywords' => ['Stechuhr']]);
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($user)->get(route('help.center.index', ['q' => 'Stechuhr']));

        $response->assertOk()
            ->assertSee(__('Passende Seiten'))
            ->assertSee(route('attendance.index'), false)
            ->assertSee(__('Gefunden über „:keyword“', ['keyword' => 'Stechuhr']))
            ->assertSee(__('Zur Seite'));
    }

    /** Dialogseiten (Profil …) öffnen im Dialog-Host, nicht als eingebettete Seite. */
    public function test_dialog_pages_are_marked_for_the_dialog_host(): void {
        $this->makeTopic('account.profile', ['title' => 'Profil und Konto', 'keywords' => ['Darkmode']]);
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $profile = route('account.profile.edit');

        $items = collect(collect($this->actingAs($user)->getJson(route('api.internal.search', ['q' => 'Profil bearbeiten']))->json('groups'))
            ->firstWhere('key', 'pages')['items'] ?? []);
        $this->assertTrue($items->firstWhere('url', $profile)['modal'] ?? null);
        $stamp = collect(collect($this->actingAs($user)->getJson(route('api.internal.search', ['q' => 'Stempeluhr']))->json('groups'))
            ->firstWhere('key', 'pages')['items'] ?? []);
        $this->assertFalse($stamp->firstWhere('url', route('attendance.index'))['modal'] ?? null);

        $html = $this->actingAs($user)->get(route('help.center.index', ['q' => 'Darkmode']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/href="' . preg_quote($profile, '/') . '"[^>]*data-entry-modal-trigger/', (string) $html);

        $html = $this->actingAs($user)->get(route('help.center.index', ['q' => 'Profil bearbeiten']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/href="' . preg_quote($profile, '/') . '"\s+data-entry-modal-trigger/', (string) $html);
    }
}
