<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_04_120300_create_etsy_ledger_entries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Etsy-Payment-Ledger-Spiegel (Feature 101, MVP-498): Unique je
 * (Org, ledger_entry_id). `amount`/`balance` bewusst als ROHE Integer in
 * kleinster Währungseinheit (Etsy liefert kein Money-Objekt und keinen
 * Divisor, W0 §6) — die Umrechnung passiert erst in der Anzeige.
 * `reference_id` ist bei Etsy ein String. Kurze, explizite Index-Namen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('etsy_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'etsyl_org_fk')->cascadeOnDelete();
            $table->unsignedBigInteger('ledger_entry_id');
            $table->string('ledger_type', 64)->nullable();
            $table->bigInteger('amount')->default(0);
            $table->bigInteger('balance')->default(0);
            $table->string('currency', 3)->nullable();
            $table->string('description')->nullable();
            $table->string('reference_type', 32)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->unsignedBigInteger('receipt_id')->nullable()->index('etsyl_receipt_idx');
            $table->timestamp('posted_at')->nullable()->index('etsyl_posted_idx');
            $table->timestamps();

            $table->unique(['organization_id', 'ledger_entry_id'], 'etsyl_org_entry_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('etsy_ledger_entries');
    }
};
