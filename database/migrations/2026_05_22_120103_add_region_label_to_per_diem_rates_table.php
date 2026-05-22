<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_22_120103_add_region_label_to_per_diem_rates_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erweitert `per_diem_rates` um eine Region-/Stadtspalte, damit die deutsche
 * BMF-Auslandstabelle korrekt abgebildet werden kann (z. B. „US: New York"
 * mit höherer Pauschale als der USA-Durchschnitt).
 *
 * `region_label` ist nullable; null = Standard-Pauschale des Landes.
 * Bei der Auflösung gilt: zuerst exakte Region-Übereinstimmung, sonst
 * Fallback auf null.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('per_diem_rates', function (Blueprint $table): void {
            $table->string('region_label', 100)->nullable()->after('country');
            $table->index(['country', 'region_label', 'valid_from'], 'per_diem_rates_country_region_from_idx');
        });
    }

    public function down(): void {
        Schema::table('per_diem_rates', function (Blueprint $table): void {
            $table->dropIndex('per_diem_rates_country_region_from_idx');
            $table->dropColumn('region_label');
        });
    }
};
