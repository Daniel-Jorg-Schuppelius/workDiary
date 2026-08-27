<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FlexBalanceWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\{FlexBalance, User};
use App\Services\Flextime\FlexTrafficLight;
use Illuminate\Contracts\View\View;

/**
 * Gleitzeit-Saldo mit Ampel.
 *
 * Liest den gespeicherten Monatssaldo (flex_balances) statt ihn neu zu
 * rechnen — FlexCalculator::monthlyBalance() läuft Tag für Tag und ist für
 * eine Kachel zu teuer. Ist der laufende Monat noch nicht berechnet, zeigt
 * die Kachel den zuletzt berechneten Monat samt Datum.
 */
class FlexBalanceWidget extends Widget {
    public function key(): string {
        return 'flex-balance';
    }

    public function label(): string {
        return (string) __('Arbeitszeitkonto');
    }

    public function icon(): string {
        return 'balance';
    }

    public function defaultOrder(): int {
        return 26;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Time;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.flex_balance.description');
    }

    public function render(User $user): View|string {
        $balance = FlexBalance::query()
            ->where('user_id', $user->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        $light = FlexTrafficLight::current();

        return view('dashboard.widgets.flex-balance', [
            'balance' => $balance,
            'tone' => $balance !== null ? $light->tone($balance->balance_minutes) : 'ghost',
        ]);
    }
}
