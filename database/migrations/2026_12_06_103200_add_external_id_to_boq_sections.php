<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_103200_add_external_id_to_boq_sections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kennung der LV-Gruppe aus der GAEB-Datei (`BoQCtgy/@ID`). Ab GAEB DA XML 3.3
 * ist das Attribut Pflicht (`xs:ID`) und die Klammer zu Fremdsystemen bzw. zum
 * BIM-Modell; ohne die Spalte erzeugt jeder Export eine neue Kennung und die
 * Verknüpfung bricht. Positionen führen dasselbe Feld bereits.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('boq_sections', function (Blueprint $table): void {
            $table->string('external_id', 64)->nullable()->after('label');
        });
    }

    public function down(): void {
        Schema::table('boq_sections', function (Blueprint $table): void {
            $table->dropColumn('external_id');
        });
    }
};
