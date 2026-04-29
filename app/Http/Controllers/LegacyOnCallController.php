<?php

namespace App\Http\Controllers;

use App\Models\Legacy\LegacyOnCall;
use App\Models\Legacy\LegacyUser;
use App\Support\LegacyRoleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LegacyOnCallController extends Controller {
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

        $query = LegacyOnCall::query()->with('mitarbeiter:id,uname')->orderBy($sortColumn, $dir);

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

        return view('legacy.oncall.index', [
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

        return view('legacy.oncall.form', [
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

        LegacyOnCall::query()->create($data);

        return redirect()->route('legacy.oncall.index')->with('success', __('Bereitschaft angelegt.'));
    }

    public function edit(LegacyOnCall $oncall): View {
        $this->ensureAdmin();

        return view('legacy.oncall.form', [
            'item' => $oncall,
            'users' => LegacyUser::query()->where('id', '>', 3)->orderBy('uname')->get(['id', 'uname']),
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, LegacyOnCall $oncall): RedirectResponse {
        $this->ensureAdmin();

        $data = $request->validate([
            'user' => ['required', 'integer', 'min:4'],
            'von' => ['required', 'date'],
            'bis' => ['required', 'date', 'after_or_equal:von'],
        ]);

        $oncall->update($data);

        return redirect()->route('legacy.oncall.index')->with('success', __('Bereitschaft aktualisiert.'));
    }

    public function destroy(LegacyOnCall $oncall): RedirectResponse {
        $this->ensureAdmin();

        $oncall->delete();

        return redirect()->route('legacy.oncall.index')->with('success', __('Bereitschaft gelöscht.'));
    }

    private function ensureAdmin(): void {
        abort_if(! LegacyRoleResolver::isAdmin(Auth::user()), 403);
    }
}
