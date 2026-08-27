<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StopwatchWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\User;
use App\Services\Timesheet\Stopwatch;
use Illuminate\Contracts\View\View;

/**
 * Laufende Projektzeit-Erfassung. Standardmäßig aus: den Kurzstatus zeigt
 * schon der Kopf der Seite; die Kachel ergänzt Projekt, Beschreibung und
 * einen Weg zum Stundenzettel.
 */
class StopwatchWidget extends Widget {
    public function __construct(private readonly Stopwatch $stopwatch) {}

    public function key(): string {
        return 'stopwatch';
    }

    public function label(): string {
        return (string) __('Stoppuhr');
    }

    public function icon(): string {
        return 'timer';
    }

    public function defaultOrder(): int {
        return 25;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Time;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.stopwatch.description');
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.stopwatch', [
            'entry' => $this->stopwatch->current($user),
        ]);
    }
}
