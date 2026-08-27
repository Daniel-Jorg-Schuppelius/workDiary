<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApprovalsWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Contracts\View\View;

/**
 * Was auf eine Entscheidung wartet: Spesen und Urlaubsanträge. Bewusst eine
 * eigene Kachel neben „Finanzen & Reisen" — wer nur genehmigt, braucht die
 * Monatszahlen nicht und kann sie ausblenden.
 */
class ApprovalsWidget extends Widget {
    public function __construct(private readonly DashboardService $service) {}

    public function key(): string {
        return 'approvals';
    }

    public function label(): string {
        return (string) __('Offene Genehmigungen');
    }

    public function icon(): string {
        return 'rule';
    }

    public function defaultOrder(): int {
        return 74;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Tasks;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.approvals.description');
    }

    public function availableFor(User $user): bool {
        return $user->isAdmin();
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.approvals', [
            'pending' => $this->service->approverPending($user) ?? ['expenses' => 0, 'vacations' => 0],
        ]);
    }
}
