<?php

namespace App\Http\Controllers;

use App\Models\Legacy\LegacyDiaryEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LegacyOverviewController extends Controller {
    public function index(Request $request): View {
        $legacyUserId = (int) (Auth::user()?->legacy_user_id ?? 0);
        $isAdmin = $legacyUserId > 0 && $legacyUserId <= 3;

        $zeitpunkt = (int) $request->input('zeitpunkt', 1);
        $status = (int) $request->input('status', 2);

        $sortableColumns = [
            'id' => 'id',
            'author' => 'user',
            'von' => 'von',
            'bis' => 'bis',
            'status' => 'gelesen',
        ];

        $sort = (string) $request->query('sort', 'von');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortColumn = $sortableColumns[$sort] ?? $sortableColumns['von'];

        $query = LegacyDiaryEntry::query()->with('author:id,uname')->orderBy($sortColumn, $dir);

        if ($status !== 0) {
            $query->where('gelesen', $status);
        }

        $today = now()->startOfDay();
        if ($zeitpunkt === 1) {
            // Daten ab heute: Einträge, die heute oder später enden
            $query->where('bis', '>=', $today);
        } elseif ($zeitpunkt === 2) {
            // Daten bis heute: Einträge, die vor heute begonnen haben
            $query->where('von', '<=', $today);
        }

        if (! $isAdmin && $legacyUserId > 3) {
            $query->where('user', $legacyUserId);
        }

        $entries = $query->paginate(40)->withQueryString();

        return view('legacy.overview.index', [
            'entries' => $entries,
            'isAdmin' => $isAdmin,
            'filters' => [
                'zeitpunkt' => $zeitpunkt,
                'status' => $status,
            ],
            'sort' => array_key_exists($sort, $sortableColumns) ? $sort : 'von',
            'dir' => $dir,
        ]);
    }
}
