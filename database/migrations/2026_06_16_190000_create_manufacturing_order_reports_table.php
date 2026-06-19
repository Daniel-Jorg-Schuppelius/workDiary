<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_16_190000_create_manufacturing_order_reports_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teilrückmeldungen eines Fertigungsauftrags (Feature 047, MVP-065). Jede
 * Rückmeldung hält produzierte Menge, Gut-, Ausschuss- und Nacharbeitsmenge
 * sowie die ausführende Person und den Zeitpunkt fest. Offene Menge,
 * kumulierter Verbrauch und Restreservierung werden daraus berechnet.
 * Mandantengrenze transitiv über den Auftrag.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('manufacturing_order_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manufacturing_order_id')->constrained('manufacturing_orders')->cascadeOnDelete();
            $table->decimal('produced_qty', 18, 4)->default(0);
            $table->decimal('good_qty', 18, 4)->default(0);
            $table->decimal('scrap_qty', 18, 4)->default(0);
            $table->decimal('rework_qty', 18, 4)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->index('manufacturing_order_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('manufacturing_order_reports');
    }
};
