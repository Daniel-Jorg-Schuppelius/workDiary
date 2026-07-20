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
use App\Models\{Customer, ForeignCustomer, IntegrationInboxItem, Organization, Project, User};
use App\Services\Integration\{InboxActionService, InboxGroupBookerRegistry, MatchProfileRegistry};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB};
use Illuminate\View\View;

/**
 * Zentrale Zuordnungs-Inbox (Datenimport-Drehscheibe): listet offene
 * Import-Einträge (unmatched/conflict/ambiguous) und führt die Anwender-
 * Entscheidungen aus. Siehe ../WorkDiary-Architecture/features/053.
 */
class IntegrationInboxController extends Controller {
    public function index(Request $request, MatchProfileRegistry $registry, InboxGroupBookerRegistry $bookers): View {
        $user = $this->authorizeBilling();
        $organization = $this->organizationOf($user);

        $status = (string) $request->input('status', IntegrationInboxItem::STATUS_OPEN);
        $caseType = (string) $request->input('case', 'all');
        $plugin = (string) $request->input('plugin', 'all');
        $target = (string) $request->input('target', 'all');

        // Gruppierte Zeit-Import-Einträge werden separat als Gruppen dargestellt;
        // die per-Eintrag-Liste zeigt nur ungruppierte Items.
        $query = IntegrationInboxItem::query()
            ->where('organization_id', $user->organization_id)
            ->whereNull('group_key')
            ->when($status !== 'all', fn($q) => $q->where('status', $status))
            ->when($caseType !== 'all', fn($q) => $q->where('case_type', $caseType))
            ->when($plugin !== 'all', fn($q) => $q->where('plugin_id', $plugin))
            ->when($target !== 'all', fn($q) => $q->where('target_type', $target))
            ->with('referenceable')
            ->orderByDesc('id');

        $plugins = IntegrationInboxItem::query()
            ->where('organization_id', $user->organization_id)
            ->distinct()->orderBy('plugin_id')->pluck('plugin_id')->all();

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

        $assignTargets = $this->buildAssignTargets($user, $registry);
        // Asset-Optionen für den Fernwartungs-Form-Typ „asset" (Geräte-Bindung).
        if ($groups->contains(fn(array $g): bool => ($g['form'] ?? null) === 'asset')) {
            $assignTargets[\App\Models\Asset::class] = \App\Models\Asset::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->orderBy('name')
                ->limit(1000)
                ->get(['id', 'name'])
                ->mapWithKeys(fn(\App\Models\Asset $a): array => [$a->getRouteKey() => (string) $a->name])
                ->all();
        }

        // Fremdkunden-Optionen nur für den Zeit-Import-Form-Typ „customer_project".
        $foreignCustomers = $groups->contains(fn(array $g): bool => ($g['form'] ?? null) === 'customer_project')
            ? $this->foreignCustomerOptions($user)
            : [];

        return view('admin.integration.inbox', [
            'items' => $query->paginate(25)->withQueryString(),
            'groups' => $groups,
            'projects' => $this->projectOptions($user),
            'foreignCustomers' => $foreignCustomers,
            'filters' => ['status' => $status, 'case' => $caseType, 'plugin' => $plugin, 'target' => $target],
            'plugins' => $plugins,
            'targets' => $registry->options(),
            'assignTargets' => $assignTargets,
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
     * unmatched-Einträgen). Pro Typ auf 1000 begrenzt — bei größeren Beständen
     * folgt später eine Such-Auswahl.
     *
     * @return array<string, array<string, string>>  targetType => [sqid => label]
     */
    private function buildAssignTargets(User $user, MatchProfileRegistry $registry): array {
        $out = [];
        foreach (array_keys($registry->options()) as $type) {
            if (! class_exists($type)) {
                continue;
            }
            /** @var array<string, string> $options */
            $options = [];
            /** @var class-string<Model> $type */
            $rows = (new $type)->newQuery()
                ->withoutGlobalScopes()
                ->where('organization_id', $user->organization_id)
                ->orderBy('name')
                ->limit(1000)
                ->get();
            foreach ($rows as $row) {
                $options[$row->getRouteKey()] = (string) ($row->getAttribute('name') ?? ('#' . $row->getKey()));
            }
            $out[$type] = $options;
        }

        return $out;
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
        $model = $service->createFromItem($item);

        return back()->with('success', __('Neuer Datensatz angelegt und zugeordnet (#:id).', ['id' => $model->getKey()]));
    }

    public function acceptRemote(IntegrationInboxItem $item, InboxActionService $service): RedirectResponse {
        $this->guard($item);
        $service->acceptRemote($item);

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
     * Bestehende Projekte der Organisation als Auswahloptionen für die
     * Gruppen-Buchung, gruppiert nach Kunde (Optgroup-Anzeige, per
     * `customer_sqid` clientseitig auf den gewählten Kunden filterbar) — inkl.
     * kundenloser (interner) Projekte, damit unter „Intern" ein vorhandenes
     * Firmenprojekt gewählt werden kann (customer_id = null). Werte sind opake
     * Sqids (nicht der Slug-Route-Key), damit Auswahl und Vorschlags-Preselect
     * dieselbe Kennung sprechen.
     *
     * @return list<array{customer_sqid: string, label: string, projects: list<array{sqid: string, name: string}>}>
     */
    private function projectOptions(User $user): array {
        $projects = Project::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $user->organization_id)
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id']);

        $companies = Customer::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $projects->pluck('customer_id')->filter()->unique()->all())
            ->get(['id', 'name', 'company'])
            ->keyBy('id');

        $out = [];
        foreach ($projects as $project) {
            $company = $project->customer_id !== null ? $companies->get($project->customer_id) : null;
            $key = $company !== null ? (string) $company->sqid : '';
            $out[$key] ??= [
                'customer_sqid' => $key,
                'label' => $company !== null
                    ? (string) ($company->company ?: $company->name)
                    : (string) __('Intern (ohne Kunde)'),
                'projects' => [],
            ];
            $out[$key]['projects'][] = ['sqid' => (string) $project->sqid, 'name' => (string) $project->name];
        }
        usort($out, fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $out;
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
                'label' => (string) ($company?->company ?: $company?->name ?: '—'),
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
