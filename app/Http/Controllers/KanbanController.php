<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DiaryEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class KanbanController extends Controller
{
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

    public function index(Request $request): View
    {
        /** @var User $auth */
        $auth = Auth::user();
        $teamScope = $request->query('scope') === 'team';

        // Zeitraum: Preset (range) oder explizite from/to-Werte
        $rangePresets = [
            '7' => __('Letzte 7 Tage'),
            '30' => __('Letzte 30 Tage'),
            '90' => __('Letzte 90 Tage'),
            'all' => __('Alle'),
        ];
        $range = (string) $request->query('range', '30');
        if (! array_key_exists($range, $rangePresets)) {
            $range = '30';
        }
        $from = $request->date('from');
        $to = $request->date('to');
        if ($from || $to) {
            $range = 'custom';
        }

        $query = DiaryEntry::query()
            ->select(['id', 'user_id', 'content', 'status', 'start_at'])
            ->with(['user:id,name', 'tags:id,name,color'])
            ->where('is_archived', false)
            ->orderByDesc('start_at');

        if (! $teamScope) {
            $query->where('user_id', $auth->id);
        }

        if ($range !== 'all' && $range !== 'custom') {
            $days = (int) $range;
            $query->where('start_at', '>=', now()->subDays($days)->startOfDay());
        } elseif ($range === 'custom') {
            if ($from) {
                $query->whereDate('start_at', '>=', $from);
            }
            if ($to) {
                $query->whereDate('start_at', '<=', $to);
            }
        }

        $entries = $query->limit(self::MAX_ENTRIES)->get();
        $byStatus = $entries->groupBy(fn (DiaryEntry $e) => (int) $e->status);

        return view('kanban.index', [
            'columns' => self::columns(),
            'byStatus' => $byStatus,
            'teamScope' => $teamScope,
            'canEditOthers' => $auth->canCreateEntriesForOthers(),
            'isLimited' => $entries->count() === self::MAX_ENTRIES,
            'range' => $range,
            'rangePresets' => $rangePresets,
            'from' => $from?->format('Y-m-d'),
            'to' => $to?->format('Y-m-d'),
        ]);
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
