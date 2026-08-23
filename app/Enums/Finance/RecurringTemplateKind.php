<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurringTemplateKind.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Finance;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Art einer wiederkehrenden Vorlage (Feature 125, MVP-675).
 *
 * Die Trennung ist der Kern des Pakets: Eine **Belegerwartung** weiß nur, dass
 * ein Beleg kommen sollte — sie erzeugt keinen. Eine **Buchungsvorlage** darf
 * einen Buchungsentwurf erzeugen, aber nur für Vorgänge, die fachlich ohne
 * neue Lieferantenrechnung gebucht werden.
 *
 * Wiederkehrende Ausgangsrechnungen sind bewusst NICHT hier: Sie bleiben beim
 * vorhandenen `InvoiceSchedule`.
 */
enum RecurringTemplateKind: string implements HasLabel {
    use HasOptions;

    case DocumentExpectation = 'document_expectation';
    case PostingTemplate = 'posting_template';

    public function label(): string {
        return (string) __('enums.finance.recurring-template-kind.' . $this->value);
    }

    public function tone(): string {
        return $this === self::DocumentExpectation ? 'info' : 'secondary';
    }

    /** Darf der Lauf einen Buchungsentwurf erzeugen? */
    public function createsDraft(): bool {
        return $this === self::PostingTemplate;
    }
}
