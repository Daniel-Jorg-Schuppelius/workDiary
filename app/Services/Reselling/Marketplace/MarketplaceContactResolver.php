<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarketplaceContactResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Marketplace;

use App\Models\{Customer, ExternalReference, ForeignCustomer, Organization};
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Integration\Match\{EntityMatcher, MatchStrategy};
use App\Services\Integration\Profiles\CustomerMatchProfile;
use App\Services\Reselling\Contracts\InvoiceLineSource;
use App\Services\SqidEncoder;
use Throwable;

/**
 * Ordnet Marketplace-Firmen den Lexoffice-Kontakten zu — in dieser Reihenfolge:
 *
 * 1. Zuordnungsdatei (Firma → Kontakt-UUID, Kunden-Sqid oder
 *    `partner:<Name|Sqid>` für Abrechnung über einen Partner),
 * 2. **Fremdkunde**: Die Firma ist als Fremdkunde eines Kunden angelegt —
 *    die Rechnung geht an diesen Partner, der sie an den Endkunden
 *    weiterreicht; geprüft werden die Rechnungen des Partners,
 * 3. Partner-Kundennummer aus dem Export (Quality Hosting trägt die
 *    Kundennummer des Resellers): Kunde mit dieser Nummer bzw. Lexoffice-
 *    Kontakt mit dieser Kundennummer,
 * 4. Kunden-Matching über das Integrations-Profil (USt-ID/E-Mail/Name) und
 *    dessen Lexoffice-Kontaktverknüpfung (`external_references`),
 * 5. Lexoffice-Kundennummer am Kunden,
 * 6. Namenssuche in Lexoffice, nur bei eindeutigem Treffer.
 *
 * Mehrdeutiges wird nicht geraten, sondern mit Kandidaten gemeldet. Ein
 * unscharfer Namenstreffer zählt nur bei gleichem normalisierten Namen; ein
 * E-Mail-Treffer zählt, wird aber mit Grund ausgewiesen — der Besteller-Login
 * im Marketplace kann zu einer anderen Firma desselben Inhabers gehören.
 */
final class MarketplaceContactResolver {
    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    private const REASON_LABELS = [
        'vat_id' => 'USt-IdNr.',
        'lexoffice_contact_number' => 'Kundennummer',
        'email' => 'E-Mail',
        'company_zip' => 'Firma+PLZ',
        'name' => 'Name (unscharf)',
    ];

    /** @var list<string> */
    private array $errors = [];

    public function __construct(
        private readonly EntityMatcher $matcher,
        private readonly CustomerMatchProfile $profile,
        private readonly SqidEncoder $sqids,
    ) {}

    /**
     * @param  array<string, string>  $manual  Zuordnungsdatei: Firmen-Schlüssel oder normalisierter Name → Ziel (Kontakt-UUID | customer:<sqid> | partner:<Name|sqid>)
     * @param  array<string, string>  $stored  in der Oberfläche gespeicherte Zuordnungen, gleiches Format (Datei gewinnt)
     */
    public function resolve(Organization $organization, MarketplaceCompany $company, array $manual, ?InvoiceLineSource $source, array $stored = []): ContactMapping {
        foreach ([[$manual, ContactMapping::SOURCE_MANUAL], [$stored, ContactMapping::SOURCE_STORED]] as [$targets, $label]) {
            $target = $this->manualTarget($company, $targets);
            if ($target !== null) {
                $mapping = $this->fromManual($organization, $company, $target, $source, $label);
                if ($mapping !== null) {
                    return $mapping;
                }
            }
        }

        $mapping = $this->fromForeignCustomer($organization, $company, $source);
        if ($mapping !== null) {
            return $mapping;
        }

        $partner = trim((string) ($company->partnerCustomerNumber ?? ''));
        if ($partner !== '') {
            $mapping = $this->fromPartnerNumber($organization, $company, $partner, $source);
            if ($mapping !== null) {
                return $mapping;
            }
        }

        $match = $this->matchCustomer($organization, $company);
        $customer = $match['customer'];
        $candidates = $match['candidates'];
        $detail = $match['detail'];

        if ($customer !== null) {
            $ids = $this->contactIdsOf($organization, $customer);
            if ($ids !== []) {
                return new ContactMapping($company, $customer, $ids, ContactMapping::SOURCE_REFERENCE, [], $detail);
            }

            $number = trim((string) ($customer->lexoffice_contact_number ?? ''));
            if ($number !== '' && $source !== null) {
                $hits = $this->safe(static fn(): array => $source->findContactsByNumber($number));
                if (count($hits) === 1) {
                    return new ContactMapping($company, $customer, [$hits[0]['id']], ContactMapping::SOURCE_NUMBER, [], $detail);
                }
            }
        }

        if ($source !== null) {
            $found = $this->fromLexofficeSearch($company, $source, $customer, $candidates);
            if ($found !== null) {
                return $found;
            }
        }

        return new ContactMapping($company, $customer, [], ContactMapping::SOURCE_NONE, array_values(array_unique($candidates)));
    }

