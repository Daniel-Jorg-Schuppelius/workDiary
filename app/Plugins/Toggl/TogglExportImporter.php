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
use App\Models\{Customer, ExternalReference, ForeignCustomer, Organization, Project, TimeEntry, User};
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
 * Kunden/Projekte/Benutzer werden per Name bzw. E-Mail dedupliziert (bestehende
 * werden wiederverwendet, nicht dupliziert). Zeiteinträge sind über eine
 * {@see ExternalReference} (entry-Key) idempotent. Mit `dryRun` wird alles in
 * einer Transaktion ausgeführt und am Ende zurückgerollt — liefert exakte
 * „Würde anlegen/buchen"-Zahlen ohne zu schreiben.
 */
class TogglExportImporter {
    public const MODE_SKIP = 'skip';

    public const MODE_OWN = 'own';

    public const MODE_CUSTOMER = 'customer';

    public const USER_PER_EMAIL = 'per_email';

    public const USER_SINGLE = 'single';

    /** ExternalReference-Typ für gemerkte Endkunden-Zuordnungen (Toggl-Client → ForeignCustomer). */
    public const EXT_TYPE_FOREIGN_CLIENT = 'foreign_client';

    /** @var array<string, Customer> lower(name) → Customer (Lauf-Cache) */
    private array $customerCache = [];

    /** @var array<string, ForeignCustomer> "customerId|lower(name)" → ForeignCustomer (Lauf-Cache) */
    private array $foreignCustomerCache = [];

    /** @var array<string, User> lower(email) → User (Lauf-Cache) */
    private array $userCache = [];

    private ?User $defaultUser = null;

    public function __construct(private readonly TogglWorkspaceReader $reader = new TogglWorkspaceReader) {}

