<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvoiceSpec.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs;

use App\Enums\Import\{ImportEntity, ImportErrorCode};
use App\Models\{Customer, Invoice, Organization};
use App\Services\Finance\BillingModeResolver;
use App\Services\Import\{ImportOutcome, ValidationIssue};
use App\Services\Import\Specs\Concerns\{ResolvesImportReferences, ValidatesImportDates};
use App\Services\Invoicing\InvoicePartySnapshot;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\Helper\Data\NumberHelper;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Altrechnungen mit OP-Stand aus einem Vorsystem (MVP-707, Vollscan H20).
 *
 * Die Zeile trägt den Beleg-Kopf (Alt-Nummer, Datum, Fälligkeit, Netto/Brutto/
 * Steuersatz, Währung) plus den Zahlstand (bezahlt, Zahldatum). Angelegt wird
 * ein **Eröffnungs-OP**: sofort festgeschrieben (Partei-Snapshot → Unverän-
 * derlichkeits-Guard), eigene Nummer `ALT-<Alt-Nummer>` außerhalb des
 * Nummernkreises, `number_source = legacy_import`, eine Summenposition.
 * Status aus total vs. bezahlt (issued/partially_paid/paid); der bezahlte
 * Altbetrag steht unveränderlich in `import_metadata` und fließt über
 * {@see \App\Services\Finance\ReconciliationService::allocatedSum()} in
 * Mahnlauf, Girocode und Deckungsprüfung ein. Keine Journalbuchung (die
 * Posting-Adapter überspringen `legacy_import`). Rechnungshoheit extern →
 * Zeile blockiert. Idempotenz: (Organisation, Alt-Nummer) — vorhandene
 * Altrechnungen werden übersprungen, nie überschrieben.
 */
class InvoiceSpec extends AbstractEntitySpec {
    use ResolvesImportReferences;
    use ValidatesImportDates;

    public const NUMBER_SOURCE = 'legacy_import';

    public const NUMBER_PREFIX = 'ALT-';

    /** Länge der Alt-Nummer so, dass Präfix + Nummer in `invoices.number` (64) passt. */
    private const MAX_EXTERNAL_NUMBER = 60;

    private const DEFAULT_PAYMENT_TERMS_DAYS = 14;

    public function entity(): ImportEntity {
        return ImportEntity::Invoices;
    }

    public function columns(): array {
        return [
            'external_number',
            'customer_number',
            'customer_name',
            'issued_on',
            'due_on',
            'net_amount',
            'tax_rate',
            'gross_amount',
            'currency',
            'paid_amount',
            'paid_on',
            'description',
            'project_number',
            'legacy_source',
            'notes',
        ];
    }

    public function requiredColumns(): array {
        return ['external_number', 'issued_on'];
    }

    public function headerAliases(): array {
        return [
            'rechnungsnummer' => 'external_number',
            'alt-nummer' => 'external_number',
            'altnummer' => 'external_number',
            'belegnummer' => 'external_number',
            'nummer' => 'external_number',
            'number' => 'external_number',
            'invoice_number' => 'external_number',
            'kundennummer' => 'customer_number',
            'kunde' => 'customer_name',
            'kundenname' => 'customer_name',
            'customer' => 'customer_name',
            'rechnungsdatum' => 'issued_on',
            'belegdatum' => 'issued_on',
            'datum' => 'issued_on',
            'date' => 'issued_on',
            'fälligkeit' => 'due_on',
            'faelligkeit' => 'due_on',
            'fällig am' => 'due_on',
            'due' => 'due_on',
            'netto' => 'net_amount',
            'nettobetrag' => 'net_amount',
            'net' => 'net_amount',
            'steuersatz' => 'tax_rate',
            'mwst' => 'tax_rate',
            'ust' => 'tax_rate',
            'brutto' => 'gross_amount',
            'bruttobetrag' => 'gross_amount',
            'gesamt' => 'gross_amount',
            'betrag' => 'gross_amount',
            'summe' => 'gross_amount',
            'total' => 'gross_amount',
            'gross' => 'gross_amount',
            'währung' => 'currency',
            'waehrung' => 'currency',
            'bezahlt' => 'paid_amount',
            'gezahlt' => 'paid_amount',
            'zahlbetrag' => 'paid_amount',
            'paid' => 'paid_amount',
            'zahldatum' => 'paid_on',
            'bezahlt am' => 'paid_on',
            'beschreibung' => 'description',
            'text' => 'description',
            'leistung' => 'description',
            'projektnummer' => 'project_number',
            'projekt' => 'project_number',
            'quelle' => 'legacy_source',
            'altsystem' => 'legacy_source',
            'source' => 'legacy_source',
            'notiz' => 'notes',
            'bemerkung' => 'notes',
        ];
    }

