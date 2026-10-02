<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_01_100000_add_labour_share_to_document_lines.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MVP-1053: Anteil der Arbeits-, Maschinen- und Fahrtkosten je Belegposition
 * (§ 35a EStG) und die Überschreibung des Ausweises am Beleg. `null` heißt
 * „nicht bestimmt“ und zählt nicht zum Ausweis.
 */
return new class extends Migration {
    public function up(): void {
        foreach (['invoice_items', 'quote_items', 'invoice_schedule_items'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->decimal('labour_share_percent', 5, 2)->nullable();
            });
        }
        foreach (['invoices', 'quotes'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->boolean('is_labour_cost_disclosed')->nullable();
            });
        }
    }

    public function down(): void {
        foreach (['invoice_items', 'quote_items', 'invoice_schedule_items'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('labour_share_percent');
            });
        }
        foreach (['invoices', 'quotes'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('is_labour_cost_disclosed');
            });
        }
    }
};
