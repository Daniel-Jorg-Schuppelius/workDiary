<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_25_130000_add_disposal_details_to_fixed_assets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-891: Abgangsart und Veräußerungserlös für die Abgangsbuchung. */
return new class extends Migration {
    public function up(): void {
        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->string('disposal_kind', 16)->nullable()->after('disposed_on');
            $table->decimal('disposal_proceeds_amount', 15, 2)->nullable()->after('disposal_kind');
        });
    }

    public function down(): void {
        Schema::table('fixed_assets', function (Blueprint $table): void {
            $table->dropColumn(['disposal_kind', 'disposal_proceeds_amount']);
        });
    }
};
