<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglExportImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl;

use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, ExternalReference, ExternalReferenceAlias, ForeignCustomer, Organization, Project, TimeEntry, User};
use App\Plugins\Support\AttachesImportedTags;
use App\Plugins\Toggl\Sources\{FolderWorkspaceSource, TogglEntry, TogglWorkspaceReader, WorkspaceSourceInterface};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Einmal-Migration eines vollständigen Toggl-Workspace-Exports
 * ({@see TogglWorkspaceReader}) in workDiary.
 *
 * Zwei Modi pro Workspace-Ordner:
 *  - 'own': Der eigene Workspace. Toggl-Clients → Kunden, Toggl-Projekte →
 *    Projekte (unter dem jeweiligen Kunden).
 *  - 'customer': Der Workspace IST ein Kunde. Es wird genau ein Kunde
 *    (= konfigurierter Name) angelegt/wiederverwendet. Jeder interne Toggl-Client
 *    (= Endkunde der Firma) wird als {@see ForeignCustomer} unter diesem Kunden
 *    angelegt; die Toggl-Projekte hängen unter dem Kunden und verweisen per
 *    `foreign_customer_id` auf ihren Endkunden — so bleibt die Endkunden-Trennung
 *    erhalten (Abrechnung/Auswertung je Endkunde), ohne Projektnamen-Präfixe.
 *
 * Kunden/Projekte werden zuerst über ihre {@see ExternalReference}/Aliase
 * aufgelöst (überlebt Merges in workDiary), dann per Name dedupliziert;
 * Benutzer per E-Mail. Zeiteinträge sind über eine
 * {@see ExternalReference} (entry-Key) idempotent. Mit `dryRun` wird alles in
 * einer Transaktion ausgeführt und am Ende zurückgerollt — liefert exakte
 * „Würde anlegen/buchen"-Zahlen ohne zu schreiben.
 */
class TogglExportImporter {
    use AttachesImportedTags;

    public const MODE_SKIP = 'skip';

    public const MODE_OWN = 'own';

    public const MODE_CUSTOMER = 'customer';

    public const USER_PER_EMAIL = 'per_email';

    /** Wie {@see USER_PER_EMAIL}, aber unbekannte E-Mails legen ausdrücklich neue Benutzer an. */
    public const USER_PER_EMAIL_CREATE = 'per_email_create';

    public const USER_SINGLE = 'single';

    /** ExternalReference-Typ für gemerkte Endkunden-Zuordnungen (Toggl-Client → ForeignCustomer). */
    public const EXT_TYPE_FOREIGN_CLIENT = 'foreign_client';

    /** @var array<string, Customer> lower(name) → Customer (Lauf-Cache) */
    private array $customerCache = [];

    /** @var array<string, ForeignCustomer> "customerId|lower(name)" → ForeignCustomer (Lauf-Cache) */
    private array $foreignCustomerCache = [];

    /** @var array<string, User> lower(email) → User (Lauf-Cache) */
    private array $userCache = [];

    /** @var array<string, true> lower(email) → nicht auflösbar (Lauf-Cache, spart Wiederholungs-Queries) */
    private array $unresolvedCache = [];

    /** @var array<string, int> lower(email) → vorgegebene User-ID (explizite Zuordnung aus der UI) */
    private array $userMap = [];

    private ?User $defaultUser = null;

    public function __construct(private readonly TogglWorkspaceReader $reader = new TogglWorkspaceReader) {}

    /**
     * Import aus Workspace-Export-Ordnern auf der Platte.
     *
     * @param  array<string, array{mode: string, customer_id?: int|string|null, customer_name?: ?string}>  $workspaceModes  Ordnername → Konfiguration
     * @param  array<string, int>  $userMap  lower(email) → User-ID (explizite Benutzer-Zuordnung; Vorrang vor $userMode)
     * @return array{dry_run: bool, workspaces: array<int, array<string, mixed>>, totals: array<string, mixed>, user_mode: string, single_user_name: ?string}
     */
    public function import(string $basePath, Organization $organization, array $workspaceModes, string $userMode, bool $dryRun, array $userMap = []): array {
        $basePath = rtrim($basePath, '/');
        $sources = [];
        foreach ($workspaceModes as $folder => $config) {
            if ($config['mode'] === self::MODE_SKIP) {
                continue;
            }
            $path = $basePath . '/' . $folder;
            if (! is_dir($path)) {
                continue;
            }
            $sources[(string) $folder] = new FolderWorkspaceSource($path, $this->reader);
        }

        return $this->run($organization, $sources, $workspaceModes, $userMode, $dryRun, $userMap);
    }

