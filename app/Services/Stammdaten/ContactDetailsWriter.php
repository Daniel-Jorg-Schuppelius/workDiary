<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactDetailsWriter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Stammdaten;

use App\Models\Contacts\{ContactAddress, ContactBankAccount};
use App\Models\Contracts\ContactDetailsHolder;
use Illuminate\Database\Eloquent\Model;

/**
 * Schreibweg für Kontakt-Adresse/-Bankverbindung (Vollscan 2026-08-23, F8,
 * Entscheid E6): `contact_addresses`/`contact_bank_accounts` sind die EINE
 * Quelle für jede Partei mit {@see ContactDetailsHolder} (MVP-869); die
 * Inline-Spalten (nur customers/suppliers address_… & bank_…) füllt
 * ausschließlich {@see \App\Observers\ContactDetailsProjectionObserver} als
 * Lese-Projektion nach. Verschlüsselte Felder nie als '' speichern
 * (DecryptException — Memory „Leere encrypted-Strings").
 */
class ContactDetailsWriter {
    /**
     * Die Inline-Projektionsspalten auf customers/suppliers, wie sie in
     * Formularen und gemappten Import-Wertesätzen heißen.
     *
     * @var list<string>
     */
    public const array INLINE_FIELDS = [
        'address_street', 'address_zip', 'address_city', 'country',
        'bank_account_holder', 'bank_iban', 'bank_bic', 'bank_name',
    ];

    /** Inline-Spalte → Schlüssel von {@see writeAddress}. */
    private const array ADDRESS_MAP = [
        'address_street' => 'street',
        'address_zip' => 'zip',
        'address_city' => 'city',
        'country' => 'country',
    ];

    /**
     * Eingabenamen der Lieferanschrift (MVP-1689). Sie hat keine Projektion:
     * die Inline-Spalten bleiben die Rechnungsanschrift.
     *
     * @var list<string>
     */
    public const array SHIPPING_FIELDS = [
        'shipping_street', 'shipping_supplement', 'shipping_zip', 'shipping_city', 'shipping_country',
    ];

    /** Eingabename → Schlüssel von {@see writeShippingAddress}. */
    private const array SHIPPING_MAP = [
        'shipping_street' => 'street',
        'shipping_supplement' => 'supplement',
        'shipping_zip' => 'zip',
        'shipping_city' => 'city',
        'shipping_country' => 'country',
    ];

    /** Inline-Spalte → Schlüssel von {@see writeBankAccount}. */
    private const array BANK_MAP = [
        'bank_account_holder' => 'account_holder',
        'bank_iban' => 'iban',
        'bank_bic' => 'bic',
        'bank_name' => 'bank_name',
    ];

    /** Auswahlfelder der Zusammenführung: Rechnungsanschrift und Bankverbindung als Ganzes (MVP-1701). */
    public const string MERGE_BILLING_ADDRESS = 'billing_address';

    public const string MERGE_BANK_ACCOUNT = 'bank_account';

