<?php

/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_18_100000_create_entry_types_table.php
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
        Schema::create('entry_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()
                ->constrained('organizations')->cascadeOnDelete();

            $table->string('slug', 64);
            $table->string('label', 120);
            $table->string('icon', 64)->default('assignment');
            $table->string('color', 16)->default('primary');
            $table->string('description', 255)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);

            // Feld-Flags steuern UI-Sichtbarkeit & Pflichtfelder
            $table->boolean('requires_customer')->default(false);
            $table->boolean('requires_address')->default(false);
            $table->boolean('requires_schedule')->default(false);
            $table->boolean('requires_tour')->default(false);
            $table->boolean('allow_priority')->default(true);
            $table->boolean('allow_tour')->default(false);

            $table->tinyInteger('default_status')->default(2);
            $table->unsignedSmallInteger('default_service_minutes')->nullable();
            $table->string('default_priority', 16)->nullable();

            $table->timestamps();

            $table->unique(['organization_id', 'slug']);
            $table->index(['organization_id', 'is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_types');
    }
};
