<?php
/*
 * Created on   : Mon Jun 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_06_120100_create_contact_bank_accounts_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Polymorphe Bankverbindungen für Kontakte (Customer/Supplier).
 *
 * Lexoffice exponiert Bankdaten NICHT über die Contact-API — diese Tabelle
 * ist daher lokal/push-führend. Bestehende `bank_*`-Einzelfelder an
 * customers/suppliers werden als primäre Bankverbindung übernommen
 * (Backfill); die Altspalten bleiben vorerst als Legacy erhalten.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('contact_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->morphs('accountable');
            // Verschluesselte PII (Model-Cast): text statt string, da der
            // encrypted-Cast deutlich laengere Werte erzeugt (vgl. widen-Migration).
            $table->text('account_holder')->nullable();
            $table->text('iban')->nullable();
            $table->text('bic')->nullable();
            $table->string('bank_name', 200)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('external_id', 64)->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['accountable_type', 'accountable_id'], 'contact_bank_accounts_owner_idx');
        });

        $this->backfill('customers', \App\Models\Customer::class);
        $this->backfill('suppliers', \App\Models\Supplier::class);
    }

    /**
     * Übernimmt vorhandene bank_*-Spalten als primäre Bankverbindung.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $morphClass
     */
    private function backfill(string $table, string $morphClass): void {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'bank_iban')) {
            return;
        }

        DB::table($table)
            ->where(function ($q): void {
                $q->whereNotNull('bank_iban')
                    ->orWhereNotNull('bank_account_holder')
                    ->orWhereNotNull('bank_bic')
                    ->orWhereNotNull('bank_name');
            })
            ->orderBy('id')
            ->each(function (object $row) use ($morphClass): void {
                if (($row->bank_iban ?? null) === null
                    && ($row->bank_account_holder ?? null) === null
                    && ($row->bank_bic ?? null) === null
                    && ($row->bank_name ?? null) === null
                ) {
                    return;
                }

                DB::table('contact_bank_accounts')->insert([
                    'organization_id' => $row->organization_id ?? null,
                    'accountable_type' => $morphClass,
                    'accountable_id' => $row->id,
                    'account_holder' => $row->bank_account_holder ?? null,
                    'iban' => $row->bank_iban ?? null,
                    'bic' => $row->bank_bic ?? null,
                    'bank_name' => $row->bank_name ?? null,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void {
        Schema::dropIfExists('contact_bank_accounts');
    }
};