    /**
     * Lexoffice-Namenssuche: eindeutiger Namenstreffer oder einziger Treffer.
     * Auch als Nachschlag für zurückgestufte Zuordnungen (E-Mail-Treffer ohne
     * Microsoft-Positionen), damit der echte Lexoffice-Kontakt noch gefunden wird.
     *
     * @param  list<string>  $candidates  wird um die Suchtreffer ergänzt
     */
    public function fromLexofficeSearch(MarketplaceCompany $company, InvoiceLineSource $source, ?Customer $customer = null, array &$candidates = []): ?ContactMapping {
        $hits = $this->safe(static fn(): array => $source->findContactsByName($company->name));
        $wanted = $company->normalizedName();
        $exact = array_values(array_filter($hits, static fn(array $hit): bool => MarketplaceCompany::normalizeName($hit['name']) === $wanted));
        if (count($exact) === 1) {
            return new ContactMapping($company, $customer, [$exact[0]['id']], ContactMapping::SOURCE_SEARCH, [], 'Name gleich');
        }
        if ($exact === [] && count($hits) === 1) {
            return new ContactMapping($company, $customer, [$hits[0]['id']], ContactMapping::SOURCE_SEARCH, [], 'einziger Treffer: ' . $hits[0]['name']);
        }
        foreach ($hits as $hit) {
            $candidates[] = 'Lexoffice: ' . $hit['name'] . ' (' . $hit['id'] . ')';
        }

        return null;
    }

    private function fromPartnerNumber(Organization $organization, MarketplaceCompany $company, string $partner, ?InvoiceLineSource $source): ?ContactMapping {
        $customers = Customer::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->where(static fn($query) => $query->where('number', $partner)->orWhere('lexoffice_contact_number', $partner))
            ->limit(2)
            ->get();
        $detail = 'Nr. ' . $partner;

        if ($customers->count() === 1) {
            /** @var Customer $customer */
            $customer = $customers->first();
            $ids = $this->contactIdsOf($organization, $customer);
            if ($ids !== []) {
                return new ContactMapping($company, $customer, $ids, ContactMapping::SOURCE_PARTNER_NUMBER, [], $detail . ($this->sameName($company, $customer) ? '' : ' · Name weicht ab'));
            }
        }

        if ($source !== null) {
            $hits = $this->safe(static fn(): array => $source->findContactsByNumber($partner));
            if (count($hits) === 1) {
                $customer = $customers->count() === 1 ? $customers->first() : null;

                return new ContactMapping($company, $customer instanceof Customer ? $customer : null, [$hits[0]['id']], ContactMapping::SOURCE_PARTNER_NUMBER, [], $detail . ' (Lexoffice: ' . $hits[0]['name'] . ')');
            }
        }

        return null;
    }

    /**
     * Zuordnungsdatei: Schlüssel ist die Marketplace-Firmen-ID oder der Name
     * (schreibweisen-tolerant, gleiche Normalisierung wie beim Matching).
     *
     * @param  array<string, string>  $manual
     */
    private function manualTarget(MarketplaceCompany $company, array $manual): ?string {
        if (isset($manual[$company->key])) {
            return $manual[$company->key];
        }

        $wanted = $company->normalizedName();
        foreach ($manual as $key => $target) {
            if (MarketplaceCompany::normalizeName((string) $key) === $wanted) {
                return $target;
            }
        }

        return null;
    }

