<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_29_120000_create_number_formats_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('number_formats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 64);
            $table->string('prefix', 16)->default('');
            $table->string('prefix_separator', 4)->default('-');
            $table->boolean('include_year')->default(true);
            $table->string('year_separator', 4)->default('-');
            $table->unsignedTinyInteger('padding')->default(4);
            $table->boolean('reset_per_year')->default(true);
            $table->unsignedBigInteger('starts_at')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'scope'], 'number_formats_org_scope_uniq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('number_formats');
    }
};
