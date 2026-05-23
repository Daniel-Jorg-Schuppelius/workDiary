<?php

/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_03_130000_create_classification_requirements_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('classification_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('entry_type_code', 60);
            $table->string('required_domain', 40);
            $table->string('enforce_phase', 20);
            $table->string('severity', 20);
            $table->boolean('allow_multi')->default(false);
            $table->integer('min_count')->default(1);
            $table->integer('max_count')->nullable();
            $table->json('only_if_json')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(
                ['organization_id', 'entry_type_code', 'required_domain', 'enforce_phase'],
                'classification_requirements_uniq_req'
            );
            $table->index(['organization_id', 'entry_type_code', 'enforce_phase'], 'classification_requirements_lookup_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('classification_requirements');
    }
};
