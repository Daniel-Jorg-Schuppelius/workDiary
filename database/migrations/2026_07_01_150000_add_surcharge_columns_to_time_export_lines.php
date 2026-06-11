<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_01_150000_add_surcharge_columns_to_time_export_lines.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuschlags-Erweiterung der Export-Zeilen (Feature 005, MVP).
 *
 * Zuschlagszeilen referenzieren ihre Regel und tragen die für
 * DATEV/Lexware nötige Lohnart sowie den angewandten Prozentsatz.
 * Alle Spalten nullable — bestehende work.normal-Zeilen bleiben unberührt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('time_export_lines', function (Blueprint $t): void {
            $t->foreignId('surcharge_rule_id')->nullable()
                ->after('source_refs')
                ->constrained('surcharge_rules', indexName: 'tel_sur_rule_fk')
                ->nullOnDelete();
            $t->string('wage_type_code', 20)->nullable()->after('surcharge_rule_id');
            $t->decimal('percentage', 5, 2)->nullable()->after('wage_type_code');
        });
    }

    public function down(): void {
        Schema::table('time_export_lines', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('surcharge_rule_id');
            $t->dropColumn(['wage_type_code', 'percentage']);
        });
    }
};
