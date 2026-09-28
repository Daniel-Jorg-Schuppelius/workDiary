<?php
/*
 * Created on   : Mon Sep 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_193000_add_acquisition_and_charging_to_vehicles.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-993: Anschaffungsdatum und Merkmal „extern aufladbar“ für die Bemessung der 1-%-Regel. */
return new class extends Migration {
    public function up(): void {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->date('acquired_on')->nullable()->after('commute_distance_km');
            $table->boolean('is_externally_chargeable')->default(false)->after('acquired_on');
        });
    }

    public function down(): void {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn(['acquired_on', 'is_externally_chargeable']);
        });
    }
};
