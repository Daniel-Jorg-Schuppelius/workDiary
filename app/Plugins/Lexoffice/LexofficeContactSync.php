<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeContactSync.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice;

use APIToolkit\API\Authentication\BearerAuthentication;
use App\Models\{ContactAddress, Customer, ExternalReference, ExternalReferenceAlias, IntegrationInboxItem, Organization, Supplier};
use App\Plugins\Support\PluginHttpFactory;
use CommonToolkit\ValueObjects\VatNumber;
use Illuminate\Database\Eloquent\{Builder, Model};
use RuntimeException;

/**
 * Pull-Sync für Lexoffice-Kontakte. Verbindet existierende workDiary-Datensätze
 * mit ihren Lexoffice-Pendants über mehrere Match-Strategien und entscheidet
 * anhand der konfigurierten {@see LexofficeMatchPolicy}, wie mit
 * Daten-Konflikten umzugehen ist.
 *
 * Rollen-bewusst: Lexoffice-Kontakte tragen `roles.customer` und/oder
 * `roles.vendor`. Kunden-Rollen werden auf {@see Customer}, Lieferanten-Rollen
 * auf {@see Supplier} gemappt. Ein Kontakt mit beiden Rollen erzeugt zwei
 * ExternalReference-Zeilen mit identischer external_id, aber unterschiedlichem
 * referenceable_type.
 *
 * Verwendet bewusst HTTP direkt statt SDK, weil wir mit dem Roh-JSON arbeiten
 * (Konflikt-Vergleich Feld für Feld).
 */
class LexofficeContactSync {
    /** @var array<string, int> */
    private array $counters = [];

    /**
     * @param  'both'|'customers'|'suppliers'  $only
     * @param  bool  $stageUnmatched  Remote-Kontakte ohne lokalen Match nicht verwerfen,
     *                                 sondern als CASE_UNMATCHED in die Zuordnungs-Inbox stellen.
     *                                 Greift nur, wenn $createMissingLocal === false.
     * @return array{matched: int, linked: int, created: int, conflicts: int, updated: int, unmatched: int, ambiguous: int, supplier_matched: int, supplier_linked: int, supplier_created: int, supplier_conflicts: int, supplier_updated: int, supplier_unmatched: int}
     */
    public function sync(
        Organization $organization,
        LexofficeMatchPolicy $policy,
        ?string $apiKey,
        string $baseUrl = 'https://api.lexoffice.io/v1',
        bool $createMissingLocal = false,
        string $only = 'both',
        bool $stageUnmatched = false,
    ): array {
        if ($apiKey === null || $apiKey === '') {
            throw new RuntimeException('Lexoffice API key is not configured (LEXOFFICE_API_KEY).');
        }

        $this->counters = [
            'matched' => 0,
            'linked' => 0,
            'created' => 0,
            'conflicts' => 0,
            'updated' => 0,
            'unmatched' => 0,
            'ambiguous' => 0,
            'supplier_matched' => 0,
            'supplier_linked' => 0,
            'supplier_created' => 0,
            'supplier_conflicts' => 0,
            'supplier_updated' => 0,
            'supplier_unmatched' => 0,
        ];

        $api = app(PluginHttpFactory::class)->client(LexofficePlugin::ID, $baseUrl, LexofficeConfig::requestInterval());
        $api->setAuthentication(new BearerAuthentication($apiKey));

        $page = 0;
        $pageSize = 100;

        do {
            $response = $api->getResponse($baseUrl . '/contacts', [
                'page' => $page,
                'size' => $pageSize,
            ]);

            if (! $response->successful()) {
                throw LexofficeApiException::fromResponse($response, __('Kontakte'), __('Kontakte abrufen'));
            }

            /** @var array<string, mixed> $body */
            $body = $response->json() ?? [];
            $items = (array) ($body['content'] ?? []);

            foreach ($items as $remote) {
                if (! is_array($remote) || empty($remote['id'])) {
                    continue;
                }
                foreach ($this->kindsFor($remote, $only) as $kind) {
                    $this->processKind($kind, $organization, $remote, $policy, $createMissingLocal, $stageUnmatched);
                }
            }

            $totalPages = (int) ($body['totalPages'] ?? 1);
            $page++;
        } while ($page < $totalPages);

        /** @var array{matched: int, linked: int, created: int, conflicts: int, updated: int, unmatched: int, ambiguous: int, supplier_matched: int, supplier_linked: int, supplier_created: int, supplier_conflicts: int, supplier_updated: int, supplier_unmatched: int} $result */
        $result = $this->counters;

        return $result;
    }

