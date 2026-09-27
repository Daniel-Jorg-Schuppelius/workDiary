<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_270000_create_strategic_objectives_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-942: strategische Ziele mit Kennzahlen, Investitionen zugeordnet. */
return new class extends Migration {
    public function up(): void {
        Schema::create('strategic_objectives', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'strat_obj_org_fk')->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users', indexName: 'strat_obj_owner_fk')->nullOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'strat_obj_created_by_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('strategic_key_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'strat_kr_org_fk')->cascadeOnDelete();
            $table->foreignId('strategic_objective_id')->constrained('strategic_objectives', indexName: 'strat_kr_objective_fk')->cascadeOnDelete();
            $table->string('label', 200);
            $table->string('unit', 20)->nullable();
            $table->decimal('baseline_value', 16, 4)->nullable();
            $table->decimal('target_value', 16, 4);
            $table->decimal('current_value', 16, 4)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('investment_cases', function (Blueprint $table): void {
            $table->foreignId('strategic_objective_id')->nullable()->after('investment_program_id')
                ->constrained('strategic_objectives', indexName: 'inv_cases_objective_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('investment_cases', function (Blueprint $table): void {
            $table->dropForeign('inv_cases_objective_fk');
            $table->dropColumn('strategic_objective_id');
        });
        Schema::dropIfExists('strategic_key_results');
        Schema::dropIfExists('strategic_objectives');
    }
};
