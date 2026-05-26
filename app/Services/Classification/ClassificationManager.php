<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationManager.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Classification;

use App\Enums\Classification\ClassificationDomain;
use App\Exceptions\ClassificationValidationException;
use App\Models\Classification;
use App\Models\Concerns\Auditable;
use Illuminate\Support\Facades\DB;

/**
 * Org-Pflege für Klassifikationen (MVP-031).
 *
 * Verantwortlichkeiten:
 *  - Erstellen / Aktualisieren / Reaktivieren von Org-Werten.
 *  - Override für Plattform-Defaults (duplizieren in Org-Scope).
 *  - Org-lokales Deaktivieren eines Plattform-Defaults via leerem Override
 *    mit active=false.
 *  - Reorder (sort_order in 10er-Schritten).
 *  - Idempotenter CSV-Import (max. 1000 Zeilen).
 *  - Löschen mit Referenz-Schutz (HTTP 409 equiv.).
 *
 * Audit-Events (created/updated/deactivated/...) entstehen über den
 * {@see Auditable}-Trait am Modell. Der Cache des
 * {@see ClassificationResolver} wird bei jeder Mutation invalidiert.
 */
class ClassificationManager {
    public const CODE_REGEX = '/^[a-z][a-z0-9_]{1,58}$/';

    public const MAX_LABEL_LENGTH = 180;

    public const COLOR_REGEX = '/^#[0-9a-fA-F]{6}$/';

    public const SORT_STEP = 10;

    public const IMPORT_MAX_ROWS = 1000;

    /**
     * Registry für Referenz-Schutz beim Löschen.
     * Map ['domain' => [ ['table' => 'diary_entries', 'column' => 'entry_type_classification_id'], ... ] ].
     *
     * @var array<string, list<array{table: string, column: string}>>
     */
    private array $referenceRegistry = [];

    public function __construct(
        private readonly ClassificationResolver $resolver,
    ) {}

    /**
     * Registriert eine FK-Referenz für den Löschschutz.
     */
    public function registerReference(ClassificationDomain $domain, string $table, string $column): void {
        $this->referenceRegistry[$domain->value][] = ['table' => $table, 'column' => $column];
    }

    /**
     * Org-Wert anlegen (organization_id wird gesetzt).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createForOrganization(int $organizationId, ClassificationDomain $domain, array $attributes): Classification {
        $this->validateAttributes($attributes);

        $code = (string) $attributes['code'];

        $exists = Classification::query()
            ->where('organization_id', $organizationId)
            ->where('domain', $domain->value)
            ->where('code', $code)
            ->exists();
        if ($exists) {
            throw ClassificationValidationException::duplicate($code);
        }

        $payload = [
            'organization_id' => $organizationId,
            'domain' => $domain->value,
            'code' => $code,
            'label' => (string) $attributes['label'],
            'sort_order' => (int) ($attributes['sort_order'] ?? 100),
            'color_hex' => $attributes['color_hex'] ?? null,
            'icon' => $attributes['icon'] ?? null,
            'active' => (bool) ($attributes['active'] ?? true),
            'deprecated_at' => (bool) ($attributes['active'] ?? true) ? null : now(),
            'description' => $attributes['description'] ?? null,
        ];

        $row = Classification::query()->create($payload);

        $this->resolver->forget($organizationId, $domain);

        return $row->refresh();
    }

    /**
     * Aktualisiert einen Org-Wert; Plattform-Defaults dürfen nicht über diese
     * Methode editiert werden (eigene platform.manage-Permission).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Classification $classification, array $attributes): Classification {
        if ($classification->isPlatformDefault()) {
            throw ClassificationValidationException::platformProtected();
        }

        if (array_key_exists('label', $attributes)) {
            $label = (string) $attributes['label'];
            if ($label === '' || strlen($label) > self::MAX_LABEL_LENGTH) {
                throw ClassificationValidationException::invalidLabel();
            }
        }
        if (
            array_key_exists('color_hex', $attributes) && $attributes['color_hex'] !== null
            && preg_match(self::COLOR_REGEX, (string) $attributes['color_hex']) !== 1
        ) {
            throw ClassificationValidationException::invalidColor((string) $attributes['color_hex']);
        }

        $update = array_intersect_key($attributes, array_flip([
            'label',
            'sort_order',
            'color_hex',
            'icon',
            'description',
        ]));

        $classification->fill($update);

        if (array_key_exists('active', $attributes)) {
            $next = (bool) $attributes['active'];
            $classification->active = $next;
            $classification->deprecated_at = $next ? null : now();
        }

        $classification->save();

        $this->resolver->forget($classification->organization_id, $classification->domain);

        return $classification->refresh();
    }

    /**
     * Erstellt einen Org-Override eines Plattform-Default-Codes.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function overridePlatformDefault(int $organizationId, Classification $platformDefault, array $overrides = []): Classification {
        if (! $platformDefault->isPlatformDefault()) {
            throw ClassificationValidationException::platformProtected();
        }

        $payload = array_merge([
            'code' => $platformDefault->code,
            'label' => $overrides['label'] ?? $platformDefault->label,
            'sort_order' => $overrides['sort_order'] ?? $platformDefault->sort_order,
            'color_hex' => $overrides['color_hex'] ?? $platformDefault->color_hex,
            'icon' => $overrides['icon'] ?? $platformDefault->icon,
            'description' => $overrides['description'] ?? $platformDefault->description,
            'active' => $overrides['active'] ?? true,
        ], $overrides);

        $existing = Classification::query()
            ->where('organization_id', $organizationId)
            ->where('domain', $platformDefault->domain->value)
            ->where('code', $platformDefault->code)
            ->first();

        if ($existing instanceof Classification) {
            return $this->update($existing, $payload);
        }

        return $this->createForOrganization($organizationId, $platformDefault->domain, $payload);
    }

    /**
     * Deaktiviert einen Plattform-Default org-lokal über einen Override
     * mit `active = false`.
     */
    public function deactivatePlatformDefaultForOrganization(int $organizationId, Classification $platformDefault): Classification {
        return $this->overridePlatformDefault($organizationId, $platformDefault, ['active' => false]);
    }