    public function normalize(array $row): array {
        $out = [];
        foreach ($this->columns() as $col) {
            $raw = $row[$col] ?? null;
            $out[$col] = match ($col) {
                'net_amount', 'gross_amount', 'paid_amount', 'tax_rate' => $this->decimal($this->trimmedString($raw)),
                'currency' => $this->upperOrNull($this->trimmedString($raw)),
                default => $this->trimmedString($raw),
            };
        }

        return $out;
    }

    public function validateRow(array $row, Organization $organization): array {
        $issues = [];

        $external = $row['external_number'] ?? null;
        if ($external === null) {
            $issues[] = $this->requiredIssue('external_number');
        } elseif (mb_strlen((string) $external) > self::MAX_EXTERNAL_NUMBER) {
            $issues[] = $this->tooLongIssue('external_number', self::MAX_EXTERNAL_NUMBER);
        }

        [$customer, $customerIssue] = $this->resolveCustomer($organization, $row);
        if ($customerIssue !== null) {
            $issues[] = $customerIssue;
        }
        if ($customer !== null) {
            // Rechnungshoheit (Feature 045): bei externem Fakturierungsprogramm
            // entstehen lokal keine Belege — auch keine Altbestände.
            $mode = app(BillingModeResolver::class)->effectiveFor($customer);
            if ($mode->isExternal()) {
                $issues[] = new ValidationIssue(
                    ImportErrorCode::Blocked,
                    'customer_number',
                    (string) __('import.error.blocked.invoiceSovereignty', ['program' => $mode->label()]),
                );
            }
        }

        if (($row['issued_on'] ?? null) === null) {
            $issues[] = $this->requiredIssue('issued_on');
        }
        $this->validateDateField($issues, $row, 'issued_on');
        $this->validateDateField($issues, $row, 'due_on');
        $this->validateDateField($issues, $row, 'paid_on');

        if (! empty($row['currency']) && ! preg_match('/^[A-Z]{3}$/', (string) $row['currency'])) {
            $issues[] = $this->formatIssue('currency', (string) __('import.error.format.currency'));
        }

        $currency = $this->currencyOf($row);
        if ($currency !== null) {
            $amounts = $this->amounts($row, $currency);
            if ($amounts === null) {
                $issues[] = new ValidationIssue(
                    ImportErrorCode::Required,
                    'gross_amount',
                    (string) __('import.error.invoice.amountMissing'),
                );
            } else {
                $paid = Money::of((string) ($row['paid_amount'] ?? '0'), $currency);
                if ($paid->compareTo($amounts['gross']) > 0) {
                    $issues[] = new ValidationIssue(
                        ImportErrorCode::OutOfRange,
                        'paid_amount',
                        (string) __('import.error.invoice.paidExceedsTotal', [
                            'paid' => $paid->getAmount(),
                            'total' => $amounts['gross']->getAmount(),
                        ]),
                    );
                }
            }
        }

        $projectNumber = $row['project_number'] ?? null;
        if ($projectNumber !== null && $this->projectByNumber($organization, (string) $projectNumber) === null) {
            $issues[] = $this->fkIssue('project_number', 'projectNumber', (string) $projectNumber);
        }

        return $issues;
    }

