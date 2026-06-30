<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CustomerSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\Organization;
use App\Services\Import\{ImportOutcome, InboxFirstSpec, ValidationIssue};
use App\Services\Import\Specs\Concerns\DedupsAndStages;
use App\Services\Integration\Profiles\CustomerMatchProfile;
use Throwable;

/**
 * CSV-Spezifikation für den Kunden-Import (MVP-049).
 *
 * Fachlicher Schlüssel zur Idempotenz: `number` (Kundennummer) je
 * Mandant. Ohne Nummer wird stets neu angelegt; doppelte Namen sind
 * erlaubt, doppelte Nummern werden geupdatet.
 */
class CustomerSpec extends AbstractEntitySpec implements InboxFirstSpec {
    use DedupsAndStages;

    public function entity(): ImportEntity {
        return ImportEntity::Customers;
    }

    public function columns(): array {
        return [
            'name',
            'number',
            'company',
            'vat_id',
            'contact_name',
            'email',
            'phone',
            'mobile',
            'fax',
            'homepage',
            'address',
            'address_street',
            'address_zip',
            'address_city',
            'country',
            'currency',
            'hourly_rate',
            'internal_rate',
            'comment',
            'invoice_text',
            'billable',
            'external_id',
        ];
    }

    public function requiredColumns(): array {
        return ['name'];
    }

    public function headerAliases(): array {
        return [
            'kunde' => 'name',
            'fremd-id' => 'external_id',
            'fremdid' => 'external_id',
            'externe-id' => 'external_id',
            'quell-id' => 'external_id',
            'nummer' => 'number',
            'kundennummer' => 'number',
            'firma' => 'company',
            'ust-idnr.' => 'vat_id',
            'ustid' => 'vat_id',
            'ansprechpartner' => 'contact_name',
            'e-mail' => 'email',
            'telefon' => 'phone',
            'mobil' => 'mobile',
            'website' => 'homepage',
            'adresse' => 'address',
            'street' => 'address_street',
            'straße' => 'address_street',
            'strasse' => 'address_street',
            'zip' => 'address_zip',
            'plz' => 'address_zip',
            'city' => 'address_city',
            'ort' => 'address_city',
            'land' => 'country',
            'währung' => 'currency',
            'waehrung' => 'currency',
            'stundensatz' => 'hourly_rate',
            'notiz' => 'comment',
            'rechnungstext' => 'invoice_text',
            'abrechenbar' => 'billable',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'billable' => $raw === null || $raw === '' ? null : $this->boolish($raw),
                'hourly_rate', 'internal_rate' => $this->decimal($this->trimmedString($raw)),
                'country', 'currency' => $this->upperOrNull($this->trimmedString($raw)),
                default => $this->trimmedString($raw),
            };
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];

        if (($row['name'] ?? null) === null) {
            $issues[] = $this->requiredIssue('name');
        } elseif (mb_strlen((string) $row['name']) > 255) {
            $issues[] = $this->tooLongIssue('name', 255);
        }

        if (! empty($row['email']) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $issues[] = $this->formatIssue('email', (string) __('import.error.format.email'));
        }

        foreach (['number' => 64, 'company' => 255, 'vat_id' => 64, 'address_zip' => 16] as $f => $max) {
            if (! empty($row[$f]) && mb_strlen((string) $row[$f]) > $max) {
                $issues[] = $this->tooLongIssue($f, $max);
            }
        }

        if (! empty($row['country']) && ! preg_match('/^[A-Z]{2,3}$/', (string) $row['country'])) {
            $issues[] = $this->formatIssue('country', (string) __('import.error.format.country'));
        }
        if (! empty($row['currency']) && ! preg_match('/^[A-Z]{3}$/', (string) $row['currency'])) {
            $issues[] = $this->formatIssue('currency', (string) __('import.error.format.currency'));
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        return $this->run($row, $organization, false);
    }

    public function upsertOrStage(array $row, Organization $organization): array {
        return $this->run($row, $organization, true);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: ImportOutcome, 1: ?ValidationIssue}
     */
    private function run(array $row, Organization $organization, bool $inboxFirst): array {
        try {
            return $this->resolveImport(
                $organization,
                $this->payload($row, $organization),
                app(CustomerMatchProfile::class),
                $this->entity()->value,
                $inboxFirst,
            );
        } catch (Throwable $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage())];
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function payload(array $row, Organization $organization): array {
        $payload = array_filter($row, static fn($v): bool => $v !== null);
        $payload['organization_id'] = $organization->id;
        $payload['currency'] ??= 'EUR';

        return $payload;
    }
}
