<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Sales;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Geltungsbereich einer Provisionsregel (Feature 146).
 *
 * Der Bereich entscheidet zweierlei: ob die Regel auf einen Beleg passt und —
 * bei {@see self::ProductGroup} — worauf sich der Satz bezieht (nur die
 * Positionen der Produktgruppe statt des ganzen Belegs).
 *
 * {@see specificity()} bricht den Gleichstand bei gleicher Prioritaet: die
 * engere Regel gewinnt. Ohne diese Ordnung waere die Regelauswahl bei zwei
 * gleich priorisierten Regeln von der Zeilenreihenfolge abhaengig.
 */
enum CommissionScope: string implements HasLabel {
    use HasOptions;

    /** Grundsatz der Organisation — greift, wenn keine engere Regel passt. */
    case All = 'all';

    /** Satz je Lead-Quelle (LeadSource des Herkunfts-Leads). */
    case LeadSource = 'lead_source';

    /** Satz je Produktgruppe (articles.category der Belegpositionen). */
    case ProductGroup = 'product_group';

    /** Satz je Vertriebsperson. */
    case User = 'user';

    public function label(): string {
        return match ($this) {
            self::All => __('commission.scope.all'),
            self::LeadSource => __('commission.scope.lead_source'),
            self::ProductGroup => __('commission.scope.product_group'),
            self::User => __('commission.scope.user'),
        };
    }

    /** Hoeher = enger; entscheidet bei gleicher Prioritaet. */
    public function specificity(): int {
        return match ($this) {
            self::All => 0,
            self::LeadSource => 1,
            self::ProductGroup => 2,
            self::User => 3,
        };
    }

    /** Braucht die Regel einen Wert in `scope_value`? */
    public function needsValue(): bool {
        return $this === self::LeadSource || $this === self::ProductGroup;
    }
}
