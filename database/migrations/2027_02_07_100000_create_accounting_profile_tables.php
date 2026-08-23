<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_07_100000_create_accounting_profile_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 125, MVP-671: Einrichtungsprofil, Buchungshoheit und Perioden.
 *
 * Bewusst OHNE Buchungskern — der kommt mit MVP-672. Hier entsteht nur der
 * Rahmen, in dem später gebucht werden darf: wer für welchen Zeitraum das
 * Hauptbuch führt (`accounting_sovereignty_periods`), nach welchem Verfahren
 * ausgewertet wird und welche Perioden überhaupt offen sind.
 *
 * Bestandsorganisationen bekommen KEINE Zeile: fehlt das Profil, gilt
 * `preaccounting` (heutiger Zustand). Ein Deployment darf die Buchungshoheit
 * einer laufenden Organisation nicht verschieben.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_profiles', function (Blueprint $table): void {
            $table->id();
            // Genau ein Profil je Organisation — die Buchungshoheit ist eine
            // Eigenschaft des Mandanten, nicht des einzelnen Belegs.
            $table->foreignId('organization_id')->unique('acc_profile_org_unique')->constrained()->cascadeOnDelete();
            $table->string('sovereignty', 20)->default('preaccounting');
            // Führender Provider bei externer Hoheit (plugin_id, z. B. 'lexoffice').
            $table->string('external_provider', 64)->nullable();
            $table->string('profit_determination', 20)->default('euer');
            $table->string('base_currency', 3)->default('EUR');
            // 1–12; das Geschäftsjahr muss nicht im Januar beginnen.
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            // Ab diesem Tag entstehen lokale Buchungen; davor bleibt alles Historie.
            $table->date('starts_on')->nullable();
            $table->json('preflight')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Stichtags-Guard: lückenlose, überschneidungsfreie Abschnitte der
        // Buchungshoheit. Ein Wechsel schließt den laufenden Abschnitt und
        // eröffnet den nächsten — nie zwei Festschreibungsziele im selben
        // Zeitraum. Getrennt vom Profil, weil die Frage „wer führte im
        // März?" auch nach dem Wechsel beantwortbar bleiben muss.
        Schema::create('accounting_sovereignty_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('sovereignty', 20);
            $table->string('external_provider', 64)->nullable();
            $table->date('valid_from');
            // NULL = laufender Abschnitt (offenes Ende).
            $table->date('valid_to')->nullable();
            // Expliziter FK-Name: der generierte Name wäre 66 Zeichen lang und
            // damit über der MySQL-Grenze von 64.
            $table->unsignedBigInteger('accounting_migration_run_id')->nullable();
            $table->foreign('accounting_migration_run_id', 'acc_sov_run_fk')
                ->references('id')->on('accounting_migration_runs')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'valid_from'], 'acc_sov_org_from_unique');
            $table->index(['organization_id', 'valid_to'], 'acc_sov_org_to_idx');
        });

        Schema::create('accounting_fiscal_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('label', 32);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open');
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'starts_on'], 'acc_fy_org_start_unique');
            $table->unique(['organization_id', 'label'], 'acc_fy_org_label_unique');
            $table->index(['organization_id', 'status'], 'acc_fy_org_status_idx');
        });

        Schema::create('accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_fiscal_year_id')->constrained('accounting_fiscal_years')->cascadeOnDelete();
            // Laufende Nummer innerhalb des Geschäftsjahres (1 = erster Monat).
            $table->unsignedTinyInteger('sequence');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 20)->default('open');
            $table->dateTime('soft_closed_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['accounting_fiscal_year_id', 'sequence'], 'acc_period_fy_seq_unique');
            // Buchungsdatum → Periode ist der heißeste Lesepfad ab MVP-672.
            $table->index(['organization_id', 'starts_on', 'ends_on'], 'acc_period_org_range_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounting_fiscal_years');
        Schema::dropIfExists('accounting_sovereignty_periods');
        Schema::dropIfExists('accounting_profiles');
    }
};
