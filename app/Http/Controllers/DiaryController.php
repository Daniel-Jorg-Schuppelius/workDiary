<?php

namespace App\Http\Controllers;

use App\Models\DiaryEntry;
use App\Models\Legacy\LegacyDiaryEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DiaryController extends Controller {
    public function index(Request $request): View {
        $query = DiaryEntry::query()->with('user:id,name')->orderByDesc('start_at');

        // Filter: Status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', (int) $request->status);
        }

        // Filter: Datumsbereich
        if ($request->filled('from')) {
            $query->whereDate('start_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('start_at', '<=', $request->to);
        }

        // Filter: Nur eigene
        if ($request->boolean('mine')) {
            $query->where('user_id', Auth::id());
        }

        $entries = $query->paginate(20)->withQueryString();

        $counts = [
            'all' => DiaryEntry::count(),
            'open' => DiaryEntry::where('status', 2)->count(),
            'alert' => DiaryEntry::where('status', 3)->count(),
            'done' => DiaryEntry::where('status', -1)->count(),
        ];

        return view('diary.index', [
            'entries' => $entries,
            'counts' => $counts,
            'filters' => $request->only('status', 'from', 'to', 'mine'),
        ]);
    }

    public function create(): View {
        return view('diary.form', [
            'entry' => null,
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:65535'],
            'response' => ['nullable', 'string', 'max:65535'],
            'status' => ['required', 'integer', 'in:-1,1,2,3'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
        ]);

        $entry = Auth::user()->diaryEntries()->create($data);

        return redirect()->route('diary.show', $entry)->with('success', __('Eintrag gespeichert.'));
    }

    public function show(DiaryEntry $diary): View {
        $diary->load('user:id,name');

        // Falls der Eintrag aus einem Legacy-Import stammt, auch die Legacy-Daten laden
        $legacyEntry = null;
        if ($diary->legacy_id && filled(config('database.connections.legacy.database'))) {
            try {
                $legacyEntry = LegacyDiaryEntry::with('author:id,uname')->find($diary->legacy_id);
            } catch (\Exception) {
                // Legacy nicht erreichbar
            }
        }

        return view('diary.show', compact('diary', 'legacyEntry'));
    }

    public function edit(DiaryEntry $diary): View {
        $this->authorize('update', $diary);

        return view('diary.form', [
            'entry' => $diary,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, DiaryEntry $diary): RedirectResponse {
        $this->authorize('update', $diary);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:65535'],
            'response' => ['nullable', 'string', 'max:65535'],
            'status' => ['required', 'integer', 'in:-1,1,2,3'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
        ]);

        $diary->update($data);

        return redirect()->route('diary.show', $diary)->with('success', __('Eintrag aktualisiert.'));
    }

    public function destroy(DiaryEntry $diary): RedirectResponse {
        $this->authorize('delete', $diary);

        $diary->delete();

        return redirect()->route('diary.index')->with('success', __('Eintrag gelöscht.'));
    }
}
