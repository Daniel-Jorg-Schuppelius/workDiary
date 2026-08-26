<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkTimeSummarySection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Privacy\SubjectData;

use App\Models\{SickLeave, TimeEntry, User, Vacation};
use Illuminate\Database\Eloquent\Model;

/**
 * Arbeits-/Abwesenheitszeiten des Mitarbeiters als SUMMEN (Anzahl, Zeitraum,
 * Gesamtminuten) — bewusst keine Rohlisten im MVP (Datenminimierung in der
 * Auskunft; Detailtiefe kann je Familie nachgerüstet werden).
 */
class WorkTimeSummarySection extends AbstractSubjectSection {
    public function key(): string {
        return 'work_time';
    }

    public function title(): string {
        return __('Arbeits- und Abwesenheitszeiten (Übersicht)');
    }

    public function portable(): bool {
        return false;
    }

    public function build(Model $subject): array {
        $this->expect($subject, User::class);
        /** @var User $u */
        $u = $subject;
        $orgId = (int) $u->organization_id;

        $minutes = (int) TimeEntry::query()->withoutGlobalScopes()
            ->where('organization_id', $orgId)->where('user_id', $u->id)->sum('minutes');

        return ['families' => [
            $this->family(
                'time_entries',
                __('Zeiteinträge'),
                TimeEntry::query()->withoutGlobalScopes()->where('organization_id', $orgId)->where('user_id', $u->id),
                'date',
                ['minutes_total' => $minutes],
            ),
            $this->family(
                'vacations',
                __('Urlaubsanträge'),
                Vacation::query()->withoutGlobalScopes()->where('organization_id', $orgId)->where('user_id', $u->id),
                'start_date',
            ),
            $this->family(
                'sick_leaves',
                __('Krankmeldungen'),
                SickLeave::query()->withoutGlobalScopes()->where('organization_id', $orgId)->where('user_id', $u->id),
                'start_date',
            ),
        ]];
    }
}
