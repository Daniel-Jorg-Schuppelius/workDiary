<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationInboxController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Customer, ForeignCustomer, IntegrationInboxItem, Organization, Project, TimeEntry, User};
use App\Services\Integration\{InboxActionService, InboxGroupBookerRegistry, MatchProfileRegistry};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{MorphTo, Relation};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Schema};
use Illuminate\View\View;

/**
 * Zentrale Zuordnungs-Inbox (Datenimport-Drehscheibe): listet offene
 * Import-Einträge (unmatched/conflict/ambiguous) und führt die Anwender-
 * Entscheidungen aus. Siehe ../WorkDiary-Architecture/features/053.
 */
class IntegrationInboxController extends Controller {
    /** Obergrenze je Ziel-Typ in der „Zuordnen"-Auswahl (darüber: Suchfeld). */
    private const ASSIGN_TARGET_LIMIT = 1000;

    public function index(Request $request, MatchProfileRegistry $registry, InboxGroupBookerRegistry $bookers, \App\Plugins\PluginManager $pluginManager): View {
        $user = $this->authorizeBilling();
        $organization = $this->organizationOf($user);

        $status = (string) $request->input('status', IntegrationInboxItem::STATUS_OPEN);
        $caseType = (string) $request->input('case', 'all');
        $plugin = (string) $request->input('plugin', 'all');
        $target = (string) $request->input('target', 'all');
        $targetSearch = trim((string) $request->query('target_search', ''));

        // Gruppierte Zeit-Import-Einträge werden separat als Gruppen dargestellt;
        // die per-Eintrag-Liste zeigt nur ungruppierte Items.
        $query = IntegrationInboxItem::query()
            ->where('organization_id', $user->organization_id)
            ->whereNull('group_key')
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($caseType !== 'all', fn($q) => $q->where('case_type', $caseType))
            ->when($plugin !== 'all', fn($q) => $q->where('plugin_id', $plugin))
            ->when($target !== 'all', fn($q) => $q->where('target_type', $target))
            // Zeiteintrag-Kontext (Datum/Zeit/Projekt/Kunde) wird je Item
            // angezeigt — die Relationen kommen ohne N+1 mit.
            ->with(['referenceable' => function (Relation $relation): void {
                if ($relation instanceof MorphTo) {
                    $relation->morphWith([TimeEntry::class => ['project.customer', 'user:id,name']]);
                }
            }])
            ->orderByDesc('id');

        $plugins = IntegrationInboxItem::query()
            ->where('organization_id', $user->organization_id)
            ->distinct()->orderBy('plugin_id')->pluck('plugin_id')->all();

        // Offene Einzel-Items je Quelle für die Quellen-Tabs — unabhängig vom
        // aktiven Plugin-Filter, damit die Tab-Zähler stabil bleiben.
        $pluginOpenCounts = IntegrationInboxItem::query()
            ->where('organization_id', $user->organization_id)
            ->whereNull('group_key')
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->selectRaw('plugin_id, COUNT(*) AS aggregate')
            ->groupBy('plugin_id')
            ->pluck('aggregate', 'plugin_id')
            ->all();

        // Offene Zeit-Import-Gruppen je Plugin (nur wenn Status-Filter sie zeigt).
        $groups = collect();
        if (in_array($status, [IntegrationInboxItem::STATUS_OPEN, 'all'], true)) {
            foreach ($bookers->pluginIds() as $pid) {
                if ($plugin !== 'all' && $plugin !== $pid) {
                    continue;
                }
                $booker = $bookers->for($pid);
                if ($booker !== null) {
                    $groups = $groups->concat($booker->groups($organization));
                }
            }
        }

        [$assignTargets, $assignTargetsTruncated, $assignProjects] = $this->buildAssignTargets($user, $registry, $targetSearch);
        // Asset-Optionen für den Fernwartungs-Form-Typ „asset" (Geräte-Bindung).
        if ($groups->contains(fn(array $g): bool => ($g['form'] ?? null) === 'asset')) {
            $assetRows = \App\Models\Asset::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->when($targetSearch !== '', fn($q) => $q->whereLikeEscaped('name', $targetSearch))
                ->orderBy('name')
                ->limit(self::ASSIGN_TARGET_LIMIT + 1)
                ->get(['id', 'name']);
            if ($assetRows->count() > self::ASSIGN_TARGET_LIMIT) {
                $assignTargetsTruncated[\App\Models\Asset::class] = true;
                $assetRows = $assetRows->take(self::ASSIGN_TARGET_LIMIT);
            }
            $assignTargets[\App\Models\Asset::class] = $assetRows
                ->mapWithKeys(fn(\App\Models\Asset $a): array => [$a->getRouteKey() => (string) $a->name])
                ->all();
        }

        // Fremdkunden-Optionen für die Form-Typen mit Endkunden-Auswahl
        // (Zeit-Import „customer_project" und FritzBox-Rufnummern).
        $foreignCustomers = $groups->contains(fn(array $g): bool => in_array($g['form'] ?? null, ['customer_project', 'phone_number'], true))
            ? $this->foreignCustomerOptions($user)
            : [];

        // Anzeigenamen der Quellen (Plugin::name()) für Tabs und Badges —
        // über Items UND Gruppen; Nicht-Plugin-Quellen (CSV-Import,
        // Mail-Intake) bekommen feste Labels.
        $pluginNames = [];
        foreach (array_unique(array_merge($plugins, $groups->pluck('plugin_id')->all())) as $pid) {
            $pluginNames[$pid] = match ($pid) {
                IntegrationInboxItem::PLUGIN_CSV => (string) __('CSV-Import'),
                \App\Services\Mail\MailIntakeService::PLUGIN_ID => (string) __('E-Mail-Eingang'),
                default => $pluginManager->find($pid)?->name() ?? ucfirst($pid),
            };
        }

        return view('admin.integration.inbox', [
            'items' => $query->paginate(25)->withQueryString(),
            'groups' => $groups,
            // Projekt-Auswahl der Gruppen-Buchung: x-project-options gruppiert
            // selbst nach Kunde (data-customer/data-foreign für den
            // clientseitigen Filter); kundenlose (interne) Projekte inklusive.
            'projects' => Project::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->orderBy('name')
                ->get(['id', 'name', 'customer_id', 'foreign_customer_id']),
            'foreignCustomers' => $foreignCustomers,
            'filters' => ['status' => $status, 'case' => $caseType, 'plugin' => $plugin, 'target' => $target, 'target_search' => $targetSearch],
            'assignTargetsTruncated' => $assignTargetsTruncated,
            'plugins' => $plugins,
            'pluginNames' => $pluginNames,
            'pluginOpenCounts' => $pluginOpenCounts,
            'targets' => $registry->options(),
            'assignTargets' => $assignTargets,
            'assignProjects' => $assignProjects,
            // Benutzer-Zuordnungsfälle (MVP-509): aktive interne Benutzer als
            // Buchungsziel — Portalkonten und deaktivierte sind ausgeschlossen.
            'orgUsers' => User::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->whereNull('customer_id')
                ->whereNull('deactivated_at')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->mapWithKeys(fn(User $u): array => [(string) $u->sqid => (string) $u->name])
                ->all(),
            'openCount' => $this->openCount($user),
        ]);
    }

