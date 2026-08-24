<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaQuotaScan.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Notification\DeadlineScans;

use App\Enums\Notification\NotificationEvent;
use App\Models\{SlaContract, SlaContractQuota};
use App\Services\Notification\NotificationDispatcher;
use App\Services\ServiceTicket\SlaQuotaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * SLA-Inklusivzeit-Kontingente (Feature 010 → Rang 44): erreicht der
 * Verbrauch im aktuellen Zeitraum die Warnschwelle, geht einmal je Periode
 * eine Benachrichtigung an die Teamleitung. Dedup pro Periode über
 * `last_warned_period` am Kontingent (die Dispatcher-Dedup ist subjektbasiert
 * und würde eine neue Periode sonst nicht erneut melden). Bleibt explizit
 * (C18): kein dedup-Flag, dafür Statefortschreibung je Zeile.
 */
class SlaQuotaScan extends AbstractDeadlineScan {
    public function __construct(private readonly SlaQuotaService $quotas) {}

    public function key(): string {
        return 'sla_quotas';
    }

    public function run(NotificationDispatcher $dispatcher, DeadlineScanOptions $options): int {
        $now = Carbon::now();
        $sent = 0;

        SlaContractQuota::query()->withoutGlobalScopes()
            ->chunkById(200, function (Collection $rows) use ($dispatcher, $now, &$sent): void {
                /** @var Collection<int, SlaContractQuota> $rows */
                foreach ($rows as $quota) {
                    $contract = SlaContract::query()->withoutGlobalScopes()->find($quota->sla_contract_id);
                    if (! $contract instanceof SlaContract || ! $contract->is_active) {
                        continue;
                    }

                    $usage = $this->quotas->usage($contract, $quota, $now);
                    if (! $usage['threshold_reached'] || $quota->last_warned_period === $usage['period_key']) {
                        continue; // Schwelle nicht erreicht oder in dieser Periode bereits gewarnt
                    }

                    $sent += $dispatcher->notify(
                        NotificationEvent::SlaQuotaWarning,
                        $quota,
                        null,
                        $this->quotaPayload($contract, $usage),
                    );
                    $quota->forceFill(['last_warned_period' => $usage['period_key']])->save();
                }
            });

        return $sent;
    }

    /**
     * @param  array<string, mixed>  $usage
     * @return array{title: string, message: string, url: null}
     */
    private function quotaPayload(SlaContract $contract, array $usage): array {
        return [
            'title' => trim($contract->code . ' — ' . $contract->label, ' —'),
            'message' => (string) __('notification.message.sla_quota_warning', [
                'percent' => (int) $usage['percentage'],
                'consumed' => (int) $usage['consumed_minutes'],
                'included' => (int) $usage['included_minutes'],
                'period' => (string) $usage['period_key'],
            ]),
            'message_key' => 'notification.message.sla_quota_warning',
            'message_params' => [
                'percent' => (int) $usage['percentage'],
                'consumed' => (int) $usage['consumed_minutes'],
                'included' => (int) $usage['included_minutes'],
                'period' => (string) $usage['period_key'],
            ],
            'url' => null,
        ];
    }
}
