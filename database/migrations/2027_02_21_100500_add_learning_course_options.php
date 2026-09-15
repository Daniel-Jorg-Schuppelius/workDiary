<?php
/*
 * Created on   : Tue Sep 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_21_100500_add_learning_course_options.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kursoptionen (Feature 149, MVP-788): Kurskategorien, Verfügbarkeitsfenster
 * und Teilnehmergrenze am Kurs, Vorschau-Einheiten, Dateiregeln und
 * Auto-Freigabe an Aufgaben. Freischaltplan und Mindestverweildauer leben
 * weiter in `release_rule`/`completion_rule` — sie bekommen nur neue Schlüssel.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_course_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'slug'], 'lrn_course_cat_org_slug_uq');
        });

        Schema::table('learning_courses', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('kind')
                ->constrained('learning_course_categories')->nullOnDelete();
            // Katalogsichtbarkeit und Selbsteinschreibung; Zuweisung bleibt frei.
            $table->date('available_from')->nullable()->after('access_days');
            $table->date('available_until')->nullable()->after('available_from');
            // Aktive Einschreibungen; Pflicht umgeht, Buchung zählt erst bei Zusage.
            $table->unsignedInteger('max_enrollments')->nullable()->after('available_until');
        });

        Schema::table('learning_units', function (Blueprint $table): void {
            // Ohne Einschreibung sichtbar (nur Inhaltsblöcke) — Katalog und Portal.
            $table->boolean('is_preview')->default(false)->after('is_mandatory');
        });

        Schema::table('learning_assignments', function (Blueprint $table): void {
            $table->json('allowed_extensions')->nullable()->after('submission_kind');
            $table->unsignedTinyInteger('max_files')->nullable()->after('allowed_extensions');
            $table->unsignedSmallInteger('max_file_mb')->nullable()->after('max_files');
            // Volle Punkte bei Abgabe, ohne Bewerter — mit Vier-Augen unvereinbar.
            $table->boolean('auto_approve')->default(false)->after('requires_second_opinion');
        });
    }

    public function down(): void {
        Schema::table('learning_assignments', function (Blueprint $table): void {
            $table->dropColumn(['allowed_extensions', 'max_files', 'max_file_mb', 'auto_approve']);
        });
        Schema::table('learning_units', function (Blueprint $table): void {
            $table->dropColumn('is_preview');
        });
        Schema::table('learning_courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['available_from', 'available_until', 'max_enrollments']);
        });
        Schema::dropIfExists('learning_course_categories');
    }
};
