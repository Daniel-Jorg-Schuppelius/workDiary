<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IdeaMapController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Ideas\IdeaShareRole;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Requests\SaveIdeaMapRequest;
use App\Models\{AuditLog, IdeaMap, IdeaMapShare, IdeaNode, Team, User};
use App\Services\Ideas\{IdeaMapImportService, IdeaMapService};
use App\Services\SqidEncoder;
use Illuminate\Http\{JsonResponse, RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Cache, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Ideenlandkarten (Feature 054, MVP-104/105). Datenschutz-Grundsatz: Die
 * Übersicht listet AUSSCHLIESSLICH über {@see IdeaMap::scopeVisibleTo()}
 * (eigene + freigegebene Karten) — nie „alle der Org, Policy filtert schon".
 * Jede Einzel-Route autorisiert zusätzlich über die {@see \App\Policies\IdeaMapPolicy}.
 * Modul-Gating über `ideas.*` → module.ideas.
 */
class IdeaMapController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly IdeaMapService $maps) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', IdeaMap::class);

        /** @var User $user */
        $user = Auth::user();
        $filter = $request->string('filter')->toString() ?: 'active';

        $query = IdeaMap::query()
            ->visibleTo($user)
            ->with(['owner:id,name'])
            ->withCount('nodes');

        match ($filter) {
            'archived' => $query->whereNotNull('archived_at'),
            'trashed' => $query->onlyTrashed()->where('owner_user_id', $user->id),
            default => $query->whereNull('archived_at'),
        };

        return view('ideas.index', [
            'maps' => $query->latest('updated_at')->paginate(25)->withQueryString(),
            'filter' => $filter,
        ]);
    }

    public function create(): View {
        Gate::authorize('create', IdeaMap::class);

        return view('ideas._form_dialog', ['isDialog' => true] + $this->contextOptions());
    }

    public function store(SaveIdeaMapRequest $request): RedirectResponse {
        Gate::authorize('create', IdeaMap::class);
        $data = $request->validated();

        /** @var User $user */
        $user = Auth::user();
        $map = $this->maps->create($this->currentOrganization(), $user, (string) $data['title'], $data['description'] ?? null, [
            'customer_id' => ! empty($data['customer']) ? (int) $data['customer'] : null,
            'project_id' => ! empty($data['project']) ? (int) $data['project'] : null,
        ]);

        return redirect()->route('ideas.show', $map)->with('success', __('ideas.flash.created'));
    }

    /**
     * Import aus FreeMind/Freeplane (`.mm`) oder OPML (MVP-138): erzeugt eine
     * neue, private Karte des Importeurs. XML wird XXE-gehärtet gelesen
     * ({@see IdeaMapImportService}); Fehler (Format/Größe) kommen als
     * Formular-Fehlermeldung zurück.
     */
    public function import(Request $request, IdeaMapImportService $import): RedirectResponse {
        Gate::authorize('create', IdeaMap::class);

        $request->validate([
            'file' => ['required', 'file', 'max:4096'], // 4 MB
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');
        $content = (string) file_get_contents($file->getRealPath());

        /** @var User $user */
        $user = Auth::user();

        try {
            $map = $import->import($this->currentOrganization(), $user, $content, $file->getClientOriginalName());
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('ideas.show', $map)->with('success', __('ideas.import.done'));
    }

    /** Editor-Seite (P3); bis dahin Gliederungs-Rohliste als Platzhalter. */
    public function show(IdeaMap $map): View {
        Gate::authorize('view', $map);

        $map->load(['owner:id,name', 'nodes' => fn ($q) => $q->orderBy('sort_order')]);
        $canShare = Gate::allows('share', $map);

        return view('ideas.show', [
            'map' => $map,
            'root' => $map->rootNode()->first(),
            'canUpdate' => Gate::allows('update', $map),
            'canShare' => $canShare,
            'shares' => $canShare ? $map->shares()->with(['user:id,name', 'team:id,name'])->get() : collect(),
            'shareUsers' => $canShare ? User::query()->where('organization_id', $map->organization_id)->where('id', '!=', $map->owner_user_id)->orderBy('name')->limit(500)->get(['id', 'name']) : collect(),
            'shareTeams' => $canShare ? Team::query()->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    /** JSON-Export (MVP-110): dokumentiertes, stabiles Schema — nur Eigentümer, auditiert. */
    public function exportJson(IdeaMap $map, \App\Services\Ideas\IdeaMapExportService $exports): \Symfony\Component\HttpFoundation\Response {
        Gate::authorize('export', $map);

        $map->audit('idea_map.exported', ['format' => 'json']);

        return response()->json($exports->toArray($map), 200, [
            'Content-Disposition' => 'attachment; filename="idea-map-' . $map->sqid . '.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /** PDF-Export (MVP-110): Gliederungsdarstellung — nur Eigentümer, auditiert. */
    public function exportPdf(IdeaMap $map, \App\Services\Ideas\IdeaMapExportService $exports): \Illuminate\Http\Response {
        Gate::authorize('export', $map);

        $map->audit('idea_map.exported', ['format' => 'pdf']);

        return response($exports->pdf($map), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="idea-map-' . $map->sqid . '.pdf"',
        ]);
    }

    /** OPML-Export (MVP-138): Standard-Gliederungsformat — nur Eigentümer, auditiert. */
    public function exportOpml(IdeaMap $map, \App\Services\Ideas\IdeaMapExportService $exports): \Illuminate\Http\Response {
        Gate::authorize('export', $map);

        $map->audit('idea_map.exported', ['format' => 'opml']);

        return response($exports->opml($map), 200, [
            'Content-Type' => 'text/x-opml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="idea-map-' . $map->sqid . '.opml"',
        ]);
    }

    /** Markdown-Export (MVP-138): eingerückte Gliederung — nur Eigentümer, auditiert. */
    public function exportMarkdown(IdeaMap $map, \App\Services\Ideas\IdeaMapExportService $exports): \Illuminate\Http\Response {
        Gate::authorize('export', $map);

        $map->audit('idea_map.exported', ['format' => 'markdown']);

        return response($exports->markdown($map), 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="idea-map-' . $map->sqid . '.md"',
        ]);
    }

    /**
     * Präsenz-Heartbeat (MVP-108, bewusst ohne WebSockets): merkt den
     * Bearbeiter je Karte im Cache (kurze TTL) und liefert die anderen gerade
     * aktiven Personen zurück („X bearbeitet gerade").
     */
    public function presence(IdeaMap $map): JsonResponse {
        Gate::authorize('view', $map);

        /** @var User $user */
        $user = Auth::user();
        $key = 'idea-presence:' . $map->id;
        $now = now()->getTimestamp();

        /** @var array<int|string, array{name: string, ts: int}> $entries */
        $entries = Cache::get($key, []);
        $entries[$user->id] = ['name' => (string) $user->name, 'ts' => $now];
        $entries = array_filter($entries, fn (array $e): bool => ($now - $e['ts']) < 45);
        Cache::put($key, $entries, 90);

        $others = [];
        foreach ($entries as $id => $entry) {
            if ((int) $id !== (int) $user->id) {
                $others[] = $entry['name'];
            }
        }

        return response()->json(['editing' => $others]);
    }

    /**
     * Änderungsverlauf (MVP-108): aggregiert die `audit_logs` der Karte und
     * aller (auch gelöschter) Knoten — Person, Zeitpunkt, Aktion, Betreff.
     */
    public function history(IdeaMap $map): JsonResponse {
        Gate::authorize('view', $map);

        $nodeIds = $map->nodes()->withTrashed()->pluck('id');
        $nodeMorph = (new IdeaNode())->getMorphClass();

        $logs = AuditLog::query()
            ->where(function ($q) use ($map, $nodeIds, $nodeMorph): void {
                $q->where(fn ($qq) => $qq->where('auditable_type', $map->getMorphClass())->where('auditable_id', $map->id))
                    ->orWhere(fn ($qq) => $qq->where('auditable_type', $nodeMorph)->whereIn('auditable_id', $nodeIds));
            })
            ->with('user:id,name')
            ->latest('id')
            ->limit(50)
            ->get();

        return response()->json([
            'entries' => $logs->map(function (AuditLog $log) use ($nodeMorph): array {
                $changes = is_array($log->changes) ? $log->changes : [];

                return [
                    'at' => $log->created_at?->format('d.m.Y H:i'),
                    'user' => $log->user?->name,
                    'event' => (string) $log->event,
                    'subject' => (string) ($changes['title'] ?? ($changes['after']['title'] ?? '')) ?: ($log->auditable_type === $nodeMorph ? null : ''),
                ];
            })->values(),
        ]);
    }

    /** Freigabe anlegen (nur Eigentümer): genau EINE Person ODER EIN Team je Aufruf. */
    public function storeShare(Request $request, IdeaMap $map): RedirectResponse {
        Gate::authorize('share', $map);

        $data = $request->validate([
            'user' => ['nullable', 'string'],
            'team' => ['nullable', 'string'],
            'role' => ['required', 'in:viewer,editor'],
        ]);
        $role = IdeaShareRole::from((string) $data['role']);

        /** @var User $actor */
        $actor = Auth::user();

        $userId = ! empty($data['user']) ? app(SqidEncoder::class)->decode(User::class, (string) $data['user']) : null;
        $teamId = ! empty($data['team']) ? app(SqidEncoder::class)->decode(Team::class, (string) $data['team']) : null;

        if ($userId !== null && $teamId === null) {
            $target = User::query()->where('organization_id', $map->organization_id)->find($userId);
            if (! $target instanceof User || (int) $target->id === (int) $map->owner_user_id) {
                return back()->with('error', __('ideas.flash.share_invalid'));
            }
            $this->maps->shareWithUser($map, $target, $role, $actor);
        } elseif ($teamId !== null && $userId === null) {
            $team = Team::query()->find($teamId);
            if (! $team instanceof Team) {
                return back()->with('error', __('ideas.flash.share_invalid'));
            }
            $this->maps->shareWithTeam($map, $team, $role, $actor);
        } else {
            return back()->with('error', __('ideas.flash.share_invalid'));
        }

        return back()->with('success', __('ideas.flash.share_granted'));
    }

    /** Freigabe entziehen (nur Eigentümer). */
    public function destroyShare(IdeaMap $map, IdeaMapShare $share): RedirectResponse {
        Gate::authorize('share', $map);
        abort_unless((int) $share->idea_map_id === (int) $map->id, 404);

        $this->maps->revokeShare($map, $share);

        return back()->with('success', __('ideas.flash.share_revoked'));
    }

    public function edit(IdeaMap $map): View {
        Gate::authorize('update', $map);

        return view('ideas._form_dialog', ['isDialog' => true, 'map' => $map] + $this->contextOptions());
    }

    public function update(SaveIdeaMapRequest $request, IdeaMap $map): RedirectResponse {
        Gate::authorize('update', $map);
        $data = $request->validated();

        $this->maps->rename($map, (string) $data['title'], $data['description'] ?? null, [
            'customer_id' => ! empty($data['customer']) ? (int) $data['customer'] : null,
            'project_id' => ! empty($data['project']) ? (int) $data['project'] : null,
        ]);

        return redirect()->route('ideas.show', $map)->with('success', __('ideas.flash.updated'));
    }

    /**
     * Auswahloptionen für Kontextbezüge (MVP-109), org-gescopt.
     *
     * @return array{customers: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Customer>, projects: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project>}
     */
    private function contextOptions(): array {
        return [
            'customers' => \App\Models\Customer::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'projects' => \App\Models\Project::query()->orderBy('name')->limit(500)->get(['id', 'name']),
        ];
    }

    public function archive(IdeaMap $map): RedirectResponse {
        Gate::authorize('delete', $map);
        $this->maps->archive($map);

        return redirect()->route('ideas.index')->with('success', __('ideas.flash.archived'));
    }

    public function unarchive(IdeaMap $map): RedirectResponse {
        Gate::authorize('delete', $map);
        $this->maps->unarchive($map);

        return redirect()->route('ideas.show', $map)->with('success', __('ideas.flash.unarchived'));
    }

    /** Papierkorb (SoftDelete): wiederherstellbar, kein Datenverlust. */
    public function destroy(IdeaMap $map): RedirectResponse {
        Gate::authorize('delete', $map);
        $map->delete();

        return redirect()->route('ideas.index')->with('success', __('ideas.flash.deleted'));
    }

    /** SoftDeleted bindet nicht über das Sqid-Routing → manuell aus dem Papierkorb auflösen. */
    public function restore(string $mapSqid): RedirectResponse {
        $id = app(SqidEncoder::class)->decode(IdeaMap::class, $mapSqid);
        $map = $id !== null ? IdeaMap::onlyTrashed()->find($id) : null;
        abort_unless($map instanceof IdeaMap, 404);
        Gate::authorize('restore', $map);

        $map->restore();

        return redirect()->route('ideas.show', $map)->with('success', __('ideas.flash.restored'));
    }

    /**
     * Eigentum übertragen (Eigentümer selbst oder `manageLifecycle`-Admin bei
     * Nutzer-Austritt — ohne Inhaltszugriff, auditiert).
     */
    public function transferOwnership(Request $request, IdeaMap $map): RedirectResponse {
        if (! Gate::allows('manageLifecycle', $map) && ! Gate::allows('share', $map)) {
            abort(403);
        }

        $newOwnerId = app(SqidEncoder::class)->decode(User::class, (string) $request->input('owner'));
        $newOwner = $newOwnerId !== null
            ? User::query()->where('organization_id', $map->organization_id)->find($newOwnerId)
            : null;
        if (! $newOwner instanceof User) {
            return back()->with('error', __('ideas.flash.owner_invalid'));
        }

        /** @var User $actor */
        $actor = Auth::user();
        $this->maps->transferOwnership($map, $newOwner, $actor);

        return back()->with('success', __('ideas.flash.ownership_transferred'));
    }
}
