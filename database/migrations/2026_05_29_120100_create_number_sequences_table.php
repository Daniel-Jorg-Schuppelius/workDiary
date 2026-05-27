<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_29_120100_create_number_sequences_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 64);
            $table->string('period', 8)->nullable();
            $table->unsignedBigInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'scope', 'period'], 'number_sequences_org_scope_period_uniq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('number_sequences');
    }
};
