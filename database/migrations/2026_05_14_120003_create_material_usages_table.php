<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_14_120003_create_material_usages_table.php
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
        Schema::create('material_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('timesheet_id')->constrained('timesheets')->cascadeOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 20)->default('Stk.');
            $table->decimal('unit_price', 10, 4)->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->decimal('line_total_net', 12, 2)->default(0);
            $table->timestamps();

            $table->index('timesheet_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_usages');
    }
};
