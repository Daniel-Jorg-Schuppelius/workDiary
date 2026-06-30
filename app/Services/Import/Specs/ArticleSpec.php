<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Article\{ArticleStatus, ArticleType};
use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\Organization;
use App\Services\Import\{ImportOutcome, InboxFirstSpec, ValidationIssue};
use App\Services\Import\Specs\Concerns\DedupsAndStages;
use App\Services\Integration\Profiles\ArticleMatchProfile;
use Throwable;

/**
 * CSV-Spezifikation für den Artikelstamm-Import (Basis-Artikel; Varianten/
 * Einheiten bleiben außen vor).
 *
 * Fachliche Schlüssel zur Idempotenz: `number` (Artikelnummer) und `gtin`, beide
 * je Mandant eindeutig. Reimport mit abweichender Nummer dedupliziert über den
 * gemeinsamen {@see EntityMatcher} (number/gtin), erzeugt also keine Dublette.
 */
class ArticleSpec extends AbstractEntitySpec implements InboxFirstSpec {
    use DedupsAndStages;

    public function entity(): ImportEntity {
        return ImportEntity::Articles;
    }

    public function columns(): array {
        return [
            'number',
            'gtin',
            'name',
            'description',
            'type',
            'base_unit',
            'tax_class',
            'status',
            'stockable',
            'purchasable',
            'sellable',
            'manufacturable',
            'default_purchase_price',
            'default_sale_price',
            'currency',
            'external_id',
        ];
    }

    public function requiredColumns(): array {
        return ['name'];
    }

    public function headerAliases(): array {
        return [
            'artikelnummer' => 'number',
            'fremd-id' => 'external_id',
            'fremdid' => 'external_id',
            'externe-id' => 'external_id',
            'quell-id' => 'external_id',
            'nummer' => 'number',
            'ean' => 'gtin',
            'artikel' => 'name',
            'bezeichnung' => 'name',
            'beschreibung' => 'description',
            'typ' => 'type',
            'einheit' => 'base_unit',
            'basiseinheit' => 'base_unit',
            'steuerklasse' => 'tax_class',
            'lagerführung' => 'stockable',
            'lagerfuehrung' => 'stockable',
            'einkaufspreis' => 'default_purchase_price',
            'verkaufspreis' => 'default_sale_price',
            'währung' => 'currency',
            'waehrung' => 'currency',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'stockable', 'purchasable', 'sellable', 'manufacturable' => $raw === null || $raw === '' ? null : $this->boolish($raw),
                'default_purchase_price', 'default_sale_price' => $this->decimal($this->trimmedString($raw)),
                'currency' => $this->upperOrNull($this->trimmedString($raw)),
                'type', 'status' => $this->lowerOrNull($this->trimmedString($raw)),
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

        if (! empty($row['type']) && ArticleType::tryFrom((string) $row['type']) === null) {
            $issues[] = $this->formatIssue('type', (string) __('import.error.format.enum'));
        }
        if (! empty($row['status']) && ArticleStatus::tryFrom((string) $row['status']) === null) {
            $issues[] = $this->formatIssue('status', (string) __('import.error.format.enum'));
        }

        foreach (['number' => 64, 'gtin' => 14, 'base_unit' => 20] as $f => $max) {
            if (! empty($row[$f]) && mb_strlen((string) $row[$f]) > $max) {
                $issues[] = $this->tooLongIssue($f, $max);
            }
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
                app(ArticleMatchProfile::class),
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
