<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyDashboardService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Legacy\Services;

use App\Legacy\Models\LegacyDiaryEntry;
use App\Legacy\Models\LegacyNotdienst;
use App\Legacy\Models\LegacyOnCall;
use App\Legacy\Support\LegacyRoleResolver;
use App\Models\User;
use App\Models\Vacation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Aggregiert die Daten für das Legacy-Dashboard
 * (Aufträge / Bereitschaft / Notdienst / Urlaub).
 */
class LegacyDashboardService
{
    private const ALLOWED_TABS = ['auftraege', 'bereitschaft', 'notdienst', 'urlaub'];

    private const SORTABLE = [
        'id' => 'id',
        'mitarbeiter' => 'user',
        'status' => 'gelesen',
        'von' => 'von',
        'bis' => 'bis',
        'aktuell' => 'aktuell',
    ];

    /** Sortierbare Spalten für Bereitschaft / Notdienst. */
    private const SORTABLE_DUTY = [
        'mitarbeiter' => 'user',
        'von' => 'von',
        'bis' => 'bis',
    ];

    /** Sortierbare Spalten für Urlaubsanträge. */
    private const SORTABLE_VACATION = [
        'mitarbeiter' => 'user_id',
        'typ' => 'type',
        'status' => 'status',
        'von' => 'start_date',
        'bis' => 'end_date',
    ];

    /**
     * @return array<string,mixed>
     */
    public function buildIndexData(Request $request, User $currentUser): array
    {
        $tab = $this->resolveTab((string) $request->query('tab', 'auftraege'));
        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId($currentUser);
        $isAdmin = LegacyRoleResolver::isAdmin($currentUser);
        // Sichtbarkeit aller Legacy-Daten: Legacy-Admin ODER Spatie-Rolle "buchhaltung".
        // Schreibrechte (Anlegen/Bearbeiten/Bulk) bleiben weiterhin Admin-only via $isAdmin.
        $canViewAll = $currentUser->canViewAllLegacyData();
        $vacationCanViewAll = $canViewAll;

        [$diaryQuery, $sort, $dir] = $this->buildDiaryQuery($request, $canViewAll, $legacyUserId, $tab);
        $diaryCounts = $this->diaryCounts($request, $canViewAll, $legacyUserId);

        // Sort/Dir-Anzeige in der View tab-spezifisch berechnen, damit das Icon
        // jeweils die echte Sortierreihenfolge der aktiven Tab-Tabelle widerspiegelt.
        $rawSort = (string) $request->query('sort', '');
        $rawDir = strtolower((string) $request->query('dir', '')) === 'asc' ? 'asc' : 'desc';
        [$tabSort, $tabDir] = $this->resolveTabSort($tab, $rawSort, $rawDir);

        $oncallQuery = $this->buildLegacyDutyQuery(LegacyOnCall::query(), $request, $canViewAll, $legacyUserId, $tab === 'bereitschaft' ? $sort : null, $dir);
        $notdienstQuery = $this->buildLegacyDutyQuery(LegacyNotdienst::query(), $request, $canViewAll, $legacyUserId, $tab === 'notdienst' ? $sort : null, $dir);
        $vacationQuery = $this->buildVacationQuery($request, $currentUser, $vacationCanViewAll, $tab === 'urlaub' ? $sort : null, $dir);

        $today = now()->toDateString();
        $oncallCounts = $this->dutyCounts($oncallQuery, $today);
        $notdienstCounts = $this->dutyCounts($notdienstQuery, $today);

        $tabCounts = [
            'auftraege' => $diaryCounts['all'],
            'bereitschaft' => $oncallCounts['all'],
            'notdienst' => $notdienstCounts['all'],
            'urlaub' => (clone $vacationQuery)->count(),
        ];

        return [
            'tab' => $tab,
            'isAdmin' => $isAdmin,
            'canViewAll' => $canViewAll,
            'canFilterMine' => $legacyUserId > 0,
            'legacyUserId' => $legacyUserId,
            'tabCounts' => $tabCounts,
            'entries' => $diaryQuery->paginate(20, ['*'], 'dpage')->withQueryString(),
            'diaryCounts' => $diaryCounts,
            'filters' => $request->only(
                'status',
                'from',
                'to',
                'mine',
                'sort',
                'dir',
                'user',
                'zeitpunkt',
                'vtype',
                'vstatus',
                'user_id'
            ),
            'vacations' => $vacationQuery->paginate(15, ['*'], 'vpage')->withQueryString(),
            'vacationKpis' => $this->vacationKpis($vacationQuery, $tabCounts['urlaub']),
            'vacationIsAdmin' => $vacationCanViewAll,
            'vacationUsers' => $vacationCanViewAll
                ? User::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'sort' => $tabSort,
            'dir' => $tabDir,
            'oncallItems' => $oncallQuery->paginate(30, ['*'], 'opage')->withQueryString(),
            'oncallCounts' => $oncallCounts,
            'notdienstItems' => $notdienstQuery->paginate(30, ['*'], 'npage')->withQueryString(),
            'notdienstCounts' => $notdienstCounts,
        ];
    }