    /**
     * Ermittelt, für welche Rollen (customer/vendor) ein Remote-Kontakt
     * verarbeitet werden soll — unter Berücksichtigung des `$only`-Filters.
     *
     * @param  array<string, mixed>  $remote
     * @return array<int, 'customer'|'vendor'>
     */
    private function kindsFor(array $remote, string $only): array {
        $roles = (array) data_get($remote, 'roles', []);
        $hasCustomer = array_key_exists('customer', $roles);
        $hasVendor = array_key_exists('vendor', $roles);

        // Legacy-Fallback: Kontakte ohne Rollen werden als Kunde behandelt.
        if (! $hasCustomer && ! $hasVendor) {
            $hasCustomer = true;
        }

        $kinds = [];
        if ($hasCustomer && $only !== 'suppliers') {
            $kinds[] = 'customer';
        }
        if ($hasVendor && $only !== 'customers') {
            $kinds[] = 'vendor';
        }

        return $kinds;
    }

    /**
     * @param  'customer'|'vendor'  $kind
     * @param  array<string, mixed>  $remote
     */
    private function processKind(string $kind, Organization $organization, array $remote, LexofficeMatchPolicy $policy, bool $createMissingLocal, bool $stageUnmatched = false): void {
        $modelClass = $kind === 'vendor' ? Supplier::class : Customer::class;
        $morphClass = (new $modelClass)->getMorphClass();
        $externalId = (string) $remote['id'];

        $existingRef = ExternalReference::query()
            ->forPlugin($organization, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $morphClass)
            ->forExternalId($externalId)
            ->first();

        if ($existingRef instanceof ExternalReference) {
            /** @var Customer|Supplier|null $record */
            $record = $modelClass::query()->find($existingRef->referenceable_id);
            if ($record === null) {
                return;
            }
            // Bereits verknüpft: ein evtl. noch offenes Staging-Item ist erledigt.
            $this->resolveUnmatchedInbox($organization, $morphClass, $externalId, $record);
            $this->bump($kind, 'matched');
            if ($this->applyRemote($record, $remote, $policy, $externalId)) {
                $this->bump($kind, 'updated');
            }
            // Konflikt-Erfassung ist unabhängig vom (Metadaten-)Update: auch wenn
            // gerade die Kontaktnummer aktualisiert wurde, kann es offene
            // Nutzerdaten-Konflikte geben, die der Mensch prüfen muss.
            if ($policy === LexofficeMatchPolicy::ManualReview && $this->hasConflict($record, $remote)) {
                $this->recordConflict($record, $remote, $externalId, $organization);
                $this->bump($kind, 'conflicts');
            }

            return;
        }

        // Alias-Fallback: per Merge umgeleitete Kontakt-UUID zeigt aufs
        // Merge-Ziel. Nur als „matched" zählen — Stammdaten pflegt die
        // Primär-Referenz des Ziels; der Alias-Kontakt ist das in Lexoffice
        // verbliebene Duplikat und darf das Ziel nicht überschreiben.
        $aliased = ExternalReferenceAlias::resolveModel($organization->id, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT, $externalId);
        if ($aliased instanceof $modelClass) {
            $this->resolveUnmatchedInbox($organization, $morphClass, $externalId, $aliased);
            $this->bump($kind, 'matched');

            return;
        }

        $match = $this->findLocalMatch($modelClass, $organization, $remote);
        if ($match instanceof Model) {
            // Phase-C-Schutz: Zwei verschiedene Remote-Kontakte können denselben
            // lokalen Datensatz matchen. Existiert bereits eine Contact-Ref mit
            // ANDERER external_id, würde ein zweiter Insert extref_unique verletzen.
            if ($this->hasConflictingRef($match, $morphClass, $externalId)) {
                $this->recordAmbiguous($organization, $morphClass, $externalId, $remote, $match);
                $this->bump($kind, 'ambiguous');

                return;
            }

            ExternalReference::create([
                'organization_id' => $organization->id,
                'plugin_id' => LexofficePlugin::ID,
                'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
                'referenceable_type' => $morphClass,
                'referenceable_id' => $match->getKey(),
                'external_id' => $externalId,
                'payload' => $remote,
                'synced_at' => now(),
            ]);
            // Frischer Match: ein evtl. zuvor gestagtes Unmatched-Item ist erledigt.
            $this->resolveUnmatchedInbox($organization, $morphClass, $externalId, $match);
            $this->bump($kind, 'linked');
            if ($this->applyRemote($match, $remote, $policy, $externalId)) {
                $this->bump($kind, 'updated');
            }
            if ($policy === LexofficeMatchPolicy::ManualReview && $this->hasConflict($match, $remote)) {
                $this->recordConflict($match, $remote, $externalId, $organization);
                $this->bump($kind, 'conflicts');
            }

            return;
        }

        if ($createMissingLocal) {
            $new = $this->createFromRemote($kind, $organization, $remote);
            if ($new instanceof Model) {
                ExternalReference::create([
                    'organization_id' => $organization->id,
                    'plugin_id' => LexofficePlugin::ID,
                    'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
                    'referenceable_type' => $morphClass,
                    'referenceable_id' => $new->getKey(),
                    'external_id' => $externalId,
                    'payload' => $remote,
                    'synced_at' => now(),
                ]);
                // Aus dem Staging heraus angelegt → offenes Unmatched-Item schließen.
                $this->resolveUnmatchedInbox($organization, $morphClass, $externalId, $new);
                $this->bump($kind, 'created');
            }

            return;
        }

        // Inbox-First: Remote-Kontakt ohne lokales Pendant nicht verwerfen,
        // sondern zur manuellen Zuordnung/Zusammenführung in die Inbox stellen.
        if ($stageUnmatched) {
            $this->recordUnmatched($organization, $morphClass, $externalId, $remote);
            $this->bump($kind, 'unmatched');
        }
    }

