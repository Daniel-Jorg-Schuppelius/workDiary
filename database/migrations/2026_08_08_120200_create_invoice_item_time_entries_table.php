<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_08_08_120200_create_invoice_item_time_entries_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: eine Rechnungsposition kann durch die Taktungs-/Zusammenfassungs-Logik
 * mehrere Zeiteinträge bündeln. Die Einzel-FK invoice_items.time_entry_id bleibt
 * bestehen (gefüllt mit dem ersten Eintrag des Blocks).
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('invoice_item_time_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('time_entry_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['invoice_item_id', 'time_entry_id'], 'iite_item_entry_unique');
            $table->index('time_entry_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('invoice_item_time_entries');
    }
};
