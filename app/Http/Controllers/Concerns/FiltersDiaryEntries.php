<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FiltersDiaryEntries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Concerns;

use App\Enums\Diary\{LocationMode, Mode};
use App\Models\{Customer, DiaryEntry, EntryType, Project, Tag};
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gemeinsame Filterlogik für die Auftragsbuch-Listen. Wird von
 * DiaryController (/diary) und DutyController (/duties?tab=diary)
 * genutzt, damit beide Listen identisch filtern und nicht auseinanderlaufen.
 */
trait FiltersDiaryEntries {
    /**
     * Wendet alle Auftragsbuch-Filter auf $query an und liefert das
     * normalisierte Filter-Array (inkl. from/to) für die View zurück.
     *
     * @param  Builder<DiaryEntry>  $query
     * @return array<string,mixed>
     */
    protected function applyDiaryFilters(Builder $query, Request $request, string $from, string $to): array {
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (int) $request->status);
        }

        // Modus-bewusste Datumsfilterung (Overlap; backlog/recurring immer).
        $query->overlappingDateRange($from, $to);

        if ($request->boolean('mine')) {
            $query->where('user_id', Auth::id());
        }

        $customerId = Sqid::decodeOrNumeric(Customer::class, (string) $request->query('customer', ''), 0);
        if ($customerId > 0) {
            $query->where('customer_id', $customerId);
        }

        if (! $request->boolean('archived')) {
            $query->where('is_archived', false);
        }

        $tagId = Sqid::decodeOrNumeric(Tag::class, (string) $request->query('tag', ''), 0);
        if ($tagId > 0) {
            $query->whereHas('tags', fn($q) => $q->where('tags.id', $tagId));
        }

        $entryTypeId = Sqid::decodeOrNumeric(EntryType::class, (string) $request->query('entry_type', ''), 0);
        if ($entryTypeId > 0) {
            $query->where('entry_type_id', $entryTypeId);
        }

        $projectId = Sqid::decodeOrNumeric(Project::class, (string) $request->query('project', ''), 0);
        if ($projectId > 0) {
            $query->where('project_id', $projectId);
        }

        $modeFilter = (string) $request->query('mode', '');
        if ($modeFilter !== '' && in_array($modeFilter, Mode::values(), true)) {
            $query->where('mode', $modeFilter);
        }

        $locationFilter = (string) $request->query('location', '');
        if ($locationFilter !== '' && in_array($locationFilter, LocationMode::values(), true)) {
            $query->where('location_mode', $locationFilter);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $query->where(function ($w) use ($like): void {
                $w->where('content', 'like', $like)->orWhere('response', 'like', $like);
            });
        }

        $filters = $request->only('status', 'mine', 'archived', 'tag', 'project', 'q', 'entry_type', 'mode', 'location', 'customer');
        $filters['from'] = $from;
        $filters['to'] = $to;

        return $filters;
    }

    /**
     * Liefert die für die Listendarstellung nötigen Spalten. Gemeinsam,
     * damit beide Listen dieselben Felder selektieren (mode/due/window für
     * die modus-abhängige Datumszeile in der Karte).
     *
     * @return array<int,string>
     */
    protected function diaryListColumns(): array {
        return ['id', 'user_id', 'content', 'status', 'is_archived', 'start_at', 'end_at', 'created_at',
            'mode', 'due_date', 'window_start_date', 'window_end_date', 'location_mode'];
    }
}
