<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SecuritySiemExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\Security\SecurityEventType;
use App\Services\Security\SecurityEventLogger;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{File, Log};
use Tests\TestCase;

/** MVP-452: strukturierter SIEM-Export der Sicherheitsereignisse (CEF/JSON). */
final class SecuritySiemExportTest extends TestCase {
    use RefreshDatabase;

    /**
     * @param  array<string, scalar|null>  $context
     * @return list<string>
     */
    private function capture(string $format, SecurityEventType $type, array $context): array {
        $path = sys_get_temp_dir() . '/siem-' . uniqid('', true) . '.log';
        config()->set('logging.security_siem_format', $format);
        config()->set('logging.channels.security_siem', [
            'driver' => 'single', 'path' => $path,
            'formatter' => \Monolog\Formatter\LineFormatter::class, 'formatter_with' => ['format' => "%message%\n"],
        ]);
        Log::forgetChannel('security_siem');
        app(SecurityEventLogger::class)->log($type, $context);
        $lines = File::isFile($path) ? array_values(array_filter(explode("\n", File::get($path)))) : [];
        File::delete($path);

        return $lines;
    }

    public function test_cef_line_maps_ip_user_and_agent(): void {
        $lines = $this->capture('cef', SecurityEventType::TerminalBadgeUnknown, ['ip' => '203.0.113.7', 'user' => 'a=b', 'ua' => 'curl', 'guard' => 'web']);

        $this->assertCount(1, $lines);
        $this->assertStringStartsWith('CEF:0|WorkDiary|WorkDiary|', $lines[0]);
        $this->assertStringContainsString('|terminal.badge_unknown|terminal.badge_unknown|4|', $lines[0]);
        $this->assertStringContainsString('src=203.0.113.7 suser=a\\=b requestClientApplication=curl msg=guard\\=web', $lines[0]);
    }

    public function test_json_line_and_off_by_default(): void {
        $lines = $this->capture('json', SecurityEventType::TerminalBadgeUnknown, ['ip' => '203.0.113.7', 'badge' => '0815']);
        $data = JsonHelper::decode($lines[0]);
        $this->assertSame(['terminal.badge_unknown', 4, '203.0.113.7', '0815'], [$data['event'], $data['severity'], $data['ip'], $data['badge']]);

        $this->assertSame([], $this->capture('off', SecurityEventType::TerminalBadgeUnknown, ['ip' => '203.0.113.7']));
    }
}
