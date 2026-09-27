<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_350000_create_liquidity_scenarios_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-954: Szenarien zur Liquiditätsvorschau. */
return new class extends Migration {
    public function up(): void {
        Schema::create('liquidity_scenarios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'liq_scenarios_org_fk')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('receipt_delay_days')->default(0);
            $table->decimal('inflow_change_percent', 6, 2)->default(0);
            $table->decimal('outflow_change_percent', 6, 2)->default(0);
            $table->boolean('is_including_investments')->default(false);
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'liq_scenarios_creator_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('liquidity_scenario_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'liq_items_org_fk')->cascadeOnDelete();
            $table->foreignId('liquidity_scenario_id')->constrained('liquidity_scenarios', indexName: 'liq_items_scenario_fk')->cascadeOnDelete();
            $table->string('label', 200);
            $table->string('direction', 3);
            $table->decimal('amount', 14, 2);
            $table->date('expected_on');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('liquidity_scenario_items');
        Schema::dropIfExists('liquidity_scenarios');
    }
};
