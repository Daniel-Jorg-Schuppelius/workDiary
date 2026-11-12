<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2027_04_17_180000_add_protocol_to_boq_item_progress.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LV-Fortschritt aus einem Protokoll (Feature 049, MVP-1582): die Quelle
 * „Protokoll“ verweist auf das Protokoll, aus dem die Meldung stammt.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('boq_item_progress', function (Blueprint $table): void {
            $table->foreignId('protocol_id')->nullable()->after('material_usage_id')
                ->constrained('protocols', indexName: 'boqp_protocol_fk')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('boq_item_progress', function (Blueprint $table): void {
            $table->dropForeign('boqp_protocol_fk');
            $table->dropColumn('protocol_id');
        });
    }
};
