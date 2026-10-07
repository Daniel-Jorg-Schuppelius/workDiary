<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DialogRedirectAsJsonTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Enums\Integration\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * UI-Fuzz 2026-09-21: Dialog-Formulare verloren jede Rückmeldung, weil fetch
 * dem Redirect folgte und dessen GET den Flash verbrauchte — besonders
 * schmerzhaft beim nur einmal sichtbaren Webhook-Schlüssel.
 */
class DialogRedirectAsJsonTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const DIALOG = ['X-Entry-Dialog' => '1', 'Accept' => 'application/json'];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        Route::middleware('web')->post('/__dialog-test/errors', fn () => back()->withErrors(['name' => 'Name fehlt.']));
        Route::middleware('web')->post('/__dialog-test/away', fn () => redirect()->away('https://auth.example.com/authorize'));
        Route::middleware('web')->post('/__dialog-test/rotate', fn () => redirect('/__dialog-test/dialog')
            ->with('dialog_token', 'abc')->with('success', 'Erneuert.'));
    }

    public function test_dialog_redirect_becomes_json_and_keeps_the_flash_for_the_target_page(): void {
        $admin = $this->orgAdmin();

        $this->actingAs($admin)
            ->withHeaders(self::DIALOG)
            ->post(route('admin.webhooks.store'), [
                'label' => 'ERP',
                'url' => 'https://example.test/hooks',
                'events' => [WebhookEvent::OpenIssueAssigned->value],
                'active' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('redirect', route('admin.webhooks.index'));

        $secret = session('webhook_secret');
        $this->assertIsString($secret);

        // Die Zielseite, zu der der Dialog navigiert, zeigt den Schlüssel.
        $this->actingAs($admin)->get(route('admin.webhooks.index'))->assertSee($secret);
    }

    public function test_back_with_errors_becomes_422_for_the_dialog_toast(): void {
        $this->withHeaders(self::DIALOG)
            ->post('/__dialog-test/errors')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Name fehlt.')
            ->assertJsonPath('errors.name.0', 'Name fehlt.');
    }

    /** Issue #106: ein Folgedialog kehrt in die Ursprungsmaske zurück — die Meldung gehört in den Toast. */
    public function test_stacked_dialog_stays_and_takes_the_message_out_of_the_session(): void {
        $this->withHeaders(self::DIALOG + ['X-Entry-Dialog-Stacked' => '1'])
            ->post('/__dialog-test/rotate')
            ->assertOk()
            ->assertJsonPath('stay', true)
            ->assertJsonPath('messages.0.tone', 'success')
            ->assertJsonPath('messages.0.message', 'Erneuert.');

        $this->assertFalse(session()->has('success'), 'sonst erschiene die Meldung ein zweites Mal auf der nächsten Seite');
        $this->assertSame('abc', session('dialog_token'));
    }

    public function test_redirect_onto_the_own_dialog_reloads_it_instead_of_navigating(): void {
        $this->withHeaders(self::DIALOG + ['X-Entry-Dialog-Url' => url('/__dialog-test/dialog?ki=1')])
            ->post('/__dialog-test/rotate')
            ->assertOk()
            ->assertJsonPath('stay', true)
            ->assertJsonPath('redirect', url('/__dialog-test/dialog'));
    }

    public function test_redirect_to_another_page_keeps_the_flash_for_it(): void {
        $this->withHeaders(self::DIALOG + ['X-Entry-Dialog-Url' => '/__dialog-test/other'])
            ->post('/__dialog-test/rotate')
            ->assertOk()
            ->assertJsonMissingPath('stay');

        $this->assertSame('Erneuert.', session('success'));
    }

    public function test_external_redirect_and_plain_forms_stay_untouched(): void {
        $this->withHeaders(self::DIALOG)->post('/__dialog-test/away')
            ->assertRedirect('https://auth.example.com/authorize');

        $this->flushHeaders()->post('/__dialog-test/errors')->assertRedirect();
    }
}
