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

use App\Enums\Gaeb\{BoqItemType, GaebPhase};

/**
 * GAEB-Import-Preflight (Feature 049, MVP-081). Prüft Version, Austauschphase,
 * Struktur, Ordnungszahl-Eindeutigkeit sowie Mengen-/Einheitenplausibilität,
 * bevor irgendetwas in laufende Projekte geschrieben wird. Blockierende Befunde
 * landen in `errors`, nicht-blockierende in `warnings`.
 */
class GaebPreflight {
    /** Unterstützte GAEB-DA-XML-Hauptversion (Ziellinie 3.3; Beta 3.4 bewusst nicht). */
    private const SUPPORTED_MAJOR = '3.';

    /**
     * @return array{
     *     ok: bool,
     *     errors: list<string>,
     *     warnings: list<string>,
     *     meta: array{version: ?string, phase: ?string, phase_label: ?string, item_count: int, section_count: int}
     * }
     */
    public function check(ParsedBoq $boq): array {
        $errors = [];
        $warnings = [];

        $phase = GaebPhase::fromCode($boq->phase);

        if ($boq->version === null) {
            $warnings[] = __('gaeb.preflight.version_unknown');
        } elseif (!str_starts_with($boq->version, self::SUPPORTED_MAJOR)) {
            $errors[] = __('gaeb.preflight.version_unsupported', ['version' => $boq->version]);
        }

        if ($phase === null) {
            $warnings[] = __('gaeb.preflight.phase_unknown', ['code' => (string) $boq->phase]);
        }

        if ($boq->itemCount() === 0) {
            $errors[] = __('gaeb.preflight.no_items');
        }

        $seen = [];
        foreach ($boq->items as $item) {
            $ref = $item['ref'];

            if ($ref === '') {
                $errors[] = __('gaeb.preflight.item_missing_ref', ['text' => (string) ($item['short_text'] ?? '')]);
            } elseif (isset($seen[$ref])) {
                $errors[] = __('gaeb.preflight.duplicate_ref', ['ref' => $ref]);
            } else {
                $seen[$ref] = true;
            }

            $type = BoqItemType::tryFrom($item['type']) ?? BoqItemType::Standard;

            if ($type->isBillable()) {
                if ($item['quantity'] === null) {
                    $errors[] = __('gaeb.preflight.missing_quantity', ['ref' => $ref]);
                } elseif ((float) $item['quantity'] <= 0.0) {
                    $warnings[] = __('gaeb.preflight.non_positive_quantity', ['ref' => $ref]);
                }
                if ($item['unit'] === null) {
                    $errors[] = __('gaeb.preflight.missing_unit', ['ref' => $ref]);
                }
            }

            if ($phase !== null && $phase->carriesPrices() && $type->isBillable() && $item['unit_price'] === null) {
                $warnings[] = __('gaeb.preflight.missing_price', ['ref' => $ref]);
            }

            if (($item['short_text'] ?? null) === null && ($item['long_text'] ?? null) === null) {
                $warnings[] = __('gaeb.preflight.missing_text', ['ref' => $ref]);
            }
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'meta' => [
                'version' => $boq->version,
                'phase' => $boq->phase,
                'phase_label' => $phase?->label(),
                'item_count' => $boq->itemCount(),
                'section_count' => $boq->sectionCount(),
            ],
        ];
    }
}
