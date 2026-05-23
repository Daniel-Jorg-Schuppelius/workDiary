<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_26_120100_create_procedure_template_versions_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('procedure_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('procedure_template_id')->constrained('procedure_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->text('change_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('risk_level', 20)->default('normal');
            $table->json('applicability')->nullable();
            $table->timestamps();

            $table->unique(['procedure_template_id', 'version'], 'procedure_template_versions_uniq');
            $table->index(['procedure_template_id', 'valid_from', 'valid_to'], 'procedure_template_versions_valid_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('procedure_template_versions');
    }
};
