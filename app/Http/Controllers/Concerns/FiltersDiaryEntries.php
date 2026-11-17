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
use App\Models\Classification\{EntryType, Tag};
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Project\Project;
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
            // Benannte Status-Gruppen (z. B. die /duties-KPI-Kacheln, die mehrere
            // Status zusammenfassen) — sonst Einzelstatus wie bisher (numerisch).
            // 9–11 sind die Prüfzustände (Feature 198): zur Prüfung und freigegeben
            // laufen wie „in Bearbeitung“, die Rückfrage ist ein Alarm.
            $statusGroups = [
                'progress' => [1, 4, 9, 11],             // In Bearbeitung (InProgress, Accepted, InReview, ReviewApproved)
                'active' => [1, 3, 4, 5, 9, 10, 11],     // Kachel „In Bearbeitung“ des Auftragsbuchs (progress + alert)
                'open' => [2, 3],                        // Dashboard-Kachel „offene Einträge“ (Open, Problem)
                'alert' => [3, 5, 10],                   // Probleme (WaitingCustomer, WaitingMaterial, ReviewQueried)
                'done' => [-1, 6, 7],                    // Erledigt (Done, AcceptedFinal, Invoiced)
                'review' => [9, 10, 11],                 // Interne Prüfung (Feature 198)
            ];
            $raw = (string) $request->status;
            if (isset($statusGroups[$raw])) {
                $query->whereIn('status', $statusGroups[$raw]);
            } else {
                $query->where('status', (int) $raw);
            }
        }

        // Modus-bewusste Datumsfilterung (Overlap; backlog/recurring immer).
        $query->overlappingDateRange($from, $to);

        if ($request->boolean('mine')) {
            // Eigene Einträge; während einer Vertretung zusätzlich die den
            // Vertretenen zugewiesenen (Feature 202, MVP-1648).
            $viewer = Auth::user();
            $principals = $viewer instanceof \App\Models\Platform\User ? app(\App\Services\Delegation\ActsForResolver::class)->principalIds($viewer) : [];
            $query->where(static fn (Builder $mine) => $mine->where('user_id', Auth::id())
                ->when($principals !== [], static fn (Builder $q) => $q->orWhereIn('assigned_user_id', $principals)));
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
            $query->search($q);
        }

        // Periode (Feature 198): genau der kanonische Schlüssel; ein ungültiger Wert filtert nicht.
        $period = \App\Support\Period\PeriodKey::tryParse((string) $request->query('period', ''));
        if ($period !== null) {
            $query->where('period_key', $period->key());
        }

        $filters = $request->only('status', 'mine', 'archived', 'tag', 'project', 'q', 'entry_type', 'mode', 'location', 'customer');
        $filters['period'] = $period?->key();
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
        // user_id und assigned_user_id braucht die Karte für can('view').
        // period_key: Spalte „Periode“ (Feature 198).
        return ['id', 'user_id', 'assigned_user_id', 'content', 'status', 'is_archived', 'start_at', 'end_at', 'created_at',
            'mode', 'due_date', 'window_start_date', 'window_end_date', 'location_mode', 'period_key'];
    }
}
