<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PluginContributionsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins;

use App\Enums\Search\SearchSourceType;
use App\Models\Platform\{PluginSetting, User};
use App\Models\Plugins\Lexoffice\LexofficeArticle;
use App\Plugins\PluginManager;
use App\Plugins\RemoteSupport\RemoteSupportPlugin;
use App\Services\Diagnostics\ConnectionHealthModels;
use App\Services\Domain\DomainProviderResolver;
use App\Services\Org\OrganizationFileTables;
use App\Services\Search\ActivitySearchVisibility;
use App\Services\Stammdaten\IdentifierAuditModels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Betriebsbeiträge der Plugins (MVP-1043/1044): Der Kern nennt keine
 * Plugin-Klassen mehr, die Plugins tragen sich beim Booten ein.
 */
class PluginContributionsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_plugins_register_their_operational_contributions(): void {
        $connections = app(ConnectionHealthModels::class);
        foreach (['email', 'cti', 'carrier', 'msgraph', 'sharepoint', 'webdav', 'caldav', 'carddav', 'google_calendar'] as $key) {
            $this->assertArrayHasKey($key, $connections->all(), $key);
        }
        // Betriebsaufgabe bei Störung wie bisher nur für diese Teilmenge.
        $this->assertSame(['email', 'cti', 'carrier', 'webdav', 'caldav'], array_values(array_intersect(
            ['email', 'cti', 'carrier', 'webdav', 'caldav', 'msgraph', 'carddav'],
            array_keys($connections->withOperationsTask()),
        )));
        $this->assertArrayNotHasKey('msgraph', $connections->withOperationsTask());

        $this->assertContains(LexofficeArticle::class, app(IdentifierAuditModels::class)->all());
        $this->assertSame(['path' => 'file_path'], app(OrganizationFileTables::class)->all()['lexoffice_vouchers'] ?? null);
        $this->assertNotNull(RateLimiter::limiter('todoist-webhook'));
        $this->assertNotNull(RateLimiter::limiter('lexoffice-webhook'));
    }

    public function test_domain_registrar_is_found_by_contract(): void {
        $this->setUpOrganization();
        $resolver = app(DomainProviderResolver::class);

        $this->assertSame('domainreselling', $resolver->pluginId());
        $settings = $resolver->settings((int) $this->organization->id);
        $this->assertSame(100, $settings->listPageSize);
        $this->assertSame(24, $settings->staleAfterHours);
    }

    public function test_remote_sessions_are_searchable_only_with_an_active_provider(): void {
        $this->setUpOrganization();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $visibility = app(ActivitySearchVisibility::class);

        $this->assertNotContains(SearchSourceType::RemoteSession, $visibility->types($admin));

        PluginSetting::query()->create(['organization_id' => $this->organization->id, 'plugin_id' => RemoteSupportPlugin::ID, 'enabled' => true, 'settings' => []]);
        app(PluginManager::class)->flushRuntimeCaches();

        $this->assertContains(SearchSourceType::RemoteSession, $visibility->types($admin));

        // Treffer führt in die Sitzungsliste des Plugins (MVP-1046).
        $document = (new \App\Models\Search\SearchDocument)->forceFill(['source_type' => SearchSourceType::RemoteSession, 'source_id' => 1]);
        $urls = app(\App\Services\Search\SearchResultLinker::class)->urls(collect([$document]));
        $this->assertSame(route('admin.remote-support.pending.index'), $urls[SearchSourceType::RemoteSession->value . ':1'] ?? null);
    }

    public function test_plugins_contribute_to_module_extension_points(): void {
        $modules = app(\App\Modules\ModuleRegistry::class);

        $this->assertSame([
            \App\Plugins\CalDav\Services\CalDavImportFeed::class,
            \App\Plugins\GoogleCalendar\Services\GoogleCalendarImportFeed::class,
            \App\Plugins\Msgraph\Services\MsgraphCalendarImportFeed::class,
        ], array_values(array_intersect($modules->extensions(\App\Services\Import\Contracts\CalendarImportFeed::class), [
            \App\Plugins\CalDav\Services\CalDavImportFeed::class,
            \App\Plugins\GoogleCalendar\Services\GoogleCalendarImportFeed::class,
            \App\Plugins\Msgraph\Services\MsgraphCalendarImportFeed::class,
        ])));
        $this->assertContains(\App\Plugins\RemoteSupport\Import\RemoteSessionSpec::class, $modules->extensions(\App\Services\Import\EntitySpec::class));
        $this->assertContains(\App\Plugins\Fritzbox\FritzboxCallReportMailHandler::class, $modules->extensions(\App\Services\Mail\Contracts\MailIntakeHandler::class));

        $this->expectException(\InvalidArgumentException::class);
        $modules->contribute(\App\Services\Import\EntitySpec::class, self::class);
    }
}
