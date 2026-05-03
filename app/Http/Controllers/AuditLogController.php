<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\DiaryEntry;
use App\Models\EmergencyAssignment;
use App\Models\OnCallShift;
use App\Support\LookupCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditLogController extends Controller {
    private const TYPE_MAP = [
        'diary' => DiaryEntry::class,
        'comment' => Comment::class,
        'shift' => OnCallShift::class,
        'assignment' => EmergencyAssignment::class,
        'attachment' => Attachment::class,
    ];

    public function index(Request $request): View {
        Gate::authorize('viewAny', AuditLog::class);

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

        if ($from = $request->string('from')->toString()) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->string('to')->toString()) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        $logs = $query->latest()->paginate(50)->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'users' => LookupCache::userDropdown(),
            'events' => ['created', 'updated', 'deleted'],
            'types' => self::TYPE_MAP,
            'filters' => $request->only(['event', 'user_id', 'type', 'from', 'to']),
        ]);
    }
}
