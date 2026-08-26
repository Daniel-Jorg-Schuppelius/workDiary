<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\{Organization, Quote, QuoteItem};
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\Concerns\{ResolvesImportReferences, ValidatesImportDates};
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Angebote aus einem Vorsystem (MVP-707, Vollscan H20): eine Zeile = Kopf
 * (Angebotsnummer, Kunde, Version, Status, Bindefrist) + EINE kompakte
 * Position (Beschreibung, Menge, Einheit, Einzelpreis, Steuersatz, optional
 * Artikelnummer → `article_id` org-gescopt). Mehrere Positionen = mehrere
 * Zeilen mit derselben Nummer; Kopfzeilen ohne Beschreibung sind erlaubt.
 * Idempotenz: (Organisation, Nummer) für den Kopf, Position über `position`
 * bzw. Beschreibung. Status nur draft/sent/accepted/rejected/expired —
 * keine Annahme-Tokens, kein Entscheidungs-Snapshot (Altbestand).
 */
class QuoteSpec extends AbstractEntitySpec {
    use ResolvesImportReferences;
    use ValidatesImportDates;

    /** @var list<string> */
    public const ALLOWED_STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

    private const DEFAULT_TAX_RATE = '19.00';

    public function entity(): ImportEntity {
        return ImportEntity::Quotes;
    }

    public function columns(): array {
        return [
            'number',
            'customer_number',
            'version',
            'status',
            'valid_until',
            'terms',
            'position',
            'description',
            'quantity',
            'unit',
            'unit_price',
            'tax_rate',
            'article_number',
            'optional',
        ];
    }

    public function requiredColumns(): array {
        return ['number', 'customer_number'];
    }

    public function headerAliases(): array {
        return [
            'angebotsnummer' => 'number',
            'nummer' => 'number',
            'kundennummer' => 'customer_number',
            'gültig bis' => 'valid_until',
            'gueltig bis' => 'valid_until',
            'bindefrist' => 'valid_until',
            'bedingungen' => 'terms',
            'konditionen' => 'terms',
            'pos' => 'position',
            'pos.' => 'position',
            'beschreibung' => 'description',
            'text' => 'description',
            'bezeichnung' => 'description',
            'menge' => 'quantity',
            'anzahl' => 'quantity',
            'einheit' => 'unit',
            'einzelpreis' => 'unit_price',
            'preis' => 'unit_price',
            'steuersatz' => 'tax_rate',
            'mwst' => 'tax_rate',
            'ust' => 'tax_rate',
            'artikelnummer' => 'article_number',
            'artikel' => 'article_number',
            'sku' => 'article_number',
            'eventualposition' => 'optional',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'version', 'position' => ($v = $this->decimal($this->trimmedString($raw))) !== null ? (int) round((float) $v) : null,
                'quantity', 'unit_price', 'tax_rate' => $this->decimal($this->trimmedString($raw)),
                'status' => $this->lowerOrNull($this->trimmedString($raw)),
                'optional' => $raw === null || trim((string) $raw) === '' ? null : $this->boolish($raw),
                default => $this->trimmedString($raw),
            };
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];

        if (($row['number'] ?? null) === null) {
            $issues[] = $this->requiredIssue('number');
        } elseif (mb_strlen((string) $row['number']) > 40) {
            $issues[] = $this->tooLongIssue('number', 40);
        }

        if (($row['customer_number'] ?? null) === null) {
            $issues[] = $this->requiredIssue('customer_number');
        } elseif ($this->customerByNumber($organization, (string) $row['customer_number']) === null) {
            $issues[] = $this->fkIssue('customer_number', 'customer', (string) $row['customer_number']);
        }

        if (! empty($row['status']) && ! in_array($row['status'], self::ALLOWED_STATUSES, true)) {
            $issues[] = $this->formatIssue('status', (string) __('import.error.format.status'));
        }

        $this->validateDateField($issues, $row, 'valid_until');

        if (($row['article_number'] ?? null) !== null && $this->articleByNumber($organization, (string) $row['article_number']) === null) {
            $issues[] = $this->fkIssue('article_number', 'article', (string) $row['article_number']);
        }

        if (($row['description'] ?? null) !== null && mb_strlen((string) $row['description']) > 1000) {
            $issues[] = $this->tooLongIssue('description', 1000);
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            $customer = $this->customerByNumber($organization, (string) $row['customer_number']);
            if ($customer === null) {
                return [ImportOutcome::Failed, $this->fkIssue('customer_number', 'customer', (string) $row['customer_number'])];
            }

            $article = $this->articleByNumber($organization, $row['article_number'] ?? null);
            if (($row['article_number'] ?? null) !== null && $article === null) {
                return [ImportOutcome::Failed, $this->fkIssue('article_number', 'article', (string) $row['article_number'])];
            }

            $created = false;
            DB::transaction(function () use ($row, $organization, $customer, $article, &$created): void {
                $quote = Quote::query()
                    ->where('organization_id', $organization->id)
                    ->where('number', (string) $row['number'])
                    ->orderByDesc('version')
                    ->first();

                $head = array_filter([
                    'customer_id' => $customer->id,
                    'status' => $row['status'] ?? null,
                    'valid_until' => $this->dateString($row['valid_until'] ?? null),
                    'terms' => $row['terms'] ?? null,
                ], static fn($v): bool => $v !== null);

                if ($quote === null) {
                    $quote = Quote::query()->create($head + [
                        'organization_id' => $organization->id,
                        'number' => (string) $row['number'],
                        'version' => $row['version'] ?? 1,
                        'status' => $row['status'] ?? 'draft',
                    ]);
                    $created = true;
                } else {
                    $quote->fill($head)->save();
                }

                if (($row['description'] ?? null) !== null) {
                    $this->upsertItem($quote, $row, $organization, $article?->id);
                }

                $quote->unsetRelation('items');
                $quote->recalculate();
                $quote->save();
            });

            return [$created ? ImportOutcome::Created : ImportOutcome::Updated, null];
        } catch (Throwable $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage())];
        }
    }

    /**
     * Position anhand `position` (sonst Beschreibung) aktualisieren oder anhängen.
     *
     * @param  array<string, mixed>  $row
     */
    private function upsertItem(Quote $quote, array $row, Organization $organization, ?int $articleId): void {
        $position = $row['position'] ?? null;
        $existing = $position !== null
            ? $quote->items()->where('position', $position)->first()
            : $quote->items()->where('description', (string) $row['description'])->first();

        $payload = [
            'organization_id' => $organization->id,
            'position' => $position ?? ((int) $quote->items()->max('position') + 1),
            'description' => (string) $row['description'],
            'quantity' => $row['quantity'] ?? '1',
            'unit' => $row['unit'] ?? null,
            'unit_price' => $row['unit_price'] ?? '0',
            'tax_rate' => $row['tax_rate'] ?? self::DEFAULT_TAX_RATE,
            'optional' => $row['optional'] ?? false,
        ];
        if ($articleId !== null) {
            $payload['article_id'] = $articleId;
        }

        if ($existing instanceof QuoteItem) {
            $existing->fill($payload)->save();

            return;
        }

        $quote->items()->create($payload);
    }
}
