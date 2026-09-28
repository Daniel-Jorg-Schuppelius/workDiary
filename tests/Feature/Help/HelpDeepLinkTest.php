<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpDeepLinkTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Help;

use App\Enums\Help\HelpDeepLink;
use App\Models\Platform\{HelpTopic, User};
use App\Services\Help\HelpTopicLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{File, Route};
use Tests\TestCase;

/** MVP-972: feste Anker, Hilfe-Links der Fehlerseiten, „Zuletzt angesehen“. */
final class HelpDeepLinkTest extends TestCase {
    use RefreshDatabase;

    public function test_fixed_heading_anchor_is_the_same_in_every_language(): void {
        $root = sys_get_temp_dir() . '/help-anchor-' . uniqid('', true);
        File::makeDirectory($root . '/de', 0755, true);
        File::makeDirectory($root . '/en', 0755, true);
        File::put($root . '/de/x.errors.md', "---\ntitle: X\n---\n\n## Zugriff verweigert {#forbidden}\n\nText\n\n## Ohne Anker\n");
        File::put($root . '/en/x.errors.md', "---\ntitle: X\n---\n\n## Access denied {#forbidden}\n\nText\n");

        try {
            $loader = new HelpTopicLoader($root);
            $de = $loader->load('x.errors', 'de');
            $en = $loader->load('x.errors', 'en');
        } finally {
            File::deleteDirectory($root);
        }

        $this->assertStringContainsString('<h2 id="sec-forbidden">Zugriff verweigert</h2>', (string) $de['body_html']);
        $this->assertStringContainsString('<h2 id="sec-forbidden">Access denied</h2>', (string) $en['body_html']);
        $this->assertSame(['level' => 2, 'text' => 'Zugriff verweigert', 'anchor' => 'sec-forbidden'], $de['headings'][0]);
        $this->assertSame('sec-ohne-anker', $de['headings'][1]['anchor']);
    }

    public function test_every_error_link_anchor_exists_in_every_language(): void {
        $loader = new HelpTopicLoader(HelpTopicLoader::defaultPath());
        foreach (['de', 'en', 'fr', 'es', 'it'] as $locale) {
            $topic = $loader->load(HelpDeepLink::TOPIC, $locale);
            $this->assertNotNull($topic, $locale);
            $anchors = array_column($topic['headings'], 'anchor');
            foreach (HelpDeepLink::cases() as $link) {
                $this->assertContains($link->anchor(), $anchors, "{$locale}: {$link->value}");
            }
        }
    }

    public function test_error_pages_link_the_matching_help_section_for_signed_in_users(): void {
        Route::middleware('web')->get('/_test/forbidden', static fn () => abort(403));
        $user = User::factory()->user()->create();
        $href = route('help.center.show', ['topic' => HelpDeepLink::TOPIC]) . '#sec-forbidden';

        $this->actingAs($user)->get('/_test/forbidden')->assertForbidden()
            ->assertSeeText(__('Was bedeutet das?'))
            ->assertSee($href, false);

        auth()->logout();
        $this->get('/_test/forbidden')->assertForbidden()->assertDontSeeText(__('Was bedeutet das?'));
    }

    public function test_help_center_offers_the_recent_list_and_marks_the_article(): void {
        HelpTopic::query()->create([
            'topic' => 'help.errors', 'locale' => 'de', 'title' => 'Fehlermeldungen verstehen', 'audience' => [], 'modules' => [],
            'version' => 1, 'body_md' => 'x', 'body_html' => '<p>x</p>', 'related' => [], 'headings' => [],
        ]);
        $user = User::factory()->user()->create();

        $this->actingAs($user)->get(route('help.center.index'))->assertOk()
            ->assertSee('data-help-recent', false)
            ->assertSee(route('help.center.show', ['topic' => 'recent.placeholder']), false);
        $this->actingAs($user)->get(route('help.center.show', ['topic' => 'help.errors']))->assertOk()
            ->assertSee('data-help-center-topic="help.errors"', false);
    }
}
