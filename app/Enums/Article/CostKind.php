<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CostKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Article;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Kostenart der Kalkulation (MVP-1055) — dieselben Arten wie die
 * Einheitspreis-Aufgliederung von GAEB und die Zeilen des EFB-Formblatts 221.
 */
enum CostKind: string implements HasLabel {
    use HasOptions;

    case Labour = 'labour';
    case Material = 'material';
    case Equipment = 'equipment';
    case Other = 'other';
    case Subcontract = 'subcontract';

    public function label(): string {
        return match ($this) {
            self::Labour => (string) __('article.calculation.kind.labour'),
            self::Material => (string) __('article.calculation.kind.material'),
            self::Equipment => (string) __('article.calculation.kind.equipment'),
            self::Other => (string) __('article.calculation.kind.other'),
            self::Subcontract => (string) __('article.calculation.kind.subcontract'),
        };
    }

    /**
     * Kostenart eines EP-Anteils aus dem GAEB-LV-Kopf (MVP-1056): Typattribut
     * `Wages|Materials|Plant|Miscellaneous`, sonst die Bezeichnung.
     */
    public static function fromGaebComponent(?string $category, ?string $label): self {
        return match (strtolower(trim((string) $category))) {
            'wages' => self::Labour,
            'materials', 'material' => self::Material,
            'plant', 'equipment' => self::Equipment,
            'miscellaneous' => self::Other,
            default => match (true) {
                (bool) preg_match('/lohn|wage|labou?r/i', (string) $label) => self::Labour,
                (bool) preg_match('/stoff|material/i', (string) $label) => self::Material,
                (bool) preg_match('/ger(ä|ae|a)t|plant|maschin/i', (string) $label) => self::Equipment,
                (bool) preg_match('/nachunternehm|fremd|subunternehm/i', (string) $label) => self::Subcontract,
                default => self::Other,
            },
        };
    }

    /** Arbeits- und Maschinenkosten zählen für § 35a EStG (MVP-1053), Stoffe und Fremdleistung nicht. */
    public function countsAsLabourCost(): bool {
        return $this === self::Labour || $this === self::Equipment;
    }
}
