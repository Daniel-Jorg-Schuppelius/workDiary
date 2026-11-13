<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HasContactAndBankDetails.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Contacts\{ContactAddress, ContactBankAccount};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Kontaktdaten-Konvention für Parteien (MVP-869): der Primärkontakt
 * (E-Mail, Telefon, Ansprechpartner) steht inline am Modell, Adressen und
 * Bankverbindungen liegen als Satelliten in `contact_addresses` und
 * `contact_bank_accounts`. Kunde und Lieferant tragen zusätzlich eine
 * Projektion der Primärwerte in Inline-Spalten (`address_*`, `bank_*`) für
 * Listen und den Lexoffice-Abgleich — die Leser hier greifen zuerst auf den
 * Satelliten zu und fallen nur dort auf die Projektion zurück.
 *
 * @mixin Model
 */
trait HasContactAndBankDetails {
    /** Satelliten gehen mit der Partei; weiche Löschung behält sie. */
    public static function bootHasContactAndBankDetails(): void {
        static::deleted(static function (Model $party): void {
            if (method_exists($party, 'isForceDeleting') && ! $party->isForceDeleting()) {
                return;
            }
            $alias = $party->getMorphClass();
            ContactAddress::query()->withoutGlobalScopes()
                ->where('addressable_type', $alias)->where('addressable_id', $party->getKey())->delete();
            ContactBankAccount::query()->withoutGlobalScopes()
                ->where('accountable_type', $alias)->where('accountable_id', $party->getKey())->delete();
        });
    }

    /** Art einer neu angelegten Primäradresse; Kunde/Lieferant überschreiben mit `billing` (Lexoffice). */
    public function contactAddressKind(): string {
        return ContactAddress::KIND_DEFAULT;
    }

    /** @return MorphMany<ContactAddress, $this> */
    public function addresses(): MorphMany {
        return $this->morphMany(ContactAddress::class, 'addressable');
    }

    /** @return MorphMany<ContactBankAccount, $this> */
    public function bankAccounts(): MorphMany {
        return $this->morphMany(ContactBankAccount::class, 'accountable');
    }

    /** Rechnungs- bzw. Postanschrift; eine Lieferanschrift ist nie die primäre (MVP-1689). */
    public function primaryAddress(): ?ContactAddress {
        return $this->addresses()->where('kind', '!=', ContactAddress::KIND_SHIPPING)->where('is_primary', true)->first()
            ?? $this->addresses()->where('kind', '!=', ContactAddress::KIND_SHIPPING)->orderBy('id')->first();
    }

    /** Abweichende Lieferanschrift (MVP-1689); mehrere aus dem Buchhaltungsprogramm: die erste. */
    public function shippingAddress(): ?ContactAddress {
        return $this->addresses()->where('kind', ContactAddress::KIND_SHIPPING)
            ->orderByDesc('is_primary')->orderBy('id')->first();
    }

    /**
     * Lieferanschrift unter den Eingabenamen des Formulars, leer ohne Lieferanschrift.
     *
     * @return array{shipping_street: ?string, shipping_supplement: ?string, shipping_zip: ?string, shipping_city: ?string, shipping_country: ?string}
     */
    public function shippingAddressFields(): array {
        $shipping = $this->shippingAddress();

        return [
            'shipping_street' => $shipping?->street,
            'shipping_supplement' => $shipping?->supplement,
            'shipping_zip' => $shipping?->zip,
            'shipping_city' => $shipping?->city,
            'shipping_country' => $shipping?->country_code,
        ];
    }

    /** @return list<string> Zeilen der Lieferanschrift für die Anzeige, leer ohne Lieferanschrift. */
    public function shippingAddressLines(): array {
        $shipping = $this->shippingAddress();
        if ($shipping === null) {
            return [];
        }
        $lines = [
            trim((string) $shipping->street),
            trim((string) $shipping->supplement),
            trim(trim((string) $shipping->zip) . ' ' . trim((string) $shipping->city)),
            trim((string) $shipping->country_code),
        ];

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    public function primaryBankAccount(): ?ContactBankAccount {
        return $this->bankAccounts()->where('is_primary', true)->first()
            ?? $this->bankAccounts()->first();
    }

    /**
     * Postanschrift (Satellit vor Projektion).
     *
     * @return array{has_any: bool, street: ?string, supplement: ?string, zip: ?string, city: ?string, country: ?string}
     */
    public function postalAddress(): array {
        $primary = $this->primaryAddress();
        $address = [
            'street' => $primary->street ?? $this->inlineString('address_street'),
            'supplement' => $primary?->supplement,
            'zip' => $primary->zip ?? $this->inlineString('address_zip'),
            'city' => $primary->city ?? $this->inlineString('address_city'),
            'country' => $primary->country_code ?? $this->inlineString('country'),
        ];

        return ['has_any' => array_filter([$address['street'], $address['zip'], $address['city']]) !== []] + $address;
    }

    /** @return list<string> Zeilen „Straße", „PLZ Ort" für Anzeige und Druck (ohne Leerzeilen). */
    public function postalAddressLines(): array {
        $address = $this->postalAddress();
        $lines = [trim((string) $address['street']), trim((string) $address['supplement']), trim(trim((string) $address['zip']) . ' ' . trim((string) $address['city']))];

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    /**
     * Aggregierte Bankverbindung (Satellit vor Projektion).
     *
     * @return array{has_any: bool, holder: ?string, iban: ?string, bic: ?string, bank: ?string}
     */
    public function bankDetails(): array {
        $primary = $this->primaryBankAccount();
        $bank = [
            'holder' => $primary->account_holder ?? $this->inlineString('bank_account_holder'),
            'iban' => $primary->iban ?? $this->inlineString('bank_iban'),
            'bic' => $primary->bic ?? $this->inlineString('bank_bic'),
            'bank' => $primary->bank_name ?? $this->inlineString('bank_name'),
        ];

        return ['has_any' => array_filter($bank) !== []] + $bank;
    }

    /**
     * Primärer Ansprechpartner, gemerged mit den Inline-Einzelfeldern.
     *
     * @return array{name: ?string, email: ?string, phone: ?string}
     */
    public function primaryContact(): array {
        $persons = $this->getAttribute('contact_persons');
        $persons = is_array($persons) ? $persons : [];
        $primary = collect($persons)->firstWhere('primary', true) ?? ($persons[0] ?? []);

        return [
            'name' => $primary['name'] ?? $this->inlineString('contact_name'),
            'email' => $primary['email'] ?? $this->inlineString('email'),
            'phone' => $primary['phone'] ?? ($this->inlineString('phone') ?? $this->inlineString('mobile')),
        ];
    }

    /** Inline-Spalte, sofern das Modell sie trägt (Projektion) — sonst null. */
    private function inlineString(string $attribute): ?string {
        if (! array_key_exists($attribute, $this->getAttributes())) {
            return null;
        }
        $value = $this->getAttribute($attribute);
        if ($value === null || ! is_scalar($value)) {
            return null;
        }
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
