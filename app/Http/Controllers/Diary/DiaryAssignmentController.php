<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DiaryAssignmentController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Diary;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Services\Diary\OrderAssignment;
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/**
 * Mehrfachauswahl „Zuständigkeit ändern“ in der Auftragsliste (Feature 202,
 * MVP-1648): einzelne Aufträge endgültig einer anderen Person zuweisen. Je
 * Auftrag gilt das Bearbeitungsrecht und die Qualifikationssperre; was nicht
 * geht, wird übersprungen und gezählt.
 */
class DiaryAssignmentController extends Controller {
    use ResolvesCurrentOrganization;

    private const int MAX = 200;

    public function __construct(private readonly OrderAssignment $assignment) {}

    public function bulk(Request $request): RedirectResponse {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:' . self::MAX],
            'ids.*' => ['string', 'max:64'],
            'assignee' => ['required', 'string', 'max:64'],
        ]);
        $organization = $this->currentOrganization();

        $assigneeId = Sqid::decode(User::class, (string) $data['assignee']);
        $assignee = $assigneeId !== null
            ? User::query()->where('organization_id', $organization->id)->whereNull('deactivated_at')->whereKey($assigneeId)->first()
            : null;
        if (! $assignee instanceof User) {
            return back()->withErrors(['assignee' => __('delegation.reassign.unknown_person')]);
        }

        $ids = array_values(array_filter(array_map(static fn (mixed $key): ?int => Sqid::decode(DiaryEntry::class, (string) $key), $data['ids'])));
        $entries = DiaryEntry::query()->where('organization_id', $organization->id)->whereKey($ids)->get();
        $moved = 0;
        foreach ($entries as $entry) {
            if (Gate::allows('update', $entry) && $this->assignment->reassign($entry, $assignee)) {
                $moved++;
            }
        }
        $skipped = count($data['ids']) - $moved;

        $message = trans_choice('delegation.reassign.done', $moved, ['count' => $moved, 'name' => $assignee->name]);
        if ($skipped > 0) {
            $message .= ' ' . trans_choice('delegation.reassign.skipped', $skipped, ['count' => $skipped]);
        }

        return back()->with($moved > 0 ? 'success' : 'warning', $message);
    }
}
