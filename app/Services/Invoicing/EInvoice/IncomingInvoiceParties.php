<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoiceParties.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\EInvoice;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceMatchKind};
use App\Models\Customer\Customer;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\{Organization, User};
use App\Models\Supplier\Supplier;
use App\Modules\ModuleRegistry;
use App\Services\Integration\Profiles\{CustomerMatchProfile, SupplierMatchProfile};
use App\Services\Invoicing\Contracts\IncomingPartyLookup;
use App\Services\Invoicing\Dto\IncomingPartyCandidate;
use App\Services\Stammdaten\{ContactIdentity, ContactNumberAdopter, ContactPersons};
use CommonToolkit\Helper\Data\{BankHelper, StringHelper};
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\{Cache, Log};
use RuntimeException;
use Throwable;

/**
 * Gegenparteien des Rechnungseingangs (Phase 152): Stammsatz aus Belegdaten,
 * Bestand vor Neuanlage (L2), Gruppen einer Auswahl, Kandidaten aus dem
 * Buchhaltungsprogramm (MVP-1226) und die am Sammelkontakt geparkten Belege
 * (MVP-1225).
 */
class IncomingInvoiceParties {
    public const LOOKUP_TTL = 600;

    /** Freie Postfächer: gleiche Domain heißt dort nicht gleiche Firma. */
    private const SHARED_MAIL_DOMAINS = ['gmail.com', 'googlemail.com', 'web.de', 'gmx.de', 'gmx.net', 't-online.de', 'outlook.com', 'outlook.de', 'hotmail.com', 'hotmail.de', 'yahoo.com', 'yahoo.de', 'icloud.com', 'me.com', 'live.de', 'freenet.de', 'posteo.de', 'mail.de'];

    public function __construct(
        private readonly IncomingInvoiceMatcher $matcher,
        private readonly ModuleRegistry $modules,
        private readonly ContactIdentity $identity,
    ) {}

    /**
     * Belegdaten der Gegenpartei als Stammsatzfelder (Vorbelegung, Neuanlage, Vergleich).
     *
     * @return array{name: ?string, vat_id: ?string, email: ?string, iban: ?string, street: ?string, zip: ?string, city: ?string, country: ?string}
     */
    public function fieldsOf(IncomingEInvoice $incoming): array {
        $isPurchase = $incoming->direction !== DocumentDirection::Outgoing;
        $summary = (array) $incoming->summary;
        $address = (array) ($summary[$isPurchase ? 'seller_address' : 'buyer_address'] ?? []);
        $text = static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null;
        $country = $text($address['country'] ?? null);

        return [
            'name' => $text($isPurchase ? $incoming->seller_name : $incoming->buyer_name),
            'vat_id' => $text($isPurchase ? $incoming->seller_vat_id : $incoming->buyer_vat_id),
            'email' => $text($isPurchase ? ($summary['seller_email'] ?? $incoming->sender_email) : ($summary['buyer_email'] ?? null)),
            'iban' => $isPurchase ? $text($incoming->creditor_iban) : null,
            'street' => $text($address['street'] ?? null),
            'zip' => $text($address['zip'] ?? null),
            'city' => $text($address['city'] ?? null),
            'country' => $country === null ? null : strtoupper($country),
        ];
    }

    /**
     * Neuanlage aus Belegdaten (Dialog, Sammelaktion, Umhängen vom Sammelkontakt).
     *
     * @param  array<string, mixed>  $overrides  Formularwerte, die die Belegdaten ersetzen
     */
    public function createFromIncoming(IncomingEInvoice $incoming, array $overrides = []): Supplier|Customer {
        $organization = $incoming->organization;
        if ($organization === null) {
            throw new RuntimeException('Eingang ohne Organisation.');
        }

        return $this->create($organization, $incoming->direction !== DocumentDirection::Outgoing, [...$this->fieldsOf($incoming), ...$overrides]);
    }

