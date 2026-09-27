<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_330000_create_price_index_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-952: Verbraucherpreisindex mit Freigabe und Indexanpassungen an Verträgen. */
return new class extends Migration {
    public function up(): void {
        Schema::create('price_index_values', function (Blueprint $table): void {
            $table->id();
            $table->string('series', 20);
            $table->date('period_on');
            $table->decimal('value', 10, 1);
            $table->string('source', 30);
            $table->string('status', 16);
            $table->foreignId('approver_user_id')->nullable()->constrained('users', indexName: 'price_index_approver_fk')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['series', 'period_on'], 'price_index_series_period_unique');
        });

        Schema::table('contracts', function (Blueprint $table): void {
            $table->decimal('indexation_base_value', 10, 1)->nullable()->after('indexation_note');
            $table->date('indexation_base_period_on')->nullable()->after('indexation_base_value');
            $table->decimal('indexation_threshold_percent', 6, 2)->nullable()->after('indexation_base_period_on');
            $table->decimal('indexation_pass_through_percent', 5, 2)->nullable()->after('indexation_threshold_percent');
        });

        Schema::create('contract_indexations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'contract_idx_org_fk')->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained('contracts', indexName: 'contract_idx_contract_fk')->cascadeOnDelete();
            $table->string('status', 16);
            $table->date('base_period_on');
            $table->decimal('base_value', 10, 1);
            $table->date('index_period_on');
            $table->decimal('index_value', 10, 1);
            $table->decimal('change_percent', 8, 4);
            $table->decimal('old_amount', 14, 2);
            $table->decimal('new_amount', 14, 2);
            $table->string('currency', 3);
            $table->date('effective_on')->nullable();
            $table->foreignId('decider_user_id')->nullable()->constrained('users', indexName: 'contract_idx_decider_fk')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->index(['contract_id', 'status'], 'contract_idx_contract_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('contract_indexations');
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn(['indexation_base_value', 'indexation_base_period_on', 'indexation_threshold_percent', 'indexation_pass_through_percent']);
        });
        Schema::dropIfExists('price_index_values');
    }
};
