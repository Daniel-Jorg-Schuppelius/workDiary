<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SidebarNewsFeedTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Services\UI\SidebarNewsFeedService;
use App\Settings\SettingScope;
use App\Support\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Blade, Cache};
use RuntimeException;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class SidebarNewsFeedTest extends TestCase {
    use RefreshDatabase;

    private const FEED_URL = 'https://news.example.test/feed.xml';

    protected function setUp(): void {
        parent::setUp();
        Cache::forget(SidebarNewsFeedService::CACHE_KEY);
        config([
            'ui.news_feed.enabled' => true,
            'ui.news_feed.url' => self::FEED_URL,
            'ui.news_feed.max_items' => 5,
            'ui.news_feed.rotation_seconds' => 15,
        ]);
    }

    public function test_refresh_normalizes_rss_and_removes_feed_markup(): void {
        FakePluginHttp::fake([
            self::FEED_URL => FakePluginHttp::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <rss version="2.0">
                  <channel>
                    <title>WorkDiary Aktuell</title>
                    <item>
                      <title><![CDATA[<b>Version 2.4</b> &amp; bereit]]></title>
                      <link>https://workdiary.example.test/releases/2-4</link>
                      <pubDate>Fri, 31 Jul 2026 08:00:00 +0200</pubDate>
                    </item>
                    <item>
                      <title>Unsicheres Ziel</title>
                      <link>javascript:alert(1)</link>
                    </item>
                  </channel>
                </rss>
                XML, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $service = app(SidebarNewsFeedService::class);

        $this->assertSame(1, $service->refresh());
        $this->assertSame([
            [
                'title' => 'Version 2.4 & bereit',
                'url' => 'https://workdiary.example.test/releases/2-4',
                'source' => 'WorkDiary Aktuell',
                'published_at' => '2026-07-31T08:00:00+02:00',
            ],
        ], $service->items());
    }

    public function test_refresh_supports_atom_default_namespace(): void {
        FakePluginHttp::fake([
            self::FEED_URL => FakePluginHttp::response(<<<'XML'
                <?xml version="1.0" encoding="utf-8"?>
                <feed xmlns="http://www.w3.org/2005/Atom">
                  <title>Heise Online</title>
                  <entry>
                    <title>Ein ruhiger Testbeitrag</title>
                    <link rel="alternate" href="https://www.heise.de/test-1" />
                    <updated>2026-07-31T09:30:00+02:00</updated>
                  </entry>
                </feed>
                XML, 200, ['Content-Type' => 'application/atom+xml']),
        ]);

        $service = app(SidebarNewsFeedService::class);

        $this->assertSame(1, $service->refresh());
        $this->assertSame('Heise Online', $service->items()[0]['source']);
        $this->assertSame('https://www.heise.de/test-1', $service->items()[0]['url']);
    }

    public function test_failed_refresh_keeps_last_successful_payload(): void {
        FakePluginHttp::fake([self::FEED_URL => FakePluginHttp::response($this->rss('Erster Stand'), 200)]);
        $service = app(SidebarNewsFeedService::class);
        $service->refresh();

        FakePluginHttp::fake([self::FEED_URL => FakePluginHttp::response('Ausfall', 503)]);

        try {
            $service->refresh();
            $this->fail('Der fehlgeschlagene Abruf muss eine Exception auslösen.');
        } catch (RuntimeException) {
            $this->assertSame('Erster Stand', $service->items()[0]['title']);
        }
    }

    public function test_private_feed_target_is_rejected_before_http_request(): void {
        config(['ui.news_feed.url' => 'http://127.0.0.1/internal.xml']);
        $fake = FakePluginHttp::fake();

        try {
            app(SidebarNewsFeedService::class)->refresh();
            $this->fail('SSRF-Guard hat nicht gegriffen.');
        } catch (RuntimeException) {
            $fake->assertNothingSent();
        }
    }

    public function test_system_settings_accept_http_feed_and_reject_other_schemes(): void {
        Setting::set('ui.news_feed.url', self::FEED_URL, SettingScope::System);
        Setting::set('ui.news_feed.enabled', true, SettingScope::System);

        $this->assertTrue(app(SidebarNewsFeedService::class)->isEnabled());

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        Setting::set('ui.news_feed.url', 'ftp://news.example.test/feed.xml', SettingScope::System);
    }

    public function test_external_xml_entities_are_never_resolved(): void {
        FakePluginHttp::fake([
            self::FEED_URL => FakePluginHttp::response(<<<'XML'
                <?xml version="1.0"?>
                <!DOCTYPE rss [<!ENTITY secret SYSTEM "file:///etc/passwd">]>
                <rss version="2.0">
                  <channel>
                    <title>Untrusted</title>
                    <item>
                      <title>&secret;</title>
                      <link>https://news.example.test/item</link>
                    </item>
                  </channel>
                </rss>
                XML),
        ]);

        $this->expectException(RuntimeException::class);
        app(SidebarNewsFeedService::class)->refresh();
    }

    public function test_help_drawer_renders_separate_accessible_news_controls(): void {
        FakePluginHttp::fake([self::FEED_URL => FakePluginHttp::response($this->rss('Neue Funktionen im WorkDiary'), 200)]);
        app(SidebarNewsFeedService::class)->refresh();

        $html = Blade::render('<x-help-drawer />');

        $this->assertStringContainsString('data-help-news', $html);
        $this->assertStringContainsString('class="wd-help-news-title"', $html);
        $this->assertStringContainsString('aria-label="Neuigkeit von WorkDiary: Neue Funktionen im WorkDiary"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('data-help-news-toggle', $html);
        $this->assertStringNotContainsString('<button type="button" class="absolute inset-y-0', $html);
    }

    private function rss(string $title): string {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <rss version="2.0">
              <channel>
                <title>WorkDiary</title>
                <item>
                  <title>{$title}</title>
                  <link>https://workdiary.example.test/news/1</link>
                </item>
                <item>
                  <title>Zweiter Beitrag</title>
                  <link>https://workdiary.example.test/news/2</link>
                </item>
              </channel>
            </rss>
            XML;
    }
}
