<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102600_add_denormalized_amounts_to_incoming_einvoices.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Eingangsrechnungs-Kennwerte als echte Spalten (Feature 105, MVP-544).
 *
 * Betrag, Währung, Belegnummer und Datumsangaben lagen bisher ausschließlich
 * im JSON-Feld `summary` — dort weder typsicher summierbar noch indizierbar.
 * Der Belegfluss muss sie sortieren und aggregieren können. Bewusst echte
 * Spalten statt generierter JSON-Spalten: Entwicklung fährt SQLite,
 * Produktion MariaDB.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->string('invoice_number', 64)->nullable()->after('source');
            $table->string('seller_name', 191)->nullable()->after('invoice_number');
            $table->date('issue_date')->nullable()->after('seller_name');
            $table->date('due_date')->nullable()->after('issue_date');
            $table->char('currency', 3)->nullable()->after('due_date');
            $table->decimal('amount_net', 18, 2)->nullable()->after('currency');
            $table->decimal('amount_tax', 18, 2)->nullable()->after('amount_net');
            $table->decimal('amount_gross', 18, 2)->nullable()->after('amount_tax');

            $table->index(['organization_id', 'issue_date'], 'inc_einv_org_issue_idx');
            $table->index(['organization_id', 'due_date'], 'inc_einv_org_due_idx');
        });

        $this->backfill();
    }

    public function down(): void {
        Schema::table('incoming_einvoices', function (Blueprint $table): void {
            $table->dropIndex('inc_einv_org_issue_idx');
            $table->dropIndex('inc_einv_org_due_idx');
            $table->dropColumn([
                'invoice_number', 'seller_name', 'issue_date', 'due_date',
                'currency', 'amount_net', 'amount_tax', 'amount_gross',
            ]);
        });
    }

    /**
     * Bestand aus dem vorhandenen `summary`-JSON nachziehen. Ohne Modelle und
     * ohne Events — die Zeilen sind revisionsrelevant, hier wird nur eine
     * bereits gespeicherte Information zusätzlich in Spalten abgelegt.
     */
    private function backfill(): void {
        DB::table('incoming_einvoices')
            ->select(['id', 'summary'])
            ->orderBy('id')
            ->chunk(200, function ($rows): void {
                foreach ($rows as $row) {
                    /** @var array<string, mixed> $summary */
                    $summary = json_decode((string) $row->summary, true) ?: [];
                    if ($summary === []) {
                        continue;
                    }

                    DB::table('incoming_einvoices')->where('id', $row->id)->update([
                        'invoice_number' => $this->str($summary['number'] ?? null, 64),
                        'seller_name' => $this->str($summary['seller'] ?? null, 191),
                        'issue_date' => $this->str($summary['issue_date'] ?? null, 10),
                        'due_date' => $this->str($summary['due_date'] ?? null, 10),
                        'currency' => $this->str($summary['currency'] ?? null, 3),
                        'amount_net' => $this->num($summary['net'] ?? null),
                        'amount_tax' => $this->num($summary['tax'] ?? null),
                        'amount_gross' => $this->num($summary['gross'] ?? null),
                    ]);
                }
            });
    }

    private function str(mixed $value, int $max): ?string {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function num(mixed $value): ?string {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }
};
