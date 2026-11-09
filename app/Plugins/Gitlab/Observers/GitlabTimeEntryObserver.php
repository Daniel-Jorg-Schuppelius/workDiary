<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GitlabTimeEntryObserver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Gitlab\Observers;

use App\Models\Integration\ExternalReference;
use App\Models\Project\Task;
use App\Models\Time\TimeEntry;
use App\Plugins\Gitlab\{GitlabConfig, GitlabPlugin};
use App\Plugins\Gitlab\Services\GitlabIssueWritebackDispatcher;
use App\Services\Integration\IntegrationOutboxService;

/**
 * Zeit-Rückkanal nach GitLab (MVP-1513): Zeit zu einer Issue-verknüpften
 * Aufgabe geht als Outbox-Eintrag an den {@see GitlabIssueWritebackDispatcher}.
 * Auch ein späterer Wechsel auf eine verknüpfte Aufgabe oder ein gestoppter
 * Timer (0 → n Minuten) löst aus, solange der Eintrag noch nicht gebucht ist.
 */
class GitlabTimeEntryObserver {
    public function __construct(private readonly IntegrationOutboxService $outbox) {}

    public function created(TimeEntry $timeEntry): void {
        $this->enqueue($timeEntry);
    }

    public function updated(TimeEntry $timeEntry): void {
        if (array_intersect(array_keys($timeEntry->getChanges()), ['task_id', 'minutes']) === []) {
            return;
        }

        $this->enqueue($timeEntry);
    }

    private function enqueue(TimeEntry $timeEntry): void {
        $organizationId = (int) $timeEntry->organization_id;
        if ($timeEntry->task_id === null || (int) $timeEntry->minutes <= 0 || ! GitlabConfig::timeWritebackEnabled($organizationId)) {
            return;
        }

        $linked = ExternalReference::query()
            ->forPlugin($organizationId, GitlabPlugin::ID, GitlabPlugin::EXT_TYPE_ISSUE)
            ->where('referenceable_type', (new Task)->getMorphClass())
            ->where('referenceable_id', $timeEntry->task_id)
            ->exists();
        if (! $linked || GitlabIssueWritebackDispatcher::alreadyBooked($organizationId, $timeEntry)) {
            return;
        }

        $this->outbox->enqueue(
            $organizationId,
            GitlabPlugin::ID,
            GitlabIssueWritebackDispatcher::OP_SPENT_TIME,
            ['time_entry_id' => $timeEntry->getKey()],
            // Aufgabe im Schlüssel: nach einem Wechsel auf ein anderes Issue zählt der neue Stand.
            'gitlab-spent-time:' . $timeEntry->getKey() . ':' . $timeEntry->task_id,
            $timeEntry,
        );
    }
}
