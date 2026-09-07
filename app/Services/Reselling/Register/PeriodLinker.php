<?php
/*
 * Created on   : Mon Sep 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PeriodLinker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Register;

use App\Enums\Reselling\{LinkOrigin, PeriodStatus};
use App\Models\LexofficeVoucherLine;
use App\Models\Reselling\{ResalePeriod, ResalePeriodLink};

/**
 * Manuelle Bezüge (Feature 152, MVP-761): Rechnungsposition an eine Periode
 * hängen und den Periodenstatus aus der Deckung ableiten. Eine Schreibstelle
 * für Dialog, Schnellzuordnung am Abo und Abgleich je Empfänger.
 */
final class PeriodLinker {
    public function attach(ResalePeriod $period, LexofficeVoucherLine $line, float $months, ?string $note, ?int $userId): ResalePeriodLink {
        $termMonths = $period->termMonths();
        $link = ResalePeriodLink::query()->updateOrCreate(
            ['period_id' => $period->id, 'linkable_type' => $line->getMorphClass(), 'linkable_id' => $line->id],
            [
                'organization_id' => $period->organization_id,
                'subscription_id' => $period->subscription_id,
                'voucher_number' => $line->voucher->voucher_number,
                'voucher_date' => $line->voucher->voucher_date,
                'quantity' => round($months / $termMonths, 3),
                'months' => round($months, 2),
                'amount' => $line->unit_net->times(LicenseMonths::unitsFor($line, $months, $termMonths))->withScale(2),
                'currency' => $line->currency->value,
                'origin' => LinkOrigin::Manual,
                'note' => $note,
                'created_by_user_id' => $userId,
                'confirmed_at' => now(),
            ],
        );
        $period->unsetRelation('links');
        $this->settle($period, $userId, null);

        return $link;
    }

    /** Status aus der Deckung ableiten; entschieden = Nutzer hat bestätigt/verknüpft. */
    public function settle(ResalePeriod $period, ?int $userId, ?string $note, bool $decided = true): void {
        $period->load('links');
        $covered = $period->coveredMonths();
        $status = $covered >= $period->requiredMonths() - 0.001 ? PeriodStatus::Billed : ($covered > 0.001 ? PeriodStatus::Partial : PeriodStatus::Open);
        $period->forceFill([
            'status' => $status,
            'decided_by_user_id' => $decided ? $userId : null,
            'decided_at' => $decided ? now() : null,
            'note' => $note !== null && $note !== '' ? $note : $period->note,
        ])->save();
    }
}
