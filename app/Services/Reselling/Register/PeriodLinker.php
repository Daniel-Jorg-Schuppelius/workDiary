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
    /**
     * Noch nicht vergebene Lizenzmonate der Position — Bezüge an DIESER Periode
     * zählen nicht, weil ein erneuter Bezug sie ersetzt. Eine 24er-Position
     * darf nicht mit 48 + 12 verbucht werden.
     */
    public function freeMonths(LexofficeVoucherLine $line, ?ResalePeriod $except = null): float {
        $line->loadMissing('voucher');
        $query = ResalePeriodLink::query()
            ->where('linkable_type', $line->getMorphClass())
            ->where('linkable_id', $line->id);
        if ($except !== null) {
            $query->where('period_id', '!=', $except->id);
        }
        $used = (float) $query->sum('months');

        return max(0.0, LicenseMonths::ofLine($line) - $used);
    }

    public function attach(ResalePeriod $period, LexofficeVoucherLine $line, float $months, ?string $note, ?int $userId): ResalePeriodLink {
        $free = $this->freeMonths($line, $period);
        if ($months > $free + 0.001) {
            throw new \InvalidArgumentException((string) __('resale.link.error.exceeds', ['amount' => LicenseMonths::label($free, LicenseMonths::split($line)['months'])]));
        }
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

    /**
     * Validierungsregeln für die Deckung: Lizenzen × Monate je Lizenz (Formulare
     * am Abo und im Abgleich) oder rohe Lizenzmonate (Dialog).
     *
     * @return array<string, list<string>>
     */
    public static function amountRules(): array {
        return [
            'months' => ['required_without:licences', 'nullable', 'numeric', 'min:0.01', 'max:100000'],
            'licences' => ['required_without:months', 'nullable', 'numeric', 'min:0.01', 'max:10000'],
            'per_licence' => ['required_with:licences', 'nullable', 'numeric', 'min:0.01', 'max:1200'],
        ];
    }

    /**
     * Lizenzmonate aus der Eingabe: Lizenzen × Monate je Lizenz, sonst Lizenzmonate.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function monthsFrom(array $validated): float {
        if (isset($validated['licences']) && $validated['licences'] !== '') {
            return round((float) $validated['licences'] * (float) ($validated['per_licence'] ?? 1), 2);
        }

        return (float) ($validated['months'] ?? 0);
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
