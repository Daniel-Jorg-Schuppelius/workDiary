<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglUserMappingService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Services;

use App\Models\{Customer, ExternalReference, ExternalReferenceAlias, ForeignCustomer, IntegrationInboxItem, Organization, Project, User};
use App\Plugins\Toggl\Sources\TogglApiClient;
use App\Plugins\Toggl\{TogglConfig, TogglImportService, TogglOptionBuilder, TogglPlugin};
use App\Support\Sqid;
use Illuminate\Support\Collection;

/**
 * Mapping-Verwaltung des Toggl-Plugins (aus dem TogglController extrahiert,
 * Vollscan 2026-08-23 B12): stellt die View-Daten der Zuordnungsseite
 * zusammen (Primär-Referenzen + Alias-Zeilen, unaufgelöste Toggl-Adressen)
 * und führt die Mutationen an Referenzen/Aliassen aus. Org-/Plugin-Grenze
 * wird hier erzwungen (404 bei Fremdzugriff, 403 bei fremdem Ziel).
 */
class TogglUserMappingService {
    public function __construct(
        private readonly TogglImportService $import,
        private readonly TogglOptionBuilder $options,
    ) {}

    /**
     * Kompletter View-Datensatz der Mapping-Seite.
     *
     * @return array{
     *     mappings: Collection<int, ExternalReference>,
     *     customers: array<int, array{sqid: string, id: int, label: string}>,
     *     projects: array<int, array{sqid: string, customer_id: int|null, name: string}>,
     *     foreignCustomers: array<int, array{sqid: string, customer_id: int, name: string}>,
     *     users: array<int, array{sqid: string, label: string}>,
     *     togglEmails: array<int, array{email: string, name: string}>,
     *     allTogglEmailsMapped: bool,
     *     userMappings: Collection<int, object>
     * }
     */
    public function viewData(?Organization $organization): array {
        $mappings = $organization instanceof Organization ? $this->import->mappings($organization) : collect();

        // Benutzer-Zuordnungen vereint aus Primär-Referenzen UND Aliassen —
        // Zweitadressen desselben Benutzers liegen wegen extref_unique im Alias.
        $userMappings = collect();
        if ($organization instanceof Organization) {
            foreach ($mappings->where('external_type', TogglImportService::EXT_TYPE_USER_EMAIL) as $ref) {
                $userMappings->push((object) ['sqid' => $ref->sqid, 'source' => 'ref', 'email' => (string) $ref->external_id, 'user' => $ref->referenceable]);
            }
            $aliasRows = ExternalReferenceAlias::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', TogglPlugin::ID)
                ->where('external_type', TogglImportService::EXT_TYPE_USER_EMAIL)
                ->with('referenceable')
                ->get();
            foreach ($aliasRows as $alias) {
                $userMappings->push((object) ['sqid' => $alias->sqid, 'source' => 'alias', 'email' => (string) $alias->external_id, 'user' => $alias->referenceable]);
            }
            $userMappings = $userMappings->sortBy('email')->values();
        }

        // Dropdown nur mit UNaufgelösten Toggl-Adressen: Bereits zugeordnete
        // (oder direkt per E-Mail matchende) verschwinden aus der Auswahl.
        $togglEmails = [];
        $allMapped = false;
        if ($organization instanceof Organization) {
            $known = $this->knownTogglEmails($organization);
            $togglEmails = array_values(array_filter(
                $known,
                fn (array $tu): bool => $this->import->resolveImportUser($organization, $tu['email']) === null,
            ));
            $allMapped = $known !== [] && $togglEmails === [];
        }

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'company']);
        // Inkl. kundenloser (interner) Projekte, damit eine Name-Zuordnung auch auf ein
        // unternehmenseigenes Projekt zeigen kann.
        $projects = Project::query()
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id']);
        // Client-Zuordnungen können auch auf Fremdkunden (Endkunden) zeigen.
        $foreignCustomers = ForeignCustomer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id'])
            ->map(fn(ForeignCustomer $fc): array => [
                'sqid' => $fc->sqid,
                'customer_id' => (int) $fc->customer_id,
                'name' => (string) $fc->name,
            ])->all();

        return [
            'mappings' => $mappings,
            'customers' => $this->options->customerOptions($customers),
            'projects' => $this->options->projectOptions($projects),
            'foreignCustomers' => $foreignCustomers,
            'users' => $this->options->userSelectOptions(),
            'togglEmails' => $togglEmails,
            'allTogglEmailsMapped' => $allMapped,
            'userMappings' => $userMappings,
        ];
    }

    /**
     * Bekannte Toggl-Benutzer für die Zuordnungs-Auswahl (statt Freitext):
     * Workspace-Benutzer der API (falls Token konfiguriert) plus E-Mails aus
     * offenen Inbox-Snapshots (CSV-Quelle). Dedupe per lower(email).
     *
     * @return array<int, array{email: string, name: string}>
     */
    private function knownTogglEmails(Organization $organization): array {
        $bucket = [];

        $config = TogglConfig::resolve($organization->id);
        if ($config['enabled'] && $config['api_token'] !== null) {
            try {
                $client = TogglApiClient::fromConfig($config);
                foreach ($client->workspaces() as $workspace) {
                    $this->options->collectTogglUsers($bucket, $client->workspaceUsers((int) $workspace['id']));
                }
            } catch (\Throwable) {
                // API nicht erreichbar → nur Snapshot-Quellen anbieten.
            }
        }

        // E-Mails aus offenen Zeit-Import-Snapshots (deckt reine CSV-Importe ab).
        $snapshots = IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->whereNotNull('group_key')
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('remote_snapshot');
        foreach ($snapshots as $snap) {
            $email = trim((string) (((array) $snap)['user_email'] ?? ''));
            if ($email !== '') {
                $this->options->collectTogglUsers($bucket, [['email' => $email, 'name' => $email]]);
            }
        }

        return $this->options->sortTogglUsers($bucket);
    }

    /**
     * Legt eine Benutzer-Zuordnung an: Toggl-E-Mail → Benutzer der Organisation.
     * Für Mitarbeiter, deren Toggl-Adresse von der workDiary-Adresse abweicht —
     * greift in CSV-/API-Import, Inbox-Buchung und Reparatur-Befehl.
     */
    public function storeUserMapping(Organization $organization, string $togglEmail, mixed $rawUser): void {
        $this->import->rememberUserEmail($organization, $togglEmail, $this->targetUser($organization, $rawUser));
    }

    /** Zeigt eine Alias-Benutzer-Zuordnung (Zweitadresse) auf einen anderen Benutzer um. */
    public function updateUserAliasMapping(Organization $organization, int $aliasId, mixed $rawTarget): void {
        $row = $this->findUserAlias($organization, $aliasId);
        $user = $this->targetUser($organization, $rawTarget);

        $row->update([
            'referenceable_type' => $user->getMorphClass(),
            'referenceable_id' => $user->getKey(),
        ]);
    }

    /** Löscht eine Alias-Benutzer-Zuordnung (Zweitadresse). */
    public function deleteUserAliasMapping(Organization $organization, int $aliasId): void {
        $this->findUserAlias($organization, $aliasId)->delete();
    }

    /** Zeigt eine gemerkte Zuordnung auf einen anderen Kunden/ein anderes Projekt um. */
    public function updateMapping(Organization $organization, int $referenceId, mixed $rawTarget): void {
        $ref = $this->findMapping($organization, $referenceId);

        if ($ref->external_type === TogglImportService::EXT_TYPE_USER_EMAIL) {
            $user = $this->targetUser($organization, $rawTarget);
            $ref->update([
                'referenceable_type' => $user->getMorphClass(),
                'referenceable_id' => $user->getKey(),
                'synced_at' => now(),
            ]);

            return;
        }

        if ($ref->external_type === TogglImportService::EXT_TYPE_CLIENT) {
            // Client-Ziel kann Kunde oder Fremdkunde (Endkunde) sein — die Sqids
            // sind modell-spezifisch, daher nacheinander dekodieren.
            $raw = (string) $rawTarget;
            $foreignId = Sqid::decode(ForeignCustomer::class, $raw);
            $target = $foreignId !== null
                ? ForeignCustomer::query()->whereKey($foreignId)->firstOrFail()
                : Customer::query()->whereKey($this->options->decodeId(Customer::class, $raw))->firstOrFail();
        } else {
            $target = Project::query()->whereKey($this->options->decodeId(Project::class, $rawTarget))->firstOrFail();
        }
        abort_unless((int) $target->organization_id === (int) $organization->id, 403);

        $ref->update([
            'referenceable_type' => $target->getMorphClass(),
            'referenceable_id' => $target->getKey(),
            'synced_at' => now(),
        ]);
    }

    /** Löscht eine gemerkte Zuordnung (künftige Importe matchen dann nicht mehr automatisch). */
    public function deleteMapping(Organization $organization, int $referenceId): void {
        $this->findMapping($organization, $referenceId)->delete();
    }

    /** Ziel-Benutzer aus der Formularauswahl auflösen und auf die Organisation begrenzen (403). */
    private function targetUser(Organization $organization, mixed $raw): User {
        $user = User::query()->whereKey($this->options->decodeId(User::class, $raw))->firstOrFail();
        abort_unless((int) $user->organization_id === (int) $organization->id, 403);

        return $user;
    }

    private function findUserAlias(Organization $organization, int $alias): ExternalReferenceAlias {
        return ExternalReferenceAlias::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', TogglPlugin::ID)
            ->where('external_type', TogglImportService::EXT_TYPE_USER_EMAIL)
            ->whereKey($alias)
            ->firstOrFail();
    }

    /** Lädt eine Toggl-Mapping-Reference der Organisation oder bricht mit 404 ab. */
    private function findMapping(Organization $organization, int $id): ExternalReference {
        return ExternalReference::query()
            ->forPlugin($organization->id, TogglPlugin::ID)
            ->whereIn('external_type', [TogglImportService::EXT_TYPE_CLIENT, TogglImportService::EXT_TYPE_PROJECT])
            ->whereKey($id)
            ->firstOrFail();
    }
}