    private function resolveTab(string $tab): string
    {
        return in_array($tab, self::ALLOWED_TABS, true) ? $tab : 'auftraege';
    }

    /**
     * Liefert das pro Tab effektiv angewandte Sort-Tupel (für die Anzeige der Sortier-Icons).
     *
     * @return array{0:string,1:string}
     */
    private function resolveTabSort(string $tab, string $rawSort, string $rawDir): array
    {
        $map = match ($tab) {
            'bereitschaft', 'notdienst' => self::SORTABLE_DUTY,
            'urlaub' => self::SORTABLE_VACATION,
            default => self::SORTABLE,
        };
        $default = match ($tab) {
            'bereitschaft', 'notdienst' => ['von', 'asc'],
            'urlaub' => ['von', 'desc'],
            default => ['bis', 'desc'],
        };

        if ($rawSort !== '' && array_key_exists($rawSort, $map)) {
            return [$rawSort, $rawDir];
        }

        return $default;
    }

    /**
     * @return array{0:Builder<LegacyDiaryEntry>,1:string,2:string}
     */
    private function buildDiaryQuery(Request $request, bool $canViewAll, int $legacyUserId, string $tab = 'auftraege'): array
    {
        // Default-Sortierung wie im Archiv: nach Enddatum (bis) absteigend.
        // sort/dir gelten nur für den aktiven Tab; sonst Default verwenden.
        $rawSort = (string) $request->query('sort', '');
        $sort = $tab === 'auftraege' && $rawSort !== '' && array_key_exists($rawSort, self::SORTABLE)
            ? $rawSort
            : 'bis';
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortColumn = self::SORTABLE[$sort];

        $query = LegacyDiaryEntry::query()
            ->select(['id', 'user', 'von', 'bis', 'gelesen', 'aktuell', 'inhalt', 'antwort'])
            ->with('author:id,uname')
            ->orderBy($sortColumn, $dir);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('gelesen', (int) $request->status);
        }
        if ($request->filled('from')) {
            $query->whereDate('von', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('bis', '<=', $request->to);
        }
        $authUser = $request->user();
        $authLegacyUserId = $authUser !== null ? (int) ($authUser->legacy_user_id ?? 0) : 0;
        // "Nur meine" filtert immer auf die eigene Legacy-User-ID. Für Nicht-Admin/
        // Nicht-Buchhaltung greift ohnehin der untenstehende Scope; für Admin/Buchhaltung
        // ist dies der wesentliche Schalter.
        $mineUserId = $authLegacyUserId > 0 ? $authLegacyUserId : $legacyUserId;
        if ($request->boolean('mine') && $mineUserId > 0) {
            $query->where('user', $mineUserId);
        }

        // Mitarbeiter-Filter: Wer alle Daten sehen darf (Admin oder Buchhaltung),
        // darf nach beliebigem Legacy-User filtern. Normale User sehen nur eigene Einträge.
        if (! $canViewAll && $legacyUserId > 0) {
            $query->where('user', $legacyUserId);
        } elseif ($canViewAll && $request->filled('user')) {
            $query->where('user', (int) $request->user);
        }

        return [$query, $sort, $dir];
    }

