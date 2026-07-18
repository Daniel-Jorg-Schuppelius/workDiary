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

use App\Enums\Diary\Status;
use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\{DiaryEntry, User};
use App\Services\UI\DateRangeContext;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class KanbanController extends Controller {
    use ResolvesGlobalDateRange;

    private const MAX_ENTRIES = 200;

    /**
     * Spalten-Reihenfolge: Status-Code => Konfiguration
     *
     * @return array<int, array{label: string, tone: string}>
     */
    public static function columns(): array {
        return [
            Status::Planned->value => ['label' => Status::Planned->label(), 'tone' => 'open'],
            Status::Accepted->value => ['label' => Status::Accepted->label(), 'tone' => 'progress'],
            Status::InProgress->value => ['label' => Status::InProgress->label(), 'tone' => 'progress'],
            Status::WaitingCustomer->value => ['label' => Status::WaitingCustomer->label(), 'tone' => 'alert'],
            Status::WaitingMaterial->value => ['label' => Status::WaitingMaterial->label(), 'tone' => 'alert'],
            Status::Completed->value => ['label' => Status::Completed->label(), 'tone' => 'done'],
            Status::AcceptedFinal->value => ['label' => Status::AcceptedFinal->label(), 'tone' => 'done'],
            Status::Invoiced->value => ['label' => Status::Invoiced->label(), 'tone' => 'done'],
            Status::Cancelled->value => ['label' => Status::Cancelled->label(), 'tone' => 'neutral'],
        ];
    }

    public function index(Request $request): View|RedirectResponse {
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

        // organization_id/assigned_user_id: Grundlage der Policy-Checks für
        // die per Karte freigegebenen Auftragsaktionen (Drag-and-Drop).
        $query = DiaryEntry::query()
            ->select(['id', 'user_id', 'organization_id', 'assigned_user_id', 'content', 'status', 'start_at'])
            ->with(['user:id,name', 'tags:id,name,color'])
            ->where('is_archived', false)
            ->orderByDesc('start_at');

        if (! $teamScope) {
            $query->where('user_id', $auth->id);
        }

        $query->whereDate('start_at', '>=', $range['from']->toDateString());
        $query->whereDate('start_at', '<=', $range['to']->toDateString());

        $entries = $query->limit(self::MAX_ENTRIES)->get();
        $byStatus = $entries->groupBy(fn(DiaryEntry $e) => $e->status->value);

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
    private function migrateLegacyRange(Request $request): void {
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

}
