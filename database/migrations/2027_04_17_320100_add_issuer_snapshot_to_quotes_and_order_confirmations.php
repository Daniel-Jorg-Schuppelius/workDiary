<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_320100_add_issuer_snapshot_to_quotes_and_order_confirmations.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausstellerangaben und Ansprechpartner von Angebot und Auftragsbestätigung,
 * eingefroren beim Versand bzw. bei der Bestätigung (MVP-1624) — wie die
 * Rechnung sie im Partei-Snapshot trägt. Bestandsbelege bleiben ohne und
 * zeigen weiter die aktuellen Stammdaten.
 */
return new class extends Migration {
    public function up(): void {
        foreach (['quotes', 'order_confirmations'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'issuer_snapshot')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->json('issuer_snapshot')->nullable();
                });
            }
        }
    }

    public function down(): void {
        foreach (['quotes', 'order_confirmations'] as $table) {
            if (Schema::hasColumn($table, 'issuer_snapshot')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('issuer_snapshot');
                });
            }
        }
    }
};
