<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpShortcutsTopicTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Help;

use App\Services\Help\HelpTopicReindexer;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Tastenkürzel-Übersicht (Feature 037, MVP-721; Vollscan G16): Help-Topic
 * `account.shortcuts` in allen aktivierten Sprachen, JSON-Endpunkt, und das
 * Layout liefert Dialog + Ziel-Attribute der Navigations-Kürzel (nur mit Recht).
 */
class HelpShortcutsTopicTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_topic_exists_in_every_enabled_locale(): void {
        foreach (Locales::enabledCodes() as $locale) {
            $path = resource_path("help/{$locale}/account.shortcuts.md");
            $this->assertFileExists($path);
            $this->assertStringContainsString('topic: account.shortcuts', (string) file_get_contents($path));
        }
    }

    public function test_json_endpoint_serves_the_topic(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        app(HelpTopicReindexer::class)->reindex();

        $response = $this->actingAs($this->orgUser())->getJson(route('help.topics.show', ['topic' => 'account.shortcuts']));

        $response->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('topic', 'account.shortcuts');
        $this->assertNotSame('', (string) $response->json('title'));
        $this->assertStringContainsString('<code>', (string) $response->json('body_html'), 'Kürzel werden als Code-Tasten dargestellt.');
    }

    public function test_layout_exposes_dialog_and_navigation_targets_for_permitted_routes(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $response = $this->actingAs($this->orgAdmin())->get(route('customers.index'));

        $response->assertOk()
            ->assertSee('id="shortcuts-dialog"', false)
            ->assertSee('data-help-topic="account.shortcuts"', false)
            ->assertSee('data-shortcut-customers="' . route('customers.index') . '"', false)
            ->assertSee('data-shortcut-projects="' . route('projects.index') . '"', false)
            ->assertSee('data-shortcut-diary="' . route('diary.index') . '"', false)
            ->assertSee('data-shortcut-new-entry="' . route('diary.create') . '"', false);
    }
}
