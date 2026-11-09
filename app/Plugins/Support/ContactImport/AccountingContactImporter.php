<?php
/*
 * Created on   : Sun Nov 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingContactImporter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\ContactImport;

use App\Enums\Integration\{ConflictFieldPolicy, ImportMatchPolicy};
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\Organization;
use App\Models\Supplier\Supplier;
use App\Services\Integration\IntegrationResolver;
use App\Services\Integration\Profiles\{CustomerMatchProfile, SupplierMatchProfile};
use App\Services\Stammdaten\Contracts\ContactPushTarget;

/**
 * Kontakt-Rückweg eines Buchhaltungsprogramms (MVP-1502): Führt die
 * Buchhaltung die Stammdaten (`finance.master_data_authority = accounting`),
 * entfällt der Push, und Kunden und Lieferanten kommen aus dem Programm.
 * Jeder Kontakt läuft durch den {@see IntegrationResolver} unter der
 * Kontaktreferenz der Übertragung (`contact`): ein verknüpfter Kontakt
 * übernimmt den Stand des Programms, ein sicherer Treffer wird verknüpft,
 * alles Unklare wird Klärfall in der Integrations-Inbox. Angelegt wird nie.
 */
abstract class AccountingContactImporter {
    public function __construct(
        private readonly IntegrationResolver $resolver,
        private readonly ContactPushTarget $contacts,
    ) {}

    abstract public function pluginId(): string;

    /** Zugang hinterlegt und Plugin für die Organisation eingeschaltet. */
    abstract public function isConfigured(int $organizationId): bool;

    /** @return iterable<RemoteContact> alle Kontakte des Programms */
    abstract protected function remoteContacts(int $organizationId): iterable;

    /** Nach dem Verknüpfen: Nummer des Programms an der Referenz festhalten bzw. übernehmen. */
    abstract protected function linked(Customer|Supplier $party, RemoteContact $contact, ?string $number): void;

    /**
     * Ein Lauf für die gebundene Organisation; ohne „Buchhaltung führt“ geschieht nichts.
     *
     * @return array{read: int, linked: int, inboxed: int, skipped: int}
     */
    public function run(Organization $organization): array {
        $counters = ['read' => 0, 'linked' => 0, 'inboxed' => 0, 'skipped' => 0];
        if ($this->contacts->pushAllowed() || ! $this->isConfigured((int) $organization->id)) {
            return $counters;
        }

        foreach ($this->remoteContacts((int) $organization->id) as $contact) {
            $counters['read']++;
            if ($contact->numbers === []) {
                $counters['skipped']++;

                continue;
            }
            foreach ($contact->numbers as $party => $number) {
                $isSupplier = $party === Supplier::class;
                $attributes = array_filter(
                    $contact->attributes + ($isSupplier ? [] : ['accounting_number' => $number]),
                    static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== [],
                );
                $outcome = $this->resolver->resolve(
                    $organization,
                    $this->pluginId(),
                    $isSupplier ? new SupplierMatchProfile : new CustomerMatchProfile,
                    ExternalReference::TYPE_CONTACT,
                    $contact->externalId,
                    $attributes,
                    $contact->raw,
                    ImportMatchPolicy::AutoLinkExactOnly,
                    // Das Programm führt: ein verknüpfter Kontakt übernimmt dessen Stand ohne Rückfrage.
                    ConflictFieldPolicy::RemoteWins,
                    $this->pluginId(),
                );
                if ($outcome->isResolved() && ($outcome->model instanceof Customer || $outcome->model instanceof Supplier)) {
                    $this->linked($outcome->model, $contact, $number);
                    $counters['linked']++;
                } else {
                    $counters['inboxed']++;
                }
            }
        }

        return $counters;
    }
}
