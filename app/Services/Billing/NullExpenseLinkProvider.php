<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullExpenseLinkProvider.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\{Expense, ExternalReference};
use App\Services\Billing\Contracts\ExpenseLinkProvider;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Kein Buchhaltungssystem angebunden (Vollscan 2026-08-23, B9): keine
 * Vorschläge, kein Push — und beim Versuch eine klare Meldung statt eines
 * stillen No-ops. Lesende Wege (referenceFor/voucherFor/wasPushed) antworten
 * leer, damit der Beleg-Dialog ohne Sonderfall rendert.
 */
class NullExpenseLinkProvider implements ExpenseLinkProvider {
    public function label(): ?string {
        return null;
    }

    public function isAvailable(): bool {
        return false;
    }

    public function link(Expense $expense, string $voucherKey): ExpenseVoucherRef {
        throw new RuntimeException($this->message());
    }

    public function unlink(Expense $expense): void {
        // Ohne Provider gibt es keine Verknüpfung, die zu lösen wäre.
    }

    public function referenceFor(Expense $expense): ?ExternalReference {
        return null;
    }

    public function voucherFor(Expense $expense): ?ExpenseVoucherRef {
        return null;
    }

    /** @return Collection<int, ExpenseVoucherRef> */
    public function suggestionsFor(Expense $expense, int $limit = 5): Collection {
        return new Collection();
    }

    public function canPush(Expense $expense): bool {
        return false;
    }

    public function pushVoucher(Expense $expense): ExpenseVoucherRef {
        throw new RuntimeException($this->message());
    }

    public function wasPushed(Expense $expense): bool {
        return false;
    }

    private function message(): string {
        return (string) __('expenses.receipt.no_provider_hint');
    }
}
