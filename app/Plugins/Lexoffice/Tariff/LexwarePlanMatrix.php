<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexwarePlanMatrix.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Tariff;

use App\Enums\Lexoffice\{LexwareCoverage, LexwareFeature, LexwarePlan};

/**
 * Versionierte Funktionsmatrix der Lexware-Office-Tarife (Feature 158,
 * MVP-831). Herstellerstand vom 2026-09-21 laut
 * `WorkDiary-Architecture/lexoffice-ergaenzungen-mvp-plan-2026-09-21.md`;
 * eine geänderte Tarifpolitik ändert VERSION und die Tabelle, nie stille
 * Sonderfälle in Views.
 */
final class LexwarePlanMatrix {
    public const VERSION = '2025-09';

    /** @var list<string> Öffentliche Quellen des Standes. */
    public const SOURCES = [
        'https://www.lexware.de/preise/',
        'https://www.lexware.de/funktionen/serienrechnungen/',
        'https://help.lexware.de/de-form/articles/548863-alles-rund-um-public-api',
    ];

    /**
     * Zielmatrix: je Funktion die Abdeckung für S, M, L, XL.
     *
     * @var array<string, array{s: LexwareCoverage, m: LexwareCoverage, l: LexwareCoverage, xl: LexwareCoverage}>
     */
    private const MATRIX = [
        'invoices' => ['s' => LexwareCoverage::Supplement, 'm' => LexwareCoverage::Lexware, 'l' => LexwareCoverage::Lexware, 'xl' => LexwareCoverage::Lexware],
        'quotes' => ['s' => LexwareCoverage::Supplement, 'm' => LexwareCoverage::Lexware, 'l' => LexwareCoverage::Lexware, 'xl' => LexwareCoverage::Lexware],
        'dunning' => ['s' => LexwareCoverage::Supplement, 'm' => LexwareCoverage::Lexware, 'l' => LexwareCoverage::Lexware, 'xl' => LexwareCoverage::Lexware],
        'recurring_invoices' => ['s' => LexwareCoverage::Supplement, 'm' => LexwareCoverage::Supplement, 'l' => LexwareCoverage::Supplement, 'xl' => LexwareCoverage::Lexware],
        'partial_final_invoices' => ['s' => LexwareCoverage::Expansion, 'm' => LexwareCoverage::Expansion, 'l' => LexwareCoverage::Expansion, 'xl' => LexwareCoverage::Lexware],
        'foreign_tax_cases' => ['s' => LexwareCoverage::Expansion, 'm' => LexwareCoverage::Expansion, 'l' => LexwareCoverage::Expansion, 'xl' => LexwareCoverage::Lexware],
        'accounting' => ['s' => LexwareCoverage::Expansion, 'm' => LexwareCoverage::Expansion, 'l' => LexwareCoverage::Lexware, 'xl' => LexwareCoverage::Lexware],
        'tax_filings' => ['s' => LexwareCoverage::Expansion, 'm' => LexwareCoverage::Expansion, 'l' => LexwareCoverage::Lexware, 'xl' => LexwareCoverage::Lexware],
    ];

    /** Unbekannter oder Sondertarif: keine sichere Aussage über Lexware. */
    public function coverage(LexwarePlan $plan, LexwareFeature $feature): LexwareCoverage {
        if (! $plan->isKnown()) {
            return LexwareCoverage::Unknown;
        }

        return self::MATRIX[$feature->value][$plan->value] ?? LexwareCoverage::Unknown;
    }

    /** Eigener API-Schlüssel nur im XL-Tarif — für S/M/L keine direkte Anbindung versprechen. */
    public function allowsOwnApiKey(LexwarePlan $plan): bool {
        return $plan === LexwarePlan::XL;
    }
}