    /**
     * Prüft, ob der lokale Datensatz bereits eine Lexoffice-Contact-Ref mit
     * einer ABWEICHENDEN external_id besitzt (Mehrdeutigkeit).
     */
    private function hasConflictingRef(Model $record, string $morphClass, string $externalId): bool {
        return ExternalReference::query()
            ->forPlugin((int) $record->getAttribute('organization_id'), LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $morphClass)
            ->where('referenceable_id', $record->getKey())
            ->where('external_id', '!=', $externalId)
            ->exists();
    }

    /**
     * Versucht über vat_id → email → company+postcode → name einen lokalen
     * Datensatz (Customer oder Supplier) zu finden.
     *
     * @param  class-string<Customer|Supplier>  $modelClass
     * @param  array<string, mixed>  $remote
     */
    private function findLocalMatch(string $modelClass, Organization $organization, array $remote): ?Model {
        $vatId = $this->extractVatId($remote);
        $email = $this->extractEmail($remote);
        $company = (string) data_get($remote, 'company.name', '');
        $personName = trim(((string) data_get($remote, 'person.firstName', '')) . ' ' . ((string) data_get($remote, 'person.lastName', '')));
        $zip = (string) data_get($remote, 'addresses.billing.0.zip', '');

        $base = fn() => $modelClass::query()->where('organization_id', $organization->id);

        if ($vatId !== '') {
            $byVat = $base()->where('vat_id', $vatId)->first();
            if ($byVat instanceof Model) {
                return $byVat;
            }
        }

        if ($email !== '') {
            $byEmail = $base()->where('email', $email)->first();
            if ($byEmail instanceof Model) {
                return $byEmail;
            }
        }

        if ($company !== '' && $zip !== '') {
            $byCompany = $base()->where('company', $company)->where('address_zip', $zip)->first();
            if ($byCompany instanceof Model) {
                return $byCompany;
            }
        }

        // Schwache Heuristiken (Name allein) nur akzeptieren, wenn sie EINDEUTIG
        // sind. Bei mehreren Gleichnamigen nicht raten → die Inbox entscheidet.
        if ($company !== '') {
            $byCompany = $this->uniqueMatch($base()->where('company', $company));
            if ($byCompany instanceof Model) {
                return $byCompany;
            }
        }

        if ($personName !== '') {
            $byName = $this->uniqueMatch($base()->where('name', $personName));
            if ($byName instanceof Model) {
                return $byName;
            }
        }

        return null;
    }

    /**
     * Liefert den Treffer nur, wenn er eindeutig ist (genau ein Datensatz).
     * Null bei null oder mehr als einem Kandidaten — verhindert das Raten bei
     * mehrdeutigen Namens-Matches.
     *
     * @param  Builder<Customer>|Builder<Supplier>  $query
     */
    private function uniqueMatch(Builder $query): ?Model {
        $hits = $query->limit(2)->get();

        return $hits->count() === 1 ? $hits->first() : null;
    }

