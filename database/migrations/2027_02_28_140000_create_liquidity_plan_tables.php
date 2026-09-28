<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_140000_create_liquidity_plan_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-984: manuelle Planpositionen der Liquiditätsvorschau und festgehaltene Wochenstände für Plan/Ist. */
return new class extends Migration {
    public function up(): void {
        Schema::create('liquidity_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'liq_plan_org_fk')->cascadeOnDelete();
            $table->string('label', 200);
            $table->string('direction', 3);
            $table->decimal('planned_amount', 15, 2);
            $table->char('currency', 3);
            $table->date('starts_on');
            $table->string('recurrence', 10);
            $table->date('ends_on')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'liq_plan_creator_fk')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('liquidity_forecast_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'liq_snap_org_fk')->cascadeOnDelete();
            $table->date('taken_on');
            $table->decimal('opening_balance', 15, 2);
            $table->json('weeks');
            $table->timestamps();
            $table->unique(['organization_id', 'taken_on'], 'liq_snap_org_taken_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('liquidity_forecast_snapshots');
        Schema::dropIfExists('liquidity_plan_items');
    }
};
