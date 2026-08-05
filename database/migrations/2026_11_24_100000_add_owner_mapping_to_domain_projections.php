<?php
/*
 * Created on   : Wed Aug 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_24_100000_add_owner_mapping_to_domain_projections.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inhaber-Zuordnung von Domains (Feature 083): optionaler Endkunden-Bezug
 * analog zu Projekten (Domain eines Kunden gehört fachlich dessen Endkunden,
 * z. B. Reseller-Fall) sowie das Eigenbestand-Flag für Domains der eigenen
 * Firma — beides schließt sich mit einer Kundenzuordnung nicht aus bzw.
 * ersetzt sie (Eigenbestand ⇒ kein Kunde; Regel im MappingService).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('domain_projections', function (Blueprint $table): void {
            $table->foreignId('foreign_customer_id')->nullable()->after('customer_id')
                ->constrained('foreign_customers')->nullOnDelete();
            $table->boolean('is_own_holding')->default(false)->after('foreign_customer_id');
        });
    }

    public function down(): void {
        Schema::table('domain_projections', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('foreign_customer_id');
            $table->dropColumn('is_own_holding');
        });
    }
};
