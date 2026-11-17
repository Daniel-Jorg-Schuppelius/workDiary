<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferTaxCases.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\Targets;

use App\Enums\Finance\TransferChannel;
use App\Exceptions\UnmappableTaxCaseException;
use App\Models\Finance\{BillingTransfer, BillingTransferPosition};
use App\Models\Platform\Organization;
use App\Models\Time\TimeEntry;
use App\Services\Article\Catalog\LocalArticleCatalogSource;
use App\Services\Finance\BillingPositionBuilder;
use App\Services\Invoicing\Dto\OutgoingTaxCase;
use App\Services\Invoicing\{OutgoingTaxCaseResolver, PlaceOfSupply, PlaceOfSupplyResolver};
use Illuminate\Support\Collection;

/**
 * Steuerfall einer Übergabe (MVP-1712). Eine Übergabe ist keine ausgestellte
 * Rechnung, es gibt keinen eingefrorenen Fall: Er entsteht wie im
 * Rechnungslauf aus Kunde und Leistungsort der Positionen — Zeiten über ihre
 * Leistung und den Einsatzort, Auffüllungen über das Projekt, Abos über den
 * Leistungsartikel, Material als Ware. Ergeben die Positionen mehrere Fälle,
 * ist die Übergabe wie eine Rechnung zu teilen.
 */
final class TransferTaxCases {
    public function __construct(
        private readonly OutgoingTaxCaseResolver $resolver,
        private readonly PlaceOfSupplyResolver $places,
        private readonly BillingPositionBuilder $positions,
    ) {}

    /**
     * @param  Collection<int, BillingTransferPosition>|null  $positions  schon berechnete Positionen (Vorschau)
     * @return list<OutgoingTaxCase>
     */
    public function for(BillingTransfer $transfer, ?Collection $positions = null): array {
        $organization = $transfer->organization ?? throw new \LogicException('Übergabe ohne Organisation.');
        $transfer->loadMissing(['items', 'customer']);
        $positions ??= $this->positions->positionsFor($transfer);
        $goods = $transfer->channel === TransferChannel::Material;
        // Stichtag wie bei der Rechnung: das letzte Leistungsdatum.
        $onDate = null;
        foreach ($positions as $position) {
            if ($position->service_to !== null && ($onDate === null || $position->service_to->gt($onDate))) {
                $onDate = $position->service_to;
            }
        }
        $onDate ??= $transfer->period_to ?? now();

        return $this->resolver->forPlaces($organization, $transfer->customer, $onDate, $goods, $goods ? [] : $this->placesOf($organization, $positions));
    }

    /**
     * Der eine Steuerfall der Übergabe.
     *
     * @throws UnmappableTaxCaseException wenn die Positionen mehrere Fälle ergeben
     */
    public function single(BillingTransfer $transfer): OutgoingTaxCase {
        return self::one($this->for($transfer));
    }

    /**
     * Grund, warum die Übergabe so nicht an ihr Ziel gehen kann; null = abbildbar.
     *
     * @param  FacturationTarget|null  $target  null = Ziel nicht verfügbar; dann zählen nur gemischte Fälle
     * @param  list<OutgoingTaxCase>|null  $cases  schon ermittelte Fälle (Vorschau)
     */
    public function blocker(BillingTransfer $transfer, ?FacturationTarget $target, ?array $cases = null): ?string {
        try {
            $case = self::one($cases ?? $this->for($transfer));
        } catch (UnmappableTaxCaseException $e) {
            return $e->getMessage();
        }

        return $target instanceof MapsTaxCases ? $target->taxCaseBlocker((int) $transfer->organization_id, $case) : null;
    }

    /**
     * @param  list<OutgoingTaxCase>  $cases
     *
     * @throws UnmappableTaxCaseException
     */
    private static function one(array $cases): OutgoingTaxCase {
        if (count($cases) !== 1) {
            throw new UnmappableTaxCaseException((string) __('finance.tax_case.mixed', [
                'cases' => implode(', ', array_map(static fn (OutgoingTaxCase $case): string => $case->case->label(), $cases)),
            ]));
        }

        return $cases[0];
    }

    /**
     * Leistungsorte der Positionen; Positionen ohne Leistung (etwa ein Abo ohne
     * Artikel des Artikelstamms) zählen nicht, leer gilt die Vorgabe der
     * Organisation — wie bei der Rechnung.
     *
     * @param  Collection<int, BillingTransferPosition>  $positions
     * @return list<PlaceOfSupply>
     */
    private function placesOf(Organization $organization, Collection $positions): array {
        $places = [];
        $entryIds = $positions->where('source_kind', BillingTransferPosition::KIND_TIME)
            ->flatMap(static fn (BillingTransferPosition $position): array => array_map('intval', (array) $position->source_ids))
            ->unique()->values()->all();
        if ($entryIds !== []) {
            foreach (TimeEntry::query()->whereIn('id', $entryIds)->with(['project', 'diaryEntry'])->get() as $entry) {
                $place = $this->places->forTimeEntry($organization, $entry);
                $places[$place->key()] = $place;
            }
        }
        foreach ($positions->where('source_kind', BillingTransferPosition::KIND_TARGET) as $position) {
            $place = $this->places->forProject($organization, $position->project);
            $places[$place->key()] = $place;
        }
        $articleIds = array_values(array_filter($positions
            ->whereNotIn('source_kind', [BillingTransferPosition::KIND_TIME, BillingTransferPosition::KIND_TARGET, BillingTransferPosition::KIND_MATERIAL])
            ->map(static fn (BillingTransferPosition $position): ?int => LocalArticleCatalogSource::idOf($position->article_ref))
            ->all(), static fn (?int $id): bool => $id !== null));
        foreach ($this->places->forArticles($organization, $articleIds) as $place) {
            $places[$place->key()] = $place;
        }

        return array_values($places);
    }
}