    /**
     * Import aus bereits gebundenen Workspace-Quellen (z. B. der Toggl-API).
     *
     * @param  array<string, WorkspaceSourceInterface>  $sources  Label → Quelle
     * @param  array<string, array{mode: string, customer_id?: int|string|null, customer_name?: ?string}>  $workspaceModes  Label → Konfiguration
     * @param  array<string, int>  $userMap  lower(email) → User-ID (explizite Benutzer-Zuordnung; Vorrang vor $userMode)
     * @return array{dry_run: bool, workspaces: array<int, array<string, mixed>>, totals: array<string, mixed>, user_mode: string, single_user_name: ?string}
     */
    public function importFromApi(Organization $organization, array $sources, array $workspaceModes, string $userMode, bool $dryRun, array $userMap = []): array {
        return $this->run($organization, $sources, $workspaceModes, $userMode, $dryRun, $userMap);
    }

    /**
     * Gemeinsamer Importkern: verarbeitet die gebundenen Quellen gemäß ihrer
     * Modus-Konfiguration in einer Transaktion (mit Dry-Run-Rollback).
     *
     * @param  array<string, WorkspaceSourceInterface>  $sources  Label → Quelle
     * @param  array<string, array{mode: string, customer_id?: int|string|null, customer_name?: ?string}>  $workspaceModes  Label → Konfiguration
     * @param  array<string, int>  $userMap  lower(email) → User-ID (explizite Benutzer-Zuordnung; Vorrang vor $userMode)
     * @return array{dry_run: bool, workspaces: array<int, array<string, mixed>>, totals: array<string, mixed>, user_mode: string, single_user_name: ?string}
     */
    private function run(Organization $organization, array $sources, array $workspaceModes, string $userMode, bool $dryRun, array $userMap = []): array {
        $this->customerCache = [];
        $this->foreignCustomerCache = [];
        $this->userCache = [];
        $this->unresolvedCache = [];
        $this->userMap = $userMap;
        $this->defaultUser = null;

        $workspaces = [];

        DB::beginTransaction();
        try {
            foreach ($sources as $label => $source) {
                $config = $workspaceModes[$label] ?? ['mode' => self::MODE_SKIP];
                $mode = $config['mode'];
                if ($mode === self::MODE_SKIP) {
                    continue;
                }

                $stats = $this->newStats();
                $stats['workspace'] = $label;
                $stats['mode'] = $mode;

                if ($mode === self::MODE_OWN) {
                    $this->importOwn($organization, $source, $userMode, $stats);
                } else {
                    $customer = $this->resolveTargetCustomer($organization, $config, (string) $label, $stats);
                    $stats['customer'] = $customer->name;
                    $this->importCustomer($organization, $source, $customer, $userMode, $stats);
                }

                $workspaces[] = $stats;
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return [
            'dry_run' => $dryRun,
            'workspaces' => $workspaces,
            'totals' => $this->sumTotals($workspaces),
            // Einbenutzer-Modus muss in Vorschau UND Ergebnis deutlich sichtbar
            // sein (MVP-509) — inkl. des tatsächlichen Buchungsziels.
            'user_mode' => $userMode,
            'single_user_name' => $userMode === self::USER_SINGLE ? $this->defaultUser($organization)->name : null,
        ];
    }

    /**
     * Eigener Workspace: Clients → Kunden, Projekte → Projekte (unter ihrem Client).
     *
     * @param  array<string, mixed>  $stats
     */
    private function importOwn(Organization $organization, WorkspaceSourceInterface $source, string $userMode, array &$stats): void {
        foreach ($source->clients() as $client) {
            $customer = $this->findOrCreateCustomer($organization, $client['name'], $client['archived'], $stats);
            $this->rememberReference($organization, TogglImportService::EXT_TYPE_CLIENT, $client['name'], $customer);
        }

        foreach ($source->users() as $u) {
            $this->resolveUser($organization, $u['email'], $u['name'], $userMode, $stats);
        }

        // projectKey(clientName, projectName) → Project, für die Eintrag-Zuordnung.
        $projectMap = [];
        foreach ($source->projects() as $p) {
            $customer = $this->findOrCreateCustomer($organization, (string) ($p['client_name'] ?? ''), false, $stats);
            $project = $this->findOrCreateProject($organization, $customer, $p['name'], $p, $stats, clientName: $p['client_name'] ?? null);
            $projectMap[$this->key($p['client_name'], $p['name'])] = $project;
            $this->rememberReference($organization, TogglImportService::EXT_TYPE_PROJECT, $this->key($p['client_name'], $p['name']), $project);
        }

        foreach ($source->entries() as $entry) {
            $project = $projectMap[$this->key($entry->clientName, $entry->projectName)] ?? null;
            if ($project === null) {
                // Eintrag verweist auf (gelöschtes) Projekt/Client → on-the-fly anlegen.
                $customer = $this->findOrCreateCustomer($organization, (string) $entry->clientName, false, $stats);
                $project = $this->findOrCreateProject($organization, $customer, (string) ($entry->projectName ?: __('Ohne Projekt')), [], $stats, clientName: $entry->clientName);
                $projectMap[$this->key($entry->clientName, $entry->projectName)] = $project;
            }
            $this->bookEntry($organization, $project, $entry, $userMode, $stats);
        }
    }

    /**
     * Ermittelt den Zielkunden für einen Kunden-Workspace: entweder einen
     * explizit gewählten bestehenden Kunden (`customer_id`, scoped auf die
     * Organisation) oder — als Fallback — einen per Name angelegten/gefundenen
     * Kunden (`customer_name`, sonst Workspace-Label). So lässt sich aus der UI
     * sowohl „bestehenden Kunden wählen" als auch „neuen Kunden anlegen" abbilden.
     *
     * @param  array{mode: string, customer_id?: int|string|null, customer_name?: ?string}  $config
     * @param  array<string, mixed>  $stats
     */
    private function resolveTargetCustomer(Organization $organization, array $config, string $label, array &$stats): Customer {
        $customerId = $config['customer_id'] ?? null;
        if ($customerId !== null && $customerId !== '') {
            $existing = Customer::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereKey((int) $customerId)
                ->first();

            if ($existing instanceof Customer) {
                $stats['customers_reused']++;
                $this->customerCache[mb_strtolower((string) $existing->name)] = $existing;

                return $existing;
            }
        }

        $name = trim((string) ($config['customer_name'] ?? $label)) ?: $label;

        return $this->findOrCreateCustomer($organization, $name, false, $stats);
    }

    /**
     * Kunden-Workspace: genau ein Kunde (= die Firma, gewählt/angelegt via
     * {@see resolveTargetCustomer()}). Jeder interne Toggl-Client wird als
     * Fremdkunde (Endkunde) unter dem Kunden angelegt; die Projekte verweisen
     * per `foreign_customer_id` auf ihren Endkunden. Keine Projektnamen-Präfixe —
     * gleichnamige Projekte verschiedener Endkunden bleiben durch die Verknüpfung
     * getrennt.
     *
     * @param  array<string, mixed>  $stats
     */
    private function importCustomer(Organization $organization, WorkspaceSourceInterface $source, Customer $customer, string $userMode, array &$stats): void {
        $this->rememberReference($organization, TogglImportService::EXT_TYPE_CLIENT, $customer->name, $customer);

        foreach ($source->users() as $u) {
            $this->resolveUser($organization, $u['email'], $u['name'], $userMode, $stats);
        }

        // key(clientName, projectName) → Project, getrennt je Endkunde (Fremdkunde).
        $projectMap = [];
        foreach ($source->projects() as $p) {
            $foreign = $this->findOrCreateForeignCustomer($organization, $customer, $p['client_name'], $stats);
            $project = $this->findOrCreateProject($organization, $customer, $p['name'], $p, $stats, $foreign, clientName: $p['client_name'] ?? null);
            $projectMap[$this->key($p['client_name'], $p['name'])] = $project;
            $this->rememberReference($organization, TogglImportService::EXT_TYPE_PROJECT, $this->key($p['client_name'], $p['name']), $project);
        }

        foreach ($source->entries() as $entry) {
            $project = $projectMap[$this->key($entry->clientName, $entry->projectName)] ?? null;
            if ($project === null) {
                $foreign = $this->findOrCreateForeignCustomer($organization, $customer, $entry->clientName, $stats);
                $project = $this->findOrCreateProject($organization, $customer, (string) ($entry->projectName ?: __('Ohne Projekt')), [], $stats, $foreign, clientName: $entry->clientName);
                $projectMap[$this->key($entry->clientName, $entry->projectName)] = $project;
            }
            $this->bookEntry($organization, $project, $entry, $userMode, $stats);
        }
    }

    /**
     * Findet/erstellt einen Fremdkunden (Endkunde) unter der Firma. Dedupe per
     * (customer_id, lower(name)). Leerer Client-Name → null (Projekt hängt dann
     * direkt unter der Firma ohne Endkunde).
     *
     * @param  array<string, mixed>  $stats
     */
    private function findOrCreateForeignCustomer(Organization $organization, Customer $customer, ?string $name, array &$stats): ?ForeignCustomer {
        $name = trim((string) $name);
        // Interner Client = Firma selbst → kein eigener Endkunde.
        if ($name === '' || mb_strtolower($name) === mb_strtolower((string) $customer->name)) {
            return null;
        }

        $cacheKey = $customer->id . '|' . mb_strtolower($name);
        if (isset($this->foreignCustomerCache[$cacheKey])) {
            return $this->foreignCustomerCache[$cacheKey];
        }

        $existing = ForeignCustomer::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $customer->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing instanceof ForeignCustomer) {
            $stats['foreign_customers_reused']++;

            return $this->foreignCustomerCache[$cacheKey] = $existing;
        }

        $foreign = ForeignCustomer::query()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'name' => $name,
        ]);
        $stats['foreign_customers_created']++;
        $this->rememberReference($organization, self::EXT_TYPE_FOREIGN_CLIENT, $this->key($customer->name, $name), $foreign);

