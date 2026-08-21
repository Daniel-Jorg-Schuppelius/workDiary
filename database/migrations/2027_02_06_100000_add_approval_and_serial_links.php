<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_06_100000_add_approval_and_serial_links.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zwei offene Punkte aus Welle 6 (Nutzerentscheid 2026-08-21):
 *
 * - **Feature 119:** Vier-Augen-Freigabe des Rundschreibens. Die Freigabe ist
 *   ein eigener Schritt mit eigener Person — deshalb eigene Spalten und nicht
 *   nur ein Zeitstempel am Versand.
 * - **Feature 118:** Optionale Verknüpfung einer Anlagen-Komponente mit der
 *   Seriennummer aus der eigenen Bestandsführung. `serial_no` bleibt als
 *   Freitext daneben stehen: Fremdteile haben keine Bestandsführung, und ihre
 *   Nummer soll trotzdem am Gerät stehen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('customer_circulars', function (Blueprint $table): void {
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        Schema::table('asset_components', function (Blueprint $table): void {
            $table->foreignId('stock_serial_id')->nullable()->after('serial_no')
                ->constrained('stock_serials')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('customer_circulars', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
        Schema::table('asset_components', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_serial_id');
        });
    }
};
