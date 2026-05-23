<?php
/*
 * Created on   : Sun May 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_24_120100_create_protocol_items_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('protocol_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('protocol_id')->constrained('protocols')->cascadeOnDelete();
            $table->foreignId('parent_item_id')->nullable()->constrained('protocol_items')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('item_type', 40)->default('checklist');
            $table->string('label', 180);
            $table->text('description')->nullable();
            $table->boolean('required')->default(false);
            $table->json('value_json')->nullable();
            $table->string('result', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('measured_at')->nullable();
            $table->foreignId('measured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['protocol_id', 'sort_order'], 'protocol_items_order_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('protocol_items');
    }
};
