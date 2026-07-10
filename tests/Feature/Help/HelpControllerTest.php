<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Help;

use App\Models\{HelpTopic, HelpView, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpControllerTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_show_returns_topic_payload_and_logs_anonymous_view(): void {
        HelpTopic::query()->create([
            'topic' => 'sample.show',
            'locale' => 'de',
            'title' => 'Beispiel',
            'audience' => [],
            'version' => 1,
            'body_md' => 'Body',
            'body_html' => '<p>Body</p>',
            // 'sample.missing' existiert nicht und darf nicht ausgeliefert
            // werden (keine toten Links / rohen Slugs in der UI).
            'related' => ['sample.edit', 'sample.missing'],
        ]);
        HelpTopic::query()->create([
            'topic' => 'sample.edit',
            'locale' => 'de',
            'title' => 'Beispiel bearbeiten',
            'audience' => [],
            'version' => 1,
            'body_md' => 'Edit',
            'body_html' => '<p>Edit</p>',
            'related' => [],
        ]);

        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->getJson(route('help.topics.show', ['topic' => 'sample.show']));

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('topic', 'sample.show')
            ->assertJsonPath('title', 'Beispiel')
            ->assertJsonPath('body_html', '<p>Body</p>')
            ->assertJsonPath('related.0.topic', 'sample.edit')
            ->assertJsonPath('related.0.title', 'Beispiel bearbeiten')
            ->assertJsonCount(1, 'related');

        $view = HelpView::query()->where('topic', 'sample.show')->first();
        $this->assertNotNull($view);
        $this->assertSame($user->organization_id, $view->organization_id);
        $this->assertNull($view->was_helpful);
    }

    public function test_show_returns_404_for_unknown_topic(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->getJson(route('help.topics.show', ['topic' => 'does-not.exist']))
            ->assertNotFound()
            ->assertJsonPath('found', false);
    }

    public function test_search_returns_matches(): void {
        HelpTopic::query()->create([
            'topic' => 'time-entries.start',
            'locale' => 'de',
            'title' => 'Zeiterfassung starten',
            'audience' => [],
            'version' => 1,
            'body_md' => 'Body',
            'body_html' => '<p>Body</p>',
            'related' => [],
        ]);

        $user = User::factory()->user()->create();
        $this->app->setLocale('de');

        $response = $this->actingAs($user)->getJson(route('help.search', ['q' => 'Zeiterfassung']));

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.topic', 'time-entries.start');
    }

    public function test_feedback_writes_anonymous_helpful_flag(): void {
        $user = User::factory()->user()->create();
        $this->app->setLocale('de');

        $response = $this->actingAs($user)->postJson(
            route('help.topics.feedback', ['topic' => 'time-entries.start']),
            ['helpful' => true]
        );

        $response->assertOk()->assertJsonPath('accepted', true);

        $view = HelpView::query()->where('topic', 'time-entries.start')->first();
        $this->assertNotNull($view);
        $this->assertTrue((bool) $view->was_helpful);
        $this->assertSame($user->organization_id, $view->organization_id);
    }
}
