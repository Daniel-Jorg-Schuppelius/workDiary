<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingPartyCandidate.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Dto;

/** Kontakt aus einem Buchhaltungsprogramm als Kandidat für die Gegenpartei eines Eingangs (MVP-1226). */
final class IncomingPartyCandidate {
    public function __construct(
        public readonly string $pluginId,
        public readonly string $pluginLabel,
        public readonly string $externalId,
        public readonly string $name,
        public readonly ?string $number = null,
        public readonly ?string $vatId = null,
        public readonly ?string $email = null,
        public readonly ?string $street = null,
        public readonly ?string $zip = null,
        public readonly ?string $city = null,
        public readonly ?string $country = null,
        /** @var array<string, mixed>|null Hauptansprechpartner (`ContactPersons::normalize()`) */
        public readonly ?array $contact = null,
        /** @var list<array<string, mixed>> Ansprechpartner des Kontakts */
        public readonly array $persons = [],
    ) {}

    /** @return array<string, mixed> Cache-Form: der Cache hält nur Arrays, keine Objekte (CLAUDE.md „Cache“) */
    public function toArray(): array {
        return [
            'plugin_id' => $this->pluginId,
            'plugin_label' => $this->pluginLabel,
            'external_id' => $this->externalId,
            'name' => $this->name,
            'number' => $this->number,
            'vat_id' => $this->vatId,
            'email' => $this->email,
            'street' => $this->street,
            'zip' => $this->zip,
            'city' => $this->city,
            'country' => $this->country,
            'contact' => $this->contact,
            'persons' => $this->persons,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self {
        $text = static fn (string $key): ?string => isset($data[$key]) && $data[$key] !== '' ? (string) $data[$key] : null;

        return new self(
            pluginId: (string) ($data['plugin_id'] ?? ''),
            pluginLabel: (string) ($data['plugin_label'] ?? ''),
            externalId: (string) ($data['external_id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            number: $text('number'),
            vatId: $text('vat_id'),
            email: $text('email'),
            street: $text('street'),
            zip: $text('zip'),
            city: $text('city'),
            country: $text('country'),
            contact: is_array($data['contact'] ?? null) ? $data['contact'] : null,
            persons: is_array($data['persons'] ?? null) ? array_values($data['persons']) : [],
        );
    }

    /** Formularwert: Plugin und externe ID — die Stammdaten holt der Kern beim Übernehmen erneut (E4). */
    public function key(): string {
        return $this->pluginId . ':' . $this->externalId;
    }

    public function describe(): string {
        $parts = [$this->name];
        if ((string) $this->number !== '') {
            $parts[] = (string) __('Nr. :number', ['number' => $this->number]);
        }
        if ((string) $this->vatId !== '') {
            $parts[] = (string) $this->vatId;
        }
        if ((string) $this->city !== '') {
            $parts[] = trim((string) $this->zip . ' ' . $this->city);
        }

        return implode(' · ', $parts);
    }

    /** @return array<string, mixed> Felder für die Neuanlage (nur gefüllte) */
    public function partyFields(): array {
        $fields = array_filter([
            'name' => $this->name,
            'vat_id' => $this->vatId,
            'email' => $this->email,
            'street' => $this->street,
            'zip' => $this->zip,
            'city' => $this->city,
            'country' => $this->country,
        ], static fn (?string $value): bool => $value !== null && trim($value) !== '');
        if ($this->contact !== null) {
            $fields['contact'] = $this->contact;
        }
        if ($this->persons !== []) {
            $fields['contact_persons'] = $this->persons;
        }

        return $fields;
    }
}
