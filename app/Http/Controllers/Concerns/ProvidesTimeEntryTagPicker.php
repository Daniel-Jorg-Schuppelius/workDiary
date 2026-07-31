<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProvidesTimeEntryTagPicker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Concerns;

use App\Models\{Tag, TimeEntry};
use App\Support\{LookupCache, Sqid, TagInput};
use Illuminate\Support\Facades\Auth;

/**
 * Gemeinsame View-Daten des <x-tag-picker> für Zeiteintrags-Formulare
 * (Projekt-Dialog, Heute-Leiste, Admin-Formular) — statt drei Kopien der
 * DiaryController-Helfer.
 */
trait ProvidesTimeEntryTagPicker {
    /**
     * @return array{allTags: \Illuminate\Support\Collection<int, Tag>, selectedTagIds: array<string>, recentTagIds: array<string>}
     */
    protected function tagPickerData(?TimeEntry $entry = null): array {
        return [
            'allTags' => LookupCache::tagOptions(),
            'selectedTagIds' => $entry?->tags->map(fn (Tag $tag): string => $tag->sqid)->values()->all() ?? [],
            'recentTagIds' => $this->recentTimeEntryTagSqids((int) Auth::id()),
        ];
    }

    /**
     * Löst tag_ids/new_tags aus dem validated()-Array heraus — die Felder sind
     * keine TimeEntry-Spalten und dürfen nicht ins Mass-Assignment.
     *
     * @param  array<string, mixed>  $data  wird in-place bereinigt
     * @return array{0: array<int, int>, 1: array<int, string>}
     */
    protected function pullTagInput(array &$data): array {
        $tagIds = TagInput::ids($data['tag_ids'] ?? []);
        $newTags = TagInput::names($data['new_tags'] ?? '');
        unset($data['tag_ids'], $data['new_tags']);

        return [$tagIds, $newTags];
    }

    /**
     * Zuletzt vom Nutzer vergebene Zeiteintrags-Tags (Schnellauswahl). Die
     * Pivot `taggables` hat keine Timestamps — Reihenfolge über die jüngsten
     * Zeiteinträge des Nutzers.
     *
     * @return array<string> Opake Tag-Sqids (Format des Tag-Pickers).
     */
    protected function recentTimeEntryTagSqids(int $userId, int $limit = 8): array {
        return Tag::query()
            ->select('tags.id')
            ->join('taggables', 'taggables.tag_id', '=', 'tags.id')
            ->join('time_entries', function ($join): void {
                $join->on('time_entries.id', '=', 'taggables.taggable_id')
                    ->where('taggables.taggable_type', '=', (new TimeEntry())->getMorphClass());
            })
            ->where('time_entries.user_id', $userId)
            ->orderByDesc('time_entries.id')
            ->pluck('tags.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take($limit)
            ->map(fn (int $id): string => Sqid::encode(Tag::class, $id))
            ->values()
            ->all();
    }
}
