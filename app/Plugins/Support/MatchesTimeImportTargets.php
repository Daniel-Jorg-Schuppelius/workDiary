<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MatchesTimeImportTargets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\{Customer, ExternalReference, ExternalReferenceAlias, ForeignCustomer, Organization, Project, User};
use App\Services\Integration\ProjectKeywordMatcher;
use Illuminate\Database\Eloquent\Model;

/**
 * Matching-/Vorschlags-Hälfte der {@see MatchingTimeImportService}-Pipeline:
 * Fremd-Kunde/-Projekt/-Benutzer über gemerkte References (inkl. Merge-Alias)
 * und Namensgleichheit auflösen, Fuzzy-Vorschläge für die Zuordnungs-Inbox,
 * Referenz-Gedächtnis. Konstanten (EXT_TYPE_*, SUGGEST_THRESHOLD) liefert die
 * komponierende Klasse.
 */
trait MatchesTimeImportTargets {
    /** Plugin-Id, unter der References abgelegt werden. */
    abstract protected function pluginId(): string;

    public function matchCustomer(Organization $organization, ?string $clientName): ?Customer {
        $clientName = $clientName !== null ? trim($clientName) : '';
        if ($clientName === '') {
            return null;
        }

        $byName = $this->resolveByReference($organization, self::EXT_TYPE_CLIENT, $clientName);
        if ($byName instanceof Customer) {
            return $byName;
        }
        // Client als Fremdkunde (Endkunde) gemerkt → dessen Firma ist der Kunde.
        if ($byName instanceof ForeignCustomer) {
            return $byName->customer;
        }

        return Customer::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where(function ($q) use ($clientName): void {
                $q->whereRaw('LOWER(name) = ?', [mb_strtolower($clientName)])
                    ->orWhereRaw('LOWER(company) = ?', [mb_strtolower($clientName)]);
            })
            ->first();
    }

    public function matchProject(Organization $organization, ImportedTimeEntry $entry): ?Project {
        // API-Quelle: die numerische Fremd-Projekt-ID ist der stabilste Schlüssel
        // (übersteht Umbenennungen im Quellsystem).
        if ($entry->projectId !== null) {
            $byId = $this->resolveByReference($organization, self::EXT_TYPE_PROJECT_ID, (string) $entry->projectId);
            if ($byId instanceof Project) {
                return $byId;
            }
        }

        $projectName = $entry->projectName !== null ? trim($entry->projectName) : '';
        if ($projectName !== '') {
            $byName = $this->resolveByReference($organization, self::EXT_TYPE_PROJECT, $this->projectKey($entry->clientName, $projectName));
            if ($byName instanceof Project) {
                return $byName;
            }
        }

        $client = $this->matchClientForEntry($organization, $entry);

        if ($projectName !== '') {
            $query = Project::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($projectName)]);

            // Fremdkunde (Endkunde): gleichnamige Projekte verschiedener Endkunden
            // derselben Firma bleiben getrennt — daher zusätzlich auf ihn scopen.
            if ($client instanceof ForeignCustomer) {
                $query->where('customer_id', $client->customer_id)->where('foreign_customer_id', $client->id);
            } elseif ($client instanceof Customer) {
                $query->where('customer_id', $client->id);
            }

