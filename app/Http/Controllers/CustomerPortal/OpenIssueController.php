<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OpenIssueController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Enums\OpenIssue\OpenIssueVisibility;
use App\Http\Controllers\Controller;
use App\Models\{Customer, DiaryEntry, OpenIssue, Project, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OpenIssueController extends Controller {
    /**
     * Listet offene Punkte, die für die Kundenrolle freigegeben sind und
     * mit dem zugewiesenen Kunden zusammenhängen. Es werden nur Punkte mit
     * `visibility = customer` ausgeliefert.
     */
    public function index(): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        $customerId = (int) $user->customer_id;

        $diaryIds = DiaryEntry::query()
            ->where('customer_id', $customerId)
            ->pluck('id');
        $projectIds = Project::query()
            ->where('customer_id', $customerId)
            ->pluck('id');

        $issues = OpenIssue::query()
            ->where('visibility', OpenIssueVisibility::Customer->value)
            ->where(function (Builder $q) use ($customerId, $diaryIds, $projectIds): void {
                $q->where(function (Builder $sub) use ($customerId): void {
                    $sub->where('subject_type', Customer::class)
                        ->where('subject_id', $customerId);
                });
                if ($diaryIds->isNotEmpty()) {
                    $q->orWhere(function (Builder $sub) use ($diaryIds): void {
                        $sub->where('subject_type', DiaryEntry::class)
                            ->whereIn('subject_id', $diaryIds);
                    });
                }
                if ($projectIds->isNotEmpty()) {
                    $q->orWhere(function (Builder $sub) use ($projectIds): void {
                        $sub->where('subject_type', Project::class)
                            ->whereIn('subject_id', $projectIds);
                    });
                }
            })
            ->with(['assignee', 'creator', 'subject'])
            ->orderByRaw('CASE WHEN closed_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('id')
            ->paginate(25);

        return view('customer.open-issues.index', ['issues' => $issues]);
    }
}
