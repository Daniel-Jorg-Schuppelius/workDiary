<?php
/*
 * Created on   : Fri Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_12_100000_create_accounting_recurring_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 125, MVP-675: wiederkehrende Belegerwartungen und Buchungsvorlagen.
 *
 * Wiederkehrende AUSGANGSrechnungen bleiben beim vorhandenen
 * `InvoiceSchedule` — hier entstehen nur die beiden Arten, für die es bisher
 * keinen Ort gab.
 *
 * Die Idempotenz hängt an `(template, period_key)`: Ein zweiter Lauf für
 * dieselbe Periode findet den Vorgang vor, statt ihn zu verdoppeln. Genau das
 * ist die Abnahmebedingung des Pakets.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('accounting_recurring_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('name', 191);
            $table->string('interval', 24);
            // Fälligkeitstag innerhalb der Periode (1–28, damit jeder Monat ihn hat).
            $table->unsignedTinyInteger('due_day')->default(1);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_due_on')->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedSmallInteger('version')->default(1);
            $table->decimal('expected_amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->unsignedBigInteger('supplier_id')->nullable();
            // Buchungsvorlage: Zeilen als Konten-/Betragsvorlage.
            $table->json('template_lines')->nullable();
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'next_due_on'], 'acc_rec_org_status_due_idx');

            $table->foreign('supplier_id', 'acc_rec_supplier_fk')
                ->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('responsible_user_id', 'acc_rec_user_fk')
                ->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('accounting_recurring_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('accounting_recurring_template_id');
            $table->string('period_key', 16);
            $table->date('due_on');
            $table->string('status', 24)->default('expected');
            $table->decimal('expected_amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            // Buchungsvorlage: erzeugter Entwurf. Belegerwartung: erfüllender Beleg.
            $table->unsignedBigInteger('accounting_entry_id')->nullable();
            $table->nullableMorphs('fulfilled_by', 'acc_rec_run_fulfilled_idx');
            $table->dateTime('fulfilled_at')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->dateTime('notified_at')->nullable();
            $table->timestamps();

            // Idempotenz: je Vorlage und Periode höchstens ein Vorgang.
            $table->unique(['accounting_recurring_template_id', 'period_key'], 'acc_rec_run_period_uq');
            $table->index(['organization_id', 'status', 'due_on'], 'acc_rec_run_org_status_idx');

            $table->foreign('accounting_recurring_template_id', 'acc_rec_run_template_fk')
                ->references('id')->on('accounting_recurring_templates')->cascadeOnDelete();
            $table->foreign('accounting_entry_id', 'acc_rec_run_entry_fk')
                ->references('id')->on('accounting_entries')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::dropIfExists('accounting_recurring_runs');
        Schema::dropIfExists('accounting_recurring_templates');
    }
};
