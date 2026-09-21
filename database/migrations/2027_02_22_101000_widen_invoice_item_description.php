<?php
/*
 * Created on   : Mon Sep 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_02_22_101000_widen_invoice_item_description.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI-Fuzz 2026-09-21: Positionstexte kommen aus Zeiten (bis 500 Zeichen),
 * Angebotspositionen (500), Vorlagen wiederkehrender Rechnungen (TEXT) und
 * dem Positionsdialog (1000) — varchar(255) ließ den Rechnungslauf mit 1406
 * abbrechen. Kürzen käme einer Änderung des Rechnungstexts gleich.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->text('description')->change();
        });
    }

    public function down(): void {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->string('description', 255)->change();
        });
    }
};
