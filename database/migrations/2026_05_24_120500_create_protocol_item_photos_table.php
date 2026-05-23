<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_24_120500_create_protocol_item_photos_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('protocol_item_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('protocol_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->string('phase', 20);
            $table->string('caption', 180)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('taken_at')->nullable();
            $table->decimal('geo_lat', 9, 6)->nullable();
            $table->decimal('geo_lng', 9, 6)->nullable();
            $table->foreignId('captured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['protocol_item_id', 'attachment_id'], 'protocol_item_photos_pair_uniq');
            $table->index(['protocol_item_id', 'phase', 'sort_order'], 'protocol_item_photos_item_phase_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('protocol_item_photos');
    }
};
