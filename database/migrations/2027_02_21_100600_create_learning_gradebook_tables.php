<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_21_100600_create_learning_gradebook_tables.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notenbuch-Ausbau (Feature 149, MVP-790): gewichtete Komponenten je Kurs
 * (Prüfung, Aufgabe, manuelle Note) und manuelle Noten je Einschreibung —
 * additiv, die jüngste zählt. Ohne Komponenten rechnet das Notenbuch wie
 * bisher aus Versuchen und Abgaben; gespeichert wird weiterhin kein
 * Gesamtergebnis.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_gradebook_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_course_id')->constrained('learning_courses')->cascadeOnDelete();
            // quiz | assignment | manual
            $table->string('kind', 12);
            $table->foreignId('learning_unit_id')->nullable()->constrained('learning_units')->nullOnDelete();
            $table->string('title', 180);
            // NULL = ungewichtet (Punkte addieren); sonst Summe aller Komponenten = 100.
            $table->unsignedTinyInteger('weight_percent')->nullable();
            // Nur bei manuellen Komponenten: Bezugsgröße der Note.
            $table->unsignedInteger('max_points')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['learning_course_id', 'position'], 'lrn_gb_comp_course_pos_idx');
        });

        Schema::create('learning_manual_grades', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('learning_enrollment_id')->constrained('learning_enrollments')->cascadeOnDelete();
            $table->foreignId('learning_gradebook_component_id')->constrained('learning_gradebook_components')->cascadeOnDelete();
            $table->unsignedInteger('points');
            $table->unsignedInteger('max_points');
            $table->text('note')->nullable();
            $table->foreignId('graded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('graded_at');
            $table->timestamps();

            $table->index(['learning_enrollment_id', 'learning_gradebook_component_id'], 'lrn_manual_grade_enr_comp_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('learning_manual_grades');
        Schema::dropIfExists('learning_gradebook_components');
    }
};
