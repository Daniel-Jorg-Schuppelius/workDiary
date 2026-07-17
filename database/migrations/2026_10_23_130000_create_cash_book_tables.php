<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_23_130000_create_cash_book_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MVP-414: GoBD-konformes Kassenbuch — Kassen, append-only Einträge (Hash-Kette), Tagesabschlüsse.
return new class extends Migration {
    public function up(): void {
        Schema::create('cash_registers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->date('opened_on');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'active'], 'cash_registers_org_active_idx');
        });

        Schema::create('cash_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            // Fortlaufende, lückenlose Belegnummer je Kasse (GoBD).
            $table->unsignedInteger('seq_no');
            $table->date('booked_on');
            // in | out
            $table->string('direction', 3);
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->string('purpose', 500);
            $table->string('counterparty', 180)->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            // Storno statt Löschen: Gegenbuchung verweist auf das Original.
            $table->foreignId('reversal_of_id')->nullable()->constrained('cash_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Revisionssichere Hash-Kette (config/audit.php, audit:verify).
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['cash_register_id', 'seq_no'], 'cash_entries_register_seq_uq');
            $table->index(['organization_id', 'booked_on'], 'cash_entries_org_booked_idx');
        });

        Schema::create('cash_daily_closings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->date('closing_date');
            $table->decimal('expected_balance', 12, 2);
            $table->decimal('counted_balance', 12, 2);
            $table->decimal('difference', 12, 2);
            $table->string('note', 500)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['cash_register_id', 'closing_date'], 'cash_closings_register_date_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('cash_daily_closings');
        Schema::dropIfExists('cash_entries');
        Schema::dropIfExists('cash_registers');
    }
};
