<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_101400_create_time_accounts_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-526 (Feature 103): generisches Zeitkonten-Framework — konfigurierbare
 * Zusatzkonten (Schichtkonten, Freizeit-/Ansparkonten, Zähler) mit
 * append-only Journal (Storno statt Update) und deklarativen
 * Bebuchungsregeln auf vorhandene Quellen (time_rule_results,
 * Anwesenheits-Netto, Abwesenheiten, Schichttyp-Zähler, externe
 * Lohnarten-Positionen). Gleitzeit- und Urlaubskonto bleiben bewusst die
 * bestehenden Spezialkonten — keine Doppelführung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('time_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('unit', 16)->default('minutes');
            // Ampel-Schwellen (absolut, in Konteneinheit); NULL = keine Ampel.
            $table->decimal('warn_threshold', 12, 2)->nullable();
            $table->decimal('critical_threshold', 12, 2)->nullable();
            // Übertrag: carry (kumulierend) | cap (Kappung beim Monatsabschluss).
            $table->string('carryover_policy', 16)->default('carry');
            $table->decimal('cap_amount', 12, 2)->nullable();
            $table->boolean('show_on_terminal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'tacc_org_code_unique');
        });

        Schema::create('time_account_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('time_account_id')->constrained('time_accounts', indexName: 'taccr_account_fk')->cascadeOnDelete();
            // Quelle: wage_type | attendance_net | absence | shift_type_count | external_item
            $table->string('source_type', 32);
            // Quell-Match: Lohnart-Muster (Str::is), Abwesenheitsart, ShiftType-ID …
            $table->string('match_value', 128)->nullable();
            $table->decimal('factor', 8, 4)->default(1);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
        });

        Schema::create('time_account_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained(indexName: 'tacce_org_fk')->cascadeOnDelete();
            $table->foreignId('time_account_id')->constrained('time_accounts', indexName: 'tacce_account_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', indexName: 'tacce_user_fk')->cascadeOnDelete();
            $table->date('booking_date');
            $table->decimal('quantity', 12, 2);
            // Quell-Bezug für Idempotenz/Nachvollziehbarkeit; NULL = manuell.
            $table->string('source_type', 32)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users', indexName: 'tacce_posted_fk')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('time_account_entries', indexName: 'tacce_reversal_fk')->nullOnDelete();
            $table->timestamps();

            $table->index(['time_account_id', 'user_id', 'booking_date'], 'tacce_acc_user_date_idx');
            $table->index(['time_account_id', 'source_type', 'source_id'], 'tacce_source_idx');
        });

        Schema::create('time_account_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained(indexName: 'taccb_org_fk')->cascadeOnDelete();
            $table->foreignId('time_account_id')->constrained('time_accounts', indexName: 'taccb_account_fk')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users', indexName: 'taccb_user_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            // Monatsumsatz + kumulierter Stand zum Monatsende.
            $table->decimal('turnover', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->timestamp('computed_at')->nullable();

            $table->unique(['time_account_id', 'user_id', 'year', 'month'], 'taccb_slot_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('time_account_balances');
        Schema::dropIfExists('time_account_entries');
        Schema::dropIfExists('time_account_rules');
        Schema::dropIfExists('time_accounts');
    }
};
