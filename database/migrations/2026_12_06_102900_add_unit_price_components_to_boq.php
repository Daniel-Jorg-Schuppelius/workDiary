<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_12_06_102900_add_unit_price_components_to_boq.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feature 108, MVP-567: Aufgliederung des Einheitspreises. Der Auftraggeber gibt
 * im LV bis zu sechs Anteile mit Bezeichnung und Kategorie vor (fachlich das
 * VHB-Formblatt 223); der Bieter liefert sie je Position zurück. Ihre Summe muss
 * den Einheitspreis ergeben.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('bill_of_quantities', function (Blueprint $table): void {
            $table->json('up_components')->nullable()->after('gaeb_version'); // Labels + Kategorie je Anteil
        });

        Schema::table('boq_items', function (Blueprint $table): void {
            $table->json('unit_price_components')->nullable()->after('unit_price'); // Beträge je Anteil
        });
    }

    public function down(): void {
        Schema::table('bill_of_quantities', function (Blueprint $table): void {
            $table->dropColumn('up_components');
        });

        Schema::table('boq_items', function (Blueprint $table): void {
            $table->dropColumn('unit_price_components');
        });
    }
};
