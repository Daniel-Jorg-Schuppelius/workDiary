<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_03_120000_create_classifications_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('classifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')
                ->nullable()
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('domain', 40);
            $table->string('code', 60);
            $table->string('label', 180);
            $table->json('label_i18n')->nullable();
            $table->integer('sort_order')->default(100);
            $table->char('color_hex', 7)->nullable();
            $table->string('icon', 60)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('deprecated_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'domain', 'code'], 'classifications_uniq_code');
            $table->index(['organization_id', 'domain', 'active'], 'classifications_lookup_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('classifications');
    }
};
