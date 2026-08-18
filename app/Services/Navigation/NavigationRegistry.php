<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NavigationRegistry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Enums\User\Permission;
use App\Legacy\LegacyBridge;
use App\Models\User;
use App\Plugins\PluginManager;
use App\Services\Licensing\FeatureFlagResolver;
use Illuminate\Support\Facades\{Auth, Cache, Gate, Route};

/**
 * Zentrale, adressierbare Navigations-Registry: einzige Quelle aller
 * Menüstrukturen (Sidebar, Haupt-/Verwaltungs-/Systemmenü, Schnellerstellung,
 * Benutzermenü). Jeder Eintrag trägt einen stabilen Schlüssel für die
 * Per-User-Menüanpassung und den Funktionskatalog. Sichtbarkeit: Modul-Gating +
 * Rechte über {@see NavGate}, dann Per-User-Ausblendungen (nur Darstellung, D13);
 * die Struktur hält {@see \Tests\Feature\Navigation\NavigationGoldenTest} fest.
 * Konzept: Feature 081 (WorkDiary-Architecture).
 */
class NavigationRegistry {
    /** Präfixe der stabilen Ausblende-Schlüssel (MVP-374). */
    public const KEY_SECTION = 'section:';
    public const KEY_GROUP = 'group:';
    public const KEY_ITEM = 'item:';
    public const KEY_CREATE = 'create:';

    /** Preference-Key der Per-User-Ausblendungen (users.preferences). */
    public const PREFERENCE_HIDDEN = 'nav_hidden';

    public function __construct(
        private readonly FeatureFlagResolver $features,
        private readonly NavGate $gate,
        private readonly NavFocusService $focus,
    ) {}

    /**
     * Baut alle Menüstrukturen für den aktuellen Nutzer/Request.
     *
     *
     * @param  ?string  $focusKey  Aktiver Arbeitsbereich (Feature 082, MVP-377);
     *                             `null` = kein Fokusfilter (heutiges Verhalten,
     *                             Golden-Snapshot). Der Fokus greift NUR im neuen
     *                             Modus und ausschließlich als letzter, rein
     *                             kosmetischer Filterschritt (D13).
     * @return array{
     *     mainNavItems: list<array<string, mixed>>,
     *     manageNavItems: list<array<string, mixed>>,
     *     adminNavItems: list<array<string, mixed>>,
     *     userNavItems: list<array<string, mixed>>,
     *     sidebarSections: list<array<string, mixed>>,
     *     createGroups: list<array<string, mixed>>,
     *     pluginPanelItems: list<array<string, mixed>>,
     *     pluginPanelRoutes: list<string>,
     * }
     */
    public function build(bool $isLegacyMode, string $indexRoute, ?string $focusKey = null): array {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [
                'mainNavItems' => [],
                'manageNavItems' => [],
                'adminNavItems' => [],
                'userNavItems' => [],
                'sidebarSections' => [],
                'createGroups' => [],
                'pluginPanelItems' => [],
                'pluginPanelRoutes' => [],
            ];
        }

        $hidden = $isLegacyMode ? [] : $this->hiddenNavKeys($user);
        // Arbeitsbereich-Filter nur im neuen Modus; `null` lässt alles unberührt.
        $focusKeep = (! $isLegacyMode && $focusKey !== null) ? $this->focus->keepKeys($focusKey) : null;
        $focusManage = (! $isLegacyMode && $focusKey !== null) ? $this->focus->manageKeep($focusKey) : null;

        [$adminNavItems, $manageNavItems, $pluginPanelItems, $pluginPanelRoutes] = $this->headerMenus($user, $isLegacyMode);

        $mainNavItems = array_values(array_filter(
            $this->mainNavItems($isLegacyMode, $indexRoute),
            fn(array $it): bool => $this->gate->allows(isset($it['route']) ? (string) $it['route'] : null)
        ));
        $manageNavItems = array_values(array_filter(
            $manageNavItems,
            fn(array $it): bool => $this->gate->allows(isset($it['route']) ? (string) $it['route'] : null)
        ));
        // Arbeitsbereich-Filter: Verwaltungsmenü auf die Fokus-Routen einschränken; null = unverändert.
        if ($focusManage !== null) {
            $manageNavItems = array_values(array_filter(
                $manageNavItems,
                static fn(array $it): bool => \in_array((string) ($it['route'] ?? ''), $focusManage, true)
            ));
        }
        // Systemmenü einheitlich über NavGate (Plan UND Recht).
        $adminNavItems = array_values(array_filter(
            $adminNavItems,
            fn(array $it): bool => $this->gate->allows(isset($it['route']) ? (string) $it['route'] : null)
        ));
        $pluginPanelItems = array_values(array_filter(
            $pluginPanelItems,
            fn(array $it): bool => $this->gate->allows(isset($it['route']) ? (string) $it['route'] : null)
        ));

