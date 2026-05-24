<?php
/*
 * Created on   : Sun Nov 23 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlobalSearchController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Models\{Customer, Expense, PerDiemTrip, Project, User};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};

/**
 * Liefert die Treffer für die globale Suche (Command-Palette / Spotlight).
 *
 * Pro Entität werden bis zu 5 Treffer zurückgegeben (Limit je Endpoint-Aufruf
 * insgesamt ≤ 30 Datensätze), gefiltert nach Organisation des angemeldeten
 * Benutzers. Datenschutz: Mitarbeiterliste ist Admins/Approver:innen vorbehalten.
 */
class GlobalSearchController extends Controller {
    private const PER_TYPE_LIMIT = 5;

    public function __invoke(Request $request): JsonResponse {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $term = trim((string) ($data['q'] ?? ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['groups' => []]);
        }

        /** @var User $user */
        $user = Auth::user();
        $orgId = $user->organization_id;
        $like = '%' . $term . '%';

        $groups = [];

        $groups[] = $this->makeGroup(
            'customers',
            __('Kunden'),
            'badge',
            Customer::query()
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                ->where(fn($q) => $q->where('name', 'like', $like)
                    ->orWhere('number', 'like', $like)
                    ->orWhere('email', 'like', $like))
                ->orderBy('name')
                ->limit(self::PER_TYPE_LIMIT)
                ->get()
                ->map(fn(Customer $c) => [
                    'id' => $c->id,
                    'title' => $c->name,
                    'subtitle' => trim(($c->number ? '#' . $c->number : '') . ($c->email ? ' · ' . $c->email : '')) ?: null,
                    'url' => route('customers.show', $c),
                ])
                ->all(),
        );

        $groups[] = $this->makeGroup(
            'projects',
            __('Projekte'),
            'folder_special',
            Project::query()
                ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                ->where(fn($q) => $q->where('name', 'like', $like))
                ->with('customer:id,name')
                ->orderBy('name')
                ->limit(self::PER_TYPE_LIMIT)
                ->get()
                ->map(fn(Project $p) => [
                    'id' => $p->id,
                    'title' => $p->name,
                    'subtitle' => $p->customer?->name,
                    'url' => route('projects.show', $p),
                ])
                ->all(),
        );

        $expenseQuery = Expense::query()
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->where(fn($q) => $q->where('vendor', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('reimbursement_reference', 'like', $like));
        if (! Gate::allows('viewAny', Expense::class)) {
            $expenseQuery->where('user_id', $user->id);
        }
        $groups[] = $this->makeGroup(
            'expenses',
            __('Spesen'),
            'receipt_long',
            $expenseQuery->orderByDesc('date')
                ->limit(self::PER_TYPE_LIMIT)
                ->get()
                ->map(fn(Expense $e) => [
                    'id' => $e->id,
                    'title' => $e->vendor ?: ($e->description ?: __('Spese #:id', ['id' => $e->id])),
                    'subtitle' => $e->date->format('d.m.Y')
                        . ' · ' . number_format((float) $e->amount_gross, 2, ',', '.') . ' €',
                    'url' => route('expenses.show', $e),
                ])
                ->all(),
        );

        $tripQuery = PerDiemTrip::query()
            ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
            ->where(fn($q) => $q->where('location', 'like', $like)
                ->orWhere('purpose', 'like', $like)
                ->orWhere('country', 'like', $like));
        if (! Gate::allows('viewAny', PerDiemTrip::class)) {
            $tripQuery->where('user_id', $user->id);
        }
        $groups[] = $this->makeGroup(
            'per_diem_trips',
            __('Reisekosten'),
            'flight',
            $tripQuery->orderByDesc('started_at')
                ->limit(self::PER_TYPE_LIMIT)
                ->get()
                ->map(fn(PerDiemTrip $t) => [
                    'id' => $t->id,
                    'title' => trim(($t->location ?: '—') . ($t->country ? ' (' . $t->country . ')' : '')),
                    'subtitle' => $t->started_at->format('d.m.Y')
                        . ($t->purpose ? ' · ' . mb_strimwidth($t->purpose, 0, 60, '…') : ''),
                    'url' => route('per-diem-trips.show', $t),
                ])
                ->all(),
        );

        // Nur Admin/Org-Manager dürfen Mitarbeiter durchsuchen.
        if ($user->isAdmin() || Gate::allows('manage-members')) {
            $groups[] = $this->makeGroup(
                'users',
                __('Mitarbeiter'),
                'group',
                User::query()
                    ->when($orgId !== null, fn($q) => $q->where('organization_id', $orgId))
                    ->where(fn($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
                    ->orderBy('name')
                    ->limit(self::PER_TYPE_LIMIT)
                    ->get()
                    ->map(fn(User $u) => [
                        'id' => $u->id,
                        'title' => $u->name,
                        'subtitle' => $u->email,
                        'url' => Gate::allows('manage-members') ? route('org.members.index') : '#',
                    ])
                    ->all(),
            );
        }

        // Leere Gruppen entfernen.
        $groups = array_values(array_filter($groups, fn(array $g) => count($g['items']) > 0));

        return response()->json(['groups' => $groups, 'q' => $term]);
    }

    /**
     * @param array<int, array{id:int|string,title:string,subtitle:?string,url:string}> $items
     * @return array{key:string,label:string,icon:string,items:array<int, array{id:int|string,title:string,subtitle:?string,url:string}>}
     */
    private function makeGroup(string $key, string $label, string $icon, array $items): array {
        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'items' => $items,
        ];
    }
}