    private function fromManual(Organization $organization, MarketplaceCompany $company, string $target, ?InvoiceLineSource $source, string $label = ContactMapping::SOURCE_MANUAL): ?ContactMapping {
        $target = trim($target);
        if (preg_match(self::UUID, $target) === 1) {
            return new ContactMapping($company, null, [$target], $label);
        }

        // `partner:<Name|Sqid>`: Abrechnung über einen Partner (Fremdkunde ohne Stammsatz).
        if (str_starts_with($target, 'partner:')) {
            $partner = $this->customerByReference($organization, substr($target, 8));
            if (! $partner instanceof Customer) {
                return null;
            }
            $ids = $this->contactIdsForCustomer($organization, $partner, $source);

            return $ids === [] ? null : new ContactMapping($company, $partner, $ids, $label, [], 'über ' . $partner->name, $partner->name);
        }

        $sqid = str_starts_with($target, 'customer:') ? substr($target, 9) : $target;
        $customer = $this->customerByReference($organization, $sqid);
        if (! $customer instanceof Customer) {
            return null;
        }

        $ids = $this->contactIdsForCustomer($organization, $customer, $source);

        return $ids === [] ? null : new ContactMapping($company, $customer, $ids, $label);
    }

    /**
     * Fremdkunde: Die Marketplace-Firma steht als Fremdkunde unter einem
     * Kunden (Partner) — der Partner bekommt die Rechnung und reicht sie an
     * den Endkunden weiter. Geprüft werden deshalb die Rechnungen des Partners.
     */
    private function fromForeignCustomer(Organization $organization, MarketplaceCompany $company, ?InvoiceLineSource $source): ?ContactMapping {
        $wanted = $company->normalizedName();
        if ($wanted === '') {
            return null;
        }

        // Exakte Namensgleichheit zuerst, sonst tokenbasiert („Auerswald" ↔
        // „Auerswald GmbH & Co. KG", „Urban Fliesen" ↔ „Fliesen Urban").
        $exact = [];
        $fuzzy = [];
        $candidates = ForeignCustomer::query()->withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->whereNull('archived_at')
            ->with('customer')
            ->get();
        foreach ($candidates as $foreign) {
            foreach ([$foreign->name, $foreign->company] as $name) {
                if (! is_string($name) || $name === '') {
                    continue;
                }
                if (MarketplaceCompany::normalizeName($name) === $wanted) {
                    $exact[$foreign->id] = $foreign;
                    break;
                }
                if (NameTokenMatcher::matches($name, $company->name)) {
                    $fuzzy[$foreign->id] = $foreign;
                }
            }
        }
        $matches = $exact !== [] ? $exact : $fuzzy;
        if ($matches === []) {
            return null;
        }

        // Mehrere Fremdkunden desselben Partners sind eindeutig; verschiedene Partner nicht.
        $partners = [];
        foreach ($matches as $foreign) {
            if ($foreign->customer instanceof Customer) {
                $partners[$foreign->customer->id] = $foreign->customer;
            }
        }
        if (count($partners) !== 1) {
            return null;
        }
        /** @var Customer $partner */
        $partner = array_values($partners)[0];

        $ids = $this->contactIdsForCustomer($organization, $partner, $source);
        if ($ids === []) {
            return new ContactMapping($company, $partner, [], ContactMapping::SOURCE_NONE, ['Partner ' . $partner->name . ' ohne Lexoffice-Kontakt'], 'über ' . $partner->name, $partner->name);
        }

        // Ist die Firma zugleich eigener Kunde (z. B. Thieme Transporte), zählen
        // auch dessen Rechnungen — der Vorrat wird je Kontakt geteilt.
        $detail = 'über ' . $partner->name . ($exact === [] ? ' · Name ähnlich' : '');
        $own = $this->matchCustomer($organization, $company);
        if ($own['customer'] instanceof Customer && $own['customer']->id !== $partner->id) {
            $ownIds = $this->contactIdsForCustomer($organization, $own['customer'], $source);
            if ($ownIds !== []) {
                $ids = array_values(array_unique(array_merge($ids, $ownIds)));
                $detail .= ' + eigener Kunde ' . $own['customer']->name;
            }
        }

        return new ContactMapping($company, $partner, $ids, ContactMapping::SOURCE_FOREIGN, [], $detail, $partner->name);
    }

    /**
     * Kunde per Sqid oder eindeutigem (normalisiertem) Namen/Firma.
     */
    private function customerByReference(Organization $organization, string $reference): ?Customer {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        $id = $this->sqids->decode(Customer::class, $reference);
        if ($id !== null) {
            $customer = Customer::query()->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereKey($id)
                ->first();
            if ($customer instanceof Customer) {
                return $customer;
            }
        }

        $wanted = MarketplaceCompany::normalizeName($reference);
        $hits = [];
        foreach (Customer::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->get(['id', 'name', 'company', 'lexoffice_contact_number']) as $customer) {
            foreach ([$customer->name, $customer->company] as $name) {
                if (is_string($name) && $name !== '' && MarketplaceCompany::normalizeName($name) === $wanted) {
                    $hits[$customer->id] = $customer;
                    break;
                }
            }
        }

        return count($hits) === 1 ? array_values($hits)[0] : null;
    }

