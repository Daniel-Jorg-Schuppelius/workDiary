<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_28_211000_add_recorded_at_to_attendances.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MVP-1015: Zeitpunkt der ursprünglichen Erfassung (MiLoG § 17) — Stempelmoment oder Quellangabe des Imports. */
return new class extends Migration {
    public function up(): void {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->timestamp('recorded_at')->nullable()->after('source');
        });
    }

    public function down(): void {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn('recorded_at');
        });
    }
};