    public function bookGroup(Request $request, InboxGroupBookerRegistry $bookers): RedirectResponse {
        $user = $this->authorizeBilling();

        $booker = $bookers->for((string) $request->input('plugin'));
        abort_if($booker === null, 404);

        $data = $request->validate(array_merge([
            'plugin' => ['required', 'string'],
            'group_key' => ['required', 'string'],
        ], $booker->rules()));

        // Validierung + Ziel-Auflösung + Buchung liegen im Booker; der Controller
        // bleibt plugin-agnostisch.
        $organization = $this->organizationOf($user);
        $result = DB::transaction(fn(): array => $booker->book($organization, (string) $data['group_key'], $data));

        return back()->with('success', __(':created gebucht, :skipped bereits vorhanden.', $result));
    }

    public function dismissGroup(Request $request, InboxGroupBookerRegistry $bookers): RedirectResponse {
        $user = $this->authorizeBilling();
        $data = $request->validate([
            'plugin' => ['required', 'string'],
            'group_key' => ['required', 'string'],
        ]);

        $booker = $bookers->for($data['plugin']);
        abort_if($booker === null, 404);

        $count = $booker->dismiss($this->organizationOf($user), (string) $data['group_key']);

        return back()->with('success', __(':count Eintrag/Einträge verworfen.', ['count' => $count]));
    }

    /**
     * Bestehende lokale Datensätze je Ziel-Typ (für die „Zuordnen"-Auswahl bei
     * unmatched-Einträgen). Pro Typ auf ASSIGN_TARGET_LIMIT begrenzt; `$search`
     * (Filterleiste) grenzt die Auswahl serverseitig ein. Gekürzte Typen werden
     * im zweiten Rückgabewert gemeldet, damit die Ansicht darauf hinweist; der
     * dritte liefert die Projekt-Zeilen als Collection für x-project-options.
     *
     * @return array{0: array<string, array<string, string>>, 1: array<string, bool>, 2: \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project>|null}
     */
    private function buildAssignTargets(User $user, MatchProfileRegistry $registry, string $search = ''): array {
        $out = [];
        $truncated = [];
        // Projekt-Zeilen zusätzlich als Collection: das Assign-Dropdown rendert
        // Projekte über x-project-options (Kundengruppierung) statt der flachen
        // sqid-Label-Map.
        $projectRows = null;
        foreach (array_keys($registry->options()) as $type) {
            if (! class_exists($type)) {
                continue;
            }
            /** @var array<string, string> $options */
            $options = [];
            /** @var class-string<Model> $type */
            $model = new $type;
            // Nicht jedes Ziel hat eine `name`-Spalte (z. B. Event → `title`).
            $labelColumn = $this->targetLabelColumn($model);
            $rows = $model->newQuery()
                ->withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->when($search !== '' && $labelColumn !== null, fn($q) => $q->whereLikeEscaped((string) $labelColumn, $search))
                ->orderBy($labelColumn ?? $model->getKeyName())
                ->limit(self::ASSIGN_TARGET_LIMIT + 1)
                ->get();
            if ($rows->count() > self::ASSIGN_TARGET_LIMIT) {
                $truncated[$type] = true;
                $rows = $rows->take(self::ASSIGN_TARGET_LIMIT);
            }
            foreach ($rows as $row) {
                $label = $labelColumn !== null ? (string) ($row->getAttribute($labelColumn) ?? '') : '';
                $options[$row->getRouteKey()] = $label !== '' ? $label : ('#' . $row->getKey());
            }
            $out[$type] = $options;
            if ($type === \App\Models\Project::class) {
                $projectRows = $rows;
            }
        }

        return [$out, $truncated, $projectRows];
    }

