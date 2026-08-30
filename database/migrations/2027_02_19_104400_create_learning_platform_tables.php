<?php
/*
 * Created on   : Fri Aug 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_104400_create_learning_platform_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lernplattform, Datenfundament (Feature 149, MVP-735): Kurs → Abschnitt →
 * Lerneinheit plus eingefrorene Inhaltsversion.
 *
 * Kein zweiter Katalog: `training_courses` (Feature 145) bleibt das Soll
 * („wer muss was bis wann"); ein Lernkurs hängt optional daran und liefert
 * nur die Durchführungsform. Die Freigabe schreibt die 145-Kursversion —
 * einzige Schreibrichtung zwischen beiden Modulen.
 *
 * Kein SoftDelete auf Kurs/Version: die Unique-Schlüssel (Kurscode je Org,
 * Version je Kurs, 1:1-Bezug zum Trainingskurs) müssten sonst gelöschte
 * Zeilen mitzählen (bekannte 1062-Falle). Ausmustern läuft über den Status
 * `archived`.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('title', 180);
            $table->string('subtitle', 255)->nullable();
            $table->text('description')->nullable();
            $table->text('objectives')->nullable();
            $table->string('language', 5)->default('de');
            $table->string('status', 12)->default('draft'); // draft|review|released|archived
            // Zielgruppen als Liste (internal|external|customer|public):
            // steuert nur die Sichtbarkeit, nie die Beweiskraft des Nachweises.
            $table->json('audiences')->nullable();
            $table->string('access_kind', 12)->default('enrolled'); // open|enrolled|bookable|closed
            // Optionale Kopplung an das Soll aus Feature 145 (1:1 je Org).
            $table->foreignId('training_course_id')->nullable()->constrained('training_courses')->nullOnDelete();
            // Optionaler Verkaufsartikel — Faktura bleibt führend (kein Payment im LMS).
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('validity_months')->nullable();
            $table->unsignedSmallInteger('points')->default(0);
            // Zeitpolitik: entscheidet, ob Lernen außerhalb der Arbeitszeit
            // erlaubt ist und ob es als Arbeitszeit zählt (§ 12 ArbSchG).
            $table->string('time_policy', 20)->default('work_time_required');
            // Unterweisungstauglichkeit: reines E-Learning genügt den
            // Unfallversicherungsträgern meist nur ergänzend.
            $table->string('instruction_suitability', 20)->default('supplementary');
            $table->boolean('certificate_enabled')->default(false);
            $table->unsignedSmallInteger('access_days')->nullable();
            $table->boolean('sequential')->default(false);
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'lrn_course_org_code_uq');
            $table->unique(['organization_id', 'training_course_id'], 'lrn_course_org_training_uq');
            $table->index(['organization_id', 'status'], 'lrn_course_org_status_idx');
        });

        Schema::create('learning_course_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_course_id')->constrained('learning_courses')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('label', 60)->nullable();
            // Eingefrorener Inhaltsbaum: ohne ihn ließe sich ein Nachweis
            // nach einer Kursänderung nicht mehr erklären.
            $table->longText('content_snapshot')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_current')->default(false);
            // Spiegel in die 145-Kursversion (nur diese Richtung).
            $table->foreignId('training_course_version_id')->nullable()->constrained('training_course_versions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['learning_course_id', 'version'], 'lrn_course_ver_uq');
            $table->index(['organization_id', 'learning_course_id'], 'lrn_course_ver_org_idx');
        });

        Schema::create('learning_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_course_id')->constrained('learning_courses')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['learning_course_id', 'position'], 'lrn_section_course_pos_idx');
        });

        Schema::create('learning_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_course_id')->constrained('learning_courses')->cascadeOnDelete();
            $table->foreignId('learning_section_id')->nullable()->constrained('learning_sections')->cascadeOnDelete();
            $table->string('title', 180);
            // content|quiz|assignment|procedure|event|scorm|survey|external
            $table->string('kind', 12)->default('content');
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            // Inhaltsblöcke bzw. Zeiger auf die Fremdressource.
            $table->longText('content')->nullable();
            // Abschlusskriterium und Freischaltregel als Regelobjekte.
            $table->json('completion_rule')->nullable();
            $table->json('release_rule')->nullable();
            $table->timestamps();

            $table->index(['learning_course_id', 'position'], 'lrn_unit_course_pos_idx');
            $table->index(['organization_id', 'kind'], 'lrn_unit_org_kind_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_units');
        Schema::dropIfExists('learning_sections');
        Schema::dropIfExists('learning_course_versions');
        Schema::dropIfExists('learning_courses');
    }
};
