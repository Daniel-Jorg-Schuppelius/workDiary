<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaxFilingsWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\User\Permission;
use App\Models\Accounting\AccountingFilingObligation;
use App\Models\User;
use App\Support\Query\DateRange;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/** Die nächsten Melde- und Abgabetermine (Umsatzsteuer, Zusammenfassung …). */
class TaxFilingsWidget extends Widget {
    private const WINDOW_DAYS = 45;

    public function key(): string {
        return 'tax-filings';
    }

    public function label(): string {
        return (string) __('Steuertermine');
    }

    public function icon(): string {
        return 'event_note';
    }

    public function defaultOrder(): int {
        return 133;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Finance;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.tax_filings.description');
    }

    public function availableFor(User $user): bool {
        return Gate::forUser($user)->allows(Permission::AccountingLedgerView->value);
    }

    public function render(User $user): View|string {
        $obligations = AccountingFilingObligation::query()
            ->whereNull('submitted_at')
            ->whereNotNull('due_on')
            ->where('due_on', '<=', DateRange::day(now()->addDays(self::WINDOW_DAYS)))
            ->orderBy('due_on')
            ->limit(5)
            ->get();

        return view('dashboard.widgets.tax-filings', [
            'obligations' => $obligations,
        ]);
    }
}
