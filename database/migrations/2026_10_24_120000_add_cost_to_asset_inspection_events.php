<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_24_120000_add_cost_to_asset_inspection_events.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prüfkosten je Prüfereignis (MVP-291 „Prüfkosten"; Vollaudit 2026-07, M33) —
 * Grundlage der Kostenauswertung im Auditbericht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('asset_inspection_events', function (Blueprint $table): void {
            $table->decimal('cost', 10, 2)->nullable()->after('note');
        });
    }

    public function down(): void {
        Schema::table('asset_inspection_events', function (Blueprint $table): void {
            $table->dropColumn('cost');
        });
    }
};
