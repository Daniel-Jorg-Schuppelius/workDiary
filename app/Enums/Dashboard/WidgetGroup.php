<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WidgetGroup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Dashboard;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Thematische Gruppe einer Dashboard-Kachel. Ordnet die Anpassungsseite;
 * auf dem Dashboard selbst zählt allein die Sortierung des Nutzers.
 */
enum WidgetGroup: string implements HasLabel {
    use HasOptions;

    case Overview = 'overview';
    case Time = 'time';
    case Tasks = 'tasks';
    case Activity = 'activity';
    case Deadlines = 'deadlines';
    case Finance = 'finance';
    case Operations = 'operations';

    public function label(): string {
        return match ($this) {
            self::Overview => (string) __('dashboard.group.overview'),
            self::Time => (string) __('dashboard.group.time'),
            self::Tasks => (string) __('dashboard.group.tasks'),
            self::Activity => (string) __('dashboard.group.activity'),
            self::Deadlines => (string) __('dashboard.group.deadlines'),
            self::Finance => (string) __('dashboard.group.finance'),
            self::Operations => (string) __('dashboard.group.operations'),
        };
    }

    public function icon(): string {
        return match ($this) {
            self::Overview => 'dashboard',
            self::Time => 'schedule',
            self::Tasks => 'checklist',
            self::Activity => 'forum',
            self::Deadlines => 'event_upcoming',
            self::Finance => 'payments',
            self::Operations => 'settings_suggest',
        };
    }

    /** Reihenfolge der Gruppen auf der Anpassungsseite. */
    public function order(): int {
        return match ($this) {
            self::Overview => 0,
            self::Time => 1,
            self::Tasks => 2,
            self::Activity => 3,
            self::Deadlines => 4,
            self::Finance => 5,
            self::Operations => 6,
        };
    }
}
