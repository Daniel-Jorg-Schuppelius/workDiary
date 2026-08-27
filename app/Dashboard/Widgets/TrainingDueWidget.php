<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TrainingDueWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\Training\TrainingAssignment;
use App\Models\User;
use Illuminate\Contracts\View\View;

/**
 * Die eigenen offenen Schulungs-/Unterweisungspflichten — überfällige zuerst.
 * Kein Permission-Gate: es sind die eigenen Pflichten.
 */
class TrainingDueWidget extends Widget {
    public function key(): string {
        return 'training-due';
    }

    public function label(): string {
        return (string) __('Meine Schulungspflichten');
    }

    public function icon(): string {
        return 'school';
    }

    public function defaultOrder(): int {
        return 176;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Deadlines;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.training_due.description');
    }

    public function render(User $user): View|string {
        $assignments = TrainingAssignment::query()
            ->open()
            // scopeOpen prüft nur, dass ein Termin gesetzt ist — erfüllte
            // Pflichten müssen zusätzlich raus.
            ->whereNull('fulfilled_at')
            ->where('user_id', $user->id)
            ->with('course:id,title')
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->limit(5)
            ->get();

        return view('dashboard.widgets.training-due', [
            'assignments' => $assignments,
        ]);
    }
}
