<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxCaseAlignment.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Invoicing;

/**
 * Ergebnis des Steuerfall-Abgleichs eines Entwurfs (MVP-1623,
 * {@see \App\Services\Invoicing\InvoiceIssueService::alignTaxCase()}).
 * `NoteKept`: Der Fall ist angepasst, der Hinweistext aber von Hand geändert
 * und deshalb stehen geblieben — die Person prüft ihn selbst.
 * `Mixed`: Die Positionen ergeben nach ihrem Leistungsort verschiedene
 * Steuerfälle (MVP-1688); der Entwurf bleibt, ausgestellt wird er nicht.
 * `LineRates`: Der Fall stimmt, aber Positionen ohne eigene Steuerkategorie
 * trugen einen widersprechenden Satz und folgen ihm jetzt (MVP-1718); die
 * Summen ändern sich, die Person prüft sie.
 */
enum TaxCaseAlignment: string {
    case Unchanged = 'unchanged';
    case Aligned = 'aligned';
    case NoteKept = 'note_kept';
    case Mixed = 'mixed';
    case LineRates = 'line_rates';

    public function changed(): bool {
        return $this === self::Aligned || $this === self::NoteKept || $this === self::LineRates;
    }
}
