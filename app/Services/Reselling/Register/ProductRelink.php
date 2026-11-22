<?php
/*
 * Created on   : Thu Nov 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProductRelink.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\PeriodStatus;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink, ResaleSubscription};
use App\Services\Reselling\Mirror\InvoiceMirror;
use Illuminate\Support\Facades\DB;

/**
 * Produkt neu ordnen (Phase 184, MVP-1751): Rechnungsbezüge eines Produkts bei
 * einem Empfänger lösen, damit der Vorschlagslauf sie neu verteilt. Für
 * Altzuordnungen, die um ein Jahr verschoben oder überbucht sind — jeden Bezug
 * einzeln zu verschieben scheitert dort an den belegten Nachbarperioden.
 *
 * Bleibt stehen: Verzicht und Einspruch, Gutschriftbezüge (nur von Hand
 * gesetzt) und Bezüge außerhalb des Belegspiegels.
 */
final class ProductRelink {
    public const string AUDIT = 'resale_period.links_reset';

    public function __construct(private readonly InvoiceMirror $mirror, private readonly PeriodLinker $linker) {}

    /**
     * @param  iterable<ResaleSubscription>  $subscriptions  Abos des Produkts (mit `periods.links`)
     * @return array{periods: int, links: int}
     */
    public function reset(iterable $subscriptions): array {
        $morphClasses = $this->mirror->morphClasses();

        return DB::transaction(function () use ($subscriptions, $morphClasses): array {
            $result = ['periods' => 0, 'links' => 0];
            foreach ($subscriptions as $subscription) {
                foreach ($subscription->periods as $period) {
                    if (in_array($period->status, [PeriodStatus::Waived, PeriodStatus::Disputed], true)) {
                        continue;
                    }
                    $removed = $this->release($period, $morphClasses);
                    if ($removed === []) {
                        continue;
                    }
                    $result['periods']++;
                    $result['links'] += count($removed);
                }
            }

            return $result;
        });
    }

    /**
     * @param  list<string>  $morphClasses
     * @return list<string> Belegnummern der gelösten Bezüge
     */
    private function release(ResalePeriod $period, array $morphClasses): array {
        $links = $period->links()->whereIn('linkable_type', $morphClasses)->where('months', '>', 0)->get();
        if ($links->isEmpty()) {
            return [];
        }
        $before = $period->status->value;
        $vouchers = array_values(array_unique($links->map(static fn(ResalePeriodLink $link): string => (string) $link->voucher_number)->all()));
        ResalePeriodLink::query()->whereKey($links->modelKeys())->delete();
        // Löschen per Abfrage feuert kein Modell-Ereignis: Registerstand selbst weiterzählen.
        ResaleCacheStamp::bump((int) $period->organization_id);
        $period->unsetRelation('links');
        $this->linker->settle($period, null, null, false);
        $period->audit(self::AUDIT, ['vouchers' => $vouchers, 'links' => $links->count(), 'status_before' => $before, 'status_now' => $period->status->value]);

        return $vouchers;
    }
}
