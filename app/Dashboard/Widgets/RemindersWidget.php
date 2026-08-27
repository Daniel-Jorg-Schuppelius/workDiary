<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemindersWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\{WidgetGroup, WidgetWidth};
use App\Models\User;
use App\Services\Reminders\ReminderService;
use Illuminate\Contracts\View\View;

/**
 * Die Smart-Reminder, die sonst nur unter der Glocke im Kopf hängen — als
 * Kachel mit Beschreibung und Ziel. Berechnet werden sie ohnehin für jede
 * Seite; die Kachel greift denselben Cache ab.
 */
class RemindersWidget extends Widget {
    public function __construct(private readonly ReminderService $reminders) {}

    public function key(): string {
        return 'reminders';
    }

    public function label(): string {
        return (string) __('Erinnerungen');
    }

    public function icon(): string {
        return 'notifications_active';
    }

    public function defaultOrder(): int {
        return 15;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function defaultWidth(): WidgetWidth {
        return WidgetWidth::Full;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Tasks;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.reminders.description');
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.reminders', [
            'items' => $this->reminders->for($user),
        ]);
    }
}
