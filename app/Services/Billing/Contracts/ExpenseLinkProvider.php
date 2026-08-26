<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExpenseLinkProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\{Expense, ExternalReference};
use App\Services\Billing\ExpenseVoucherRef;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Naht zwischen Auslage (Feature 105/106) und dem Buchhaltungssystem, das die
 * Belege führt (Vollscan 2026-08-23, B9).
 *
 * Vorher hingen `DocumentLinks` und `ExpenseVoucherPush` hart an
 * `LexofficeVoucher`; der Kern kannte damit eine Plugin-Tabelle. Ab hier
 * spricht der Kern nur noch dieses Interface und bekommt neutrale
 * {@see ExpenseVoucherRef}-Werte zurück. Getragen wird die Zuordnung weiterhin
 * von {@see ExternalReference} — polymorph und genau dafür da.
 *
 * Entscheid E8: Der aktive Auslagen-Push bleibt Lexoffice-only. Es gibt daher
 * bewusst genau EINE echte Implementierung
 * ({@see \App\Plugins\Lexoffice\LexofficeExpenseLinkProvider}) plus den
 * {@see \App\Services\Billing\NullExpenseLinkProvider} für Organisationen ohne
 * angebundene Buchhaltung — kein Provider heißt „keine Vorschläge, kein Push",
 * nicht „stiller Fehler".
 */
interface ExpenseLinkProvider {
    /** Anzeigename des führenden Systems (UI-Meldungen); null = keines. */
    public function label(): ?string;

    /** Trägt dieser Provider im aktuellen Organisationskontext? */
    public function isAvailable(): bool;

    /**
     * Bestätigt die Zuordnung einer Auslage zu einem Beleg des Providers.
     *
     * @param  string  $voucherKey  Formularschlüssel des Belegs (Sqid, provider-eigen)
     *
     * @throws RuntimeException wenn ohne Provider zugeordnet werden soll
     */
    public function link(Expense $expense, string $voucherKey): ExpenseVoucherRef;

    /**
     * Hebt die Zuordnung wieder auf — die Auslage zählt danach wieder selbst.
     *
     * @throws RuntimeException bei einer aktiv gepushten Verknüpfung
     */
    public function unlink(Expense $expense): void;

    /** Die Verknüpfungszeile selbst, falls vorhanden. */
    public function referenceFor(Expense $expense): ?ExternalReference;

    /** Zugeordneter Beleg, falls vorhanden. */
    public function voucherFor(Expense $expense): ?ExpenseVoucherRef;

    /**
     * Belegkandidaten zu einer Auslage (Betrag/Datum im Toleranzfenster).
     *
     * @return Collection<int, ExpenseVoucherRef>
     */
    public function suggestionsFor(Expense $expense, int $limit = 5): Collection;

    /** Ist der aktive Belegpush für diese Auslage anbietbar? */
    public function canPush(Expense $expense): bool;

    /**
     * Legt die genehmigte Auslage als Einkaufsbeleg im führenden System an.
     *
     * @throws RuntimeException wenn ein Wächter verletzt ist
     */
    public function pushVoucher(Expense $expense): ExpenseVoucherRef;

    /** Wurde diese Auslage aktiv gepusht (im Gegensatz zur bloßen Zuordnung)? */
    public function wasPushed(Expense $expense): bool;
}
