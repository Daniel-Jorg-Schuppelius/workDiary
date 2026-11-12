<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Diary\Cockpit;

use App\Enums\User\Permission;
use App\Http\Controllers\Diary\OpenIssueController;
use App\Models\Platform\User;
use App\Services\Cockpit\Contracts\CockpitPanel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/** Offene Punkte an Kunde, Projekt und Asset (MVP-1597): dasselbe Panel wie am Auftrag. */
final class OpenIssueCockpitPanel implements CockpitPanel {
    public function order(): int {
        return 30;
    }

    public function supports(Model $subject): bool {
        return $this->kindOf($subject) !== null;
    }

    public function render(Model $subject, User $viewer): ?View {
        $kind = $this->kindOf($subject);
        if ($kind === null
            || (! $viewer->can(Permission::OpenIssueViewAny->value) && ! $viewer->can(Permission::OpenIssueView->value) && ! $viewer->can(Permission::OpenIssueCreate->value))) {
            return null;
        }

        return view('open-issues._panel', ['subject' => $subject, 'subjectKind' => $kind]);
    }

    private function kindOf(Model $subject): ?string {
        foreach (OpenIssueController::SUBJECT_MAP as $kind => $class) {
            // Der Auftrag bindet das Panel selbst ein.
            if ($kind !== 'diary' && $subject instanceof $class) {
                return $kind;
            }
        }

        return null;
    }
}
