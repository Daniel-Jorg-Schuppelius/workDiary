<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_09_24_100000_add_sla_contract_id_to_assets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Direkte SLA-Vertrags-Zuordnung eines Assets (Feature 027 → Rang 48) als
 * Override der Kunden-/Default-Auflösung. Nullable; bleibt leer, greift die
 * normale Auflösung über den Kunden ({@see \App\Services\ServiceTicket\SlaTimer::resolveContract}).
 * `nullOnDelete`: verschwindet der Vertrag, fällt das Asset auf die Auflösung zurück.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('assets', function (Blueprint $table): void {
            $table->foreignId('sla_contract_id')->nullable()
                ->constrained('sla_contracts', indexName: 'asset_sla_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sla_contract_id');
        });
    }
};
