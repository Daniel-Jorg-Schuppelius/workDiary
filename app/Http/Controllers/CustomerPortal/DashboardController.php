<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DashboardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\DiaryEntry;
use App\Models\Invoice;
use App\Models\OpenIssue;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller {
    public function __invoke(): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();
        $customerId = (int) $user->customer_id;

        $stats = [
            'diary' => DiaryEntry::query()->where('customer_id', $customerId)->count(),
            'time_entries' => TimeEntry::query()
                ->whereHas('project', fn($q) => $q->where('customer_id', $customerId))
                ->count(),
            'invoices' => Invoice::query()->where('customer_id', $customerId)->count(),
            'open_issues' => OpenIssue::query()
                ->where('visibility', \App\Enums\OpenIssue\OpenIssueVisibility::Customer->value)
                ->whereNull('closed_at')
                ->where(function (Builder $q) use ($customerId): void {
                    $q->where(function (Builder $sub) use ($customerId): void {
                        $sub->where('subject_type', \App\Models\Customer::class)
                            ->where('subject_id', $customerId);
                    })
                    ->orWhere(function (Builder $sub) use ($customerId): void {
                        $sub->where('subject_type', DiaryEntry::class)
                            ->whereIn('subject_id', DiaryEntry::query()
                                ->where('customer_id', $customerId)
                                ->select('id'));
                    })
                    ->orWhere(function (Builder $sub) use ($customerId): void {
                        $sub->where('subject_type', Project::class)
                            ->whereIn('subject_id', Project::query()
                                ->where('customer_id', $customerId)
                                ->select('id'));
                    });
                })
                ->count(),
        ];

        return view('customer.dashboard', [
            'user' => $user,
            'customer' => $user->customer,
            'stats' => $stats,
        ]);
    }
}