    /**
     * Import aus Workspace-Export-Ordnern auf der Platte.
     *
     * @param  array<string, array{mode: string, customer_name?: ?string}>  $workspaceModes  Ordnername → Konfiguration
     * @return array{dry_run: bool, workspaces: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function import(string $basePath, Organization $organization, array $workspaceModes, string $userMode, bool $dryRun): array {
        $basePath = rtrim($basePath, '/');
        $sources = [];
        foreach ($workspaceModes as $folder => $config) {
            if (($config['mode'] ?? self::MODE_SKIP) === self::MODE_SKIP) {
                continue;
            }
            $path = $basePath . '/' . $folder;
            if (! is_dir($path)) {
                continue;
            }
            $sources[(string) $folder] = new FolderWorkspaceSource($path, $this->reader);
        }

        return $this->run($organization, $sources, $workspaceModes, $userMode, $dryRun);
    }

    /**
     * Import aus bereits gebundenen Workspace-Quellen (z. B. der Toggl-API).
     *
     * @param  array<string, WorkspaceSourceInterface>  $sources  Label → Quelle
     * @param  array<string, array{mode: string, customer_name?: ?string}>  $workspaceModes  Label → Konfiguration
     * @return array{dry_run: bool, workspaces: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function importFromApi(Organization $organization, array $sources, array $workspaceModes, string $userMode, bool $dryRun): array {
        return $this->run($organization, $sources, $workspaceModes, $userMode, $dryRun);
    }

    /**
     * Gemeinsamer Importkern: verarbeitet die gebundenen Quellen gemäß ihrer
     * Modus-Konfiguration in einer Transaktion (mit Dry-Run-Rollback).
     *
     * @param  array<string, WorkspaceSourceInterface>  $sources  Label → Quelle
     * @param  array<string, array{mode: string, customer_name?: ?string}>  $workspaceModes  Label → Konfiguration
     * @return array{dry_run: bool, workspaces: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    private function run(Organization $organization, array $sources, array $workspaceModes, string $userMode, bool $dryRun): array {
        $this->customerCache = [];
        $this->foreignCustomerCache = [];
        $this->userCache = [];
        $this->defaultUser = null;

        $workspaces = [];

        DB::beginTransaction();
        try {
            foreach ($sources as $label => $source) {
                $config = $workspaceModes[$label] ?? ['mode' => self::MODE_SKIP];
                $mode = $config['mode'] ?? self::MODE_SKIP;
                if ($mode === self::MODE_SKIP) {
                    continue;
                }

                $stats = $this->newStats();
                $stats['workspace'] = $label;
                $stats['mode'] = $mode;

                if ($mode === self::MODE_OWN) {
                    $this->importOwn($organization, $source, $userMode, $stats);
                } else {
                    $customerName = trim((string) ($config['customer_name'] ?? $label)) ?: (string) $label;
                    $stats['customer'] = $customerName;
                    $this->importCustomer($organization, $source, $customerName, $userMode, $stats);
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
            $project = $this->findOrCreateProject($organization, $customer, $p['name'], $p, $stats);
            $projectMap[$this->key($p['client_name'], $p['name'])] = $project;
            $this->rememberReference($organization, TogglImportService::EXT_TYPE_PROJECT, $this->key($p['client_name'], $p['name']), $project);
        }

        foreach ($source->entries() as $entry) {
            $project = $projectMap[$this->key($entry->clientName, $entry->projectName)] ?? null;
            if ($project === null) {
                // Eintrag verweist auf (gelöschtes) Projekt/Client → on-the-fly anlegen.
                $customer = $this->findOrCreateCustomer($organization, (string) $entry->clientName, false, $stats);
                $project = $this->findOrCreateProject($organization, $customer, (string) ($entry->projectName ?: __('Ohne Projekt')), [], $stats);
                $projectMap[$this->key($entry->clientName, $entry->projectName)] = $project;
            }
            $this->bookEntry($organization, $project, $entry, $userMode, $stats);
        }
    }

    /**
     * Kunden-Workspace: genau ein Kunde (= die Firma). Jeder interne Toggl-Client
     * wird als Fremdkunde (Endkunde) unter dem Kunden angelegt; die Projekte
     * verweisen per `foreign_customer_id` auf ihren Endkunden. Keine
     * Projektnamen-Präfixe — gleichnamige Projekte verschiedener Endkunden
     * bleiben durch die Verknüpfung getrennt.
     *
     * @param  array<string, mixed>  $stats
     */
    private function importCustomer(Organization $organization, WorkspaceSourceInterface $source, string $customerName, string $userMode, array &$stats): void {
        $customer = $this->findOrCreateCustomer($organization, $customerName, false, $stats);
        $this->rememberReference($organization, TogglImportService::EXT_TYPE_CLIENT, $customerName, $customer);

        foreach ($source->users() as $u) {
            $this->resolveUser($organization, $u['email'], $u['name'], $userMode, $stats);
        }

        // key(clientName, projectName) → Project, getrennt je Endkunde (Fremdkunde).
        $projectMap = [];
        foreach ($source->projects() as $p) {
            $foreign = $this->findOrCreateForeignCustomer($organization, $customer, $p['client_name'], $stats);
            $project = $this->findOrCreateProject($organization, $customer, $p['name'], $p, $stats, $foreign);
            $projectMap[$this->key($p['client_name'], $p['name'])] = $project;
            $this->rememberReference($organization, TogglImportService::EXT_TYPE_PROJECT, $this->key($p['client_name'], $p['name']), $project);
        }

        foreach ($source->entries() as $entry) {
            $project = $projectMap[$this->key($entry->clientName, $entry->projectName)] ?? null;
            if ($project === null) {
                $foreign = $this->findOrCreateForeignCustomer($organization, $customer, $entry->clientName, $stats);
                $project = $this->findOrCreateProject($organization, $customer, (string) ($entry->projectName ?: __('Ohne Projekt')), [], $stats, $foreign);
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
    private function findOrCreateProject(Organization $organization, Customer $customer, string $name, array $meta, array &$stats, ?ForeignCustomer $foreignCustomer = null): Project {
        $name = trim($name) ?: (string) __('Ohne Projekt');

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
            'billable' => \array_key_exists('billable', $meta) ? (bool) $meta['billable'] : null,
            'status' => (($meta['active'] ?? true) ? ProjectStatus::Active : ProjectStatus::Archived)->value,
            'starts_on' => $meta['start_date'] ?? null,
            'is_default' => false,
        ]);
        $stats['projects_created']++;

        return $project;
    }

    /** @param array<string, mixed> $stats */
    private function resolveUser(Organization $organization, ?string $email, ?string $name, string $userMode, array &$stats): User {
        if ($userMode === self::USER_SINGLE) {
            return $this->defaultUser($organization);
        }

        $email = trim((string) $email);
        if ($email === '') {
            return $this->defaultUser($organization);
        }
        $cacheKey = mb_strtolower($email);

        if (isset($this->userCache[$cacheKey])) {
            return $this->userCache[$cacheKey];
        }

        $existing = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereRaw('LOWER(email) = ?', [$cacheKey])
            ->first();

        if ($existing instanceof User) {
            return $this->userCache[$cacheKey] = $existing;
        }

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

    /** @param array<string, mixed> $stats */
    private function bookEntry(Organization $organization, Project $project, TogglEntry $entry, string $userMode, array &$stats): void {
        if ($this->alreadyImported($organization, $entry->entryKey)) {
            $stats['entries_skipped']++;

            return;
        }

        $user = $this->resolveUser($organization, $entry->userEmail, null, $userMode, $stats);

        $description = trim(implode(' — ', array_filter([
            $entry->projectName,
            $entry->description,
        ]))) ?: (string) __('Toggl-Zeiteintrag');

        $timeEntry = TimeEntry::query()->create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'date' => $entry->startedAt->toDateString(),
            'started_at' => $entry->startedAt,
            'ended_at' => $entry->endedAt,
            'kind' => TimeEntryKind::Work,
            'description' => $description,
            'billable' => $entry->billable,
        ]);

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
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('external_type', TogglImportService::EXT_TYPE_ENTRY)
            ->where('external_id', $entryKey)
            ->exists();
    }

    private function rememberReference(Organization $organization, string $type, string $externalId, \Illuminate\Database\Eloquent\Model $referenceable): void {
        ExternalReference::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'plugin_id' => TogglPlugin::ID,
                'external_type' => $type,
                'external_id' => $externalId,
            ],
            [
                'referenceable_type' => $referenceable->getMorphClass(),
                'referenceable_id' => $referenceable->getKey(),
                'synced_at' => now(),
            ],
        );
    }

    private function defaultUser(Organization $organization): User {
        if ($this->defaultUser instanceof User) {
            return $this->defaultUser;
        }

        $user = null;
        if ($organization->owner_id !== null) {
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
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $workspaces
     * @return array<string, int>
     */
    private function sumTotals(array $workspaces): array {
        $totals = $this->newStats();
        foreach ($workspaces as $w) {
            foreach (array_keys($totals) as $k) {
                $totals[$k] += (int) ($w[$k] ?? 0);
            }
        }

        return $totals;
    }
}