        return $this->foreignCustomerCache[$cacheKey] = $foreign;
    }

    /** @param array<string, mixed> $stats */
    private function findOrCreateCustomer(Organization $organization, string $name, bool $archived, array &$stats): Customer {
        $name = trim($name);
        if ($name === '') {
            $name = (string) __('Ohne Kunde');
        }
        $cacheKey = mb_strtolower($name);

        if (isset($this->customerCache[$cacheKey])) {
            return $this->customerCache[$cacheKey];
        }

        // Referenz/Alias zuerst: Nach einem Kunden-Merge zeigt der Toggl-Client-
        // Schlüssel aufs Merge-Ziel — die Namenssuche würde das gelöschte
        // Duplikat sonst neu anlegen.
        $referenced = $this->resolveReference($organization, TogglImportService::EXT_TYPE_CLIENT, $name);
        if ($referenced instanceof Customer) {
            $stats['customers_reused']++;

            return $this->customerCache[$cacheKey] = $referenced;
        }

        $existing = Customer::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(name) = ?', [$cacheKey])
            ->first();

        if ($existing instanceof Customer) {
            $stats['customers_reused']++;

            return $this->customerCache[$cacheKey] = $existing;
        }

        $customer = Customer::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'archived_at' => $archived ? now() : null,
        ]);
        $stats['customers_created']++;

        return $this->customerCache[$cacheKey] = $customer;
    }

    /**
     * @param  array<string, mixed>  $meta  Toggl-Projektmetadaten (color, billable, active, start_date)
     * @param  array<string, mixed>  $stats
     */
    private function findOrCreateProject(Organization $organization, Customer $customer, string $name, array $meta, array &$stats, ?ForeignCustomer $foreignCustomer = null, ?string $clientName = null): Project {
        $name = trim($name) ?: (string) __('Ohne Projekt');

        // Referenz/Alias zuerst (Schlüssel „client|projekt"): Nach einem
        // Projekt-Merge zeigt der Schlüssel aufs Merge-Ziel — die Namens-/
        // Endkunden-Suche darunter würde das gelöschte Duplikat sonst neu anlegen.
        $referenced = $this->resolveReference($organization, TogglImportService::EXT_TYPE_PROJECT, $this->key($clientName, $name));
        if ($referenced instanceof Project) {
            $stats['projects_reused']++;

            return $referenced;
        }

        // Dedupe pro (Kunde, Endkunde, Name): gleichnamige Projekte verschiedener
        // Endkunden sind eigenständig.
        $existing = Project::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('customer_id', $customer->id)
            ->where('foreign_customer_id', $foreignCustomer?->id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing instanceof Project) {
            $stats['projects_reused']++;

            return $existing;
        }

        $project = Project::query()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customer->id,
            'foreign_customer_id' => $foreignCustomer?->id,
            'name' => $name,
            'color' => $meta['color'] ?? null,
            // Toggl liefert billable für jedes Projekt (Free-Plan: immer false,
            // das Flag ist dort Premium) — nur ein echtes true ist ein Signal,
            // sonst erben (null) statt hartem „nicht abrechenbar".
            'billable' => ($meta['billable'] ?? false) ? true : null,
            'status' => (($meta['active'] ?? true) ? ProjectStatus::Active : ProjectStatus::Archived)->value,
            'starts_on' => $meta['start_date'] ?? null,
            'is_default' => false,
        ]);
        $stats['projects_created']++;

        return $project;
    }

    /**
     * Verbindliche Auflösungsreihenfolge (MVP-509): explizite UI-Zuordnung →
     * Einbenutzer-Modus (ausdrücklich gewählt, konfigurierter Standard-Benutzer)
     * → gespeicherte user_email-Zuordnung (inkl. Alias) → aktiver interner
     * Benutzer mit gleicher E-Mail → nur im Modus {@see USER_PER_EMAIL_CREATE}
     * Neuanlage. Sonst null — der Eintrag wird sichtbar zur Zuordnung gestellt,
     * NIE still auf den Organisationsinhaber gebucht.
     *
     * @param  array<string, mixed>  $stats
     */
    private function resolveUser(Organization $organization, ?string $email, ?string $name, string $userMode, array &$stats): ?User {
        $email = trim((string) $email);
        $cacheKey = mb_strtolower($email);

        if ($cacheKey !== '' && isset($this->userCache[$cacheKey])) {
            return $this->userCache[$cacheKey];
        }

        // Explizite Zuordnung aus der UI (Toggl-E-Mail → bestimmter Benutzer) hat
        // immer Vorrang — auch vor dem Einzelbenutzer-Modus.
        if ($cacheKey !== '' && isset($this->userMap[$cacheKey])) {
            $mapped = $this->loadOrgUser($organization, (int) $this->userMap[$cacheKey]);
            if ($mapped instanceof User) {
                // Explizite Zuordnung persistieren — damit greift sie auch für
                // künftige CSV-/API-Importe mit abweichender Toggl-Adresse.
                $this->rememberReference($organization, TogglImportService::EXT_TYPE_USER_EMAIL, $cacheKey, $mapped);

                return $this->userCache[$cacheKey] = $mapped;
            }
        }

        // Nur der ausdrücklich gewählte Einbenutzer-Modus darf auf den
        // konfigurierten Standard-Benutzer buchen.
        if ($userMode === self::USER_SINGLE) {
            return $this->defaultUser($organization);
        }

        // Ohne E-Mail-Signal gibt es außerhalb des Einbenutzer-Modus kein
        // Buchungsziel; bekannte Fehlschläge nicht erneut abfragen.
        if ($cacheKey === '' || isset($this->unresolvedCache[$cacheKey])) {
            return null;
        }

        // Gespeicherte Zuordnung (user_email-Referenz inkl. Merge-Alias) — wie
        // der laufende CSV-/API-Import.
        $byRef = $this->resolveReference($organization, TogglImportService::EXT_TYPE_USER_EMAIL, $cacheKey);
        if ($byRef instanceof User && $byRef->customer_id === null && ! $byRef->isDeactivated()) {
            return $this->userCache[$cacheKey] = $byRef;
        }

        $existing = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('customer_id')
            ->whereNull('deactivated_at')
            ->whereRaw('LOWER(email) = ?', [$cacheKey])
            ->first();

        if ($existing instanceof User) {
            return $this->userCache[$cacheKey] = $existing;
        }

        if ($userMode !== self::USER_PER_EMAIL_CREATE) {
            $this->unresolvedCache[$cacheKey] = true;

            return null;
        }

        // Vollaudit 2026-07 (H8): Lizenz-Nutzerlimit gilt auch für den Import
        // (Abbruch mit klarer Meldung statt stiller Limit-Überschreitung).
        app(\App\Services\Licensing\LimitGuard::class)->ensureCanCreateUser($organization);

        $user = User::query()->create([
            'organization_id' => $organization->id,
            'name' => trim((string) $name) ?: $email,
            'email' => $email,
            'password' => Str::random(40),
            'must_change_password' => true,
            'is_new_system' => true,
        ]);
        $stats['users_created']++;

        return $this->userCache[$cacheKey] = $user;
    }

    /**
     * Lädt einen aktiven internen Benutzer der Zielorganisation (oder null),
     * für explizite Zuordnungen — Portalkonten und deaktivierte ausgeschlossen.
     */
    private function loadOrgUser(Organization $organization, int $userId): ?User {
        return User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('customer_id')
            ->whereNull('deactivated_at')
            ->whereKey($userId)
            ->first();
    }

    /** @param array<string, mixed> $stats */
    private function bookEntry(Organization $organization, Project $project, TogglEntry $entry, string $userMode, array &$stats): void {
        if ($this->alreadyImported($organization, $entry->entryKey)) {
            $stats['entries_skipped']++;

            return;
        }

        // Bestandsimporte vor MVP-509 tragen den CSV-Schlüssel ohne E-Mail —
        // unter dem Alt-Schlüssel bekannte Einträge sind keine Duplikate.
        if ($entry->legacyEntryKey !== null
            && $entry->legacyEntryKey !== $entry->entryKey
            && $this->alreadyImported($organization, $entry->legacyEntryKey)) {
            $stats['entries_skipped']++;

            return;
        }

        $user = $this->resolveUser($organization, $entry->userEmail, null, $userMode, $stats);
        if ($user === null) {
            // MVP-509: sichtbar zur Zuordnung stellen statt still auf den
            // Organisationsinhaber zu buchen — Zuordnung oben pflegen und
            // den (idempotenten) Import erneut ausführen.
            $emailKey = mb_strtolower(trim((string) $entry->userEmail));
            $stats['entries_unresolved_user']++;
            $stats['unresolved_emails'][$emailKey] = (int) ($stats['unresolved_emails'][$emailKey] ?? 0) + 1;

            return;
        }

        $description = trim(implode(' — ', array_filter([
            $entry->projectName,
            $entry->description,
        ]))) ?: (string) __('Toggl-Zeiteintrag');

        $attributes = [
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'date' => $entry->startedAt->toDateString(),
            'started_at' => $entry->startedAt,
            'ended_at' => $entry->endedAt,
            'kind' => TimeEntryKind::Work,
            'description' => $description,
        ];
        if ($entry->billable) {
            // Nur echtes true ist ein Signal (Free-Plan liefert immer false) —
            // sonst weglassen → Boot erbt effectiveBillable() des Projekts
            // (Spiegel der Projekt-Regel in findOrCreateProject).
            $attributes['billable'] = true;
        }
        $timeEntry = TimeEntry::query()->create($attributes);

        // Toggl-Tags (nur API-Quelle; der Ordner-Reader liefert keine) additiv anhängen.
        $this->attachImportedTags($organization, $timeEntry, $entry->tags);

        ExternalReference::query()->create([
            'organization_id' => $organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => TogglImportService::EXT_TYPE_ENTRY,
            'referenceable_type' => $timeEntry->getMorphClass(),
            'referenceable_id' => $timeEntry->getKey(),
            'external_id' => $entry->entryKey,
            'payload' => [
                'source' => $entry->source,
                'client' => $entry->clientName,
                'project' => $entry->projectName,
                'user_email' => $entry->userEmail,
            ],
            'synced_at' => now(),
        ]);

        $stats['entries_created']++;
    }

    private function alreadyImported(Organization $organization, string $entryKey): bool {
        return ExternalReference::query()
            ->forPlugin($organization->id, TogglPlugin::ID, TogglImportService::EXT_TYPE_ENTRY)
            ->forExternalId($entryKey)
            ->exists();
    }

    private function rememberReference(Organization $organization, string $type, string $externalId, \Illuminate\Database\Eloquent\Model $referenceable): void {
        $key = [
            'organization_id' => $organization->id,
            'plugin_id' => TogglPlugin::ID,
            'external_type' => $type,
            'external_id' => $externalId,
        ];
        $target = [
            'referenceable_type' => $referenceable->getMorphClass(),
            'referenceable_id' => $referenceable->getKey(),
        ];

        $byKey = ExternalReference::query()
            ->forPlugin($organization->id, TogglPlugin::ID, $type)
            ->forExternalId($externalId)
            ->first();
        if ($byKey !== null) {
            // Umhängen auf ein Ziel, das schon einen anderen Schlüssel trägt,
            // würde extref_unique verletzen → Schlüssel wird unten zum Alias.
            $occupied = ExternalReference::query()
                ->withoutGlobalScopes()
                ->where('plugin_id', TogglPlugin::ID)
                ->where('external_type', $type)
                ->where('referenceable_type', $target['referenceable_type'])
                ->where('referenceable_id', $target['referenceable_id'])
                ->where('id', '!=', $byKey->id)
                ->exists();

            if (! $occupied) {
                $byKey->fill($target + ['synced_at' => now()])->save();

                return;
            }

            $byKey->delete();
        }

        // extref_unique erlaubt nur eine Primär-Referenz je Plugin/Typ/Entität.
        // Trägt die Entität bereits einen anderen Schlüssel (Merge/Umbenennung),
        // wird dieser Schlüssel als Alias gesichert statt zu kollidieren.
        $hasPrimary = ExternalReference::query()
            ->withoutGlobalScopes()
            ->where('plugin_id', TogglPlugin::ID)
            ->where('external_type', $type)
            ->where('referenceable_type', $target['referenceable_type'])
            ->where('referenceable_id', $target['referenceable_id'])
            ->exists();

        if ($hasPrimary) {
            ExternalReferenceAlias::query()->withoutGlobalScopes()->updateOrCreate($key, $target);

            return;
        }

        ExternalReference::query()->create($key + $target + ['synced_at' => now()]);
    }

    /**
     * Löst eine Fremd-ID über die Primär-Referenz und — als Fallback — die
     * Alias-Tabelle (Merge-Weiterleitungen) auf. Analog
     * {@see \App\Plugins\Support\MatchingTimeImportService::resolveByReference()}.
     */
    private function resolveReference(Organization $organization, string $type, string $externalId): ?\Illuminate\Database\Eloquent\Model {
        if ($externalId === '') {
            return null;
        }

        $ref = ExternalReference::query()
            ->forPlugin($organization->id, TogglPlugin::ID, $type)
            ->forExternalId($externalId)
            ->first();

        if ($ref?->referenceable instanceof \Illuminate\Database\Eloquent\Model) {
            return $ref->referenceable;
        }

        return ExternalReferenceAlias::resolveModel($organization->id, TogglPlugin::ID, $type, $externalId);
    }

    /**
     * Buchungsziel des Einbenutzer-Modus: der in den Toggl-Einstellungen
     * konfigurierte Standard-Benutzer (MVP-509), erst danach Org-Owner bzw.
     * erster Org-Benutzer als Fallback.
     */
    private function defaultUser(Organization $organization): User {
        if ($this->defaultUser instanceof User) {
            return $this->defaultUser;
        }

        $config = TogglConfig::resolve((int) $organization->id);
        $user = null;
        if ($config['default_user_id'] !== null) {
            $user = User::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereKey((int) $config['default_user_id'])
                ->first();
        }
        if ($user === null && $organization->owner_id !== null) {
            $user = User::query()->withoutGlobalScopes()->whereKey($organization->owner_id)->first();
        }
        $user ??= User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->first();

        if (! $user instanceof User) {
            throw new \RuntimeException('Kein Benutzer in der Zielorganisation vorhanden.');
        }

        return $this->defaultUser = $user;
    }

    /** Stabiler, case-insensitiver Schlüssel (Client|Projekt). */
    private function key(?string $a, ?string $b): string {
        return mb_strtolower(trim((string) $a) . '|' . trim((string) $b));
    }

    /** @return array<string, mixed> */
    private function newStats(): array {
        return [
            'customers_created' => 0,
            'customers_reused' => 0,
            'foreign_customers_created' => 0,
            'foreign_customers_reused' => 0,
            'projects_created' => 0,
            'projects_reused' => 0,
            'users_created' => 0,
            'entries_created' => 0,
            'entries_skipped' => 0,
            'entries_unresolved_user' => 0,
            // lower(E-Mail) → Anzahl nicht gebuchter Einträge ('' = ohne Signal).
            'unresolved_emails' => [],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $workspaces
     * @return array<string, mixed>
     */
    private function sumTotals(array $workspaces): array {
        $totals = $this->newStats();
        foreach ($workspaces as $w) {
            foreach (array_keys($totals) as $k) {
                if ($k === 'unresolved_emails') {
                    foreach ((array) ($w[$k] ?? []) as $email => $count) {
                        $totals[$k][$email] = (int) ($totals[$k][$email] ?? 0) + (int) $count;
                    }

                    continue;
                }
                $totals[$k] += (int) ($w[$k] ?? 0);
            }
        }

        return $totals;
    }
}
