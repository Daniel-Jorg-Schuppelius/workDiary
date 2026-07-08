<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_23_100000_create_sla_contract_quotas_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inklusivzeit-Kontingente eines SLA-Vertrags (Feature 010 → Rang 44): je Vertrag
 * ein Kontingent pro Periodentyp (month/quarter/year) mit inkludierten Minuten,
 * optionaler Überschreitungs-/Pauschalinfo (rein als Nachweis — die
 * Rechnungshoheit liegt extern) und einer Warnschwelle. `last_warned_period`
 * dedupliziert die Verbrauchswarnung genau einmal je Periode.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('sla_contract_quotas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations', indexName: 'slaquota_org_fk')->cascadeOnDelete();
            $table->foreignId('sla_contract_id')->constrained('sla_contracts', indexName: 'slaquota_contract_fk')->cascadeOnDelete();
            $table->string('period_kind', 16); // month|quarter|year
            $table->unsignedInteger('included_minutes');
            $table->decimal('overage_rate', 12, 2)->nullable(); // Nachweis, keine Berechnung
            $table->decimal('flat_fee', 12, 2)->nullable();     // Nachweis, keine Berechnung
            $table->unsignedTinyInteger('warn_threshold_pct')->default(80);
            $table->string('last_warned_period', 16)->nullable();
            $table->timestamps();

            $table->index('organization_id', 'slaquota_org_idx');
            $table->unique(['sla_contract_id', 'period_kind'], 'slaquota_contract_period_unique');
        });
    }

    public function down(): void {
        Schema::dropIfExists('sla_contract_quotas');
    }
};
