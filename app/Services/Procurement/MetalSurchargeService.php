<?php
/*
 * Created on   : Sun Aug 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MetalSurchargeService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procurement;

use App\Models\{MetalQuotation, SupplierCatalogItem};
use CommonToolkit\ValueObjects\Money;

/**
 * Rechnet die DATANORM-Rohstoffzuschläge (Feature 107, MVP-564) in den
 * effektiven Einkaufspreis eines Katalogartikels ein — auf Basis der org-weit
 * gepflegten Metallnotierungen ({@see MetalQuotation}, €/kg):
 *
 *  - deutsche Methode (DEL): (Tagesnotierung − enthaltene Basis je kg) ×
 *    Gewichtsanteil, nie negativ (Spez: kein Abschlag unter Basis);
 *  - internationale Methode: fester Betrag/Prozentsatz, solange die
 *    Notierung im Von/Bis-Fenster liegt.
 *
 * Ohne gepflegte Notierung bleibt der Basispreis unverändert — die Zuschläge
 * liegen dann weiterhin transparent in `extra_attributes`.
 */
class MetalSurchargeService {
    /** @var array<string, MetalQuotation|null> Notierungs-Memo je Org+Metall (Listen-Seiten). */
    private array $quotations = [];

    /**
     * Effektiver EK je Einheit: Basispreis plus bewertete Rohstoffzuschläge.
     * Null, wenn der Artikel keinen Basispreis hat.
     */
    public function effectivePurchasePrice(SupplierCatalogItem $item): ?Money {
        $base = $item->purchase_price;
        if ($base === null) {
            return null;
        }

        $surcharges = (array) (((array) $item->extra_attributes)['datanorm_raw_surcharges'] ?? []);
        if ($surcharges === []) {
            return $base;
        }

        $effective = $base->withScale(6);
        foreach ($surcharges as $surcharge) {
            if (! is_array($surcharge)) {
                continue;
            }
            $quotation = $this->currentQuotation((int) $item->organization_id, (string) ($surcharge['material'] ?? ''));
            if ($quotation?->price_per_kg === null) {
                continue;
            }
            $addition = $this->evaluate($surcharge, $quotation->price_per_kg, $base);
            if ($addition !== null) {
                $effective = $effective->plus($addition->withScale(6));
            }
        }

        return $effective->withScale(4);
    }

    /**
     * Verkaufsseitiger Kupferzuschlag je Einheit (Feature 107, MVP-603):
     * deutsche Methode aus den Artikel-Kupferfeldern — (DEL-Notierung −
     * enthaltene Basis je kg, Basis in €/100 kg) × Gewicht in kg/Einheit.
     * Null ohne Kupferdaten, ohne Notierung oder wenn die Notierung die
     * Basis nicht übersteigt (dann entfällt die Position).
     */
    public function salesSurcharge(\App\Models\Article $article): ?Money {
        if ($article->copper_weight === null || (float) $article->copper_weight <= 0 || $article->copper_base_price === null) {
            return null;
        }
        $quotation = $this->currentQuotation((int) $article->organization_id, 'CU');
        if ($quotation?->price_per_kg === null) {
            return null;
        }

        $includedPerKg = Money::of((string) $article->copper_base_price, $quotation->price_per_kg->getCurrency(), 6)->times(0.01);
        $difference = $quotation->price_per_kg->withScale(6)->minus($includedPerKg);
        if (! $difference->isPositive()) {
            return null;
        }

        return $difference->times((float) $article->copper_weight)->withScale(4);
    }

    /** Jüngste Notierung eines Metalls (CU, AL, …) der Organisation. */
    public function currentQuotation(int $organizationId, string $metal): ?MetalQuotation {
        if (trim($metal) === '') {
            return null;
        }

        $key = $organizationId . ':' . strtoupper(trim($metal));

        return $this->quotations[$key] ??= MetalQuotation::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('metal', strtoupper(trim($metal)))
            ->orderByDesc('quoted_at')
            ->first();
    }

    /**
     * Bewertet einen einzelnen Zuschlag (Struktur aus dem DATANORM-Import,
     * {@see DatanormImportService::extraAttributes()}).
     *
     * @param  array<string, mixed>  $surcharge
     */
    private function evaluate(array $surcharge, Money $dayPricePerKg, Money $basePrice): ?Money {
        $priceUnitAmount = max(1, (int) ($surcharge['price_unit_amount'] ?? 1));

        if (($surcharge['method'] ?? '') === 'german') {
            $includedBase = (string) ($surcharge['included_base'] ?? '');
            if ($includedBase === '' || ! is_numeric($includedBase)) {
                return null;
            }
            $includedPerKg = Money::of($includedBase, $dayPricePerKg->getCurrency(), 6)
                ->times((float) ($surcharge['base_factor'] ?? 0));
            $difference = $dayPricePerKg->withScale(6)->minus($includedPerKg);
            if (! $difference->isPositive()) {
                return Money::zero($dayPricePerKg->getCurrency(), 6);
            }
            $kilograms = (float) ($surcharge['weight'] ?? 0) * (float) ($surcharge['weight_factor'] ?? 1);

            return $difference->times($kilograms)->dividedBy($priceUnitAmount)->withScale(6);
        }

        // Internationale Methode: nur innerhalb des Tagespreis-Fensters.
        $from = (string) ($surcharge['from_day_price'] ?? '');
        $to = (string) ($surcharge['to_day_price'] ?? '');
        $day = $dayPricePerKg->withScale(4);
        if ($from !== '' && is_numeric($from) && $day->compareTo(Money::of($from, $day->getCurrency(), 4)) < 0) {
            return null;
        }
        if ($to !== '' && is_numeric($to) && $day->compareTo(Money::of($to, $day->getCurrency(), 4)) > 0) {
            return null;
        }

        $sign = ($surcharge['discount'] ?? false) === true ? -1 : 1;
        if (isset($surcharge['percent']) && is_numeric((string) $surcharge['percent'])) {
            return $basePrice->withScale(6)->percentage((float) $surcharge['percent'])->times($sign);
        }
        $amount = (string) ($surcharge['amount'] ?? '');
        if ($amount !== '' && is_numeric($amount)) {
            return Money::of($amount, $basePrice->getCurrency(), 6)->dividedBy($priceUnitAmount)->times($sign);
        }

        return null;
    }
}
