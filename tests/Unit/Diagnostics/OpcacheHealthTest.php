<?php
/*
 * Created on   : Sun Nov 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpcacheHealthTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Diagnostics;

use App\Services\Diagnostics\{DiagnosticStatus, OpcacheHealth};
use PHPUnit\Framework\TestCase;

class OpcacheHealthTest extends TestCase {
    /** @return array<string, mixed> */
    private function opcache(int $usedMb, int $freeMb, int $keys, int $maxKeys, int $oomRestarts = 0, bool $full = false): array {
        return [
            'opcache_enabled' => true,
            'cache_full' => $full,
            'memory_usage' => ['used_memory' => $usedMb * 1048576, 'free_memory' => $freeMb * 1048576, 'wasted_memory' => 0],
            'interned_strings_usage' => ['buffer_size' => 8 * 1048576, 'used_memory' => 2 * 1048576],
            'opcache_statistics' => ['num_cached_scripts' => $keys, 'num_cached_keys' => $keys, 'max_cached_keys' => $maxKeys, 'oom_restarts' => $oomRestarts, 'hash_restarts' => 0],
        ];
    }

    public function test_roomy_cache_is_ok(): void {
        $result = OpcacheHealth::evaluate($this->opcache(100, 284, 6000, 32531), 'fpm-fcgi');

        $this->assertSame(DiagnosticStatus::Ok, $result['status']);
        $this->assertSame([], $result['messages']);
        $this->assertSame(26, $result['metrics']['opcache.memory_used_percent']);
        $this->assertSame(384, $result['metrics']['opcache.memory_mb']);
    }

    public function test_nearly_full_memory_warns_and_names_the_setting(): void {
        $result = OpcacheHealth::evaluate($this->opcache(112, 16, 6000, 32531), 'fpm-fcgi');

        $this->assertSame(DiagnosticStatus::Warn, $result['status']);
        $this->assertStringContainsString('opcache.memory_consumption', $result['messages'][0]);
    }

    public function test_exhausted_keys_are_critical(): void {
        $result = OpcacheHealth::evaluate($this->opcache(100, 284, 16229, 16229), 'fpm-fcgi');

        $this->assertSame(DiagnosticStatus::Critical, $result['status']);
        $this->assertStringContainsString('opcache.max_accelerated_files', $result['messages'][0]);
    }

    public function test_restart_for_lack_of_space_is_critical_even_with_room(): void {
        $result = OpcacheHealth::evaluate($this->opcache(40, 88, 3000, 16229, oomRestarts: 3), 'fpm-fcgi');

        $this->assertSame(DiagnosticStatus::Critical, $result['status']);
        $this->assertSame(3, $result['metrics']['opcache.restarts_full']);
    }

    public function test_disabled_cache_warns_on_web_but_not_on_cli(): void {
        $this->assertSame(DiagnosticStatus::Warn, OpcacheHealth::evaluate(null, 'fpm-fcgi')['status']);
        $this->assertSame(DiagnosticStatus::Warn, OpcacheHealth::evaluate(['opcache_enabled' => false], 'apache2handler')['status']);
        $this->assertSame(DiagnosticStatus::Ok, OpcacheHealth::evaluate(null, 'cli')['status']);
    }
}
