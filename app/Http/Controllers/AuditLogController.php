<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditLogController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesGlobalDateRange;
use App\Models\{Attachment, AuditLog, Comment, Customer, DiaryEntry, EmergencyAssignment, ImportRun, NumberSequence, OnCallShift, Supplier};
use App\Services\UI\DateRangeContext;
use App\Support\{LookupCache, SortableQuery};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditLogController extends Controller {
    use ResolvesGlobalDateRange;

    private const TYPE_MAP = [
        'diary' => DiaryEntry::class,
        'comment' => Comment::class,
        'shift' => OnCallShift::class,
        'assignment' => EmergencyAssignment::class,
        'attachment' => Attachment::class,
        'customer' => Customer::class,
        'supplier' => Supplier::class,
        'import_run' => ImportRun::class,
        'number_sequence' => NumberSequence::class,
    ];

    public function index(Request $request): View|RedirectResponse {
        Gate::authorize('viewAny', AuditLog::class);

        // Backward-Compat: ?from=&to= einmalig in den globalen Context.
        if ($request->filled('from') || $request->filled('to')) {
            app(DateRangeContext::class)->set(
                DateRangeContext::PRESET_CUSTOM,
                (string) $request->query('from', ''),
                (string) $request->query('to', ''),
            );

            return redirect()->route('audit.index', $request->except(['from', 'to']));
        }

        $query = AuditLog::query()->with(['user:id,name', 'auditable']);

        if ($event = $request->string('event')->toString()) {
            $query->where('event', $event);
        }

        if ($userId = $request->integer('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($typeKey = $request->string('type')->toString()) {
            $class = self::TYPE_MAP[$typeKey] ?? null;
            if ($class) {
                $query->where('auditable_type', $class);
            }
        }

        $range = $this->globalDateRange();
        $query->where('created_at', '>=', $range['from']->startOfDay());
        $query->where('created_at', '<=', $range['to']->endOfDay());

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'created_at' => 'created_at',
            'event' => 'event',
            'auditable_type' => 'auditable_type',
            'user_id' => 'user_id',
            'ip' => 'ip',
        ], 'created_at', 'desc');

        $logs = $query->paginate(50)->withQueryString();

        $filters = $request->only(['event', 'user_id', 'type']);
        $filters['from'] = $range['from']->toDateString();
        $filters['to'] = $range['to']->toDateString();

        return view('audit.index', [
            'logs' => $logs,
            'users' => LookupCache::userDropdown(),
            'events' => ['created', 'updated', 'deleted', 'archived', 'restored', 'import.confirmed', 'import.started', 'import.finished', 'import.partial', 'import.preflightFailed'],
            'types' => self::TYPE_MAP,
            'filters' => $filters,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }
}
