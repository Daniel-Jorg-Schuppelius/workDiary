<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SmsChannelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Notification;

use App\Enums\Notification\{NotificationEvent, SmsDeliveryStatus};
use App\Models\{Customer, User};
use App\Models\Notification\{NotificationDispatchLog, NotificationRule};
use App\Plugins\PluginManager;
use App\Plugins\SevenIo\SevenIoPlugin;
use App\Plugins\Sipgate\SipgatePlugin;
use App\Services\Notification\NotificationDispatcher;
use App\Services\Notification\Sms\{SmsChannelService, SmsOptInService, SmsText};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;

/**
 * SMS-Kanal (Feature 147, MVP-730 — Vollscan G12).
 *
 * Geprüft werden die Tore, an denen der Kanal scheitern MUSS (fehlendes
 * Opt-in, unkritisches Ereignis, erschöpftes Budget, Doppelversand) — sie sind
 * teurer als der Erfolgsfall: eine SMS zu viel kostet Geld und geht an eine
 * Rufnummer, die die Plattform verlassen hat.
 *
 * HTTP läuft über den Guzzle-MockHandler des `php-api-toolkit`
 * ({@see FakePluginHttp}), nicht über `Http::fake()`.
 */
class SmsChannelTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    private const SEVEN_SMS = 'https://gateway.seven.io/api/sms';

    private const SIPGATE_SMS = 'https://api.sipgate.com/v2/sessions/sms';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        NotificationFacade::fake();
    }

    // --- Aufbau ----------------------------------------------------------

    private function sevenIo(): void {
        $this->enablePluginFor($this->organization, SevenIoPlugin::ID, [
            'api_key' => 'test-key',
            'from' => 'workDiary',
        ]);
        app(PluginManager::class)->flushRuntimeCaches();
    }

    private function sipgate(): void {
        $this->enablePluginFor($this->organization, SipgatePlugin::ID, [
            'token_id' => 'token-id',
            'token' => 'secret',
            'sms_id' => 's0',
        ]);
        app(PluginManager::class)->flushRuntimeCaches();
    }

    /** Antwort des seven.io-Gateways: Erfolgscode 100 mit einer Nachricht. */
    private static function sevenOk(): \GuzzleHttp\Psr7\Response {
        return FakePluginHttp::response([
            'success' => '100',
            'messages' => [['id' => 'MSG-1', 'parts' => 1]],
        ]);
    }

    private function recipient(bool $optIn = true): User {
        $user = $this->orgUser(['mobile' => '+4915112345678']);
        if ($optIn) {
            $user->setPreference('notifications', [
                'sms_opt_in' => true,
                'sms_number_hash' => hash('sha256', '+4915112345678'),
                'sms_verified_at' => now()->toIso8601String(),
            ]);
        }

        return $user->refresh();
    }

    /** @param list<string> $channels */
    private function rule(NotificationEvent $event, array $channels = ['inApp', 'sms']): void {
        NotificationRule::query()->create([
            'organization_id' => $this->organization->id,
            'event' => $event,
            'enabled' => true,
            'channels' => $channels,
            'notify_affected' => true,
            'recipient_roles' => [],
            'recipient_user_ids' => [],
        ]);
    }

    private function send(User $user, NotificationEvent $event = NotificationEvent::CrisisAlert, array $payload = ['title' => 'KRISENALARM']): bool {
        $subject = Customer::factory()->create(['organization_id' => $this->organization->id]);

        return app(SmsChannelService::class)->send($user, $event, $subject, $payload, NotificationDispatchLog::STAGE_INITIAL);
    }

    private function smsLog(): ?NotificationDispatchLog {
        return NotificationDispatchLog::query()
            ->withoutGlobalScopes()
            ->where('channel', NotificationDispatchLog::CHANNEL_SMS)
            ->first();
    }

    // --- Versand ---------------------------------------------------------

    public function test_critical_event_reaches_opted_in_recipient_via_seven_io(): void {
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => self::sevenOk()]);
        $this->sevenIo();
        $this->rule(NotificationEvent::CrisisAlert);

        $this->assertTrue($this->send($this->recipient()));

        $fake->assertSent(fn (RequestInterface $r): bool => (string) $r->getUri() === self::SEVEN_SMS
            && str_contains((string) $r->getBody(), '+4915112345678'));

        $log = $this->smsLog();
        $this->assertNotNull($log);
        $this->assertSame(SmsDeliveryStatus::Sent, $log->status);
        $this->assertSame(SevenIoPlugin::ID, $log->provider);
        $this->assertSame('MSG-1', $log->provider_message_id);
        $this->assertSame(1, $log->segments);
    }

    public function test_sipgate_uses_its_own_endpoint_and_reports_no_message_id(): void {
        $fake = FakePluginHttp::fake([self::SIPGATE_SMS => FakePluginHttp::response(null, 204)]);
        $this->sipgate();
        $this->rule(NotificationEvent::CrisisAlert);

        $this->assertTrue($this->send($this->recipient()));

        $fake->assertSent(fn (RequestInterface $r): bool => (string) $r->getUri() === self::SIPGATE_SMS);
        $log = $this->smsLog();
        $this->assertNotNull($log);
        $this->assertSame(SmsDeliveryStatus::Sent, $log->status);
        $this->assertSame(SipgatePlugin::ID, $log->provider);
        // sipgate antwortet mit 204 — es GIBT keine Provider-ID.
        $this->assertNull($log->provider_message_id);
    }

    // --- Tore ------------------------------------------------------------

    public function test_without_opt_in_nothing_is_sent(): void {
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => self::sevenOk()]);
        $this->sevenIo();
        $this->rule(NotificationEvent::CrisisAlert);

        $this->assertFalse($this->send($this->recipient(optIn: false)));

        $fake->assertNothingSent();
        $this->assertNull($this->smsLog());
    }

    public function test_changed_mobile_number_invalidates_the_opt_in(): void {
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => self::sevenOk()]);
        $this->sevenIo();
        $this->rule(NotificationEvent::CrisisAlert);

        $user = $this->recipient();
        // Neue Nummer, altes Opt-in: der Hash passt nicht mehr.
        $user->forceFill(['mobile' => '+4915199999999'])->save();

        $this->assertFalse($this->send($user->refresh()));
        $fake->assertNothingSent();
    }

    public function test_non_critical_event_never_uses_the_sms_channel(): void {
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => self::sevenOk()]);
        $this->sevenIo();
        // Regel mit SMS-Kanal an einem gewöhnlichen Fristen-Ereignis.
        $this->rule(NotificationEvent::OpenIssueAssigned);

        $this->assertFalse($this->send($this->recipient(), NotificationEvent::OpenIssueAssigned));
        $fake->assertNothingSent();
    }

    public function test_channel_not_selected_in_the_rule_means_no_sms(): void {
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => self::sevenOk()]);
        $this->sevenIo();
        $this->rule(NotificationEvent::CrisisAlert, ['inApp', 'mail']);

        $this->assertFalse($this->send($this->recipient()));
        $fake->assertNothingSent();
    }

    public function test_without_gateway_nothing_is_sent(): void {
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => self::sevenOk()]);
        $this->rule(NotificationEvent::CrisisAlert);

        $this->assertFalse($this->send($this->recipient()));
        $fake->assertNothingSent();
    }

    public function test_same_alarm_is_never_sent_twice_to_the_same_person(): void {
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => self::sevenOk()]);
        $this->sevenIo();
        $this->rule(NotificationEvent::CrisisAlert);
        $user = $this->recipient();
        $subject = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $service = app(SmsChannelService::class);

        $this->assertTrue($service->send($user, NotificationEvent::CrisisAlert, $subject, ['title' => 'A'], 'initial'));
        $this->assertFalse($service->send($user, NotificationEvent::CrisisAlert, $subject, ['title' => 'A'], 'initial'));

        $fake->assertSentCount(1);
    }

    public function test_escalation_stage_reaches_the_same_person_again(): void {
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => self::sevenOk()]);
        $this->sevenIo();
        $this->rule(NotificationEvent::CrisisAlert);
        $user = $this->recipient();
        $subject = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $service = app(SmsChannelService::class);

        $this->assertTrue($service->send($user, NotificationEvent::CrisisAlert, $subject, ['title' => 'A'], 'initial'));
        $this->assertTrue($service->send($user, NotificationEvent::CrisisAlert, $subject, ['title' => 'A'], 'escalation'));

        $fake->assertSentCount(2);
    }

    // --- Budget ----------------------------------------------------------

    public function test_monthly_budget_blocks_the_send_and_records_the_reason(): void {
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => self::sevenOk()]);
        $this->sevenIo();
        $this->rule(NotificationEvent::CrisisAlert);
        $this->organization->forceFill(['settings' => ['notifications' => ['sms' => ['monthly_limit' => 1]]]])->save();
        app()->instance('currentOrganization', $this->organization->refresh());

        $first = $this->recipient();
        $this->assertTrue($this->send($first));

        $second = $this->orgUser(['mobile' => '+4915100000000']);
        $second->setPreference('notifications', [
            'sms_opt_in' => true,
            'sms_number_hash' => hash('sha256', '+4915100000000'),
        ]);
        $this->assertFalse($this->send($second->refresh()));

        $fake->assertSentCount(1);
        $blocked = NotificationDispatchLog::query()->withoutGlobalScopes()
            ->where('recipient_user_id', $second->id)->first();
        $this->assertNotNull($blocked);
        $this->assertSame(SmsDeliveryStatus::Blocked, $blocked->status);
        $this->assertSame('budget_exceeded', $blocked->error_code);
        $this->assertSame(0, $blocked->segments);
    }

    // --- Fehlerpfad / Retry-Regel ----------------------------------------

    public function test_gateway_5xx_is_not_retried_and_lands_as_failed(): void {
        // api-toolkit ≥ v2.9.2: ein gesendetes POST wird NICHT wiederholt —
        // sonst wäre der „Retry" eine zweite SMS mit zweiter Rechnungszeile.
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => FakePluginHttp::response(['error' => 'down'], 503)]);
        $this->sevenIo();
        $this->rule(NotificationEvent::CrisisAlert);

        $this->send($this->recipient());

        $fake->assertSentCount(1);
        $log = $this->smsLog();
        $this->assertNotNull($log);
        $this->assertSame(SmsDeliveryStatus::Failed, $log->status);
        $this->assertSame('http_503', $log->error_code);
        $this->assertSame(0, $log->segments);
    }

    public function test_gateway_4xx_is_not_retried_either(): void {
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => FakePluginHttp::response(['error' => 'nope'], 401)]);
        $this->sevenIo();
        $this->rule(NotificationEvent::CrisisAlert);

        $this->send($this->recipient());

        $fake->assertSentCount(1);
        $this->assertSame(SmsDeliveryStatus::Failed, $this->smsLog()?->status);
    }

    public function test_provider_error_code_in_a_http_200_body_counts_as_failure(): void {
        // seven.io meldet Fehler mit HTTP 200 im Feld `success` — würde das
        // niemand auswerten, liefe eine nie versendete SMS als „zugestellt".
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => FakePluginHttp::response(['success' => '900'])]);
        $this->sevenIo();
        $this->rule(NotificationEvent::CrisisAlert);

        $this->send($this->recipient());

        $fake->assertSentCount(1);
        $log = $this->smsLog();
        $this->assertSame(SmsDeliveryStatus::Failed, $log?->status);
        $this->assertSame('seven_900', $log?->error_code);
    }

    // --- Text ------------------------------------------------------------

    public function test_text_is_rendered_at_send_time_and_cut_to_one_segment(): void {
        $text = SmsText::forEvent(NotificationEvent::CrisisAlert, [
            'title_key' => 'sms.section',
            'message' => str_repeat('Sehr langer Lagebericht ', 20),
        ]);

        $this->assertLessThanOrEqual(SmsText::LIMIT_GSM7, mb_strlen($text));
        $this->assertStringStartsWith((string) __('sms.section'), $text);
        $this->assertStringEndsWith('...', $text);
        // Sauber gekürzt: kein abgeschnittenes Wort vor dem Auslassungszeichen.
        $this->assertStringNotContainsString(' ...', $text);
    }

    public function test_non_gsm_characters_shrink_the_limit_to_one_ucs2_segment(): void {
        $text = SmsText::shorten(str_repeat('Böe ☔ ', 40));

        $this->assertFalse(SmsText::isGsm7($text));
        $this->assertLessThanOrEqual(SmsText::LIMIT_UCS2, mb_strlen($text));
    }

    // --- Opt-in-Bestätigung ----------------------------------------------

    public function test_opt_in_requires_a_confirmation_code_from_the_real_number(): void {
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => self::sevenOk()]);
        $this->sevenIo();
        $user = $this->orgUser(['mobile' => '+4915112345678']);
        $service = app(SmsOptInService::class);

        $this->assertFalse($service->hasOptIn($user));
        $service->startVerification($user);

        // Code aus dem Nachrichtentext lesen — NICHT aus dem Rohbody: dort
        // steht auch die Rufnummer, und deren erste sechs Ziffern sähen wie
        // ein Code aus.
        $code = '';
        $fake->assertSent(function (RequestInterface $r) use (&$code): bool {
            $body = json_decode((string) $r->getBody(), true);
            $text = is_array($body) ? (string) ($body['text'] ?? '') : '';
            if (preg_match('/(\d{6})/', $text, $m) === 1) {
                $code = $m[1];
            }

            return $code !== '';
        });

        $this->assertNotSame('', $code);
        // Falscher Code verbraucht die Anforderung nicht — sonst wäre jeder
        // Tippfehler ein neuer kostenpflichtiger Versand.
        $this->assertFalse($service->confirm($user, 'falsch'));
        $this->assertTrue($service->confirm($user->refresh(), $code));
        $this->assertTrue($service->hasOptIn($user->refresh()));

        $service->revoke($user);
        $this->assertFalse($service->hasOptIn($user->refresh()));
    }

    public function test_self_service_page_renders_and_revoke_works(): void {
        FakePluginHttp::fake([self::SEVEN_SMS => self::sevenOk()]);
        $this->sevenIo();
        $user = $this->recipient();

        $this->actingAs($user)->get(route('account.sms.index'))->assertOk();

        $this->actingAs($user)->delete(route('account.sms.destroy'))->assertRedirect();
        $this->assertFalse(app(SmsOptInService::class)->hasOptIn($user->refresh()));
    }

    // --- Dispatcher-Anbindung --------------------------------------------

    public function test_dispatcher_routes_a_critical_event_through_the_sms_channel(): void {
        $fake = FakePluginHttp::fake([self::SEVEN_SMS => self::sevenOk()]);
        $this->sevenIo();
        $this->rule(NotificationEvent::CrisisAlert);
        $user = $this->recipient();
        $subject = Customer::factory()->create(['organization_id' => $this->organization->id]);

        app(NotificationDispatcher::class)->notify(
            NotificationEvent::CrisisAlert,
            $subject,
            $user,
            ['title' => 'KRISENALARM: Stromausfall'],
        );

        $fake->assertSent(fn (RequestInterface $r): bool => (string) $r->getUri() === self::SEVEN_SMS);
        $this->assertSame(SmsDeliveryStatus::Sent, $this->smsLog()?->status);
    }

    // --- Healthcheck je Organisation --------------------------------------

    public function test_health_check_runs_per_organization(): void {
        FakePluginHttp::fake(['https://gateway.seven.io/api/balance' => FakePluginHttp::response('12.34')]);
        $this->sevenIo();

        $health = $this->withPluginOrgContext($this->organization, fn () => app(SevenIoPlugin::class)->healthCheck());
        $this->assertTrue($health->isOk());
    }

    public function test_health_check_reports_a_rejected_key_as_failing(): void {
        FakePluginHttp::fake(['https://gateway.seven.io/api/balance' => FakePluginHttp::response([], 401)]);
        $this->sevenIo();

        $health = $this->withPluginOrgContext($this->organization, fn () => app(SevenIoPlugin::class)->healthCheck());
        $this->assertTrue($health->isFailing());
    }
}