    public function upsert(array $row, Organization $organization): array {
        try {
            [$customer, $customerIssue] = $this->resolveCustomer($organization, $row);
            if ($customer === null) {
                return [ImportOutcome::Failed, $customerIssue];
            }

            $external = (string) $row['external_number'];

            // Idempotenz: dieselbe Alt-Nummer wird nie ein zweites Mal angelegt
            // und — festgeschrieben — auch nicht überschrieben.
            $alreadyImported = Invoice::query()
                ->where('organization_id', $organization->id)
                ->where('number_source', self::NUMBER_SOURCE)
                ->where('external_number', $external)
                ->exists();
            if ($alreadyImported) {
                return [ImportOutcome::Skipped, null];
            }

            $number = self::NUMBER_PREFIX . $external;
            $numberTaken = Invoice::query()
                ->where('organization_id', $organization->id)
                ->where('number', $number)
                ->exists();
            if ($numberTaken) {
                return [
                    ImportOutcome::Failed,
                    new ValidationIssue(ImportErrorCode::Unique, 'external_number', (string) __('import.error.invoice.numberTaken', ['number' => $number])),
                ];
            }

            $currency = $this->currencyOf($row) ?? CurrencyCode::Euro;
            $amounts = $this->amounts($row, $currency);
            if ($amounts === null) {
                return [
                    ImportOutcome::Failed,
                    new ValidationIssue(ImportErrorCode::Required, 'gross_amount', (string) __('import.error.invoice.amountMissing')),
                ];
            }

            $paid = Money::of((string) ($row['paid_amount'] ?? '0'), $currency);
            if ($paid->compareTo($amounts['gross']) > 0) {
                return [
                    ImportOutcome::Failed,
                    new ValidationIssue(ImportErrorCode::OutOfRange, 'paid_amount', (string) __('import.error.invoice.paidExceedsTotal', [
                        'paid' => $paid->getAmount(),
                        'total' => $amounts['gross']->getAmount(),
                    ])),
                ];
            }

            $status = match (true) {
                $paid->compareTo($amounts['gross']) >= 0 => Invoice::STATUS_PAID,
                $paid->isPositive() => Invoice::STATUS_PARTIALLY_PAID,
                default => Invoice::STATUS_ISSUED,
            };

            $issuedOn = (string) $this->dateString($row['issued_on']);
            $dueOn = $this->dateString($row['due_on'] ?? null)
                ?? (string) $this->parseDate($issuedOn)?->addDays(self::DEFAULT_PAYMENT_TERMS_DAYS)->toDateString();
            $paidOn = $this->dateString($row['paid_on'] ?? null);
            $project = $this->projectByNumber($organization, $row['project_number'] ?? null);

            $breakdown = [[
                'rate' => $amounts['rate'],
                'net' => $amounts['net']->getAmount(),
                'tax' => $amounts['tax']->getAmount(),
            ]];

            $payload = [
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'project_id' => $project?->id,
                'number' => $number,
                'external_number' => $external,
                'number_source' => self::NUMBER_SOURCE,
                'status' => $status,
                'type' => Invoice::TYPE_INVOICE,
                'category' => Invoice::CATEGORY_SERVICE,
                'issued_on' => $issuedOn,
                'due_on' => $dueOn,
                // paid_on = Datum der vollständigen Bezahlung; bei Teilzahlung offen.
                'paid_on' => $status === Invoice::STATUS_PAID ? ($paidOn ?? $issuedOn) : null,
                'currency' => $currency->value,
                'subtotal' => $amounts['net']->getAmount(),
                'tax_rate' => $amounts['rate'],
                'tax_amount' => $amounts['tax']->getAmount(),
                'total' => $amounts['gross']->getAmount(),
                'tax_breakdown' => $breakdown,
                'tax_context' => [
                    'resolved_on' => $issuedOn,
                    'rate' => $amounts['rate'],
                    'is_reverse_charge' => false,
                    'breakdown' => $breakdown,
                    'legacy_import' => true,
                ],
                'payment_terms_days' => self::DEFAULT_PAYMENT_TERMS_DAYS,
                'notes' => $row['notes'] ?? null,
                'import_metadata' => [
                    'source' => self::NUMBER_SOURCE,
                    'legacy_source' => $row['legacy_source'] ?? null,
                    'external_number' => $external,
                    'imported_at' => now()->toIso8601String(),
                    'paid_amount' => $paid->getAmount(),
                    'paid_on' => $paidOn,
                ],
            ];

            DB::transaction(function () use ($payload, $customer, $organization, $amounts, $issuedOn, $external, $row): void {
                // GoBD: sofort festgeschrieben — der Partei-Snapshot aktiviert den
                // Unveränderlichkeits-Guard (MVP-162) ab dem ersten Save.
                $draft = new Invoice($payload);
                $draft->setRelation('customer', $customer);
                $draft->setRelation('organization', $organization);
                $payload['party_snapshot'] = app(InvoicePartySnapshot::class)->capture($draft);

                $invoice = Invoice::query()->create($payload);

                // Mindestens eine Summenposition, damit Beleg-Renderer, Umsatz-
                // berichte und Positionsprüfungen nicht ins Leere laufen.
                $invoice->items()->create([
                    'organization_id' => $organization->id,
                    'position' => 1,
                    'service_date' => $issuedOn,
                    'description' => $row['description'] ?? (string) __('import.legacy.position', ['number' => $external]),
                    'quantity' => '1',
                    'unit' => (string) __('invoicing.unit_flat'),
                    'unit_price' => $amounts['net']->getAmount(),
                    'amount' => $amounts['net']->getAmount(),
                    'tax_rate' => $amounts['rate'],
                ]);
            });

            return [ImportOutcome::Created, null];
        } catch (Throwable $e) {
            return [ImportOutcome::Failed, new ValidationIssue(ImportErrorCode::Persist, null, $e->getMessage())];
        }
    }

