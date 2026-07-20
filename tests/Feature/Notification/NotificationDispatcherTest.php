<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationDispatcherTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Notification;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\Notification\NotificationRule;
use App\Models\{OpenIssue, User};
use App\Notifications\GenericEventNotification;
use App\Services\Notification\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $affected;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->affected = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function makeIssue(): OpenIssue {
        return OpenIssue::factory()->create([
            'organization_id' => $this->organization->id,
            'created_by_user_id' => $this->affected->id,
        ]);
    }

    private function dispatcher(): NotificationDispatcher {
        return app(NotificationDispatcher::class);
    }

    /** @return array{title: string, message: string, url: null} */
    private function payload(): array {
        return ['title' => 'Testpunkt', 'message' => 'Testnachricht', 'url' => null];
    }

    /**
     * Vollaudit 2026-07 (W3.2 — H12/M16/M31/N4): die zehn neuen Registry-
     * Events tragen Labels in allen Sprachen und die geplanten Default-Rollen.
     */
    public function test_vollaudit_events_are_registered_with_labels_and_roles(): void {
        $expected = [
            ['event' => \App\Enums\Notification\NotificationEvent::MonthClosureDecided, 'roles' => []],
            ['event' => \App\Enums\Notification\NotificationEvent::DomainExpiring, 'roles' => ['admin']],
            ['event' => \App\Enums\Notification\NotificationEvent::DomainTransferChanged, 'roles' => ['admin']],
            ['event' => \App\Enums\Notification\NotificationEvent::DomainSyncFailed, 'roles' => ['admin']],
            ['event' => \App\Enums\Notification\NotificationEvent::DomainHighRiskAction, 'roles' => ['admin']],
            ['event' => \App\Enums\Notification\NotificationEvent::FinanceTransferFailed, 'roles' => ['buchhaltung']],
            ['event' => \App\Enums\Notification\NotificationEvent::FinanceBankImportFailed, 'roles' => ['buchhaltung']],
            ['event' => \App\Enums\Notification\NotificationEvent::FinanceReconciliationReview, 'roles' => ['buchhaltung']],
            ['event' => \App\Enums\Notification\NotificationEvent::InvestmentDecisionDue, 'roles' => ['teamleitung', 'buchhaltung']],
            ['event' => \App\Enums\Notification\NotificationEvent::InvestmentDecided, 'roles' => []],
        ];

        foreach ($expected as $row) {
            $label = $row['event']->label();
            $this->assertNotSame('enums.notification.event.' . $row['event']->value, $label, 'Label fehlt: ' . $row['event']->value);
            $this->assertSame($row['roles'], $row['event']->defaultRecipientRoles(), 'Rollen: ' . $row['event']->value);
            $this->assertNotSame('', $row['event']->icon());
        }
    }

    public function test_default_rule_sends_in_app_and_mail_to_affected(): void {
        Notification::fake();

        $sent = $this->dispatcher()->notify(
            NotificationEvent::OpenIssueAssigned,
            $this->makeIssue(),
            $this->affected,
            $this->payload(),
        );

        $this->assertSame(1, $sent);
        Notification::assertSentTo(
            $this->affected,
            GenericEventNotification::class,
            fn(GenericEventNotification $n): bool => $n->channels === ['database', 'mail']
                && $n->event === NotificationEvent::OpenIssueAssigned,
        );
    }

    public function test_disabled_rule_suppresses_all_notifications(): void {
        NotificationRule::factory()
            ->forEvent(NotificationEvent::OpenIssueAssigned)
            ->create(['organization_id' => $this->organization->id, 'enabled' => false]);

        Notification::fake();

        $sent = $this->dispatcher()->notify(
            NotificationEvent::OpenIssueAssigned,
            $this->makeIssue(),
            $this->affected,
            $this->payload(),
        );

        $this->assertSame(0, $sent);
        Notification::assertNothingSent();
    }

    public function test_rule_channel_selection_in_app_only(): void {
        NotificationRule::factory()
            ->forEvent(NotificationEvent::OpenIssueAssigned)
            ->create([
                'organization_id' => $this->organization->id,
                'channels' => [NotificationChannel::InApp->value],
            ]);

        Notification::fake();

        $this->dispatcher()->notify(
            NotificationEvent::OpenIssueAssigned,
            $this->makeIssue(),
            $this->affected,
            $this->payload(),
        );

        Notification::assertSentTo(
            $this->affected,
            GenericEventNotification::class,
            fn(GenericEventNotification $n): bool => $n->channels === ['database'],
        );
    }

    public function test_user_preference_mail_disabled_results_in_database_only(): void {
        $this->affected->setPreference('notifications', ['mail_enabled' => false]);

        Notification::fake();

        $this->dispatcher()->notify(
            NotificationEvent::OpenIssueAssigned,
            $this->makeIssue(),
            $this->affected,
            $this->payload(),
        );

        Notification::assertSentTo(
            $this->affected,
            GenericEventNotification::class,
            fn(GenericEventNotification $n): bool => $n->channels === ['database'],
        );
    }

    public function test_quiet_hours_suppress_mail_but_in_app_still_collects(): void {
        $tz = (string) config('app.timezone', 'UTC');
        $this->affected->setPreference('notifications', [
            'mail_enabled' => true,
            'quiet_from' => Carbon::now($tz)->subHour()->format('H:i'),
            'quiet_to' => Carbon::now($tz)->addHour()->format('H:i'),
        ]);

        Notification::fake();

        $this->dispatcher()->notify(
            NotificationEvent::OpenIssueAssigned,
            $this->makeIssue(),
            $this->affected,
            $this->payload(),
        );

        Notification::assertSentTo(
            $this->affected,
            GenericEventNotification::class,
            fn(GenericEventNotification $n): bool => $n->channels === ['database'],
        );
    }

    public function test_role_recipient_strategy_notifies_role_holders_not_affected(): void {
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        NotificationRule::factory()
            ->forEvent(NotificationEvent::OpenIssueAssigned)
            ->create([
                'organization_id' => $this->organization->id,
                'notify_affected' => false,
                'recipient_roles' => ['teamleitung'],
            ]);

        Notification::fake();

        $sent = $this->dispatcher()->notify(
            NotificationEvent::OpenIssueAssigned,
            $this->makeIssue(),
            $this->affected,
            $this->payload(),
        );

        $this->assertSame(1, $sent);
        Notification::assertSentTo($teamlead, GenericEventNotification::class);
        Notification::assertNotSentTo($this->affected, GenericEventNotification::class);
    }

    public function test_fixed_user_recipients_are_notified(): void {
        $extra = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        NotificationRule::factory()
            ->forEvent(NotificationEvent::OpenIssueAssigned)
            ->create([
                'organization_id' => $this->organization->id,
                'notify_affected' => true,
                'recipient_user_ids' => [$extra->id],
            ]);

        Notification::fake();

        $sent = $this->dispatcher()->notify(
            NotificationEvent::OpenIssueAssigned,
            $this->makeIssue(),
            $this->affected,
            $this->payload(),
        );

        $this->assertSame(2, $sent);
        Notification::assertSentTo($extra, GenericEventNotification::class);
        Notification::assertSentTo($this->affected, GenericEventNotification::class);
    }

    public function test_database_notification_is_persisted_with_event_payload(): void {
        NotificationRule::factory()
            ->forEvent(NotificationEvent::OpenIssueAssigned)
            ->create([
                'organization_id' => $this->organization->id,
                'channels' => [NotificationChannel::InApp->value],
            ]);

        $this->dispatcher()->notify(
            NotificationEvent::OpenIssueAssigned,
            $this->makeIssue(),
            $this->affected,
            $this->payload(),
        );

        $this->assertSame(1, $this->affected->notifications()->count());
        $data = (array) $this->affected->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::OpenIssueAssigned->value, $data['event'] ?? null);
        $this->assertSame('Testpunkt', $data['title'] ?? null);
    }

    public function test_title_and_message_keys_render_in_recipient_locale(): void {
        // Scheduler/Queue laufen in der App-Default-Locale (de) — Empfänger
        // mit anderer Sprachpräferenz müssen Titel UND Nachricht trotzdem in
        // ihrer Sprache sehen (render-time via title_key/message_key), und
        // ISO-Datums-Params werden erst bei der Anzeige formatiert.
        $this->affected->setPreference('locale', 'en');

        $sent = $this->dispatcher()->notify(
            NotificationEvent::OpenIssueAssigned,
            $this->makeIssue(),
            $this->affected,
            [
                'title' => 'Wiedervorlage fällig: Ticket 42',
                'title_key' => 'Wiedervorlage fällig: Ticket :no',
                'title_params' => ['no' => '42'],
                'message' => 'Fällig am 15.07.2026.',
                'message_key' => 'Fällig am :date.',
                'message_params' => ['date' => '2026-07-15'],
                'url' => null,
            ],
        );

        $this->assertSame(1, $sent);

        $data = (array) $this->affected->notifications()->first()?->data;
        $this->assertSame('Wiedervorlage fällig: Ticket :no', $data['title_key'] ?? null);
        $this->assertSame('Fällig am :date.', $data['message_key'] ?? null);

        $previous = app()->getLocale();
        app()->setLocale('en');
        try {
            $this->assertSame('Follow-up due: ticket 42', \App\Support\NotificationText::title($data));
            $message = \App\Support\NotificationText::message($data);
            $this->assertStringStartsWith('Due on', $message);
            // ISO-Param wurde in ein Anzeigeformat übersetzt
            $this->assertStringNotContainsString('2026-07-15', $message);
        } finally {
            app()->setLocale($previous);
        }
    }
}