    /** Erste vorhandene Anzeige-/Sortierspalte des Ziel-Modells (oder null). */
    private function targetLabelColumn(Model $model): ?string {
        foreach (['name', 'title'] as $column) {
            if (Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), $column)) {
                return $column;
            }
        }

        return null;
    }

    public function assign(Request $request, IntegrationInboxItem $item, InboxActionService $service): RedirectResponse {
        $this->guard($item);
        $request->validate(['target' => ['required', 'string']]);

        $model = $this->resolveTarget($item, (string) $request->input('target'));
        abort_unless($model instanceof Model, 404);

        $service->assignTo($item, $model);

        return back()->with('success', __('Eintrag zugeordnet.'));
    }

    public function create(IntegrationInboxItem $item, InboxActionService $service): RedirectResponse {
        $this->guard($item);

        try {
            $model = $service->createFromItem($item);
        } catch (\RuntimeException $e) {
            // Ziel-Typ ohne Anlege-Profil (oder fachliche Sperre) — als
            // Meldung zeigen, nicht als Fehlerseite (Muster acceptRemote).
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Neuer Datensatz angelegt und zugeordnet (#:id).', ['id' => $model->getKey()]));
    }

    public function acceptRemote(IntegrationInboxItem $item, InboxActionService $service): RedirectResponse {
        $this->guard($item);

        try {
            $service->acceptRemote($item);
        } catch (\RuntimeException $e) {
            // Fachliche Sperre (z. B. bereits abgerechnet) — als Meldung zeigen,
            // nicht als Fehlerseite.
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Konflikt zugunsten der Remote-Werte gelöst.'));
    }

    public function keepLocal(IntegrationInboxItem $item, InboxActionService $service): RedirectResponse {
        $this->guard($item);
        $service->keepLocal($item);

        return back()->with('success', __('Konflikt zugunsten der lokalen Werte geschlossen.'));
    }

    public function dismiss(IntegrationInboxItem $item, InboxActionService $service): RedirectResponse {
        $this->guard($item);
        $service->dismiss($item);

        return back()->with('success', __('Eintrag verworfen.'));
    }

    /**
     * Löst die Ziel-Entität aus der Sqid auf — die Modellklasse ergibt sich aus
     * dem target_type des Eintrags (mandanten-gescopt über den Global Scope).
     */
    private function resolveTarget(IntegrationInboxItem $item, string $sqid): ?Model {
        $class = $item->target_type;
        if (! class_exists($class)) {
            return null;
        }

        /** @var class-string<Model> $class */
        return (new $class)->resolveRouteBinding($sqid);
    }

    /**
     * Fremdkunden (Endkunden) der Organisation für die Gruppen-Buchung,
     * gruppiert nach Firma (Optgroup-Anzeige, per `customer_sqid` clientseitig
     * auf den gewählten Kunden filterbar).
     *
     * @return list<array{customer_sqid: string, label: string, foreigns: list<array{sqid: string, name: string}>}>
     */
    private function foreignCustomerOptions(User $user): array {
        $rows = ForeignCustomer::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'customer_id', 'name']);

        $companies = Customer::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $rows->pluck('customer_id')->unique()->all())
            ->get(['id', 'name', 'company'])
            ->keyBy('id');

        $out = [];
        foreach ($rows as $foreign) {
            $company = $companies->get($foreign->customer_id);
            $key = $company !== null ? (string) $company->sqid : '';
            $out[$key] ??= [
                'customer_sqid' => $key,
                'label' => (string) ($company?->displayLabel() ?: '—'),
                'foreigns' => [],
            ];
            $out[$key]['foreigns'][] = ['sqid' => (string) $foreign->getRouteKey(), 'name' => (string) $foreign->name];
        }
        usort($out, fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $out;
    }

    private function authorizeBilling(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->canManageBilling(), 403);

        return $user;
    }

    private function organizationOf(User $user): Organization {
        $organization = $user->organization;
        abort_unless($organization instanceof Organization, 403);

        return $organization;
    }

    private function guard(IntegrationInboxItem $item): void {
        $user = $this->authorizeBilling();
        abort_unless($item->organization_id === $user->organization_id, 404);
        abort_unless($item->isOpen(), 422);
    }

    private function openCount(User $user): int {
        return IntegrationInboxItem::query()
            ->where('organization_id', $user->organization_id)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->count();
    }
}