    /**
     * Nimmt die Inline-Kontaktfelder und die Lieferanschrift aus einem
     * Wertesatz heraus (Referenz wird um sie erleichtert) und gibt sie zurück —
     * damit ein Import den Rest wie bisher per fill()/create() schreiben kann,
     * die Kontaktdaten aber über {@see writeInline} in
     * contact_addresses/contact_bank_accounts landen.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function pullInline(array &$attributes): array {
        $names = [...self::INLINE_FIELDS, ...self::SHIPPING_FIELDS];
        $fields = array_intersect_key($attributes, array_flip($names));
        foreach ($names as $field) {
            unset($attributes[$field]);
        }

        return $fields;
    }

    /**
     * Teil-Update über die Inline-Spaltennamen: NUR übergebene Schlüssel
     * ändern sich, fehlende behalten den Stand der primären Adresse/Bank
     * (Altbestand ohne Satellit: den der Projektion). Importe und API liefern
     * nur die abweichenden Felder — ein Voll-Overwrite (wie im Formularpfad)
     * würde den Rest der Adresse leeren.
     *
     * @param  array<string, mixed>  $fields
     */
    public function writeInline(Model&ContactDetailsHolder $contact, array $fields): void {
        $address = $this->mapPresent($fields, self::ADDRESS_MAP);
        if ($address !== []) {
            $this->adoptProjectedAddress($contact);
            $primary = $contact->primaryAddress();
            $this->writeAddress($contact, $address + [
                'street' => $primary?->street,
                'supplement' => $primary?->supplement,
                'zip' => $primary?->zip,
                'city' => $primary?->city,
                'country' => $primary?->country_code,
            ]);
        }

        $bank = $this->mapPresent($fields, self::BANK_MAP);
        if ($bank !== []) {
            $this->adoptProjectedBankAccount($contact);
            $primary = $contact->primaryBankAccount();
            $this->writeBankAccount($contact, $bank + [
                'account_holder' => $primary?->account_holder,
                'iban' => $primary?->iban,
                'bic' => $primary?->bic,
                'bank_name' => $primary?->bank_name,
            ]);
        }

        $shipping = $this->mapPresent($fields, self::SHIPPING_MAP);
        if ($shipping !== []) {
            $current = $contact->shippingAddress();
            $this->writeShippingAddress($contact, $shipping + [
                'street' => $current?->street,
                'supplement' => $current?->supplement,
                'zip' => $current?->zip,
                'city' => $current?->city,
                'country' => $current?->country_code,
            ]);
        }
    }

    /**
     * Altbestand mit Projektion, aber ohne Satellit (vor MVP-869 angelegt oder am
     * Satelliten vorbei geschrieben): Satelliten aus der Projektion anlegen. Sonst
     * leerte ein Teil-Update die nicht gelieferten Felder, weil der neue Satellit
     * die Projektion überschreibt (MVP-1700).
     */
    public function adoptProjection(Model&ContactDetailsHolder $contact): void {
        $this->adoptProjectedAddress($contact);
        $this->adoptProjectedBankAccount($contact);
    }

    private function adoptProjectedAddress(Model&ContactDetailsHolder $contact): void {
        if ($contact->primaryAddress() !== null) {
            return;
        }
        $this->writeAddress($contact, [
            'street' => $this->projected($contact, 'address_street'),
            'zip' => $this->projected($contact, 'address_zip'),
            'city' => $this->projected($contact, 'address_city'),
            'country' => $this->projected($contact, 'country'),
        ]);
    }

    private function adoptProjectedBankAccount(Model&ContactDetailsHolder $contact): void {
        if ($contact->primaryBankAccount() !== null) {
            return;
        }
        $this->writeBankAccount($contact, [
            'account_holder' => $this->projected($contact, 'bank_account_holder'),
            'iban' => $this->projected($contact, 'bank_iban'),
            'bic' => $this->projected($contact, 'bank_bic'),
            'bank_name' => $this->projected($contact, 'bank_name'),
        ]);
    }

    /**
     * Zusammenführen (MVP-1701): die Satelliten der Quelle wandern zum Ziel, danach
     * trägt es genau eine primäre Rechnungsanschrift und eine primäre Bankverbindung —
     * die eigene, ausgewählt oder ohne eigene die der Quelle. Die andere bleibt als
     * weitere stehen (Auskunft, Buchhaltungsprogramm), eine gleichlautende der Quelle
     * entfällt. Die Projektion folgt der primären. Lieferanschriften ordnet der
     * Aufrufer vorher.
     *
     * @return bool true, wenn die Rechnungsanschrift der Quelle gilt
     */
    public function mergeInto(Model&ContactDetailsHolder $source, Model&ContactDetailsHolder $target, bool $preferSourceAddress, bool $preferSourceBank): bool {
        $this->adoptProjection($source);
        $this->adoptProjection($target);

        $targetAddress = $target->primaryAddress();
        $sourceAddress = $source->primaryAddress();
        if ($targetAddress !== null && $sourceAddress !== null && self::sameAddress($targetAddress, $sourceAddress)) {
            $sourceAddress->delete();
            $sourceAddress = null;
        }
        $address = $sourceAddress !== null && ($preferSourceAddress || $targetAddress === null) ? $sourceAddress : $targetAddress;

        $targetBank = $target->primaryBankAccount();
        $sourceBank = $source->primaryBankAccount();
        if ($targetBank !== null && $sourceBank !== null && self::sameBankAccount($targetBank, $sourceBank)) {
            $sourceBank->delete();
            $sourceBank = null;
        }
        $bank = $sourceBank !== null && ($preferSourceBank || $targetBank === null) ? $sourceBank : $targetBank;

        $this->moveSatellites($source, $target);
        $this->settlePrimary($target, $address);
        $this->settlePrimary($target, $bank);

        return $address !== null && $address->getKey() === $sourceAddress?->getKey();
    }

