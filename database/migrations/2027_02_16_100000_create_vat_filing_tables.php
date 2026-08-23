<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_16_100000_create_vat_filing_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meldeprofil der Umsatzsteuer (Feature 125, MVP-684).
 *
 * Der Voranmeldungszeitraum ist ein **Abschnitt**, kein Feld: Ein Wechsel
 * geschieht zum Jahreswechsel, und „welcher Zeitraum galt 2025?" muss auch
 * Jahre später beantwortbar sein. Die Dauerfristverlängerung dagegen hängt am
 * Kalenderjahr — sie gilt zwar dauerhaft weiter, die Sondervorauszahlung ist
 * aber jedes Jahr neu anzumelden (§ 48 UStDV).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_vat_filing_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('interval', 16);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'valid_from'], 'acc_vatint_org_from_uq');
            $table->index(['organization_id', 'valid_to'], 'acc_vatint_org_to_idx');
            $table->foreign('actor_user_id', 'acc_vatint_actor_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('accounting_vat_extensions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // Kalenderjahr — der Voranmeldungszeitraum folgt dem Kalenderjahr,
            // auch bei abweichendem Geschäftsjahr.
            $table->unsignedSmallInteger('year');
            $table->date('granted_on')->nullable();
            $table->decimal('special_prepayment_amount', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->unsignedBigInteger('special_prepayment_entry_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'year'], 'acc_vatext_org_year_uq');
            $table->foreign('special_prepayment_entry_id', 'acc_vatext_entry_fk')
                ->references('id')->on('accounting_entries')->nullOnDelete();
            $table->foreign('actor_user_id', 'acc_vatext_actor_fk')
                ->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_vat_extensions');
        Schema::dropIfExists('accounting_vat_filing_periods');
    }
};
