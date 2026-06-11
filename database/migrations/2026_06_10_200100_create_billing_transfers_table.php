<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_10_200100_create_billing_transfers_table.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Übergabenachweise (Feature 045): zielbezogener Nachweis, welche Quellen
 * (Zeiten/Material) wann an welches Fakturierungsziel übergeben wurden.
 * Aufbewahrungspflichtig (GoBD) — daher SoftDeletes statt Hard-Delete und
 * purgeable_on_downgrade = false in config/plans.php.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('billing_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('channel', 16);                       // time|material
            $table->string('target', 16);                        // lexoffice|datev|file
            $table->string('status', 16)->default('draft');      // draft|confirmed|transferred|failed|voided
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedInteger('position_count')->default(0);
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('total_quantity', 10, 2)->nullable();
            $table->string('payload_hash', 64);                  // SHA-256 über kanonisches Positions-JSON
            $table->foreignId('external_reference_id')->nullable()->constrained('external_references')->nullOnDelete();
            $table->string('file_path')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('transferred_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status'], 'bt_org_status_idx');
            $table->index(['organization_id', 'customer_id'], 'bt_org_customer_idx');
            $table->index(['channel', 'status'], 'bt_channel_status_idx');
        });
    }

    public function down(): void {
        Schema::dropIfExists('billing_transfers');
    }
};