    /**
     * Hängt Anschriften und Bankverbindungen um. Die Fremd-ID einer Anschrift
     * (Lexoffice `billing-0` …) gehört zum Kontakt im Buchhaltungsprogramm; trägt das
     * Ziel dieselbe schon, verliert die der Quelle sie, damit der Abgleich eindeutig bleibt.
     */
    private function moveSatellites(Model $source, Model $target): void {
        $morph = $source->getMorphClass();
        $targetExternal = ContactAddress::query()->withoutGlobalScopes()
            ->where('addressable_type', $morph)->where('addressable_id', $target->getKey())
            ->whereNotNull('external_id')->get(['kind', 'external_id'])
            ->map(static fn (ContactAddress $a): string => $a->kind . '|' . $a->external_id)->all();
        $sourceAddresses = ContactAddress::query()->withoutGlobalScopes()
            ->where('addressable_type', $morph)->where('addressable_id', $source->getKey());
        foreach ((clone $sourceAddresses)->whereNotNull('external_id')->get(['id', 'kind', 'external_id']) as $address) {
            if (in_array($address->kind . '|' . $address->external_id, $targetExternal, true)) {
                ContactAddress::query()->withoutGlobalScopes()->whereKey($address->id)->update(['external_id' => null]);
            }
        }
        $sourceAddresses->update(['addressable_id' => $target->getKey()]);
        ContactBankAccount::query()->withoutGlobalScopes()
            ->where('accountable_type', $morph)->where('accountable_id', $source->getKey())
            ->update(['accountable_id' => $target->getKey()]);
    }

    /** Genau ein primärer Satellit je Partei; das Speichern des gewählten löst die Projektion aus. */
    private function settlePrimary(Model&ContactDetailsHolder $party, ContactAddress|ContactBankAccount|null $keep): void {
        if ($keep === null) {
            return;
        }
        $others = $keep instanceof ContactAddress
            ? $party->addresses()->where('kind', '!=', ContactAddress::KIND_SHIPPING)
            : $party->bankAccounts();
        $others->where('is_primary', true)->whereKeyNot($keep->getKey())->update(['is_primary' => false]);

        // refresh(): nach dem Umhängen zeigt der Satellit auf das Ziel.
        $keep->refresh()->forceFill(['is_primary' => true])->save();
    }

    private static function sameAddress(ContactAddress $a, ContactAddress $b): bool {
        foreach (['street', 'supplement', 'zip', 'city', 'country_code'] as $field) {
            if (mb_strtolower(trim((string) $a->getAttribute($field))) !== mb_strtolower(trim((string) $b->getAttribute($field)))) {
                return false;
            }
        }

        return true;
    }

    private static function sameBankAccount(ContactBankAccount $a, ContactBankAccount $b): bool {
        foreach (['account_holder', 'iban', 'bic', 'bank_name'] as $field) {
            if (trim((string) $a->getAttribute($field)) !== trim((string) $b->getAttribute($field))) {
                return false;
            }
        }

        return true;
    }

