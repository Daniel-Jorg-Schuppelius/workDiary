<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MsgraphAdminPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Plugins\Msgraph;

use App\Models\Platform\User;
use App\Plugins\Msgraph\Enums\MsgraphConnectionStatus;
use App\Plugins\Msgraph\Models\{MsgraphConnection, MsgraphContactConnection, MsgraphMailConnection, MsgraphTaskConnection};
use Dom\{Element, HTMLDocument};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Die Microsoft-365-Seite im verbundenen Zustand: jede Aktion ist ein Formular
 * mit eigenem Ziel und Absendeknopf, die Felder behalten ihre Namen
 * (Konsolidierungs-Audit 2026-10, k4-13: Knöpfe, Abzeichen und Karten laufen
 * über die Komponenten).
 */
final class MsgraphAdminPageTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->pluginSecret('msgraph', ['client_id' => 'test-client', 'client_secret' => 'test-secret']);
    }

    private function page(): HTMLDocument {
        $html = (string) $this->actingAs($this->admin)->get(route('admin.msgraph.index'))->assertOk()->getContent();

        return HTMLDocument::createFromString($html, LIBXML_NOERROR);
    }

    /** Absendeknopf des Formulars mit diesem Ziel. */
    private function submitOf(HTMLDocument $page, string $action): ?Element {
        foreach ($page->querySelectorAll('form') as $form) {
            if ($form->getAttribute('action') === $action) {
                return $form->querySelector('button[type="submit"]');
            }
        }

        return null;
    }

    public function test_connected_page_keeps_every_action_as_a_submitting_form(): void {
        FakePluginHttp::fake([
            'https://graph.microsoft.com/v1.0/me/calendars*' => FakePluginHttp::response(['value' => [['id' => 'cal-1', 'name' => 'Team']]]),
            'https://graph.microsoft.com/v1.0/me/todo/lists*' => FakePluginHttp::response(['value' => [['id' => 'list-1', 'displayName' => 'Aufgaben']]]),
        ]);
        $active = ['organization_id' => $this->organization->id, 'access_token' => 'secret-token', 'status' => MsgraphConnectionStatus::Active];
        MsgraphConnection::query()->create($active);
        MsgraphMailConnection::query()->create($active);
        MsgraphContactConnection::query()->create($active);
        MsgraphTaskConnection::query()->create($active);

        $page = $this->page();

        $actions = [
            'admin.msgraph.publish' => __('msgraph::msgraph.action.publish'),
            'admin.msgraph.disconnect' => __('msgraph::msgraph.action.disconnect'),
            'admin.msgraph.mail.settings' => __('msgraph::msgraph.action.save'),
            'admin.msgraph.mail.test' => __('msgraph::msgraph_mail.test.send'),
            'admin.msgraph.mail.disconnect' => __('msgraph::msgraph_mail.disconnect'),
            'admin.msgraph.contacts.disconnect' => __('msgraph::msgraph_contacts.disconnect'),
            'admin.msgraph.tasks.links.store' => __('msgraph::msgraph_tasks.link.add'),
            'admin.msgraph.tasks.disconnect' => __('msgraph::msgraph_tasks.disconnect'),
            'admin.msgraph.calendar.store' => __('msgraph::msgraph.action.save'),
            'admin.msgraph.adminconsent.start' => __('msgraph::msgraph.entra.consent'),
        ];
        foreach ($actions as $route => $label) {
            $button = $this->submitOf($page, route($route));
            $this->assertNotNull($button, "Formular oder Absendeknopf fehlt: $route");
            $this->assertStringContainsString('btn', (string) $button->getAttribute('class'), $route);
            // Symbolknöpfe tragen den Icon-Namen als Ligatur vor dem Text.
            $this->assertStringEndsWith($label, trim((string) $button->textContent), $route);
        }

        foreach (['from_address', 'save_to_sent_items', 'test_recipient', 'todo_list_id', 'project_id', 'sync_mode', 'calendar_id', 'teams_meetings', 'two_way'] as $field) {
            $this->assertNotNull($page->querySelector('[name="' . $field . '"]'), "Feld fehlt: $field");
        }

        // Der Zielkalender ist eine Karte, die selbst das Formular ist.
        $calendarForm = $this->submitOf($page, route('admin.msgraph.calendar.store'))?->closest('form');
        $this->assertNotNull($calendarForm);
        $this->assertStringContainsString('wd-card', (string) $calendarForm->getAttribute('class'));
        $this->assertNotNull($calendarForm->querySelector('input[name="_token"]'));

        $this->assertGreaterThanOrEqual(4, $page->querySelectorAll('.badge.badge-success')->length, 'Verbunden-Abzeichen je Abschnitt');
        $this->assertSame(0, $page->querySelectorAll('[data-oauth-popup]')->length, 'verbunden: kein Verbinden-Formular');
    }

    public function test_unconnected_page_offers_the_connect_forms(): void {
        $page = $this->page();

        foreach (['admin.msgraph.oauth.start', 'admin.msgraph.mail.oauth.start', 'admin.msgraph.contacts.oauth.start', 'admin.msgraph.tasks.oauth.start'] as $route) {
            $button = $this->submitOf($page, route($route));
            $this->assertNotNull($button, "Verbinden-Formular fehlt: $route");
            $this->assertNotNull($button->closest('form[data-oauth-popup]'), $route);
        }
        $this->assertSame(0, $page->querySelectorAll('.badge.badge-success')->length);
    }
}
