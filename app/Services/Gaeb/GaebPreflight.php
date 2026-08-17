<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GaebPreflight.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Gaeb;

use App\Enums\Gaeb\GaebPhase;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use ERechnungToolkit\Entities\Gaeb\GaebBoq;
use ERechnungToolkit\Helper\Gaeb\GaebCalculator;

/**
 * GAEB-Import-Preflight (Feature 049, MVP-081; Feature 108). Prüft Version,
 * Austauschphase, Struktur, Ordnungszahl-Eindeutigkeit sowie Mengen-,
 * Einheiten- und Preisplausibilität, bevor irgendetwas in laufende Projekte
 * geschrieben wird. Blockierende Befunde landen in `errors`, nicht-blockierende
 * in `warnings`.
 *
 * Das Lesen selbst macht das erechnung-toolkit; hier stehen nur die Regeln, die
 * WorkDiary für seine eigenen Daten durchsetzt.
 */
class GaebPreflight {
    /** Unterstützte GAEB-DA-XML-Hauptversion (Ziellinie 3.3; Beta 3.4 bewusst nicht). */
    private const SUPPORTED_MAJOR = '3.';

    /**
     * Prüfung vor der Abgabe (MVP-569): nimmt vorweg, was ava-sign beim Reimport
     * der DA84 prüft — bepreist oder ausdrücklich nicht angeboten, gefüllte
     * Textlücken, stimmige Angebotssumme — und ergänzt, was das Schema für die
     * Phase verlangt (Bieteranschrift in X84/X86/X87).
     *
     * @return array{
     *     ok: bool,
     *     errors: list<string>,
     *     warnings: list<string>,
     *     meta: array{version: ?string, phase: ?string, phase_label: ?string, item_count: int, section_count: int}
     * }
     */
    public function checkForExport(GaebBoq $boq, GaebPhase $phase, bool $hasContractor = true): array {
        $report = $this->check($boq);
        $errors = $report['errors'];
        $warnings = $report['warnings'];

        // Die gelieferte Summe wird nachgerechnet; wer rechnen kann, rechnet
        // selbst (GAEB-Regel) — Abweichungen sind ein Befund, keine Korrektur.
        $calculator = new GaebCalculator;
        $stated = $boq->getTotals()?->getTotal();
        if ($stated !== null && $calculator->statedTotalMatches($boq) === false) {
            $errors[] = __('gaeb.preflight.total_mismatch', [
                'stated' => $stated->format(false),
                'computed' => $calculator->documentTotal($boq)->format(false),
            ]);
        }

        foreach ($boq->getItems() as $item) {
            foreach ($item->getTextComplements() as $complement) {
                if (!$complement->isBidderComplement()) {
                    continue;
                }
                if (trim((string) $complement->getBody()) === '') {
                    $errors[] = __('gaeb.preflight.complement_empty', [
                        'ref' => $item->getReference(),
                        'mark' => $complement->getMark(),
                    ]);
                }
            }
        }

        // CTR ist in Angebotsabgabe, Auftrag und Bestätigung Pflicht; ohne
        // Anschrift wäre die Datei schemawidrig.
        if (!$hasContractor && in_array($phase, [GaebPhase::Bid, GaebPhase::Award, GaebPhase::AwardConfirmation], true)) {
            $errors[] = __('gaeb.preflight.contractor_missing');
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'meta' => $report['meta'],
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     errors: list<string>,
     *     warnings: list<string>,
     *     meta: array{version: ?string, phase: ?string, phase_label: ?string, item_count: int, section_count: int}
     * }
     */
    public function check(GaebBoq $boq): array {
        $errors = [];
        $warnings = [];

        $phase = GaebPhase::fromCode($boq->getPhaseCode());

        $version = $boq->getVersion();
        if ($version === null) {
            $warnings[] = __('gaeb.preflight.version_unknown');
        } elseif (!str_starts_with($version, self::SUPPORTED_MAJOR)) {
            $errors[] = __('gaeb.preflight.version_unsupported', ['version' => $version]);
        }

        if ($phase === null) {
            $warnings[] = __('gaeb.preflight.phase_unknown', ['code' => (string) $boq->getPhaseCode()]);
        }

        if ($boq->countItems() === 0) {
            $errors[] = __('gaeb.preflight.no_items');
        }

        $seen = [];
        foreach ($boq->getItems() as $item) {
            $ref = $item->getReference();

            if ($ref === '') {
                $errors[] = __('gaeb.preflight.item_missing_ref', ['text' => (string) $item->getShortText()]);
            } elseif (isset($seen[$ref])) {
                $errors[] = __('gaeb.preflight.duplicate_ref', ['ref' => $ref]);
            } else {
                $seen[$ref] = true;
            }

            // Menge und Einheit nur fordern, wo die Phase sie verlangt: in der
            // X84 entfallen sie regulär, und außerhalb der LV-Phasen (z. B. X31
            // Mengenermittlung) kennen wir die Regel nicht.
            if ($item->getType()->isBillable() && $phase !== null && $phase->carriesQuantities()) {
                $quantity = $item->getQuantity();
                if ($quantity === null) {
                    $errors[] = __('gaeb.preflight.missing_quantity', ['ref' => $ref]);
                } elseif ((float) $quantity <= 0.0) {
                    $warnings[] = __('gaeb.preflight.non_positive_quantity', ['ref' => $ref]);
                }
                if ($item->getUnit() === null) {
                    $errors[] = __('gaeb.preflight.missing_unit', ['ref' => $ref]);
                }
            }

            // In der Angebotsabgabe muss jede Position entweder bepreist oder
            // ausdrücklich als „nicht angeboten" gekennzeichnet sein — genau das
            // prüft ava-sign beim Reimport der DA84. In den übrigen
            // preisführenden Phasen bleibt es eine Warnung.
            if ($phase !== null && $phase->carriesPrices() && $item->expectsUnitPrice() && $item->getUnitPrice() === null) {
                if ($phase === GaebPhase::Bid) {
                    $errors[] = __('gaeb.preflight.unpriced_item', ['ref' => $ref]);
                } else {
                    $warnings[] = __('gaeb.preflight.missing_price', ['ref' => $ref]);
                }
            }

            // Umgekehrt: eine abgelehnte Position darf keinen Preis tragen.
            if ($item->isNotOffered() && $item->getUnitPrice() !== null) {
                $errors[] = __('gaeb.preflight.priced_but_not_offered', ['ref' => $ref]);
            }

            if ($item->getShortText() === null && $item->getLongText() === null) {
                $warnings[] = __('gaeb.preflight.missing_text', ['ref' => $ref]);
            }

            // Die Summe der Einheitspreisanteile muss den Einheitspreis ergeben
            // (GAEB 3.3, Aufgliederung von Einheitspreisen).
            if (!$item->unitPriceComponentsAddUp()) {
                // Money statt float: der Befund nennt exakt die Beträge, an
                // denen der Bieter gemessen wird.
                $price = $item->getUnitPrice();
                $sum = Money::zero($price?->getCurrency() ?? CurrencyCode::Euro, $price?->getScale() ?? 4);
                foreach ($item->getUnitPriceComponents() as $share) {
                    $sum = $sum->plus($share);
                }
                $errors[] = __('gaeb.preflight.up_components_mismatch', [
                    'ref' => $ref,
                    'sum' => $sum->format(false),
                    'price' => $price?->format(false) ?? '0,00',
                ]);
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'meta' => [
                'version' => $boq->getVersion(),
                'phase' => $boq->getPhaseCode(),
                'phase_label' => $phase?->label(),
                'item_count' => $boq->countItems(),
                'section_count' => $boq->countSections(),
            ],
        ];
    }
}
