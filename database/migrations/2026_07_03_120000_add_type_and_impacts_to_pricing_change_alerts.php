<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_07_03_120000_add_type_and_impacts_to_pricing_change_alerts.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dokumentbezogene Abgleichwarnungen (Feature 050, MVP-094): Warnungen tragen
 * einen Typ (Marge/Verfügbarkeit) und die beim Auslösen betroffenen offenen
 * Vorgänge (Bestellungen, LV-Positionen, Fertigungsaufträge) als Snapshot.
 * Preisfelder werden nullable, da Verfügbarkeitswarnungen keine Marge tragen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('pricing_change_alerts', function (Blueprint $table): void {
            $table->string('type', 20)->default('margin')->after('supplier_id');
            $table->json('impacts')->nullable()->after('min_margin');
            $table->decimal('new_purchase_price', 18, 4)->nullable()->change();
            $table->decimal('sale_price', 18, 4)->nullable()->change();
            $table->decimal('new_margin', 8, 3)->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('pricing_change_alerts', function (Blueprint $table): void {
            $table->dropColumn(['type', 'impacts']);
        });
    }
};
