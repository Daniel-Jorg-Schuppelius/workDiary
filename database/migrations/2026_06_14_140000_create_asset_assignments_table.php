<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_14_140000_create_asset_assignments_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('asset_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('diary_entry_id')->nullable()->constrained('diary_entries')->nullOnDelete();
            $table->timestamp('checked_out_at');
            $table->foreignId('checked_out_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expected_return_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition_out', 180)->nullable();
            $table->string('condition_in', 180)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'asset_id'], 'asgn_idx_org_asset');
            $table->index(['asset_id', 'returned_at'], 'asgn_idx_asset_open');
            $table->index(['assigned_to_user_id', 'returned_at'], 'asgn_idx_user_open');
            $table->index(['expected_return_at', 'returned_at'], 'asgn_idx_overdue');
        });
    }

    public function down(): void {
        Schema::dropIfExists('asset_assignments');
    }
};
