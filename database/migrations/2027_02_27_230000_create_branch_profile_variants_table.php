<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_230000_create_branch_profile_variants_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-933: kundenspezifische Varianten eines Branchenprofils als Überlagerung. */
return new class extends Migration {
    public function up(): void {
        Schema::create('branch_profile_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'bp_variants_org_fk')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('label', 200);
            $table->text('description')->nullable();
            $table->string('base_code', 60);
            $table->json('removals')->nullable();
            $table->json('additions')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users', indexName: 'bp_variants_created_by_fk')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users', indexName: 'bp_variants_updated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'bp_variants_org_code_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('branch_profile_variants');
    }
};
