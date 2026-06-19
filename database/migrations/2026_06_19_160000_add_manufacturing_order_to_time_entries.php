<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_19_160000_add_manufacturing_order_to_time_entries.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpft Zeitbuchungen mit einem Fertigungsauftrag (Feature 047/048, E7):
 * Grundlage für die Lohnkosten in der Nachkalkulation.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->foreignId('manufacturing_order_id')->nullable()->constrained('manufacturing_orders')->nullOnDelete();
            $table->index('manufacturing_order_id');
        });
    }

    public function down(): void {
        Schema::table('time_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manufacturing_order_id');
        });
    }
};
