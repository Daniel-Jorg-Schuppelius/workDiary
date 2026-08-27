<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanbanStatusWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\Diary\Status;
use App\Models\{DiaryEntry, User};
use Illuminate\Contracts\View\View;

/**
 * Statusverteilung der Aufträge, die dem Nutzer zugewiesen sind — dieselben
 * Spalten wie das Kanban-Board, nur als Zähler.
 */
class KanbanStatusWidget extends Widget {
    /** Spalten, die das Board als „in Arbeit" führt (ohne Abschluss-Status). */
    private const ACTIVE_STATUSES = [
        Status::Planned,
        Status::Accepted,
        Status::InProgress,
        Status::WaitingCustomer,
        Status::WaitingMaterial,
    ];

    public function key(): string {
        return 'kanban-status';
    }

    public function label(): string {
        return (string) __('Meine Aufträge im Kanban');
    }

    public function icon(): string {
        return 'view_kanban';
    }

    public function defaultOrder(): int {
        return 72;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Tasks;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.kanban_status.description');
    }

    public function requiredModule(): ?string {
        return 'module.kanban';
    }

    public function render(User $user): View|string {
        $counts = DiaryEntry::query()
            ->where('assigned_user_id', $user->id)
            ->where('is_archived', false)
            ->whereIn('status', array_map(static fn (Status $s): int => $s->value, self::ACTIVE_STATUSES))
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return view('dashboard.widgets.kanban-status', [
            'statuses' => self::ACTIVE_STATUSES,
            'counts' => $counts,
        ]);
    }
}
