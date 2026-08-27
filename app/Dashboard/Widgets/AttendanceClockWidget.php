<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AttendanceClockWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Models\{Attendance, User};
use App\Services\Attendance\AttendanceClockService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Dashboard-Kachel „Stempeluhr": Ein-/Ausstempeln, Pause, Zwischen-Status
 * direkt vom Dashboard. Rendert bewusst dasselbe Panel wie die Tagesansicht
 * (attendances._panel), damit Stempel-Mechanik nur an einer Stelle gepflegt
 * wird; der Header-Chip im Layout bleibt als Schnellzugriff daneben bestehen.
 */
class AttendanceClockWidget extends Widget {
    public function __construct(private readonly AttendanceClockService $clock) {}

    public function key(): string {
        return 'attendance-clock';
    }

    public function label(): string {
        return (string) __('Stempeluhr');
    }

    public function icon(): string {
        return 'punch_clock';
    }

    public function defaultOrder(): int {
        return 20;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Time;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.attendance_clock.description');
    }

    public function availableFor(User $user): bool {
        return parent::availableFor($user)
            && Gate::forUser($user)->allows('viewAny', Attendance::class);
    }

    public function render(User $user): View|string {
        return view('attendances._panel', [
            'current' => $this->clock->current($user),
        ]);
    }
}
