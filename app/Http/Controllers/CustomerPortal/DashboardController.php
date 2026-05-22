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
use App\Models\TimeEntry;
use App\Models\User;
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
        ];

        return view('customer.dashboard', [
            'user' => $user,
            'customer' => $user->customer,
            'stats' => $stats,
        ]);
    }
}
