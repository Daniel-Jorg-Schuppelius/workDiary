<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_05_27_120000_create_sla_contracts_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sla_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('code', 60);
            $table->string('label', 180);
            $table->json('priority_table');
            $table->json('business_hours')->nullable();
            $table->json('escalation_chain')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'code'], 'sla_contracts_uniq_code');
            $table->index(['organization_id', 'customer_id', 'is_active'], 'sla_contracts_idx_lookup');
        });
    }

    public function down(): void {
        Schema::dropIfExists('sla_contracts');
    }
};
