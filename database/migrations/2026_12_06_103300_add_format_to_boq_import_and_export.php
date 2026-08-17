<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_103300_add_format_to_boq_import_and_export.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formatfamilie an Import und Export (Feature 108, D6). Vergabestellen geben
 * dieselbe Ausschreibung als GAEB 90, GAEB 2000 oder DA XML heraus; zurück
 * erwarten sie das **Herkunftsformat**. Ohne die Spalte am LV wäre nach dem
 * Import nicht mehr bekannt, womit die Gegenseite arbeitet.
 *
 * Am Export steht zusätzlich, was die Wandlung gekostet hat: der Weg nach
 * GAEB 90 verliert Textergänzungen, Preisanteile und Katalogzuordnungen. Der
 * Befund gehört ins Protokoll, nicht ins Vergessen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('bill_of_quantities', function (Blueprint $table): void {
            $table->string('source_format', 16)->nullable()->after('gaeb_version');
        });

        Schema::table('gaeb_imports', function (Blueprint $table): void {
            $table->string('source_format', 16)->nullable()->after('filename');
        });

        Schema::table('boq_exports', function (Blueprint $table): void {
            $table->string('format', 16)->default('daxml')->after('gaeb_version');
            $table->json('losses')->nullable()->after('format');
        });
    }

    public function down(): void {
        Schema::table('bill_of_quantities', function (Blueprint $table): void {
            $table->dropColumn('source_format');
        });

        Schema::table('gaeb_imports', function (Blueprint $table): void {
            $table->dropColumn('source_format');
        });

        Schema::table('boq_exports', function (Blueprint $table): void {
            $table->dropColumn(['format', 'losses']);
        });
    }
};
