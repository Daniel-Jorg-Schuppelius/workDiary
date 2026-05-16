<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KanbanController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\DiaryEntry;
use App\Models\User;
use App\Services\UI\DateRangeContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class KanbanController extends Controller
{
    use ResolvesGlobalDateRange;

    private const MAX_ENTRIES = 200;

    /**
     * Spalten-Reihenfolge: Status-Code => Konfiguration
     *
     * @return array<int, array{label: string, tone: string}>
     */
    public static function columns(): array
    {
        return [
            2 => ['label' => 'Offen', 'tone' => 'open'],
            3 => ['label' => 'Problem', 'tone' => 'alert'],
            1 => ['label' => 'Bestätigt', 'tone' => 'progress'],
            -1 => ['label' => 'Erledigt', 'tone' => 'done'],
        ];
    }

    public function index(Request $request): View|RedirectResponse
    {
        // Backward-Compat: alte URLs mit ?range=7|30|90|all|custom oder
        // ?from=&to= einmalig in den globalen DateRangeContext übersetzen
        // und auf die saubere URL umleiten. Der Header-Selektor übernimmt.
        if ($request->has('range') || $request->filled('from') || $request->filled('to')) {
            $this->migrateLegacyRange($request);

            return redirect()->route('kanban.index', $request->except(['range', 'from', 'to']));
        }

        /** @var User $auth */
        $auth = Auth::user();
        $teamScope = $request->query('scope') === 'team';

        $range = $this->globalDateRange();

        $query = DiaryEntry::query()
            ->select(['id', 'user_id', 'content', 'status', 'start_at'])
            ->with(['user:id,name', 'tags:id,name,color'])
            ->where('is_archived', false)
            ->orderByDesc('start_at');

        if (! $teamScope) {
            $query->where('user_id', $auth->id);
        }

        $query->whereDate('start_at', '>=', $range['from']->toDateString());
        $query->whereDate('start_at', '<=', $range['to']->toDateString());

        $entries = $query->limit(self::MAX_ENTRIES)->get();
        $byStatus = $entries->groupBy(fn (DiaryEntry $e) => (int) $e->status);

        return view('kanban.index', [
            'columns' => self::columns(),
            'byStatus' => $byStatus,
            'teamScope' => $teamScope,
            'canEditOthers' => $auth->canCreateEntriesForOthers(),
            'isLimited' => $entries->count() === self::MAX_ENTRIES,
        ]);
    }

    /**
     * Übersetzt alte Query-Parameter (?range=7|30|90|all|custom, ?from=, ?to=)
     * in den globalen DateRangeContext, damit bestehende Bookmarks weiter
     * funktionieren.
     */
    private function migrateLegacyRange(Request $request): void
    {
        $ctx = app(DateRangeContext::class);

        if ($request->filled('from') || $request->filled('to')) {
            $ctx->set(
                DateRangeContext::PRESET_CUSTOM,
                (string) $request->query('from', ''),
                (string) $request->query('to', ''),
            );

            return;
        }

        $preset = match ((string) $request->query('range', '')) {
            '7' => DateRangeContext::PRESET_LAST_7_DAYS,
            '30' => DateRangeContext::PRESET_LAST_30_DAYS,
            '90' => DateRangeContext::PRESET_LAST_90_DAYS,
            'all' => DateRangeContext::PRESET_THIS_YEAR,
            default => null,
        };
        if ($preset !== null) {
            $ctx->set($preset);
        }
    }

    public function updateStatus(Request $request, DiaryEntry $entry): JsonResponse
    {
        Gate::authorize('update', $entry);

        $validated = $request->validate([
            'status' => 'required|integer|in:-1,1,2,3',
        ]);

        $entry->update(['status' => (int) $validated['status']]);

        return response()->json(['ok' => true, 'status' => $entry->status]);
    }
}