        return [
            'mainNavItems' => $mainNavItems,
            'manageNavItems' => $manageNavItems,
            'adminNavItems' => $adminNavItems,
            'userNavItems' => $this->userNavItems($isLegacyMode),
            'sidebarSections' => $isLegacyMode ? [] : $this->applyFocus($this->filterSidebar($this->sidebarBlueprint($indexRoute), $hidden), $focusKeep),
            'createGroups' => $isLegacyMode ? [] : $this->applyFocusCreateGroups($this->filterCreateGroups($this->createGroupsBlueprint(), $hidden), $focusKeep),
            'pluginPanelItems' => $pluginPanelItems,
            'pluginPanelRoutes' => $pluginPanelRoutes,
        ];
    }

    /**
     * Bereinigte Per-User-Ausblendungen (nur Schlüssel, die die Registry kennt
     * bzw. kennen kann — unbekannte Einträge werden ignoriert, damit entfernte
     * Menüpunkte nach Updates keine Karteileichen erzeugen).
     *
     * @return list<string>
     */
    public function hiddenNavKeys(User $user): array {
        $stored = $user->getPreference(self::PREFERENCE_HIDDEN, []);
        if (! is_array($stored)) {
            return [];
        }

        $prefixes = [self::KEY_SECTION, self::KEY_GROUP, self::KEY_ITEM, self::KEY_CREATE];

        return array_values(array_filter(
            array_map(static fn($v): string => (string) $v, $stored),
            static function (string $key) use ($prefixes): bool {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($key, $prefix) && strlen($key) > strlen($prefix)) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }

    /** Rechte-Ebene einer Route (NavGate::mayAccess) — für den Funktionskatalog. */
    public function mayAccessRoute(string $route): bool {
        return $this->gate->mayAccess($route);
    }

    /**
     * Entfernt null-Einträge (bedingt eingeblendete Items) aus einer Item-Liste
     * und reindiziert. Kapselt `array_values(array_filter())` mit einem
     * array-Parameter, damit die Reindizierung auch statisch als notwendig gilt.
     *
     * @param  list<array<string, mixed>|null>  $items
     * @return list<array<string, mixed>>
     */
    private function compactItems(array $items): array {
        return array_values(array_filter($items));
    }

    /**
     * Sektions-Schlüssel → Modul (hartes Sidebar-Gating auf Sektionsebene).
     *
     * @return array<string, string>
     */
    public function moduleBySectionKey(): array {
        return [
            'plan' => 'module.planung',
            'travel-expenses' => 'module.spesen',
            'fleet' => 'module.fuhrpark',
            'facility' => 'module.liegenschaften',
            'location' => 'module.standorterfassung',
            'sales' => 'module.vertrieb',
            'compliance' => 'module.compliance',
            'datenschutz' => 'module.datenschutz',
            'isms' => 'module.isms',
        ];
    }

    /**
     * Item-Route → Modul (feines Sidebar-Gating einzelner Einträge).
     *
     * @return array<string, string>
     */
    public function moduleByItemRoute(): array {
        return [
            'kanban.index' => 'module.kanban',
            'agile.reports.overview' => 'module.agile_projects',
            'tenders.index' => 'module.applications',
            'tender-radar.index' => 'module.applications',
            'tenders.cockpit' => 'module.applications',
            'investments.index' => 'module.investments',
            'crisis.index' => 'module.crisis_management',
            'sustainability.index' => 'module.sustainability',
            'claims.index' => 'module.claims',
            'claims.reports.index' => 'module.claims',
            'passenger-rides.index' => 'module.fuhrpark',
            'passenger-masterdata.index' => 'module.fuhrpark',
            'passenger-settlements.index' => 'module.fuhrpark',
            'print-orders.index' => 'module.lager',
            'domains.index' => 'module.domain',
            'domain-reseller.index' => 'module.domain',
            'domains.reports' => 'module.domain',
            'admin.domain-provider.index' => 'module.domain',
            'admin.document-design.index' => 'module.dokumentdesign',
            'admin.ai.index' => 'module.ai',
            'rental.index' => 'module.rental',
            'rental.calendar' => 'module.rental',
            'rental.profiles.index' => 'module.rental',
            'rental.rates.index' => 'module.rental',
            'rental.reports.index' => 'module.rental',
            'disposal.index' => 'module.entsorgung',
            'disposal.reports.index' => 'module.entsorgung',
            'asset-finance.index' => 'module.asset_finance',
            'asset-finance.deadlines.index' => 'module.asset_finance',
            'asset-finance.reports.index' => 'module.asset_finance',
            'contracts.index' => 'module.contracts',
            'asset-compliance.index' => 'module.asset_compliance',
            'asset-compliance.profiles.index' => 'module.asset_compliance',
            'asset-compliance.schedules.index' => 'module.asset_compliance',
            'asset-compliance.reports.index' => 'module.asset_compliance',
            'crisis.exercises.index' => 'module.crisis_management',
            'recruiting.requisitions.index' => 'module.applications',
            'recruiting.applications.index' => 'module.applications',
            'documents.index' => 'module.documents',
            'knowledge.index' => 'module.knowledge',
            'ideas.index' => 'module.ideas',
            'form-submissions.index' => 'module.forms',
            'finance.open-times.index' => 'module.finance',
            'finance.transfers.index' => 'module.finance',
            'finance.reconciliation.index' => 'module.finance',
            'finance.bank-accounts.index' => 'module.finance',
            'finance.datev.index' => 'module.finance',
            'finance.gobd.index' => 'module.finance',
            // Lager & Fertigung: ohne module.lager ausblenden statt nur per Route-Gate (423) sperren.
            'articles.index' => 'module.lager',
            'warehouses.index' => 'module.lager',
            'manufacturing-orders.index' => 'module.lager',
            'serials.index' => 'module.lager',
            'purchase-orders.index' => 'module.lager',
            'supplier-catalogs.index' => 'module.lager',
            'pricing-margin-rules.index' => 'module.lager',
            'inventory.scan' => 'module.lager',
            'work-centers.index' => 'module.lager',
            'inventory.lots' => 'module.lager',
            'inventory.label-templates.index' => 'module.lager',
            'b2b-catalog.index' => 'module.b2b_katalog',
            'bill-of-quantities.index' => 'module.bau',
            'bill-of-quantities.packages' => 'module.bau',
            'catalog-rules.index' => 'module.bau',
            'cost-catalogs.index' => 'module.bau',
        ];
    }

    /**
     * Untergruppen-Schlüssel → Modul.
     *
     * @return array<string, string>
     */
    public function moduleByGroupKey(): array {
        return [
            'reports-team' => 'module.auswertungen_team',
            'reports-projects' => 'module.auswertungen_team',
            'reports-resources' => 'module.auswertungen_team',
        ];
    }

    /**
     * Sidebar-Sektionen VOR Modul-/Ausblende-Filter (Rechte-Checks inline wie
     * bisher). Öffentlich, weil der Funktionskatalog (MVP-375) die vollständige
     * Struktur inklusive modul-deaktivierter Bereiche benötigt.
     *
     * @return list<array<string, mixed>>
     */
    public function sidebarBlueprint(string $indexRoute): array {
        /** @var User|null $user */
        $user = Auth::user();
        $isGlobalAdmin = $user instanceof User && $user->isAdmin();

        $sidebarSections = [];
        $sidebarSections[] = [
            'key' => 'work',
            'label' => __('Tagesgeschäft'),
            'collapsible' => true,
            'groups' => [
                [
                    'key' => 'work-capture',
                    'label' => __('Erfassung'),
                    'icon' => 'edit_note',
                    'items' => $this->compactItems([
                        // „Heute" ist seit MVP-015 auch der Tagesabschluss (day-close.* für Fremdtage/Admin via ?user=).
                        ['route' => 'today.show', 'label' => __('Heute'), 'icon' => 'today', 'modal' => false, 'matches' => ['today.show', 'day-close.*']],
                        ['route' => $indexRoute, 'label' => __('Arbeitsliste'), 'icon' => 'list_alt', 'modal' => false, 'matches' => [$indexRoute, 'diary.*']],
                        ['route' => 'week.index', 'label' => __('Wochenansicht'), 'icon' => 'calendar_view_week', 'modal' => false, 'matches' => ['week.index']],
                        ['route' => 'kanban.index', 'label' => __('Kanban'), 'icon' => 'view_kanban', 'modal' => false, 'matches' => ['kanban.index']],
                        // Agile Übersicht (Feature 064): org-weiter Einstieg; Board/Backlog sind projektgebunden.
                        Gate::allows(Permission::AgileReportView->value)
                            ? ['route' => 'agile.reports.overview', 'label' => __('Agile Übersicht'), 'icon' => 'sprint', 'modal' => false, 'matches' => ['agile.*']]
                            : null,
                        ['route' => 'attendance.index', 'label' => __('Stempeluhr'), 'icon' => 'punch_clock', 'modal' => false, 'matches' => ['attendance.*']],
                    ]),
                ],
                [
                    'key' => 'work-knowledge',
                    'label' => __('Wissen & Doku'),
                    'icon' => 'menu_book',
                    'items' => $this->compactItems([
                        // Dokumente & Formulare per Tab zusammengelegt → ein Eintrag. Route zeigt auf die
                        // zugängliche Seite, bleibt sichtbar, wenn nur eines von beiden (Recht/Modul) verfügbar ist.
                        [
                            'route' => (Gate::allows('viewAny', \App\Models\Document::class)
                                && $this->features->isEnabled('module.documents'))
                                ? 'documents.index' : 'form-submissions.index',
                            'label' => __('document.title.index') . ' & ' . __('form.title.submissions'),
                            'icon' => 'folder_open',
                            'modal' => false,
                            'matches' => ['documents.*', 'form-submissions.*'],
                        ],
                        ['route' => 'knowledge.index', 'label' => __('knowledge.title.index'), 'icon' => 'school', 'modal' => false, 'matches' => ['knowledge.*']],
                        ['route' => 'ideas.index', 'label' => __('ideas.title.index'), 'icon' => 'emoji_objects', 'modal' => false, 'matches' => ['ideas.*']],
                        // Sicherheitsereignisse: sichtbar für Melder (create) und Register-Berechtigte (viewAny).
                        (Gate::allows('viewAny', \App\Models\SafetyEvent::class)
                            || Gate::allows('create', \App\Models\SafetyEvent::class))
                            ? ['route' => 'safety-events.index', 'label' => __('safety.title.index'), 'icon' => 'health_and_safety', 'modal' => false, 'matches' => ['safety-events.*']]
                            : null,
                    ]),
                ],
            ],
        ];
        $sidebarSections[] = [
            'key' => 'plan',
            'label' => __('Planung'),
            'collapsible' => true,
            'items' => $this->compactItems([
                // Dienstpläne + Verfügbarkeit/Wunschdienste per Tab zusammengelegt → ein Eintrag.
                ['route' => 'duty-plans.index', 'label' => __('Dienstpläne'), 'icon' => 'event_available', 'modal' => false, 'matches' => ['duty-plans.*', 'schedule.availability.*']],
                // Schichtplan + Schichttausch ebenso.
                ['route' => 'schedule.index', 'label' => __('Schichtplan'), 'icon' => 'schedule', 'modal' => false, 'matches' => ['schedule.index', 'schedule.api.*', 'schedule.shifts.*', 'schedule.types.*', 'schedule.import.*', 'schedule.suggest', 'schedule.exchanges.*']],
                ['route' => 'timesheets.index', 'label' => __('Stundenzettel'), 'icon' => 'description', 'modal' => false, 'matches' => ['timesheets.*', 'projects.timesheets.*']],
                ['route' => 'flex.index', 'label' => __('Arbeitszeitkonto'), 'icon' => 'hourglass_top', 'modal' => false, 'matches' => ['flex.*']],
                // MVP-526: konfigurierbare Zusatz-Zeitkonten.
                ['route' => 'time-accounts.index', 'label' => __('Zeitkonten'), 'icon' => 'account_balance', 'modal' => false, 'matches' => ['time-accounts.*']],
                // MVP-524: Anwesenheits-Board — nur bei aktiviertem Org-Opt-in.
                (bool) data_get(
                    app()->bound('currentOrganization') && app('currentOrganization') instanceof \App\Models\Organization
                        ? app('currentOrganization')->settings
                        : null,
                    'presence.board_enabled',
                    false,
                )
                    ? ['route' => 'presence.board', 'label' => __('Aktuelle Belegung'), 'icon' => 'meeting_room', 'modal' => false, 'matches' => ['presence.board']]
                    : null,
                ['route' => 'tours.index', 'label' => __('Touren'), 'icon' => 'route', 'modal' => false, 'matches' => ['tours.index', 'tours.map', 'tours.create', 'tours.show', 'tours.edit']],
                // Leitstelle (Feature 029): Dispatch-Board + Karte.
                Gate::allows(Permission::DispatchViewAny->value)
                    ? ['route' => 'dispatch.board', 'label' => __('Leitstelle'), 'icon' => 'dashboard', 'modal' => false, 'matches' => ['dispatch.board', 'dispatch.map']]
                    : null,
            ]),
        ];
        $sidebarSections[] = [
            'key' => 'travel-expenses',
            'label' => __('Reisen & Spesen'),
            'collapsible' => true,
            'items' => [
                ['route' => 'travel-logs.index', 'label' => __('Fahrtenbuch'), 'icon' => 'directions_car', 'modal' => false, 'matches' => ['travel-logs.*']],
                ['route' => 'expenses.index', 'label' => __('Spesen'), 'icon' => 'receipt_long', 'modal' => false, 'matches' => ['expenses.*']],
                ['route' => 'per-diem-trips.index', 'label' => __('Verpflegungspauschalen'), 'icon' => 'restaurant_menu', 'modal' => false, 'matches' => ['per-diem-trips.*']],
                ...($isGlobalAdmin ? [
                    ['route' => 'expense-approvals.inbox', 'label' => __('Spesen-Genehmigung'), 'icon' => 'fact_check', 'modal' => false, 'matches' => ['expense-approvals.*']],
                ] : []),
            ],
        ];
        $sidebarSections[] = [
            'key' => 'fleet',
            'label' => __('Fuhrpark'),
            'collapsible' => true,
            'items' => [
                ...(Gate::allows('viewAny', \App\Models\Asset::class) ? [
                    ['route' => 'assets.index', 'label' => __('Objekte & Assets'), 'icon' => 'precision_manufacturing', 'modal' => false, 'matches' => ['assets.*']],
                ] : []),
                ['route' => 'vehicles.index', 'label' => __('Fahrzeuge'), 'icon' => 'directions_car', 'modal' => false, 'matches' => ['vehicles.*']],
                ['route' => 'energy-logs.index', 'label' => __('Tank & Ladelog'), 'icon' => 'local_gas_station', 'modal' => false, 'matches' => ['energy-logs.*']],
            ],
        ];
        $sidebarSections[] = [
            'key' => 'facility',
            'label' => __('Liegenschaften'),
            'collapsible' => true,
            'items' => [
                ['route' => 'sites.index', 'label' => __('Standorte'), 'icon' => 'location_on', 'modal' => false, 'matches' => ['sites.*']],
                ['route' => 'buildings.index', 'label' => __('Gebäude'), 'icon' => 'apartment', 'modal' => false, 'matches' => ['buildings.*']],
                ['route' => 'floors.index', 'label' => __('Geschosse'), 'icon' => 'layers', 'modal' => false, 'matches' => ['floors.*']],
                ['route' => 'rooms.index', 'label' => __('Räume'), 'icon' => 'meeting_room', 'modal' => false, 'matches' => ['rooms.*']],
            ],
        ];
        $sidebarSections[] = [
            'key' => 'servicedesk',
            'label' => __('Service Desk'),
            'collapsible' => true,
            'items' => $this->compactItems([
                Gate::allows(Permission::ServiceTicketView->value)
                    ? ['route' => 'service-tickets.index', 'label' => __('Tickets'), 'icon' => 'confirmation_number', 'modal' => false, 'matches' => ['service-tickets.*']]
                    : null,
                Gate::allows(Permission::ServiceTicketView->value)
                    ? ['route' => 'helpdesk.board.index', 'label' => __('Queue-Board'), 'icon' => 'view_kanban', 'modal' => false, 'matches' => ['helpdesk.board.*']]
                    : null,
                Gate::allows(Permission::HelpdeskQueueManage->value)
                    ? ['route' => 'helpdesk.queues.index', 'label' => __('Queues'), 'icon' => 'inbox', 'modal' => false, 'matches' => ['helpdesk.queues.*']]
                    : null,
                Gate::allows(Permission::HelpdeskQueueManage->value)
                    ? ['route' => 'helpdesk.routing.index', 'label' => __('Ticket-Routing'), 'icon' => 'alt_route', 'modal' => false, 'matches' => ['helpdesk.routing.*']]
                    : null,
                Gate::allows('viewAny', \App\Models\RequestItem::class)
                    ? ['route' => 'servicedesk.catalog.index', 'label' => __('Servicekatalog'), 'icon' => 'storefront', 'modal' => false, 'matches' => ['servicedesk.catalog.*']]
                    : null,
                Gate::allows(Permission::ServiceRequestApprove->value)
                    ? ['route' => 'servicedesk.approvals.index', 'label' => __('Genehmigungen'), 'icon' => 'approval', 'modal' => false, 'matches' => ['servicedesk.approvals.*']]
                    : null,
                Gate::allows('viewAny', \App\Models\Problem::class)
                    ? ['route' => 'servicedesk.problems.index', 'label' => __('Probleme'), 'icon' => 'troubleshoot', 'modal' => false, 'matches' => ['servicedesk.problems.*']]
                    : null,
                Gate::allows('viewAny', \App\Models\Change::class)
                    ? ['route' => 'servicedesk.changes.index', 'label' => __('Changes'), 'icon' => 'published_with_changes', 'modal' => false, 'matches' => ['servicedesk.changes.*', 'servicedesk.change-templates.*']]
                    : null,
                Gate::allows(Permission::SlaContractView->value)
                    ? ['route' => 'sla-contracts.index', 'label' => __('SLA-Verträge'), 'icon' => 'handshake', 'modal' => false, 'matches' => ['sla-contracts.*']]
                    : null,
                Gate::allows(Permission::SlaViewAny->value)
                    ? ['route' => 'helpdesk.reports.index', 'label' => __('Helpdesk-Bericht'), 'icon' => 'monitoring', 'modal' => false, 'matches' => ['helpdesk.reports.*']]
                    : null,
            ]),
        ];
        $sidebarSections[] = [
            'key' => 'location',
            'label' => __('Standorterfassung'),
            'collapsible' => true,
            'items' => [
                ['route' => 'geofences.index', 'label' => __('Geofences'), 'icon' => 'pin_drop', 'modal' => false, 'matches' => ['geofences.*']],
                ['route' => 'location.review.index', 'label' => __('Standort-Vorschläge'), 'icon' => 'where_to_vote', 'modal' => false, 'matches' => ['location.review.*']],
                ['route' => 'location.devices.index', 'label' => __('Meine Geräte'), 'icon' => 'smartphone', 'modal' => false, 'matches' => ['location.devices.*']],
            ],
        ];
        $sidebarSections[] = [
            'key' => 'sales',
            'label' => __('Vertrieb & Abrechnung'),
            'collapsible' => true,
            'groups' => [
                [
                    'key' => 'sales-crm',
                    'label' => __('Vertrieb'),
                    'icon' => 'badge',
                    'items' => [
                        ['route' => 'customers.index', 'label' => __('Kunden'), 'icon' => 'badge', 'modal' => false, 'matches' => ['customers.*']],
                        ['route' => 'suppliers.index', 'label' => __('Lieferanten'), 'icon' => 'local_shipping', 'modal' => false, 'matches' => ['suppliers.*']],
                        ['route' => 'projects.index', 'label' => __('Projekte'), 'icon' => 'folder_special', 'modal' => false, 'matches' => ['projects.*']],
                        ['route' => 'events.index', 'label' => __('Veranstaltungen'), 'icon' => 'event', 'modal' => false, 'matches' => ['events.*']],
                        ['route' => 'tenders.index', 'label' => __('Ausschreibungen'), 'icon' => 'gavel', 'modal' => false, 'matches' => ['tenders.*']],
                        ['route' => 'tender-radar.index', 'label' => __('Bekanntmachungs-Radar'), 'icon' => 'radar', 'modal' => false, 'matches' => ['tender-radar.*']],
                        ['route' => 'tenders.cockpit', 'label' => __('Vergabe-Cockpit'), 'icon' => 'query_stats', 'modal' => false, 'matches' => ['tenders.cockpit']],
                        // Ein Eintrag für die gesamte Personalgewinnung — Stellen/Bewerbungen
                        // laufen auf der Seite selbst über Tabs (recruiting._tabs).
                        ['route' => 'recruiting.requisitions.index', 'label' => __('Personalgewinnung'), 'icon' => 'person_search', 'modal' => false, 'matches' => ['recruiting.*']],
                    ],
                ],
                [
                    'key' => 'sales-inventory',
                    'label' => __('Lager & Fertigung'),
                    'icon' => 'warehouse',
                    'items' => [
                        ['route' => 'products.index', 'label' => __('products.title.index'), 'icon' => 'category', 'modal' => false, 'matches' => ['products.*']],
                        ['route' => 'articles.index', 'label' => __('article.title'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['articles.*']],
                        ['route' => 'warehouses.index', 'label' => __('inventory.title'), 'icon' => 'warehouse', 'modal' => false, 'matches' => ['warehouses.*', 'inventory.*']],
                        ['route' => 'manufacturing-orders.index', 'label' => __('manufacturing.order.title'), 'icon' => 'precision_manufacturing', 'modal' => false, 'matches' => ['manufacturing-orders.*']],
                        ['route' => 'serials.index', 'label' => __('inventory.serial.title'), 'icon' => 'tag', 'modal' => false, 'matches' => ['serials.*']],
                        ['route' => 'purchase-orders.index', 'label' => __('procurement.title'), 'icon' => 'shopping_cart', 'modal' => false, 'matches' => ['purchase-orders.*']],
                        ['route' => 'supplier-catalogs.index', 'label' => __('procurement.catalog.title'), 'icon' => 'import_export', 'modal' => false, 'matches' => ['supplier-catalogs.*']],
                        ['route' => 'b2b-catalog.index', 'label' => __('b2b_catalog.title'), 'icon' => 'storefront', 'modal' => false, 'matches' => ['b2b-catalog.*']],
                        ['route' => 'pricing-margin-rules.index', 'label' => __('procurement.margin.title'), 'icon' => 'percent', 'modal' => false, 'matches' => ['pricing-margin-rules.*']],
                        ['route' => 'bill-of-quantities.index', 'label' => __('gaeb.title'), 'icon' => 'request_quote', 'modal' => false, 'matches' => ['bill-of-quantities.*']],
                        ['route' => 'bill-of-quantities.packages', 'label' => __('Vergabeunterlagen'), 'icon' => 'folder_zip', 'modal' => false, 'matches' => ['bill-of-quantities.packages*']],
                        ['route' => 'catalog-rules.index', 'label' => __('Zuordnungsregeln'), 'icon' => 'auto_fix_high', 'modal' => false, 'matches' => ['catalog-rules.*']],
                        ['route' => 'cost-catalogs.index', 'label' => __('Baukostenkataloge'), 'icon' => 'price_change', 'modal' => false, 'matches' => ['cost-catalogs.*']],
                        ['route' => 'inventory.scan', 'label' => __('inventory.scan.title'), 'icon' => 'qr_code_scanner', 'modal' => false, 'matches' => ['inventory.scan*']],
                        ['route' => 'work-centers.index', 'label' => __('manufacturing.capacity.title'), 'icon' => 'event_available', 'modal' => false, 'matches' => ['work-centers.*']],
                        ['route' => 'inventory.lots', 'label' => __('inventory.lot.title'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['inventory.lots*']],
                        ['route' => 'inventory.label-templates.index', 'label' => __('inventory.label_template.title'), 'icon' => 'label', 'modal' => false, 'matches' => ['inventory.label-templates.*']],
                    ],
                ],
                [
                    'key' => 'sales-billing',
                    'label' => __('Abrechnung & Finanzen'),
                    'icon' => 'request_quote',
                    'items' => [
                        ['route' => 'billing.feed', 'label' => __('billing.feed.title'), 'icon' => 'request_quote', 'modal' => false, 'matches' => ['billing.feed', 'invoices.*', 'quotes.*', 'lexoffice.vouchers.*'], 'badge' => $this->overdueDocumentCount()],
                        ...(Gate::allows('timeEntry.viewAny')
                            ? [['route' => 'finance.open-times.index', 'label' => __('finance.open_times.menu'), 'icon' => 'pending_actions', 'modal' => false, 'matches' => ['finance.open-times.*']]]
                            : []),
                        ['route' => 'finance.transfers.index', 'label' => __('finance.title.menu'), 'icon' => 'outbox', 'modal' => false, 'matches' => ['finance.transfers.*', 'finance.reconciliation.*', 'finance.bank-accounts.*']],
                        ['route' => 'finance.datev.index', 'label' => __('finance.datev.menu'), 'icon' => 'account_tree', 'modal' => false, 'matches' => ['finance.datev.*']],
                        ['route' => 'finance.gobd.index', 'label' => __('gobd.title'), 'icon' => 'gavel', 'modal' => false, 'matches' => ['finance.gobd.*']],
                        ['route' => 'lexoffice.articles.index', 'label' => __('Produkte & Leistungen'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['lexoffice.articles.*']],
                        ['route' => 'investments.index', 'label' => __('Investitionen'), 'icon' => 'trending_up', 'modal' => false, 'matches' => ['investments.*']],
                    ],
                ],
            ],
        ];
        // Meldestelle: nur für eigens Berechtigte (NICHT automatisch Admins), s. WhistleblowingCasePolicy.
        if (Gate::allows('viewAny', \App\Models\Whistleblowing\WhistleblowingCase::class)) {
            $sidebarSections[] = [
                'key' => 'compliance',
                'label' => __('Compliance'),
                'collapsible' => true,
                'items' => [
                    ['route' => 'whistleblowing.internal.index', 'label' => __('Meldestelle'), 'icon' => 'report', 'modal' => false, 'matches' => ['whistleblowing.internal.*']],
                ],
            ];
        }
        if (Gate::allows('viewAny', \App\Models\Sustainability\SustainabilityAssessment::class)) {
            $sidebarSections[] = [
                'key' => 'sustainability',
                'label' => __('Nachhaltigkeit'),
                'collapsible' => true,
                'items' => [
                    ['route' => 'sustainability.index', 'label' => __('Nachhaltigkeit & ESG'), 'icon' => 'eco', 'modal' => false, 'matches' => ['sustainability.*']],
                ],
            ];
        }
        if (Gate::allows('viewAny', \App\Models\Claims\ClaimCase::class)) {
            $sidebarSections[] = [
                'key' => 'claims',
                'label' => __('Reklamationen'),
                'collapsible' => true,
                'items' => [
                    ['route' => 'claims.index', 'label' => __('Reklamationsakten'), 'icon' => 'assignment_return', 'modal' => false, 'matches' => ['claims.index', 'claims.show']],
                    ['route' => 'claims.reports.index', 'label' => __('Qualitätsbericht'), 'icon' => 'query_stats', 'modal' => false, 'matches' => ['claims.reports.*']],
                ],
            ];
        }
        // MVP-456: Personenbeförderung — erscheint nur mit installiertem
        // Branchenprofil taxi-mietwagen (Profil-Gate wie im Controller).
        $navOrganization = $user?->organization;
        if (
            $navOrganization !== null
            && app(\App\Services\Passenger\PassengerRideService::class)->isPassengerProfileActive($navOrganization)
            && Gate::allows('viewAny', \App\Models\Passenger\PassengerRide::class)
        ) {
            $sidebarSections[] = [
                'key' => 'passenger',
                'label' => __('passenger.nav.section'),
                'collapsible' => true,
                'items' => [
                    ['route' => 'passenger-rides.index', 'label' => __('passenger.rides.title'), 'icon' => 'local_taxi', 'modal' => false, 'matches' => ['passenger-rides.*']],
                    ['route' => 'passenger-masterdata.index', 'label' => __('passenger.masterdata.title'), 'icon' => 'price_change', 'modal' => false, 'matches' => ['passenger-masterdata.*']],
                    ['route' => 'passenger-settlements.index', 'label' => __('passenger.settlements.title'), 'icon' => 'payments', 'modal' => false, 'matches' => ['passenger-settlements.*']],
                ],
            ];
        }
        // MVP-459: Druckaufträge — erscheint nur mit installiertem
        // Branchenprofil druck-kopiershop (Profil-Gate wie im Controller).
        if (
            $navOrganization !== null
            && app(\App\Services\Print\PrintOrderService::class)->isPrintProfileActive($navOrganization)
            && Gate::allows('viewAny', \App\Models\Print\PrintOrder::class)
        ) {
            $sidebarSections[] = [
                'key' => 'print',
                'label' => __('print.nav.section'),
                'collapsible' => true,
                'items' => [
                    ['route' => 'print-orders.index', 'label' => __('print.orders.title'), 'icon' => 'print', 'modal' => false, 'matches' => ['print-orders.*']],
                ],
            ];
        }
        if (Gate::allows('viewAny', \App\Models\Domain\DomainProjection::class)) {
            $sidebarSections[] = [
                'key' => 'domains',
                'label' => __('domain.title.index'),
                'collapsible' => true,
                'items' => [
                    ['route' => 'domains.index', 'label' => __('domain.title.index'), 'icon' => 'dns', 'modal' => false, 'matches' => ['domains.index', 'domains.show']],
                    ['route' => 'domain-reseller.index', 'label' => __('domain.title.reseller'), 'icon' => 'account_tree', 'modal' => false, 'matches' => ['domain-reseller.*']],
                    ['route' => 'domains.reports', 'label' => __('domain.title.reports'), 'icon' => 'analytics', 'modal' => false, 'matches' => ['domains.reports']],
                ],
            ];
        }
        if (Gate::allows('viewAny', \App\Models\Rental\RentalCase::class)) {
            $sidebarSections[] = [
                'key' => 'rental',
                'label' => __('Verleih'),
                'collapsible' => true,
                'items' => [
                    ['route' => 'rental.index', 'label' => __('Verleihakten'), 'icon' => 'forklift', 'modal' => false, 'matches' => ['rental.index', 'rental.show']],
                    ['route' => 'rental.calendar', 'label' => __('Verfügbarkeitskalender'), 'icon' => 'calendar_month', 'modal' => false, 'matches' => ['rental.calendar']],
                    ['route' => 'rental.profiles.index', 'label' => __('Gerätepool'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['rental.profiles.*']],
                    ['route' => 'rental.rates.index', 'label' => __('Preislisten'), 'icon' => 'price_change', 'modal' => false, 'matches' => ['rental.rates.*']],
                    ['route' => 'rental.reports.index', 'label' => __('Verleihbericht'), 'icon' => 'query_stats', 'modal' => false, 'matches' => ['rental.reports.*']],
                ],
            ];
        }
        if (Gate::allows('viewAny', \App\Models\Disposal\DisposalJob::class)) {
            $sidebarSections[] = [
                'key' => 'disposal',
                'label' => __('Entsorgung'),
                'collapsible' => true,
                'items' => [
                    ['route' => 'disposal.index', 'label' => __('Entsorgungsakten'), 'icon' => 'recycling', 'modal' => false, 'matches' => ['disposal.index', 'disposal.show']],
                    ['route' => 'disposal.reports.index', 'label' => __('Entsorgungsbericht'), 'icon' => 'query_stats', 'modal' => false, 'matches' => ['disposal.reports.*']],
                ],
            ];
        }
        if (Gate::allows('viewAny', \App\Models\AssetFinance\AssetFinanceContract::class)) {
            $sidebarSections[] = [
                'key' => 'asset-finance',
                'label' => __('Leasing & Verträge'),
                'collapsible' => true,
                'items' => [
                    ['route' => 'asset-finance.index', 'label' => __('Leasingakten'), 'icon' => 'request_quote', 'modal' => false, 'matches' => ['asset-finance.index', 'asset-finance.show']],
                    ['route' => 'asset-finance.deadlines.index', 'label' => __('Fristenkalender'), 'icon' => 'event_upcoming', 'modal' => false, 'matches' => ['asset-finance.deadlines.*']],
                    ['route' => 'asset-finance.reports.index', 'label' => __('Leasingbericht'), 'icon' => 'query_stats', 'modal' => false, 'matches' => ['asset-finance.reports.*']],
                ],
            ];
        }
        if (Gate::allows('viewAny', \App\Models\Contract\Contract::class)) {
            $sidebarSections[] = [
                'key' => 'contracts',
                'label' => __('Verträge'),
                'collapsible' => true,
                'items' => [
                    ['route' => 'contracts.index', 'label' => __('Vertragsakten'), 'icon' => 'contract', 'modal' => false, 'matches' => ['contracts.index', 'contracts.show']],
                ],
            ];
        }
        if (Gate::allows('viewAny', \App\Models\AssetCompliance\AssetComplianceProfile::class)) {
            $sidebarSections[] = [
                'key' => 'asset-compliance',
                'label' => __('Prüfmittel'),
                'collapsible' => true,
                'items' => [
                    ['route' => 'asset-compliance.index', 'label' => __('Prüf-Dashboard'), 'icon' => 'rule_settings', 'modal' => false, 'matches' => ['asset-compliance.index']],
                    ['route' => 'asset-compliance.profiles.index', 'label' => __('Prüfprofile'), 'icon' => 'checklist', 'modal' => false, 'matches' => ['asset-compliance.profiles.*']],
                    ['route' => 'asset-compliance.schedules.index', 'label' => __('Prüfkalender'), 'icon' => 'event_available', 'modal' => false, 'matches' => ['asset-compliance.schedules.*']],
                    ['route' => 'asset-compliance.reports.index', 'label' => __('Auditbericht'), 'icon' => 'query_stats', 'modal' => false, 'matches' => ['asset-compliance.reports.*']],
                ],
            ];
        }
        if (Gate::allows('viewAny', \App\Models\Crisis\CrisisCase::class)) {
            $sidebarSections[] = [
                'key' => 'crisis',
                'label' => __('Krisenmanagement'),
                'collapsible' => true,
                'items' => [
                    ['route' => 'crisis.index', 'label' => __('Krisenakten'), 'icon' => 'emergency_home', 'modal' => false, 'matches' => ['crisis.index', 'crisis.show']],
                    ['route' => 'crisis.exercises.index', 'label' => __('Übungen'), 'icon' => 'model_training', 'modal' => false, 'matches' => ['crisis.exercises.*']],
                ],
            ];
        }
        // Datenschutz: nur Rolle `datenschutz` (NICHT automatisch Admins); Pro+.
        if (
            Gate::allows('viewAny', \App\Models\Privacy\ProcessingActivity::class)
            || Gate::allows('viewAny', \App\Models\Privacy\DataSubjectRequest::class)
        ) {
            $sidebarSections[] = [
                'key' => 'datenschutz',
                'label' => __('Datenschutz'),
                'collapsible' => true,
                'groups' => [
                    [
                        'key' => 'datenschutz-records',
                        'label' => __('Verzeichnisse'),
                        'icon' => 'fact_check',
                        'items' => [
                            ['route' => 'dataprotection.activities.index', 'label' => __('Verarbeitungstätigkeiten'), 'icon' => 'fact_check', 'modal' => false, 'matches' => ['dataprotection.activities.*']],
                            ['route' => 'dataprotection.processors.index', 'label' => __('Dienstleister & AVV'), 'icon' => 'handshake', 'modal' => false, 'matches' => ['dataprotection.processors.*', 'dataprotection.agreements.*']],
                            ['route' => 'dataprotection.gvv.index', 'label' => __('Gemeinsame Verantwortlichkeit'), 'icon' => 'diversity_3', 'modal' => false, 'matches' => ['dataprotection.gvv.*']],
                            ['route' => 'dataprotection.tom.index', 'label' => __('TOM-Katalog'), 'icon' => 'shield_lock', 'modal' => false, 'matches' => ['dataprotection.tom.*']],
                        ],
                    ],
                    [
                        'key' => 'datenschutz-cases',
                        'label' => __('Vorfälle & Prüfung'),
                        'icon' => 'gpp_maybe',
                        'items' => [
                            ['route' => 'dataprotection.requests.index', 'label' => __('Betroffenenanfragen'), 'icon' => 'contact_mail', 'modal' => false, 'matches' => ['dataprotection.requests.*']],
                            ['route' => 'dataprotection.incidents.index', 'label' => __('Datenschutzvorfälle'), 'icon' => 'gpp_maybe', 'modal' => false, 'matches' => ['dataprotection.incidents.*']],
                            ['route' => 'dataprotection.compliance.index', 'label' => __('Lückenanalyse'), 'icon' => 'rule', 'modal' => false, 'matches' => ['dataprotection.compliance.*']],
                        ],
                    ],
                ],
            ];
        }
        // ISMS (Feature 044): admin + Geschäftsführung; nur Enterprise (module.isms).
        if (Gate::allows('viewAny', \App\Models\Isms\IsmsRisk::class)) {
            $sidebarSections[] = [
                'key' => 'isms',
                'label' => __('isms.title.section'),
                'collapsible' => true,
                'groups' => [
                    [
                        'key' => 'isms-governance',
                        'label' => __('Steuerung'),
                        'icon' => 'monitoring',
                        'items' => [
                            ['route' => 'isms.dashboard', 'label' => __('isms.title.dashboard'), 'icon' => 'monitoring', 'modal' => false, 'matches' => ['isms.dashboard']],
                            ['route' => 'isms.readiness', 'label' => __('isms.title.readiness'), 'icon' => 'speed', 'modal' => false, 'matches' => ['isms.readiness']],
                            ['route' => 'isms.requirements.index', 'label' => __('isms.title.requirements'), 'icon' => 'checklist', 'modal' => false, 'matches' => ['isms.requirements.*', 'isms.statements.*']],
                            ['route' => 'isms.csf', 'label' => __('isms.title.csf'), 'icon' => 'radar', 'modal' => false, 'matches' => ['isms.csf', 'isms.csf.*']],
                            ['route' => 'isms.controls.index', 'label' => __('isms.title.controls'), 'icon' => 'verified_user', 'modal' => false, 'matches' => ['isms.controls.*']],
                            ['route' => 'isms.risks.index', 'label' => __('isms.title.risks'), 'icon' => 'warning_amber', 'modal' => false, 'matches' => ['isms.risks.*']],
                        ],
                    ],
                    [
                        'key' => 'isms-operations',
                        'label' => __('Betrieb'),
                        'icon' => 'report',
                        'items' => [
                            ['route' => 'isms.incidents.index', 'label' => __('isms.title.incidents'), 'icon' => 'report', 'modal' => false, 'matches' => ['isms.incidents.*']],
                            ['route' => 'isms.vulnerabilities.index', 'label' => __('isms.title.vulnerabilities'), 'icon' => 'bug_report', 'modal' => false, 'matches' => ['isms.vulnerabilities.*', 'isms.advisories.*']],
                            ['route' => 'isms.software.index', 'label' => __('isms.title.software'), 'icon' => 'apps', 'modal' => false, 'matches' => ['isms.software.*']],
                        ],
                    ],
                    [
                        'key' => 'isms-audit',
                        'label' => __('Lieferanten & Audit'),
                        'icon' => 'handshake',
                        'items' => $this->compactItems([
                            ['route' => 'isms.suppliers.index', 'label' => __('isms.title.suppliers'), 'icon' => 'handshake', 'modal' => false, 'matches' => ['isms.suppliers.*']],
                            ['route' => 'isms.conformity.index', 'label' => __('isms.title.conformity'), 'icon' => 'workspace_premium', 'modal' => false, 'matches' => ['isms.conformity.*']],
                            ['route' => 'isms.audits.index', 'label' => __('isms.title.audits'), 'icon' => 'fact_check', 'modal' => false, 'matches' => ['isms.audits.*']],
                            ['route' => 'isms.reviews.index', 'label' => __('isms.title.reviews'), 'icon' => 'grading', 'modal' => false, 'matches' => ['isms.reviews.*']],
                            ['route' => 'isms.packages.index', 'label' => __('isms.title.packages'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['isms.packages.*']],
                            ['route' => 'isms.soa', 'label' => __('isms.title.soa'), 'icon' => 'rule_folder', 'modal' => true, 'matches' => ['isms.soa']],
                            // Geltungsbereiche: nur isms.manage (IsmsScopePolicy).
                            Gate::allows('viewAny', \App\Models\Isms\IsmsScope::class)
                                ? ['route' => 'isms.scopes.index', 'label' => __('isms.title.scopes'), 'icon' => 'travel_explore', 'modal' => false, 'matches' => ['isms.scopes.*']]
                                : null,
                        ]),
                    ],
                ],
            ];
        }
        $sidebarSections[] = [
            'key' => 'reports',
            'label' => __('Auswertungen'),
            'collapsible' => true,
            'groups' => [
                [
                    // Landing (Feature 002): KPIs + gruppierter Einstieg; speist auch reports.index selbst.
                    'key' => 'reports-overview',
                    'label' => __('Übersicht'),
                    'icon' => 'dashboard',
                    'items' => [
                        ['route' => 'reports.index', 'label' => __('Übersicht'), 'icon' => 'dashboard', 'modal' => false, 'matches' => ['reports.index']],
                    ],
                ],
                [
                    'key' => 'reports-personal',
                    'label' => __('Persönlich'),
                    'icon' => 'person',
                    'items' => [
                        ['route' => 'reports.my-month', 'label' => __('Mein Monat'), 'icon' => 'calendar_view_week', 'modal' => false, 'matches' => ['reports.my-month']],
                        ['route' => 'reports.my-year', 'label' => __('Mein Jahr'), 'icon' => 'calendar_view_month', 'modal' => false, 'matches' => ['reports.my-year']],
                        ['route' => 'reports.work-balance', 'label' => __('Arbeitsbilanz'), 'icon' => 'balance', 'modal' => false, 'matches' => ['reports.work-balance']],
                        ['route' => 'reports.attendance', 'label' => __('Anwesenheit'), 'icon' => 'co_present', 'modal' => false, 'matches' => ['reports.attendance']],
                        // Plan/Ist (MVP-018) war bisher nur per URL erreichbar; Tab-Leiste der Seite führt zu Team/Org/Dimensionen.
                        ['route' => 'reports.plan-ist.presence', 'label' => __('Plan/Ist'), 'icon' => 'schedule', 'modal' => false, 'matches' => ['reports.plan-ist.*']],
                    ],
                ],
                [
                    'key' => 'reports-team',
                    'label' => __('Team'),
                    'icon' => 'groups',
                    'items' => $this->compactItems([
                        ['route' => 'reports.week-by-user', 'label' => __('Woche pro Mitarbeiter'), 'icon' => 'date_range', 'modal' => false, 'matches' => ['reports.week-by-user']],
                        ['route' => 'reports.month-by-user-team', 'label' => __('Monat pro Mitarbeiter'), 'icon' => 'calendar_view_month', 'modal' => false, 'matches' => ['reports.month-by-user-team']],
                        // Auslastung & Realisierung (MVP-467): Seite prüft viewAny(User)/Admin.
                        ['route' => 'reports.utilization', 'label' => __('Auslastung'), 'icon' => 'speed', 'modal' => false, 'matches' => ['reports.utilization']],
                        ['route' => 'reports.coverage', 'label' => __('Coverage'), 'icon' => 'group_work', 'modal' => false, 'matches' => ['reports.coverage']],
                        // MVP-518: Notfall-Anwesenheitsliste — eigene Berechtigung, kein Modul-Gate.
                        $user?->can(Permission::ReportPresenceEmergency->value)
                            ? ['route' => 'reports.presence-emergency', 'label' => __('reporting.presence_emergency.nav'), 'icon' => 'emergency_home', 'modal' => false, 'matches' => ['reports.presence-emergency']]
                            : null,
                        // MVP-533: Zuschlags-Prognose auf geplante Dienste.
                        ($user?->isAdmin() || $user?->can(Permission::ReportView->value))
                            ? ['route' => 'reports.surcharge-forecast', 'label' => __('reporting.surcharge_forecast.nav'), 'icon' => 'query_stats', 'modal' => false, 'matches' => ['reports.surcharge-forecast']]
                            : null,
                        ['route' => 'reports.absences', 'label' => __('Urlaub & Flex'), 'icon' => 'event_busy', 'modal' => false, 'matches' => ['reports.absences']],
                        // MVP-520: Ganzjahres-Urlaubsplan + Fehlzeitenkarte.
                        ['route' => 'reports.absence-calendar', 'label' => __('Urlaubsplan'), 'icon' => 'calendar_month', 'modal' => false, 'matches' => ['reports.absence-calendar']],
                        // MVP-526: Zeitkonten-Auswertung (Anfangsstand/Umsatz/Endstand).
                        ['route' => 'reports.time-accounts', 'label' => __('Zeitkonten'), 'icon' => 'account_balance', 'modal' => false, 'matches' => ['reports.time-accounts']],
                        // MVP-540: Periodenvergleich (Q1 S. 114) — Umsätze je KW/Monat nebeneinander.
                        ['route' => 'reports.time-account-comparison', 'label' => __('Periodenvergleich'), 'icon' => 'view_week', 'modal' => false, 'matches' => ['reports.time-account-comparison']],
                        // MVP-529: benannte, teilbare Report-Ansichten.
                        ['route' => 'report-views.index', 'label' => __('Gespeicherte Auswertungen'), 'icon' => 'bookmark', 'modal' => false, 'matches' => ['report-views.*']],
                        ['route' => 'reports.sickness', 'label' => __('Krankheiten'), 'icon' => 'sick', 'modal' => false, 'matches' => ['reports.sickness']],
                        ['route' => 'reports.qualifications', 'label' => __('Qualifikationen'), 'icon' => 'verified', 'modal' => false, 'matches' => ['reports.qualifications']],
                        // Feature 002: Kohortenvergleich vor/nach Fortbildung — org-weite Personaldaten → nur report.view/Admin.
                        ($user?->isAdmin() || $user?->can(Permission::ReportView->value))
                            ? ['route' => 'reports.cohort-comparison', 'label' => __('reporting.cohort.nav'), 'icon' => 'compare_arrows', 'modal' => false, 'matches' => ['reports.cohort-comparison']]
                            : null,
                        $user?->can(Permission::SafetyViewAny->value)
                            ? ['route' => 'reports.safety', 'label' => __('safety.report.nav'), 'icon' => 'health_and_safety', 'modal' => false, 'matches' => ['reports.safety']]
                            : null,
                    ]),
                ],
                [
                    'key' => 'reports-projects',
                    'label' => __('Projekte & Kunden'),
                    'icon' => 'folder_special',
                    'items' => $this->compactItems([
                        ['route' => 'reports.customers', 'label' => __('Kundenanalyse'), 'icon' => 'bar_chart', 'modal' => false, 'matches' => ['reports.customers']],
                        // Kundenwert/Kundenbindung (MVP-465/466): Erlösdaten → nur report.view/Admin.
                        ($user?->isAdmin() || $user?->can(Permission::ReportView->value))
                            ? ['route' => 'reports.customer-value', 'label' => __('Kundenwert'), 'icon' => 'diamond', 'modal' => false, 'matches' => ['reports.customer-value']]
                            : null,
                        ($user?->isAdmin() || $user?->can(Permission::ReportView->value))
                            ? ['route' => 'reports.customer-retention', 'label' => __('Kundenbindung'), 'icon' => 'favorite', 'modal' => false, 'matches' => ['reports.customer-retention']]
                            : null,
                        ['route' => 'reports.entry-types', 'label' => __('Auftragstypanalyse'), 'icon' => 'stacked_bar_chart', 'modal' => false, 'matches' => ['reports.entry-types']],
                        ['route' => 'reports.assets', 'label' => __('Produktanalyse'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['reports.assets']],
                        ['route' => 'reports.customer-project', 'label' => __('Kunden & Projekte'), 'icon' => 'pie_chart', 'modal' => false, 'matches' => ['reports.customer-project']],
                        // MVP-514 P3: aufgeteilte Zeit je Dimension (Feature 103).
                        ($user?->isAdmin() || $user?->can(Permission::ReportView->value))
                            ? ['route' => 'reports.allocations', 'label' => __('reporting.allocations.nav'), 'icon' => 'call_split', 'modal' => false, 'matches' => ['reports.allocations']]
                            : null,
                        ['route' => 'reports.project-details', 'label' => __('Projekt-Details'), 'icon' => 'analytics', 'modal' => false, 'matches' => ['reports.project-details']],
                        ['route' => 'reports.project-inactive', 'label' => __('Inaktive Projekte'), 'icon' => 'folder_off', 'modal' => false, 'matches' => ['reports.project-inactive']],
                        ['route' => 'reports.operations', 'label' => __('Operations'), 'icon' => 'assignment', 'modal' => false, 'matches' => ['reports.operations']],
                        // SLA-Report (Feature 010): nur für SLA-Berechtigte.
                        $user?->can(Permission::SlaViewAny->value)
                            ? ['route' => 'reports.sla', 'label' => __('sla.report.nav'), 'icon' => 'timer', 'modal' => false, 'matches' => ['reports.sla', 'reports.sla.*']]
                            : null,
                        // SLA-Verträge (Feature 010): read-only Detailseite, eigenes Recht.
                        $user?->can(Permission::SlaContractView->value)
                            ? ['route' => 'sla-contracts.index', 'label' => __('SLA-Verträge'), 'icon' => 'gavel', 'modal' => false, 'matches' => ['sla-contracts.index', 'sla-contracts.show']]
                            : null,
                    ]),
                ],
                [
                    'key' => 'reports-resources',
                    'label' => __('Ressourcen'),
                    'icon' => 'inventory_2',
                    'items' => [
                        ['route' => 'reports.fleet', 'label' => __('Fuhrpark'), 'icon' => 'directions_car', 'modal' => false, 'matches' => ['reports.fleet']],
                        ['route' => 'reports.materials', 'label' => __('Materialien'), 'icon' => 'inventory', 'modal' => false, 'matches' => ['reports.materials']],
                        ['route' => 'reports.on-call', 'label' => __('Notdienst'), 'icon' => 'notifications_active', 'modal' => false, 'matches' => ['reports.on-call']],
                    ],
                ],
                [
                    'key' => 'reports-finance',
                    'label' => __('Finanzen & Audit'),
                    'icon' => 'request_quote',
                    'items' => $this->compactItems([
                        // Wirtschaftlichkeit/Nachkalkulation (Feature 014): org-weite Finanzdaten → nur report.view-Berechtigte.
                        ($user?->isAdmin() || $user?->can(Permission::ReportView->value))
                            ? ['route' => 'reports.economics', 'label' => __('Wirtschaftlichkeit'), 'icon' => 'trending_up', 'modal' => false, 'matches' => ['reports.economics']]
                            : null,
                        ['route' => 'reports.billing', 'label' => __('Abrechnung'), 'icon' => 'request_quote', 'modal' => false, 'matches' => ['reports.billing']],
                        // Zahlungsverhalten (MVP-468): lokale Rechnungsdaten → nur report.view/Admin.
                        ($user?->isAdmin() || $user?->can(Permission::ReportView->value))
                            ? ['route' => 'reports.payment-behavior', 'label' => __('Zahlungsverhalten'), 'icon' => 'schedule_send', 'modal' => false, 'matches' => ['reports.payment-behavior']]
                            : null,
                        // Lieferantenanalyse (MVP-472): Ausgaben/Beschaffung je Lieferant → Finanzdaten, nur report.view/Admin.
                        ($user?->isAdmin() || $user?->can(Permission::ReportView->value))
                            ? ['route' => 'reports.suppliers', 'label' => __('Lieferantenanalyse'), 'icon' => 'local_shipping', 'modal' => false, 'matches' => ['reports.suppliers']]
                            : null,
                        // Lieferantenwert (MVP-473): RFM/Portfolio je Lieferant → Finanzdaten, nur report.view/Admin.
                        ($user?->isAdmin() || $user?->can(Permission::ReportView->value))
                            ? ['route' => 'reports.supplier-value', 'label' => __('Lieferantenwert'), 'icon' => 'diamond', 'modal' => false, 'matches' => ['reports.supplier-value']]
                            : null,
                        ['route' => 'reports.expenses', 'label' => __('Spesen'), 'icon' => 'receipt_long', 'modal' => false, 'matches' => ['reports.expenses']],
                        // Externe Auszahlungen: sensible Vergütungsdaten → nur für Payroll-Berechtigte.
                        $user?->can(Permission::UserPayrollManage->value)
                            ? ['route' => 'reports.external-payouts', 'label' => __('Externe Auszahlungen'), 'icon' => 'payments', 'modal' => false, 'matches' => ['reports.external-payouts']]
                            : null,
                        // ArbZG-Compliance auf Ist-Arbeitszeit (Feature 006): nur für Compliance-Berechtigte.
                        $user?->can(Permission::ComplianceViewAny->value)
                            ? ['route' => 'reports.arbzg-compliance', 'label' => __('compliance.report.nav'), 'icon' => 'gavel', 'modal' => false, 'matches' => ['reports.arbzg-compliance']]
                            : null,
                        ['route' => 'reports.audit-activity', 'label' => __('Audit-Aktivität'), 'icon' => 'security', 'modal' => false, 'matches' => ['reports.audit-activity']],
                    ]),
                ],
            ],
        ];
        $sidebarSections[] = [
            'key' => 'archive',
            'label' => __('Archiv'),
            'collapsible' => true,
            'items' => [
                ['route' => 'archive.index', 'label' => __('Archiv-Übersicht'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['archive.*']],
            ],
        ];

        return $sidebarSections;
    }

    /**
     * Modul-/Rechte-Filter + Per-User-Ausblendungen auf den Sidebar-Bauplan
     * (identisch zur früheren Blade-Logik; Ausblendungen greifen ZULETZT).
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  list<string>  $hidden
     * @return list<array<string, mixed>>
     */
    public function filterSidebar(array $sections, array $hidden = []): array {
        $moduleByKey = $this->moduleBySectionKey();
        $moduleByItemRoute = $this->moduleByItemRoute();
        $moduleByGroupKey = $this->moduleByGroupKey();

        $sections = array_values(array_filter(
            $sections,
            fn(array $s): bool => (! isset($moduleByKey[$s['key']]) || $this->features->isEnabled($moduleByKey[$s['key']]))
                && ! in_array(self::KEY_SECTION . (string) $s['key'], $hidden, true)
        ));

        $itemVisible = fn(array $it): bool => (! isset($moduleByItemRoute[$it['route']]) || $this->features->isEnabled($moduleByItemRoute[$it['route']]))
            && $this->gate->mayAccess(isset($it['route']) ? (string) $it['route'] : null)
            && ! in_array(self::KEY_ITEM . (string) $it['route'], $hidden, true);

        foreach ($sections as $i => $section) {
            if (! empty($section['items']) && is_array($section['items'])) {
                $sections[$i]['items'] = array_values(array_filter($section['items'], $itemVisible));
            }
            if (! empty($section['groups']) && is_array($section['groups'])) {
                $groups = array_filter(
                    $section['groups'],
                    fn(array $g): bool => (! isset($moduleByGroupKey[$g['key']]) || $this->features->isEnabled($moduleByGroupKey[$g['key']]))
                        && ! in_array(self::KEY_GROUP . (string) $g['key'], $hidden, true)
                );
                foreach ($groups as $gi => $group) {
                    $groups[$gi]['items'] = array_values(array_filter(
                        is_array($group['items'] ?? null) ? $group['items'] : [],
                        $itemVisible
                    ));
                }
                $sections[$i]['groups'] = array_values(array_filter($groups, static fn(array $g): bool => ! empty($g['items'])));
            }
        }

        // Leere Sektionen entfernen (weder Items noch Gruppen uebrig).
        return array_values(array_filter(
            $sections,
            static fn(array $s): bool => ! empty($s['items']) || ! empty($s['groups'])
        ));
    }

    /**
     * Schnellerstellungs-Gruppen („Neu …") VOR Filterung. Die Gruppen tragen
     * die Schlüssel ihrer thematischen Sidebar-Sektionen — wer eine Sektion
     * ausblendet, blendet die zugehörige Erstellgruppe mit aus.
     *
     * @return list<array<string, mixed>>
     */
    public function createGroupsBlueprint(): array {
        return [
            [
                'key' => 'work',
                'label' => __('Tagesgeschäft'),
                'items' => [
                    ['route' => 'diary.create', 'label' => __('Auftrag'), 'icon' => 'assignment'],
                    ['route' => 'time-entries.create', 'label' => __('Zeiteintrag'), 'icon' => 'timer'],
                    ['route' => 'timesheets.create', 'label' => __('Stundenzettel'), 'icon' => 'description'],
                    ['route' => 'admin-time-entries.create', 'label' => __('Verwaltungszeit'), 'icon' => 'schedule'],
                ],
            ],
            [
                'key' => 'plan',
                'label' => __('Planung'),
                'items' => [
                    ['route' => 'duty-plans.create', 'label' => __('Dienstplan'), 'icon' => 'event_available'],
                    ['route' => 'vacations.create', 'label' => __('Urlaub'), 'icon' => 'beach_access'],
                    ['route' => 'events.create', 'label' => __('Veranstaltung'), 'icon' => 'event'],
                    ['route' => 'travel-logs.create', 'label' => __('Fahrtbuch'), 'icon' => 'route'],
                    ['route' => 'expenses.create', 'label' => __('Spese'), 'icon' => 'receipt_long'],
                    ['route' => 'tours.create', 'label' => __('Tour'), 'icon' => 'directions_bus'],
                ],
            ],
            [
                'key' => 'fleet',
                'label' => __('Fuhrpark'),
                'items' => [
                    ['route' => 'vehicles.create', 'label' => __('Fahrzeug'), 'icon' => 'directions_car'],
                    ['route' => 'energy-logs.create', 'label' => __('Tank-/Ladelog'), 'icon' => 'local_gas_station'],
                ],
            ],
            [
                'key' => 'master',
                'label' => __('Stammdaten'),
                'items' => [
                    ['route' => 'customers.create', 'label' => __('Kunde'), 'icon' => 'badge'],
                    ['route' => 'projects.create', 'label' => __('Projekt'), 'icon' => 'folder_special'],
                    ['route' => 'shift-types.create', 'label' => __('Schichttyp'), 'icon' => 'label'],
                    ['route' => 'qualifications.create', 'label' => __('Qualifikation'), 'icon' => 'verified'],
                ],
            ],
        ];
    }

    /**
     * Nicht registrierte Routen + nicht im Plan enthaltene Module entfernen,
     * Per-User-Ausblendungen anwenden, leere Gruppen verwerfen.
     *
     * @param  list<array<string, mixed>>  $groups
     * @param  list<string>  $hidden
     * @return list<array<string, mixed>>
     */
    public function filterCreateGroups(array $groups, array $hidden = []): array {
        $out = [];
        foreach ($groups as $group) {
            if (in_array(self::KEY_CREATE . (string) ($group['key'] ?? ''), $hidden, true)) {
                continue;
            }
            // Ausgeblendete Sidebar-Sektion blendet die gleichnamige Erstellgruppe mit aus ('master' hat keine Sektion).
            if (in_array(self::KEY_SECTION . (string) ($group['key'] ?? ''), $hidden, true)) {
                continue;
            }
            $items = array_values(array_filter(
                is_array($group['items'] ?? null) ? $group['items'] : [],
                fn(array $i): bool => Route::has((string) $i['route']) && $this->gate->allows((string) $i['route'])
            ));
            if ($items === []) {
                continue;
            }
            $group['items'] = $items;
            $out[] = $group;
        }

        return $out;
    }

    /**
     * Arbeitsbereich-Filter auf den (bereits modul-/rechte-/ausblende-gefilterten)
     * Sidebar-Bauplan (Feature 082, MVP-377). Rein kosmetisch, letzter Schritt.
     *
     * `$keep` ist eine Positivliste stabiler Schlüssel (`section:`/`group:`/
     * `item:`). `null` = kein Filter (Arbeitsbereich 'all' oder keiner). Ein
     * gelisteter Sektions-Schlüssel behält die ganze Sektion; ein Gruppen-/Item-
     * Schlüssel behält nur diesen Ausschnitt. Enthält der aktive Fokus keine
     * Referenz auf eine Sektion, verschwindet sie aus der Sidebar (bleibt aber
     * über Suche, Funktionskatalog und „Alle anzeigen" erreichbar).
     *
     * @param  list<array<string, mixed>>  $sections
     * @param  list<string>|null  $keep
     * @return list<array<string, mixed>>
     */
    public function applyFocus(array $sections, ?array $keep): array {
        if ($keep === null) {
            return $sections;
        }
        $set = array_flip($keep);

        $out = [];
        foreach ($sections as $section) {
            $sectionKept = isset($set[self::KEY_SECTION . (string) ($section['key'] ?? '')]);

            if (! empty($section['groups']) && \is_array($section['groups'])) {
                $groups = [];
                foreach ($section['groups'] as $group) {
                    if ($sectionKept || isset($set[self::KEY_GROUP . (string) ($group['key'] ?? '')])) {
                        $groups[] = $group;
                        continue;
                    }
                    $items = array_values(array_filter(
                        \is_array($group['items'] ?? null) ? $group['items'] : [],
                        static fn(array $it): bool => isset($set[self::KEY_ITEM . (string) ($it['route'] ?? '')])
                    ));
                    if ($items !== []) {
                        $group['items'] = $items;
                        $groups[] = $group;
                    }
                }
                if ($groups !== []) {
                    $section['groups'] = $groups;
                    $out[] = $section;
                }
                continue;
            }

            if (! empty($section['items']) && \is_array($section['items'])) {
                if ($sectionKept) {
                    $out[] = $section;
                    continue;
                }
                $items = array_values(array_filter(
                    $section['items'],
                    static fn(array $it): bool => isset($set[self::KEY_ITEM . (string) ($it['route'] ?? '')])
                ));
                if ($items !== []) {
                    $section['items'] = $items;
                    $out[] = $section;
                }
                continue;
            }

            if ($sectionKept) {
                $out[] = $section;
            }
        }

        return $out;
    }

    /**
     * Arbeitsbereich-Filter auf die Schnellerstellungs-Gruppen (MVP-377).
     * Behält eine Gruppe, wenn ihr Schlüssel als Sektion/Erstellgruppe im Fokus
     * gelistet ist; 'master' (Stammdaten) bleibt als übergreifende Gruppe immer.
     * `null` = kein Filter.
     *
     * @param  list<array<string, mixed>>  $groups
     * @param  list<string>|null  $keep
     * @return list<array<string, mixed>>
     */
    public function applyFocusCreateGroups(array $groups, ?array $keep): array {
        if ($keep === null) {
            return $groups;
        }
        $set = array_flip($keep);

        $out = [];
        foreach ($groups as $group) {
            $key = (string) ($group['key'] ?? '');
            if (
                $key === 'master'
                || isset($set[self::KEY_SECTION . $key])
                || isset($set[self::KEY_CREATE . $key])
            ) {
                $out[] = $group;
            }
        }

        return $out;
    }

    /**
     * Anzahl überfälliger Forderungen für das Menü-Badge (Feature 105,
     * MVP-547). Bewusst UNABHÄNGIG vom Header-Zeitraum: eine Rechnung aus dem
     * Vormonat ist genau dann interessant, wenn man gerade nicht auf sie
     * schaut. Gezählt wird nur, was tatsächlich noch offen ist.
     */
    private function overdueDocumentCount(): int {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user === null || $user->organization_id === null) {
            return 0;
        }

        $today = \Illuminate\Support\Carbon::today()->toDateString();

        $invoices = \App\Models\Invoice::query()
            ->whereIn('status', [\App\Models\Invoice::STATUS_ISSUED, \App\Models\Invoice::STATUS_PARTIALLY_PAID])
            ->whereNotNull('due_on')
            ->where('due_on', '<', $today)
            ->count();

        $vouchers = \App\Models\LexofficeVoucher::query()
            ->where('organization_id', $user->organization_id)
            ->where('archived', false)
            ->whereIn('voucher_type', \App\Support\Billing\VoucherTypes::REVENUE)
            ->whereNotIn('voucher_status', ['draft', 'voided', 'paid', 'paidoff'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->count();

        return $invoices + $vouchers;
    }

    /**
     * Hauptnavigation (Header): pro Modus eine Liste mit Routenname + Label.
     *
     * @return list<array<string, mixed>>
     */
    private function mainNavItems(bool $isLegacyMode, string $indexRoute): array {
        return $isLegacyMode
            ? [
                ['route' => 'legacy.diary.week', 'label' => __('Wochenansicht'), 'icon' => 'calendar_view_week', 'modal' => false, 'matches' => ['legacy.diary.week']],
                ['route' => $indexRoute, 'label' => __('Arbeitsliste'), 'icon' => 'list_alt', 'modal' => false, 'matches' => [$indexRoute, 'legacy.oncall.*', 'legacy.notdienst.*']],
                ['route' => 'legacy.callcenter.notdienst', 'label' => __('Zentrale'), 'icon' => 'support_agent', 'modal' => false, 'matches' => ['legacy.callcenter.*', 'legacy.overview.*']],
            ]
            : [
                ['route' => $indexRoute, 'label' => __('Arbeitsliste'), 'icon' => 'list_alt', 'modal' => false, 'matches' => [$indexRoute, 'diary.*']],
                ['route' => 'week.index', 'label' => __('Wochenansicht'), 'icon' => 'calendar_view_week', 'modal' => false, 'matches' => ['week.index']],
                ['route' => 'kanban.index', 'label' => __('Kanban'), 'icon' => 'view_kanban', 'modal' => false, 'matches' => ['kanban.index']],
                ['route' => 'chat.index', 'label' => __('Chat'), 'icon' => 'forum', 'modal' => false, 'matches' => ['chat.*']],
                ['route' => 'duty-plans.index', 'label' => __('Dienstpläne'), 'icon' => 'event_available', 'modal' => false, 'matches' => ['duty-plans.*']],
                ['route' => 'schedule.index', 'label' => __('Schichtplan'), 'icon' => 'schedule', 'modal' => false, 'matches' => ['schedule.*']],
                ['route' => 'timesheets.index', 'label' => __('Stundenzettel'), 'icon' => 'description', 'modal' => false, 'matches' => ['timesheets.*', 'projects.timesheets.*']],
                ['route' => 'customers.index', 'label' => __('Kunden'), 'icon' => 'badge', 'modal' => false, 'matches' => ['customers.*']],
                ['route' => 'customer-queries.index', 'label' => __('customer-query.title'), 'icon' => 'contact_support', 'modal' => false, 'matches' => ['customer-queries.*']],
                ['route' => 'suppliers.index', 'label' => __('Lieferanten'), 'icon' => 'local_shipping', 'modal' => false, 'matches' => ['suppliers.*']],
                ['route' => 'products.index', 'label' => __('products.title.index'), 'icon' => 'category', 'modal' => false, 'matches' => ['products.*']],
                ['route' => 'articles.index', 'label' => __('article.title'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['articles.*']],
                ['route' => 'warehouses.index', 'label' => __('inventory.title'), 'icon' => 'warehouse', 'modal' => false, 'matches' => ['warehouses.*', 'inventory.*']],
                ['route' => 'manufacturing-orders.index', 'label' => __('manufacturing.order.title'), 'icon' => 'precision_manufacturing', 'modal' => false, 'matches' => ['manufacturing-orders.*']],
                ['route' => 'serials.index', 'label' => __('inventory.serial.title'), 'icon' => 'tag', 'modal' => false, 'matches' => ['serials.*']],
                ['route' => 'purchase-orders.index', 'label' => __('procurement.title'), 'icon' => 'shopping_cart', 'modal' => false, 'matches' => ['purchase-orders.*']],
                ['route' => 'supplier-catalogs.index', 'label' => __('procurement.catalog.title'), 'icon' => 'import_export', 'modal' => false, 'matches' => ['supplier-catalogs.*']],
                ['route' => 'b2b-catalog.index', 'label' => __('b2b_catalog.title'), 'icon' => 'storefront', 'modal' => false, 'matches' => ['b2b-catalog.*']],
                ['route' => 'pricing-margin-rules.index', 'label' => __('procurement.margin.title'), 'icon' => 'percent', 'modal' => false, 'matches' => ['pricing-margin-rules.*']],
                ['route' => 'bill-of-quantities.index', 'label' => __('gaeb.title'), 'icon' => 'request_quote', 'modal' => false, 'matches' => ['bill-of-quantities.*']],
                ['route' => 'inventory.scan', 'label' => __('inventory.scan.title'), 'icon' => 'qr_code_scanner', 'modal' => false, 'matches' => ['inventory.scan*']],
                ['route' => 'work-centers.index', 'label' => __('manufacturing.capacity.title'), 'icon' => 'event_available', 'modal' => false, 'matches' => ['work-centers.*']],
                ['route' => 'inventory.lots', 'label' => __('inventory.lot.title'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['inventory.lots*']],
                ['route' => 'inventory.label-templates.index', 'label' => __('inventory.label_template.title'), 'icon' => 'label', 'modal' => false, 'matches' => ['inventory.label-templates.*']],
                ['route' => 'projects.index', 'label' => __('Projekte'), 'icon' => 'folder_special', 'modal' => false, 'matches' => ['projects.*']],
                ['route' => 'billing.feed', 'label' => __('billing.feed.title'), 'icon' => 'receipt_long', 'modal' => false, 'matches' => ['billing.feed', 'invoices.*', 'quotes.*', 'lexoffice.vouchers.*'], 'badge' => $this->overdueDocumentCount()],
                ['route' => 'finance.transfers.index', 'label' => __('finance.title.menu'), 'icon' => 'outbox', 'modal' => false, 'matches' => ['finance.transfers.*']],
                ['route' => 'finance.reconciliation.index', 'label' => __('bank.title.menu'), 'icon' => 'account_balance', 'modal' => false, 'matches' => ['finance.reconciliation.*', 'finance.bank-accounts.*']],
                ['route' => 'lexoffice.articles.index', 'label' => __('Produkte & Leistungen'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['lexoffice.articles.*']],
                ['route' => 'events.index', 'label' => __('Veranstaltungen'), 'icon' => 'event', 'modal' => false, 'matches' => ['events.*']],
                ['route' => 'flex.index', 'label' => __('Arbeitszeitkonto'), 'icon' => 'hourglass_top', 'modal' => false, 'matches' => ['flex.*']],
                ['route' => 'archive.index', 'label' => __('Archiv'), 'icon' => 'inventory_2', 'modal' => false, 'matches' => ['archive.*']],
            ];
    }

    /**
     * Verwaltungs- + Systemmenü (inkl. Plugin-Panels und Badges) — Reihenfolge
     * und Bedingungen unverändert aus der Blade-Vorlage übernommen.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>, 3: list<string>}
     */
    private function headerMenus(User $user, bool $isLegacyMode): array {
        $manageNavItems = [];
        $adminNavItems = [];
        $pluginPanelRoutes = []; // Routen aktiver Plugin-Panels (für Ungruppiert-Ausschluss)
        $pluginPanelItems = [];  // fertige Menü-Items der Plugin-Panels (eigene Systemmenü-Gruppe „Plugins")

        $isLegacyAdmin = LegacyBridge::isLegacyAdmin($user);
        $isGlobalAdmin = $user->isAdmin();
        $isPlatformAdmin = $user->isGlobalAdmin();

        // Zugriff für Legacy-Admins (ID ≤ 3/Namens-Fallback) UND echte App-Admins (Spatie-Rolle),
        // sonst sähe ein frisch angelegter Admin ohne Legacy-ID die Verwaltung nicht.
        $isAppAdmin = $isLegacyAdmin || $isGlobalAdmin;
        if ($isAppAdmin) {
            if ($isLegacyMode) {
                $manageNavItems[] = ['route' => 'legacy.users.index', 'label' => __('Mitarbeiter'), 'icon' => 'group', 'modal' => false];
            }
            if (! $isLegacyMode) {
                $manageNavItems[] = ['route' => 'holidays.index', 'label' => __('Feiertage'), 'icon' => 'celebration', 'modal' => false];
                $manageNavItems[] = ['route' => 'qualifications.index', 'label' => __('Qualifikationen'), 'icon' => 'workspace_premium', 'modal' => false];
                $manageNavItems[] = ['route' => 'event-categories.index', 'label' => __('Veranstaltungs-Kategorien'), 'icon' => 'category', 'modal' => false];
                $manageNavItems[] = ['route' => 'shift-types.index', 'label' => __('Schichttypen'), 'icon' => 'work_history', 'modal' => false];
                $manageNavItems[] = ['route' => 'materials.index', 'label' => __('Materialien'), 'icon' => 'inventory', 'modal' => false];
                $manageNavItems[] = ['route' => 'tags.index', 'label' => __('Tags'), 'icon' => 'label', 'modal' => false];
                if ($isPlatformAdmin) {
                    // Mandantenliste (Cross-Tenant) nur für Plattform-Betreiber.
                    $adminNavItems[] = ['route' => 'admin.organizations.index', 'label' => __('Organisationen'), 'icon' => 'corporate_fare', 'modal' => false];
                } elseif ($user->organization_id !== null) {
                    // Org-lokaler Admin: direkter Einstieg in die EIGENE Org.
                    $adminNavItems[] = ['route' => 'admin.organizations.edit', 'route_params' => [$user->organization_id], 'label' => __('Organisation'), 'icon' => 'corporate_fare', 'modal' => false];
                }
                $adminNavItems[] = ['route' => 'admin.branding.edit', 'label' => __('Branding'), 'icon' => 'palette', 'modal' => false];
                // PDF-Dokumentdesign/CI-Basisdesign (Feature 076, Ausbau #83): war bislang nur per Direkt-URL erreichbar.
                $adminNavItems[] = ['route' => 'admin.document-design.index', 'label' => __('document_design.title'), 'icon' => 'design_services', 'modal' => false, 'matches' => ['admin.document-design.*']];
                $adminNavItems[] = ['route' => 'admin.themes.index', 'label' => __('Themes'), 'icon' => 'format_paint', 'modal' => false, 'matches' => ['admin.themes.*']];
                if (Gate::allows(Permission::OrganizationScopeManage->value)) {
                    $adminNavItems[] = ['route' => 'admin.scope.index', 'label' => __('scope.title.index'), 'icon' => 'tune', 'modal' => false, 'matches' => ['admin.scope.*']];
                    $adminNavItems[] = ['route' => 'admin.workspaces.index', 'label' => __('scope.focus.admin.title'), 'icon' => 'dashboard_customize', 'modal' => false, 'matches' => ['admin.workspaces.*']];
                }
                $adminNavItems[] = ['route' => 'admin.entry-types.index', 'label' => __('Eintragstypen'), 'icon' => 'category', 'modal' => false];
                $adminNavItems[] = ['route' => 'admin.classifications.index', 'label' => __('Klassifikationen'), 'icon' => 'category_search', 'modal' => false];
                $adminNavItems[] = ['route' => 'admin.classification-requirements.index', 'label' => __('Pflichtregeln'), 'icon' => 'rule_settings', 'modal' => false];
                $adminNavItems[] = ['route' => 'admin.branch-profiles.index', 'label' => __('Branchenprofile'), 'icon' => 'storefront', 'modal' => false];
                $adminNavItems[] = ['route' => 'admin.expense-categories.index', 'label' => __('Spesenkategorien'), 'icon' => 'receipt_long', 'modal' => false];
                $adminNavItems[] = ['route' => 'admin.per-diem-rates.index', 'label' => __('Verpflegungspauschalen'), 'icon' => 'restaurant_menu', 'modal' => false];
                $adminNavItems[] = ['route' => 'admin.automations.index', 'label' => __('Automatisierungen'), 'icon' => 'bolt', 'modal' => false];
                if (Gate::allows(Permission::NotificationRuleViewAny->value)) {
                    $adminNavItems[] = ['route' => 'admin.notification-rules.index', 'label' => __('notification.title.rules'), 'icon' => 'notifications_active', 'modal' => false];
                }
                if (Gate::allows(Permission::WebhookViewAny->value)) {
                    $adminNavItems[] = ['route' => 'admin.webhooks.index', 'label' => __('integration.webhook.title.index'), 'icon' => 'webhook', 'modal' => false, 'matches' => ['admin.webhooks.*']];
                }
                if (Gate::allows('viewAny', \App\Models\CloudIntake\CloudDocumentConnection::class)) {
                    $adminNavItems[] = ['route' => 'admin.cloud-intake.index', 'label' => __('cloud_intake.title.index'), 'icon' => 'cloud_download', 'modal' => false, 'matches' => ['admin.cloud-intake.*']];
                }
                // DomainReselling-Verbindungen (Feature 083): gegated via module.domain.
                if (Gate::allows('viewAny', \App\Models\Domain\DomainProviderConnection::class)) {
                    $adminNavItems[] = ['route' => 'admin.domain-provider.index', 'label' => __('domain.title.connections'), 'icon' => 'dns', 'modal' => false, 'matches' => ['admin.domain-provider.*']];
                }
                // KI-Dienste (Feature 025, MVP-400): gegated via module.ai.
                if (Gate::allows('viewAny', \App\Models\Ai\AiProviderConnection::class)) {
                    $adminNavItems[] = ['route' => 'admin.ai.index', 'label' => __('ai.title.connections'), 'icon' => 'smart_toy', 'modal' => false, 'matches' => ['admin.ai.*']];
                }
                // Cloud-Backupziele (Feature 017 Phase 32): nur Plattform-Admin.
                if (Gate::allows('viewAny', \App\Models\Backup\BackupTargetConnection::class)) {
                    $adminNavItems[] = ['route' => 'admin.backup-targets.index', 'label' => __('backup_targets.title'), 'icon' => 'cloud_upload', 'modal' => false, 'matches' => ['admin.backup-targets.*']];
                }
                if (Gate::allows(Permission::SurchargeRuleViewAny->value)) {
                    $adminNavItems[] = ['route' => 'admin.surcharge-rules.index', 'label' => __('surcharge.title.rules'), 'icon' => 'percent', 'modal' => false];
                }
                if (Gate::allows(Permission::CostCenterRuleViewAny->value)) {
                    $adminNavItems[] = ['route' => 'admin.cost-center-rules.index', 'label' => __('costcenter.title.rules'), 'icon' => 'account_balance', 'modal' => false];
                }
                // MVP-514 P2: freie Mandanten-Dimensionen (admin-gebunden wie Terminals).
                if (\Illuminate\Support\Facades\Auth::user()?->isAdmin() === true) {
                    $adminNavItems[] = ['route' => 'admin.time-dimensions.index', 'label' => __('allocation.dimensions.nav'), 'icon' => 'category', 'modal' => false, 'matches' => ['admin.time-dimensions.*']];
                    // MVP-522: Rollpläne (rollierende Dienst-Vorplanung).
                    $adminNavItems[] = ['route' => 'admin.shift-rotations.index', 'label' => __('Rollpläne'), 'icon' => 'event_repeat', 'modal' => false, 'matches' => ['admin.shift-rotations.*']];
                    // MVP-526: Zeitkonten-Verwaltung.
                    $adminNavItems[] = ['route' => 'admin.time-accounts.index', 'label' => __('Zeitkonten'), 'icon' => 'account_balance', 'modal' => false, 'matches' => ['admin.time-accounts.*']];
                    // MVP-528: Änderungsverlauf/Versionsvergleich auf der Audit-Kette.
                    $adminNavItems[] = ['route' => 'admin.audit-diff.index', 'label' => __('Änderungsverlauf'), 'icon' => 'difference', 'modal' => false, 'matches' => ['admin.audit-diff.*']];
                }
                if (Gate::allows(Permission::WageTypeMappingViewAny->value)) {
                    $adminNavItems[] = ['route' => 'admin.wage-type-mappings.index', 'label' => __('wage_types.title.index'), 'icon' => 'badge', 'modal' => false];
                }
                if (Gate::allows(Permission::ReportTargetManage->value)) {
                    $adminNavItems[] = ['route' => 'admin.report-targets.index', 'label' => __('reporting.target.nav'), 'icon' => 'flag', 'modal' => false];
                }
                if (Gate::allows(Permission::FinanceConfig->value)) {
                    $adminNavItems[] = ['route' => 'finance.bank-accounts.index', 'label' => __('bank.title.accounts'), 'icon' => 'account_balance', 'modal' => false];
                    $adminNavItems[] = ['route' => 'admin.text-corrections.index', 'label' => __('textcorrections.title.index'), 'icon' => 'spellcheck', 'modal' => false, 'matches' => ['admin.text-corrections.*']];
                }
                if (Gate::allows(Permission::FormTemplateViewAny->value)) {
                    $adminNavItems[] = ['route' => 'form-templates.index', 'label' => __('form.title.templates'), 'icon' => 'assignment', 'modal' => false];
                }
                if (Gate::allows(Permission::ProcedureTemplateView->value)) {
                    $adminNavItems[] = ['route' => 'procedures.index', 'label' => __('procedure.title.templates'), 'icon' => 'rule', 'modal' => false, 'matches' => ['procedures.*']];
                }
                $adminNavItems[] = ['route' => 'admin.data.index', 'label' => __('Datentransfer'), 'icon' => 'sync_alt', 'modal' => false];
                if ($user->canManageBilling() && Route::has('admin.integration.inbox')) {
                    $iiOrg = $user->organization_id;
                    $iiOpen = $iiOrg !== null
                        ? \App\Models\IntegrationInboxItem::query()
                        ->where('organization_id', $iiOrg)
                        ->where('status', \App\Models\IntegrationInboxItem::STATUS_OPEN)
                        ->count()
                        : 0;
                    $adminNavItems[] = ['route' => 'admin.integration.inbox', 'label' => __('Zuordnungs-Inbox'), 'icon' => 'rule', 'modal' => false, 'matches' => ['admin.integration.*'], 'badge' => $iiOpen];
                }
                if (Route::has('admin.remote-support.pending.index')) {
                    $rsOrg = $user->organization;
                    $rsPending = $rsOrg !== null
                        ? \App\Models\RemotePendingSession::query()
                        ->where('organization_id', $rsOrg->id)
                        ->where('status', \App\Models\RemotePendingSession::STATUS_OPEN)
                        ->count()
                        : 0;
                    $adminNavItems[] = ['route' => 'admin.remote-support.pending.index', 'label' => __('Fernwartung – Inbox'), 'icon' => 'inbox', 'modal' => false, 'badge' => $rsPending];
                }
            }
            if (! $isLegacyMode && Gate::allows('manage-access')) {
                $adminNavItems[] = ['route' => 'admin.access.index', 'label' => __('access.title.hub'), 'icon' => 'admin_panel_settings', 'modal' => false];
            }
            if (! $isLegacyMode) {
                $adminNavItems[] = ['route' => 'audit.index', 'label' => __('Audit-Log'), 'icon' => 'fact_check', 'modal' => false];
                if (Gate::allows('platform.license.view')) {
                    $adminNavItems[] = ['route' => 'admin.license.index', 'label' => __('Lizenz'), 'icon' => 'key', 'modal' => false];
                }
                if (Gate::allows(Permission::MetricsView->value)) {
                    $adminNavItems[] = ['route' => 'admin.metrics.index', 'label' => __('metrics.title.index'), 'icon' => 'monitoring', 'modal' => false];
                    // Feature-004-Restpunkt: Offline entstandene Daten sichtbar machen.
                    $adminNavItems[] = ['route' => 'admin.offline-sync.index', 'label' => __('Offline-Synchronisierung'), 'icon' => 'cloud_sync', 'modal' => false];
                    $adminNavItems[] = ['route' => 'admin.components.index', 'label' => __('isms.components.title'), 'icon' => 'receipt_long', 'modal' => false];
                }
                if (Gate::allows(Permission::SecurityView->value)) {
                    $adminNavItems[] = ['route' => 'admin.security.index', 'label' => __('security.title.index'), 'icon' => 'shield_lock', 'modal' => false];
                }
                if (Gate::allows(Permission::SecuritySessionsView->value)) {
                    $adminNavItems[] = ['route' => 'admin.sessions.index', 'label' => __('sessions.title.index'), 'icon' => 'devices', 'modal' => false, 'matches' => ['admin.sessions.*']];
                }
                // Quelltext-Integrität (095) + Angriffserkennung (096):
                // installationsweit, daher nur für Plattform-Admins sichtbar.
                if (Auth::user()?->isGlobalAdmin() === true) {
                    $adminNavItems[] = ['route' => 'admin.integrity.index', 'label' => __('Quelltext-Integrität'), 'icon' => 'verified_user', 'modal' => false];
                    $adminNavItems[] = ['route' => 'admin.security-events.index', 'label' => __('Angriffserkennung'), 'icon' => 'gpp_bad', 'modal' => false];
                }
                if (Gate::allows(Permission::BackupView->value)) {
                    $adminNavItems[] = ['route' => 'admin.backup.status', 'label' => __('backup.title.status'), 'icon' => 'backup', 'modal' => false];
                }
                if (Gate::allows(Permission::PlatformSchedulerManage->value)) {
                    $adminNavItems[] = ['route' => 'admin.scheduler.index', 'label' => __('scheduler.title.index'), 'icon' => 'schedule', 'modal' => false];
                }
                if (Gate::allows(Permission::PlatformSettingsManage->value)) {
                    $adminNavItems[] = ['route' => 'admin.settings.index', 'label' => __('settingsregistry.title.index'), 'icon' => 'tune', 'modal' => false];
                }
                if (Gate::allows(Permission::PlatformOperationsManage->value)) {
                    $adminNavItems[] = ['route' => 'admin.maintenance-windows.index', 'label' => __('maintenance.window.title'), 'icon' => 'engineering', 'modal' => false];
                }
                // Badge = aktive Aufgaben der Org: gecachter Count (kein Query/Request), Invalidierung via OperationsTask::booted.
                if (Gate::allows(Permission::PlatformOperationsView->value)) {
                    $opsOrg = $user->organization_id;
                    $opsOpen = $opsOrg !== null
                        ? (int) Cache::remember(
                            \App\Models\OperationsTask::navBadgeCacheKey((int) $opsOrg),
                            \App\Models\OperationsTask::NAV_BADGE_TTL,
                            static fn(): int => \App\Models\OperationsTask::query()
                                ->where('organization_id', $opsOrg)
                                ->active()
                                ->count(),
                        )
                        : 0;
                    $adminNavItems[] = ['route' => 'admin.operations.index', 'label' => __('operations.title.index'), 'icon' => 'task_alt', 'modal' => false, 'badge' => $opsOpen];
                }
                if (Gate::allows(Permission::ProblemReportManage->value)) {
                    $adminNavItems[] = ['route' => 'admin.problem-reports.index', 'label' => __('problemreport.title.inbox'), 'icon' => 'flag', 'modal' => false];
                }
                if (Gate::allows(Permission::SupportGrantManage->value)) {
                    $adminNavItems[] = ['route' => 'admin.support.grants.index', 'label' => __('Supportfreigaben'), 'icon' => 'support_agent', 'modal' => false];
                }
                if (Gate::allows('whistleblowing.settings.manage')) {
                    $adminNavItems[] = ['route' => 'whistleblowing.portal.edit', 'label' => __('Meldeportal'), 'icon' => 'campaign', 'modal' => false];
                }
                $adminNavItems[] = ['route' => 'admin.plugins.index', 'label' => __('Plugins'), 'icon' => 'extension', 'modal' => false];
                // Zähler offener Plugin-Fehler (Review 2026-08, W4c/E5) — 60 s
                // gecacht, org-gescopet (eigene Org + globale Fehler).
                $peOrg = (int) (auth()->user()->organization_id ?? 0);
                $peOpen = (int) Cache::remember(
                    'nav-badge:plugin-errors:' . $peOrg,
                    60,
                    static fn(): int => \App\Models\PluginError::query()
                        ->whereNull('acknowledged_at')
                        ->where(static function ($q) use ($peOrg): void {
                            $q->whereNull('organization_id')->orWhere('organization_id', $peOrg);
                        })
                        ->count(),
                );
                $adminNavItems[] = ['route' => 'admin.plugin-errors.index', 'label' => __('Plugin-Fehler'), 'icon' => 'bug_report', 'modal' => false, 'badge' => $peOpen];

                // Aktive Plugins mit eigenem Admin-Panel dynamisch ins Systemmenü („Plugins").
                foreach (app(PluginManager::class)->enabled() as $plugin) {
                    $panel = $plugin->adminPanel();
                    if ($panel === null || empty($panel['route'])) {
                        continue;
                    }
                    $routeDef = Route::getRoutes()->getByName((string) $panel['route']);
                    if ($routeDef === null) {
                        continue; // Route (noch) nicht registriert – Plugin liefert sie ggf. erst bei Aktivierung
                    }
                    // admin.plugins.edit/{plugin}: Parameter mit Plugin-ID füllen, sonst wirft route() beim Rendern.
                    $params = count($routeDef->parameterNames()) > 0 ? [$plugin->id()] : [];
                    $item = [
                        'route' => (string) $panel['route'],
                        'route_params' => $params,
                        'label' => $panel['label'] ?? $plugin->name(),
                        'icon' => $panel['icon'] ?? 'extension',
                        // admin.plugins.edit rendert nur das Settings-Modal-Fragment → als Modal-Trigger öffnen.
                        'modal' => $panel['route'] === 'admin.plugins.edit',
                    ];
                    $adminNavItems[] = $item;
                    $pluginPanelItems[] = $item;
                    $pluginPanelRoutes[] = (string) $panel['route'];
                }
            }
            $adminNavItems[] = ['route' => 'admin.legacy-migration.index', 'label' => __('Legacy-Migration'), 'icon' => 'sync_alt', 'modal' => false];
        }
        if (! $isLegacyMode && (Gate::allows('manage-members') || $user->can(Permission::UserPayrollManage->value))) {
            // Admin ODER Personalverwaltung/GF (Personal-/Lohndaten + Arbeitszeit-Modell).
            $manageNavItems[] = ['route' => 'org.members.index', 'label' => __('Mitarbeiter'), 'icon' => 'group', 'modal' => false];
        } elseif (! $isLegacyMode && $isPlatformAdmin) {
            // Plattform-Betreiber ohne Org-Kontext: Link auf Mandanten-Verwaltung zur Zuordnung.
            $manageNavItems[] = ['route' => 'admin.organizations.index', 'label' => __('Mitarbeiter'), 'icon' => 'group', 'modal' => false];
        }
        if (! $isLegacyMode && $user->can(Permission::TeamViewAny->value)) {
            $manageNavItems[] = ['route' => 'teams.index', 'label' => __('Teams'), 'icon' => 'groups', 'modal' => false];
        }
        if (! $isLegacyMode && $user->can(Permission::UserPayrollManage->value)) {
            $manageNavItems[] = ['route' => 'payroll.index', 'label' => __('Lohn & SV'), 'icon' => 'payments', 'modal' => false];
        }
        if (! $isLegacyMode) {
            $manageNavItems[] = ['route' => 'activity-categories.index', 'label' => __('Tätigkeitskategorien'), 'icon' => 'category', 'modal' => false];
        }

        return [$adminNavItems, $manageNavItems, $pluginPanelItems, $pluginPanelRoutes];
    }

    /**
     * Benutzermenü (Profil-Dropdown).
     *
     * @return list<array<string, mixed>>
     */
    private function userNavItems(bool $isLegacyMode): array {
        $userNavItems = [];
        if (! $isLegacyMode) {
            $userNavItems[] = ['route' => 'account.profile.edit', 'label' => __('Profil bearbeiten'), 'modal' => true];
            $userNavItems[] = ['route' => 'account.work-schedule', 'label' => __('Arbeitszeit-Modell'), 'modal' => true];
            $userNavItems[] = ['route' => 'account.calendar.show', 'label' => __('Kalender-Abo'), 'modal' => false];
        } else {
            $userNavItems[] = ['route' => 'legacy.account.password.edit', 'label' => __('Passwort ändern'), 'modal' => true];
        }
        // Authentifizierungsbezogene Punkte in einem Untermenü bündeln.
        $userNavItems[] = [
            'label' => __('Authentifizierungen'),
            'children' => [
                ['route' => 'account.2fa.show', 'label' => __('Zwei-Faktor-Authentifizierung'), 'modal' => false],
                ['route' => 'profile.api-tokens.index', 'label' => __('API-Tokens'), 'modal' => false],
            ],
        ];

        return $userNavItems;
    }
}
