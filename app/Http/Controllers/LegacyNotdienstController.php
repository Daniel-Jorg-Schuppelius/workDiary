<?php

namespace App\Http\Controllers;

use App\Models\Legacy\LegacyNotdienst;
use App\Models\Legacy\LegacyUser;
use App\Support\LegacyRoleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LegacyNotdienstController extends Controller {
    public function index(Request $request): View {
        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId(Auth::user());
        $isAdmin = LegacyRoleResolver::isAdmin(Auth::user());

        $sortableColumns = [
            'id' => 'id',
            'user' => 'user',
            'von' => 'von',
            'bis' => 'bis',
        ];

        $sort = (string) $request->query('sort', 'von');
        $dir = strtolower((string) $request->query('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortColumn = $sortableColumns[$sort] ?? $sortableColumns['von'];

        $query = LegacyNotdienst::query()->with('mitarbeiter:id,uname')->orderBy($sortColumn, $dir);

        if (! $isAdmin && $legacyUserId > 3) {
            $query->where('user', $legacyUserId);
        } elseif ($request->filled('user')) {
            $query->where('user', (int) $request->user);
        }

        if ($request->filled('from')) {
            $query->whereDate('von', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('bis', '<=', $request->to);
        }

        $items = $query->paginate(30)->withQueryString();

        $users = LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname']);

        return view('legacy.notdienst.index', [
            'items' => $items,
            'users' => $users,
            'isAdmin' => $isAdmin,
            'legacyUserId' => $legacyUserId,
            'filters' => $request->only('user', 'from', 'to'),
            'sort' => array_key_exists($sort, $sortableColumns) ? $sort : 'von',
            'dir' => $dir,
        ]);
    }

    public function create(): View {
        $this->ensureAdmin();

        return view('legacy.notdienst.form', [
            'item' => null,
            'users' => LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname']),
            'isEdit' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse {
        $this->ensureAdmin();

        $data = $request->validate([
            'user' => ['required', 'integer', 'min:4'],
            'von' => ['required', 'date'],
            'bis' => ['required', 'date', 'after_or_equal:von'],
        ]);

        LegacyNotdienst::query()->create($data);

        return redirect()->route('legacy.notdienst.index')->with('success', __('Notdienst angelegt.'));
    }

    public function edit(LegacyNotdienst $notdienst): View {
        $this->ensureAdmin();

        return view('legacy.notdienst.form', [
            'item' => $notdienst,
            'users' => LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname']),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, LegacyNotdienst $notdienst): RedirectResponse {
        $this->ensureAdmin();

        $data = $request->validate([
            'user' => ['required', 'integer', 'min:4'],
            'von' => ['required', 'date'],
            'bis' => ['required', 'date', 'after_or_equal:von'],
        ]);

        $notdienst->update($data);

        return redirect()->route('legacy.notdienst.index')->with('success', __('Notdienst aktualisiert.'));
    }

    public function destroy(LegacyNotdienst $notdienst): RedirectResponse {
        $this->ensureAdmin();

        $notdienst->delete();

        return redirect()->route('legacy.notdienst.index')->with('success', __('Notdienst gelöscht.'));
    }

    private function ensureAdmin(): void {
        abort_if(! LegacyRoleResolver::isAdmin(Auth::user()), 403);
    }
}