            $exact = $query->first();
            if ($exact instanceof Project) {
                return $exact;
            }
        }

        // Letzte Stufe (MVP-483): Schlüsselwörter im Text. Greift nur bei
        // erkanntem Kunden und eindeutigem Treffer — sonst bleibt es bei der
        // Zuordnungs-Inbox.
        return app(ProjectKeywordMatcher::class)->match(
            $organization,
            $client,
            (string) $entry->description,
            (string) $entry->activity,
            $projectName,
        )?->project;
    }

    /**
     * Client-Auflösung im Projekt-Name-Fallback: gemerkte Referenz kann auf
     * einen Kunden oder Fremdkunden (Endkunden) zeigen. Hook für Plugins mit
     * zusätzlichen Schlüsseln (Toggl: stabile client_id).
     */
    protected function matchClientForEntry(Organization $organization, ImportedTimeEntry $entry): Customer|ForeignCustomer|null {
        $clientName = $entry->clientName !== null ? trim($entry->clientName) : '';
        if ($clientName !== '') {
            $byName = $this->resolveByReference($organization, self::EXT_TYPE_CLIENT, $clientName);
            if ($byName instanceof Customer || $byName instanceof ForeignCustomer) {
                return $byName;
            }
        }

        return $this->matchCustomer($organization, $entry->clientName);
    }

    protected function resolveByReference(Organization $organization, string $externalType, string $externalId): ?Model {
        if ($externalId === '') {
            return null;
        }

        $ref = ExternalReference::query()
            ->forPlugin($organization, $this->pluginId(), $externalType)
            ->forExternalId($externalId)
            ->first();

        if ($ref?->referenceable instanceof Model) {
            return $ref->referenceable;
        }

        return ExternalReferenceAlias::resolveModel($organization->id, $this->pluginId(), $externalType, $externalId);
    }

    public function suggestCustomer(Organization $organization, ?string $clientName): ?Customer {
        $needle = $this->normalize($clientName);
        if ($needle === '') {
            return null;
        }

        $best = null;
        $bestScore = 0.0;
        foreach (Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNull('archived_at')->get() as $customer) {
            $score = max($this->similarity($needle, $this->normalize($customer->name)), $this->similarity($needle, $this->normalize($customer->company)));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $customer;
            }
        }

        return $bestScore >= self::SUGGEST_THRESHOLD ? $best : null;
    }

    /**
     * Fuzzy-Vorschlag eines Fremdkunden (Endkunden) zum Toggl-/Import-Client:
     * gemerkte Client-Referenz zuerst (exakt), dann Namensähnlichkeit über alle
     * aktiven Fremdkunden der Organisation.
     */
    public function suggestForeignCustomer(Organization $organization, ?string $clientName): ?ForeignCustomer {
        $trimmed = $clientName !== null ? trim($clientName) : '';
        if ($trimmed === '') {
            return null;
        }

        $byReference = $this->resolveByReference($organization, self::EXT_TYPE_CLIENT, $trimmed);
        if ($byReference instanceof ForeignCustomer) {
            return $byReference;
        }

        $needle = $this->normalize($trimmed);
        $best = null;
        $bestScore = 0.0;
        foreach (ForeignCustomer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNull('archived_at')->get() as $foreign) {
            $score = max($this->similarity($needle, $this->normalize($foreign->name)), $this->similarity($needle, $this->normalize($foreign->company)));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $foreign;
            }
        }

        return $bestScore >= self::SUGGEST_THRESHOLD ? $best : null;
    }

    public function suggestProject(Organization $organization, ?Customer $customer, ?string $projectName, ?ForeignCustomer $foreignCustomer = null): ?Project {
        $needle = $this->normalize($projectName);
        if ($needle === '') {
            return null;
        }

        $query = Project::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereNull('archived_at');
        if ($foreignCustomer !== null) {
            $query->where('foreign_customer_id', $foreignCustomer->id);
        } elseif ($customer !== null) {
            $query->where('customer_id', $customer->id);
        }

        $best = null;
        $bestScore = 0.0;
        foreach ($query->get() as $project) {
            $score = $this->similarity($needle, $this->normalize($project->name));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $project;
            }
        }

        return $bestScore >= self::SUGGEST_THRESHOLD ? $best : null;
    }

    protected function rememberReference(Organization $organization, string $type, string $externalId, Model $referenceable): void {
        $key = [
            'organization_id' => $organization->id,
            'plugin_id' => $this->pluginId(),
            'external_type' => $type,
            'external_id' => $externalId,
        ];
        $target = [
            'referenceable_type' => $referenceable->getMorphClass(),
            'referenceable_id' => $referenceable->getKey(),
        ];

        // extref_unique erlaubt je Plugin/Typ nur EINE Primär-Referenz pro
        // Zielmodell. Zeigt bereits ein anderer Schlüssel auf das Ziel (mehrere
        // Toggl-Projekte → ein Projekt, Merge, Umbenennung), wird dieser
        // Schlüssel als Alias gemerkt statt zu kollidieren.
        $targetTaken = ExternalReference::query()
            ->forPlugin($organization, $this->pluginId(), $type)
            ->forReferenceable($referenceable)
            ->where('external_id', '!=', $externalId)
            ->exists();

        if ($targetTaken) {
            // Veraltete Primär-Referenz DIESES Schlüssels (anderes Ziel) entfernen,
            // den Schlüssel als Alias aufs Ziel weiterleiten.
            ExternalReference::query()->withoutGlobalScopes()->where($key)->delete();
            ExternalReferenceAlias::query()->withoutGlobalScopes()->updateOrCreate($key, $target);

            return;
        }

        ExternalReference::query()->updateOrCreate($key, $target + ['synced_at' => now()]);
        // Ein früherer Alias desselben Schlüssels ist durch die Primär-Referenz überholt.
        ExternalReferenceAlias::query()->withoutGlobalScopes()->where($key)->delete();
    }

    /** @var array<string, int|null>  lower(E-Mail) → User-ID (Lauf-Cache) */
    private array $userIdByEmail = [];

    /**
     * Buchungs-Benutzer je Eintrag: die Quell-E-Mail (Toggl-/Kimai-Benutzer)
     * gewinnt, wenn sie aufgelöst werden kann — sonst der übergebene
     * Standard-Benutzer. Kein Auto-Anlegen (Inbox-First-Prinzip).
     */
    protected function resolveEntryUserId(Organization $organization, ?string $email, int $fallbackUserId): int {
        return $this->resolveImportUser($organization, $email) ?? $fallbackUserId;
    }

    /**
     * Benutzer zu einer Quell-E-Mail: gemerkte Zuordnung (user_email-Referenz,
     * für abweichende Toggl-Adressen — UI „Zuordnungen verwalten" bzw.
     * Workspace-Import) vor direkter E-Mail-Gleichheit. Nur aktive interne
     * Benutzer sind Buchungsziel — Portalkonten und deaktivierte Konten lösen
     * nie auf (MVP-509: dann offener Zuordnungsfall statt stiller Buchung).
     * Null, wenn nichts passt.
     */
    public function resolveImportUser(Organization $organization, ?string $email): ?int {
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }

        $key = mb_strtolower($email);
        if (array_key_exists($key, $this->userIdByEmail)) {
            return $this->userIdByEmail[$key];
        }

        $byRef = $this->resolveByReference($organization, self::EXT_TYPE_USER_EMAIL, $key);
        if ($byRef instanceof User) {
            // Explizite Zuordnung auf ein inzwischen deaktiviertes/Portal-Konto:
            // nicht still auf E-Mail-Gleichheit umleiten — offen lassen.
            return $this->userIdByEmail[$key] = ($byRef->customer_id === null && ! $byRef->isDeactivated())
                ? (int) $byRef->id
                : null;
        }

        $user = User::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('customer_id')
            ->whereNull('deactivated_at')
            ->whereRaw('LOWER(email) = ?', [$key])
            ->first();

        return $this->userIdByEmail[$key] = ($user !== null ? (int) $user->id : null);
    }

    /** Merkt eine Quell-E-Mail → Benutzer-Zuordnung (inkl. Alias-Fallback). */
    public function rememberUserEmail(Organization $organization, string $email, User $user): void {
        $key = mb_strtolower(trim($email));
        if ($key === '') {
            return;
        }

        $this->rememberReference($organization, self::EXT_TYPE_USER_EMAIL, $key, $user);
        unset($this->userIdByEmail[$key]);
    }

    /** Stabiler Gruppen-/Referenz-Schlüssel (Kunde|Projekt[|Tätigkeit], case-insensitiv). */
    protected function projectKey(?string $clientName, ?string $projectName, ?string $activity = null): string {
        $parts = [trim((string) $clientName), trim((string) $projectName)];
        if ($activity !== null && trim($activity) !== '') {
            $parts[] = trim($activity);
        }

        return mb_strtolower(implode('|', $parts));
    }

    protected function normalize(?string $value): string {
        return mb_strtolower(\CommonToolkit\Helper\Data\StringHelper::normalizeWhitespace($value));
    }

    protected function similarity(string $a, string $b): float {
        // Toolkit (Vollscan 2026-08-23, B20, common-toolkit v1.26).
        return \CommonToolkit\Helper\Data\StringHelper::similarity($a, $b);
    }
}
