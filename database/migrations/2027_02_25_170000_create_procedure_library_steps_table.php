<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_25_170000_create_procedure_library_steps_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-896: wiederverwendbare Prozedurschritte; eingefügt wird eine Kopie, die Herkunft bleibt am Schritt. */
return new class extends Migration {
    public function up(): void {
        Schema::create('procedure_library_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('step_kind', 40);
            $table->string('label', 180);
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_blocking')->default(true);
            $table->json('config')->nullable();
            $table->string('required_role', 40)->nullable();
            $table->string('required_qualification_code', 60)->nullable();
            $table->boolean('requires_second_person')->default(false);
            $table->string('requires_proof_kind', 40)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'procedure_library_steps_org_code_uniq');
        });

        Schema::table('procedure_step_defs', function (Blueprint $table): void {
            $table->foreignId('library_step_id')->nullable()->constrained('procedure_library_steps')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('procedure_step_defs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('library_step_id');
        });
        Schema::dropIfExists('procedure_library_steps');
    }
};
