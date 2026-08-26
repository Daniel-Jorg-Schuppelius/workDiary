<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactPersonSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\{Customer, Organization, Supplier};
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\Concerns\ResolvesImportReferences;
use Throwable;

/**
 * Ansprechpartner aus einem Vorsystem (MVP-707, Vollscan H20).
 *
 * Zielmodell: workDiary führt Ansprechpartner NICHT als eigenes Personen-
 * Modell, sondern als JSON-Liste `contact_persons` (name/email/phone/primary)
 * am Kunden bzw. Lieferanten ({@see \App\Models\Concerns\HasContactAndBankDetails::primaryContact()});
 * `ExternalContact` ist ein org-weites Profil für externe Beteiligte (Feature
 * 033) und `ContactAddress` eine Anschrift — beide keine Kunden-Personen.
 * Die Spec schreibt deshalb in die JSON-Liste der Partei (max. 20 wie das
 * Formular). Idempotenz je Partei über E-Mail (case-insensitiv), sonst Name.
 */
class ContactPersonSpec extends AbstractEntitySpec {
    use ResolvesImportReferences;

    public const PARTY_CUSTOMER = 'customer';

    public const PARTY_SUPPLIER = 'supplier';

    /** Deckel der Formular-Validierung ({@see \App\Http\Requests\Concerns\PartyFormFields}). */
    public const MAX_PERSONS = 20;

    public function entity(): ImportEntity {
        return ImportEntity::ContactPersons;
    }

    public function columns(): array {
        return ['party_type', 'party_number', 'name', 'email', 'phone', 'primary'];
    }

    public function requiredColumns(): array {
        return ['party_number', 'name'];
    }

    public function headerAliases(): array {
        return [
            'typ' => 'party_type',
            'art' => 'party_type',
            'partei' => 'party_type',
            'kundennummer' => 'party_number',
            'lieferantennummer' => 'party_number',
            'nummer' => 'party_number',
            'ansprechpartner' => 'name',
            'e-mail' => 'email',
            'mail' => 'email',
            'telefon' => 'phone',
            'tel' => 'phone',
            'hauptkontakt' => 'primary',
            'primär' => 'primary',
            'primaer' => 'primary',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'party_type' => $this->partyType($this->trimmedString($raw)),
                'email' => $this->lowerOrNull($this->trimmedString($raw)),
                'primary' => $raw === null || trim((string) $raw) === '' ? null : $this->boolish($raw),
                default => $this->trimmedString($raw),
            };
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];

        if (! in_array($row['party_type'] ?? null, [self::PARTY_CUSTOMER, self::PARTY_SUPPLIER], true)) {
            $issues[] = $this->formatIssue('party_type', (string) __('import.error.format.enum'));
        }

        if (($row['party_number'] ?? null) === null) {
            $issues[] = $this->requiredIssue('party_number');
        } elseif ($this->party($organization, $row) === null) {
            $issues[] = $this->partyIssue($row);
        }

        if (($row['name'] ?? null) === null) {
            $issues[] = $this->requiredIssue('name');
        } elseif (mb_strlen((string) $row['name']) > 200) {
            $issues[] = $this->tooLongIssue('name', 200);
        }

        if (! empty($row['email']) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $issues[] = $this->formatIssue('email', (string) __('import.error.format.email'));
        }
        if (! empty($row['phone']) && mb_strlen((string) $row['phone']) > 64) {
            $issues[] = $this->tooLongIssue('phone', 64);
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            $party = $this->party($organization, $row);
            if ($party === null) {
                return [ImportOutcome::Failed, $this->partyIssue($row)];
            }

            /** @var list<array{name?: string, email?: string, phone?: string, primary?: bool}> $persons */
            $persons = array_values((array) ($party->contact_persons ?? []));
            [$index, $matchedBy] = $this->matchIndex($persons, $row);

            if ($index === null && count($persons) >= self::MAX_PERSONS) {
                return [
                    ImportOutcome::Failed,
                    new ValidationIssue(ImportErrorCode::OutOfRange, 'name', (string) __('import.error.outOfRange.contactPersons', ['max' => self::MAX_PERSONS])),
                ];
            }

            $person = $index !== null ? $persons[$index] : [];
            // Namenstreffer (case-insensitiv) behält die gepflegte Schreibweise.
            if ($matchedBy !== 'name') {
                $person['name'] = (string) $row['name'];
            }
            foreach (['email', 'phone'] as $field) {
                if (($row[$field] ?? null) !== null) {
                    $person[$field] = (string) $row[$field];
                }
            }

            $primary = $row['primary'] ?? null;
            // Erster Ansprechpartner einer Partei wird Hauptkontakt, sofern nicht widersprochen.
            if ($primary === null && $persons === []) {
                $primary = true;
            }
            if ($primary === true) {
                foreach ($persons as $i => $existing) {
                    $persons[$i]['primary'] = false;
                }
                $person['primary'] = true;
            } elseif ($primary === false) {
                $person['primary'] = false;
            } else {
                $person['primary'] = (bool) ($person['primary'] ?? false);
            }

            if ($index !== null) {
                $persons[$index] = $person;
            } else {
                $persons[] = $person;
            }

            $party->contact_persons = $persons;
            $party->save();

            return [$index !== null ? ImportOutcome::Updated : ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage())];
        }
    }

    private function partyType(?string $value): string {
        $v = $value === null ? '' : mb_strtolower($value);

        return match ($v) {
            '', 'customer', 'kunde', 'k' => self::PARTY_CUSTOMER,
            'supplier', 'lieferant', 'l' => self::PARTY_SUPPLIER,
            default => $v,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function party(Organization $organization, array $row): Customer|Supplier|null {
        $number = $row['party_number'] ?? null;

        return match ($row['party_type'] ?? null) {
            self::PARTY_CUSTOMER => $this->customerByNumber($organization, $number === null ? null : (string) $number),
            self::PARTY_SUPPLIER => $this->supplierByNumber($organization, $number === null ? null : (string) $number),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function partyIssue(array $row): ValidationIssue {
        $key = ($row['party_type'] ?? null) === self::PARTY_SUPPLIER ? 'supplier' : 'customer';

        return $this->fkIssue('party_number', $key, (string) ($row['party_number'] ?? ''));
    }

    /**
     * Treffer über E-Mail (case-insensitiv), sonst über den Namen.
     *
     * @param  list<array{name?: string, email?: string, phone?: string, primary?: bool}>  $persons
     * @param  array<string, mixed>  $row
     * @return array{0: ?int, 1: 'email'|'name'|null}
     */
    private function matchIndex(array $persons, array $row): array {
        $email = $row['email'] ?? null;
        if ($email !== null) {
            foreach ($persons as $i => $person) {
                if (mb_strtolower((string) ($person['email'] ?? '')) === $email) {
                    return [$i, 'email'];
                }
            }
        }
        $name = mb_strtolower((string) $row['name']);
        foreach ($persons as $i => $person) {
            if (mb_strtolower((string) ($person['name'] ?? '')) === $name) {
                return [$i, 'name'];
            }
        }

        return [null, null];
    }
}
