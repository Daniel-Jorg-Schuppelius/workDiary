<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_09_100000_add_material_fields_to_billing_transfer_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialpositions-Snapshot (Feature 045, Akzeptanzkriterium „Materialpositionen
 * enthalten nachvollziehbare Menge, Einheit, Preis, Steuerbehandlung und
 * DATEV-Kostenposition"): der Übergabe-Snapshot hielt bisher nur Menge und
 * Betrag fest. Einheit, Einzelpreis, Steuersatz und die optionale
 * DATEV-Kostenposition werden ergänzt, damit der Nachweis die Materialzeile zum
 * Übergabezeitpunkt vollständig dokumentiert (Zeit-Positionen lassen die Felder
 * leer). Kind-Tabelle ohne eigene organization_id — Mandantengrenze transitiv
 * über billing_transfers (siehe docs/security/tenant-audit-2026.md).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('billing_transfer_items', function (Blueprint $table): void {
            $table->string('unit', 20)->nullable()->after('quantity');
            $table->decimal('unit_price', 12, 4)->nullable()->after('unit');
            $table->decimal('tax_rate', 5, 2)->nullable()->after('unit_price');
            $table->string('cost_position', 60)->nullable()->after('tax_rate');
        });
    }

    public function down(): void {
        Schema::table('billing_transfer_items', function (Blueprint $table): void {
            $table->dropColumn(['unit', 'unit_price', 'tax_rate', 'cost_position']);
        });
    }
};
