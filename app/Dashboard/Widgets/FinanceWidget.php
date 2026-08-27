<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinanceWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\{WidgetGroup, WidgetWidth};
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;

/** Spesen-/Reise-Monatszahlen und (für Admins) der Genehmigungs-Stack. */
class FinanceWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'finance';
    }

    public function label(): string {
        return (string) __('Finanzen & Reisen');
    }

    public function icon(): string {
        return 'payments';
    }

    public function defaultOrder(): int {
        return 130;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.finance.description');
    }

    public function defaultWidth(): WidgetWidth {
        return WidgetWidth::Full;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Finance;
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.finance', [
            'finance' => $this->service->finance($user),
            'now' => $this->service->now(),
        ]);
    }
}