    /**
     * Lexoffice-Kontakte eines Kunden: Verknüpfung, sonst Kundennummer, sonst
     * eindeutige Namenssuche.
     *
     * @return list<string>
     */
    private function contactIdsForCustomer(Organization $organization, Customer $customer, ?InvoiceLineSource $source): array {
        $ids = $this->contactIdsOf($organization, $customer);
        if ($ids !== [] || $source === null) {
            return $ids;
        }

        $number = trim((string) ($customer->lexoffice_contact_number ?? ''));
        if ($number !== '') {
            $hits = $this->safe(static fn(): array => $source->findContactsByNumber($number));
            if (count($hits) === 1) {
                return [$hits[0]['id']];
            }
        }

        $name = (string) ($customer->company ?: $customer->name);
        $wanted = MarketplaceCompany::normalizeName($name);
        $hits = $this->safe(static fn(): array => $source->findContactsByName($name));
        $exact = array_values(array_filter($hits, static fn(array $hit): bool => MarketplaceCompany::normalizeName($hit['name']) === $wanted));

        return count($exact) === 1 ? [$exact[0]['id']] : [];
    }

    /**
     * Fehler der Fremdsystem-Suche (Zugang, Netz) — die Zuordnung läuft ohne
     * sie weiter, der Bericht muss sie aber nennen, sonst sieht „—" nach
     * „gibt es nicht" aus.
     *
     * @return list<string>
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * @return array{customer: ?Customer, candidates: list<string>, detail: string}
     */
    private function matchCustomer(Organization $organization, MarketplaceCompany $company): array {
        $mapped = array_filter([
            'name' => $company->name,
            'company' => $company->name,
            'email' => $company->email,
        ], static fn(?string $value): bool => $value !== null && $value !== '');

        $result = $this->matcher->match($organization, $this->profile, $mapped);
        $candidates = $result->candidates();
        $best = $result->best();
        if ($best !== null && $best['model'] instanceof Customer) {
            $customer = $best['model'];
            $confidence = (string) $best['confidence'];
            $strong = in_array($confidence, [MatchStrategy::EXACT, MatchStrategy::LIKELY], true);
            $sameName = $this->sameName($company, $customer);
            if ($strong || $sameName) {
                $detail = $this->reasonLabels($best['reasons']);
                if (! $sameName) {
                    $detail .= ' · Name weicht ab';
                }

                return ['customer' => $customer, 'candidates' => [], 'detail' => $detail];
            }
        }

        $names = [];
        foreach ($candidates as $candidate) {
            $model = $candidate['model'];
            if ($model instanceof Customer) {
                $names[] = 'Kunde: ' . $model->name . ' (' . $this->reasonLabels($candidate['reasons']) . ')';
            }
        }

        return ['customer' => null, 'candidates' => $names, 'detail' => ''];
    }

    private function sameName(MarketplaceCompany $company, Customer $customer): bool {
        $wanted = $company->normalizedName();
        foreach ([$customer->company, $customer->name] as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }
            if (MarketplaceCompany::normalizeName($candidate) === $wanted || NameTokenMatcher::matches($candidate, $company->name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function reasonLabels(array $reasons): string {
        $labels = [];
        foreach ($reasons as $reason) {
            $labels[] = self::REASON_LABELS[$reason] ?? $reason;
        }

        return implode(', ', array_values(array_unique($labels)));
    }

    /**
     * @return list<string>
     */
    private function contactIdsOf(Organization $organization, Customer $customer): array {
        $ids = ExternalReference::query()
            ->forPlugin($organization, LexofficePlugin::ID, LexofficePlugin::EXT_TYPE_CONTACT)
            ->forReferenceable($customer)
            ->pluck('external_id')
            ->map(static fn(mixed $id): string => (string) $id)
            ->filter(static fn(string $id): bool => $id !== '')
            ->unique()
            ->all();

        return array_values($ids);
    }

    /**
     * @param  callable(): list<array{id: string, name: string}>  $call
     * @return list<array{id: string, name: string}>
     */
    private function safe(callable $call): array {
        try {
            return $call();
        } catch (Throwable $e) {
            if (! in_array($e->getMessage(), $this->errors, true)) {
                $this->errors[] = $e->getMessage();
            }

            return [];
        }
    }
}
