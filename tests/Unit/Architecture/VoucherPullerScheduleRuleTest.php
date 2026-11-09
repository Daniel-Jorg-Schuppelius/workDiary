<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VoucherPullerScheduleRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Services\Finance\Accounting\Vouchers\{LineAwareVoucherPuller, VoucherPuller, VoucherPullerRegistry};
use Illuminate\Support\Facades\Artisan;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

/**
 * Gate zu MVP-1490 (Umsetzungslücken 2026-11-08, ul-01): Die Belegspiegel von
 * easybill, JTL-Wawi und InvoicePlane waren registriert, liefen aber nie —
 * Umsatz, Kundenwert, Portal und Belegfluss blieben leer. Jeder registrierte
 * {@see VoucherPuller} braucht einen Zeitplaneintrag mit seiner Plugin-Bindung:
 * `accounting:pull-vouchers` (mit seiner `--plugin`-ID oder ohne) oder ein
 * eigener Befehl, der den Puller aufruft und bei Einzelabruf-Positionen
 * `VoucherMirror::pullPendingLines()` mitlädt.
 */
class VoucherPullerScheduleRuleTest extends TestCase {
    private const string GENERAL_COMMAND = 'accounting:pull-vouchers';

    public function test_every_registered_voucher_puller_has_a_scheduled_run(): void {
        $pullers = app(VoucherPullerRegistry::class)->all();
        $this->assertGreaterThanOrEqual(6, count($pullers), 'Puller-Register nicht gefüllt — Gate umgebaut?');

        $this->assertSame([], $this->unscheduled($pullers, (array) config('scheduler.jobs', [])), "Belegspiegel ohne Zeitplan — Eintrag in config/scheduler.php ergänzen ('command' => 'accounting:pull-vouchers --plugin=<id>', 'plugin' => '<id>') und SchedulerRegistrationTest nachziehen:");
    }

    public function test_gate_flags_a_puller_whose_entry_is_missing(): void {
        $jobs = (array) config('scheduler.jobs', []);
        unset($jobs['easybill.pull-vouchers']);

        $this->assertContains('easybill', $this->unscheduled(app(VoucherPullerRegistry::class)->all(), $jobs));
    }

    /**
     * @param  list<VoucherPuller>  $pullers
     * @param  array<array-key, mixed>  $jobs
     * @return list<string> Plugin-IDs ohne geplanten Lauf
     */
    private function unscheduled(array $pullers, array $jobs): array {
        $commands = Artisan::all();
        $missing = [];
        foreach ($pullers as $puller) {
            $id = $puller->pluginId();
            $covered = false;
            foreach ($jobs as $job) {
                if (! is_array($job) || ! is_string($job['command'] ?? null)) {
                    continue;
                }
                $command = $job['command'];
                $name = (string) strtok($command, ' ');
                $bound = in_array($id, array_map('trim', explode(',', (string) ($job['plugin'] ?? ''))), true);
                if ($name === self::GENERAL_COMMAND) {
                    $only = preg_match('/--plugin=(\S+)/', $command, $m) === 1 ? $m[1] : null;
                    $covered = $only === null || ($only === $id && $bound);
                } elseif ($bound && isset($commands[$name])) {
                    $source = $this->source($commands[$name]);
                    $covered = str_contains($source, class_basename($puller))
                        && (! $puller instanceof LineAwareVoucherPuller || str_contains($source, 'pullPendingLines('));
                }
                if ($covered) {
                    break;
                }
            }
            if (! $covered) {
                $missing[] = $id;
            }
        }

        return $missing;
    }

    private function source(Command $command): string {
        $file = (new ReflectionClass($command))->getFileName();

        return is_string($file) ? (string) file_get_contents($file) : '';
    }
}
