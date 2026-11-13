<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SepaMandateExpiryScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\DeadlineScans;

use App\Enums\Finance\MandateStatus;
use App\Models\Finance\SepaMandate;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScanOptions};
use App\Services\Notification\NotificationDispatcher;
use App\Support\Query\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Verfall unbenutzter SEPA-Mandate (MVP-1620): 36 Monate nach dem letzten
 * Einzug ist ein Mandat nach dem SEPA-Regelwerk erloschen, ein nie genutztes
 * 36 Monate nach der Unterschrift (MVP-1682). Der Zahlungslauf nimmt es schon
 * über {@see SepaMandate::isUsable()} nicht mehr; der Lauf schreibt den Stand
 * fest, damit Liste und Filter ihn zeigen.
 */
class SepaMandateExpiryScan extends AbstractDeadlineScan {
    public function key(): string {
        return 'sepa_mandate_expiry';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $today = CarbonImmutable::today();
        $threshold = DateRange::day($today->subMonthsNoOverflow(SepaMandate::DORMANT_MONTHS));
        $expired = 0;

        SepaMandate::query()
            ->where('status', MandateStatus::Active->value)
            ->where(fn ($query) => $query
                ->where(fn ($used) => $used->whereNotNull('last_collected_on')->where('last_collected_on', '<', $threshold))
                ->orWhere(fn ($unused) => $unused->whereNull('last_collected_on')->whereNotNull('signed_on')->where('signed_on', '<', $threshold)))
            ->orderBy('id')
            ->pluck('organization_id', 'id')
            ->each(function (mixed $organizationId, int $id) use ($today, &$expired): void {
                if (! $this->moduleActiveFor($organizationId)) {
                    return;
                }
                DB::transaction(function () use ($id, $today, &$expired): void {
                    $mandate = SepaMandate::query()->whereKey($id)->lockForUpdate()->first();
                    if ($mandate === null || $mandate->status !== MandateStatus::Active || $mandate->isUsable($today)) {
                        return;
                    }
                    $mandate->forceFill(['status' => MandateStatus::Expired->value])->save();
                    $mandate->audit('sepaMandate.expired', [
                        'reference' => $mandate->reference,
                        'last_collected_on' => $mandate->last_collected_on?->toDateString(),
                        'signed_on' => $mandate->signed_on?->toDateString(),
                    ]);
                    $expired++;
                });
            });

        if ($expired > 0) {
            $options->info("SEPA-Mandate verfallen: {$expired}");
        }

        return 0;
    }
}
