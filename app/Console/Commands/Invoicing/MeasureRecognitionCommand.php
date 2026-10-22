<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MeasureRecognitionCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Invoicing\IncomingInvoiceRecognition;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Services\Invoicing\InvoicePdfImportService;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Console\Command;

/**
 * Messlauf der Texterkennung (Phase 151, MVP-1217): lässt die Heuristik über
 * die gespeicherten Belegtexte der unstrukturierten Eingänge laufen und zählt
 * je Feld gefunden/fehlt, Belegart-Hinweise und Abweichungen zu von Hand
 * erfassten Werten. Schreibt nichts — der Prüfstand für jede Änderung der
 * Beschriftungen. Vorher `search:extract-texts --retry-failed`, sonst fehlen
 * Belegen die Texte.
 */
class MeasureRecognitionCommand extends Command {
    use IteratesOrganizations;

    private const FIELDS = ['number', 'issued_on', 'due_on', 'net', 'tax', 'gross'];

    protected $signature = 'incoming-invoices:measure-recognition ' . self::ORGANIZATION_OPTION . '
        {--limit=0 : Höchstens N Eingänge je Organisation (0 = alle)}
        {--json : Ergebnis als JSON}';

    protected $description = 'Misst die Texterkennung gegen die gespeicherten Belegtexte: je Feld gefunden/fehlt, Belegart-Hinweise, Abweichungen zu erfassten Werten. Schreibt nichts.';

    public function handle(InvoicePdfImportService $import): int {
        $report = [];
        foreach ($this->organizationsToProcess() as $org) {
            $this->withOrganizationContext($org, function (Organization $org) use ($import, &$report): void {
                $report[(int) $org->id] = $this->measure($org, $import);
            });
        }
        if ((bool) $this->option('json')) {
            $this->line(JsonHelper::encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }
        foreach ($report as $organizationId => $stats) {
            $this->info(sprintf('Organisation #%d: %d unstrukturierte Eingänge, %d mit Text, %d ohne Text.', $organizationId, $stats['total'], $stats['with_text'], $stats['without_text']));
            $rows = [];
            foreach (self::FIELDS as $field) {
                $found = $stats['fields'][$field];
                $rows[] = [$field, $found, $stats['with_text'] - $found, $stats['with_text'] > 0 ? sprintf('%d %%', (int) round($found * 100 / $stats['with_text'])) : '—'];
            }
            $rows[] = ['Nummer + Brutto', $stats['complete'], $stats['with_text'] - $stats['complete'], $stats['with_text'] > 0 ? sprintf('%d %%', (int) round($stats['complete'] * 100 / $stats['with_text'])) : '—'];
            $this->table(['Feld', 'gefunden', 'fehlt', 'Quote'], $rows);
            if ($stats['kind_hints'] !== []) {
                $this->table(['Belegart-Hinweis', 'Eingänge'], array_map(static fn (string $kind, int $count): array => [$kind, $count], array_keys($stats['kind_hints']), $stats['kind_hints']));
            }
            if ($stats['manual']['compared'] > 0) {
                $this->table(['Vergleich mit erfassten Werten', 'übereinstimmend', 'verglichen'], [
                    ['Nummer', $stats['manual']['number'], $stats['manual']['compared']],
                    ['Rechnungsdatum', $stats['manual']['issued_on'], $stats['manual']['compared']],
                    ['Brutto', $stats['manual']['gross'], $stats['manual']['compared']],
                ]);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{total: int, with_text: int, without_text: int, fields: array<string, int>, complete: int, kind_hints: array<string, int>, manual: array{compared: int, number: int, issued_on: int, gross: int}}
     */
    private function measure(Organization $org, InvoicePdfImportService $import): array {
        $stats = [
            'total' => 0, 'with_text' => 0, 'without_text' => 0,
            'fields' => array_fill_keys(self::FIELDS, 0), 'complete' => 0, 'kind_hints' => [],
            'manual' => ['compared' => 0, 'number' => 0, 'issued_on' => 0, 'gross' => 0],
        ];
        $query = IncomingEInvoice::query()->withoutGlobalScopes()
            ->join('documents', 'documents.id', '=', 'incoming_einvoices.document_id')
            ->leftJoin('document_version_texts', 'document_version_texts.document_version_id', '=', 'documents.current_version_id')
            ->where('incoming_einvoices.organization_id', $org->id)
            ->where('incoming_einvoices.recognition', '!=', IncomingInvoiceRecognition::Structured->value)
            ->whereNull('documents.deleted_at')
            ->orderBy('incoming_einvoices.id')
            ->select(['incoming_einvoices.*', 'document_version_texts.text as index_text']);
        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $query->limit($limit);
        }
        foreach ($query->cursor() as $incoming) {
            $stats['total']++;
            $text = $incoming->getAttribute('index_text');
            if (! is_string($text) || trim($text) === '') {
                $stats['without_text']++;

                continue;
            }
            $stats['with_text']++;
            $result = $import->analyzeText($text);
            foreach (self::FIELDS as $field) {
                if (($result[$field] ?? null) !== null) {
                    $stats['fields'][$field]++;
                }
            }
            if ($result['number'] !== null && $result['gross'] !== null) {
                $stats['complete']++;
            }
            if ($result['kind_hint'] !== null) {
                $stats['kind_hints'][$result['kind_hint']] = ($stats['kind_hints'][$result['kind_hint']] ?? 0) + 1;
            }
            $manual = (array) (((array) $incoming->summary)['manual'] ?? []);
            if ($manual !== []) {
                $stats['manual']['compared']++;
                if (\App\Support\ReferenceTokens::key($result['number']) !== null && \App\Support\ReferenceTokens::key($result['number']) === \App\Support\ReferenceTokens::key($manual['invoice_number'] ?? null)) {
                    $stats['manual']['number']++;
                }
                if ($result['issued_on'] !== null && $result['issued_on'] === substr((string) ($manual['issue_date'] ?? ''), 0, 10)) {
                    $stats['manual']['issued_on']++;
                }
                if (is_numeric($result['gross']) && is_numeric($manual['amount_gross'] ?? null) && abs((float) $result['gross'] - (float) $manual['amount_gross']) < 0.005) {
                    $stats['manual']['gross']++;
                }
            }
        }
        ksort($stats['kind_hints']);

        return $stats;
    }
}
