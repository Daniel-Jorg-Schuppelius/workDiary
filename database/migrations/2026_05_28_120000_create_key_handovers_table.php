<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_28_120000_create_key_handovers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('key_handovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 16); // out|in
            $table->string('person_name', 180);
            $table->string('person_reference', 120)->nullable();
            $table->foreignId('handed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamp('expected_return_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('signature_token', 64)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'asset_id', 'occurred_at'], 'key_handovers_asset_occurred_idx');
            $table->index(['organization_id', 'direction'], 'key_handovers_org_direction_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('key_handovers');
    }
};
