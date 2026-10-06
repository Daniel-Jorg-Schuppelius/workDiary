<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisOfflineController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Crisis;

use App\Enums\Crisis\{CrisisActionStatus, CrisisCaseStatus};
use App\Http\Controllers\Controller;
use App\Models\Crisis\{CrisisAction, CrisisCase, CrisisCommunication, CrisisTeamAssignment};
use App\Models\Platform\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Offline-Krisenmappe (Feature 070, MVP-914): aktive Krisen, die die Person
 * sehen darf — Lage, offene Maßnahmen, Krisenstab mit Erreichbarkeit,
 * versandte Mitteilungen. Nur auf ausdrückliche Anforderung im Gerät, beim
 * Abmelden gelöscht (offline-sync.js).
 */
class CrisisOfflineController extends Controller {
    public function bundle(): JsonResponse {
        Gate::authorize('viewAny', CrisisCase::class);

        $cases = CrisisCase::query()
            ->whereIn('status', CrisisCaseStatus::active())
            ->with(['responsible:id,name', 'team.role', 'team.user', 'team.deputy', 'actions.assignee:id,name', 'communications', 'situationReports'])
            ->orderByDesc('activated_at')
            ->get()
            ->filter(fn (CrisisCase $case): bool => Gate::allows('view', $case))
            ->values();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'organization' => $this->authUser()->organization?->name,
            'cases' => $cases->map(fn (CrisisCase $case): array => [
                'title' => $case->title,
                'severity' => $case->severity,
                'status' => $case->status->value,
                'activated_at' => $case->activated_at?->toIso8601String(),
                'responsible' => $case->responsible?->name,
                'description' => $case->description,
                'situation' => ($report = $case->situationReports->sortByDesc('version')->first()) !== null ? ['content' => $report->content, 'risks' => $report->risks, 'at' => $report->created_at?->toIso8601String()] : null,
                'actions' => $case->actions->where('status', '!=', CrisisActionStatus::Done)->values()->map(fn (CrisisAction $a): array => ['title' => $a->title, 'due_at' => $a->due_at?->toIso8601String(), 'assignee' => $a->assignee?->name, 'status' => $a->status->value])->all(),
                'team' => $case->team->map(fn (CrisisTeamAssignment $t): array => ['role' => $t->role?->name, 'person' => $this->contact($t->user), 'deputy' => $this->contact($t->deputy), 'note' => $t->contact_note])->all(),
                'communications' => $case->communications->whereNotNull('sent_at')->values()->map(fn (CrisisCommunication $c): array => ['subject' => $c->subject, 'audience' => $c->audience, 'sent_at' => $c->sent_at?->toIso8601String(), 'body' => $c->body])->all(),
            ])->all(),
        ]);
    }

    /** @return array{name: string, phone: ?string, email: ?string}|null */
    private function contact(?User $user): ?array {
        return $user === null ? null : ['name' => (string) $user->name, 'phone' => $user->mobile ?: $user->phone, 'email' => $user->email];
    }
}
