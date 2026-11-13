<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_18_110100_add_source_to_negative_stock_requests.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1679: Ein Negativbestand-Antrag kann aus einem Materialverbrauch
 * stammen. Die Quelle sagt der Freigabe, wohin die Buchung gehört; ein
 * geänderter oder gelöschter Verbrauch zieht seinen offenen Antrag zurück.
 */
return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('negative_stock_requests', 'source_type')) {
            return;
        }

        Schema::table('negative_stock_requests', function (Blueprint $table): void {
            $table->string('source_type', 100)->nullable()->after('entered_unit');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['source_type', 'source_id'], 'negative_stock_requests_source_idx');
        });
    }

    public function down(): void {
        if (! Schema::hasColumn('negative_stock_requests', 'source_type')) {
            return;
        }

        Schema::table('negative_stock_requests', function (Blueprint $table): void {
            $table->dropIndex('negative_stock_requests_source_idx');
            $table->dropColumn(['source_type', 'source_id']);
        });
    }
};
