<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportBillingExcelCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Billing;

use App\Models\{Customer, User};
use App\Services\Billing\{CustomerAccountStatementService, ExcelHistoryImporter};
use App\Services\SqidEncoder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Einmal-Import der Excel-Zeiterfassung (Feature 098). Voraussetzung: Kunde
 * hat ein Abrechnungsprofil MIT Satzzeilen (Kundenakte → Sonderkonditionen).
 * --dry-run rechnet alles in einer Transaktion und rollt zurück; die
 * Abschluss-Tabelle vergleicht die berechneten Monatswerte mit den
 * Excel-„Gesamt"-Werten (Abnahmekriterium).
 */
class ImportBillingExcelCommand extends Command {
    protected $signature = 'customer-billing:import-excel
        {customer : Kunde (Sqid oder numerische ID)}
        {file : Pfad zur XLSX-Datei}
        {--user= : Nutzer für die TimeEntries (Sqid, ID oder E-Mail)}
        {--tz=Europe/Berlin : Zeitzone der Excel-Zeiten}
        {--dry-run : Nur rechnen, nichts speichern}';

    protected $description = 'Importiert die Excel-Zeiterfassung eines Sonderkonditions-Kunden (Feature 098)';

    public function handle(ExcelHistoryImporter $importer, CustomerAccountStatementService $statements): int {
        $customer = $this->resolveCustomer((string) $this->argument('customer'));
        if ($customer === null) {
            $this->error('Kunde nicht gefunden.');

            return self::FAILURE;
        }

        $user = $this->resolveUser($customer);
        if ($user === null) {
            $this->error('Kein Nutzer auflösbar — bitte --user angeben (Sqid, ID oder E-Mail).');

            return self::FAILURE;
        }

        $file = (string) $this->argument('file');
        if (! is_readable($file)) {
            $this->error("Datei nicht lesbar: {$file}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $tz = (string) $this->option('tz');
        $this->info(sprintf(
            'Import für %s (Nutzer: %s, TZ: %s)%s',
            $customer->name,
            $user->email,
            $tz,
            $dryRun ? ' — DRY-RUN' : ''
        ));

        DB::beginTransaction();
        try {
            $summary = $importer->import($customer, $file, $user, $tz);
            // Vergleichswerte (App vs. Excel) noch innerhalb der Transaktion einsammeln.
            $summary = $this->attachComputed($customer, $summary, $statements);
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Blatt', 'Zeilen neu', 'übersprungen', 'Std', 'Zahlung', 'Gesamt (App)', 'Gesamt (Excel)', 'Δ'],
            array_map(static function (array $row): array {
                $delta = ($row['computed_gross'] !== null && $row['excel_gross'] !== null)
                    ? number_format($row['computed_gross'] - $row['excel_gross'], 2, ',', '.')
                    : '—';

                return [
                    $row['sheet'],
                    $row['entries_created'],
                    $row['entries_skipped'],
                    sprintf('%d:%02d', intdiv($row['minutes'], 60), $row['minutes'] % 60),
                    $row['payment'] === null ? '—' : number_format($row['payment'], 2, ',', '.') . ($row['payment_created'] ? '' : ' (vorh.)'),
                    $row['computed_gross'] === null ? '—' : number_format($row['computed_gross'], 2, ',', '.'),
                    $row['excel_gross'] === null ? '—' : number_format($row['excel_gross'], 2, ',', '.'),
                    $delta,
                ];
            }, $summary)
        );

        if ($dryRun) {
            $this->warn('DRY-RUN — es wurde nichts gespeichert.');
        } else {
            $this->info('Import abgeschlossen.');
        }

        return self::SUCCESS;
    }

    /**
     * @param list<array<string, mixed>> $summary
     * @return list<array<string, mixed>>
     */
    private function attachComputed(Customer $customer, array $summary, CustomerAccountStatementService $statements): array {
        $agreement = $customer->billingAgreement()->firstOrFail();
        $statements->recalculateOpen($agreement);

        foreach ($summary as &$row) {
            $statement = $agreement->statements()
                ->where('year', $row['year'])
                ->where('month', $row['month'])
                ->first();
            $row['computed_gross'] = $statement?->gross_value?->toFloat();
        }

        return $summary;
    }

    private function resolveCustomer(string $key): ?Customer {
        if (ctype_digit($key)) {
            return Customer::query()->find((int) $key);
        }
        $id = app(SqidEncoder::class)->decode(Customer::class, $key);

        return $id !== null ? Customer::query()->find($id) : null;
    }

    private function resolveUser(Customer $customer): ?User {
        $option = trim((string) $this->option('user'));
        if ($option === '') {
            // Fallback: ältester aktiver Nutzer der Organisation des Kunden.
            return User::query()
                ->where('organization_id', $customer->organization_id)
                ->whereNull('deactivated_at')
                ->orderBy('id')
                ->first();
        }
        if (str_contains($option, '@')) {
            return User::query()->where('email', $option)->where('organization_id', $customer->organization_id)->first();
        }
        if (ctype_digit($option)) {
            return User::query()->whereKey((int) $option)->where('organization_id', $customer->organization_id)->first();
        }
        $id = app(SqidEncoder::class)->decode(User::class, $option);

        return $id !== null
            ? User::query()->whereKey($id)->where('organization_id', $customer->organization_id)->first()
            : null;
    }
}
