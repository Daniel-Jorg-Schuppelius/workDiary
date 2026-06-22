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

/**
 * Kern-Domänen der Klassifikationen (MVP-030).
 *
 * Quelle: docs/kernklassifikationen.md §2.
 */
enum ClassificationDomain: string {
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
}
