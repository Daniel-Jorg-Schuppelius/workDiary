<?php

/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_16_120000_create_project_billing_rules_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_billing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('plugin_id', 50)->default('lexoffice');
            $table->string('applies_to_kind', 30)->nullable();
            $table->string('lexoffice_article_id')->nullable();
            $table->string('item_type', 20)->default('service');
            $table->string('unit_name', 50)->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->decimal('net_unit_price', 12, 4)->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'plugin_id', 'applies_to_kind'], 'pbr_proj_plugin_kind_idx');
            $table->index(['organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_billing_rules');
    }
};
