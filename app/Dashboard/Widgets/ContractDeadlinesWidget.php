<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractDeadlinesWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\User\Permission;
use App\Models\Contract\ContractObligation;
use App\Models\User;
use App\Support\Query\DateRange;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/** Offene Vertragspflichten und -fristen der nächsten Wochen. */
class ContractDeadlinesWidget extends Widget {
    private const WINDOW_DAYS = 60;

    public function key(): string {
        return 'contract-deadlines';
    }

    public function label(): string {
        return (string) __('Vertragsfristen');
    }

    public function icon(): string {
        return 'contract';
    }

    public function defaultOrder(): int {
        return 173;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Deadlines;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.contract_deadlines.description');
    }

    public function requiredModule(): ?string {
        return 'module.contracts';
    }

    public function availableFor(User $user): bool {
        return parent::availableFor($user)
            && Gate::forUser($user)->allows(Permission::ContractViewAny->value);
    }

    public function render(User $user): View|string {
        $obligations = ContractObligation::query()
            ->open()
            ->whereNotNull('due_on')
            ->where('due_on', '<=', DateRange::day(now()->addDays(self::WINDOW_DAYS)))
            ->with('contract:id,number,title')
            ->orderBy('due_on')
            ->limit(5)
            ->get();

        return view('dashboard.widgets.contract-deadlines', [
            'obligations' => $obligations,
        ]);
    }
}
