<?php
/*
 * Created on   : Fri Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_23_120000_create_invoice_schedules_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MVP-415: Wiederkehrende Rechnungen — Abrechnungsplan + Positionsvorlage + idempotente Läufe.
return new class extends Migration {
    public function up(): void {
        Schema::create('invoice_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->string('title', 180);
            // week | month | quarter | year
            $table->string('interval_unit', 10)->default('month');
            $table->unsignedSmallInteger('interval_count')->default(1);
            // previous = abgelaufener Zeitraum, current = laufender Zeitraum
            $table->string('billing_period_mode', 10)->default('previous');
            $table->date('next_run_on');
            $table->date('last_run_on')->nullable();
            $table->date('end_on')->nullable();
            // active | paused | ended
            $table->string('status', 10)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'next_run_on'], 'inv_schedules_org_status_next_idx');
        });

        Schema::create('invoice_schedule_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_schedule_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            // Platzhalter {zeitraum_von}/{zeitraum_bis} werden je Lauf ersetzt.
            $table->text('description');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit', 32)->nullable();
            $table->decimal('unit_price', 12, 4);
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->string('tax_category', 2)->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_schedule_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_schedule_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            // Idempotenz: je Plan und Periode höchstens ein Entwurf.
            $table->unique(['invoice_schedule_id', 'period_start'], 'inv_schedule_runs_period_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('invoice_schedule_runs');
        Schema::dropIfExists('invoice_schedule_items');
        Schema::dropIfExists('invoice_schedules');
    }
};
