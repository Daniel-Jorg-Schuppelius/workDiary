<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionsWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\TimeApproval\TimeCorrectionStatus;
use App\Models\{TimeCorrectionRequest, User};
use Illuminate\Contracts\View\View;

/** Eigene Zeitkorrektur-Anträge, die noch offen sind (Entwurf/eingereicht). */
class TimeCorrectionsWidget extends Widget {
    public function key(): string {
        return 'time-corrections';
    }

    public function label(): string {
        return (string) __('Offene Zeitkorrekturen');
    }

    public function icon(): string {
        return 'edit_calendar';
    }

    public function defaultOrder(): int {
        return 28;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Time;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.time_corrections.description');
    }

    public function render(User $user): View|string {
        $requests = TimeCorrectionRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [TimeCorrectionStatus::Draft, TimeCorrectionStatus::Submitted])
            ->orderByDesc('scope_date')
            ->limit(5)
            ->get();

        return view('dashboard.widgets.time-corrections', [
            'requests' => $requests,
        ]);
    }
}
