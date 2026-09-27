<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_180000_create_investment_programs_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-927: mehrjährige Investitionsprogramme mit Jahresbudgets. */
return new class extends Migration {
    public function up(): void {
        Schema::create('investment_programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'inv_programs_org_fk')->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('starts_year');
            $table->unsignedSmallInteger('ends_year');
            $table->string('currency', 3)->default('EUR');
            $table->string('status', 16);
            $table->foreignId('responsible_user_id')->nullable()->constrained('users', indexName: 'inv_programs_responsible_fk')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'inv_programs_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'inv_programs_updated_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('investment_program_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'inv_prog_budgets_org_fk')->cascadeOnDelete();
            $table->foreignId('investment_program_id')->constrained('investment_programs', indexName: 'inv_prog_budgets_program_fk')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('budget_amount', 14, 2);
            $table->timestamps();

            $table->unique(['investment_program_id', 'year'], 'inv_prog_budgets_program_year_uq');
        });

        Schema::table('investment_cases', function (Blueprint $table): void {
            $table->foreignId('investment_program_id')->nullable()->after('project_id')
                ->constrained('investment_programs', indexName: 'inv_cases_program_fk')->nullOnDelete();
            $table->unsignedSmallInteger('planned_year')->nullable()->after('investment_program_id');
        });
    }

    public function down(): void {
        Schema::table('investment_cases', function (Blueprint $table): void {
            $table->dropForeign('inv_cases_program_fk');
            $table->dropColumn(['investment_program_id', 'planned_year']);
        });
        Schema::dropIfExists('investment_program_budgets');
        Schema::dropIfExists('investment_programs');
    }
};