    /**
     * @param  'customer'|'vendor'  $kind
     * @param  array<string, mixed>  $remote
     */
    private function createFromRemote(string $kind, Organization $organization, array $remote): ?Model {
        $isCompany = ! empty(data_get($remote, 'company.name'));
        $name = $isCompany
            ? (string) data_get($remote, 'company.name')
            : trim(((string) data_get($remote, 'person.firstName', '')) . ' ' . ((string) data_get($remote, 'person.lastName', '')));

        if ($name === '') {
            return null;
        }

        $attributes = [
            'organization_id' => $organization->id,
            'name' => $name,
            'company' => $isCompany ? $name : null,
            'vat_id' => $this->extractVatId($remote) ?: null,
            'tax_number' => $this->extractTaxNumber($remote) ?: null,
            'email' => $this->extractEmail($remote) ?: null,
            'phone' => (string) data_get($remote, 'phoneNumbers.business.0', '') ?: null,
            'mobile' => (string) data_get($remote, 'phoneNumbers.mobile.0', '') ?: null,
            'fax' => (string) data_get($remote, 'phoneNumbers.fax.0', '') ?: null,
            'comment' => (string) data_get($remote, 'note', '') ?: null,
            // F8/E6: Adresse geht ausschließlich über syncAddresses() nach
            // contact_addresses; die Inline-Spalten füllt die Projektion.
            'currency' => 'EUR',
        ];

        $contactNumber = $this->extractContactNumber($remote, $kind);
        if ($kind === 'vendor') {
            if ($contactNumber !== '') {
                $attributes['vendor_number'] = $contactNumber;
            }
            $supplier = Supplier::create($attributes);
            $this->syncAddresses($supplier, $remote);
            $this->syncContactPersons($supplier, $remote);

            return $supplier;
        }

        if ($contactNumber !== '') {
            $attributes['lexoffice_contact_number'] = $contactNumber;
        }
        $attributes['billable'] = true;

        $customer = Customer::create($attributes);
        $this->syncAddresses($customer, $remote);
        $this->syncContactPersons($customer, $remote);

        return $customer;
    }

    /**
     * Wendet Remote-Felder gemäß Policy auf den lokalen Datensatz an.
     *
     * @param  array<string, mixed>  $remote
     */
    private function applyRemote(Model $record, array $remote, LexofficeMatchPolicy $policy, string $externalId): bool {
        // Die offizielle Lexoffice-Kontaktnummer ist nicht nutzergepflegt und
        // wird daher unabhängig von der Policy immer in das passende Feld
        // übernommen (Customer: lexoffice_contact_number, Supplier: vendor_number).
        $changed = $this->applyContactNumber($record, $remote);

        if ($policy === LexofficeMatchPolicy::LocalWins || $policy === LexofficeMatchPolicy::ManualReview) {
            if ($changed) {
                $record->save();
            }
            $this->touchSnapshot($record, $remote, $externalId);

            // Nutzerdaten bleiben unberührt, aber die Kontaktnummer (Metadaten)
            // kann sich geändert haben — das ist ein echtes Update und wird gezählt.
            return $changed;
        }

        $changes = $this->buildChangesFromRemote($remote);
        if ($changes !== []) {
            $record->fill($changes);
            $changed = true;
        }
        if ($changed) {
            $record->save();
        }
        $this->syncAddresses($record, $remote);
        $this->syncContactPersons($record, $remote);
        $this->touchSnapshot($record, $remote, $externalId);

        return $changed || $changes !== [];
    }

    /**
     * Übernimmt die Lexoffice-Kontaktnummer in das rollenpassende Feld.
     *
     * @param  array<string, mixed>  $remote
     */
    private function applyContactNumber(Model $record, array $remote): bool {
        $kind = $record instanceof Supplier ? 'vendor' : 'customer';
        $number = $this->extractContactNumber($remote, $kind);
        if ($number === '') {
            return false;
        }
        $column = $record instanceof Supplier ? 'vendor_number' : 'lexoffice_contact_number';
        $changed = false;
        if ((string) $record->getAttribute($column) !== $number) {
            $record->setAttribute($column, $number);
            $changed = true;
        }

        // Ist Lexoffice für die Nummer führend, ersetzt die offizielle Nummer
        // die lokale Entwurfsnummer.
        if (
            (string) $record->getAttribute('number_source') === 'lexoffice'
            && (string) $record->getAttribute('number') !== $number
        ) {
            $record->setAttribute('number', $number);
            $changed = true;
        }

        return $changed;
    }

