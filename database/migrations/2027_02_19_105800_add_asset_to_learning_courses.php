<?php
/*
 * Created on   : Sat Aug 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_19_105800_add_asset_to_learning_courses.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geräteeinweisung (Feature 149, MVP-740, Konzept Abschnitt 11 Nr. 5).
 *
 * Ein Zeiger auf `assets` — mehr nicht. Damit ist die Einweisung **am
 * konkreten Gerät** nachweisbar (Betreiberpflicht) und die Geräteausgabe
 * kann sie voraussetzen.
 *
 * **Kein zweiter Guard:** die Sperrwirkung bleibt beim Qualifikationsstatus
 * (Feature 013) und beim Asset-Sperrmodell. Das LMS liefert den Nachweis,
 * es sperrt nichts.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('learning_courses', function (Blueprint $table): void {
            $table->foreignId('asset_id')->nullable()->after('qualification_id')
                ->constrained('assets')->nullOnDelete();
        });

        Schema::table('safety_instructions', function (Blueprint $table): void {
            // Der Nachweis trägt das Gerät mit: ohne ihn wäre nur
            // dokumentiert, DASS unterwiesen wurde, nicht WORAN.
            $table->foreignId('asset_id')->nullable()->after('training_course_version_id')
                ->constrained('assets')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('safety_instructions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('asset_id');
        });

        Schema::table('learning_courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
