<?php
/*
 * Created on   : Sun Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_22_100000_add_invoice_texts_to_billing_transfers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rechnungstexte je Übergabe (MVP-491): Einleitung und Schlussbemerkung des
 * Belegs. Bisher gingen fest verdrahtete Übersetzungstexte auf die Kunden-
 * rechnung; jetzt entstehen sie beim Anlegen aus Kunden-/Org-Vorlage und sind
 * am Nachweis bearbeitbar, bevor er rausgeht.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('billing_transfers', function (Blueprint $table): void {
            $table->text('intro_text')->nullable()->after('correction_reason');
            $table->text('closing_text')->nullable()->after('intro_text');
        });
    }

    public function down(): void {
        Schema::table('billing_transfers', function (Blueprint $table): void {
            $table->dropColumn(['intro_text', 'closing_text']);
        });
    }
};