    public function deactivate(Classification $classification): Classification {
        return $this->update($classification, ['active' => false]);
    }

    public function reactivate(Classification $classification): Classification {
        return $this->update($classification, ['active' => true]);
    }

    /**
     * Setzt die Reihenfolge in 10er-Schritten gemäß übergebener ID-Liste.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorder(int $organizationId, ClassificationDomain $domain, array $orderedIds): void {
        $sort = 0;
        foreach ($orderedIds as $id) {
            $sort += self::SORT_STEP;
            Classification::query()
                ->where('organization_id', $organizationId)
                ->where('domain', $domain->value)
                ->whereKey($id)
                ->update(['sort_order' => $sort, 'updated_at' => now()]);

            $row = Classification::query()->whereKey($id)->first();
            if ($row instanceof Classification) {
                $row->audit('classification.sortChanged', ['sort_order' => $sort]);
            }
        }

        $this->resolver->forget($organizationId, $domain);
    }

    /**
     * Idempotenter CSV-Import — bestehende Codes werden aktualisiert.
     *
     * @param  iterable<int, array{domain?: string, code?: string, label?: string, sort_order?: int|string|null, color_hex?: string|null, icon?: string|null}>  $rows
     * @return array{created: int, updated: int}
     */
    public function importCsv(int $organizationId, iterable $rows): array {
        $created = 0;
        $updated = 0;
        $touchedDomains = [];
        $line = 0;

        $buffer = is_array($rows) ? $rows : iterator_to_array($rows);
        if (count($buffer) > self::IMPORT_MAX_ROWS) {
            throw ClassificationValidationException::importTooLarge(count($buffer), self::IMPORT_MAX_ROWS);
        }

        DB::transaction(function () use ($organizationId, $buffer, &$created, &$updated, &$touchedDomains, &$line): void {
            foreach ($buffer as $row) {
                $line++;
                $domainValue = isset($row['domain']) ? (string) $row['domain'] : '';
                $domain = ClassificationDomain::tryFrom($domainValue);
                if ($domain === null) {
                    throw ClassificationValidationException::importInvalid($line, "Unbekannte Domain '{$domainValue}'");
                }

                $attrs = [
                    'code' => $row['code'] ?? '',
                    'label' => $row['label'] ?? '',
                    'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : 100,
                    'color_hex' => $row['color_hex'] ?? null,
                    'icon' => $row['icon'] ?? null,
                ];
                $this->validateAttributes($attrs);

                $existing = Classification::query()
                    ->where('organization_id', $organizationId)
                    ->where('domain', $domain->value)
                    ->where('code', $attrs['code'])
                    ->first();

                if ($existing instanceof Classification) {
                    $rowModel = $this->update($existing, $attrs);
                    $rowModel->audit('classification.imported', ['mode' => 'updated']);
                    $updated++;
                } else {
                    $rowModel = $this->createForOrganization($organizationId, $domain, $attrs);
                    $rowModel->audit('classification.imported', ['mode' => 'created']);
                    $created++;
                }

                $touchedDomains[$domain->value] = $domain;
            }
        });

        foreach ($touchedDomains as $domain) {
            $this->resolver->forget($organizationId, $domain);
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Löscht einen Org-Klassifikationswert. Plattform-Defaults sind geschützt
     * (nur platform.manage darf sie löschen — diese Methode lehnt sie ab).
     */
    public function delete(Classification $classification): void {
        if ($classification->isPlatformDefault()) {
            throw ClassificationValidationException::platformProtected();
        }

        if ($this->isReferenced($classification)) {
            throw ClassificationValidationException::referenced();
        }

        $orgId = $classification->organization_id;
        $domain = $classification->domain;

        $classification->delete();

        $this->resolver->forget($orgId, $domain);
    }

    public function isReferenced(Classification $classification): bool {
        $refs = $this->referenceRegistry[$classification->domain->value] ?? [];
        foreach ($refs as $ref) {
            $exists = DB::table($ref['table'])->where($ref['column'], $classification->id)->exists();
            if ($exists) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validateAttributes(array $attributes): void {
        $code = isset($attributes['code']) ? (string) $attributes['code'] : '';
        if (preg_match(self::CODE_REGEX, $code) !== 1) {
            throw ClassificationValidationException::invalidCode($code);
        }

        $label = isset($attributes['label']) ? (string) $attributes['label'] : '';
        if ($label === '' || strlen($label) > self::MAX_LABEL_LENGTH) {
            throw ClassificationValidationException::invalidLabel();
        }

        if (
            ! empty($attributes['color_hex'])
            && preg_match(self::COLOR_REGEX, (string) $attributes['color_hex']) !== 1
        ) {
            throw ClassificationValidationException::invalidColor((string) $attributes['color_hex']);
        }
    }
}
