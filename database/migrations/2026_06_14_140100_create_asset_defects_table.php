<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_140100_create_asset_defects_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('asset_defects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at');
            $table->string('severity', 20);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open');
            $table->boolean('blocks_usage')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'asset_id'], 'adft_idx_org_asset');
            $table->index(['asset_id', 'status'], 'adft_idx_asset_status');
            $table->index(['asset_id', 'blocks_usage', 'status'], 'adft_idx_block');
        });
    }

    public function down(): void {
        Schema::dropIfExists('asset_defects');
    }
};
