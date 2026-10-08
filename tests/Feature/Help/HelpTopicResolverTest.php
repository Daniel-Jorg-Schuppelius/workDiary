<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpTopicResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Help;

use App\Models\Platform\{HelpTopic, User};
use App\Services\Help\HelpTopicResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpTopicResolverTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    public function test_resolver_falls_back_from_en_to_de_when_topic_missing(): void {
        HelpTopic::query()->create([
            'topic' => 'sample.x',
            'locale' => 'de',
            'title' => 'DE-Titel',
            'audience' => [],
            'version' => 1,
            'body_md' => 'DE Body',
            'body_html' => '<p>DE Body</p>',
            'related' => [],
        ]);

        $resolver = app(HelpTopicResolver::class);
        $found = $resolver->find('sample.x', null, 'en');

        $this->assertNotNull($found);
        $this->assertSame('de', $found->locale);
    }

    public function test_resolver_filters_by_audience_role(): void {
        HelpTopic::query()->create([
            'topic' => 'admin.only',
            'locale' => 'de',
            'title' => 'Admin only',
            'audience' => ['admin'],
            'version' => 1,
            'body_md' => 'A',
            'body_html' => '<p>A</p>',
            'related' => [],
        ]);

        $resolver = app(HelpTopicResolver::class);

        $regularUser = User::factory()->user()->create();
        $adminUser = User::factory()->admin()->create();

        $this->assertNull($resolver->find('admin.only', $regularUser, 'de'));
        $this->assertNotNull($resolver->find('admin.only', $adminUser, 'de'));
    }

    public function test_resolver_topic_without_audience_is_visible_for_everyone(): void {
        HelpTopic::query()->create([
            'topic' => 'open.help',
            'locale' => 'de',
            'title' => 'Open',
            'audience' => [],
            'version' => 1,
            'body_md' => 'A',
            'body_html' => '<p>A</p>',
            'related' => [],
        ]);

        $resolver = app(HelpTopicResolver::class);

        $this->assertNotNull($resolver->find('open.help', null, 'de'));
    }
}