    /** Projektionsspalte, sofern die Partei sie führt (nur Kunde und Lieferant). */
    private function projected(Model $contact, string $column): ?string {
        if (! array_key_exists($column, $contact->getAttributes())) {
            return null;
        }
        $value = $contact->getAttribute($column);

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, string>  $map
     * @return array<string, ?string>
     */
    private function mapPresent(array $fields, array $map): array {
        $out = [];
        foreach ($map as $inline => $key) {
            if (array_key_exists($inline, $fields)) {
                $out[$key] = $fields[$inline] === null ? null : (string) $fields[$inline];
            }
        }

        return $out;
    }

    /**
     * Primäradresse upserten. Alles leer + keine Adresse ⇒ no-op;
     * alles leer + Adresse vorhanden ⇒ Felder werden geleert (null).
     *
     * @param  array{street?: ?string, supplement?: ?string, zip?: ?string, city?: ?string, country?: ?string}  $fields
     */
    public function writeAddress(Model&ContactDetailsHolder $contact, array $fields): void {
        $values = [
            'street' => $this->nullIfBlank($fields['street'] ?? null),
            'supplement' => $this->nullIfBlank($fields['supplement'] ?? null),
            'zip' => $this->nullIfBlank($fields['zip'] ?? null),
            'city' => $this->nullIfBlank($fields['city'] ?? null),
            'country_code' => $this->nullIfBlank($fields['country'] ?? null),
        ];

        $primary = $contact->primaryAddress();
        if ($primary === null && array_filter($values) === []) {
            return;
        }

        if ($primary === null) {
            $contact->addresses()->create($values + [
                'organization_id' => $contact->getAttribute('organization_id'),
                'kind' => $contact->contactAddressKind(),
                'is_primary' => true,
            ]);

            return;
        }

        $primary->fill($values)->save();
    }

    /**
     * Lieferanschrift upserten (MVP-1689). Alles leer bedeutet „keine
     * Lieferanschrift“: vorhandene Lieferanschriften entfallen, auch weitere aus
     * dem Buchhaltungsprogramm. Die Projektion der Rechnungsanschrift bleibt unberührt.
     *
     * @param  array{street?: ?string, supplement?: ?string, zip?: ?string, city?: ?string, country?: ?string}  $fields
     */
    public function writeShippingAddress(Model&ContactDetailsHolder $contact, array $fields): void {
        $values = [
            'street' => $this->nullIfBlank($fields['street'] ?? null),
            'supplement' => $this->nullIfBlank($fields['supplement'] ?? null),
            'zip' => $this->nullIfBlank($fields['zip'] ?? null),
            'city' => $this->nullIfBlank($fields['city'] ?? null),
            'country_code' => $this->nullIfBlank($fields['country'] ?? null),
        ];
        if ($values['country_code'] !== null) {
            $values['country_code'] = strtoupper($values['country_code']);
        }

        if (array_filter($values) === []) {
            foreach ($contact->addresses()->where('kind', ContactAddress::KIND_SHIPPING)->get() as $address) {
                $address->delete();
            }

            return;
        }

        $current = $contact->shippingAddress();
        if ($current === null) {
            $contact->addresses()->create($values + [
                'organization_id' => $contact->getAttribute('organization_id'),
                'kind' => ContactAddress::KIND_SHIPPING,
                'is_primary' => false,
            ]);

            return;
        }

        $current->fill($values)->save();
    }

    /**
     * Primäre Bankverbindung upserten (Semantik wie {@see writeAddress}).
     *
     * @param  array{account_holder?: ?string, iban?: ?string, bic?: ?string, bank_name?: ?string}  $fields
     */
    public function writeBankAccount(Model&ContactDetailsHolder $contact, array $fields): void {
        $values = [
            'account_holder' => $this->nullIfBlank($fields['account_holder'] ?? null),
            'iban' => $this->nullIfBlank($fields['iban'] ?? null),
            'bic' => $this->nullIfBlank($fields['bic'] ?? null),
            'bank_name' => $this->nullIfBlank($fields['bank_name'] ?? null),
        ];

        $primary = $contact->primaryBankAccount();
        if ($primary === null && array_filter($values) === []) {
            return;
        }

        if ($primary === null) {
            $contact->bankAccounts()->create($values + [
                'organization_id' => $contact->getAttribute('organization_id'),
                'is_primary' => true,
            ]);

            return;
        }

        $primary->fill($values)->save();
    }

    private function nullIfBlank(?string $value): ?string {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
