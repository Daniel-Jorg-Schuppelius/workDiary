<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_03_31_110200_add_lead_id_to_quotes_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Angebot aus dem Lead (MVP-1295): Herkunft für Verlauf und Auswertung.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->foreignId('lead_id')->nullable()->constrained('leads', indexName: 'quotes_lead_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropForeign('quotes_lead_fk');
            $table->dropColumn('lead_id');
        });
    }
};
