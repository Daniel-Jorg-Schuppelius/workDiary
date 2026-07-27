<?php
/*
 * Created on   : Mon Jul 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AuditIdentifiersCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Models\{Article, ArticleVariant, ContactBankAccount, Customer, LexofficeArticle, Supplier, SupplierCatalogItem, User};
use App\Models\Finance\BankAccount;
use App\Services\Stammdaten\IdentifierIssueDetector;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Prüflauf vor der Umstellung der Identifikatoren auf Value Objects.
 *
 * Die Toolkit-VOs validieren streng (Prüfziffern). Werte, die hier auftauchen,
 * würden nach der Umstellung beim Lesen `null` ergeben und damit aus
 * E-Rechnung, DATEV-Stammdaten und Lexoffice-Abgleich verschwinden — deshalb
 * erst bereinigen, dann casten.
 *
 * Verschlüsselte Spalten laufen über das Model (Laravel entschlüsselt), nicht
 * über die Query-Builder-Ebene.
 */
class AuditIdentifiersCommand extends Command {
    protected $signature = 'identifiers:audit {--csv= : Pfad für einen CSV-Export der Fundstellen}';

    protected $description = 'Prüft USt-IdNr., Steuernummern, Steuer-IDs, IBAN/BIC und GTIN auf Gültigkeit (vor der VO-Umstellung)';

    /**
     * Geprüfte Models — welche Spalten ein Datensatz trägt, entscheidet der
     * {@see IdentifierIssueDetector}.
     *
     * @return list<class-string<Model>>
     */
    private function models(): array {
        return [
            Customer::class, Supplier::class, User::class,
            BankAccount::class, ContactBankAccount::class,
            Article::class, ArticleVariant::class, LexofficeArticle::class, SupplierCatalogItem::class,
        ];
    }

    public function handle(): int {
        /** @var list<array{model: string, id: int|string, field: string, value: string}> $findings */
        $findings = [];
        $checked = 0;

        $detector = app(IdentifierIssueDetector::class);

        foreach ($this->models() as $class) {
            $class::query()->chunkById(500, function ($rows) use ($detector, &$findings, &$checked, $class): void {
                foreach ($rows as $row) {
                    $checked++;
                    foreach ($detector->forModel($row) as $issue) {
                        $findings[] = [
                            'model' => class_basename($class),
                            'id' => $row->getKey(),
                            'field' => $issue['field'],
                            'value' => $issue['value'],
                            'reason' => $issue['reason'],
                            'suggestion' => $issue['suggestion'] ?? '',
                        ];
                    }
                }
            });
        }

        $this->info(sprintf('%d Datensätze geprüft, %d beanstandete Identifikatoren.', $checked, count($findings)));

        if ($findings !== []) {
            $this->table(['Model', 'ID', 'Feld', 'Wert', 'Grund', 'Vorschlag'], array_map(array_values(...), array_slice($findings, 0, 50)));
            if (count($findings) > 50) {
                $this->line(sprintf('… und %d weitere.', count($findings) - 50));
            }
        }

        $csv = $this->option('csv');
        if (is_string($csv) && $csv !== '') {
            $handle = fopen($csv, 'w');
            if ($handle === false) {
                $this->error('CSV konnte nicht geschrieben werden: ' . $csv);

                return self::FAILURE;
            }
            fputcsv($handle, ['model', 'id', 'field', 'value', 'reason', 'suggestion'], ';');
            foreach ($findings as $f) {
                fputcsv($handle, array_values($f), ';');
            }
            fclose($handle);
            $this->info('CSV geschrieben: ' . $csv);
        }

        // Kein Fehler-Exit: der Lauf ist ein Bericht, kein Gate.
        return self::SUCCESS;
    }
}
