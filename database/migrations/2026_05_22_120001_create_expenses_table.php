<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_22_120001_create_expenses_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spesen / Auslagen (Expense reports):
 *
 *  - Erfasst einzelne Belege/Auslagen einer Person (Restaurant, Tanken,
 *    Übernachtung, Bewirtung, Material, …).
 *  - Optional verknüpft mit Projekt/Kunde/Aufgabe sowie einer
 *    Anwesenheit (für tagesbezogene Auswertung).
 *  - Status-Workflow: draft → pending → approved → reimbursed/invoiced;
 *    oder rejected/cancelled als Endzustand.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->nullable()
                ->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('project_id')->nullable()
                ->constrained('projects')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')->nullOnDelete();
            $table->foreignId('task_id')->nullable()
                ->constrained('tasks')->nullOnDelete();
            $table->foreignId('attendance_id')->nullable()
                ->constrained('attendances')->nullOnDelete();

            $table->date('date');
            $table->string('vendor')->nullable();
            $table->string('description');
            $table->string('payment_method', 32)->default('private_paid');
            $table->string('currency', 3)->default('EUR');
            $table->decimal('amount_net', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('amount_gross', 12, 2)->default(0);
            $table->boolean('billable')->default(false);

            // draft | pending | approved | rejected | cancelled | reimbursed | invoiced
            $table->string('status', 20)->default('draft');
            $table->foreignId('decided_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('reject_reason')->nullable();

            $table->timestamp('reimbursed_at')->nullable();
            $table->string('reimbursement_reference')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['organization_id', 'date']);
            $table->index(['status', 'date']);
            $table->index(['organization_id', 'status']);
            $table->index('project_id');
            $table->index('customer_id');
            $table->index('billable');
        });
    }

    public function down(): void {
        Schema::dropIfExists('expenses');
    }
};