    /**
     * @param  array<string, mixed>  $fields  name, vat_id, email, street, zip, city, country, iban; aus dem
     *                                        Buchhaltungsprogramm dazu contact (Hauptansprechpartner) und contact_persons
     */
    public function create(Organization $organization, bool $isPurchase, array $fields): Supplier|Customer {
        $name = trim((string) ($fields['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException((string) __('Ohne Namen lässt sich keine Partei anlegen.'));
        }
        $iban = trim((string) ($fields['iban'] ?? ''));
        $persons = ContactPersons::normalizeAll(is_array($fields['contact_persons'] ?? null) ? $fields['contact_persons'] : []);
        $mapped = array_filter([
            'name' => $name,
            'company' => $name,
            'vat_id' => $fields['vat_id'] ?? null,
            'email' => $fields['email'] ?? null,
            'address_street' => $fields['street'] ?? null,
            'address_zip' => $fields['zip'] ?? null,
            'address_city' => $fields['city'] ?? null,
            'country' => isset($fields['country']) && is_string($fields['country']) ? strtoupper(trim($fields['country'])) : null,
            'bank_iban' => $iban !== '' ? BankHelper::normalizeIBAN($iban) : null,
            'contact_persons' => $persons !== [] ? $persons : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $profile = $isPurchase ? app(SupplierMatchProfile::class) : app(CustomerMatchProfile::class);
        $party = $profile->create($organization, $mapped);
        if (! $party instanceof Supplier && ! $party instanceof Customer) {
            throw new RuntimeException('Neuanlage lieferte keine Partei.');
        }
        $contact = is_array($fields['contact'] ?? null) ? ContactPersons::normalize($fields['contact']) : null;
        if (ContactPersons::adoptPrimary($party, $contact)) {
            $party->save();
        }

        return $party;
    }

    /** Bestehende Partei mit derselben USt-IdNr. — nie ein Sammelkontakt. */
    public function withSameVat(int $organizationId, bool $isPurchase, ?string $vatId): Supplier|Customer|null {
        $vat = IncomingEInvoiceService::normalizedVat($vatId);
        if ($vat === '') {
            return null;
        }
        $id = $this->partyQuery($organizationId, $isPurchase)->whereNotNull('vat_id')->pluck('vat_id', 'id')
            ->filter(static fn (mixed $value): bool => IncomingEInvoiceService::normalizedVat($value) === $vat)
            ->keys()->first();

        return $id === null ? null : $this->partyQuery($organizationId, $isPurchase)->find((int) $id);
    }

    /** Bestehende Partei mit demselben Namensschlüssel (MVP-1208) — nie ein Sammelkontakt. */
    public function withSameName(int $organizationId, bool $isPurchase, ?string $name): Supplier|Customer|null {
        $key = self::partyKey($name);
        if ($key === null) {
            return null;
        }
        foreach ($this->partyQuery($organizationId, $isPurchase)->orderBy('id')->get() as $party) {
            if (self::partyKey($party->name) === $key || self::partyKey($party->company ?? null) === $key) {
                return $party;
            }
        }

        return null;
    }

    /**
     * Ähnliche Stammsätze (Dublettenwarnung, MVP-1227): gleicher Namensschlüssel,
     * gleiche E-Mail, gleiche Firmen-Domain oder unscharf gleicher Name.
     *
     * @return list<array{party: Supplier|Customer, reason: string}>
     */
    public function similarLocal(IncomingEInvoice $incoming, int $limit = 5): array {
        $fields = $this->fieldsOf($incoming);
        if ($fields['name'] === null && $fields['email'] === null) {
            return [];
        }
        $isPurchase = $incoming->direction !== DocumentDirection::Outgoing;
        $nameKey = self::partyKey($fields['name']);
        $email = mb_strtolower((string) $fields['email']);
        $domain = self::companyDomain($email !== '' ? $email : mb_strtolower((string) $incoming->sender_email));
        $probe = $this->probe($isPurchase, $fields);
        $current = $incoming->counterparty();
        $found = [];
        foreach ($this->partyQuery((int) $incoming->organization_id, $isPurchase)->whereNull('archived_at')->orderBy('name')->get() as $party) {
            if ($current !== null && $current::class === $party::class && (int) $current->id === (int) $party->id) {
                continue;
            }
            $partyEmail = mb_strtolower((string) ($party->email ?? ''));
            $reason = match (true) {
                $nameKey !== null && (self::partyKey($party->name) === $nameKey || self::partyKey($party->company ?? null) === $nameKey) => 'name',
                $email !== '' && $partyEmail === $email => 'email',
                $domain !== null && self::companyDomain($partyEmail) === $domain => 'domain',
                $fields['name'] !== null && $this->identity->same($probe, ['name' => (string) $party->name, 'company' => (string) ($party->company ?? ''), 'vat_id' => $party->vat_id]) => 'similar',
                default => null,
            };
            if ($reason !== null) {
                $found[] = ['party' => $party, 'reason' => $reason];
                if (count($found) >= $limit) {
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Auswahl nach Richtung und Gegenpartei gruppieren (E3): normalisierte
     * USt-IdNr., sonst Namensschlüssel; `existing` ist die bestehende Partei
     * mit gleicher USt-IdNr. oder gleichem Namen.
     *
     * @param  Collection<int, IncomingEInvoice>  $incomings
     * @return list<array{direction: DocumentDirection, key: string, name: ?string, vat_id: ?string, incomings: list<IncomingEInvoice>, existing: Supplier|Customer|null}>
     */
    public function groups(Collection $incomings): array {
        $groups = [];
        foreach ($incomings as $incoming) {
            $direction = $incoming->direction === DocumentDirection::Outgoing ? DocumentDirection::Outgoing : DocumentDirection::Incoming;
            $fields = $this->fieldsOf($incoming);
            $vat = IncomingEInvoiceService::normalizedVat($fields['vat_id']);
            $key = $vat !== '' ? 'vat:' . $vat : 'name:' . (self::partyKey($fields['name']) ?? 'id:' . $incoming->id);
            $id = $direction->value . '|' . $key;
            $groups[$id] ??= ['direction' => $direction, 'key' => $key, 'name' => $fields['name'], 'vat_id' => $fields['vat_id'], 'incomings' => [], 'existing' => null];
            $groups[$id]['incomings'][] = $incoming;
            $groups[$id]['name'] ??= $fields['name'];
            $groups[$id]['vat_id'] ??= $fields['vat_id'];
        }
        foreach ($groups as &$group) {
            $isPurchase = $group['direction'] === DocumentDirection::Incoming;
            $organizationId = (int) $group['incomings'][0]->organization_id;
            $group['existing'] = $this->withSameVat($organizationId, $isPurchase, $group['vat_id']) ?? $this->withSameName($organizationId, $isPurchase, $group['name']);
        }
        unset($group);

        return array_values($groups);
    }

    /**
     * Kandidaten aus den Buchhaltungsprogrammen (MVP-1226): alle eingeschalteten
     * Quellen, Identitätsprüfung im Kern (L5), zehn Minuten Cache je Beleg.
     *
     * @return array{candidates: list<IncomingPartyCandidate>, sources: list<array{label: string, state: 'found'|'none'|'failed'}>}
     */
    public function lookupCandidates(IncomingEInvoice $incoming, bool $fresh = false): array {
        $empty = ['candidates' => [], 'sources' => []];
        $organization = $incoming->organization;
        $fields = $this->fieldsOf($incoming);
        if ($organization === null || ($fields['name'] === null && $fields['email'] === null)) {
            return $empty;
        }
        // Versionsteil im Schlüssel: die Kandidaten liegen als Arrays im Cache (Objekte liest er nicht zurück), ein älterer Stand kennt die Ansprechpartner-Felder nicht.
        $cacheKey = 'incoming-party-lookup:3:' . $incoming->id . ':' . ($incoming->updated_at?->getTimestamp() ?? 0);
        if (! $fresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && is_array($cached['candidates'] ?? null) && is_array($cached['sources'] ?? null)) {
                /** @var array{candidates: list<array<string, mixed>>, sources: list<array{label: string, state: 'found'|'none'|'failed'}>} $cached */
                return ['candidates' => array_map(IncomingPartyCandidate::fromArray(...), $cached['candidates']), 'sources' => $cached['sources']];
            }
        }
        $isPurchase = $incoming->direction !== DocumentDirection::Outgoing;
        $probe = $this->probe($isPurchase, $fields);
        $email = mb_strtolower((string) $fields['email']);
        $result = $empty;
        $seen = [];
        foreach ($this->modules->extensions(IncomingPartyLookup::class) as $class) {
            $source = app($class);
            if (! $source instanceof IncomingPartyLookup || ! $source->isEnabled($organization)) {
                continue;
            }
            try {
                $found = $source->lookup($incoming);
            } catch (Throwable $e) {
                Log::info('Kontaktsuche im Buchhaltungsprogramm nicht möglich', ['source' => $source->key(), 'incoming' => $incoming->id, 'exception' => $e::class]);
                $result['sources'][] = ['label' => $source->label(), 'state' => 'failed'];

                continue;
            }
            $matching = 0;
            foreach ($found as $candidate) {
                if (isset($seen[$candidate->key()])) {
                    continue;
                }
                $sameMail = $email !== '' && mb_strtolower((string) $candidate->email) === $email;
                if (! $sameMail && ! $this->identity->same($probe, ['name' => $candidate->name, 'company' => $candidate->name, 'vat_id' => $candidate->vatId])) {
                    continue;
                }
                $seen[$candidate->key()] = true;
                $result['candidates'][] = $candidate;
                $matching++;
            }
            $result['sources'][] = ['label' => $source->label(), 'state' => $matching > 0 ? 'found' : 'none'];
        }
        Cache::put($cacheKey, ['candidates' => array_map(static fn (IncomingPartyCandidate $candidate): array => $candidate->toArray(), $result['candidates']), 'sources' => $result['sources']], self::LOOKUP_TTL);

        return $result;
    }

    /**
     * Kandidat übernehmen (MVP-1226): lokaler Stammsatz aus dem Kontakt des
     * Programms — Bestand mit gleicher USt-IdNr. oder gleichem Namen wird
     * verknüpft statt neu angelegt —, Verknüpfung samt Kontaktnummer.
     */
    public function adopt(IncomingEInvoice $incoming, string $candidateKey, User $actor): Supplier|Customer {
        $candidate = $this->candidate($incoming, $candidateKey) ?? $this->candidate($incoming, $candidateKey, true);
        if ($candidate === null) {
            throw new RuntimeException((string) __('Der Kontakt aus der Buchhaltung ist nicht mehr auffindbar.'));
        }
        $isPurchase = $incoming->direction !== DocumentDirection::Outgoing;
        $organizationId = (int) $incoming->organization_id;
        $organization = $incoming->organization;
        if ($organization === null) {
            throw new RuntimeException('Eingang ohne Organisation.');
        }
        $party = $this->withSameVat($organizationId, $isPurchase, $candidate->vatId)
            ?? $this->withSameName($organizationId, $isPurchase, $candidate->name)
            ?? $this->create($organization, $isPurchase, $candidate->partyFields());
        app(ContactNumberAdopter::class)->linked($party, $candidate->pluginId, $candidate->externalId, $candidate->number);
        $incoming->audit('incoming_einvoice.party_adopted', ['plugin' => $candidate->pluginId, 'external_id' => $candidate->externalId, 'party_id' => (int) $party->id, 'actor_user_id' => $actor->id]);

        return $party;
    }

    /**
     * Am Sammelkontakt geparkte Belege nach Richtung und Gegenpartei (MVP-1225).
     *
     * @return list<array{direction: DocumentDirection, key: string, name: ?string, vat_id: ?string, total: int, open: int, first: IncomingEInvoice, existing: Supplier|Customer|null}>
     */
    public function parkedAtCollective(int $organizationId): array {
        $rows = IncomingEInvoice::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('status', '!=', IncomingEInvoiceStatus::Rejected->value)
            ->where(static fn ($query) => $query
                ->whereHas('supplier', static fn ($supplier) => $supplier->withoutGlobalScopes()->where('is_collective', true))
                ->orWhereHas('customer', static fn ($customer) => $customer->withoutGlobalScopes()->where('is_collective', true)))
            ->orderBy('id')
            ->get();

        return array_map(static fn (array $group): array => [
            'direction' => $group['direction'],
            'key' => $group['key'],
            'name' => $group['name'],
            'vat_id' => $group['vat_id'],
            'total' => count($group['incomings']),
            'open' => count(array_filter($group['incomings'], static fn (IncomingEInvoice $incoming): bool => $incoming->transferred_at === null)),
            'first' => $group['incomings'][0],
            'existing' => $group['existing'],
        ], $this->groups($rows));
    }

    /**
     * Vom Sammelkontakt auf eine echte Partei umhängen: Bestand vor Neuanlage,
     * offene Belege umhängen, übergebene bleiben.
     *
     * @return array{party: Supplier|Customer, moved: int, left: int}
     */
    public function promote(int $organizationId, DocumentDirection $direction, string $key, User $actor): array {
        $group = null;
        foreach ($this->parkedAtCollective($organizationId) as $candidate) {
            if ($candidate['direction'] === $direction && $candidate['key'] === $key) {
                $group = $candidate;
                break;
            }
        }
        if ($group === null) {
            throw new RuntimeException((string) __('Diese Gruppe gibt es am Sammelkontakt nicht mehr.'));
        }
        $party = $group['existing'] ?? $this->createFromIncoming($group['first']);
        $moved = 0;
        $left = 0;
        $members = IncomingEInvoice::query()->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('status', '!=', IncomingEInvoiceStatus::Rejected->value)
            ->where($direction === DocumentDirection::Outgoing ? 'customer_id' : 'supplier_id', $direction === DocumentDirection::Outgoing ? $group['first']->customer_id : $group['first']->supplier_id)
            ->orderBy('id')->get();
        foreach ($this->groups($members) as $parked) {
            if ($parked['direction'] !== $direction || $parked['key'] !== $key) {
                continue;
            }
            foreach ($parked['incomings'] as $incoming) {
                if ($incoming->transferred_at !== null) {
                    $left++;

                    continue;
                }
                $this->matcher->assign($incoming, $party, IncomingInvoiceMatchKind::Manual, $actor);
                $moved++;
            }
        }
        $group['first']->audit('incoming_einvoice.collective_promoted', ['party_id' => (int) $party->id, 'moved' => $moved, 'left' => $left, 'actor_user_id' => $actor->id]);

        return ['party' => $party, 'moved' => $moved, 'left' => $left];
    }

    private function candidate(IncomingEInvoice $incoming, string $key, bool $fresh = false): ?IncomingPartyCandidate {
        foreach ($this->lookupCandidates($incoming, $fresh)['candidates'] as $candidate) {
            if ($candidate->key() === $key) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Flüchtige Partei aus Belegdaten für den Identitätsvergleich — wird nie gespeichert.
     *
     * @param  array<string, mixed>  $fields
     */
    private function probe(bool $isPurchase, array $fields): Supplier|Customer {
        $attributes = ['name' => (string) $fields['name'], 'company' => (string) $fields['name'], 'vat_id' => $fields['vat_id']];

        return $isPurchase ? new Supplier($attributes) : new Customer($attributes);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Supplier>|\Illuminate\Database\Eloquent\Builder<Customer>
     */
    private function partyQuery(int $organizationId, bool $isPurchase): \Illuminate\Database\Eloquent\Builder {
        $query = $isPurchase ? Supplier::query() : Customer::query();

        return $query->withoutGlobalScopes()->withoutCollective()->where('organization_id', $organizationId);
    }

    /** Namensschlüssel der Gegenpartei: „JTL-Software-GmbH“ und „JTL Software GmbH“ sind dieselbe Firma. */
    public static function partyKey(?string $name): ?string {
        $key = StringHelper::slugify(trim((string) $name));

        return $key === '' ? null : mb_substr($key, 0, 191);
    }

    private static function companyDomain(string $email): ?string {
        $at = strrpos($email, '@');
        if ($at === false) {
            return null;
        }
        $domain = substr($email, $at + 1);

        return $domain === '' || in_array($domain, self::SHARED_MAIL_DOMAINS, true) ? null : $domain;
    }
}