    /**
     * Kunde über Nummer, ersatzweise über eindeutigen Namen.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: ?Customer, 1: ?ValidationIssue}
     */
    private function resolveCustomer(Organization $organization, array $row): array {
        $number = $row['customer_number'] ?? null;
        $name = $row['customer_name'] ?? null;

        if ($number !== null) {
            $customer = $this->customerByNumber($organization, (string) $number);

            return $customer !== null
                ? [$customer, null]
                : [null, $this->fkIssue('customer_number', 'customer', (string) $number)];
        }
        if ($name !== null) {
            $customer = $this->customerByUniqueName($organization, (string) $name);

            return $customer !== null
                ? [$customer, null]
                : [null, $this->fkIssue('customer_name', 'customerName', (string) $name)];
        }

        return [null, $this->requiredIssue('customer_number')];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function currencyOf(array $row): ?CurrencyCode {
        $raw = $row['currency'] ?? null;
        if ($raw === null || $raw === '') {
            return CurrencyCode::Euro;
        }

        return CurrencyCode::tryFrom((string) $raw);
    }

    /**
     * Netto/Steuer/Brutto aus den gegebenen Spalten: Netto+Brutto (Satz
     * abgeleitet), Netto+Satz oder Brutto+Satz. Sonst null (Betrag fehlt).
     *
     * @param  array<string, mixed>  $row
     * @return array{net: Money, tax: Money, gross: Money, rate: string}|null
     */
    private function amounts(array $row, CurrencyCode $currency): ?array {
        $net = $this->numeric($row['net_amount'] ?? null);
        $gross = $this->numeric($row['gross_amount'] ?? null);
        $rate = $this->numeric($row['tax_rate'] ?? null);

        if ($net !== null && $gross !== null) {
            $netM = Money::of($net, $currency);
            $grossM = Money::of($gross, $currency);
            $taxM = $grossM->minus($netM);
            $rate ??= $netM->isPositive()
                ? NumberHelper::roundPrecise(NumberHelper::dividePrecise(NumberHelper::multiplyPrecise($taxM->getAmount(), '100', 6), $netM->getAmount(), 6), 2)
                : '0';

            return ['net' => $netM, 'tax' => $taxM, 'gross' => $grossM, 'rate' => NumberHelper::roundPrecise($rate, 2)];
        }
        if ($net !== null && $rate !== null) {
            $netM = Money::of($net, $currency);
            $taxM = $netM->percentage($rate);

            return ['net' => $netM, 'tax' => $taxM, 'gross' => $netM->plus($taxM), 'rate' => NumberHelper::roundPrecise($rate, 2)];
        }
        if ($gross !== null && $rate !== null) {
            $grossM = Money::of($gross, $currency);
            $divisor = NumberHelper::addPrecise('1', NumberHelper::dividePrecise($rate, '100', 6), 6);
            $netM = $grossM->dividedBy($divisor);

            return ['net' => $netM, 'tax' => $grossM->minus($netM), 'gross' => $grossM, 'rate' => NumberHelper::roundPrecise($rate, 2)];
        }

        return null;
    }

    /**
     * Normalisierte Dezimalzeichenkette als numeric-string (sonst null).
     *
     * @return numeric-string|null
     */
    private function numeric(mixed $value): ?string {
        return is_string($value) && is_numeric($value) ? $value : null;
    }
}
