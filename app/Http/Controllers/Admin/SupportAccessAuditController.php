<?php
/*
 * Created on   : Sat Nov 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupportAccessAuditController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * MVP-004: Read-only Audit-Sicht für Supportzugriffe (`support.*`).
 *
 * Listet AuditLog-Events mit Präfix `support.` für die eigene
 * Organisation (und optional Plattform-weit bei `platform.support.*`-
 * Berechtigung). Filter: Datumsbereich, Event-Typ, Akteur.
 */
class SupportAccessAuditController extends Controller {
    private const ALLOWED_SORTS = ['created_at', 'event'];

    public function index(Request $request): View {
        Gate::authorize(Permission::PrivacySupportView->value);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->organization;
        abort_if($organization === null, Response::HTTP_NOT_FOUND);

        $filters = [
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'event' => (string) $request->query('event', ''),
            'actor' => (string) $request->query('actor', ''),
        ];

        $from = $this->parseDate($filters['from']);
        $to = $this->parseDate($filters['to']);

        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'created_at';
        $dir = $request->string('dir')->toString() === 'asc' ? 'asc' : 'desc';

        $query = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('event', 'like', 'support.%');

        if ($from !== null) {
            $query->where('created_at', '>=', $from->startOfDay());
        }
        if ($to !== null) {
            $query->where('created_at', '<=', $to->endOfDay());
        }
        if ($filters['event'] !== '' && str_starts_with($filters['event'], 'support.')) {
            $query->where('event', $filters['event']);
        }
        if ($filters['actor'] !== '' && ctype_digit($filters['actor'])) {
            $query->where('user_id', (int) $filters['actor']);
        }

        $entries = $query->orderBy($sort, $dir)->paginate(50)->withQueryString();

        $actorIds = collect($entries->items())->pluck('user_id')->filter()->unique();
        $actors = User::query()->whereIn('id', $actorIds)->get()->keyBy('id');

        $eventOptions = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->where('event', 'like', 'support.%')
            ->distinct()
            ->orderBy('event')
            ->pluck('event')
            ->all();

        return view('admin.support.access-audit.index', [
            'organization' => $organization,
            'entries' => $entries,
            'actors' => $actors,
            'filters' => $filters,
            'eventOptions' => $eventOptions,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    private function parseDate(string $value): ?CarbonImmutable {
        if ($value === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
