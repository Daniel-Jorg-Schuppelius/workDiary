<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Classification;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Kern-Domänen der Klassifikationen (MVP-030).
 *
 * Quelle: ../WorkDiary-Architecture/kernklassifikationen.md §2.
 */
enum ClassificationDomain: string implements HasLabel {
    use HasOptions;

    case EntryType = 'entry_type';
    case Activity = 'activity';
    case DefectType = 'defect_type';
    case RootCause = 'root_cause';
    case Result = 'result';
    case Priority = 'priority';
    case GoodwillReason = 'goodwill_reason';
    case ReworkReason = 'rework_reason';
    case ProductGroup = 'product_group';
    case DienstmittelType = 'dienstmittel_type';
    case Allergen = 'allergen';
    case Trade = 'trade';
    case PermitType = 'permit_type';
    case WasteCode = 'waste_code';

    /** Anzeigename der Domäne (Label-Helfer, nie rohen Enum-Wert in Views). */
    public function label(): string {
        return match ($this) {
            self::EntryType => (string) __('Auftragstypen'),
            self::Activity => (string) __('Tätigkeiten'),
            self::DefectType => (string) __('Fehlertypen'),
            self::RootCause => (string) __('Ursachen'),
            self::Result => (string) __('Ergebnisse'),
            self::Priority => (string) __('Prioritäten'),
            self::GoodwillReason => (string) __('Kulanzgründe'),
            self::ReworkReason => (string) __('Nacharbeitsgründe'),
            self::ProductGroup => (string) __('Produktgruppen'),
            self::DienstmittelType => (string) __('Dienstmitteltypen'),
            self::Allergen => (string) __('Allergene'),
            self::Trade => (string) __('Gewerke'),
            self::PermitType => (string) __('Genehmigungsarten'),
            self::WasteCode => (string) __('Abfallschlüssel (AVV)'),
        };
    }
}
