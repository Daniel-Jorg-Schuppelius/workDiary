<?php
/*
 * Created on   : Sun Aug 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_11_21_100000_add_correction_link_to_billing_transfers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Korrektur-Übergaben (MVP-490): ein übergebener Nachweis bleibt
 * unveränderlich — eine Korrektur entsteht als EIGENER Nachweis, der auf den
 * ursprünglichen zeigt. Damit bleibt die Kette lückenlos nachvollziehbar,
 * statt einen ausgelieferten Beleg still zurückzudrehen.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('billing_transfers', function (Blueprint $table): void {
            $table->foreignId('corrects_transfer_id')->nullable()->after('target')
                ->constrained('billing_transfers')->nullOnDelete();
            $table->string('correction_reason', 500)->nullable()->after('corrects_transfer_id');

            $table->index('corrects_transfer_id', 'bt_corrects_idx');
        });
    }

    public function down(): void {
        Schema::table('billing_transfers', function (Blueprint $table): void {
            $table->dropForeign(['corrects_transfer_id']);
            $table->dropIndex('bt_corrects_idx');
            $table->dropColumn(['corrects_transfer_id', 'correction_reason']);
        });
    }
};
