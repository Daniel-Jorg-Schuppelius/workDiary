<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_01_103100_extend_invoices_for_tax_model.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 066, MVP-162: belastbares Rechnungs-/Steuermodell —
 * Empfänger-/Verkäufer-Snapshots (beim Ausstellen eingefroren), mehrere
 * Steuersätze über Positionssätze + tax_breakdown, Zahlungsziel je
 * Rechnung, Stornorechnungs-Typ (Nummernkreis S).
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->json('party_snapshot')->nullable();   // seller+buyer, beim issue eingefroren
            $table->json('tax_breakdown')->nullable();    // [{rate, net, tax}]
            $table->unsignedSmallInteger('payment_terms_days')->nullable(); // null = Default 14
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            // Positionssteuersatz: NULL = Kopfsatz (Alt-Verhalten bleibt).
            $table->decimal('tax_rate', 5, 2)->nullable();
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn('tax_rate');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['party_snapshot', 'tax_breakdown', 'payment_terms_days']);
        });
    }
};
