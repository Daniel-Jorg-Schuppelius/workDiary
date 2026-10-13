<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentRelationKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Was ein verbundener Beleg für den betrachteten ist (MVP-1157): Die Quelle
 * beschreibt immer die andere Seite.
 */
enum DocumentRelationKind: string implements HasLabel {
    use HasOptions;

    /** Grundlage, aus der dieser Beleg entstand (Angebot, Auftrag, Pro-forma, Plan). */
    case Source = 'source';
    /** Beleg, der aus diesem entstand. */
    case FollowUp = 'follow_up';
    /** Andere Version desselben Belegs. */
    case Version = 'version';
    /** Ursprungsbeleg, den dieser korrigiert oder storniert. */
    case Corrects = 'corrects';
    /** Gutschrift oder Storno zu diesem Beleg. */
    case CorrectedBy = 'corrected_by';
    /** Abschlag, den diese Schlussrechnung anrechnet. */
    case Settles = 'settles';
    /** Schlussrechnung, die diesen Abschlag anrechnet. */
    case SettledBy = 'settled_by';
    /** Gegenstück im Buchhaltungsprogramm bzw. in workDiary. */
    case Counterpart = 'counterpart';
    /** Bezug, den das Buchhaltungsprogramm ohne Richtung meldet. */
    case Related = 'related';

    public function label(): string {
        return (string) __('enums.billing.document_relation_kind.' . $this->value);
    }
}
