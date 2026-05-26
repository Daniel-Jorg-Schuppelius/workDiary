<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_26_130100_create_time_correction_items_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('time_correction_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('time_correction_request_id')
                ->constrained('time_correction_requests')
                ->cascadeOnDelete();
            $table->string('target_type', 40);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('action', 20);
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->index(['time_correction_request_id'], 'tci_request_idx');
            $table->index(['target_type', 'target_id'], 'tci_target_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('time_correction_items');
    }
};
