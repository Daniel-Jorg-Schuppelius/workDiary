<?php
/*
 * Created on   : Thu Aug 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupStatusWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Dashboard\WidgetGroup;
use App\Enums\User\Permission;
use App\Models\User;
use App\Services\Backup\BackupStatusService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/** Frische der Sicherungen je Quelle und Stand des letzten Restore-Tests. */
class BackupStatusWidget extends Widget {
    public function __construct(private readonly BackupStatusService $status) {}

    public function key(): string {
        return 'backup-status';
    }

    public function label(): string {
        return (string) __('Sicherungen');
    }

    public function icon(): string {
        return 'backup';
    }

    public function defaultOrder(): int {
        return 162;
    }

    public function defaultHidden(): bool {
        return true;
    }

    public function group(): WidgetGroup {
        return WidgetGroup::Operations;
    }

    public function description(): ?string {
        return (string) __('dashboard.widget.backup_status.description');
    }

    public function availableFor(User $user): bool {
        // Der Sicherungsstand gilt der ganzen Installation, nicht dem
        // Mandanten — wie die zugehörige Seite bleibt die Kachel dem
        // Betreiber vorbehalten (Sicherheitsscan 2026-08-23, S-02).
        return $user->isGlobalAdmin() && Gate::forUser($user)->allows(Permission::BackupView->value);
    }

    public function render(User $user): View|string {
        return view('dashboard.widgets.backup-status', [
            'status' => $this->status->collect(),
        ]);
    }
}
