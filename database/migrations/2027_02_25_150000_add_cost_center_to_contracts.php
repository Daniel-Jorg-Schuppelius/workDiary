<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_25_150000_add_cost_center_to_contracts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-894: Vertragswert je Kostenstelle. */
return new class extends Migration {
    public function up(): void {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->foreignId('cost_center_id')->nullable()->after('value_period')->constrained('cost_centers')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cost_center_id');
        });
    }
};
