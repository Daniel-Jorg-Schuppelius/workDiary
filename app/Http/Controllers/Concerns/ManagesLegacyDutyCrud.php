<?php

namespace App\Http\Controllers\Concerns;

use App\Support\LegacyRoleResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait ManagesLegacyDutyCrud {
    /**
     * @template TModel of Model
     * @param Builder<TModel> $query
     * @return array<string, mixed>
     */
    private function legacyDutyIndexData(Request $request, Builder $query): array {
        $legacyUserId = LegacyRoleResolver::resolveLegacyUserId(Auth::user());
        $isAdmin = LegacyRoleResolver::isAdmin(Auth::user());

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

        return [
            'items' => $query->paginate(30)->withQueryString(),
            'users' => $this->legacyUsersForSelect(),
            'isAdmin' => $isAdmin,
            'legacyUserId' => $legacyUserId,
            'filters' => $request->only('user', 'from', 'to'),
        ];
    }

    /** @return array<string, mixed> */
    private function legacyDutyFormData(mixed $item, bool $isEdit): array {
        return [
            'item' => $item,
            'users' => $this->legacyUsersForSelect(),
            'isEdit' => $isEdit,
        ];
    }

    private function redirectToWeekIfMigrated(string $modernModelClass, int $legacyId): ?RedirectResponse {
        if ($modernModelClass::where('legacy_id', $legacyId)->exists()) {
            return redirect()->route('week.index');
        }

        return null;
    }
}
