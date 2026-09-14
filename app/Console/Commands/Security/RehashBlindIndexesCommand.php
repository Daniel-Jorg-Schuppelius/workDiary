<?php
/*
 * Created on   : Sun Sep 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RehashBlindIndexesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Security;

use App\Models\Applications\JobApplication;
use App\Models\Finance\{BankAccount, BankTransaction, SepaMandate};
use App\Models\User;
use App\Support\Crypto\BlindIndex;
use Illuminate\Console\Command;

/**
 * Rechnet die Nachschlage-Abdrücke verschlüsselter Felder auf den
 * geschlüsselten Abdruck um (Sicherheitsaudit 2026-09-13).
 *
 * Vorher war das ein ungesalzenes SHA-256 über IBAN, E-Mail und Durchwahl —
 * bei diesen kleinen, strukturierten Werteräumen zurückrechenbar. Bis dieser
 * Lauf durch ist, finden die Abfragen über beide Abdrücke
 * ({@see BlindIndex::candidates()}); danach steht nur noch der geschlüsselte
 * in der Datenbank.
 *
 * Der Klartext kommt aus der verschlüsselten Spalte derselben Zeile. Nur
 * `bank_statements.statement_iban_hash` hat keinen Klartext daneben und bleibt
 * deshalb auf dem alten Abdruck — dort greift das Doppellesen dauerhaft.
 */
class RehashBlindIndexesCommand extends Command {
    protected $signature = 'security:rehash-blind-indexes {--dry-run : Nur zählen, nichts schreiben}';

    protected $description = 'Nachschlage-Abdrücke (IBAN, E-Mail, Durchwahl) auf den geschlüsselten Abdruck umrechnen';

    public function handle(): int {
        $dry = (bool) $this->option('dry-run');
        $total = 0;

        $total += $this->rehash('Bankkonten', BankAccount::query()->withoutGlobalScopes(), 'iban_hash',
            static fn (BankAccount $m): ?string => BlindIndex::ofIban($m->iban), $dry);

        $total += $this->rehash('SEPA-Mandate', SepaMandate::query()->withoutGlobalScopes(), 'iban_hash',
            static fn (SepaMandate $m): ?string => BlindIndex::ofIban($m->iban), $dry);

        $total += $this->rehash('Bankumsätze', BankTransaction::query()->withoutGlobalScopes()->whereNotNull('counterparty_iban'),
            'counterparty_iban_hash', static fn (BankTransaction $m): ?string => BlindIndex::ofIban($m->counterparty_iban), $dry);

        $total += $this->rehash('Bewerbungen', JobApplication::query()->withoutGlobalScopes()->whereNotNull('email'),
            'email_hash', static fn (JobApplication $m): ?string => BlindIndex::ofEmail($m->email), $dry);

        $total += $this->rehash('Durchwahlen', User::query()->whereNotNull('cti_extension'), 'cti_extension_hash',
            static fn (User $m): string => BlindIndex::of((string) $m->cti_extension), $dry);

        $this->newLine();
        $this->info($dry
            ? sprintf('Trockenlauf: %d Abdrücke wären neu berechnet worden.', $total)
            : sprintf('%d Abdrücke neu berechnet.', $total));
        $this->line('Hinweis: bank_statements.statement_iban_hash hat keinen Klartext daneben und bleibt auf dem alten Abdruck.');

        return self::SUCCESS;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>  $query
     * @param  callable(TModel): ?string  $compute
     */
    private function rehash(string $label, \Illuminate\Database\Eloquent\Builder $query, string $column, callable $compute, bool $dry): int {
        $changed = 0;

        $query->chunkById(200, function ($rows) use ($column, $compute, $dry, &$changed): void {
            foreach ($rows as $row) {
                $fresh = $compute($row);
                if ($fresh === null || $fresh === $row->getAttribute($column)) {
                    continue;
                }
                $changed++;
                if (! $dry) {
                    // forceFill + saveQuietly: kein Observer, kein Audit-Rauschen —
                    // fachlich ändert sich nichts, nur die Ableitung.
                    $row->forceFill([$column => $fresh])->saveQuietly();
                }
            }
        });

        $this->line(sprintf('  %-16s %d', $label, $changed));

        return $changed;
    }
}