    /**
     * Normalisiert leere Strings zu null — Pflicht für `encrypted`-Felder, da
     * Laravel einen leeren String zu entschlüsseln versucht und mit
     * "The payload is invalid." abbricht.
     */
    private function nullIfBlank(mixed $value): ?string {
        if ($value === null) {
            return null;
        }
        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    /**
     * Spiegelt die Lexoffice-Adressen (billing/shipping) in contact_addresses.
     *
     * @param  array<string, mixed>  $remote
     */
    private function syncAddresses(Model $record, array $remote): void {
        if (! ($record instanceof Customer) && ! ($record instanceof Supplier)) {
            return;
        }
        $orgId = $record->getAttribute('organization_id');

        foreach ([ContactAddress::KIND_BILLING, ContactAddress::KIND_SHIPPING] as $kind) {
            $list = (array) data_get($remote, 'addresses.' . $kind, []);
            foreach (array_values($list) as $i => $addr) {
                if (! is_array($addr)) {
                    continue;
                }
                $record->addresses()->updateOrCreate(
                    ['kind' => $kind, 'external_id' => $kind . '-' . $i],
                    [
                        'organization_id' => $orgId,
                        // Verschlüsselte Felder NIE als leeren String speichern:
                        // '' lässt sich nicht entschlüsseln und sprengt sonst
                        // jeden späteren Lese-/Save-Zugriff (DecryptException).
                        'supplement' => $this->nullIfBlank($addr['supplement'] ?? null),
                        'street' => $this->nullIfBlank($addr['street'] ?? null),
                        'zip' => $this->nullIfBlank($addr['zip'] ?? null),
                        'city' => $this->nullIfBlank($addr['city'] ?? null),
                        'country_code' => $addr['countryCode'] ?? null,
                        'is_primary' => $i === 0 && $kind === ContactAddress::KIND_BILLING,
                    ],
                );
            }
        }
    }

    /**
     * Übernimmt die Lexoffice-Ansprechpartner in das contact_persons-JSON.
     *
     * @param  array<string, mixed>  $remote
     */
    private function syncContactPersons(Model $record, array $remote): void {
        if (! ($record instanceof Customer) && ! ($record instanceof Supplier)) {
            return;
        }
        $persons = (array) data_get($remote, 'company.contactPersons', []);
        if ($persons === []) {
            return;
        }

        $list = [];
        foreach ($persons as $p) {
            if (! is_array($p)) {
                continue;
            }
            $name = trim(((string) ($p['firstName'] ?? '')) . ' ' . ((string) ($p['lastName'] ?? '')));
            if ($name === '') {
                continue;
            }
            $list[] = array_filter([
                'name' => $name,
                'email' => $p['emailAddress'] ?? null,
                'phone' => $p['phoneNumber'] ?? null,
                'primary' => (bool) ($p['primary'] ?? false),
            ], static fn($v) => $v !== null && $v !== '');
        }

        if ($list !== []) {
            $record->contact_persons = $list;
            $record->save();
        }
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function touchSnapshot(Model $record, array $remote, string $externalId): void {
        ExternalReference::query()
            ->forPlugin((int) $record->getAttribute('organization_id'), LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->where('referenceable_type', $record->getMorphClass())
            ->forExternalId($externalId)
            ->update([
                'payload' => $remote,
                'synced_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function hasConflict(Model $record, array $remote): bool {
        return $this->diffFields($record, $remote) !== [];
    }

    /**
     * Liste der Felder, in denen sich lokaler Datensatz und Remote-Snapshot unterscheiden.
     *
     * @param  array<string, mixed>  $remote
     * @return list<string>
     */
    private function diffFields(Model $record, array $remote): array {
        $remoteVals = $this->buildChangesFromRemote($remote);
        $diff = [];
        foreach ($remoteVals as $field => $value) {
            $local = (string) ($record->getAttribute($field) ?? '');
            if ($local !== (string) $value) {
                $diff[] = $field;
            }
        }

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function recordConflict(Model $record, array $remote, string $externalId, Organization $organization): void {
        $diff = $this->diffFields($record, $remote);
        if ($diff === []) {
            return;
        }

        $mapped = $this->buildChangesFromRemote($remote);
        $this->upsertInboxItem(
            $organization,
            $record->getMorphClass(),
            $externalId,
            IntegrationInboxItem::CASE_CONFLICT,
            $remote,
            $mapped,
            $record,
            $record->only(array_keys($mapped)),
            $diff,
        );
    }

    /**
     * Schreibt ein unmatched-Inbox-Item: ein Remote-Kontakt ohne lokales
     * Pendant. Statt ihn zu verwerfen, wird er zur manuellen Zuordnung
     * (Zusammenführung) oder Neuanlage in die Inbox gestellt.
     *
     * @param  array<string, mixed>  $remote
     */
    private function recordUnmatched(Organization $organization, string $morphClass, string $externalId, array $remote): void {
        $this->upsertInboxItem(
            $organization,
            $morphClass,
            $externalId,
            IntegrationInboxItem::CASE_UNMATCHED,
            $remote,
            $this->buildChangesFromRemote($remote),
        );
    }

    /**
     * Schließt ein zuvor gestagtes, noch offenes Unmatched-Inbox-Item, sobald
     * der Remote-Kontakt einem lokalen Datensatz zugeordnet (oder daraus
     * angelegt) wurde — verhindert Karteileichen beim späteren Pull.
     */
    private function resolveUnmatchedInbox(Organization $organization, string $morphClass, string $externalId, Model $target): void {
        $dedupeKey = LexofficePlugin::EXT_TYPE_CONTACT . ':' . $externalId . ':' . class_basename($morphClass);

        IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', LexofficePlugin::ID)
            ->where('dedupe_key', $dedupeKey)
            ->where('case_type', IntegrationInboxItem::CASE_UNMATCHED)
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->update([
                'status' => IntegrationInboxItem::STATUS_RESOLVED_LINKED,
                'referenceable_type' => $target->getMorphClass(),
                'referenceable_id' => $target->getKey(),
                'resolved_to_type' => $target->getMorphClass(),
                'resolved_to_id' => $target->getKey(),
                'resolved_at' => now(),
            ]);
    }

    /**
     * Schreibt ein ambiguous-Inbox-Item: mehrere Remote-Kontakte zeigen auf
     * denselben lokalen Datensatz (zuvor still gezählt).
     *
     * @param  array<string, mixed>  $remote
     */
    private function recordAmbiguous(Organization $organization, string $morphClass, string $externalId, array $remote, Model $candidate): void {
        $this->upsertInboxItem(
            $organization,
            $morphClass,
            $externalId,
            IntegrationInboxItem::CASE_AMBIGUOUS,
            $remote,
            $this->buildChangesFromRemote($remote),
            $candidate,
        );
    }

    /**
     * Idempotentes Schreiben eines Lexoffice-Eintrags in die universelle
     * Zuordnungs-Inbox. Bereits aufgelöste/verworfene Items werden nicht
     * reaktiviert (nur Snapshots aktualisiert).
     *
     * @param  array<string, mixed>  $remote
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>|null  $localSnapshot
     * @param  list<string>  $diffFields
     */
    private function upsertInboxItem(
        Organization $organization,
        string $morphClass,
        string $externalId,
        string $caseType,
        array $remote,
        array $mapped,
        ?Model $referenceable = null,
        ?array $localSnapshot = null,
        array $diffFields = [],
    ): void {
        $dedupeKey = LexofficePlugin::EXT_TYPE_CONTACT . ':' . $externalId . ':' . class_basename($morphClass);
        $title = (string) ($mapped['name'] ?? $mapped['company'] ?? $externalId);
        $subtitle = (string) ($mapped['email'] ?? $mapped['vat_id'] ?? '');

        // $values (2. Arg) greifen nur bei Neuanlage — ein bereits vorhandenes
        // Item behält seinen Status (wird nicht reaktiviert).
        /** @var IntegrationInboxItem $item */
        $item = IntegrationInboxItem::query()->firstOrNew(
            [
                'organization_id' => $organization->id,
                'plugin_id' => LexofficePlugin::ID,
                'dedupe_key' => $dedupeKey,
            ],
            ['status' => IntegrationInboxItem::STATUS_OPEN],
        );
        $item->fill([
            'source' => 'api',
            'target_type' => $morphClass,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => $externalId,
            'case_type' => $caseType,
            'referenceable_type' => $referenceable?->getMorphClass(),
            'referenceable_id' => $referenceable?->getKey(),
            'remote_snapshot' => $remote,
            'mapped_snapshot' => $mapped,
            'local_snapshot' => $localSnapshot,
            'diff_fields' => $diffFields !== [] ? $diffFields : null,
            'display_title' => $title,
            'display_subtitle' => $subtitle !== '' ? $subtitle : null,
        ]);
        $item->save();
    }

    /**
     * Übersetzt das Remote-JSON in das workDiary-Customer-Schema.
     *
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>
     */
    private function buildChangesFromRemote(array $remote): array {
        $isCompany = ! empty(data_get($remote, 'company.name'));
        $name = $isCompany
            ? (string) data_get($remote, 'company.name')
            : trim(((string) data_get($remote, 'person.firstName', '')) . ' ' . ((string) data_get($remote, 'person.lastName', '')));

        $out = array_filter([
            'name' => $name !== '' ? $name : null,
            'company' => $isCompany ? (string) data_get($remote, 'company.name') : null,
            'vat_id' => $this->extractVatId($remote) ?: null,
            'tax_number' => $this->extractTaxNumber($remote) ?: null,
            'email' => $this->extractEmail($remote) ?: null,
            'phone' => (string) data_get($remote, 'phoneNumbers.business.0', '') ?: null,
            'mobile' => (string) data_get($remote, 'phoneNumbers.mobile.0', '') ?: null,
            'fax' => (string) data_get($remote, 'phoneNumbers.fax.0', '') ?: null,
            'comment' => (string) data_get($remote, 'note', '') ?: null,
            // F8/E6: Adresse nur noch über syncAddresses() → Projektion.
        ], static fn($v) => $v !== null);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function extractVatId(array $remote): string {
        // NUR die USt-IdNr. — kein Fallback auf company.taxNumber, sonst
        // landet eine Steuernummer im vat_id-Feld (Fehl-Beschriftung) und
        // verschmutzt obendrein den vat_id-Match in findLocalMatch().
        $value = trim((string) data_get($remote, 'company.vatRegistrationId', ''));

        // Auch das Feld selbst ist drüben oft fehlbeschriftet (Steuernummer in
        // der USt-IdNr.). Ein solcher Wert wird nicht übernommen: bei der
        // Aktualisierung fällt er aus den Änderungen (Bestand bleibt), bei der
        // Neuanlage bleibt das Feld leer. Sichtbar wird er über den
        // Stammdaten-Hinweis am Kontakt; der Rohwert bleibt im
        // `remote_snapshot` der Inbox-Fälle nachvollziehbar.
        return $value !== '' && VatNumber::tryFrom($value) === null ? '' : $value;
    }

    /**
     * Steuernummer (getrennt von der USt-IdNr.).
     *
     * @param  array<string, mixed>  $remote
     */
    private function extractTaxNumber(array $remote): string {
        return (string) data_get($remote, 'company.taxNumber', '');
    }

    /**
     * Offizielle Lexoffice-Kontaktnummer für die gerade verarbeitete Rolle.
     * Bei Doppel-Rollen (customer + vendor) haben beide i. d. R. eigene
     * Nummern — daher rollenbewusst: zuerst die passende Rolle, erst dann
     * (als Notnagel) die andere.
     *
     * @param  array<string, mixed>  $remote
     * @param  'customer'|'vendor'  $kind
     */
    private function extractContactNumber(array $remote, string $kind): string {
        $primary = $kind === 'vendor' ? 'vendor' : 'customer';
        $secondary = $kind === 'vendor' ? 'customer' : 'vendor';

        $number = (string) data_get($remote, 'roles.' . $primary . '.number', '');
        if ($number !== '') {
            return $number;
        }

        return (string) data_get($remote, 'roles.' . $secondary . '.number', '');
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function extractEmail(array $remote): string {
        $mails = (array) data_get($remote, 'emailAddresses.business', []);

        return (string) ($mails[0] ?? data_get($remote, 'emailAddresses.private.0', ''));
    }

    /**
     * Inkrementiert den passenden Zähler je nach Rolle.
     */
    private function bump(string $kind, string $metric): void {
        if ($metric === 'ambiguous') {
            $this->counters['ambiguous']++;

            return;
        }
        $key = $kind === 'vendor' ? 'supplier_' . $metric : $metric;
        $this->counters[$key] = ($this->counters[$key] ?? 0) + 1;
    }
}
