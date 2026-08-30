<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProblemReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Support;

use App\Enums\Support\{ProblemReportDeliveryTarget, ProblemReportStatus};
use App\Mail\ProblemReportForwardMail;
use App\Models\{ProblemReport, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Mail, Storage};
use Tests\TestCase;

class ProblemReportTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array {
        return array_merge([
            'summary' => 'Kalender friert ein',
            'description' => 'Beim Klick auf einen Tag passiert nichts mehr.',
            'severity' => 'high',
            'contact_ok' => '1',
            'context_route' => 'schedule.index',
            // Eigener Ursprung: eine fremde Adresse führt seit dem
            // Sicherheitsscan 2026-08-23 (S-19) zurück zur Meldungsliste.
            'context_url' => '/schedule',
            'context_topic' => 'duties.overview',
        ], $overrides);
    }

    public function test_create_returns_standalone_page_on_full_navigation(): void {
        // Klick auf „Problem melden" auf einer standalone Fehlerseite löst eine
        // volle Seitennavigation aus (kein Dialog-Host): die Antwort muss eine
        // eigenständige, gestylte HTML-Seite sein — nicht das nackte Fragment.
        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->get(route('problem-reports.create', ['code' => 500]));

        $response->assertOk();
        $response->assertViewIs('problem-reports.create');
        $response->assertSee('<!DOCTYPE html>', false);
    }

    public function test_create_returns_bare_fragment_for_dialog_host(): void {
        // Der Dialog-Host lädt dieselbe Route per AJAX mit ?dialog=1 — dann nur
        // das eingebettete Modal-Fragment ohne eigenes HTML-Gerüst.
        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->get(route('problem-reports.create', ['dialog' => 1]));

        $response->assertOk();
        $response->assertViewIs('problem-reports._form_dialog');
        $response->assertDontSee('<!DOCTYPE html>', false);
    }

    public function test_user_can_file_report_with_reference_and_context(): void {
        $user = User::factory()->user()->create();

        $response = $this->actingAs($user)->post(route('problem-reports.store'), $this->payload());

        $response->assertRedirect('/schedule');
        $report = ProblemReport::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^PR-\d{4}-\d{4}$/', $report->reference_no);
        $this->assertSame('schedule.index', $report->page_context['route']);
        $this->assertSame((string) config('app.version'), $report->page_context['app_version']);
        $this->assertNotEmpty($report->page_context['request_id']);
        $this->assertSame(ProblemReportStatus::New_, $report->status);
        // Default saas_inbox: sofort als zugestellt markiert.
        $this->assertSame(ProblemReportDeliveryTarget::SaasInbox, $report->delivery_target);
        $this->assertNotNull($report->delivered_at);
    }

    public function test_fremde_ruecksprungadresse_wird_verworfen(): void {
        // Das Feld ist ein verstecktes Formularfeld und damit frei setzbar —
        // ohne Prüfung war der Melde-Dialog eine offene Weiterleitung.
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('problem-reports.store'), $this->payload(['context_url' => 'https://angreifer.example/phish']))
            ->assertRedirect(route('problem-reports.index'));
    }

    public function test_guest_cannot_file_report(): void {
        $this->post(route('problem-reports.store'), $this->payload())
            ->assertRedirect(route('login'));
    }

    public function test_diagnostics_are_included_only_on_opt_in_and_are_redacted(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)->post(route('problem-reports.store'), $this->payload());
        $this->assertNull(ProblemReport::query()->firstOrFail()->diagnostic_excerpt);

        $this->actingAs($user)->post(route('problem-reports.store'), $this->payload([
            'include_diagnostics' => '1',
            'summary' => 'Zweiter Fall',
        ]));

        $report = ProblemReport::query()->latest('id')->firstOrFail();
        $excerpt = $report->diagnostic_excerpt;
        $this->assertIsArray($excerpt);
        $this->assertArrayHasKey('health', $excerpt);
        $encoded = json_encode($excerpt);
        // Redaktionsregeln des Supportberichts: keine Secrets/Passwörter.
        $this->assertStringNotContainsString('APP_KEY', (string) $encoded);
        $this->assertStringNotContainsString('DB_PASSWORD', (string) $encoded);
        $this->assertSame($user->id, $report->diagnostics_approved_by);
    }

    public function test_error_request_id_flows_from_error_page_into_report(): void {
        // Fehlerseiten-Flow: rid (Request-ID des FEHLGESCHLAGENEN Requests)
        // muss vom Link über das Formular bis in page_context durchgereicht
        // werden — sonst zeigt der Diagnose-Auszug die Logzeilen des
        // Melde-Requests statt der des Fehlers.
        $user = User::factory()->user()->create();
        $rid = '01KXPAQN560C6FEMMS77B4D3GG';

        $this->actingAs($user)
            ->get(route('problem-reports.create', ['context' => 'error', 'code' => 500, 'rid' => $rid]))
            ->assertOk()
            ->assertSee('name="context_request_id"', false)
            ->assertSee($rid);

        $this->actingAs($user)->post(route('problem-reports.store'), $this->payload([
            'context_request_id' => $rid,
            'include_diagnostics' => '1',
        ]));

        $report = ProblemReport::query()->firstOrFail();
        $this->assertSame($rid, $report->page_context['error_request_id']);
    }

    public function test_invalid_error_request_id_is_ignored(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('problem-reports.create', ['rid' => 'zu-kurz']))
            ->assertOk()
            ->assertDontSee('context_request_id');
    }

    public function test_never_mode_ignores_opt_in(): void {
        \App\Support\Setting::set('support.problem_reports.diagnostics', 'never', \App\Settings\SettingScope::System);
        $user = User::factory()->user()->create();

        $this->actingAs($user)->post(route('problem-reports.store'), $this->payload(['include_diagnostics' => '1']));

        $this->assertNull(ProblemReport::query()->firstOrFail()->diagnostic_excerpt);
    }

    public function test_mail_delivery_queues_forward_mail(): void {
        Mail::fake();
        \App\Support\Setting::set('support.problem_reports.delivery', 'mail', \App\Settings\SettingScope::System);
        \App\Support\Setting::set('support.problem_reports.mail_to', 'support@example.test', \App\Settings\SettingScope::System);
        $user = User::factory()->user()->create();

        $this->actingAs($user)->post(route('problem-reports.store'), $this->payload());

        Mail::assertQueued(ProblemReportForwardMail::class);
        $this->assertNotNull(ProblemReport::query()->firstOrFail()->delivered_at);
    }

    public function test_mail_delivery_without_address_records_error_but_keeps_report(): void {
        Mail::fake();
        \App\Support\Setting::set('support.problem_reports.delivery', 'mail', \App\Settings\SettingScope::System);
        $user = User::factory()->user()->create();

        $this->actingAs($user)->post(route('problem-reports.store'), $this->payload());

        $report = ProblemReport::query()->firstOrFail();
        $this->assertNull($report->delivered_at);
        $this->assertNotNull($report->delivery_error);
        Mail::assertNothingQueued();
    }

    public function test_screenshot_attachment_is_stored(): void {
        Storage::fake('local');
        $user = User::factory()->user()->create();

        $this->actingAs($user)->post(route('problem-reports.store'), $this->payload([
            'screenshots' => [UploadedFile::fake()->image('fehler.png')],
        ]));

        $report = ProblemReport::query()->firstOrFail();
        $this->assertSame(1, $report->attachments()->count());
        $this->assertSame('fehler.png', $report->attachments()->first()?->original_name);
    }

    public function test_reporter_sees_only_own_reports(): void {
        $reporter = User::factory()->user()->create();
        $other = User::factory()->user()->create();

        $this->actingAs($reporter)->post(route('problem-reports.store'), $this->payload());

        $this->actingAs($other)->get(route('problem-reports.index'))
            ->assertOk()
            ->assertDontSee('Kalender friert ein');
        $this->actingAs($reporter)->get(route('problem-reports.index'))
            ->assertOk()
            ->assertSee('Kalender friert ein');
    }

    public function test_admin_inbox_requires_permission_and_lists_reports(): void {
        $reporter = User::factory()->user()->create();
        $this->actingAs($reporter)->post(route('problem-reports.store'), $this->payload());

        $this->actingAs($reporter)->get(route('admin.problem-reports.index'))->assertForbidden();

        // Inbox ist org-gescoped: der Admin derselben Organisation sieht sie.
        $admin = User::factory()->admin()->create(['organization_id' => $reporter->organization_id]);
        $this->actingAs($admin)->get(route('admin.problem-reports.index'))
            ->assertOk()
            ->assertSee('Kalender friert ein');
    }

    public function test_admin_can_update_status_and_download_export(): void {
        $reporter = User::factory()->user()->create();
        $this->actingAs($reporter)->post(route('problem-reports.store'), $this->payload());
        $report = ProblemReport::query()->firstOrFail();
        $admin = User::factory()->admin()->create(['organization_id' => $reporter->organization_id]);

        $this->actingAs($admin)
            ->put(route('admin.problem-reports.status', $report), ['status' => 'in_review'])
            ->assertRedirect();
        $this->assertSame(ProblemReportStatus::InReview, $report->fresh()?->status);

        $download = $this->actingAs($admin)->get(route('admin.problem-reports.download', $report));
        $download->assertOk();
        $this->assertStringContainsString($report->reference_no, $download->streamedContent());
    }

    public function test_operator_notification_is_sent_on_new_report(): void {
        $admin = User::factory()->admin()->create();
        $reporter = User::factory()->user()->create();

        $this->actingAs($reporter)->post(route('problem-reports.store'), $this->payload());

        $this->assertSame(1, $admin->notifications()->count());
    }
}
