<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_27_160000_create_job_application_ratings_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-924: Kompetenz-Einschätzung je Bewerbung für die Eignungsmatrix. */
return new class extends Migration {
    public function up(): void {
        Schema::create('job_application_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'job_app_ratings_org_fk')->cascadeOnDelete();
            $table->foreignId('job_application_id')->constrained('job_applications', indexName: 'job_app_ratings_app_fk')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies', indexName: 'job_app_ratings_comp_fk')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->text('note')->nullable();
            $table->foreignId('rated_by')->nullable()->constrained('users', indexName: 'job_app_ratings_rated_by_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['job_application_id', 'competency_id'], 'job_app_ratings_app_comp_uq');
        });
    }

    public function down(): void {
        Schema::dropIfExists('job_application_ratings');
    }
};
