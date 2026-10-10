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
    // Kundengruppe (MVP-949): Segment für Auswertungen wie den ESG-Vergleich.
    case CustomerGroup = 'customer_group';

    /** Anzeigename der Domäne (Label-Helfer, nie rohen Enum-Wert in Views). */
    public function label(): string {
        return match ($this) {
            self::EntryType => (string) __('enums.classification.classification_domain.entry_type'),
            self::Activity => (string) __('enums.classification.classification_domain.activity'),
            self::DefectType => (string) __('enums.classification.classification_domain.defect_type'),
            self::RootCause => (string) __('enums.classification.classification_domain.root_cause'),
            self::Result => (string) __('enums.classification.classification_domain.result'),
            self::Priority => (string) __('enums.classification.classification_domain.priority'),
            self::GoodwillReason => (string) __('enums.classification.classification_domain.goodwill_reason'),
            self::ReworkReason => (string) __('enums.classification.classification_domain.rework_reason'),
            self::ProductGroup => (string) __('enums.classification.classification_domain.product_group'),
            self::DienstmittelType => (string) __('enums.classification.classification_domain.dienstmittel_type'),
            self::Allergen => (string) __('enums.classification.classification_domain.allergen'),
            self::Trade => (string) __('enums.classification.classification_domain.trade'),
            self::PermitType => (string) __('enums.classification.classification_domain.permit_type'),
            self::WasteCode => (string) __('enums.classification.classification_domain.waste_code'),
            self::CustomerGroup => (string) __('enums.classification.classification_domain.customer_group'),
        };
    }
}