    /**
     * @return array{all:int,open:int,alert:int,done:int}
     */
    private function diaryCounts(Request $request, bool $canViewAll, int $legacyUserId): array
    {
        $query = LegacyDiaryEntry::query();
        if (! $canViewAll && $legacyUserId > 0) {
            $query->where('user', $legacyUserId);
        } elseif ($canViewAll && $request->filled('user')) {
            $query->where('user', (int) $request->user);
        }

        $authUser = $request->user();
        $authLegacyUserId = $authUser !== null ? (int) ($authUser->legacy_user_id ?? 0) : 0;
        $mineUserId = $authLegacyUserId > 0 ? $authLegacyUserId : $legacyUserId;
        if ($request->boolean('mine') && $mineUserId > 0) {
            $query->where('user', $mineUserId);
        }
        /** @var LegacyDiaryEntry|null $row */
        $row = $query->selectRaw(
            'COUNT(*) as cnt_all,'.
                'SUM(gelesen = 2) as cnt_open,'.
                'SUM(gelesen = 3) as cnt_alert,'.
                'SUM(gelesen = -1) as cnt_done'
        )->first();
        $attrs = $row?->getAttributes() ?? [];

        return [
            'all' => (int) ($attrs['cnt_all'] ?? 0),
            'open' => (int) ($attrs['cnt_open'] ?? 0),
            'alert' => (int) ($attrs['cnt_alert'] ?? 0),
            'done' => (int) ($attrs['cnt_done'] ?? 0),
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function buildLegacyDutyQuery(Builder $query, Request $request, bool $canViewAll, int $legacyUserId, ?string $sort = null, string $dir = 'desc'): Builder
    {
        $query->with('mitarbeiter:id,uname');

        if ($sort !== null && isset(self::SORTABLE_DUTY[$sort])) {
            $query->orderBy(self::SORTABLE_DUTY[$sort], $dir);
            if (self::SORTABLE_DUTY[$sort] !== 'user') {
                $query->orderBy('user');
            }
        } else {
            $query->orderBy('von')->orderBy('user');
        }

        if (! $canViewAll && $legacyUserId > 0) {
            $query->where('user', $legacyUserId);
        } elseif ($canViewAll && $request->filled('user')) {
            $query->where('user', (int) $request->user);
        }
        if ($request->filled('from')) {
            $query->whereDate('von', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('bis', '<=', $request->to);
        }

        return $query;
    }

    /**
     * @return Builder<Vacation>
     */
    private function buildVacationQuery(Request $request, User $currentUser, bool $canViewAll, ?string $sort = null, string $dir = 'desc'): Builder
    {
        $query = Vacation::query()->with('user:id,name');

        if ($sort !== null && isset(self::SORTABLE_VACATION[$sort])) {
            $query->orderBy(self::SORTABLE_VACATION[$sort], $dir);
        } else {
            $query->orderByDesc('start_date');
        }

        if (! $canViewAll) {
            $query->where('user_id', $currentUser->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        } elseif ($request->boolean('mine')) {
            $query->where('user_id', $currentUser->id);
        }
        if ($request->filled('vtype')) {
            $query->where('type', $request->vtype);
        }
        if ($request->filled('vstatus')) {
            $query->where('status', $request->vstatus);
        }
        if ($request->filled('from')) {
            $query->where('end_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('start_date', '<=', $request->to);
        }

        return $query;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return array{all:int,today:int,upcoming:int,past:int}
     */
    private function dutyCounts(Builder $query, string $today): array
    {
        return [
            'all' => (clone $query)->count(),
            'today' => (clone $query)->whereDate('von', '<=', $today)->whereDate('bis', '>=', $today)->count(),
            'upcoming' => (clone $query)->whereDate('von', '>', $today)->count(),
            'past' => (clone $query)->whereDate('bis', '<', $today)->count(),
        ];
    }

    /**
     * @param  Builder<Vacation>  $query
     * @return array{total:int,pending:int,approved:int,rejected:int}
     */
    private function vacationKpis(Builder $query, int $total): array
    {
        return [
            'total' => $total,
            'pending' => (clone $query)->where('status', Vacation::STATUS_PENDING)->count(),
            'approved' => (clone $query)
                ->where('status', Vacation::STATUS_APPROVED)
                ->where('end_date', '>=', now()->startOfYear())
                ->count(),
            'rejected' => (clone $query)->where('status', Vacation::STATUS_REJECTED)->count(),
        ];
    }
}
