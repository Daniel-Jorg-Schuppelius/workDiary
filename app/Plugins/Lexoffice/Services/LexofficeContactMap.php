<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeContactMap.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Lexoffice\Services;

use App\Models\{Customer, ExternalReference, Organization};
use App\Models\Reselling\ResaleSubscription;
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Support\Collection;

/**
 * Lexoffice-Kontakt ↔ Kunde (Feature 152, Review 2026-09-10): die eine Quelle
 * für „welche Kontakte gehören zum Rechnungsempfänger" — Vorschlagslauf,
 * Abgleich, Rechnungslisten und Dialoge fragen hier statt je eigene
 * `ExternalReference`-Abfrage zu halten. Ein Kunde kann mehrere Kontakte
 * haben, ein Kontakt gehört höchstens einem Kunden.
 */
final class LexofficeContactMap {
    /** @var array<int, list<string>> Kunde → Kontakte */
    private array $byCustomer = [];

    /**
     * @param  array<string, int>  $byContact  Kontakt → Kunde
     */
    private function __construct(private readonly array $byContact) {
        foreach ($byContact as $contact => $customerId) {
            $this->byCustomer[$customerId][] = (string) $contact;
        }
    }

    /**
     * Alle Kontakt-Verknüpfungen der Organisation — mit Abo-Liste nur die
     * der Rechnungsempfänger dieser Abos (Kunde bzw. Partner des Fremdkunden).
     *
     * @param  Collection<int, ResaleSubscription>|null  $subscriptions
     */
    public static function forOrganization(Organization $organization, ?Collection $subscriptions = null): self {
        if ($subscriptions === null) {
            return self::load($organization->id, null);
        }
        $customerIds = [];
        foreach ($subscriptions as $subscription) {
            $billedTo = $subscription->billedTo();
            if ($billedTo !== null) {
                $customerIds[$billedTo->id] = true;
            }
        }

        return self::load($organization->id, array_keys($customerIds));
    }

    /** Nur die Kontakte eines Rechnungsempfängers. */
    public static function forCustomer(Customer $customer): self {
        return self::load((int) $customer->organization_id, [$customer->id]);
    }

    /**
     * @param  list<int>|null  $customerIds  null = alle Kunden der Organisation
     */
    private static function load(int $organizationId, ?array $customerIds): self {
        if ($customerIds === []) {
            return new self([]);
        }
        $query = ExternalReference::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('external_type', LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', (new Customer)->getMorphClass());
        if ($customerIds !== null) {
            $query->whereIn('referenceable_id', $customerIds);
        }
        $byContact = [];
        foreach ($query->get(['referenceable_id', 'external_id']) as $reference) {
            $byContact[(string) $reference->external_id] = (int) $reference->referenceable_id;
        }

        return new self($byContact);
    }

    /**
     * Lexoffice-Kontakte eines Kunden.
     *
     * @return list<string>
     */
    public function byCustomer(int $customerId): array {
        return $this->byCustomer[$customerId] ?? [];
    }

    /** Kunde zu einem Lexoffice-Kontakt, null ohne Verknüpfung. */
    public function byContact(string $externalId): ?int {
        return $this->byContact[$externalId] ?? null;
    }

    /**
     * Alle Verknüpfungen: Kontakt → Kunde.
     *
     * @return array<string, int>
     */
    public function all(): array {
        return $this->byContact;
    }

    /**
     * Alle Verknüpfungen gruppiert: Kunde → Kontakte.
     *
     * @return array<int, list<string>>
     */
    public function customers(): array {
        return $this->byCustomer;
    }
}
