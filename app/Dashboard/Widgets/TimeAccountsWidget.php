<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeAccountsWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\{TimeAccountBalance, User};
use Illuminate\Contracts\View\View;

/**
 * Salden der Zeitkonten des Nutzers (Überstunden, Sonderkonten …) — der
 * jeweils zuletzt berechnete Monat je Konto.
 */
class TimeAccountsWidget extends Widget {
    public function key(): string {
        return 'time-accounts';
    }

    public function label(): string {
        return (string) __('Zeitkonten');
    }

    public function icon(): string {
        return 'account_balance_wallet';
    }

    public function defaultOrder(): int {
        return 27;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Time;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.time_accounts.description');
    }

    public function render(User $user): View|string {
        $balances = TimeAccountBalance::query()
            ->where('user_id', $user->id)
            ->with('account:id,code,name,unit,warn_threshold,critical_threshold')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get()
            // Je Konto nur der jüngste Monat — die Tabelle führt eine Zeile je Monat.
            ->unique('time_account_id')
            ->values();

        return view('dashboard.widgets.time-accounts', [
            'balances' => $balances,
        ]);
    }
}
