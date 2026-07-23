<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecurityEventLogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Security;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Security\SecurityEventType;
use App\Models\{SecurityEvent, User};
use App\Notifications\GenericEventNotification;
use App\Services\Security\{KnownDeviceService, SecurityEventLogger};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Notification};
use Tests\TestCase;

/**
 * Security-Event-Log + Angriffserkennung (Feature 096, MVP-443–446):
 * Format-Vertrag mit den fail2ban-Filtern, Quellen-Anbindung, selektive
 * Persistenz (Hinweisgeber nie in der DB), Schwellwert-Zustandswechsel,
 * Known-Device-Benachrichtigung und Plattform-Admin-IP-Allowlist.
 */
class SecurityEventLogTest extends TestCase {
    use RefreshDatabase;

    private string $logPath = '';

    protected function setUp(): void {
        parent::setUp();
        $this->logPath = sys_get_temp_dir() . '/wd_security_' . uniqid() . '.log';
        // daily-Kanal auf 'single' drehen: deterministische eine Datei je Test.
        config()->set('logging.channels.security.driver', 'single');
        config()->set('logging.channels.security.path', $this->logPath);
    }

    protected function tearDown(): void {
        @unlink($this->logPath);
        parent::tearDown();
    }

    private function logContents(): string {
        return is_file($this->logPath) ? (string) file_get_contents($this->logPath) : '';
    }

    public function test_log_line_matches_fail2ban_contract(): void {
        app(SecurityEventLogger::class)->log(SecurityEventType::AuthFailed, [
            'ip' => '203.0.113.7',
            'user' => "evil\"user\nwith newline",
            'guard' => 'web',
        ]);

        $line = trim($this->logContents());
        // Eine Zeile, Ereignis + ip=-Anker an fester Position, Werte entschärft.
        $this->assertSame(1, substr_count($this->logContents(), "\n"));
        $this->assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] security\.WARNING: auth\.failed ip=203\.0\.113\.7 user="[^"]*" guard="web"$/',
            $line,
        );

        // Die ausgelieferten fail2ban-failregex matchen die reale Zeile.
        $filter = (string) file_get_contents(base_path('deploy/fail2ban/filter.d/workdiary.conf'));
        $this->assertSame(1, preg_match('/^failregex = (.+)$/m', $filter, $m));
        $regex = '#' . str_replace('<HOST>', '(?:\d{1,3}\.){3}\d{1,3}', trim($m[1])) . '#';
        $this->assertMatchesRegularExpression($regex, $line);
    }

    public function test_failed_login_writes_log_line_and_persists_event(): void {
        User::factory()->create(['email' => 'opfer@example.com']);

        $this->post('/login', ['username' => 'opfer@example.com', 'password' => 'falsch-falsch'])
            ->assertSessionHasErrors();

        $this->assertStringContainsString('auth.failed', $this->logContents());
        $this->assertStringContainsString('user="opfer@example.com"', $this->logContents());
        // Ein Fehllogin kann mehrere Failed-Events feuern (Legacy-Fallback-Kette).
        $this->assertGreaterThanOrEqual(1, SecurityEvent::query()->where('event', SecurityEventType::AuthFailed->value)->count());
    }

    public function test_whistleblowing_login_failure_stays_out_of_database(): void {
        config()->set('whistleblowing.key', base64_encode(random_bytes(32)));
        config()->set('whistleblowing.lookup_key', base64_encode(random_bytes(32)));

        $this->post(route('whistleblowing.mailbox.authenticate'), ['secret' => 'voellig-falsch'])
            ->assertSessionHasErrors();

        // Datei ja (fail2ban) …
        $this->assertStringContainsString('wb.login_failed', $this->logContents());
        // … Datenbank nein (Anonymitätsschutz HinSchG).
        $this->assertSame(0, SecurityEvent::query()->where('event', SecurityEventType::WbLoginFailed->value)->count());
    }

    public function test_threshold_alarm_fires_on_state_change_only(): void {
        Notification::fake();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        config()->set('security.events.thresholds', [
            ['key' => 'test_rule', 'event' => 'auth.failed', 'scope' => 'global', 'window_minutes' => 10, 'limit' => 3],
        ]);
        Cache::forget('security:alarm:test_rule');

        foreach (range(1, 4) as $i) {
            SecurityEvent::query()->create([
                'event' => SecurityEventType::AuthFailed,
                'ip' => '198.51.100.' . $i,
                'occurred_at' => now(),
            ]);
        }

        $this->artisan('security:evaluate')->assertExitCode(0);
        Notification::assertSentToTimes($admin, GenericEventNotification::class, 1);

        // Zustand unverändert → kein Doppel-Alarm.
        $this->artisan('security:evaluate')->assertExitCode(0);
        Notification::assertSentToTimes($admin, GenericEventNotification::class, 1);

        // Fenster leert sich → Entwarnung genau einmal.
        SecurityEvent::query()->update(['occurred_at' => now()->subHours(2)]);
        $this->artisan('security:evaluate')->assertExitCode(0);
        $this->artisan('security:evaluate')->assertExitCode(0);
        Notification::assertSentToTimes($admin, GenericEventNotification::class, 2);
    }

    public function test_known_device_notifies_only_on_new_fingerprint(): void {
        Notification::fake();
        $user = User::factory()->create();
        $service = app(KnownDeviceService::class);

        $chromeWindows = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';
        $firefoxLinux = 'Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0';

        // Erstkontakt = Enrollment, kein Alarm; Wiederkennung ebenfalls still.
        $service->touch($user, $chromeWindows, '127.0.0.1');
        $service->touch($user, $chromeWindows, '127.0.0.1');
        Notification::assertNothingSent();

        // Neues Gerät → genau eine Benachrichtigung an den Nutzer.
        $service->touch($user, $firefoxLinux, '127.0.0.1');
        Notification::assertSentToTimes($user, GenericEventNotification::class, 1);
        Notification::assertSentTo($user, GenericEventNotification::class, fn(GenericEventNotification $n): bool => $n->event === NotificationEvent::SecurityNewDevice);

        // Opt-out über users.preferences: weiteres neues Gerät bleibt still.
        $user->forceFill(['preferences' => [KnownDeviceService::PREFERENCE_KEY => false]])->save();
        $service->touch($user->fresh(), $chromeWindows, '203.0.113.7');
        Notification::assertSentToTimes($user, GenericEventNotification::class, 1);
    }

    public function test_platform_admin_ip_allowlist(): void {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        // Passende Liste (Loopback) → Zugriff bleibt möglich.
        config()->set('security.platform_admin_ip_allowlist', '127.0.0.1, 10.0.0.0/8');
        $this->actingAs($admin)->get(route('admin.integrity.index'))->assertOk();

        // Nicht passende Liste → 403 + Security-Event.
        config()->set('security.platform_admin_ip_allowlist', '10.0.0.0/8');
        $this->actingAs($admin)->get(route('admin.integrity.index'))->assertForbidden();
        $this->assertStringContainsString('admin.ip_blocked', $this->logContents());

        // Org-Nutzer bleiben von der Liste unberührt.
        $orgUser = User::factory()->create();
        $this->actingAs($orgUser)->get(route('dashboard'))->assertSuccessful();
    }
}
