<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_101900_add_components_to_vacation_entitlements.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-535 (Feature 103, Q1-Drittabgleich): getrennte Anspruchskomponenten —
 * Tarifurlaub (`entitled_days`) bleibt, dazu SGB-IX-Zusatzurlaub für
 * schwerbehinderte Menschen und sonstige Ansprüche (Q1 S. 70/90).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('vacation_entitlements', function (Blueprint $table): void {
            $table->decimal('severely_disabled_days', 5, 1)->default(0)->after('entitled_days');
            $table->decimal('other_days', 5, 1)->default(0)->after('severely_disabled_days');
        });
    }

    public function down(): void {
        Schema::table('vacation_entitlements', function (Blueprint $table): void {
            $table->dropColumn(['severely_disabled_days', 'other_days']);
        });
    }
};
