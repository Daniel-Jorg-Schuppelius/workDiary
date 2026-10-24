<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_171700_add_refunds_deletions_and_timezone_to_prestashop.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Shop-Spiegel (Feature 175): erstatteter Betrag aus dem Shop (null =
 * unbekannt) und der Zeitpunkt, zu dem der tägliche Abgleich die Bestellung
 * im Shop nicht mehr fand.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('prestashop_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('prestashop_orders', 'refunded_amount')) {
                $table->decimal('refunded_amount', 15, 2)->nullable()->after('net_amount');
            }
            if (! Schema::hasColumn('prestashop_orders', 'remote_deleted_at')) {
                $table->timestamp('remote_deleted_at')->nullable()->after('remote_modified_at');
            }
        });
        // Beim Prüfen der Verbindung aus PS_TIMEZONE gelesen; die Einstellung geht vor.
        Schema::table('prestashop_connections', function (Blueprint $table): void {
            if (! Schema::hasColumn('prestashop_connections', 'detected_timezone')) {
                $table->string('detected_timezone', 64)->nullable()->after('account_name');
            }
        });
    }

    public function down(): void {
        Schema::table('prestashop_orders', function (Blueprint $table): void {
            $table->dropColumn(['refunded_amount', 'remote_deleted_at']);
        });
        Schema::table('prestashop_connections', function (Blueprint $table): void {
            $table->dropColumn('detected_timezone');
        });
    }
};
