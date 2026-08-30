<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_105300_create_learning_paths_and_competencies.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lernpfade und Kompetenzmatrix (Feature 149, MVP-745).
 *
 * **Kompetenz ist nicht Qualifikation.** Die Qualifikation (Feature 013)
 * ist ein Nachweis mit Gültigkeit und Sperrwirkung — die Kompetenz ist eine
 * Einschätzung mit Stufe ("kann anleiten"). Sie sperrt nichts; sie zeigt
 * Lücken. Deshalb eine eigene Tabelle statt einer Erweiterung von 013.
 *
 * Die Soll-Matrix folgt dem Muster der Pflichtmatrix aus Feature 145
 * (`subject_kind` role|team + `subject_key`), damit beide gleich gelesen
 * werden.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_paths', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('title', 180);
            $table->text('description')->nullable();
            // Zielgruppe als Rollen-Slug — wie in der Pflichtmatrix (145).
            $table->string('target_role', 60)->nullable();
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'lrn_path_org_code_uq');
        });

        Schema::create('learning_path_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_path_id')->constrained('learning_paths')->cascadeOnDelete();
            $table->foreignId('learning_course_id')->constrained('learning_courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_mandatory')->default(true);
            // Frist ab Start des Pfades.
            $table->unsignedSmallInteger('due_days')->nullable();
            $table->timestamps();

            $table->unique(['learning_path_id', 'learning_course_id'], 'lrn_path_item_uq');
            $table->index(['learning_path_id', 'position'], 'lrn_path_item_pos_idx');
        });

        Schema::create('competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 180);
            $table->text('description')->nullable();
            // Stufen 1..max_level (z. B. 1 Grundkenntnis … 4 kann anleiten).
            $table->unsignedTinyInteger('max_level')->default(4);
            $table->string('category', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'competency_org_code_uq');
        });

        Schema::create('user_competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            // course|assessment — woher die Einschätzung stammt. Eine
            // Einschätzung ohne Herkunft wäre nicht überprüfbar.
            $table->string('source', 12)->default('assessment');
            $table->foreignId('learning_enrollment_id')->nullable()->constrained('learning_enrollments')->nullOnDelete();
            $table->foreignId('assessed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('assessed_on');
            $table->date('valid_until')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'competency_id'], 'user_competency_uq');
            $table->index(['organization_id', 'competency_id', 'level'], 'user_competency_level_idx');
        });

        Schema::create('competency_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            // role|team wie in der Pflichtmatrix aus Feature 145.
            $table->string('subject_kind', 10);
            $table->string('subject_key', 60);
            $table->unsignedTinyInteger('required_level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'competency_id', 'subject_kind', 'subject_key'], 'competency_req_uq');
        });

        Schema::table('learning_courses', function (Blueprint $table): void {
            // Welche Kompetenzstufe der Abschluss belegt (optional).
            $table->foreignId('competency_id')->nullable()->after('qualification_id')->constrained('competencies')->nullOnDelete();
            $table->unsignedTinyInteger('competency_level')->nullable()->after('competency_id');
        });
    }

    public function down(): void {
        Schema::table('learning_courses', function (Blueprint $table): void {
            $table->dropColumn('competency_level');
            $table->dropConstrainedForeignId('competency_id');
        });

        Schema::dropIfExists('competency_requirements');
        Schema::dropIfExists('user_competencies');
        Schema::dropIfExists('competencies');
        Schema::dropIfExists('learning_path_items');
        Schema::dropIfExists('learning_paths');
    }
};
