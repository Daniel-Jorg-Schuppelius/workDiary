<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DataProtectionWidget.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Dashboard\Widgets;

use App\Dashboard\Widget;
use App\Enums\Privacy\DataSubjectRequestStatus;
use App\Models\Privacy\{DataSubjectRequest, ProcessingActivity};
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Dashboard-Kachel des Datenschutzmoduls: ueberfaellige VVT-Reviews und offene/
 * ueberfaellige Betroffenenanfragen. Sichtbar nur mit `dataprotection.view`
 * (kein Admin-Bypass) UND aktivem Modul (Plan).
 */
class DataProtectionWidget extends Widget {
    public function key(): string {
        return 'data-protection';
    }

    public function label(): string {
        return (string) __('Datenschutz');
    }

    public function icon(): string {
        return 'privacy_tip';
    }

    public function requiredModule(): ?string {
        return 'module.datenschutz';
    }

    public function availableFor(User $user): bool {
        // Modul-Gating übernimmt die Basisklasse (requiredModule); hier bleibt
        // nur der Policy-Check ohne Admin-Bypass (dataprotection.view).
        return parent::availableFor($user)
            && Gate::forUser($user)->allows('viewAny', ProcessingActivity::class);
    }

    public function render(User $user): View|string {
        $openStatuses = array_map(
            static fn (DataSubjectRequestStatus $s): string => $s->value,
            array_values(array_filter(
                DataSubjectRequestStatus::cases(),
                static fn (DataSubjectRequestStatus $s): bool => $s->isOpen(),
            )),
        );

        $overdueReviews = ProcessingActivity::query()
            ->whereNotNull('review_due_at')
            ->whereDate('review_due_at', '<', now())
            ->count();

        $openRequests = DataSubjectRequest::query()->whereIn('status', $openStatuses)->count();

        $overdueRequests = DataSubjectRequest::query()
            ->whereIn('status', $openStatuses)
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<', now())
            ->count();

        return view('dashboard.widgets.data-protection', [
            'overdueReviews' => $overdueReviews,
            'openRequests' => $openRequests,
            'overdueRequests' => $overdueRequests,
        ]);
    }
}
