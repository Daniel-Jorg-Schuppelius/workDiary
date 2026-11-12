<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManualTimeBooking.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Timekeeping;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Support\TagInput;
use Carbon\CarbonImmutable;

/**
 * Manuelle Projektzeit aus der Eingabeleiste auf „Heute“ — online und als
 * Offline-Befehl `time.entry` (MVP-1593). Erwartet die geprüften Daten des
 * {@see \App\Http\Requests\Time\QuickTimeEntryRequest}; Abrechnungs-Snapshot,
 * Sollzeit-Marker und Tagesableitung setzt der TimeEntryObserver wie bei jeder Buchung.
 */
final class ManualTimeBooking {
    /** @param  array<string, mixed>  $data */
    public function book(User $user, array $data): TimeEntry {
        $tagIds = TagInput::ids($data['tag_ids'] ?? []);
        $newTags = TagInput::names($data['new_tags'] ?? '');

        $project = Project::query()->findOrFail((int) $data['project_id']);

        $attributes = [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'organization_id' => $project->organization_id,
            'kind' => TimeEntryKind::Work,
            'task_id' => $data['task_id'] ?? null,
            'diary_entry_id' => $data['diary_entry_id'] ?? null,
            'description' => $data['description'] ?? null,
        ];

        if (filled($data['started_at'] ?? null) && filled($data['ended_at'] ?? null)) {
            // minutes + date leitet der Observer aus der Spanne ab; die Werte sind bereits UTC.
            $attributes['started_at'] = $data['started_at'];
            $attributes['ended_at'] = $data['ended_at'];
            $attributes['break_minutes'] = (int) ($data['break_minutes'] ?? 0);
        } else {
            $attributes['minutes'] = (int) $data['minutes'];
            $attributes['date'] = CarbonImmutable::parse((string) $data['date'])->startOfDay();
        }

        $entry = TimeEntry::create($attributes);
        $entry->syncTagsFromInput($tagIds, $newTags);

        return $entry;
    }
}
