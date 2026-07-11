<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_10_04_140000_add_settlement_to_invoice_items.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Feature 066 (Belegkette): Anrechnung von Abschlags-/Anzahlungs-
        // rechnungen in der Schlussrechnung (§ 14 Abs. 5 UStG). Die
        // Absetzungsposition der Schlussrechnung referenziert die
        // angerechnete Abschlagsrechnung — die Abschlagsrechnung selbst
        // bleibt unverändert (Ausstellungs-Unveränderlichkeit); "offen" ist
        // eine Abfrage über nicht-stornierte Schlussrechnungen.
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->foreignId('settled_invoice_id')
                ->nullable()
                ->constrained('invoices', indexName: 'ii_settled_invoice_fk')
                ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('settled_invoice_id');
        });
    }
};
