<?php
/*
 * Created on   : Sat May 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeEntryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\{TimeEntry, User};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TimeEntryController extends Controller {
    public function index(): View {
        /** @var User $user */
        $user = Auth::guard('customer')->user();

        $entries = TimeEntry::query()
            ->whereHas('project', fn($q) => $q->where('customer_id', $user->customer_id))
            ->with(['project:id,name,customer_id', 'user:id,name'])
            ->orderByDesc('start_at')
            ->paginate(25);

        return view('customer.time-entries.index', ['entries' => $entries]);
    }
}
